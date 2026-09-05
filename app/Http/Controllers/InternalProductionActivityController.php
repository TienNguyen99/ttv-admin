<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\NormalizesDateInput;
use App\Models\InternalProductionActivity;
use App\Models\InternalItemCatalog;
use App\Models\InternalProductionOperationProgress;
use App\Models\InternalProductionOrder;
use App\Services\InternalAudit;
use App\Services\InternalDocumentNumber;
use App\Services\InternalProductionOperationCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class InternalProductionActivityController extends Controller
{
    use NormalizesDateInput;

    private InternalProductionOperationCatalog $operationCatalog;

    public function __construct(InternalProductionOperationCatalog $operationCatalog)
    {
        $this->operationCatalog = $operationCatalog;
    }

    public function index()
    {
        return view('client.production-operation-entry');
    }

    public function show(Request $request)
    {
        $orderCode = trim((string) $request->query('production_order', ''));
        if ($orderCode === '') {
            return response()->json(['message' => 'Chọn lệnh sản xuất.'], 422);
        }

        return response()->json(['data' => $this->orderPayload($orderCode)]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'activity_date' => 'required|date',
            'production_order' => 'required|string|max:100',
            'operation_code' => 'required|string|max:50',
            'next_operation_code' => 'nullable|string|max:50',
            'operator_name' => 'nullable|string|max:150',
            'note' => 'nullable|string|max:500',
            'lines' => 'required|array|min:1|max:200',
            'lines.*.internal_item_code' => 'required|string|max:100',
            'lines.*.good_quantity' => 'required|numeric|min:0',
            'lines.*.defect_quantity' => 'nullable|numeric|min:0',
            'lines.*.note' => 'nullable|string|max:500',
        ]);
        $data = $this->normalizeDateFields($data, ['activity_date']);
        $orderCode = trim($data['production_order']);
        $operationCode = $this->code($data['operation_code']);
        $payload = $this->orderPayload($orderCode);
        $operation = collect($payload['operations'])->first(fn ($row) => $this->code($row['code']) === $operationCode);
        if (!$operation) {
            return response()->json(['message' => 'Công đoạn không hợp lệ.'], 422);
        }
        $nextOperationCode = $this->code($data['next_operation_code'] ?? '');
        $nextOperation = $nextOperationCode === '' ? null : collect($payload['operations'])
            ->first(fn ($row) => $this->code($row['code']) === $nextOperationCode);
        if ($nextOperationCode !== '' && (!$nextOperation || $nextOperationCode === $operationCode)) {
            return response()->json(['message' => 'Công đoạn nhận không hợp lệ.'], 422);
        }

        $items = collect($payload['items'])->keyBy(fn ($row) => $this->code($row['internal_item_code']));
        $lineInputs = collect($data['lines'])->map(function ($line) {
            return [
                'internal_item_code' => $this->code($line['internal_item_code']),
                'good_quantity' => (float) $line['good_quantity'],
                'defect_quantity' => (float) ($line['defect_quantity'] ?? 0),
                'note' => trim((string) ($line['note'] ?? '')),
            ];
        })->filter(fn ($line) => $line['good_quantity'] > 0 || $line['defect_quantity'] > 0)->values();

        if ($lineInputs->isEmpty()) {
            return response()->json(['message' => 'Nhập số lượng đạt hoặc lỗi cho ít nhất một mã.'], 422);
        }

        foreach ($lineInputs as $index => $line) {
            $item = $items->get($line['internal_item_code']);
            if (!$item) {
                return response()->json(['message' => 'Dòng ' . ($index + 1) . ': mã không thuộc lệnh sản xuất.'], 422);
            }
            $progress = collect($item['operation_progress'])->firstWhere('operation_code', $operationCode);
            $remaining = max(0, (float) $item['planned_quantity'] - (float) ($progress['good_quantity'] ?? 0));
            if ($line['good_quantity'] - $remaining > 0.000001) {
                return response()->json([
                    'message' => $line['internal_item_code'] . ' chỉ còn ' . $this->formatNumber($remaining)
                        . ' ' . ($item['unit'] ?: 'PCS') . ' chưa ghi nhận tại công đoạn ' . $operation['name'] . '.',
                ], 422);
            }
        }

        $activity = DB::connection('internal')->transaction(function () use ($data, $lineInputs, $items, $orderCode, $operationCode, $operation, $nextOperationCode, $nextOperation) {
            $activity = InternalProductionActivity::query()->create([
                'activity_code' => app(InternalDocumentNumber::class)->next('NSX'),
                'activity_date' => $data['activity_date'],
                'production_order_code' => $orderCode,
                'operation_code' => $operationCode,
                'operation_name' => $operation['name'],
                'operator_name' => trim((string) ($data['operator_name'] ?? '')) ?: null,
                'status' => 'posted',
                'transfer_code' => $nextOperation ? app(InternalDocumentNumber::class)->next('PCD') : null,
                'next_operation_code' => $nextOperationCode ?: null,
                'next_operation_name' => $nextOperation ? $nextOperation['name'] : null,
                'transfer_status' => $nextOperation ? 'issued' : null,
                'note' => trim((string) ($data['note'] ?? '')) ?: null,
            ]);

            foreach ($lineInputs as $line) {
                $item = $items->get($line['internal_item_code']);
                $activity->lines()->create([
                    'production_order_id' => $item['production_order_id'] ?: null,
                    'source_item_code' => $item['source_item_code'],
                    'internal_item_code' => $item['internal_item_code'],
                    'item_name' => $item['item_name'],
                    'size' => $item['size'],
                    'color' => $item['color'],
                    'unit' => $item['unit'],
                    'planned_quantity' => $item['planned_quantity'],
                    'good_quantity' => $line['good_quantity'],
                    'defect_quantity' => $line['defect_quantity'],
                    'note' => $line['note'] ?: null,
                ]);
            }

            return $activity->load('lines');
        });

        $this->rebuildProgress($orderCode);
        app(InternalAudit::class)->model('production_activity.created', $activity, [
            'production_order' => $orderCode,
            'operation_code' => $operationCode,
            'line_count' => $activity->lines->count(),
        ], $request);

        return response()->json([
            'message' => 'Đã ghi nhận công đoạn ' . $operation['name'] . '.'
                . ($nextOperation ? ' Đã tạo phiếu chuyển ' . $activity->transfer_code . ' sang ' . $nextOperation['name'] . '.' : ''),
            'data' => $activity,
            'order' => $this->orderPayload($orderCode),
        ], 201);
    }

    public function storeVariant(Request $request)
    {
        $data = $request->validate([
            'production_order' => 'required|string|max:100',
            'internal_item_code' => 'required|string|max:200',
            'planned_quantity' => 'required|numeric|min:0.001|max:999999999999999',
            'size' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:1000',
            'unit' => 'nullable|string|max:50',
        ]);

        $orderCode = trim((string) $data['production_order']);
        $variantCode = $this->code($data['internal_item_code']);
        $plannedQuantity = (float) $data['planned_quantity'];
        $created = false;

        $variant = DB::connection('internal')->transaction(function () use ($data, $orderCode, $variantCode, $plannedQuantity, &$created) {
            $rows = InternalProductionOrder::query()
                ->where('production_order', $orderCode)
                ->lockForUpdate()
                ->orderBy('id')
                ->get();
            $parent = $rows->first(function ($row) {
                $rawData = is_array($row->raw_data) ? $row->raw_data : [];
                return (bool) $row->is_variant_parent
                    && (($rawData['_internal_order_type'] ?? '') === 'supplemental'
                        || $row->sync_batch === 'manual-supplemental');
            });
            if (!$parent) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Chỉ lệnh phụ mới được tạo biến thể tại màn hình ghi nhận sản xuất.',
                ], 422));
            }

            $baseCode = $this->code($parent->item_code);
            $validPrefix = $baseCode !== ''
                && ($variantCode === $baseCode || str_starts_with($variantCode, $baseCode . '-') || str_starts_with($variantCode, $baseCode . '_'));
            if (!$validPrefix || $variantCode === $baseCode) {
                throw new HttpResponseException(response()->json([
                    'message' => "Mã biến thể phải bắt đầu bằng {$baseCode}- hoặc {$baseCode}_ (ví dụ {$baseCode}-2AB).",
                ], 422));
            }

            $existing = $rows->first(fn ($row) => $row->variant_parent_id
                && $this->code($row->standard_item_code ?: $row->item_code) === $variantCode);
            if ($existing) {
                return $existing;
            }

            $rawData = is_array($parent->raw_data) ? $parent->raw_data : [];
            $totalQuantity = (float) ($rawData['_internal_order']['total_order_quantity'] ?? $parent->order_quantity);
            $allocatedQuantity = (float) $rows
                ->filter(fn ($row) => $row->variant_parent_id && $row->is_active)
                ->sum('order_quantity');
            if ($totalQuantity > 0 && $allocatedQuantity + $plannedQuantity > $totalQuantity + 0.000001) {
                $remaining = max(0, $totalQuantity - $allocatedQuantity);
                throw new HttpResponseException(response()->json([
                    'message' => 'Số lượng biến thể vượt tổng đơn hàng. Còn có thể phân bổ ' . $this->formatNumber($remaining) . '.',
                ], 422));
            }

            $catalog = InternalItemCatalog::query()
                ->where('is_active', true)
                ->whereRaw('UPPER(TRIM(item_code)) = ?', [$variantCode])
                ->orderByDesc('id')
                ->first();
            $rawData['_internal_variant'] = [
                'parent_id' => (int) $parent->id,
                'variant_key' => 'PRODUCTION|CODE',
                'variant_identity' => 'CODE|' . $variantCode,
                'source_quantity' => $totalQuantity,
                'created_from' => 'production_operation_entry',
            ];

            $variant = InternalProductionOrder::query()->create([
                'row_key' => hash('sha256', 'SUPPLEMENTAL_VARIANT|' . $parent->id . '|CODE|' . $variantCode),
                'production_order' => $parent->production_order,
                'purchase_order' => $parent->purchase_order,
                'tracking_staff' => $parent->tracking_staff,
                'customer' => $parent->customer,
                'item_code' => $baseCode,
                'standard_item_code' => $variantCode,
                'standard_catalog_id' => $catalog ? $catalog->id : null,
                'variant_parent_id' => $parent->id,
                'is_variant_parent' => false,
                'is_manual_variant' => true,
                'specification' => $parent->specification,
                'description' => trim((string) ($catalog ? $catalog->item_name : ($parent->description ?: $variantCode))),
                'size' => trim((string) ($data['size'] ?? '')) ?: trim((string) ($catalog ? $catalog->size : '')),
                'color' => trim((string) ($data['color'] ?? '')) ?: trim((string) ($catalog ? $catalog->color : '')),
                'unit' => mb_strtoupper(trim((string) ($data['unit'] ?? ''))) ?: mb_strtoupper(trim((string) (($catalog && $catalog->unit) ? $catalog->unit : ($parent->unit ?: 'PCS')))),
                'order_quantity' => $plannedQuantity,
                'location' => $parent->location,
                'received_date' => $parent->received_date,
                'promised_date' => $parent->promised_date,
                'customer_requested_date' => $parent->customer_requested_date,
                'delivery_place' => $parent->delivery_place,
                'status' => 'pending',
                'source_row' => null,
                'raw_data' => $rawData,
                'source_hash' => hash('sha256', 'SUPPLEMENTAL_VARIANT|' . $parent->id . '|' . $variantCode . '|' . $plannedQuantity),
                'sync_batch' => 'production-variant',
                'is_active' => true,
            ]);
            $parent->update(['is_active' => false]);
            $created = true;

            return $variant;
        });

        if ($created) {
            app(InternalAudit::class)->model('production_order.variant_created', $variant, [
                'production_order' => $orderCode,
                'internal_item_code' => $variantCode,
                'planned_quantity' => $plannedQuantity,
            ], $request);
            Cache::put(
                'internal_production_order_search_version',
                (int) Cache::get('internal_production_order_search_version', 1) + 1
            );
        }

        return response()->json([
            'message' => $created ? "Đã thêm biến thể {$variantCode} vào {$orderCode}." : "Biến thể {$variantCode} đã có trong lệnh.",
            'data' => $variant,
            'order' => $this->orderPayload($orderCode),
        ], $created ? 201 : 200);
    }

    public function qr(Request $request)
    {
        $orderCode = trim((string) $request->query('production_order', ''));
        $payload = $this->orderPayload($orderCode);

        return view('client.production-order-qr', ['order' => $payload]);
    }

    public function transferPrint(InternalProductionActivity $productionActivity)
    {
        if (!$productionActivity->transfer_code) {
            abort(404);
        }

        return view('client.production-operation-transfer-print', [
            'activity' => $productionActivity->load('lines'),
        ]);
    }

    public function createTransfer(Request $request, InternalProductionActivity $productionActivity)
    {
        $data = $request->validate([
            'next_operation_code' => 'required|string|max:50',
        ]);
        $nextOperationCode = $this->code($data['next_operation_code']);
        $payload = $this->orderPayload($productionActivity->production_order_code);
        $nextOperation = collect($payload['operations'])
            ->first(fn ($row) => $this->code($row['code']) === $nextOperationCode);
        if (!$nextOperation || $nextOperationCode === $this->code($productionActivity->operation_code)) {
            return response()->json(['message' => 'Công đoạn nhận không hợp lệ.'], 422);
        }

        $created = false;
        $activity = DB::connection('internal')->transaction(function () use ($productionActivity, $nextOperationCode, $nextOperation, &$created) {
            $activity = InternalProductionActivity::query()->lockForUpdate()->findOrFail($productionActivity->id);
            if ($activity->transfer_code) {
                return $activity;
            }
            $activity->update([
                'transfer_code' => app(InternalDocumentNumber::class)->next('PCD'),
                'next_operation_code' => $nextOperationCode,
                'next_operation_name' => $nextOperation['name'],
                'transfer_status' => 'issued',
            ]);
            $created = true;

            return $activity;
        });

        if ($created) {
            app(InternalAudit::class)->model('production_activity.transfer_created', $activity, [
                'production_order' => $activity->production_order_code,
                'from_operation' => $activity->operation_code,
                'to_operation' => $nextOperationCode,
            ], $request);
        }

        return response()->json([
            'message' => $created
                ? 'Đã tạo phiếu chuyển ' . $activity->transfer_code . ' sang ' . $nextOperation['name'] . '.'
                : 'Lần ghi nhận này đã có phiếu chuyển ' . $activity->transfer_code . '.',
            'order' => $this->orderPayload($activity->production_order_code),
        ], $created ? 201 : 200);
    }

    public function destroy(Request $request, InternalProductionActivity $productionActivity)
    {
        $orderCode = $productionActivity->production_order_code;
        DB::connection('internal')->transaction(fn () => $productionActivity->delete());
        $this->rebuildProgress($orderCode);
        app(InternalAudit::class)->record(
            'production_activity.deleted',
            'InternalProductionActivity',
            (int) $productionActivity->id,
            $productionActivity->activity_code,
            ['production_order' => $orderCode],
            $request
        );

        return response()->json(['message' => 'Đã xóa lần ghi nhận.', 'order' => $this->orderPayload($orderCode)]);
    }

    private function orderPayload(string $orderCode): array
    {
        $orders = InternalProductionOrder::query()
            ->where('is_active', true)
            ->where('production_order', $orderCode)
            ->orderBy('id')
            ->get();
        if ($orders->isEmpty()) {
            throw new HttpResponseException(response()->json(['message' => 'Không tìm thấy lệnh sản xuất.'], 404));
        }

        $operations = $this->operationCatalog->forOrders($orders);
        $activities = InternalProductionActivity::query()
            ->with('lines')
            ->where('production_order_code', $orderCode)
            ->where('status', 'posted')
            ->orderByDesc('activity_date')
            ->orderByDesc('id')
            ->get();
        $activities->each(function ($activity) {
            $activity->setAttribute('transfer_print_url', $activity->transfer_code
                ? url('/client/ghi-nhan-san-xuat/' . $activity->id . '/phieu-chuyen')
                : null);
        });

        $items = $orders->groupBy(fn ($row) => $this->code($row->standard_item_code ?: $row->item_code))
            ->map(function ($rows, $itemCode) use ($operations, $activities) {
                $first = $rows->first();
                $planned = (float) $rows->sum('order_quantity');
                $lineRows = $activities->flatMap(function ($activity) use ($itemCode) {
                    return $activity->lines->where('internal_item_code', $itemCode)
                        ->map(fn ($line) => ['activity' => $activity, 'line' => $line]);
                });
                $progress = $operations->map(function ($operation) use ($lineRows, $planned) {
                    $rows = $lineRows->filter(fn ($row) => $this->code($row['activity']->operation_code) === $this->code($operation['code']));
                    $good = (float) $rows->sum(fn ($row) => $row['line']->good_quantity);
                    $defect = (float) $rows->sum(fn ($row) => $row['line']->defect_quantity);
                    return [
                        'operation_code' => $this->code($operation['code']),
                        'good_quantity' => $good,
                        'defect_quantity' => $defect,
                        'remaining_quantity' => max(0, $planned - $good),
                        'percent' => $planned > 0 ? min(100, round($good / $planned * 100, 1)) : 0,
                    ];
                })->values();

                return [
                    'production_order_id' => (int) $first->id,
                    'source_item_code' => trim((string) $first->item_code),
                    'internal_item_code' => $itemCode,
                    'item_name' => trim((string) $first->description),
                    'size' => trim((string) $first->size),
                    'color' => trim((string) $first->color),
                    'unit' => trim((string) $first->unit) ?: 'PCS',
                    'planned_quantity' => $planned,
                    'operation_progress' => $progress,
                ];
            })->values();

        $operationSummary = $operations->map(function ($operation) use ($items) {
            $good = (float) $items->sum(function ($item) use ($operation) {
                return (float) (collect($item['operation_progress'])->firstWhere('operation_code', $this->code($operation['code']))['good_quantity'] ?? 0);
            });
            $planned = (float) $items->sum('planned_quantity');
            return $operation + [
                'good_quantity' => $good,
                'planned_quantity' => $planned,
                'percent' => $planned > 0 ? min(100, round($good / $planned * 100, 1)) : 0,
            ];
        })->values();

        $orderQuantity = (float) $orders->map(function ($order) {
            $rawData = is_array($order->raw_data) ? $order->raw_data : [];
            return (float) ($rawData['_internal_order']['total_order_quantity']
                ?? $rawData['_internal_variant']['source_quantity']
                ?? 0);
        })->filter(fn ($quantity) => $quantity > 0)->max() ?: (float) $orders->sum('order_quantity');
        $variantAllocatedQuantity = (float) $orders->filter(fn ($order) => (bool) $order->variant_parent_id)->sum('order_quantity');

        return [
            'production_order' => $orderCode,
            'order_type' => $orders->contains(function ($order) {
                $rawData = is_array($order->raw_data) ? $order->raw_data : [];
                return ($rawData['_internal_order_type'] ?? '') === 'supplemental'
                    || $order->sync_batch === 'manual-supplemental';
            }) ? 'supplemental' : 'standard',
            'order_quantity' => $orderQuantity,
            'variant_allocated_quantity' => $variantAllocatedQuantity,
            'variant_remaining_quantity' => max(0, $orderQuantity - $variantAllocatedQuantity),
            'customer' => trim((string) $orders->first()->customer),
            'purchase_order' => trim((string) $orders->first()->purchase_order),
            'root_item_codes' => $orders->pluck('item_code')->map(fn ($code) => $this->code($code))->filter()->unique()->values(),
            'operations' => $operationSummary,
            'items' => $items,
            'activities' => $activities,
            'qr_url' => url('/client/ghi-nhan-san-xuat?production_order=' . rawurlencode($orderCode)),
        ];
    }

    private function rebuildProgress(string $orderCode): void
    {
        $payload = $this->orderPayload($orderCode);
        $items = collect($payload['items']);
        foreach ($payload['operations'] as $index => $operation) {
            $rows = $items->map(fn ($item) => collect($item['operation_progress'])->firstWhere('operation_code', $this->code($operation['code'])))->filter();
            $good = (float) $rows->sum('good_quantity');
            $planned = (float) $items->sum('planned_quantity');
            if (empty($operation['is_configured']) && $good <= 0) {
                InternalProductionOperationProgress::query()
                    ->where('production_order_code', $orderCode)
                    ->where('operation_code', $this->code($operation['code']))
                    ->delete();
                continue;
            }
            $status = $good <= 0 ? 'pending' : ($planned > 0 && $good >= $planned ? 'completed' : 'in_progress');
            $progress = InternalProductionOperationProgress::query()->firstOrNew([
                'production_order_code' => $orderCode,
                'operation_code' => $this->code($operation['code']),
            ]);
            $progress->operation_name = $operation['name'];
            $progress->sequence = $index + 1;
            $progress->status = $status;
            $progress->started_at = $status === 'pending' ? null : ($progress->started_at ?: now());
            $progress->completed_at = $status === 'completed' ? now() : null;
            $progress->note = $this->formatNumber($good) . ' / ' . $this->formatNumber($planned);
            $progress->save();
        }
    }

    private function code($value): string
    {
        return mb_strtoupper(trim((string) $value));
    }

    private function formatNumber(float $value): string
    {
        return number_format($value, 3, ',', '.');
    }
}
