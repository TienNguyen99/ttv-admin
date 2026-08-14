<?php

namespace App\Services;

class WarehouseRackCode
{
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

    public function parseLocation(string $locationCode): ?array
    {
        $code = strtoupper(trim($locationCode));
        if (!preg_match('/^([A-Z]{1,2})0*(\d{1,4})$/', $code, $matches)) {
            return null;
        }

        $letterIndex = $this->labelToIndex($matches[1]);
        if ($letterIndex < 0) {
            return null;
        }

        return [
            'label' => $matches[1],
            'letter_index' => $letterIndex,
            'bay' => (int) $matches[2],
            'line' => intdiv($letterIndex, 5) + 1,
            'tier' => 5 - ($letterIndex % 5),
        ];
    }
}
