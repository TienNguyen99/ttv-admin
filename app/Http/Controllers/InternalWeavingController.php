<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\NormalizesDateInput;
use App\Models\InternalItemCatalog;
use App\Models\InternalProductionOrder;
use App\Models\InternalWeavingBom;
use App\Models\InternalWeavingItem;
use App\Models\InternalWeavingOrder;
use App\Models\InventoryPackage;
use App\Services\InternalUnitConverter;
use App\Services\WeavingExcelBatchExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class InternalWeavingController extends Controller
{
    use NormalizesDateInput;

    public function designerDashboard(Request $request)
    {
        $keyword = mb_strtolower(trim((string) $request->query('keyword', '')));
        $customer = trim((string) $request->query('customer', ''));
        $status = trim((string) $request->query('status', ''));
        $currentYear = (int) now('Asia/Ho_Chi_Minh')->format('Y');
        $year = (int) $request->query('year', $currentYear);
        if ($year < 2000 || $year > 2099) {
            $year = $currentYear;
        }
        $orderYearSuffix = '/' . str_pad((string) ($year % 100), 2, '0', STR_PAD_LEFT);
        $page = max((int) $request->query('page', 1), 1);
        $perPage = min(max((int) $request->query('per_page', 50), 25), 200);

        $availableYears = InternalWeavingOrder::query()
            ->pluck('order_code')
            ->map(function ($orderCode) {
                return preg_match('/\/(\d{2})\s*$/', trim((string) $orderCode), $matches)
                    ? 2000 + (int) $matches[1]
                    : null;
            })
            ->filter()
            ->unique()
            ->sortDesc()
            ->values();

        $orders = InternalWeavingOrder::query()
            ->with('item:id,item_code,item_name')
            ->whereRaw('TRIM(order_code) LIKE ?', ['%' . $orderYearSuffix])
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->get();
        $receiptTotals = collect();
        $orders->pluck('order_code')->filter()->unique()->chunk(500)->each(function ($orderCodes) use ($receiptTotals) {
            DB::connection('internal')->table('internal_material_receipt_lines as line')
                ->join('internal_material_receipts as receipt', 'receipt.id', '=', 'line.receipt_id')
                ->whereIn('line.production_order', $orderCodes->values()->all())
                ->where(function ($query) {
                    $query->whereNull('receipt.status')->orWhere('receipt.status', '<>', 'cancelled');
                })
                ->selectRaw('line.production_order as order_key')
                ->selectRaw('SUM(line.quantity) as received_quantity')
                ->selectRaw('COUNT(DISTINCT line.receipt_id) as receipt_count')
                ->selectRaw('MAX(receipt.receipt_date) as last_receipt_date')
                ->groupBy('line.production_order')
                ->get()
                ->each(function ($row) use ($receiptTotals) {
                    $receiptTotals->put(mb_strtoupper(trim((string) $row->order_key)), $row);
                });
        });

        $allRows = $orders
            ->map(function (InternalWeavingOrder $order) use ($receiptTotals) {
                $receipt = $receiptTotals->get(mb_strtoupper(trim((string) $order->order_code)));
                $planned = (float) $order->order_quantity;
                $metadata = json_decode((string) ($order->metadata_json ?? ''), true) ?: [];
                $sentToProduction = $order->status === 'issued' || !empty($metadata['sent_to_production_at']);
                $rawReceived = (float) ($receipt->received_quantity ?? 0);
                $rawReceiptCount = (int) ($receipt->receipt_count ?? 0);
                $receivedBaseline = (float) ($metadata['receipt_quantity_baseline'] ?? 0);
                $receiptCountBaseline = (int) ($metadata['receipt_count_baseline'] ?? 0);
                $received = $sentToProduction ? max($rawReceived - $receivedBaseline, 0) : 0;
                $receiptCount = $sentToProduction ? max($rawReceiptCount - $receiptCountBaseline, 0) : 0;
                $remaining = max($planned - $received, 0);
                $progress = $planned > 0 ? min(($received / $planned) * 100, 100) : 0;
                $workflowStatus = !$sentToProduction
                    ? 'waiting'
                    : ($received > 0
                        ? ($planned > 0 && $received + 0.0001 >= $planned ? 'completed' : 'partial')
                        : 'producing');
                $isOverdue = $remaining > 0 && $order->due_date && $order->due_date->isBefore(now('Asia/Ho_Chi_Minh')->startOfDay());

                return [
                    'id' => (int) $order->id,
                    'order_code' => trim((string) $order->order_code),
                    'item_code' => trim((string) $order->item_code),
                    'item_name' => trim((string) ($order->item->item_name ?? '')),
                    'customer' => trim((string) $order->customer),
                    'po_number' => trim((string) $order->po_number),
                    'design_code' => trim((string) $order->design_code),
                    'order_quantity' => $planned,
                    'received_quantity' => $received,
                    'remaining_quantity' => $remaining,
                    'unit' => trim((string) $order->unit),
                    'order_date' => optional($order->order_date)->format('Y-m-d'),
                    'due_date' => optional($order->due_date)->format('Y-m-d'),
                    'sent_at' => $metadata['sent_to_production_at'] ?? ($order->status === 'issued' ? optional($order->updated_at)->toIso8601String() : null),
                    'last_receipt_date' => $receiptCount > 0 ? ($receipt->last_receipt_date ?? null) : null,
                    'receipt_count' => $receiptCount,
                    'progress' => round($progress, 1),
                    'workflow_status' => $workflowStatus,
                    'is_overdue' => (bool) $isOverdue,
                    'note' => trim((string) $order->note),
                ];
            });

        $customers = $allRows->pluck('customer')->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
        $scopeRows = $allRows->filter(function ($row) use ($keyword, $customer) {
            if ($customer !== '' && mb_strtolower($row['customer']) !== mb_strtolower($customer)) {
                return false;
            }
            if ($keyword === '') {
                return true;
            }
            $haystack = mb_strtolower(implode(' ', [
                $row['order_code'], $row['item_code'], $row['item_name'], $row['customer'],
                $row['po_number'], $row['design_code'], $row['note'],
            ]));
            return mb_strpos($haystack, $keyword) !== false;
        })->values();
        $filtered = $scopeRows->filter(function ($row) use ($status) {
            return $status === ''
                || $row['workflow_status'] === $status
                || ($status === 'overdue' && $row['is_overdue']);
        })->values();

        $summary = [
            'total' => $scopeRows->count(),
            'waiting' => $scopeRows->where('workflow_status', 'waiting')->count(),
            'producing' => $scopeRows->where('workflow_status', 'producing')->count(),
            'partial' => $scopeRows->where('workflow_status', 'partial')->count(),
            'completed' => $scopeRows->where('workflow_status', 'completed')->count(),
            'overdue' => $scopeRows->where('is_overdue', true)->count(),
        ];
        $total = $filtered->count();
        $rows = $filtered->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'data' => $rows,
            'summary' => $summary,
            'charts' => $this->designerChartData($scopeRows),
            'customers' => $customers,
            'filters' => [
                'year' => $year,
                'years' => $availableYears,
            ],
            'pagination' => $this->pagination($page, $perPage, $total),
        ]);
    }

    public function sendToProduction(InternalWeavingOrder $order)
    {
        if ($order->status === 'issued') {
            return response()->json(['message' => 'Lệnh này đã được gửi xuống sản xuất.']);
        }

        $metadata = json_decode((string) ($order->metadata_json ?? ''), true) ?: [];
        $receiptBaseline = DB::connection('internal')->table('internal_material_receipt_lines as line')
            ->join('internal_material_receipts as receipt', 'receipt.id', '=', 'line.receipt_id')
            ->where('line.production_order', trim((string) $order->order_code))
            ->where(function ($query) {
                $query->whereNull('receipt.status')->orWhere('receipt.status', '<>', 'cancelled');
            })
            ->selectRaw('COALESCE(SUM(line.quantity), 0) as received_quantity')
            ->selectRaw('COUNT(DISTINCT line.receipt_id) as receipt_count')
            ->first();
        $metadata['sent_to_production_at'] = now('Asia/Ho_Chi_Minh')->toIso8601String();
        $metadata['receipt_quantity_baseline'] = (float) ($receiptBaseline->received_quantity ?? 0);
        $metadata['receipt_count_baseline'] = (int) ($receiptBaseline->receipt_count ?? 0);
        $order->status = 'issued';
        $order->metadata_json = json_encode($metadata, JSON_UNESCAPED_UNICODE);
        $order->save();

        return response()->json(['message' => 'Đã chuyển lệnh ' . $order->order_code . ' sang Đang sản xuất.']);
    }

    private function designerChartData($rows): array
    {
        $statusLabels = ['Chờ sản xuất', 'Đang sản xuất', 'Nhập một phần', 'Đã nhập kho'];
        $statusValues = [
            $rows->where('workflow_status', 'waiting')->count(),
            $rows->where('workflow_status', 'producing')->count(),
            $rows->where('workflow_status', 'partial')->count(),
            $rows->where('workflow_status', 'completed')->count(),
        ];
        $overdueByCustomer = $rows->where('is_overdue', true)
            ->groupBy(fn ($row) => $row['customer'] ?: 'Chưa xác định')
            ->map(fn ($group) => $group->count())
            ->sortDesc()
            ->take(7);
        $trend = collect(range(7, 0))->map(function ($weeksAgo) use ($rows) {
            $start = now('Asia/Ho_Chi_Minh')->startOfWeek()->subWeeks($weeksAgo);
            $end = $start->copy()->endOfWeek();
            $orderCount = $rows->filter(function ($row) use ($start, $end) {
                if (empty($row['order_date'])) return false;
                $date = \Carbon\Carbon::parse($row['order_date'], 'Asia/Ho_Chi_Minh');
                return $date->betweenIncluded($start, $end);
            })->count();
            $receiptCount = $rows->filter(function ($row) use ($start, $end) {
                if (empty($row['last_receipt_date'])) return false;
                $date = \Carbon\Carbon::parse($row['last_receipt_date'], 'Asia/Ho_Chi_Minh');
                return $date->betweenIncluded($start, $end);
            })->count();

            return [
                'label' => $start->format('d/m'),
                'orders' => $orderCount,
                'receipts' => $receiptCount,
            ];
        })->values();

        return [
            'status' => ['labels' => $statusLabels, 'values' => $statusValues],
            'trend' => $trend,
            'overdue_customers' => [
                'labels' => $overdueByCustomer->keys()->values(),
                'values' => $overdueByCustomer->values(),
            ],
        ];
    }

    public function items(Request $request)
    {
        $keyword = mb_strtoupper(trim((string) $request->query('keyword', '')));
        $page = max((int) $request->query('page', 1), 1);
        $perPage = min(max((int) $request->query('per_page', 50), 25), 200);

        $query = InternalWeavingItem::query()
            ->withCount('boms')
            ->orderBy('item_code');

        if ($keyword !== '') {
            $query->where(function ($sub) use ($keyword) {
                $sub->whereRaw('UPPER(item_code) LIKE ?', ["%{$keyword}%"])
                    ->orWhereRaw('UPPER(item_name) LIKE ?', ["%{$keyword}%"])
                    ->orWhereRaw('UPPER(customer) LIKE ?', ["%{$keyword}%"]);
            });
        }

        $total = (clone $query)->count();
        $rows = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return response()->json([
            'data' => $rows,
            'summary' => [
                'item_count' => InternalWeavingItem::query()->count(),
                'bom_count' => InternalWeavingBom::query()->count(),
            ],
            'pagination' => $this->pagination($page, $perPage, $total),
        ]);
    }

    public function storeItem(Request $request)
    {
        $data = $request->validate([
            'item_code' => 'required|string|max:120',
            'item_name' => 'nullable|string|max:500',
            'customer' => 'nullable|string|max:200',
            'unit' => 'nullable|string|max:50',
            'note' => 'nullable|string|max:2000',
        ]);

        $item = InternalWeavingItem::query()->updateOrCreate(
            ['item_code' => $this->cleanCode($data['item_code'])],
            [
                'item_name' => trim((string) ($data['item_name'] ?? '')),
                'customer' => trim((string) ($data['customer'] ?? '')),
                'unit' => trim((string) ($data['unit'] ?? '')),
                'note' => trim((string) ($data['note'] ?? '')),
            ]
        );

        return response()->json(['message' => 'Da luu ma hang det.', 'data' => $item]);
    }

    public function importItems(Request $request)
    {
        $data = $request->validate([
            'text' => 'required|string',
        ]);

        $rows = $this->parseTsv($data['text']);
        $created = 0;
        $updated = 0;

        DB::connection('internal')->transaction(function () use ($rows, &$created, &$updated) {
            foreach ($rows as $row) {
                $code = $this->cleanCode($row[0] ?? '');
                if ($code === '') continue;

                $item = InternalWeavingItem::query()->updateOrCreate(
                    ['item_code' => $code],
                    [
                        'item_name' => trim((string) ($row[1] ?? '')),
                        'customer' => trim((string) ($row[2] ?? '')),
                        'unit' => trim((string) ($row[3] ?? '')),
                        'note' => trim((string) ($row[4] ?? '')),
                    ]
                );
                $item->wasRecentlyCreated ? $created++ : $updated++;
            }
        });

        return response()->json(['message' => 'Da import danh muc hang det.', 'data' => compact('created', 'updated')]);
    }

    public function boms(Request $request)
    {
        $itemCode = $this->cleanCode($request->query('item_code', ''));
        if ($itemCode === '') {
            return response()->json(['data' => [], 'summary' => ['line_count' => 0]]);
        }

        $item = InternalWeavingItem::query()->where('item_code', $itemCode)->first();
        if (!$item) {
            return response()->json(['data' => [], 'summary' => ['line_count' => 0]]);
        }

        $rows = $item->boms()->orderBy('material_code')->get();
        $catalog = $this->catalogByCodes($rows->pluck('material_code')->all());

        return response()->json([
            'item' => $item,
            'data' => $rows->map(fn ($row) => $this->decorateBomRow($row, $catalog))->values(),
            'summary' => ['line_count' => $rows->count()],
        ]);
    }

    public function replaceBoms(Request $request)
    {
        $data = $request->validate([
            'item_code' => 'required|string|max:120',
            'item_name' => 'nullable|string|max:500',
            'customer' => 'nullable|string|max:200',
            'unit' => 'nullable|string|max:50',
            'lines' => 'required|array|min:1|max:500',
            'lines.*.material_code' => 'required|string|max:120',
            'lines.*.line_role' => 'nullable|string|max:120',
            'lines.*.material_name' => 'nullable|string|max:500',
            'lines.*.unit' => 'nullable|string|max:50',
            'lines.*.consumption_per_unit' => 'required|numeric|min:0',
            'lines.*.waste_percent' => 'nullable|numeric|min:0|max:999',
            'lines.*.note' => 'nullable|string|max:2000',
        ]);

        $missingCatalog = $this->missingCatalogCodes(collect($data['lines'])->pluck('material_code')->all());
        if (!empty($missingCatalog)) {
            return response()->json([
                'message' => 'Mã sợi chưa có trong DANH MỤC nội bộ: ' . implode(', ', array_slice($missingCatalog, 0, 20)),
                'missing_catalog' => $missingCatalog,
            ], 422);
        }

        $item = DB::connection('internal')->transaction(function () use ($data) {
            $item = InternalWeavingItem::query()->updateOrCreate(
                ['item_code' => $this->cleanCode($data['item_code'])],
                [
                    'item_name' => trim((string) ($data['item_name'] ?? '')),
                    'customer' => trim((string) ($data['customer'] ?? '')),
                    'unit' => trim((string) ($data['unit'] ?? '')),
                ]
            );

            $item->boms()->delete();
            foreach ($data['lines'] as $index => $line) {
                $lineRole = $this->cleanCode($line['line_role'] ?? '') ?: ('DONG-' . ($index + 1));
                $item->boms()->create([
                    'material_code' => $this->cleanCode($line['material_code']),
                    'line_role' => $lineRole,
                    'material_name' => trim((string) ($line['material_name'] ?? '')),
                    'unit' => trim((string) ($line['unit'] ?? '')),
                    'consumption_per_unit' => (float) $line['consumption_per_unit'],
                    'waste_percent' => (float) ($line['waste_percent'] ?? 0),
                    'note' => trim((string) ($line['note'] ?? '')),
                ]);
            }

            return $item->load('boms');
        });

        return response()->json(['message' => 'Da luu dinh muc soi.', 'data' => $item]);
    }

    public function importBoms(Request $request)
    {
        $data = $request->validate(['text' => 'required|string']);
        $rows = $this->parseTsv($data['text']);
        $itemsTouched = [];
        $lineCount = 0;
        $validRows = collect($rows)
            ->map(fn ($row) => $this->normalizeBomImportRow($row))
            ->filter(fn ($row) => $row['item_code'] !== '' && $row['material_code'] !== '')
            ->values();
        $missingCatalog = $this->missingCatalogCodes($validRows->pluck('material_code')->all());
        if (!empty($missingCatalog)) {
            return response()->json([
                'message' => 'Import dừng: mã sợi chưa có trong DANH MỤC nội bộ: ' . implode(', ', array_slice($missingCatalog, 0, 20)),
                'missing_catalog' => $missingCatalog,
            ], 422);
        }

        $itemCodes = $validRows->pluck('item_code')->unique()->values();
        $materialCatalog = $this->catalogByCodes($validRows->pluck('material_code')->all());
        $itemCatalog = $this->catalogByCodes($itemCodes->all());
        $productionNames = InternalProductionOrder::query()
            ->where('is_active', true)
            ->whereIn('item_code', $itemCodes->all())
            ->select('item_code', DB::raw('MIN(description) as description'), DB::raw('MIN(customer) as customer'), DB::raw('MIN(unit) as unit'))
            ->groupBy('item_code')
            ->get()
            ->keyBy(fn ($row) => $this->cleanCode($row->item_code));

        DB::connection('internal')->transaction(function () use ($validRows, $materialCatalog, $itemCatalog, $productionNames, &$itemsTouched, &$lineCount) {
            foreach ($validRows as $index => $row) {
                $itemCode = $row['item_code'];
                $materialCode = $row['material_code'];
                $lineRole = $row['line_role'] ?: ('DONG-' . ($index + 1));
                $itemCatalogRow = $itemCatalog[$itemCode] ?? null;
                $productionRow = $productionNames[$itemCode] ?? null;
                $materialCatalogRow = $materialCatalog[$materialCode] ?? null;

                $item = InternalWeavingItem::query()->firstOrCreate(
                    ['item_code' => $itemCode],
                    [
                        'item_name' => trim((string) ($itemCatalogRow->item_name ?? $productionRow->description ?? '')),
                        'customer' => trim((string) ($itemCatalogRow->customer ?? $productionRow->customer ?? '')),
                        'unit' => trim((string) ($itemCatalogRow->unit ?? $productionRow->unit ?? '')),
                    ]
                );
                if (trim((string) $item->item_name) === '') {
                    $item->item_name = trim((string) ($itemCatalogRow->item_name ?? $productionRow->description ?? ''));
                    $item->customer = trim((string) ($item->customer ?: ($itemCatalogRow->customer ?? $productionRow->customer ?? '')));
                    $item->unit = trim((string) ($item->unit ?: ($itemCatalogRow->unit ?? $productionRow->unit ?? '')));
                    $item->save();
                }

                InternalWeavingBom::query()->updateOrCreate(
                    ['weaving_item_id' => $item->id, 'material_code' => $materialCode, 'line_role' => $lineRole],
                    [
                        'material_name' => trim((string) ($materialCatalogRow->item_name ?? '')),
                        'unit' => trim((string) ($row['unit'] ?: ($materialCatalogRow->unit ?? ''))),
                        'consumption_per_unit' => $row['consumption_per_unit'],
                        'waste_percent' => $row['waste_percent'],
                        'note' => $row['note'],
                    ]
                );
                $itemsTouched[$itemCode] = true;
                $lineCount++;
            }
        });

        return response()->json([
            'message' => 'Da import dinh muc.',
            'data' => ['item_count' => count($itemsTouched), 'line_count' => $lineCount],
        ]);
    }

    public function importDesignSheet(Request $request)
    {
        $data = $request->validate(['text' => 'required|string']);
        $parsed = $this->parseDesignSheet($data['text']);

        if ($parsed['order_code'] === '' || empty($parsed['lines'])) {
            return response()->json([
                'message' => 'Không đọc được phiếu lệnh dệt. Cần có LỆNH IN và bảng chỉ có TL/1PCS.',
                'parsed' => $parsed,
            ], 422);
        }

        $resolved = $this->applyCentralProductionOrder($parsed);
        if ($resolved['error']) {
            return response()->json([
                'message' => $resolved['error']['message'],
                'source_error' => $resolved['error'],
                'parsed' => $parsed,
            ], 422);
        }
        $parsed = $resolved['parsed'];

        $missingCatalog = $this->missingCatalogCodes(collect($parsed['lines'])->pluck('material_code')->all());
        if (!empty($missingCatalog)) {
            return response()->json([
                'message' => 'Import dung: ma soi/chi chua co trong DANH MUC noi bo: ' . implode(', ', array_slice($missingCatalog, 0, 20)),
                'missing_catalog' => $missingCatalog,
                'parsed' => $parsed,
            ], 422);
        }

        $result = $this->saveParsedDesignSheet($parsed);

        return response()->json([
            'message' => 'Da import phieu lenh det.',
            'data' => $result,
            'parsed' => $parsed,
        ]);
    }

    public function importDesignWorkbook(Request $request)
    {
        $data = $request->validate([
            'file' => 'required|file|mimes:xlsx,xlsm,xls,ods|max:204800',
            'dry_run' => 'nullable|boolean',
            'skip_missing_catalog' => 'nullable|boolean',
            'max_sheets' => 'nullable|integer|min:1|max:20000',
        ]);

        @set_time_limit(0);
        $path = $data['file']->getRealPath();
        $dryRun = (bool) ($data['dry_run'] ?? false);
        $skipMissingCatalog = (bool) ($data['skip_missing_catalog'] ?? true);
        $maxSheets = (int) ($data['max_sheets'] ?? 20000);

        return $this->importDesignWorkbookFromPath($path, [
            'dry_run' => $dryRun,
            'skip_missing_catalog' => $skipMissingCatalog,
            'max_sheets' => $maxSheets,
            'file_name' => $data['file']->getClientOriginalName(),
            'file_size' => $data['file']->getSize(),
            'mime' => $data['file']->getClientMimeType(),
        ]);
    }

    public function importDesignWorkbookChunk(Request $request)
    {
        $data = $request->validate([
            'upload_id' => 'required|string|max:120',
            'chunk_index' => 'required|integer|min:0|max:100000',
            'total_chunks' => 'required|integer|min:1|max:100000',
            'file_name' => 'required|string|max:255',
            'chunk' => 'required|file|max:6144',
            'skip_missing_catalog' => 'nullable|boolean',
            'dry_run' => 'nullable|boolean',
            'max_sheets' => 'nullable|integer|min:1|max:20000',
        ]);

        @set_time_limit(0);
        $uploadId = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $data['upload_id']);
        if ($uploadId === '') {
            return response()->json(['message' => 'Upload id khong hop le.'], 422);
        }

        $dir = storage_path('app/weaving-imports/' . $uploadId);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $chunkIndex = (int) $data['chunk_index'];
        $totalChunks = (int) $data['total_chunks'];
        $chunkPath = $dir . DIRECTORY_SEPARATOR . sprintf('%06d.part', $chunkIndex);
        $data['chunk']->move($dir, basename($chunkPath));

        if ($chunkIndex + 1 < $totalChunks) {
            return response()->json([
                'message' => 'Da nhan chunk ' . ($chunkIndex + 1) . '/' . $totalChunks,
                'done' => false,
                'received' => $chunkIndex + 1,
                'total_chunks' => $totalChunks,
            ]);
        }

        for ($i = 0; $i < $totalChunks; $i++) {
            if (!is_file($dir . DIRECTORY_SEPARATOR . sprintf('%06d.part', $i))) {
                return response()->json([
                    'message' => 'Thieu chunk ' . ($i + 1) . '/' . $totalChunks . '. Hay import lai file.',
                    'done' => false,
                ], 422);
            }
        }

        $safeName = preg_replace('/[^A-Za-z0-9_\.\-]/', '_', (string) $data['file_name']);
        $assembledPath = $dir . DIRECTORY_SEPARATOR . ($safeName ?: 'workbook.xlsx');
        $out = fopen($assembledPath, 'wb');
        for ($i = 0; $i < $totalChunks; $i++) {
            $partPath = $dir . DIRECTORY_SEPARATOR . sprintf('%06d.part', $i);
            $in = fopen($partPath, 'rb');
            stream_copy_to_stream($in, $out);
            fclose($in);
        }
        fclose($out);

        try {
            $response = $this->importDesignWorkbookFromPath($assembledPath, [
                'dry_run' => (bool) ($data['dry_run'] ?? false),
                'skip_missing_catalog' => (bool) ($data['skip_missing_catalog'] ?? true),
                'max_sheets' => (int) ($data['max_sheets'] ?? 20000),
                'file_name' => $data['file_name'],
                'file_size' => filesize($assembledPath),
                'mime' => 'chunked-upload',
                'chunked' => true,
            ]);
            return $response;
        } finally {
            foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }

    private function importDesignWorkbookFromPath(string $path, array $options)
    {
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $skipMissingCatalog = (bool) ($options['skip_missing_catalog'] ?? true);
        $maxSheets = (int) ($options['max_sheets'] ?? 20000);

        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $sheetNames = array_slice($reader->listWorksheetNames($path), 0, $maxSheets);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Khong doc duoc file Excel. File co the bi loi, qua lon, hoac khong dung dinh dang xlsx/xls.',
                'debug' => [
                    'file_name' => $options['file_name'] ?? basename($path),
                    'file_size' => $options['file_size'] ?? (is_file($path) ? filesize($path) : null),
                    'mime' => $options['mime'] ?? '',
                    'error' => $e->getMessage(),
                ],
            ], 422);
        }

        $summary = [
            'total_sheets' => count($sheetNames),
            'imported' => 0,
            'skipped' => 0,
            'errors' => 0,
            'missing_catalog_count' => 0,
            'dry_run' => $dryRun,
        ];
        $errors = [];
        $samples = [];
        $centralOrders = $this->centralProductionOrderMap();

        foreach ($sheetNames as $sheetName) {
            try {
                $reader = IOFactory::createReaderForFile($path);
                $reader->setReadDataOnly(true);
                $reader->setLoadSheetsOnly([$sheetName]);
                $spreadsheet = $reader->load($path);
                $sheet = $spreadsheet->getSheet(0);
                $rows = $this->worksheetToRows($sheet);
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);

                $parsed = $this->parseDesignSheetRows($rows, $sheetName);
                if ($parsed['order_code'] === '' || empty($parsed['lines'])) {
                    $summary['skipped']++;
                    $errors[] = ['sheet' => $sheetName, 'message' => 'Không đúng form lệnh dệt hoặc thiếu LỆNH IN/BOM.'];
                    continue;
                }

                $resolved = $this->applyCentralProductionOrder($parsed, $centralOrders);
                if ($resolved['error']) {
                    $summary['errors']++;
                    $errors[] = array_merge(['sheet' => $sheetName], $resolved['error']);
                    continue;
                }
                $parsed = $resolved['parsed'];

                $missingCatalog = $this->missingCatalogCodes(collect($parsed['lines'])->pluck('material_code')->all());
                if (!empty($missingCatalog)) {
                    $summary['missing_catalog_count'] += count($missingCatalog);
                    if (!$skipMissingCatalog) {
                        $summary['errors']++;
                        $errors[] = ['sheet' => $sheetName, 'message' => 'Thieu ma soi trong danh muc.', 'missing_catalog' => $missingCatalog];
                        continue;
                    }
                    $parsed['warnings'][] = 'Thieu danh muc: ' . implode(', ', array_slice($missingCatalog, 0, 20));
                }

                if (!$dryRun) {
                    $this->saveParsedDesignSheet($parsed, $skipMissingCatalog ? $missingCatalog : []);
                }

                $summary['imported']++;
                if (count($samples) < 10) {
                    $samples[] = [
                        'sheet' => $sheetName,
                        'order_code' => $parsed['order_code'],
                        'item_code' => $parsed['item_code'],
                        'customer' => $parsed['customer'],
                        'line_count' => count($parsed['lines']),
                        'warnings' => $parsed['warnings'],
                    ];
                }
            } catch (\Throwable $e) {
                $summary['errors']++;
                $errors[] = ['sheet' => $sheetName, 'message' => $e->getMessage()];
            }
        }

        return response()->json([
            'message' => $dryRun ? 'Da kiem tra file lenh det.' : 'Da import file lenh det.',
            'summary' => $summary,
            'samples' => $samples,
            'errors' => array_slice($errors, 0, 200),
        ]);
    }

    public function orders(Request $request)
    {
        $keyword = mb_strtoupper(trim((string) $request->query('keyword', '')));
        $status = trim((string) $request->query('status', ''));
        $page = max((int) $request->query('page', 1), 1);
        $perPage = min(max((int) $request->query('per_page', 50), 25), 200);

        $query = InternalWeavingOrder::query()
            ->with('item:id,item_code,item_name')
            ->orderByDesc('order_date')
            ->orderByDesc('id');

        if ($keyword !== '') {
            $query->where(function ($sub) use ($keyword) {
                $sub->whereRaw('UPPER(order_code) LIKE ?', ["%{$keyword}%"])
                    ->orWhereRaw('UPPER(item_code) LIKE ?', ["%{$keyword}%"])
                    ->orWhereRaw('UPPER(customer) LIKE ?', ["%{$keyword}%"]);
            });
        }
        if ($status !== '') {
            $query->where('status', $status);
        }

        $total = (clone $query)->count();
        $rows = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return response()->json([
            'data' => $rows,
            'summary' => [
                'order_count' => InternalWeavingOrder::query()->count(),
                'draft_count' => InternalWeavingOrder::query()->where('status', 'draft')->count(),
                'issued_count' => InternalWeavingOrder::query()->where('status', 'issued')->count(),
            ],
            'pagination' => $this->pagination($page, $perPage, $total),
        ]);
    }

    public function productionOrders(Request $request)
    {
        $keyword = mb_strtoupper(trim((string) $request->query('keyword', '')));
        $customer = mb_strtoupper(trim((string) $request->query('customer', '')));
        $status = trim((string) $request->query('status', ''));
        $hasBom = filter_var($request->query('has_bom', false), FILTER_VALIDATE_BOOLEAN);
        $year = (int) $request->query('year', 0);
        $page = max((int) $request->query('page', 1), 1);
        $perPage = min(max((int) $request->query('per_page', 50), 25), 200);

        $baseQuery = InternalProductionOrder::query()->where('is_active', true);
        if ($year >= 2000 && $year <= 2099) {
            $suffix = '/' . str_pad((string) ($year % 100), 2, '0', STR_PAD_LEFT);
            $baseQuery->whereRaw('TRIM(production_order) LIKE ?', ['%' . $suffix]);
        }
        if ($keyword !== '') {
            $baseQuery->where(function ($sub) use ($keyword) {
                $like = '%' . $keyword . '%';
                $sub->whereRaw('UPPER(production_order) LIKE ?', [$like])
                    ->orWhereRaw('UPPER(purchase_order) LIKE ?', [$like])
                    ->orWhereRaw('UPPER(customer) LIKE ?', [$like])
                    ->orWhereRaw('UPPER(item_code) LIKE ?', [$like])
                    ->orWhereRaw('UPPER(description) LIKE ?', [$like])
                    ->orWhereRaw('UPPER(size) LIKE ?', [$like])
                    ->orWhereRaw('UPPER(color) LIKE ?', [$like]);
            });
        }
        if ($status !== '') {
            $baseQuery->where('status', $status);
        }
        if ($hasBom) {
            $this->filterProductionOrdersWithBom($baseQuery);
        }
        $customers = (clone $baseQuery)
            ->whereNotNull('customer')
            ->where('customer', '<>', '')
            ->select('customer')
            ->distinct()
            ->orderBy('customer')
            ->pluck('customer')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values();
        if ($customer !== '') {
            $baseQuery->whereRaw('UPPER(TRIM(customer)) = ?', [$customer]);
        }

        $groupQuery = (clone $baseQuery)
            ->select(
                'production_order',
                DB::raw('MIN(id) as first_id'),
                DB::raw('MIN(received_date) as first_date'),
                DB::raw('MIN(promised_date) as promised_date'),
                DB::raw('MIN(customer) as customer'),
                DB::raw('MIN(item_code) as first_item_code'),
                DB::raw('MIN(description) as first_description'),
                DB::raw('MIN(unit) as unit'),
                DB::raw('SUM(order_quantity) as planned_quantity'),
                DB::raw('COUNT(*) as line_count'),
                DB::raw('COUNT(DISTINCT item_code) as item_count')
            )
            ->whereNotNull('production_order')
            ->where('production_order', '<>', '')
            ->groupBy('production_order')
            ->orderByDesc(DB::raw('COALESCE(MIN(received_date), MIN(created_at))'))
            ->orderByDesc('first_id');

        $total = DB::connection('internal')
            ->query()
            ->fromSub((clone $groupQuery), 'grouped_orders')
            ->count();

        $rows = $groupQuery
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(function ($row) {
                return [
                    'production_order' => trim((string) $row->production_order),
                    'item_code' => trim((string) $row->first_item_code),
                    'description' => trim((string) $row->first_description),
                    'customer' => trim((string) $row->customer),
                    'planned_quantity' => (float) $row->planned_quantity,
                    'unit' => trim((string) $row->unit),
                    'received_date' => $row->first_date,
                    'promised_date' => $row->promised_date,
                    'line_count' => (int) $row->line_count,
                    'item_count' => (int) $row->item_count,
                ];
            });

        return response()->json([
            'data' => $rows,
            'customers' => $customers,
            'summary' => [
                'order_count' => (clone $baseQuery)->distinct()->count('production_order'),
                'line_count' => (clone $baseQuery)->count(),
                'total_quantity' => (float) (clone $baseQuery)->sum('order_quantity'),
            ],
            'pagination' => $this->pagination($page, $perPage, $total),
        ]);
    }

    public function materialSuggestions(Request $request)
    {
        $keyword = mb_strtoupper(trim((string) $request->query('keyword', '')));
        if (mb_strlen($keyword) < 1) {
            return response()->json(['data' => []]);
        }

        $rows = InternalItemCatalog::query()
            ->where('is_active', true)
            ->whereNotNull('item_code')
            ->where('item_code', '<>', '')
            ->where(function ($query) use ($keyword) {
                $like = '%' . $keyword . '%';
                $query->whereRaw('UPPER(item_code) LIKE ?', [$like])
                    ->orWhereRaw('UPPER(item_name) LIKE ?', [$like])
                    ->orWhereRaw('UPPER(color) LIKE ?', [$like]);
            })
            ->orderByRaw('CASE WHEN UPPER(item_code) = ? THEN 0 WHEN UPPER(item_code) LIKE ? THEN 1 ELSE 2 END', [$keyword, $keyword . '%'])
            ->orderBy('item_code')
            ->limit(20)
            ->get(['item_code', 'item_name', 'unit', 'shelf_code', 'color']);

        return response()->json([
            'data' => $rows->map(fn ($row) => [
                'item_code' => trim((string) $row->item_code),
                'item_name' => trim((string) $row->item_name),
                'unit' => trim((string) $row->unit),
                'shelf_code' => trim((string) $row->shelf_code),
                'color' => trim((string) $row->color),
            ])->values(),
        ]);
    }

    private function filterProductionOrdersWithBom($query)
    {
        $itemCodes = DB::connection('internal')
            ->table('internal_weaving_items as weaving_item')
            ->join('internal_weaving_boms as weaving_bom', 'weaving_bom.weaving_item_id', '=', 'weaving_item.id')
            ->whereNotNull('weaving_bom.material_code')
            ->where('weaving_bom.material_code', '<>', '')
            ->where('weaving_bom.consumption_per_unit', '>', 0)
            ->distinct()
            ->pluck('weaving_item.item_code')
            ->map(fn ($code) => trim((string) $code))
            ->filter()
            ->values();

        if ($itemCodes->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('item_code', $itemCodes->all());
    }

    public function storeOrder(Request $request)
    {
        $data = $request->validate([
            'order_code' => 'required|string|max:120',
            'item_code' => 'required|string|max:120',
            'customer' => 'nullable|string|max:200',
            'order_quantity' => 'required|numeric|min:0.001',
            'unit' => 'nullable|string|max:50',
            'order_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'status' => 'nullable|string|max:40',
            'note' => 'nullable|string|max:2000',
        ]);
        $data = $this->normalizeDateFields($data, ['order_date', 'due_date']);

        $itemCode = $this->cleanCode($data['item_code']);
        $item = InternalWeavingItem::query()->firstOrCreate(['item_code' => $itemCode]);

        $order = InternalWeavingOrder::query()->updateOrCreate(
            ['order_code' => $this->cleanCode($data['order_code'])],
            [
                'weaving_item_id' => $item->id,
                'item_code' => $itemCode,
                'customer' => trim((string) ($data['customer'] ?? $item->customer ?? '')),
                'order_quantity' => (float) $data['order_quantity'],
                'unit' => trim((string) ($data['unit'] ?? $item->unit ?? '')),
                'order_date' => $data['order_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'status' => trim((string) ($data['status'] ?? 'draft')) ?: 'draft',
                'note' => trim((string) ($data['note'] ?? '')),
            ]
        );

        return response()->json(['message' => 'Da luu lenh det.', 'data' => $order->load('item')]);
    }

    public function importOrders(Request $request)
    {
        $data = $request->validate(['text' => 'required|string']);
        $rows = $this->parseTsv($data['text']);
        $created = 0;
        $updated = 0;

        DB::connection('internal')->transaction(function () use ($rows, &$created, &$updated) {
            foreach ($rows as $row) {
                $orderCode = $this->cleanCode($row[0] ?? '');
                $itemCode = $this->cleanCode($row[1] ?? '');
                if ($orderCode === '' || $itemCode === '') continue;
                $item = InternalWeavingItem::query()->firstOrCreate(
                    ['item_code' => $itemCode],
                    ['item_name' => trim((string) ($row[2] ?? ''))]
                );
                $order = InternalWeavingOrder::query()->updateOrCreate(
                    ['order_code' => $orderCode],
                    [
                        'weaving_item_id' => $item->id,
                        'item_code' => $itemCode,
                        'customer' => trim((string) ($row[3] ?? '')),
                        'order_quantity' => $this->toNumber($row[4] ?? 0),
                        'unit' => trim((string) ($row[5] ?? '')),
                        'order_date' => $this->dateOrNull($row[6] ?? ''),
                        'due_date' => $this->dateOrNull($row[7] ?? ''),
                        'status' => trim((string) ($row[8] ?? 'draft')) ?: 'draft',
                        'note' => trim((string) ($row[9] ?? '')),
                    ]
                );
                $order->wasRecentlyCreated ? $created++ : $updated++;
            }
        });

        return response()->json(['message' => 'Da import lenh det.', 'data' => compact('created', 'updated')]);
    }

    public function plan(Request $request, InternalWeavingOrder $order)
    {
        $order->load('item.boms');
        $bomRows = $order->item ? $order->item->boms : collect();
        $orderMetadata = json_decode((string) ($order->metadata_json ?? ''), true) ?: [];
        $itemMetadata = json_decode((string) ($order->item->metadata_json ?? ''), true) ?: [];
        $itemCatalog = InternalItemCatalog::query()
            ->whereRaw('UPPER(TRIM(item_code)) = ?', [$this->cleanCode($order->item_code)])
            ->where('is_active', true)
            ->first();
        $materialCodes = $bomRows->pluck('material_code')->map(fn ($code) => $this->cleanCode($code))->filter()->unique()->values();
        $stock = $this->stockByMaterial($materialCodes->all());
        $catalog = InternalItemCatalog::query()
            ->whereIn('item_code', $materialCodes->all())
            ->get()
            ->keyBy(fn ($row) => $this->cleanCode($row->item_code));
        $unitConverter = app(InternalUnitConverter::class);

        $lines = $bomRows->map(function (InternalWeavingBom $bom) use ($order, $stock, $catalog, $unitConverter) {
            $code = $this->cleanCode($bom->material_code);
            $bomMetadata = json_decode((string) ($bom->metadata_json ?? ''), true) ?: [];
            $requiredRaw = round((float) $order->order_quantity * (float) $bom->consumption_per_unit * (1 + ((float) $bom->waste_percent / 100)), 3);
            $stockRow = $stock[$code] ?? ['quantity' => 0, 'locations' => collect()];
            $locations = collect($stockRow['locations'])->values();
            $catalogRow = $catalog[$code] ?? null;
            $base = $unitConverter->toBase($code, $requiredRaw, $bom->unit ?? '', $catalogRow->unit ?? ($bom->unit ?? ''));
            $required = round((float) $base['quantity'], 3);
            $stockQuantity = (float) $stockRow['quantity'];
            if ($locations->isEmpty() && $catalogRow && trim((string) $catalogRow->shelf_code) !== '') {
                $stockQuantity = max($stockQuantity, (float) $catalogRow->opening_quantity);
                $locations = collect([[
                    'location_code' => $catalogRow->shelf_code,
                    'quantity' => (float) $catalogRow->opening_quantity,
                    'color' => $catalogRow->color ?? '',
                    'pantone_hex' => $catalogRow->pantone_hex ?? '',
                ]]);
            }

            return [
                'material_code' => $code,
                'material_name' => $catalogRow->item_name ?? $bom->material_name ?? '',
                'unit' => $base['unit'] ?: ($catalogRow->unit ?? $bom->unit ?? ''),
                'bom_unit' => $bom->unit ?? '',
                'required_quantity_raw' => $requiredRaw,
                'converted' => (bool) $base['converted'],
                'conversion_factor' => (float) $base['factor'],
                'catalog_exists' => (bool) $catalogRow,
                'catalog_name' => $catalogRow->item_name ?? '',
                'catalog_unit' => $catalogRow->unit ?? '',
                'catalog_shelf_code' => $catalogRow->shelf_code ?? '',
                'type' => $bomMetadata['type'] ?? '',
                'shelf_hint' => $bomMetadata['shelf_hint'] ?? '',
                'total_grams' => (float) ($bomMetadata['total_grams'] ?? 0),
                'consumption_per_unit' => (float) $bom->consumption_per_unit,
                'waste_percent' => (float) $bom->waste_percent,
                'required_quantity' => $required,
                'stock_quantity' => $stockQuantity,
                'shortage_quantity' => max(0, $required - $stockQuantity),
                'locations' => $locations,
                'first_location' => $locations->first()['location_code'] ?? '',
                'status' => $stockQuantity >= $required ? 'enough' : 'short',
                'note' => $bom->note,
            ];
        })->values();

        return response()->json([
            'order' => [
                'production_order' => $order->order_code,
                'order_code' => $order->order_code,
                'customer' => $order->customer,
                'item_code' => $order->item_code,
                'line_count' => 1,
                'item_count' => 1,
                'planned_quantity' => (float) $order->order_quantity,
                'unit' => $order->unit,
                'po_number' => $order->po_number,
                'design_code' => $order->design_code,
                'order_date' => optional($order->order_date)->format('Y-m-d'),
                'due_date' => optional($order->due_date)->format('Y-m-d'),
                'note' => $order->note,
                'catalog_id' => $itemCatalog ? (int) $itemCatalog->id : null,
                'image_url' => $itemCatalog->image_url ?? '',
                'metadata' => array_merge($itemMetadata, $orderMetadata),
            ],
            'source_items' => [[
                'item_code' => $order->item_code,
                'item_name' => $order->item->item_name ?? '',
                'catalog_id' => $itemCatalog ? (int) $itemCatalog->id : null,
                'image_url' => $itemCatalog->image_url ?? '',
                'design_code' => $order->design_code,
                'po_number' => $order->po_number,
                'metadata' => $itemMetadata,
                'customer' => $order->customer,
                'order_quantity' => (float) $order->order_quantity,
                'unit' => $order->unit,
                'has_bom' => $bomRows->isNotEmpty(),
                'material_count' => $lines->count(),
                'required_quantity' => (float) $lines->sum('required_quantity'),
                'materials' => $lines->values(),
            ]],
            'data' => $lines,
            'summary' => [
                'line_count' => $lines->count(),
                'required_quantity' => (float) $lines->sum('required_quantity'),
                'short_count' => $lines->where('status', 'short')->count(),
                'missing_catalog_count' => $lines->where('catalog_exists', false)->count(),
                'source_item_count' => 1,
                'missing_bom_count' => $bomRows->isEmpty() ? 1 : 0,
                'missing_bom_items' => $bomRows->isEmpty() ? [$order->item_code] : [],
            ],
        ]);
    }

    public function productionPlan(Request $request)
    {
        $productionOrder = trim((string) $request->query('production_order', ''));
        if ($productionOrder === '') {
            return response()->json(['message' => 'Thiếu lệnh sản xuất.'], 422);
        }

        $normalizedOrderCode = $this->cleanCode($productionOrder);
        $weavingOrder = InternalWeavingOrder::query()
            ->with('item')
            ->whereRaw('UPPER(TRIM(order_code)) = ?', [$normalizedOrderCode])
            ->first();
        $sourceLines = InternalProductionOrder::query()
            ->where('is_active', true)
            ->whereRaw('UPPER(TRIM(production_order)) = ?', [$normalizedOrderCode])
            ->orderBy('id')
            ->get();

        if ($sourceLines->isEmpty()) {
            $internalOrder = InternalWeavingOrder::query()
                ->whereRaw('UPPER(TRIM(order_code)) = ?', [$normalizedOrderCode])
                ->first();
            if ($internalOrder) {
                return $this->plan($request, $internalOrder);
            }

            return response()->json(['message' => 'Không tìm thấy lệnh sản xuất: ' . $productionOrder], 404);
        }

        $itemCodes = $sourceLines->pluck('item_code')->map(fn ($code) => $this->cleanCode($code))->filter()->unique()->values();
        $weavingOrderMatchesSource = $weavingOrder
            && $itemCodes->contains($this->cleanCode($weavingOrder->item_code));
        $matchedWeavingOrder = $weavingOrderMatchesSource ? $weavingOrder : null;
        $items = InternalWeavingItem::query()
            ->with('boms')
            ->whereIn('item_code', $itemCodes->all())
            ->get()
            ->keyBy(fn ($item) => $this->cleanCode($item->item_code));
        $sourceCatalogs = InternalItemCatalog::query()
            ->whereIn('item_code', $itemCodes->all())
            ->where('is_active', true)
            ->get()
            ->keyBy(fn ($row) => $this->cleanCode($row->item_code));
        $weavingOrderMetadata = json_decode((string) ($matchedWeavingOrder->metadata_json ?? ''), true) ?: [];

        $materialRequirements = [];
        $missingBomItems = [];
        $sourceItemBreakdown = [];

        foreach ($sourceLines as $sourceLine) {
            $itemCode = $this->cleanCode($sourceLine->item_code);
            $item = $items[$itemCode] ?? null;
            $sourceCatalog = $sourceCatalogs[$itemCode] ?? null;
            $itemMetadata = json_decode((string) ($item->metadata_json ?? ''), true) ?: [];
            $sourceItem = [
                'item_code' => $itemCode,
                'item_name' => trim((string) ($item->item_name ?? $sourceLine->description)),
                'catalog_id' => $sourceCatalog ? (int) $sourceCatalog->id : null,
                'image_url' => $sourceCatalog->image_url ?? '',
                'design_code' => trim((string) ($item->design_code ?? $matchedWeavingOrder->design_code ?? '')),
                'po_number' => trim((string) ($matchedWeavingOrder->po_number ?? $sourceLine->purchase_order ?? '')),
                'metadata' => $itemMetadata,
                'customer' => trim((string) ($matchedWeavingOrder->customer ?? $sourceLine->customer)),
                'size' => trim((string) $sourceLine->size),
                'color' => trim((string) $sourceLine->color),
                'order_quantity' => (float) $sourceLine->order_quantity,
                'unit' => trim((string) $sourceLine->unit),
                'has_bom' => (bool) ($item && $item->boms->isNotEmpty()),
                'materials' => [],
            ];

            if (!$item || $item->boms->isEmpty()) {
                $missingBomItems[$itemCode ?: 'CHUA-CO-MA-HANG'] = true;
                $sourceItemBreakdown[] = $sourceItem;
                continue;
            }

            foreach ($item->boms as $bom) {
                $materialCode = $this->cleanCode($bom->material_code);
                if ($materialCode === '') continue;
                $bomMetadata = json_decode((string) ($bom->metadata_json ?? ''), true) ?: [];
                $required = round((float) $sourceLine->order_quantity * (float) $bom->consumption_per_unit * (1 + ((float) $bom->waste_percent / 100)), 3);
                $sourceItem['materials'][] = [
                    'material_code' => $materialCode,
                    'line_role' => trim((string) $bom->line_role),
                    'material_name' => trim((string) $bom->material_name),
                    'unit' => trim((string) $bom->unit),
                    'consumption_per_unit' => (float) $bom->consumption_per_unit,
                    'waste_percent' => (float) $bom->waste_percent,
                    'required_quantity' => $required,
                    'type' => $bomMetadata['type'] ?? '',
                    'shelf_hint' => $bomMetadata['shelf_hint'] ?? '',
                    'total_grams' => (float) ($bomMetadata['total_grams'] ?? 0),
                    'note' => trim((string) $bom->note),
                ];
                if (!isset($materialRequirements[$materialCode])) {
                    $materialRequirements[$materialCode] = [
                        'material_code' => $materialCode,
                        'material_name' => $bom->material_name,
                        'unit' => $bom->unit,
                        'required_quantity' => 0,
                        'source_items' => [],
                    ];
                }
                $materialRequirements[$materialCode]['required_quantity'] += $required;
                $materialRequirements[$materialCode]['source_items'][] = [
                    'item_code' => $itemCode,
                    'description' => trim((string) $sourceLine->description),
                    'quantity' => (float) $sourceLine->order_quantity,
                    'required_quantity' => $required,
                ];
            }

            $sourceItemBreakdown[] = $sourceItem;
        }

        $materialCodes = array_keys($materialRequirements);
        $stock = $this->stockByMaterial($materialCodes);
        $catalog = $this->catalogByCodes($materialCodes);
        $unitConverter = app(InternalUnitConverter::class);

        $sourceItems = collect($sourceItemBreakdown)->map(function ($sourceItem) use ($catalog, $unitConverter) {
            $materials = collect($sourceItem['materials'])->map(function ($material) use ($catalog, $unitConverter) {
                $code = $this->cleanCode($material['material_code'] ?? '');
                $catalogRow = $catalog[$code] ?? null;
                $base = $unitConverter->toBase(
                    $code,
                    (float) ($material['required_quantity'] ?? 0),
                    $material['unit'] ?? '',
                    $catalogRow->unit ?? ($material['unit'] ?? '')
                );

                return array_merge($material, [
                    'material_name' => $catalogRow->item_name ?? $material['material_name'] ?? '',
                    'unit' => $base['unit'] ?: ($catalogRow->unit ?? $material['unit'] ?? ''),
                    'bom_unit' => $material['unit'] ?? '',
                    'required_quantity_raw' => (float) ($material['required_quantity'] ?? 0),
                    'required_quantity' => (float) $base['quantity'],
                    'converted' => (bool) $base['converted'],
                    'conversion_factor' => (float) $base['factor'],
                    'catalog_exists' => (bool) $catalogRow,
                    'catalog_shelf_code' => $catalogRow->shelf_code ?? '',
                    'catalog_color' => $catalogRow->color ?? '',
                    'pantone_hex' => $catalogRow->pantone_hex ?? '',
                ]);
            })->values();

            $sourceItem['materials'] = $materials;
            $sourceItem['material_count'] = $materials->count();
            $sourceItem['required_quantity'] = (float) $materials->sum('required_quantity');

            return $sourceItem;
        })->values();

        $lines = collect($materialRequirements)->map(function ($row) use ($stock, $catalog, $unitConverter) {
            $code = $row['material_code'];
            $catalogRow = $catalog[$code] ?? null;
            $stockRow = $stock[$code] ?? ['quantity' => 0, 'locations' => collect()];
            $stockQuantity = (float) $stockRow['quantity'];
            $locations = collect($stockRow['locations'])->values();

            if ($locations->isEmpty() && $catalogRow && trim((string) $catalogRow->shelf_code) !== '') {
                $stockQuantity = max($stockQuantity, (float) $catalogRow->opening_quantity);
                $locations = collect([[
                    'location_code' => $catalogRow->shelf_code,
                    'quantity' => (float) $catalogRow->opening_quantity,
                    'color' => $catalogRow->color ?? '',
                    'pantone_hex' => $catalogRow->pantone_hex ?? '',
                ]]);
            }

            $base = $unitConverter->toBase(
                $code,
                (float) $row['required_quantity'],
                $row['unit'] ?? '',
                $catalogRow->unit ?? ($row['unit'] ?? '')
            );
            $required = round((float) $base['quantity'], 3);

            return [
                'material_code' => $code,
                'material_name' => $catalogRow->item_name ?? $row['material_name'] ?? '',
                'unit' => $base['unit'] ?: ($catalogRow->unit ?? $row['unit'] ?? ''),
                'bom_unit' => $row['unit'] ?? '',
                'required_quantity_raw' => round((float) $row['required_quantity'], 3),
                'converted' => (bool) $base['converted'],
                'conversion_factor' => (float) $base['factor'],
                'catalog_exists' => (bool) $catalogRow,
                'catalog_name' => $catalogRow->item_name ?? '',
                'catalog_unit' => $catalogRow->unit ?? '',
                'catalog_shelf_code' => $catalogRow->shelf_code ?? '',
                'required_quantity' => $required,
                'stock_quantity' => $stockQuantity,
                'shortage_quantity' => max(0, $required - $stockQuantity),
                'locations' => $locations,
                'first_location' => $locations->first()['location_code'] ?? '',
                'status' => $stockQuantity >= $required ? 'enough' : 'short',
                'source_items' => collect($row['source_items'])->take(5)->values(),
            ];
        })->sortBy('material_code')->values();

        return response()->json([
            'order' => [
                'production_order' => $productionOrder,
                'weaving_order_id' => $matchedWeavingOrder ? (int) $matchedWeavingOrder->id : null,
                'customer' => trim((string) ($matchedWeavingOrder->customer ?? $sourceLines->pluck('customer')->filter()->first())),
                'item_code' => $itemCodes->count() === 1 ? (string) $itemCodes->first() : '',
                'line_count' => $sourceLines->count(),
                'item_count' => $itemCodes->count(),
                'planned_quantity' => (float) $sourceLines->sum('order_quantity'),
                'unit' => trim((string) ($matchedWeavingOrder->unit ?? $sourceLines->pluck('unit')->filter()->first())),
                'po_number' => trim((string) ($matchedWeavingOrder->po_number ?? $sourceLines->pluck('purchase_order')->filter()->first())),
                'design_code' => trim((string) ($matchedWeavingOrder->design_code ?? '')),
                'order_date' => optional($matchedWeavingOrder->order_date ?? null)->format('Y-m-d')
                    ?: optional($sourceLines->pluck('received_date')->filter()->sort()->first())->format('Y-m-d'),
                'due_date' => optional($sourceLines->pluck('promised_date')->filter()->sort()->first())->format('Y-m-d'),
                'note' => trim((string) ($matchedWeavingOrder->note ?? '')),
                'catalog_id' => $itemCodes->count() === 1
                    ? (int) (($sourceCatalogs[$itemCodes->first()]->id ?? 0))
                    : null,
                'image_url' => $itemCodes->count() === 1
                    ? (string) (($sourceCatalogs[$itemCodes->first()]->image_url ?? ''))
                    : '',
                'metadata' => array_merge(
                    $itemCodes->count() === 1
                        ? (json_decode((string) (($items[$itemCodes->first()]->metadata_json ?? '')), true) ?: [])
                        : [],
                    $weavingOrderMetadata
                ),
                'ignored_weaving_order_mismatch' => $weavingOrder && !$weavingOrderMatchesSource
                    ? [
                        'weaving_order_id' => (int) $weavingOrder->id,
                        'imported_item_code' => trim((string) $weavingOrder->item_code),
                        'expected_item_codes' => $itemCodes->values(),
                    ]
                    : null,
            ],
            'source_items' => $sourceItems,
            'data' => $lines,
            'summary' => [
                'line_count' => $lines->count(),
                'required_quantity' => (float) $lines->sum('required_quantity'),
                'short_count' => $lines->where('status', 'short')->count(),
                'missing_catalog_count' => $lines->where('catalog_exists', false)->count(),
                'source_item_count' => $sourceItems->count(),
                'missing_bom_count' => count($missingBomItems),
                'missing_bom_items' => array_keys($missingBomItems),
            ],
        ]);
    }

    public function exportExcel(Request $request, WeavingExcelBatchExporter $exporter)
    {
        $data = $request->validate([
            'production_order' => 'required|string|max:120',
        ]);
        $productionOrder = trim($data['production_order']);
        $planResponse = $this->productionPlan(Request::create(
            '/api/lenh-det/production-order-plan',
            'GET',
            ['production_order' => $productionOrder]
        ));
        $plan = $planResponse->getData(true);
        if ($planResponse->getStatusCode() >= 400) {
            return response()->json($plan, $planResponse->getStatusCode());
        }

        $sourceItemCodes = collect($plan['source_items'] ?? [])
            ->pluck('item_code')
            ->map(fn ($code) => $this->cleanCode($code))
            ->filter()
            ->unique()
            ->values();
        if ($sourceItemCodes->count() !== 1) {
            return response()->json([
                'message' => 'Mẫu LENH_DET chỉ nhận một mã hàng; lệnh có ' . $sourceItemCodes->count() . ' mã.',
            ], 422);
        }
        $templateLineCount = collect($plan['source_items'] ?? [])
            ->flatMap(fn ($item) => (array) ($item['materials'] ?? []))
            ->count();
        if ($templateLineCount === 0) {
            return response()->json(['message' => 'Lệnh chưa có định mức sợi.'], 422);
        }
        if ($templateLineCount > 7) {
            return response()->json([
                'message' => 'Định mức có ' . $templateLineCount . ' dòng, vượt giới hạn 7 dòng của mẫu.',
            ], 422);
        }

        $centralCustomer = InternalProductionOrder::query()
            ->where('is_active', true)
            ->whereRaw('UPPER(TRIM(production_order)) = ?', [$this->cleanCode($productionOrder)])
            ->whereNotNull('customer')
            ->where('customer', '<>', '')
            ->value('customer');
        if (trim((string) $centralCustomer) !== '') {
            $plan['order']['customer'] = trim((string) $centralCustomer);
            foreach ($plan['source_items'] as &$sourceItem) {
                $sourceItem['customer'] = trim((string) $centralCustomer);
            }
            unset($sourceItem);
        }

        try {
            $file = $exporter->single($productionOrder, $plan);

            return response()->download($file['path'], $file['name'], [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'private, no-store',
            ])->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Không tạo được file Excel: ' . $e->getMessage()], 422);
        }
    }

    public function startBatchExport(Request $request, WeavingExcelBatchExporter $exporter)
    {
        $data = $request->validate([
            'select_all' => 'nullable|boolean',
            'production_orders' => 'nullable|array|max:1000',
            'production_orders.*' => 'required|string|max:120',
            'year' => 'nullable|integer|min:2000|max:2099',
            'customer' => 'nullable|string|max:200',
            'keyword' => 'nullable|string|max:200',
        ]);

        $query = InternalProductionOrder::query()
            ->where('is_active', true)
            ->whereNotNull('production_order')
            ->where('production_order', '<>', '');
        $this->filterProductionOrdersWithBom($query);

        if (!empty($data['select_all'])) {
            $year = (int) ($data['year'] ?? 0);
            if ($year >= 2000 && $year <= 2099) {
                $suffix = '/' . str_pad((string) ($year % 100), 2, '0', STR_PAD_LEFT);
                $query->whereRaw('TRIM(production_order) LIKE ?', ['%' . $suffix]);
            }
            $customer = mb_strtoupper(trim((string) ($data['customer'] ?? '')));
            if ($customer !== '') {
                $query->whereRaw('UPPER(TRIM(customer)) = ?', [$customer]);
            }
            $keyword = mb_strtoupper(trim((string) ($data['keyword'] ?? '')));
            if ($keyword !== '') {
                $query->where(function ($sub) use ($keyword) {
                    $like = '%' . $keyword . '%';
                    $sub->whereRaw('UPPER(production_order) LIKE ?', [$like])
                        ->orWhereRaw('UPPER(purchase_order) LIKE ?', [$like])
                        ->orWhereRaw('UPPER(customer) LIKE ?', [$like])
                        ->orWhereRaw('UPPER(item_code) LIKE ?', [$like])
                        ->orWhereRaw('UPPER(description) LIKE ?', [$like]);
                });
            }
        } else {
            $codes = collect($data['production_orders'] ?? [])
                ->map(fn ($code) => $this->cleanCode($code))
                ->filter()
                ->unique()
                ->values();
            if ($codes->isEmpty()) {
                return response()->json(['message' => 'Hãy chọn ít nhất một lệnh sản xuất.'], 422);
            }
            $query->whereIn(DB::raw('UPPER(TRIM(production_order))'), $codes->all());
        }

        $orders = $query
            ->select(
                'production_order',
                DB::raw("COALESCE(NULLIF(TRIM(MIN(customer)), ''), 'CHUA-XAC-DINH') as customer"),
                DB::raw('MIN(id) as first_id')
            )
            ->groupBy('production_order')
            ->orderByDesc('first_id')
            ->limit(1001)
            ->get()
            ->map(fn ($row) => [
                'production_order' => trim((string) $row->production_order),
                'customer' => trim((string) $row->customer),
            ])
            ->values();

        if ($orders->count() > 1000) {
            return response()->json(['message' => 'Kết quả vượt 1.000 lệnh. Hãy lọc theo khách hàng hoặc từ khóa.'], 422);
        }
        if ($orders->isEmpty()) {
            return response()->json([
                'message' => 'Không có lệnh nào đã có định mức sợi/vật tư để xuất Excel.',
            ], 422);
        }

        try {
            return response()->json($exporter->start($orders->all(), [
                'year' => $data['year'] ?? null,
                'customer' => $data['customer'] ?? null,
                'keyword' => $data['keyword'] ?? null,
            ]), 201);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function processBatchExport(string $token, WeavingExcelBatchExporter $exporter)
    {
        try {
            $pending = $exporter->pending($token);
            if (empty($pending)) {
                return response()->json($exporter->status($token));
            }

            $status = null;
            $readyExports = [];
            foreach ($pending as $batchOrder) {
                $productionOrder = trim((string) $batchOrder['production_order']);
                try {
                    $planResponse = $this->productionPlan(Request::create(
                        '/api/lenh-det/production-order-plan',
                        'GET',
                        ['production_order' => $productionOrder]
                    ));
                    $plan = $planResponse->getData(true);
                    if ($planResponse->getStatusCode() >= 400) {
                        throw new \RuntimeException($plan['message'] ?? 'Không đọc được lệnh sản xuất.');
                    }

                    $sourceItemCodes = collect($plan['source_items'] ?? [])
                        ->pluck('item_code')
                        ->map(fn ($code) => $this->cleanCode($code))
                        ->filter()
                        ->unique()
                        ->values();
                    if ($sourceItemCodes->count() !== 1) {
                        throw new \RuntimeException('Mẫu LENH_DET chỉ nhận một mã hàng; lệnh có ' . $sourceItemCodes->count() . ' mã.');
                    }
                    $templateLineCount = collect($plan['source_items'] ?? [])
                        ->flatMap(fn ($item) => (array) ($item['materials'] ?? []))
                        ->count();
                    if ($templateLineCount === 0) {
                        throw new \RuntimeException('Lệnh chưa có định mức sợi.');
                    }
                    if ($templateLineCount > 7) {
                        throw new \RuntimeException('Định mức có ' . $templateLineCount . ' dòng, vượt giới hạn 7 dòng của mẫu.');
                    }

                    $centralCustomer = trim((string) $batchOrder['customer']) ?: 'CHUA-XAC-DINH';
                    $plan['order']['customer'] = $centralCustomer;
                    foreach ($plan['source_items'] as &$sourceItem) {
                        $sourceItem['customer'] = $centralCustomer;
                    }
                    unset($sourceItem);

                    $readyExports[] = [
                        'production_order' => $productionOrder,
                        'plan' => $plan,
                    ];
                } catch (\Throwable $e) {
                    report($e);
                    $status = $exporter->mark($token, $productionOrder, 'failed', null, $e->getMessage());
                }
            }
            if (!empty($readyExports)) {
                try {
                    $exportResults = $exporter->appendMany($token, $readyExports);
                } catch (\Throwable $e) {
                    report($e);
                    $exportResults = collect($readyExports)->map(fn ($export) => [
                        'production_order' => $export['production_order'],
                        'status' => 'failed',
                        'file' => null,
                        'error' => $e->getMessage(),
                    ])->all();
                }
                foreach ($exportResults as $result) {
                    $status = $exporter->mark(
                        $token,
                        $result['production_order'],
                        $result['status'],
                        $result['file'],
                        $result['error']
                    );
                }
            }

            return response()->json($status ?: $exporter->status($token));
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function batchExportStatus(string $token, WeavingExcelBatchExporter $exporter)
    {
        try {
            return response()->json($exporter->status($token));
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function downloadBatchExport(string $token, WeavingExcelBatchExporter $exporter)
    {
        try {
            $file = $exporter->download($token);

            return response()->download($file['path'], $file['name'], [
                'Content-Type' => 'application/zip',
                'Cache-Control' => 'private, no-store',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function saveTemplateDetails(Request $request)
    {
        $data = $request->validate([
            'production_order' => 'required|string|max:120',
            'item_code' => 'nullable|string|max:120',
            'customer' => 'nullable|string|max:200',
            'po_number' => 'nullable|string|max:200',
            'design_code' => 'nullable|string|max:200',
            'order_quantity' => 'nullable|numeric|min:0',
            'unit' => 'nullable|string|max:50',
            'order_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'metadata' => 'nullable|array',
            'metadata.job_number' => 'nullable|string|max:200',
            'metadata.label_name' => 'nullable|string|max:500',
            'metadata.length' => 'nullable|string|max:200',
            'metadata.finished_size' => 'nullable|string|max:200',
            'metadata.box_code' => 'nullable|string|max:200',
            'metadata.quantity_per_box' => 'nullable|string|max:100',
            'metadata.warp_grams' => 'nullable|numeric|min:0',
            'metadata.pick' => 'nullable|string|max:100',
            'metadata.density' => 'nullable|string|max:100',
            'metadata.machine' => 'nullable|string|max:100',
            'metadata.roll_machine_small' => 'nullable|string|max:100',
            'metadata.roll_count_small' => 'nullable|numeric|min:0',
            'metadata.roll_machine_large' => 'nullable|string|max:100',
            'metadata.roll_count_large' => 'nullable|numeric|min:0',
            'metadata.quantity_plus_10' => 'nullable|numeric|min:0',
            'metadata.row_machine_small' => 'nullable|string|max:100',
            'metadata.row_count_plus_10' => 'nullable|numeric|min:0',
            'metadata.row_machine_large' => 'nullable|string|max:100',
            'metadata.row_count_plus_10_large' => 'nullable|numeric|min:0',
            'metadata.shift' => 'nullable|string|max:100',
            'metadata.shift_large' => 'nullable|string|max:100',
            'metadata.file_name' => 'nullable|string|max:300',
            'metadata.usb_small' => 'nullable|string|max:200',
            'metadata.usb_large' => 'nullable|string|max:200',
            'metadata.row_count' => 'nullable|numeric|min:0',
            'metadata.operations' => 'nullable|array',
            'metadata.operations.*' => 'nullable|string|max:1000',
        ]);
        $data = $this->normalizeDateFields($data, ['order_date', 'due_date']);

        $orderCode = $this->cleanCode($data['production_order']);
        $existingOrder = InternalWeavingOrder::query()
            ->whereRaw('UPPER(TRIM(order_code)) = ?', [$orderCode])
            ->first();
        $sourceLines = InternalProductionOrder::query()
            ->where('is_active', true)
            ->whereRaw('UPPER(TRIM(production_order)) = ?', [$orderCode])
            ->get();
        $sourceItemCodes = $sourceLines->pluck('item_code')
            ->map(fn ($code) => $this->cleanCode($code))
            ->filter()
            ->unique()
            ->values();
        $itemCode = $this->cleanCode($data['item_code'] ?? $existingOrder->item_code ?? ($sourceItemCodes->count() === 1 ? $sourceItemCodes->first() : ''));
        if ($itemCode === '') {
            return response()->json(['message' => 'Không xác định được mã hàng của lệnh dệt.'], 422);
        }
        if ($sourceItemCodes->count() > 1) {
            return response()->json(['message' => 'Lệnh có nhiều mã hàng, cần tách lệnh trước khi lưu mẫu.'], 422);
        }

        $item = InternalWeavingItem::query()->firstOrCreate(
            ['item_code' => $itemCode],
            [
                'item_name' => trim((string) ($sourceLines->pluck('description')->filter()->first() ?? $itemCode)),
                'customer' => trim((string) ($sourceLines->pluck('customer')->filter()->first() ?? '')),
                'unit' => trim((string) ($sourceLines->pluck('unit')->filter()->first() ?? 'PCS')),
            ]
        );
        $existingMetadata = json_decode((string) ($existingOrder->metadata_json ?? ''), true) ?: [];
        $incomingMetadata = (array) ($data['metadata'] ?? []);
        $incomingOperations = (array) ($incomingMetadata['operations'] ?? []);
        unset($incomingMetadata['operations']);
        $metadata = array_replace($existingMetadata, $incomingMetadata);
        $metadata['operations'] = array_replace(
            (array) ($existingMetadata['operations'] ?? []),
            $incomingOperations
        );

        $order = InternalWeavingOrder::query()->updateOrCreate(
            ['order_code' => $orderCode],
            [
                'weaving_item_id' => $item->id,
                'item_code' => $itemCode,
                'customer' => trim((string) ($data['customer'] ?? $existingOrder->customer ?? $sourceLines->pluck('customer')->filter()->first() ?? '')),
                'po_number' => trim((string) ($data['po_number'] ?? $existingOrder->po_number ?? $sourceLines->pluck('purchase_order')->filter()->first() ?? '')) ?: null,
                'design_code' => trim((string) ($data['design_code'] ?? $existingOrder->design_code ?? '')) ?: null,
                'order_quantity' => (float) ($data['order_quantity'] ?? $existingOrder->order_quantity ?? $sourceLines->sum('order_quantity')),
                'unit' => trim((string) ($data['unit'] ?? $existingOrder->unit ?? $sourceLines->pluck('unit')->filter()->first() ?? 'PCS')),
                'order_date' => $data['order_date'] ?? optional($existingOrder->order_date ?? null)->format('Y-m-d')
                    ?? optional($sourceLines->pluck('received_date')->filter()->sort()->first())->format('Y-m-d'),
                'due_date' => optional($sourceLines->pluck('promised_date')->filter()->sort()->first())->format('Y-m-d'),
                'status' => $existingOrder->status ?? 'draft',
                'note' => $existingOrder->note ?? '',
                'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            ]
        );

        return response()->json([
            'message' => 'Đã lưu đầy đủ thông tin mẫu cho lệnh ' . $order->order_code . '.',
            'data' => $order->fresh('item'),
        ]);
    }

    public function saveDesignerOrder(Request $request)
    {
        $request->merge([
            'lines' => $this->deriveBomConsumptionFromTotals(
                (array) $request->input('lines', []),
                $this->toNumber($request->input('order_quantity', 0))
            ),
        ]);

        $data = $request->validate([
            'action' => 'required|in:draft,issued',
            'production_order' => 'required|string|max:120',
            'item_code' => 'required|string|max:120',
            'item_name' => 'nullable|string|max:500',
            'customer' => 'nullable|string|max:200',
            'po_number' => 'nullable|string|max:200',
            'design_code' => 'nullable|string|max:200',
            'order_quantity' => 'required|numeric|min:0.001',
            'unit' => 'nullable|string|max:50',
            'order_date' => ['nullable', 'string', 'regex:/^(\d{2}[\/-]\d{2}[\/-]\d{4}|\d{4}-\d{2}-\d{2})$/'],
            'due_date' => ['nullable', 'string', 'regex:/^(\d{2}[\/-]\d{2}[\/-]\d{4}|\d{4}-\d{2}-\d{2})$/'],
            'metadata' => 'nullable|array',
            'metadata.job_number' => 'nullable|string|max:200',
            'metadata.label_name' => 'nullable|string|max:500',
            'metadata.length' => 'nullable|string|max:200',
            'metadata.finished_size' => 'nullable|string|max:200',
            'metadata.box_code' => 'nullable|string|max:200',
            'metadata.quantity_per_box' => 'nullable|string|max:100',
            'metadata.warp_grams' => 'nullable|numeric|min:0',
            'metadata.pick' => 'nullable|string|max:100',
            'metadata.density' => 'nullable|string|max:100',
            'metadata.machine' => 'nullable|string|max:100',
            'metadata.roll_machine_small' => 'nullable|string|max:100',
            'metadata.roll_count_small' => 'nullable|numeric|min:0',
            'metadata.roll_machine_large' => 'nullable|string|max:100',
            'metadata.roll_count_large' => 'nullable|numeric|min:0',
            'metadata.quantity_plus_10' => 'nullable|numeric|min:0',
            'metadata.row_machine_small' => 'nullable|string|max:100',
            'metadata.row_count_plus_10' => 'nullable|numeric|min:0',
            'metadata.row_machine_large' => 'nullable|string|max:100',
            'metadata.row_count_plus_10_large' => 'nullable|numeric|min:0',
            'metadata.shift' => 'nullable|string|max:100',
            'metadata.shift_large' => 'nullable|string|max:100',
            'metadata.file_name' => 'nullable|string|max:300',
            'metadata.usb_small' => 'nullable|string|max:200',
            'metadata.usb_large' => 'nullable|string|max:200',
            'metadata.row_count' => 'nullable|numeric|min:0',
            'metadata.operations' => 'nullable|array',
            'metadata.operations.*' => 'nullable|string|max:1000',
            'lines' => 'required|array|min:1|max:7',
            'lines.*.material_code' => 'required|string|max:120',
            'lines.*.line_role' => 'nullable|string|max:120',
            'lines.*.type' => 'nullable|string|max:100',
            'lines.*.material_name' => 'nullable|string|max:500',
            'lines.*.unit' => 'nullable|string|max:50',
            'lines.*.consumption_per_unit' => 'required|numeric|min:0.000001',
            'lines.*.waste_percent' => 'nullable|numeric|min:0|max:999',
            'lines.*.shelf_hint' => 'nullable|string|max:100',
            'lines.*.total_grams' => 'nullable|numeric|min:0',
            'lines.*.note' => 'nullable|string|max:1000',
        ]);
        $data['metadata'] = array_replace((array) ($data['metadata'] ?? []), [
            'roll_machine_small' => 'Muller',
            'roll_machine_large' => 'Hi-Tex',
            'row_machine_small' => 'Muller',
            'row_machine_large' => 'Hi-Tex',
        ]);
        $data = $this->normalizeDateFields($data, ['order_date', 'due_date']);

        $orderCode = $this->cleanCode($data['production_order']);
        $itemCode = $this->cleanCode($data['item_code']);
        $sourceLines = InternalProductionOrder::query()
            ->where('is_active', true)
            ->whereRaw('UPPER(TRIM(production_order)) = ?', [$orderCode])
            ->get();
        if ($sourceLines->isEmpty()) {
            return response()->json(['message' => 'Lệnh không còn trong Lệnh sản xuất trung tâm.'], 422);
        }
        $data['due_date'] = optional(
            $sourceLines->pluck('promised_date')->filter()->sort()->first()
        )->format('Y-m-d');
        $sourceItemCodes = $sourceLines->pluck('item_code')
            ->map(fn ($code) => $this->cleanCode($code))
            ->filter()
            ->unique()
            ->values();
        if ($sourceItemCodes->count() !== 1 || $sourceItemCodes->first() !== $itemCode) {
            return response()->json([
                'message' => 'Mã hàng không khớp Lệnh sản xuất trung tâm. Hãy tải lại lệnh trước khi lưu.',
            ], 422);
        }

        $missingCatalog = $this->missingCatalogCodes(collect($data['lines'])->pluck('material_code')->all());
        if (!empty($missingCatalog)) {
            return response()->json([
                'message' => 'Mã sợi chưa có trong DANH MỤC nội bộ: ' . implode(', ', array_slice($missingCatalog, 0, 20)),
                'missing_catalog' => $missingCatalog,
            ], 422);
        }

        $order = DB::connection('internal')->transaction(function () use ($data, $orderCode, $itemCode, $sourceLines) {
            $existingOrder = InternalWeavingOrder::query()
                ->whereRaw('UPPER(TRIM(order_code)) = ?', [$orderCode])
                ->first();
            $item = InternalWeavingItem::query()->updateOrCreate(
                ['item_code' => $itemCode],
                [
                    'item_name' => trim((string) ($data['item_name'] ?? $sourceLines->pluck('description')->filter()->first() ?? $itemCode)),
                    'design_code' => trim((string) ($data['design_code'] ?? '')) ?: null,
                    'customer' => trim((string) ($data['customer'] ?? $sourceLines->pluck('customer')->filter()->first() ?? '')),
                    'unit' => trim((string) ($data['unit'] ?? $sourceLines->pluck('unit')->filter()->first() ?? 'PCS')),
                ]
            );

            $item->boms()->delete();
            foreach ($data['lines'] as $index => $line) {
                $lineRole = $this->cleanCode($line['line_role'] ?? '') ?: ('DONG-' . ($index + 1));
                $lineMetadata = [
                    'type' => trim((string) ($line['type'] ?? '')),
                    'shelf_hint' => trim((string) ($line['shelf_hint'] ?? '')),
                    'total_grams' => (float) ($line['total_grams'] ?? 0),
                ];
                $item->boms()->create([
                    'material_code' => $this->cleanCode($line['material_code']),
                    'line_role' => $lineRole,
                    'material_name' => trim((string) ($line['material_name'] ?? '')),
                    'unit' => trim((string) ($line['unit'] ?? 'gam')) ?: 'gam',
                    'consumption_per_unit' => (float) $line['consumption_per_unit'],
                    'waste_percent' => (float) ($line['waste_percent'] ?? 0),
                    'note' => trim((string) ($line['note'] ?? '')),
                    'metadata_json' => json_encode($lineMetadata, JSON_UNESCAPED_UNICODE),
                ]);
            }

            $existingMetadata = json_decode((string) ($existingOrder->metadata_json ?? ''), true) ?: [];
            $incomingMetadata = (array) ($data['metadata'] ?? []);
            $metadata = array_replace($existingMetadata, $incomingMetadata);
            $metadata['operations'] = array_replace(
                (array) ($existingMetadata['operations'] ?? []),
                (array) ($incomingMetadata['operations'] ?? [])
            );
            $metadata['designer_source'] = 'web';
            $metadata['designer_saved_at'] = now('Asia/Ho_Chi_Minh')->toIso8601String();

            $status = $data['action'] === 'issued' || ($existingOrder && $existingOrder->status === 'issued')
                ? 'issued'
                : 'draft';
            if ($data['action'] === 'issued') {
                $metadata['bom_snapshot'] = collect($data['lines'])->values()->all();
                $metadata['bom_snapshot_at'] = now('Asia/Ho_Chi_Minh')->toIso8601String();
                if (empty($metadata['sent_to_production_at'])) {
                    $receiptBaseline = DB::connection('internal')->table('internal_material_receipt_lines as line')
                        ->join('internal_material_receipts as receipt', 'receipt.id', '=', 'line.receipt_id')
                        ->where('line.production_order', $orderCode)
                        ->where(function ($query) {
                            $query->whereNull('receipt.status')->orWhere('receipt.status', '<>', 'cancelled');
                        })
                        ->selectRaw('COALESCE(SUM(line.quantity), 0) as received_quantity')
                        ->selectRaw('COUNT(DISTINCT line.receipt_id) as receipt_count')
                        ->first();
                    $metadata['sent_to_production_at'] = now('Asia/Ho_Chi_Minh')->toIso8601String();
                    $metadata['receipt_quantity_baseline'] = (float) ($receiptBaseline->received_quantity ?? 0);
                    $metadata['receipt_count_baseline'] = (int) ($receiptBaseline->receipt_count ?? 0);
                }
            }

            return InternalWeavingOrder::query()->updateOrCreate(
                ['order_code' => $orderCode],
                [
                    'weaving_item_id' => $item->id,
                    'item_code' => $itemCode,
                    'customer' => trim((string) ($data['customer'] ?? '')),
                    'po_number' => trim((string) ($data['po_number'] ?? '')) ?: null,
                    'design_code' => trim((string) ($data['design_code'] ?? '')) ?: null,
                    'order_quantity' => (float) $data['order_quantity'],
                    'unit' => trim((string) ($data['unit'] ?? 'PCS')) ?: 'PCS',
                    'order_date' => $data['order_date'] ?? null,
                    'due_date' => $data['due_date'] ?? null,
                    'status' => $status,
                    'note' => trim((string) ($existingOrder->note ?? '')),
                    'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                ]
            );
        });

        return response()->json([
            'message' => $data['action'] === 'issued'
                ? 'Đã lưu và gửi lệnh ' . $orderCode . ' xuống sản xuất.'
                : 'Đã lưu nháp lệnh ' . $orderCode . '.',
            'data' => $order->fresh('item'),
        ]);
    }

    private function deriveBomConsumptionFromTotals(array $lines, float $orderQuantity): array
    {
        if ($orderQuantity <= 0) {
            return $lines;
        }

        return collect($lines)->map(function ($line) use ($orderQuantity) {
            $line = (array) $line;
            $consumption = $this->toNumber($line['consumption_per_unit'] ?? 0);
            $totalGrams = $this->toNumber($line['total_grams'] ?? 0);
            $wastePercent = $this->toNumber($line['waste_percent'] ?? 0);
            $line['consumption_per_unit'] = $consumption;
            $line['total_grams'] = $totalGrams;
            $line['waste_percent'] = $wastePercent;

            if ($consumption <= 0 && $totalGrams > 0) {
                $line['consumption_per_unit'] = round(
                    $totalGrams / $orderQuantity / (1 + $wastePercent / 100),
                    6
                );
            }

            return $line;
        })->all();
    }

    public function createIssue(Request $request, InternalWeavingOrder $order)
    {
        $planResponse = $this->plan($request, $order)->getData(true);
        $missingCatalog = collect($planResponse['data'] ?? [])
            ->filter(fn ($line) => empty($line['catalog_exists']))
            ->pluck('material_code')
            ->values()
            ->all();
        if (!empty($missingCatalog)) {
            return response()->json([
                'message' => 'Không thể tạo phiếu xuất. Mã sợi chưa có trong DANH MỤC nội bộ: ' . implode(', ', array_slice($missingCatalog, 0, 20)),
                'missing_catalog' => $missingCatalog,
            ], 422);
        }

        $lines = collect($planResponse['data'] ?? [])
            ->filter(fn ($line) => (float) ($line['required_quantity'] ?? 0) > 0)
            ->map(function ($line) use ($order) {
                return [
                    'production_order' => $order->order_code,
                    'ma_hh' => $line['material_code'],
                    'internal_item_code' => $line['material_code'],
                    'ten_hh' => $line['material_name'] ?? '',
                    'dvt' => $line['unit'] ?? '',
                    'quantity' => $line['required_quantity'],
                    'location_code' => $line['first_location'] ?? '',
                    'note' => 'Xuat soi theo lenh det ' . $order->order_code,
                ];
            })
            ->values();

        if ($lines->isEmpty()) {
            return response()->json(['message' => 'Lenh det chua co dinh muc soi de xuat.'], 422);
        }

        $payload = [
            'issue_type' => 'material',
            'issue_date' => $request->input('issue_date', now('Asia/Ho_Chi_Minh')->format('Y-m-d')),
            'receiver_name' => $request->input('receiver_name', 'San xuat det'),
            'department' => $request->input('department', 'San xuat'),
            'production_order' => $order->order_code,
            'purpose' => 'Xuat soi cho lenh det',
            'note' => trim((string) $request->input('note', 'Tao tu module Lenh det')),
            'lines' => $lines->all(),
        ];

        $issueRequest = Request::create('/api/xuat-vat-tu-noi-bo', 'POST', $payload);
        $issueRequest->headers->set('Accept', 'application/json');
        $response = app(InternalMaterialIssueController::class)->store($issueRequest);

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $order->status = 'issued';
            $order->save();
        }

        return $response;
    }

    public function createProductionIssue(Request $request)
    {
        $data = $request->validate([
            'production_order' => 'required|string|max:120',
            'issue_date' => 'nullable|date',
            'receiver_name' => 'nullable|string|max:150',
            'department' => 'nullable|string|max:150',
            'note' => 'nullable|string|max:1000',
        ]);
        $data = $this->normalizeDateFields($data, ['issue_date']);

        $planRequest = Request::create('/api/lenh-det/production-order-plan', 'GET', [
            'production_order' => $data['production_order'],
        ]);
        $planResponse = $this->productionPlan($planRequest)->getData(true);

        $missingCatalog = collect($planResponse['data'] ?? [])
            ->filter(fn ($line) => empty($line['catalog_exists']))
            ->pluck('material_code')
            ->values()
            ->all();
        if (!empty($missingCatalog)) {
            return response()->json([
                'message' => 'Không thể tạo phiếu xuất. Mã sợi chưa có trong DANH MỤC nội bộ: ' . implode(', ', array_slice($missingCatalog, 0, 20)),
                'missing_catalog' => $missingCatalog,
            ], 422);
        }

        $lines = collect($planResponse['data'] ?? [])
            ->filter(fn ($line) => (float) ($line['required_quantity'] ?? 0) > 0)
            ->map(function ($line) use ($data) {
                return [
                    'production_order' => trim((string) $data['production_order']),
                    'ma_hh' => $line['material_code'],
                    'internal_item_code' => $line['material_code'],
                    'ten_hh' => $line['material_name'] ?? '',
                    'dvt' => $line['unit'] ?? '',
                    'quantity' => $line['required_quantity'],
                    'location_code' => $line['first_location'] ?? '',
                    'note' => 'Xuat soi theo lenh SX ' . trim((string) $data['production_order']),
                ];
            })
            ->values();

        if ($lines->isEmpty()) {
            return response()->json(['message' => 'Lệnh sản xuất chưa có định mức sợi để xuất.'], 422);
        }

        $payload = [
            'issue_type' => 'material',
            'issue_date' => $data['issue_date'] ?? now('Asia/Ho_Chi_Minh')->format('Y-m-d'),
            'receiver_name' => trim((string) ($data['receiver_name'] ?? 'San xuat det')),
            'department' => trim((string) ($data['department'] ?? 'San xuat')),
            'production_order' => trim((string) $data['production_order']),
            'purpose' => 'Xuat soi cho lenh san xuat',
            'note' => trim((string) ($data['note'] ?? 'Tao tu module Lenh det')),
            'lines' => $lines->all(),
        ];

        $issueRequest = Request::create('/api/xuat-vat-tu-noi-bo', 'POST', $payload);
        $issueRequest->headers->set('Accept', 'application/json');

        return app(InternalMaterialIssueController::class)->store($issueRequest);
    }

    private function stockByMaterial(array $materialCodes): array
    {
        if (empty($materialCodes)) return [];

        $rows = InventoryPackage::query()
            ->with('location:id,location_code')
            ->where('quantity', '>', 0)
            ->where(function ($query) use ($materialCodes) {
                $query->whereIn(DB::raw('UPPER(TRIM(internal_item_code))'), $materialCodes)
                    ->orWhereIn(DB::raw('UPPER(TRIM(ma_sp))'), $materialCodes);
            })
            ->get();

        return $rows
            ->groupBy(fn ($row) => $this->cleanCode($row->internal_item_code ?: $row->ma_sp))
            ->map(function ($items) {
                return [
                    'quantity' => (float) $items->sum('quantity'),
                    'locations' => $items
                        ->groupBy(fn ($row) => optional($row->location)->location_code ?: $row->location_code ?: '')
                        ->map(function ($locationRows, $locationCode) {
                            return [
                                'location_code' => $locationCode ?: 'CHUA-XEP',
                                'quantity' => (float) $locationRows->sum('quantity'),
                                'color' => trim((string) $locationRows->pluck('color')->filter()->first()),
                                'pantone_hex' => '',
                            ];
                        })
                        ->sortByDesc('quantity')
                        ->values(),
                ];
            })
            ->all();
    }

    private function catalogByCodes(array $codes)
    {
        $cleanCodes = collect($codes)->map(fn ($code) => $this->cleanCode($code))->filter()->unique()->values();
        if ($cleanCodes->isEmpty()) {
            return collect();
        }

        return InternalItemCatalog::query()
            ->whereIn('item_code', $cleanCodes->all())
            ->get()
            ->keyBy(fn ($row) => $this->cleanCode($row->item_code));
    }

    private function missingCatalogCodes(array $codes): array
    {
        $cleanCodes = collect($codes)->map(fn ($code) => $this->cleanCode($code))->filter()->unique()->values();
        if ($cleanCodes->isEmpty()) {
            return [];
        }

        $existing = $this->catalogByCodes($cleanCodes->all())->keys();
        return $cleanCodes->diff($existing)->values()->all();
    }

    private function decorateBomRow(InternalWeavingBom $row, $catalog): array
    {
        $code = $this->cleanCode($row->material_code);
        $catalogRow = $catalog[$code] ?? null;
        return array_merge($row->toArray(), [
            'catalog_exists' => (bool) $catalogRow,
            'catalog_name' => $catalogRow->item_name ?? '',
            'catalog_unit' => $catalogRow->unit ?? '',
            'catalog_shelf_code' => $catalogRow->shelf_code ?? '',
            'catalog_opening_quantity' => (float) ($catalogRow->opening_quantity ?? 0),
        ]);
    }

    private function normalizeBomImportRow(array $row): array
    {
        $oldConsumption = trim((string) ($row[4] ?? ''));
        if ($oldConsumption !== '' && is_numeric(str_replace(',', '.', preg_replace('/[^\d,\.\-]/', '', $oldConsumption)))) {
            return [
                'item_code' => $this->cleanCode($row[0] ?? ''),
                'material_code' => $this->cleanCode($row[2] ?? ''),
                'line_role' => '',
                'consumption_per_unit' => $this->toNumber($row[4] ?? 0),
                'unit' => trim((string) ($row[5] ?? '')),
                'waste_percent' => $this->toNumber($row[6] ?? 0),
                'note' => trim((string) ($row[7] ?? '')),
            ];
        }

        $roleConsumption = trim((string) ($row[3] ?? ''));
        if ($roleConsumption !== '' && is_numeric(str_replace(',', '.', preg_replace('/[^\d,\.\-]/', '', $roleConsumption)))) {
            return [
                'item_code' => $this->cleanCode($row[0] ?? ''),
                'material_code' => $this->cleanCode($row[1] ?? ''),
                'line_role' => $this->cleanCode($row[2] ?? ''),
                'consumption_per_unit' => $this->toNumber($row[3] ?? 0),
                'unit' => trim((string) ($row[4] ?? '')),
                'waste_percent' => $this->toNumber($row[5] ?? 0),
                'note' => trim((string) ($row[6] ?? '')),
            ];
        }

        return [
            'item_code' => $this->cleanCode($row[0] ?? ''),
            'material_code' => $this->cleanCode($row[1] ?? ''),
            'line_role' => '',
            'consumption_per_unit' => $this->toNumber($row[2] ?? 0),
            'unit' => trim((string) ($row[3] ?? '')),
            'waste_percent' => $this->toNumber($row[4] ?? 0),
            'note' => trim((string) ($row[5] ?? '')),
        ];
    }

    private function centralProductionOrderMap(): array
    {
        return InternalProductionOrder::query()
            ->where('is_active', true)
            ->whereNotNull('production_order')
            ->get([
                'production_order',
                'item_code',
                'description',
                'customer',
                'purchase_order',
                'order_quantity',
                'unit',
                'received_date',
                'promised_date',
            ])
            ->mapWithKeys(function ($row) {
                $orderCode = $this->cleanCode($row->production_order);
                if ($orderCode === '') {
                    return [];
                }

                return [$orderCode => $this->centralProductionOrderData($row, $orderCode)];
            })
            ->all();
    }

    private function centralProductionOrderData($row, string $orderCode): array
    {
        return [
            'order_code' => $orderCode,
            'item_code' => $this->cleanCode($row->item_code),
            'item_name' => trim((string) $row->description),
            'customer' => trim((string) $row->customer),
            'po' => trim((string) $row->purchase_order),
            'order_quantity' => (float) $row->order_quantity,
            'unit' => trim((string) $row->unit) ?: 'PCS',
            'received_date' => optional($row->received_date)->format('Y-m-d'),
            'promised_date' => optional($row->promised_date)->format('Y-m-d'),
        ];
    }

    private function applyCentralProductionOrder(array $parsed, ?array $centralOrders = null): array
    {
        $orderCode = $this->cleanCode($parsed['order_code'] ?? '');
        if ($orderCode === '') {
            return [
                'parsed' => $parsed,
                'error' => ['message' => 'Phiếu lệnh dệt thiếu LỆNH IN.'],
            ];
        }

        if ($centralOrders === null) {
            $row = InternalProductionOrder::query()
                ->where('is_active', true)
                ->whereRaw('UPPER(TRIM(production_order)) = ?', [$orderCode])
                ->first([
                    'production_order',
                    'item_code',
                    'description',
                    'customer',
                    'purchase_order',
                    'order_quantity',
                    'unit',
                    'received_date',
                    'promised_date',
                ]);
            $centralOrders = $row
                ? [$orderCode => $this->centralProductionOrderData($row, $orderCode)]
                : [];
        }

        $central = $centralOrders[$orderCode] ?? null;
        if (!$central || ($central['item_code'] ?? '') === '') {
            return [
                'parsed' => $parsed,
                'error' => [
                    'message' => "Lệnh {$orderCode} chưa có trong Lệnh SX trung tâm hoặc chưa có mã hàng.",
                    'order_code' => $orderCode,
                ],
            ];
        }

        $fileItemCode = $this->cleanCode($parsed['item_code'] ?? '');
        if ($fileItemCode !== '' && $fileItemCode !== $central['item_code']) {
            $parsed['warnings'][] = "Bỏ qua mã {$fileItemCode} trong file; dùng mã {$central['item_code']} từ Lệnh SX trung tâm.";
        }

        $parsed['order_code'] = $orderCode;
        $parsed['item_code'] = $central['item_code'];
        $parsed['item_name'] = $central['item_name'];
        $parsed['customer'] = $central['customer'];
        $parsed['po'] = $central['po'];
        $parsed['order_quantity'] = $central['order_quantity'];
        $parsed['unit'] = $central['unit'];
        $parsed['job_date'] = $central['received_date'] ?: ($parsed['job_date'] ?? null);
        $parsed['delivery_date'] = $central['promised_date'] ?: ($parsed['delivery_date'] ?? null);

        return ['parsed' => $parsed, 'error' => null];
    }

    private function saveParsedDesignSheet(array $parsed, array $missingCatalog = []): array
    {
        return DB::connection('internal')->transaction(function () use ($parsed, $missingCatalog) {
            $metadata = $parsed['metadata'] ?? [];
            $item = InternalWeavingItem::query()->updateOrCreate(
                ['item_code' => $parsed['item_code']],
                [
                    'item_name' => $parsed['item_name'] ?: ($metadata['label_name'] ?: ($parsed['design_code'] ?: $parsed['item_code'])),
                    'design_code' => $parsed['design_code'] ?: null,
                    'customer' => $parsed['customer'],
                    'unit' => $parsed['unit'] ?: 'PCS',
                    'note' => trim(implode(' | ', array_filter([
                        $parsed['po'] ? 'PO: ' . $parsed['po'] : '',
                        $parsed['job_date'] ? 'Ngay phieu: ' . $parsed['job_date'] : '',
                        'Import tu phieu lenh det',
                    ]))),
                    'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                ]
            );

            $skipCodes = collect($missingCatalog)->map(fn ($code) => $this->cleanCode($code))->flip();
            $item->boms()->delete();
            foreach ($parsed['lines'] as $index => $line) {
                if ($skipCodes->has($line['material_code'])) {
                    continue;
                }
                $item->boms()->create([
                    'material_code' => $line['material_code'],
                    'line_role' => $line['line_role'] ?: ('DONG-' . ($index + 1)),
                    'material_name' => $line['material_name'],
                    'unit' => 'gam',
                    'consumption_per_unit' => $line['consumption_per_unit'],
                    'waste_percent' => 0,
                    'note' => trim(implode(' | ', array_filter([
                        $line['type'] ? 'Loai: ' . $line['type'] : '',
                        $line['shelf_hint'] ? 'Ke tren phieu: ' . $line['shelf_hint'] : '',
                        $line['total_grams'] ? 'TL phieu: ' . $line['total_grams'] . 'g' : '',
                    ]))),
                    'metadata_json' => json_encode($line, JSON_UNESCAPED_UNICODE),
                ]);
            }

            $order = null;
            if ($parsed['order_code'] !== '') {
                $order = InternalWeavingOrder::query()->updateOrCreate(
                    ['order_code' => $parsed['order_code']],
                    [
                        'weaving_item_id' => $item->id,
                        'item_code' => $parsed['item_code'],
                        'customer' => $parsed['customer'],
                        'po_number' => $parsed['po'] ?: null,
                        'design_code' => $parsed['design_code'] ?: null,
                        'order_quantity' => $parsed['order_quantity'],
                        'unit' => $parsed['unit'] ?: 'PCS',
                        'order_date' => $parsed['job_date'] ?: now('Asia/Ho_Chi_Minh')->format('Y-m-d'),
                        'due_date' => $parsed['delivery_date'] ?: null,
                        'status' => 'draft',
                        'note' => trim(implode(' | ', array_filter([
                            $parsed['po'] ? 'PO: ' . $parsed['po'] : '',
                            $parsed['design_code'] ? 'Design: ' . $parsed['design_code'] : '',
                            'Import tu phieu lenh det',
                        ]))),
                        'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                    ]
                );
            }

            return [
                'item' => $item->fresh('boms'),
                'order' => $order,
                'line_count' => $item->boms()->count(),
            ];
        });
    }

    private function parseDesignSheet(string $text): array
    {
        return $this->parseDesignSheetRows($this->parseTsv($text));
    }

    private function parseDesignSheetRows(array $rows, string $sheetName = ''): array
    {
        $customer = $this->findValueAfterLabel($rows, ['KHACH HANG', 'KHÁCH HÀNG']);
        $orderCode = $this->findValueAfterLabel($rows, ['LENH IN', 'LỆNH IN']);
        $itemCode = $this->cleanCode($this->findValueAfterLabel($rows, ['MA HANG', 'MÃ HÀNG']));
        $po = $this->findValueAfterLabel($rows, ['PO']);
        $designCode = $this->findValueAfterLabel($rows, ['MA SO DESIGN', 'MÃ SỐ DESIGN']);
        $deliveryDate = $this->dateOrNull($this->findValueAfterLabel($rows, ['NGAY GIAO', 'NGÀY GIAO']));
        $jobDate = null;
        $metadata = $this->parseDesignSheetMetadata($rows, $sheetName);

        foreach ($rows as $row) {
            if ($jobDate) break;
            foreach ($row as $cell) {
                $date = $this->dateTokenOrNull($cell);
                if ($date) {
                    $jobDate = $date;
                    break;
                }
            }
        }

        $lines = [];
        $inThreadTable = false;
        foreach ($rows as $row) {
            $lineText = $this->normalizeText(implode(' ', $row));
            if (str_contains($lineText, 'MA SO CHI') && str_contains($lineText, 'TL/1PCS')) {
                $inThreadTable = true;
                continue;
            }
            if (!$inThreadTable) {
                continue;
            }
            if (str_contains($lineText, 'SO PICK') || str_contains($lineText, 'TEN FILE') || str_contains($lineText, 'SIZE')) {
                break;
            }

            $line = $this->parseDesignSheetThreadLine($row);
            if ($line) {
                $lines[] = $line;
            }
        }

        return [
            'customer' => trim((string) $customer),
            'order_code' => $this->cleanCode($orderCode),
            'item_code' => $itemCode,
            'item_name' => '',
            'po' => trim((string) $po),
            'design_code' => trim((string) $designCode),
            'job_date' => $jobDate,
            'delivery_date' => $deliveryDate,
            'order_quantity' => $this->parseDesignSheetQuantity($rows, $lines),
            'unit' => 'PCS',
            'lines' => $lines,
            'metadata' => $metadata,
            'warnings' => [],
        ];
    }

    private function worksheetToRows($sheet): array
    {
        $highestRow = min((int) $sheet->getHighestDataRow(), 80);
        $highestColumnIndex = min(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn()), 40);
        $rows = [];
        for ($row = 1; $row <= $highestRow; $row++) {
            $values = [];
            $hasValue = false;
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $value = $sheet->getCellByColumnAndRow($col, $row)->getFormattedValue();
                $value = trim((string) $value);
                $values[] = $value;
                if ($value !== '') {
                    $hasValue = true;
                }
            }
            if ($hasValue) {
                $rows[] = $values;
            }
        }
        return $rows;
    }

    private function parseDesignSheetMetadata(array $rows, string $sheetName = ''): array
    {
        $operationLabels = ['TEN LABEL', 'UI KEO', 'LOOP', 'PHAN TREN', 'PHẦN TRÊN', 'PHAN DUOI', 'PHẦN DƯỚI', 'CHIEU DAI', 'CHIỀU DÀI', 'HOAN CHINH', 'HOÀN CHỈNH', 'MA SO HOP', 'MÃ SỐ HỘP'];
        $normalizedOperationLabels = array_values(array_unique(array_map(fn ($label) => $this->normalizeText($label), $operationLabels)));
        $threadTableStart = null;
        foreach ($rows as $row) {
            $normalizedCells = array_map(fn ($cell) => $this->normalizeText($cell), $row);
            $materialCodeColumn = array_search('MA SO CHI', $normalizedCells, true);
            if ($materialCodeColumn === false || !in_array('TL/1PCS', $normalizedCells, true)) {
                continue;
            }
            $typeColumn = array_search('LOAI', $normalizedCells, true);
            $threadTableStart = $typeColumn !== false
                ? (int) $typeColumn
                : max(0, (int) $materialCodeColumn - 1);
            break;
        }

        $operations = [];
        foreach ($rows as $row) {
            $labelIndex = null;
            $label = '';
            foreach ($row as $index => $cell) {
                $candidate = $this->normalizeText($cell);
                if (in_array($candidate, $normalizedOperationLabels, true)) {
                    $labelIndex = $index;
                    $label = $candidate;
                    break;
                }
            }
            if ($labelIndex === null) {
                continue;
            }

            $endColumn = $threadTableStart === null ? count($row) : min(count($row), $threadTableStart);
            $valueLength = max(0, $endColumn - $labelIndex - 1);
            $operations[$label] = trim(implode(' ', array_filter(
                array_slice($row, $labelIndex + 1, $valueLength),
                fn ($cell) => trim((string) $cell) !== ''
            )));
        }

        $machineMetrics = $this->parseDesignSheetMachineMetrics($rows);

        return [
            'sheet_name' => $sheetName,
            'label_name' => trim((string) ($operations['TEN LABEL'] ?? '')),
            'length' => $this->findValueAfterLabel($rows, ['CHIEU DAI', 'CHIỀU DÀI']),
            'finished_size' => $this->findValueAfterLabel($rows, ['HOAN CHINH', 'HOÀN CHỈNH']),
            'box_code' => $this->findValueAfterLabel($rows, ['MA SO HOP', 'MÃ SỐ HỘP']),
            'pick' => $machineMetrics['pick'],
            'density' => $machineMetrics['density'],
            'machine' => $machineMetrics['machine'],
            'roll_count' => $machineMetrics['roll_count_small'],
            'roll_machine_small' => $machineMetrics['roll_machine_small'],
            'roll_count_small' => $machineMetrics['roll_count_small'],
            'roll_machine_large' => $machineMetrics['roll_machine_large'],
            'roll_count_large' => $machineMetrics['roll_count_large'],
            'quantity_plus_10' => $machineMetrics['quantity_plus_10'],
            'row_machine_small' => $machineMetrics['row_machine_small'],
            'row_count_plus_10' => $machineMetrics['row_count_plus_10'],
            'row_machine_large' => $machineMetrics['row_machine_large'],
            'row_count_plus_10_large' => $machineMetrics['row_count_plus_10_large'],
            'shift' => $machineMetrics['shift'],
            'shift_large' => $machineMetrics['shift_large'],
            'operations' => $operations,
            'production_capacity_rows' => $this->parseCapacityRows($rows),
        ];
    }

    private function parseDesignSheetMachineMetrics(array $rows): array
    {
        $result = [
            'pick' => '',
            'density' => '',
            'machine' => '',
            'roll_machine_small' => '',
            'roll_count_small' => '',
            'roll_machine_large' => '',
            'roll_count_large' => '',
            'quantity_plus_10' => '',
            'row_machine_small' => '',
            'row_count_plus_10' => '',
            'row_machine_large' => '',
            'row_count_plus_10_large' => '',
            'shift' => '',
            'shift_large' => '',
        ];

        $headerRowIndex = null;
        $columns = [];
        foreach ($rows as $rowIndex => $row) {
            $normalized = array_map(fn ($cell) => $this->normalizeText($cell), $row);
            if (!in_array('SO PICK', $normalized, true) || !in_array('SO CUON', $normalized, true)) {
                continue;
            }

            $headerRowIndex = $rowIndex;
            foreach ([
                'pick' => 'SO PICK',
                'density' => 'MAT DO',
                'machine' => 'MAY',
                'roll' => 'SO CUON',
                'quantity' => 'SO LUONG +10%',
                'row' => 'SO DONG+10%',
                'shift' => 'CA',
            ] as $key => $label) {
                $index = array_search($label, $normalized, true);
                if ($index === false && $key === 'row') {
                    $index = array_search('SO DONG +10%', $normalized, true);
                }
                if ($index === false && $key === 'quantity') {
                    $index = array_search('SO LUONG+10%', $normalized, true);
                }
                $columns[$key] = $index === false ? null : (int) $index;
            }
            break;
        }

        if ($headerRowIndex === null) {
            $result['pick'] = $this->extractValueNearLabel($rows, ['SO PICK', 'SỐ PICK']);
            $result['density'] = $this->extractValueNearLabel($rows, ['MAT DO', 'MẬT ĐỘ']);
            $result['machine'] = $this->extractValueNearLabel($rows, ['MAY', 'MÁY']);
            return $result;
        }

        $dataRows = [];
        for ($index = $headerRowIndex + 1; $index < min($headerRowIndex + 5, count($rows)); $index++) {
            if (str_contains($this->normalizeText(implode(' ', $rows[$index])), 'TEN FILE')) {
                break;
            }
            $dataRows[] = $rows[$index];
        }

        $firstValue = function (string $columnKey) use ($dataRows, $columns): string {
            $column = $columns[$columnKey] ?? null;
            if ($column === null) {
                return '';
            }
            foreach ($dataRows as $row) {
                $value = trim((string) ($row[$column] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
            return '';
        };

        $sectionRows = function (string $startKey, string $endKey) use ($dataRows, $columns): array {
            $start = $columns[$startKey] ?? null;
            $end = $columns[$endKey] ?? null;
            if ($start === null) {
                return [];
            }
            $end = $end === null ? $start + 4 : $end;
            $values = [];
            foreach ($dataRows as $row) {
                $tokens = [];
                for ($column = $start; $column < $end; $column++) {
                    $value = trim((string) ($row[$column] ?? ''));
                    if ($value !== '') {
                        $tokens[] = $value;
                    }
                }
                if ($tokens) {
                    $values[] = $tokens;
                }
            }
            return $values;
        };

        $result['pick'] = $firstValue('pick');
        $result['density'] = $firstValue('density');
        $result['machine'] = $firstValue('machine');
        $result['quantity_plus_10'] = $firstValue('quantity');
        $result['shift'] = $firstValue('shift');

        $rollRows = $sectionRows('roll', 'quantity');
        $result['roll_machine_small'] = trim((string) ($rollRows[0][0] ?? ''));
        $result['roll_count_small'] = trim((string) ($rollRows[0][1] ?? ''));
        $result['roll_machine_large'] = trim((string) ($rollRows[1][0] ?? ''));
        $result['roll_count_large'] = trim((string) ($rollRows[1][1] ?? ''));

        $rowRows = $sectionRows('row', 'shift');
        $result['row_machine_small'] = trim((string) ($rowRows[0][0] ?? ''));
        $result['row_count_plus_10'] = trim((string) ($rowRows[0][1] ?? ''));
        $result['row_machine_large'] = trim((string) ($rowRows[1][0] ?? ''));
        $result['row_count_plus_10_large'] = trim((string) ($rowRows[1][1] ?? ''));
        $result['shift_large'] = isset($dataRows[1], $columns['shift'])
            ? trim((string) ($dataRows[1][$columns['shift']] ?? ''))
            : '';

        return $result;
    }

    private function parseDesignSheetThreadLine(array $row): ?array
    {
        $tokens = collect($row)
            ->map(fn ($cell) => trim((string) $cell))
            ->filter(fn ($cell) => $cell !== '')
            ->values()
            ->all();

        if (count($tokens) < 5) {
            return null;
        }

        $lineNoIndex = null;
        foreach ($tokens as $index => $token) {
            if (preg_match('/^\d{1,2}$/', $token)) {
                $lineNoIndex = $index;
                break;
            }
        }
        if ($lineNoIndex === null || !isset($tokens[$lineNoIndex + 2])) {
            return null;
        }

        $type = trim((string) ($tokens[$lineNoIndex + 1] ?? ''));
        $materialCode = $this->cleanCode($tokens[$lineNoIndex + 2] ?? '');
        if ($materialCode === '') {
            return null;
        }

        $numericAfter = [];
        for ($i = $lineNoIndex + 3; $i < count($tokens); $i++) {
            if ($this->isPlainNumberToken($tokens[$i])) {
                $numericAfter[] = ['index' => $i, 'value' => $this->toNumber($tokens[$i])];
            }
        }
        if (empty($numericAfter)) {
            return null;
        }

        $consumptionIndex = $numericAfter[0]['index'];
        $consumption = (float) $numericAfter[0]['value'];
        $totalGrams = isset($numericAfter[1]) ? (float) $numericAfter[1]['value'] : 0;
        if ($consumption <= 0) {
            return null;
        }

        $middle = array_slice($tokens, $lineNoIndex + 3, max(0, $consumptionIndex - ($lineNoIndex + 3)));
        $shelfHint = '';
        $materialName = '';
        if (count($middle) >= 2) {
            $shelfHint = $middle[0];
            $materialName = trim(implode(' ', array_slice($middle, 1)));
        } elseif (count($middle) === 1) {
            $materialName = $middle[0];
        }

        $role = trim(implode(' - ', array_slice($tokens, 0, $lineNoIndex)));
        if ($role === '') {
            $role = 'DONG-' . ($tokens[$lineNoIndex] ?? '');
        }

        return [
            'line_role' => $this->cleanCode($role),
            'type' => $type,
            'material_code' => $materialCode,
            'shelf_hint' => trim((string) $shelfHint),
            'material_name' => trim((string) ($materialName ?: $materialCode)),
            'consumption_per_unit' => $consumption,
            'total_grams' => $totalGrams,
        ];
    }

    private function parseDesignSheetQuantity(array $rows, array $lines): float
    {
        foreach ($rows as $rowIndex => $row) {
            $text = $this->normalizeText(implode(' ', $row));
            if (!str_contains($text, 'SO LUONG')) {
                continue;
            }
            for ($i = $rowIndex + 1; $i < min($rowIndex + 8, count($rows)); $i++) {
                foreach ($rows[$i] as $cell) {
                    if ($this->isPlainNumberToken($cell)) {
                        $value = $this->toNumber($cell);
                        if ($value > 0) {
                            return $value;
                        }
                    }
                }
            }
        }

        foreach ($lines as $line) {
            if (($line['consumption_per_unit'] ?? 0) > 0 && ($line['total_grams'] ?? 0) > 0) {
                return round((float) $line['total_grams'] / (float) $line['consumption_per_unit'], 3);
            }
        }

        return 0;
    }

    private function findValueAfterLabel(array $rows, array $labels): string
    {
        $normalizedLabels = array_map(fn ($label) => $this->normalizeText($label), $labels);
        foreach ($rows as $row) {
            foreach ($row as $index => $cell) {
                $normalized = $this->normalizeText($cell);
                foreach ($normalizedLabels as $label) {
                    if ($normalized === $label || str_starts_with($normalized, $label . ':')) {
                        $inline = trim((string) preg_replace('/^.*?:/u', '', (string) $cell));
                        if ($inline !== '' && $this->normalizeText($inline) !== $label) {
                            return $inline;
                        }
                        for ($i = $index + 1; $i < count($row); $i++) {
                            if (trim((string) $row[$i]) !== '') {
                                return trim((string) $row[$i]);
                            }
                        }
                    }
                }
            }
        }
        return '';
    }

    private function extractValueNearLabel(array $rows, array $labels): string
    {
        $normalizedLabels = array_map(fn ($label) => $this->normalizeText($label), $labels);
        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $index => $cell) {
                $normalized = $this->normalizeText($cell);
                if (!in_array($normalized, $normalizedLabels, true)) {
                    continue;
                }
                for ($i = $rowIndex + 1; $i < min($rowIndex + 4, count($rows)); $i++) {
                    $value = trim((string) ($rows[$i][$index] ?? ''));
                    if ($value !== '') {
                        return $value;
                    }
                }
                for ($i = $index + 1; $i < count($row); $i++) {
                    $value = trim((string) ($row[$i] ?? ''));
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }
        return '';
    }

    private function parseCapacityRows(array $rows): array
    {
        $start = null;
        foreach ($rows as $index => $row) {
            $text = $this->normalizeText(implode(' ', $row));
            if (str_contains($text, 'NANG LUC SAN XUAT')) {
                $start = $index;
                break;
            }
        }
        if ($start === null) {
            return [];
        }

        $result = [];
        for ($i = $start + 1; $i < min($start + 12, count($rows)); $i++) {
            $cells = collect($rows[$i])->map(fn ($cell) => trim((string) $cell))->values();
            if ($cells->filter()->isEmpty()) {
                continue;
            }
            $result[] = [
                'raw' => $cells->all(),
                'date' => $cells->first(fn ($cell) => $this->dateTokenOrNull($cell)) ?: '',
                'quantity' => collect($cells)->map(fn ($cell) => $this->isPlainNumberToken($cell) ? $this->toNumber($cell) : null)->filter(fn ($value) => $value !== null)->last(),
            ];
        }
        return $result;
    }

    private function isPlainNumberToken($value): bool
    {
        $value = trim((string) $value);
        if ($value === '' || str_contains($value, '/')) {
            return false;
        }
        return (bool) preg_match('/^-?\d+(?:[,.]\d+)?$/', $value);
    }

    private function dateTokenOrNull($value): ?string
    {
        $value = trim((string) $value);
        if (!preg_match('/^(?:\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4}|\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2})$/', $value)) {
            return null;
        }
        return $this->dateOrNull($value);
    }

    private function normalizeText($value): string
    {
        $value = mb_strtoupper(trim((string) $value));
        $map = [
            'Á'=>'A','À'=>'A','Ả'=>'A','Ã'=>'A','Ạ'=>'A','Ă'=>'A','Ắ'=>'A','Ằ'=>'A','Ẳ'=>'A','Ẵ'=>'A','Ặ'=>'A','Â'=>'A','Ấ'=>'A','Ầ'=>'A','Ẩ'=>'A','Ẫ'=>'A','Ậ'=>'A',
            'É'=>'E','È'=>'E','Ẻ'=>'E','Ẽ'=>'E','Ẹ'=>'E','Ê'=>'E','Ế'=>'E','Ề'=>'E','Ể'=>'E','Ễ'=>'E','Ệ'=>'E',
            'Í'=>'I','Ì'=>'I','Ỉ'=>'I','Ĩ'=>'I','Ị'=>'I',
            'Ó'=>'O','Ò'=>'O','Ỏ'=>'O','Õ'=>'O','Ọ'=>'O','Ô'=>'O','Ố'=>'O','Ồ'=>'O','Ổ'=>'O','Ỗ'=>'O','Ộ'=>'O','Ơ'=>'O','Ớ'=>'O','Ờ'=>'O','Ở'=>'O','Ỡ'=>'O','Ợ'=>'O',
            'Ú'=>'U','Ù'=>'U','Ủ'=>'U','Ũ'=>'U','Ụ'=>'U','Ư'=>'U','Ứ'=>'U','Ừ'=>'U','Ử'=>'U','Ữ'=>'U','Ự'=>'U',
            'Ý'=>'Y','Ỳ'=>'Y','Ỷ'=>'Y','Ỹ'=>'Y','Ỵ'=>'Y','Đ'=>'D',
        ];
        return preg_replace('/\s+/u', ' ', strtr($value, $map));
    }

    private function pagination(int $page, int $perPage, int $total): array
    {
        $totalPages = max(1, (int) ceil($total / $perPage));
        return [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'has_more' => $page < $totalPages,
        ];
    }

    private function parseTsv(string $text): array
    {
        return collect(preg_split('/\r\n|\r|\n/', trim($text)))
            ->map(fn ($line) => array_map('trim', explode("\t", $line)))
            ->filter(fn ($row) => collect($row)->filter(fn ($cell) => $cell !== '')->isNotEmpty())
            ->values()
            ->all();
    }

    private function cleanCode($value): string
    {
        return mb_strtoupper(trim((string) $value));
    }

    private function toNumber($value): float
    {
        $normalized = str_replace(',', '.', preg_replace('/[^\d,\.\-]/', '', (string) $value));
        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private function dateOrNull($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        try {
            return $this->normalizeDateFields(['date' => $value], ['date'])['date'];
        } catch (\Throwable $e) {
            return null;
        }
    }
}
