<?php

namespace App\Services;

use App\Models\InternalBtpProductionOrder;
use App\Models\InternalProductBomProfile;
use App\Models\InternalProductionOrder;
use App\Models\InternalProductionOrderBomSnapshot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InternalOrderBomService
{
    private InternalBomCalculator $calculator;

    public function __construct(InternalBomCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    public function central(Collection $orders, bool $persist = false): array
    {
        $itemCodes = $orders
            ->map(fn ($order) => $this->calculator->code($order->standard_item_code ?: $order->item_code))
            ->filter()
            ->unique();
        $profiles = $this->profiles($itemCodes);
        $snapshotsByOrder = InternalProductionOrderBomSnapshot::query()
            ->where('order_type', 'central')
            ->whereIn('production_order_id', $orders->pluck('id')->all())
            ->where('order_line_id', 0)
            ->orderBy('sequence')
            ->get()
            ->groupBy('production_order_id');
        $missing = [];
        $data = [];

        foreach ($orders as $order) {
            $itemCode = $this->calculator->code($order->standard_item_code ?: $order->item_code);
            $snapshots = $snapshotsByOrder->get($order->id, collect());
            $profile = $profiles->get($itemCode);
            if ($snapshots->isEmpty() && !$profile) {
                $missing[$itemCode] = true;
                continue;
            }

            if ($snapshots->isEmpty() && $persist) {
                $snapshots = $this->snapshotLines(
                    'central',
                    (int) $order->id,
                    0,
                    (string) $order->production_order,
                    $itemCode,
                    (float) $order->order_quantity,
                    $profile
                );
            }

            $data[] = $this->presentOrder(
                'central',
                $order,
                null,
                $itemCode,
                (float) $order->order_quantity,
                $profile,
                $snapshots
            );
        }

        return ['data' => $data, 'missing_items' => array_keys($missing)];
    }

    public function btp(InternalBtpProductionOrder $order, bool $persist = false): array
    {
        $lines = $order->relationLoaded('lines') ? $order->lines : $order->load('lines')->lines;
        $lines = $lines->filter(function ($line) {
            return $this->calculator->code($line->internal_item_code ?: $line->ma_hh) !== '';
        })->values();
        $itemCodes = $lines
            ->map(fn ($line) => $this->calculator->code($line->internal_item_code ?: $line->ma_hh))
            ->unique();
        $profiles = $this->profiles($itemCodes);
        $snapshotsByLine = InternalProductionOrderBomSnapshot::query()
            ->where('order_type', 'btp')
            ->where('production_order_id', $order->id)
            ->whereIn('order_line_id', $lines->pluck('id')->all())
            ->orderBy('sequence')
            ->get()
            ->groupBy('order_line_id');
        $missing = [];
        $data = [];

        foreach ($lines as $line) {
            $itemCode = $this->calculator->code($line->internal_item_code ?: $line->ma_hh);
            $quantity = (float) ($line->ordered_quantity ?: $line->quantity);
            $snapshots = $snapshotsByLine->get($line->id, collect());
            $profile = $profiles->get($itemCode);
            if ($snapshots->isEmpty() && !$profile) {
                $missing[$itemCode] = true;
                continue;
            }

            if ($snapshots->isEmpty() && $persist) {
                $snapshots = $this->snapshotLines(
                    'btp',
                    (int) $order->id,
                    (int) $line->id,
                    (string) $order->btp_order_code,
                    $itemCode,
                    $quantity,
                    $profile
                );
            }

            $data[] = $this->presentOrder('btp', $order, $line, $itemCode, $quantity, $profile, $snapshots);
        }

        return [
            'data' => $data,
            'missing_items' => array_keys($missing),
            'order_type' => 'btp',
        ];
    }

    public function aggregate(Collection $orderCodes): array
    {
        $orderCodes = $orderCodes
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values();
        $orders = InternalProductionOrder::query()
            ->whereIn('production_order', $orderCodes->all())
            ->where('is_active', true)
            ->orderBy('production_order')
            ->orderBy('id')
            ->get();
        $central = $this->central($orders);
        $foundOrders = $orders->pluck('production_order')->map(fn ($value) => trim((string) $value))->unique();
        $rows = collect($central['data']);
        $missingItems = collect($central['missing_items']);

        $btpOrders = InternalBtpProductionOrder::query()
            ->with('lines')
            ->whereIn('btp_order_code', $orderCodes->diff($foundOrders)->all())
            ->get();
        foreach ($btpOrders as $btpOrder) {
            $result = $this->btp($btpOrder);
            $rows = $rows->concat($result['data']);
            $missingItems = $missingItems->concat($result['missing_items']);
            $foundOrders->push($btpOrder->btp_order_code);
        }

        $orderMeta = $orders->groupBy('production_order')->map(function ($items) {
            $first = $items->first();
            return [
                'customer' => trim((string) $first->customer),
                'purchase_order' => trim((string) $first->purchase_order),
            ];
        });
        $summaryRows = $rows->flatMap(function ($row) use ($orderMeta) {
            $meta = $orderMeta->get($row['production_order'], ['customer' => '', 'purchase_order' => '']);
            return collect($row['materials'])->map(function ($material) use ($row, $meta) {
                return array_merge($material, [
                    'production_order' => $row['production_order'],
                    'customer' => $meta['customer'],
                    'purchase_order' => $meta['purchase_order'],
                    'finished_item_code' => $row['item_code'],
                ]);
            });
        })->groupBy(function ($row) {
            return implode('|', [
                $row['production_order'], $row['material_code'], $row['component_role'],
                $row['unit'], $row['calculation_mode'], (string) $row['yield_quantity'],
                (string) $row['consumption_per_unit'], (string) $row['waste_percent'],
            ]);
        })->map(function ($items) {
            $first = $items->first();
            $exact = (float) $items->sum('exact_required_quantity');
            $first['exact_required_quantity'] = round($exact, 6);
            $first['required_quantity'] = $this->calculator->shouldRoundRequirement($first)
                ? (float) ceil($exact - 0.000000001)
                : round($exact, 6);
            return $first;
        })->sortBy(fn ($row) => $row['production_order'] . '|' . $row['material_code'])->values();

        $issuedByMaterial = $this->issuedByMaterial($orderCodes);
        $summaryRows = $summaryRows->map(function ($row) use ($issuedByMaterial) {
            $key = implode('|', [
                $this->calculator->code($row['production_order']),
                $this->calculator->code($row['material_code']),
                $this->calculator->code($row['component_role'] ?: 'CHUNG'),
                $this->calculator->code($row['unit']),
            ]);
            $issued = (float) $issuedByMaterial->get($key, 0);
            $row['issued_quantity'] = round($issued, 6);
            $row['suggested_quantity'] = round(max(0, (float) $row['required_quantity'] - $issued), 6);
            $row['over_issued_quantity'] = round(max(0, $issued - (float) $row['required_quantity']), 6);
            return $row;
        });
        $summaryRows = $this->attachStockAvailability($summaryRows);

        return [
            'data' => $summaryRows,
            'missing_orders' => $orderCodes->diff($foundOrders->unique())->values(),
            'missing_items' => $missingItems->unique()->values(),
        ];
    }

    private function profiles(Collection $itemCodes): Collection
    {
        if ($itemCodes->isEmpty()) {
            return collect();
        }

        return InternalProductBomProfile::query()
            ->with(['lines', 'routings'])
            ->whereIn('item_code', $itemCodes->all())
            ->get()
            ->keyBy('item_code');
    }

    private function snapshotLines(
        string $orderType,
        int $orderId,
        int $orderLineId,
        string $orderCode,
        string $itemCode,
        float $orderQuantity,
        InternalProductBomProfile $profile
    ): Collection {
        return $profile->lines->map(function ($line) use (
            $orderType,
            $orderId,
            $orderLineId,
            $orderCode,
            $itemCode,
            $orderQuantity,
            $profile
        ) {
            $requirement = $this->calculator->requirement($line, $orderQuantity);

            return InternalProductionOrderBomSnapshot::query()->create([
                'order_type' => $orderType,
                'production_order_id' => $orderId,
                'order_line_id' => $orderLineId,
                'production_order_code' => $orderCode,
                'finished_item_code' => $itemCode,
                'bom_revision' => $profile->revision,
                'sequence' => $line->sequence,
                'material_code' => $line->material_code,
                'material_name' => $line->material_name,
                'component_role' => $line->component_role,
                'calculation_mode' => $line->calculation_mode,
                'formula_code' => $line->formula_code,
                'formula_output_per_unit' => $line->formula_output_per_unit,
                'formula_output_unit' => $line->formula_output_unit,
                'formula_part' => $line->formula_part,
                'unit' => $line->unit,
                'consumption_per_unit' => $line->consumption_per_unit,
                'yield_quantity' => $line->yield_quantity,
                'waste_percent' => $line->waste_percent,
                'round_to_whole' => $line->round_to_whole,
                'order_quantity' => $orderQuantity,
                'required_quantity' => $requirement['required'],
                'exact_required_quantity' => $requirement['exact'],
                'operation_code' => $line->operation_code,
                'snapshotted_at' => now(),
            ]);
        })->values();
    }

    private function presentOrder(
        string $orderType,
        $order,
        $line,
        string $itemCode,
        float $quantity,
        ?InternalProductBomProfile $profile,
        Collection $snapshots
    ): array {
        $isSnapshot = $snapshots->isNotEmpty();
        $sourceLines = $isSnapshot ? $snapshots : $profile->lines;

        return [
            'order_type' => $orderType,
            'production_order_id' => (int) $order->id,
            'production_order_line_id' => $line ? (int) $line->id : null,
            'production_order' => $orderType === 'btp' ? $order->btp_order_code : $order->production_order,
            'item_code' => $itemCode,
            'item_name' => $line ? $line->ten_hh : $order->description,
            'size' => $line ? $line->size : $order->size,
            'color' => $line ? $line->color : $order->color,
            'order_quantity' => $quantity,
            'is_snapshot' => $isSnapshot,
            'bom_revision' => $isSnapshot ? (int) $snapshots->first()->bom_revision : (int) $profile->revision,
            'operations' => $profile ? $profile->routings->values() : collect(),
            'materials' => $this->presentMaterials($sourceLines, $quantity, $isSnapshot),
        ];
    }

    private function presentMaterials(Collection $lines, float $quantity, bool $isSnapshot): Collection
    {
        return $lines->map(function ($line) use ($quantity, $isSnapshot) {
            $requirement = $isSnapshot
                ? [
                    'exact' => (float) ($line->exact_required_quantity ?? $line->required_quantity),
                    'required' => (float) $line->required_quantity,
                ]
                : $this->calculator->requirement($line, $quantity);

            return [
                'material_code' => $line->material_code,
                'material_name' => $line->material_name,
                'component_role' => $line->component_role,
                'calculation_mode' => $line->calculation_mode ?: 'consumption',
                'formula_code' => $line->formula_code,
                'formula_output_per_unit' => $line->formula_output_per_unit !== null ? (float) $line->formula_output_per_unit : null,
                'formula_output_unit' => $line->formula_output_unit,
                'formula_part' => $line->formula_part !== null ? (float) $line->formula_part : null,
                'unit' => $line->unit,
                'consumption_per_unit' => (float) $line->consumption_per_unit,
                'yield_quantity' => $line->yield_quantity !== null ? (float) $line->yield_quantity : null,
                'waste_percent' => (float) $line->waste_percent,
                'round_to_whole' => (bool) $line->round_to_whole,
                'exact_required_quantity' => round($requirement['exact'], 6),
                'required_quantity' => round($requirement['required'], 6),
                'operation_code' => $line->operation_code,
            ];
        })->values();
    }

    private function issuedByMaterial(Collection $orderCodes): Collection
    {
        return DB::connection('internal')
            ->table('internal_material_order_allocations as allocation')
            ->join('internal_material_issue_lines as line', 'line.id', '=', 'allocation.issue_line_id')
            ->join('internal_material_issues as issue', 'issue.id', '=', 'line.issue_id')
            ->where('issue.status', 'posted')
            ->where('issue.issue_type', 'material')
            ->whereIn('allocation.production_order_code', $orderCodes->all())
            ->selectRaw("allocation.production_order_code, COALESCE(NULLIF(TRIM(line.internal_item_code), ''), line.ma_hh) as material_code, COALESCE(NULLIF(TRIM(line.component_role), ''), 'CHUNG') as component_role, COALESCE(NULLIF(TRIM(line.base_dvt), ''), line.dvt) as unit, SUM(allocation.allocated_quantity) as issued_quantity")
            ->groupBy(
                'allocation.production_order_code',
                'line.internal_item_code',
                'line.ma_hh',
                'line.component_role',
                'line.base_dvt',
                'line.dvt'
            )
            ->get()
            ->groupBy(fn ($row) => implode('|', [
                $this->calculator->code($row->production_order_code),
                $this->calculator->code($row->material_code),
                $this->calculator->code($row->component_role ?: 'CHUNG'),
                $this->calculator->code($row->unit),
            ]))
            ->map(fn ($items) => (float) $items->sum('issued_quantity'));
    }

    private function attachStockAvailability(Collection $rows): Collection
    {
        $materialCodes = $rows->pluck('material_code')
            ->map(fn ($code) => $this->calculator->code($code))
            ->filter()
            ->unique()
            ->values();
        if ($materialCodes->isEmpty()) {
            return $rows;
        }

        $stockByCode = DB::connection('internal')
            ->table('inventory_packages')
            ->where('quantity', '>', 0)
            ->whereIn(DB::raw("UPPER(TRIM(COALESCE(NULLIF(internal_item_code, ''), ma_sp)))"), $materialCodes->all())
            ->select('internal_item_code', 'ma_sp')
            ->selectRaw('SUM(quantity) as available_quantity')
            ->groupBy('internal_item_code', 'ma_sp')
            ->get()
            ->groupBy(fn ($row) => $this->calculator->code($row->internal_item_code ?: $row->ma_sp))
            ->map(fn ($items) => (float) $items->sum('available_quantity'));

        $unitByCode = DB::connection('internal')
            ->table('internal_item_catalogs')
            ->where('is_active', true)
            ->whereIn(DB::raw('UPPER(TRIM(item_code))'), $materialCodes->all())
            ->orderByDesc('source_row')
            ->orderByDesc('id')
            ->get(['item_code', 'unit'])
            ->unique(fn ($row) => $this->calculator->code($row->item_code))
            ->mapWithKeys(fn ($row) => [
                $this->calculator->code($row->item_code) => trim((string) $row->unit),
            ]);

        $balances = $stockByCode->all();

        return $rows->map(function ($row) use (&$balances, $unitByCode) {
            $code = $this->calculator->code($row['material_code']);
            $bomUnit = $this->calculator->normalizeUnit($row['unit']);
            $stockUnit = $this->calculator->normalizeUnit($unitByCode->get($code, $row['unit']));
            $available = max(0, (float) ($balances[$code] ?? 0));
            $suggested = max(0, (float) ($row['suggested_quantity'] ?? 0));
            $unitCompatible = $stockUnit === '' || $bomUnit === '' || $stockUnit === $bomUnit;
            $allocatable = $unitCompatible ? min($available, $suggested) : 0;
            $shortage = $unitCompatible ? max(0, $suggested - $available) : $suggested;

            if ($unitCompatible) {
                $balances[$code] = max(0, $available - $allocatable);
            }

            $row['available_quantity'] = round($available, 6);
            $row['stock_unit'] = $stockUnit ?: $bomUnit;
            $row['stock_allocatable_quantity'] = round($allocatable, 6);
            $row['shortage_quantity'] = round($shortage, 6);
            $row['stock_status'] = !$unitCompatible
                ? 'unit_mismatch'
                : ($shortage > 0.000001 ? 'short' : 'enough');

            return $row;
        });
    }
}
