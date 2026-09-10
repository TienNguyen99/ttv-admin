<?php

namespace Database\Seeders;

use App\Models\PanelNormalization;
use App\Models\PanelNormalizationAlias;
use App\Services\Panel\PanelCanonicalizer;
use App\Services\Panel\PanelRuleRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PanelNormalizationSeeder extends Seeder
{
    public function run()
    {
        $canonicalizer = app(PanelCanonicalizer::class);
        $rules = [
            'FRONT' => ['F', 'FRONT'],
            'LEFT BACK' => ['LB', 'L/B', 'L/BACK', 'LEFT/BACK'],
            'RIGHT SIDE' => ['RS', 'R/S'],
            'LEFT SIDE' => ['LS', 'L/S', 'L/SIDE'],
            'BACK' => ['B', 'BACK'],
            'UPPER' => ['UP', 'UPPER'],
            'RIGHT BACK' => ['RB', 'R/B', 'R/BACK'],
            'LEFT FRONT' => ['LF', 'L/F', 'L/FRONT'],
            'TOP' => ['TOP'],
            'UNDER' => ['UNDER', "'UNDER"],
            'RIGHT FRONT' => ['RF'],
            'INSIDE RIGHT SIDE' => ['INSIDE R/S'],
            'RIGHT' => ['R', 'RIGHT'],
            'LEFT' => ['L', 'LEFT'],
        ];

        DB::connection('internal')->transaction(function () use ($rules, $canonicalizer) {
            $sortOrder = 10;
            foreach ($rules as $standardName => $aliases) {
                $normalizedStandard = $canonicalizer->canonical($standardName);
                $normalization = PanelNormalization::query()->firstOrCreate(
                    ['normalized_standard_name' => $normalizedStandard],
                    [
                        'standard_name' => $standardName,
                        'status' => 'AUTO',
                        'is_active' => true,
                        'sort_order' => $sortOrder,
                    ]
                );

                foreach ($aliases as $alias) {
                    PanelNormalizationAlias::query()->firstOrCreate(
                        ['normalized_alias' => $canonicalizer->canonical($alias)],
                        ['panel_normalization_id' => $normalization->id, 'alias' => $alias]
                    );
                }
                $sortOrder += 10;
            }
        });

        app(PanelRuleRepository::class)->forget();
    }
}
