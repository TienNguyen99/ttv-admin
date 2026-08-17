<?php

namespace App\Services;

class WarehouseRackCode
{
    private const PHYSICAL_RACK_TIER_COUNTS = [5, 5, 5, 2, 2, 2, 2];

    public function labelToIndex(string $label): int
    {
        $label = strtoupper(trim($label));
        if (!preg_match('/^[A-Z]{1,2}$/', $label)) {
            return -1;
        }

        $value = 0;
        foreach (str_split($label) as $character) {
            $value = ($value * 26) + (ord($character) - ord('A') + 1);
        }

        return $value - 1;
    }

    public function indexToLabel(int $index): string
    {
        if ($index < 0 || $index >= 26 * 27) {
            return '';
        }

        $value = $index + 1;
        $label = '';
        while ($value > 0) {
            $value--;
            $label = chr(ord('A') + ($value % 26)) . $label;
            $value = intdiv($value, 26);
        }

        return $label;
    }

    public function rackTierCounts(): array
    {
        return self::PHYSICAL_RACK_TIER_COUNTS;
    }

    public function rackPositionForLabel(string $label): ?array
    {
        $index = $this->labelToIndex($label);
        if ($index < 0) {
            return null;
        }

        $offset = 0;
        foreach (self::PHYSICAL_RACK_TIER_COUNTS as $rackIndex => $tierCount) {
            if ($index < $offset + $tierCount) {
                return [
                    'rack' => $rackIndex + 1,
                    'tier' => $tierCount - ($index - $offset),
                    'tier_count' => $tierCount,
                ];
            }
            $offset += $tierCount;
        }

        $relativeIndex = $index - $offset;
        return [
            'rack' => count(self::PHYSICAL_RACK_TIER_COUNTS) + intdiv($relativeIndex, 5) + 1,
            'tier' => 5 - ($relativeIndex % 5),
            'tier_count' => 5,
        ];
    }

    public function labelForRackTier(int $rack, int $tier): string
    {
        if ($rack < 1 || $tier < 1) {
            return '';
        }

        $counts = self::PHYSICAL_RACK_TIER_COUNTS;
        $offset = 0;
        for ($rackNumber = 1; $rackNumber < $rack; $rackNumber++) {
            $offset += $counts[$rackNumber - 1] ?? 5;
        }
        $tierCount = $counts[$rack - 1] ?? 5;
        if ($tier > $tierCount) {
            return '';
        }

        return $this->indexToLabel($offset + ($tierCount - $tier));
    }

    public function parseLocation(string $locationCode): ?array
    {
        $code = strtoupper(trim($locationCode));
        if (!preg_match('/^([A-Z]{1,2})0*(\d{1,4})$/', $code, $matches)) {
            return null;
        }

        $letterIndex = $this->labelToIndex($matches[1]);
        $position = $this->rackPositionForLabel($matches[1]);
        if ($letterIndex < 0 || !$position) {
            return null;
        }

        return [
            'label' => $matches[1],
            'letter_index' => $letterIndex,
            'bay' => (int) $matches[2],
            'line' => $position['rack'],
            'tier' => $position['tier'],
        ];
    }
}
