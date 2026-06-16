<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class InternalDocumentNumber
{
    public function next(string $prefix, int $padding = 4): string
    {
        $date = now()->format('Ymd');
        $sequenceKey = $prefix . ':' . $date;
        $existingValue = $this->existingValue($prefix, $date);

        DB::connection('internal')->table('internal_document_sequences')->insertOrIgnore([
            'sequence_key' => $sequenceKey,
            'current_value' => $existingValue,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::connection('internal')
            ->table('internal_document_sequences')
            ->where('sequence_key', $sequenceKey)
            ->lockForUpdate()
            ->first();

        $number = max((int) $row->current_value, $existingValue) + 1;

        DB::connection('internal')
            ->table('internal_document_sequences')
            ->where('sequence_key', $sequenceKey)
            ->update([
                'current_value' => $number,
                'updated_at' => now(),
            ]);

        return $prefix . '-' . $date . '-' . str_pad((string) $number, $padding, '0', STR_PAD_LEFT);
    }

    private function existingValue(string $prefix, string $date): int
    {
        $sources = [
            'PNTP' => ['internal_material_receipts', 'receipt_code'],
            'PXVT' => ['internal_material_issues', 'issue_code'],
            'PXBTP' => ['internal_material_issues', 'issue_code'],
            'PXTP' => ['internal_material_issues', 'issue_code'],
            'PK' => ['inventory_packages', 'package_code'],
        ];

        if (!isset($sources[$prefix])) {
            return 0;
        }

        [$table, $column] = $sources[$prefix];
        $codePrefix = $prefix . '-' . $date . '-';
        $lastCode = DB::connection('internal')
            ->table($table)
            ->where($column, 'like', $codePrefix . '%')
            ->orderByDesc($column)
            ->value($column);

        if (!$lastCode || strpos($lastCode, $codePrefix) !== 0) {
            return 0;
        }

        return (int) substr($lastCode, strlen($codePrefix));
    }
}
