<?php

namespace App\Http\Controllers;

use App\Models\InternalItemCatalog;
use App\Models\InternalCustomer;
use App\Services\InternalCustomerCatalogSync;
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
            'customer' => 'nullable|string|max:200',
            'customer_group' => 'nullable|string|max:100',
        ]);

        $month = Carbon::createFromFormat('Y-m-d', $data['month'] . '-01')->startOfMonth();
        $monthStart = $month->format('Y-m-d');
        $monthEnd = $month->copy()->endOfMonth()->format('Y-m-d');
        $productionOrder = trim((string) ($data['production_order'] ?? ''));
        $itemCode = trim((string) ($data['item_code'] ?? ''));
        $customer = trim((string) ($data['customer'] ?? ''));
        $customerGroup = trim((string) ($data['customer_group'] ?? ''));
        $catalogMap = $this->catalogMap($resolver);
        $customerMap = $this->customerMap();
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
            $itemCode,
            $customerMap,
            $customer,
            $customerGroup
        );
        $summaryRows = ($productionOrder !== '' || $customer !== '' || $customerGroup !== '')
            ? $this->orderStockSummary(
                $monthStart,
                $monthEnd,
                $catalogMap,
                $resolver,
                $productionOrder,
                $itemCode,
                $customerMap,
                $customer,
                $customerGroup
            )
            : $this->stockSummary($monthStart, $monthEnd, $catalogMap, $resolver, $itemCode);

        if ($productionOrder !== '' || $itemCode !== '' || $customer !== '' || $customerGroup !== '') {
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
                    'customer' => $customer,
                    'customer_group' => $customerGroup,
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
                [
                    'production_order' => $productionOrder,
                    'item_code' => $itemCode,
                    'customer' => $customer,
                    'customer_group' => $customerGroup,
                ]
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

    private function customerMap(): array
    {
        return InternalCustomer::query()
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->get(['name', 'customer_group'])
            ->mapWithKeys(function ($customer) {
                return [InternalCustomerCatalogSync::normalize($customer->name) => [
                    'name' => trim((string) $customer->name),
                    'group' => trim((string) $customer->customer_group) ?: 'Chưa phân loại',
                ]];
            })
            ->all();
    }

    private function transactions(
        string $monthStart,
        string $monthEnd,
        array $catalogMap,
        InternalItemGroupResolver $resolver,
        string $productionOrder = '',
        string $itemCode = '',
        array $customerMap = [],
        string $customer = '',
        string $customerGroup = ''
    ): Collection {
        $receiptsQuery = DB::connection('internal')
            ->table('internal_material_receipt_lines as l')
            ->join('internal_material_receipts as r', 'r.id', '=', 'l.receipt_id')
            ->leftJoin('internal_production_orders as po', 'po.id', '=', 'l.production_order_id')
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
        if ($customer !== '') {
            $receiptsQuery->whereRaw(
                "UPPER(TRIM(COALESCE(NULLIF(l.customer, ''), po.customer, ''))) LIKE ?",
                ['%' . mb_strtoupper($customer) . '%']
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
                DB::raw("COALESCE(NULLIF(l.customer, ''), po.customer, '') as customer"),
                'l.id as line_id',
            ])
            ->get();

        $issuesQuery = DB::connection('internal')
            ->table('internal_material_issue_lines as l')
            ->join('internal_material_issues as i', 'i.id', '=', 'l.issue_id')
            ->leftJoin('internal_production_orders as po', 'po.id', '=', 'l.production_order_id')
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
        if ($customer !== '') {
            $issuesQuery->whereRaw(
                "UPPER(TRIM(COALESCE(NULLIF(l.customer, ''), po.customer, NULLIF(i.receiver_name, ''), ''))) LIKE ?",
                ['%' . mb_strtoupper($customer) . '%']
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
                DB::raw("COALESCE(NULLIF(l.customer, ''), po.customer, NULLIF(i.receiver_name, ''), '') as customer"),
                'l.id as line_id',
            ])
            ->get();

        return $receipts
            ->concat($issues)
            ->map(function ($row) use ($catalogMap, $resolver, $customerMap) {
                $code = trim((string) ($row->internal_item_code ?: $row->ma_hh));
                $catalog = $catalogMap[mb_strtoupper($code)] ?? null;
                $name = trim((string) ($catalog['name'] ?? $row->ten_hh ?? ''));
                $unit = mb_strtoupper(trim((string) ($row->unit ?: ($catalog['unit'] ?? ''))));
                $customerName = preg_replace('/\s+/u', ' ', trim((string) $row->customer));
                $customerCatalog = $customerMap[InternalCustomerCatalogSync::normalize($customerName)] ?? null;

                return [
                    'date' => (string) $row->transaction_date,
                    'document_code' => (string) $row->document_code,
                    'production_order' => trim((string) $row->production_order),
                    'code' => $code,
                    'name' => $name,
                    'quantity' => (float) $row->quantity,
                    'unit' => $unit !== '' ? $unit : 'CHƯA CÓ ĐVT',
                    'operation' => $row->transaction_type === 'receipt' ? 'Nhập kho' : 'Xuất kho',
                    'customer' => $customerCatalog['name'] ?? ($customerName !== '' ? $customerName : 'Chưa xác định'),
                    'customer_group' => $customerCatalog['group'] ?? 'Chưa phân loại',
                    'group' => $catalog['group'] ?? $resolver->resolveName($name),
                    'line_id' => (int) $row->line_id,
                ];
            })
            ->when(
                $customerGroup !== '',
                fn ($rows) => $rows->filter(
                    fn ($row) => mb_strtoupper($row['customer_group']) === mb_strtoupper($customerGroup)
                )
            )
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
        string $itemCode = '',
        array $customerMap = [],
        string $customer = '',
        string $customerGroup = ''
    ): Collection {
        return $this->transactions(
            '1900-01-01',
            $monthEnd,
            $catalogMap,
            $resolver,
            $productionOrder,
            $itemCode,
            $customerMap,
            $customer,
            $customerGroup
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
        $sheet->mergeCells('A1:K1');
        $sheet->setCellValue('A1', 'BÁO CÁO NHẬP XUẤT TỒN - ' . mb_strtoupper($group));
        $subtitle = ['Tháng ' . $month->format('m/Y'), 'Dữ liệu kho nội bộ'];
        if (trim((string) ($filters['production_order'] ?? '')) !== '') {
            $subtitle[] = 'Lệnh: ' . trim((string) $filters['production_order']);
        }
        if (trim((string) ($filters['item_code'] ?? '')) !== '') {
            $subtitle[] = 'Mã hàng: ' . trim((string) $filters['item_code']);
        }
        if (trim((string) ($filters['customer'] ?? '')) !== '') {
            $subtitle[] = 'Khách: ' . trim((string) $filters['customer']);
        }
        if (trim((string) ($filters['customer_group'] ?? '')) !== '') {
            $subtitle[] = 'Nhóm khách: ' . trim((string) $filters['customer_group']);
        }
        $sheet->mergeCells('A2:K2');
        $sheet->setCellValue('A2', implode(' · ', $subtitle));
        $headers = ['STT', 'Ngày', 'Số phiếu', 'Lệnh SX', 'Khách hàng', 'Nhóm khách', 'Mã hàng', 'Tên hàng', 'Số lượng', 'Đơn vị tính', 'Nhập kho / Xuất kho'];
        $sheet->fromArray($headers, null, 'A4');

        $rowNumber = 5;
        foreach ($rows as $index => $row) {
            $sheet->setCellValue('A' . $rowNumber, $index + 1);
            $sheet->setCellValue('B' . $rowNumber, ExcelDate::PHPToExcel(Carbon::parse($row['date'])));
            $sheet->setCellValueExplicit('C' . $rowNumber, $row['document_code'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('D' . $rowNumber, $row['production_order'], DataType::TYPE_STRING);
            $sheet->setCellValue('E' . $rowNumber, $row['customer']);
            $sheet->setCellValue('F' . $rowNumber, $row['customer_group']);
            $sheet->setCellValueExplicit('G' . $rowNumber, $row['code'], DataType::TYPE_STRING);
            $sheet->setCellValue('H' . $rowNumber, $row['name']);
            $sheet->setCellValue('I' . $rowNumber, $row['quantity']);
            $sheet->setCellValue('J' . $rowNumber, $row['unit']);
            $sheet->setCellValue('K' . $rowNumber, $row['operation']);
            $rowNumber++;
        }

        if ($rows->isEmpty()) {
            $sheet->mergeCells('A5:K5');
            $sheet->setCellValue('A5', 'Không có giao dịch trong tháng.');
            $sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $rowNumber = 6;
        }

        $summaryStart = $rowNumber + 1;
        $sheet->fromArray(['SUBTOTAL', 'Đơn vị tính', 'Tồn đầu kỳ', 'Nhập kho', 'Xuất kho', 'Tồn cuối kỳ'], null, 'F' . $summaryStart);
        $summaryRow = $summaryStart + 1;
        foreach ($summary->sortBy('unit') as $subtotal) {
            $sheet->setCellValue('F' . $summaryRow, 'SUBTOTAL');
            $sheet->setCellValue('G' . $summaryRow, $subtotal['unit']);
            $sheet->setCellValue('H' . $summaryRow, $subtotal['opening']);
            $sheet->setCellValue('I' . $summaryRow, $subtotal['receipt']);
            $sheet->setCellValue('J' . $summaryRow, $subtotal['issue']);
            $sheet->setCellValue('K' . $summaryRow, $subtotal['closing']);
            $summaryRow++;
        }
        if ($summary->isEmpty()) {
            $sheet->mergeCells('F' . $summaryRow . ':K' . $summaryRow);
            $sheet->setCellValue('F' . $summaryRow, 'Không có số dư cho loại hàng này.');
        }

        $lastRow = max($summaryRow, $summaryStart + 1);
        $sheet->getStyle('A1:K1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 15, 'color' => ['rgb' => '123653']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'DDEEFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle('A2:K2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A4:K4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '173F6B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('F' . $summaryStart . ':K' . $summaryStart)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '173F6B']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'EAF4FF']],
        ]);
        $sheet->getStyle('A4:K' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CAD8E5');
        $sheet->getStyle('B5:B' . max($rowNumber - 1, 5))->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $sheet->getStyle('I5:I' . max($rowNumber - 1, 5))->getNumberFormat()->setFormatCode('#,##0.###');
        $sheet->getStyle('H' . ($summaryStart + 1) . ':K' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.###');
        $sheet->getStyle('A4:K' . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('H5:H' . max($rowNumber - 1, 5))->getAlignment()->setWrapText(true);
        $sheet->freezePane('A5');
        if ($rows->isNotEmpty()) {
            $sheet->setAutoFilter('A4:K' . ($rowNumber - 1));
        }
        $sheet->getColumnDimension('A')->setWidth(7);
        $sheet->getColumnDimension('B')->setWidth(13);
        $sheet->getColumnDimension('C')->setWidth(23);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(24);
        $sheet->getColumnDimension('F')->setWidth(20);
        $sheet->getColumnDimension('G')->setWidth(22);
        $sheet->getColumnDimension('H')->setWidth(48);
        $sheet->getColumnDimension('I')->setWidth(16);
        $sheet->getColumnDimension('J')->setWidth(15);
        $sheet->getColumnDimension('K')->setWidth(20);
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
