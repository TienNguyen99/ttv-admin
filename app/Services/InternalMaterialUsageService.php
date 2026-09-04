<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InternalMaterialUsageService
{
    private const WINDOWS = [1, 3, 6, 9, 12];

    public function report(
        Carbon $asOf,
        int $selectedWindow = 3,
        int $leadTimeDays = 14,
        float $safetyPercent = 20,
        float $targetMonths = 2,
        string $keyword = '',
        string $status = 'all',
        bool $fresh = false
    ): array {
        $asOf = $asOf->copy()->endOfDay();
        $selectedWindow = in_array($selectedWindow, self::WINDOWS, true) ? $selectedWindow : 3;
        $historyStart = $asOf->copy()->startOfMonth()->subMonthsNoOverflow(23)->format('Y-m-d');
        $historyEnd = $asOf->format('Y-m-d');

        $cacheKey = 'internal-material-demand:v3:' . $asOf->format('Y-m-d');
        if ($fresh) {
            Cache::forget($cacheKey);
        }
        $base = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($historyStart, $historyEnd, $asOf) {
            $catalogs = $this->allCatalogs();
            $centralIssues = $this->monthlyIssues($historyStart, $historyEnd);
            $legacyIssues = $this->monthlyXntIssues($historyStart, $historyEnd, $catalogs);
            $issues = $this->aggregateMonthly($centralIssues->concat($legacyIssues));
            $codes = $issues->pluck('item_code')->filter()->unique()->values();
            if ($codes->isEmpty()) {
                return ['issues' => $issues, 'codes' => $codes];
            }

            return [
                'issues' => $issues,
                'codes' => $codes,
                'receipts' => $this->monthlyReceipts($historyStart, $historyEnd, $codes),
                'returns' => $this->monthlyReturns($historyStart, $historyEnd, $codes),
                'stock' => $this->currentStock($asOf, $codes),
                'catalogs' => $catalogs,
                'first_issue_dates' => $this->firstIssueDates($codes, $legacyIssues),
            ];
        });
        $issues = $base['issues'];
        $materialCodes = $base['codes'];

        if ($materialCodes->isEmpty()) {
            return $this->emptyReport($asOf, $selectedWindow, $leadTimeDays, $safetyPercent, $targetMonths);
        }

        $receipts = $base['receipts'];
        $returns = $base['returns'];
        $stock = $base['stock'];
        $catalogs = $base['catalogs'];
        $firstIssueDates = $base['first_issue_dates'];

        $issueMap = $this->monthlyMap($issues, 'quantity');
        $receiptMap = $this->monthlyMap($receipts, 'quantity');
        $returnMap = $this->monthlyMap($returns, 'quantity');
        $issueInfo = $issues
            ->sortByDesc('month_key')
            ->groupBy('item_code')
            ->map(fn ($rows) => $rows->first());
        $issueUnits = $issues->groupBy('item_code')->map(function ($rows) {
            return $rows->flatMap(fn ($row) => $row->source_units ?? [$row->unit])
                ->map(fn ($unit) => strtoupper(trim((string) $unit)))
                ->filter()
                ->unique()
                ->values();
        });
        $issueSources = $issues->groupBy('item_code')->map(function ($rows) {
            return $rows->flatMap(fn ($row) => $row->sources ?? [])
                ->filter()
                ->unique()
                ->values();
        });
        $monthlyKeys = $this->monthKeys($asOf, 12);
        $rows = [];

        foreach ($materialCodes as $code) {
            $catalog = $catalogs->get($code);
            $fallback = $issueInfo->get($code);
            $name = trim((string) ($catalog->item_name ?? ($fallback->item_name ?? '')));
            $unit = strtoupper(trim((string) ($catalog->unit ?? ($fallback->unit ?? ''))));
            $unitConflict = ($issueUnits->get($code) ?? collect())->count() > 1;
            $currentStock = (float) ($stock[$code] ?? 0);
            $averages = [];
            $netTotals = [];

            foreach (self::WINDOWS as $window) {
                $keys = $this->monthKeys($asOf, $window);
                $gross = $this->sumMonths($issueMap, $code, $keys);
                $returned = $this->sumMonths($returnMap, $code, $keys);
                $net = max(0, $gross - $returned);
                $netTotals[$window] = $net;
                $averages[$window] = $net / $window;
            }

            $selectedKeys = $this->monthKeys($asOf, $selectedWindow);
            $previousEnd = $asOf->copy()->startOfMonth()->subDay();
            $previousKeys = $this->monthKeys($previousEnd, $selectedWindow);
            $gross = $this->sumMonths($issueMap, $code, $selectedKeys);
            $returned = $this->sumMonths($returnMap, $code, $selectedKeys);
            $net = max(0, $gross - $returned);
            $previousNet = max(
                0,
                $this->sumMonths($issueMap, $code, $previousKeys)
                    - $this->sumMonths($returnMap, $code, $previousKeys)
            );
            $monthlyAverage = $net / $selectedWindow;
            $previousAverage = $previousNet / $selectedWindow;
            $dailyAverage = $monthlyAverage / 30.4375;
            $safetyStock = $monthlyAverage * ($safetyPercent / 100);
            $reorderPoint = ($dailyAverage * $leadTimeDays) + $safetyStock;
            $targetStock = ($monthlyAverage * $targetMonths) + $safetyStock;
            $suggestedPurchase = $monthlyAverage > 0 && $currentStock <= $reorderPoint
                ? max(0, $targetStock - $currentStock)
                : 0;
            $coverMonths = $monthlyAverage > 0 ? $currentStock / $monthlyAverage : null;
            $trend = $previousAverage > 0
                ? (($monthlyAverage - $previousAverage) / $previousAverage) * 100
                : null;
            $firstIssue = $firstIssueDates[$code] ?? null;
            $requiredStart = $asOf->copy()->startOfMonth()->subMonthsNoOverflow($selectedWindow - 1);
            $historyComplete = $firstIssue && Carbon::parse($firstIssue)->startOfMonth()->lte($requiredStart);

            $row = [
                'item_code' => $code,
                'item_name' => $name,
                'unit' => $unit ?: 'CHUA CO DVT',
                'current_stock' => round($currentStock, 6),
                'receipt_quantity' => round($this->sumMonths($receiptMap, $code, $selectedKeys), 6),
                'gross_issue_quantity' => round($gross, 6),
                'returned_quantity' => round($returned, 6),
                'net_usage_quantity' => round($net, 6),
                'monthly_average' => round($monthlyAverage, 6),
                'averages' => collect($averages)->map(fn ($value) => round($value, 6))->all(),
                'cover_months' => $coverMonths === null ? null : round($coverMonths, 2),
                'trend_percent' => $trend === null ? null : round($trend, 2),
                'reorder_point' => round($reorderPoint, 6),
                'suggested_purchase' => round($suggestedPurchase, 6),
                'needs_purchase' => $suggestedPurchase > 0,
                'history_complete' => (bool) $historyComplete,
                'unit_conflict' => $unitConflict,
                'source_units' => ($issueUnits->get($code) ?? collect())->all(),
                'history_sources' => ($issueSources->get($code) ?? collect())->all(),
                'first_issue_date' => $firstIssue,
                'monthly' => collect($monthlyKeys)->map(function ($monthKey) use ($code, $issueMap, $returnMap) {
                    $gross = (float) ($issueMap[$code][$monthKey] ?? 0);
                    $returned = (float) ($returnMap[$code][$monthKey] ?? 0);

                    return [
                        'month' => $monthKey,
                        'usage' => round(max(0, $gross - $returned), 6),
                    ];
                })->values()->all(),
            ];

            if (!$this->matchesKeyword($row, $keyword)) {
                continue;
            }
            if ($status === 'needs_purchase' && !$row['needs_purchase']) {
                continue;
            }
            if ($status === 'in_stock' && $row['current_stock'] <= 0) {
                continue;
            }

            $rows[] = $row;
        }

        usort($rows, function (array $left, array $right) {
            if ($left['needs_purchase'] !== $right['needs_purchase']) {
                return $left['needs_purchase'] ? -1 : 1;
            }
            $leftCover = $left['cover_months'] ?? PHP_FLOAT_MAX;
            $rightCover = $right['cover_months'] ?? PHP_FLOAT_MAX;
            if ($leftCover !== $rightCover) {
                return $leftCover <=> $rightCover;
            }

            return strnatcasecmp($left['item_code'], $right['item_code']);
        });

        return [
            'data' => $rows,
            'summary' => [
                'item_count' => count($rows),
                'needs_purchase_count' => collect($rows)->where('needs_purchase', true)->count(),
                'negative_stock_count' => collect($rows)->where('current_stock', '<', 0)->count(),
                'insufficient_history_count' => collect($rows)->where('history_complete', false)->count(),
                'unit_conflict_count' => collect($rows)->where('unit_conflict', true)->count(),
                'as_of' => $asOf->format('Y-m-d'),
                'window_months' => $selectedWindow,
                'lead_time_days' => $leadTimeDays,
                'safety_percent' => $safetyPercent,
                'target_months' => $targetMonths,
            ],
        ];
    }

    private function monthlyIssues(string $start, string $end): Collection
    {
        return DB::connection('internal')->table('internal_material_issue_lines as l')
            ->join('internal_material_issues as i', 'i.id', '=', 'l.issue_id')
            ->selectRaw("UPPER(TRIM(COALESCE(NULLIF(l.internal_item_code, ''), l.ma_hh))) as item_code")
            ->selectRaw("MAX(COALESCE(NULLIF(l.ten_hh, ''), '')) as item_name")
            ->selectRaw("MAX(COALESCE(NULLIF(l.base_dvt, ''), NULLIF(l.dvt, ''), '')) as unit")
            ->selectRaw("DATE_FORMAT(i.issue_date, '%Y-%m') as month_key")
            ->selectRaw('SUM(COALESCE(l.base_quantity, l.quantity, 0)) as quantity')
            ->selectRaw("'Phiếu nội bộ' as source")
            ->whereRaw("COALESCE(i.issue_type, 'material') = 'material'")
            ->whereIn('i.status', ['posted', 'completed'])
            ->whereBetween('i.issue_date', [$start, $end])
            ->whereRaw("TRIM(COALESCE(NULLIF(l.internal_item_code, ''), l.ma_hh, '')) <> ''")
            ->groupBy('item_code', 'month_key')
            ->get();
    }

    private function monthlyReceipts(string $start, string $end, Collection $codes): Collection
    {
        return DB::connection('internal')->table('internal_material_receipt_lines as l')
            ->join('internal_material_receipts as r', 'r.id', '=', 'l.receipt_id')
            ->selectRaw("UPPER(TRIM(COALESCE(NULLIF(l.internal_item_code, ''), l.ma_hh))) as item_code")
            ->selectRaw("DATE_FORMAT(r.receipt_date, '%Y-%m') as month_key")
            ->selectRaw('SUM(COALESCE(l.base_quantity, l.quantity, 0)) as quantity')
            ->where('r.status', 'posted')
            ->whereBetween('r.receipt_date', [$start, $end])
            ->where(function ($query) {
                $query->whereNull('r.source')->orWhere('r.source', '<>', 'Dieu chinh kiem ke');
            })
            ->whereIn(DB::raw("UPPER(TRIM(COALESCE(NULLIF(l.internal_item_code, ''), l.ma_hh)))"), $codes->all())
            ->groupBy('item_code', 'month_key')
            ->get();
    }

    private function monthlyXntIssues(string $start, string $end, Collection $catalogs): Collection
    {
        $rows = DB::connection('internal')->table('internal_xnt_rows')
            ->selectRaw("UPPER(TRIM(COALESCE(item_code, ''))) as source_code")
            ->selectRaw("TRIM(COALESCE(item_name, '')) as item_name")
            ->selectRaw("UPPER(TRIM(COALESCE(unit, ''))) as unit")
            ->selectRaw("DATE_FORMAT(issue_date, '%Y-%m') as month_key")
            ->selectRaw('SUM(COALESCE(quantity, 0)) as quantity')
            ->where('is_active', true)
            ->whereNull('issue_id')
            ->whereBetween('issue_date', [$start, $end])
            ->groupBy('source_code', 'item_name', 'unit', 'month_key')
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $catalogByName = [];
        $catalogNameRows = [];
        foreach ($catalogs as $catalog) {
            $name = $this->normalizeItemName((string) $catalog->item_name);
            if ($name === '') {
                continue;
            }
            if (!isset($catalogByName[$name])) {
                $catalogByName[$name] = $catalog;
            }
            $catalogNameRows[] = ['name' => $name, 'catalog' => $catalog];
        }
        $conversions = $this->unitConversions();
        $converter = app(InternalUnitConverter::class);

        return $rows->map(function ($row) use ($catalogs, $catalogByName, $catalogNameRows, $conversions, $converter) {
            $sourceCode = strtoupper(trim((string) $row->source_code));
            $catalog = $sourceCode !== '' ? $catalogs->get($sourceCode) : null;
            $normalizedName = $this->normalizeItemName((string) $row->item_name);
            if (!$catalog && $normalizedName !== '') {
                $catalog = $catalogByName[$normalizedName] ?? null;
            }
            if (!$catalog && $normalizedName !== '') {
                foreach ($catalogNameRows as $entry) {
                    if (strpos($normalizedName, $entry['name']) !== false || strpos($entry['name'], $normalizedName) !== false) {
                        $catalog = $entry['catalog'];
                        break;
                    }
                }
            }
            if (!$catalog) {
                return null;
            }

            $code = strtoupper(trim((string) $catalog->item_code));
            $fromUnit = $converter->normalizeUnit($row->unit);
            $baseUnit = $converter->normalizeUnit($catalog->unit ?: $fromUnit);
            $quantity = (float) $row->quantity;
            if ($fromUnit !== '' && $baseUnit !== '' && $fromUnit !== $baseUnit) {
                $factor = $conversions[$code . '|' . $fromUnit . '|' . $baseUnit]
                    ?? $conversions['*|' . $fromUnit . '|' . $baseUnit]
                    ?? $this->defaultUnitFactor($fromUnit, $baseUnit);
                if ($factor !== null) {
                    $quantity *= $factor;
                    $fromUnit = $baseUnit;
                }
            }

            return (object) [
                'item_code' => $code,
                'item_name' => trim((string) $catalog->item_name),
                'unit' => $fromUnit ?: $baseUnit,
                'month_key' => $row->month_key,
                'quantity' => $quantity,
                'source' => 'XNT đồng bộ',
            ];
        })->filter()->values();
    }

    private function monthlyReturns(string $start, string $end, Collection $codes): Collection
    {
        return DB::connection('internal')->table('internal_material_return_lines as l')
            ->join('internal_material_returns as r', 'r.id', '=', 'l.return_id')
            ->selectRaw('UPPER(TRIM(l.material_item_code)) as item_code')
            ->selectRaw("DATE_FORMAT(r.return_date, '%Y-%m') as month_key")
            ->selectRaw('SUM(COALESCE(l.reusable_quantity, 0)) as quantity')
            ->where('r.status', 'posted')
            ->whereBetween('r.return_date', [$start, $end])
            ->whereIn(DB::raw('UPPER(TRIM(l.material_item_code))'), $codes->all())
            ->groupBy('item_code', 'month_key')
            ->get();
    }

    private function currentStock(Carbon $asOf, Collection $codes): Collection
    {
        return app(InternalStockLedger::class)
            ->query($asOf->copy()->startOfMonth()->format('Y-m-d'), $asOf->format('Y-m-d'))
            ->selectRaw("UPPER(TRIM(COALESCE(NULLIF(internal_item_code, ''), ma_hh))) as item_code")
            ->selectRaw('SUM(opening_quantity + receipt_quantity - issue_quantity) as quantity')
            ->whereIn(DB::raw("UPPER(TRIM(COALESCE(NULLIF(internal_item_code, ''), ma_hh)))"), $codes->all())
            ->groupBy('item_code')
            ->pluck('quantity', 'item_code');
    }

    private function allCatalogs(): Collection
    {
        return DB::connection('internal')->table('internal_item_catalogs')
            ->select(['item_code', 'item_name', 'unit', 'source_row'])
            ->where('is_active', true)
            ->whereNotNull('item_code')
            ->where('item_code', '<>', '')
            ->orderByDesc('source_row')
            ->get()
            ->groupBy(fn ($row) => strtoupper(trim((string) $row->item_code)))
            ->map(fn ($rows) => $rows->first());
    }

    private function firstIssueDates(Collection $codes, Collection $legacyIssues): Collection
    {
        $dates = DB::connection('internal')->table('internal_material_issue_lines as l')
            ->join('internal_material_issues as i', 'i.id', '=', 'l.issue_id')
            ->selectRaw("UPPER(TRIM(COALESCE(NULLIF(l.internal_item_code, ''), l.ma_hh))) as item_code")
            ->selectRaw('MIN(i.issue_date) as first_issue_date')
            ->whereRaw("COALESCE(i.issue_type, 'material') = 'material'")
            ->whereIn('i.status', ['posted', 'completed'])
            ->whereIn(DB::raw("UPPER(TRIM(COALESCE(NULLIF(l.internal_item_code, ''), l.ma_hh)))"), $codes->all())
            ->groupBy('item_code')
            ->get()
            ->pluck('first_issue_date', 'item_code');

        foreach ($legacyIssues->groupBy('item_code') as $code => $rows) {
            $legacyDate = $rows->min('month_key') . '-01';
            if (!$dates->has($code) || $legacyDate < $dates->get($code)) {
                $dates->put($code, $legacyDate);
            }
        }

        return $dates;
    }

    private function aggregateMonthly(Collection $rows): Collection
    {
        return $rows->groupBy(fn ($row) => $row->item_code . '|' . $row->month_key)
            ->map(function ($group) {
                $first = $group->first();

                return (object) [
                    'item_code' => $first->item_code,
                    'item_name' => $group->pluck('item_name')->filter()->first() ?: '',
                    'unit' => $group->pluck('unit')->filter()->first() ?: '',
                    'source_units' => $group->pluck('unit')->filter()->unique()->values()->all(),
                    'sources' => $group->pluck('source')->filter()->unique()->values()->all(),
                    'month_key' => $first->month_key,
                    'quantity' => (float) $group->sum('quantity'),
                ];
            })->values();
    }

    private function unitConversions(): array
    {
        $map = [];
        $rows = DB::connection('internal')->table('internal_unit_conversions')
            ->select(['item_code', 'from_unit', 'to_unit', 'factor'])
            ->orderByRaw("CASE WHEN item_code IS NULL OR item_code = '' THEN 1 ELSE 0 END")
            ->get();
        foreach ($rows as $row) {
            $itemCode = strtoupper(trim((string) $row->item_code)) ?: '*';
            $key = $itemCode . '|' . strtoupper(trim((string) $row->from_unit)) . '|' . strtoupper(trim((string) $row->to_unit));
            if (!isset($map[$key])) {
                $map[$key] = (float) $row->factor;
            }
        }

        return $map;
    }

    private function defaultUnitFactor(string $from, string $to): ?float
    {
        $defaults = ['G|KG' => 0.001, 'KG|G' => 1000.0];

        return $defaults[$from . '|' . $to] ?? null;
    }

    private function normalizeItemName(string $value): string
    {
        $value = preg_replace('/^\s*[0-9]+[\.\)]\s*/', '', $value);
        $value = Str::ascii(mb_strtolower($value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);

        return trim(preg_replace('/\s+/', ' ', $value));
    }

    private function monthlyMap(Collection $rows, string $value): array
    {
        $map = [];
        foreach ($rows as $row) {
            $map[$row->item_code][$row->month_key] = (float) $row->{$value};
        }

        return $map;
    }

    private function monthKeys(Carbon $end, int $months): array
    {
        $keys = [];
        $cursor = $end->copy()->startOfMonth()->subMonthsNoOverflow($months - 1);
        for ($index = 0; $index < $months; $index++) {
            $keys[] = $cursor->copy()->addMonthsNoOverflow($index)->format('Y-m');
        }

        return $keys;
    }

    private function sumMonths(array $map, string $code, array $months): float
    {
        return (float) collect($months)->sum(fn ($month) => $map[$code][$month] ?? 0);
    }

    private function matchesKeyword(array $row, string $keyword): bool
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return true;
        }

        return mb_stripos($row['item_code'] . ' ' . $row['item_name'], $keyword) !== false;
    }

    private function emptyReport(Carbon $asOf, int $window, int $leadTime, float $safety, float $target): array
    {
        return [
            'data' => [],
            'summary' => [
                'item_count' => 0,
                'needs_purchase_count' => 0,
                'negative_stock_count' => 0,
                'insufficient_history_count' => 0,
                'unit_conflict_count' => 0,
                'as_of' => $asOf->format('Y-m-d'),
                'window_months' => $window,
                'lead_time_days' => $leadTime,
                'safety_percent' => $safety,
                'target_months' => $target,
            ],
        ];
    }
}
