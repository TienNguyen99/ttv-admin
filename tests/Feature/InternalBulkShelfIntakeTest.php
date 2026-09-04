<?php

namespace Tests\Feature;

use App\Models\InternalItemCatalog;
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
}
