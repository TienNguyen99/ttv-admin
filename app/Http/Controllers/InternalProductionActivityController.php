<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\NormalizesDateInput;
use App\Models\InternalProductionActivity;
use App\Models\InternalProductionOperationProgress;
use App\Models\InternalProductionOrder;
use App\Services\InternalAudit;
use App\Services\InternalDocumentNumber;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InternalProductionActivityController extends Controller
{
    use NormalizesDateInput;

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

        $activity = DB::connection('internal')->transaction(function () use ($data, $lineInputs, $items, $orderCode, $operationCode, $operation) {
            $activity = InternalProductionActivity::query()->create([
                'activity_code' => app(InternalDocumentNumber::class)->next('NSX'),
                'activity_date' => $data['activity_date'],
                'production_order_code' => $orderCode,
                'operation_code' => $operationCode,
                'operation_name' => $operation['name'],
                'operator_name' => trim((string) ($data['operator_name'] ?? '')) ?: null,
                'status' => 'posted',
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
            'message' => 'Đã ghi nhận công đoạn ' . $operation['name'] . '.',
            'data' => $activity,
            'order' => $this->orderPayload($orderCode),
        ], 201);
    }

    public function qr(Request $request)
    {
        $orderCode = trim((string) $request->query('production_order', ''));
        $payload = $this->orderPayload($orderCode);

        return view('client.production-order-qr', ['order' => $payload]);
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

        $operations = $this->resolveOperations($orders);
        $activities = InternalProductionActivity::query()
            ->with('lines')
            ->where('production_order_code', $orderCode)
            ->where('status', 'posted')
            ->orderByDesc('activity_date')
            ->orderByDesc('id')
            ->get();

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

        return [
            'production_order' => $orderCode,
            'customer' => trim((string) $orders->first()->customer),
            'purchase_order' => trim((string) $orders->first()->purchase_order),
            'root_item_codes' => $orders->pluck('item_code')->map(fn ($code) => $this->code($code))->filter()->unique()->values(),
            'operations' => $operationSummary,
            'items' => $items,
            'activities' => $activities,
            'qr_url' => url('/client/ghi-nhan-san-xuat?production_order=' . rawurlencode($orderCode)),
        ];
    }

    private function resolveOperations(Collection $orders): Collection
    {
        $exactCodes = $orders->map(fn ($row) => $this->code($row->standard_item_code ?: $row->item_code))->filter()->unique();
        $sourceCodes = $orders->map(fn ($row) => $this->code($row->item_code))->filter()->unique();
        $profiles = DB::connection('internal')->table('internal_product_bom_profiles')
            ->where('status', 'active')
            ->whereIn('item_code', $exactCodes->concat($sourceCodes)->unique()->all())
            ->get()
            ->keyBy(fn ($row) => $this->code($row->item_code));
        $profileIds = $exactCodes->concat($sourceCodes)
            ->map(fn ($code) => optional($profiles->get($code))->id)
            ->filter()
            ->unique();

        $configured = $profileIds->isEmpty()
            ? collect()
            : DB::connection('internal')->table('internal_product_routings')
                ->whereIn('profile_id', $profileIds->all())
                ->orderBy('sequence')
                ->get()
                ->unique(fn ($row) => $this->code($row->operation_code))
                ->values()
                ->map(fn ($row, $index) => [
                'code' => $this->code($row->operation_code),
                'name' => trim((string) $row->operation_name),
                'sequence' => $index + 1,
                'is_configured' => true,
            ]);

        $configuredCodes = $configured->pluck('code')->map(fn ($code) => $this->code($code));
        $standard = collect($this->standardOperations())
            ->reject(fn ($operation) => $configuredCodes->contains($this->code($operation['code'])))
            ->values()
            ->map(fn ($operation, $index) => $operation + [
                'sequence' => $configured->count() + $index + 1,
                'is_configured' => false,
            ]);

        return $configured->concat($standard)->values();
    }

    private function standardOperations(): array
    {
        return [
            ['code' => 'PHA', 'name' => 'Pha nguyên liệu'],
            ['code' => 'DUC', 'name' => 'Đúc'],
            ['code' => 'IN', 'name' => 'In'],
            ['code' => 'EP', 'name' => 'Ép'],
            ['code' => 'DET', 'name' => 'Dệt'],
            ['code' => 'CAT', 'name' => 'Cắt'],
            ['code' => 'MAY', 'name' => 'May'],
            ['code' => 'HOAN_THIEN', 'name' => 'Hoàn thiện'],
            ['code' => 'KCS', 'name' => 'KCS'],
            ['code' => 'DONG_GOI', 'name' => 'Đóng gói'],
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
