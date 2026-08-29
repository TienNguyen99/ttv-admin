<?php

namespace Tests\Feature;

use App\Http\Controllers\InternalItemCatalogController;
use App\Http\Controllers\InternalProductionOrderController;
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
