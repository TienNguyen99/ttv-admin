<?php

namespace App\Services;

use App\Models\InternalItemCatalog;
use Illuminate\Support\Str;

class PantoneColorMatcher
{
    private array $colors = [];

    private array $byCode = [];

    public function __construct()
    {
        $path = storage_path('app/pantone-colors.json');
        if (!is_file($path)) {
            $path = resource_path('data/pantone-colors.json');
        }
        if (!is_file($path)) {
            return;
        }

        $json = json_decode((string) file_get_contents($path), true);
        if (!is_array($json)) {
            return;
        }

        foreach ($json as $row) {
            $pantone = trim((string) ($row['pantone'] ?? ''));
            $hex = strtolower(trim((string) ($row['hex'] ?? '')));
            if ($pantone === '' || !$this->isHex($hex)) {
                continue;
            }

            $color = [
                'pantone' => strtoupper($pantone),
                'hex' => $hex,
            ];

            $this->colors[] = $color;
            $this->byCode[$this->normalizePantoneCode($pantone)] = $color;
        }
    }

    public function matchCatalog(?InternalItemCatalog $catalog): array
    {
        if (!$catalog) {
            return $this->emptyMatch();
        }

        $raw = is_array($catalog->raw_data ?? null) ? $catalog->raw_data : [];
        $explicitHex = $this->pickRaw($raw, ['hex', 'ma mau hex', 'color hex', 'mau hex']);
        if ($this->isHex($explicitHex)) {
            return [
                'pantone' => $this->pickRaw($raw, ['pantone', 'ma pantone', 'pms']),
                'hex' => strtolower($explicitHex),
                'source' => 'catalog_hex',
            ];
        }

        $explicitPantone = $this->pickRaw($raw, ['pantone', 'ma pantone', 'pms']);
        $matched = $this->matchPantoneText($explicitPantone);
        if ($matched['hex']) {
            $matched['source'] = 'catalog_pantone';
            return $matched;
        }

        return $this->matchValues([
            $catalog->item_code,
            $catalog->item_name,
            $catalog->color,
            $catalog->logo_color,
            $catalog->size,
            $catalog->side,
        ]);
    }

    public function matchValues(array $values, ?InternalItemCatalog $catalog = null): array
    {
        if ($catalog) {
            $matched = $this->matchCatalog($catalog);
            if ($matched['hex']) {
                return $matched;
            }
        }

        foreach ($values as $value) {
            $matched = $this->matchPantoneText((string) $value);
            if ($matched['hex']) {
                return $matched;
            }
        }

        return $this->matchCommonColor($values);
    }

    private function matchPantoneText(string $text): array
    {
        $text = trim($text);
        if ($text === '' || empty($this->byCode)) {
            return $this->emptyMatch();
        }

        if (preg_match_all('/(?:PANTONE|PMS)?\s*([0-9]{2,4})\s*[- ]?\s*([A-Z]{1,4})\b/i', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $key = $this->normalizePantoneCode($match[1] . $match[2]);
                if (isset($this->byCode[$key])) {
                    return $this->byCode[$key] + ['source' => 'text'];
                }
            }
        }

        $key = $this->normalizePantoneCode($text);
        return $this->byCode[$key] ?? $this->emptyMatch();
    }

    private function matchCommonColor(array $values): array
    {
        $map = [
            'black' => '#111111',
            'den' => '#111111',
            'white' => '#ffffff',
            'trang' => '#ffffff',
            'red' => '#dc2626',
            'do' => '#dc2626',
            'blue' => '#2563eb',
            'xanh' => '#2563eb',
            'green' => '#16a34a',
            'yellow' => '#facc15',
            'vang' => '#facc15',
            'grey' => '#6b7280',
            'gray' => '#6b7280',
            'xam' => '#6b7280',
        ];

        $text = $this->normalizeSearch(implode(' ', array_map('strval', $values)));
        foreach ($map as $word => $hex) {
            if (strpos($text, $word) !== false) {
                return ['pantone' => '', 'hex' => $hex, 'source' => 'common_name'];
            }
        }

        return $this->emptyMatch();
    }

    private function pickRaw(array $row, array $keys): string
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalized[$this->normalizeHeader($key)] = trim((string) $value);
        }

        foreach ($keys as $key) {
            $key = $this->normalizeHeader($key);
            if (($normalized[$key] ?? '') !== '') {
                return $normalized[$key];
            }
        }

        return '';
    }

    private function normalizePantoneCode(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', Str::ascii(mb_strtolower($value))) ?: '';
    }

    private function normalizeHeader($value): string
    {
        $value = preg_replace('/[^a-z0-9]+/', ' ', Str::ascii(mb_strtolower(trim((string) $value))));
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    private function normalizeSearch(string $value): string
    {
        $value = Str::ascii(mb_strtolower($value));
        return preg_replace('/[^a-z0-9]+/', ' ', $value) ?: '';
    }

    private function isHex(string $value): bool
    {
        return (bool) preg_match('/^#[0-9a-f]{6}$/i', trim($value));
    }

    private function emptyMatch(): array
    {
        return ['pantone' => '', 'hex' => '', 'source' => ''];
    }
}
