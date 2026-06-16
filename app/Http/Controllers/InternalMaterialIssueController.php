<?php

namespace App\Http\Controllers;

use App\Models\InternalInventoryCount;
use App\Models\InternalMaterialIssue;
use App\Models\InternalMaterialIssueAllocation;
use App\Models\InternalProductionOrder;
use App\Models\InventoryPackage;
use App\Models\WarehouseLocation;
use App\Services\InternalAudit;
use App\Services\InternalDocumentNumber;
use App\Services\GoogleSheetInternalCatalog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InternalMaterialIssueController extends Controller
{
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

        $issueLines = DB::connection('internal')->table('internal_material_issue_lines as l')
            ->join('internal_material_issues as i', 'i.id', '=', 'l.issue_id')
            ->where('i.issue_code', 'like', 'PXBTP-%')
            ->select(
                'i.id as issue_id',
                'i.issue_code',
                'i.issue_date',
                'i.warehouse_code',
                'i.receiver_name',
                'i.department',
                DB::raw("COALESCE(NULLIF(l.production_order, ''), NULLIF(i.production_order, '')) as production_order"),
                'l.purchase_order',
                'l.customer',
                'l.ma_hh',
                'l.internal_item_code',
                'l.size',
                'l.color',
                'l.dvt',
                'l.quantity'
            )
            ->orderBy('i.issue_date')
            ->orderBy('i.id')
            ->get();

        $groups = [];
        foreach ($issueLines as $line) {
            $order = trim((string) $line->production_order);
            if ($order === '' || strpos($order, ',') !== false) {
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
                    'issue_codes' => [],
                ];
            }

            $groups[$key]['issued_quantity'] += (float) $line->quantity;
            $groups[$key]['first_issue_date'] = min($groups[$key]['first_issue_date'], (string) $line->issue_date);
            $groups[$key]['last_issue_date'] = max($groups[$key]['last_issue_date'], (string) $line->issue_date);
            $groups[$key]['issue_codes'][$line->issue_code] = true;
        }

        $orderNames = collect($groups)->pluck('production_order')->unique()->values();
        if ($orderNames->isNotEmpty()) {
            $receiptLines = DB::connection('internal')->table('internal_material_receipt_lines as l')
                ->join('internal_material_receipts as r', 'r.id', '=', 'l.receipt_id')
                ->where('r.source', 'Phieu nhap thanh pham')
                ->whereIn(DB::raw("COALESCE(NULLIF(l.production_order, ''), l.note)"), $orderNames->all())
                ->select(
                    DB::raw("COALESCE(NULLIF(l.production_order, ''), l.note) as production_order"),
                    'l.size',
                    'l.color',
                    DB::raw('SUM(l.quantity) as quantity')
                )
                ->groupBy(DB::raw("COALESCE(NULLIF(l.production_order, ''), l.note)"), 'l.size', 'l.color')
                ->get();

            foreach ($receiptLines as $line) {
                $key = $this->productionTrackingKey($line->production_order, $line->size, $line->color);
                if (isset($groups[$key])) {
                    $groups[$key]['returned_quantity'] += (float) $line->quantity;
                }
            }
        }

        $today = now()->startOfDay();
        $rows = collect($groups)->map(function ($row) use ($today) {
            $row['issue_codes'] = array_keys($row['issue_codes']);
            $row['outstanding_quantity'] = max(0, $row['issued_quantity'] - $row['returned_quantity']);
            $row['progress_percent'] = $row['issued_quantity'] > 0
                ? min(100, round(($row['returned_quantity'] / $row['issued_quantity']) * 100, 1))
                : 0;
            $row['age_days'] = Carbon::parse($row['first_issue_date'])->startOfDay()->diffInDays($today);
            $row['aging_status'] = $row['age_days'] >= 8 ? 'overdue' : ($row['age_days'] >= 4 ? 'warning' : 'normal');

            return $row;
        })->filter(function ($row) use ($keyword, $aging) {
            if ($row['outstanding_quantity'] <= 0) {
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
                $row['purchase_order'],
                $row['customer'],
                $row['ma_hh'],
                $row['internal_item_code'],
                $row['size'],
                $row['color'],
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
            ->withCount('lines')
            ->withSum('lines', 'quantity')
            ->orderByDesc('issue_date')
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
                        $lineQuery->where('ma_hh', 'like', '%' . $keyword . '%')
                            ->orWhere('ten_hh', 'like', '%' . $keyword . '%')
                            ->orWhere('location_code', 'like', '%' . $keyword . '%');
                    });
            });
        }

        $data = $query->limit(200)->get();

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
            'issue_type' => 'nullable|in:material,production',
            'issue_date' => 'required|date',
            'warehouse_code' => 'nullable|string|max:50',
            'receiver_name' => 'nullable|string|max:150',
            'department' => 'nullable|string|max:150',
            'production_order' => 'nullable|string|max:100',
            'purpose' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
            'lines' => 'required|array|min:1',
            'lines.*.ma_hh' => 'required|string|max:100',
            'lines.*.ten_hh' => 'nullable|string|max:255',
            'lines.*.dvt' => 'nullable|string|max:50',
            'lines.*.quantity' => 'required|numeric|min:0.001',
            'lines.*.location_code' => 'nullable|string|max:100',
            'lines.*.internal_item_code' => 'nullable|string|max:100',
            'lines.*.size' => 'nullable|string|max:100',
            'lines.*.color' => 'nullable|string|max:100',
            'lines.*.note' => 'nullable|string|max:500',
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

        $issueType = $data['issue_type'] ?? 'material';

        $issue = DB::connection('internal')->transaction(function () use ($data, $issueType) {
            $issue = InternalMaterialIssue::query()->create([
                'issue_code' => $this->nextIssueCode($issueType),
                'issue_date' => $data['issue_date'],
                'warehouse_code' => strtoupper(trim($data['warehouse_code'] ?? '')),
                'receiver_name' => trim($data['receiver_name'] ?? ''),
                'department' => trim($data['department'] ?? '') ?: ($issueType === 'production' ? 'Sản xuất' : ''),
                'production_order' => trim($data['production_order'] ?? ''),
                'purpose' => trim($data['purpose'] ?? '') ?: ($issueType === 'production' ? 'Xuất BTP đi sản xuất' : 'Xuất vật tư'),
                'status' => 'posted',
                'note' => trim($data['note'] ?? ''),
            ]);

            foreach ($data['lines'] as $line) {
                $issueLine = $issue->lines()->create([
                    'production_order_id' => $line['production_order_id'] ?? null,
                    'production_order' => trim($line['production_order'] ?? ''),
                    'purchase_order' => trim($line['purchase_order'] ?? ''),
                    'customer' => trim($line['customer'] ?? ''),
                    'ma_hh' => strtoupper(trim($line['ma_hh'])),
                    'ten_hh' => trim($line['ten_hh'] ?? ''),
                    'dvt' => trim($line['dvt'] ?? ''),
                    'ordered_quantity' => $line['ordered_quantity'] ?? null,
                    'quantity' => $line['quantity'],
                    'location_code' => strtoupper(trim($line['location_code'] ?? '')),
                    'internal_item_code' => trim($line['internal_item_code'] ?? ''),
                    'size' => trim($line['size'] ?? ''),
                    'color' => trim($line['color'] ?? ''),
                    'note' => trim($line['note'] ?? ''),
                ]);

                $this->decreaseInternalStock(
                    $line,
                    strtoupper(trim($data['warehouse_code'] ?? '')),
                    $issueLine->id
                );
            }

            return $issue->load('lines');
        });

        app(InternalAudit::class)->model('issue.created', $issue, [
            'line_count' => $issue->lines->count(),
            'total_quantity' => (float) $issue->lines->sum('quantity'),
        ], $request);

        return response()->json([
            'message' => $issueType === 'production'
                ? 'Đã tạo phiếu xuất BTP đi sản xuất.'
                : 'Đã tạo phiếu xuất vật tư nội bộ.',
            'data' => $issue,
            'print_url' => url('/client/xuat-vat-tu-noi-bo/' . $issue->id . '/in'),
        ]);
    }

    public function productionOrderLines(Request $request)
    {
        $productionOrder = trim((string) $request->query('production_order', ''));
        if ($productionOrder === '') {
            return response()->json(['data' => []]);
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
                    $warnings[] = 'Thiếu mã vật tư nội bộ.';
                } elseif (!$catalogItem) {
                    $warnings[] = 'Mã nội bộ không có trong sheet DANH MỤC.';
                }
                if ($locationCode === '') {
                    $warnings[] = 'Chưa có vị trí, khi nhập sẽ đưa vào CHUA-XEP.';
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
            $available = (float) $packages->sum('quantity');
            if ($quantity <= 0) {
                $warnings[] = 'Số lượng xuất chưa hợp lệ.';
            } elseif (!$isUnipaxReceipt && $available > 0 && $quantity > $available + 0.0001) {
                $warnings[] = 'Số lượng xuất lớn hơn tồn phù hợp.';
            }

            return [
                'source_row' => $index + 1,
                'issue_date' => $line['issue_date'] ?? null,
                'customer' => trim($data['customer']),
                'production_order_id' => $productionOrder->id ?? null,
                'production_order' => $productionOrderCode,
                'purchase_order' => $psNumber ?: ($productionOrder->purchase_order ?? ''),
                'ma_hh' => $maHh ?: ($accountingCodes->count() === 1 ? $accountingCodes->first() : ''),
                'ten_hh' => $catalogItem['name'] ?? ($productionOrder->description ?? ''),
                'internal_item_code' => $internalCode,
                'size' => $size,
                'color' => $color,
                'dvt' => trim((string) ($line['dvt'] ?? '')) ?: ($catalogItem['unit'] ?? ''),
                'ordered_quantity' => (float) ($line['ordered_quantity'] ?? 0),
                'quantity' => $quantity,
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

    public function destroy(InternalMaterialIssue $issue)
    {
        $auditPayload = [
            'issue_code' => $issue->issue_code,
            'issue_date' => optional($issue->issue_date)->format('Y-m-d'),
        ];

        DB::connection('internal')->transaction(function () use ($issue) {
            $issue->load('lines.allocations');

            foreach ($issue->lines as $line) {
                if ($line->allocations->isNotEmpty()) {
                    foreach ($line->allocations as $allocation) {
                        $this->restoreAllocation($allocation);
                    }

                    continue;
                }

                $this->increaseInternalStock([
                    'ma_hh' => $line->ma_hh,
                    'quantity' => $line->quantity,
                    'location_code' => $line->location_code,
                    'internal_item_code' => $line->internal_item_code,
                    'size' => $line->size,
                    'color' => $line->color,
                    'note' => 'Hoan phieu xuat ' . $issue->issue_code,
                ], $issue->warehouse_code, $issue->issue_date);
            }

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
            'message' => 'Đã xóa phiếu xuất vật tư nội bộ.',
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
        return app(InternalDocumentNumber::class)
            ->next($issueType === 'production' ? 'PXBTP' : 'PXVT', 4);
    }

    private function productionTrackingKey($productionOrder, $size, $color): string
    {
        return implode('|', array_map(function ($value) {
            return mb_strtoupper(trim((string) $value));
        }, [$productionOrder, $size, $color]));
    }

    private function decreaseInternalStock(array $line, string $warehouseCode, int $issueLineId): void
    {
        $requestedQuantity = (float) $line['quantity'];
        $remaining = $requestedQuantity;
        $maHh = strtoupper(trim($line['ma_hh']));
        $locationCode = strtoupper(trim($line['location_code'] ?? ''));
        $internalCode = trim($line['internal_item_code'] ?? '');
        $size = trim($line['size'] ?? '');
        $color = trim($line['color'] ?? '');

        $query = InventoryPackage::query()
            ->where('ma_sp', $maHh)
            ->where('quantity', '>', 0)
            ->orderBy('checked_at')
            ->orderBy('id')
            ->lockForUpdate();

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

        $packages = $query->get();
        $available = (float) $packages->sum('quantity');

        if ($available + 0.0001 < $requestedQuantity) {
            throw ValidationException::withMessages([
                'lines' => "Ton noi bo khong du cho ma {$maHh}. Can {$requestedQuantity}, hien co {$available}.",
            ]);
        }

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
                    $count->delete();
                } else {
                    $count->save();
                }
            }

            if ((float) $package->quantity <= 0) {
                $location = $package->warehouse_location_id
                    ? WarehouseLocation::query()->find($package->warehouse_location_id)
                    : null;

                $package->delete();

                if ($location && !InventoryPackage::query()->where('warehouse_location_id', $location->id)->exists()) {
                    $location->status = 'pending';
                    $location->save();
                }
            } else {
                $package->save();
            }
        }
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
            'side' => '',
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

    private function restoreAllocation(InternalMaterialIssueAllocation $allocation): void
    {
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
        $count->counted_quantity = (float) $count->counted_quantity + (float) $allocation->quantity;
        $count->save();

        $package = InventoryPackage::query()
            ->where('package_code', $allocation->source_package_code)
            ->lockForUpdate()
            ->first();

        if ($package) {
            $package->quantity = (float) $package->quantity + (float) $allocation->quantity;
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
                'quantity' => $allocation->quantity,
                'checked_at' => $allocation->checked_at,
                'note' => $allocation->note,
            ]);
        }

        $allocation->inventory_package_id = $package->id;
        $allocation->inventory_count_id = $count->id;
        $allocation->warehouse_location_id = $location->id;
        $allocation->save();

        $location->status = 'counting';
        $location->save();
    }
}
