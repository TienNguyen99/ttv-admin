<?php

namespace App\Http\Controllers;

use App\Models\InternalItemCatalog;
use App\Models\InternalMaterialIssue;
use App\Models\InternalXntRow;
use App\Services\InternalUnitConverter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class InternalXntController extends Controller
{
    private const SPREADSHEET_ID = '1nd9sOnKCq-hDf44Uo7_002qT7zoznrx7mcQoRw0oEcs';
    private const SHEET_NAME = 'XNT';

    private $catalogRows = null;
    private array $catalogByCode = [];
    private array $catalogByName = [];
    private array $catalogNameRows = [];
    private array $stockCache = [];

    public function index()
    {
        return view('client.internal-xnt-issue');
    }

    public function data(Request $request)
    {
        $keyword = trim((string) $request->query('keyword', ''));
        $productionOrder = trim((string) $request->query('production_order', ''));
        $limit = min(max((int) $request->query('limit', 300), 1), 1000);

        $query = InternalXntRow::query()->with('issue:id,issue_code')->where('is_active', true);

        if ($productionOrder !== '') {
            $query->where('production_order', 'like', '%' . $productionOrder . '%');
        }

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('voucher_code', 'like', '%' . $keyword . '%')
                    ->orWhere('item_code', 'like', '%' . $keyword . '%')
                    ->orWhere('item_name', 'like', '%' . $keyword . '%')
                    ->orWhere('receiver_name', 'like', '%' . $keyword . '%')
                    ->orWhere('production_order', 'like', '%' . $keyword . '%');
            });
        }

        $rows = $query->orderByDesc('issue_date')
            ->orderByDesc('source_row')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => $this->enrichRow($row));

        return response()->json([
            'data' => $rows,
            'summary' => [
                'line_count' => $rows->count(),
                'matched_count' => $rows->where('is_matched', true)->count(),
                'missing_count' => $rows->where('is_matched', false)->count(),
                'total_quantity' => (float) $rows->sum('quantity'),
                'last_synced_at' => InternalXntRow::query()->max('updated_at'),
            ],
        ]);
    }

    public function sync()
    {
        $url = sprintf(
            'https://docs.google.com/spreadsheets/d/%s/gviz/tq?tqx=out:csv&sheet=%s',
            self::SPREADSHEET_ID,
            rawurlencode(self::SHEET_NAME)
        );

        $response = Http::timeout(90)->withOptions(['verify' => false])->get($url);
        if (!$response->successful()) {
            return response()->json(['message' => 'KhÃ´ng Ä‘á»c Ä‘Æ°á»£c tab XNT tá»« Google Sheet.'], 502);
        }

        $rows = $this->parseCsv($response->body());
        if (count($rows) < 2) {
            return response()->json(['message' => 'Tab XNT khÃ´ng cÃ³ dá»¯ liá»‡u há»£p lá»‡.'], 422);
        }

        $headers = array_map([$this, 'normalizeHeader'], array_shift($rows));
        $batch = (string) Str::uuid();
        $activeKeys = [];
        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::connection('internal')->transaction(function () use ($rows, $headers, $batch, &$activeKeys, &$created, &$updated, &$skipped) {
            foreach ($rows as $index => $values) {
                $row = [];
                foreach ($headers as $column => $header) {
                    if ($header !== '') {
                        $row[$header] = trim((string) ($values[$column] ?? ''));
                    }
                }

                $voucher = $this->pick($row, ['so phieu', 'sá»‘ phiáº¿u']);
                $itemName = $this->pick($row, ['ten hang', 'tÃªn hÃ ng']);
                $quantity = $this->number($this->pick($row, ['so luong', 'sá»‘ lÆ°á»£ng']));
                if ($voucher === '' && $itemName === '' && $quantity <= 0) {
                    $skipped++;
                    continue;
                }

                $sourceRow = $index + 2;
                $rowKey = sha1(implode('|', ['XNT', $voucher, $sourceRow]));
                $activeKeys[] = $rowKey;

                $existing = InternalXntRow::query()->where('row_key', $rowKey)->exists();
                InternalXntRow::query()->updateOrCreate(
                    ['row_key' => $rowKey],
                    [
                        'source_row' => $sourceRow,
                        'voucher_code' => $voucher,
                        'issue_date' => $this->dateValue($this->pick($row, ['ngay xuat', 'ngÃ y xuáº¥t'])),
                        'item_code' => $this->pick($row, ['ma hang', 'mÃ£ hÃ ng']),
                        'item_name' => $itemName,
                        'quantity' => $quantity,
                        'unit' => $this->pick($row, ['dvt', 'Ä‘vt']),
                        'receiver_name' => $this->pick($row, ['nguoi nhan', 'ngÆ°á»i nháº­n']),
                        'production_order' => $this->pick($row, ['lenh sx', 'lá»‡nh sx']),
                        'raw_data' => $row,
                        'sync_batch' => $batch,
                        'is_active' => true,
                    ]
                );

                $existing ? $updated++ : $created++;
            }

            $archive = InternalXntRow::query()->where('is_active', true);
            if ($activeKeys) {
                $archive->whereNotIn('row_key', array_unique($activeKeys));
            }
            $archive->update(['is_active' => false]);
        });

        $issueSync = $this->syncIssuesFromRows();

        return response()->json([
            'message' => 'ÄÃ£ Ä‘á»“ng bá»™ XNT.',
            'data' => [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'active' => InternalXntRow::query()->where('is_active', true)->count(),
                'issue_created' => $issueSync['created'],
                'issue_linked' => $issueSync['linked'],
                'issue_skipped' => $issueSync['skipped'],
                'issue_errors' => $issueSync['errors'],
            ],
        ]);
    }

    public function createIssue(Request $request)
    {
        $data = $request->validate([
            'row_ids' => 'required|array|min:1',
            'row_ids.*' => 'integer',
            'issue_date' => 'nullable|date',
            'receiver_name' => 'nullable|string|max:150',
            'department' => 'nullable|string|max:150',
            'note' => 'nullable|string|max:1000',
        ]);

        $rows = InternalXntRow::query()
            ->with('issue:id,issue_code')
            ->where('is_active', true)
            ->whereIn('id', $data['row_ids'])
            ->orderBy('issue_date')
            ->orderBy('source_row')
            ->get()
            ->map(fn ($row) => $this->enrichRow($row));

        if ($rows->isEmpty()) {
            return response()->json(['message' => 'KhÃ´ng cÃ³ dÃ²ng XNT há»£p lá»‡ Ä‘á»ƒ xuáº¥t.'], 422);
        }

        $alreadyIssued = $rows->whereNotNull('issue_id')->values();
        if ($alreadyIssued->isNotEmpty()) {
            return response()->json([
                'message' => 'CÃ³ dÃ²ng XNT Ä‘Ã£ cÃ³ phiáº¿u xuáº¥t chá»‰. Bá» cÃ¡c dÃ²ng Ä‘Ã£ xuáº¥t Ä‘á»ƒ trÃ¡nh trá»« tá»“n trÃ¹ng.',
                'issued' => $alreadyIssued->map(fn ($row) => [
                    'id' => $row['id'],
                    'source_row' => $row['source_row'],
                    'issue_code' => $row['issue_code'],
                ]),
            ], 422);
        }

        $missingOrder = $rows->filter(fn ($row) => trim((string) $row['production_order']) === '')->values();
        if ($missingOrder->isNotEmpty()) {
            return response()->json([
                'message' => 'Chá»‰ táº¡o phiáº¿u xuáº¥t chá»‰ cho cÃ¡c dÃ²ng XNT cÃ³ Lá»‡nh SX.',
                'missing_order' => $missingOrder->map(fn ($row) => [
                    'id' => $row['id'],
                    'source_row' => $row['source_row'],
                    'item_name' => $row['item_name'],
                ]),
            ], 422);
        }

        $missing = $rows->where('is_matched', false)->values();
        if ($missing->isNotEmpty()) {
            return response()->json([
                'message' => 'CÃ³ dÃ²ng XNT chÆ°a khá»›p danh má»¥c ná»™i bá»™. Bá»• sung mÃ£/tÃªn trong DANH Má»¤C trÆ°á»›c khi xuáº¥t.',
                'missing' => $missing->map(fn ($row) => [
                    'id' => $row['id'],
                    'item_name' => $row['item_name'],
                    'item_code' => $row['item_code'],
                    'source_row' => $row['source_row'],
                ]),
            ], 422);
        }

        $issueDate = $data['issue_date'] ?? ($rows->pluck('issue_date')->filter()->first() ?: now()->format('Y-m-d'));
        $productionOrders = $rows->pluck('production_order')
            ->map(fn ($value) => $this->compactText($value))
            ->filter()
            ->unique()
            ->values();
        $receiverName = trim((string) ($data['receiver_name'] ?? ''))
            ?: $rows->pluck('receiver_name')->filter()->unique()->implode(', ');

        $payload = [
            'issue_type' => 'material',
            'issue_date' => $issueDate,
            'warehouse_code' => '',
            'receiver_name' => mb_substr($receiverName, 0, 150),
            'department' => trim((string) ($data['department'] ?? '')) ?: 'Sáº£n xuáº¥t',
            'production_order' => mb_substr($productionOrders->implode(', '), 0, 100),
            'purpose' => 'Xuáº¥t chá»‰ cho lá»‡nh SX',
            'note' => trim((string) ($data['note'] ?? '')) ?: 'Táº¡o tá»« Google Sheet XNT.',
            'lines' => $rows->map(function ($row) {
                return [
                    'production_order' => mb_substr($this->compactText($row['production_order']), 0, 100),
                    'purchase_order' => $row['voucher_code'],
                    'customer' => '',
                    'ma_hh' => $row['catalog_code'],
                    'ten_hh' => $row['catalog_name'] ?: $row['item_name'],
                    'dvt' => $row['unit'],
                    'quantity' => $row['quantity'],
                    'location_code' => $row['suggested_location'],
                    'internal_item_code' => $row['catalog_code'],
                    'size' => $row['catalog_size'],
                    'color' => $row['catalog_color'],
                    'side' => $row['catalog_side'],
                    'note' => mb_substr('XNT ' . $row['voucher_code'] . ' - dÃ²ng sheet ' . $row['source_row'], 0, 500),
                ];
            })->values()->all(),
        ];

        $issueRequest = Request::create('/api/xuat-vat-tu-noi-bo', 'POST', $payload);
        $issueRequest->headers->set('Accept', 'application/json');
        $issueRequest->headers->set('X-CSRF-TOKEN', (string) $request->header('X-CSRF-TOKEN', ''));
        $issueRequest->setUserResolver($request->getUserResolver());
        $issueRequest->setRouteResolver($request->getRouteResolver());

        $response = app(InternalMaterialIssueController::class)->store($issueRequest);

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $result = $response->getData(true);
            $issueId = data_get($result, 'data.id');
            if ($issueId) {
                InternalXntRow::query()
                    ->whereIn('id', $rows->pluck('id')->all())
                    ->update([
                        'issue_id' => $issueId,
                        'issued_at' => now(),
                    ]);
            }
        }

        return $response;
    }

    private function syncIssuesFromRows(): array
    {
        $result = [
            'created' => 0,
            'linked' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $groups = InternalXntRow::query()
            ->where('is_active', true)
            ->whereNull('issue_id')
            ->whereNotNull('voucher_code')
            ->where('voucher_code', '<>', '')
            ->whereNotNull('production_order')
            ->where('production_order', '<>', '')
            ->orderBy('voucher_code')
            ->orderBy('source_row')
            ->get()
            ->groupBy(fn ($row) => trim((string) $row->voucher_code));

        foreach ($groups as $voucherCode => $sourceRows) {
            $issueCode = mb_substr(trim((string) $voucherCode), 0, 50);
            if ($issueCode === '') {
                $result['skipped']++;
                continue;
            }

            $existingIssue = InternalMaterialIssue::query()
                ->where('issue_code', $issueCode)
                ->first();

            if ($existingIssue) {
                InternalXntRow::query()
                    ->whereIn('id', $sourceRows->pluck('id')->all())
                    ->update([
                        'issue_id' => $existingIssue->id,
                        'issued_at' => $existingIssue->created_at ?: now(),
                    ]);
                $result['linked']++;
                continue;
            }

            $rows = $sourceRows->map(fn ($row) => $this->enrichRow($row));
            $missing = $rows->where('is_matched', false)->values();
            if ($missing->isNotEmpty()) {
                $result['skipped']++;
                $result['errors'][] = [
                    'voucher_code' => $issueCode,
                    'reason' => 'Chua khop danh muc noi bo',
                    'rows' => $missing->pluck('source_row')->values()->all(),
                ];
                continue;
            }

            $payload = $this->issuePayloadFromRows($rows, $issueCode, 'Dong bo tu Google Sheet XNT.');
            $issueRequest = Request::create('/api/xuat-vat-tu-noi-bo', 'POST', $payload);
            $issueRequest->headers->set('Accept', 'application/json');

            $response = app(InternalMaterialIssueController::class)->store($issueRequest);
            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                $body = $response->getData(true);
                $result['skipped']++;
                $result['errors'][] = [
                    'voucher_code' => $issueCode,
                    'reason' => $body['message'] ?? 'Khong tao duoc phieu xuat',
                    'rows' => $rows->pluck('source_row')->values()->all(),
                ];
                continue;
            }

            $body = $response->getData(true);
            $issueId = data_get($body, 'data.id');
            $issue = $issueId ? InternalMaterialIssue::query()->find($issueId) : null;
            if (!$issue) {
                $result['skipped']++;
                continue;
            }

            $conflict = InternalMaterialIssue::query()
                ->where('issue_code', $issueCode)
                ->where('id', '<>', $issue->id)
                ->exists();
            if (!$conflict) {
                $issue->issue_code = $issueCode;
                $issue->save();
            }

            InternalXntRow::query()
                ->whereIn('id', $sourceRows->pluck('id')->all())
                ->update([
                    'issue_id' => $issue->id,
                    'issued_at' => now(),
                ]);

            $result['created']++;
        }

        return $result;
    }

    private function issuePayloadFromRows($rows, string $issueCode, string $note): array
    {
        $issueDate = $rows->pluck('issue_date')->filter()->first() ?: now()->format('Y-m-d');
        $productionOrders = $rows->pluck('production_order')
            ->map(fn ($value) => $this->compactText($value))
            ->filter()
            ->unique()
            ->values();
        $receiverName = $rows->pluck('receiver_name')->filter()->unique()->implode(', ');

        return [
            'issue_type' => 'material',
            'issue_date' => $issueDate,
            'warehouse_code' => '',
            'receiver_name' => mb_substr($receiverName, 0, 150),
            'department' => 'San xuat',
            'production_order' => mb_substr($productionOrders->implode(', '), 0, 100),
            'purpose' => 'Xuat chi cho lenh SX',
            'note' => $note . ' So phieu XNT: ' . $issueCode,
            'lines' => $rows->map(function ($row) {
                return [
                    'production_order' => mb_substr($this->compactText($row['production_order']), 0, 100),
                    'purchase_order' => $row['voucher_code'],
                    'customer' => '',
                    'ma_hh' => $row['catalog_code'],
                    'ten_hh' => $row['catalog_name'] ?: $row['item_name'],
                    'dvt' => $row['unit'],
                    'quantity' => $row['quantity'],
                    'location_code' => $row['suggested_location'],
                    'internal_item_code' => $row['catalog_code'],
                    'size' => $row['catalog_size'],
                    'color' => $row['catalog_color'],
                    'side' => $row['catalog_side'],
                    'note' => mb_substr('XNT ' . $row['voucher_code'] . ' - dong sheet ' . $row['source_row'], 0, 500),
                ];
            })->values()->all(),
        ];
    }

    private function enrichRow(InternalXntRow $row): array
    {
        $catalog = $this->matchCatalog($row);
        $stock = $catalog
            ? $this->stockFor($catalog->item_code, $catalog->size, $catalog->color, $catalog->side)
            : collect();
        $unit = trim((string) ($row->unit ?: ($catalog->unit ?? '')));
        $base = app(InternalUnitConverter::class)->toBase(
            $catalog->item_code ?? '',
            (float) $row->quantity,
            $unit,
            $catalog->unit ?? $unit
        );

        return [
            'id' => $row->id,
            'source_row' => $row->source_row,
            'voucher_code' => $row->voucher_code,
            'issue_date' => optional($row->issue_date)->format('Y-m-d'),
            'item_code' => $row->item_code,
            'item_name' => $row->item_name,
            'quantity' => (float) $row->quantity,
            'unit' => $unit,
            'receiver_name' => $row->receiver_name,
            'production_order' => $row->production_order,
            'issue_id' => $row->issue_id,
            'issued_at' => optional($row->issued_at)->format('Y-m-d H:i:s'),
            'issue_code' => optional($row->issue)->issue_code,
            'needs_issue' => trim((string) $row->production_order) !== '' && !$row->issue_id,
            'is_matched' => (bool) $catalog,
            'catalog_code' => $catalog->item_code ?? '',
            'catalog_name' => $catalog->item_name ?? '',
            'catalog_unit' => $catalog->unit ?? '',
            'catalog_size' => $catalog->size ?? '',
            'catalog_color' => $catalog->color ?? '',
            'catalog_side' => $catalog->side ?? '',
            'catalog_shelf' => $catalog->shelf_code ?? '',
            'base_quantity' => (float) $base['quantity'],
            'base_dvt' => $base['unit'],
            'unit_factor' => (float) $base['factor'],
            'converted' => (bool) $base['converted'],
            'available_quantity' => (float) $stock->sum('quantity'),
            'suggested_location' => $stock->sortByDesc('quantity')->first()->location_code ?? ($catalog->shelf_code ?? ''),
            'locations' => $stock->map(fn ($item) => [
                'location_code' => $item->location_code,
                'quantity' => (float) $item->quantity,
            ])->values(),
        ];
    }

    private function matchCatalog(InternalXntRow $row): ?InternalItemCatalog
    {
        $code = mb_strtoupper(trim((string) $row->item_code));
        $this->catalogRows();
        if ($code !== '') {
            $exact = $this->catalogByCode[$code] ?? null;
            if ($exact) {
                return $exact;
            }
        }

        $needle = $this->normalizeItemName((string) $row->item_name);
        if ($needle === '') {
            return null;
        }

        if (isset($this->catalogByName[$needle])) {
            return $this->catalogByName[$needle];
        }

        foreach ($this->catalogNameRows as $entry) {
            $name = $entry['name'];
            if ($name !== '' && (Str::contains($needle, $name) || Str::contains($name, $needle))) {
                return $entry['row'];
            }
        }

        return null;
    }

    private function catalogRows()
    {
        if ($this->catalogRows === null) {
            $this->catalogRows = InternalItemCatalog::query()
                ->where('is_active', true)
                ->whereNotNull('item_code')
                ->where('item_code', '<>', '')
                ->whereNotNull('item_name')
                ->orderBy('id')
                ->get();

            foreach ($this->catalogRows as $row) {
                $code = mb_strtoupper(trim((string) $row->item_code));
                if ($code !== '' && !isset($this->catalogByCode[$code])) {
                    $this->catalogByCode[$code] = $row;
                }

                $name = $this->normalizeItemName((string) $row->item_name);
                if ($name !== '') {
                    $this->catalogNameRows[] = ['name' => $name, 'row' => $row];
                    if (!isset($this->catalogByName[$name])) {
                        $this->catalogByName[$name] = $row;
                    }
                }
            }
        }

        return $this->catalogRows;
    }

    private function stockFor($code, $size = '', $color = '', $side = '')
    {
        $code = trim((string) $code);
        if ($code === '') {
            return collect();
        }

        $cacheKey = mb_strtoupper(implode('|', [
            trim((string) $code),
            trim((string) $size),
            trim((string) $color),
            trim((string) $side),
        ]));
        if (array_key_exists($cacheKey, $this->stockCache)) {
            return $this->stockCache[$cacheKey];
        }

        $query = DB::connection('internal')->table('inventory_packages as p')
            ->leftJoin('warehouse_locations as l', 'l.id', '=', 'p.warehouse_location_id')
            ->where('p.quantity', '>', 0)
            ->whereRaw("UPPER(TRIM(COALESCE(p.internal_item_code, ''))) = ?", [mb_strtoupper($code)])
            ->select(DB::raw("COALESCE(l.location_code, p.ma_ko, 'CHUA-XEP') as location_code"), DB::raw('SUM(p.quantity) as quantity'))
            ->groupBy(DB::raw("COALESCE(l.location_code, p.ma_ko, 'CHUA-XEP')"));

        foreach (['size' => $size, 'color' => $color, 'side' => $side] as $field => $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $query->whereRaw("UPPER(TRIM(COALESCE(p.$field, ''))) = ?", [mb_strtoupper($value)]);
            }
        }

        return $this->stockCache[$cacheKey] = $query->get();
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

    private function normalizeItemName(string $value): string
    {
        $value = preg_replace('/^\s*[0-9]+[\.\)]\s*/', '', $value);
        $value = Str::ascii(mb_strtolower($value));
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
            return 0.0;
        }
        $value = str_replace(['.', ' '], '', $value);
        $value = str_replace(',', '.', $value);
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function compactText($value): string
    {
        return trim(preg_replace('/\s+/', ' ', str_replace(["\r", "\n"], ' ', (string) $value)));
    }

    private function dateValue($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'm/d/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->format('Y-m-d');
            } catch (\Throwable $e) {
            }
        }
        return null;
    }
}

