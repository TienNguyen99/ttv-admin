<?php

namespace App\Services;

use App\Models\InternalColorMapping;
use App\Models\InternalItemCatalog;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PantoneColorMatcher
{
    private array $colors = [];

    private array $byCode = [];

    private array $byName = [];

    private int $maxNameWords = 1;

    private array $nameMatchCache = [];

    private array $byInternalCode = [];

    public function __construct()
    {
        $this->loadPantoneColors();
        $this->loadTcxColors();
        $this->loadInternalColors();

    }

    public function matchCatalog(?InternalItemCatalog $catalog): array
    {
        if (!$catalog) {
            return $this->emptyMatch();
        }

        $internal = $this->matchInternalColor([
            $catalog->item_code,
            $catalog->color,
        ]);
        if ($internal['hex']) {
            return $internal;
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

        $internal = $this->matchInternalColor($values);
        if ($internal['hex']) {
            return $internal;
        }

        foreach ($values as $value) {
            $text = (string) $value;
            $matched = $this->isExplicitPantoneText($text)
                ? $this->matchPantoneText($text)
                : $this->matchColorName($text);
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
        if (empty($this->byName)) {
            return $this->emptyMatch();
        }

        $normalized = trim($this->normalizeSearch($text));
        if ($normalized === '') {
            return $this->emptyMatch();
        }
        if (array_key_exists($normalized, $this->nameMatchCache)) {
            return $this->nameMatchCache[$normalized];
        }

        $words = preg_split('/\s+/', $normalized) ?: [];
        $maxWords = min(count($words), $this->maxNameWords);
        $matchedNameKey = '';
        for ($wordCount = $maxWords; $wordCount >= 1; $wordCount--) {
            $lastStart = count($words) - $wordCount;
            for ($start = 0; $start <= $lastStart; $start++) {
                $nameKey = implode(' ', array_slice($words, $start, $wordCount));
                if (isset($this->byName[$nameKey]) && strlen($nameKey) > strlen($matchedNameKey)) {
                    $matchedNameKey = $nameKey;
                }
            }
        }
        if ($matchedNameKey !== '') {
            return $this->nameMatchCache[$normalized] = array_merge(
                $this->byName[$matchedNameKey],
                ['source' => 'color_name']
            );
        }

        return $this->nameMatchCache[$normalized] = $this->emptyMatch();
    }

    private function matchCommonColor(array $values): array
    {
        $map = [
            'black' => ['#111111', 'Đen'],
            'den' => ['#111111', 'Đen'],
            'white' => ['#ffffff', 'Trắng'],
            'trang' => ['#ffffff', 'Trắng'],
            'red' => ['#dc2626', 'Đỏ'],
            'do' => ['#dc2626', 'Đỏ'],
            'blue' => ['#2563eb', 'Xanh dương'],
            'xanh' => ['#2563eb', 'Xanh'],
            'green' => ['#16a34a', 'Xanh lá'],
            'yellow' => ['#facc15', 'Vàng'],
            'vang' => ['#facc15', 'Vàng'],
            'grey' => ['#6b7280', 'Xám'],
            'gray' => ['#6b7280', 'Xám'],
            'xam' => ['#6b7280', 'Xám'],
        ];

        $text = $this->normalizeSearch(implode(' ', array_map('strval', $values)));
        foreach ($map as $word => [$hex, $name]) {
            if (strpos($text, $word) !== false) {
                return ['pantone' => '', 'hex' => $hex, 'name' => $name, 'source' => 'common_name'];
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

    private function loadInternalColors(): void
    {
        try {
            if (!Schema::connection('internal')->hasTable('internal_color_mappings')) {
                return;
            }

            InternalColorMapping::query()
                ->where('is_active', true)
                ->get(['color_code', 'color_name', 'hex', 'pantone_code'])
                ->each(function ($row) {
                    $key = $this->normalizePantoneCode((string) $row->color_code);
                    $hex = $this->normalizeHex((string) $row->hex);
                    if ($key === '' || $hex === '') return;
                    $this->byInternalCode[$key] = [
                        'pantone' => trim((string) $row->pantone_code),
                        'hex' => $hex,
                        'name' => trim((string) $row->color_name),
                        'source' => 'internal_mapping',
                    ];
                });
        } catch (\Throwable $error) {
            // Migrations may not have run yet; color matching must remain available.
        }
    }

    private function matchInternalColor(array $values): array
    {
        foreach ($values as $value) {
            $text = trim((string) $value);
            $candidates = [$text];

            foreach (preg_split('/\s+(?:-|\/|\|)\s+|[,;]+/', $text) ?: [] as $part) {
                if (trim($part) !== '') {
                    $candidates[] = trim($part);
                }
            }

            foreach (array_unique($candidates) as $candidate) {
                $key = $this->normalizePantoneCode($candidate);
                if ($key !== '' && isset($this->byInternalCode[$key])) {
                    return $this->byInternalCode[$key];
                }
            }
        }

        return $this->emptyMatch();
    }

    private function isExplicitPantoneText(string $text): bool
    {
        $exactCode = $this->normalizePantoneCode(trim($text));

        return ($exactCode !== '' && isset($this->byCode[$exactCode]))
            || (bool) preg_match('/\b(?:PANTONE|PMS|TCX|TPX|TPG)\b|\b[0-9]{2}\s*[- ]\s*[0-9]{4}\b/i', $text);
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
            'name' => $name,
        ];

        $this->colors[] = $color;
        $this->byCode[$this->normalizePantoneCode($pantone)] = $color;

        if (preg_match('/^([0-9]{2})-([0-9]{4})\s+TCX$/i', $pantone, $match)) {
            $this->byCode[$this->normalizePantoneCode($match[1] . '-' . $match[2])] = $color;
        }

        $nameKey = $this->normalizeSearch($name);
        if ($nameKey !== '') {
            $this->byName[$nameKey] = $color;
            $this->maxNameWords = max($this->maxNameWords, substr_count($nameKey, ' ') + 1);
        }
    }

    private function emptyMatch(): array
    {
        return ['pantone' => '', 'hex' => '', 'name' => '', 'source' => ''];
    }
}
