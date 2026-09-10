<?php

namespace App\Http\Controllers;

use App\Exceptions\InternalGoogleSyncBusyException;
use App\Exceptions\InternalGoogleSyncSourceException;
use App\Models\InternalItemCatalog;
use App\Models\InternalProductBomProfile;
use App\Models\InternalProductionOrder;
use App\Models\InternalProductionOperationProgress;
use App\Models\InternalProductionOrderBomSnapshot;
use App\Services\InternalAudit;
use App\Services\InternalGoogleSyncContext;
use App\Services\InternalGoogleSyncCoordinator;
use App\Services\InternalProductionLifecycleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class InternalProductionOrderController extends Controller
{
    private const SPREADSHEET_ID = '1nd9sOnKCq-hDf44Uo7_002qT7zoznrx7mcQoRw0oEcs';
    private const SHEET_NAME = 'LENH_SAN_XUAT';

    public function index()
    {
        return view('client.internal-production-orders');
    }

    public function workflowIndex()
    {
        return view('client.production-order-workflow');
    }

    public function storeSupplemental(Request $request)
    {
        $data = $request->validate([
            'base_item_code' => 'required|string|max:200',
            'order_quantity' => 'required|numeric|min:0.001|max:999999999999999',
            'customer' => 'nullable|string|max:200',
            'purchase_order' => 'nullable|string|max:1000',
            'received_date' => 'required|date',
            'unit' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:1000',
        ]);

        $baseCode = mb_strtoupper(trim((string) $data['base_item_code']));
        $catalog = InternalItemCatalog::query()
            ->where('is_active', true)
            ->whereRaw('UPPER(TRIM(item_code)) = ?', [$baseCode])
            ->orderByDesc('id')
            ->first();

        $order = DB::connection('internal')->transaction(function () use ($data, $baseCode, $catalog) {
            $year = Carbon::parse($data['received_date'])->format('Y');
            $prefix = 'LSP-' . $year . '-';
            $lastCode = InternalProductionOrder::query()
                ->where('production_order', 'like', $prefix . '%')
                ->orderByDesc('production_order')
                ->lockForUpdate()
                ->value('production_order');
            $sequence = preg_match('/(\d+)$/', (string) $lastCode, $matches)
                ? ((int) $matches[1]) + 1
                : 1;
            do {
                $orderCode = $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
                $sequence++;
            } while (InternalProductionOrder::query()->where('production_order', $orderCode)->exists());

            $rawData = [
                '_internal_order_type' => 'supplemental',
                '_internal_order' => [
                    'base_item_code' => $baseCode,
                    'total_order_quantity' => (float) $data['order_quantity'],
                    'created_from' => 'quick_finished_goods_receipt',
                ],
            ];

            return InternalProductionOrder::query()->create([
                'row_key' => hash('sha256', 'SUPPLEMENTAL|' . $orderCode),
                'production_order' => $orderCode,
                'purchase_order' => trim((string) ($data['purchase_order'] ?? '')),
                'customer' => trim((string) ($data['customer'] ?? '')),
                'item_code' => $baseCode,
                'standard_item_code' => $catalog ? $catalog->item_code : null,
                'standard_catalog_id' => $catalog ? $catalog->id : null,
                'is_variant_parent' => true,
                'is_manual_variant' => false,
                'specification' => '',
                'description' => trim((string) ($data['description'] ?? '')) ?: ($catalog->item_name ?? $baseCode),
                'size' => '',
                'color' => '',
                'unit' => mb_strtoupper(trim((string) ($data['unit'] ?? ''))) ?: ($catalog->unit ?? 'PCS'),
                'order_quantity' => (float) $data['order_quantity'],
                'received_date' => Carbon::parse($data['received_date'])->format('Y-m-d'),
                'status' => 'pending',
                'source_row' => null,
                'raw_data' => $rawData,
                'source_hash' => hash('sha256', json_encode($rawData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                'sync_batch' => 'manual-supplemental',
                'is_active' => true,
            ]);
        });

        app(InternalAudit::class)->model('production_order.supplemental_created', $order, [
            'production_order' => $order->production_order,
            'base_item_code' => $baseCode,
            'order_quantity' => (float) $order->order_quantity,
        ], $request);
        Cache::put(
            'internal_production_order_search_version',
            (int) Cache::get('internal_production_order_search_version', 1) + 1
        );

        return response()->json([
            'message' => "Đã tạo lệnh phụ {$order->production_order}.",
            'data' => $order,
        ], 201);
    }

    public function search(Request $request)
    {
        $keyword = trim((string) $request->query('keyword', ''));
        if (mb_strlen($keyword) < 2) {
            return response()->json(['data' => []]);
        }

        $limit = min(max((int) $request->query('limit', 20), 1), 30);
        $searchVersion = (int) Cache::get('internal_production_order_search_version', 1);
        $cacheKey = 'internal_production_order_search:' . sha1($searchVersion . '|' . mb_strtoupper($keyword) . '|' . $limit);

        $data = Cache::remember($cacheKey, now()->addSeconds(30), function () use ($keyword, $limit) {
            $contains = '%' . $keyword . '%';
            $prefix = $keyword . '%';
            $candidateLimit = $limit * 25;
            $orderCodes = InternalProductionOrder::query()
                ->where('is_active', true)
                ->where(function ($query) use ($contains) {
                    $query->where('production_order', 'like', $contains)
                        ->orWhere('purchase_order', 'like', $contains)
                        ->orWhere('customer', 'like', $contains)
                        ->orWhere('item_code', 'like', $contains)
                        ->orWhere('standard_item_code', 'like', $contains)
                        ->orWhere('description', 'like', $contains)
                        ->orWhere('size', 'like', $contains)
                        ->orWhere('color', 'like', $contains);
                })
                ->orderByRaw(
                    'CASE WHEN production_order LIKE ? THEN 0 WHEN item_code LIKE ? OR standard_item_code LIKE ? THEN 1 ELSE 2 END',
                    [$prefix, $prefix, $prefix]
                )
                ->orderByDesc('updated_at')
                ->limit($candidateLimit)
                ->pluck('production_order')
                ->map(fn ($code) => trim((string) $code))
                ->filter()
                ->unique()
                ->take($limit)
                ->values();

            if ($orderCodes->isEmpty()) {
                return [];
            }

            $position = $orderCodes->flip();
            return InternalProductionOrder::query()
                ->select([
                    'production_order', 'customer', 'purchase_order', 'promised_date',
                    'item_code', 'standard_item_code', 'description', 'size', 'color',
                    'unit', 'order_quantity', 'raw_data', 'sync_batch',
                ])
                ->where('is_active', true)
                ->whereIn('production_order', $orderCodes->all())
                ->orderBy('id')
                ->get()
                ->groupBy('production_order')
                ->map(function ($rows, $productionOrder) {
                    $first = $rows->first();
                    $rawData = is_array($first->raw_data) ? $first->raw_data : [];
                    $orderType = ($rawData['_internal_order_type'] ?? '') === 'supplemental'
                        || $first->sync_batch === 'manual-supplemental'
                        ? 'supplemental'
                        : 'standard';
                    return [
                        'production_order' => trim((string) $productionOrder),
                        'order_type' => $orderType,
                        'order_quantity' => $this->plannedQuantityForOrderLines($rows),
                        'customer' => trim((string) $first->customer),
                        'purchase_order' => trim((string) $first->purchase_order),
                        'promised_date' => optional($first->promised_date)->format('Y-m-d'),
                        'items' => $rows->map(fn ($row) => [
                            'item_code' => trim((string) ($row->standard_item_code ?: $row->item_code)),
                            'source_item_code' => trim((string) $row->item_code),
                            'item_name' => trim((string) $row->description),
                            'size' => trim((string) $row->size),
                            'color' => trim((string) $row->color),
                            'unit' => trim((string) $row->unit) ?: 'PCS',
                            'order_quantity' => (float) $row->order_quantity,
                        ])->unique(fn ($row) => implode('|', [
                            mb_strtoupper($row['item_code']),
                            mb_strtoupper($row['size']),
                            mb_strtoupper($row['color']),
                        ]))->values()->all(),
                    ];
                })
                ->sortBy(fn ($row) => $position->get($row['production_order'], PHP_INT_MAX))
                ->values()
                ->all();
        });

        return response()->json(['data' => $data]);
    }

    public function workflow(Request $request, ?InternalProductionLifecycleService $lifecycleService = null)
    {
        $lifecycleService = $lifecycleService ?: app(InternalProductionLifecycleService::class);
        $keyword = mb_strtoupper(trim((string) $request->query('keyword', '')));
        $status = trim((string) $request->query('status', ''));
        $limit = min(max((int) $request->query('limit', 250), 1), 1000);

        $orders = InternalProductionOrder::query()
            ->where('is_active', true)
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->where(function ($q) use ($keyword) {
                    $like = '%' . $keyword . '%';
                    $q->whereRaw('UPPER(production_order) LIKE ?', [$like])
                        ->orWhereRaw('UPPER(purchase_order) LIKE ?', [$like])
                        ->orWhereRaw('UPPER(customer) LIKE ?', [$like])
                        ->orWhereRaw('UPPER(item_code) LIKE ?', [$like])
                        ->orWhereRaw('UPPER(standard_item_code) LIKE ?', [$like])
                        ->orWhereRaw('UPPER(description) LIKE ?', [$like])
                        ->orWhereRaw('UPPER(size) LIKE ?', [$like])
                        ->orWhereRaw('UPPER(color) LIKE ?', [$like]);
                });
            })
            ->orderByRaw('promised_date IS NULL')
            ->orderBy('promised_date')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        $orderCodes = $orders->pluck('production_order')->filter()->unique()->values();
        $orderIds = $orders->pluck('id')->map(fn ($id) => (int) $id)->filter()->values();
        $catalogIds = $orders->pluck('standard_catalog_id')->filter()->unique()->values();
        $catalogCodes = $orders->flatMap(function ($order) {
            return [$order->standard_item_code, $order->item_code];
        })->map(fn ($code) => trim((string) $code))->filter()->unique()->values();
        $catalogRows = collect();
        if ($catalogIds->isNotEmpty() || $catalogCodes->isNotEmpty()) {
            $catalogRows = InternalItemCatalog::query()
                ->select('id', 'item_code', 'item_name', 'unit', 'size', 'color', 'image_url', 'source_row')
                ->where('is_active', true)
                ->where(function ($query) use ($catalogIds, $catalogCodes) {
                    if ($catalogIds->isNotEmpty()) {
                        $query->whereIn('id', $catalogIds->all());
                    }
                    if ($catalogCodes->isNotEmpty()) {
                        $method = $catalogIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                        $query->{$method}('item_code', $catalogCodes->all());
                    }
                })
                ->orderBy('source_row')
                ->get();
        }
        $catalogsById = $catalogRows->keyBy('id');
        $catalogsByCode = $catalogRows->keyBy(
            fn ($catalog) => mb_strtoupper(trim((string) $catalog->item_code))
        );

        if ($orderCodes->isEmpty()) {
            return response()->json([
                'data' => [],
                'summary' => $this->workflowSummary(collect()),
            ]);
        }

        $bomProfiles = InternalProductBomProfile::query()
            ->with(['routings' => fn ($query) => $query->orderBy('sequence')])
            ->withCount('lines')
            ->where('status', 'active')
            ->whereIn('item_code', $catalogCodes->map(fn ($code) => mb_strtoupper($code))->all())
            ->get()
            ->keyBy(fn ($profile) => mb_strtoupper(trim((string) $profile->item_code)));
        $snapshotItemsByOrder = InternalProductionOrderBomSnapshot::query()
            ->where('order_type', 'central')
            ->whereIn('production_order_code', $orderCodes->all())
            ->select('production_order_code', 'finished_item_code')
            ->distinct()
            ->get()
            ->groupBy('production_order_code')
            ->map(fn ($rows) => $rows->pluck('finished_item_code')->map(fn ($code) => mb_strtoupper(trim((string) $code)))->unique()->values());
        $operationProgressByOrder = InternalProductionOperationProgress::query()
            ->whereIn('production_order_code', $orderCodes->all())
            ->orderBy('sequence')
            ->get()
            ->groupBy('production_order_code');

        $receiptRows = DB::connection('internal')->table('internal_material_receipt_lines as l')
            ->join('internal_material_receipts as r', 'r.id', '=', 'l.receipt_id')
            ->leftJoin('internal_production_orders as linked_order', 'linked_order.id', '=', 'l.production_order_id')
            ->where('r.status', 'posted')
            ->where(function ($query) {
                $query->where('r.source', 'Phieu nhap thanh pham')
                    ->orWhere('r.receipt_code', 'like', 'PNTP-%');
            })
            ->where(function ($query) use ($orderCodes, $orderIds) {
                $query->whereIn('l.production_order', $orderCodes->all())
                    ->orWhereIn('l.production_order_id', $orderIds->all());
            })
            ->selectRaw("COALESCE(linked_order.production_order, NULLIF(l.production_order, '')) as production_order")
            ->selectRaw('SUM(l.quantity) as quantity')
            ->selectRaw('COUNT(*) as line_count')
            ->selectRaw('COUNT(DISTINCT r.id) as document_count')
            ->selectRaw("GROUP_CONCAT(DISTINCT r.receipt_code ORDER BY r.receipt_date SEPARATOR ', ') as document_codes")
            ->groupBy('production_order')
            ->get()
            ->keyBy('production_order');

        $issueRows = DB::connection('internal')->table('internal_material_issue_lines as l')
            ->join('internal_material_issues as i', 'i.id', '=', 'l.issue_id')
            ->leftJoin('internal_production_orders as linked_order', 'linked_order.id', '=', 'l.production_order_id')
            ->where('i.status', 'posted')
            ->where(function ($query) use ($orderCodes, $orderIds) {
                $query->whereIn('l.production_order', $orderCodes->all())
                    ->orWhereIn('l.production_order_id', $orderIds->all());
            })
            ->selectRaw("COALESCE(linked_order.production_order, NULLIF(l.production_order, '')) as production_order")
            ->selectRaw("SUM(CASE WHEN i.issue_type = 'material' THEN l.quantity ELSE 0 END) as material_quantity")
            ->selectRaw("SUM(CASE WHEN i.issue_type = 'production' THEN l.quantity ELSE 0 END) as production_quantity")
            ->selectRaw("SUM(CASE WHEN i.issue_type = 'customer' THEN l.quantity ELSE 0 END) as customer_quantity")
            ->selectRaw("COUNT(DISTINCT CASE WHEN i.issue_type = 'material' THEN i.id END) as material_document_count")
            ->selectRaw("COUNT(DISTINCT CASE WHEN i.issue_type = 'production' THEN i.id END) as production_document_count")
            ->selectRaw("COUNT(DISTINCT CASE WHEN i.issue_type = 'customer' THEN i.id END) as customer_document_count")
            ->selectRaw("GROUP_CONCAT(DISTINCT CASE WHEN i.issue_type = 'material' THEN i.issue_code END ORDER BY i.issue_date SEPARATOR ', ') as material_issue_codes")
            ->selectRaw("GROUP_CONCAT(DISTINCT CASE WHEN i.issue_type = 'production' THEN i.issue_code END ORDER BY i.issue_date SEPARATOR ', ') as production_issue_codes")
            ->selectRaw("GROUP_CONCAT(DISTINCT CASE WHEN i.issue_type = 'customer' THEN i.issue_code END ORDER BY i.issue_date SEPARATOR ', ') as customer_issue_codes")
            ->groupBy('production_order')
            ->get()
            ->keyBy('production_order');

        $rows = $orders
            ->groupBy('production_order')
            ->map(function ($lines, $productionOrder) use ($receiptRows, $issueRows, $catalogsById, $catalogsByCode, $bomProfiles, $snapshotItemsByOrder, $operationProgressByOrder) {
                $first = $lines->first();
                $receipt = $receiptRows->get($productionOrder);
                $issue = $issueRows->get($productionOrder);
                $plannedQuantity = $this->plannedQuantityForOrderLines($lines);
                $receivedQuantity = (float) ($receipt->quantity ?? 0);
                $issuedMaterial = (float) ($issue->material_quantity ?? 0);
                $issuedProduction = (float) ($issue->production_quantity ?? 0);
                $issuedCustomer = (float) ($issue->customer_quantity ?? 0);
                $remainingAfterCustomer = $receivedQuantity - $issuedCustomer;
                $items = $lines->map(function ($line) use ($catalogsById, $catalogsByCode) {
                    $sourceItemCode = trim((string) $line->item_code);
                    $standardItemCode = trim((string) $line->standard_item_code);
                    $catalog = $line->standard_catalog_id ? $catalogsById->get($line->standard_catalog_id) : null;
                    if ($catalog && $standardItemCode !== ''
                        && mb_strtoupper(trim((string) $catalog->item_code)) !== mb_strtoupper($standardItemCode)) {
                        $catalog = null;
                    }
                    if (!$catalog) {
                        $catalog = $catalogsByCode->get(
                            mb_strtoupper($standardItemCode !== '' ? $standardItemCode : $sourceItemCode)
                        );
                    }
                    return [
                        'id' => (int) $line->id,
                        'production_order' => trim((string) $line->production_order),
                        'item_code' => trim((string) ($catalog->item_code ?? '')) ?: ($standardItemCode !== '' ? $standardItemCode : $sourceItemCode),
                        'source_item_code' => $sourceItemCode,
                        'standard_item_code' => $standardItemCode,
                        'variant_item_code' => $standardItemCode !== '' && mb_strtoupper($standardItemCode) !== mb_strtoupper($sourceItemCode)
                            ? $standardItemCode
                            : null,
                        'variant_parent_id' => $line->variant_parent_id ? (int) $line->variant_parent_id : null,
                        'is_variant' => (bool) $line->variant_parent_id,
                        'is_variant_parent' => (bool) $line->is_variant_parent,
                        'standard_catalog_id' => $catalog ? (int) $catalog->id : null,
                        'catalog_id' => $catalog ? (int) $catalog->id : null,
                        'image_url' => trim((string) ($catalog->image_url ?? '')),
                        'description' => trim((string) ($catalog->item_name ?? '')) ?: trim((string) $line->description),
                        'size' => trim((string) ($catalog->size ?? '')) ?: trim((string) $line->size),
                        'color' => trim((string) ($catalog->color ?? '')) ?: trim((string) $line->color),
                        'quantity' => (float) $line->order_quantity,
                        'unit' => trim((string) ($catalog->unit ?? '')) ?: trim((string) $line->unit),
                    ];
                })->values();
                $lifecycle = $this->buildLifecycle(
                    $productionOrder,
                    $items,
                    $bomProfiles,
                    $snapshotItemsByOrder->get($productionOrder, collect()),
                    $operationProgressByOrder->get($productionOrder, collect()),
                    $issuedMaterial,
                    $issuedProduction,
                    $receivedQuantity,
                    $plannedQuantity,
                    $issuedCustomer
                );
                $status = $this->workflowStatus(
                    $plannedQuantity,
                    $receivedQuantity,
                    $issuedMaterial,
                    $issuedProduction,
                    $issuedCustomer,
                    $lifecycle
                );

                return [
                    'production_order' => $productionOrder,
                    'customer' => trim((string) $first->customer),
                    'purchase_order' => trim((string) $first->purchase_order),
                    'tracking_staff' => trim((string) $first->tracking_staff),
                    'promised_date' => optional($first->promised_date)->format('Y-m-d'),
                    'customer_requested_date' => optional($first->customer_requested_date)->format('Y-m-d'),
                    'delivery_place' => trim((string) $first->delivery_place),
                    'line_count' => $lines->count(),
                    'planned_quantity' => $plannedQuantity,
                    'received_quantity' => $receivedQuantity,
                    'material_issue_quantity' => $issuedMaterial,
                    'production_issue_quantity' => $issuedProduction,
                    'customer_issue_quantity' => $issuedCustomer,
                    'remaining_quantity' => $remainingAfterCustomer,
                    'material_document_count' => (int) ($issue->material_document_count ?? 0),
                    'production_document_count' => (int) ($issue->production_document_count ?? 0),
                    'customer_document_count' => (int) ($issue->customer_document_count ?? 0),
                    'receipt_document_count' => (int) ($receipt->document_count ?? 0),
                    'receipt_codes' => $this->splitCodes($receipt->document_codes ?? ''),
                    'material_issue_codes' => $this->splitCodes($issue->material_issue_codes ?? ''),
                    'production_issue_codes' => $this->splitCodes($issue->production_issue_codes ?? ''),
                    'customer_issue_codes' => $this->splitCodes($issue->customer_issue_codes ?? ''),
                    'status' => $status,
                    'lifecycle' => $lifecycle,
                    'items' => $items,
                ];
            })
            ->values();

        $rows = $lifecycleService
            ->enrich($orders, $rows)
            ->filter(function ($row) use ($status) {
                return $status === '' || $row['status'] === $status;
            })
            ->when($request->boolean('exceptions'), function ($rows) {
                return $rows->filter(fn ($row) => (int) ($row['warning_count'] ?? 0) > 0);
            })
            ->values();

        return response()->json([
            'data' => $rows,
            'summary' => $this->workflowSummary($rows),
        ]);
    }

    public function lifecycle(InternalProductionOrder $order, InternalProductionLifecycleService $lifecycleService)
    {
        return response()->json([
            'data' => $lifecycleService->detail($order),
        ]);
    }

    public function updateStandardItemCode(Request $request, InternalProductionOrder $order)
    {
        $data = $request->validate([
            'standard_catalog_id' => 'nullable|integer',
            'standard_item_code' => 'nullable|string|max:200',
            'reset' => 'sometimes|boolean',
        ]);
        $reset = (bool) ($data['reset'] ?? false);
        $catalogId = (int) ($data['standard_catalog_id'] ?? 0);
        $standardItemCode = trim((string) ($data['standard_item_code'] ?? ''));
        $catalog = null;

        if (!$reset && $catalogId > 0) {
            $catalog = InternalItemCatalog::query()->where('is_active', true)->find($catalogId);
            if (!$catalog) {
                return response()->json([
                    'message' => 'Dòng mã chuẩn không tồn tại trong Danh mục nội bộ.',
                ], 422);
            }

            if ($standardItemCode !== '' && mb_strtoupper(trim((string) $catalog->item_code)) !== mb_strtoupper($standardItemCode)) {
                return response()->json([
                    'message' => 'Mã đã gõ không khớp dòng Danh mục được chọn. Hãy chọn lại đúng dòng.',
                ], 422);
            }
        } elseif (!$reset && $standardItemCode !== '') {
            $catalogs = InternalItemCatalog::query()
                ->where('is_active', true)
                ->whereRaw('UPPER(TRIM(item_code)) = ?', [mb_strtoupper($standardItemCode)])
                ->orderBy('source_row')
                ->limit(2)
                ->get();

            if ($catalogs->count() === 1) {
                $catalog = $catalogs->first();
            } elseif ($catalogs->count() > 1) {
                return response()->json([
                    'message' => 'Có nhiều dòng Danh mục cùng mã. Hãy chọn đúng dòng theo tên, màu hoặc ảnh.',
                ], 422);
            } else {
                return response()->json([
                    'message' => 'Mã chuẩn chưa tồn tại trong Danh mục nội bộ.',
                ], 422);
            }
        } elseif (!$reset) {
            return response()->json([
                'message' => 'Hãy nhập hoặc chọn một mã chuẩn. Dùng nút "Dùng mã gốc" nếu muốn bỏ liên kết.',
            ], 422);
        }

        $order->standard_catalog_id = $catalog ? $catalog->id : null;
        $order->standard_item_code = $catalog ? trim((string) $catalog->item_code) : null;
        $order->save();
        Cache::forget('internal_catalog_customer_map_v1');

        return response()->json([
            'message' => 'Đã cập nhật mã hàng chuẩn.',
            'data' => [
                'id' => (int) $order->id,
                'source_item_code' => trim((string) $order->item_code),
                'standard_item_code' => trim((string) $order->standard_item_code),
                'standard_catalog_id' => $order->standard_catalog_id ? (int) $order->standard_catalog_id : null,
                'item_code' => trim((string) ($order->standard_item_code ?: $order->item_code)),
                'image_url' => $catalog ? trim((string) $catalog->image_url) : '',
            ],
        ]);
    }

    public function updateOperationProgress(Request $request)
    {
        $data = $request->validate([
            'production_order' => 'required|string|max:100',
            'operation_code' => 'required|string|max:50',
            'status' => 'required|in:pending,in_progress,completed',
            'updated_by' => 'nullable|string|max:150',
            'note' => 'nullable|string|max:500',
        ]);
        $orderCode = trim($data['production_order']);
        $operationCode = mb_strtoupper(trim($data['operation_code']));
        $orders = InternalProductionOrder::query()
            ->where('is_active', true)
            ->where('production_order', $orderCode)
            ->get();
        if ($orders->isEmpty()) {
            return response()->json(['message' => 'Lệnh sản xuất không tồn tại.'], 404);
        }

        $itemCodes = $orders->map(fn ($order) => mb_strtoupper(trim((string) ($order->standard_item_code ?: $order->item_code))))->filter()->unique();
        $routing = DB::connection('internal')->table('internal_product_routings as routing')
            ->join('internal_product_bom_profiles as profile', 'profile.id', '=', 'routing.profile_id')
            ->where('profile.status', 'active')
            ->whereIn('profile.item_code', $itemCodes->all())
            ->where('routing.operation_code', $operationCode)
            ->orderBy('routing.sequence')
            ->select('routing.operation_code', 'routing.operation_name', 'routing.sequence')
            ->first();
        if (!$routing) {
            return response()->json(['message' => 'Công đoạn chưa có trong BOM của mã hàng thuộc lệnh này.'], 422);
        }

        $progress = InternalProductionOperationProgress::query()->firstOrNew([
            'production_order_code' => $orderCode,
            'operation_code' => $operationCode,
        ]);
        $progress->operation_name = $routing->operation_name;
        $progress->sequence = (int) $routing->sequence;
        $progress->status = $data['status'];
        $progress->updated_by = trim((string) ($data['updated_by'] ?? '')) ?: null;
        $progress->note = trim((string) ($data['note'] ?? '')) ?: null;
        if ($data['status'] === 'in_progress') {
            $progress->started_at = $progress->started_at ?: now();
            $progress->completed_at = null;
        } elseif ($data['status'] === 'completed') {
            $progress->started_at = $progress->started_at ?: now();
            $progress->completed_at = now();
        } else {
            $progress->started_at = null;
            $progress->completed_at = null;
        }
        $progress->save();

        app(InternalAudit::class)->model('production_operation.updated', $progress, [
            'production_order' => $orderCode,
            'operation_code' => $operationCode,
            'status' => $data['status'],
        ], $request);

        return response()->json(['message' => 'Đã cập nhật công đoạn ' . $routing->operation_name . '.', 'data' => $progress]);
    }

    public function data(Request $request)
    {
        $query = InternalProductionOrder::query()->where('is_active', true);
        $keyword = trim((string) $request->query('keyword', ''));
        $status = trim((string) $request->query('status', ''));
        $productionOrder = trim((string) $request->query('production_order', ''));
        $firstFinishedReceiptDate = DB::connection('internal')
            ->table('internal_material_receipts')
            ->where(function ($receiptQuery) {
                $receiptQuery->where('source', 'Phieu nhap thanh pham')
                    ->orWhere('receipt_code', 'like', 'PNTP-%');
            })
            ->min('receipt_date');

        // Keep the browse list date-safe. A targeted search must still expose an
        // active order whose source sheet has not supplied its received date yet.
        if ($productionOrder === '' && $request->filled('order_date_to')) {
            if ($keyword === '') {
                $query->whereNotNull('received_date')
                    ->whereDate('received_date', '<=', $request->query('order_date_to'));
            } else {
                $query->where(function ($dateQuery) use ($request) {
                    $dateQuery->whereNull('received_date')
                        ->orWhereDate('received_date', '<=', $request->query('order_date_to'));
                });
            }
            // Keep the default list within the managed period, while targeted
            // item/order searches may still find older orders that need handling.
            if ($firstFinishedReceiptDate && $keyword === '') {
                $query->where(function ($eligibleOrderQuery) use ($firstFinishedReceiptDate) {
                    $eligibleOrderQuery->whereDate('received_date', '>=', $firstFinishedReceiptDate)
                        ->orWhereRaw("EXISTS (
                            SELECT 1
                            FROM internal_material_receipt_lines AS linked_line
                            INNER JOIN internal_material_receipts AS linked_receipt
                                ON linked_receipt.id = linked_line.receipt_id
                            WHERE linked_line.production_order = internal_production_orders.production_order
                              AND linked_receipt.source = 'Phieu nhap thanh pham'
                        )");
                });
            }
        }

        if ($request->boolean('unfinished') && $productionOrder === '') {
            $receivedQuantitySql = "COALESCE((
                SELECT SUM(receipt_line.quantity)
                FROM internal_material_receipt_lines AS receipt_line
                INNER JOIN internal_material_receipts AS receipt
                    ON receipt.id = receipt_line.receipt_id
                WHERE receipt_line.production_order = internal_production_orders.production_order
                  AND receipt.source = 'Phieu nhap thanh pham'
            ), 0)";
            $plannedQuantitySql = "COALESCE((
                SELECT MAX(CAST(JSON_UNQUOTE(JSON_EXTRACT(planned_variant.raw_data, '$._internal_variant.source_quantity')) AS DECIMAL(18, 3)))
                FROM internal_production_orders AS planned_variant
                WHERE planned_variant.production_order = internal_production_orders.production_order
                  AND planned_variant.is_active = 1
                  AND planned_variant.is_manual_variant = 1
            ), (
                SELECT SUM(planned_order.order_quantity)
                FROM internal_production_orders AS planned_order
                WHERE planned_order.production_order = internal_production_orders.production_order
                  AND planned_order.is_active = 1
            ), 0)";

            // A blank synced quantity is unknown, not a completed production order.
            $query->whereRaw("({$plannedQuantitySql} <= 0 OR {$receivedQuantitySql} < {$plannedQuantitySql})");
        }

        if ($productionOrder !== '') {
            $query->where('production_order', $productionOrder);
        }

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('production_order', 'like', '%' . $keyword . '%')
                    ->orWhere('purchase_order', 'like', '%' . $keyword . '%')
                    ->orWhere('customer', 'like', '%' . $keyword . '%')
                    ->orWhere('item_code', 'like', '%' . $keyword . '%')
                    ->orWhere('standard_item_code', 'like', '%' . $keyword . '%')
                    ->orWhere('specification', 'like', '%' . $keyword . '%')
                    ->orWhere('description', 'like', '%' . $keyword . '%')
                    ->orWhere('tracking_staff', 'like', '%' . $keyword . '%')
                    ->orWhere('size', 'like', '%' . $keyword . '%')
                    ->orWhere('color', 'like', '%' . $keyword . '%')
                    ->orWhere('unit', 'like', '%' . $keyword . '%')
                    ->orWhere('location', 'like', '%' . $keyword . '%')
                    ->orWhere('delivery_place', 'like', '%' . $keyword . '%')
                    ->orWhere('status', 'like', '%' . $keyword . '%')
                    ->orWhere('order_quantity', 'like', '%' . $keyword . '%')
                    ->orWhere('received_date', 'like', '%' . $keyword . '%')
                    ->orWhere('promised_date', 'like', '%' . $keyword . '%')
                    ->orWhere('customer_requested_date', 'like', '%' . $keyword . '%')
                    ->orWhere('source_row', 'like', '%' . $keyword . '%')
                    ->orWhereRaw('CAST(raw_data AS CHAR) LIKE ?', ['%' . $keyword . '%']);
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('promised_date', '>=', $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('promised_date', '<=', $request->query('to_date'));
        }

        $summaryQuery = clone $query;
        $summaryStats = (clone $summaryQuery)
            ->selectRaw("COUNT(DISTINCT production_order) as order_count")
            ->selectRaw('COUNT(*) as variant_count')
            ->selectRaw('COALESCE(SUM(order_quantity), 0) as total_quantity')
            ->selectRaw("SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count")
            ->selectRaw("SUM(CASE WHEN status = 'due' THEN 1 ELSE 0 END) as due_count")
            ->selectRaw('COUNT(DISTINCT customer) as customer_count')
            ->selectRaw('(SELECT MAX(sync_order.updated_at) FROM internal_production_orders AS sync_order) as last_synced_at')
            ->first();
        $isPaged = $request->has('page') || $request->has('per_page');
        $page = max((int) $request->query('page', 1), 1);
        $perPage = min(max((int) $request->query('per_page', 100), 25), 300);
        $limit = min(max((int) $request->query('limit', 500), 1), 2000);
        if ($productionOrder !== '') {
            $query->orderBy('source_row');
        } elseif ($keyword !== '') {
            $contains = '%' . $keyword . '%';
            $query->orderByRaw(
                'CASE WHEN production_order = ? THEN 0 WHEN production_order LIKE ? THEN 1 WHEN item_code LIKE ? OR standard_item_code LIKE ? THEN 2 ELSE 3 END',
                [$keyword, $contains, $contains, $contains]
            )
                ->orderByDesc('updated_at');
        } else {
            $query->orderByRaw('promised_date IS NULL')
                ->orderBy('promised_date')
                ->orderByDesc('production_order');
        }

        $rowsQuery = clone $query;
        $rows = $isPaged
            ? $rowsQuery->skip(($page - 1) * $perPage)->take($perPage)->get()
            : $rowsQuery->limit($limit)->get();
        $totalRows = (int) ($summaryStats->variant_count ?? 0);

        if (($request->boolean('unfinished') || $request->boolean('with_progress') || $productionOrder !== '') && $rows->isNotEmpty()) {
            $orderCodes = $rows->pluck('production_order')->filter()->unique()->values();
            $plannedByOrder = InternalProductionOrder::query()
                ->where('is_active', true)
                ->whereIn('production_order', $orderCodes)
                ->get()
                ->groupBy('production_order')
                ->map(fn ($lines) => $this->plannedQuantityForOrderLines($lines));
            $receiptsByOrder = collect();
            $issuesByOrder = collect();
            try {
                $receiptsByOrder = DB::connection('internal')
                    ->table('internal_material_receipt_lines as receipt_line')
                    ->join('internal_material_receipts as receipt', 'receipt.id', '=', 'receipt_line.receipt_id')
                    ->whereIn('receipt_line.production_order', $orderCodes)
                    ->where('receipt.source', 'Phieu nhap thanh pham')
                    ->select(
                        'receipt_line.production_order',
                        DB::raw('SUM(receipt_line.quantity) as received_quantity'),
                        DB::raw("GROUP_CONCAT(DISTINCT receipt.receipt_code ORDER BY receipt.receipt_date SEPARATOR ',') as receipt_codes")
                    )
                    ->groupBy('receipt_line.production_order')
                    ->get()
                    ->keyBy('production_order');
            } catch (\Throwable $exception) {
                report($exception);
            }
            try {
                $issuesByOrder = DB::connection('internal')
                    ->table('internal_material_issue_lines as issue_line')
                    ->join('internal_material_issues as issue', 'issue.id', '=', 'issue_line.issue_id')
                    ->whereIn('issue_line.production_order', $orderCodes)
                    ->select(
                        'issue_line.production_order',
                        DB::raw("SUM(CASE WHEN issue.issue_type = 'production' THEN issue_line.quantity ELSE 0 END) as production_issue_quantity"),
                        DB::raw("SUM(CASE WHEN issue.issue_type = 'customer' THEN issue_line.quantity ELSE 0 END) as customer_issue_quantity"),
                        DB::raw("GROUP_CONCAT(DISTINCT CASE WHEN issue.issue_type = 'production' THEN issue.issue_code END ORDER BY issue.issue_date SEPARATOR ',') as production_issue_codes"),
                        DB::raw("GROUP_CONCAT(DISTINCT CASE WHEN issue.issue_type = 'customer' THEN issue.issue_code END ORDER BY issue.issue_date SEPARATOR ',') as customer_issue_codes")
                    )
                    ->groupBy('issue_line.production_order')
                    ->get()
                    ->keyBy('production_order');
            } catch (\Throwable $exception) {
                report($exception);
            }

            $rows->each(function ($row) use ($plannedByOrder, $receiptsByOrder, $issuesByOrder) {
                $planned = (float) ($plannedByOrder[$row->production_order] ?? 0);
                $receipt = $receiptsByOrder->get($row->production_order);
                $issue = $issuesByOrder->get($row->production_order);
                $received = (float) ($receipt->received_quantity ?? 0);
                $issuedToProduction = (float) ($issue->production_issue_quantity ?? 0);
                $issuedToCustomer = (float) ($issue->customer_issue_quantity ?? 0);
                $row->setAttribute('planned_quantity', $planned);
                $row->setAttribute('has_planned_quantity', $planned > 0);
                $row->setAttribute('received_quantity', $received);
                $row->setAttribute('remaining_quantity', max($planned - $received, 0));
                $row->setAttribute('available_quantity', $received - $issuedToCustomer);
                $row->setAttribute('production_issue_quantity', $issuedToProduction);
                $row->setAttribute('customer_issue_quantity', $issuedToCustomer);
                $row->setAttribute('receipt_codes', $this->splitCodes($receipt->receipt_codes ?? ''));
                $row->setAttribute('production_issue_codes', $this->splitCodes($issue->production_issue_codes ?? ''));
                $row->setAttribute('customer_issue_codes', $this->splitCodes($issue->customer_issue_codes ?? ''));
            });
        }

        // API consumers use the central standard code while the synced source code stays intact.
        $dataCatalogIds = $rows->pluck('standard_catalog_id')->filter()->unique()->values();
        $dataCatalogCodes = $rows->pluck('standard_item_code')
            ->map(fn ($code) => trim((string) $code))
            ->filter()
            ->unique()
            ->values();
        $dataCatalogs = InternalItemCatalog::query()
            ->where('is_active', true)
            ->where(function ($query) use ($dataCatalogIds, $dataCatalogCodes) {
                if ($dataCatalogIds->isNotEmpty()) {
                    $query->whereIn('id', $dataCatalogIds->all());
                }
                if ($dataCatalogCodes->isNotEmpty()) {
                    $method = $dataCatalogIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('item_code', $dataCatalogCodes->all());
                }
            })
            ->get();
        $dataCatalogsById = $dataCatalogs->keyBy('id');
        $dataCatalogsByCode = $dataCatalogs->keyBy(
            fn ($catalog) => mb_strtoupper(trim((string) $catalog->item_code))
        );
        $rows->each(function ($row) use ($dataCatalogsById, $dataCatalogsByCode) {
            $sourceItemCode = trim((string) $row->item_code);
            $standardItemCode = trim((string) $row->standard_item_code);
            $catalog = $row->standard_catalog_id ? $dataCatalogsById->get($row->standard_catalog_id) : null;
            if ($catalog && $standardItemCode !== ''
                && mb_strtoupper(trim((string) $catalog->item_code)) !== mb_strtoupper($standardItemCode)) {
                $catalog = null;
            }
            if (!$catalog && $standardItemCode !== '') {
                $catalog = $dataCatalogsByCode->get(mb_strtoupper($standardItemCode));
            }
            $row->setAttribute('source_item_code', $sourceItemCode);
            $row->setAttribute('item_code', trim((string) ($catalog->item_code ?? '')) ?: ($standardItemCode !== '' ? $standardItemCode : $sourceItemCode));
            if ($catalog) {
                $row->setAttribute('description', trim((string) $catalog->item_name) ?: $row->description);
                $row->setAttribute('size', trim((string) $catalog->size) ?: $row->size);
                $row->setAttribute('color', trim((string) $catalog->color) ?: $row->color);
                $row->setAttribute('unit', trim((string) $catalog->unit) ?: $row->unit);
            }
        });

        $receiptProgress = null;
        if ($productionOrder !== '') {
            $plannedQuantity = $this->plannedQuantityForOrderLines($rows);
            $orderDate = optional((clone $summaryQuery)->orderBy('received_date')->first())->received_date;
            $progressRow = $rows->first();
            $receivedQuantity = (float) ($progressRow->received_quantity ?? 0);
            $receiptCodes = $progressRow->receipt_codes ?? [];
            $productionIssueCodes = $progressRow->production_issue_codes ?? [];
            $customerIssueCodes = $progressRow->customer_issue_codes ?? [];
            $receiptProgress = [
                'planned_quantity' => $plannedQuantity,
                'has_planned_quantity' => $plannedQuantity > 0,
                'received_quantity' => $receivedQuantity,
                'remaining_quantity' => max($plannedQuantity - $receivedQuantity, 0),
                'excess_quantity' => max($receivedQuantity - $plannedQuantity, 0),
                'is_over_received' => $receivedQuantity > $plannedQuantity + 0.0001,
                'available_quantity' => (float) ($progressRow->available_quantity ?? $receivedQuantity),
                'production_issue_quantity' => (float) ($progressRow->production_issue_quantity ?? 0),
                'customer_issue_quantity' => (float) ($progressRow->customer_issue_quantity ?? 0),
                'receipt_codes' => $receiptCodes,
                'production_issue_codes' => $productionIssueCodes,
                'customer_issue_codes' => $customerIssueCodes,
                'order_date' => $orderDate ? $orderDate->format('Y-m-d') : null,
                'receipt_data_start_date' => $firstFinishedReceiptDate,
                'has_linked_finished_receipt' => count($receiptCodes) > 0,
            ];
        }

        return response()->json([
            'data' => $rows,
            'summary' => [
                'order_count' => (int) ($summaryStats->order_count ?? 0),
                'variant_count' => (int) ($summaryStats->variant_count ?? 0),
                'total_quantity' => (float) ($summaryStats->total_quantity ?? 0),
                'late_count' => (int) ($summaryStats->late_count ?? 0),
                'due_count' => (int) ($summaryStats->due_count ?? 0),
                'customer_count' => (int) ($summaryStats->customer_count ?? 0),
                'last_synced_at' => $summaryStats->last_synced_at ?? null,
                'receipt_progress' => $receiptProgress,
            ],
            'pagination' => [
                'page' => $isPaged ? $page : 1,
                'per_page' => $isPaged ? $perPage : $limit,
                'total' => $totalRows,
                'total_pages' => $isPaged ? (int) ceil($totalRows / $perPage) : 1,
                'has_more' => $isPaged ? ($page * $perPage < $totalRows) : ($rows->count() < $totalRows),
            ],
            'source' => [
                'spreadsheet_id' => self::SPREADSHEET_ID,
                'sheet' => self::SHEET_NAME,
                'mode' => 'read_only',
            ],
        ]);
    }

    public function sync(?InternalGoogleSyncCoordinator $sync = null)
    {
        $sync = $sync ?: app(InternalGoogleSyncCoordinator::class);

        try {
            $result = $sync->run('operational', 'production_orders', function (InternalGoogleSyncContext $syncContext) {
        $url = sprintf(
            'https://docs.google.com/spreadsheets/d/%s/gviz/tq?tqx=out:csv&sheet=%s',
            self::SPREADSHEET_ID,
            rawurlencode(self::SHEET_NAME)
        );

        $response = Http::timeout(60)
            ->withOptions(['verify' => false])
            ->get($url);

        if (!$response->successful()) {
            throw new InternalGoogleSyncSourceException(
                'Không đọc được Google Sheet. Kiểm tra quyền chia sẻ của file.',
                502
            );
        }

        $rows = $this->parseCsv($response->body());
        if (count($rows) < 2) {
            throw new InternalGoogleSyncSourceException(
                'Tab LENH_SAN_XUAT không có dữ liệu hợp lệ.',
                422
            );
        }

        $headers = array_map([$this, 'normalizeHeader'], array_shift($rows));
        $batch = (string) Str::uuid();
        $activeKeys = [];
        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $skipped = 0;
        $existingByRowKey = InternalProductionOrder::query()
            ->whereNotNull('row_key')
            ->select(['id', 'row_key', 'source_hash', 'is_active', 'is_variant_parent'])
            ->get()
            ->keyBy('row_key');

        DB::connection('internal')->transaction(function () use ($rows, $headers, $batch, $syncContext, $existingByRowKey, &$activeKeys, &$created, &$updated, &$unchanged, &$skipped) {
            foreach ($rows as $index => $values) {
                $sourceRow = $index + 2;
                $syncContext->checkpoint($sourceRow);
                $row = [];
                $productionOrder = '';

                try {
                    foreach ($headers as $column => $header) {
                        $row[$header] = trim((string) ($values[$column] ?? ''));
                    }

                    $productionOrder = $this->pick($row, ['lenh sx']);
                    if ($productionOrder === '') {
                        $skipped++;
                        continue;
                    }

                $itemCode = $this->pick($row, ['ma hang']);
                $size = $this->pick($row, ['size']);
                $color = $this->pick($row, ['color']);
                $description = $this->pick($row, ['mo ta ten nhan']);
                $rowKey = $this->rowKey($productionOrder, $itemCode, $size, $color, $description);
                $activeKeys[] = $rowKey;
                $promisedDate = $this->date($this->pick($row, ['ngay hen giao']));
                $customerDate = $this->date($this->pick($row, ['ngay khach hang yeu cau giao']));
                $targetDate = $customerDate ?: $promisedDate;
                $status = $this->status($targetDate);
                $existing = $existingByRowKey->get($rowKey);
                $wasExisting = (bool) $existing;
                $sourceHash = hash('sha256', 'production-order-sync-v2|' . json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                if ($existing && hash_equals((string) ($existing->source_hash ?? ''), $sourceHash)) {
                    if (!$existing->is_active && !$existing->is_variant_parent) {
                        $existing->update(['is_active' => true]);
                    }
                    $unchanged++;
                    continue;
                }

                $attributes = [
                        'production_order' => $productionOrder,
                        'purchase_order' => $this->pick($row, ['purchase order po']),
                        'tracking_staff' => $this->pick($row, ['nhan vien theo doi']),
                        'customer' => $this->pick($row, ['khach hang']),
                        'item_code' => $itemCode,
                        'specification' => $this->pick($row, ['quy cach']),
                        'description' => $description,
                        'size' => $size,
                        'color' => $color,
                        'unit' => $this->pick($row, ['dvt']),
                        'order_quantity' => $this->quantityNumber(
                            $this->pick($row, ['so luong dat']),
                            $this->pick($row, ['dvt'])
                        ),
                        'location' => $this->pick($row, ['vi tri']),
                        'received_date' => $this->dateValue($this->pick($row, ['ngay nhan', 'ngay ra lenh'])),
                        'promised_date' => $this->dateValue($this->pick($row, ['ngay hen giao'])),
                        'customer_requested_date' => $this->dateValue($this->pick($row, ['ngay khach hang yeu cau giao'])),
                        'delivery_place' => $this->pick($row, ['noi giao']),
                        'status' => $status,
                        'source_row' => $sourceRow,
                        'raw_data' => $row,
                        'source_hash' => $sourceHash,
                        'sync_batch' => $batch,
                        'is_active' => !($existing && $existing->is_variant_parent),
                    ];

                if ($existing) {
                    $existing->fill($attributes)->save();
                } else {
                    $existing = InternalProductionOrder::query()->create(['row_key' => $rowKey] + $attributes);
                    $existingByRowKey->put($rowKey, $existing);
                }

                    $wasExisting ? $updated++ : $created++;
                } catch (\Throwable $error) {
                    $skipped++;
                    $syncContext->recordRowError($sourceRow, $productionOrder, $error, $row);
                }
            }

            $archiveQuery = InternalProductionOrder::query()
                ->where('is_active', true)
                ->where('is_manual_variant', false)
                ->where(function ($query) {
                    $query->whereNull('sync_batch')
                        ->orWhere('sync_batch', '!=', 'manual-supplemental');
                });
            if ($activeKeys) {
                $archiveQuery->whereNotIn('row_key', array_unique($activeKeys));
            }
            $archiveQuery->update(['is_active' => false]);
        });
        // Linking historical documents is an explicit reviewed action in the central-order screen.
        $relinkedDocumentLines = 0;
        $customerSync = app(\App\Services\InternalCustomerCatalogSync::class)->syncFromProductionOrders();
        Cache::forget('internal_catalog_customer_map_v1');
        Cache::put(
            'internal_production_order_search_version',
            (int) Cache::get('internal_production_order_search_version', 1) + 1
        );

        return [
                'created' => $created,
                'updated' => $updated,
                'unchanged' => $unchanged,
                'skipped' => $skipped,
                'processed' => count($rows),
                'active_variants' => count(array_unique($activeKeys)),
                'relinked_document_lines' => $relinkedDocumentLines,
                'customers' => $customerSync,
                'sheet' => self::SHEET_NAME,
            ];
            }, (int) config('internal_sync.operational_lock_seconds', 110));

            return response()->json([
                'message' => ($result['failed'] ?? 0) > 0
                    ? 'Đã đồng bộ một phần lệnh sản xuất; có dòng cần kiểm tra.'
                    : 'Đã đồng bộ lệnh sản xuất từ Google Sheet.',
                'data' => $result,
            ]);
        } catch (InternalGoogleSyncBusyException $error) {
            return response()->json(['message' => $error->getMessage()], 409);
        } catch (InternalGoogleSyncSourceException $error) {
            return response()->json(['message' => $error->getMessage()], $error->statusCode());
        } catch (\Throwable $error) {
            report($error);

            return response()->json(['message' => 'Đồng bộ lệnh sản xuất thất bại: ' . $error->getMessage()], 500);
        }
    }

    private function reconcileDocumentProductionOrderLinks(): int
    {
        $candidateIds = [];
        $candidateIdsByVariant = [];
        $candidateIdsBySize = [];
        $candidateIdsByParentAndCode = [];
        $candidateIdsByParentAndSize = [];
        InternalProductionOrder::query()
            ->where('is_active', true)
            ->whereNotNull('production_order')
            ->where('production_order', '<>', '')
            ->select(['id', 'production_order', 'item_code', 'standard_item_code', 'size', 'color', 'variant_parent_id'])
            ->orderBy('id')
            ->chunkById(500, function ($orders) use (&$candidateIds, &$candidateIdsByVariant, &$candidateIdsBySize, &$candidateIdsByParentAndCode, &$candidateIdsByParentAndSize) {
                foreach ($orders as $order) {
                    $orderCode = mb_strtoupper(trim((string) $order->production_order));
                    $size = mb_strtoupper(trim((string) $order->size));
                    $color = mb_strtoupper(trim((string) $order->color));
                    foreach ([$order->standard_item_code, $order->item_code] as $itemCode) {
                        $itemCode = mb_strtoupper(trim((string) $itemCode));
                        if ($orderCode === '' || $itemCode === '') {
                            continue;
                        }
                        $candidateIds[$orderCode . '|' . $itemCode][(int) $order->id] = true;
                        if ($order->variant_parent_id) {
                            $candidateIdsByParentAndCode[(int) $order->variant_parent_id . '|' . $itemCode][(int) $order->id] = true;
                        }
                    }
                    if ($orderCode !== '' && $size !== '' && $color !== '') {
                        $candidateIdsByVariant[$orderCode . '|' . $size . '|' . $color][(int) $order->id] = true;
                    }
                    foreach ([$size, $color] as $variantValue) {
                        if ($orderCode !== '' && $variantValue !== '') {
                            $candidateIdsBySize[$orderCode . '|' . $variantValue][(int) $order->id] = true;
                        }
                        if ($order->variant_parent_id && $variantValue !== '') {
                            $candidateIdsByParentAndSize[(int) $order->variant_parent_id . '|' . $variantValue][(int) $order->id] = true;
                        }
                    }
                }
            });

        $uniqueMaps = [];
        foreach ([$candidateIds, $candidateIdsByVariant, $candidateIdsBySize, $candidateIdsByParentAndCode, $candidateIdsByParentAndSize] as $map) {
            $uniqueMap = [];
            foreach ($map as $key => $ids) {
                if (count($ids) === 1) {
                    $uniqueMap[$key] = (int) array_key_first($ids);
                }
            }
            $uniqueMaps[] = $uniqueMap;
        }
        [$uniqueIdByKey, $uniqueIdByVariant, $uniqueIdBySize, $uniqueIdByParentAndCode, $uniqueIdByParentAndSize] = $uniqueMaps;

        $updated = 0;
        foreach (['internal_material_receipt_lines', 'internal_material_issue_lines'] as $table) {
            DB::connection('internal')->table($table)
                ->where(function ($query) {
                    $query->where(function ($query) {
                        $query->whereNotNull('production_order')
                            ->where('production_order', '<>', '');
                    })->orWhereNotNull('production_order_id');
                })
                ->whereNotNull('internal_item_code')
                ->where('internal_item_code', '<>', '')
                ->select(['id', 'production_order_id', 'production_order', 'internal_item_code', 'size', 'color'])
                ->orderBy('id')
                ->chunkById(500, function ($lines) use ($table, $uniqueIdByKey, $uniqueIdByVariant, $uniqueIdBySize, $uniqueIdByParentAndCode, $uniqueIdByParentAndSize, &$updated) {
                    foreach ($lines as $line) {
                        $orderCode = mb_strtoupper(trim((string) $line->production_order));
                        $itemCode = mb_strtoupper(trim((string) $line->internal_item_code));
                        $size = mb_strtoupper(trim((string) $line->size));
                        $color = mb_strtoupper(trim((string) $line->color));
                        $productionOrderId = $uniqueIdByKey[$orderCode . '|' . $itemCode] ?? null;
                        if (!$productionOrderId && $size !== '' && $color !== '') {
                            $productionOrderId = $uniqueIdByVariant[$orderCode . '|' . $size . '|' . $color] ?? null;
                        }
                        if (!$productionOrderId && $size !== '') {
                            $productionOrderId = $uniqueIdBySize[$orderCode . '|' . $size] ?? null;
                        }
                        if (!$productionOrderId && $line->production_order_id) {
                            $productionOrderId = $uniqueIdByParentAndCode[(int) $line->production_order_id . '|' . $itemCode] ?? null;
                        }
                        if (!$productionOrderId && $line->production_order_id && $size !== '') {
                            $productionOrderId = $uniqueIdByParentAndSize[(int) $line->production_order_id . '|' . $size] ?? null;
                        }
                        if (!$productionOrderId || (int) $line->production_order_id === $productionOrderId) {
                            continue;
                        }

                        DB::connection('internal')->table($table)->where('id', $line->id)->update([
                            'production_order_id' => $productionOrderId,
                        ]);
                        $updated++;
                    }
                });
        }

        return $updated;
    }

    private function buildLifecycle(
        string $productionOrder,
        $items,
        $bomProfiles,
        $snapshotItemCodes,
        $operationProgressRows,
        float $issuedMaterial,
        float $issuedProduction,
        float $receivedQuantity,
        float $plannedQuantity,
        float $issuedCustomer
    ): array {
        $itemCodes = collect($items)
            ->pluck('item_code')
            ->map(fn ($code) => mb_strtoupper(trim((string) $code)))
            ->filter()
            ->unique()
            ->values();
        $snapshotItemCodes = collect($snapshotItemCodes)->map(fn ($code) => mb_strtoupper(trim((string) $code)));
        $bomItemCount = $itemCodes->filter(function ($itemCode) use ($bomProfiles, $snapshotItemCodes) {
            $profile = $bomProfiles->get($itemCode);
            return ($profile && (int) $profile->lines_count > 0) || $snapshotItemCodes->contains($itemCode);
        })->count();
        $bomComplete = $itemCodes->isNotEmpty() && $bomItemCount === $itemCodes->count();
        $bomStatus = $bomComplete ? 'completed' : 'active';
        $bomDetail = $bomComplete
            ? $bomItemCount . '/' . $itemCodes->count() . ' mã đã có định mức'
            : $bomItemCount . '/' . $itemCodes->count() . ' mã có định mức';

        $routingRows = $itemCodes->flatMap(function ($itemCode) use ($bomProfiles) {
            $profile = $bomProfiles->get($itemCode);
            return $profile ? $profile->routings : collect();
        })->sortBy('sequence')->unique('operation_code')->values();
        if ($routingRows->isEmpty() && collect($operationProgressRows)->isNotEmpty()) {
            $routingRows = collect($operationProgressRows)
                ->sortBy('sequence')
                ->map(function ($progress) {
                    return (object) [
                        'operation_code' => $progress->operation_code,
                        'operation_name' => $progress->operation_name,
                        'sequence' => $progress->sequence,
                    ];
                })
                ->values();
        }
        $savedProgress = collect($operationProgressRows)->keyBy(fn ($row) => mb_strtoupper(trim((string) $row->operation_code)));
        $productionStarted = $issuedMaterial > 0 || $issuedProduction > 0;
        $operations = $routingRows->map(function ($routing, $index) use ($savedProgress, $productionStarted) {
            $code = mb_strtoupper(trim((string) $routing->operation_code));
            $saved = $savedProgress->get($code);
            $status = $saved ? $saved->status : 'pending';
            if (!$saved && $productionStarted && $index === 0) {
                $status = 'in_progress';
            }
            return [
                'code' => $code,
                'name' => trim((string) $routing->operation_name),
                'sequence' => (int) $routing->sequence,
                'status' => $status,
                'updated_by' => trim((string) ($saved->updated_by ?? '')),
                'note' => trim((string) ($saved->note ?? '')),
            ];
        })->values();
        $allOperationsDone = $operations->isNotEmpty() && $operations->every(fn ($operation) => $operation['status'] === 'completed');
        $hasOperationActivity = $operations->contains(fn ($operation) => in_array($operation['status'], ['in_progress', 'completed'], true));
        $currentOperation = $operations->first(fn ($operation) => $operation['status'] === 'in_progress')
            ?: $operations->first(fn ($operation) => $operation['status'] === 'pending');

        $materialStatus = $productionStarted ? 'completed' : ($bomComplete ? 'active' : 'pending');
        $productionStatus = $allOperationsDone
            ? 'completed'
            : (($productionStarted || $hasOperationActivity) ? 'active' : 'pending');
        $receiptStatus = $receivedQuantity > 0
            ? (($plannedQuantity > 0 && $receivedQuantity >= $plannedQuantity) ? 'completed' : 'active')
            : ($allOperationsDone ? 'active' : 'pending');
        $shipmentStatus = $issuedCustomer > 0
            ? (($plannedQuantity > 0 && $issuedCustomer >= $plannedQuantity) ? 'completed' : 'active')
            : ($receiptStatus === 'completed' ? 'active' : 'pending');
        $stages = collect([
            ['key' => 'order', 'label' => 'Lệnh SX', 'status' => 'completed', 'detail' => $productionOrder],
            ['key' => 'bom', 'label' => 'Phân tích BOM', 'status' => $bomStatus, 'detail' => $bomDetail],
            ['key' => 'material', 'label' => 'Xuất vật tư', 'status' => $materialStatus, 'detail' => $issuedMaterial > 0 ? number_format($issuedMaterial, 3, ',', '.') : ($issuedProduction > 0 ? 'Đã xuất BTP' : 'Chưa có phiếu')],
            ['key' => 'production', 'label' => 'Sản xuất', 'status' => $productionStatus, 'detail' => $currentOperation ? $currentOperation['name'] : ($operations->isEmpty() ? 'Chưa có tuyến công đoạn' : 'Đã xong công đoạn')],
            ['key' => 'receipt', 'label' => 'Nhập kho', 'status' => $receiptStatus, 'detail' => number_format($receivedQuantity, 3, ',', '.')],
            ['key' => 'shipment', 'label' => 'Xuất kho', 'status' => $shipmentStatus, 'detail' => number_format($issuedCustomer, 3, ',', '.')],
        ]);
        $score = $stages->sum(fn ($stage) => $stage['status'] === 'completed' ? 1 : ($stage['status'] === 'active' ? 0.5 : 0));
        $currentStage = $stages->first(fn ($stage) => $stage['status'] === 'active');

        return [
            'percent' => (int) round($score / max($stages->count(), 1) * 100),
            'current_stage' => $currentStage['label'] ?? 'Hoàn tất',
            'stages' => $stages->values(),
            'operations' => $operations,
            'bom_complete' => $bomComplete,
        ];
    }

    private function workflowStatus(
        float $planned,
        float $received,
        float $issuedMaterial,
        float $issuedProduction,
        float $issuedCustomer,
        array $lifecycle
    ): string
    {
        if ($issuedCustomer > 0) {
            return 'shipped_customer';
        }

        $productionStage = collect($lifecycle['stages'] ?? [])->firstWhere('key', 'production');
        if (($productionStage['status'] ?? '') === 'completed') {
            return 'production_done';
        }

        if ($issuedMaterial > 0 || $issuedProduction > 0 || ($productionStage['status'] ?? '') === 'active') {
            return 'in_production';
        }

        if ($received > 0) {
            return 'received';
        }

        return $planned > 0 ? 'planned' : 'empty';
    }

    private function workflowSummary($rows): array
    {
        $rows = collect($rows);

        return [
            'order_count' => $rows->count(),
            'planned_quantity' => (float) $rows->sum('planned_quantity'),
            'received_quantity' => (float) $rows->sum('received_quantity'),
            'production_issue_quantity' => (float) $rows->sum('production_issue_quantity'),
            'customer_issue_quantity' => (float) $rows->sum('customer_issue_quantity'),
            'planned_count' => $rows->where('status', 'planned')->count(),
            'received_count' => $rows->where('status', 'received')->count(),
            'in_production_count' => $rows->where('status', 'in_production')->count(),
            'production_done_count' => $rows->where('status', 'production_done')->count(),
            'shipped_customer_count' => $rows->where('status', 'shipped_customer')->count(),
            'warning_count' => $rows->filter(fn ($row) => (int) ($row['warning_count'] ?? 0) > 0)->count(),
            'error_count' => $rows->filter(fn ($row) => (bool) ($row['has_error'] ?? false))->count(),
        ];
    }

    private function plannedQuantityForOrderLines($lines): float
    {
        $sourceQuantities = collect($lines)
            ->map(function ($line) {
                $rawData = is_array($line->raw_data) ? $line->raw_data : [];
                return (float) ($rawData['_internal_variant']['source_quantity'] ?? 0);
            })
            ->filter(fn ($quantity) => $quantity > 0);

        return $sourceQuantities->isNotEmpty()
            ? (float) $sourceQuantities->max()
            : (float) collect($lines)->sum('order_quantity');
    }

    private function splitCodes($value): array
    {
        return collect(explode(',', (string) $value))
            ->map(fn ($code) => trim($code))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function parseCsv(string $contents): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream)) !== false) {
            $rows[] = $row;
        }
        fclose($stream);
        return $rows;
    }

    private function normalizeHeader($value): string
    {
        $value = preg_replace('/[^a-z0-9]+/', ' ', Str::ascii(mb_strtolower(trim((string) $value))));
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    private function pick(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            $key = $this->normalizeHeader($key);
            if (array_key_exists($key, $row)) {
                return trim((string) $row[$key]);
            }
        }
        return '';
    }

    private function number($value): float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }
        $value = str_replace(['.', ' '], '', $value);
        $value = str_replace(',', '.', $value);
        return is_numeric($value) ? (float) $value : 0;
    }

    private function quantityNumber($value, $unit): float
    {
        $raw = preg_replace('/\s+/u', '', trim((string) $value));
        if ($raw === '') {
            return 0;
        }

        $normalizedUnit = mb_strtoupper(Str::ascii(trim((string) $unit)));
        $wholeNumberUnits = ['PCS', 'PC', 'CAI', 'BO', 'SET', 'CAP', 'CHIEC', 'CUON', 'THUNG'];

        // Counted units cannot have a fractional item. Sheets commonly export
        // their thousands separator as either 15,756 or 15.756.
        if (in_array($normalizedUnit, $wholeNumberUnits, true)
            && preg_match('/^[+-]?\d{1,3}(?:[.,]\d{3})+$/', $raw)) {
            return (float) str_replace([',', '.'], '', $raw);
        }

        return $this->number($raw);
    }

    private function date($value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->startOfDay();
            } catch (\Throwable $e) {
            }
        }
        return null;
    }

    private function dateValue($value): ?string
    {
        $date = $this->date($value);
        return $date ? $date->format('Y-m-d') : null;
    }

    private function status(?Carbon $targetDate): string
    {
        if (!$targetDate) {
            return 'pending';
        }
        if ($targetDate->isBefore(now()->startOfDay())) {
            return 'late';
        }
        if ($targetDate->lte(now()->addDays(3)->startOfDay())) {
            return 'due';
        }
        return 'scheduled';
    }

    private function rowKey($productionOrder, $itemCode, $size, $color, $description): string
    {
        $parts = [$productionOrder, $itemCode, $size, $color, $description];
        $parts = array_map(function ($value) {
            return mb_strtoupper(trim((string) $value));
        }, $parts);

        return hash('sha256', implode('|', $parts));
    }
}
