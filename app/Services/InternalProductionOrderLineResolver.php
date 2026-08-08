<?php

namespace App\Services;

use App\Models\InternalItemCatalog;
use App\Models\InternalProductionOrder;
use Illuminate\Support\Facades\DB;

class InternalProductionOrderLineResolver
{
    public function resolve(array $line, bool $createMissingVariant = true): ?int
    {
        $orderCode = trim((string) ($line['production_order'] ?? ''));
        $itemCode = trim((string) ($line['internal_item_code'] ?? ''));
        $submittedId = (int) ($line['production_order_id'] ?? 0);

        if ($orderCode === '' || $itemCode === '' || $this->isBtpOrder($orderCode)) {
            return $this->isBtpOrder($orderCode) ? null : ($submittedId ?: null);
        }

        $exact = $this->findExactVariant($orderCode, $itemCode, $line);
        if ($exact) {
            return (int) $exact->id;
        }

        $submitted = $submittedId ? InternalProductionOrder::query()->find($submittedId) : null;
        if ($submitted && !$this->same($submitted->production_order, $orderCode)) {
            $submitted = null;
        }

        $parent = $this->resolveParent($orderCode, $itemCode, $submitted);
        if (!$parent) {
            return null;
        }

        if (!$createMissingVariant || !$this->shouldCreateVariant($parent, $submitted, $itemCode, $line)) {
            return (int) $parent->id;
        }

        return $this->createVariant($parent, $itemCode, $line);
    }

    private function findExactVariant(string $orderCode, string $itemCode, array $line): ?InternalProductionOrder
    {
        $candidates = InternalProductionOrder::query()
            ->where('production_order', $orderCode)
            ->where(function ($query) use ($itemCode) {
                $query->where('standard_item_code', $itemCode)
                    ->orWhere(function ($query) use ($itemCode) {
                        $query->where(function ($query) {
                            $query->whereNull('standard_item_code')->orWhere('standard_item_code', '');
                        })->where('item_code', $itemCode);
                    });
            })
            ->orderByDesc('is_active')
            ->orderByDesc('is_manual_variant')
            ->get();

        if ($candidates->count() <= 1) {
            return $candidates->first();
        }

        $size = trim((string) ($line['size'] ?? ''));
        $color = trim((string) ($line['color'] ?? ''));

        return $candidates->first(function ($candidate) use ($size, $color) {
            return ($size === '' || $this->same($candidate->size, $size))
                && ($color === '' || $this->same($candidate->color, $color));
        }) ?: $candidates->first();
    }

    private function resolveParent(string $orderCode, string $itemCode, ?InternalProductionOrder $submitted): ?InternalProductionOrder
    {
        if ($submitted) {
            if ($submitted->variant_parent_id) {
                return InternalProductionOrder::query()->find($submitted->variant_parent_id) ?: $submitted;
            }

            return $submitted;
        }

        $orders = InternalProductionOrder::query()
            ->where('production_order', $orderCode)
            ->orderByRaw('CASE WHEN variant_parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('is_active')
            ->get();

        $itemKey = $this->key($itemCode);
        return $orders->first(function ($order) use ($itemKey) {
            $sourceKey = $this->key($order->item_code);
            return $sourceKey !== '' && ($sourceKey === $itemKey || strpos($itemKey, $sourceKey . '-') === 0 || strpos($itemKey, $sourceKey . '_') === 0);
        }) ?: ($orders->count() === 1 ? $orders->first() : null);
    }

    private function shouldCreateVariant(InternalProductionOrder $parent, ?InternalProductionOrder $submitted, string $itemCode, array $line): bool
    {
        if ($this->same($parent->item_code, $itemCode) || $this->same($parent->standard_item_code, $itemCode)) {
            return false;
        }

        $catalogExists = InternalItemCatalog::query()
            ->where('is_active', true)
            ->where('item_code', $itemCode)
            ->exists();
        if (!$catalogExists) {
            return false;
        }

        $sourceKey = $this->key($parent->item_code ?: $parent->standard_item_code);
        $itemKey = $this->key($itemCode);
        $belongsToSource = $sourceKey !== ''
            && (strpos($itemKey, $sourceKey . '-') === 0 || strpos($itemKey, $sourceKey . '_') === 0);
        $variantContext = (bool) $parent->is_variant_parent
            || (bool) ($submitted && $submitted->variant_parent_id)
            || !$this->same($parent->size, $line['size'] ?? '')
            || !$this->same($parent->color, $line['color'] ?? '');

        return $belongsToSource && $variantContext;
    }

    private function createVariant(InternalProductionOrder $parent, string $itemCode, array $line): int
    {
        return DB::connection('internal')->transaction(function () use ($parent, $itemCode, $line) {
            $parent = InternalProductionOrder::query()->lockForUpdate()->find($parent->id) ?: $parent;
            $existing = $this->findExactVariant((string) $parent->production_order, $itemCode, $line);
            if ($existing) {
                return (int) $existing->id;
            }

            $catalog = InternalItemCatalog::query()
                ->where('is_active', true)
                ->where('item_code', $itemCode)
                ->orderByDesc('id')
                ->first();
            $codeKey = $this->key($itemCode);
            $rowKey = hash('sha256', 'MANUAL_VARIANT|' . $parent->id . '|CODE|' . $codeKey);
            $rawData = is_array($parent->raw_data) ? $parent->raw_data : [];
            $rawData['_internal_variant'] = [
                'parent_id' => (int) $parent->id,
                'variant_key' => 'RECEIPT|' . $codeKey,
                'variant_identity' => 'CODE|' . $codeKey,
                'source_quantity' => (float) $parent->order_quantity,
            ];

            $variant = InternalProductionOrder::query()->firstOrNew(['row_key' => $rowKey]);
            $variant->fill([
                'production_order' => $parent->production_order,
                'purchase_order' => $parent->purchase_order,
                'tracking_staff' => $parent->tracking_staff,
                'customer' => $parent->customer,
                'item_code' => $parent->item_code,
                'standard_item_code' => $itemCode,
                'standard_catalog_id' => $catalog ? $catalog->id : null,
                'variant_parent_id' => $parent->id,
                'is_variant_parent' => false,
                'is_manual_variant' => true,
                'specification' => $parent->specification,
                'description' => $catalog && $catalog->item_name ? $catalog->item_name : $parent->description,
                'size' => trim((string) ($line['size'] ?? '')),
                'color' => trim((string) ($line['color'] ?? '')),
                'unit' => trim((string) ($line['dvt'] ?? '')) ?: ($catalog->unit ?? $parent->unit),
                'order_quantity' => (float) ($line['ordered_quantity'] ?? 0) ?: (float) ($line['quantity'] ?? 0),
                'location' => $parent->location,
                'received_date' => $parent->received_date,
                'promised_date' => $parent->promised_date,
                'customer_requested_date' => $parent->customer_requested_date,
                'delivery_place' => $parent->delivery_place,
                'status' => $parent->status,
                'source_row' => null,
                'raw_data' => $rawData,
                'source_hash' => null,
                'sync_batch' => 'receipt-variant',
                'is_active' => true,
            ]);
            $variant->save();

            $parent->update([
                'is_variant_parent' => true,
                'is_manual_variant' => false,
                'is_active' => false,
            ]);

            return (int) $variant->id;
        });
    }

    private function isBtpOrder(string $orderCode): bool
    {
        return strpos($this->key($orderCode), 'BTP') === 0;
    }

    private function same($left, $right): bool
    {
        return $this->key($left) === $this->key($right);
    }

    private function key($value): string
    {
        return mb_strtoupper(trim((string) $value));
    }
}
