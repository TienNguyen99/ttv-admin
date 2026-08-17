<?php

namespace Tests\Unit;

use App\Http\Controllers\InternalItemCatalogController;
use App\Services\InternalStocktakeService;
use PHPUnit\Framework\TestCase;

class InternalStocktakeServiceTest extends TestCase
{
    public function test_line_key_normalizes_case_and_spacing(): void
    {
        $service = new InternalStocktakeService();

        $first = $service->lineKey(' a1 ', 'KT-01', ' tt01 ', '6mm', 'đen', 'front');
        $second = $service->lineKey('A1', 'OTHER', 'TT01', ' 6MM ', 'ĐEN', 'FRONT');

        $this->assertSame($first, $second);
    }

    public function test_line_key_keeps_internal_variants_separate(): void
    {
        $service = new InternalStocktakeService();

        $black = $service->lineKey('A1', '', 'GC-PULLER', '', 'BLACK', '');
        $pink = $service->lineKey('A1', '', 'GC-PULLER', '', 'PINK', '');

        $this->assertNotSame($black, $pink);
    }

    public function test_duplicate_counts_for_the_same_variant_are_summed(): void
    {
        $service = new InternalStocktakeService();

        $lines = $service->mergeCountLines([
            ['internal_item_code' => '80020376-B', 'unit' => 'YARD', 'counted_quantity' => 1500, 'counted_weight_kg' => 18],
            ['internal_item_code' => '80020376-B', 'unit' => 'YARD', 'counted_quantity' => 372, 'counted_weight_kg' => 4.464],
            ['internal_item_code' => '80020376-B', 'unit' => 'YARD', 'counted_quantity' => 1000, 'counted_weight_kg' => 12],
        ], 'W20');

        $this->assertCount(1, $lines);
        $this->assertSame(2872.0, $lines[0]['counted_quantity']);
        $this->assertEqualsWithDelta(34.464, $lines[0]['counted_weight_kg'], 0.000001);
    }

    public function test_duplicate_counts_keep_variants_separate(): void
    {
        $service = new InternalStocktakeService();

        $lines = $service->mergeCountLines([
            ['internal_item_code' => 'ITEM-01', 'size' => 'S', 'color' => 'BLACK', 'counted_quantity' => 10],
            ['internal_item_code' => 'ITEM-01', 'size' => 'M', 'color' => 'BLACK', 'counted_quantity' => 20],
        ], 'A1');

        $this->assertCount(2, $lines);
    }

    public function test_catalog_weight_norm_accepts_grams_and_kilograms(): void
    {
        $method = new \ReflectionMethod(InternalItemCatalogController::class, 'weightPerUnitGrams');
        $method->setAccessible(true);
        $controller = new InternalItemCatalogController();

        $this->assertSame(12.5, $method->invoke($controller, '12,5'));
        $this->assertSame(12.0, $method->invoke($controller, '0,012 kg/yard'));
        $this->assertNull($method->invoke($controller, ''));
    }
}
