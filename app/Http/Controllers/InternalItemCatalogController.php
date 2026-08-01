<?php

namespace App\Http\Controllers;

use App\Models\InternalItemCatalog;
use App\Models\InternalProductionOrder;
use App\Models\WarehouseLocation;
use App\Services\GoogleSheetCatalogWriter;
use App\Services\InternalItemGroupResolver;
use App\Services\PantoneColorMatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

    public function bulkShelfIntake(
        Request $request,
        GoogleSheetCatalogWriter $writer
    ) {
        $data = $request->validate([
            'apply' => 'nullable|boolean',
            'receipt_date' => 'required|date',
            'item_group' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:500',
            'lines' => 'required|array|min:1|max:500',
            'lines.*.item_code' => 'required|string|max:100',
            'lines.*.item_name' => 'required|string|max:500',
            'lines.*.quantity' => 'required|numeric|min:0.001|max:999999999999999',
            'lines.*.unit' => 'required|string|max:50',
            'lines.*.shelf_code' => 'required|string|max:100',
            'lines.*.size' => 'nullable|string|max:100',
            'lines.*.color' => 'nullable|string|max:100',
        ]);

        $group = trim((string) ($data['item_group'] ?? '')) ?: 'SỢI';
        $normalized = collect($data['lines'])->map(function ($line) {
            return [
                'item_code' => mb_strtoupper(trim((string) $line['item_code'])),
                'item_name' => trim((string) $line['item_name']),
                'quantity' => (float) $line['quantity'],
                'unit' => mb_strtoupper(trim((string) $line['unit'])),
                'shelf_code' => mb_strtoupper(trim((string) $line['shelf_code'])),
                'size' => trim((string) ($line['size'] ?? '')),
                'color' => trim((string) ($line['color'] ?? '')) ?: trim((string) $line['item_name']),
            ];
        });

        $conflicts = [];
        $lines = $normalized
            ->groupBy(fn ($line) => $line['item_code'])
            ->map(function ($codeLines, $code) use (&$conflicts) {
                $signatures = $codeLines->map(fn ($line) => mb_strtoupper(implode('|', [
                    $line['item_name'],
                    $line['unit'],
                    $line['shelf_code'],
                    $line['size'],
                    $line['color'],
                ])))->unique();
                if ($signatures->count() > 1) {
                    $conflicts[] = $code;
                }

                $line = $codeLines->first();
                $line['quantity'] = (float) $codeLines->sum('quantity');
                $line['merged_rows'] = $codeLines->count();
                return $line;
            })
            ->values();

        if ($conflicts) {
            return response()->json([
                'message' => 'Có mã bị lặp nhưng khác tên, đơn vị, màu hoặc kệ.',
                'errors' => ['lines' => array_map(fn ($code) => "Kiểm tra lại mã {$code}.", $conflicts)],
            ], 422);
        }

        $codes = $lines->pluck('item_code')->all();
        $catalogGroups = InternalItemCatalog::query()
            ->whereIn(DB::raw('UPPER(TRIM(item_code))'), $codes)
            ->orderByDesc('is_active')
            ->orderByDesc('source_row')
            ->get()
            ->groupBy(fn ($row) => mb_strtoupper(trim((string) $row->item_code)));
        $duplicateCatalogCodes = $catalogGroups
            ->filter(fn ($rows) => $rows->where('is_active', true)->count() > 1)
            ->keys()
            ->values();
        if ($duplicateCatalogCodes->isNotEmpty()) {
            return response()->json([
                'message' => 'Danh mục đang có mã trùng. Hãy tách mã trước khi nhập hàng loạt.',
                'errors' => [
                    'lines' => $duplicateCatalogCodes
                        ->map(fn ($code) => "Mã {$code} có nhiều hơn một dòng đang hoạt động.")
                        ->all(),
                ],
            ], 422);
        }
        $existing = $catalogGroups->map(fn ($rows) => $rows->first());

        $preview = $lines->map(function ($line) use ($existing) {
            $catalog = $existing->get($line['item_code']);
            if ($catalog) {
                $line['item_name'] = trim((string) $catalog->item_name) ?: $line['item_name'];
                $line['unit'] = trim((string) $catalog->unit) ?: $line['unit'];
                $line['size'] = trim((string) $catalog->size) ?: $line['size'];
                $line['color'] = trim((string) $catalog->color) ?: $line['color'];
            }
            $line['catalog_id'] = $catalog ? (int) $catalog->id : null;
            $line['source_row'] = $catalog ? (int) $catalog->source_row : null;
            $line['catalog_status'] = !$catalog || (int) $catalog->source_row < 2 ? 'new' : 'existing';
            $line['shelf_changed'] = $catalog
                && mb_strtoupper(trim((string) $catalog->shelf_code)) !== $line['shelf_code'];
            $line['current_shelf'] = $catalog ? trim((string) $catalog->shelf_code) : '';
            return $line;
        })->values();

        $summary = [
            'input_rows' => count($data['lines']),
            'line_count' => $preview->count(),
            'new_count' => $preview->where('catalog_status', 'new')->count(),
            'existing_count' => $preview->where('catalog_status', 'existing')->count(),
            'shelf_update_count' => $preview->where('shelf_changed', true)->count(),
            'shelf_count' => $preview->pluck('shelf_code')->unique()->count(),
            'total_quantity' => (float) $preview->sum('quantity'),
            'units' => $preview->pluck('unit')->filter()->unique()->values()->all(),
            'write_configured' => $writer->isConfigured(),
        ];

        if (!$request->boolean('apply')) {
            return response()->json([
                'data' => $preview,
                'summary' => $summary,
            ]);
        }

        if (!$writer->isConfigured()) {
            return response()->json([
                'message' => 'Chưa cấu hình quyền ghi Google Sheet. Chưa có dữ liệu nào được lưu.',
            ], 503);
        }

        $appendLines = $preview->where('catalog_status', 'new')->values();
        $sheetRowsByCode = $appendLines->isEmpty()
            ? []
            : $writer->findItemRows(
                self::SPREADSHEET_ID,
                self::SHEET_NAME,
                $appendLines->pluck('item_code')->all()
            );
        $duplicateSheetCodes = collect($sheetRowsByCode)
            ->filter(fn ($rows) => count($rows) > 1)
            ->keys()
            ->values();
        if ($duplicateSheetCodes->isNotEmpty()) {
            return response()->json([
                'message' => 'Google Sheet DANH MỤC đang có mã trùng. Chưa append thêm dữ liệu.',
                'errors' => [
                    'lines' => $duplicateSheetCodes
                        ->map(fn ($code) => "Mã {$code} xuất hiện ở nhiều dòng Google Sheet.")
                        ->all(),
                ],
            ], 422);
        }

        $linesToAppend = $appendLines
            ->reject(fn ($line) => !empty($sheetRowsByCode[$line['item_code']] ?? []))
            ->values();
        $appendedRows = [];
        if ($linesToAppend->isNotEmpty()) {
            $appendedRows = $writer->appendRows(
                self::SPREADSHEET_ID,
                self::SHEET_NAME,
                $linesToAppend->map(fn ($line) => [
                    'MÃ HÀNG' => $line['item_code'],
                    'TÊN HÀNG' => $line['item_name'],
                    'DVT' => $line['unit'],
                    'SIZE' => $line['size'],
                    'MÀU' => $line['color'],
                    'KỆ' => $line['shelf_code'],
                    'TỒN ĐẦU' => $line['quantity'],
                    'LOẠI' => $group,
                ])->all()
            );
        }

        $sourceRowsByCode = [];
        foreach ($sheetRowsByCode as $code => $rows) {
            $sourceRowsByCode[$code] = (int) ($rows[0] ?? 0);
        }
        foreach ($linesToAppend as $index => $line) {
            $sourceRowsByCode[$line['item_code']] = (int) ($appendedRows[$index] ?? 0);
        }
        foreach ($preview as $line) {
            if (!isset($sourceRowsByCode[$line['item_code']]) && (int) $line['source_row'] > 0) {
                $sourceRowsByCode[$line['item_code']] = (int) $line['source_row'];
            }
        }

        $openingChanges = $preview
            ->map(function ($line) use ($sourceRowsByCode) {
                return [
                    'source_row' => (int) ($sourceRowsByCode[$line['item_code']] ?? 0),
                    'fields' => [
                        'KỆ' => $line['shelf_code'],
                        'TỒN ĐẦU' => $line['quantity'],
                    ],
                ];
            })
            ->filter(fn ($change) => $change['source_row'] >= 2)
            ->values()
            ->all();
        if ($openingChanges) {
            $writer->writeRowsFields(self::SPREADSHEET_ID, self::SHEET_NAME, $openingChanges);
        }

        $batch = (string) Str::uuid();
        DB::connection('internal')->transaction(function () use (
            $preview,
            $sourceRowsByCode,
            $batch,
            $group
        ) {
            foreach ($preview as $line) {
                $isNewCatalog = !$line['catalog_id'];
                $catalog = $line['catalog_id']
                    ? InternalItemCatalog::query()->find($line['catalog_id'])
                    : null;
                $sourceRow = $sourceRowsByCode[$line['item_code']] ?? (int) ($catalog->source_row ?? 0);
                if (!$catalog && $sourceRow > 0) {
                    $catalog = InternalItemCatalog::query()
                        ->where('source_row', $sourceRow)
                        ->lockForUpdate()
                        ->first();
                }
                $raw = is_array($catalog->raw_data ?? null) ? $catalog->raw_data : [];
                $raw = array_merge($raw, [
                    'ma hang' => $line['item_code'],
                    'ten hang' => $line['item_name'],
                    'dvt' => $line['unit'],
                    'size' => $line['size'],
                    'mau' => $line['color'],
                    'ke' => $line['shelf_code'],
                    'ton dau' => $line['quantity'],
                    'loai' => $raw['loai'] ?? $group,
                ]);

                $attributes = [
                    'source_row' => $sourceRow ?: null,
                    'item_code' => $line['item_code'],
                    'item_name' => $isNewCatalog ? $line['item_name'] : ($catalog->item_name ?? $line['item_name']),
                    'unit' => $isNewCatalog ? $line['unit'] : ($catalog->unit ?? $line['unit']),
                    'size' => $isNewCatalog ? $line['size'] : ($catalog->size ?? $line['size']),
                    'color' => $isNewCatalog ? $line['color'] : ($catalog->color ?? $line['color']),
                    'logo_color' => $isNewCatalog ? '' : ($catalog->logo_color ?? ''),
                    'side' => $isNewCatalog ? '' : ($catalog->side ?? ''),
                    'shelf_code' => $line['shelf_code'],
                    'opening_quantity' => $line['quantity'],
                    'raw_data' => $raw,
                    'source_hash' => hash('sha256', json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                    'sync_batch' => $batch,
                    'is_active' => true,
                ];
                $catalog
                    ? $catalog->update($attributes)
                    : InternalItemCatalog::query()->create($attributes);
            }
        });

        Cache::forget('internal_catalog_customer_map_v1');
        Cache::put(
            'internal_color_mapping_version',
            (string) (((int) Cache::get('internal_color_mapping_version', 1)) + 1)
        );

        return response()->json([
            'message' => 'Đã cập nhật tồn đầu và vị trí vào DANH MỤC.',
            'data' => $preview,
            'catalog_summary' => $summary,
        ], 201);
    }

    public function data(Request $request)
    {
        $keyword = trim((string) $request->query('keyword', ''));
        $isPaged = $request->has('page') || $request->has('per_page');
        $page = max((int) $request->query('page', 1), 1);
        $perPage = min(max((int) $request->query('per_page', 100), 25), 300);
        $limit = min(max((int) $request->query('limit', 500), 1), 2000);
        $customer = trim((string) $request->query('customer', ''));
        $group = trim((string) $request->query('group', ''));
        $tokens = $this->searchTokens($keyword);
        $customerMap = $this->catalogCustomerMap();

        $allRows = InternalItemCatalog::query()
            ->where('is_active', true)
            ->select([
                'id',
                'source_row',
                'item_code',
                'item_name',
                'unit',
                'size',
                'color',
                'logo_color',
                'side',
                'shelf_code',
                'opening_quantity',
                'image_url',
                'image_public_id',
                'image_source',
                'image_uploaded_at',
                'raw_data',
                'updated_at',
            ])
            ->orderBy('item_code')
            ->get();

        $allRows->each(function ($row) use ($customerMap) {
            $codeKey = mb_strtoupper(trim((string) $row->item_code));
            $raw = is_array($row->raw_data) ? $row->raw_data : [];
            $row->setAttribute('source_type', trim((string) ($raw['loai'] ?? '')));
            $row->setAttribute('item_group', $this->catalogItemGroup($row));
            $row->setAttribute('customers', $customerMap[$codeKey] ?? []);
        });

        $facets = [
            'customers' => $allRows->pluck('customers')->flatten()->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values(),
            'groups' => $allRows->pluck('item_group')->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values(),
        ];

        $filteredRows = $allRows
            ->filter(fn ($row) => $this->matchesTokens($this->catalogSearchText($row), $tokens))
            ->filter(fn ($row) => $customer === '' || collect($row->customers)->contains(fn ($value) => mb_strtolower($value) === mb_strtolower($customer)))
            ->filter(fn ($row) => $group === '' || mb_strtolower((string) $row->item_group) === mb_strtolower($group))
            ->values();

        $rows = $isPaged
            ? $filteredRows->slice(($page - 1) * $perPage, $perPage)->values()
            : $filteredRows->take($limit)->values();
        $matcher = app(PantoneColorMatcher::class);

        return response()->json([
            'data' => $rows->map(function ($row) use ($matcher) {
                $match = $matcher->matchCatalog($row);
                $data = $row->toArray();
                unset($data['raw_data']);
                $data['pantone_code'] = $match['pantone'];
                $data['pantone_hex'] = $match['hex'];
                $data['color_name'] = $match['name'] ?? '';
                $data['pantone_source'] = $match['source'];
                return $data;
            }),
            'summary' => [
                'item_count' => $filteredRows->count(),
                'shelf_count' => $filteredRows->pluck('shelf_code')->filter()->unique()->count(),
                'with_unit_count' => $filteredRows->filter(fn ($row) => trim((string) $row->unit) !== '')->count(),
                'last_synced_at' => InternalItemCatalog::query()->max('updated_at'),
            ],
            'pagination' => [
                'page' => $isPaged ? $page : 1,
                'per_page' => $isPaged ? $perPage : $limit,
                'total' => $filteredRows->count(),
                'total_pages' => $isPaged ? (int) ceil($filteredRows->count() / $perPage) : 1,
                'has_more' => $isPaged ? ($page * $perPage < $filteredRows->count()) : ($rows->count() < $filteredRows->count()),
            ],
            'facets' => $facets,
            'source' => [
                'spreadsheet_id' => self::SPREADSHEET_ID,
                'sheet' => self::SHEET_NAME,
                'mode' => 'read_write_confirmed',
            ],
        ]);
    }

    public function invalidDocumentCodes(Request $request)
    {
        $keyword = trim((string) $request->query('keyword', ''));
        $type = trim((string) $request->query('type', 'all'));
        $isPaged = $request->has('page') || $request->has('per_page');
        $page = max((int) $request->query('page', 1), 1);
        $perPage = min(max((int) $request->query('per_page', 100), 25), 300);
        $limit = min(max((int) $request->query('limit', 500), 1), 2000);
        $tokens = $this->searchTokens($keyword);

        $receiptRows = collect();
        if (in_array($type, ['all', 'receipt'], true)) {
            $receiptRows = DB::connection('internal')
                ->table('internal_material_receipt_lines as l')
                ->join('internal_material_receipts as r', 'r.id', '=', 'l.receipt_id')
                ->leftJoin('internal_item_catalogs as c', function ($join) {
                    $join->whereRaw("UPPER(TRIM(COALESCE(c.item_code, ''))) = UPPER(TRIM(COALESCE(l.internal_item_code, '')))")
                        ->where('c.is_active', true);
                })
                ->whereRaw("TRIM(COALESCE(l.internal_item_code, '')) <> ''")
                ->whereNull('c.id')
                ->select([
                    DB::raw("'receipt' as document_type"),
                    'r.id as document_id',
                    'l.id as line_id',
                    'r.receipt_code as document_code',
                    'r.receipt_date as document_date',
                    'l.internal_item_code',
                    'l.ma_hh',
                    'l.ten_hh',
                    'l.size',
                    'l.color',
                    'l.side',
                    'l.quantity',
                    'l.location_code',
                    'l.note',
                ])
                ->get();
        }

        $issueRows = collect();
        if (in_array($type, ['all', 'issue'], true)) {
            $issueRows = DB::connection('internal')
                ->table('internal_material_issue_lines as l')
                ->join('internal_material_issues as i', 'i.id', '=', 'l.issue_id')
                ->leftJoin('internal_item_catalogs as c', function ($join) {
                    $join->whereRaw("UPPER(TRIM(COALESCE(c.item_code, ''))) = UPPER(TRIM(COALESCE(l.internal_item_code, '')))")
                        ->where('c.is_active', true);
                })
                ->whereRaw("TRIM(COALESCE(l.internal_item_code, '')) <> ''")
                ->whereNull('c.id')
                ->select([
                    DB::raw("'issue' as document_type"),
                    'i.id as document_id',
                    'l.id as line_id',
                    'i.issue_code as document_code',
                    'i.issue_date as document_date',
                    'l.internal_item_code',
                    'l.ma_hh',
                    'l.ten_hh',
                    'l.size',
                    'l.color',
                    'l.side',
                    'l.quantity',
                    'l.location_code',
                    'l.note',
                ])
                ->get();
        }

        $allRows = $receiptRows
            ->concat($issueRows)
            ->filter(fn ($row) => $this->matchesTokens($this->documentLineSearchText($row), $tokens))
            ->sortByDesc('document_date')
            ->values();

        $summary = [
            'total' => $allRows->count(),
            'receipt_count' => $allRows->where('document_type', 'receipt')->count(),
            'issue_count' => $allRows->where('document_type', 'issue')->count(),
            'unique_code_count' => $allRows->pluck('internal_item_code')->map(fn ($value) => mb_strtoupper(trim((string) $value)))->unique()->count(),
        ];

        $pagedRows = $isPaged
            ? $allRows->slice(($page - 1) * $perPage, $perPage)->values()
            : $allRows->take($limit)->values();

        $rows = $pagedRows
            ->map(function ($row) {
                return [
                    'document_type' => $row->document_type,
                    'document_label' => $row->document_type === 'receipt' ? 'Phiếu nhập' : 'Phiếu xuất',
                    'document_id' => $row->document_id,
                    'line_id' => $row->line_id,
                    'document_code' => $row->document_code,
                    'document_date' => $row->document_date,
                    'internal_item_code' => $row->internal_item_code,
                    'ma_hh' => $row->ma_hh,
                    'ten_hh' => $row->ten_hh,
                    'size' => $row->size,
                    'color' => $row->color,
                    'side' => $row->side,
                    'quantity' => (float) $row->quantity,
                    'location_code' => $row->location_code,
                    'note' => $row->note,
                    'edit_url' => $row->document_type === 'receipt'
                        ? url('/client/kiem-ton-kho?view=receipts&keyword=' . rawurlencode($row->document_code))
                        : url('/client/xuat-vat-tu-noi-bo?keyword=' . rawurlencode($row->document_code)),
                ];
            });

        return response()->json([
            'data' => $rows,
            'summary' => $summary,
            'pagination' => [
                'page' => $isPaged ? $page : 1,
                'per_page' => $isPaged ? $perPage : $limit,
                'total' => $allRows->count(),
                'total_pages' => $isPaged ? (int) ceil($allRows->count() / $perPage) : 1,
                'has_more' => $isPaged ? ($page * $perPage < $allRows->count()) : ($rows->count() < $allRows->count()),
            ],
        ]);
    }

    public function suggestions(Request $request)
    {
        $keyword = trim((string) $request->query('keyword', ''));
        $limit = min(max((int) $request->query('limit', 30), 1), 100);
        $withColor = $request->boolean('with_color', true);
        $normalizedKeyword = $this->normalizeSearchText($keyword);
        $tokens = collect(explode(' ', $normalizedKeyword))->filter()->values();
        $searchColumns = [
            'item_code',
            'item_name',
            'unit',
            'shelf_code',
            'size',
            'color',
            'logo_color',
            'side',
        ];

        $query = InternalItemCatalog::query()
            ->where('is_active', true);

        $tokens->each(function ($token) use ($query, $searchColumns) {
            $like = '%' . addcslashes((string) $token, '\\%_') . '%';
            $query->where(function ($inner) use ($searchColumns, $like) {
                foreach ($searchColumns as $column) {
                    $inner->orWhere($column, 'like', $like);
                }
            });
        });

        $colorVersion = Cache::get('internal_color_mapping_version', '1');
        $cacheKey = 'internal_catalog_suggestions:v6:' . md5($normalizedKeyword . '|' . $limit . '|' . (int) $withColor . '|' . $colorVersion);
        $data = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($query, $keyword, $limit, $withColor) {
            return $query
                ->orderByRaw("CASE WHEN item_code IS NULL OR item_code = '' THEN 1 ELSE 0 END")
                ->orderByRaw("CASE WHEN item_code = ? THEN 0 WHEN item_code LIKE ? THEN 1 ELSE 2 END", [
                    $keyword,
                    $keyword . '%',
                ])
                ->orderBy('item_code')
                ->orderBy('item_name')
                ->limit($limit * 3)
                ->get()
                ->map(function ($row) use ($withColor) {
                    $code = trim((string) $row->item_code);
                    $match = $withColor
                        ? app(PantoneColorMatcher::class)->matchCatalog($row)
                        : ['pantone' => '', 'hex' => '', 'source' => ''];

                    return [
                        'code' => $code,
                        'value' => $code !== '' ? $code : $row->item_name,
                        'has_code' => $code !== '',
                        'name' => $row->item_name,
                        'unit' => $row->unit,
                        'shelf' => $row->shelf_code,
                        'size' => $row->size,
                        'color' => $row->color,
                        'logo_color' => $row->logo_color,
                        'side' => $row->side,
                        'image_url' => $row->image_url,
                        'pantone_code' => $match['pantone'],
                        'pantone_hex' => $match['hex'],
                        'color_name' => $match['name'] ?? '',
                        'pantone_source' => $match['source'],
                    ];
                })
                ->unique(function ($row) {
                    return mb_strtoupper(implode('|', [
                        $row['code'] ?: $row['name'],
                        $row['unit'],
                        $row['size'],
                        $row['color'],
                        $row['logo_color'],
                        $row['side'],
                    ]));
                })
                ->take($limit)
                ->values();
        });

        return response()->json([
            'data' => $data,
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
        $unchanged = 0;
        $skipped = 0;
        $activeSourceRows = [];

        DB::connection('internal')->transaction(function () use (
            $rows,
            $headers,
            $batch,
            &$created,
            &$updated,
            &$unchanged,
            &$activeSourceRows,
            &$skipped
        ) {
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
                $activeSourceRows[] = $sourceRow;
                $code = trim($this->pick($row, ['ma hang', 'mahang']));
                $existingCatalog = InternalItemCatalog::query()->where('source_row', $sourceRow)->first();
                $existing = (bool) $existingCatalog;
                $sheetImage = $this->pick($row, ['anh']);
                $sourceHash = hash('sha256', json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                if ($existingCatalog && hash_equals((string) ($existingCatalog->source_hash ?? ''), $sourceHash)) {
                    if (!$existingCatalog->is_active) {
                        $existingCatalog->update(['is_active' => true]);
                    }
                    $unchanged++;
                    continue;
                }

                InternalItemCatalog::query()->updateOrCreate(
                    ['source_row' => $sourceRow],
                    [
                        'item_code' => $code !== '' ? $code : null,
                        'item_name' => $name,
                        'unit' => $this->pick($row, ['dvt']),
                        'size' => $this->pick($row, ['size', 'kich co']),
                        'color' => $this->pick($row, ['mau', 'mau vai', 'fabric color', 'color']),
                        'logo_color' => $this->pick($row, ['mau in', 'logo color']),
                        'side' => $this->pick($row, ['mat', 'vi tri', 'side', 'position']),
                        'shelf_code' => $this->pick($row, ['ke']),
                        'opening_quantity' => $this->number($this->pick($row, ['ton dau'])),
                        'image_url' => $sheetImage !== '' ? $sheetImage : ($existingCatalog->image_url ?? null),
                        'raw_data' => $row,
                        'source_hash' => $sourceHash,
                        'sync_batch' => $batch,
                        'is_active' => true,
                    ]
                );

                $existing ? $updated++ : $created++;
            }

            $archiveQuery = InternalItemCatalog::query()->where('is_active', true);
            if ($activeSourceRows) {
                $archiveQuery->whereNotIn('source_row', array_unique($activeSourceRows));
            }
            $archiveQuery->update(['is_active' => false]);
        });
        Cache::forget('internal_catalog_customer_map_v1');

        return response()->json([
            'message' => 'Đã đồng bộ DANH MỤC vào database nội bộ.',
            'data' => [
                'created' => $created,
                'updated' => $updated,
                'unchanged' => $unchanged,
                'skipped' => $skipped,
                'active' => InternalItemCatalog::query()->where('is_active', true)->count(),
                'sheet' => self::SHEET_NAME,
            ],
        ]);
    }

    public function productionOrderVariants(Request $request, GoogleSheetCatalogWriter $writer)
    {
        $data = $request->validate([
            'production_order' => 'nullable|string|max:120|required_without:cross_order_variants',
            'apply' => 'nullable|boolean',
            'sizes' => 'nullable|array|max:100',
            'sizes.*' => 'required|string|max:100',
            'manual_variants' => 'nullable|array|max:100',
            'manual_variants.*.variant_key' => 'required|string|max:220',
            'manual_variants.*.size' => 'nullable|string|max:100',
            'manual_variants.*.color' => 'nullable|string|max:250',
            'manual_variants.*.quantity' => 'nullable|numeric|min:0.001|max:999999999999999',
            'cross_order_variants' => 'nullable|array|max:100',
            'cross_order_variants.*.order_id' => 'nullable|integer',
            'cross_order_variants.*.production_order' => 'required|string|max:120',
            'cross_order_variants.*.variant_key' => 'required|string|max:220',
            'cross_order_variants.*.base_code' => 'required|string|max:200',
            'cross_order_variants.*.size' => 'nullable|string|max:100',
            'cross_order_variants.*.color' => 'nullable|string|max:250',
            'cross_order_variants.*.quantity' => 'nullable|numeric|min:0.001|max:999999999999999',
            'variants' => 'nullable|array|max:100',
            'variants.*.order_id' => 'required|integer',
            'variants.*.variant_key' => 'required|string|max:220',
            'variants.*.new_code' => 'required|string|max:200',
        ]);
        $orderCode = trim((string) ($data['production_order'] ?? ''));
        $crossOrderVariants = collect($data['cross_order_variants'] ?? [])->values();
        $crossOrderIds = $crossOrderVariants->pluck('order_id')->map(fn ($id) => (int) $id)->filter()->unique();
        $crossOrderCodes = $crossOrderVariants->pluck('production_order')->map(fn ($code) => trim((string) $code))->filter()->unique();
        $orders = InternalProductionOrder::query()
            ->where('is_active', true)
            ->when(
                $crossOrderVariants->isNotEmpty(),
                fn ($query) => $query->where(function ($query) use ($crossOrderIds, $crossOrderCodes) {
                    $query->when($crossOrderIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $crossOrderIds))
                        ->when($crossOrderCodes->isNotEmpty(), fn ($query) => $query->orWhereIn('production_order', $crossOrderCodes));
                }),
                fn ($query) => $query->where('production_order', $orderCode)
            )
            ->orderBy('source_row')
            ->get();

        if ($orders->isEmpty()) {
            return response()->json(['message' => 'Không tìm thấy lệnh sản xuất cần tách mã.'], 404);
        }

        $manualSizes = collect($data['sizes'] ?? [])->map(fn ($size) => trim((string) $size))->filter()->unique()->values();
        $manualVariants = collect($data['manual_variants'] ?? [])
            ->map(fn ($variant) => [
                'variant_key' => trim((string) $variant['variant_key']),
                'size' => trim((string) ($variant['size'] ?? '')),
                'color' => trim((string) ($variant['color'] ?? '')),
                'quantity' => (float) ($variant['quantity'] ?? 0),
            ])
            ->filter(fn ($variant) => $variant['variant_key'] !== '' && ($variant['size'] !== '' || $variant['color'] !== ''))
            ->unique('variant_key')
            ->values();
        if ($crossOrderVariants->isNotEmpty()) {
            $ordersById = $orders->keyBy(fn ($order) => (int) $order->id);
            $ordersByCode = $orders->groupBy(fn ($order) => mb_strtoupper(trim((string) $order->production_order)));
            $resolvedCrossVariants = $crossOrderVariants->map(function ($variant) use ($ordersById, $ordersByCode) {
                $order = $ordersById->get((int) ($variant['order_id'] ?? 0));
                if (!$order) {
                    $candidates = $ordersByCode->get(mb_strtoupper(trim((string) $variant['production_order'])), collect());
                    $baseCode = mb_strtoupper(trim((string) $variant['base_code']));
                    $size = mb_strtoupper(trim((string) ($variant['size'] ?? '')));
                    $color = mb_strtoupper(trim((string) ($variant['color'] ?? '')));
                    $order = $candidates->first(function ($candidate) use ($baseCode, $size, $color) {
                        $candidateCode = mb_strtoupper(trim((string) ($candidate->standard_item_code ?: $candidate->item_code)));
                        return ($candidateCode === $baseCode || mb_strtoupper(trim((string) $candidate->item_code)) === $baseCode)
                            && ($size === '' || mb_strtoupper(trim((string) $candidate->size)) === $size)
                            && ($color === '' || mb_strtoupper(trim((string) $candidate->color)) === $color);
                    }) ?: $candidates->first();
                }
                if (!$order) {
                    return null;
                }
                $variant['order_id'] = (int) $order->id;
                return $variant;
            });
            if ($resolvedCrossVariants->contains(null)) {
                return response()->json([
                    'message' => 'Không xác định được một số lệnh sản xuất. Hãy chọn lại lệnh từ danh sách gợi ý.',
                ], 422);
            }
            $crossOrderVariants = $resolvedCrossVariants->values();

            $basePlans = $crossOrderVariants->map(function ($variant) use ($ordersById) {
                $order = $ordersById->get((int) $variant['order_id']);
                $baseCode = trim((string) $variant['base_code']);
                $size = trim((string) ($variant['size'] ?? ''));
                $color = trim((string) ($variant['color'] ?? ''));
                $candidate = $this->catalogCodePart($baseCode);
                $sizePart = $this->catalogCodePart($size);
                if ($sizePart !== '') {
                    $candidate .= '-' . $sizePart;
                }

                return [
                    'order_id' => (int) $order->id,
                    'variant_key' => trim((string) $variant['variant_key']),
                    'production_order' => trim((string) $order->production_order),
                    'base_code' => $baseCode,
                    'candidate' => $candidate,
                    'color_part' => $this->catalogCodePart($color),
                    'item_name' => trim((string) ($order->description ?: $order->specification ?: $baseCode)),
                    'size' => $size,
                    'color' => $color,
                    'quantity' => (float) ($variant['quantity'] ?? 0),
                    'unit' => trim((string) $order->unit) ?: 'Cái',
                    'manual' => false,
                    'requires_split' => true,
                ];
            })->values();
            $candidateCounts = $basePlans->countBy('candidate');
            $plans = $basePlans->map(function ($plan) use ($candidateCounts) {
                $proposed = $plan['candidate'];
                if (($candidateCounts[$proposed] ?? 0) > 1 && $plan['color_part'] !== '') {
                    $proposed .= '-' . $plan['color_part'];
                }
                $plan['proposed_code'] = $proposed;
                unset($plan['candidate'], $plan['color_part']);
                return $plan;
            })->values();
        } else {
            $plans = $this->buildProductionVariantPlans($orders, $manualSizes, $manualVariants);
        }
        $catalogByCode = InternalItemCatalog::query()
            ->where('is_active', true)
            ->whereNotNull('item_code')
            ->whereIn('item_code', $plans->pluck('proposed_code')->all())
            ->get()
            ->keyBy(fn ($row) => mb_strtoupper(trim((string) $row->item_code)));
        $plans = $plans->map(function ($plan) use ($catalogByCode) {
            $catalog = $catalogByCode->get(mb_strtoupper($plan['proposed_code']));
            $plan['exists'] = (bool) $catalog;
            $plan['catalog_id'] = $catalog ? (int) $catalog->id : null;
            return $plan;
        })->values();

        if (!$request->boolean('apply')) {
            return response()->json([
                'data' => $plans,
                'summary' => [
                    'total' => $plans->count(),
                    'existing' => $plans->where('exists', true)->count(),
                    'missing' => $plans->where('exists', false)->count(),
                    'split_required' => $plans->where('requires_split', true)->count(),
                    'write_configured' => $writer->isConfigured(),
                    'source_variant_count' => $orders->count(),
                    'manual_size_count' => $manualSizes->count(),
                    'manual_variant_count' => $manualVariants->count(),
                    'cross_order_variant_count' => $crossOrderVariants->count(),
                    'needs_production_link' => $manualVariants->isNotEmpty()
                        && !$orders->contains(fn ($order) => (bool) $order->is_manual_variant),
                    'requires_size_input' => false,
                ],
            ]);
        }

        $submitted = collect($data['variants'] ?? [])->keyBy(fn ($row) => (string) $row['variant_key']);
        $missingPlans = $plans->where('exists', false)->map(function ($plan) use ($submitted) {
            $input = $submitted->get((string) $plan['variant_key']);
            $plan['new_code'] = mb_strtoupper(trim((string) ($input['new_code'] ?? $plan['proposed_code'])));
            return $plan;
        })->values();
        if ($missingPlans->isNotEmpty() && !$writer->isConfigured()) {
            return response()->json(['message' => 'Chưa cấu hình quyền ghi Google Sheet.'], 503);
        }
        $duplicateCodeConflict = $missingPlans
            ->groupBy(fn ($plan) => mb_strtoupper(trim((string) $plan['new_code'])))
            ->first(function ($group) {
                return $group->unique(fn ($plan) => mb_strtoupper(trim((string) $plan['size']))
                    . '|' . mb_strtoupper(trim((string) $plan['color'])))->count() > 1;
            });
        if ($duplicateCodeConflict) {
            return response()->json(['message' => 'Hai size/màu khác nhau đang dùng chung một mã mới.'], 422);
        }
        $catalogCreationPlans = $missingPlans
            ->unique(fn ($plan) => mb_strtoupper(trim((string) $plan['new_code'])))
            ->values();
        $newCodes = $catalogCreationPlans->pluck('new_code')->filter()->values();
        $conflictingCodes = InternalItemCatalog::query()
            ->whereNotNull('item_code')
            ->whereIn('item_code', $newCodes->all())
            ->pluck('item_code')
            ->filter()
            ->values();
        if ($conflictingCodes->isNotEmpty()) {
            return response()->json([
                'message' => 'Một số mã đã có trong danh mục: ' . $conflictingCodes->implode(', '),
            ], 422);
        }

        $manualQuantityTotal = (float) $manualVariants->sum('quantity');
        $sourceOrder = $orders->first(fn ($order) => !$order->is_manual_variant) ?: $orders->first();
        $manualQuantityMatchesSource = $manualQuantityTotal > 0
            && $sourceOrder
            && abs($manualQuantityTotal - (float) $sourceOrder->order_quantity) <= 0.0001;

        $sheetRows = $catalogCreationPlans->map(fn ($plan) => [
            'MAHANG' => $plan['new_code'],
            'TEN HANG' => $plan['item_name'],
            'DVT' => $plan['unit'],
            'SIZE' => $plan['size'],
            'MAU' => $plan['color'],
            'LOAI' => 'TP',
            'KE' => '',
            'TON DAU' => 0,
        ])->all();
        $sourceRows = $writer->appendRows(self::SPREADSHEET_ID, self::SHEET_NAME, $sheetRows);
        $batch = (string) Str::uuid();
        $createdCatalogs = collect();

        DB::connection('internal')->transaction(function () use ($catalogCreationPlans, $sourceRows, $batch, &$createdCatalogs) {
            foreach ($catalogCreationPlans as $index => $plan) {
                $rawData = [
                    'ma hang' => $plan['new_code'],
                    'ten hang' => $plan['item_name'],
                    'dvt' => $plan['unit'],
                    'size' => $plan['size'],
                    'mau' => $plan['color'],
                    'loai' => 'TP',
                    'ke' => '',
                    'ton dau' => 0,
                ];
                $catalog = InternalItemCatalog::query()->updateOrCreate(
                    ['source_row' => (int) $sourceRows[$index]],
                    [
                        'item_code' => $plan['new_code'],
                        'item_name' => $plan['item_name'],
                        'unit' => $plan['unit'],
                        'size' => $plan['size'],
                        'color' => $plan['color'],
                        'logo_color' => '',
                        'side' => '',
                        'shelf_code' => '',
                        'opening_quantity' => 0,
                        'raw_data' => $rawData,
                        'source_hash' => null,
                        'sync_batch' => $batch,
                        'is_active' => true,
                    ]
                );
                $createdCatalogs->put((int) $plan['order_id'], $catalog);
            }
        });

        $allCatalogsByCode = InternalItemCatalog::query()
            ->where('is_active', true)
            ->whereIn('item_code', $plans->pluck('proposed_code')->merge($newCodes)->all())
            ->get()
            ->keyBy(fn ($row) => mb_strtoupper(trim((string) $row->item_code)));
        $resultPlans = $plans->map(function ($plan) use ($submitted, $allCatalogsByCode) {
            $input = $submitted->get((string) $plan['variant_key']);
            $finalCode = $plan['exists']
                ? $plan['proposed_code']
                : mb_strtoupper(trim((string) ($input['new_code'] ?? $plan['proposed_code'])));
            $catalog = $allCatalogsByCode->get(mb_strtoupper($finalCode));
            if ($catalog && empty($plan['manual'])) {
                InternalProductionOrder::query()->whereKey($plan['order_id'])->update([
                    'standard_catalog_id' => $catalog->id,
                    'standard_item_code' => $catalog->item_code,
                ]);
            }
            $plan['final_code'] = $finalCode;
            $plan['catalog_id'] = $catalog ? (int) $catalog->id : null;
            $plan['exists'] = true;
            return $plan;
        })->values();
        if ($manualQuantityTotal > 0 && $sourceOrder) {
            $variantIds = $this->syncManualProductionVariants($sourceOrder, $resultPlans);
            $resultPlans = $resultPlans->map(function ($plan) use ($variantIds) {
                $plan['production_order_variant_id'] = $variantIds[(string) $plan['variant_key']] ?? null;
                return $plan;
            })->values();
        }
        Cache::forget('internal_catalog_customer_map_v1');

        return response()->json([
            'message' => 'Đã thêm ' . $catalogCreationPlans->count() . ' mã biến thể vào Google Sheet DANH MỤC.',
            'data' => $resultPlans,
            'summary' => [
                'total' => $resultPlans->count(),
                'created' => $catalogCreationPlans->count(),
                'linked' => $resultPlans->whereNotNull('catalog_id')->count(),
                'production_variants' => $resultPlans->whereNotNull('production_order_variant_id')->count(),
                'partial_receipt_split' => $manualQuantityTotal > 0 && !$manualQuantityMatchesSource,
            ],
        ]);
    }

    private function syncManualProductionVariants(InternalProductionOrder $parent, $plans): array
    {
        $variantIds = [];
        DB::connection('internal')->transaction(function () use ($parent, $plans, &$variantIds) {
            $activeKeys = [];
            foreach ($plans as $plan) {
                $variantKey = (string) $plan['variant_key'];
                $rowKey = hash('sha256', 'MANUAL_VARIANT|' . $parent->id . '|' . $variantKey);
                $activeKeys[] = $rowKey;
                $rawData = is_array($parent->raw_data) ? $parent->raw_data : [];
                $rawData['_internal_variant'] = [
                    'parent_id' => (int) $parent->id,
                    'variant_key' => $variantKey,
                    'source_quantity' => (float) $parent->order_quantity,
                ];

                $variant = InternalProductionOrder::query()->updateOrCreate(
                    ['row_key' => $rowKey],
                    [
                        'production_order' => $parent->production_order,
                        'purchase_order' => $parent->purchase_order,
                        'tracking_staff' => $parent->tracking_staff,
                        'customer' => $parent->customer,
                        'item_code' => $parent->item_code,
                        'standard_item_code' => $plan['final_code'],
                        'standard_catalog_id' => $plan['catalog_id'],
                        'variant_parent_id' => $parent->id,
                        'is_variant_parent' => false,
                        'is_manual_variant' => true,
                        'specification' => $parent->specification,
                        'description' => $plan['item_name'] ?: $parent->description,
                        'size' => $plan['size'],
                        'color' => $plan['color'],
                        'unit' => $plan['unit'] ?: $parent->unit,
                        'order_quantity' => (float) ($plan['quantity'] ?? 0),
                        'location' => $parent->location,
                        'received_date' => $parent->received_date,
                        'promised_date' => $parent->promised_date,
                        'customer_requested_date' => $parent->customer_requested_date,
                        'delivery_place' => $parent->delivery_place,
                        'status' => $parent->status,
                        'source_row' => null,
                        'raw_data' => $rawData,
                        'source_hash' => null,
                        'sync_batch' => 'manual-variant',
                        'is_active' => true,
                    ]
                );
                $variantIds[$variantKey] = (int) $variant->id;
            }

            InternalProductionOrder::query()
                ->where('variant_parent_id', $parent->id)
                ->where('is_manual_variant', true)
                ->when($activeKeys, fn ($query) => $query->whereNotIn('row_key', $activeKeys))
                ->update(['is_active' => false]);
            $parent->update([
                'is_variant_parent' => true,
                'is_manual_variant' => false,
                'is_active' => false,
            ]);
        });

        return $variantIds;
    }

    private function buildProductionVariantPlans($orders, $manualSizes = null, $manualVariants = null)
    {
        $manualSizes = collect($manualSizes ?: []);
        $manualVariants = collect($manualVariants ?: []);
        if ($manualVariants->isNotEmpty()) {
            $template = $orders->first();
            $baseCode = trim((string) ($template->item_code ?: $template->standard_item_code));
            $basePlans = $manualVariants->map(function ($variant) use ($template, $baseCode) {
                $size = trim((string) ($variant['size'] ?? ''));
                $color = trim((string) ($variant['color'] ?? ''));
                $candidate = $this->catalogCodePart($baseCode);
                $sizePart = $this->catalogCodePart($size);
                if ($sizePart !== '') {
                    $candidate .= '-' . $sizePart;
                }

                return [
                    'order_id' => (int) $template->id,
                    'variant_key' => trim((string) $variant['variant_key']),
                    'production_order' => trim((string) $template->production_order),
                    'base_code' => $baseCode,
                    'candidate' => $candidate,
                    'color_part' => $this->catalogCodePart($color),
                    'item_name' => trim((string) ($template->description ?: $template->specification ?: $baseCode)),
                    'size' => $size,
                    'color' => $color,
                    'quantity' => (float) ($variant['quantity'] ?? 0),
                    'unit' => trim((string) $template->unit) ?: 'Cái',
                    'manual' => true,
                ];
            });
        } elseif ($manualSizes->isNotEmpty()) {
            $template = $orders->first();
            $baseCode = trim((string) ($template->item_code ?: $template->standard_item_code));
            $basePlans = $manualSizes->map(function ($size) use ($template, $baseCode) {
                return [
                    'order_id' => (int) $template->id,
                    'variant_key' => 'SIZE:' . mb_strtoupper($size),
                    'production_order' => trim((string) $template->production_order),
                    'base_code' => $baseCode,
                    'candidate' => $this->catalogCodePart($baseCode) . '-' . $this->catalogCodePart($size),
                    'color_part' => $this->catalogCodePart($template->color),
                    'item_name' => trim((string) ($template->description ?: $template->specification ?: $baseCode)),
                    'size' => $size,
                    'color' => trim((string) $template->color),
                    'unit' => trim((string) $template->unit) ?: 'Cái',
                    'manual' => true,
                ];
            });
        } else {
            $basePlans = $orders->map(function ($order) {
                $sourceCode = trim((string) ($order->item_code ?: $order->standard_item_code));

                return [
                    'order_id' => (int) $order->id,
                    'variant_key' => 'ORDER:' . (int) $order->id,
                    'production_order' => trim((string) $order->production_order),
                    'base_code' => $sourceCode,
                    'linked_code' => trim((string) $order->standard_item_code),
                    'item_name' => trim((string) ($order->description ?: $order->specification ?: $sourceCode)),
                    'size' => trim((string) $order->size),
                    'color' => trim((string) $order->color),
                    'unit' => trim((string) $order->unit) ?: 'Cái',
                    'manual' => false,
                ];
            });
        }

        $basePlans = $basePlans->unique(fn ($plan) => implode('|', [
            mb_strtoupper($plan['base_code']),
            mb_strtoupper($plan['size']),
            mb_strtoupper($plan['color']),
            mb_strtoupper($plan['item_name']),
        ]))->values();

        if ($manualSizes->isNotEmpty() || $manualVariants->isNotEmpty()) {
            $candidateCounts = $basePlans->countBy('candidate');

            return $basePlans->map(function ($plan) use ($candidateCounts) {
                $proposed = $plan['candidate'];
                if (($candidateCounts[$proposed] ?? 0) > 1 && $plan['color_part'] !== '') {
                    $proposed .= '-' . $plan['color_part'];
                }
                $plan['proposed_code'] = $proposed;
                $plan['requires_split'] = true;
                unset($plan['candidate'], $plan['color_part'], $plan['linked_code']);
                return $plan;
            });
        }

        $variantCounts = $basePlans
            ->groupBy(fn ($plan) => mb_strtoupper(trim((string) $plan['base_code'])))
            ->map(fn ($plans) => $plans->unique(fn ($plan) => implode('|', [
                mb_strtoupper(trim((string) $plan['size'])),
                mb_strtoupper(trim((string) $plan['color'])),
            ]))->count());
        $linkedCodeCounts = $basePlans
            ->filter(fn ($plan) => trim((string) ($plan['linked_code'] ?? '')) !== '')
            ->countBy(fn ($plan) => mb_strtoupper(trim((string) $plan['linked_code'])));

        $plans = $basePlans->map(function ($plan) use ($variantCounts, $linkedCodeCounts) {
            $baseCode = trim((string) $plan['base_code']);
            $baseKey = mb_strtoupper($baseCode);
            $linkedCode = trim((string) ($plan['linked_code'] ?? ''));
            $linkedKey = mb_strtoupper($linkedCode);
            $hasMultipleVariants = ($variantCounts[$baseKey] ?? 0) > 1;
            $hasDedicatedLinkedCode = $linkedCode !== '' && ($linkedCodeCounts[$linkedKey] ?? 0) === 1;
            $requiresSplit = $hasMultipleVariants && !$hasDedicatedLinkedCode;

            if (!$requiresSplit) {
                $plan['candidate'] = $linkedCode !== '' ? $linkedCode : $baseCode;
                $plan['color_part'] = '';
            } else {
                $sizePart = $this->catalogCodePart($plan['size']);
                $plan['candidate'] = $this->catalogCodePart($baseCode);
                if ($sizePart !== '') {
                    $plan['candidate'] .= '-' . $sizePart;
                }
                $plan['color_part'] = $this->catalogCodePart($plan['color']);
            }
            $plan['requires_split'] = $requiresSplit;
            return $plan;
        });
        $candidateCounts = $plans->countBy('candidate');

        return $plans->map(function ($plan) use ($candidateCounts) {
            $proposed = $plan['candidate'];
            if ($plan['requires_split'] && ($candidateCounts[$proposed] ?? 0) > 1 && $plan['color_part'] !== '') {
                $proposed .= '-' . $plan['color_part'];
            }
            $plan['proposed_code'] = $proposed;
            unset($plan['candidate'], $plan['color_part'], $plan['linked_code']);
            return $plan;
        });
    }

    private function catalogCodePart($value): string
    {
        return trim(preg_replace('/-+/', '-', preg_replace('/[^A-Z0-9]+/', '-', mb_strtoupper(Str::ascii(trim((string) $value))))), '-');
    }

    public function splitDuplicateCodes(Request $request, GoogleSheetCatalogWriter $writer)
    {
        $data = $request->validate([
            'code' => 'required|string|max:200',
            'changes' => 'nullable|array',
            'changes.*.id' => 'required|integer',
            'changes.*.new_code' => 'required|string|max:200',
        ]);
        $apply = $request->boolean('apply');
        $preview = $this->duplicateCodeSplitPreview($data['code']);

        if ($apply && !empty($data['changes'])) {
            $editableIds = collect($preview['changes'])->pluck('id')->map(fn ($id) => (int) $id)->all();
            $submitted = collect($data['changes'])->keyBy(fn ($change) => (int) $change['id']);

            if ($submitted->keys()->diff($editableIds)->isNotEmpty() || count($editableIds) !== $submitted->count()) {
                return response()->json([
                    'message' => 'Danh sách dòng đã thay đổi. Hãy đóng cửa sổ và xem trước lại.',
                    'data' => $preview,
                    'write_configured' => $writer->isConfigured(),
                ], 422);
            }

            $newCodes = [];

            foreach ($preview['changes'] as &$change) {
                $newCode = trim((string) $submitted->get((int) $change['id'])['new_code']);
                $normalized = mb_strtoupper($newCode);
                $alreadyExists = $newCode !== '' && InternalItemCatalog::query()
                    ->where('is_active', true)
                    ->whereNotIn('id', $editableIds)
                    ->whereRaw('UPPER(TRIM(item_code)) = ?', [$normalized])
                    ->exists();
                if ($newCode === '' || $alreadyExists || isset($newCodes[$normalized])) {
                    return response()->json([
                        'message' => "Mã mới {$newCode} đang trống hoặc đã tồn tại. Hãy nhập mã khác.",
                        'data' => $preview,
                        'write_configured' => $writer->isConfigured(),
                    ], 422);
                }
                $change['new_code'] = $newCode;
                $newCodes[$normalized] = true;
            }
            unset($change);
        }

        if (!$apply || !$preview['changes']) {
            return response()->json([
                'message' => $preview['changes'] ? 'Đã tạo bản xem trước tách mã.' : 'Danh mục không còn mã trùng.',
                'data' => $preview,
                'write_configured' => $writer->isConfigured(),
            ]);
        }

        if (!$writer->isConfigured()) {
            return response()->json([
                'message' => 'Chưa cấu hình quyền ghi Google Sheet. Chưa có dữ liệu nào được thay đổi.',
                'data' => $preview,
                'write_configured' => false,
            ], 503);
        }

        $writer->writeItemCodes(self::SPREADSHEET_ID, self::SHEET_NAME, $preview['changes']);

        DB::connection('internal')->transaction(function () use ($preview) {
            foreach ($preview['changes'] as $change) {
                $catalog = InternalItemCatalog::query()->findOrFail($change['id']);
                $rawData = is_array($catalog->raw_data) ? $catalog->raw_data : [];
                $rawData[array_key_exists('mahang', $rawData) ? 'mahang' : 'ma hang'] = $change['new_code'];
                $catalog->update([
                    'item_code' => $change['new_code'],
                    'raw_data' => $rawData,
                ]);
                DB::connection('internal')->table('internal_production_orders')
                    ->where('standard_catalog_id', $catalog->id)
                    ->update(['standard_item_code' => $change['new_code']]);
            }
        });
        Cache::forget('internal_catalog_customer_map_v1');

        return response()->json([
            'message' => 'Đã tách mã và ghi lại tab DANH MỤC trên Google Sheet.',
            'data' => $preview,
            'write_configured' => true,
        ]);
    }

    public function updateCatalogRow(Request $request, InternalItemCatalog $catalog, GoogleSheetCatalogWriter $writer)
    {
        if (!$catalog->is_active || (int) $catalog->source_row < 2) {
            return response()->json(['message' => 'Dòng danh mục không còn hoạt động hoặc thiếu dòng nguồn.'], 422);
        }

        $data = $request->validate([
            'item_code' => 'nullable|string|max:200',
            'item_name' => 'required|string|max:500',
            'unit' => 'nullable|string|max:50',
            'source_type' => 'nullable|string|max:100',
            'shelf_code' => 'nullable|string|max:150',
            'opening_quantity' => 'nullable|numeric|min:-999999999999999|max:999999999999999',
        ], [
            'item_name.required' => 'Tên hàng không được để trống.',
            'opening_quantity.numeric' => 'Tồn đầu phải là số.',
        ]);

        $itemCode = trim((string) ($data['item_code'] ?? ''));
        if ($itemCode !== '' && InternalItemCatalog::query()
            ->where('is_active', true)
            ->where('id', '<>', $catalog->id)
            ->whereRaw('UPPER(TRIM(item_code)) = ?', [mb_strtoupper($itemCode)])
            ->exists()) {
            return response()->json(['message' => "Mã {$itemCode} đã tồn tại trong danh mục."], 422);
        }
        if (!$writer->isConfigured()) {
            return response()->json(['message' => 'Chưa cấu hình quyền ghi Google Sheet. Chưa có dữ liệu nào được thay đổi.'], 503);
        }

        $fields = [
            'mahang' => $itemCode,
            'ten hang' => trim((string) $data['item_name']),
            'dvt' => trim((string) ($data['unit'] ?? '')),
            'loai' => trim((string) ($data['source_type'] ?? '')),
            'ke' => trim((string) ($data['shelf_code'] ?? '')),
            'ton dau' => (float) ($data['opening_quantity'] ?? 0),
        ];

        try {
            $writer->writeRowFields(self::SPREADSHEET_ID, self::SHEET_NAME, (int) $catalog->source_row, $fields);
        } catch (\Throwable $error) {
            report($error);
            return response()->json([
                'message' => 'Không ghi được Google Sheet: ' . $error->getMessage() . ' Database nội bộ chưa thay đổi.',
            ], 502);
        }

        DB::connection('internal')->transaction(function () use ($catalog, $fields) {
            $raw = is_array($catalog->raw_data) ? $catalog->raw_data : [];
            foreach ($fields as $key => $value) {
                $raw[$key] = (string) $value;
            }
            $catalog->update([
                'item_code' => $fields['mahang'] !== '' ? $fields['mahang'] : null,
                'item_name' => $fields['ten hang'],
                'unit' => $fields['dvt'],
                'shelf_code' => $fields['ke'],
                'opening_quantity' => $fields['ton dau'],
                'raw_data' => $raw,
            ]);
            DB::connection('internal')->table('internal_production_orders')
                ->where('standard_catalog_id', $catalog->id)
                ->update(['standard_item_code' => $catalog->item_code]);
        });
        Cache::forget('internal_catalog_customer_map_v1');

        return response()->json([
            'message' => 'Đã cập nhật đúng dòng ' . (int) $catalog->source_row . ' trên Google Sheet.',
        ]);
    }

    public function autoSync(Request $request)
    {
        $minutes = min(max((int) $request->input('minutes', 30), 5), 1440);
        $lastSync = InternalItemCatalog::query()->max('updated_at');

        if ($lastSync && now()->diffInMinutes($lastSync) < $minutes) {
            return response()->json([
                'message' => 'Danh muc van con moi, chua can dong bo.',
                'skipped' => true,
                'data' => [
                    'last_synced_at' => $lastSync,
                    'next_sync_at' => \Carbon\Carbon::parse($lastSync)->addMinutes($minutes)->toDateTimeString(),
                    'active' => InternalItemCatalog::query()->where('is_active', true)->count(),
                    'sheet' => self::SHEET_NAME,
                ],
            ]);
        }

        $response = $this->sync();
        $payload = $response->getData(true);
        $payload['skipped'] = false;

        return response()->json($payload, $response->getStatusCode());
    }

    public function syncShelvesToLocations()
    {
        $shelves = InternalItemCatalog::query()
            ->where('is_active', true)
            ->whereNotNull('shelf_code')
            ->where('shelf_code', '<>', '')
            ->pluck('shelf_code')
            ->flatMap(function ($value) {
                return preg_split('/[,;|\n\r]+/', (string) $value) ?: [];
            })
            ->map(fn ($value) => $this->normalizeShelfLocationCode($value))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::connection('internal')->transaction(function () use ($shelves, &$created, &$updated, &$skipped) {
            foreach ($shelves as $index => $locationCode) {
                $existing = WarehouseLocation::query()
                    ->where('location_code', $locationCode)
                    ->first();

                $shelf = $this->inferShelfCode($locationCode);
                $bay = $this->inferBayCode($locationCode);
                $tier = $this->inferTierFromLocationCode($locationCode) ?: 1;

                if ($existing) {
                    $changed = false;
                    if ($shelf !== '' && $existing->shelf_code !== $shelf) {
                        $existing->shelf_code = $shelf;
                        $changed = true;
                    }
                    if ((int) $existing->tier !== (int) $tier) {
                        $existing->tier = $tier;
                        $changed = true;
                    }
                    if ($bay !== '' && (string) $existing->bay_code !== $bay) {
                        $existing->bay_code = $bay;
                        $changed = true;
                    }
                    if (!$existing->location_name) {
                        $existing->location_name = 'Kệ ' . $locationCode;
                        $changed = true;
                    }
                    if ($changed) {
                        $existing->save();
                        $updated++;
                    } else {
                        $skipped++;
                    }
                    continue;
                }

                WarehouseLocation::query()->create([
                    'location_code' => $locationCode,
                    'warehouse_code' => '',
                    'shelf_code' => $shelf,
                    'tier' => $tier,
                    'bay_code' => $bay ?: null,
                    'grid_x' => (($index % 6) * 4) + 1,
                    'grid_y' => ((int) floor($index / 6) * 3) + 1,
                    'grid_w' => 4,
                    'grid_h' => 2,
                    'location_name' => 'Kệ ' . $locationCode,
                    'status' => 'pending',
                    'note' => 'Tạo từ cột Kệ trong DANH MỤC.',
                ]);
                $created++;
            }
        });

        return response()->json([
            'message' => 'Đã đồng bộ kệ trong DANH MỤC sang vị trí kho nội bộ.',
            'data' => [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'total_valid_shelves' => $shelves->count(),
            ],
        ]);
    }

    public function uploadImage(Request $request, InternalItemCatalog $catalog)
    {
        $data = $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp,gif|max:8192',
        ]);

        $file = $data['image'];
        $cloudName = trim((string) env('CLOUDINARY_CLOUD_NAME', ''));
        $apiKey = trim((string) env('CLOUDINARY_API_KEY', ''));
        $apiSecret = trim((string) env('CLOUDINARY_API_SECRET', ''));

        if ($cloudName === '' || $apiKey === '' || $apiSecret === '') {
            return response()->json([
                'message' => 'Chưa cấu hình Cloudinary. Thêm CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, CLOUDINARY_API_SECRET vào .env.',
            ], 422);
        }

        $code = trim((string) ($catalog->item_code ?: ('catalog-' . $catalog->id)));
        $safeCode = Str::slug(Str::ascii($code), '-');
        if ($safeCode === '') {
            $safeCode = 'catalog-' . $catalog->id;
        }

        $timestamp = time();
        $folder = trim((string) env('CLOUDINARY_CATALOG_FOLDER', 'ttv-admin/catalog'));
        $publicId = $safeCode . '-' . now('Asia/Ho_Chi_Minh')->format('YmdHis') . '-' . Str::random(6);
        $paramsToSign = [
            'folder' => $folder,
            'public_id' => $publicId,
            'timestamp' => $timestamp,
        ];
        ksort($paramsToSign);
        $signatureBase = collect($paramsToSign)
            ->map(fn ($value, $key) => $key . '=' . $value)
            ->implode('&') . $apiSecret;
        $signature = sha1($signatureBase);

        $response = Http::timeout(60)
            ->attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
            ->post("https://api.cloudinary.com/v1_1/{$cloudName}/image/upload", [
                'api_key' => $apiKey,
                'timestamp' => $timestamp,
                'folder' => $folder,
                'public_id' => $publicId,
                'signature' => $signature,
            ]);

        if (!$response->successful()) {
            return response()->json([
                'message' => 'Upload ảnh lên Cloudinary thất bại.',
                'debug' => $response->json() ?: $response->body(),
            ], 502);
        }

        $payload = $response->json();

        $catalog->image_url = $payload['secure_url'] ?? $payload['url'] ?? '';
        $catalog->image_public_id = $payload['public_id'] ?? $publicId;
        $catalog->image_source = 'cloudinary';
        $catalog->image_uploaded_at = now('Asia/Ho_Chi_Minh');
        $catalog->save();

        return response()->json([
            'message' => 'Đã upload ảnh lên Cloudinary.',
            'data' => [
                'id' => $catalog->id,
                'item_code' => $catalog->item_code,
                'image_url' => $catalog->image_url,
                'image_public_id' => $catalog->image_public_id,
                'image_source' => $catalog->image_source,
            ],
        ]);
    }

    public function ensureFromOrder(Request $request, GoogleSheetCatalogWriter $writer)
    {
        $data = $request->validate([
            'item_code' => 'required|string|max:200',
            'source_item_code' => 'nullable|string|max:200',
            'production_order_line_id' => 'nullable|integer|min:1',
            'source_catalog_id' => 'nullable|integer|min:1',
            'item_name' => 'nullable|string|max:500',
            'unit' => 'nullable|string|max:50',
            'size' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:1000',
            'item_group' => 'nullable|string|max:100',
            'image_url' => 'nullable|string|max:2048',
        ]);

        $sourceItemCode = trim((string) ($data['source_item_code'] ?? $data['item_code']));
        $requestedItemCode = trim((string) $data['item_code']);
        $itemCode = trim(preg_replace('/-{2,}/', '-', preg_replace('/[\s\x{00A0}]+/u', '-', $requestedItemCode)), '-');
        $normalizedCode = mb_strtoupper($itemCode);
        $itemName = trim((string) ($data['item_name'] ?? ''));
        $unit = trim((string) ($data['unit'] ?? ''));
        $size = trim((string) ($data['size'] ?? ''));
        $color = trim((string) ($data['color'] ?? ''));
        $itemGroup = trim((string) ($data['item_group'] ?? ''));
        $linkOrderId = (int) ($data['production_order_line_id'] ?? 0);
        $linkOrder = $linkOrderId > 0
            ? InternalProductionOrder::query()->find($linkOrderId)
            : null;
        if ($linkOrderId > 0 && !$linkOrder) {
            return response()->json([
                'message' => 'Dòng Lệnh SX trung tâm cần liên kết không còn tồn tại.',
            ], 422);
        }
        $sourceCatalog = !empty($data['source_catalog_id'])
            ? InternalItemCatalog::query()->find((int) $data['source_catalog_id'])
            : null;
        if ($itemGroup === '') {
            $sourceRawData = is_array($sourceCatalog->raw_data ?? null) ? $sourceCatalog->raw_data : [];
            $itemGroup = trim((string) ($sourceRawData['loai'] ?? '')) ?: 'TP';
        }
        $imageUrl = trim((string) ($data['image_url'] ?? ''))
            ?: trim((string) ($sourceCatalog->image_url ?? ''));

        $catalogMatches = InternalItemCatalog::query()
            ->whereRaw('UPPER(TRIM(item_code)) = ?', [$normalizedCode])
            ->orderBy('source_row')
            ->limit(2)
            ->get();
        if ($catalogMatches->count() > 1) {
            return response()->json([
                'message' => "Mã {$itemCode} đang có nhiều dòng trong Danh mục nội bộ. Hãy chọn đúng dòng, không append thêm.",
            ], 409);
        }
        $catalog = $catalogMatches->first();

        if ($catalog && (int) $catalog->source_row >= 2) {
            if (!$catalog->is_active) {
                $catalog->is_active = true;
                $catalog->save();
            }
            if ($linkOrder) {
                $linkOrder->standard_item_code = trim((string) $catalog->item_code);
                $linkOrder->standard_catalog_id = (int) $catalog->id;
                $linkOrder->save();
            }

            return response()->json([
                'message' => 'Mã đã có trong Danh mục nội bộ và Google Sheet.',
                'data' => [
                    'id' => (int) $catalog->id,
                    'item_code' => trim((string) $catalog->item_code),
                    'item_name' => trim((string) $catalog->item_name),
                    'image_url' => trim((string) $catalog->image_url),
                    'source_row' => (int) $catalog->source_row,
                    'created_from_order' => false,
                    'appended_to_sheet' => false,
                    'linked_order_line_id' => $linkOrder ? (int) $linkOrder->id : null,
                ],
            ]);
        }

        if (!$writer->isConfigured()) {
            return response()->json([
                'message' => 'Chưa cấu hình quyền ghi Google Sheet. Mã chưa được tạo để tránh lệch Danh mục.',
            ], 503);
        }

        try {
            $sheetRows = $writer->findItemRows(self::SPREADSHEET_ID, self::SHEET_NAME, [$itemCode]);
            $matchingRows = array_values($sheetRows[$normalizedCode] ?? []);

            if (count($matchingRows) > 1) {
                return response()->json([
                    'message' => "Mã {$itemCode} đang xuất hiện ở nhiều dòng Google Sheet. Hãy xử lý mã trùng trước.",
                    'rows' => $matchingRows,
                ], 409);
            }

            $appendedToSheet = false;
            $sourceRow = (int) ($matchingRows[0] ?? 0);
            if ($sourceRow < 2) {
                $appendedRows = $writer->appendRows(self::SPREADSHEET_ID, self::SHEET_NAME, [[
                    'MAHANG' => $itemCode,
                    'TEN HANG' => $itemName,
                    'DVT' => $unit,
                    'SIZE' => $size,
                    'MAU' => $color,
                    'LOAI' => $itemGroup,
                    'KE' => '',
                    'TON DAU' => 0,
                    'ANH' => $imageUrl,
                ]]);
                $sourceRow = (int) ($appendedRows[0] ?? 0);
                $appendedToSheet = true;
            }
        } catch (\Throwable $error) {
            return response()->json([
                'message' => 'Không append được mã vào Google Sheet DANH MỤC: ' . $error->getMessage(),
            ], 502);
        }

        if ($sourceRow < 2) {
            return response()->json([
                'message' => 'Google Sheet không trả về dòng vừa append. Danh mục nội bộ chưa thay đổi.',
            ], 502);
        }

        try {
            $catalog = DB::connection('internal')->transaction(function () use (
                $itemCode,
                $sourceItemCode,
                $normalizedCode,
                $itemName,
                $unit,
                $size,
                $color,
                $itemGroup,
                $sourceRow,
                $imageUrl,
                $sourceCatalog
            ) {
                $existingMatches = InternalItemCatalog::query()
                    ->whereRaw('UPPER(TRIM(item_code)) = ?', [$normalizedCode])
                    ->lockForUpdate()
                    ->limit(2)
                    ->get();
                if ($existingMatches->count() > 1) {
                    throw new \RuntimeException("Mã {$itemCode} đang có nhiều dòng trong Danh mục nội bộ.");
                }
                $existing = $existingMatches->first();

                $sourceOwner = InternalItemCatalog::query()
                    ->where('source_row', $sourceRow)
                    ->lockForUpdate()
                    ->first();
                if ($sourceOwner && (!$existing || (int) $sourceOwner->id !== (int) $existing->id)) {
                    throw new \RuntimeException(
                        "Dòng Google Sheet {$sourceRow} đang liên kết với mã {$sourceOwner->item_code}."
                    );
                }

                if ($existing) {
                    $raw = is_array($existing->raw_data) ? $existing->raw_data : [];
                    $raw = array_merge($raw, [
                        'ma hang' => $itemCode,
                        'ma hang nguon' => $sourceItemCode,
                        'ten hang' => trim((string) $existing->item_name) ?: $itemName,
                        'dvt' => trim((string) $existing->unit) ?: $unit,
                        'size' => trim((string) $existing->size) ?: $size,
                        'mau' => trim((string) $existing->color) ?: $color,
                        'loai' => $raw['loai'] ?? $itemGroup,
                        'anh' => trim((string) $existing->image_url) ?: $imageUrl,
                        '_internal_origin' => 'production_order_standard_code',
                    ]);
                    $existing->source_row = $sourceRow;
                    $existing->item_name = trim((string) $existing->item_name) ?: $itemName;
                    $existing->unit = trim((string) $existing->unit) ?: $unit;
                    $existing->size = trim((string) $existing->size) ?: $size;
                    $existing->color = trim((string) $existing->color) ?: $color;
                    $existing->image_url = trim((string) $existing->image_url) ?: ($imageUrl ?: null);
                    $existing->image_public_id = $existing->image_public_id ?: ($sourceCatalog->image_public_id ?? null);
                    $existing->image_source = $existing->image_source ?: ($sourceCatalog->image_source ?? null);
                    $existing->image_uploaded_at = $existing->image_uploaded_at ?: ($sourceCatalog->image_uploaded_at ?? null);
                    $existing->raw_data = $raw;
                    $existing->source_hash = hash('sha256', json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                    $existing->sync_batch = 'internal-image';
                    $existing->is_active = true;
                    $existing->save();

                    return $existing;
                }

                $raw = [
                    'ma hang' => $itemCode,
                    'ma hang nguon' => $sourceItemCode,
                    'ten hang' => $itemName,
                    'dvt' => $unit,
                    'size' => $size,
                    'mau' => $color,
                    'loai' => $itemGroup,
                    'anh' => $imageUrl,
                    '_internal_origin' => 'production_order_standard_code',
                ];

                return InternalItemCatalog::query()->create([
                    'source_row' => $sourceRow,
                    'item_code' => $itemCode,
                    'item_name' => $itemName,
                    'unit' => $unit,
                    'size' => $size,
                    'color' => $color,
                    'image_url' => $imageUrl ?: null,
                    'image_public_id' => $sourceCatalog->image_public_id ?? null,
                    'image_source' => $sourceCatalog->image_source ?? ($imageUrl !== '' ? 'catalog_copy' : null),
                    'image_uploaded_at' => $sourceCatalog->image_uploaded_at ?? null,
                    'opening_quantity' => 0,
                    'raw_data' => $raw,
                    'source_hash' => hash('sha256', json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                    'sync_batch' => 'internal-image',
                    'is_active' => true,
                ]);
            });

            $linkQuery = InternalProductionOrder::query();
            if ($linkOrder) {
                $linkQuery->whereKey($linkOrder->id);
            } else {
                $linkQuery->whereRaw('UPPER(TRIM(item_code)) = ?', [mb_strtoupper($sourceItemCode)]);
            }
            $linkQuery->update([
                'standard_item_code' => $catalog->item_code,
                'standard_catalog_id' => $catalog->id,
            ]);
        } catch (\Throwable $error) {
            return response()->json([
                'message' => 'Google Sheet đã có mã nhưng không liên kết được database nội bộ: ' . $error->getMessage(),
            ], 409);
        }

        Cache::forget('internal_catalog_customer_map_v1');

        return response()->json([
            'message' => $appendedToSheet
                ? 'Đã append mã vào cuối Google Sheet DANH MỤC và liên kết database nội bộ.'
                : 'Đã liên kết mã có sẵn trên Google Sheet với database nội bộ.',
            'data' => [
                'id' => (int) $catalog->id,
                'item_code' => trim((string) $catalog->item_code),
                'item_name' => trim((string) $catalog->item_name),
                'image_url' => trim((string) $catalog->image_url),
                'source_row' => (int) $catalog->source_row,
                'created_from_order' => (bool) $catalog->wasRecentlyCreated,
                'appended_to_sheet' => $appendedToSheet,
                'linked_order_line_id' => $linkOrder ? (int) $linkOrder->id : null,
            ],
        ], $appendedToSheet || $catalog->wasRecentlyCreated ? 201 : 200);
    }

    private function duplicateCodeSplitPreview(string $requestedCode): array
    {
        $requestedCode = mb_strtoupper(trim($requestedCode));
        $rows = InternalItemCatalog::query()
            ->where('is_active', true)
            ->whereNotNull('item_code')
            ->where('item_code', '<>', '')
            ->orderBy('source_row')
            ->get();
        $usedCodes = $rows->pluck('item_code')
            ->map(fn ($code) => mb_strtoupper(trim((string) $code)))
            ->filter()
            ->flip()
            ->all();
        $changes = [];
        $groupCount = 0;

        $rows->filter(fn ($row) => mb_strtoupper(trim((string) $row->item_code)) === $requestedCode)
            ->groupBy(fn ($row) => mb_strtoupper(trim((string) $row->item_code)))
            ->filter(fn ($group) => $group->count() > 1)
            ->each(function ($group, $normalizedCode) use (&$changes, &$groupCount, &$usedCodes) {
                $groupCount++;
                $sorted = $group->sortBy(function ($row) {
                    return ($this->catalogVariantSuffix($row) === '' ? '0' : '1') . str_pad((string) $row->source_row, 10, '0', STR_PAD_LEFT);
                })->values();
                $baseCode = trim((string) $sorted->first()->item_code);

                $sorted->slice(1)->each(function ($row) use (&$changes, &$usedCodes, $baseCode) {
                    $suffix = $this->catalogVariantSuffix($row);
                    if ($suffix === '') {
                        $suffix = 'ROW' . (int) $row->source_row;
                    }
                    $candidate = mb_substr($baseCode . '-' . $suffix, 0, 200);
                    if (isset($usedCodes[mb_strtoupper($candidate)])) {
                        $candidate = mb_substr($baseCode . '-' . $suffix . '-R' . (int) $row->source_row, 0, 200);
                    }
                    $sequence = 2;
                    while (isset($usedCodes[mb_strtoupper($candidate)])) {
                        $candidate = mb_substr($baseCode . '-' . $suffix . '-' . $sequence, 0, 200);
                        $sequence++;
                    }
                    $usedCodes[mb_strtoupper($candidate)] = true;
                    $changes[] = [
                        'id' => (int) $row->id,
                        'source_row' => (int) $row->source_row,
                        'old_code' => trim((string) $row->item_code),
                        'new_code' => $candidate,
                        'item_name' => trim((string) $row->item_name),
                        'color' => trim((string) $row->color),
                    ];
                });
            });

        return [
            'code' => $requestedCode,
            'duplicate_group_count' => $groupCount,
            'change_count' => count($changes),
            'changes' => $changes,
        ];
    }

    private function catalogVariantSuffix($row): string
    {
        $text = mb_strtoupper(Str::ascii(implode(' ', [
            $row->color,
            $row->item_name,
            $row->logo_color,
        ])));
        $colors = [
            'BLACK' => ['BLACK', ' DEN '],
            'WHITE' => ['WHITE', ' TRANG ', 'TAY'],
            'PINK' => ['PINK', ' HONG '],
            'PURPLE' => ['PURPLE', ' TIM '],
            'NAVY' => ['NAVY'],
            'BLUE' => ['BLUE', ' XANH DUONG '],
            'GREEN' => ['GREEN', ' XANH LA '],
            'GREY' => ['GREY', 'GRAY', ' XAM '],
            'RED' => ['RED', ' DO '],
            'YELLOW' => ['YELLOW', ' VANG '],
            'ORANGE' => ['ORANGE', ' CAM '],
            'BROWN' => ['BROWN', ' NAU '],
            'BEIGE' => ['BEIGE', ' KEM '],
        ];
        $haystack = ' ' . preg_replace('/[^A-Z0-9]+/', ' ', $text) . ' ';
        foreach ($colors as $suffix => $needles) {
            foreach ($needles as $needle) {
                if (strpos($haystack, $needle) !== false) {
                    return $suffix;
                }
            }
        }
        return '';
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

    private function searchTokens($keyword)
    {
        return collect(explode(' ', $this->normalizeSearchText($keyword)))
            ->filter()
            ->values();
    }

    private function matchesTokens(string $haystack, $tokens): bool
    {
        if ($tokens->isEmpty()) {
            return true;
        }

        $haystack = $this->normalizeSearchText($haystack);
        return $tokens->every(fn ($token) => strpos($haystack, $token) !== false);
    }

    private function catalogSearchText($row): string
    {
        return implode(' ', [
            $row->item_code,
            $row->item_name,
            $row->unit,
            $row->size,
            $row->color,
            $row->logo_color,
            $row->side,
            $row->shelf_code,
            $row->opening_quantity,
            $row->source_row,
            $row->item_group ?? '',
            implode(' ', $row->customers ?? []),
            is_array($row->raw_data) ? json_encode($row->raw_data, JSON_UNESCAPED_UNICODE) : $row->raw_data,
        ]);
    }

    private function catalogCustomerMap(): array
    {
        return Cache::remember('internal_catalog_customer_map_v1', now()->addMinutes(5), function () {
            $map = [];
            DB::connection('internal')->table('internal_production_orders')
                ->whereNotNull('customer')
                ->where('customer', '<>', '')
                ->select(['customer', 'item_code', 'standard_item_code'])
                ->orderBy('id')
                ->chunk(1000, function ($orders) use (&$map) {
                    foreach ($orders as $order) {
                        $code = trim((string) ($order->standard_item_code ?: $order->item_code));
                        $customer = trim((string) $order->customer);
                        if ($code === '' || $customer === '') {
                            continue;
                        }
                        $key = mb_strtoupper($code);
                        $map[$key][$customer] = true;
                    }
                });

            return collect($map)->map(fn ($customers) => collect(array_keys($customers))->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all())->all();
        });
    }

    private function catalogItemGroup($row): string
    {
        return app(InternalItemGroupResolver::class)->resolve($row);
    }

    private function documentLineSearchText($row): string
    {
        return implode(' ', [
            $row->document_code,
            $row->document_date,
            $row->internal_item_code,
            $row->ma_hh,
            $row->ten_hh,
            $row->size,
            $row->color,
            $row->side,
            $row->quantity,
            $row->location_code,
            $row->note,
        ]);
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

    private function normalizeShelfLocationCode($value): string
    {
        $raw = strtoupper(trim(Str::ascii((string) $value)));
        $raw = preg_replace('/\s+/', '', $raw);
        if ($raw === '' || in_array($raw, ['CHUAXUAT', 'CHUAXEP', 'CHUA-XUAT', 'CHUA-XEP', 'KHONGCO', 'NONE'], true)) {
            return '';
        }

        if (preg_match('/^[A-Z]{1,3}0*\d{1,4}$/', $raw)) {
            return $raw;
        }

        return '';
    }

    private function inferShelfCode(string $locationCode): string
    {
        if (preg_match('/^([A-Z])0*(\d{1,4})$/', $locationCode, $match)) {
            return ltrim($match[2], '0') ?: '0';
        }

        return preg_match('/^[A-Z]+/', $locationCode, $match) ? $match[0] : '';
    }

    private function inferBayCode(string $locationCode): string
    {
        return preg_match('/(\d+)$/', $locationCode, $match) ? ltrim($match[1], '0') ?: '0' : '';
    }

    private function inferTierFromLocationCode(string $locationCode): int
    {
        if (!preg_match('/^([A-Z])0*\d{1,4}$/', $locationCode, $match)) {
            return 0;
        }

        $letterIndex = ord($match[1]) - ord('A');

        return 5 - ($letterIndex % 5);
    }
}
