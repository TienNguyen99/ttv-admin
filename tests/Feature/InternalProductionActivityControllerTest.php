<?php

namespace Tests\Feature;

use App\Models\InternalProductBomProfile;
use App\Models\InternalProductionActivity;
use App\Models\InternalProductionOrder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InternalProductionActivityControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = ['internal'];

    public function test_it_records_variant_quantity_for_an_operation_without_changing_stock(): void
    {
        InternalProductionOrder::query()->create([
            'source_row' => 990001,
            'production_order' => 'T-TEST-ACTIVITY',
            'item_code' => '108333',
            'standard_item_code' => '108333-1AB',
            'description' => 'Test item',
            'order_quantity' => 18000,
            'unit' => 'PCS',
            'is_active' => true,
            'raw_data' => [],
        ]);
        $profile = InternalProductBomProfile::query()->create([
            'item_code' => '108333',
            'revision' => 1,
            'status' => 'active',
        ]);
        $profile->routings()->create(['sequence' => 1, 'operation_code' => 'IN', 'operation_name' => 'In']);
        $profile->routings()->create(['sequence' => 2, 'operation_code' => 'EP', 'operation_name' => 'Ép']);

        $response = $this->postJson('/api/ghi-nhan-san-xuat', [
            'activity_date' => '2026-08-27',
            'production_order' => 'T-TEST-ACTIVITY',
            'operation_code' => 'IN',
            'lines' => [[
                'internal_item_code' => '108333-1AB',
                'good_quantity' => 18000,
                'defect_quantity' => 0,
            ]],
        ]);

        $response->assertCreated()->assertJsonPath('order.operations.0.percent', 100);
        $this->assertDatabaseHas('internal_production_activity_lines', [
            'internal_item_code' => '108333-1AB',
            'good_quantity' => 18000,
        ], 'internal');
    }

    public function test_it_records_a_standard_operation_without_a_bom(): void
    {
        InternalProductionOrder::query()->create([
            'source_row' => 990002,
            'production_order' => 'T-TEST-NO-ROUTE',
            'item_code' => 'NO-BOM',
            'description' => 'No route',
            'order_quantity' => 10,
            'unit' => 'PCS',
            'is_active' => true,
            'raw_data' => [],
        ]);

        $this->postJson('/api/ghi-nhan-san-xuat', [
            'activity_date' => '2026-08-27',
            'production_order' => 'T-TEST-NO-ROUTE',
            'operation_code' => 'IN',
            'lines' => [['internal_item_code' => 'NO-BOM', 'good_quantity' => 5]],
        ])->assertCreated()
            ->assertJsonPath('data.operation_code', 'IN')
            ->assertJsonPath('data.operation_name', 'In');

        $this->assertDatabaseHas('internal_production_activity_lines', [
            'internal_item_code' => 'NO-BOM',
            'good_quantity' => 5,
        ], 'internal');
        $this->assertDatabaseCount('internal_production_operation_progress', 1, 'internal');
        $this->assertDatabaseHas('internal_production_operation_progress', [
            'production_order_code' => 'T-TEST-NO-ROUTE',
            'operation_code' => 'IN',
        ], 'internal');
    }

    public function test_it_rejects_an_unknown_operation(): void
    {
        InternalProductionOrder::query()->create([
            'source_row' => 990003,
            'production_order' => 'T-TEST-UNKNOWN-OPERATION',
            'item_code' => 'NO-BOM-2',
            'description' => 'No route',
            'order_quantity' => 10,
            'unit' => 'PCS',
            'is_active' => true,
            'raw_data' => [],
        ]);

        $this->postJson('/api/ghi-nhan-san-xuat', [
            'activity_date' => '2026-08-27',
            'production_order' => 'T-TEST-UNKNOWN-OPERATION',
            'operation_code' => 'UNKNOWN',
            'lines' => [['internal_item_code' => 'NO-BOM-2', 'good_quantity' => 5]],
        ])->assertStatus(422)->assertJsonPath('message', 'Công đoạn không hợp lệ.');
    }
}
