<?php

namespace Tests\Unit;

use App\Services\WarehouseRackCode;
use PHPUnit\Framework\TestCase;

class WarehouseRackCodeTest extends TestCase
{
    public function test_converts_single_and_double_letter_labels(): void
    {
        $service = new WarehouseRackCode();

        $this->assertSame(0, $service->labelToIndex('A'));
        $this->assertSame(25, $service->labelToIndex('Z'));
        $this->assertSame(26, $service->labelToIndex('AA'));
        $this->assertSame(34, $service->labelToIndex('AI'));
        $this->assertSame('Z', $service->indexToLabel(25));
        $this->assertSame('AA', $service->indexToLabel(26));
        $this->assertSame('AI', $service->indexToLabel(34));
    }

    public function test_parses_line_and_tier_after_z(): void
    {
        $service = new WarehouseRackCode();

        $this->assertSame(['label' => 'Z', 'letter_index' => 25, 'bay' => 1, 'line' => 6, 'tier' => 5], $service->parseLocation('Z1'));
        $this->assertSame(['label' => 'AA', 'letter_index' => 26, 'bay' => 2, 'line' => 6, 'tier' => 4], $service->parseLocation('AA2'));
        $this->assertSame(['label' => 'AI', 'letter_index' => 34, 'bay' => 12, 'line' => 7, 'tier' => 1], $service->parseLocation('AI12'));
    }
}
