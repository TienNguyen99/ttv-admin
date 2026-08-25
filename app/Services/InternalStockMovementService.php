<?php

namespace App\Services;

use App\Models\InternalInventoryCount;
use App\Models\InventoryPackage;
use App\Models\WarehouseLocation;
use Carbon\Carbon;

class InternalStockMovementService
{
    public function receive(array $line, string $warehouseCode, $date, string $note = ''): InventoryPackage
    {
        $locationCode = mb_strtoupper(trim((string) ($line['location_code'] ?? ''))) ?: 'CHUA-XEP';
        $location = WarehouseLocation::query()->firstOrCreate(
            ['location_code' => $locationCode],
            [
                'warehouse_code' => mb_strtoupper(trim($warehouseCode)),
                'shelf_code' => 'CX',
                'tier' => 1,
                'grid_x' => 1,
                'grid_y' => 1,
                'grid_w' => 4,
                'grid_h' => 2,
                'location_name' => $locationCode === 'CHUA-XEP' ? 'Chua xep vi tri' : $locationCode,
                'status' => 'counting',
            ]
        );

        $checkedAt = Carbon::parse($date)->format('Y-m-d');
        $attributes = [
            'ma_sp' => mb_strtoupper(trim((string) ($line['ma_hh'] ?? $line['internal_item_code'] ?? ''))),
            'ma_ko' => mb_strtoupper(trim($warehouseCode)),
            'internal_item_code' => trim((string) ($line['internal_item_code'] ?? '')),
            'size' => trim((string) ($line['size'] ?? '')),
            'color' => trim((string) ($line['color'] ?? '')),
            'side' => trim((string) ($line['side'] ?? '')),
            'checked_at' => $checkedAt,
        ];

        $count = InternalInventoryCount::query()->where($attributes)->lockForUpdate()->first();
        if (!$count) {
            $count = InternalInventoryCount::query()->create($attributes + [
                'counted_quantity' => 0,
                'note' => mb_substr($note, 0, 500),
            ]);
        }
        $count->counted_quantity = (float) $count->counted_quantity + (float) $line['quantity'];
        $count->note = mb_substr($note ?: (string) $count->note, 0, 500);
        $count->save();

        $package = InventoryPackage::query()->create($attributes + [
            'package_code' => app(InternalDocumentNumber::class)->next('PK', 5),
            'warehouse_location_id' => $location->id,
            'inventory_count_id' => $count->id,
            'quantity' => (float) $line['quantity'],
            'note' => mb_substr($note, 0, 500),
        ]);

        $location->status = 'counting';
        $location->save();

        return $package;
    }
}
