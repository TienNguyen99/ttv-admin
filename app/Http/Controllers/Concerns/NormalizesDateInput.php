<?php

namespace App\Http\Controllers\Concerns;

use Carbon\Carbon;

trait NormalizesDateInput
{
    protected function normalizeDateInput($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        foreach (['d/m/Y', 'd-m-Y', 'm/d/Y', 'm-d-Y', 'Y/m/d'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date && $date->format($format) === $value) {
                    return $date->format('Y-m-d');
                }
            } catch (\Throwable $ignored) {
                // Try the next accepted human-entered format.
            }
        }

        if (is_numeric($value) && (float) $value > 20000 && (float) $value < 80000) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->format('Y-m-d');
        }

        return Carbon::parse($value)->format('Y-m-d');
    }

    protected function normalizeDateFields(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->normalizeDateInput($data[$field]);
            }
        }

        return $data;
    }
}
