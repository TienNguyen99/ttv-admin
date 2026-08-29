<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class InternalBomCalculator
{
    private InternalUnitConverter $unitConverter;

    public function __construct(InternalUnitConverter $unitConverter)
    {
        $this->unitConverter = $unitConverter;
    }

    public function normalizeOperations(array $operations): Collection
    {
        $normalized = collect($operations)->map(function ($operation) {
            return [
                'operation_code' => $this->code($operation['operation_code'] ?? ''),
                'operation_name' => trim((string) ($operation['operation_name'] ?? '')),
                'work_center' => trim((string) ($operation['work_center'] ?? '')),
                'is_outsourced' => !empty($operation['is_outsourced']),
                'note' => trim((string) ($operation['note'] ?? '')),
            ];
        })->filter(fn ($operation) => $operation['operation_code'] !== '')->values();

        if ($normalized->pluck('operation_code')->duplicates()->isNotEmpty()) {
            throw new InvalidArgumentException('Mã công đoạn không được trùng trong cùng một mã hàng.');
        }

        return $normalized;
    }

    public function normalizeMaterials(array $materials, Collection $operationCodes): Collection
    {
        $normalized = collect($materials)->map(function ($material) {
            $mode = in_array(($material['calculation_mode'] ?? 'consumption'), ['yield', 'formula'], true)
                ? $material['calculation_mode']
                : 'consumption';
            $yieldQuantity = (float) ($material['yield_quantity'] ?? 0);
            $consumption = $mode === 'yield'
                ? ($yieldQuantity > 0 ? 1 / $yieldQuantity : 0)
                : ($mode === 'formula' ? 0 : (float) ($material['consumption_per_unit'] ?? 0));

            return [
                'material_code' => $this->code($material['material_code'] ?? ''),
                'material_name' => trim((string) ($material['material_name'] ?? '')),
                'component_role' => $this->code($material['component_role'] ?? '') ?: 'CHUNG',
                'calculation_mode' => $mode,
                'formula_code' => $mode === 'formula' ? $this->code($material['formula_code'] ?? '') : null,
                'formula_output_per_unit' => $mode === 'formula' ? (float) ($material['formula_output_per_unit'] ?? 0) : null,
                'formula_output_unit' => $mode === 'formula'
                    ? $this->unitConverter->normalizeUnit($material['formula_output_unit'] ?? '')
                    : null,
                'formula_part' => $mode === 'formula' ? (float) ($material['formula_part'] ?? 0) : null,
                'unit' => $this->unitConverter->normalizeUnit($material['unit'] ?? ''),
                'consumption_per_unit' => $consumption,
                'yield_quantity' => $mode === 'yield' ? $yieldQuantity : null,
                'waste_percent' => (float) ($material['waste_percent'] ?? 0),
                'round_to_whole' => !empty($material['round_to_whole']),
                'operation_code' => $this->code($material['operation_code'] ?? ''),
                'note' => trim((string) ($material['note'] ?? '')),
            ];
        })->values();

        $this->expandFormulaConsumption($normalized);

        $invalidRate = $normalized->search(fn ($material) => $material['consumption_per_unit'] <= 0);
        if ($invalidRate !== false) {
            throw new InvalidArgumentException(
                'Dòng vật tư ' . ((int) $invalidRate + 1) . ' chưa có định mức hoặc năng suất hợp lệ.'
            );
        }

        $invalidOperation = $normalized->pluck('operation_code')->filter()
            ->first(fn ($code) => !$operationCodes->contains($code));
        if ($invalidOperation) {
            throw new InvalidArgumentException(
                'Công đoạn ' . $invalidOperation . ' của dòng vật tư chưa có trong tuyến công đoạn.'
            );
        }

        return $normalized;
    }

    public function requirement($line, float $orderQuantity): array
    {
        $yieldQuantity = (float) data_get($line, 'yield_quantity', 0);
        $baseQuantity = data_get($line, 'calculation_mode', 'consumption') === 'yield' && $yieldQuantity > 0
            ? $orderQuantity / $yieldQuantity
            : $orderQuantity * (float) data_get($line, 'consumption_per_unit', 0);
        $exact = $baseQuantity * (1 + ((float) data_get($line, 'waste_percent', 0) / 100));

        return [
            'exact' => $exact,
            'required' => $this->shouldRoundRequirement($line)
                ? (float) ceil($exact - 0.000000001)
                : $exact,
        ];
    }

    public function shouldRoundRequirement($line): bool
    {
        if (!empty(data_get($line, 'round_to_whole'))) {
            return true;
        }
        if (data_get($line, 'calculation_mode', 'consumption') !== 'yield') {
            return false;
        }

        $unit = strtoupper(Str::ascii($this->unitConverter->normalizeUnit(data_get($line, 'unit'))));

        return in_array($unit, ['TAM', 'CUON', 'THUNG', 'PCS', 'CAI', 'BO'], true);
    }

    public function code($value): string
    {
        return mb_strtoupper(trim((string) $value));
    }

    public function normalizeUnit($value): string
    {
        return $this->unitConverter->normalizeUnit($value);
    }

    private function expandFormulaConsumption(Collection $materials): void
    {
        foreach ($materials->where('calculation_mode', 'formula')->groupBy('formula_code') as $formulaCode => $lines) {
            if ($formulaCode === '' || $lines->count() < 2) {
                throw new InvalidArgumentException('Công thức pha cần mã nhóm và ít nhất 2 nguyên liệu.');
            }

            $outputQuantities = $lines->pluck('formula_output_per_unit')->unique()->values();
            $outputUnits = $lines->pluck('formula_output_unit')->unique()->values();
            $totalParts = (float) $lines->sum('formula_part');
            if ($outputQuantities->count() !== 1
                || (float) $outputQuantities->first() <= 0
                || $outputUnits->count() !== 1
                || !$outputUnits->first()
                || $totalParts <= 0) {
                throw new InvalidArgumentException(
                    'Các dòng công thức ' . $formulaCode . ' phải cùng tổng hỗn hợp/PCS, cùng đơn vị và có tỷ lệ lớn hơn 0.'
                );
            }

            foreach ($lines as $index => $line) {
                $factor = $this->unitConverter->factor(
                    $line['material_code'],
                    $line['formula_output_unit'],
                    $line['unit']
                );
                if ($factor === null) {
                    throw new InvalidArgumentException(
                        'Chưa có quy đổi ' . $line['formula_output_unit'] . ' sang ' . $line['unit']
                        . ' cho ' . $line['material_code'] . '.'
                    );
                }

                $materials->put($index, array_merge($line, [
                    'consumption_per_unit' => (float) $line['formula_output_per_unit']
                        * ((float) $line['formula_part'] / $totalParts)
                        * $factor,
                ]));
            }
        }
    }
}
