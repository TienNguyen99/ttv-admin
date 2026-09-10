<?php

namespace App\Services\Panel;

use App\Models\PanelNormalization;
use Illuminate\Support\Facades\Cache;

class PanelRuleRepository
{
    public const CACHE_KEY = 'panel_normalization_rules_v1';

    private $canonicalizer;

    public function __construct(PanelCanonicalizer $canonicalizer)
    {
        $this->canonicalizer = $canonicalizer;
    }

    public function rules(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(10), function () {
            $rows = PanelNormalization::query()
                ->with('aliases')
                ->orderBy('sort_order')
                ->orderBy('standard_name')
                ->get();

            $automatic = [];
            $aliasStates = [];
            $protectedAliases = [];

            foreach ($rows as $row) {
                foreach ($row->aliases as $alias) {
                    $normalized = $alias->normalized_alias;
                    $aliasStates[$normalized] = [
                        'standard_name' => $row->standard_name,
                        'status' => $row->status,
                        'is_active' => (bool) $row->is_active,
                    ];

                    if ($row->is_active && strpos($normalized, '/') !== false) {
                        $protectedAliases[] = $alias->alias;
                    }

                    if ($row->is_active && $row->status === 'AUTO') {
                        $automatic[$normalized] = $row->standard_name;
                    }
                }
            }

            usort($protectedAliases, function ($left, $right) {
                return strlen($right) <=> strlen($left);
            });

            return [
                'automatic' => $automatic,
                'alias_states' => $aliasStates,
                'protected_aliases' => array_values(array_unique($protectedAliases)),
                'snapshot' => $rows->map(function ($row) {
                    return [
                        'standard_name' => $row->standard_name,
                        'status' => $row->status,
                        'is_active' => (bool) $row->is_active,
                        'sort_order' => (int) $row->sort_order,
                        'note' => $row->note,
                        'aliases' => $row->aliases->pluck('alias')->values()->all(),
                    ];
                })->all(),
            ];
        });
    }

    public function canonical($value): string
    {
        return $this->canonicalizer->canonical($value);
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
