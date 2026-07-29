<?php

namespace App\Services;

use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use ZipArchive;

class WeavingExcelBatchExporter
{
    private const CHUNK_SIZE = 20;
    private const MAX_ORDERS = 1000;

    private $templateWriter;
    private $templateFactory;

    public function __construct(
        GoogleSheetWeavingTemplateWriter $templateWriter,
        WeavingExcelTemplateFactory $templateFactory
    )
    {
        $this->templateWriter = $templateWriter;
        $this->templateFactory = $templateFactory;
    }

    public function start(array $orders, array $filters = []): array
    {
        $orders = collect($orders)
            ->map(function (array $order) {
                return [
                    'production_order' => trim((string) ($order['production_order'] ?? '')),
                    'customer' => trim((string) ($order['customer'] ?? '')) ?: 'CHUA-XAC-DINH',
                    'status' => 'pending',
                    'error' => null,
                    'file' => null,
                ];
            })
            ->filter(fn (array $order) => $order['production_order'] !== '')
            ->unique('production_order')
            ->values();

        if ($orders->isEmpty()) {
            throw new RuntimeException('Không có lệnh sản xuất để xuất.');
        }
        if ($orders->count() > self::MAX_ORDERS) {
            throw new RuntimeException('Mỗi lần chỉ xuất tối đa ' . self::MAX_ORDERS . ' lệnh.');
        }

        $this->cleanupExpired();
        $token = bin2hex(random_bytes(20));
        $directory = $this->directory($token);
        $this->makeDirectory($directory);

        $customers = $orders->pluck('customer')->filter()->unique()->values();
        $customerLabel = $customers->count() === 1 ? $customers->first() : 'NHIEU-KHACH';
        $zipName = sprintf(
            'LENH-DET_%s_%s.zip',
            $this->safeName($customerLabel, 50),
            now('Asia/Ho_Chi_Minh')->format('Ymd_His')
        );

        $manifest = [
            'token' => $token,
            'status' => 'processing',
            'total' => $orders->count(),
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'orders' => $orders->all(),
            'filters' => $filters,
            'zip_name' => $zipName,
            'created_at' => now('Asia/Ho_Chi_Minh')->toIso8601String(),
            'updated_at' => now('Asia/Ho_Chi_Minh')->toIso8601String(),
        ];
        $this->saveManifest($manifest);

        return $this->publicManifest($manifest);
    }

    public function pending(string $token): array
    {
        return collect($this->manifest($token)['orders'])
            ->where('status', 'pending')
            ->take(self::CHUNK_SIZE)
            ->values()
            ->all();
    }

    public function append(string $token, string $productionOrder, array $plan): string
    {
        $results = $this->appendMany($token, [[
            'production_order' => $productionOrder,
            'plan' => $plan,
        ]]);
        $result = $results[0];
        if ($result['status'] !== 'success') {
            throw new RuntimeException($result['error']);
        }

        return $result['file'];
    }

    public function single(string $productionOrder, array $plan): array
    {
        $directory = storage_path('app/weaving-downloads');
        $this->makeDirectory($directory);
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*.xlsx') ?: [] as $oldFile) {
            if (is_file($oldFile) && filemtime($oldFile) < time() - 3600) {
                @unlink($oldFile);
            }
        }

        $itemCode = trim((string) ($plan['order']['item_code'] ?? ''));
        $name = 'LENH-DET_' . $this->safeName($productionOrder, 45);
        if ($itemCode !== '') {
            $name .= '_' . $this->safeName($itemCode, 45);
        }
        $name .= '.xlsx';
        $path = $directory . DIRECTORY_SEPARATOR . bin2hex(random_bytes(12)) . '.xlsx';
        $this->writeWorkbook($plan, $path);

