<?php

namespace Tests\Feature;

use App\Http\Controllers\InternalProductBomController;
use App\Http\Controllers\InternalProductionOrderController;
use App\Models\InternalBtpProductionOrder;
use App\Models\InternalItemCatalog;
use App\Models\InternalProductionOperationProgress;
use App\Models\InternalProductionOrder;
use App\Models\InventoryPackage;
use App\Models\WarehouseLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InternalProductBomControllerTest extends TestCase
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

    public function test_it_allows_the_same_material_in_different_roles_and_calculates_order_need(): void
    {
        $controller = app(InternalProductBomController::class);
        $saveRequest = Request::create('/api/dinh-muc-san-xuat/luu', 'POST', [
            'item_code' => 'TEST-BOM-FG',
            'item_name' => 'Test finished item',
            'unit' => 'PCS',
            'operations' => [
                ['operation_code' => 'DET', 'operation_name' => 'Dệt'],
            ],
            'materials' => [
                [
                    'material_code' => 'TEST-YARN-01',
                    'component_role' => 'DOC',
                    'unit' => 'KG',
                    'consumption_per_unit' => 0.01,
                    'waste_percent' => 10,
                    'operation_code' => 'DET',
                ],
                [
                    'material_code' => 'TEST-YARN-01',
                    'component_role' => 'NGANG',
                    'unit' => 'KG',
                    'consumption_per_unit' => 0.02,
                    'waste_percent' => 0,
                    'operation_code' => 'DET',
                ],
            ],
        ]);

        $saved = $controller->save($saveRequest)->getData(true);
        $this->assertCount(2, $saved['data']['lines']);

        InternalProductionOrder::query()->create([
            'production_order' => 'TEST-ORDER-BOM',
            'item_code' => 'TEST-BOM-FG',
            'standard_item_code' => 'TEST-BOM-FG',
            'description' => 'Test finished item',
            'order_quantity' => 100,
            'is_active' => true,
        ]);

        $needRequest = Request::create('/api/dinh-muc-san-xuat/nhu-cau', 'GET', [
            'production_order' => 'TEST-ORDER-BOM',
        ]);
        $needs = $controller->orderNeeds($needRequest)->getData(true);

        $this->assertSame([], $needs['missing_items']);
        $this->assertCount(2, $needs['data'][0]['materials']);
        $this->assertEquals(1.1, $needs['data'][0]['materials'][0]['required_quantity']);
        $this->assertEquals(2.0, $needs['data'][0]['materials'][1]['required_quantity']);
    }

    public function test_it_calculates_each_line_of_a_btp_order(): void
    {
        $controller = app(InternalProductBomController::class);
        $controller->save(Request::create('/api/dinh-muc-san-xuat/luu', 'POST', [
            'item_code' => 'TEST-BTP-FG',
            'unit' => 'PCS',
            'operations' => [
                ['operation_code' => 'EP', 'operation_name' => 'Ép'],
            ],
            'materials' => [
                [
                    'material_code' => 'TEST-BTP-MATERIAL',
                    'component_role' => 'CHUNG',
                    'unit' => 'KG',
                    'consumption_per_unit' => 0.5,
                    'waste_percent' => 0,
                    'operation_code' => 'EP',
                ],
            ],
        ]));

        $order = InternalBtpProductionOrder::query()->create([
            'btp_order_code' => 'BTP-TEST-NEEDS',
            'order_date' => '2026-08-25',
            'status' => 'draft',
        ]);
        $order->lines()->create([
            'ma_hh' => 'TEST-BTP-FG',
            'internal_item_code' => 'TEST-BTP-FG',
            'ten_hh' => 'Test BTP item',
            'dvt' => 'PCS',
            'quantity' => 20,
        ]);
        $order->lines()->create([
            'ma_hh' => 'TEST-MISSING-BOM',
            'internal_item_code' => 'TEST-MISSING-BOM',
            'ten_hh' => 'Missing BOM item',
            'dvt' => 'PCS',
            'quantity' => 5,
        ]);

        $response = $controller->orderNeeds(Request::create('/api/dinh-muc-san-xuat/nhu-cau', 'GET', [
            'production_order' => 'BTP-TEST-NEEDS',
        ]))->getData(true);

        $this->assertSame('btp', $response['order_type']);
        $this->assertCount(1, $response['data']);
        $this->assertSame('btp', $response['data'][0]['order_type']);
        $this->assertEquals(10.0, $response['data'][0]['materials'][0]['required_quantity']);
        $this->assertSame(['TEST-MISSING-BOM'], $response['missing_items']);
    }

    public function test_it_converts_yield_to_material_need_and_rounds_physical_units(): void
    {
        $controller = app(InternalProductBomController::class);
        $saved = $controller->save(Request::create('/api/dinh-muc-san-xuat/luu', 'POST', [
            'item_code' => 'TEST-SHEET-FG',
            'item_name' => 'Finished item made from film sheet',
            'unit' => 'PCS',
            'operations' => [
                ['operation_code' => 'IN', 'operation_name' => 'In'],
            ],
            'materials' => [[
                'material_code' => 'TEST-FILM-100MIC',
                'material_name' => 'PHIM IN CHUYỂN MỜ 100 MIC',
                'component_role' => 'IN',
                'calculation_mode' => 'yield',
                'yield_quantity' => 1840,
                'unit' => 'TẤM',
                'waste_percent' => 0,
                'round_to_whole' => false,
                'operation_code' => 'IN',
            ]],
        ]))->getData(true);

        $line = $saved['data']['lines'][0];
        $this->assertSame('yield', $line['calculation_mode']);
        $this->assertEquals(1840, $line['yield_quantity']);
        $this->assertFalse($line['round_to_whole']);
        $this->assertEqualsWithDelta(1 / 1840, $line['consumption_per_unit'], 0.0000001);

        InternalProductionOrder::query()->create([
            'production_order' => 'TEST-ORDER-SHEET',
            'item_code' => 'TEST-SHEET-FG',
            'standard_item_code' => 'TEST-SHEET-FG',
            'description' => 'Finished item made from film sheet',
            'order_quantity' => 7500,
            'is_active' => true,
        ]);

        $needs = $controller->orderNeeds(Request::create('/api/dinh-muc-san-xuat/nhu-cau', 'GET', [
            'production_order' => 'TEST-ORDER-SHEET',
        ]))->getData(true);
        $material = $needs['data'][0]['materials'][0];

        $this->assertSame('yield', $material['calculation_mode']);
        $this->assertEquals(1840, $material['yield_quantity']);
        $this->assertEqualsWithDelta(4.076087, $material['exact_required_quantity'], 0.000001);
        $this->assertEquals(5.0, $material['required_quantity']);

        $controller->snapshotOrder(Request::create('/api/dinh-muc-san-xuat/chot-lenh', 'POST', [
            'production_order' => 'TEST-ORDER-SHEET',
        ]));
        $snapshotted = $controller->orderNeeds(Request::create('/api/dinh-muc-san-xuat/nhu-cau', 'GET', [
            'production_order' => 'TEST-ORDER-SHEET',
        ]))->getData(true)['data'][0];

        $this->assertTrue($snapshotted['is_snapshot']);
        $this->assertEqualsWithDelta(4.076087, $snapshotted['materials'][0]['exact_required_quantity'], 0.000001);
        $this->assertEquals(5.0, $snapshotted['materials'][0]['required_quantity']);

        InternalProductionOrder::query()->create([
            'production_order' => 'TEST-ORDER-SHEET-02',
            'item_code' => 'TEST-SHEET-FG',
            'standard_item_code' => 'TEST-SHEET-FG',
            'description' => 'Second order using the same film sheet',
            'order_quantity' => 18400,
            'is_active' => true,
        ]);
        $aggregate = $controller->aggregateOrderNeeds(Request::create(
            '/api/dinh-muc-san-xuat/tong-hop-cap-vat-tu',
            'GET',
            ['production_orders' => ['TEST-ORDER-SHEET', 'TEST-ORDER-SHEET-02']]
        ))->getData(true);

        $this->assertCount(2, $aggregate['data']);
        $this->assertSame([], $aggregate['missing_orders']);
        $rowsByOrder = collect($aggregate['data'])->keyBy('production_order');
        $this->assertEquals(5.0, $rowsByOrder['TEST-ORDER-SHEET']['required_quantity']);
        $this->assertEquals(0.0, $rowsByOrder['TEST-ORDER-SHEET']['issued_quantity']);
        $this->assertEquals(5.0, $rowsByOrder['TEST-ORDER-SHEET']['suggested_quantity']);
        $this->assertEquals(10.0, $rowsByOrder['TEST-ORDER-SHEET-02']['required_quantity']);
    }

    public function test_it_expands_a_mixing_formula_into_real_material_quantities(): void
    {
        $controller = app(InternalProductBomController::class);
        $saved = $controller->save(Request::create('/api/dinh-muc-san-xuat/luu', 'POST', [
            'item_code' => 'TEST-SILICONE-FG',
            'unit' => 'PCS',
            'operations' => [['operation_code' => 'DUC', 'operation_name' => 'Đúc']],
            'materials' => [
                [
                    'material_code' => 'TEST-SY1008',
                    'component_role' => 'PHA',
                    'calculation_mode' => 'formula',
                    'formula_code' => 'PHA-SILICONE',
                    'formula_output_per_unit' => 2,
                    'formula_output_unit' => 'G',
                    'formula_part' => 100,
                    'unit' => 'KG',
                    'operation_code' => 'DUC',
                ],
                [
                    'material_code' => 'TEST-TANG-BAM-6',
                    'component_role' => 'PHA',
                    'calculation_mode' => 'formula',
                    'formula_code' => 'PHA-SILICONE',
                    'formula_output_per_unit' => 2,
                    'formula_output_unit' => 'G',
                    'formula_part' => 10,
                    'unit' => 'KG',
                    'operation_code' => 'DUC',
                ],
            ],
        ]))->getData(true);

        $this->assertEqualsWithDelta(0.001818182, $saved['data']['lines'][0]['consumption_per_unit'], 0.000000001);
        $this->assertEqualsWithDelta(0.000181818, $saved['data']['lines'][1]['consumption_per_unit'], 0.000000001);

        InternalProductionOrder::query()->create([
            'production_order' => 'TEST-ORDER-SILICONE',
            'item_code' => 'TEST-SILICONE-FG',
            'standard_item_code' => 'TEST-SILICONE-FG',
            'description' => 'Silicone label',
            'order_quantity' => 42000,
            'is_active' => true,
        ]);

        $materials = $controller->orderNeeds(Request::create('/api/dinh-muc-san-xuat/nhu-cau', 'GET', [
            'production_order' => 'TEST-ORDER-SILICONE',
        ]))->getData(true)['data'][0]['materials'];

        $this->assertSame('formula', $materials[0]['calculation_mode']);
        $this->assertEqualsWithDelta(76.363636, $materials[0]['required_quantity'], 0.00001);
        $this->assertEqualsWithDelta(7.636364, $materials[1]['required_quantity'], 0.00001);
        $this->assertEqualsWithDelta(84.0, $materials[0]['required_quantity'] + $materials[1]['required_quantity'], 0.000001);
    }

    public function test_snapshot_keeps_the_original_requirement_after_the_profile_changes(): void
    {
        $controller = app(InternalProductBomController::class);
        $payload = [
            'item_code' => 'TEST-SNAPSHOT-FG',
            'unit' => 'PCS',
            'operations' => [['operation_code' => 'IN', 'operation_name' => 'In']],
            'materials' => [[
                'material_code' => 'TEST-SNAPSHOT-MATERIAL',
                'component_role' => 'CHUNG',
                'unit' => 'KG',
                'consumption_per_unit' => 0.01,
                'waste_percent' => 0,
                'operation_code' => 'IN',
            ]],
        ];
        $controller->save(Request::create('/api/dinh-muc-san-xuat/luu', 'POST', $payload));

        InternalProductionOrder::query()->create([
            'production_order' => 'TEST-SNAPSHOT-ORDER',
            'item_code' => 'TEST-SNAPSHOT-FG',
            'standard_item_code' => 'TEST-SNAPSHOT-FG',
            'order_quantity' => 100,
            'is_active' => true,
        ]);
        $controller->snapshotOrder(Request::create('/api/dinh-muc-san-xuat/chot-lenh', 'POST', [
            'production_order' => 'TEST-SNAPSHOT-ORDER',
        ]));

        $payload['materials'][0]['consumption_per_unit'] = 0.02;
        $controller->save(Request::create('/api/dinh-muc-san-xuat/luu', 'POST', $payload));

        $snapshotted = $controller->orderNeeds(Request::create('/api/dinh-muc-san-xuat/nhu-cau', 'GET', [
            'production_order' => 'TEST-SNAPSHOT-ORDER',
        ]))->getData(true)['data'][0];

        $this->assertTrue($snapshotted['is_snapshot']);
        $this->assertSame(1, $snapshotted['bom_revision']);
        $this->assertEquals(1.0, $snapshotted['materials'][0]['required_quantity']);
    }

    public function test_aggregate_reserves_shared_stock_in_order_and_reports_the_shortage(): void
    {
        $controller = app(InternalProductBomController::class);
        $controller->save(Request::create('/api/dinh-muc-san-xuat/luu', 'POST', [
            'item_code' => 'TEST-STOCK-FG',
            'unit' => 'PCS',
            'operations' => [['operation_code' => 'IN', 'operation_name' => 'In']],
            'materials' => [[
                'material_code' => 'TEST-SHARED-STOCK',
                'component_role' => 'CHUNG',
                'unit' => 'KG',
                'consumption_per_unit' => 1,
                'waste_percent' => 0,
                'operation_code' => 'IN',
            ]],
        ]));
        InternalItemCatalog::query()->create([
            'item_code' => 'TEST-SHARED-STOCK',
            'item_name' => 'Shared stock',
            'unit' => 'KG',
            'is_active' => true,
        ]);
        $location = WarehouseLocation::query()->create([
            'location_code' => 'TEST-BOM-STOCK-' . uniqid(),
            'warehouse_code' => 'KTPHAM',
            'status' => 'active',
        ]);
        InventoryPackage::query()->create([
            'package_code' => 'TEST-BOM-PKG-' . uniqid(),
            'warehouse_location_id' => $location->id,
            'ma_sp' => 'TEST-SHARED-STOCK',
            'ma_ko' => 'KTPHAM',
            'internal_item_code' => 'TEST-SHARED-STOCK',
            'quantity' => 10,
            'checked_at' => '2026-08-01',
        ]);
        foreach (['01', '02'] as $suffix) {
            InternalProductionOrder::query()->create([
                'production_order' => 'TEST-STOCK-ORDER-' . $suffix,
                'item_code' => 'TEST-STOCK-FG',
                'standard_item_code' => 'TEST-STOCK-FG',
                'order_quantity' => 7,
                'is_active' => true,
            ]);
        }

        $rows = $controller->aggregateOrderNeeds(Request::create(
            '/api/dinh-muc-san-xuat/tong-hop-cap-vat-tu',
            'GET',
            ['production_orders' => ['TEST-STOCK-ORDER-01', 'TEST-STOCK-ORDER-02']]
        ))->getData(true)['data'];

        $this->assertCount(2, $rows);
        $this->assertEquals(10.0, $rows[0]['available_quantity']);
        $this->assertEquals(7.0, $rows[0]['stock_allocatable_quantity']);
        $this->assertEquals(0.0, $rows[0]['shortage_quantity']);
        $this->assertSame('enough', $rows[0]['stock_status']);
        $this->assertEquals(3.0, $rows[1]['available_quantity']);
        $this->assertEquals(3.0, $rows[1]['stock_allocatable_quantity']);
        $this->assertEquals(4.0, $rows[1]['shortage_quantity']);
        $this->assertSame('short', $rows[1]['stock_status']);
    }

    public function test_aggregate_query_count_does_not_grow_with_the_number_of_orders(): void
    {
        $controller = app(InternalProductBomController::class);
        $controller->save(Request::create('/api/dinh-muc-san-xuat/luu', 'POST', [
            'item_code' => 'TEST-BATCH-FG',
            'unit' => 'PCS',
            'operations' => [['operation_code' => 'IN', 'operation_name' => 'In']],
            'materials' => [[
                'material_code' => 'TEST-BATCH-MATERIAL',
                'component_role' => 'CHUNG',
                'unit' => 'KG',
                'consumption_per_unit' => 0.1,
                'operation_code' => 'IN',
            ]],
        ]));
        $codes = [];
        foreach (range(1, 20) as $index) {
            $codes[] = $code = 'TEST-BATCH-ORDER-' . str_pad((string) $index, 2, '0', STR_PAD_LEFT);
            InternalProductionOrder::query()->create([
                'production_order' => $code,
                'item_code' => 'TEST-BATCH-FG',
                'standard_item_code' => 'TEST-BATCH-FG',
                'order_quantity' => 10,
                'is_active' => true,
            ]);
        }

        DB::connection('internal')->flushQueryLog();
        DB::connection('internal')->enableQueryLog();
        $response = $controller->aggregateOrderNeeds(Request::create(
            '/api/dinh-muc-san-xuat/tong-hop-cap-vat-tu',
            'GET',
            ['production_orders' => $codes]
        ))->getData(true);
        $queryCount = count(DB::connection('internal')->getQueryLog());
        DB::connection('internal')->disableQueryLog();

        $this->assertCount(20, $response['data']);
        $this->assertLessThanOrEqual(12, $queryCount);
    }

    public function test_central_order_exposes_lifecycle_and_updates_an_operation(): void
    {
        $bomController = app(InternalProductBomController::class);
        $bomController->save(Request::create('/api/dinh-muc-san-xuat/luu', 'POST', [
            'item_code' => 'TEST-LIFECYCLE-FG',
            'item_name' => 'Lifecycle finished item',
            'unit' => 'PCS',
            'operations' => [
                ['operation_code' => 'DET', 'operation_name' => 'Dệt'],
                ['operation_code' => 'KCS', 'operation_name' => 'KCS'],
            ],
            'materials' => [[
                'material_code' => 'TEST-LIFECYCLE-YARN',
                'component_role' => 'CHUNG',
                'unit' => 'KG',
                'consumption_per_unit' => 0.01,
                'waste_percent' => 0,
                'operation_code' => 'DET',
            ]],
        ]));

        InternalProductionOrder::query()->create([
            'production_order' => 'TEST-LIFECYCLE-ORDER',
            'item_code' => 'TEST-LIFECYCLE-FG',
            'standard_item_code' => 'TEST-LIFECYCLE-FG',
            'description' => 'Lifecycle finished item',
            'order_quantity' => 100,
            'is_active' => true,
        ]);

        $controller = app(InternalProductionOrderController::class);
        $before = $controller->workflow(Request::create('/api/lenh-san-xuat-trung-tam', 'GET', [
            'keyword' => 'TEST-LIFECYCLE-ORDER',
        ]))->getData(true)['data'][0];

        $this->assertTrue($before['lifecycle']['bom_complete']);
        $this->assertSame('Xuất vật tư', $before['lifecycle']['current_stage']);
        $this->assertCount(2, $before['lifecycle']['operations']);

        $updated = $controller->updateOperationProgress(Request::create(
            '/api/lenh-san-xuat-trung-tam/cong-doan',
            'PATCH',
            [
                'production_order' => 'TEST-LIFECYCLE-ORDER',
                'operation_code' => 'DET',
                'status' => 'in_progress',
                'updated_by' => 'KCS test',
            ]
        ));

        $this->assertSame(200, $updated->getStatusCode());
        $this->assertDatabaseHas('internal_production_operation_progress', [
            'production_order_code' => 'TEST-LIFECYCLE-ORDER',
            'operation_code' => 'DET',
            'status' => 'in_progress',
        ], 'internal');

        $after = $controller->workflow(Request::create('/api/lenh-san-xuat-trung-tam', 'GET', [
            'keyword' => 'TEST-LIFECYCLE-ORDER',
        ]))->getData(true)['data'][0];
        $operation = collect($after['lifecycle']['operations'])->firstWhere('code', 'DET');

        $this->assertSame('in_progress', $operation['status']);
        $this->assertSame('KCS test', $operation['updated_by']);
    }
}
