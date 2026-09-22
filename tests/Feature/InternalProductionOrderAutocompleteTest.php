<?php

namespace Tests\Feature;

use App\Models\InternalProductionOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class InternalProductionOrderAutocompleteTest extends TestCase
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

    public function test_targeted_quick_receipt_search_includes_active_order_without_received_date(): void
    {
        $suffix = Str::upper(Str::random(8));
        $orderCode = "T-NODATE-{$suffix}/26";

        InternalProductionOrder::query()->create([
            'row_key' => hash('sha256', $orderCode),
            'production_order' => $orderCode,
            'item_code' => 'ITEM-' . $suffix,
            'description' => 'Order without received date',
            'unit' => 'PCS',
            'order_quantity' => 90,
            'received_date' => null,
            'promised_date' => '2026-10-09',
            'status' => 'scheduled',
            'sync_batch' => 'autocomplete-test',
            'is_active' => true,
            'raw_data' => [],
        ]);

        $this->getJson('/api/lenh-san-xuat-sheet?' . http_build_query([
            'keyword' => $orderCode,
            'with_progress' => 1,
            'order_date_to' => '2026-09-10',
            'limit' => 30,
        ]))
            ->assertOk()
            ->assertJsonPath('data.0.production_order', $orderCode)
            ->assertJsonPath('data.0.remaining_quantity', 90);
    }

    public function test_targeted_quick_receipt_search_includes_order_received_after_receipt_date(): void
    {
        $suffix = Str::upper(Str::random(8));
        $orderCode = 'T-FUTURE-' . $suffix . '/26';

        InternalProductionOrder::query()->create([
            'row_key' => hash('sha256', $orderCode),
            'production_order' => $orderCode,
            'item_code' => 'ITEM-' . $suffix,
            'description' => 'Future order found by targeted search',
            'unit' => 'PCS',
            'order_quantity' => 120,
            'received_date' => '2026-10-08',
            'status' => 'pending',
            'sync_batch' => 'autocomplete-test',
            'is_active' => true,
            'raw_data' => [],
        ]);

        $this->getJson('/api/lenh-san-xuat-sheet?' . http_build_query([
            'keyword' => $orderCode,
            'with_progress' => 1,
            'order_date_to' => '2026-09-14',
            'limit' => 30,
        ]))
            ->assertOk()
            ->assertJsonPath('data.0.production_order', $orderCode)
            ->assertJsonPath('data.0.received_date', '2026-10-08')
            ->assertJsonPath('data.0.remaining_quantity', 120);
    }
}
