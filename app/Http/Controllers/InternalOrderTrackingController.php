<?php

namespace App\Http\Controllers;

use App\Exceptions\InternalGoogleSyncBusyException;
use App\Exceptions\InternalGoogleSyncSourceException;
use App\Models\InternalOrderTrackingRow;
use App\Services\GoogleSheetReader;
use App\Services\InternalGoogleSyncContext;
use App\Services\InternalGoogleSyncCoordinator;
use App\Services\Panel\PanelRuleRepository;
use Carbon\Carbon;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class InternalOrderTrackingController extends Controller
{
    private $panelRules;

    public function __construct(PanelRuleRepository $panelRules)
    {
        $this->panelRules = $panelRules;
    }

    public function index()
    {
        return view('client.internal-order-tracking');
    }

    public function data(Request $request)
    {
        $query = InternalOrderTrackingRow::query()->where('is_active', true);
        $sheet = strtoupper(trim((string) $request->query('sheet', 'A')));
        $keyword = trim((string) $request->query('keyword', ''));
        $status = trim((string) $request->query('status', ''));

        if (in_array($sheet, ['A', 'B'], true)) {
            $query->where('sheet_code', $sheet);
        }

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('item_code', 'like', '%' . $keyword . '%')
                    ->orWhere('order_number', 'like', '%' . $keyword . '%')
                    ->orWhere('panel', 'like', '%' . $keyword . '%')
                    ->orWhere('voucher_number', 'like', '%' . $keyword . '%')
                    ->orWhere('fabric_color', 'like', '%' . $keyword . '%')
                    ->orWhere('logo_color', 'like', '%' . $keyword . '%')
                    ->orWhere('size', 'like', '%' . $keyword . '%');
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('delivery_date', '>=', $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('delivery_date', '<=', $request->query('to_date'));
        }

        $summaryQuery = clone $query;
        $limit = min(max((int) $request->query('limit', 500), 1), 2000);
        $rows = $query
            ->orderByRaw('delivery_date IS NULL')
            ->orderBy('delivery_date')
            ->orderBy('item_code')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $rows,
            'summary' => [
                'row_count' => (clone $summaryQuery)->count(),
                'order_quantity' => (float) (clone $summaryQuery)->sum('order_quantity'),
                'received_quantity' => (float) (clone $summaryQuery)->sum('received_quantity'),
                'remaining_quantity' => (float) (clone $summaryQuery)->sum('remaining_quantity'),
                'late_count' => (clone $summaryQuery)
                    ->whereNotNull('delivery_date')
                    ->whereDate('delivery_date', '<', now()->format('Y-m-d'))
                    ->where('remaining_quantity', '>', 0)
                    ->count(),
            ],
        ]);
    }

    public function import(Request $request)
    {
        $data = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:20480',
        ]);

        $reader = IOFactory::createReaderForFile($data['file']->getRealPath());
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($data['file']->getRealPath());
        try {
            $sheets = $this->resolveOrderSheets($spreadsheet->getAllSheets());
            if (count($sheets) < 1) {
                throw ValidationException::withMessages(['file' => 'File không có sheet đơn hàng để nhập.']);
            }

            $batch = (string) Str::uuid();
            $result = ['created' => 0, 'updated' => 0, 'archived' => 0, 'sheets' => []];

            DB::connection('internal')->transaction(function () use ($sheets, $batch, &$result) {
                foreach ($sheets as $index => $worksheet) {
                    $sheetCode = $this->sheetCode($worksheet->getTitle(), $index);
                    $parsed = $this->parseWorksheet($worksheet, $sheetCode, $batch);
                    if (!$parsed) {
                        throw ValidationException::withMessages([
                            'file' => 'Sheet ' . $worksheet->getTitle() . ' không có dòng PS/PANEL hợp lệ. Dữ liệu cũ được giữ nguyên.',
                        ]);
                    }

                    $activeKeys = array_column($parsed, 'row_key');
                    $existingRows = InternalOrderTrackingRow::query()
                        ->where('sheet_code', $sheetCode)
                        ->whereIn('row_key', $activeKeys)
                        ->get()
                        ->keyBy('row_key');
                    $receiptTotals = collect();
                    if ($existingRows->isNotEmpty()) {
                        $receiptTotals = DB::connection('internal')->table('internal_panel_receipt_lines')
                            ->select('order_tracking_row_id', DB::raw('SUM(received_quantity) AS total_received'))
                            ->whereIn('order_tracking_row_id', $existingRows->pluck('id')->all())
                            ->groupBy('order_tracking_row_id')
                            ->pluck('total_received', 'order_tracking_row_id');
                    }

                    foreach ($parsed as $row) {
                        $existing = $existingRows->get($row['row_key']);
                        if ($row['panel'] !== '') {
                            $received = $existing ? (float) ($receiptTotals[$existing->id] ?? 0) : 0.0;
                            $row = $this->withReceiptProgress($row, $received);
                        }

                        InternalOrderTrackingRow::query()->updateOrCreate(
                            ['sheet_code' => $sheetCode, 'row_key' => $row['row_key']],
                            $row
                        );

                        $existing ? $result['updated']++ : $result['created']++;
                    }

                    $archiveQuery = InternalOrderTrackingRow::query()
                        ->where('sheet_code', $sheetCode)
                        ->where('is_active', true);

                    $archiveQuery->whereNotIn('row_key', $activeKeys);

                    $archived = $archiveQuery->update(['is_active' => false]);
                    $result['archived'] += $archived;
                    $result['sheets'][$sheetCode] = [
                        'name' => $worksheet->getTitle(),
                        'rows' => count($parsed),
                    ];
                }
            });
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        return response()->json([
            'message' => 'Đã đồng bộ file Excel vào database nội bộ.',
            'data' => $result,
        ]);
    }

    public function syncGoogle(GoogleSheetReader $reader, InternalGoogleSyncCoordinator $sync)
    {
        $spreadsheetId = trim((string) config('internal_orders.spreadsheet_id'));
        $sheetName = trim((string) config('internal_orders.sheet_name', 'DON_HANG_TACH'));
        $columns = trim((string) config('internal_orders.sheet_columns', 'A:AZ'));

        if (!$reader->isConfigured()) {
            return response()->json([
                'message' => 'Chưa cấu hình service account để đọc Google Sheet.',
            ], 422);
        }

        try {
            $result = $sync->run(
                'reference',
                'orders-don-hang-tach',
                function (InternalGoogleSyncContext $context) use ($reader, $spreadsheetId, $sheetName, $columns) {
                    return $this->syncGoogleSource($reader, $context, $spreadsheetId, $sheetName, $columns);
                },
                (int) config('internal_sync.reference_lock_seconds', 900)
            );

            return response()->json([
                'message' => 'Đã đồng bộ Google Sheet vào database nội bộ.',
                'data' => $result,
            ]);
        } catch (InternalGoogleSyncBusyException $error) {
            return response()->json(['message' => $error->getMessage()], 409);
        } catch (InternalGoogleSyncSourceException $error) {
            return response()->json(['message' => $error->getMessage()], $error->statusCode());
        } catch (\Throwable $error) {
            report($error);

            return response()->json([
                'message' => 'Đồng bộ ' . $sheetName . ' thất bại: ' . $error->getMessage(),
            ], 500);
        }
    }

    private function syncGoogleSource(
        GoogleSheetReader $reader,
        InternalGoogleSyncContext $context,
        string $spreadsheetId,
        string $sheetName,
        string $columns
    ): array {
        try {
            $values = $reader->values($spreadsheetId, $sheetName, $columns);
        } catch (GoogleServiceException $error) {
            if ((int) $error->getCode() === 403) {
                $email = $reader->serviceAccountEmail();
                $suffix = $email !== '' ? ' (' . $email . ')' : '';
                throw new InternalGoogleSyncSourceException(
                    'File Google Sheet chưa chia sẻ quyền xem cho service account' . $suffix . '.',
                    403
                );
            }
            throw $error;
        }

        if (count($values) < 2) {
            throw new InternalGoogleSyncSourceException(
                'Tab ' . $sheetName . ' không có dữ liệu hợp lệ.',
                422
            );
        }

        $spreadsheet = new Spreadsheet();
        try {
            $worksheet = $spreadsheet->getActiveSheet();
            $worksheet->setTitle(substr($sheetName, 0, 31));
            $worksheet->fromArray($values, null, 'A1', true);
            $batch = (string) Str::uuid();
            $parsed = $this->parseWorksheet($worksheet, 'A', $batch);
            if (!$parsed) {
                throw new InternalGoogleSyncSourceException(
                    'Tab ' . $sheetName . ' không có dòng PS/PANEL hợp lệ; dữ liệu cũ được giữ nguyên.',
                    422
                );
            }

            $context->checkpoint(count($values));
            $result = $this->persistGoogleRows($parsed, 'A', $sheetName);
            $result['processed'] = count($parsed);
            $result['sheet'] = $sheetName;

            return $result;
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    private function persistGoogleRows(array $parsed, string $sheetCode, string $sheetName): array
    {
        $result = ['created' => 0, 'updated' => 0, 'archived' => 0, 'sheets' => []];
        $activeKeys = array_column($parsed, 'row_key');

        DB::connection('internal')->transaction(function () use (
            $parsed,
            $sheetCode,
            $sheetName,
            $activeKeys,
            &$result
        ) {
            $existingRows = InternalOrderTrackingRow::query()
                ->where('sheet_code', $sheetCode)
                ->whereIn('row_key', $activeKeys)
                ->get()
                ->keyBy('row_key');

            $receiptTotals = collect();
            if ($existingRows->isNotEmpty()) {
                $receiptTotals = DB::connection('internal')
                    ->table('internal_panel_receipt_lines')
                    ->select('order_tracking_row_id', DB::raw('SUM(received_quantity) AS total_received'))
                    ->whereIn('order_tracking_row_id', $existingRows->pluck('id')->all())
                    ->groupBy('order_tracking_row_id')
                    ->pluck('total_received', 'order_tracking_row_id');
            }

            foreach ($parsed as $row) {
                $existing = $existingRows->get($row['row_key']);
                if ($row['panel'] !== '') {
                    $received = $existing ? (float) ($receiptTotals[$existing->id] ?? 0) : 0.0;
                    $row = $this->withReceiptProgress($row, $received);
                }

                InternalOrderTrackingRow::query()->updateOrCreate(
                    ['sheet_code' => $sheetCode, 'row_key' => $row['row_key']],
                    $row
                );
                $existing ? $result['updated']++ : $result['created']++;
            }

            $result['archived'] = InternalOrderTrackingRow::query()
                ->where('sheet_code', $sheetCode)
                ->where('is_active', true)
                ->whereNotIn('row_key', $activeKeys)
                ->update(['is_active' => false]);
            $result['sheets'][$sheetCode] = [
                'name' => $sheetName,
                'rows' => count($parsed),
            ];
        });

        return $result;
    }

    private function resolveOrderSheets(array $sheets): array
    {
        $resolved = [];
        foreach (['A', 'B'] as $code) {
            foreach ($sheets as $sheet) {
                $title = strtoupper(trim($sheet->getTitle()));
                if ($title === $code || $title === 'SHEET ' . $code || $title === 'SHEET_' . $code) {
                    $resolved[$code] = $sheet;
                    break;
                }
            }
        }

        if ($resolved) {
            return array_values($resolved);
        }

        return array_slice($sheets, 0, 2);
    }

    private function sheetCode(string $title, int $index): string
    {
        $title = strtoupper(trim($title));
        if (in_array($title, ['B', 'SHEET B', 'SHEET_B'], true)) {
            return 'B';
        }
        if (in_array($title, ['A', 'SHEET A', 'SHEET_A'], true)) {
            return 'A';
        }
        return $index === 0 ? 'A' : 'B';
    }

    private function parseWorksheet($worksheet, string $sheetCode, string $batch): array
    {
        $highestRow = $worksheet->getHighestDataRow();
        $highestColumn = $worksheet->getHighestDataColumn();
        $matrix = $worksheet->rangeToArray("A1:{$highestColumn}{$highestRow}", null, true, true, false);
        $this->expandMergedHeaders($worksheet, $matrix);
        $headerEnd = $this->detectHeaderEnd($matrix);
        $headers = $this->buildHeaders($matrix, $headerEnd);
        $rows = [];
        $occurrences = [];

        for ($rowIndex = $headerEnd + 1; $rowIndex < count($matrix); $rowIndex++) {
            $values = $matrix[$rowIndex];
            $normalized = $this->normalizeRow($headers, $values);

            if (!$this->hasOrderData($normalized)) {
                continue;
            }

            $orderQuantity = $this->number($this->pick($normalized, [
                'so luong dat hang', 'quantity order', 'quantityorder', 'sl dat', 'sl don hang', 'q ty don hang',
            ]));
            $quantity = $this->number($this->pick($normalized, [
                'quantity', 'qty', 'so luong', 'quantity dat', 'sl panel',
            ]));
            $panel = $this->normalizePanel($this->pick($normalized, ['panel', 'vi tri', 'mat']));
            if ($orderQuantity <= 0 && $panel !== '') {
                $orderQuantity = $quantity;
            }
            $quantityFront = $this->number($this->pick($normalized, ['quantity front', 'front']));
            $quantityBack = $this->number($this->pick($normalized, ['quantity back', 'back']));
            $received = $this->number($this->pick($normalized, ['so luong nhan set', 'received quantity', 'so luong nhan']));
            $frontPass = $this->number($this->pick($normalized, ['so luong mat truoc dat', 'front dat', 'front pass', 'kinh doanh dat']));
            $frontFail = $this->number($this->pick($normalized, ['so luong mat truoc loi', 'front loi', 'front fail', 'kinh doanh loi']));
            $backPass = $this->number($this->pick($normalized, ['so luong mat sau dat', 'back dat', 'back pass']));
            $backFail = $this->number($this->pick($normalized, ['so luong mat sau loi', 'back loi', 'back fail']));

            if ($received <= 0 && $panel === '') {
                $received = max($frontPass + $frontFail, $backPass + $backFail, $quantity);
            }

            $remaining = max(0, $orderQuantity - $received);
            $deliveryDate = $this->date($this->pick($normalized, ['delivery date', 'ngay giao hang']));
            $status = $remaining <= 0 && $orderQuantity > 0
                ? 'completed'
                : ($deliveryDate && $deliveryDate->isBefore(now()->startOfDay()) ? 'late' : ($received > 0 ? 'partial' : 'pending'));

            $identity = [
                $this->text($this->pick($normalized, ['ma hang', 'item code', 'item no', 'item'])),
                $this->text($this->pick($normalized, ['ps sub', 'ps no', 'ps', 'don hang', 'order number'])),
                $panel,
                $this->text($this->pick($normalized, ['size'])),
                $this->text($this->pick($normalized, ['fabric color', 'mau vai', 'mau', 'color'])),
                $this->text($this->pick($normalized, ['logo color', 'mau in'])),
                $this->text($this->pick($normalized, ['so phieu', 'voucher number', 'voucher'])),
                $worksheet->getTitle(),
            ];
            $identityKey = implode('|', array_map('mb_strtoupper', $identity));
            $occurrences[$identityKey] = ($occurrences[$identityKey] ?? 0) + 1;

            $rows[] = [
                'source_sheet' => $worksheet->getTitle(),
                'row_key' => hash('sha256', $identityKey . '|' . $occurrences[$identityKey]),
                'source_row' => $rowIndex + 1,
                'sequence_no' => $this->text($this->pick($normalized, ['stt'])),
                'export_date' => $this->dateValue($this->pick($normalized, ['export date', 'ngay xuat'])),
                'item_code' => $identity[0],
                'order_number' => $identity[1],
                'panel' => $identity[2],
                'size' => $identity[3],
                'fabric_color' => $identity[4],
                'logo_color' => $identity[5],
                'panel_out_date' => $this->dateValue($this->pick($normalized, ['date out panel', 'ngay gui panel'])),
                'voucher_number' => $identity[6],
                'order_quantity' => $orderQuantity,
                'quantity' => $quantity,
                'quantity_front' => $quantityFront,
                'quantity_back' => $quantityBack,
                'received_quantity' => $received,
                'delivery_date' => $deliveryDate ? $deliveryDate->format('Y-m-d') : null,
                'front_pass' => $frontPass,
                'front_fail' => $frontFail,
                'back_pass' => $backPass,
                'back_fail' => $backFail,
                'remaining_quantity' => $remaining,
                'status' => $status,
                'note' => $this->text($this->pick($normalized, ['ghi chu'])),
                'extra_data' => $normalized,
                'import_batch' => $batch,
                'is_active' => true,
            ];
        }

        return $rows;
    }

    private function withReceiptProgress(array $row, float $received): array
    {
        $remaining = max(0, (float) $row['order_quantity'] - $received);
        $late = !empty($row['delivery_date'])
            && Carbon::parse($row['delivery_date'])->startOfDay()->lt(now()->startOfDay());
        $row['received_quantity'] = $received;
        $row['remaining_quantity'] = $remaining;
        $row['status'] = $remaining <= 0 && (float) $row['order_quantity'] > 0
            ? 'completed'
            : ($late ? 'late' : ($received > 0 ? 'partial' : 'pending'));
        return $row;
    }

    private function detectHeaderEnd(array $matrix): int
    {
        $candidate = 0;
        $keywords = [
            'stt', 'export date', 'ma hang', 'ps sub', 'don hang', 'size',
            'fabric color', 'logo color', 'date out panel', 'so phieu',
            'quantity', 'delivery date', 'ghi chu', 'so luong', 'panel',
        ];

        foreach (array_slice($matrix, 0, 12) as $index => $row) {
            $text = $this->normalize(implode(' ', array_filter(array_map([$this, 'text'], $row))));
            $score = 0;
            foreach ($keywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    $score++;
                }
            }
            if ($score >= 2) {
                $candidate = $index;
            }
        }

        return $candidate;
    }

    private function expandMergedHeaders($worksheet, array &$matrix): void
    {
        foreach ($worksheet->getMergeCells() as $range) {
            [$start, $end] = Coordinate::rangeBoundaries($range);
            $startColumn = $start[0] - 1;
            $startRow = $start[1] - 1;
            $endColumn = $end[0] - 1;
            $endRow = $end[1] - 1;

            if ($startRow > 11 || !isset($matrix[$startRow][$startColumn])) {
                continue;
            }

            $value = $matrix[$startRow][$startColumn];
            for ($row = $startRow; $row <= min($endRow, 11); $row++) {
                for ($column = $startColumn; $column <= $endColumn; $column++) {
                    if (($matrix[$row][$column] ?? null) === null || $matrix[$row][$column] === '') {
                        $matrix[$row][$column] = $value;
                    }
                }
            }
        }
    }

    private function buildHeaders(array $matrix, int $headerEnd): array
    {
        $headers = [];
        $columnCount = count($matrix[0] ?? []);

        for ($column = 0; $column < $columnCount; $column++) {
            $parts = [];
            for ($row = 0; $row <= $headerEnd; $row++) {
                $value = $this->normalize($this->text($matrix[$row][$column] ?? ''));
                if ($value !== '' && !in_array($value, $parts, true)) {
                    $parts[] = $value;
                }
            }
            $headers[$column] = implode(' ', $parts) ?: 'column_' . ($column + 1);
        }

        return $headers;
    }

    private function normalizeRow(array $headers, array $values): array
    {
        $row = [];
        foreach ($headers as $column => $header) {
            $row[$header] = $values[$column] ?? null;
        }
        return $row;
    }

    private function hasOrderData(array $row): bool
    {
        return $this->text($this->pick($row, ['ma hang', 'item code', 'item no', 'item'])) !== ''
            || $this->text($this->pick($row, ['ps sub', 'ps no', 'ps', 'don hang', 'order number'])) !== '';
    }

    private function pick(array $row, array $needles)
    {
        $normalizedNeedles = array_map(function ($needle) {
            return $this->normalize($needle);
        }, $needles);

        foreach ($normalizedNeedles as $needle) {
            if (array_key_exists($needle, $row)) {
                return $row[$needle];
            }
        }

        foreach ($normalizedNeedles as $needle) {
            foreach ($row as $header => $value) {
                if (strpos($header, $needle) !== false) {
                    return $value;
                }
            }
        }
        return null;
    }

    private function normalize(string $value): string
    {
        $value = preg_replace('/[^a-z0-9]+/', ' ', Str::ascii(mb_strtolower($value)));
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    private function text($value): string
    {
        return trim((string) $value);
    }

    private function number($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        $value = preg_replace('/[^\d,.-]/', '', $this->text($value));
        $value = str_replace(',', '', $value);
        return is_numeric($value) ? (float) $value : 0;
    }

    private function normalizePanel($value): string
    {
        $value = $this->text($value);
        if ($value === '') {
            return '';
        }
        $canonical = $this->panelRules->canonical($value);
        return $this->panelRules->rules()['automatic'][$canonical] ?? $value;
    }

    private function date($value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject($value));
        }
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'm/d/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, trim((string) $value))->startOfDay();
            } catch (\Throwable $e) {
            }
        }
        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function dateValue($value): ?string
    {
        $date = $this->date($value);
        return $date ? $date->format('Y-m-d') : null;
    }
}
