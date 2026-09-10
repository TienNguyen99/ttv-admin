<?php

namespace Tests\Feature;

use App\Models\InternalItemCatalog;
use App\Models\InternalStocktakeSession;
use App\Models\InventoryPackage;
use App\Models\WarehouseLocation;
use App\Services\InternalStocktakeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class InternalCurrentStockConsistencyTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = ['internal'];

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_total_stocktake_replaces_old_variant_packages(): void
    {
        Carbon::setTestNow('2037-09-07 09:00:00');
        [$code, $oldPackage, $session] = $this->stocktakeFixture();

        app(InternalStocktakeService::class)->post($session);

        $this->assertSame(0.0, (float) $oldPackage->fresh()->quantity);
        $this->assertSame(100.0, (float) InventoryPackage::query()
            ->where('internal_item_code', $code)
            ->where('quantity', '>', 0)
            ->sum('quantity'));
    }

    public function test_current_stock_report_uses_ledger_instead_of_stale_packages(): void
    {
        Carbon::setTestNow('2037-09-07 09:00:00');
        [$code, , $session, $oldLocation] = $this->stocktakeFixture();
        app(InternalStocktakeService::class)->post($session);

        InventoryPackage::query()->create([
            'package_code' => 'STALE-' . strtoupper(uniqid()),
            'warehouse_location_id' => $oldLocation->id,
            'ma_sp' => $code,
            'internal_item_code' => $code,
            'size' => 'W=50MM',
            'color' => 'BLACK',
            'quantity' => 50,
            'checked_at' => '2037-08-01',
        ]);

        $url = '/api/bao-cao-nhap-xuat-ton/xuat?' . http_build_query([
            'month' => '2037-09',
            'report_type' => 'current',
            'groups' => ['Thun bản'],
            'item_code' => $code,
        ]);
        $response = $this->get($url)->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'stock-report-');
        file_put_contents($path, $response->streamedContent());
        try {
            $sheet = IOFactory::load($path)->getSheetByName('Thun bản');
            $this->assertNotNull($sheet);
            $this->assertSame($code, $sheet->getCell('B5')->getValue());
            $this->assertSame(100.0, (float) $sheet->getCell('J5')->getValue());
            $this->assertStringContainsString('100.000', (string) $sheet->getCell('F5')->getValue());
        } finally {
            @unlink($path);
        }
    }

    private function stocktakeFixture(): array
    {
        $suffix = strtoupper(uniqid());
        $code = 'STOCK-REPORT-' . $suffix;
        $now = now();
        $oldLocation = WarehouseLocation::query()->create([
            'location_code' => 'OLD-' . $suffix,
            'location_name' => 'Old location',
            'status' => 'active',
        ]);
        $countLocation = WarehouseLocation::query()->create([
            'location_code' => 'V15-' . $suffix,
            'location_name' => 'V15',
            'status' => 'active',
        ]);
        $oldPackage = InventoryPackage::query()->create([
            'package_code' => 'OLD-PACKAGE-' . $suffix,
            'warehouse_location_id' => $oldLocation->id,
            'ma_sp' => $code,
            'internal_item_code' => $code,
            'size' => 'W=50MM',
            'color' => 'BLACK',
            'quantity' => 50,
            'checked_at' => '2037-08-01',
        ]);
        InternalItemCatalog::query()->create([
            'item_code' => $code,
            'item_name' => 'THUN BẢN KIỂM THỬ',
            'unit' => 'YARD',
            'opening_quantity' => 0,
            'raw_data' => ['loai' => 'TP-THUNBAN'],
            'is_active' => true,
        ]);
        $session = InternalStocktakeSession::query()->create([
            'stocktake_code' => 'KK-' . $suffix,
            'name' => 'Kiểm thử tồn tổng',
            'count_date' => '2037-09-06',
            'status' => 'completed',
            'started_at' => $now,
            'completed_at' => $now,
        ]);
        $sessionLocationId = DB::connection('internal')->table('internal_stocktake_locations')->insertGetId([
            'session_id' => $session->id,
            'warehouse_location_id' => $countLocation->id,
            'location_code' => $countLocation->location_code,
            'status' => 'completed',
            'started_at' => $now,
            'completed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::connection('internal')->table('internal_stocktake_lines')->insert([
            'session_id' => $session->id,
            'session_location_id' => $sessionLocationId,
            'line_key' => sha1($countLocation->location_code . '|' . $code),
            'location_code' => $countLocation->location_code,
            'ma_hh' => $code,
            'internal_item_code' => $code,
            'item_name' => 'THUN BẢN KIỂM THỬ',
            'unit' => 'YARD',
            'size' => '',
            'color' => '',
            'side' => '',
            'expected_quantity' => 50,
            'counted_quantity' => 100,
            'counted_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$code, $oldPackage, $session, $oldLocation];
    }
}
