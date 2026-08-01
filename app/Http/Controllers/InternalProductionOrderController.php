<?php

namespace App\Http\Controllers;

use App\Models\InternalItemCatalog;
use App\Models\InternalProductionOrder;
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

    public function workflow(Request $request)
    {
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

        $receiptRows = DB::connection('internal')->table('internal_material_receipt_lines as l')
            ->join('internal_material_receipts as r', 'r.id', '=', 'l.receipt_id')
            ->whereIn('l.production_order', $orderCodes->all())
            ->select(
                'l.production_order',
                DB::raw('SUM(l.quantity) as quantity'),
                DB::raw('COUNT(*) as line_count'),
                DB::raw('COUNT(DISTINCT r.id) as document_count'),
                DB::raw("GROUP_CONCAT(DISTINCT r.receipt_code ORDER BY r.receipt_date SEPARATOR ', ') as document_codes")
            )
            ->groupBy('l.production_order')
            ->get()
            ->keyBy('production_order');

        $issueRows = DB::connection('internal')->table('internal_material_issue_lines as l')
            ->join('internal_material_issues as i', 'i.id', '=', 'l.issue_id')
            ->whereIn('l.production_order', $orderCodes->all())
            ->select(
                'l.production_order',
                DB::raw("SUM(CASE WHEN i.issue_type = 'production' THEN l.quantity ELSE 0 END) as production_quantity"),
                DB::raw("SUM(CASE WHEN i.issue_type = 'customer' THEN l.quantity ELSE 0 END) as customer_quantity"),
                DB::raw("COUNT(DISTINCT CASE WHEN i.issue_type = 'production' THEN i.id END) as production_document_count"),
                DB::raw("COUNT(DISTINCT CASE WHEN i.issue_type = 'customer' THEN i.id END) as customer_document_count"),
                DB::raw("MAX(CASE WHEN i.status = 'completed' THEN 1 ELSE 0 END) as has_completed_issue"),
                DB::raw("GROUP_CONCAT(DISTINCT CASE WHEN i.issue_type = 'production' THEN i.issue_code END ORDER BY i.issue_date SEPARATOR ', ') as production_issue_codes"),
                DB::raw("GROUP_CONCAT(DISTINCT CASE WHEN i.issue_type = 'customer' THEN i.issue_code END ORDER BY i.issue_date SEPARATOR ', ') as customer_issue_codes")
            )
            ->groupBy('l.production_order')
            ->get()
            ->keyBy('production_order');

        $rows = $orders
            ->groupBy('production_order')
            ->map(function ($lines, $productionOrder) use ($receiptRows, $issueRows, $catalogsById, $catalogsByCode) {
                $first = $lines->first();
                $receipt = $receiptRows->get($productionOrder);
                $issue = $issueRows->get($productionOrder);
                $plannedQuantity = $this->plannedQuantityForOrderLines($lines);
                $receivedQuantity = (float) ($receipt->quantity ?? 0);
                $issuedProduction = (float) ($issue->production_quantity ?? 0);
                $issuedCustomer = (float) ($issue->customer_quantity ?? 0);
                $remainingAfterCustomer = $receivedQuantity - $issuedCustomer;
                $status = $this->workflowStatus($plannedQuantity, $receivedQuantity, $issuedProduction, (bool) ($issue->has_completed_issue ?? false), $issuedCustomer);

                $items = $lines->map(function ($line) use ($catalogsById, $catalogsByCode) {
                    $sourceItemCode = trim((string) $line->item_code);
                    $standardItemCode = trim((string) $line->standard_item_code);
                    $catalog = $line->standard_catalog_id ? $catalogsById->get($line->standard_catalog_id) : null;
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
                    'production_issue_quantity' => $issuedProduction,
                    'customer_issue_quantity' => $issuedCustomer,
                    'remaining_quantity' => $remainingAfterCustomer,
                    'production_document_count' => (int) ($issue->production_document_count ?? 0),
                    'customer_document_count' => (int) ($issue->customer_document_count ?? 0),
                    'receipt_document_count' => (int) ($receipt->document_count ?? 0),
                    'receipt_codes' => $this->splitCodes($receipt->document_codes ?? ''),
                    'production_issue_codes' => $this->splitCodes($issue->production_issue_codes ?? ''),
                    'customer_issue_codes' => $this->splitCodes($issue->customer_issue_codes ?? ''),
                    'status' => $status,
                    'items' => $items,
                ];
            })
            ->values()
            ->filter(function ($row) use ($status) {
                return $status === '' || $row['status'] === $status;
            })
            ->values();

        return response()->json([
            'data' => $rows,
            'summary' => $this->workflowSummary($rows),
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

        // Quick receipts should only suggest orders available by the selected receipt date.
        if ($productionOrder === '' && $request->filled('order_date_to')) {
            $query->whereNotNull('received_date')
                ->whereDate('received_date', '<=', $request->query('order_date_to'));
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
        $isPaged = $request->has('page') || $request->has('per_page');
        $page = max((int) $request->query('page', 1), 1);
        $perPage = min(max((int) $request->query('per_page', 100), 25), 300);
        $limit = min(max((int) $request->query('limit', 500), 1), 2000);
        if ($productionOrder !== '') {
            $query->orderBy('source_row');
        } else {
            $query->orderByRaw('promised_date IS NULL')
                ->orderBy('promised_date')
                ->orderByDesc('production_order');
        }

        $rowsQuery = clone $query;
        $rows = $isPaged
            ? $rowsQuery->skip(($page - 1) * $perPage)->take($perPage)->get()
            : $rowsQuery->limit($limit)->get();
        $totalRows = (clone $summaryQuery)->count();

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
        $dataCatalogsById = InternalItemCatalog::query()
            ->whereIn('id', $rows->pluck('standard_catalog_id')->filter()->unique()->values())
            ->get()
            ->keyBy('id');
        $rows->each(function ($row) use ($dataCatalogsById) {
            $sourceItemCode = trim((string) $row->item_code);
            $standardItemCode = trim((string) $row->standard_item_code);
            $catalog = $row->standard_catalog_id ? $dataCatalogsById->get($row->standard_catalog_id) : null;
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
                'order_count' => (clone $summaryQuery)->distinct()->count('production_order'),
                'variant_count' => (clone $summaryQuery)->count(),
                'total_quantity' => (float) (clone $summaryQuery)->sum('order_quantity'),
                'late_count' => (clone $summaryQuery)->where('status', 'late')->count(),
                'due_count' => (clone $summaryQuery)->where('status', 'due')->count(),
                'customer_count' => (clone $summaryQuery)->whereNotNull('customer')->distinct('customer')->count('customer'),
                'last_synced_at' => InternalProductionOrder::query()->max('updated_at'),
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

    public function sync()
    {
        $url = sprintf(
            'https://docs.google.com/spreadsheets/d/%s/gviz/tq?tqx=out:csv&sheet=%s',
            self::SPREADSHEET_ID,
            rawurlencode(self::SHEET_NAME)
        );

        $response = Http::timeout(60)
            ->withOptions(['verify' => false])
            ->get($url);

        if (!$response->successful()) {
            return response()->json([
                'message' => 'Không đọc được Google Sheet. Kiểm tra quyền chia sẻ của file.',
            ], 502);
        }

        $rows = $this->parseCsv($response->body());
        if (count($rows) < 2) {
            return response()->json([
                'message' => 'Tab LENH_SAN_XUAT không có dữ liệu hợp lệ.',
            ], 422);
        }

        $headers = array_map([$this, 'normalizeHeader'], array_shift($rows));
        $batch = (string) Str::uuid();
        $activeKeys = [];
        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $skipped = 0;

        DB::connection('internal')->transaction(function () use ($rows, $headers, $batch, &$activeKeys, &$created, &$updated, &$unchanged, &$skipped) {
            foreach ($rows as $index => $values) {
                $row = [];
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
                $existing = InternalProductionOrder::query()
                    ->where('row_key', $rowKey)
                    ->first();
                $sourceHash = hash('sha256', json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                if ($existing && hash_equals((string) ($existing->source_hash ?? ''), $sourceHash)) {
                    if (!$existing->is_active && !$existing->is_variant_parent) {
                        $existing->update(['is_active' => true]);
                    }
                    $unchanged++;
                    continue;
                }

                InternalProductionOrder::query()->updateOrCreate(
                    ['row_key' => $rowKey],
                    [
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
                        'order_quantity' => $this->number($this->pick($row, ['so luong dat'])),
                        'location' => $this->pick($row, ['vi tri']),
                        'received_date' => $this->dateValue($this->pick($row, ['ngay nhan', 'ngay ra lenh'])),
                        'promised_date' => $this->dateValue($this->pick($row, ['ngay hen giao'])),
                        'customer_requested_date' => $this->dateValue($this->pick($row, ['ngay khach hang yeu cau giao'])),
                        'delivery_place' => $this->pick($row, ['noi giao']),
                        'status' => $status,
                        'source_row' => $index + 2,
                        'raw_data' => $row,
                        'source_hash' => $sourceHash,
                        'sync_batch' => $batch,
                        'is_active' => !($existing && $existing->is_variant_parent),
                    ]
                );

                $existing ? $updated++ : $created++;
            }

            $archiveQuery = InternalProductionOrder::query()
                ->where('is_active', true)
                ->where('is_manual_variant', false);
            if ($activeKeys) {
                $archiveQuery->whereNotIn('row_key', array_unique($activeKeys));
            }
            $archiveQuery->update(['is_active' => false]);
        });
        Cache::forget('internal_catalog_customer_map_v1');

        return response()->json([
            'message' => 'Đã đồng bộ lệnh sản xuất từ Google Sheet.',
            'data' => [
                'created' => $created,
                'updated' => $updated,
                'unchanged' => $unchanged,
                'skipped' => $skipped,
                'active_variants' => count(array_unique($activeKeys)),
                'sheet' => self::SHEET_NAME,
            ],
        ]);
    }

    private function workflowStatus(float $planned, float $received, float $issuedProduction, bool $completedIssue, float $issuedCustomer): string
    {
        if ($issuedCustomer > 0) {
            return 'shipped_customer';
        }

        if ($completedIssue) {
            return 'production_done';
        }

        if ($issuedProduction > 0) {
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
