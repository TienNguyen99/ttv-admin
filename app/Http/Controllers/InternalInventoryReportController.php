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
            'production_order' => 'nullable|string|max:100',
            'item_code' => 'nullable|string|max:200',
        ]);

        $month = Carbon::createFromFormat('Y-m-d', $data['month'] . '-01')->startOfMonth();
        $monthStart = $month->format('Y-m-d');
        $monthEnd = $month->copy()->endOfMonth()->format('Y-m-d');
        $productionOrder = trim((string) ($data['production_order'] ?? ''));
        $itemCode = trim((string) ($data['item_code'] ?? ''));
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

        $transactionRows = $this->transactions(
            $monthStart,
            $monthEnd,
            $catalogMap,
            $resolver,
            $productionOrder,
            $itemCode
        );
        $summaryRows = $productionOrder !== ''
            ? $this->orderStockSummary(
                $monthStart,
                $monthEnd,
                $catalogMap,
                $resolver,
                $productionOrder,
                $itemCode
            )
            : $this->stockSummary($monthStart, $monthEnd, $catalogMap, $resolver, $itemCode);

        if ($productionOrder !== '' || $itemCode !== '') {
            $matchedGroups = $transactionRows
                ->pluck('group')
                ->merge($summaryRows->pluck('group'))
                ->filter()
                ->unique()
                ->values();
            if ($matchedGroups->isNotEmpty() && $selectedGroups->intersect($matchedGroups)->isEmpty()) {
                $selectedGroups = $matchedGroups;
            }
        }

        $transactions = $transactionRows
            ->filter(fn ($row) => $selectedGroups->contains($row['group']))
            ->groupBy('group');
        $summary = $summaryRows
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
                $summary->get($group, collect()),
                [
                    'production_order' => $productionOrder,
                    'item_code' => $itemCode,
                ]
            );
        }

        if ($selectedGroups->isEmpty()) {
            $this->writeSheet(
                $spreadsheet->getActiveSheet(),
                'Không có dữ liệu',
                $month,
                collect(),
                collect(),
                ['production_order' => $productionOrder, 'item_code' => $itemCode]
            );
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
        InternalItemGroupResolver $resolver,
        string $productionOrder = '',
        string $itemCode = ''
    ): Collection {
        $receiptsQuery = DB::connection('internal')
            ->table('internal_material_receipt_lines as l')
            ->join('internal_material_receipts as r', 'r.id', '=', 'l.receipt_id')
            ->where('r.source', 'Phieu nhap thanh pham')
            ->whereBetween('r.receipt_date', [$monthStart, $monthEnd]);
        if ($productionOrder !== '') {
            $receiptsQuery->whereRaw(
                'UPPER(TRIM(l.production_order)) LIKE ?',
                ['%' . mb_strtoupper($productionOrder) . '%']
            );
        }
        if ($itemCode !== '') {
            $receiptsQuery->whereRaw(
                "UPPER(TRIM(COALESCE(NULLIF(l.internal_item_code, ''), l.ma_hh))) = ?",
                [mb_strtoupper($itemCode)]
            );
        }
        $receipts = $receiptsQuery
            ->select([
                DB::raw("'receipt' as transaction_type"),
                'r.receipt_date as transaction_date',
                'r.receipt_code as document_code',
                'l.production_order',
                'l.internal_item_code',
                'l.ma_hh',
                'l.ten_hh',
                DB::raw('COALESCE(l.base_quantity, l.quantity) as quantity'),
                DB::raw("COALESCE(NULLIF(l.base_dvt, ''), l.dvt, '') as unit"),
                'l.id as line_id',
            ])
            ->get();

        $issuesQuery = DB::connection('internal')
            ->table('internal_material_issue_lines as l')
            ->join('internal_material_issues as i', 'i.id', '=', 'l.issue_id')
            ->whereRaw("COALESCE(i.issue_type, 'material') <> 'production'")
            ->whereBetween('i.issue_date', [$monthStart, $monthEnd]);
        if ($productionOrder !== '') {
            $issuesQuery->whereRaw(
                "UPPER(TRIM(COALESCE(NULLIF(l.production_order, ''), i.production_order))) LIKE ?",
                ['%' . mb_strtoupper($productionOrder) . '%']
            );
        }
        if ($itemCode !== '') {
            $issuesQuery->whereRaw(
                "UPPER(TRIM(COALESCE(NULLIF(l.internal_item_code, ''), l.ma_hh))) = ?",
                [mb_strtoupper($itemCode)]
            );
        }
        $issues = $issuesQuery
            ->select([
                DB::raw("'issue' as transaction_type"),
                'i.issue_date as transaction_date',
                'i.issue_code as document_code',
                DB::raw("COALESCE(NULLIF(l.production_order, ''), i.production_order) as production_order"),
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
                    'production_order' => trim((string) $row->production_order),
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
        InternalItemGroupResolver $resolver,
        string $itemCode = ''
    ): Collection {
        $normalizedItemCode = mb_strtoupper(trim($itemCode));

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
                    'code' => $code,
                    'group' => $catalog['group'] ?? $resolver->resolveName($name),
                    'unit' => $unit !== '' ? $unit : 'CHƯA CÓ ĐVT',
                    'opening' => (float) $row->opening_quantity,
                    'receipt' => (float) $row->receipt_quantity,
                    'issue' => (float) $row->issue_quantity,
                    'closing' => (float) $row->closing_quantity,
                ];
            })
            ->when(
                $normalizedItemCode !== '',
                fn ($rows) => $rows->filter(
                    fn ($row) => mb_strtoupper(trim((string) $row['code'])) === $normalizedItemCode
                )
            )
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

    private function orderStockSummary(
        string $monthStart,
        string $monthEnd,
        array $catalogMap,
        InternalItemGroupResolver $resolver,
        string $productionOrder,
        string $itemCode = ''
    ): Collection {
        return $this->transactions(
            '1900-01-01',
            $monthEnd,
            $catalogMap,
            $resolver,
            $productionOrder,
            $itemCode
        )
            ->groupBy(fn ($row) => $row['group'] . '|' . $row['unit'])
            ->map(function ($rows) use ($monthStart) {
                $first = $rows->first();
                $opening = $rows
                    ->filter(fn ($row) => $row['date'] < $monthStart)
                    ->sum(fn ($row) => $row['operation'] === 'Nhập kho' ? $row['quantity'] : -$row['quantity']);
                $periodRows = $rows->filter(fn ($row) => $row['date'] >= $monthStart);
                $receipt = $periodRows
                    ->where('operation', 'Nhập kho')
                    ->sum('quantity');
                $issue = $periodRows
                    ->where('operation', 'Xuất kho')
                    ->sum('quantity');

                return [
                    'group' => $first['group'],
                    'unit' => $first['unit'],
                    'opening' => (float) $opening,
                    'receipt' => (float) $receipt,
                    'issue' => (float) $issue,
                    'closing' => (float) ($opening + $receipt - $issue),
                ];
            })
            ->values();
    }

    private function writeSheet(
        $sheet,
        string $group,
        Carbon $month,
        Collection $rows,
        Collection $summary,
        array $filters = []
    ): void
    {
        $sheet->mergeCells('A1:I1');
        $sheet->setCellValue('A1', 'BÁO CÁO NHẬP XUẤT TỒN - ' . mb_strtoupper($group));
        $subtitle = ['Tháng ' . $month->format('m/Y'), 'Dữ liệu kho nội bộ'];
        if (trim((string) ($filters['production_order'] ?? '')) !== '') {
            $subtitle[] = 'Lệnh: ' . trim((string) $filters['production_order']);
        }
        if (trim((string) ($filters['item_code'] ?? '')) !== '') {
            $subtitle[] = 'Mã hàng: ' . trim((string) $filters['item_code']);
        }
        $sheet->mergeCells('A2:I2');
        $sheet->setCellValue('A2', implode(' · ', $subtitle));
        $headers = ['STT', 'Ngày', 'Số phiếu', 'Lệnh SX', 'Mã hàng', 'Tên hàng', 'Số lượng', 'Đơn vị tính', 'Nhập kho / Xuất kho'];
        $sheet->fromArray($headers, null, 'A4');

        $rowNumber = 5;
        foreach ($rows as $index => $row) {
            $sheet->setCellValue('A' . $rowNumber, $index + 1);
            $sheet->setCellValue('B' . $rowNumber, ExcelDate::PHPToExcel(Carbon::parse($row['date'])));
            $sheet->setCellValueExplicit('C' . $rowNumber, $row['document_code'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('D' . $rowNumber, $row['production_order'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('E' . $rowNumber, $row['code'], DataType::TYPE_STRING);
            $sheet->setCellValue('F' . $rowNumber, $row['name']);
            $sheet->setCellValue('G' . $rowNumber, $row['quantity']);
            $sheet->setCellValue('H' . $rowNumber, $row['unit']);
            $sheet->setCellValue('I' . $rowNumber, $row['operation']);
            $rowNumber++;
        }

        if ($rows->isEmpty()) {
            $sheet->mergeCells('A5:I5');
            $sheet->setCellValue('A5', 'Không có giao dịch trong tháng.');
            $sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $rowNumber = 6;
        }

        $summaryStart = $rowNumber + 1;
        $sheet->fromArray(['SUBTOTAL', 'Đơn vị tính', 'Tồn đầu kỳ', 'Nhập kho', 'Xuất kho', 'Tồn cuối kỳ'], null, 'D' . $summaryStart);
        $summaryRow = $summaryStart + 1;
        foreach ($summary->sortBy('unit') as $subtotal) {
            $sheet->setCellValue('D' . $summaryRow, 'SUBTOTAL');
            $sheet->setCellValue('E' . $summaryRow, $subtotal['unit']);
            $sheet->setCellValue('F' . $summaryRow, $subtotal['opening']);
            $sheet->setCellValue('G' . $summaryRow, $subtotal['receipt']);
            $sheet->setCellValue('H' . $summaryRow, $subtotal['issue']);
            $sheet->setCellValue('I' . $summaryRow, $subtotal['closing']);
            $summaryRow++;
        }
        if ($summary->isEmpty()) {
            $sheet->mergeCells('D' . $summaryRow . ':I' . $summaryRow);
            $sheet->setCellValue('D' . $summaryRow, 'Không có số dư cho loại hàng này.');
        }

        $lastRow = max($summaryRow, $summaryStart + 1);
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 15, 'color' => ['rgb' => '123653']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'DDEEFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle('A2:I2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A4:I4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '173F6B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('D' . $summaryStart . ':I' . $summaryStart)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '173F6B']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'EAF4FF']],
        ]);
        $sheet->getStyle('A4:I' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CAD8E5');
        $sheet->getStyle('B5:B' . max($rowNumber - 1, 5))->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $sheet->getStyle('G5:G' . max($rowNumber - 1, 5))->getNumberFormat()->setFormatCode('#,##0.###');
        $sheet->getStyle('F' . ($summaryStart + 1) . ':I' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.###');
        $sheet->getStyle('A4:I' . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('F5:F' . max($rowNumber - 1, 5))->getAlignment()->setWrapText(true);
        $sheet->freezePane('A5');
        if ($rows->isNotEmpty()) {
            $sheet->setAutoFilter('A4:I' . ($rowNumber - 1));
        }
        $sheet->getColumnDimension('A')->setWidth(7);
        $sheet->getColumnDimension('B')->setWidth(13);
        $sheet->getColumnDimension('C')->setWidth(23);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(22);
        $sheet->getColumnDimension('F')->setWidth(48);
        $sheet->getColumnDimension('G')->setWidth(16);
        $sheet->getColumnDimension('H')->setWidth(15);
        $sheet->getColumnDimension('I')->setWidth(20);
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
