<?php

namespace App\Http\Controllers;

use App\Models\InternalBtpProductionOrder;
use App\Models\InternalMaterialIssue;
use App\Services\InternalAudit;
use App\Services\InternalCatalogValidator;
use App\Services\InternalDocumentNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InternalBtpProductionOrderController extends Controller
{
    public function index()
    {
        return view('client.internal-btp-production-orders');
    }

    public function printLabels(Request $request)
    {
        $data = $request->validate([
            'ids' => 'nullable|string|max:2000',
            'codes' => 'nullable|string|max:5000',
        ]);

        $ids = collect(explode(',', (string) ($data['ids'] ?? '')))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->unique()
            ->values();

        $codes = collect(explode(',', (string) ($data['codes'] ?? '')))
            ->map(fn ($code) => strtoupper(trim($code)))
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty() && $codes->isEmpty()) {
            return view('client.labels.btp-orders', [
                'orders' => collect(),
            ]);
        }

        $orders = InternalBtpProductionOrder::query()
            ->with('lines')
            ->when($ids->isNotEmpty(), fn ($query) => $query->whereIn('id', $ids))
            ->when($ids->isEmpty() && $codes->isNotEmpty(), fn ($query) => $query->whereIn('btp_order_code', $codes))
            ->orderBy('order_date')
            ->orderBy('id')
            ->get();

        return view('client.labels.btp-orders', [
            'orders' => $orders,
        ]);
    }

    public function data(Request $request)
    {
        $query = InternalBtpProductionOrder::query()
            ->with('lines')
            ->withCount('lines')
            ->withSum('lines', 'quantity')
            ->orderByDesc('order_date')
            ->orderByDesc('id');

        $keyword = trim((string) $request->query('keyword', ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('btp_order_code', 'like', '%' . $keyword . '%')
                    ->orWhere('issue_code', 'like', '%' . $keyword . '%')
                    ->orWhere('customer', 'like', '%' . $keyword . '%')
                    ->orWhere('receiver_name', 'like', '%' . $keyword . '%')
                    ->orWhere('department', 'like', '%' . $keyword . '%')
                    ->orWhereHas('lines', function ($lineQuery) use ($keyword) {
                        $lineQuery->where('ma_hh', 'like', '%' . $keyword . '%')
                            ->orWhere('ten_hh', 'like', '%' . $keyword . '%')
                            ->orWhere('internal_item_code', 'like', '%' . $keyword . '%')
                            ->orWhere('size', 'like', '%' . $keyword . '%')
                            ->orWhere('color', 'like', '%' . $keyword . '%')
                            ->orWhere('side', 'like', '%' . $keyword . '%')
                            ->orWhere('location_code', 'like', '%' . $keyword . '%');
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('customer')) {
            $query->where('customer', 'like', '%' . trim((string) $request->query('customer')) . '%');
        }

        if ($request->filled('from_date')) {
            $query->whereDate('order_date', '>=', $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('order_date', '<=', $request->query('to_date'));
        }

        $summaryQuery = clone $query;
        $rows = $query->limit(min(max((int) $request->query('limit', 200), 1), 1000))->get();

        return response()->json([
            'data' => $rows,
            'customers' => InternalBtpProductionOrder::query()
                ->whereNotNull('customer')
                ->where('customer', '<>', '')
                ->distinct()
                ->orderBy('customer')
                ->limit(200)
                ->pluck('customer')
                ->values(),
            'summary' => [
                'order_count' => (clone $summaryQuery)->count(),
                'line_count' => (float) $rows->sum('lines_count'),
                'total_quantity' => (float) $rows->sum('lines_sum_quantity'),
                'draft_count' => (clone $summaryQuery)->where('status', 'draft')->count(),
                'issued_count' => (clone $summaryQuery)->where('status', 'issued')->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedPayload($request);
        $this->ensureLineIdentity($data['lines']);

        $order = DB::connection('internal')->transaction(function () use ($data) {
            $order = $this->createHeader($data);
            foreach ($data['lines'] as $line) {
                $order->lines()->create($this->normalizeLine($line));
            }

            return $order->load('lines');
        });

        app(InternalAudit::class)->model('btp_order.created', $order, [
            'line_count' => $order->lines->count(),
            'total_quantity' => (float) $order->lines->sum('quantity'),
        ], $request);

        return response()->json([
            'message' => 'Đã tạo lệnh BTP ' . $order->btp_order_code . '.',
            'data' => $order,
        ]);
    }

    public function storeBatch(Request $request)
    {
        $data = $this->validatedPayload($request);
        $this->ensureLineIdentity($data['lines']);

        $orders = DB::connection('internal')->transaction(function () use ($data) {
            $created = collect();

            foreach ($data['lines'] as $line) {
                $lineHeader = $data;
                if (trim((string) ($line['customer'] ?? '')) !== '') {
                    $lineHeader['customer'] = trim((string) $line['customer']);
                }

                $order = $this->createHeader($lineHeader);
                $order->lines()->create($this->normalizeLine($line));
                $created->push($order->load('lines'));
            }

            return $created;
        });

        foreach ($orders as $order) {
            app(InternalAudit::class)->model('btp_order.created', $order, [
                'line_count' => 1,
                'total_quantity' => (float) $order->lines->sum('quantity'),
                'batch_created' => true,
            ], $request);
        }

        return response()->json([
            'message' => 'Đã tạo ' . $orders->count() . ' lệnh BTP.',
            'data' => $orders->values(),
            'codes' => $orders->pluck('btp_order_code')->values(),
        ]);
    }

    public function show(InternalBtpProductionOrder $btpOrder)
    {
        return response()->json([
            'data' => $btpOrder->load('lines'),
        ]);
    }

    public function update(Request $request, InternalBtpProductionOrder $btpOrder)
    {
        if ($btpOrder->status !== 'draft' || $btpOrder->issue_id) {
            return response()->json([
                'message' => 'Lệnh đã xuất SX, không sửa trực tiếp. Xóa phiếu xuất liên quan trước.',
            ], 422);
        }

        $data = $this->validatedPayload($request);
        $this->ensureLineIdentity($data['lines']);

        DB::connection('internal')->transaction(function () use ($btpOrder, $data) {
            $btpOrder->update([
                'order_date' => $data['order_date'] ?? now()->format('Y-m-d'),
                'receiver_name' => trim($data['receiver_name'] ?? ''),
                'customer' => trim($data['customer'] ?? ''),
                'department' => trim($data['department'] ?? '') ?: 'Sản xuất',
                'purpose' => trim($data['purpose'] ?? '') ?: 'Xuất BTP đi sản xuất',
                'note' => trim($data['note'] ?? ''),
            ]);

            $btpOrder->lines()->delete();
            foreach ($data['lines'] as $line) {
                $btpOrder->lines()->create($this->normalizeLine($line));
            }
        });

        $btpOrder->refresh()->load('lines');
        app(InternalAudit::class)->model('btp_order.updated', $btpOrder, [
            'line_count' => $btpOrder->lines->count(),
            'total_quantity' => (float) $btpOrder->lines->sum('quantity'),
        ], $request);

        return response()->json([
            'message' => 'Đã cập nhật lệnh BTP ' . $btpOrder->btp_order_code . '.',
            'data' => $btpOrder,
        ]);
    }

    public function destroy(Request $request, InternalBtpProductionOrder $btpOrder)
    {
        if ($btpOrder->status !== 'draft' || $btpOrder->issue_id) {
            return response()->json([
                'message' => 'Lệnh đã xuất SX, không xóa trực tiếp. Xóa phiếu xuất liên quan trước.',
            ], 422);
        }

        $code = $btpOrder->btp_order_code;
        app(InternalAudit::class)->model('btp_order.deleted', $btpOrder, [
            'btp_order_code' => $code,
        ], $request);
        $btpOrder->delete();

        return response()->json([
            'message' => 'Đã xóa lệnh BTP ' . $code . '.',
        ]);
    }

    public function bulkDestroy(Request $request)
    {
        $ids = collect($request->input('order_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $codes = collect($request->input('order_codes', []))
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty() && $codes->isEmpty()) {
            return response()->json(['message' => 'Chua chon lenh BTP de xoa.'], 422);
        }

        $orders = InternalBtpProductionOrder::query()
            ->where(function ($query) use ($ids, $codes) {
                if ($ids->isNotEmpty()) {
                    $query->whereIn('id', $ids->all());
                }
                if ($codes->isNotEmpty()) {
                    $query->orWhereIn('btp_order_code', $codes->all());
                }
            })
            ->get();

        if ($orders->isEmpty()) {
            return response()->json(['message' => 'Khong tim thay lenh BTP can xoa.'], 422);
        }

        $selectedIds = $orders->pluck('id')->unique()->values();
        $issueIds = $orders->pluck('issue_id')->filter()->unique()->values();
        foreach ($issueIds as $issueId) {
            $allIssueOrderIds = InternalBtpProductionOrder::query()
                ->where('issue_id', $issueId)
                ->pluck('id')
                ->unique()
                ->values();

            if ($allIssueOrderIds->diff($selectedIds)->isNotEmpty()) {
                $issueCode = InternalBtpProductionOrder::query()
                    ->where('issue_id', $issueId)
                    ->value('issue_code');

                return response()->json([
                    'message' => 'Phieu xuat ' . ($issueCode ?: ('ID ' . $issueId)) . ' gom nhieu lenh BTP. Hay chon tat ca lenh trong phieu do roi xoa lai de khong lech ton.',
                ], 422);
            }
        }

        $deletedIssues = [];
        DB::connection('internal')->transaction(function () use ($orders, $issueIds, $request, &$deletedIssues) {
            foreach ($issueIds as $issueId) {
                $issue = InternalMaterialIssue::query()->find($issueId);
                if ($issue) {
                    $deletedIssues[] = $issue->issue_code;
                    app(InternalMaterialIssueController::class)->destroy($issue);
                }
            }

            foreach ($orders as $order) {
                app(InternalAudit::class)->model('btp_order.deleted', $order, [
                    'btp_order_code' => $order->btp_order_code,
                    'bulk_deleted' => true,
                ], $request);
                $order->delete();
            }
        });

        return response()->json([
            'message' => 'Da xoa ' . $orders->count() . ' lenh BTP' . (count($deletedIssues) ? ' va hoan/xoa ' . count($deletedIssues) . ' phieu xuat lien quan.' : '.'),
            'deleted_issues' => $deletedIssues,
        ]);
    }

    public function createIssueFromOrders(Request $request)
    {
        $payload = $request->all();
        if (!isset($payload['order_ids']) && !isset($payload['order_codes'])) {
            $raw = trim((string) $request->getContent());
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $payload = array_replace($decoded, $payload);
                }
            }
        }

        $payload['order_ids'] = collect($payload['order_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $payload['order_codes'] = collect($payload['order_codes'] ?? [])
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($payload['order_ids']) && empty($payload['order_codes'])) {
            abort(response()->json([
                'message' => 'Chua co lenh BTP hop le de tao phieu xuat. Hay tao lenh BTP truoc, hoac tai lai trang roi thu lai.',
                'errors' => [
                    'order_ids' => ['Chua co lenh BTP hop le.'],
                ],
            ], 422));
        }

        $request->replace($payload);

        $data = $request->validate([
            'order_ids' => 'nullable|array|max:50',
            'order_ids.*' => 'integer',
            'order_codes' => 'nullable|array|max:50',
            'order_codes.*' => 'string|max:50',
            'issue_date' => 'nullable|date',
            'receiver_name' => 'nullable|string|max:150',
            'department' => 'nullable|string|max:150',
            'purpose' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
        ]);

        $orderIds = array_values(array_unique($data['order_ids'] ?? []));
        $orderCodes = array_values(array_unique($data['order_codes'] ?? []));
        $orders = InternalBtpProductionOrder::query()
            ->with('lines')
            ->where(function ($query) use ($orderIds, $orderCodes) {
                if (!empty($orderIds)) {
                    $query->whereIn('id', $orderIds);
                }
                if (!empty($orderCodes)) {
                    $query->orWhereIn('btp_order_code', $orderCodes);
                }
            })
            ->orderBy('order_date')
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            throw ValidationException::withMessages([
                'order_ids' => 'Khong tim thay lenh BTP vua tao.',
            ]);
        }

        $lockedOrder = $orders->first(fn ($order) => $order->status !== 'draft' || $order->issue_id);
        if ($lockedOrder) {
            throw ValidationException::withMessages([
                'order_ids' => 'Lenh ' . $lockedOrder->btp_order_code . ' da xuat hoac khong con trang thai moi tao.',
            ]);
        }

        $lines = $orders->flatMap(function (InternalBtpProductionOrder $order) {
            return $order->lines->map(function ($line) use ($order) {
                return [
                    'production_order_id' => null,
                    'production_order' => $order->btp_order_code,
                    'purchase_order' => trim((string) $line->ps_number),
                    'customer' => trim((string) $order->customer),
                    'ma_hh' => strtoupper(trim((string) ($line->ma_hh ?: $line->internal_item_code))),
                    'ten_hh' => mb_substr(trim((string) $line->ten_hh), 0, 255),
                    'dvt' => trim((string) $line->dvt),
                    'ordered_quantity' => $line->ordered_quantity,
                    'quantity' => (float) $line->quantity,
                    'location_code' => strtoupper(trim((string) $line->location_code)),
                    'internal_item_code' => trim((string) $line->internal_item_code),
                    'size' => mb_substr(trim((string) $line->size), 0, 100),
                    'color' => mb_substr(trim((string) $line->color), 0, 100),
                    'logo_color' => mb_substr(trim((string) $line->logo_color), 0, 100),
                    'side' => mb_substr(trim((string) $line->side), 0, 100),
                    'note' => mb_substr(trim((string) $line->note), 0, 500),
                ];
            });
        })->values();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'order_ids' => 'Cac lenh BTP da chon chua co dong hang.',
            ]);
        }

        $issuePayload = [
            'issue_type' => 'production',
            'issue_date' => $data['issue_date'] ?? now()->format('Y-m-d'),
            'warehouse_code' => '',
            'receiver_name' => trim($data['receiver_name'] ?? '') ?: 'San xuat',
            'department' => trim($data['department'] ?? '') ?: 'San xuat',
            'production_order' => '',
            'purpose' => trim($data['purpose'] ?? '') ?: 'Xuat BTP di san xuat',
            'note' => trim($data['note'] ?? '') ?: ('Xuat tu ' . $orders->count() . ' lenh BTP'),
            'lines' => $lines->all(),
        ];

        $issueRequest = Request::create('/api/xuat-vat-tu-noi-bo', 'POST', $issuePayload);
        $issueRequest->headers->set('Accept', 'application/json');
        $issueRequest->headers->set('X-CSRF-TOKEN', (string) $request->header('X-CSRF-TOKEN', ''));
        $issueRequest->setUserResolver($request->getUserResolver());
        $issueRequest->setRouteResolver($request->getRouteResolver());

        return app(InternalMaterialIssueController::class)->store($issueRequest);
    }

    public static function markIssued(string $btpOrderCode, $issue): void
    {
        if (trim($btpOrderCode) === '') {
            return;
        }

        $order = InternalBtpProductionOrder::query()
            ->where('btp_order_code', trim($btpOrderCode))
            ->first();

        if (!$order) {
            return;
        }

        $order->update([
            'status' => 'issued',
            'issue_id' => $issue->id,
            'issue_code' => $issue->issue_code,
            'issued_at' => now(),
        ]);

        $issue->loadMissing('lines');
        foreach ($issue->lines as $issueLine) {
            $order->lines()
                ->whereNull('source_issue_line_id')
                ->where('ma_hh', $issueLine->ma_hh)
                ->where('internal_item_code', (string) $issueLine->internal_item_code)
                ->where('size', (string) $issueLine->size)
                ->where('color', (string) $issueLine->color)
                ->where('side', (string) $issueLine->side)
                ->limit(1)
                ->update(['source_issue_line_id' => $issueLine->id]);
        }
    }

    private function validatedPayload(Request $request): array
    {
        $payload = $request->all();
        if (!isset($payload['lines'])) {
            $raw = trim((string) $request->getContent());
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $payload = array_replace($decoded, $payload);
                }
            }
        }

        if (!isset($payload['lines']) || !is_array($payload['lines']) || count($payload['lines']) === 0) {
            abort(response()->json([
                'message' => 'Chua co dong BTP de tao lenh. Neu dang paste Excel, hay bam "Dua vao phieu xuat BTP" truoc, hoac nhap it nhat 1 dong co ma noi bo va so luong.',
                'errors' => [
                    'lines' => ['Chua co dong BTP de tao lenh.'],
                ],
            ], 422));
        }

        $request->replace($payload);

        return $request->validate([
            'order_date' => 'nullable|date',
            'customer' => 'nullable|string|max:200',
            'receiver_name' => 'nullable|string|max:150',
            'department' => 'nullable|string|max:150',
            'purpose' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
            'lines' => 'required|array|min:1|max:200',
            'lines.*.ma_hh' => 'nullable|string|max:100',
            'lines.*.ps_number' => 'nullable|string|max:100',
            'lines.*.customer' => 'nullable|string|max:200',
            'lines.*.ten_hh' => 'nullable|string|max:1000',
            'lines.*.dvt' => 'nullable|string|max:50',
            'lines.*.ordered_quantity' => 'nullable|numeric|min:0',
            'lines.*.quantity' => 'required|numeric|min:0.001',
            'lines.*.location_code' => 'nullable|string|max:100',
            'lines.*.internal_item_code' => 'nullable|string|max:100',
            'lines.*.size' => 'nullable|string|max:255',
            'lines.*.color' => 'nullable|string|max:1000',
            'lines.*.logo_color' => 'nullable|string|max:100',
            'lines.*.side' => 'nullable|string|max:255',
            'lines.*.note' => 'nullable|string|max:1000',
        ]);
    }

    private function ensureLineIdentity(array $lines): void
    {
        $catalogValidator = app(InternalCatalogValidator::class);
        $catalogErrors = $catalogValidator->errorsForLines(collect($lines));
        if (!empty($catalogErrors)) {
            abort($catalogValidator->responseForErrors($catalogErrors));
        }

        foreach ($lines as $index => $line) {
            if (trim((string) ($line['ma_hh'] ?? '')) === '' && trim((string) ($line['internal_item_code'] ?? '')) === '') {
                abort(response()->json([
                    'message' => 'Dòng ' . ($index + 1) . ' cần mã nội bộ hoặc mã hàng.',
                ], 422));
            }
        }
    }

    private function createHeader(array $data): InternalBtpProductionOrder
    {
        $customer = trim((string) ($data['customer'] ?? ''));
        if ($customer === '' && !empty($data['lines'])) {
            $customer = collect($data['lines'])
                ->pluck('customer')
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->unique()
                ->values()
                ->implode(', ');
        }

        return InternalBtpProductionOrder::query()->create([
            'btp_order_code' => app(InternalDocumentNumber::class)->nextYearly('BTP', 4),
            'order_date' => $data['order_date'] ?? now()->format('Y-m-d'),
            'status' => 'draft',
            'receiver_name' => trim($data['receiver_name'] ?? ''),
            'customer' => mb_substr($customer, 0, 200),
            'department' => trim($data['department'] ?? '') ?: 'Sản xuất',
            'purpose' => trim($data['purpose'] ?? '') ?: 'Xuất BTP đi sản xuất',
            'note' => trim($data['note'] ?? ''),
        ]);
    }

    private function normalizeLine(array $line): array
    {
        $internalCode = trim($line['internal_item_code'] ?? '');
        $materialCode = strtoupper(trim($line['ma_hh'] ?? $internalCode));

        return [
            'ps_number' => trim($line['ps_number'] ?? ''),
            'ma_hh' => $materialCode,
            'ten_hh' => mb_substr(trim($line['ten_hh'] ?? ''), 0, 255),
            'dvt' => trim($line['dvt'] ?? ''),
            'ordered_quantity' => $line['ordered_quantity'] ?? null,
            'quantity' => $line['quantity'],
            'location_code' => strtoupper(trim($line['location_code'] ?? '')),
            'internal_item_code' => $internalCode,
            'size' => mb_substr(trim($line['size'] ?? ''), 0, 100),
            'color' => mb_substr(trim($line['color'] ?? ''), 0, 100),
            'logo_color' => mb_substr(trim($line['logo_color'] ?? ''), 0, 100),
            'side' => mb_substr(trim($line['side'] ?? ''), 0, 100),
            'note' => mb_substr(trim($line['note'] ?? ''), 0, 500),
        ];
    }
}
