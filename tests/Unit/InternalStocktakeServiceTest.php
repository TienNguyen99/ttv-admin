<?php

namespace Tests\Unit;

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
}
