<?php

namespace Tests\Feature;

use App\Models\InternalProductionOrder;
use App\Services\InternalProductionLifecycleService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InternalProductionLifecycleServiceTest extends TestCase
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

    public function test_it_reports_unlinked_documents_and_invalid_quantity_flow(): void
    {
        $code = 'TEST-LIFECYCLE-' . uniqid();
        $order = InternalProductionOrder::query()->create([
            'production_order' => $code,
            'item_code' => 'TEST-LIFECYCLE-FG',
            'standard_item_code' => 'TEST-LIFECYCLE-FG',
            'description' => 'Lifecycle finished item',
            'order_quantity' => 100,
            'unit' => 'PCS',
            'is_active' => true,
        ]);
        $receiptId = DB::connection('internal')->table('internal_material_receipts')->insertGetId([
            'receipt_code' => 'PNTP-' . uniqid(),
            'receipt_date' => '2026-08-29',
            'source' => 'Phieu nhap thanh pham',
            'status' => 'posted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('internal')->table('internal_material_receipt_lines')->insert([
            'receipt_id' => $receiptId,
            'production_order_id' => null,
            'production_order' => $code,
            'ma_hh' => 'TEST-LIFECYCLE-FG',
            'internal_item_code' => 'TEST-LIFECYCLE-FG',
            'dvt' => 'PCS',
            'quantity' => 50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $issueId = DB::connection('internal')->table('internal_material_issues')->insertGetId([
            'issue_code' => 'PXTP-' . uniqid(),
            'issue_date' => '2026-08-29',
            'production_order' => $code,
            'issue_type' => 'customer',
            'status' => 'posted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('internal')->table('internal_material_issue_lines')->insert([
            'issue_id' => $issueId,
            'production_order_id' => $order->id,
            'production_order' => $code,
            'ma_hh' => 'TEST-LIFECYCLE-FG',
            'internal_item_code' => 'TEST-LIFECYCLE-FG',
            'dvt' => 'PCS',
            'quantity' => 80,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rows = app(InternalProductionLifecycleService::class)->enrich(
            collect([$order]),
            collect([$this->workflowRow($code, 100, 50, 80)])
        );
        $row = $rows->first();

        $this->assertSame($order->id, $row['canonical_order_id']);
        $this->assertSame(2, $row['link_integrity']['total']);
        $this->assertSame(1, $row['link_integrity']['linked']);
        $this->assertSame(1, $row['link_integrity']['receipt']['unlinked']);
        $this->assertContains('unlinked_documents', collect($row['warnings'])->pluck('code')->all());
        $this->assertContains('shipment_over_receipt', collect($row['warnings'])->pluck('code')->all());
        $this->assertTrue($row['has_error']);
    }

    public function test_enrichment_query_count_does_not_grow_with_order_count(): void
    {
        $orders = collect();
        $rows = collect();
        foreach (range(1, 5) as $index) {
            $code = 'TEST-LIFECYCLE-Q-' . uniqid() . '-' . $index;
            $order = InternalProductionOrder::query()->create([
                'production_order' => $code,
                'item_code' => 'TEST-LIFECYCLE-Q-' . $index,
                'order_quantity' => 10,
                'is_active' => true,
            ]);
            $orders->push($order);
            $rows->push($this->workflowRow($code, 10, 0, 0));
        }

        DB::connection('internal')->flushQueryLog();
        DB::connection('internal')->enableQueryLog();
        app(InternalProductionLifecycleService::class)->enrich($orders, $rows);
        $queryCount = count(DB::connection('internal')->getQueryLog());
        DB::connection('internal')->disableQueryLog();

        $this->assertSame(2, $queryCount);
    }

    public function test_variant_rows_keep_the_synced_parent_as_the_canonical_order(): void
    {
        $code = 'TEST-LIFECYCLE-VARIANT-' . uniqid();
        $parent = InternalProductionOrder::query()->create([
            'production_order' => $code,
            'item_code' => 'TEST-SOURCE',
            'order_quantity' => 100,
            'is_variant_parent' => true,
            'is_active' => false,
        ]);
        $variant = InternalProductionOrder::query()->create([
            'production_order' => $code,
            'item_code' => 'TEST-SOURCE',
            'standard_item_code' => 'TEST-SOURCE-BLACK',
            'variant_parent_id' => $parent->id,
            'is_manual_variant' => true,
            'order_quantity' => 100,
            'is_active' => true,
        ]);

        $row = app(InternalProductionLifecycleService::class)->enrich(
            collect([$variant]),
            collect([$this->workflowRow($code, 100, 0, 0)])
        )->first();

        $this->assertSame($parent->id, $row['canonical_order_id']);
        $this->assertSame([$variant->id], $row['order_ids']);
    }

    private function workflowRow(string $code, float $planned, float $received, float $shipped): array
    {
        return [
            'production_order' => $code,
            'planned_quantity' => $planned,
            'received_quantity' => $received,
            'customer_issue_quantity' => $shipped,
            'lifecycle' => [
                'bom_complete' => false,
                'operations' => [],
            ],
        ];
    }
}
