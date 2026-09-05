<?php

namespace Tests\Feature;

use App\Http\Controllers\InternalItemCatalogController;
use App\Http\Controllers\InternalProductionOrderController;
use App\Models\InternalProductionOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class InternalGoogleSheetSyncControllerTest extends TestCase
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

    public function test_production_order_sync_is_incremental_and_returns_a_run_id(): void
    {
        $suffix = Str::upper(Str::random(8));
        $csv = implode("\n", [
            'LENH SX,MA HANG,SIZE,COLOR,MO TA TEN NHAN,SO LUONG DAT,KHACH HANG',
            "T-SYNC-{$suffix}/26,ITEM-{$suffix},M,BLUE,Sync test,10,TEST CUSTOMER",
        ]);
        Http::fake(['docs.google.com/*' => Http::response($csv, 200)]);

        $first = app(InternalProductionOrderController::class)->sync()->getData(true);
        $second = app(InternalProductionOrderController::class)->sync()->getData(true);

        $this->assertNotEmpty($first['data']['sync_run_id']);
        $this->assertSame(1, (int) $first['data']['created']);
        $this->assertSame(1, (int) $second['data']['unchanged']);
        $this->assertNotSame($first['data']['sync_run_id'], $second['data']['sync_run_id']);
    }

    public function test_production_order_sync_does_not_archive_supplemental_orders(): void
    {
        $suffix = Str::upper(Str::random(8));
        $supplemental = InternalProductionOrder::query()->create([
            'row_key' => hash('sha256', 'SUPPLEMENTAL-SYNC-' . $suffix),
            'production_order' => 'LSP-2026-' . random_int(1000, 9999),
            'item_code' => 'SUP-' . $suffix,
            'description' => 'Supplemental sync test',
            'unit' => 'PCS',
            'order_quantity' => 100,
            'received_date' => '2026-09-05',
            'status' => 'pending',
            'sync_batch' => 'manual-supplemental',
            'is_variant_parent' => true,
            'is_active' => true,
            'raw_data' => ['_internal_order_type' => 'supplemental'],
        ]);
        $csv = implode("\n", [
            'LENH SX,MA HANG,SIZE,COLOR,MO TA TEN NHAN,SO LUONG DAT,KHACH HANG',
            "T-SYNC-{$suffix}/26,ITEM-{$suffix},M,BLUE,Sync test,10,TEST CUSTOMER",
        ]);
        Http::fake(['docs.google.com/*' => Http::response($csv, 200)]);

        app(InternalProductionOrderController::class)->sync();

        $this->assertTrue((bool) $supplemental->fresh()->is_active);
    }

    public function test_production_order_sync_reads_thousands_separator_for_counted_units(): void
    {
        $suffix = Str::upper(Str::random(8));
        $csv = implode("\n", [
            'LENH SX,MA HANG,SO LUONG DAT,DVT,KHACH HANG',
            "T-SYNC-THOUSANDS-{$suffix}/26,ITEM-A,\"15,756\",PCS,TEST CUSTOMER",
            "T-SYNC-THOUSANDS-{$suffix}/26,ITEM-B,7.514,PCS,TEST CUSTOMER",
            "T-SYNC-THOUSANDS-{$suffix}/26,ITEM-C,\"0,387\",KG,TEST CUSTOMER",
        ]);
        Http::fake(['docs.google.com/*' => Http::response($csv, 200)]);

        app(InternalProductionOrderController::class)->sync();

        $quantities = InternalProductionOrder::query()
            ->where('production_order', "T-SYNC-THOUSANDS-{$suffix}/26")
            ->pluck('order_quantity', 'item_code');

        $this->assertSame(15756.0, (float) $quantities['ITEM-A']);
        $this->assertSame(7514.0, (float) $quantities['ITEM-B']);
        $this->assertSame(0.387, (float) $quantities['ITEM-C']);
    }

    public function test_catalog_sync_is_incremental_and_returns_a_run_id(): void
    {
        $suffix = Str::upper(Str::random(8));
        $csv = implode("\n", [
            'MA HANG,TEN HANG,DVT,SIZE,MAU,KE,TON DAU',
            "CAT-{$suffix},Catalog sync {$suffix},PCS,M,BLUE,A1,10",
        ]);
        Http::fake(['docs.google.com/*' => Http::response($csv, 200)]);

        $first = app(InternalItemCatalogController::class)->sync()->getData(true);
        $second = app(InternalItemCatalogController::class)->sync()->getData(true);

        $this->assertNotEmpty($first['data']['sync_run_id']);
        $this->assertSame(1, (int) $first['data']['updated']);
        $this->assertSame(1, (int) $second['data']['unchanged']);
        $this->assertNotSame($first['data']['sync_run_id'], $second['data']['sync_run_id']);
    }
}
