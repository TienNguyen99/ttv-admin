<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\NormalizesDateInput;
use App\Models\InternalInventoryCount;
use App\Models\InternalBtpProductionOrder;
use App\Models\InternalMaterialIssue;
use App\Models\InternalMaterialIssueAllocation;
use App\Models\InternalMaterialIssueLine;
use App\Models\InternalMaterialReceiptLine;
use App\Models\InternalMaterialReceipt;
use App\Models\InternalProductionOrder;
use App\Models\InternalWeavingOrder;
use App\Models\InternalXntRow;
use App\Models\InventoryPackage;
use App\Models\WarehouseLocation;
use App\Services\InternalAudit;
use App\Services\InternalBtpOrderMatcher;
use App\Services\InternalCatalogValidator;
use App\Services\InternalDocumentNumber;
use App\Services\GoogleSheetInternalCatalog;
use App\Services\InternalProductionOrderLineResolver;
use App\Services\InternalUnitConverter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InternalMaterialIssueController extends Controller
{
    use NormalizesDateInput;

    public function index()
    {
        return view('client.internal-material-issue');
    }

    public function productionTrackingIndex()
    {
        return view('client.production-wip');
    }

    public function productionTracking(Request $request)
    {
        $keyword = mb_strtoupper(trim((string) $request->query('keyword', '')));
        $aging = trim((string) $request->query('aging', ''));
        $status = trim((string) $request->query('status', 'active'));
        if (!in_array($status, ['active', 'completed', 'all'], true)) {
            $status = 'active';
        }

        $issueLines = DB::connection('internal')->table('internal_material_issue_lines as l')
            ->join('internal_material_issues as i', 'i.id', '=', 'l.issue_id')
            ->where('i.issue_code', 'like', 'PXBTP-%')
            ->select(
                'l.id as issue_line_id',
                'i.id as issue_id',
                'i.issue_code',
                'i.issue_date',
                'i.status as issue_status',
                'i.source_receipt_id',
                'i.warehouse_code',
                'i.receiver_name',
                'i.department',
                DB::raw("COALESCE(NULLIF(l.production_order, ''), NULLIF(i.production_order, ''), i.issue_code) as production_order"),
                'l.purchase_order',
                'l.customer',
                'l.ma_hh',
                'l.internal_item_code',
                'l.size',
                'l.color',
                'l.dvt',
                'l.quantity',
                'l.completed_at'
            )
            ->orderBy('i.issue_date')
            ->orderBy('i.id')
            ->get();

        $groups = [];
        foreach ($issueLines as $line) {
            $order = trim((string) $line->production_order);
            if ($order === '') {
                continue;
            }

            $key = $this->productionTrackingKey($order, $line->size, $line->color);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'production_order' => $order,
                    'purchase_order' => trim((string) $line->purchase_order),
                    'customer' => trim((string) $line->customer),
                    'warehouse_code' => trim((string) $line->warehouse_code),
                    'receiver_name' => trim((string) $line->receiver_name),
                    'department' => trim((string) $line->department),
                    'ma_hh' => trim((string) $line->ma_hh),
                    'internal_item_code' => trim((string) $line->internal_item_code),
                    'size' => trim((string) $line->size),
                    'color' => trim((string) $line->color),
                    'dvt' => trim((string) $line->dvt),
                    'issued_quantity' => 0.0,
                    'returned_quantity' => 0.0,
                    'first_issue_date' => (string) $line->issue_date,
                    'last_issue_date' => (string) $line->issue_date,
                    'source_receipt_ids' => [],
                    'issue_ids' => [],
                    'issue_line_ids' => [],
                    'issue_codes' => [],
                    'issue_statuses' => [],
                ];
            }

            $groups[$key]['issued_quantity'] += (float) $line->quantity;
            $groups[$key]['first_issue_date'] = min($groups[$key]['first_issue_date'], (string) $line->issue_date);
            $groups[$key]['last_issue_date'] = max($groups[$key]['last_issue_date'], (string) $line->issue_date);
            if ($line->source_receipt_id) {
                $groups[$key]['source_receipt_ids'][(int) $line->source_receipt_id] = true;
            }
            $groups[$key]['issue_ids'][$line->issue_id] = true;
            if (!$line->completed_at) {
                $groups[$key]['issue_line_ids'][(int) $line->issue_line_id] = true;
            }
            $groups[$key]['issue_codes'][$line->issue_code] = true;
            $groups[$key]['issue_statuses'][trim((string) $line->issue_status)] = true;
        }

        $btpOrders = InternalBtpProductionOrder::query()
            ->with('lines')
            ->whereIn('status', ['draft', 'issued'])
            ->orderBy('order_date')
            ->orderBy('id')
            ->get();

        foreach ($btpOrders as $order) {
            foreach ($order->lines as $line) {
                $orderCode = trim((string) $order->btp_order_code);
                if ($orderCode === '') {
                    continue;
                }

                $key = $this->productionTrackingKey($orderCode, $line->size, $line->color);
                if (isset($groups[$key])) {
                    $groups[$key]['btp_status'] = $order->status;
                    $groups[$key]['planned_quantity'] = max((float) ($groups[$key]['planned_quantity'] ?? 0), (float) $line->quantity);
                    continue;
                }

                $groups[$key] = [
                    'production_order' => $orderCode,
                    'purchase_order' => '',
                    'customer' => '',
                    'warehouse_code' => '',
                    'receiver_name' => trim((string) $order->receiver_name),
                    'department' => trim((string) $order->department),
                    'ma_hh' => trim((string) $line->ma_hh),
                    'internal_item_code' => trim((string) $line->internal_item_code),
                    'size' => trim((string) $line->size),
                    'color' => trim((string) $line->color),
                    'dvt' => trim((string) $line->dvt),
                    'planned_quantity' => (float) $line->quantity,
                    'issued_quantity' => 0.0,
                    'returned_quantity' => 0.0,
                    'first_issue_date' => (string) $order->order_date,
                    'last_issue_date' => (string) $order->order_date,
                    'source_receipt_ids' => [],
                    'issue_ids' => [],
                    'issue_line_ids' => [],
                    'issue_codes' => [],
                    'issue_statuses' => [],
                    'btp_status' => $order->status,
                ];
            }
        }

        $orderNames = collect($groups)->pluck('production_order')->unique()->values();
        if ($orderNames->isNotEmpty()) {
            $sourceReceiptIds = collect($groups)
                ->flatMap(fn ($row) => array_keys($row['source_receipt_ids'] ?? []))
                ->filter()
                ->unique()
                ->values();

            $receiptLines = DB::connection('internal')->table('internal_material_receipt_lines as l')
                ->join('internal_material_receipts as r', 'r.id', '=', 'l.receipt_id')
                ->where('r.source', 'Phieu nhap thanh pham')
                ->whereIn(DB::raw("COALESCE(NULLIF(l.production_order, ''), l.note)"), $orderNames->all())
                ->when($sourceReceiptIds->isNotEmpty(), function ($query) use ($sourceReceiptIds) {
                    $query->whereNotIn('r.id', $sourceReceiptIds->all());
                })
                ->select(
                    DB::raw("COALESCE(NULLIF(l.production_order, ''), l.note) as production_order"),
                    'l.size',
                    'l.color',
                    DB::raw('SUM(l.quantity) as quantity')
                )
                ->groupBy('l.production_order', 'l.note', 'l.size', 'l.color')
                ->get();

            foreach ($receiptLines as $line) {
                $key = $this->productionTrackingKey($line->production_order, $line->size, $line->color);
                if (isset($groups[$key])) {
                    $groups[$key]['returned_quantity'] += (float) $line->quantity;
                }
            }
        }

        $today = now('Asia/Ho_Chi_Minh')->startOfDay();
        $rows = collect($groups)->map(function ($row) use ($today) {
            unset($row['source_receipt_ids']);
            $row['issue_ids'] = array_map('intval', array_keys($row['issue_ids'] ?? []));
            $row['issue_line_ids'] = array_map('intval', array_keys($row['issue_line_ids'] ?? []));
            $row['issue_codes'] = array_keys($row['issue_codes']);
            $issueStatuses = array_keys($row['issue_statuses'] ?? []);
            unset($row['issue_statuses']);
            $row['btp_status'] = in_array('completed', $issueStatuses, true) ? 'completed' : ($row['btp_status'] ?? 'issued');
            $row['planned_quantity'] = (float) ($row['planned_quantity'] ?? $row['issued_quantity']);
            $row['outstanding_quantity'] = $row['issued_quantity'] > 0
                ? max(0, $row['issued_quantity'] - $row['returned_quantity'])
                : max(0, $row['planned_quantity'] - $row['returned_quantity']);
            $row['progress_percent'] = $row['issued_quantity'] > 0
                ? min(100, round(($row['returned_quantity'] / $row['issued_quantity']) * 100, 1))
                : 0;
            $row['age_days'] = Carbon::parse($row['first_issue_date'])->startOfDay()->diffInDays($today);
            $row['aging_status'] = $row['age_days'] >= 8 ? 'overdue' : ($row['age_days'] >= 4 ? 'warning' : 'normal');
            $row['workflow_status'] = $row['outstanding_quantity'] <= 0 || $row['btp_status'] === 'completed'
                ? 'completed'
                : 'active';
            $row['source_type'] = 'btp';
            $row['source_label'] = 'Bán thành phẩm';

            return $row;
        });

        $weavingOrders = InternalWeavingOrder::query()
            ->with('item:id,item_code,item_name')
            ->where(function ($query) {
                $query->where('status', 'issued')
                    ->orWhereNotNull('metadata_json');
            })
            ->get()
            ->filter(function (InternalWeavingOrder $order) {
                $metadata = json_decode((string) ($order->metadata_json ?? ''), true) ?: [];

                return $order->status === 'issued' || !empty($metadata['sent_to_production_at']);
            });

        $weavingReceiptTotals = collect();
        $weavingOrders->pluck('order_code')->filter()->unique()->chunk(500)->each(
            function ($orderCodes) use ($weavingReceiptTotals) {
                DB::connection('internal')->table('internal_material_receipt_lines as line')
                    ->join('internal_material_receipts as receipt', 'receipt.id', '=', 'line.receipt_id')
                    ->whereIn('line.production_order', $orderCodes->values()->all())
                    ->where(function ($query) {
                        $query->whereNull('receipt.status')->orWhere('receipt.status', '<>', 'cancelled');
                    })
                    ->selectRaw('line.production_order as order_key')
                    ->selectRaw('SUM(line.quantity) as received_quantity')
                    ->groupBy('line.production_order')
                    ->get()
                    ->each(function ($receipt) use ($weavingReceiptTotals) {
                        $weavingReceiptTotals->put(
                            mb_strtoupper(trim((string) $receipt->order_key)),
                            (float) $receipt->received_quantity
                        );
                    });
            }
        );

        $weavingRows = $weavingOrders->map(function (InternalWeavingOrder $order) use ($today, $weavingReceiptTotals) {
            $metadata = json_decode((string) ($order->metadata_json ?? ''), true) ?: [];
            $planned = (float) $order->order_quantity;
            $rawReceived = (float) $weavingReceiptTotals->get(
                mb_strtoupper(trim((string) $order->order_code)),
                0
            );
            $received = max(0, $rawReceived - (float) ($metadata['receipt_quantity_baseline'] ?? 0));
            $outstanding = max(0, $planned - $received);
            $sentAt = !empty($metadata['sent_to_production_at'])
                ? Carbon::parse($metadata['sent_to_production_at'])->timezone('Asia/Ho_Chi_Minh')
                : ($order->updated_at ?: $order->order_date);
            $startDate = $sentAt ? Carbon::parse($sentAt)->format('Y-m-d') : $today->format('Y-m-d');

            return [
                'production_order' => trim((string) $order->order_code),
                'purchase_order' => trim((string) $order->po_number),
                'customer' => trim((string) $order->customer),
                'warehouse_code' => '',
                'receiver_name' => '',
                'department' => 'Dệt',
                'ma_hh' => trim((string) $order->item_code),
                'internal_item_code' => trim((string) $order->item_code),
                'item_name' => trim((string) ($order->item->item_name ?? '')),
                'size' => '',
                'color' => '',
                'dvt' => trim((string) $order->unit),
                'planned_quantity' => $planned,
                'issued_quantity' => $planned,
                'returned_quantity' => $received,
                'outstanding_quantity' => $outstanding,
                'progress_percent' => $planned > 0 ? min(100, round(($received / $planned) * 100, 1)) : 0,
                'first_issue_date' => $startDate,
                'last_issue_date' => $startDate,
                'issue_ids' => [],
                'issue_line_ids' => [],
                'issue_codes' => [],
                'btp_status' => $outstanding <= 0 ? 'completed' : 'issued',
                'workflow_status' => $outstanding <= 0 ? 'completed' : 'active',
                'age_days' => Carbon::parse($startDate)->startOfDay()->diffInDays($today),
                'aging_status' => Carbon::parse($startDate)->startOfDay()->diffInDays($today) >= 8
                    ? 'overdue'
                    : (Carbon::parse($startDate)->startOfDay()->diffInDays($today) >= 4 ? 'warning' : 'normal'),
                'source_type' => 'weaving',
                'source_label' => 'Lệnh dệt',
                'due_date' => optional($order->due_date)->format('Y-m-d'),
            ];
        });

        $rows = $rows->concat($weavingRows)->filter(function ($row) use ($keyword, $aging, $status) {
            if ($status !== 'all' && $row['workflow_status'] !== $status) {
                return false;
            }
            if ($aging !== '' && $row['aging_status'] !== $aging) {
                return false;
            }
            if ($keyword === '') {
                return true;
            }

            $searchable = mb_strtoupper(implode(' ', [
                $row['production_order'],
                $row['btp_status'],
                $row['purchase_order'],
                $row['customer'],
                $row['ma_hh'],
                $row['internal_item_code'],
                $row['size'],
                $row['color'],
                $row['source_label'],
                implode(' ', $row['issue_codes']),
            ]));

            return mb_strpos($searchable, $keyword) !== false;
        })->sortByDesc(function ($row) {
            return sprintf('%03d|%s', $row['age_days'], $row['first_issue_date']);
        })->values();

        return response()->json([
            'data' => $rows,
            'summary' => [
                'order_count' => $rows->pluck('production_order')->unique()->count(),
                'active_order_count' => $rows->where('workflow_status', 'active')->pluck('production_order')->unique()->count(),
                'completed_order_count' => $rows->where('workflow_status', 'completed')->pluck('production_order')->unique()->count(),
                'line_count' => $rows->count(),
                'issued_quantity' => (float) $rows->sum('issued_quantity'),
                'returned_quantity' => (float) $rows->sum('returned_quantity'),
                'outstanding_quantity' => (float) $rows->sum('outstanding_quantity'),
                'overdue_count' => $rows->where('aging_status', 'overdue')->count(),
            ],
        ]);
    }

    public function list(Request $request)
    {
        $query = InternalMaterialIssue::query()
            ->with('lines:id,issue_id,production_order')
            ->withCount('lines')
            ->withSum('lines', 'quantity')
            ->orderByDesc('issue_date')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($request->filled('from_date')) {
            $query->whereDate('issue_date', '>=', $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('issue_date', '<=', $request->query('to_date'));
        }

        $keyword = trim((string) $request->query('keyword', ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('issue_code', 'like', '%' . $keyword . '%')
                    ->orWhere('warehouse_code', 'like', '%' . $keyword . '%')
                    ->orWhere('receiver_name', 'like', '%' . $keyword . '%')
                    ->orWhere('department', 'like', '%' . $keyword . '%')
                    ->orWhere('production_order', 'like', '%' . $keyword . '%')
                    ->orWhereHas('lines', function ($lineQuery) use ($keyword) {
                        $lineQuery->where('production_order', 'like', '%' . $keyword . '%')
                            ->orWhere('ma_hh', 'like', '%' . $keyword . '%')
                            ->orWhere('internal_item_code', 'like', '%' . $keyword . '%')
                            ->orWhere('ten_hh', 'like', '%' . $keyword . '%')
                            ->orWhere('size', 'like', '%' . $keyword . '%')
                            ->orWhere('color', 'like', '%' . $keyword . '%')
                            ->orWhere('side', 'like', '%' . $keyword . '%')
                            ->orWhere('location_code', 'like', '%' . $keyword . '%');
                    });
            });
        }

        $data = $query->limit(200)->get()->map(function (InternalMaterialIssue $issue) {
            $btpCodes = $issue->lines
                ->pluck('production_order')
                ->map(fn ($code) => trim((string) $code))
                ->filter(fn ($code) => strpos($code, 'BTP') === 0)
                ->unique()
                ->values();

            $issue->setAttribute('btp_label_count', $btpCodes->count());
            $issue->setAttribute('btp_label_print_url', $btpCodes->isNotEmpty()
                ? url('/client/lenh-btp/tem-qr?codes=' . urlencode($btpCodes->implode(',')))
                : null);

            return $issue;
        });

        return response()->json([
            'data' => $data,
            'summary' => [
                'total_issues' => $data->count(),
                'total_lines' => $data->sum('lines_count'),
                'total_quantity' => (float) $data->sum('lines_sum_quantity'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'issue_type' => 'nullable|in:material,production,customer',
            'issue_date' => 'required|date',
            'warehouse_code' => 'nullable|string|max:50',
            'receiver_name' => 'nullable|string|max:150',
            'department' => 'nullable|string|max:150',
            'production_order' => 'nullable|string|max:100',
            'purpose' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
            'force_new_btp_orders' => 'nullable|boolean',
            'allow_negative' => 'nullable|boolean',
            'lines' => 'required|array|min:1',
            'lines.*.ma_hh' => 'nullable|string|max:100',
            'lines.*.ten_hh' => 'nullable|string|max:1000',
            'lines.*.dvt' => 'nullable|string|max:50',
            'lines.*.quantity' => 'required|numeric|min:0.001',
            'lines.*.location_code' => 'nullable|string|max:100',
            'lines.*.internal_item_code' => 'nullable|string|max:100',
            'lines.*.size' => 'nullable|string|max:255',
            'lines.*.color' => 'nullable|string|max:1000',
            'lines.*.side' => 'nullable|string|max:255',
            'lines.*.note' => 'nullable|string|max:1000',
            'lines.*.production_order_id' => 'nullable|integer',
            'lines.*.production_order' => 'nullable|string|max:100',
            'lines.*.ps_number' => 'nullable|string|max:100',
            'lines.*.order_reference' => 'nullable|string|max:100',
            'lines.*.ordered_quantity' => 'nullable|numeric|min:0',
            'lines.*.logo_color' => 'nullable|string|max:100',
            'lines.*.error_quantity' => 'nullable',
            'lines.*.purchase_order' => 'nullable|string|max:1000',
            'lines.*.customer' => 'nullable|string|max:200',
            'lines.*.ordered_quantity' => 'nullable|numeric|min:0',
        ]);
        $data = $this->normalizeDateFields($data, ['issue_date']);

        $catalogValidator = app(InternalCatalogValidator::class);
        $catalogErrors = $catalogValidator->errorsForLines(collect($data['lines']));
        if (!empty($catalogErrors)) {
            return $catalogValidator->responseForErrors($catalogErrors);
        }

        $issueType = $data['issue_type'] ?? 'production';
        $warehouseCode = strtoupper(trim($data['warehouse_code'] ?? ''));
        $stockWarnings = $this->stockShortages($data['lines'], $warehouseCode);
        if (!empty($stockWarnings) && empty($data['allow_negative'])) {
            return $this->negativeStockWarningResponse($stockWarnings);
        }
        $createdBtpOrderCodes = [];

        $issue = DB::connection('internal')->transaction(function () use ($data, $issueType, &$createdBtpOrderCodes) {
            $createdBtpOrderCodes = $this->createMissingBtpOrdersForIssue($data, $issueType);

            $issue = InternalMaterialIssue::query()->create([
                'issue_code' => $this->nextIssueCode($issueType),
                'issue_type' => $issueType,
                'issue_date' => $data['issue_date'],
                'warehouse_code' => strtoupper(trim($data['warehouse_code'] ?? '')),
                'receiver_name' => trim($data['receiver_name'] ?? ''),
                'department' => trim($data['department'] ?? '') ?: ($issueType === 'production' ? 'Sản xuất' : ($issueType === 'customer' ? 'Kinh doanh' : '')),
                'production_order' => trim($data['production_order'] ?? ''),
                'purpose' => trim($data['purpose'] ?? '') ?: ($issueType === 'production' ? 'Xuất BTP đi sản xuất' : ($issueType === 'customer' ? 'Xuất thành phẩm cho khách hàng' : 'Xuất kho nội bộ')),
                'status' => 'posted',
                'note' => trim($data['note'] ?? ''),
            ]);

            $matchedProductionOrders = [];
            foreach ($data['lines'] as $line) {
                if ($issueType === 'customer' && trim((string) ($line['production_order'] ?? '')) === '') {
                    $matchedBtpLine = app(InternalBtpOrderMatcher::class)->find($line);
                    if ($matchedBtpLine) {
                        $line['production_order'] = trim((string) $matchedBtpLine->btp_order_code);
                        $line['production_order_id'] = null;
                    }
                }
                if (trim((string) ($line['production_order'] ?? '')) !== '') {
                    $matchedProductionOrders[] = trim((string) $line['production_order']);
                }

                $line['production_order_id'] = app(InternalProductionOrderLineResolver::class)->resolve($line);

                $base = $this->baseQuantityForLine($line);

                $issueLine = $issue->lines()->create([
                    'production_order_id' => $line['production_order_id'] ?? null,
                    'production_order' => trim($line['production_order'] ?? ''),
                    'purchase_order' => trim($line['purchase_order'] ?? ''),
                    'customer' => trim($line['customer'] ?? ''),
                    'ma_hh' => strtoupper(trim($line['ma_hh'] ?? ($line['internal_item_code'] ?? ''))),
                    'ten_hh' => mb_substr(trim($line['ten_hh'] ?? ''), 0, 255),
                    'dvt' => trim($line['dvt'] ?? ''),
                    'ordered_quantity' => $line['ordered_quantity'] ?? null,
                    'quantity' => $line['quantity'],
                    'base_quantity' => $base['quantity'],
                    'base_dvt' => $base['unit'],
                    'unit_factor' => $base['factor'],
                    'location_code' => strtoupper(trim($line['location_code'] ?? '')),
                    'internal_item_code' => trim($line['internal_item_code'] ?? ''),
                    'size' => mb_substr(trim($line['size'] ?? ''), 0, 100),
                    'color' => mb_substr(trim($line['color'] ?? ''), 0, 100),
                    'side' => mb_substr(trim($line['side'] ?? ''), 0, 100),
                    'note' => mb_substr(trim($line['note'] ?? ''), 0, 500),
                ]);

                $this->decreaseInternalStock(
                    $line,
                    strtoupper(trim($data['warehouse_code'] ?? '')),
                    $issueLine->id,
                    !empty($data['allow_negative']) || $issueType !== 'customer'
                );
            }

            $matchedProductionOrders = array_values(array_unique(array_filter($matchedProductionOrders)));
            if (!empty($matchedProductionOrders) && trim((string) $issue->production_order) === '') {
                $issue->production_order = implode(', ', $matchedProductionOrders);
                $issue->save();
            }

            return $issue->load('lines');
        });

        if ($issueType === 'production') {
            $this->markBtpOrdersIssuedFromIssue($issue);
        }

        app(InternalAudit::class)->model('issue.created', $issue, [
            'line_count' => $issue->lines->count(),
            'total_quantity' => (float) $issue->lines->sum('quantity'),
        ], $request);

        return response()->json([
            'message' => $issueType === 'production'
                ? 'Đã tạo phiếu xuất BTP đi sản xuất.'
                : ($issueType === 'customer' ? 'Đã tạo phiếu xuất thành phẩm cho khách hàng.' : 'Đã tạo phiếu xuất kho nội bộ.'),
            'data' => $issue,
            'btp_order_codes' => $createdBtpOrderCodes,
            'stock_warnings' => $stockWarnings,
            'print_url' => url('/client/xuat-vat-tu-noi-bo/' . $issue->id . '/in'),
        ]);
    }

    public function createFromReceipt(Request $request, InternalMaterialReceipt $receipt)
    {
        $data = $request->validate([
            'issue_date' => 'nullable|date',
            'receiver_name' => 'nullable|string|max:150',
            'department' => 'nullable|string|max:150',
            'purpose' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
        ]);
        $data = $this->normalizeDateFields($data, ['issue_date']);

        $receipt->load('lines');
        if ($receipt->lines->isEmpty()) {
            return response()->json(['message' => 'Phiếu nhập không có dòng hàng để xuất.'], 422);
        }

        $existingIssue = InternalMaterialIssue::query()
            ->where('issue_type', 'customer')
            ->where(function ($query) use ($receipt) {
                $query->where('source_receipt_id', $receipt->id)
                    ->orWhere('note', 'like', '%' . $receipt->receipt_code . '%');
            })
            ->first();

        if ($existingIssue) {
            return response()->json([
                'message' => 'Phiếu nhập này đã được tạo phiếu xuất trước đó: ' . $existingIssue->issue_code,
                'data' => $existingIssue,
                'existing' => true,
                'print_url' => url('/client/xuat-vat-tu-noi-bo/' . $existingIssue->id . '/in'),
            ]);
        }

        $issueDate = $data['issue_date'] ?? now('Asia/Ho_Chi_Minh')->format('Y-m-d');
        $warehouseCode = strtoupper(trim((string) $receipt->warehouse_code));
        $productionOrders = $receipt->lines
            ->pluck('production_order')
            ->filter()
            ->unique()
            ->values()
            ->implode(', ');

        $issue = DB::connection('internal')->transaction(function () use ($receipt, $data, $issueDate, $warehouseCode, $productionOrders) {
            $issue = InternalMaterialIssue::query()->create([
                'source_receipt_id' => $receipt->id,
                'issue_code' => $this->nextIssueCode('customer'),
                'issue_type' => 'customer',
                'issue_date' => $issueDate,
                'warehouse_code' => $warehouseCode,
                'receiver_name' => trim($data['receiver_name'] ?? '') ?: 'Khách hàng',
                'department' => trim($data['department'] ?? '') ?: 'Kinh doanh',
                'production_order' => $productionOrders,
                'purpose' => trim($data['purpose'] ?? '') ?: 'Xuất thành phẩm cho khách hàng',
                'status' => 'posted',
                'note' => trim($data['note'] ?? '') ?: ('Tạo từ phiếu nhập ' . $receipt->receipt_code),
            ]);

            $matchedProductionOrders = [];
            foreach ($receipt->lines as $receiptLine) {
                $line = [
                    'production_order_id' => $receiptLine->production_order_id,
                    'production_order' => trim((string) $receiptLine->production_order),
                    'purchase_order' => trim((string) $receiptLine->purchase_order),
                    'customer' => trim((string) $receiptLine->customer),
                    'ma_hh' => strtoupper(trim((string) $receiptLine->ma_hh)),
                    'ten_hh' => trim((string) $receiptLine->ten_hh),
                    'dvt' => trim((string) $receiptLine->dvt),
                    'ordered_quantity' => (float) ($receiptLine->ordered_quantity ?? 0),
                    'quantity' => (float) $receiptLine->quantity,
                    'location_code' => strtoupper(trim((string) ($receiptLine->location_code ?: $receipt->location_code))),
                    'internal_item_code' => trim((string) $receiptLine->internal_item_code),
                    'size' => trim((string) $receiptLine->size),
                    'color' => trim((string) $receiptLine->color),
                    'logo_color' => trim((string) ($receiptLine->logo_color ?? '')),
                    'side' => trim((string) $receiptLine->side),
                    'note' => trim((string) $receiptLine->note),
                ];

                if ($line['production_order'] === '') {
                    $matchedBtpLine = app(InternalBtpOrderMatcher::class)->find($line);
                    if ($matchedBtpLine) {
                        $line['production_order'] = trim((string) $matchedBtpLine->btp_order_code);
                        $line['production_order_id'] = null;
                        $receiptLine->production_order = $line['production_order'];
                        $receiptLine->production_order_id = null;
                        $receiptLine->save();
                    }
                }
                if ($line['production_order'] !== '') {
                    $matchedProductionOrders[] = $line['production_order'];
                }

                $this->assertSufficientStockForCustomerIssue([$line], $warehouseCode);

                $base = $this->baseQuantityForLine($line);
                $issueLineData = $line;
                unset($issueLineData['logo_color']);
                $issueLineData['base_quantity'] = $base['quantity'];
                $issueLineData['base_dvt'] = $base['unit'];
                $issueLineData['unit_factor'] = $base['factor'];
                $issueLine = $issue->lines()->create($issueLineData);
                $this->decreaseInternalStock($line, $warehouseCode, $issueLine->id, false);
            }

            $matchedProductionOrders = array_values(array_unique(array_filter($matchedProductionOrders)));
            if (!empty($matchedProductionOrders) && trim((string) $issue->production_order) === '') {
                $issue->production_order = implode(', ', $matchedProductionOrders);
                $issue->save();
            }

            return $issue->load('lines');
        });

        app(InternalAudit::class)->model('issue.created_from_receipt', $issue, [
            'receipt_id' => $receipt->id,
            'receipt_code' => $receipt->receipt_code,
            'line_count' => $issue->lines->count(),
            'total_quantity' => (float) $issue->lines->sum('quantity'),
        ], $request);

        return response()->json([
            'message' => 'Đã tạo phiếu xuất thành phẩm từ phiếu nhập ' . $receipt->receipt_code . '.',
            'data' => $issue,
            'print_url' => url('/client/xuat-vat-tu-noi-bo/' . $issue->id . '/in'),
        ]);
    }

    public function sendReceiptToProduction(Request $request, InternalMaterialReceipt $receipt)
    {
        $data = $request->validate([
            'issue_date' => 'nullable|date',
            'receiver_name' => 'nullable|string|max:150',
            'department' => 'nullable|string|max:150',
            'purpose' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
        ]);
        $data = $this->normalizeDateFields($data, ['issue_date']);

        $receipt->load('lines');
        if ($receipt->lines->isEmpty()) {
            return response()->json(['message' => 'Phieu nhap khong co dong hang de gui san xuat.'], 422);
        }

        $existingIssue = InternalMaterialIssue::query()
            ->where('source_receipt_id', $receipt->id)
            ->where('issue_type', 'production')
            ->first();

        if ($existingIssue) {
            return response()->json([
                'message' => 'Phieu nhap nay da gui san xuat: ' . $existingIssue->issue_code,
                'data' => $existingIssue->load('lines'),
                'existing' => true,
                'print_url' => url('/client/xuat-vat-tu-noi-bo/' . $existingIssue->id . '/in'),
            ]);
        }

        $payload = [
            'issue_type' => 'production',
            'force_new_btp_orders' => $receipt->source === 'Phieu nhap ban thanh pham',
            'issue_date' => $data['issue_date'] ?? now()->format('Y-m-d'),
            'warehouse_code' => strtoupper(trim((string) $receipt->warehouse_code)),
            'receiver_name' => trim($data['receiver_name'] ?? '') ?: 'San xuat',
            'department' => trim($data['department'] ?? '') ?: 'San xuat',
            'production_order' => '',
            'purpose' => trim($data['purpose'] ?? '') ?: 'Gui phieu nhap sang san xuat',
            'note' => trim($data['note'] ?? '') ?: ('Gui SX tu phieu nhap ' . $receipt->receipt_code),
            'lines' => $receipt->lines->map(function ($line) use ($receipt) {
                return [
                    'production_order_id' => $line->production_order_id,
                    'production_order' => trim((string) $line->production_order),
                    'purchase_order' => trim((string) $line->purchase_order),
                    'customer' => trim((string) $line->customer),
                    'ma_hh' => strtoupper(trim((string) ($line->ma_hh ?: $line->internal_item_code))),
                    'ten_hh' => trim((string) $line->ten_hh),
                    'dvt' => trim((string) $line->dvt),
                    'ordered_quantity' => $line->ordered_quantity,
                    'quantity' => (float) $line->quantity,
                    'location_code' => strtoupper(trim((string) ($line->location_code ?: $receipt->location_code))),
                    'internal_item_code' => trim((string) $line->internal_item_code),
                    'size' => trim((string) $line->size),
                    'color' => trim((string) $line->color),
                    'logo_color' => trim((string) ($line->logo_color ?? '')),
                    'side' => trim((string) $line->side),
                    'note' => trim((string) $line->note),
                ];
            })->values()->all(),
        ];

        $issueRequest = Request::create('/api/xuat-vat-tu-noi-bo', 'POST', $payload);
        $issueRequest->setUserResolver($request->getUserResolver());

        $response = $this->store($issueRequest);
        if ($response->getStatusCode() >= 400) {
            return $response;
        }

        $body = json_decode($response->getContent(), true) ?: [];
        $issueId = $body['data']['id'] ?? null;
        $issue = $issueId ? InternalMaterialIssue::query()->find($issueId) : null;
        if ($issue) {
            $issue->source_receipt_id = $receipt->id;
            $issue->save();
            $issue->load('lines');
            $body['data'] = $issue;
            $body['print_url'] = url('/client/xuat-vat-tu-noi-bo/' . $issue->id . '/in');
        }
        $body['message'] = 'Da gui phieu nhap ' . $receipt->receipt_code . ' sang san xuat.';

        return response()->json($body, $response->getStatusCode());
    }

    public function receiveProductionIssue(Request $request, InternalMaterialIssue $issue)
    {
        $data = $request->validate([
            'checked_at' => 'nullable|date',
            'location_code' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:500',
            'line_ids' => 'nullable|array|max:200',
            'line_ids.*' => 'integer',
            'export_finished_goods' => 'nullable|boolean',
        ]);
        $data = $this->normalizeDateFields($data, ['checked_at']);

        if ($issue->issue_type !== 'production') {
            return response()->json(['message' => 'Chi phieu xuat sang san xuat moi duoc nhap lai thanh pham.'], 422);
        }

        $issue->load('lines');
        if ($issue->lines->isEmpty()) {
            return response()->json(['message' => 'Phieu xuat khong co dong hang de nhap lai.'], 422);
        }

        $requestedIds = collect($data['line_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $selectedLines = $issue->lines
            ->when($requestedIds->isNotEmpty(), fn ($lines) => $lines->whereIn('id', $requestedIds->all()))
            ->values();

        if ($selectedLines->isEmpty()) {
            return response()->json(['message' => 'Khong tim thay dong BTP da chon trong phieu xuat.'], 422);
        }

        $pendingLines = $selectedLines->whereNull('completed_at')->values();
        if ($pendingLines->isEmpty()) {
            $existingReceiptId = (int) $selectedLines->pluck('completion_receipt_id')->filter()->first();
            $existingReceipt = $existingReceiptId ? InternalMaterialReceipt::query()->find($existingReceiptId) : null;
            $existingCustomerIssueId = (int) $selectedLines->pluck('customer_issue_id')->filter()->first();
            $existingCustomerIssue = $existingCustomerIssueId ? InternalMaterialIssue::query()->find($existingCustomerIssueId) : null;

            return response()->json([
                'message' => 'Cac dong BTP da chon da hoan tat truoc do.',
                'data' => $existingReceipt ? $existingReceipt->load('lines') : null,
                'existing' => true,
                'receipt_print_url' => $existingReceipt ? url('/client/nhap-thanh-pham-noi-bo/' . $existingReceipt->id . '/in') : null,
                'customer_issue' => $existingCustomerIssue,
                'customer_issue_print_url' => $existingCustomerIssue ? url('/client/xuat-vat-tu-noi-bo/' . $existingCustomerIssue->id . '/in') : null,
            ]);
        }

        $payload = [
            'location_code' => strtoupper(trim($data['location_code'] ?? '')) ?: 'CHUA-XEP',
            'ma_ko' => '',
            'checked_at' => $data['checked_at'] ?? now()->format('Y-m-d'),
            'note' => trim($data['note'] ?? '') ?: ('Nhap lai tu phieu xuat SX ' . $issue->issue_code),
            'lines' => $pendingLines->map(function ($line) use ($issue) {
                return [
                    'category' => trim((string) $line->ten_hh),
                    'ma_sp' => trim((string) $line->ma_hh),
                    'internal_item_code' => trim((string) $line->internal_item_code),
                    'size' => trim((string) $line->size),
                    'color' => trim((string) $line->color),
                    'side' => trim((string) $line->side),
                    'dvt' => trim((string) $line->dvt),
                    'ordered_quantity' => $line->ordered_quantity,
                    'quantity' => (float) $line->quantity,
                    'logo_color' => '',
                    'location_code' => 'CHUA-XEP',
                    'note' => trim((string) $line->note),
                    'production_order_id' => $line->production_order_id,
                    'production_order' => trim((string) $line->production_order) ?: $issue->issue_code,
                    'purchase_order' => trim((string) $line->purchase_order),
                    'customer' => trim((string) $line->customer),
                ];
            })->values()->all(),
        ];

        $connection = DB::connection('internal');
        $connection->beginTransaction();
        try {
            $receiptRequest = Request::create('/api/kiem-ton-kho/phieu-nhap-tp', 'POST', $payload);
            $receiptRequest->setUserResolver($request->getUserResolver());
            $receiptResponse = app(WarehouseCountController::class)->storeReceiptBatch($receiptRequest);
            if ($receiptResponse->getStatusCode() >= 400) {
                $connection->rollBack();
                return $receiptResponse;
            }

            $body = json_decode($receiptResponse->getContent(), true) ?: [];
            $receiptId = (int) ($body['data']['id'] ?? 0);
            $receipt = $receiptId ? InternalMaterialReceipt::query()->find($receiptId) : null;
            if (!$receipt) {
                $connection->rollBack();
                return response()->json(['message' => 'Da nhap du lieu nhung khong tim thay phieu thanh pham vua tao.'], 422);
            }

            $customerIssue = null;
            $exportFinishedGoods = (bool) ($data['export_finished_goods'] ?? false);
            if ($exportFinishedGoods) {
                $customerRequest = Request::create('/api/xuat-vat-tu-noi-bo/tu-phieu-nhap/' . $receipt->id, 'POST', [
                    'issue_date' => $data['checked_at'] ?? now()->format('Y-m-d'),
                    'receiver_name' => trim((string) $pendingLines->pluck('customer')->filter()->first()) ?: 'Khach hang',
                    'department' => 'Kinh doanh',
                    'purpose' => 'Xuat thanh pham cho khach hang',
                    'note' => 'Tu dong xuat sau khi hoan tat BTP ' . $issue->issue_code,
                ]);
                $customerRequest->setUserResolver($request->getUserResolver());
                $customerResponse = $this->createFromReceipt($customerRequest, $receipt);
                if ($customerResponse->getStatusCode() >= 400) {
                    $connection->rollBack();
                    return $customerResponse;
                }
                $customerBody = json_decode($customerResponse->getContent(), true) ?: [];
                $customerIssueId = (int) ($customerBody['data']['id'] ?? 0);
                $customerIssue = $customerIssueId ? InternalMaterialIssue::query()->find($customerIssueId) : null;
            }

            $this->markProductionLinesCompleted($issue, $pendingLines, $receipt, $customerIssue);
            $connection->commit();

            $receipt->refresh()->load('lines');
            return response()->json([
                'message' => $exportFinishedGoods
                    ? 'Da nhap thanh pham va xuat thanh pham cho cac dong da chon.'
                    : 'Da nhap thanh pham cho cac dong BTP da chon.',
                'data' => $receipt,
                'completed_line_ids' => $pendingLines->pluck('id')->map(fn ($id) => (int) $id)->values(),
                'receipt_print_url' => url('/client/nhap-thanh-pham-noi-bo/' . $receipt->id . '/in'),
                'customer_issue' => $customerIssue,
                'customer_issue_print_url' => $customerIssue ? url('/client/xuat-vat-tu-noi-bo/' . $customerIssue->id . '/in') : null,
            ]);
        } catch (\Throwable $error) {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
            throw $error;
        }
    }

    public function receiveProductionLines(Request $request)
    {
        $data = $request->validate([
            'checked_at' => 'nullable|date',
            'location_code' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:500',
            'line_ids' => 'required|array|min:1|max:200',
            'line_ids.*' => 'integer',
            'export_finished_goods' => 'nullable|boolean',
        ]);
        $data = $this->normalizeDateFields($data, ['checked_at']);

        $lineIds = collect($data['line_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $productionIssueIds = InternalMaterialIssue::query()
            ->where('issue_type', 'production')
            ->pluck('id');
        $lines = InternalMaterialIssueLine::query()
            ->whereIn('id', $lineIds->all())
            ->whereIn('issue_id', $productionIssueIds)
            ->whereNull('completed_at')
            ->orderBy('issue_id')
            ->orderBy('id')
            ->get();

        if ($lines->isEmpty()) {
            return response()->json(['message' => 'Cac dong da chon khong con nam o san xuat. Hay tai lai danh sach.'], 422);
        }

        if ($lines->count() !== $lineIds->count()) {
            return response()->json([
                'message' => 'Mot so dong da hoan tat hoac khong thuoc phieu xuat BTP. Hay tai lai va chon lai.',
            ], 422);
        }

        $issues = InternalMaterialIssue::query()
            ->whereIn('id', $lines->pluck('issue_id')->unique()->all())
            ->get()
            ->keyBy('id');
        $issueCodes = $issues->pluck('issue_code')->filter()->values();
        $payload = [
            'location_code' => strtoupper(trim($data['location_code'] ?? '')) ?: 'CHUA-XEP',
            'ma_ko' => '',
            'checked_at' => $data['checked_at'] ?? now()->format('Y-m-d'),
            'note' => trim($data['note'] ?? '') ?: ('Nhap TP tu cac nhom ' . $issueCodes->implode(', ')),
            'lines' => $lines->map(function ($line) use ($issues) {
                $issue = $issues->get($line->issue_id);
                return [
                    'category' => trim((string) $line->ten_hh),
                    'ma_sp' => trim((string) $line->ma_hh),
                    'internal_item_code' => trim((string) $line->internal_item_code),
                    'size' => trim((string) $line->size),
                    'color' => trim((string) $line->color),
                    'side' => trim((string) $line->side),
                    'dvt' => trim((string) $line->dvt),
                    'ordered_quantity' => $line->ordered_quantity,
                    'quantity' => (float) $line->quantity,
                    'logo_color' => '',
                    'location_code' => 'CHUA-XEP',
                    'note' => trim((string) $line->note),
                    'production_order_id' => $line->production_order_id,
                    'production_order' => trim((string) $line->production_order) ?: ($issue->issue_code ?? ''),
                    'purchase_order' => trim((string) $line->purchase_order),
                    'customer' => trim((string) $line->customer),
                ];
            })->values()->all(),
        ];

        $connection = DB::connection('internal');
        $connection->beginTransaction();
        try {
            $receiptRequest = Request::create('/api/kiem-ton-kho/phieu-nhap-tp', 'POST', $payload);
            $receiptRequest->setUserResolver($request->getUserResolver());
            $receiptResponse = app(WarehouseCountController::class)->storeReceiptBatch($receiptRequest);
            if ($receiptResponse->getStatusCode() >= 400) {
                $connection->rollBack();
                return $receiptResponse;
            }

            $receiptBody = json_decode($receiptResponse->getContent(), true) ?: [];
            $receipt = InternalMaterialReceipt::query()->find((int) ($receiptBody['data']['id'] ?? 0));
            if (!$receipt) {
                $connection->rollBack();
                return response()->json(['message' => 'Khong tim thay phieu nhap thanh pham vua tao.'], 422);
            }

            $customerIssue = null;
            $exportFinishedGoods = (bool) ($data['export_finished_goods'] ?? true);
            if ($exportFinishedGoods) {
                $customers = $lines->pluck('customer')->map(fn ($value) => trim((string) $value))->filter()->unique();
                $customerRequest = Request::create('/api/xuat-vat-tu-noi-bo/tu-phieu-nhap/' . $receipt->id, 'POST', [
                    'issue_date' => $data['checked_at'] ?? now()->format('Y-m-d'),
                    'receiver_name' => mb_substr($customers->implode(', '), 0, 150) ?: 'Khach hang',
                    'department' => 'Kinh doanh',
                    'purpose' => 'Xuat thanh pham cho khach hang',
                    'note' => 'Gom dong tu ' . $issueCodes->implode(', '),
                ]);
                $customerRequest->setUserResolver($request->getUserResolver());
                $customerResponse = $this->createFromReceipt($customerRequest, $receipt);
                if ($customerResponse->getStatusCode() >= 400) {
                    $connection->rollBack();
                    return $customerResponse;
                }
                $customerBody = json_decode($customerResponse->getContent(), true) ?: [];
                $customerIssue = InternalMaterialIssue::query()->find((int) ($customerBody['data']['id'] ?? 0));
            }

            foreach ($lines->groupBy('issue_id') as $issueId => $issueLines) {
                $sourceIssue = $issues->get($issueId);
                if ($sourceIssue) {
                    $this->markProductionLinesCompleted($sourceIssue, $issueLines, $receipt, $customerIssue);
                }
            }
            $connection->commit();

            return response()->json([
                'message' => 'Da gom ' . $lines->count() . ' dong tu ' . $issues->count() . ' nhom vao mot phieu nhap va mot phieu xuat thanh pham.',
                'data' => $receipt->fresh()->load('lines'),
                'source_issue_codes' => $issueCodes,
                'completed_line_ids' => $lines->pluck('id')->map(fn ($id) => (int) $id)->values(),
                'receipt_print_url' => url('/client/nhap-thanh-pham-noi-bo/' . $receipt->id . '/in'),
                'customer_issue' => $customerIssue,
                'customer_issue_print_url' => $customerIssue ? url('/client/xuat-vat-tu-noi-bo/' . $customerIssue->id . '/in') : null,
            ]);
        } catch (\Throwable $error) {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
            throw $error;
        }
    }

    private function markProductionLinesCompleted(InternalMaterialIssue $issue, $lines, InternalMaterialReceipt $receipt, ?InternalMaterialIssue $customerIssue): void
    {
        $lineIds = collect($lines)->pluck('id')->map(fn ($id) => (int) $id)->filter()->values();
        InternalMaterialIssueLine::query()
            ->where('issue_id', $issue->id)
            ->whereIn('id', $lineIds->all())
            ->update([
                'completion_receipt_id' => $receipt->id,
                'customer_issue_id' => $customerIssue ? $customerIssue->id : null,
                'completed_at' => now(),
            ]);

        $hasPendingLines = InternalMaterialIssueLine::query()
            ->where('issue_id', $issue->id)
            ->whereNull('completed_at')
            ->exists();
        $issue->status = $hasPendingLines ? 'posted' : 'completed';
        $issue->save();

        $orderCodes = collect($lines)
            ->pluck('production_order')
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->filter(fn ($code) => preg_match('/^BTP\d{4}-\d{4}$/', $code))
            ->unique();
        foreach ($orderCodes as $orderCode) {
            $orderHasPendingLines = InternalMaterialIssueLine::query()
                ->where('production_order', $orderCode)
                ->whereIn('issue_id', InternalMaterialIssue::query()
                    ->select('id')
                    ->where('issue_type', 'production'))
                ->whereNull('completed_at')
                ->exists();
            if (!$orderHasPendingLines) {
                InternalBtpProductionOrder::query()
                    ->where('btp_order_code', $orderCode)
                    ->update(['status' => 'completed', 'completed_at' => now()]);
            }
        }
    }

    public function productionOrderLines(Request $request)
    {
        $productionOrder = trim((string) $request->query('production_order', ''));
        if ($productionOrder === '') {
            return response()->json(['data' => []]);
        }

        if (preg_match('/^BTP\d{4}-\d{4}$/i', $productionOrder)) {
            return $this->btpProductionOrderLines($productionOrder, $request);
        }

        $orders = InternalProductionOrder::query()
            ->where('is_active', true)
            ->where('production_order', $productionOrder)
            ->orderBy('source_row')
            ->get();

        $warehouseCode = strtoupper(trim((string) $request->query('warehouse_code', '')));

        $data = $orders->map(function (InternalProductionOrder $order) use ($warehouseCode) {
            $stockQuery = InventoryPackage::query()
                ->with('location:id,location_code')
                ->where('quantity', '>', 0)
                ->whereRaw('UPPER(TRIM(internal_item_code)) = ?', [mb_strtoupper(trim((string) $order->item_code))]);

            if ($warehouseCode !== '') {
                $stockQuery->where('ma_ko', $warehouseCode);
            }
            if (trim((string) $order->size) !== '') {
                $stockQuery->where('size', trim((string) $order->size));
            }
            if (trim((string) $order->color) !== '') {
                $stockQuery->where('color', trim((string) $order->color));
            }

            $packages = $stockQuery->orderBy('checked_at')->orderBy('id')->get();
            $accountingCodes = $packages->pluck('ma_sp')->filter()->unique()->values();
            $locations = $packages->pluck('location.location_code')->filter()->unique()->values();

            return [
                'production_order_id' => $order->id,
                'production_order' => $order->production_order,
                'purchase_order' => $order->purchase_order,
                'customer' => $order->customer,
                'internal_item_code' => mb_substr((string) $order->item_code, 0, 100),
                'ten_hh' => mb_substr((string) ($order->description ?: $order->specification), 0, 255),
                'dvt' => mb_substr((string) $order->unit, 0, 50),
                'ordered_quantity' => (float) $order->order_quantity,
                'size' => mb_substr((string) $order->size, 0, 100),
                'color' => mb_substr((string) $order->color, 0, 100),
                'ma_hh' => $accountingCodes->count() === 1 ? $accountingCodes->first() : '',
                'location_code' => $locations->count() === 1 ? $locations->first() : '',
                'available_quantity' => (float) $packages->sum('quantity'),
                'stock_match_count' => $packages->count(),
            ];
        });

        return response()->json([
            'data' => $data,
            'summary' => [
                'production_order' => $productionOrder,
                'variant_count' => $data->count(),
                'ordered_quantity' => (float) $data->sum('ordered_quantity'),
                'available_quantity' => (float) $data->sum('available_quantity'),
            ],
        ]);
    }

    private function btpProductionOrderLines(string $productionOrder, Request $request)
    {
        $order = InternalBtpProductionOrder::query()
            ->with('lines')
            ->whereRaw('UPPER(TRIM(btp_order_code)) = ?', [mb_strtoupper(trim($productionOrder))])
            ->first();

        if (!$order) {
            return response()->json([
                'data' => [],
                'summary' => [
                    'production_order' => $productionOrder,
                    'variant_count' => 0,
                    'ordered_quantity' => 0,
                    'available_quantity' => 0,
                ],
            ]);
        }

        $warehouseCode = strtoupper(trim((string) $request->query('warehouse_code', '')));

        $data = $order->lines->map(function ($line) use ($order, $warehouseCode) {
            $stockQuery = InventoryPackage::query()
                ->with('location:id,location_code')
                ->where('quantity', '>', 0);

            $internalCode = trim((string) $line->internal_item_code);
            $materialCode = strtoupper(trim((string) $line->ma_hh));

            if ($internalCode !== '') {
                $stockQuery->whereRaw('UPPER(TRIM(internal_item_code)) = ?', [mb_strtoupper($internalCode)]);
            } elseif ($materialCode !== '') {
                $stockQuery->whereRaw('UPPER(TRIM(ma_sp)) = ?', [$materialCode]);
            }

            if ($warehouseCode !== '') {
                $stockQuery->where('ma_ko', $warehouseCode);
            }
            if (trim((string) $line->size) !== '') {
                $stockQuery->where('size', trim((string) $line->size));
            }
            if (trim((string) $line->color) !== '') {
                $stockQuery->where('color', trim((string) $line->color));
            }

            $packages = ($internalCode !== '' || $materialCode !== '')
                ? $stockQuery->orderBy('checked_at')->orderBy('id')->get()
                : collect();
            $accountingCodes = $packages->pluck('ma_sp')->filter()->unique()->values();
            $locations = $packages->pluck('location.location_code')->filter()->unique()->values();

            return [
                'production_order_id' => null,
                'production_order' => $order->btp_order_code,
                'purchase_order' => '',
                'customer' => '',
                'internal_item_code' => mb_substr($internalCode, 0, 100),
                'ten_hh' => mb_substr((string) $line->ten_hh, 0, 255),
                'dvt' => mb_substr((string) $line->dvt, 0, 50),
                'ordered_quantity' => (float) ($line->ordered_quantity ?: $line->quantity),
                'size' => mb_substr((string) $line->size, 0, 100),
                'color' => mb_substr((string) $line->color, 0, 100),
                'ma_hh' => $accountingCodes->count() === 1 ? $accountingCodes->first() : $materialCode,
                'location_code' => $locations->count() === 1 ? $locations->first() : trim((string) $line->location_code),
                'available_quantity' => (float) $packages->sum('quantity'),
                'stock_match_count' => $packages->count(),
                'note' => trim((string) $line->note),
                'source' => 'btp',
            ];
        });

        return response()->json([
            'data' => $data,
            'summary' => [
                'production_order' => $order->btp_order_code,
                'variant_count' => $data->count(),
                'ordered_quantity' => (float) $data->sum('ordered_quantity'),
                'available_quantity' => (float) $data->sum('available_quantity'),
                'status' => $order->status,
            ],
        ]);
    }

    public function resolvePastedLines(Request $request)
    {
        $data = $request->validate([
            'customer' => 'required|string|max:100',
            'lines' => 'required|array|min:1|max:500',
            'lines.*.issue_date' => 'nullable|date',
            'lines.*.production_order' => 'nullable|string|max:100',
            'lines.*.ma_hh' => 'nullable|string|max:100',
            'lines.*.internal_item_code' => 'nullable|string|max:100',
            'lines.*.size' => 'nullable|string|max:100',
            'lines.*.color' => 'nullable|string|max:100',
            'lines.*.side' => 'nullable|string|max:100',
            'lines.*.dvt' => 'nullable|string|max:50',
            'lines.*.quantity' => 'nullable|numeric|min:0',
            'lines.*.location_code' => 'nullable|string|max:100',
            'lines.*.note' => 'nullable|string|max:500',
        ]);

        $catalog = app(GoogleSheetInternalCatalog::class);
        $rows = collect($data['lines'])->map(function ($line, $index) use ($data, $catalog) {
            $maHh = strtoupper(trim((string) ($line['ma_hh'] ?? '')));
            $internalCode = trim((string) ($line['internal_item_code'] ?? ''));
            $size = trim((string) ($line['size'] ?? ''));
            $color = trim((string) ($line['color'] ?? ''));
            $side = trim((string) ($line['side'] ?? ''));
            $locationCode = strtoupper(trim((string) ($line['location_code'] ?? '')));
            $productionOrderCode = trim((string) ($line['production_order'] ?? ''));
            $psNumber = trim((string) ($line['ps_number'] ?? ''));
            $productionOrder = null;
            $catalogItem = $internalCode !== '' ? $catalog->find($internalCode) : null;

            if ($internalCode === '' && $productionOrderCode !== '') {
                $productionOrderQuery = InternalProductionOrder::query()
                    ->where('is_active', true)
                    ->where('production_order', $productionOrderCode);

                if ($size !== '') {
                    $productionOrderQuery->whereRaw('UPPER(TRIM(size)) = ?', [mb_strtoupper($size)]);
                }
                if ($color !== '') {
                    $productionOrderQuery->whereRaw('UPPER(TRIM(color)) = ?', [mb_strtoupper($color)]);
                }

                $productionOrder = $productionOrderQuery->orderBy('source_row')->first();
                if ($productionOrder) {
                    $internalCode = trim((string) $productionOrder->item_code);
                }
            }

            $query = InventoryPackage::query()
                ->with('location:id,location_code')
                ->where('quantity', '>', 0);

            if ($maHh !== '') {
                $query->whereRaw('UPPER(TRIM(ma_sp)) = ?', [$maHh]);
            }
            if ($internalCode !== '') {
                $query->whereRaw('UPPER(TRIM(internal_item_code)) = ?', [mb_strtoupper($internalCode)]);
            }
            if ($size !== '') {
                $query->whereRaw('UPPER(TRIM(size)) = ?', [mb_strtoupper($size)]);
            }
            if ($color !== '') {
                $query->whereRaw('UPPER(TRIM(color)) = ?', [mb_strtoupper($color)]);
            }
            if ($locationCode !== '') {
                $query->whereHas('location', function ($locationQuery) use ($locationCode) {
                    $locationQuery->whereRaw('UPPER(TRIM(location_code)) = ?', [$locationCode]);
                });
            }

            $packages = ($maHh !== '' || $internalCode !== '')
                ? $query->orderBy('checked_at')->orderBy('id')->get()
                : collect();
            $accountingCodes = $packages->pluck('ma_sp')->filter()->unique()->values();
            $locations = $packages->pluck('location.location_code')->filter()->unique()->values();
            $warnings = [];
            $isUnipaxReceipt = mb_strtoupper(trim((string) $data['customer'])) === 'UNIPAX';

            if ($isUnipaxReceipt) {
                if ($internalCode === '') {
                    $warnings[] = 'Thiếu mã nội bộ.';
                } elseif (!$catalogItem) {
                    $warnings[] = 'Mã nội bộ không có trong sheet DANH MỤC.';
                }
            } else {
                if ($internalCode === '' && $maHh === '') {
                    $warnings[] = $productionOrderCode !== ''
                        ? 'Lệnh sản xuất chưa khớp dữ liệu.'
                        : 'Thiếu mã hàng để đối chiếu tồn.';
                } elseif ($packages->isEmpty()) {
                    $warnings[] = 'Không tìm thấy tồn nội bộ phù hợp.';
                } else {
                    if ($maHh === '' && $accountingCodes->isEmpty()) {
                        $warnings[] = 'Tồn nội bộ chưa được gán mã kế toán.';
                    }
                    if ($maHh === '' && $accountingCodes->count() > 1) {
                        $warnings[] = 'Có nhiều mã kế toán, cần chọn lại.';
                    }
                    if ($locationCode === '' && $locations->count() > 1) {
                        $warnings[] = 'Hàng nằm ở nhiều vị trí.';
                    }
                }
            }

            $quantity = (float) ($line['quantity'] ?? 0);
            $base = app(InternalUnitConverter::class)->toBase(
                $internalCode,
                $quantity,
                trim((string) ($line['dvt'] ?? '')),
                $catalogItem['unit'] ?? trim((string) ($line['dvt'] ?? ''))
            );
            $available = (float) $packages->sum('quantity');
            if ($quantity <= 0) {
                $warnings[] = 'Số lượng xuất chưa hợp lệ.';
            } elseif (!$isUnipaxReceipt && $available > 0 && (float) $base['quantity'] > $available + 0.0001) {
                $warnings[] = 'Số lượng xuất lớn hơn tồn phù hợp.';
            }

            $logoColor = trim((string) ($line['logo_color'] ?? ''));
            $matchedBtpLine = $this->findMatchingBtpOrderLine([
                'ps_number' => $psNumber,
                'internal_item_code' => $internalCode,
                'ma_hh' => $maHh,
                'size' => $size ?: ($catalogItem['size'] ?? ''),
                'color' => $color ?: ($catalogItem['color'] ?? ''),
                'logo_color' => $logoColor,
                'side' => $side ?: ($catalogItem['side'] ?? ''),
                'ordered_quantity' => (float) ($line['ordered_quantity'] ?? 0),
            ]);

            if ($matchedBtpLine) {
                if ($productionOrderCode === '') {
                    $productionOrderCode = trim((string) $matchedBtpLine->btp_order_code);
                }

                $warnings[] = 'Dong PS nay da co trong lenh BTP ' . $matchedBtpLine->btp_order_code
                    . ' (' . $matchedBtpLine->status . ').';
            }

            return [
                'source_row' => $index + 1,
                'issue_date' => $this->normalizeDateInput($line['issue_date'] ?? null),
                'customer' => trim($data['customer']),
                'production_order_id' => $productionOrder->id ?? null,
                'production_order' => $productionOrderCode,
                'purchase_order' => $psNumber ?: ($productionOrder->purchase_order ?? ''),
                'ma_hh' => $maHh ?: ($accountingCodes->count() === 1 ? $accountingCodes->first() : ''),
                'ten_hh' => $catalogItem['name'] ?? ($productionOrder->description ?? ''),
                'internal_item_code' => $internalCode,
                'size' => $size ?: ($catalogItem['size'] ?? ''),
                'color' => $color ?: ($catalogItem['color'] ?? ''),
                'logo_color' => $logoColor,
                'side' => $side ?: ($catalogItem['side'] ?? ''),
                'dvt' => trim((string) ($line['dvt'] ?? '')) ?: ($catalogItem['unit'] ?? ''),
                'ordered_quantity' => (float) ($line['ordered_quantity'] ?? 0),
                'quantity' => $quantity,
                'base_quantity' => (float) $base['quantity'],
                'base_dvt' => $base['unit'],
                'unit_factor' => $base['factor'],
                'location_code' => $locationCode ?: ($locations->count() === 1 ? $locations->first() : ''),
                'available_quantity' => $available,
                'note' => trim((string) ($line['note'] ?? '')),
                'warnings' => $warnings,
                'is_valid' => empty($warnings),
            ];
        })->values();

        return response()->json([
            'data' => $rows,
            'summary' => [
                'line_count' => $rows->count(),
                'valid_count' => $rows->where('is_valid', true)->count(),
                'warning_count' => $rows->where('is_valid', false)->count(),
                'total_quantity' => (float) $rows->sum('quantity'),
            ],
        ]);
    }

    public function internalCatalog(Request $request)
    {
        $keyword = trim((string) $request->query('keyword', ''));
        $limit = min(max((int) $request->query('limit', 30), 1), 100);

        return response()->json([
            'data' => app(GoogleSheetInternalCatalog::class)->search($keyword, $limit),
            'source' => [
                'sheet' => 'DANH MỤC',
                'mode' => 'read_only',
            ],
        ]);
    }

    public function show(InternalMaterialIssue $issue)
    {
        return response()->json([
            'data' => $issue->load('lines'),
        ]);
    }

    public function update(Request $request, InternalMaterialIssue $issue)
    {
        $data = $request->validate([
            'issue_type' => 'nullable|in:material,production,customer',
            'issue_date' => 'required|date',
            'warehouse_code' => 'nullable|string|max:50',
            'receiver_name' => 'nullable|string|max:150',
            'department' => 'nullable|string|max:150',
            'production_order' => 'nullable|string|max:1000',
            'purpose' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
            'allow_negative' => 'nullable|boolean',
            'lines' => 'required|array|min:1',
            'lines.*.ma_hh' => 'nullable|string|max:100',
            'lines.*.ten_hh' => 'nullable|string|max:1000',
            'lines.*.dvt' => 'nullable|string|max:50',
            'lines.*.quantity' => 'required|numeric|min:0.001',
            'lines.*.location_code' => 'nullable|string|max:100',
            'lines.*.internal_item_code' => 'nullable|string|max:100',
            'lines.*.size' => 'nullable|string|max:255',
            'lines.*.color' => 'nullable|string|max:1000',
            'lines.*.side' => 'nullable|string|max:255',
            'lines.*.note' => 'nullable|string|max:1000',
            'lines.*.production_order_id' => 'nullable|integer',
            'lines.*.production_order' => 'nullable|string|max:100',
            'lines.*.ps_number' => 'nullable|string|max:100',
            'lines.*.purchase_order' => 'nullable|string|max:1000',
            'lines.*.customer' => 'nullable|string|max:200',
            'lines.*.ordered_quantity' => 'nullable|numeric|min:0',
            'lines.*.logo_color' => 'nullable|string|max:100',
        ]);
        $data = $this->normalizeDateFields($data, ['issue_date']);

        $catalogValidator = app(InternalCatalogValidator::class);
        $catalogErrors = $catalogValidator->errorsForLines(collect($data['lines']));
        if (!empty($catalogErrors)) {
            return $catalogValidator->responseForErrors($catalogErrors);
        }

        $issueType = $data['issue_type'] ?? $issue->issue_type ?? 'production';
        $stockWarnings = [];
        $createdBtpOrderCodes = [];

        $updatedIssue = DB::connection('internal')->transaction(function () use ($issue, $data, $issueType, &$createdBtpOrderCodes, &$stockWarnings) {
            $issue->load('lines.allocations');
            foreach ($issue->lines as $line) {
                $this->restoreIssueLineStock($issue, $line);
                $line->delete();
            }

            $stockWarnings = $this->stockShortages(
                $data['lines'],
                strtoupper(trim($data['warehouse_code'] ?? ''))
            );
            if (!empty($stockWarnings) && empty($data['allow_negative'])) {
                throw ValidationException::withMessages([
                    'stock' => array_column($stockWarnings, 'message'),
                ]);
            }

            $createdBtpOrderCodes = $this->createMissingBtpOrdersForIssue($data, $issueType);

            $issue->update([
                'issue_type' => $issueType,
                'issue_date' => $data['issue_date'],
                'warehouse_code' => strtoupper(trim($data['warehouse_code'] ?? '')),
                'receiver_name' => trim($data['receiver_name'] ?? ''),
                'department' => trim($data['department'] ?? '') ?: ($issueType === 'production' ? 'Sản xuất' : ($issueType === 'customer' ? 'Kinh doanh' : '')),
                'production_order' => trim($data['production_order'] ?? ''),
                'purpose' => trim($data['purpose'] ?? '') ?: ($issueType === 'production' ? 'Xuất BTP đi sản xuất' : ($issueType === 'customer' ? 'Xuất thành phẩm cho khách hàng' : 'Xuất kho nội bộ')),
                'status' => 'posted',
                'note' => trim($data['note'] ?? ''),
            ]);

            foreach ($data['lines'] as $line) {
                $line['production_order_id'] = app(InternalProductionOrderLineResolver::class)->resolve($line);
                $base = $this->baseQuantityForLine($line);

                $issueLine = $issue->lines()->create([
                    'production_order_id' => $line['production_order_id'] ?? null,
                    'production_order' => trim($line['production_order'] ?? ''),
                    'purchase_order' => trim($line['purchase_order'] ?? ''),
                    'customer' => trim($line['customer'] ?? ''),
                    'ma_hh' => strtoupper(trim($line['ma_hh'] ?? ($line['internal_item_code'] ?? ''))),
                    'ten_hh' => mb_substr(trim($line['ten_hh'] ?? ''), 0, 255),
                    'dvt' => trim($line['dvt'] ?? ''),
                    'ordered_quantity' => $line['ordered_quantity'] ?? null,
                    'quantity' => $line['quantity'],
                    'base_quantity' => $base['quantity'],
                    'base_dvt' => $base['unit'],
                    'unit_factor' => $base['factor'],
                    'location_code' => strtoupper(trim($line['location_code'] ?? '')),
                    'internal_item_code' => trim($line['internal_item_code'] ?? ''),
                    'size' => mb_substr(trim($line['size'] ?? ''), 0, 100),
                    'color' => mb_substr(trim($line['color'] ?? ''), 0, 100),
                    'side' => mb_substr(trim($line['side'] ?? ''), 0, 100),
                    'note' => mb_substr(trim($line['note'] ?? ''), 0, 500),
                ]);

                $this->decreaseInternalStock(
                    $line,
                    strtoupper(trim($data['warehouse_code'] ?? '')),
                    $issueLine->id,
                    !empty($data['allow_negative']) || $issueType !== 'customer'
                );
            }

            return $issue->fresh()->load('lines');
        });

        if ($issueType === 'production') {
            $this->markBtpOrdersIssuedFromIssue($updatedIssue);
        }

        app(InternalAudit::class)->model('issue.updated', $updatedIssue, [
            'line_count' => $updatedIssue->lines->count(),
            'total_quantity' => (float) $updatedIssue->lines->sum('quantity'),
        ], $request);

        return response()->json([
            'message' => 'Đã cập nhật phiếu xuất kho nội bộ.',
            'data' => $updatedIssue,
            'btp_order_codes' => $createdBtpOrderCodes,
            'stock_warnings' => $stockWarnings,
            'print_url' => url('/client/xuat-vat-tu-noi-bo/' . $updatedIssue->id . '/in'),
        ]);
    }

    public function destroy(InternalMaterialIssue $issue)
    {
        $auditPayload = [
            'issue_code' => $issue->issue_code,
            'issue_date' => optional($issue->issue_date)->format('Y-m-d'),
        ];

        DB::connection('internal')->transaction(function () use ($issue) {
            $issue->load('lines.allocations');

            foreach ($issue->lines as $line) {
                $this->restoreIssueLineStock($issue, $line);
            }

            InternalXntRow::query()
                ->where('issue_id', $issue->id)
                ->update([
                    'issue_id' => null,
                    'issued_at' => null,
                ]);

            $issue->delete();
        });

        app(InternalAudit::class)->record(
            'issue.deleted',
            'InternalMaterialIssue',
            (int) $issue->id,
            $issue->issue_code,
            $auditPayload,
            request()
        );

        return response()->json([
            'message' => 'Đã xóa phiếu xuất kho nội bộ.',
        ]);
    }

    public function print(InternalMaterialIssue $issue)
    {
        return view('client.internal-material-issue-print', [
            'issue' => $issue->load('lines'),
        ]);
    }

    public function materialSuggestions(Request $request)
    {
        $keyword = trim((string) $request->query('keyword', ''));

        if ($keyword === '') {
            return response()->json(['data' => []]);
        }

        $data = DB::connection('sqlsrv')->table('TSoft_NhanTG_kt_new.dbo.CodeHanghoa as c')
            ->where(function ($query) use ($keyword) {
                $query->where('c.Ma_hh', 'like', '%' . $keyword . '%')
                    ->orWhere('c.Ten_hh', 'like', '%' . $keyword . '%');
            })
            ->select('c.Ma_hh', 'c.Ten_hh', 'c.Dvt')
            ->orderBy('c.Ma_hh')
            ->limit(20)
            ->get();

        return response()->json(['data' => $data]);
    }

    private function nextIssueCode(string $issueType = 'material')
    {
        $prefix = 'PXVT';
        if ($issueType === 'production') {
            $prefix = 'PXBTP';
        } elseif ($issueType === 'customer') {
            $prefix = 'PXTP';
        }

        return app(InternalDocumentNumber::class)
            ->next($prefix, 4);
    }

    private function findMatchingBtpOrderLine(array $line)
    {
        return app(InternalBtpOrderMatcher::class)->find($line);
    }

    private function createMissingBtpOrdersForIssue(array &$data, string $issueType): array
    {
        if ($issueType !== 'production') {
            return [];
        }

        $createdCodes = [];
        $issueDate = $data['issue_date'] ?? now()->format('Y-m-d');
        $receiverName = trim((string) ($data['receiver_name'] ?? ''));
        $department = trim((string) ($data['department'] ?? '')) ?: 'San xuat';
        $purpose = trim((string) ($data['purpose'] ?? '')) ?: 'Xuat BTP di san xuat';
        $headerNote = trim((string) ($data['note'] ?? ''));

        foreach ($data['lines'] as &$line) {
            $existingOrder = trim((string) ($line['production_order'] ?? ''));
            if ($existingOrder !== '') {
                continue;
            }

            $quantity = (float) ($line['quantity'] ?? 0);
            $internalCode = trim((string) ($line['internal_item_code'] ?? ''));
            if ($quantity <= 0 || $internalCode === '') {
                continue;
            }

            $matchedBtpLine = empty($data['force_new_btp_orders'])
                ? $this->findMatchingBtpOrderLine([
                    'ps_number' => $line['ps_number'] ?? ($line['purchase_order'] ?? ''),
                    'internal_item_code' => $internalCode,
                    'ma_hh' => $line['ma_hh'] ?? '',
                    'size' => $line['size'] ?? '',
                    'color' => $line['color'] ?? '',
                    'logo_color' => $line['logo_color'] ?? '',
                    'side' => $line['side'] ?? '',
                    'ordered_quantity' => $line['ordered_quantity'] ?? 0,
                ])
                : null;

            if ($matchedBtpLine) {
                $line['production_order'] = trim((string) $matchedBtpLine->btp_order_code);
                continue;
            }

            $btpOrderCode = app(InternalDocumentNumber::class)->nextYearly('BTP', 4);
            $customer = trim((string) ($line['customer'] ?? '')) ?: $receiverName;

            $order = InternalBtpProductionOrder::query()->create([
                'btp_order_code' => $btpOrderCode,
                'order_date' => $issueDate,
                'status' => 'draft',
                'receiver_name' => $receiverName,
                'customer' => mb_substr($customer, 0, 200),
                'department' => $department,
                'purpose' => $purpose,
                'note' => trim($headerNote . ' Tu tao tu dong xuat BTP.'),
            ]);

            $order->lines()->create([
                'ps_number' => trim((string) ($line['ps_number'] ?? ($line['purchase_order'] ?? ''))),
                'ma_hh' => strtoupper(trim((string) ($line['ma_hh'] ?? $internalCode))),
                'ten_hh' => mb_substr(trim((string) ($line['ten_hh'] ?? '')), 0, 255),
                'dvt' => trim((string) ($line['dvt'] ?? 'PCS')),
                'ordered_quantity' => $line['ordered_quantity'] ?? null,
                'quantity' => $quantity,
                'location_code' => strtoupper(trim((string) ($line['location_code'] ?? ''))),
                'internal_item_code' => $internalCode,
                'size' => mb_substr(trim((string) ($line['size'] ?? '')), 0, 100),
                'color' => mb_substr(trim((string) ($line['color'] ?? '')), 0, 100),
                'logo_color' => mb_substr(trim((string) ($line['logo_color'] ?? '')), 0, 100),
                'side' => mb_substr(trim((string) ($line['side'] ?? '')), 0, 100),
                'note' => mb_substr(trim((string) ($line['note'] ?? '')), 0, 500),
            ]);

            $line['production_order'] = $btpOrderCode;
            $createdCodes[] = $btpOrderCode;
        }
        unset($line);

        return $createdCodes;
    }

    private function markBtpOrdersIssuedFromIssue(InternalMaterialIssue $issue): void
    {
        $btpOrders = $issue->lines
            ->pluck('production_order')
            ->merge([$issue->production_order])
            ->filter()
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => preg_match('/^BTP\d{4}-\d{4}$/', $value))
            ->unique()
            ->values();

        foreach ($btpOrders as $btpOrderCode) {
            InternalBtpProductionOrderController::markIssued($btpOrderCode, $issue);
        }
    }

    private function productionTrackingKey($productionOrder, $size, $color): string
    {
        return implode('|', array_map(function ($value) {
            return mb_strtoupper(trim((string) $value));
        }, [$productionOrder, $size, $color]));
    }

    private function baseQuantityForLine(array $line): array
    {
        return app(InternalUnitConverter::class)->toBase(
            trim((string) ($line['internal_item_code'] ?? '')),
            (float) ($line['quantity'] ?? 0),
            trim((string) ($line['dvt'] ?? '')),
            trim((string) ($line['dvt'] ?? ''))
        );
    }

    private function assertSufficientStockForCustomerIssue(array $lines, string $warehouseCode): void
    {
        $warnings = $this->stockShortages($lines, $warehouseCode);
        if (!empty($warnings)) {
            throw ValidationException::withMessages([
                'stock' => array_column($warnings, 'message'),
            ]);
        }
    }

    private function stockShortages(array $lines, string $warehouseCode): array
    {
        $reservedByPackage = [];
        $warnings = [];

        foreach (array_values($lines) as $index => $line) {
            $requiredQuantity = (float) ($line['base_quantity'] ?? 0);
            if ($requiredQuantity <= 0) {
                $requiredQuantity = (float) $this->baseQuantityForLine($line)['quantity'];
            }

            if ($requiredQuantity <= 0) {
                continue;
            }

            $packages = $this->stockPackageQueryForLine($line, $warehouseCode)->get();
            $availableQuantity = 0.0;
            foreach ($packages as $package) {
                $availableQuantity += max(0, (float) $package->quantity - (float) ($reservedByPackage[$package->id] ?? 0));
            }

            if ($availableQuantity + 0.0001 < $requiredQuantity) {
                $code = trim((string) ($line['internal_item_code'] ?? ($line['ma_hh'] ?? '')));
                $reason = $this->negativeStockReason($line, $warehouseCode, $availableQuantity);
                $variant = implode('', array_filter([
                    trim((string) ($line['size'] ?? '')) !== '' ? ' / size ' . trim((string) $line['size']) : '',
                    trim((string) ($line['color'] ?? '')) !== '' ? ' / màu ' . trim((string) $line['color']) : '',
                    trim((string) ($line['side'] ?? '')) !== '' ? ' / mặt ' . trim((string) $line['side']) : '',
                ]));
                $shortage = $requiredQuantity - $availableQuantity;
                $message = sprintf(
                    'Dòng %d (%s%s): cần %s, tồn khả dụng %s, sẽ âm %s. %s',
                    $index + 1,
                    $code,
                    $variant,
                    $this->formatStockQuantity($requiredQuantity),
                    $this->formatStockQuantity($availableQuantity),
                    $this->formatStockQuantity($shortage),
                    $reason['label']
                );
                $warnings[] = [
                    'line_index' => $index,
                    'internal_item_code' => $code,
                    'size' => trim((string) ($line['size'] ?? '')),
                    'color' => trim((string) ($line['color'] ?? '')),
                    'side' => trim((string) ($line['side'] ?? '')),
                    'location_code' => trim((string) ($line['location_code'] ?? '')),
                    'required_quantity' => $requiredQuantity,
                    'available_quantity' => $availableQuantity,
                    'shortage_quantity' => $shortage,
                    'projected_quantity' => $availableQuantity - $requiredQuantity,
                    'reason' => $reason['code'],
                    'reason_label' => $reason['label'],
                    'message' => $message,
                ];
                continue;
            }

            $remaining = $requiredQuantity;
            foreach ($packages as $package) {
                if ($remaining <= 0.0001) {
                    break;
                }

                $packageAvailable = max(0, (float) $package->quantity - (float) ($reservedByPackage[$package->id] ?? 0));
                if ($packageAvailable <= 0) {
                    continue;
                }

                $takeQuantity = min($packageAvailable, $remaining);
                $reservedByPackage[$package->id] = (float) ($reservedByPackage[$package->id] ?? 0) + $takeQuantity;
                $remaining -= $takeQuantity;
            }
        }

        return $warnings;
    }

    private function negativeStockReason(array $line, string $warehouseCode, float $availableQuantity): array
    {
        $internalCode = trim((string) ($line['internal_item_code'] ?? ''));
        $maHh = strtoupper(trim((string) ($line['ma_hh'] ?? '')));
        $size = trim((string) ($line['size'] ?? ''));
        $color = trim((string) ($line['color'] ?? ''));
        $side = trim((string) ($line['side'] ?? ''));

        $negativeQuery = InternalInventoryCount::query()->where('counted_quantity', '<', 0);
        if ($internalCode !== '') {
            $negativeQuery->where('internal_item_code', $internalCode);
        } elseif ($maHh !== '') {
            $negativeQuery->where('ma_sp', $maHh);
        }
        foreach (['size' => $size, 'color' => $color, 'side' => $side] as $field => $value) {
            if ($value !== '') {
                $negativeQuery->where($field, $value);
            }
        }
        if ($warehouseCode !== '') {
            $negativeQuery->where('ma_ko', $warehouseCode);
        }
        if ($negativeQuery->exists()) {
            return [
                'code' => 'already_negative',
                'label' => 'Mã/biến thể này đã âm từ phiếu xuất trước; cần bổ sung hoặc sửa phiếu nhập.',
            ];
        }

        if ($availableQuantity > 0.0001) {
            return [
                'code' => 'partially_available',
                'label' => 'Có một phần tồn đúng biến thể nhưng không đủ số lượng cần xuất.',
            ];
        }

        $otherStockQuery = InventoryPackage::query()->where('quantity', '>', 0);
        if ($internalCode !== '') {
            $otherStockQuery->where('internal_item_code', $internalCode);
        } elseif ($maHh !== '') {
            $otherStockQuery->where('ma_sp', $maHh);
        }
        if ($warehouseCode !== '') {
            $otherStockQuery->where('ma_ko', $warehouseCode);
        }
        if ($otherStockQuery->exists()) {
            return [
                'code' => 'variant_or_location_mismatch',
                'label' => 'Có tồn cùng mã ở size, màu, mặt hoặc vị trí khác; kiểm tra lại biến thể trước khi xuất âm.',
            ];
        }

        $receiptQuery = InternalMaterialReceiptLine::query()
            ->join('internal_material_receipts as receipt', 'receipt.id', '=', 'internal_material_receipt_lines.receipt_id')
            ->where(function ($query) {
                $query->whereNull('receipt.status')->orWhere('receipt.status', '<>', 'cancelled');
            });
        if ($internalCode !== '') {
            $receiptQuery->whereRaw(
                'UPPER(TRIM(internal_material_receipt_lines.internal_item_code)) = ?',
                [mb_strtoupper($internalCode)]
            );
        } elseif ($maHh !== '') {
            $receiptQuery->where('internal_material_receipt_lines.ma_hh', $maHh);
        }
        foreach (['size' => $size, 'color' => $color, 'side' => $side] as $field => $value) {
            if ($value !== '') {
                $receiptQuery->where("internal_material_receipt_lines.{$field}", $value);
            }
        }
        if ($receiptQuery->exists()) {
            return [
                'code' => 'received_but_depleted',
                'label' => 'Đã có phiếu nhập phù hợp nhưng lượng đó đã được xuất hết hoặc đang lệch liên kết FIFO.',
            ];
        }

        return [
            'code' => 'not_received',
            'label' => 'Chưa thấy phiếu nhập phù hợp cho đúng mã/size/màu/mặt này.',
        ];
    }

    private function negativeStockWarningResponse(array $warnings)
    {
        return response()->json([
            'message' => 'Tồn không đủ. Kiểm tra cảnh báo hoặc xác nhận xuất âm.',
            'errors' => [
                'stock' => array_column($warnings, 'message'),
            ],
            'stock_warnings' => $warnings,
            'requires_negative_confirmation' => true,
        ], 422);
    }

    private function formatStockQuantity(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }

    private function decreaseInternalStock(array $line, string $warehouseCode, int $issueLineId, bool $allowNegative = true): void
    {
        $requestedQuantity = (float) ($line['base_quantity'] ?? 0);
        if ($requestedQuantity <= 0) {
            $requestedQuantity = (float) $this->baseQuantityForLine($line)['quantity'];
        }
        $remaining = $requestedQuantity;
        $packages = $this->stockPackageQueryForLine($line, $warehouseCode, true)->get();
        $available = (float) $packages->sum('quantity');

        foreach ($packages as $package) {
            if ($remaining <= 0) {
                break;
            }

            $takeQuantity = min((float) $package->quantity, $remaining);
            $remaining -= $takeQuantity;

            InternalMaterialIssueAllocation::query()->create([
                'issue_line_id' => $issueLineId,
                'inventory_package_id' => $package->id,
                'warehouse_location_id' => $package->warehouse_location_id,
                'inventory_count_id' => $package->inventory_count_id,
                'source_package_code' => $package->package_code,
                'location_code' => optional($package->location)->location_code,
                'ma_hh' => $package->ma_sp,
                'warehouse_code' => $package->ma_ko,
                'internal_item_code' => $package->internal_item_code,
                'size' => $package->size,
                'color' => $package->color,
                'side' => $package->side,
                'checked_at' => $package->checked_at,
                'quantity' => $takeQuantity,
                'note' => $package->note,
            ]);

            $package->quantity = (float) $package->quantity - $takeQuantity;

            $count = $package->inventory_count_id
                ? InternalInventoryCount::query()->lockForUpdate()->find($package->inventory_count_id)
                : null;

            if ($count) {
                $count->counted_quantity = max(0, (float) $count->counted_quantity - $takeQuantity);
                $hasOtherPackages = InventoryPackage::query()
                    ->where('inventory_count_id', $count->id)
                    ->where('id', '!=', $package->id)
                    ->exists();

                if ((float) $count->counted_quantity <= 0 && !$hasOtherPackages && (float) $package->quantity <= 0) {
                    $count->counted_quantity = 0;
                }
                $count->save();
            }

            if ((float) $package->quantity <= 0) {
                $package->quantity = 0;
                $package->save();
            } else {
                $package->save();
            }
        }

        if ($remaining > 0.0001 && !$allowNegative) {
            throw ValidationException::withMessages([
                'stock' => [
                    sprintf(
                        'Không đủ tồn để xuất thành phẩm %s. Cần %s, tồn khả dụng %s, thiếu %s.',
                        trim((string) ($line['internal_item_code'] ?? ($line['ma_hh'] ?? ''))),
                        $this->formatStockQuantity($requestedQuantity),
                        $this->formatStockQuantity($available),
                        $this->formatStockQuantity($remaining)
                    ),
                ],
            ]);
        }

        if ($remaining > 0.0001) {
            $this->createNegativeStockAllocation($line, $warehouseCode, $issueLineId, $remaining, $available, $requestedQuantity);
        }
    }

    private function stockPackageQueryForLine(array $line, string $warehouseCode, bool $lockForUpdate = false)
    {
        $maHh = mb_strtoupper(trim($line['ma_hh'] ?? ''));
        $locationCode = strtoupper(trim($line['location_code'] ?? ''));
        $internalCode = trim($line['internal_item_code'] ?? '');
        $size = trim($line['size'] ?? '');
        $color = trim($line['color'] ?? '');
        $side = trim($line['side'] ?? '');

        $query = InventoryPackage::query()
            ->where('quantity', '>', 0)
            ->orderBy('checked_at')
            ->orderBy('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        if ($maHh !== '' && ($internalCode === '' || $maHh !== mb_strtoupper($internalCode))) {
            $query->where('ma_sp', $maHh);
        }

        if ($warehouseCode !== '') {
            $query->where('ma_ko', $warehouseCode);
        }

        if ($locationCode !== '') {
            $query->whereHas('location', function ($q) use ($locationCode) {
                $q->where('location_code', $locationCode);
            });
        }

        if ($internalCode !== '') {
            $query->where('internal_item_code', $internalCode);
        }

        if ($size !== '') {
            $query->where('size', $size);
        }

        if ($color !== '') {
            $query->where('color', $color);
        }

        if ($side !== '') {
            $query->where('side', $side);
        }

        return $query;
    }

    private function createNegativeStockAllocation(array $line, string $warehouseCode, int $issueLineId, float $quantity, float $available, float $requestedQuantity): void
    {
        $locationCode = strtoupper(trim($line['location_code'] ?? '')) ?: 'CHUA-XEP';
        $location = WarehouseLocation::query()->firstOrCreate(
            ['location_code' => $locationCode],
            [
                'warehouse_code' => strtoupper(trim($warehouseCode)),
                'shelf_code' => 'CX',
                'tier' => 1,
                'grid_x' => 1,
                'grid_y' => 1,
                'grid_w' => 4,
                'grid_h' => 2,
                'location_name' => $locationCode === 'CHUA-XEP' ? 'Chua xep vi tri' : 'Vi tri xuat am',
            ]
        );

        $maHh = strtoupper(trim($line['ma_hh'] ?? ($line['internal_item_code'] ?? '')));
        $checkedAt = now()->format('Y-m-d');
        $note = trim((string) ($line['note'] ?? ''));
        $negativeNote = mb_substr(trim(implode(' - ', array_filter([
            'Xuat am ton noi bo',
            "Can {$requestedQuantity}, co {$available}, am {$quantity}",
            $note,
        ]))), 0, 500);

        $countAttributes = [
            'ma_sp' => $maHh,
            'ma_ko' => strtoupper(trim($warehouseCode)),
            'internal_item_code' => trim($line['internal_item_code'] ?? ''),
            'size' => trim($line['size'] ?? ''),
            'color' => trim($line['color'] ?? ''),
            'side' => trim($line['side'] ?? ''),
            'checked_at' => $checkedAt,
        ];

        $count = InternalInventoryCount::query()
            ->where($countAttributes)
            ->lockForUpdate()
            ->first();

        if (!$count) {
            $count = InternalInventoryCount::query()->create($countAttributes + [
                'counted_quantity' => 0,
                'note' => $negativeNote,
            ]);
        }

        $count->counted_quantity = (float) $count->counted_quantity - abs($quantity);
        $count->note = mb_substr(
            'Xuat am ton noi bo - Tong am ' . abs((float) $count->counted_quantity) . ' - Lan cuoi: ' . $negativeNote,
            0,
            500
        );
        $count->save();

        $package = InventoryPackage::query()->create([
            'package_code' => $this->nextPackageCode(),
            'warehouse_location_id' => $location->id,
            'inventory_count_id' => $count->id,
            'ma_sp' => $maHh,
            'ma_ko' => strtoupper(trim($warehouseCode)),
            'internal_item_code' => trim($line['internal_item_code'] ?? ''),
            'size' => trim($line['size'] ?? ''),
            'color' => trim($line['color'] ?? ''),
            'side' => trim($line['side'] ?? ''),
            'quantity' => -abs($quantity),
            'checked_at' => $checkedAt,
            'note' => $negativeNote,
        ]);

        InternalMaterialIssueAllocation::query()->create([
            'issue_line_id' => $issueLineId,
            'inventory_package_id' => $package->id,
            'warehouse_location_id' => $location->id,
            'inventory_count_id' => $count->id,
            'source_package_code' => $package->package_code,
            'location_code' => $location->location_code,
            'ma_hh' => $package->ma_sp,
            'warehouse_code' => $package->ma_ko,
            'internal_item_code' => $package->internal_item_code,
            'size' => $package->size,
            'color' => $package->color,
            'side' => $package->side,
            'checked_at' => $package->checked_at,
            'quantity' => -abs($quantity),
            'note' => $negativeNote,
        ]);

        $location->status = 'counting';
        $location->save();
    }

    private function increaseInternalStock(array $line, string $warehouseCode, $checkedAt): void
    {
        $locationCode = strtoupper(trim($line['location_code'] ?? '')) ?: 'CHUA-XEP';
        $location = WarehouseLocation::query()->firstOrCreate(
            ['location_code' => $locationCode],
            [
                'warehouse_code' => strtoupper(trim($warehouseCode)),
                'shelf_code' => 'CX',
                'tier' => 1,
                'grid_x' => 1,
                'grid_y' => 1,
                'grid_w' => 4,
                'grid_h' => 2,
                'location_name' => 'Chua xep vi tri',
            ]
        );

        $attributes = [
            'ma_sp' => strtoupper(trim($line['ma_hh'])),
            'ma_ko' => strtoupper(trim($warehouseCode)),
            'internal_item_code' => trim($line['internal_item_code'] ?? ''),
            'size' => trim($line['size'] ?? ''),
            'color' => trim($line['color'] ?? ''),
            'side' => trim($line['side'] ?? ''),
            'checked_at' => $checkedAt,
        ];

        $count = InternalInventoryCount::query()->firstOrCreate($attributes, [
            'counted_quantity' => 0,
            'note' => $line['note'] ?? null,
        ]);
        $count->counted_quantity = (float) $count->counted_quantity + (float) $line['quantity'];
        $count->save();

        InventoryPackage::query()->create(array_merge($attributes, [
            'package_code' => $this->nextPackageCode(),
            'warehouse_location_id' => $location->id,
            'inventory_count_id' => $count->id,
            'quantity' => $line['quantity'],
            'note' => $line['note'] ?? null,
        ]));

        $location->status = 'counting';
        $location->save();
    }

    private function nextPackageCode()
    {
        return app(InternalDocumentNumber::class)->next('PK', 5);
    }

    private function restoreIssueLineStock(InternalMaterialIssue $issue, $line): void
    {
        if ($line->allocations->isNotEmpty()) {
            foreach ($line->allocations as $allocation) {
                $this->restoreAllocation($allocation);
            }

            return;
        }

        $this->increaseInternalStock([
            'ma_hh' => $line->ma_hh,
            'dvt' => $line->base_dvt ?: $line->dvt,
            'quantity' => $line->base_quantity ?: $line->quantity,
            'location_code' => $line->location_code,
            'internal_item_code' => $line->internal_item_code,
            'size' => $line->size,
            'color' => $line->color,
            'side' => $line->side,
            'note' => 'Hoan phieu xuat ' . $issue->issue_code,
        ], $issue->warehouse_code, $issue->issue_date);
    }

    private function restoreAllocation(InternalMaterialIssueAllocation $allocation): void
    {
        $restoreQuantity = (float) $allocation->quantity < 0
            ? abs((float) $allocation->quantity)
            : (float) $allocation->quantity;

        $location = WarehouseLocation::query()->find($allocation->warehouse_location_id);

        if (!$location && $allocation->location_code) {
            $location = WarehouseLocation::query()->firstOrCreate(
                ['location_code' => $allocation->location_code],
                [
                    'warehouse_code' => $allocation->warehouse_code,
                    'shelf_code' => 'CX',
                    'tier' => 1,
                    'grid_x' => 1,
                    'grid_y' => 1,
                    'grid_w' => 4,
                    'grid_h' => 2,
                    'location_name' => 'Vi tri khoi phuc',
                ]
            );
        }

        if (!$location) {
            $location = WarehouseLocation::query()->firstOrCreate(
                ['location_code' => 'CHUA-XEP'],
                [
                    'warehouse_code' => $allocation->warehouse_code,
                    'shelf_code' => 'CX',
                    'tier' => 1,
                    'grid_x' => 1,
                    'grid_y' => 1,
                    'grid_w' => 4,
                    'grid_h' => 2,
                    'location_name' => 'Chua xep vi tri',
                ]
            );
        }

        $count = InternalInventoryCount::query()->firstOrCreate(
            [
                'ma_sp' => $allocation->ma_hh,
                'ma_ko' => $allocation->warehouse_code ?: '',
                'internal_item_code' => $allocation->internal_item_code ?: '',
                'size' => $allocation->size ?: '',
                'color' => $allocation->color ?: '',
                'side' => $allocation->side ?: '',
                'checked_at' => $allocation->checked_at,
            ],
            [
                'counted_quantity' => 0,
                'note' => $allocation->note,
            ]
        );
        $count->counted_quantity = (float) $count->counted_quantity + $restoreQuantity;
        $count->save();

        $package = InventoryPackage::query()
            ->where('package_code', $allocation->source_package_code)
            ->lockForUpdate()
            ->first();

        if ($package) {
            $package->quantity = (float) $package->quantity + $restoreQuantity;
            $package->warehouse_location_id = $location->id;
            $package->inventory_count_id = $count->id;
            $package->save();
        } else {
            $package = InventoryPackage::query()->create([
                'package_code' => $allocation->source_package_code,
                'warehouse_location_id' => $location->id,
                'inventory_count_id' => $count->id,
                'ma_sp' => $allocation->ma_hh,
                'ma_ko' => $allocation->warehouse_code ?: '',
                'internal_item_code' => $allocation->internal_item_code ?: '',
                'size' => $allocation->size ?: '',
                'color' => $allocation->color ?: '',
                'side' => $allocation->side ?: '',
                'quantity' => $restoreQuantity,
                'checked_at' => $allocation->checked_at,
                'note' => $allocation->note,
            ]);
        }

        $allocation->inventory_package_id = $package->id;
        $allocation->inventory_count_id = $count->id;
        $allocation->warehouse_location_id = $location->id;
        $allocation->save();
        $this->relinkReceiptLinesForRestoredPackage($allocation, $package);

        $location->status = 'counting';
        $location->save();
    }

    private function relinkReceiptLinesForRestoredPackage(InternalMaterialIssueAllocation $allocation, InventoryPackage $package): void
    {
        if ($allocation->inventory_package_id) {
            InternalMaterialReceiptLine::query()
                ->where('inventory_package_id', $allocation->inventory_package_id)
                ->update(['inventory_package_id' => $package->id]);
        }

        InternalMaterialReceiptLine::query()
            ->whereNull('inventory_package_id')
            ->where('ma_hh', $allocation->ma_hh)
            ->where('internal_item_code', $allocation->internal_item_code ?: '')
            ->where('size', $allocation->size ?: '')
            ->where('color', $allocation->color ?: '')
            ->where('side', $allocation->side ?: '')
            ->where('location_code', $allocation->location_code ?: '')
            ->whereRaw('ABS(COALESCE(quantity, 0) - ?) < 0.0001', [(float) $allocation->quantity])
            ->update(['inventory_package_id' => $package->id]);
    }
}