        return ['path' => $path, 'name' => $name];
    }

    public function appendMany(string $token, array $exports): array
    {
        $manifest = $this->manifest($token);
        $zipPath = $this->zipPath($manifest);
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            throw new RuntimeException('Không mở được file ZIP.');
        }

        $results = [];
        $tempPaths = [];
        foreach (array_values($exports) as $index => $export) {
            $productionOrder = trim((string) ($export['production_order'] ?? ''));
            $plan = (array) ($export['plan'] ?? []);
            $tempPath = $this->directory($token) . DIRECTORY_SEPARATOR . 'current-' . $index . '.xlsx';
            try {
                $this->writeWorkbook($plan, $tempPath);
                $tempPaths[] = $tempPath;

                $customer = trim((string) ($plan['order']['customer'] ?? '')) ?: 'CHUA-XAC-DINH';
                $itemCode = trim((string) ($plan['order']['item_code'] ?? ''));
                $baseName = 'LENH-DET_' . $this->safeName($productionOrder, 45);
                if ($itemCode !== '') {
                    $baseName .= '_' . $this->safeName($itemCode, 45);
                }
                $zipFolder = $this->safeName($customer, 70);
                $zipEntry = $zipFolder . '/' . $baseName . '.xlsx';
                $suffix = 2;
                while ($zip->locateName($zipEntry) !== false) {
                    $zipEntry = $zipFolder . '/' . $baseName . '-' . $suffix . '.xlsx';
                    $suffix++;
                }
                if (!$zip->addFile($tempPath, $zipEntry)) {
                    throw new RuntimeException('Không thêm được file Excel vào ZIP.');
                }
                $results[] = [
                    'production_order' => $productionOrder,
                    'status' => 'success',
                    'file' => $zipEntry,
                    'error' => null,
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'production_order' => $productionOrder,
                    'status' => 'failed',
                    'file' => null,
                    'error' => $e->getMessage(),
                ];
            }
        }
        $zip->close();
        foreach ($tempPaths as $tempPath) {
            @unlink($tempPath);
        }

        return $results;
    }

    private function writeWorkbook(array $plan, string $path): void
    {
        $spreadsheet = $this->templateFactory->create();
        $sheet = $spreadsheet->getSheetByName(GoogleSheetWeavingTemplateWriter::SHEET_NAME);
        if (!$sheet) {
            $spreadsheet->disconnectWorksheets();
            throw new RuntimeException('Mẫu Excel không có tab LENH_DET.');
        }
        foreach ($this->templateWriter->buildRanges($plan) as $rangeData) {
            $startCell = explode(':', $rangeData['range'], 2)[0];
            $sheet->fromArray($rangeData['values'], null, $startCell, true);
        }
        $sheet->setSelectedCell('A1');
        $spreadsheet->setActiveSheetIndex($spreadsheet->getIndex($sheet));

        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();
    }

    public function mark(string $token, string $productionOrder, string $status, ?string $file = null, ?string $error = null): array
    {
        $manifest = $this->manifest($token);
        foreach ($manifest['orders'] as &$order) {
            if ($order['production_order'] !== $productionOrder || $order['status'] !== 'pending') {
                continue;
            }
            $order['status'] = $status;
            $order['file'] = $file;
            $order['error'] = $error;
            break;
        }
        unset($order);

        $manifest['processed'] = collect($manifest['orders'])->whereIn('status', ['success', 'failed'])->count();
        $manifest['success'] = collect($manifest['orders'])->where('status', 'success')->count();
        $manifest['failed'] = collect($manifest['orders'])->where('status', 'failed')->count();
        $manifest['updated_at'] = now('Asia/Ho_Chi_Minh')->toIso8601String();

        if ($manifest['processed'] >= $manifest['total']) {
            $manifest['status'] = 'completed';
            $this->addErrorReport($manifest);
        }
        $this->saveManifest($manifest);

        return $this->publicManifest($manifest);
    }

    public function status(string $token): array
    {
        return $this->publicManifest($this->manifest($token));
    }

    public function download(string $token): array
    {
        $manifest = $this->manifest($token);
        if ($manifest['status'] !== 'completed') {
            throw new RuntimeException('Lô xuất chưa hoàn tất.');
        }
        $path = $this->zipPath($manifest);
        if (!is_file($path)) {
            throw new RuntimeException('Không tìm thấy file ZIP.');
        }

        return ['path' => $path, 'name' => $manifest['zip_name']];
    }

    private function addErrorReport(array $manifest): void
    {
        $errors = collect($manifest['orders'])->where('status', 'failed')->values();
        if ($errors->isEmpty()) {
            return;
        }

        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('LOI');
        $sheet->fromArray([['Lệnh sản xuất', 'Khách hàng', 'Lỗi']], null, 'A1');
        $sheet->fromArray($errors->map(fn (array $order) => [
            $order['production_order'],
            $order['customer'],
            $order['error'],
        ])->all(), null, 'A2');
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(25);
        $sheet->getColumnDimension('C')->setWidth(80);

        $tempPath = $this->directory($manifest['token']) . DIRECTORY_SEPARATOR . 'errors.xlsx';
        $writer = new Xlsx($book);
        $writer->save($tempPath);
        $book->disconnectWorksheets();

        $zip = new ZipArchive();
        if ($zip->open($this->zipPath($manifest), ZipArchive::CREATE) === true) {
            $zip->addFile($tempPath, 'BAO-CAO-LOI.xlsx');
            $zip->close();
        }
        @unlink($tempPath);
    }

    private function publicManifest(array $manifest): array
    {
        return [
            'token' => $manifest['token'],
            'status' => $manifest['status'],
            'total' => (int) $manifest['total'],
            'processed' => (int) $manifest['processed'],
            'success' => (int) $manifest['success'],
            'failed' => (int) $manifest['failed'],
            'errors' => collect($manifest['orders'])
                ->where('status', 'failed')
                ->map(fn (array $order) => [
                    'production_order' => $order['production_order'],
                    'customer' => $order['customer'],
                    'message' => $order['error'],
                ])
                ->values()
                ->all(),
            'download_url' => $manifest['status'] === 'completed'
                ? url('/api/lenh-det/batch-exports/' . $manifest['token'] . '/download')
                : null,
        ];
    }

    private function manifest(string $token): array
    {
        $path = $this->manifestPath($token);
        if (!is_file($path)) {
            throw new RuntimeException('Không tìm thấy lô xuất Excel.');
        }
        $manifest = json_decode((string) file_get_contents($path), true);
        if (!is_array($manifest)) {
            throw new RuntimeException('Dữ liệu lô xuất Excel không hợp lệ.');
        }

        return $manifest;
    }

    private function saveManifest(array $manifest): void
    {
        $json = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false || file_put_contents($this->manifestPath($manifest['token']), $json, LOCK_EX) === false) {
            throw new RuntimeException('Không lưu được tiến độ xuất Excel.');
        }
    }

    private function manifestPath(string $token): string
    {
        return $this->directory($token) . DIRECTORY_SEPARATOR . 'manifest.json';
    }

    private function zipPath(array $manifest): string
    {
        return $this->directory($manifest['token']) . DIRECTORY_SEPARATOR . $manifest['zip_name'];
    }

    private function directory(string $token): string
    {
        if (!preg_match('/^[a-f0-9]{40}$/', $token)) {
            throw new RuntimeException('Mã lô xuất Excel không hợp lệ.');
        }

        return storage_path('app/weaving-exports/' . $token);
    }

    private function makeDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Không tạo được thư mục xuất Excel.');
        }
    }

    private function safeName(string $value, int $maxLength): string
    {
        $value = strtoupper(Str::ascii(trim($value)));
        $value = trim((string) preg_replace('/[^A-Z0-9._-]+/', '-', $value), '-_.');

        return mb_substr($value !== '' ? $value : 'CHUA-XAC-DINH', 0, $maxLength);
    }

    private function cleanupExpired(): void
    {
        $root = storage_path('app/weaving-exports');
        if (!is_dir($root)) {
            return;
        }
        $cutoff = time() - 86400;
        foreach (glob($root . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $directory) {
            if (filemtime($directory) >= $cutoff || strpos(realpath($directory), realpath($root) . DIRECTORY_SEPARATOR) !== 0) {
                continue;
            }
            foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($directory);
        }
    }
}
