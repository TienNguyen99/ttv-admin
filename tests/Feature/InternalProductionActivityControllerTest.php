<?php

namespace Tests\Feature;

use App\Models\InternalProductBomProfile;
use App\Models\InternalProductionActivity;
use App\Models\InternalProductionOperationProgress;
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

        $response = $this->postJson('/api/ghi-nhan-san-xuat', [
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
        $this->assertSame(1, InternalProductionOperationProgress::query()
            ->where('production_order_code', 'T-TEST-NO-ROUTE')
            ->count());
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

    public function test_it_creates_a_variant_for_a_supplemental_order_and_records_production(): void
    {
        $parent = $this->createSupplementalOrder('LSP-TEST-VARIANT', '108333', 40000);

        $this->postJson('/api/ghi-nhan-san-xuat/bien-the', [
            'production_order' => 'LSP-TEST-VARIANT',
            'internal_item_code' => '108333-2AB',
            'planned_quantity' => 18000,
            'color' => 'BLACK',
            'unit' => 'PCS',
        ])->assertCreated()
            ->assertJsonPath('order.order_type', 'supplemental')
            ->assertJsonPath('order.order_quantity', 40000)
            ->assertJsonPath('order.variant_allocated_quantity', 18000)
            ->assertJsonPath('order.variant_remaining_quantity', 22000)
            ->assertJsonPath('order.items.0.internal_item_code', '108333-2AB')
            ->assertJsonPath('order.items.0.planned_quantity', 18000);

        $this->assertDatabaseHas('internal_production_orders', [
            'production_order' => 'LSP-TEST-VARIANT',
            'standard_item_code' => '108333-2AB',
            'variant_parent_id' => $parent->id,
            'is_manual_variant' => 1,
            'is_active' => 1,
        ], 'internal');
        $this->assertDatabaseHas('internal_production_orders', ['id' => $parent->id, 'is_active' => 0], 'internal');

        $response = $this->postJson('/api/ghi-nhan-san-xuat', [
            'activity_date' => '2026-09-05',
            'production_order' => 'LSP-TEST-VARIANT',
            'operation_code' => 'IN',
            'next_operation_code' => 'CAT',
            'lines' => [[
                'internal_item_code' => '108333-2AB',
                'good_quantity' => 10000,
                'defect_quantity' => 0,
            ]],
        ])->assertCreated();

        $progress = collect($response->json('order.items.0.operation_progress'))->firstWhere('operation_code', 'IN');
        $this->assertSame(10000, $progress['good_quantity']);
        $this->assertSame(8000, $progress['remaining_quantity']);
        $this->assertNotEmpty($response->json('data.transfer_code'));
        $this->assertSame('CAT', $response->json('data.next_operation_code'));
        $this->assertSame('issued', $response->json('data.transfer_status'));

        $printUrl = $response->json('order.activities.0.transfer_print_url');
        $this->get(parse_url($printUrl, PHP_URL_PATH))
            ->assertOk()
            ->assertSee('PHIẾU CHUYỂN CÔNG ĐOẠN')
            ->assertSee('108333-2AB')
            ->assertSee('In')
            ->assertSee('Cắt');
    }

    public function test_supplemental_variant_creation_is_idempotent_and_enforces_base_and_total(): void
    {
        $this->createSupplementalOrder('LSP-TEST-VARIANT-RULES', '108333', 20000);
        $payload = [
            'production_order' => 'LSP-TEST-VARIANT-RULES',
            'internal_item_code' => '108333-2AB',
            'planned_quantity' => 12000,
        ];

        $this->postJson('/api/ghi-nhan-san-xuat/bien-the', $payload)->assertCreated();
        $this->postJson('/api/ghi-nhan-san-xuat/bien-the', $payload)
            ->assertOk()
            ->assertJsonPath('order.variant_allocated_quantity', 12000);
        $this->assertSame(1, InternalProductionOrder::query()
            ->where('production_order', 'LSP-TEST-VARIANT-RULES')
            ->where('standard_item_code', '108333-2AB')
            ->count());

        $this->postJson('/api/ghi-nhan-san-xuat/bien-the', [
            'production_order' => 'LSP-TEST-VARIANT-RULES',
            'internal_item_code' => '999999-1AB',
            'planned_quantity' => 1000,
        ])->assertStatus(422);
        $this->postJson('/api/ghi-nhan-san-xuat/bien-the', [
            'production_order' => 'LSP-TEST-VARIANT-RULES',
            'internal_item_code' => '108333-3AB',
            'planned_quantity' => 9000,
        ])->assertStatus(422);
    }

    public function test_it_can_create_a_transfer_for_an_existing_production_record(): void
    {
        InternalProductionOrder::query()->create([
            'row_key' => hash('sha256', 'TEST-OLD-ACTIVITY-TRANSFER'),
            'production_order' => 'LSP-TEST-OLD-ACTIVITY',
            'item_code' => '108333',
            'standard_item_code' => '108333-2AB',
            'description' => 'Printed variant',
            'order_quantity' => 33300,
            'unit' => 'PCS',
            'is_active' => true,
            'raw_data' => [],
        ]);
        $record = $this->postJson('/api/ghi-nhan-san-xuat', [
            'activity_date' => '2026-09-05',
            'production_order' => 'LSP-TEST-OLD-ACTIVITY',
            'operation_code' => 'IN',
            'lines' => [['internal_item_code' => '108333-2AB', 'good_quantity' => 33300]],
        ])->assertCreated();

        $activityId = $record->json('data.id');
        $this->postJson('/api/ghi-nhan-san-xuat/' . $activityId . '/phieu-chuyen', [
            'next_operation_code' => 'CAT',
        ])->assertCreated()
            ->assertJsonPath('order.activities.0.next_operation_code', 'CAT')
            ->assertJsonPath('order.activities.0.transfer_status', 'issued');

        $this->postJson('/api/ghi-nhan-san-xuat/' . $activityId . '/phieu-chuyen', [
            'next_operation_code' => 'CAT',
        ])->assertOk();
    }

    public function test_production_entry_page_contains_supplemental_variant_form(): void
    {
        $this->get('/client/ghi-nhan-san-xuat')
            ->assertOk()
            ->assertSee('variantPanel', false)
            ->assertSee('/api/ghi-nhan-san-xuat/bien-the', false);
    }

    private function createSupplementalOrder(string $orderCode, string $baseCode, float $quantity): InternalProductionOrder
    {
        return InternalProductionOrder::query()->create([
            'row_key' => hash('sha256', 'TEST-SUPPLEMENTAL|' . $orderCode),
            'production_order' => $orderCode,
            'item_code' => $baseCode,
            'description' => 'Supplemental item',
            'order_quantity' => $quantity,
            'unit' => 'PCS',
            'is_variant_parent' => true,
            'is_manual_variant' => false,
            'sync_batch' => 'manual-supplemental',
            'is_active' => true,
            'raw_data' => [
                '_internal_order_type' => 'supplemental',
                '_internal_order' => [
                    'base_item_code' => $baseCode,
                    'total_order_quantity' => $quantity,
                ],
            ],
        ]);
    }
}
