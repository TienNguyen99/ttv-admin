<?php

namespace Tests\Feature;

use App\Models\InternalItemCatalog;
use App\Models\InternalMaterialIssue;
use App\Models\InternalMaterialIssueLine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InternalMaterialIssueIdempotencyTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = ['internal'];

    public function test_retrying_the_same_request_returns_one_issue_without_duplicate_lines(): void
    {
        [$payload, $key] = $this->draftPayload();

        $first = $this->postJson('/api/xuat-vat-tu-noi-bo', $payload)->assertOk();
        $second = $this->postJson('/api/xuat-vat-tu-noi-bo', $payload)
            ->assertOk()
            ->assertJsonPath('idempotent_replay', true);

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, InternalMaterialIssue::query()->where('idempotency_key', $key)->count());
        $this->assertSame(1, InternalMaterialIssueLine::query()->where('issue_id', $first->json('data.id'))->count());
    }

    public function test_reusing_a_key_for_changed_data_is_rejected(): void
    {
        [$payload, $key] = $this->draftPayload();
        $this->postJson('/api/xuat-vat-tu-noi-bo', $payload)->assertOk();

        $payload['lines'][0]['quantity'] = 2;
        $this->postJson('/api/xuat-vat-tu-noi-bo', $payload)
            ->assertStatus(409)
            ->assertJsonPath('idempotency_conflict', true);

        $this->assertSame(1, InternalMaterialIssue::query()->where('idempotency_key', $key)->count());
    }

    private function draftPayload(): array
    {
        $code = 'IDEMP-' . uniqid();
        $key = 'issue-' . uniqid('', true);
        InternalItemCatalog::query()->create([
            'item_code' => $code,
            'item_name' => 'Idempotency test item',
            'unit' => 'PCS',
            'is_active' => true,
        ]);

        return [[
            'idempotency_key' => $key,
            'issue_type' => 'customer',
            'issue_date' => '2026-08-28',
            'receiver_name' => 'TEST',
            'department' => 'Kinh doanh',
            'purpose' => 'Test idempotency',
            'save_as_draft' => true,
            'lines' => [[
                'ma_hh' => $code,
                'internal_item_code' => $code,
                'ten_hh' => 'Idempotency test item',
                'dvt' => 'PCS',
                'quantity' => 1,
            ]],
        ], $key];
    }
}
