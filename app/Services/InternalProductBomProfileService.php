<?php

namespace App\Services;

use App\Models\InternalItemCatalog;
use App\Models\InternalProductBomProfile;
use Illuminate\Support\Facades\DB;

class InternalProductBomProfileService
{
    private InternalBomCalculator $calculator;

    public function __construct(InternalBomCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    public function save(array $data): InternalProductBomProfile
    {
        $itemCode = $this->calculator->code($data['item_code']);
        $catalog = $this->catalog($itemCode);
        $operations = $this->calculator->normalizeOperations($data['operations'] ?? []);
        $materials = $this->calculator->normalizeMaterials(
            $data['materials'],
            $operations->pluck('operation_code')
        );

        $catalogByCode = InternalItemCatalog::query()
            ->where('is_active', true)
            ->whereIn(DB::raw('UPPER(TRIM(item_code))'), $materials->pluck('material_code')->unique()->all())
            ->orderByDesc('source_row')
            ->get()
            ->keyBy(fn ($row) => $this->calculator->code($row->item_code));

        return DB::connection('internal')->transaction(function () use (
            $data,
            $itemCode,
            $catalog,
            $operations,
            $materials,
            $catalogByCode
        ) {
            $profile = InternalProductBomProfile::query()
                ->where('item_code', $itemCode)
                ->lockForUpdate()
                ->first();
            if (!$profile) {
                $profile = new InternalProductBomProfile(['item_code' => $itemCode, 'revision' => 0]);
            }

            $profile->item_name = trim((string) ($data['item_name'] ?? ''))
                ?: trim((string) ($catalog->item_name ?? ''));
            $profile->unit = mb_strtoupper(trim((string) ($data['unit'] ?? '')))
                ?: mb_strtoupper(trim((string) ($catalog->unit ?? '')));
            $profile->revision = (int) $profile->revision + 1;
            $profile->status = 'active';
            $profile->note = trim((string) ($data['note'] ?? ''));
            $profile->save();

            $profile->routings()->delete();
            foreach ($operations as $index => $operation) {
                $profile->routings()->create($operation + ['sequence' => $index + 1]);
            }

            $profile->lines()->delete();
            foreach ($materials as $index => $material) {
                $catalogMaterial = $catalogByCode->get($material['material_code']);
                $profile->lines()->create($material + [
                    'sequence' => $index + 1,
                    'material_name' => $material['material_name']
                        ?: trim((string) ($catalogMaterial->item_name ?? '')),
                    'unit' => $material['unit']
                        ?: mb_strtoupper(trim((string) ($catalogMaterial->unit ?? ''))),
                ]);
            }

            return $profile->fresh()->load(['lines', 'routings']);
        });
    }

    private function catalog(string $itemCode): ?InternalItemCatalog
    {
        return InternalItemCatalog::query()
            ->where('is_active', true)
            ->whereRaw('UPPER(TRIM(item_code)) = ?', [$itemCode])
            ->orderByDesc('source_row')
            ->first();
    }
}
