<?php

namespace Tests\Feature;

use App\Models\InternalProductionOrder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InternalProductionOrderSearchTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = ['internal'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
    }

    public function test_search_returns_all_variants_of_a_matching_order(): void
    {
        foreach ([
            ['source_row' => 991001, 'standard_item_code' => 'SEARCH-ITEM-BLACK', 'color' => 'BLACK'],
            ['source_row' => 991002, 'standard_item_code' => 'SEARCH-ITEM-PINK', 'color' => 'PINK'],
        ] as $index => $line) {
            InternalProductionOrder::query()->create($line + [
                'row_key' => hash('sha256', 'search-order-' . $index),
                'production_order' => 'T-SEARCH-ORDER',
                'item_code' => 'SEARCH-ITEM',
                'description' => 'Search item',
                'order_quantity' => 100,
                'unit' => 'PCS',
                'is_active' => true,
                'raw_data' => [],
            ]);
        }

        $this->getJson('/api/lenh-san-xuat-trung-tam/tim-kiem?keyword=SEARCH-ITEM-BLACK&limit=20')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.production_order', 'T-SEARCH-ORDER')
            ->assertJsonCount(2, 'data.0.items')
            ->assertJsonPath('data.0.items.0.item_code', 'SEARCH-ITEM-BLACK')
            ->assertJsonPath('data.0.items.1.item_code', 'SEARCH-ITEM-PINK');
    }

    public function test_search_requires_at_least_two_characters(): void
    {
        $this->getJson('/api/lenh-san-xuat-trung-tam/tim-kiem?keyword=T')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }
}
