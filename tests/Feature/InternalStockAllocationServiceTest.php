<?php

namespace Tests\Feature;

use App\Models\InventoryPackage;
use App\Models\WarehouseLocation;
use App\Services\InternalStockAllocationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InternalStockAllocationServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = ['internal'];

    private InternalStockAllocationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(InternalStockAllocationService::class);
    }

    public function test_packages_are_selected_by_fifo_when_locations_are_not_specified(): void
    {
        [$older, $newer] = $this->createPackages('FIFO-' . uniqid(), ['2026-08-01', '2026-08-10']);

        $ids = $this->service->packageQuery([
            'internal_item_code' => $older->internal_item_code,
            'size' => '10MM',
            'color' => 'BLACK',
        ], 'KTPHAM')->pluck('id')->all();

        $this->assertSame([$older->id, $newer->id], $ids);
    }

    public function test_selected_location_order_takes_priority_over_package_age(): void
    {
        [$older, $newer] = $this->createPackages('LOCATION-' . uniqid(), ['2026-08-01', '2026-08-10']);
        $newerLocation = $newer->location->location_code;
        $olderLocation = $older->location->location_code;

        $ids = $this->service->packageQuery([
            'internal_item_code' => $older->internal_item_code,
            'size' => '10MM',
            'color' => 'BLACK',
            'location_codes' => [$newerLocation, $olderLocation],
        ], 'KTPHAM')->pluck('id')->all();

        $this->assertSame([$newer->id, $older->id], $ids);
    }

    public function test_shortage_preview_reserves_stock_across_duplicate_lines(): void
    {
        [$package] = $this->createPackages('RESERVE-' . uniqid(), ['2026-08-01'], [100]);
        $line = [
            'internal_item_code' => $package->internal_item_code,
            'size' => '10MM',
            'color' => 'BLACK',
            'dvt' => 'YARD',
        ];

        $warnings = $this->service->shortages([
            $line + ['base_quantity' => 120],
            $line + ['base_quantity' => 10],
        ], 'KTPHAM');

        $this->assertCount(2, $warnings);
        $this->assertSame(100.0, $warnings[0]['available_quantity']);
        $this->assertSame(20.0, $warnings[0]['shortage_quantity']);
        $this->assertSame(0.0, $warnings[1]['available_quantity']);
        $this->assertSame(10.0, $warnings[1]['shortage_quantity']);
    }

    private function createPackages(string $code, array $dates, ?array $quantities = null): array
    {
        $packages = [];
        foreach ($dates as $index => $date) {
            $location = WarehouseLocation::query()->create([
                'location_code' => 'TEST-' . uniqid() . '-' . $index,
                'warehouse_code' => 'KTPHAM',
                'status' => 'active',
            ]);
            $packages[] = InventoryPackage::query()->create([
                'package_code' => 'PKG-' . uniqid() . '-' . $index,
                'warehouse_location_id' => $location->id,
                'ma_sp' => $code,
                'ma_ko' => 'KTPHAM',
                'internal_item_code' => $code,
                'size' => '10MM',
                'color' => 'BLACK',
                'side' => '',
                'quantity' => $quantities[$index] ?? 50,
                'checked_at' => $date,
            ])->setRelation('location', $location);
        }

        return $packages;
    }
}
