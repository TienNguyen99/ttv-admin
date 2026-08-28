<?php

namespace Tests\Feature;

use App\Models\InternalItemCatalog;
use App\Models\InternalMaterialIssue;
use App\Models\InternalMaterialIssueLine;
use App\Models\InternalMaterialOrderAllocation;
use App\Services\InternalMaterialIssueLineService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class InternalMaterialIssueWriteSafetyTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = ['internal'];

    public function test_store_rolls_back_header_when_line_creation_fails(): void
    {
        $payload = $this->draftPayload('ROLLBACK-' . uniqid());
        $key = $payload['idempotency_key'];

        $lineService = Mockery::mock(InternalMaterialIssueLineService::class);
        $lineService->shouldReceive('create')
            ->once()
            ->andThrow(new RuntimeException('Forced line failure'));
        $this->app->instance(InternalMaterialIssueLineService::class, $lineService);

        $this->withoutExceptionHandling();
        try {
            $this->postJson('/api/xuat-vat-tu-noi-bo', $payload);
            $this->fail('The forced line failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced line failure', $exception->getMessage());
        } finally {
            $this->withExceptionHandling();
        }

        $this->assertSame(0, InternalMaterialIssue::query()->where('idempotency_key', $key)->count());
    }

    public function test_deleting_a_draft_removes_its_lines(): void
    {
        $response = $this->postJson(
            '/api/xuat-vat-tu-noi-bo',
            $this->draftPayload('DELETE-' . uniqid())
        )->assertOk();

        $issueId = (int) $response->json('data.id');
        $lineId = (int) $response->json('data.lines.0.id');

        $this->deleteJson('/api/xuat-vat-tu-noi-bo/' . $issueId)->assertOk();

        $this->assertNull(InternalMaterialIssue::query()->find($issueId));
        $this->assertNull(InternalMaterialIssueLine::query()->find($lineId));
    }

    public function test_issue_with_returned_material_cannot_be_updated_or_deleted(): void
    {
        $payload = $this->draftPayload('RETURN-' . uniqid());
        $response = $this->postJson('/api/xuat-vat-tu-noi-bo', $payload)->assertOk();
        $issueId = (int) $response->json('data.id');
        $lineId = (int) $response->json('data.lines.0.id');

        InternalMaterialOrderAllocation::query()->create([
            'issue_line_id' => $lineId,
            'production_order_code' => 'TEST-ORDER',
            'finished_item_code' => $payload['lines'][0]['internal_item_code'],
            'allocated_quantity' => 1,
            'returned_quantity' => 0.25,
            'scrap_quantity' => 0,
        ]);

        $updatePayload = $payload;
        unset($updatePayload['idempotency_key']);
        $this->putJson('/api/xuat-vat-tu-noi-bo/' . $issueId, $updatePayload)->assertStatus(409);
        $this->deleteJson('/api/xuat-vat-tu-noi-bo/' . $issueId)->assertStatus(409);

        $this->assertNotNull(InternalMaterialIssue::query()->find($issueId));
        $this->assertSame(1, InternalMaterialIssueLine::query()->where('issue_id', $issueId)->count());
    }

    private function draftPayload(string $code): array
    {
        InternalItemCatalog::query()->create([
            'item_code' => $code,
            'item_name' => 'Write safety test item',
            'unit' => 'PCS',
            'is_active' => true,
        ]);

        return [
            'idempotency_key' => 'issue-' . uniqid('', true),
            'issue_type' => 'customer',
            'issue_date' => '2026-08-28',
            'receiver_name' => 'TEST',
            'department' => 'Kinh doanh',
            'purpose' => 'Test write safety',
            'save_as_draft' => true,
            'lines' => [[
                'ma_hh' => $code,
                'internal_item_code' => $code,
                'ten_hh' => 'Write safety test item',
                'dvt' => 'PCS',
                'quantity' => 1,
            ]],
        ];
    }
}
