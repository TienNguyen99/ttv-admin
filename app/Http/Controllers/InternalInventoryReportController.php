<?php

namespace App\Http\Controllers;

use App\Models\InternalItemCatalog;
use App\Services\InternalItemGroupResolver;
use App\Services\InternalStockLedger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class InternalInventoryReportController extends Controller
{
    public function groups(InternalItemGroupResolver $resolver)
    {
        $groups = InternalItemCatalog::query()
            ->where('is_active', true)
            ->select(['item_code', 'item_name', 'raw_data'])
            ->get()
            ->groupBy(fn ($catalog) => $resolver->resolve($catalog))
            ->map(fn ($rows, $name) => [
                'name' => $name,
                'item_count' => $rows
                    ->pluck('item_code')
                    ->map(fn ($code) => mb_strtoupper(trim((string) $code)))
                    ->filter()
                    ->unique()
                    ->count(),
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return response()->json(['data' => $groups]);
    }

    public function export(Request $request, InternalItemGroupResolver $resolver)
    {
        $data = $request->validate([
            'month' => 'required|date_format:Y-m',
            'groups' => 'nullable|array|max:25',
            'groups.*' => 'string|max:100',
        ]);

        $month = Carbon::createFromFormat('Y-m-d', $data['month'] . '-01')->startOfMonth();
        $monthStart = $month->format('Y-m-d');
        $monthEnd = $month->copy()->endOfMonth()->format('Y-m-d');
        $catalogMap = $this->catalogMap($resolver);
        $availableGroups = collect($catalogMap)->pluck('group')->filter()->unique()->values();
        $selectedGroups = collect($data['groups'] ?? [])
            ->map(fn ($group) => trim((string) $group))
            ->filter()
            ->unique()
            ->values();

        if ($selectedGroups->isEmpty()) {
            $preferred = collect(['Thun bản', 'Nhãn dệt', 'Nhãn size'])
                ->filter(fn ($group) => $availableGroups->contains($group));
            $selectedGroups = $preferred->isNotEmpty() ? $preferred->values() : $availableGroups;
        }

        $transactions = $this->transactions($monthStart, $monthEnd, $catalogMap, $resolver)
            ->filter(fn ($row) => $selectedGroups->contains($row['group']))
            ->groupBy('group');
        $summary = $this->stockSummary($monthStart, $monthEnd, $catalogMap, $resolver)
            ->filter(fn ($row) => $selectedGroups->contains($row['group']))
            ->groupBy('group');

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('TTV Quản lý kho')
            ->setTitle('Báo cáo nhập xuất tồn theo loại hàng')
            ->setDescription('Dữ liệu database kho nội bộ, không lấy từ TSoft.');

        $usedTitles = [];
        foreach ($selectedGroups as $index => $group) {
            $sheet = $index === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $sheet->setTitle($this->sheetTitle($group, $usedTitles));
            $this->writeSheet(
                $sheet,
                $group,
                $month,
                $transactions->get($group, collect()),
                $summary->get($group, collect())
            );
        }

        if ($selectedGroups->isEmpty()) {
            $this->writeSheet($spreadsheet->getActiveSheet(), 'Không có dữ liệu', $month, collect(), collect());
        }

        $spreadsheet->setActiveSheetIndex(0);
        $filename = 'bao-cao-nhap-xuat-ton-' . $month->format('Y-m') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
        ]);
    }

    private function catalogMap(InternalItemGroupResolver $resolver): array
    {
        $map = [];
        InternalItemCatalog::query()
            ->where('is_active', true)
            ->select(['item_code', 'item_name', 'unit', 'raw_data', 'source_row'])
            ->orderBy('source_row')
            ->get()
            ->each(function ($catalog) use (&$map, $resolver) {
                $key = mb_strtoupper(trim((string) $catalog->item_code));
                if ($key === '' || isset($map[$key])) {
                    return;
                }
                $map[$key] = [
                    'name' => trim((string) $catalog->item_name),
                    'unit' => trim((string) $catalog->unit),
                    'group' => $resolver->resolve($catalog),
                ];
            });

        return $map;
    }

    private function transactions(
        string $monthStart,
        string $monthEnd,
        array $catalogMap,
        InternalItemGroupResolver $resolver
    ): Collection {
        $receipts = DB::connection('internal')
            ->table('internal_material_receipt_lines as l')
            ->join('internal_material_receipts as r', 'r.id', '=', 'l.receipt_id')
            ->where('r.source', 'Phieu nhap thanh pham')
            ->whereBetween('r.receipt_date', [$monthStart, $monthEnd])
            ->select([
                DB::raw("'receipt' as transaction_type"),
                'r.receipt_date as transaction_date',
                'r.receipt_code as document_code',
                'l.internal_item_code',
                'l.ma_hh',
                'l.ten_hh',
                DB::raw('COALESCE(l.base_quantity, l.quantity) as quantity'),
                DB::raw("COALESCE(NULLIF(l.base_dvt, ''), l.dvt, '') as unit"),
                'l.id as line_id',
            ])
            ->get();

        $issues = DB::connection('internal')
            ->table('internal_material_issue_lines as l')
            ->join('internal_material_issues as i', 'i.id', '=', 'l.issue_id')
            ->whereRaw("COALESCE(i.issue_type, 'material') <> 'production'")
            ->whereBetween('i.issue_date', [$monthStart, $monthEnd])
            ->select([
                DB::raw("'issue' as transaction_type"),
                'i.issue_date as transaction_date',
                'i.issue_code as document_code',
                'l.internal_item_code',
                'l.ma_hh',
                'l.ten_hh',
                DB::raw('COALESCE(l.base_quantity, l.quantity) as quantity'),
                DB::raw("COALESCE(NULLIF(l.base_dvt, ''), l.dvt, '') as unit"),
                'l.id as line_id',
            ])
            ->get();

        return $receipts
            ->concat($issues)
            ->map(function ($row) use ($catalogMap, $resolver) {
                $code = trim((string) ($row->internal_item_code ?: $row->ma_hh));
                $catalog = $catalogMap[mb_strtoupper($code)] ?? null;
                $name = trim((string) ($catalog['name'] ?? $row->ten_hh ?? ''));
                $unit = mb_strtoupper(trim((string) ($row->unit ?: ($catalog['unit'] ?? ''))));

                return [
                    'date' => (string) $row->transaction_date,
                    'document_code' => (string) $row->document_code,
                    'code' => $code,
                    'name' => $name,
                    'quantity' => (float) $row->quantity,
                    'unit' => $unit !== '' ? $unit : 'CHƯA CÓ ĐVT',
                    'operation' => $row->transaction_type === 'receipt' ? 'Nhập kho' : 'Xuất kho',
                    'group' => $catalog['group'] ?? $resolver->resolveName($name),
                    'line_id' => (int) $row->line_id,
                ];
            })
            ->sortBy(fn ($row) => implode('|', [
                $row['date'],
                $row['document_code'],
                $row['operation'],
                str_pad((string) $row['line_id'], 12, '0', STR_PAD_LEFT),
            ]))
            ->values();
    }

    private function stockSummary(
        string $monthStart,
        string $monthEnd,
        array $catalogMap,
        InternalItemGroupResolver $resolver
    ): Collection {
        return app(InternalStockLedger::class)
            ->query($monthStart, $monthEnd)
            ->select(
                'internal_item_code',
                'ma_hh',
                DB::raw('SUM(opening_quantity) as opening_quantity'),
                DB::raw('SUM(receipt_quantity) as receipt_quantity'),
                DB::raw('SUM(issue_quantity) as issue_quantity'),
                DB::raw('SUM(opening_quantity + receipt_quantity - issue_quantity) as closing_quantity')
            )
            ->groupBy('internal_item_code', 'ma_hh')
            ->get()
            ->map(function ($row) use ($catalogMap, $resolver) {
                $code = trim((string) ($row->internal_item_code ?: $row->ma_hh));
                $catalog = $catalogMap[mb_strtoupper($code)] ?? null;
                $name = trim((string) ($catalog['name'] ?? ''));
                $unit = mb_strtoupper(trim((string) ($catalog['unit'] ?? '')));

                return [
                    'group' => $catalog['group'] ?? $resolver->resolveName($name),
                    'unit' => $unit !== '' ? $unit : 'CHƯA CÓ ĐVT',
                    'opening' => (float) $row->opening_quantity,
                    'receipt' => (float) $row->receipt_quantity,
                    'issue' => (float) $row->issue_quantity,
                    'closing' => (float) $row->closing_quantity,
                ];
            })
            ->groupBy(fn ($row) => $row['group'] . '|' . $row['unit'])
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    'group' => $first['group'],
                    'unit' => $first['unit'],
                    'opening' => $rows->sum('opening'),
                    'receipt' => $rows->sum('receipt'),
                    'issue' => $rows->sum('issue'),
                    'closing' => $rows->sum('closing'),
                ];
            })
            ->values();
    }

    private function writeSheet($sheet, string $group, Carbon $month, Collection $rows, Collection $summary): void
    {
        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', 'BÁO CÁO NHẬP XUẤT TỒN - ' . mb_strtoupper($group));
        $sheet->mergeCells('A2:H2');
        $sheet->setCellValue('A2', 'Tháng ' . $month->format('m/Y') . ' · Dữ liệu kho nội bộ');
        $headers = ['STT', 'Ngày', 'Số phiếu', 'Mã hàng', 'Tên hàng', 'Số lượng', 'Đơn vị tính', 'Nhập kho / Xuất kho'];
        $sheet->fromArray($headers, null, 'A4');

        $rowNumber = 5;
        foreach ($rows as $index => $row) {
            $sheet->setCellValue('A' . $rowNumber, $index + 1);
            $sheet->setCellValue('B' . $rowNumber, ExcelDate::PHPToExcel(Carbon::parse($row['date'])));
            $sheet->setCellValueExplicit('C' . $rowNumber, $row['document_code'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('D' . $rowNumber, $row['code'], DataType::TYPE_STRING);
            $sheet->setCellValue('E' . $rowNumber, $row['name']);
            $sheet->setCellValue('F' . $rowNumber, $row['quantity']);
            $sheet->setCellValue('G' . $rowNumber, $row['unit']);
            $sheet->setCellValue('H' . $rowNumber, $row['operation']);
            $rowNumber++;
        }

        if ($rows->isEmpty()) {
            $sheet->mergeCells('A5:H5');
            $sheet->setCellValue('A5', 'Không có giao dịch trong tháng.');
            $sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $rowNumber = 6;
        }

        $summaryStart = $rowNumber + 1;
        $sheet->fromArray(['SUBTOTAL', 'Đơn vị tính', 'Tồn đầu kỳ', 'Nhập kho', 'Xuất kho', 'Tồn cuối kỳ'], null, 'C' . $summaryStart);
        $summaryRow = $summaryStart + 1;
        foreach ($summary->sortBy('unit') as $subtotal) {
            $sheet->setCellValue('C' . $summaryRow, 'SUBTOTAL');
            $sheet->setCellValue('D' . $summaryRow, $subtotal['unit']);
            $sheet->setCellValue('E' . $summaryRow, $subtotal['opening']);
            $sheet->setCellValue('F' . $summaryRow, $subtotal['receipt']);
            $sheet->setCellValue('G' . $summaryRow, $subtotal['issue']);
            $sheet->setCellValue('H' . $summaryRow, $subtotal['closing']);
            $summaryRow++;
        }
        if ($summary->isEmpty()) {
            $sheet->mergeCells('C' . $summaryRow . ':H' . $summaryRow);
            $sheet->setCellValue('C' . $summaryRow, 'Không có số dư cho loại hàng này.');
        }

        $lastRow = max($summaryRow, $summaryStart + 1);
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 15, 'color' => ['rgb' => '123653']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'DDEEFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle('A2:H2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A4:H4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '173F6B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('C' . $summaryStart . ':H' . $summaryStart)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '173F6B']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'EAF4FF']],
        ]);
        $sheet->getStyle('A4:H' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CAD8E5');
        $sheet->getStyle('B5:B' . max($rowNumber - 1, 5))->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $sheet->getStyle('F5:F' . max($rowNumber - 1, 5))->getNumberFormat()->setFormatCode('#,##0.###');
        $sheet->getStyle('E' . ($summaryStart + 1) . ':H' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.###');
        $sheet->getStyle('A4:H' . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('E5:E' . max($rowNumber - 1, 5))->getAlignment()->setWrapText(true);
        $sheet->freezePane('A5');
        if ($rows->isNotEmpty()) {
            $sheet->setAutoFilter('A4:H' . ($rowNumber - 1));
        }
        $sheet->getColumnDimension('A')->setWidth(7);
        $sheet->getColumnDimension('B')->setWidth(13);
        $sheet->getColumnDimension('C')->setWidth(23);
        $sheet->getColumnDimension('D')->setWidth(22);
        $sheet->getColumnDimension('E')->setWidth(48);
        $sheet->getColumnDimension('F')->setWidth(16);
        $sheet->getColumnDimension('G')->setWidth(15);
        $sheet->getColumnDimension('H')->setWidth(20);
        $sheet->getRowDimension(1)->setRowHeight(26);
        $sheet->setShowGridlines(false);
    }

    private function sheetTitle(string $group, array &$usedTitles): string
    {
        $base = trim(preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/u', ' ', $group)) ?: 'Chưa phân nhóm';
        $base = mb_substr($base, 0, 31);
        $title = $base;
        $suffix = 2;
        while (in_array(mb_strtolower($title), $usedTitles, true)) {
            $tail = '-' . $suffix++;
            $title = mb_substr($base, 0, 31 - mb_strlen($tail)) . $tail;
        }
        $usedTitles[] = mb_strtolower($title);

        return $title;
    }
}
