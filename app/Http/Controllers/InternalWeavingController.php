<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\NormalizesDateInput;
use App\Models\InternalItemCatalog;
use App\Models\InternalProductionOrder;
use App\Models\InternalWeavingBom;
use App\Models\InternalWeavingItem;
use App\Models\InternalWeavingOrder;
use App\Models\InventoryPackage;
use App\Services\InternalUnitConverter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class InternalWeavingController extends Controller
{
    use NormalizesDateInput;

    public function index()
    {
        return view('client.internal-weaving');
    }

    public function items(Request $request)
    {
        $keyword = mb_strtoupper(trim((string) $request->query('keyword', '')));
        $page = max((int) $request->query('page', 1), 1);
        $perPage = min(max((int) $request->query('per_page', 50), 25), 200);

        $query = InternalWeavingItem::query()
            ->withCount('boms')
            ->orderBy('item_code');

        if ($keyword !== '') {
            $query->where(function ($sub) use ($keyword) {
                $sub->whereRaw('UPPER(item_code) LIKE ?', ["%{$keyword}%"])
                    ->orWhereRaw('UPPER(item_name) LIKE ?', ["%{$keyword}%"])
                    ->orWhereRaw('UPPER(customer) LIKE ?', ["%{$keyword}%"]);
            });
        }

        $total = (clone $query)->count();
        $rows = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return response()->json([
            'data' => $rows,
            'summary' => [
                'item_count' => InternalWeavingItem::query()->count(),
                'bom_count' => InternalWeavingBom::query()->count(),
            ],
            'pagination' => $this->pagination($page, $perPage, $total),
        ]);
    }

    public function storeItem(Request $request)
    {
        $data = $request->validate([
            'item_code' => 'required|string|max:120',
            'item_name' => 'nullable|string|max:500',
            'customer' => 'nullable|string|max:200',
            'unit' => 'nullable|string|max:50',
            'note' => 'nullable|string|max:2000',
        ]);

        $item = InternalWeavingItem::query()->updateOrCreate(
            ['item_code' => $this->cleanCode($data['item_code'])],
            [
                'item_name' => trim((string) ($data['item_name'] ?? '')),
                'customer' => trim((string) ($data['customer'] ?? '')),
                'unit' => trim((string) ($data['unit'] ?? '')),
                'note' => trim((string) ($data['note'] ?? '')),
            ]
        );

        return response()->json(['message' => 'Da luu ma hang det.', 'data' => $item]);
    }

    public function importItems(Request $request)
    {
        $data = $request->validate([
            'text' => 'required|string',
        ]);

        $rows = $this->parseTsv($data['text']);
        $created = 0;
        $updated = 0;

        DB::connection('internal')->transaction(function () use ($rows, &$created, &$updated) {
            foreach ($rows as $row) {
                $code = $this->cleanCode($row[0] ?? '');
                if ($code === '') continue;

                $item = InternalWeavingItem::query()->updateOrCreate(
                    ['item_code' => $code],
                    [
                        'item_name' => trim((string) ($row[1] ?? '')),
                        'customer' => trim((string) ($row[2] ?? '')),
                        'unit' => trim((string) ($row[3] ?? '')),
                        'note' => trim((string) ($row[4] ?? '')),
                    ]
                );
                $item->wasRecentlyCreated ? $created++ : $updated++;
            }
        });

        return response()->json(['message' => 'Da import danh muc hang det.', 'data' => compact('created', 'updated')]);
    }

    public function boms(Request $request)
    {
        $itemCode = $this->cleanCode($request->query('item_code', ''));
        if ($itemCode === '') {
            return response()->json(['data' => [], 'summary' => ['line_count' => 0]]);
        }

        $item = InternalWeavingItem::query()->where('item_code', $itemCode)->first();
        if (!$item) {
            return response()->json(['data' => [], 'summary' => ['line_count' => 0]]);
        }

        $rows = $item->boms()->orderBy('material_code')->get();
        $catalog = $this->catalogByCodes($rows->pluck('material_code')->all());

        return response()->json([
            'item' => $item,
            'data' => $rows->map(fn ($row) => $this->decorateBomRow($row, $catalog))->values(),
            'summary' => ['line_count' => $rows->count()],
        ]);
    }

    public function replaceBoms(Request $request)
    {
        $data = $request->validate([
            'item_code' => 'required|string|max:120',
            'item_name' => 'nullable|string|max:500',
            'customer' => 'nullable|string|max:200',
            'unit' => 'nullable|string|max:50',
            'lines' => 'required|array|min:1|max:500',
            'lines.*.material_code' => 'required|string|max:120',
            'lines.*.line_role' => 'nullable|string|max:120',
            'lines.*.material_name' => 'nullable|string|max:500',
            'lines.*.unit' => 'nullable|string|max:50',
            'lines.*.consumption_per_unit' => 'required|numeric|min:0',
            'lines.*.waste_percent' => 'nullable|numeric|min:0|max:999',
            'lines.*.note' => 'nullable|string|max:2000',
        ]);

        $missingCatalog = $this->missingCatalogCodes(collect($data['lines'])->pluck('material_code')->all());
        if (!empty($missingCatalog)) {
            return response()->json([
                'message' => 'Mã sợi chưa có trong DANH MỤC nội bộ: ' . implode(', ', array_slice($missingCatalog, 0, 20)),
                'missing_catalog' => $missingCatalog,
            ], 422);
        }

        $item = DB::connection('internal')->transaction(function () use ($data) {
            $item = InternalWeavingItem::query()->updateOrCreate(
                ['item_code' => $this->cleanCode($data['item_code'])],
                [
                    'item_name' => trim((string) ($data['item_name'] ?? '')),
                    'customer' => trim((string) ($data['customer'] ?? '')),
                    'unit' => trim((string) ($data['unit'] ?? '')),
                ]
            );

            $item->boms()->delete();
            foreach ($data['lines'] as $index => $line) {
                $lineRole = $this->cleanCode($line['line_role'] ?? '') ?: ('DONG-' . ($index + 1));
                $item->boms()->create([
                    'material_code' => $this->cleanCode($line['material_code']),
                    'line_role' => $lineRole,
                    'material_name' => trim((string) ($line['material_name'] ?? '')),
                    'unit' => trim((string) ($line['unit'] ?? '')),
                    'consumption_per_unit' => (float) $line['consumption_per_unit'],
                    'waste_percent' => (float) ($line['waste_percent'] ?? 0),
                    'note' => trim((string) ($line['note'] ?? '')),
                ]);
            }

            return $item->load('boms');
        });

        return response()->json(['message' => 'Da luu dinh muc soi.', 'data' => $item]);
    }

    public function importBoms(Request $request)
    {
        $data = $request->validate(['text' => 'required|string']);
        $rows = $this->parseTsv($data['text']);
        $itemsTouched = [];
        $lineCount = 0;
        $validRows = collect($rows)
            ->map(fn ($row) => $this->normalizeBomImportRow($row))
            ->filter(fn ($row) => $row['item_code'] !== '' && $row['material_code'] !== '')
            ->values();
        $missingCatalog = $this->missingCatalogCodes($validRows->pluck('material_code')->all());
        if (!empty($missingCatalog)) {
            return response()->json([
                'message' => 'Import dừng: mã sợi chưa có trong DANH MỤC nội bộ: ' . implode(', ', array_slice($missingCatalog, 0, 20)),
                'missing_catalog' => $missingCatalog,
            ], 422);
        }

        $itemCodes = $validRows->pluck('item_code')->unique()->values();
        $materialCatalog = $this->catalogByCodes($validRows->pluck('material_code')->all());
        $itemCatalog = $this->catalogByCodes($itemCodes->all());
        $productionNames = InternalProductionOrder::query()
            ->where('is_active', true)
            ->whereIn('item_code', $itemCodes->all())
            ->select('item_code', DB::raw('MIN(description) as description'), DB::raw('MIN(customer) as customer'), DB::raw('MIN(unit) as unit'))
            ->groupBy('item_code')
            ->get()
            ->keyBy(fn ($row) => $this->cleanCode($row->item_code));

        DB::connection('internal')->transaction(function () use ($validRows, $materialCatalog, $itemCatalog, $productionNames, &$itemsTouched, &$lineCount) {
            foreach ($validRows as $index => $row) {
                $itemCode = $row['item_code'];
                $materialCode = $row['material_code'];
                $lineRole = $row['line_role'] ?: ('DONG-' . ($index + 1));
                $itemCatalogRow = $itemCatalog[$itemCode] ?? null;
                $productionRow = $productionNames[$itemCode] ?? null;
                $materialCatalogRow = $materialCatalog[$materialCode] ?? null;

                $item = InternalWeavingItem::query()->firstOrCreate(
                    ['item_code' => $itemCode],
                    [
                        'item_name' => trim((string) ($itemCatalogRow->item_name ?? $productionRow->description ?? '')),
                        'customer' => trim((string) ($itemCatalogRow->customer ?? $productionRow->customer ?? '')),
                        'unit' => trim((string) ($itemCatalogRow->unit ?? $productionRow->unit ?? '')),
                    ]
                );
                if (trim((string) $item->item_name) === '') {
                    $item->item_name = trim((string) ($itemCatalogRow->item_name ?? $productionRow->description ?? ''));
                    $item->customer = trim((string) ($item->customer ?: ($itemCatalogRow->customer ?? $productionRow->customer ?? '')));
                    $item->unit = trim((string) ($item->unit ?: ($itemCatalogRow->unit ?? $productionRow->unit ?? '')));
                    $item->save();
                }

                InternalWeavingBom::query()->updateOrCreate(
                    ['weaving_item_id' => $item->id, 'material_code' => $materialCode, 'line_role' => $lineRole],
                    [
                        'material_name' => trim((string) ($materialCatalogRow->item_name ?? '')),
                        'unit' => trim((string) ($row['unit'] ?: ($materialCatalogRow->unit ?? ''))),
                        'consumption_per_unit' => $row['consumption_per_unit'],
                        'waste_percent' => $row['waste_percent'],
                        'note' => $row['note'],
                    ]
                );
                $itemsTouched[$itemCode] = true;
                $lineCount++;
            }
        });

        return response()->json([
            'message' => 'Da import dinh muc.',
            'data' => ['item_count' => count($itemsTouched), 'line_count' => $lineCount],
        ]);
    }

    public function importDesignSheet(Request $request)
    {
        $data = $request->validate(['text' => 'required|string']);
        $parsed = $this->parseDesignSheet($data['text']);

        if ($parsed['item_code'] === '' || empty($parsed['lines'])) {
            return response()->json([
                'message' => 'Khong doc duoc phieu lenh det. Can co MA HANG va bang chi co TL/1PCS.',
                'parsed' => $parsed,
            ], 422);
        }

        $missingCatalog = $this->missingCatalogCodes(collect($parsed['lines'])->pluck('material_code')->all());
        if (!empty($missingCatalog)) {
            return response()->json([
                'message' => 'Import dung: ma soi/chi chua co trong DANH MUC noi bo: ' . implode(', ', array_slice($missingCatalog, 0, 20)),
                'missing_catalog' => $missingCatalog,
                'parsed' => $parsed,
            ], 422);
        }

        $result = $this->saveParsedDesignSheet($parsed);

        return response()->json([
            'message' => 'Da import phieu lenh det.',
            'data' => $result,
            'parsed' => $parsed,
        ]);
    }

    public function importDesignWorkbook(Request $request)
    {
        $data = $request->validate([
            'file' => 'required|file|mimes:xlsx,xlsm,xls,ods|max:204800',
            'dry_run' => 'nullable|boolean',
            'skip_missing_catalog' => 'nullable|boolean',
            'max_sheets' => 'nullable|integer|min:1|max:20000',
        ]);

        @set_time_limit(0);
        $path = $data['file']->getRealPath();
        $dryRun = (bool) ($data['dry_run'] ?? false);
        $skipMissingCatalog = (bool) ($data['skip_missing_catalog'] ?? true);
        $maxSheets = (int) ($data['max_sheets'] ?? 20000);

        return $this->importDesignWorkbookFromPath($path, [
            'dry_run' => $dryRun,
            'skip_missing_catalog' => $skipMissingCatalog,
            'max_sheets' => $maxSheets,
            'file_name' => $data['file']->getClientOriginalName(),
            'file_size' => $data['file']->getSize(),
            'mime' => $data['file']->getClientMimeType(),
        ]);
    }

    public function importDesignWorkbookChunk(Request $request)
    {
        $data = $request->validate([
            'upload_id' => 'required|string|max:120',
            'chunk_index' => 'required|integer|min:0|max:100000',
            'total_chunks' => 'required|integer|min:1|max:100000',
            'file_name' => 'required|string|max:255',
            'chunk' => 'required|file|max:6144',
            'skip_missing_catalog' => 'nullable|boolean',
            'dry_run' => 'nullable|boolean',
            'max_sheets' => 'nullable|integer|min:1|max:20000',
        ]);

        @set_time_limit(0);
        $uploadId = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $data['upload_id']);
        if ($uploadId === '') {
            return response()->json(['message' => 'Upload id khong hop le.'], 422);
        }

        $dir = storage_path('app/weaving-imports/' . $uploadId);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $chunkIndex = (int) $data['chunk_index'];
        $totalChunks = (int) $data['total_chunks'];
        $chunkPath = $dir . DIRECTORY_SEPARATOR . sprintf('%06d.part', $chunkIndex);
        $data['chunk']->move($dir, basename($chunkPath));

        if ($chunkIndex + 1 < $totalChunks) {
            return response()->json([
                'message' => 'Da nhan chunk ' . ($chunkIndex + 1) . '/' . $totalChunks,
                'done' => false,
                'received' => $chunkIndex + 1,
                'total_chunks' => $totalChunks,
            ]);
        }

        for ($i = 0; $i < $totalChunks; $i++) {
            if (!is_file($dir . DIRECTORY_SEPARATOR . sprintf('%06d.part', $i))) {
                return response()->json([
                    'message' => 'Thieu chunk ' . ($i + 1) . '/' . $totalChunks . '. Hay import lai file.',
                    'done' => false,
                ], 422);
            }
        }

        $safeName = preg_replace('/[^A-Za-z0-9_\.\-]/', '_', (string) $data['file_name']);
        $assembledPath = $dir . DIRECTORY_SEPARATOR . ($safeName ?: 'workbook.xlsx');
        $out = fopen($assembledPath, 'wb');
        for ($i = 0; $i < $totalChunks; $i++) {
            $partPath = $dir . DIRECTORY_SEPARATOR . sprintf('%06d.part', $i);
            $in = fopen($partPath, 'rb');
            stream_copy_to_stream($in, $out);
            fclose($in);
        }
        fclose($out);

        try {
            $response = $this->importDesignWorkbookFromPath($assembledPath, [
                'dry_run' => (bool) ($data['dry_run'] ?? false),
                'skip_missing_catalog' => (bool) ($data['skip_missing_catalog'] ?? true),
                'max_sheets' => (int) ($data['max_sheets'] ?? 20000),
                'file_name' => $data['file_name'],
                'file_size' => filesize($assembledPath),
                'mime' => 'chunked-upload',
                'chunked' => true,
            ]);
            return $response;
        } finally {
            foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }

    private function importDesignWorkbookFromPath(string $path, array $options)
    {
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $skipMissingCatalog = (bool) ($options['skip_missing_catalog'] ?? true);
        $maxSheets = (int) ($options['max_sheets'] ?? 20000);

        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $sheetNames = array_slice($reader->listWorksheetNames($path), 0, $maxSheets);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Khong doc duoc file Excel. File co the bi loi, qua lon, hoac khong dung dinh dang xlsx/xls.',
                'debug' => [
                    'file_name' => $options['file_name'] ?? basename($path),
                    'file_size' => $options['file_size'] ?? (is_file($path) ? filesize($path) : null),
                    'mime' => $options['mime'] ?? '',
                    'error' => $e->getMessage(),
                ],
            ], 422);
        }

        $summary = [
            'total_sheets' => count($sheetNames),
            'imported' => 0,
            'skipped' => 0,
            'errors' => 0,
            'missing_catalog_count' => 0,
            'dry_run' => $dryRun,
        ];
        $errors = [];
        $samples = [];

        foreach ($sheetNames as $sheetName) {
            try {
                $reader = IOFactory::createReaderForFile($path);
                $reader->setReadDataOnly(true);
                $reader->setLoadSheetsOnly([$sheetName]);
                $spreadsheet = $reader->load($path);
                $sheet = $spreadsheet->getSheet(0);
                $rows = $this->worksheetToRows($sheet);
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);

                $parsed = $this->parseDesignSheetRows($rows, $sheetName);
                if ($parsed['item_code'] === '' || empty($parsed['lines'])) {
                    $summary['skipped']++;
                    $errors[] = ['sheet' => $sheetName, 'message' => 'Khong dung form lenh det hoac thieu MA HANG/BOM.'];
                    continue;
                }

                $missingCatalog = $this->missingCatalogCodes(collect($parsed['lines'])->pluck('material_code')->all());
                if (!empty($missingCatalog)) {
                    $summary['missing_catalog_count'] += count($missingCatalog);
                    if (!$skipMissingCatalog) {
                        $summary['errors']++;
                        $errors[] = ['sheet' => $sheetName, 'message' => 'Thieu ma soi trong danh muc.', 'missing_catalog' => $missingCatalog];
                        continue;
                    }
                    $parsed['warnings'][] = 'Thieu danh muc: ' . implode(', ', array_slice($missingCatalog, 0, 20));
                }

                if (!$dryRun) {
                    $this->saveParsedDesignSheet($parsed, $skipMissingCatalog ? $missingCatalog : []);
                }

                $summary['imported']++;
                if (count($samples) < 10) {
                    $samples[] = [
                        'sheet' => $sheetName,
                        'order_code' => $parsed['order_code'],
                        'item_code' => $parsed['item_code'],
                        'customer' => $parsed['customer'],
                        'line_count' => count($parsed['lines']),
                        'warnings' => $parsed['warnings'],
                    ];
                }
            } catch (\Throwable $e) {
                $summary['errors']++;
                $errors[] = ['sheet' => $sheetName, 'message' => $e->getMessage()];
            }
        }

        return response()->json([
            'message' => $dryRun ? 'Da kiem tra file lenh det.' : 'Da import file lenh det.',
            'summary' => $summary,
            'samples' => $samples,
            'errors' => array_slice($errors, 0, 200),
        ]);
    }

    public function orders(Request $request)
    {
        $keyword = mb_strtoupper(trim((string) $request->query('keyword', '')));
        $status = trim((string) $request->query('status', ''));
        $page = max((int) $request->query('page', 1), 1);
        $perPage = min(max((int) $request->query('per_page', 50), 25), 200);

        $query = InternalWeavingOrder::query()
            ->with('item:id,item_code,item_name')
            ->orderByDesc('order_date')
            ->orderByDesc('id');

        if ($keyword !== '') {
            $query->where(function ($sub) use ($keyword) {
                $sub->whereRaw('UPPER(order_code) LIKE ?', ["%{$keyword}%"])
                    ->orWhereRaw('UPPER(item_code) LIKE ?', ["%{$keyword}%"])
                    ->orWhereRaw('UPPER(customer) LIKE ?', ["%{$keyword}%"]);
            });
        }
        if ($status !== '') {
            $query->where('status', $status);
        }

        $total = (clone $query)->count();
        $rows = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return response()->json([
            'data' => $rows,
            'summary' => [
                'order_count' => InternalWeavingOrder::query()->count(),
                'draft_count' => InternalWeavingOrder::query()->where('status', 'draft')->count(),
                'issued_count' => InternalWeavingOrder::query()->where('status', 'issued')->count(),
            ],
            'pagination' => $this->pagination($page, $perPage, $total),
        ]);
    }

    public function productionOrders(Request $request)
    {
        $keyword = mb_strtoupper(trim((string) $request->query('keyword', '')));
        $status = trim((string) $request->query('status', ''));
        $page = max((int) $request->query('page', 1), 1);
        $perPage = min(max((int) $request->query('per_page', 50), 25), 200);

        $baseQuery = InternalProductionOrder::query()->where('is_active', true);
        if ($keyword !== '') {
            $baseQuery->where(function ($sub) use ($keyword) {
                $like = '%' . $keyword . '%';
                $sub->whereRaw('UPPER(production_order) LIKE ?', [$like])
                    ->orWhereRaw('UPPER(purchase_order) LIKE ?', [$like])
                    ->orWhereRaw('UPPER(customer) LIKE ?', [$like])
                    ->orWhereRaw('UPPER(item_code) LIKE ?', [$like])
                    ->orWhereRaw('UPPER(description) LIKE ?', [$like])
                    ->orWhereRaw('UPPER(size) LIKE ?', [$like])
                    ->orWhereRaw('UPPER(color) LIKE ?', [$like]);
            });
        }
        if ($status !== '') {
            $baseQuery->where('status', $status);
        }

        $groupQuery = (clone $baseQuery)
            ->select(
                'production_order',
                DB::raw('MIN(id) as first_id'),
                DB::raw('MIN(received_date) as first_date'),
                DB::raw('MIN(promised_date) as promised_date'),
                DB::raw('MIN(customer) as customer'),
                DB::raw('MIN(item_code) as first_item_code'),
                DB::raw('MIN(description) as first_description'),
                DB::raw('MIN(unit) as unit'),
                DB::raw('SUM(order_quantity) as planned_quantity'),
                DB::raw('COUNT(*) as line_count'),
                DB::raw('COUNT(DISTINCT item_code) as item_count')
            )
            ->whereNotNull('production_order')
            ->where('production_order', '<>', '')
            ->groupBy('production_order')
            ->orderByDesc(DB::raw('COALESCE(MIN(received_date), MIN(created_at))'))
            ->orderByDesc('first_id');

        $total = DB::connection('internal')
            ->query()
            ->fromSub((clone $groupQuery), 'grouped_orders')
            ->count();

        $rows = $groupQuery
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(function ($row) {
                return [
                    'production_order' => trim((string) $row->production_order),
                    'item_code' => trim((string) $row->first_item_code),
                    'description' => trim((string) $row->first_description),
                    'customer' => trim((string) $row->customer),
                    'planned_quantity' => (float) $row->planned_quantity,
                    'unit' => trim((string) $row->unit),
                    'received_date' => $row->first_date,
                    'promised_date' => $row->promised_date,
                    'line_count' => (int) $row->line_count,
                    'item_count' => (int) $row->item_count,
                ];
            });

        return response()->json([
            'data' => $rows,
            'summary' => [
                'order_count' => InternalProductionOrder::query()->where('is_active', true)->distinct()->count('production_order'),
                'line_count' => InternalProductionOrder::query()->where('is_active', true)->count(),
                'total_quantity' => (float) InternalProductionOrder::query()->where('is_active', true)->sum('order_quantity'),
            ],
            'pagination' => $this->pagination($page, $perPage, $total),
        ]);
    }

    public function storeOrder(Request $request)
    {
        $data = $request->validate([
            'order_code' => 'required|string|max:120',
            'item_code' => 'required|string|max:120',
            'customer' => 'nullable|string|max:200',
            'order_quantity' => 'required|numeric|min:0.001',
            'unit' => 'nullable|string|max:50',
            'order_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'status' => 'nullable|string|max:40',
            'note' => 'nullable|string|max:2000',
        ]);
        $data = $this->normalizeDateFields($data, ['order_date', 'due_date']);

        $itemCode = $this->cleanCode($data['item_code']);
        $item = InternalWeavingItem::query()->firstOrCreate(['item_code' => $itemCode]);

        $order = InternalWeavingOrder::query()->updateOrCreate(
            ['order_code' => $this->cleanCode($data['order_code'])],
            [
                'weaving_item_id' => $item->id,
                'item_code' => $itemCode,
                'customer' => trim((string) ($data['customer'] ?? $item->customer ?? '')),
                'order_quantity' => (float) $data['order_quantity'],
                'unit' => trim((string) ($data['unit'] ?? $item->unit ?? '')),
                'order_date' => $data['order_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'status' => trim((string) ($data['status'] ?? 'draft')) ?: 'draft',
                'note' => trim((string) ($data['note'] ?? '')),
            ]
        );

        return response()->json(['message' => 'Da luu lenh det.', 'data' => $order->load('item')]);
    }

    public function importOrders(Request $request)
    {
        $data = $request->validate(['text' => 'required|string']);
        $rows = $this->parseTsv($data['text']);
        $created = 0;
        $updated = 0;

        DB::connection('internal')->transaction(function () use ($rows, &$created, &$updated) {
            foreach ($rows as $row) {
                $orderCode = $this->cleanCode($row[0] ?? '');
                $itemCode = $this->cleanCode($row[1] ?? '');
                if ($orderCode === '' || $itemCode === '') continue;
                $item = InternalWeavingItem::query()->firstOrCreate(
                    ['item_code' => $itemCode],
                    ['item_name' => trim((string) ($row[2] ?? ''))]
                );
                $order = InternalWeavingOrder::query()->updateOrCreate(
                    ['order_code' => $orderCode],
                    [
                        'weaving_item_id' => $item->id,
                        'item_code' => $itemCode,
                        'customer' => trim((string) ($row[3] ?? '')),
                        'order_quantity' => $this->toNumber($row[4] ?? 0),
                        'unit' => trim((string) ($row[5] ?? '')),
                        'order_date' => $this->dateOrNull($row[6] ?? ''),
                        'due_date' => $this->dateOrNull($row[7] ?? ''),
                        'status' => trim((string) ($row[8] ?? 'draft')) ?: 'draft',
                        'note' => trim((string) ($row[9] ?? '')),
                    ]
                );
                $order->wasRecentlyCreated ? $created++ : $updated++;
            }
        });

        return response()->json(['message' => 'Da import lenh det.', 'data' => compact('created', 'updated')]);
    }

    public function plan(Request $request, InternalWeavingOrder $order)
    {
        $order->load('item.boms');
        $bomRows = $order->item ? $order->item->boms : collect();
        $orderMetadata = json_decode((string) ($order->metadata_json ?? ''), true) ?: [];
        $itemMetadata = json_decode((string) ($order->item->metadata_json ?? ''), true) ?: [];
        $itemCatalog = InternalItemCatalog::query()
            ->whereRaw('UPPER(TRIM(item_code)) = ?', [$this->cleanCode($order->item_code)])
            ->where('is_active', true)
            ->first();
        $materialCodes = $bomRows->pluck('material_code')->map(fn ($code) => $this->cleanCode($code))->filter()->unique()->values();
        $stock = $this->stockByMaterial($materialCodes->all());
        $catalog = InternalItemCatalog::query()
            ->whereIn('item_code', $materialCodes->all())
            ->get()
            ->keyBy(fn ($row) => $this->cleanCode($row->item_code));
        $unitConverter = app(InternalUnitConverter::class);

        $lines = $bomRows->map(function (InternalWeavingBom $bom) use ($order, $stock, $catalog, $unitConverter) {
            $code = $this->cleanCode($bom->material_code);
            $bomMetadata = json_decode((string) ($bom->metadata_json ?? ''), true) ?: [];
            $requiredRaw = round((float) $order->order_quantity * (float) $bom->consumption_per_unit * (1 + ((float) $bom->waste_percent / 100)), 3);
            $stockRow = $stock[$code] ?? ['quantity' => 0, 'locations' => collect()];
            $locations = collect($stockRow['locations'])->values();
            $catalogRow = $catalog[$code] ?? null;
            $base = $unitConverter->toBase($code, $requiredRaw, $bom->unit ?? '', $catalogRow->unit ?? ($bom->unit ?? ''));
            $required = round((float) $base['quantity'], 3);
            $stockQuantity = (float) $stockRow['quantity'];
            if ($locations->isEmpty() && $catalogRow && trim((string) $catalogRow->shelf_code) !== '') {
                $stockQuantity = max($stockQuantity, (float) $catalogRow->opening_quantity);
                $locations = collect([[
                    'location_code' => $catalogRow->shelf_code,
                    'quantity' => (float) $catalogRow->opening_quantity,
                    'color' => $catalogRow->color ?? '',
                    'pantone_hex' => $catalogRow->pantone_hex ?? '',
                ]]);
            }

            return [
                'material_code' => $code,
                'material_name' => $catalogRow->item_name ?? $bom->material_name ?? '',
                'unit' => $base['unit'] ?: ($catalogRow->unit ?? $bom->unit ?? ''),
                'bom_unit' => $bom->unit ?? '',
                'required_quantity_raw' => $requiredRaw,
                'converted' => (bool) $base['converted'],
                'conversion_factor' => (float) $base['factor'],
                'catalog_exists' => (bool) $catalogRow,
                'catalog_name' => $catalogRow->item_name ?? '',
                'catalog_unit' => $catalogRow->unit ?? '',
                'catalog_shelf_code' => $catalogRow->shelf_code ?? '',
                'type' => $bomMetadata['type'] ?? '',
                'shelf_hint' => $bomMetadata['shelf_hint'] ?? '',
                'total_grams' => (float) ($bomMetadata['total_grams'] ?? 0),
                'consumption_per_unit' => (float) $bom->consumption_per_unit,
                'waste_percent' => (float) $bom->waste_percent,
                'required_quantity' => $required,
                'stock_quantity' => $stockQuantity,
                'shortage_quantity' => max(0, $required - $stockQuantity),
                'locations' => $locations,
                'first_location' => $locations->first()['location_code'] ?? '',
                'status' => $stockQuantity >= $required ? 'enough' : 'short',
                'note' => $bom->note,
            ];
        })->values();

        return response()->json([
            'order' => [
                'production_order' => $order->order_code,
                'order_code' => $order->order_code,
                'customer' => $order->customer,
                'item_code' => $order->item_code,
                'line_count' => 1,
                'item_count' => 1,
                'planned_quantity' => (float) $order->order_quantity,
                'unit' => $order->unit,
                'po_number' => $order->po_number,
                'design_code' => $order->design_code,
                'order_date' => optional($order->order_date)->format('Y-m-d'),
                'due_date' => optional($order->due_date)->format('Y-m-d'),
                'note' => $order->note,
                'image_url' => $itemCatalog->image_url ?? '',
                'metadata' => array_merge($itemMetadata, $orderMetadata),
            ],
            'source_items' => [[
                'item_code' => $order->item_code,
                'item_name' => $order->item->item_name ?? '',
                'image_url' => $itemCatalog->image_url ?? '',
                'design_code' => $order->design_code,
                'po_number' => $order->po_number,
                'metadata' => $itemMetadata,
                'customer' => $order->customer,
                'order_quantity' => (float) $order->order_quantity,
                'unit' => $order->unit,
                'has_bom' => $bomRows->isNotEmpty(),
                'material_count' => $lines->count(),
                'required_quantity' => (float) $lines->sum('required_quantity'),
                'materials' => $lines->values(),
            ]],
            'data' => $lines,
            'summary' => [
                'line_count' => $lines->count(),
                'required_quantity' => (float) $lines->sum('required_quantity'),
                'short_count' => $lines->where('status', 'short')->count(),
                'missing_catalog_count' => $lines->where('catalog_exists', false)->count(),
                'source_item_count' => 1,
                'missing_bom_count' => $bomRows->isEmpty() ? 1 : 0,
                'missing_bom_items' => $bomRows->isEmpty() ? [$order->item_code] : [],
            ],
        ]);
    }

    public function productionPlan(Request $request)
    {
        $productionOrder = trim((string) $request->query('production_order', ''));
        if ($productionOrder === '') {
            return response()->json(['message' => 'Thiếu lệnh sản xuất.'], 422);
        }

        $sourceLines = InternalProductionOrder::query()
            ->where('is_active', true)
            ->where('production_order', $productionOrder)
            ->orderBy('id')
            ->get();

        if ($sourceLines->isEmpty()) {
            $internalOrder = InternalWeavingOrder::query()
                ->where('order_code', $this->cleanCode($productionOrder))
                ->first();
            if ($internalOrder) {
                return $this->plan($request, $internalOrder);
            }

            return response()->json(['message' => 'Không tìm thấy lệnh sản xuất: ' . $productionOrder], 404);
        }

        $itemCodes = $sourceLines->pluck('item_code')->map(fn ($code) => $this->cleanCode($code))->filter()->unique()->values();
        $items = InternalWeavingItem::query()
            ->with('boms')
            ->whereIn('item_code', $itemCodes->all())
            ->get()
            ->keyBy(fn ($item) => $this->cleanCode($item->item_code));
        $sourceCatalogs = InternalItemCatalog::query()
            ->whereIn('item_code', $itemCodes->all())
            ->where('is_active', true)
            ->get()
            ->keyBy(fn ($row) => $this->cleanCode($row->item_code));

        $materialRequirements = [];
        $missingBomItems = [];
        $sourceItemBreakdown = [];

        foreach ($sourceLines as $sourceLine) {
            $itemCode = $this->cleanCode($sourceLine->item_code);
            $item = $items[$itemCode] ?? null;
            $sourceCatalog = $sourceCatalogs[$itemCode] ?? null;
            $sourceItem = [
                'item_code' => $itemCode,
                'item_name' => trim((string) $sourceLine->description),
                'image_url' => $sourceCatalog->image_url ?? '',
                'customer' => trim((string) $sourceLine->customer),
                'size' => trim((string) $sourceLine->size),
                'color' => trim((string) $sourceLine->color),
                'order_quantity' => (float) $sourceLine->order_quantity,
                'unit' => trim((string) $sourceLine->unit),
                'has_bom' => (bool) ($item && $item->boms->isNotEmpty()),
                'materials' => [],
            ];

            if (!$item || $item->boms->isEmpty()) {
                $missingBomItems[$itemCode ?: 'CHUA-CO-MA-HANG'] = true;
                $sourceItemBreakdown[] = $sourceItem;
                continue;
            }

            foreach ($item->boms as $bom) {
                $materialCode = $this->cleanCode($bom->material_code);
                if ($materialCode === '') continue;
                $required = round((float) $sourceLine->order_quantity * (float) $bom->consumption_per_unit * (1 + ((float) $bom->waste_percent / 100)), 3);
                $sourceItem['materials'][] = [
                    'material_code' => $materialCode,
                    'line_role' => trim((string) $bom->line_role),
                    'material_name' => trim((string) $bom->material_name),
                    'unit' => trim((string) $bom->unit),
                    'consumption_per_unit' => (float) $bom->consumption_per_unit,
                    'waste_percent' => (float) $bom->waste_percent,
                    'required_quantity' => $required,
                    'note' => trim((string) $bom->note),
                ];
                if (!isset($materialRequirements[$materialCode])) {
                    $materialRequirements[$materialCode] = [
                        'material_code' => $materialCode,
                        'material_name' => $bom->material_name,
                        'unit' => $bom->unit,
                        'required_quantity' => 0,
                        'source_items' => [],
                    ];
                }
                $materialRequirements[$materialCode]['required_quantity'] += $required;
                $materialRequirements[$materialCode]['source_items'][] = [
                    'item_code' => $itemCode,
                    'description' => trim((string) $sourceLine->description),
                    'quantity' => (float) $sourceLine->order_quantity,
                    'required_quantity' => $required,
                ];
            }

            $sourceItemBreakdown[] = $sourceItem;
        }

        $materialCodes = array_keys($materialRequirements);
        $stock = $this->stockByMaterial($materialCodes);
        $catalog = $this->catalogByCodes($materialCodes);
        $unitConverter = app(InternalUnitConverter::class);

        $sourceItems = collect($sourceItemBreakdown)->map(function ($sourceItem) use ($catalog, $unitConverter) {
            $materials = collect($sourceItem['materials'])->map(function ($material) use ($catalog, $unitConverter) {
                $code = $this->cleanCode($material['material_code'] ?? '');
                $catalogRow = $catalog[$code] ?? null;
                $base = $unitConverter->toBase(
                    $code,
                    (float) ($material['required_quantity'] ?? 0),
                    $material['unit'] ?? '',
                    $catalogRow->unit ?? ($material['unit'] ?? '')
                );

                return array_merge($material, [
                    'material_name' => $catalogRow->item_name ?? $material['material_name'] ?? '',
                    'unit' => $base['unit'] ?: ($catalogRow->unit ?? $material['unit'] ?? ''),
                    'bom_unit' => $material['unit'] ?? '',
                    'required_quantity_raw' => (float) ($material['required_quantity'] ?? 0),
                    'required_quantity' => (float) $base['quantity'],
                    'converted' => (bool) $base['converted'],
                    'conversion_factor' => (float) $base['factor'],
                    'catalog_exists' => (bool) $catalogRow,
                    'catalog_shelf_code' => $catalogRow->shelf_code ?? '',
                    'catalog_color' => $catalogRow->color ?? '',
                    'pantone_hex' => $catalogRow->pantone_hex ?? '',
                ]);
            })->values();

            $sourceItem['materials'] = $materials;
            $sourceItem['material_count'] = $materials->count();
            $sourceItem['required_quantity'] = (float) $materials->sum('required_quantity');

            return $sourceItem;
        })->values();

        $lines = collect($materialRequirements)->map(function ($row) use ($stock, $catalog, $unitConverter) {
            $code = $row['material_code'];
            $catalogRow = $catalog[$code] ?? null;
            $stockRow = $stock[$code] ?? ['quantity' => 0, 'locations' => collect()];
            $stockQuantity = (float) $stockRow['quantity'];
            $locations = collect($stockRow['locations'])->values();

            if ($locations->isEmpty() && $catalogRow && trim((string) $catalogRow->shelf_code) !== '') {
                $stockQuantity = max($stockQuantity, (float) $catalogRow->opening_quantity);
                $locations = collect([[
                    'location_code' => $catalogRow->shelf_code,
                    'quantity' => (float) $catalogRow->opening_quantity,
                    'color' => $catalogRow->color ?? '',
                    'pantone_hex' => $catalogRow->pantone_hex ?? '',
                ]]);
            }

            $base = $unitConverter->toBase(
                $code,
                (float) $row['required_quantity'],
                $row['unit'] ?? '',
                $catalogRow->unit ?? ($row['unit'] ?? '')
            );
            $required = round((float) $base['quantity'], 3);

            return [
                'material_code' => $code,
                'material_name' => $catalogRow->item_name ?? $row['material_name'] ?? '',
                'unit' => $base['unit'] ?: ($catalogRow->unit ?? $row['unit'] ?? ''),
                'bom_unit' => $row['unit'] ?? '',
                'required_quantity_raw' => round((float) $row['required_quantity'], 3),
                'converted' => (bool) $base['converted'],
                'conversion_factor' => (float) $base['factor'],
                'catalog_exists' => (bool) $catalogRow,
                'catalog_name' => $catalogRow->item_name ?? '',
                'catalog_unit' => $catalogRow->unit ?? '',
                'catalog_shelf_code' => $catalogRow->shelf_code ?? '',
                'required_quantity' => $required,
                'stock_quantity' => $stockQuantity,
                'shortage_quantity' => max(0, $required - $stockQuantity),
                'locations' => $locations,
                'first_location' => $locations->first()['location_code'] ?? '',
                'status' => $stockQuantity >= $required ? 'enough' : 'short',
                'source_items' => collect($row['source_items'])->take(5)->values(),
            ];
        })->sortBy('material_code')->values();

        return response()->json([
            'order' => [
                'production_order' => $productionOrder,
                'customer' => trim((string) $sourceLines->pluck('customer')->filter()->first()),
                'line_count' => $sourceLines->count(),
                'item_count' => $itemCodes->count(),
                'planned_quantity' => (float) $sourceLines->sum('order_quantity'),
            ],
            'source_items' => $sourceItems,
            'data' => $lines,
            'summary' => [
                'line_count' => $lines->count(),
                'required_quantity' => (float) $lines->sum('required_quantity'),
                'short_count' => $lines->where('status', 'short')->count(),
                'missing_catalog_count' => $lines->where('catalog_exists', false)->count(),
                'source_item_count' => $sourceItems->count(),
                'missing_bom_count' => count($missingBomItems),
                'missing_bom_items' => array_keys($missingBomItems),
            ],
        ]);
    }

    public function createIssue(Request $request, InternalWeavingOrder $order)
    {
        $planResponse = $this->plan($request, $order)->getData(true);
        $missingCatalog = collect($planResponse['data'] ?? [])
            ->filter(fn ($line) => empty($line['catalog_exists']))
            ->pluck('material_code')
            ->values()
            ->all();
        if (!empty($missingCatalog)) {
            return response()->json([
                'message' => 'Không thể tạo phiếu xuất. Mã sợi chưa có trong DANH MỤC nội bộ: ' . implode(', ', array_slice($missingCatalog, 0, 20)),
                'missing_catalog' => $missingCatalog,
            ], 422);
        }

        $lines = collect($planResponse['data'] ?? [])
            ->filter(fn ($line) => (float) ($line['required_quantity'] ?? 0) > 0)
            ->map(function ($line) use ($order) {
                return [
                    'production_order' => $order->order_code,
                    'ma_hh' => $line['material_code'],
                    'internal_item_code' => $line['material_code'],
                    'ten_hh' => $line['material_name'] ?? '',
                    'dvt' => $line['unit'] ?? '',
                    'quantity' => $line['required_quantity'],
                    'location_code' => $line['first_location'] ?? '',
                    'note' => 'Xuat soi theo lenh det ' . $order->order_code,
                ];
            })
            ->values();

        if ($lines->isEmpty()) {
            return response()->json(['message' => 'Lenh det chua co dinh muc soi de xuat.'], 422);
        }

        $payload = [
            'issue_type' => 'material',
            'issue_date' => $request->input('issue_date', now('Asia/Ho_Chi_Minh')->format('Y-m-d')),
            'receiver_name' => $request->input('receiver_name', 'San xuat det'),
            'department' => $request->input('department', 'San xuat'),
            'production_order' => $order->order_code,
            'purpose' => 'Xuat soi cho lenh det',
            'note' => trim((string) $request->input('note', 'Tao tu module Lenh det')),
            'lines' => $lines->all(),
        ];

        $issueRequest = Request::create('/api/xuat-vat-tu-noi-bo', 'POST', $payload);
        $issueRequest->headers->set('Accept', 'application/json');
        $response = app(InternalMaterialIssueController::class)->store($issueRequest);

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $order->status = 'issued';
            $order->save();
        }

        return $response;
    }

    public function createProductionIssue(Request $request)
    {
        $data = $request->validate([
            'production_order' => 'required|string|max:120',
            'issue_date' => 'nullable|date',
            'receiver_name' => 'nullable|string|max:150',
            'department' => 'nullable|string|max:150',
            'note' => 'nullable|string|max:1000',
        ]);
        $data = $this->normalizeDateFields($data, ['issue_date']);

        $planRequest = Request::create('/api/lenh-det/production-order-plan', 'GET', [
            'production_order' => $data['production_order'],
        ]);
        $planResponse = $this->productionPlan($planRequest)->getData(true);

        $missingCatalog = collect($planResponse['data'] ?? [])
            ->filter(fn ($line) => empty($line['catalog_exists']))
            ->pluck('material_code')
            ->values()
            ->all();
        if (!empty($missingCatalog)) {
            return response()->json([
                'message' => 'Không thể tạo phiếu xuất. Mã sợi chưa có trong DANH MỤC nội bộ: ' . implode(', ', array_slice($missingCatalog, 0, 20)),
                'missing_catalog' => $missingCatalog,
            ], 422);
        }

        $lines = collect($planResponse['data'] ?? [])
            ->filter(fn ($line) => (float) ($line['required_quantity'] ?? 0) > 0)
            ->map(function ($line) use ($data) {
                return [
                    'production_order' => trim((string) $data['production_order']),
                    'ma_hh' => $line['material_code'],
                    'internal_item_code' => $line['material_code'],
                    'ten_hh' => $line['material_name'] ?? '',
                    'dvt' => $line['unit'] ?? '',
                    'quantity' => $line['required_quantity'],
                    'location_code' => $line['first_location'] ?? '',
                    'note' => 'Xuat soi theo lenh SX ' . trim((string) $data['production_order']),
                ];
            })
            ->values();

        if ($lines->isEmpty()) {
            return response()->json(['message' => 'Lệnh sản xuất chưa có định mức sợi để xuất.'], 422);
        }

        $payload = [
            'issue_type' => 'material',
            'issue_date' => $data['issue_date'] ?? now('Asia/Ho_Chi_Minh')->format('Y-m-d'),
            'receiver_name' => trim((string) ($data['receiver_name'] ?? 'San xuat det')),
            'department' => trim((string) ($data['department'] ?? 'San xuat')),
            'production_order' => trim((string) $data['production_order']),
            'purpose' => 'Xuat soi cho lenh san xuat',
            'note' => trim((string) ($data['note'] ?? 'Tao tu module Lenh det')),
            'lines' => $lines->all(),
        ];

        $issueRequest = Request::create('/api/xuat-vat-tu-noi-bo', 'POST', $payload);
        $issueRequest->headers->set('Accept', 'application/json');

        return app(InternalMaterialIssueController::class)->store($issueRequest);
    }

    private function stockByMaterial(array $materialCodes): array
    {
        if (empty($materialCodes)) return [];

        $rows = InventoryPackage::query()
            ->with('location:id,location_code')
            ->where('quantity', '>', 0)
            ->where(function ($query) use ($materialCodes) {
                $query->whereIn(DB::raw('UPPER(TRIM(internal_item_code))'), $materialCodes)
                    ->orWhereIn(DB::raw('UPPER(TRIM(ma_sp))'), $materialCodes);
            })
            ->get();

        return $rows
            ->groupBy(fn ($row) => $this->cleanCode($row->internal_item_code ?: $row->ma_sp))
            ->map(function ($items) {
                return [
                    'quantity' => (float) $items->sum('quantity'),
                    'locations' => $items
                        ->groupBy(fn ($row) => optional($row->location)->location_code ?: $row->location_code ?: '')
                        ->map(function ($locationRows, $locationCode) {
                            return [
                                'location_code' => $locationCode ?: 'CHUA-XEP',
                                'quantity' => (float) $locationRows->sum('quantity'),
                                'color' => trim((string) $locationRows->pluck('color')->filter()->first()),
                                'pantone_hex' => '',
                            ];
                        })
                        ->sortByDesc('quantity')
                        ->values(),
                ];
            })
            ->all();
    }

    private function catalogByCodes(array $codes)
    {
        $cleanCodes = collect($codes)->map(fn ($code) => $this->cleanCode($code))->filter()->unique()->values();
        if ($cleanCodes->isEmpty()) {
            return collect();
        }

        return InternalItemCatalog::query()
            ->whereIn('item_code', $cleanCodes->all())
            ->get()
            ->keyBy(fn ($row) => $this->cleanCode($row->item_code));
    }

    private function missingCatalogCodes(array $codes): array
    {
        $cleanCodes = collect($codes)->map(fn ($code) => $this->cleanCode($code))->filter()->unique()->values();
        if ($cleanCodes->isEmpty()) {
            return [];
        }

        $existing = $this->catalogByCodes($cleanCodes->all())->keys();
        return $cleanCodes->diff($existing)->values()->all();
    }

    private function decorateBomRow(InternalWeavingBom $row, $catalog): array
    {
        $code = $this->cleanCode($row->material_code);
        $catalogRow = $catalog[$code] ?? null;
        return array_merge($row->toArray(), [
            'catalog_exists' => (bool) $catalogRow,
            'catalog_name' => $catalogRow->item_name ?? '',
            'catalog_unit' => $catalogRow->unit ?? '',
            'catalog_shelf_code' => $catalogRow->shelf_code ?? '',
            'catalog_opening_quantity' => (float) ($catalogRow->opening_quantity ?? 0),
        ]);
    }

    private function normalizeBomImportRow(array $row): array
    {
        $oldConsumption = trim((string) ($row[4] ?? ''));
        if ($oldConsumption !== '' && is_numeric(str_replace(',', '.', preg_replace('/[^\d,\.\-]/', '', $oldConsumption)))) {
            return [
                'item_code' => $this->cleanCode($row[0] ?? ''),
                'material_code' => $this->cleanCode($row[2] ?? ''),
                'line_role' => '',
                'consumption_per_unit' => $this->toNumber($row[4] ?? 0),
                'unit' => trim((string) ($row[5] ?? '')),
                'waste_percent' => $this->toNumber($row[6] ?? 0),
                'note' => trim((string) ($row[7] ?? '')),
            ];
        }

        $roleConsumption = trim((string) ($row[3] ?? ''));
        if ($roleConsumption !== '' && is_numeric(str_replace(',', '.', preg_replace('/[^\d,\.\-]/', '', $roleConsumption)))) {
            return [
                'item_code' => $this->cleanCode($row[0] ?? ''),
                'material_code' => $this->cleanCode($row[1] ?? ''),
                'line_role' => $this->cleanCode($row[2] ?? ''),
                'consumption_per_unit' => $this->toNumber($row[3] ?? 0),
                'unit' => trim((string) ($row[4] ?? '')),
                'waste_percent' => $this->toNumber($row[5] ?? 0),
                'note' => trim((string) ($row[6] ?? '')),
            ];
        }

        return [
            'item_code' => $this->cleanCode($row[0] ?? ''),
            'material_code' => $this->cleanCode($row[1] ?? ''),
            'line_role' => '',
            'consumption_per_unit' => $this->toNumber($row[2] ?? 0),
            'unit' => trim((string) ($row[3] ?? '')),
            'waste_percent' => $this->toNumber($row[4] ?? 0),
            'note' => trim((string) ($row[5] ?? '')),
        ];
    }

    private function saveParsedDesignSheet(array $parsed, array $missingCatalog = []): array
    {
        return DB::connection('internal')->transaction(function () use ($parsed, $missingCatalog) {
            $metadata = $parsed['metadata'] ?? [];
            $item = InternalWeavingItem::query()->updateOrCreate(
                ['item_code' => $parsed['item_code']],
                [
                    'item_name' => $parsed['design_code'] ?: ($metadata['label_name'] ?? $parsed['item_code']),
                    'design_code' => $parsed['design_code'] ?: null,
                    'customer' => $parsed['customer'],
                    'unit' => 'PCS',
                    'note' => trim(implode(' | ', array_filter([
                        $parsed['po'] ? 'PO: ' . $parsed['po'] : '',
                        $parsed['job_date'] ? 'Ngay phieu: ' . $parsed['job_date'] : '',
                        'Import tu phieu lenh det',
                    ]))),
                    'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                ]
            );

            $skipCodes = collect($missingCatalog)->map(fn ($code) => $this->cleanCode($code))->flip();
            $item->boms()->delete();
            foreach ($parsed['lines'] as $index => $line) {
                if ($skipCodes->has($line['material_code'])) {
                    continue;
                }
                $item->boms()->create([
                    'material_code' => $line['material_code'],
                    'line_role' => $line['line_role'] ?: ('DONG-' . ($index + 1)),
                    'material_name' => $line['material_name'],
                    'unit' => 'gam',
                    'consumption_per_unit' => $line['consumption_per_unit'],
                    'waste_percent' => 0,
                    'note' => trim(implode(' | ', array_filter([
                        $line['type'] ? 'Loai: ' . $line['type'] : '',
                        $line['shelf_hint'] ? 'Ke tren phieu: ' . $line['shelf_hint'] : '',
                        $line['total_grams'] ? 'TL phieu: ' . $line['total_grams'] . 'g' : '',
                    ]))),
                    'metadata_json' => json_encode($line, JSON_UNESCAPED_UNICODE),
                ]);
            }

            $order = null;
            if ($parsed['order_code'] !== '') {
                $order = InternalWeavingOrder::query()->updateOrCreate(
                    ['order_code' => $parsed['order_code']],
                    [
                        'weaving_item_id' => $item->id,
                        'item_code' => $parsed['item_code'],
                        'customer' => $parsed['customer'],
                        'po_number' => $parsed['po'] ?: null,
                        'design_code' => $parsed['design_code'] ?: null,
                        'order_quantity' => $parsed['order_quantity'],
                        'unit' => 'PCS',
                        'order_date' => $parsed['job_date'] ?: now('Asia/Ho_Chi_Minh')->format('Y-m-d'),
                        'due_date' => $parsed['delivery_date'] ?: null,
                        'status' => 'draft',
                        'note' => trim(implode(' | ', array_filter([
                            $parsed['po'] ? 'PO: ' . $parsed['po'] : '',
                            $parsed['design_code'] ? 'Design: ' . $parsed['design_code'] : '',
                            'Import tu phieu lenh det',
                        ]))),
                        'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                    ]
                );
            }

            return [
                'item' => $item->fresh('boms'),
                'order' => $order,
                'line_count' => $item->boms()->count(),
            ];
        });
    }

    private function parseDesignSheet(string $text): array
    {
        return $this->parseDesignSheetRows($this->parseTsv($text));
    }

    private function parseDesignSheetRows(array $rows, string $sheetName = ''): array
    {
        $customer = $this->findValueAfterLabel($rows, ['KHACH HANG', 'KHÁCH HÀNG']);
        $orderCode = $this->findValueAfterLabel($rows, ['LENH IN', 'LỆNH IN']);
        $itemCode = $this->cleanCode($this->findValueAfterLabel($rows, ['MA HANG', 'MÃ HÀNG']));
        $po = $this->findValueAfterLabel($rows, ['PO']);
        $designCode = $this->findValueAfterLabel($rows, ['MA SO DESIGN', 'MÃ SỐ DESIGN']);
        $deliveryDate = $this->dateOrNull($this->findValueAfterLabel($rows, ['NGAY GIAO', 'NGÀY GIAO']));
        $jobDate = null;
        $metadata = $this->parseDesignSheetMetadata($rows, $sheetName);

        foreach ($rows as $row) {
            if ($jobDate) break;
            foreach ($row as $cell) {
                $date = $this->dateTokenOrNull($cell);
                if ($date) {
                    $jobDate = $date;
                    break;
                }
            }
        }

        $lines = [];
        $inThreadTable = false;
        foreach ($rows as $row) {
            $lineText = $this->normalizeText(implode(' ', $row));
            if (str_contains($lineText, 'MA SO CHI') && str_contains($lineText, 'TL/1PCS')) {
                $inThreadTable = true;
                continue;
            }
            if (!$inThreadTable) {
                continue;
            }
            if (str_contains($lineText, 'SO PICK') || str_contains($lineText, 'TEN FILE') || str_contains($lineText, 'SIZE')) {
                break;
            }

            $line = $this->parseDesignSheetThreadLine($row);
            if ($line) {
                $lines[] = $line;
            }
        }

        return [
            'customer' => trim((string) $customer),
            'order_code' => $this->cleanCode($orderCode),
            'item_code' => $itemCode,
            'po' => trim((string) $po),
            'design_code' => trim((string) $designCode),
            'job_date' => $jobDate,
            'delivery_date' => $deliveryDate,
            'order_quantity' => $this->parseDesignSheetQuantity($rows, $lines),
            'lines' => $lines,
            'metadata' => $metadata,
            'warnings' => [],
        ];
    }

    private function worksheetToRows($sheet): array
    {
        $highestRow = min((int) $sheet->getHighestDataRow(), 80);
        $highestColumnIndex = min(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn()), 40);
        $rows = [];
        for ($row = 1; $row <= $highestRow; $row++) {
            $values = [];
            $hasValue = false;
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $value = $sheet->getCellByColumnAndRow($col, $row)->getFormattedValue();
                $value = trim((string) $value);
                $values[] = $value;
                if ($value !== '') {
                    $hasValue = true;
                }
            }
            if ($hasValue) {
                $rows[] = $values;
            }
        }
        return $rows;
    }

    private function parseDesignSheetMetadata(array $rows, string $sheetName = ''): array
    {
        $operationLabels = ['TEN LABEL', 'UI KEO', 'LOOP', 'PHAN TREN', 'PHẦN TRÊN', 'PHAN DUOI', 'PHẦN DƯỚI', 'CHIEU DAI', 'CHIỀU DÀI', 'HOAN CHINH', 'HOÀN CHỈNH', 'MA SO HOP', 'MÃ SỐ HỘP'];
        $operations = [];
        foreach ($rows as $row) {
            $label = $this->normalizeText($row[0] ?? '');
            if (in_array($label, array_map(fn ($x) => $this->normalizeText($x), $operationLabels), true)) {
                $operations[$label] = trim(implode(' ', array_filter(array_slice($row, 1), fn ($cell) => trim((string) $cell) !== '')));
            }
        }

        return [
            'sheet_name' => $sheetName,
            'label_name' => $this->findValueAfterLabel($rows, ['TEN LABEL']),
            'length' => $this->findValueAfterLabel($rows, ['CHIEU DAI', 'CHIỀU DÀI']),
            'finished_size' => $this->findValueAfterLabel($rows, ['HOAN CHINH', 'HOÀN CHỈNH']),
            'box_code' => $this->findValueAfterLabel($rows, ['MA SO HOP', 'MÃ SỐ HỘP']),
            'pick' => $this->extractValueNearLabel($rows, ['SO PICK', 'SỐ PICK']),
            'density' => $this->extractValueNearLabel($rows, ['MAT DO', 'MẬT ĐỘ']),
            'machine' => $this->extractValueNearLabel($rows, ['MAY', 'MÁY']),
            'roll_count' => $this->extractValueNearLabel($rows, ['SO CUON', 'SỐ CUỘN']),
            'quantity_plus_10' => $this->extractValueNearLabel($rows, ['SO LUONG +10%', 'SỐ LƯỢNG +10%']),
            'row_count_plus_10' => $this->extractValueNearLabel($rows, ['SO DONG +10%', 'SỐ DÒNG +10%']),
            'shift' => $this->extractValueNearLabel($rows, ['CA']),
            'operations' => $operations,
            'production_capacity_rows' => $this->parseCapacityRows($rows),
        ];
    }

    private function parseDesignSheetThreadLine(array $row): ?array
    {
        $tokens = collect($row)
            ->map(fn ($cell) => trim((string) $cell))
            ->filter(fn ($cell) => $cell !== '')
            ->values()
            ->all();

        if (count($tokens) < 5) {
            return null;
        }

        $lineNoIndex = null;
        foreach ($tokens as $index => $token) {
            if (preg_match('/^\d{1,2}$/', $token)) {
                $lineNoIndex = $index;
                break;
            }
        }
        if ($lineNoIndex === null || !isset($tokens[$lineNoIndex + 2])) {
            return null;
        }

        $type = trim((string) ($tokens[$lineNoIndex + 1] ?? ''));
        $materialCode = $this->cleanCode($tokens[$lineNoIndex + 2] ?? '');
        if ($materialCode === '') {
            return null;
        }

        $numericAfter = [];
        for ($i = $lineNoIndex + 3; $i < count($tokens); $i++) {
            if ($this->isPlainNumberToken($tokens[$i])) {
                $numericAfter[] = ['index' => $i, 'value' => $this->toNumber($tokens[$i])];
            }
        }
        if (empty($numericAfter)) {
            return null;
        }

        $consumptionIndex = $numericAfter[0]['index'];
        $consumption = (float) $numericAfter[0]['value'];
        $totalGrams = isset($numericAfter[1]) ? (float) $numericAfter[1]['value'] : 0;
        if ($consumption <= 0) {
            return null;
        }

        $middle = array_slice($tokens, $lineNoIndex + 3, max(0, $consumptionIndex - ($lineNoIndex + 3)));
        $shelfHint = '';
        $materialName = '';
        if (count($middle) >= 2) {
            $shelfHint = $middle[0];
            $materialName = trim(implode(' ', array_slice($middle, 1)));
        } elseif (count($middle) === 1) {
            $materialName = $middle[0];
        }

        $role = trim(implode(' - ', array_slice($tokens, 0, $lineNoIndex)));
        if ($role === '') {
            $role = 'DONG-' . ($tokens[$lineNoIndex] ?? '');
        }

        return [
            'line_role' => $this->cleanCode($role),
            'type' => $type,
            'material_code' => $materialCode,
            'shelf_hint' => trim((string) $shelfHint),
            'material_name' => trim((string) ($materialName ?: $materialCode)),
            'consumption_per_unit' => $consumption,
            'total_grams' => $totalGrams,
        ];
    }

    private function parseDesignSheetQuantity(array $rows, array $lines): float
    {
        foreach ($rows as $rowIndex => $row) {
            $text = $this->normalizeText(implode(' ', $row));
            if (!str_contains($text, 'SO LUONG')) {
                continue;
            }
            for ($i = $rowIndex + 1; $i < min($rowIndex + 8, count($rows)); $i++) {
                foreach ($rows[$i] as $cell) {
                    if ($this->isPlainNumberToken($cell)) {
                        $value = $this->toNumber($cell);
                        if ($value > 0) {
                            return $value;
                        }
                    }
                }
            }
        }

        foreach ($lines as $line) {
            if (($line['consumption_per_unit'] ?? 0) > 0 && ($line['total_grams'] ?? 0) > 0) {
                return round((float) $line['total_grams'] / (float) $line['consumption_per_unit'], 3);
            }
        }

        return 0;
    }

    private function findValueAfterLabel(array $rows, array $labels): string
    {
        $normalizedLabels = array_map(fn ($label) => $this->normalizeText($label), $labels);
        foreach ($rows as $row) {
            foreach ($row as $index => $cell) {
                $normalized = $this->normalizeText($cell);
                foreach ($normalizedLabels as $label) {
                    if ($normalized === $label || str_starts_with($normalized, $label . ':')) {
                        $inline = trim((string) preg_replace('/^.*?:/u', '', (string) $cell));
                        if ($inline !== '' && $this->normalizeText($inline) !== $label) {
                            return $inline;
                        }
                        for ($i = $index + 1; $i < count($row); $i++) {
                            if (trim((string) $row[$i]) !== '') {
                                return trim((string) $row[$i]);
                            }
                        }
                    }
                }
            }
        }
        return '';
    }

    private function extractValueNearLabel(array $rows, array $labels): string
    {
        $normalizedLabels = array_map(fn ($label) => $this->normalizeText($label), $labels);
        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $index => $cell) {
                $normalized = $this->normalizeText($cell);
                if (!in_array($normalized, $normalizedLabels, true)) {
                    continue;
                }
                for ($i = $rowIndex + 1; $i < min($rowIndex + 4, count($rows)); $i++) {
                    $value = trim((string) ($rows[$i][$index] ?? ''));
                    if ($value !== '') {
                        return $value;
                    }
                }
                for ($i = $index + 1; $i < count($row); $i++) {
                    $value = trim((string) ($row[$i] ?? ''));
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }
        return '';
    }

    private function parseCapacityRows(array $rows): array
    {
        $start = null;
        foreach ($rows as $index => $row) {
            $text = $this->normalizeText(implode(' ', $row));
            if (str_contains($text, 'NANG LUC SAN XUAT')) {
                $start = $index;
                break;
            }
        }
        if ($start === null) {
            return [];
        }

        $result = [];
        for ($i = $start + 1; $i < min($start + 12, count($rows)); $i++) {
            $cells = collect($rows[$i])->map(fn ($cell) => trim((string) $cell))->values();
            if ($cells->filter()->isEmpty()) {
                continue;
            }
            $result[] = [
                'raw' => $cells->all(),
                'date' => $cells->first(fn ($cell) => $this->dateTokenOrNull($cell)) ?: '',
                'quantity' => collect($cells)->map(fn ($cell) => $this->isPlainNumberToken($cell) ? $this->toNumber($cell) : null)->filter(fn ($value) => $value !== null)->last(),
            ];
        }
        return $result;
    }

    private function isPlainNumberToken($value): bool
    {
        $value = trim((string) $value);
        if ($value === '' || str_contains($value, '/')) {
            return false;
        }
        return (bool) preg_match('/^-?\d+(?:[,.]\d+)?$/', $value);
    }

    private function dateTokenOrNull($value): ?string
    {
        $value = trim((string) $value);
        if (!preg_match('/^(?:\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4}|\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2})$/', $value)) {
            return null;
        }
        return $this->dateOrNull($value);
    }

    private function normalizeText($value): string
    {
        $value = mb_strtoupper(trim((string) $value));
        $map = [
            'Á'=>'A','À'=>'A','Ả'=>'A','Ã'=>'A','Ạ'=>'A','Ă'=>'A','Ắ'=>'A','Ằ'=>'A','Ẳ'=>'A','Ẵ'=>'A','Ặ'=>'A','Â'=>'A','Ấ'=>'A','Ầ'=>'A','Ẩ'=>'A','Ẫ'=>'A','Ậ'=>'A',
            'É'=>'E','È'=>'E','Ẻ'=>'E','Ẽ'=>'E','Ẹ'=>'E','Ê'=>'E','Ế'=>'E','Ề'=>'E','Ể'=>'E','Ễ'=>'E','Ệ'=>'E',
            'Í'=>'I','Ì'=>'I','Ỉ'=>'I','Ĩ'=>'I','Ị'=>'I',
            'Ó'=>'O','Ò'=>'O','Ỏ'=>'O','Õ'=>'O','Ọ'=>'O','Ô'=>'O','Ố'=>'O','Ồ'=>'O','Ổ'=>'O','Ỗ'=>'O','Ộ'=>'O','Ơ'=>'O','Ớ'=>'O','Ờ'=>'O','Ở'=>'O','Ỡ'=>'O','Ợ'=>'O',
            'Ú'=>'U','Ù'=>'U','Ủ'=>'U','Ũ'=>'U','Ụ'=>'U','Ư'=>'U','Ứ'=>'U','Ừ'=>'U','Ử'=>'U','Ữ'=>'U','Ự'=>'U',
            'Ý'=>'Y','Ỳ'=>'Y','Ỷ'=>'Y','Ỹ'=>'Y','Ỵ'=>'Y','Đ'=>'D',
        ];
        return preg_replace('/\s+/u', ' ', strtr($value, $map));
    }

    private function pagination(int $page, int $perPage, int $total): array
    {
        $totalPages = max(1, (int) ceil($total / $perPage));
        return [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'has_more' => $page < $totalPages,
        ];
    }

    private function parseTsv(string $text): array
    {
        return collect(preg_split('/\r\n|\r|\n/', trim($text)))
            ->map(fn ($line) => array_map('trim', explode("\t", $line)))
            ->filter(fn ($row) => collect($row)->filter(fn ($cell) => $cell !== '')->isNotEmpty())
            ->values()
            ->all();
    }

    private function cleanCode($value): string
    {
        return mb_strtoupper(trim((string) $value));
    }

    private function toNumber($value): float
    {
        $normalized = str_replace(',', '.', preg_replace('/[^\d,\.\-]/', '', (string) $value));
        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private function dateOrNull($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        try {
            return $this->normalizeDateFields(['date' => $value], ['date'])['date'];
        } catch (\Throwable $e) {
            return null;
        }
    }
}
