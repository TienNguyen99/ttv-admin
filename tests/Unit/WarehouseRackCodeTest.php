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

    public function test_maps_actual_seven_racks_with_variable_tier_counts(): void
    {
        $service = new WarehouseRackCode();

        $expected = [
            'A' => [1, 5], 'E' => [1, 1],
            'F' => [2, 5], 'J' => [2, 1],
            'K' => [3, 5], 'O' => [3, 1],
            'P' => [4, 2], 'Q' => [4, 1],
            'R' => [5, 2], 'S' => [5, 1],
            'T' => [6, 2], 'U' => [6, 1],
            'V' => [7, 2], 'W' => [7, 1],
        ];

        foreach ($expected as $label => [$rack, $tier]) {
            $position = $service->rackPositionForLabel($label);
            $this->assertSame($rack, $position['rack'], $label);
            $this->assertSame($tier, $position['tier'], $label);
        }
    }

    public function test_finds_letter_from_rack_and_tier(): void
    {
        $service = new WarehouseRackCode();

        $this->assertSame('A', $service->labelForRackTier(1, 5));
        $this->assertSame('E', $service->labelForRackTier(1, 1));
        $this->assertSame('P', $service->labelForRackTier(4, 2));
        $this->assertSame('Q', $service->labelForRackTier(4, 1));
        $this->assertSame('V', $service->labelForRackTier(7, 2));
        $this->assertSame('W', $service->labelForRackTier(7, 1));
        $this->assertSame('', $service->labelForRackTier(4, 3));
    }

    public function test_parses_location_using_actual_rack_profile(): void
    {
        $service = new WarehouseRackCode();

        $this->assertSame(['label' => 'P', 'letter_index' => 15, 'bay' => 1, 'line' => 4, 'tier' => 2], $service->parseLocation('P1'));
        $this->assertSame(['label' => 'Q', 'letter_index' => 16, 'bay' => 12, 'line' => 4, 'tier' => 1], $service->parseLocation('Q12'));
        $this->assertSame(['label' => 'W', 'letter_index' => 22, 'bay' => 2, 'line' => 7, 'tier' => 1], $service->parseLocation('W2'));
    }

    public function test_continues_with_five_tier_racks_after_configured_profile(): void
    {
        $service = new WarehouseRackCode();

        $this->assertSame(['rack' => 8, 'tier' => 5, 'tier_count' => 5], $service->rackPositionForLabel('X'));
        $this->assertSame(['rack' => 8, 'tier' => 3, 'tier_count' => 5], $service->rackPositionForLabel('Z'));
        $this->assertSame(['rack' => 8, 'tier' => 2, 'tier_count' => 5], $service->rackPositionForLabel('AA'));
    }
}
