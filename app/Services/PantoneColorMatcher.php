<?php

namespace App\Services;

use App\Models\InternalItemCatalog;
use Illuminate\Support\Str;

class PantoneColorMatcher
{
    private array $colors = [];

    private array $byCode = [];

    private array $byName = [];

    private array $nameKeys = [];

    public function __construct()
    {
        $this->loadPantoneColors();
        $this->loadTcxColors();

        $this->nameKeys = array_keys($this->byName);
        usort($this->nameKeys, fn ($a, $b) => strlen($b) <=> strlen($a));
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

        if (preg_match_all('/\b([0-9]{2})\s*[- ]\s*([0-9]{4})(?:\s*(TCX|TPX|TPG))?\b/i', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $suffix = $match[3] ?? '';
                $keys = [
                    $this->normalizePantoneCode($match[1] . '-' . $match[2] . ' ' . $suffix),
                    $this->normalizePantoneCode($match[1] . '-' . $match[2] . ' TCX'),
                    $this->normalizePantoneCode($match[1] . '-' . $match[2]),
                ];

                foreach ($keys as $key) {
                    if (isset($this->byCode[$key])) {
                        return array_merge($this->byCode[$key], ['source' => 'text']);
                    }
                }
            }
        }

        if (preg_match_all('/(?:PANTONE|PMS)?\s*([0-9]{2,4})\s*[- ]?\s*([A-Z]{1,4})\b/i', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $key = $this->normalizePantoneCode($match[1] . $match[2]);
                if (isset($this->byCode[$key])) {
                    return array_merge($this->byCode[$key], ['source' => 'text']);
                }
            }
        }

        $key = $this->normalizePantoneCode($text);
        if (isset($this->byCode[$key])) {
            return array_merge($this->byCode[$key], ['source' => 'text']);
        }

        return $this->matchColorName($text);
    }

    private function matchColorName(string $text): array
    {
        if (empty($this->nameKeys)) {
            return $this->emptyMatch();
        }

        $haystack = ' ' . $this->normalizeSearch($text) . ' ';
        foreach ($this->nameKeys as $nameKey) {
            if ($nameKey !== '' && strpos($haystack, ' ' . $nameKey . ' ') !== false) {
                return array_merge($this->byName[$nameKey], ['source' => 'color_name']);
            }
        }

        return $this->emptyMatch();
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

    private function normalizeHex(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value !== '' && $value[0] !== '#') {
            $value = '#' . $value;
        }

        return $this->isHex($value) ? $value : '';
    }

    private function loadPantoneColors(): void
    {
        $json = $this->readJson('pantone-colors.json');
        if (!is_array($json)) {
            return;
        }

        foreach ($json as $row) {
            if (!is_array($row)) {
                continue;
            }

            $this->registerColor(
                trim((string) ($row['pantone'] ?? '')),
                (string) ($row['hex'] ?? '')
            );
        }
    }

    private function loadTcxColors(): void
    {
        $json = $this->readJson('pantone-tcx.json');
        if (!is_array($json)) {
            return;
        }

        foreach ($json as $code => $row) {
            if (!is_array($row)) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $displayCode = strtoupper(trim((string) $code)) . ' TCX';
            $this->registerColor($displayCode, (string) ($row['hex'] ?? ''), $name);
        }
    }

    private function readJson(string $fileName): ?array
    {
        $path = storage_path('app/' . $fileName);
        if (!is_file($path)) {
            $path = resource_path('data/' . $fileName);
        }
        if (!is_file($path)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($path), true);
        return is_array($json) ? $json : null;
    }

    private function registerColor(string $pantone, string $hex, string $name = ''): void
    {
        $pantone = trim($pantone);
        $hex = $this->normalizeHex($hex);
        if ($pantone === '' || $hex === '') {
            return;
        }

        $color = [
            'pantone' => strtoupper($pantone),
            'hex' => $hex,
        ];

        $this->colors[] = $color;
        $this->byCode[$this->normalizePantoneCode($pantone)] = $color;

        if (preg_match('/^([0-9]{2})-([0-9]{4})\s+TCX$/i', $pantone, $match)) {
            $this->byCode[$this->normalizePantoneCode($match[1] . '-' . $match[2])] = $color;
        }

        $nameKey = $this->normalizeSearch($name);
        if ($nameKey !== '') {
            $this->byName[$nameKey] = $color;
        }
    }

    private function emptyMatch(): array
    {
        return ['pantone' => '', 'hex' => '', 'source' => ''];
    }
}
