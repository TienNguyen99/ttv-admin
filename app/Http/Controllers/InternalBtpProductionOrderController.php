<?php

namespace App\Http\Controllers;

use App\Models\InternalBtpProductionOrder;
use App\Services\InternalAudit;
use App\Services\InternalDocumentNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InternalBtpProductionOrderController extends Controller
{
    public function index()
    {
        return view('client.internal-btp-production-orders');
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
                $order = $this->createHeader($data);
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
        return $request->validate([
            'order_date' => 'nullable|date',
            'receiver_name' => 'nullable|string|max:150',
            'department' => 'nullable|string|max:150',
            'purpose' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
            'lines' => 'required|array|min:1|max:200',
            'lines.*.ma_hh' => 'nullable|string|max:100',
            'lines.*.ten_hh' => 'nullable|string|max:1000',
            'lines.*.dvt' => 'nullable|string|max:50',
            'lines.*.ordered_quantity' => 'nullable|numeric|min:0',
            'lines.*.quantity' => 'required|numeric|min:0.001',
            'lines.*.location_code' => 'nullable|string|max:100',
            'lines.*.internal_item_code' => 'nullable|string|max:100',
            'lines.*.size' => 'nullable|string|max:255',
            'lines.*.color' => 'nullable|string|max:1000',
            'lines.*.side' => 'nullable|string|max:255',
            'lines.*.note' => 'nullable|string|max:1000',
        ]);
    }

    private function ensureLineIdentity(array $lines): void
    {
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
        return InternalBtpProductionOrder::query()->create([
            'btp_order_code' => app(InternalDocumentNumber::class)->nextYearly('BTP', 4),
            'order_date' => $data['order_date'] ?? now()->format('Y-m-d'),
            'status' => 'draft',
            'receiver_name' => trim($data['receiver_name'] ?? ''),
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
            'ma_hh' => $materialCode,
            'ten_hh' => mb_substr(trim($line['ten_hh'] ?? ''), 0, 255),
            'dvt' => trim($line['dvt'] ?? ''),
            'ordered_quantity' => $line['ordered_quantity'] ?? null,
            'quantity' => $line['quantity'],
            'location_code' => strtoupper(trim($line['location_code'] ?? '')),
            'internal_item_code' => $internalCode,
            'size' => mb_substr(trim($line['size'] ?? ''), 0, 100),
            'color' => mb_substr(trim($line['color'] ?? ''), 0, 100),
            'side' => mb_substr(trim($line['side'] ?? ''), 0, 100),
            'note' => mb_substr(trim($line['note'] ?? ''), 0, 500),
        ];
    }
}
