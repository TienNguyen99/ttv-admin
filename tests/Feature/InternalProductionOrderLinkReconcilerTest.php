<?php

namespace Tests\Feature;

use App\Models\InternalProductionOrder;
use App\Services\InternalProductionOrderLinkReconciler;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InternalProductionOrderLinkReconcilerTest extends TestCase
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

    public function test_it_previews_and_applies_only_a_unique_order_item_match(): void
    {
        $code = 'TEST-LINK-' . uniqid();
        $itemCode = 'TEST-LINK-ITEM-' . uniqid();
        $order = $this->createOrder($code, $itemCode, 'M', 'BLACK');
        $lineId = $this->createReceiptLine($code, $itemCode, 'M', 'BLACK');
        $service = app(InternalProductionOrderLinkReconciler::class);

        $preview = $service->preview(2000);
        $proposal = collect($preview['rows'])->firstWhere('line_id', $lineId);

        $this->assertNotNull($proposal);
        $this->assertSame('matchable', $proposal['status']);
        $this->assertSame($order->id, $proposal['suggested_order_id']);

        $service->apply(2000);
        $this->assertSame(
            $order->id,
            (int) DB::connection('internal')->table('internal_material_receipt_lines')->where('id', $lineId)->value('production_order_id')
        );
    }

    public function test_it_keeps_an_ambiguous_variant_for_manual_review(): void
    {
        $code = 'TEST-LINK-AMB-' . uniqid();
        $itemCode = 'TEST-LINK-AMB-ITEM-' . uniqid();
        $this->createOrder($code, $itemCode, 'M', 'BLACK');
        $this->createOrder($code, $itemCode, 'L', 'WHITE');
        $lineId = $this->createReceiptLine($code, $itemCode, '', '');

        $preview = app(InternalProductionOrderLinkReconciler::class)->preview(2000);
        $proposal = collect($preview['rows'])->firstWhere('line_id', $lineId);

        $this->assertSame('ambiguous', $proposal['status']);
        $this->assertNull($proposal['suggested_order_id']);
    }

    public function test_it_preserves_the_source_order_and_resolves_its_central_variants(): void
    {
        $code = 'TEST-LINK-PARENT-' . uniqid();
        $sourceCode = 'TEST-SOURCE-' . uniqid();
        $parent = InternalProductionOrder::query()->create([
            'production_order' => $code,
            'item_code' => $sourceCode,
            'standard_item_code' => $sourceCode,
            'order_quantity' => 200,
            'is_variant_parent' => true,
            'is_active' => false,
        ]);
        $black = InternalProductionOrder::query()->create([
            'production_order' => $code,
            'item_code' => $sourceCode,
            'standard_item_code' => $sourceCode . '-BLACK',
            'variant_parent_id' => $parent->id,
            'is_manual_variant' => true,
            'size' => 'M',
            'color' => 'BLACK',
            'order_quantity' => 100,
            'is_active' => true,
        ]);
        InternalProductionOrder::query()->create([
            'production_order' => $code,
            'item_code' => $sourceCode,
            'standard_item_code' => $sourceCode . '-PINK',
            'variant_parent_id' => $parent->id,
            'is_manual_variant' => true,
            'size' => 'L',
            'color' => 'PINK',
            'order_quantity' => 100,
            'is_active' => true,
        ]);

        $variantLineId = $this->createReceiptLine($code, $sourceCode . '-BLACK', 'M', 'BLACK');
        $sourceLineId = $this->createReceiptLine($code, $sourceCode, 'M', 'BLACK');
        $missingVariantLineId = $this->createReceiptLine($code, $sourceCode . '-BLUE', 'S', 'BLUE');
        $rows = collect(app(InternalProductionOrderLinkReconciler::class)->preview(2000)['rows']);

        $this->assertSame($black->id, $rows->firstWhere('line_id', $variantLineId)['suggested_order_id']);
        $this->assertSame($black->id, $rows->firstWhere('line_id', $sourceLineId)['suggested_order_id']);
        $this->assertSame('missing_variant', $rows->firstWhere('line_id', $missingVariantLineId)['status']);
        $this->assertSame($sourceCode, $rows->firstWhere('line_id', $missingVariantLineId)['source_item_code']);
    }

    public function test_preview_query_count_is_constant(): void
    {
        DB::connection('internal')->flushQueryLog();
        DB::connection('internal')->enableQueryLog();
        app(InternalProductionOrderLinkReconciler::class)->preview(500);
        $queryCount = count(DB::connection('internal')->getQueryLog());
        DB::connection('internal')->disableQueryLog();

        $this->assertGreaterThanOrEqual(2, $queryCount);
        $this->assertLessThanOrEqual(3, $queryCount);
    }

    private function createOrder(string $code, string $itemCode, string $size, string $color): InternalProductionOrder
    {
        return InternalProductionOrder::query()->create([
            'production_order' => $code,
            'item_code' => $itemCode,
            'standard_item_code' => $itemCode,
            'size' => $size,
            'color' => $color,
            'order_quantity' => 100,
            'is_active' => true,
        ]);
    }

    private function createReceiptLine(string $code, string $itemCode, string $size, string $color): int
    {
        $receiptId = DB::connection('internal')->table('internal_material_receipts')->insertGetId([
            'receipt_code' => 'PNTP-LINK-' . uniqid(),
            'receipt_date' => '2026-08-29',
            'source' => 'Phieu nhap thanh pham',
            'status' => 'posted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::connection('internal')->table('internal_material_receipt_lines')->insertGetId([
            'receipt_id' => $receiptId,
            'production_order_id' => null,
            'production_order' => $code,
            'ma_hh' => $itemCode,
            'internal_item_code' => $itemCode,
            'size' => $size,
            'color' => $color,
            'dvt' => 'PCS',
            'quantity' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
