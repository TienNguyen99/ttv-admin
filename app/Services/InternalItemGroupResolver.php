<?php

namespace App\Services;

use Illuminate\Support\Str;

class InternalItemGroupResolver
{
    public function resolve($catalog): string
    {
        $raw = is_array($catalog->raw_data ?? null) ? $catalog->raw_data : [];
        $sourceType = mb_strtoupper(trim((string) ($raw['loai'] ?? '')));
        $name = $this->normalize($catalog->item_name ?? '');

        if (strpos($name, 'nhan size') !== false || strpos($name, 'size label') !== false) {
            return 'Nhãn size';
        }
        if (strpos($name, 'nhan det') !== false || strpos($name, 'woven label') !== false) {
            return 'Nhãn dệt';
        }
        if (strpos($name, 'thun') !== false || strpos($name, 'elastic tape') !== false) {
            return 'Thun bản';
        }

        return [
            'TP' => 'Thành phẩm',
            'BTP' => 'Bán thành phẩm',
            'TPTHUN' => 'Thun bản',
            'SOI' => 'Sợi',
            'VT' => 'Vật tư',
            'HC' => 'Hóa chất',
            'KEO' => 'Keo',
            'MUC' => 'Mực',
            'SLC' => 'Silicone',
            'TPU' => 'TPU',
        ][$sourceType] ?? ($sourceType ?: 'Chưa phân nhóm');
    }

    public function resolveName(string $name): string
    {
        return $this->resolve((object) [
            'item_name' => $name,
            'raw_data' => [],
        ]);
    }

    private function normalize($value): string
    {
        $value = Str::ascii(mb_strtolower(trim((string) $value)));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);

        return trim(preg_replace('/\s+/', ' ', $value));
    }
}
