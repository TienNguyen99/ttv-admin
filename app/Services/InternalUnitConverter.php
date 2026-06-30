<?php

namespace App\Services;

use App\Models\InternalItemCatalog;
use App\Models\InternalUnitConversion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InternalUnitConverter
{
    public function toBase(?string $itemCode, float $quantity, ?string $fromUnit, ?string $fallbackBaseUnit = null): array
    {
        $itemCode = trim((string) $itemCode);
        $from = $this->normalizeUnit($fromUnit);
        $base = $this->baseUnit($itemCode, $fallbackBaseUnit ?: $fromUnit);

        if ($quantity == 0.0) {
            return $this->result($quantity, $base ?: $from, 1.0, false);
        }

        if ($from === '' || $base === '' || $from === $base) {
            return $this->result($quantity, $base ?: $from, 1.0, false);
        }

        $factor = $this->factor($itemCode, $from, $base);
        if ($factor === null) {
            return $this->result($quantity, $from, 1.0, false);
        }

        return $this->result(round($quantity * $factor, 3), $base, $factor, true);
    }

    public function factor(?string $itemCode, string $fromUnit, string $toUnit): ?float
    {
        $itemCode = mb_strtoupper(trim((string) $itemCode));
        $from = $this->normalizeUnit($fromUnit);
        $to = $this->normalizeUnit($toUnit);

        if ($from === '' || $to === '') {
            return null;
        }
        if ($from === $to) {
            return 1.0;
        }

        $query = InternalUnitConversion::query()
            ->where('from_unit', $from)
            ->where('to_unit', $to)
            ->orderByRaw('CASE WHEN item_code IS NULL OR item_code = "" THEN 1 ELSE 0 END');

        if ($itemCode !== '') {
            $query->where(function ($q) use ($itemCode) {
                $q->whereRaw('UPPER(TRIM(item_code)) = ?', [$itemCode])
                    ->orWhereNull('item_code')
                    ->orWhere('item_code', '');
            });
        } else {
            $query->where(function ($q) {
                $q->whereNull('item_code')->orWhere('item_code', '');
            });
        }

        $row = $query->first();
        return $row ? (float) $row->factor : null;
    }

    public function baseUnit(?string $itemCode, ?string $fallbackUnit = null): string
    {
        $itemCode = trim((string) $itemCode);
        if ($itemCode !== '') {
            $catalogUnit = InternalItemCatalog::query()
                ->where('is_active', true)
                ->whereRaw('UPPER(TRIM(item_code)) = ?', [mb_strtoupper($itemCode)])
                ->orderBy('id')
                ->value('unit');

            $unit = $this->normalizeUnit($catalogUnit);
            if ($unit !== '') {
                return $unit;
            }
        }

        return $this->normalizeUnit($fallbackUnit);
    }

    public function normalizeUnit(?string $unit): string
    {
        $unit = trim((string) $unit);
        if ($unit === '') {
            return '';
        }

        $ascii = Str::ascii(mb_strtolower($unit));
        $normalized = trim(preg_replace('/[^a-z0-9]+/', ' ', $ascii));
        $compact = str_replace(' ', '', $normalized);

        $map = [
            'm' => 'M',
            'met' => 'M',
            'meter' => 'M',
            'meters' => 'M',
            'metre' => 'M',
            'metres' => 'M',
            'yard' => 'YARD',
            'yards' => 'YARD',
            'yd' => 'YARD',
            'yds' => 'YARD',
            'kg' => 'KG',
            'kilogram' => 'KG',
            'kilograms' => 'KG',
            'kgs' => 'KG',
            'pcs' => 'PCS',
            'pc' => 'PCS',
            'cai' => 'PCS',
            'cuon' => 'CUON',
            'roll' => 'CUON',
            'rolls' => 'CUON',
        ];

        return $map[$compact] ?? mb_strtoupper($unit);
    }

    public function upsert(array $data): InternalUnitConversion
    {
        $itemCode = trim((string) ($data['item_code'] ?? ''));
        $from = $this->normalizeUnit($data['from_unit'] ?? '');
        $to = $this->normalizeUnit($data['to_unit'] ?? '');
        $factor = (float) ($data['factor'] ?? 0);

        if ($from === '' || $to === '' || $factor <= 0) {
            throw new \InvalidArgumentException('Quy đổi cần đơn vị nguồn, đơn vị đích và hệ số lớn hơn 0.');
        }

        return InternalUnitConversion::query()->updateOrCreate(
            [
                'item_code' => $itemCode !== '' ? mb_strtoupper($itemCode) : null,
                'from_unit' => $from,
                'to_unit' => $to,
            ],
            [
                'factor' => $factor,
                'note' => trim((string) ($data['note'] ?? '')),
            ]
        );
    }

    public function list(?string $keyword = null)
    {
        $keyword = trim((string) $keyword);

        return InternalUnitConversion::query()
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('item_code', 'like', '%' . $keyword . '%')
                        ->orWhere('from_unit', 'like', '%' . $keyword . '%')
                        ->orWhere('to_unit', 'like', '%' . $keyword . '%')
                        ->orWhere('note', 'like', '%' . $keyword . '%');
                });
            })
            ->orderByRaw('CASE WHEN item_code IS NULL OR item_code = "" THEN 0 ELSE 1 END')
            ->orderBy('item_code')
            ->orderBy('from_unit')
            ->orderBy('to_unit')
            ->limit(500)
            ->get();
    }

    private function result(float $quantity, string $unit, float $factor, bool $converted): array
    {
        return [
            'quantity' => $quantity,
            'unit' => $unit,
            'factor' => $factor,
            'converted' => $converted,
        ];
    }
}
