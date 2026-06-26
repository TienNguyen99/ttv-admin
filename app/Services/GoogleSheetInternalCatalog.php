<?php

namespace App\Services;

use App\Models\InternalItemCatalog;
use Illuminate\Support\Collection;

class GoogleSheetInternalCatalog
{
    public function all(): Collection
    {
        return InternalItemCatalog::query()
            ->where('is_active', true)
            ->whereNotNull('item_code')
            ->where('item_code', '<>', '')
            ->orderBy('item_code')
            ->get()
            ->map(function ($row) {
                return [
                    'name' => $row->item_name,
                    'code' => $row->item_code,
                    'unit' => $row->unit,
                    'shelf' => $row->shelf_code,
                    'size' => $row->size,
                    'color' => $row->color,
                    'logo_color' => $row->logo_color,
                    'side' => $row->side,
                ];
            });
    }

    public function search(string $keyword, int $limit = 30): Collection
    {
        $keyword = mb_strtoupper(trim($keyword));

        return $this->all()
            ->filter(function ($row) use ($keyword) {
                if ($keyword === '') {
                    return true;
                }

                return mb_strpos(mb_strtoupper($row['code']), $keyword) !== false
                    || mb_strpos(mb_strtoupper($row['name']), $keyword) !== false
                    || mb_strpos(mb_strtoupper((string) ($row['size'] ?? '')), $keyword) !== false
                    || mb_strpos(mb_strtoupper((string) ($row['color'] ?? '')), $keyword) !== false
                    || mb_strpos(mb_strtoupper((string) ($row['logo_color'] ?? '')), $keyword) !== false
                    || mb_strpos(mb_strtoupper((string) ($row['shelf'] ?? '')), $keyword) !== false
                    || mb_strpos(mb_strtoupper((string) ($row['side'] ?? '')), $keyword) !== false;
            })
            ->take($limit)
            ->values();
    }

    public function find(string $code): ?array
    {
        $code = mb_strtoupper(trim($code));
        if ($code === '') {
            return null;
        }

        return $this->all()->first(function ($row) use ($code) {
            return mb_strtoupper($row['code']) === $code;
        });
    }

}
