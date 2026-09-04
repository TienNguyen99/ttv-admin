<?php

namespace Tests\Feature;

use Tests\TestCase;

class InternalMaterialDemandTest extends TestCase
{
    public function test_material_demand_page_is_available(): void
    {
        $this->get('/client/nhu-cau-mua-vat-tu')
            ->assertOk()
            ->assertSee('Nhu cầu mua vật tư')
            ->assertSee('Phân tích phiếu kho nội bộ và lịch sử XNT đã đồng bộ.');
    }

    public function test_material_demand_api_returns_a_paginated_empty_period(): void
    {
        $this->getJson('/api/nhu-cau-mua-vat-tu?as_of=1900-01-01&window=3&per_page=10&fresh=1')
            ->assertOk()
            ->assertJsonPath('summary.window_months', 3)
            ->assertJsonPath('summary.item_count', 0)
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_material_demand_api_rejects_an_unsupported_window(): void
    {
        $this->getJson('/api/nhu-cau-mua-vat-tu?window=2')
            ->assertStatus(422)
            ->assertJsonValidationErrors('window');
    }
}
