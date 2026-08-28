<?php

namespace App\Services;

use App\Models\InternalMaterialIssue;
use App\Models\InternalMaterialOrderAllocation;
use App\Models\InternalProductionOrder;

class InternalMaterialIssueLineService
{
    private InternalProductionOrderLineResolver $orderLineResolver;
    private InternalStockAllocationService $stockAllocationService;
    private InternalUnitConverter $unitConverter;

    public function __construct(
        InternalProductionOrderLineResolver $orderLineResolver,
        InternalStockAllocationService $stockAllocationService,
        InternalUnitConverter $unitConverter
    ) {
        $this->orderLineResolver = $orderLineResolver;
        $this->stockAllocationService = $stockAllocationService;
        $this->unitConverter = $unitConverter;
    }

    public function create(InternalMaterialIssue $issue, array $line): array
    {
        $line['production_order_id'] = $this->orderLineResolver->resolve($line);
        $base = $this->unitConverter->toBase(
            trim((string) ($line['internal_item_code'] ?? '')),
            (float) ($line['quantity'] ?? 0),
            trim((string) ($line['dvt'] ?? '')),
            trim((string) ($line['dvt'] ?? ''))
        );

        $issueLine = $issue->lines()->create([
            'production_order_id' => $line['production_order_id'] ?? null,
            'production_order' => trim((string) ($line['production_order'] ?? '')),
            'purchase_order' => trim((string) ($line['purchase_order'] ?? '')),
            'customer' => trim((string) ($line['customer'] ?? '')),
            'ma_hh' => mb_strtoupper(trim((string) ($line['ma_hh'] ?? ($line['internal_item_code'] ?? '')))),
            'ten_hh' => mb_substr(trim((string) ($line['ten_hh'] ?? '')), 0, 255),
            'dvt' => trim((string) ($line['dvt'] ?? '')),
            'ordered_quantity' => $line['ordered_quantity'] ?? null,
            'quantity' => $line['quantity'],
            'base_quantity' => $base['quantity'],
            'base_dvt' => $base['unit'],
            'unit_factor' => $base['factor'],
            'location_code' => mb_substr(implode(', ', $this->stockAllocationService->selectedLocationCodes($line)), 0, 100),
            'match_by_code_only' => array_key_exists('match_by_code_only', $line)
                ? (bool) $line['match_by_code_only']
                : null,
            'internal_item_code' => trim((string) ($line['internal_item_code'] ?? '')),
            'size' => mb_substr(trim((string) ($line['size'] ?? '')), 0, 100),
            'color' => mb_substr(trim((string) ($line['color'] ?? '')), 0, 100),
            'side' => mb_substr(trim((string) ($line['side'] ?? '')), 0, 100),
            'component_role' => mb_substr(trim((string) ($line['component_role'] ?? '')) ?: 'CHUNG', 0, 100),
            'note' => mb_substr(trim((string) ($line['note'] ?? '')), 0, 500),
        ]);

        $this->createOrderAllocations($issueLine->id, $line, $base);

        return [
            'model' => $issueLine,
            'line' => $line,
            'base' => $base,
        ];
    }

    private function createOrderAllocations(int $issueLineId, array $line, array $base): void
    {
        $allocations = collect($line['allocations'] ?? []);
        if ($allocations->isEmpty() && trim((string) ($line['production_order'] ?? '')) !== '') {
            $allocations = collect([[
                'production_order_id' => $line['production_order_id'] ?? null,
                'production_order' => trim((string) $line['production_order']),
                'quantity' => (float) $line['quantity'],
                'note' => null,
            ]]);
        }

        foreach ($allocations as $allocation) {
            $orderCode = trim((string) ($allocation['production_order'] ?? ''));
            $productionOrder = !empty($allocation['production_order_id'])
                ? InternalProductionOrder::query()->find((int) $allocation['production_order_id'])
                : null;
            if (!$productionOrder && $orderCode !== '') {
                $productionOrder = InternalProductionOrder::query()
                    ->where('production_order', $orderCode)
                    ->where('is_active', true)
                    ->orderByDesc('id')
                    ->first();
            }

            InternalMaterialOrderAllocation::query()->create([
                'issue_line_id' => $issueLineId,
                'production_order_id' => $productionOrder->id ?? ($allocation['production_order_id'] ?? null),
                'production_order_code' => $orderCode,
                'finished_item_code' => $productionOrder
                    ? trim((string) ($productionOrder->standard_item_code ?: $productionOrder->item_code))
                    : null,
                'allocated_quantity' => (float) ($allocation['quantity'] ?? 0) * (float) ($base['factor'] ?: 1),
                'returned_quantity' => 0,
                'scrap_quantity' => 0,
                'note' => mb_substr(trim((string) ($allocation['note'] ?? '')), 0, 500),
            ]);
        }
    }
}
