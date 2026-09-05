<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use App\Models\InternalProductionOrder;
use Tests\TestCase;

class InternalSupplementalProductionOrderTest extends TestCase
{
    public function test_it_creates_a_reusable_supplemental_order_with_total_quantity(): void
    {
        $response = $this->postJson('/api/lenh-san-xuat-trung-tam/lenh-phu', [
            'base_item_code' => '108972',
            'order_quantity' => 50000,
            'customer' => 'UNIPAX',
            'purchase_order' => 'PO-TEST',
            'received_date' => '2026-09-05',
            'unit' => 'PCS',
        ])->assertCreated()
            ->assertJsonPath('data.item_code', '108972')
            ->assertJsonPath('data.order_quantity', 50000)
            ->assertJsonPath('data.is_variant_parent', true);

        $orderCode = $response->json('data.production_order');
        $this->assertMatchesRegularExpression('/^LSP-2026-\d{4}$/', $orderCode);
        $this->assertDatabaseHas('internal_production_orders', [
            'production_order' => $orderCode,
            'item_code' => '108972',
            'order_quantity' => 50000,
            'sync_batch' => 'manual-supplemental',
            'is_active' => 1,
        ], 'internal');

        $this->getJson('/api/lenh-san-xuat-sheet?keyword=108972&with_progress=1&order_date_to=2026-09-05')
            ->assertOk()
            ->assertJsonFragment([
                'production_order' => $orderCode,
                'planned_quantity' => 50000,
                'remaining_quantity' => 50000,
            ]);

        InternalProductionOrder::query()->where('production_order', $orderCode)->delete();
    }

    public function test_total_quantity_is_required_for_a_supplemental_order(): void
    {
        $this->postJson('/api/lenh-san-xuat-trung-tam/lenh-phu', [
            'base_item_code' => '108972',
            'order_quantity' => 0,
            'received_date' => '2026-09-05',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('order_quantity');
    }

    public function test_quick_receipt_page_exposes_the_supplemental_order_form(): void
    {
        $this->get('/client/nhap-thanh-pham-nhanh')
            ->assertOk()
            ->assertSee('Tạo lệnh phụ')
            ->assertSee('Tổng đơn hàng');
    }

    public function test_supplemental_order_appears_immediately_in_production_activity_search(): void
    {
        $baseCode = 'SUP-' . random_int(100000, 999999);
        $this->getJson('/api/lenh-san-xuat-trung-tam/tim-kiem?keyword=' . $baseCode)
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $versionBefore = (int) Cache::get('internal_production_order_search_version', 1);

        $response = $this->postJson('/api/lenh-san-xuat-trung-tam/lenh-phu', [
            'base_item_code' => $baseCode,
            'order_quantity' => 12000,
            'received_date' => '2026-09-05',
            'unit' => 'PCS',
        ])->assertCreated();
        $orderCode = $response->json('data.production_order');

        $this->assertGreaterThan($versionBefore, (int) Cache::get('internal_production_order_search_version'));
        $this->getJson('/api/lenh-san-xuat-trung-tam/tim-kiem?keyword=' . $baseCode)
            ->assertOk()
            ->assertJsonFragment([
                'production_order' => $orderCode,
                'order_type' => 'supplemental',
                'order_quantity' => 12000,
            ]);
        $this->getJson('/api/ghi-nhan-san-xuat?production_order=' . urlencode($orderCode))
            ->assertOk()
            ->assertJsonPath('data.order_type', 'supplemental')
            ->assertJsonPath('data.order_quantity', 12000);

        InternalProductionOrder::query()->where('production_order', $orderCode)->delete();
    }
}
