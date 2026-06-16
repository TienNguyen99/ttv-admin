<?php

namespace App\Http\Controllers;

use App\Models\InternalItemCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class InternalItemCatalogController extends Controller
{
    private const SPREADSHEET_ID = '1nd9sOnKCq-hDf44Uo7_002qT7zoznrx7mcQoRw0oEcs';
    private const SHEET_NAME = 'DANH MỤC';
    private const SHEET_GID = '1429367806';

    public function index()
    {
        return view('client.internal-item-catalog');
    }

    public function data(Request $request)
    {
        $keyword = trim((string) $request->query('keyword', ''));
        $limit = min(max((int) $request->query('limit', 500), 1), 2000);
        $query = InternalItemCatalog::query()->where('is_active', true);

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('item_code', 'like', '%' . $keyword . '%')
                    ->orWhere('item_name', 'like', '%' . $keyword . '%')
                    ->orWhere('unit', 'like', '%' . $keyword . '%')
                    ->orWhere('shelf_code', 'like', '%' . $keyword . '%');
            });
        }

        $summaryQuery = clone $query;
        $rows = $query->orderBy('item_code')->limit($limit)->get();

        return response()->json([
            'data' => $rows,
            'summary' => [
                'item_count' => (clone $summaryQuery)->count(),
                'shelf_count' => (clone $summaryQuery)->where('shelf_code', '<>', '')->distinct()->count('shelf_code'),
                'with_unit_count' => (clone $summaryQuery)->where('unit', '<>', '')->count(),
                'last_synced_at' => InternalItemCatalog::query()->max('updated_at'),
            ],
            'source' => [
                'spreadsheet_id' => self::SPREADSHEET_ID,
                'sheet' => self::SHEET_NAME,
                'mode' => 'read_only',
            ],
        ]);
    }

    public function suggestions(Request $request)
    {
        $keyword = trim((string) $request->query('keyword', ''));
        $limit = min(max((int) $request->query('limit', 30), 1), 100);
        $normalizedKeyword = $this->normalizeSearchText($keyword);
        $tokens = collect(explode(' ', $normalizedKeyword))->filter()->values();

        return response()->json([
            'data' => InternalItemCatalog::query()
                ->where('is_active', true)
                ->orderByRaw("CASE WHEN item_code IS NULL OR item_code = '' THEN 1 ELSE 0 END")
                ->orderBy('item_code')
                ->orderBy('item_name')
                ->get()
                ->filter(function ($row) use ($tokens) {
                    if ($tokens->isEmpty()) {
                        return true;
                    }

                    $haystack = $this->normalizeSearchText(implode(' ', [
                        $row->item_code,
                        $row->item_name,
                        $row->unit,
                        $row->shelf_code,
                    ]));

                    return $tokens->every(function ($token) use ($haystack) {
                        return strpos($haystack, $token) !== false;
                    });
                })
                ->take($limit)
                ->values()
                ->map(function ($row) {
                    $code = trim((string) $row->item_code);

                    return [
                        'code' => $code,
                        'value' => $code !== '' ? $code : $row->item_name,
                        'has_code' => $code !== '',
                        'name' => $row->item_name,
                        'unit' => $row->unit,
                        'shelf' => $row->shelf_code,
                    ];
                })
                ->unique(function ($row) {
                    return mb_strtoupper(($row['code'] ?: $row['name']) . '|' . $row['unit']);
                })
                ->values(),
            'source' => ['sheet' => self::SHEET_NAME, 'mode' => 'internal_cache'],
        ]);
    }

    public function sync()
    {
        $url = sprintf(
            'https://docs.google.com/spreadsheets/d/%s/export?format=csv&gid=%s&cache_bust=%s',
            self::SPREADSHEET_ID,
            self::SHEET_GID,
            rawurlencode((string) microtime(true))
        );
        $response = Http::timeout(90)->withOptions(['verify' => false])->get($url);

        if (!$response->successful()) {
            return response()->json([
                'message' => 'Không đọc được tab DANH MỤC từ Google Sheet.',
            ], 502);
        }

        $rows = $this->parseCsv($response->body());
        if (count($rows) < 2) {
            return response()->json(['message' => 'Tab DANH MỤC không có dữ liệu hợp lệ.'], 422);
        }

        $headers = array_map([$this, 'normalizeHeader'], array_shift($rows));
        $batch = (string) Str::uuid();
        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::connection('internal')->transaction(function () use (
            $rows,
            $headers,
            $batch,
            &$created,
            &$updated,
            &$skipped
        ) {
            InternalItemCatalog::query()->where('is_active', true)->update(['is_active' => false]);

            foreach ($rows as $index => $values) {
                $row = [];
                foreach ($headers as $column => $header) {
                    $row[$header] = trim((string) ($values[$column] ?? ''));
                }

                $name = $this->pick($row, ['ten hang']);
                if ($name === '') {
                    $skipped++;
                    continue;
                }

                $sourceRow = $index + 2;
                $code = trim($this->pick($row, ['ma hang', 'mahang']));
                $existing = InternalItemCatalog::query()->where('source_row', $sourceRow)->exists();

                InternalItemCatalog::query()->updateOrCreate(
                    ['source_row' => $sourceRow],
                    [
                        'item_code' => $code !== '' ? $code : null,
                        'item_name' => $name,
                        'unit' => $this->pick($row, ['dvt']),
                        'shelf_code' => $this->pick($row, ['ke']),
                        'opening_quantity' => $this->number($this->pick($row, ['ton dau'])),
                        'image_url' => $this->pick($row, ['anh']),
                        'raw_data' => $row,
                        'sync_batch' => $batch,
                        'is_active' => true,
                    ]
                );

                $existing ? $updated++ : $created++;
            }
        });

        return response()->json([
            'message' => 'Đã đồng bộ DANH MỤC vào database nội bộ.',
            'data' => [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'active' => InternalItemCatalog::query()->where('is_active', true)->count(),
                'sheet' => self::SHEET_NAME,
            ],
        ]);
    }

    private function parseCsv(string $contents): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream)) !== false) {
            $rows[] = $row;
        }
        fclose($stream);
        return $rows;
    }

    private function normalizeHeader($value): string
    {
        $value = preg_replace('/[^a-z0-9]+/', ' ', Str::ascii(mb_strtolower(trim((string) $value))));
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    private function normalizeSearchText($value): string
    {
        $value = Str::ascii(mb_strtolower(trim((string) $value)));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    private function pick(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            $key = $this->normalizeHeader($key);
            if (array_key_exists($key, $row)) {
                return trim((string) $row[$key]);
            }
        }
        return '';
    }

    private function number($value): float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }
        $value = str_replace(['.', ' '], '', $value);
        $value = str_replace(',', '.', $value);
        return is_numeric($value) ? (float) $value : 0;
    }
}
