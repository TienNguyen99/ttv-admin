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

        $specificSourceGroups = [
            'BTP' => 'Bán thành phẩm',
            'GIACONG' => 'Gia công',
            'HC' => 'Hóa chất',
            'HOACHAT' => 'Hóa chất',
            'KEO' => 'Keo',
            'MUC' => 'Mực',
            'MỰC' => 'Mực',
            'NPLBK' => 'Nguyên phụ liệu khác',
            'NPLKEO' => 'Keo',
            'NPL-TPU' => 'TPU',
            'QT' => 'Quy trình',
            'SLC' => 'Silicone',
            'SOI' => 'Sợi',
            'SỢI' => 'Sợi',
            'THUNSU' => 'Thành phẩm thun su',
            'TPDAYIN' => 'Thành phẩm dây in',
            'TPDET' => 'Thành phẩm dệt',
            'TPDETIN' => 'Thành phẩm dệt in',
            'TPEPDUC' => 'Thành phẩm ép đúc',
            'TPIN' => 'Thành phẩm in',
            'TPTHUN' => 'Thun bản',
            'TPTHUNBAN' => 'Thun bản',
            'TP-THUNBAN' => 'Thun bản',
            'TPTHUNIN' => 'Thành phẩm thun in',
            'TPU' => 'TPU',
            'VATTU-CONGCU' => 'Công cụ dụng cụ',
            'VATTU-SOI' => 'Sợi',
        ];
        if (isset($specificSourceGroups[$sourceType])) {
            return $specificSourceGroups[$sourceType];
        }

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
            'VT' => 'Vật tư',
            'VATTU' => 'Vật tư',
            'MUABAN' => 'Hàng mua bán',
        ][$sourceType] ?? ($sourceType ?: 'Chưa phân nhóm');
    }

    public function code($catalog): string
    {
        $group = $this->resolve($catalog);

        return [
            'Thun bản' => 'TP-THUNBAN',
            'Nhãn dệt' => 'TP-NHANDET',
            'Nhãn size' => 'TP-NHANSIZE',
            'Thành phẩm dây in' => 'TP-DAYIN',
            'Thành phẩm dệt' => 'TP-DET',
            'Thành phẩm dệt in' => 'TP-DETIN',
            'Thành phẩm ép đúc' => 'TP-EPDUC',
            'Thành phẩm in' => 'TP-IN',
            'Thành phẩm thun in' => 'TP-THUNIN',
            'Thành phẩm thun su' => 'TP-THUNSU',
            'Thành phẩm' => 'TP-KHAC',
            'Bán thành phẩm' => 'BTP',
            'Sợi' => 'NVL-SOI',
            'Nguyên phụ liệu khác' => 'NVL-KHAC',
            'Vật tư' => 'NVL-VATTU',
            'Hóa chất' => 'NVL-HOACHAT',
            'Keo' => 'NVL-KEO',
            'Mực' => 'NVL-MUC',
            'Silicone' => 'NVL-SILICONE',
            'TPU' => 'NVL-TPU',
            'Công cụ dụng cụ' => 'CCDC',
            'Hàng mua bán' => 'HANG-MUA-BAN',
            'Gia công' => 'DICHVU-GIACONG',
            'Quy trình' => 'QUY-TRINH',
            'Chưa phân nhóm' => 'CHUA-PHAN-NHOM',
        ][$group] ?? mb_strtoupper(Str::slug($group, '-'));
    }

    public function displayName($catalog): string
    {
        $group = $this->resolve($catalog);

        return [
            'Thun bản' => 'Thành phẩm thun bản',
            'Nhãn dệt' => 'Thành phẩm nhãn dệt',
            'Nhãn size' => 'Thành phẩm nhãn size',
        ][$group] ?? $group;
    }

    public function family($catalog): string
    {
        $code = $this->code($catalog);

        if (strpos($code, 'TP-') === 0) {
            return 'Thành phẩm';
        }
        if ($code === 'BTP') {
            return 'Bán thành phẩm';
        }
        if (strpos($code, 'NVL-') === 0) {
            return 'Nguyên vật liệu';
        }
        if ($code === 'CCDC') {
            return 'Công cụ dụng cụ';
        }

        return 'Khác';
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
