<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InternalFinishedGoodsVariantGuardTest extends TestCase
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

    public function test_receipt_api_rejects_one_internal_code_with_multiple_size_variants(): void
    {
        $response = $this->postJson('/api/kiem-ton-kho/phieu-nhap-tp', [
            'receipt_kind' => 'finished',
            'checked_at' => '2026-09-03',
            'location_code' => 'CHUA-XEP',
            'lines' => [
                [
                    'internal_item_code' => 'TEST-SIZE-GUARD',
                    'category' => 'NHAN SIZE',
                    'size' => '15',
                    'dvt' => 'PCS',
                    'quantity' => 100,
                ],
                [
                    'internal_item_code' => 'TEST-SIZE-GUARD',
                    'category' => 'NHAN SIZE',
                    'size' => '15.5',
                    'dvt' => 'PCS',
                    'quantity' => 100,
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('unsplit_variants.0.code', 'TEST-SIZE-GUARD')
            ->assertJsonCount(2, 'unsplit_variants.0.variants');
        $this->assertDatabaseMissing('internal_material_receipt_lines', [
            'internal_item_code' => 'TEST-SIZE-GUARD',
        ], 'internal');
    }

    public function test_quick_receipt_page_contains_catalog_only_variant_flow(): void
    {
        $this->get('/client/nhap-thanh-pham-nhanh')
            ->assertOk()
            ->assertSee('prepareCatalogOnlyVariantSplit', false)
            ->assertSee('Mỗi size/màu được lưu thành một mã danh mục riêng.', false);
    }
}
