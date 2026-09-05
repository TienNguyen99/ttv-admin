<?php

namespace Tests\Feature;

use App\Models\InternalItemCatalog;
use App\Models\InternalOpeningStock;
use App\Models\InventoryPackage;
use App\Models\WarehouseLocation;
use App\Services\GoogleSheetCatalogWriter;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InternalBulkShelfIntakeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::connection('internal')->beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::connection('internal')->rollBack();
        parent::tearDown();
    }

    public function test_preview_fills_name_and_unit_for_an_existing_catalog_code(): void
    {
        $code = 'BULK-' . strtoupper(substr(md5((string) microtime(true)), 0, 8));
        InternalItemCatalog::query()->create([
            'source_row' => random_int(700000, 900000),
            'item_code' => $code,
            'item_name' => 'Soi mau thu nghiem',
            'unit' => 'KG',
            'color' => '807-C',
            'is_active' => true,
        ]);

        $this->postJson('/api/danh-muc-noi-bo/nhap-ke-hang-loat', [
            'apply' => false,
            'receipt_date' => '2026-09-04',
            'lines' => [[
                'shelf_code' => 'A1',
                'item_code' => $code,
                'quantity' => 3.5,
            ]],
        ])->assertOk()
            ->assertJsonPath('data.0.item_name', 'Soi mau thu nghiem')
            ->assertJsonPath('data.0.unit', 'KG')
            ->assertJsonPath('data.0.catalog_status', 'existing');
    }

    public function test_preview_requires_name_and_unit_for_a_new_catalog_code(): void
    {
        $this->postJson('/api/danh-muc-noi-bo/nhap-ke-hang-loat', [
            'apply' => false,
            'receipt_date' => '2026-09-04',
            'lines' => [[
                'shelf_code' => 'A1',
                'item_code' => 'NEW-BULK-' . strtoupper(substr(md5((string) microtime(true)), 0, 8)),
                'quantity' => 2,
            ]],
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Mã mới phải có tên hàng và đơn vị tính.');
    }

    public function test_preview_accepts_up_to_one_thousand_rows(): void
    {
        $code = 'BULK-LIMIT-' . strtoupper(substr(md5((string) microtime(true)), 0, 8));
        InternalItemCatalog::query()->create([
            'source_row' => random_int(700000, 900000),
            'item_code' => $code,
            'item_name' => 'Soi kiem tra gioi han',
            'unit' => 'KG',
            'is_active' => true,
        ]);

        $lines = array_fill(0, 1000, [
            'shelf_code' => 'A1',
            'item_code' => $code,
            'quantity' => 1,
        ]);

        $this->postJson('/api/danh-muc-noi-bo/nhap-ke-hang-loat', [
            'apply' => false,
            'receipt_date' => '2026-09-04',
            'lines' => $lines,
        ])->assertOk()
            ->assertJsonPath('summary.input_rows', 1000)
            ->assertJsonPath('summary.line_count', 1)
            ->assertJsonPath('summary.total_quantity', 1000);
    }

    public function test_it_creates_only_missing_locations_from_a_pasted_list(): void
    {
        $number = random_int(700, 999);
        $existingCode = 'ZY' . $number;
        $missingCode = 'ZZ' . $number;
        WarehouseLocation::query()->create([
            'location_code' => $existingCode,
            'location_name' => 'Ke co san',
            'status' => 'pending',
        ]);

        $this->postJson('/api/kiem-ton-kho/vi-tri/bao-dam', [
            'location_codes' => [$existingCode, $missingCode, $missingCode],
        ])->assertCreated()
            ->assertJsonPath('data.requested', 2)
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.existing', 1);

        $this->assertDatabaseHas('warehouse_locations', [
            'location_code' => $missingCode,
            'location_name' => 'Kệ ' . $missingCode,
        ], 'internal');
    }

    public function test_same_item_can_be_opened_in_multiple_shelf_locations(): void
    {
        $suffix = random_int(700, 999);
        $code = 'MULTI-SHELF-' . $suffix;
        $locations = ['ZX' . $suffix, 'ZY' . $suffix];
        InternalItemCatalog::query()->create([
            'source_row' => random_int(700000, 900000),
            'item_code' => $code,
            'item_name' => 'Soi nhieu vi tri',
            'unit' => 'KG',
            'color' => 'RED',
            'is_active' => true,
        ]);
        foreach ($locations as $locationCode) {
            WarehouseLocation::query()->create([
                'location_code' => $locationCode,
                'location_name' => 'Ke ' . $locationCode,
                'status' => 'pending',
            ]);
        }

        $this->mock(GoogleSheetCatalogWriter::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->twice()->andReturn(true);
            $mock->shouldReceive('writeRowsFields')->once()->andReturnNull();
        });

        $this->postJson('/api/danh-muc-noi-bo/nhap-ke-hang-loat', [
            'apply' => true,
            'receipt_date' => '2026-09-05',
            'lines' => [
                ['shelf_code' => $locations[0], 'item_code' => $code, 'quantity' => 1],
                ['shelf_code' => $locations[1], 'item_code' => $code, 'quantity' => 2],
            ],
        ])->assertCreated()
            ->assertJsonPath('catalog_summary.item_count', 1)
            ->assertJsonPath('catalog_summary.line_count', 2)
            ->assertJsonPath('catalog_summary.total_quantity', 3);

        $this->assertSame(2, InventoryPackage::query()->where('internal_item_code', $code)->count());
        $this->assertSame(2, InternalOpeningStock::query()->where('internal_item_code', $code)->count());
        $this->assertSame(3.0, (float) InternalOpeningStock::query()->where('internal_item_code', $code)->sum('quantity'));
        $this->assertDatabaseHas('internal_item_catalogs', [
            'item_code' => $code,
            'shelf_code' => implode(', ', $locations),
            'opening_quantity' => 3,
        ], 'internal');
    }

    public function test_retrying_the_same_bulk_request_does_not_add_opening_stock_twice(): void
    {
        $suffix = random_int(700, 999);
        $code = 'IDEMPOTENT-' . $suffix;
        $location = 'ZI' . $suffix;
        InternalItemCatalog::query()->create([
            'source_row' => random_int(700000, 900000),
            'item_code' => $code,
            'item_name' => 'Soi chong trung luot',
            'unit' => 'KG',
            'is_active' => true,
        ]);
        WarehouseLocation::query()->create([
            'location_code' => $location,
            'location_name' => 'Ke ' . $location,
            'status' => 'pending',
        ]);

        $this->mock(GoogleSheetCatalogWriter::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->times(3)->andReturn(true);
            $mock->shouldReceive('writeRowsFields')->once()->andReturnNull();
        });
        $payload = [
            'apply' => true,
            'request_key' => 'rack-test-' . $suffix,
            'receipt_date' => '2026-09-05',
            'lines' => [
                ['shelf_code' => $location, 'item_code' => $code, 'quantity' => 4],
            ],
        ];

        $this->postJson('/api/danh-muc-noi-bo/nhap-ke-hang-loat', $payload)->assertCreated();
        $this->postJson('/api/danh-muc-noi-bo/nhap-ke-hang-loat', $payload)
            ->assertOk()
            ->assertJsonPath('idempotent', true);

        $this->assertSame(1, InternalOpeningStock::query()->where('internal_item_code', $code)->count());
        $this->assertSame(4.0, (float) InternalOpeningStock::query()->where('internal_item_code', $code)->sum('quantity'));
    }
}
