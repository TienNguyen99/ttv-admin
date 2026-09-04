<?php

namespace Tests\Feature;

use App\Models\InternalItemCatalog;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InternalInvalidDocumentCodeReplacementTest extends TestCase
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

    public function test_it_previews_then_replaces_the_code_on_all_receipt_and_issue_lines(): void
    {
        $suffix = strtoupper(substr(md5((string) microtime(true)), 0, 8));
        $oldCode = "INVALID-{$suffix}";
        $newCode = "VALID-{$suffix}";
        $now = now();

        InternalItemCatalog::query()->create([
            'item_code' => $newCode,
            'item_name' => 'Ma hang chuan',
            'is_active' => true,
        ]);

        $receiptId = DB::connection('internal')->table('internal_material_receipts')->insertGetId([
            'receipt_code' => "TEST-PN-{$suffix}",
            'receipt_date' => '2026-09-04',
            'status' => 'posted',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $issueId = DB::connection('internal')->table('internal_material_issues')->insertGetId([
            'issue_code' => "TEST-PX-{$suffix}",
            'issue_date' => '2026-09-04',
            'status' => 'posted',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::connection('internal')->table('internal_material_receipt_lines')->insert([
            'receipt_id' => $receiptId,
            'ma_hh' => $oldCode,
            'internal_item_code' => $oldCode,
            'quantity' => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::connection('internal')->table('internal_material_issue_lines')->insert([
            'issue_id' => $issueId,
            'ma_hh' => $oldCode,
            'internal_item_code' => $oldCode,
            'quantity' => 4,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->postJson('/api/danh-muc-noi-bo/sua-ma-phieu', [
            'old_code' => $oldCode,
            'new_code' => $newCode,
            'apply' => false,
        ])->assertOk()
            ->assertJsonPath('data.counts.receipt_lines', 1)
            ->assertJsonPath('data.counts.issue_lines', 1);

        $this->assertDatabaseHas('internal_material_receipt_lines', ['receipt_id' => $receiptId, 'internal_item_code' => $oldCode], 'internal');

        $this->postJson('/api/danh-muc-noi-bo/sua-ma-phieu', [
            'old_code' => $oldCode,
            'new_code' => $newCode,
            'apply' => true,
        ])->assertOk()
            ->assertJsonPath('data.counts.document_lines', 2);

        $this->assertDatabaseHas('internal_material_receipt_lines', ['receipt_id' => $receiptId, 'internal_item_code' => $newCode], 'internal');
        $this->assertDatabaseHas('internal_material_issue_lines', ['issue_id' => $issueId, 'internal_item_code' => $newCode], 'internal');
        $this->assertDatabaseMissing('internal_material_receipt_lines', ['receipt_id' => $receiptId, 'internal_item_code' => $oldCode], 'internal');
    }

    public function test_it_rejects_a_replacement_code_that_is_not_in_the_catalog(): void
    {
        $this->postJson('/api/danh-muc-noi-bo/sua-ma-phieu', [
            'old_code' => 'INVALID-CODE-FOR-TEST',
            'new_code' => 'MISSING-CATALOG-CODE',
            'apply' => false,
        ])->assertStatus(422);
    }
}
