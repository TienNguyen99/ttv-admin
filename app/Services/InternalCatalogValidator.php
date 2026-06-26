<?php

namespace App\Services;

use App\Models\InternalItemCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InternalCatalogValidator
{
    public function errorsForLines(Collection $lines, string $field = 'internal_item_code'): array
    {
        $normalizedCodes = $lines
            ->map(fn ($line) => $this->normalizeCode(data_get($line, $field)))
            ->filter()
            ->unique()
            ->values();

        $existing = $normalizedCodes->isEmpty()
            ? collect()
            : InternalItemCatalog::query()
                ->where('is_active', true)
                ->whereIn(DB::raw('UPPER(item_code)'), $normalizedCodes->all())
                ->pluck('item_code')
                ->mapWithKeys(fn ($code) => [$this->normalizeCode($code) => true]);

        $errors = [];

        foreach ($lines->values() as $index => $line) {
            $code = $this->normalizeCode(data_get($line, $field));

            if ($code === '') {
                $errors[] = [
                    'line' => $index + 1,
                    'field' => $field,
                    'code' => '',
                    'message' => 'Dòng ' . ($index + 1) . ': Mã nội bộ là bắt buộc và phải có trong DANH MỤC.',
                    'suggestions' => [],
                ];
                continue;
            }

            if (!$existing->has($code)) {
                $suggestions = $this->suggestionsFor($code);
                $suffix = $suggestions->isNotEmpty()
                    ? ' Gợi ý: ' . $suggestions->implode(', ')
                    : '';

                $errors[] = [
                    'line' => $index + 1,
                    'field' => $field,
                    'code' => $code,
                    'message' => 'Dòng ' . ($index + 1) . ': Mã nội bộ ' . $code . ' không có trong DANH MỤC.' . $suffix,
                    'suggestions' => $suggestions->values()->all(),
                ];
            }
        }

        return $errors;
    }

    public function responseForErrors(array $errors)
    {
        return response()->json([
            'message' => $errors[0]['message'] ?? 'Có mã nội bộ không hợp lệ.',
            'catalog_errors' => $errors,
            'errors' => [
                'internal_item_code' => array_map(fn ($error) => $error['message'], $errors),
            ],
        ], 422);
    }

    private function suggestionsFor(string $code): Collection
    {
        return InternalItemCatalog::query()
            ->where('is_active', true)
            ->where(function ($query) use ($code) {
                $query->where('item_code', 'like', $code . '%')
                    ->orWhere('item_code', 'like', '%' . $code . '%');
            })
            ->orderByRaw('CASE WHEN item_code LIKE ? THEN 0 ELSE 1 END', [$code . '%'])
            ->orderBy('item_code')
            ->limit(5)
            ->pluck('item_code')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values();
    }

    private function normalizeCode($value): string
    {
        return mb_strtoupper(trim((string) $value));
    }
}
