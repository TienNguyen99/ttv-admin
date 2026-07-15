<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\NormalizesDateInput;
use App\Models\InternalItemCatalog;
use App\Models\InternalProductionOrder;
use App\Models\InternalWeavingBom;
use App\Models\InternalWeavingItem;
use App\Models\InternalWeavingOrder;
use App\Models\InventoryPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InternalWeavingController extends Controller
{
    use NormalizesDateInput;

    public function index()
    {
        return view('client.internal-weaving');
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
            foreach ($data['lines'] as $line) {
                $item->boms()->create([
                    'material_code' => $this->cleanCode($line['material_code']),
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
        $validRows = collect($rows)->filter(function ($row) {
            return $this->cleanCode($row[0] ?? '') !== '' && $this->cleanCode($row[2] ?? '') !== '';
        })->values();
        $missingCatalog = $this->missingCatalogCodes($validRows->pluck(2)->all());
        if (!empty($missingCatalog)) {
            return response()->json([
                'message' => 'Import dừng: mã sợi chưa có trong DANH MỤC nội bộ: ' . implode(', ', array_slice($missingCatalog, 0, 20)),
                'missing_catalog' => $missingCatalog,
            ], 422);
        }

        DB::connection('internal')->transaction(function () use ($validRows, &$itemsTouched, &$lineCount) {
            foreach ($validRows as $row) {
                $itemCode = $this->cleanCode($row[0] ?? '');
                $materialCode = $this->cleanCode($row[2] ?? '');

                $item = InternalWeavingItem::query()->firstOrCreate(
                    ['item_code' => $itemCode],
                    [
                        'item_name' => trim((string) ($row[1] ?? '')),
                        'unit' => trim((string) ($row[5] ?? '')),
                    ]
                );
                if (trim((string) $item->item_name) === '' && trim((string) ($row[1] ?? '')) !== '') {
                    $item->item_name = trim((string) $row[1]);
                    $item->save();
                }

                InternalWeavingBom::query()->updateOrCreate(
                    ['weaving_item_id' => $item->id, 'material_code' => $materialCode],
                    [
                        'material_name' => trim((string) ($row[3] ?? '')),
                        'unit' => trim((string) ($row[5] ?? '')),
                        'consumption_per_unit' => $this->toNumber($row[4] ?? 0),
                        'waste_percent' => $this->toNumber($row[6] ?? 0),
                        'note' => trim((string) ($row[7] ?? '')),
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
        $status = trim((string) $request->query('status', ''));
        $page = max((int) $request->query('page', 1), 1);
        $perPage = min(max((int) $request->query('per_page', 50), 25), 200);

        $baseQuery = InternalProductionOrder::query()->where('is_active', true);
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
            'summary' => [
                'order_count' => InternalProductionOrder::query()->where('is_active', true)->distinct()->count('production_order'),
                'line_count' => InternalProductionOrder::query()->where('is_active', true)->count(),
                'total_quantity' => (float) InternalProductionOrder::query()->where('is_active', true)->sum('order_quantity'),
            ],
            'pagination' => $this->pagination($page, $perPage, $total),
        ]);
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
        $materialCodes = $bomRows->pluck('material_code')->map(fn ($code) => $this->cleanCode($code))->filter()->unique()->values();
        $stock = $this->stockByMaterial($materialCodes->all());
        $catalog = InternalItemCatalog::query()
            ->whereIn('item_code', $materialCodes->all())
            ->get()
            ->keyBy(fn ($row) => $this->cleanCode($row->item_code));

        $lines = $bomRows->map(function (InternalWeavingBom $bom) use ($order, $stock, $catalog) {
            $code = $this->cleanCode($bom->material_code);
            $required = round((float) $order->order_quantity * (float) $bom->consumption_per_unit * (1 + ((float) $bom->waste_percent / 100)), 3);
            $stockRow = $stock[$code] ?? ['quantity' => 0, 'locations' => collect()];
            $locations = collect($stockRow['locations'])->values();
            $catalogRow = $catalog[$code] ?? null;
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
                'unit' => $catalogRow->unit ?? $bom->unit ?? '',
                'catalog_exists' => (bool) $catalogRow,
                'catalog_name' => $catalogRow->item_name ?? '',
                'catalog_unit' => $catalogRow->unit ?? '',
                'catalog_shelf_code' => $catalogRow->shelf_code ?? '',
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
            'order' => $order,
            'data' => $lines,
            'summary' => [
                'line_count' => $lines->count(),
                'required_quantity' => (float) $lines->sum('required_quantity'),
                'short_count' => $lines->where('status', 'short')->count(),
            ],
        ]);
    }

    public function productionPlan(Request $request)
    {
        $productionOrder = trim((string) $request->query('production_order', ''));
        if ($productionOrder === '') {
            return response()->json(['message' => 'Thiếu lệnh sản xuất.'], 422);
        }

        $sourceLines = InternalProductionOrder::query()
            ->where('is_active', true)
            ->where('production_order', $productionOrder)
            ->orderBy('id')
            ->get();

        if ($sourceLines->isEmpty()) {
            return response()->json(['message' => 'Không tìm thấy lệnh sản xuất: ' . $productionOrder], 404);
        }

        $itemCodes = $sourceLines->pluck('item_code')->map(fn ($code) => $this->cleanCode($code))->filter()->unique()->values();
        $items = InternalWeavingItem::query()
            ->with('boms')
            ->whereIn('item_code', $itemCodes->all())
            ->get()
            ->keyBy(fn ($item) => $this->cleanCode($item->item_code));

        $materialRequirements = [];
        $missingBomItems = [];

        foreach ($sourceLines as $sourceLine) {
            $itemCode = $this->cleanCode($sourceLine->item_code);
            $item = $items[$itemCode] ?? null;
            if (!$item || $item->boms->isEmpty()) {
                $missingBomItems[$itemCode ?: 'CHUA-CO-MA-HANG'] = true;
                continue;
            }

            foreach ($item->boms as $bom) {
                $materialCode = $this->cleanCode($bom->material_code);
                if ($materialCode === '') continue;
                $required = round((float) $sourceLine->order_quantity * (float) $bom->consumption_per_unit * (1 + ((float) $bom->waste_percent / 100)), 3);
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
        }

        $materialCodes = array_keys($materialRequirements);
        $stock = $this->stockByMaterial($materialCodes);
        $catalog = $this->catalogByCodes($materialCodes);

        $lines = collect($materialRequirements)->map(function ($row) use ($stock, $catalog) {
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

            $required = round((float) $row['required_quantity'], 3);

            return [
                'material_code' => $code,
                'material_name' => $catalogRow->item_name ?? $row['material_name'] ?? '',
                'unit' => $catalogRow->unit ?? $row['unit'] ?? '',
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
                'customer' => trim((string) $sourceLines->pluck('customer')->filter()->first()),
                'line_count' => $sourceLines->count(),
                'item_count' => $itemCodes->count(),
                'planned_quantity' => (float) $sourceLines->sum('order_quantity'),
            ],
            'data' => $lines,
            'summary' => [
                'line_count' => $lines->count(),
                'required_quantity' => (float) $lines->sum('required_quantity'),
                'short_count' => $lines->where('status', 'short')->count(),
                'missing_catalog_count' => $lines->where('catalog_exists', false)->count(),
                'missing_bom_items' => array_keys($missingBomItems),
            ],
        ]);
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
