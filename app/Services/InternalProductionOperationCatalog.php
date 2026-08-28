<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InternalProductionOperationCatalog
{
    public function forOrders(Collection $orders): Collection
    {
        $itemCodes = $orders
            ->flatMap(fn ($row) => [$row->standard_item_code ?: $row->item_code, $row->item_code])
            ->map(fn ($code) => $this->code($code))
            ->filter()
            ->unique()
            ->values();

        $profiles = DB::connection('internal')->table('internal_product_bom_profiles')
            ->where('status', 'active')
            ->whereIn('item_code', $itemCodes->all())
            ->get()
            ->keyBy(fn ($row) => $this->code($row->item_code));

        $profileIds = $itemCodes
            ->map(fn ($code) => optional($profiles->get($code))->id)
            ->filter()
            ->unique()
            ->values();

        $configured = $profileIds->isEmpty()
            ? collect()
            : DB::connection('internal')->table('internal_product_routings')
                ->whereIn('profile_id', $profileIds->all())
                ->orderBy('sequence')
                ->get()
                ->unique(fn ($row) => $this->code($row->operation_code))
                ->values()
                ->map(fn ($row, $index) => [
                    'code' => $this->code($row->operation_code),
                    'name' => trim((string) $row->operation_name),
                    'sequence' => $index + 1,
                    'is_configured' => true,
                ]);

        $configuredCodes = $configured->pluck('code')->map(fn ($code) => $this->code($code));
        $standard = collect($this->standardOperations())
            ->reject(fn ($operation) => $configuredCodes->contains($this->code($operation['code'])))
            ->values()
            ->map(fn ($operation, $index) => $operation + [
                'sequence' => $configured->count() + $index + 1,
                'is_configured' => false,
            ]);

        return $configured->concat($standard)->values();
    }

    public function standardOperations(): array
    {
        return [
            ['code' => 'PHA', 'name' => 'Pha nguyên liệu'],
            ['code' => 'DUC', 'name' => 'Đúc'],
            ['code' => 'IN', 'name' => 'In'],
            ['code' => 'EP', 'name' => 'Ép'],
            ['code' => 'DET', 'name' => 'Dệt'],
            ['code' => 'CAT', 'name' => 'Cắt'],
            ['code' => 'MAY', 'name' => 'May'],
            ['code' => 'HOAN_THIEN', 'name' => 'Hoàn thiện'],
            ['code' => 'KCS', 'name' => 'KCS'],
            ['code' => 'DONG_GOI', 'name' => 'Đóng gói'],
        ];
    }

    private function code($value): string
    {
        return mb_strtoupper(trim((string) $value));
    }
}
