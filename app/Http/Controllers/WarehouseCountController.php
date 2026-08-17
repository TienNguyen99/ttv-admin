<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\NormalizesDateInput;
use App\Models\InternalBtpProductionOrder;
use App\Models\InternalInventoryCount;
use App\Models\InternalItemCatalog;
use App\Models\InternalMaterialIssue;
use App\Models\InternalMaterialReceipt;
use App\Models\InternalMaterialReceiptLine;
use App\Models\InternalOpeningStock;
use App\Models\InventoryPackage;
use App\Models\WarehouseLocation;
use App\Services\InternalAudit;
use App\Services\InternalBtpOrderMatcher;
use App\Services\InternalCatalogValidator;
use App\Services\InternalDocumentNumber;
use App\Services\InternalStockLedger;
use App\Services\InternalUnitConverter;
use App\Services\InternalProductionOrderLineResolver;
use App\Services\PantoneColorMatcher;
use App\Services\WarehouseRackCode;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WarehouseCountController extends Controller
{
    use NormalizesDateInput;

    public function index()
    {
        return view('client.warehouse-count');
    }

    public function shelfMapIndex()
    {
        return view('client.warehouse-count', [
            'shelfMapOnly' => true,
        ]);
    }

    public function stockIndex()
    {
        return view('client.internal-stock');
    }

    public function qualityIndex()
    {
        return view('client.internal-warehouse-quality');
    }

    public function finishedGoodsTvIndex()
    {
        return view('client.finished-goods-tv');
    }

    public function finishedGoodsTvData(Request $request)
    {
        $date = $request->query('date', now()->timezone('Asia/Ho_Chi_Minh')->format('Y-m-d'));
        $limit = min(max((int) $request->query('limit', 200), 1), 500);

        $rows = DB::connection('internal')->table('internal_material_receipt_lines as l')
            ->join('internal_material_receipts as r', 'r.id', '=', 'l.receipt_id')
            ->whereDate('r.receipt_date', $date)
            ->where(function ($query) {
                $query->where('r.source', 'Phieu nhap thanh pham')
                    ->orWhere('r.receipt_code', 'like', 'PNTP-%');
            })
            ->select(
                'r.id as receipt_id',
                'r.receipt_code',
                'r.receipt_date',
                'r.location_code as receipt_location',
                'r.created_at as receipt_created_at',
                'l.created_at as line_created_at',
                'l.customer',
                'l.production_order',
                'l.ma_hh',
                'l.internal_item_code',
                'l.ten_hh',
                'l.dvt',
                'l.quantity',
                'l.size',
                'l.color',
                'l.side',
                'l.location_code'
            )
            ->orderByDesc(DB::raw('COALESCE(l.created_at, r.created_at)'))
            ->orderByDesc('l.id')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $timeSource = $row->line_created_at ?: $row->receipt_created_at;
                $time = $timeSource
                    ? Carbon::parse($timeSource)->timezone('Asia/Ho_Chi_Minh')
                    : Carbon::parse($row->receipt_date)->timezone('Asia/Ho_Chi_Minh');
                $customer = trim((string) $row->customer) ?: 'Chưa xác định khách';
                $code = trim((string) ($row->internal_item_code ?: $row->ma_hh)) ?: 'Chưa có mã';
                $unit = trim((string) $row->dvt) ?: 'pcs';
                $quantity = (float) $row->quantity;

                return [
                    'receipt_id' => $row->receipt_id,
                    'receipt_code' => $row->receipt_code,
                    'receipt_date' => $row->receipt_date,
                    'time' => $time->format('H:i'),
                    'hour' => (int) $time->format('H'),
                    'minute' => (int) $time->format('i'),
                    'customer' => $customer,
                    'production_order' => trim((string) $row->production_order),
                    'ma_hh' => trim((string) $row->ma_hh),
                    'internal_item_code' => trim((string) $row->internal_item_code),
                    'display_code' => $code,
                    'ten_hh' => trim((string) $row->ten_hh),
                    'quantity' => $quantity,
                    'dvt' => $unit,
                    'size' => trim((string) $row->size),
                    'color' => trim((string) $row->color),
                    'side' => trim((string) $row->side),
                    'location_code' => trim((string) ($row->location_code ?: $row->receipt_location)),
                    'sentence' => sprintf(
                        'Vào lúc %d giờ %02d phút đã nhập thành phẩm Mã %s số lượng %s %s',
                        (int) $time->format('H'),
                        (int) $time->format('i'),
                        $code,
                        number_format($quantity, 3, ',', '.'),
                        $unit
                    ),
                ];
            });

        $groups = $rows->groupBy('customer')->map(function ($items, $customer) {
            return [
                'customer' => $customer,
                'line_count' => $items->count(),
                'total_quantity' => (float) $items->sum('quantity'),
                'items' => $items->values(),
            ];
        })->sortByDesc('total_quantity')->values();

        $btpRows = DB::connection('internal')->table('internal_btp_production_order_lines as l')
            ->join('internal_btp_production_orders as o', 'o.id', '=', 'l.btp_order_id')
            ->whereIn('o.status', ['draft', 'issued'])
            ->select(
                'o.id as order_id',
                'o.btp_order_code',
                'o.order_date',
                'o.status',
                'o.issue_code',
                'o.customer',
                'o.receiver_name',
                'o.department',
                'o.created_at as order_created_at',
                'o.issued_at',
                'l.ma_hh',
                'l.internal_item_code',
                'l.ten_hh',
                'l.dvt',
                'l.quantity',
                'l.size',
                'l.color',
                'l.side',
                'l.location_code'
            )
            ->orderByRaw("CASE WHEN o.status = 'issued' THEN 0 ELSE 1 END")
            ->orderByDesc(DB::raw('COALESCE(o.issued_at, o.created_at)'))
            ->orderByDesc('o.id')
            ->limit(12)
            ->get()
            ->map(function ($row) {
                $timeSource = $row->issued_at ?: $row->order_created_at;
                $time = $timeSource
                    ? Carbon::parse($timeSource)->timezone('Asia/Ho_Chi_Minh')
                    : Carbon::parse($row->order_date)->timezone('Asia/Ho_Chi_Minh');

                return [
                    'order_id' => $row->order_id,
                    'btp_order_code' => $row->btp_order_code,
                    'order_date' => $row->order_date,
                    'time' => $time->format('H:i'),
                    'status' => $row->status,
                    'status_label' => $row->status === 'issued' ? 'Đang sản xuất' : 'Chờ xuất',
                    'issue_code' => trim((string) $row->issue_code),
                    'customer' => trim((string) $row->customer),
                    'receiver_name' => trim((string) $row->receiver_name),
                    'department' => trim((string) $row->department),
                    'ma_hh' => trim((string) $row->ma_hh),
                    'internal_item_code' => trim((string) $row->internal_item_code),
                    'display_code' => trim((string) ($row->internal_item_code ?: $row->ma_hh)) ?: 'Chưa có mã',
                    'ten_hh' => trim((string) $row->ten_hh),
                    'quantity' => (float) $row->quantity,
                    'dvt' => trim((string) $row->dvt) ?: 'pcs',
                    'size' => trim((string) $row->size),
                    'color' => trim((string) $row->color),
                    'side' => trim((string) $row->side),
                    'location_code' => trim((string) $row->location_code),
                ];
            });

        return response()->json([
            'data' => $groups,
            'flat' => $rows->values(),
            'btp' => [
                'data' => $btpRows->values(),
                'summary' => [
                    'order_count' => $btpRows->pluck('btp_order_code')->unique()->count(),
                    'line_count' => $btpRows->count(),
                    'total_quantity' => (float) $btpRows->sum('quantity'),
                    'issued_count' => $btpRows->where('status', 'issued')->pluck('btp_order_code')->unique()->count(),
                    'draft_count' => $btpRows->where('status', 'draft')->pluck('btp_order_code')->unique()->count(),
                ],
            ],
            'summary' => [
                'date' => $date,
                'customer_count' => $groups->count(),
                'line_count' => $rows->count(),
                'total_quantity' => (float) $rows->sum('quantity'),
                'last_updated_at' => now()->timezone('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    public function showLocation(WarehouseLocation $warehouseLocation)
    {
        return view('client.warehouse-location-detail', [
            'location' => $warehouseLocation,
        ]);
    }

    public function locations()
    {
        $rackCode = app(WarehouseRackCode::class);
        $locations = WarehouseLocation::query()
            ->get()
            ->map(function ($location) {
                $tier = $this->inferTierFromLocationCode($location->location_code);
                if ($tier > 0) {
                    $location->shelf_code = $this->inferShelfCode($location->location_code);
                    $location->tier = $tier;
                    $location->bay_code = $this->inferBayCode($location->location_code) ?: $location->bay_code;
                }

                return $location;
            })
            ->sortBy(function ($location) use ($rackCode) {
                $parsed = $rackCode->parseLocation((string) $location->location_code);
                if (!$parsed) {
                    return '9999|' . mb_strtoupper((string) $location->location_code);
                }

                return str_pad((string) $parsed['letter_index'], 4, '0', STR_PAD_LEFT)
                    . '|' . str_pad((string) $parsed['bay'], 6, '0', STR_PAD_LEFT);
            })
            ->values();

        return response()->json([
            'data' => $locations,
        ]);
    }

    public function storeLocation(Request $request)
    {
        $data = $request->validate([
            'location_code' => 'required|string|max:100',
            'warehouse_code' => 'nullable|string|max:50',
            'shelf_code' => 'nullable|string|max:20',
            'tier' => 'nullable|integer|min:1|max:5',
            'bay_code' => 'nullable|string|max:50',
            'grid_x' => 'nullable|integer|min:1|max:24',
            'grid_y' => 'nullable|integer|min:1|max:40',
            'grid_w' => 'nullable|integer|min:1|max:24',
            'grid_h' => 'nullable|integer|min:1|max:10',
            'location_name' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
        ]);

        $data['location_code'] = strtoupper(trim($data['location_code']));
        $data['warehouse_code'] = strtoupper(trim($data['warehouse_code'] ?? ''));
        $data['shelf_code'] = strtoupper(trim($data['shelf_code'] ?? ''));
        $data['bay_code'] = strtoupper(trim($data['bay_code'] ?? ''));
        $inferredTier = $this->inferTierFromLocationCode($data['location_code']);

        $location = WarehouseLocation::query()->updateOrCreate(
            ['location_code' => $data['location_code']],
            [
                'warehouse_code' => $data['warehouse_code'],
                'shelf_code' => $data['shelf_code'] ?: $this->inferShelfCode($data['location_code']),
                'tier' => $inferredTier ?: max(1, min(5, (int) ($data['tier'] ?? 1))),
                'bay_code' => $data['bay_code'] ?: ($this->inferBayCode($data['location_code']) ?: null),
                'grid_x' => (int) ($data['grid_x'] ?? 1),
                'grid_y' => (int) ($data['grid_y'] ?? 1),
                'grid_w' => (int) ($data['grid_w'] ?? 4),
                'grid_h' => (int) ($data['grid_h'] ?? 2),
                'location_name' => $data['location_name'] ?? null,
                'note' => $data['note'] ?? null,
            ]
        );

        return response()->json(['data' => $location]);
    }

    public function bulkStoreLocations(Request $request)
    {
        $data = $request->validate([
            'shelf_from' => ['required', 'string', 'max:2', 'regex:/^[A-Za-z]{1,2}$/'],
            'shelf_to' => ['required', 'string', 'max:2', 'regex:/^[A-Za-z]{1,2}$/'],
            'number_from' => 'required|integer|min:1|max:999',
            'number_to' => 'required|integer|min:1|max:999',
            'warehouse_code' => 'nullable|string|max:50',
            'tier' => 'nullable|integer|min:1|max:5',
            'name_prefix' => 'nullable|string|max:100',
        ]);

        $rackCode = app(WarehouseRackCode::class);
        $fromShelf = $rackCode->labelToIndex($data['shelf_from']);
        $toShelf = $rackCode->labelToIndex($data['shelf_to']);
        if ($fromShelf < 0 || $toShelf < 0 || $fromShelf > $toShelf) {
            return response()->json(['message' => 'Dãy kệ không hợp lệ. Ví dụ: A đến AI.'], 422);
        }

        $fromNumber = (int) $data['number_from'];
        $toNumber = (int) $data['number_to'];
        if ($fromNumber > $toNumber) {
            return response()->json(['message' => 'Số bắt đầu phải nhỏ hơn hoặc bằng số kết thúc.'], 422);
        }

        $locationSpecs = [];
        for ($shelfIndex = $fromShelf; $shelfIndex <= $toShelf; $shelfIndex++) {
            $shelf = $rackCode->indexToLabel($shelfIndex);
            for ($number = $fromNumber; $number <= $toNumber; $number++) {
                $locationSpecs[] = [
                    'shelf_index' => $shelfIndex,
                    'shelf' => $shelf,
                    'number' => $number,
                    'location_code' => $shelf . $number,
                ];
            }
        }

        $totalLocations = count($locationSpecs);
        if ($totalLocations > 10000) {
            return response()->json(['message' => 'Mỗi lần chỉ tạo tối đa 10.000 vị trí. Hãy chia thành nhiều dãy nhỏ hơn.'], 422);
        }

        $warehouseCode = strtoupper(trim((string) ($data['warehouse_code'] ?? '')));
        $tier = max(1, min(5, (int) ($data['tier'] ?? 1)));
        $namePrefix = trim((string) ($data['name_prefix'] ?? 'Kệ'));
        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::connection('internal')->transaction(function () use ($rackCode, $locationSpecs, $fromShelf, $fromNumber, $warehouseCode, $tier, $namePrefix, &$created, &$updated, &$skipped) {
            $codes = collect($locationSpecs)->pluck('location_code')->all();

            $existingByCode = WarehouseLocation::query()
                ->whereIn('location_code', $codes)
                ->get()
                ->keyBy('location_code');
            $inserts = [];
            $now = now();

            foreach ($locationSpecs as $spec) {
                    $shelfIndex = $spec['shelf_index'];
                    $number = $spec['number'];
                    $locationCode = $spec['location_code'];
                    $existing = $existingByCode->get($locationCode);

                    if ($existing) {
                        $inferredShelf = $this->inferShelfCode($locationCode);
                        $inferredTier = $this->inferTierFromLocationCode($locationCode) ?: $tier;
                        $inferredBay = $this->inferBayCode($locationCode);
                        $changed = false;
                        if ($warehouseCode !== '' && !$existing->warehouse_code) {
                            $existing->warehouse_code = $warehouseCode;
                            $changed = true;
                        }
                        if ($inferredShelf !== '' && $existing->shelf_code !== $inferredShelf) {
                            $existing->shelf_code = $inferredShelf;
                            $changed = true;
                        }
                        if ((int) $existing->tier !== (int) $inferredTier) {
                            $existing->tier = $inferredTier;
                            $changed = true;
                        }
                        if ($inferredBay !== '' && (string) $existing->bay_code !== $inferredBay) {
                            $existing->bay_code = $inferredBay;
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

                    $index = $number - $fromNumber;
                    $inferredTier = $this->inferTierFromLocationCode($locationCode) ?: $tier;
                    $inserts[] = [
                        'location_code' => $locationCode,
                        'warehouse_code' => $warehouseCode,
                        'shelf_code' => $this->inferShelfCode($locationCode),
                        'tier' => $inferredTier,
                        'bay_code' => $this->inferBayCode($locationCode) ?: (string) $number,
                        'grid_x' => (($index % 6) * 4) + 1,
                        'grid_y' => (($shelfIndex - $fromShelf) * 18) + (int) floor($index / 6) * 3 + 1,
                        'grid_w' => 4,
                        'grid_h' => 2,
                        'location_name' => trim($namePrefix . ' ' . $locationCode),
                        'status' => 'pending',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
            }

            foreach (array_chunk($inserts, 500) as $chunk) {
                $created += DB::connection('internal')->table('warehouse_locations')->insertOrIgnore($chunk);
            }
        });

        return response()->json([
            'message' => "Đã tạo nhanh {$created} vị trí.",
            'data' => compact('created', 'updated', 'skipped'),
        ]);
    }

    public function updateLocationLayout(Request $request, WarehouseLocation $warehouseLocation)
    {
        $data = $request->validate([
            'grid_x' => 'required|integer|min:1|max:24',
            'grid_y' => 'required|integer|min:1|max:40',
            'grid_w' => 'required|integer|min:1|max:24',
            'grid_h' => 'required|integer|min:1|max:10',
        ]);

        $warehouseLocation->update($data);

        return response()->json([
            'message' => 'Đã lưu vị trí trên sơ đồ.',
            'data' => $warehouseLocation->fresh(),
        ]);
    }

    public function destroyLocation(WarehouseLocation $warehouseLocation)
    {
        if (InventoryPackage::query()->where('warehouse_location_id', $warehouseLocation->id)->exists()) {
            return response()->json([
                'message' => 'Vị trí còn kiện hàng. Xóa các kiện trong vị trí trước khi xóa vị trí.',
            ], 422);
        }

        $warehouseLocation->delete();

        return response()->json([
            'message' => 'Đã xóa vị trí kho.',
        ]);
    }

    public function packages(Request $request)
    {
        $query = InventoryPackage::query()
            ->with('location:id,location_code')
            ->where('quantity', '>', 0)
            ->orderByDesc('id');

        if ($request->filled('location_code')) {
            $query->whereHas('location', function ($q) use ($request) {
                $q->where('location_code', $request->query('location_code'));
            });
        }

        if ($request->filled('checked_at')) {
            $query->whereDate('checked_at', $request->query('checked_at'));
        }

        $summaryQuery = clone $query;

        $limit = min(max((int) $request->query('limit', 100), 1), 1000);
        $packages = $query->limit($limit)->get();
        $catalogsByItemCode = InternalItemCatalog::query()
            ->where('is_active', true)
            ->whereIn('item_code', $packages->pluck('internal_item_code')->filter()->unique()->values())
            ->get()
            ->keyBy(fn ($item) => mb_strtoupper(trim((string) $item->item_code)));
        $matcher = app(PantoneColorMatcher::class);

        return response()->json([
            'data' => $packages->map(function ($package) use ($catalogsByItemCode, $matcher) {
                $catalog = $catalogsByItemCode->get(mb_strtoupper(trim((string) $package->internal_item_code)));
                $match = $matcher->matchValues([
                    $package->internal_item_code,
                    $package->ma_sp,
                    $package->size,
                    $package->color,
                    $package->side,
                ], $catalog);
                $package->pantone_code = $match['pantone'];
                $package->pantone_hex = $match['hex'];
                $package->color_name = $match['name'] ?? '';
                $package->pantone_source = $match['source'];
                $package->catalog_name = $catalog->item_name ?? '';
                $package->catalog_unit = $catalog->unit ?? '';
                return $package;
            }),
            'summary' => [
                'package_count' => $summaryQuery->count(),
                'total_quantity' => (float) (clone $summaryQuery)->sum('quantity'),
            ],
        ]);
    }

    public function stockMapData(Request $request)
    {
        $date = $this->normalizeDateInput($request->query('checked_at')) ?? now('Asia/Ho_Chi_Minh')->format('Y-m-d');
        $month = Carbon::parse($date)->startOfMonth();
        $monthStart = $month->format('Y-m-d');
        $monthEnd = Carbon::parse($date)->format('Y-m-d');

        $rows = app(InternalStockLedger::class)
            ->query($monthStart, $monthEnd)
            ->select(
                'warehouse_code',
                'location_code',
                DB::raw('ma_hh as ma_sp'),
                'internal_item_code',
                'size',
                'color',
                'side',
                DB::raw('SUM(opening_quantity) as opening_quantity'),
                DB::raw('SUM(receipt_quantity) as receipt_quantity'),
                DB::raw('SUM(issue_quantity) as issue_quantity'),
                DB::raw('SUM(opening_quantity + receipt_quantity - issue_quantity) as total_quantity')
            )
            ->groupBy('warehouse_code', 'location_code', 'ma_hh', 'internal_item_code', 'size', 'color', 'side')
            ->havingRaw('SUM(opening_quantity + receipt_quantity - issue_quantity) > 0')
            ->orderBy('location_code')
            ->orderBy('internal_item_code')
            ->limit(3000)
            ->get();

        $shelfCatalogs = InternalItemCatalog::query()
            ->where('is_active', true)
            ->whereNotNull('item_code')
            ->whereRaw("TRIM(COALESCE(shelf_code, '')) <> ''")
            ->orderBy('source_row')
            ->limit(3000)
            ->get();

        $locationCodes = $rows
            ->pluck('location_code')
            ->map(fn ($code) => strtoupper(trim((string) $code)) ?: 'CHUA-XEP')
            ->merge($shelfCatalogs->pluck('shelf_code')->map(fn ($code) => strtoupper(trim((string) $code))))
            ->filter()
            ->unique()
            ->values();

        $locations = WarehouseLocation::query()
            ->whereIn('location_code', $locationCodes)
            ->get(['id', 'location_code'])
            ->keyBy(fn ($location) => strtoupper(trim((string) $location->location_code)));

        $catalogsByItemCode = InternalItemCatalog::query()
            ->where('is_active', true)
            ->whereIn(
                'item_code',
                $rows->pluck('internal_item_code')
                    ->merge($shelfCatalogs->pluck('item_code'))
                    ->filter()
                    ->unique()
                    ->values()
            )
            ->get()
            ->keyBy(fn ($item) => mb_strtoupper(trim((string) $item->item_code)));

        $matcher = app(PantoneColorMatcher::class);

        $data = $rows->map(function ($row, $index) use ($locations, $catalogsByItemCode, $matcher) {
            $locationCode = strtoupper(trim((string) $row->location_code)) ?: 'CHUA-XEP';
            $location = $locations->get($locationCode);
            $catalog = $catalogsByItemCode->get(mb_strtoupper(trim((string) $row->internal_item_code)));
            $match = $matcher->matchValues([
                $row->internal_item_code,
                $row->ma_sp,
                $row->size,
                $row->color,
                $row->side,
            ], $catalog);

            return [
                'id' => 'stock-' . ($index + 1),
                'package_code' => 'TON-' . $locationCode,
                'warehouse_location_id' => $location ? $location->id : null,
                'location' => [
                    'id' => $location ? $location->id : null,
                    'location_code' => $locationCode,
                ],
                'ma_ko' => $row->warehouse_code,
                'ma_sp' => $row->ma_sp,
                'internal_item_code' => $row->internal_item_code,
                'size' => $row->size,
                'color' => $row->color,
                'side' => $row->side,
                'quantity' => (float) $row->total_quantity,
                'opening_quantity' => (float) $row->opening_quantity,
                'receipt_quantity' => (float) $row->receipt_quantity,
                'issue_quantity' => (float) $row->issue_quantity,
                'catalog_name' => $catalog ? $catalog->item_name : '',
                'catalog_unit' => $catalog ? $catalog->unit : '',
                'pantone_code' => $match['pantone'],
                'pantone_hex' => $match['hex'],
                'color_name' => $match['name'] ?? '',
                'pantone_source' => $match['source'],
                'catalog_only' => false,
            ];
        });

        $stockKeys = $data->mapWithKeys(function ($row) {
            $key = mb_strtoupper(implode('|', [
                $row['location']['location_code'] ?? '',
                $row['internal_item_code'] ?? '',
                $row['size'] ?? '',
                $row['color'] ?? '',
                $row['side'] ?? '',
            ]));
            return [$key => true];
        });

        $catalogOnlyRows = $shelfCatalogs
            ->map(function ($catalog) use ($locations, $matcher, $stockKeys) {
                $locationCode = strtoupper(trim((string) $catalog->shelf_code));
                $key = mb_strtoupper(implode('|', [
                    $locationCode,
                    $catalog->item_code,
                    $catalog->size,
                    $catalog->color,
                    $catalog->side,
                ]));
                if ($stockKeys->has($key)) {
                    return null;
                }

                $location = $locations->get($locationCode);
                $match = $matcher->matchCatalog($catalog);

                return [
                    'id' => 'catalog-' . $catalog->id,
                    'package_code' => 'DANH-MUC-' . $locationCode,
                    'warehouse_location_id' => $location ? $location->id : null,
                    'location' => [
                        'id' => $location ? $location->id : null,
                        'location_code' => $locationCode,
                    ],
                    'ma_ko' => '',
                    'ma_sp' => '',
                    'internal_item_code' => $catalog->item_code,
                    'size' => $catalog->size,
                    'color' => $catalog->color,
                    'side' => $catalog->side,
                    'quantity' => 0,
                    'opening_quantity' => 0,
                    'receipt_quantity' => 0,
                    'issue_quantity' => 0,
                    'catalog_name' => $catalog->item_name,
                    'catalog_unit' => $catalog->unit,
                    'pantone_code' => $match['pantone'],
                    'pantone_hex' => $match['hex'],
                    'color_name' => $match['name'] ?? '',
                    'pantone_source' => $match['source'],
                    'catalog_only' => true,
                ];
            })
            ->filter()
            ->values();

        $data = $data->concat($catalogOnlyRows)->values();

        return response()->json([
            'data' => $data,
            'summary' => [
                'location_count' => $data->pluck('location.location_code')->filter()->unique()->count(),
                'line_count' => $data->count(),
                'item_count' => $data->pluck('internal_item_code')->filter()->unique()->count(),
                'total_quantity' => (float) $data->sum('quantity'),
            ],
        ]);
    }

    public function receipts(Request $request)
    {
        $query = InternalMaterialReceipt::query()
            ->withCount('lines')
            ->withSum('lines as total_quantity', 'quantity')
            ->orderByDesc('receipt_date')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $receiptKind = trim((string) $request->query('receipt_kind', 'finished'));
        if ($receiptKind === 'semi_finished') {
            $query->where(function ($inner) {
                $inner->where('source', 'Phieu nhap ban thanh pham')
                    ->orWhere('receipt_code', 'like', 'PNBTP-%');
            });
        } elseif ($receiptKind === 'all') {
            $query->where(function ($inner) {
                $inner->whereIn('source', ['Phieu nhap thanh pham', 'Phieu nhap ban thanh pham'])
                    ->orWhere('receipt_code', 'like', 'PNTP-%')
                    ->orWhere('receipt_code', 'like', 'PNBTP-%');
            });
        } else {
            $query->where(function ($inner) {
                $inner->where('source', 'Phieu nhap thanh pham')
                    ->orWhere('receipt_code', 'like', 'PNTP-%');
            });
        }

        if ($request->filled('receipt_date')) {
            $query->whereDate('receipt_date', $request->query('receipt_date'));
        }

        if ($request->filled('warehouse_code')) {
            $query->where('warehouse_code', strtoupper(trim((string) $request->query('warehouse_code'))));
        }

        if ($request->filled('location_code')) {
            $query->where('location_code', strtoupper(trim((string) $request->query('location_code'))));
        }

        $keyword = trim((string) $request->query('keyword', ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('receipt_code', 'like', '%' . $keyword . '%')
                    ->orWhere('note', 'like', '%' . $keyword . '%')
                    ->orWhereHas('lines', function ($lineQuery) use ($keyword) {
                        $lineQuery->where('production_order', 'like', '%' . $keyword . '%')
                            ->orWhere('purchase_order', 'like', '%' . $keyword . '%')
                            ->orWhere('customer', 'like', '%' . $keyword . '%')
                            ->orWhere('ma_hh', 'like', '%' . $keyword . '%')
                            ->orWhere('internal_item_code', 'like', '%' . $keyword . '%')
                            ->orWhere('ten_hh', 'like', '%' . $keyword . '%')
                            ->orWhere('size', 'like', '%' . $keyword . '%')
                            ->orWhere('color', 'like', '%' . $keyword . '%')
                            ->orWhere('logo_color', 'like', '%' . $keyword . '%')
                            ->orWhere('side', 'like', '%' . $keyword . '%');
                    });
            });
        }

        $limit = min(max((int) $request->query('limit', 100), 1), 500);
        $receipts = $query->with('lines')->limit($limit)->get();
        $fifoStatuses = $this->receiptFifoStatuses($receipts);

        return response()->json([
            'data' => $receipts->map(function ($receipt) use ($fifoStatuses) {
                $issue = InternalMaterialIssue::query()
                    ->where('source_receipt_id', $receipt->id)
                    ->orWhere('note', 'like', '%' . $receipt->receipt_code . '%')
                    ->orderByDesc('id')
                    ->first();
                $fifo = $fifoStatuses[$receipt->id] ?? [
                    'issue_status' => $issue ? 'exported' : 'not_exported',
                    'issued_quantity' => $issue ? (float) ($receipt->total_quantity ?? 0) : 0,
                    'remaining_quantity' => $issue ? 0 : (float) ($receipt->total_quantity ?? 0),
                ];
                return [
                    'id' => $receipt->id,
                    'receipt_code' => $receipt->receipt_code,
                    'receipt_date' => optional($receipt->receipt_date)->format('Y-m-d'),
                    'warehouse_code' => $receipt->warehouse_code,
                    'location_code' => $receipt->location_code,
                    'note' => $receipt->note,
                    'source' => $receipt->source,
                    'receipt_kind' => $receipt->source === 'Phieu nhap ban thanh pham' ? 'semi_finished' : 'finished',
                    'production_orders' => $receipt->lines
                        ->pluck('production_order')
                        ->map(fn ($code) => trim((string) $code))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),
                    'lines_count' => (int) $receipt->lines_count,
                    'total_quantity' => (float) ($receipt->total_quantity ?? 0),
                    'issue_status' => $fifo['issue_status'],
                    'fifo_issued_quantity' => $fifo['issued_quantity'],
                    'fifo_remaining_quantity' => $fifo['remaining_quantity'],
                    'issue_code' => $issue->issue_code ?? null,
                    'issue_type' => $issue->issue_type ?? null,
                    'issue_id' => $issue->id ?? null,
                    'issue_print_url' => $issue ? url('/client/xuat-vat-tu-noi-bo/' . $issue->id . '/in') : null,
                    'print_url' => url('/client/nhap-thanh-pham-noi-bo/' . $receipt->id . '/in'),
                ];
            }),
            'summary' => [
                'receipt_count' => $receipts->count(),
                'line_count' => (int) $receipts->sum('lines_count'),
                'total_quantity' => (float) $receipts->sum('total_quantity'),
                'exported_count' => collect($fifoStatuses)->filter(fn ($status) => $status['issue_status'] === 'exported')->count(),
            ],
        ]);
    }

    private function receiptFifoStatuses($receipts): array
    {
        $lineKeys = $receipts
            ->flatMap(fn ($receipt) => $receipt->lines)
            ->map(fn ($line) => $this->stockFlowKey($line))
            ->filter()
            ->unique()
            ->values();

        if ($lineKeys->isEmpty()) {
            return [];
        }

        $allReceiptLines = DB::connection('internal')->table('internal_material_receipt_lines as l')
            ->join('internal_material_receipts as r', 'r.id', '=', 'l.receipt_id')
            ->where('r.source', 'Phieu nhap thanh pham')
            ->select(
                'l.id',
                'l.receipt_id',
                'l.ma_hh',
                'l.internal_item_code',
                'l.size',
                'l.color',
                'l.side',
                'l.quantity',
                'r.receipt_date'
            )
            ->orderBy('r.receipt_date')
            ->orderBy('l.id')
            ->get()
            ->groupBy(fn ($line) => $this->stockFlowKey($line))
            ->only($lineKeys->all());

        $directIssueQuantities = DB::connection('internal')->table('internal_material_issue_lines as l')
            ->join('internal_material_issues as i', 'i.id', '=', 'l.issue_id')
            ->whereNotNull('i.source_receipt_id')
            ->select(
                'i.source_receipt_id',
                'l.ma_hh',
                'l.internal_item_code',
                'l.size',
                'l.color',
                'l.side',
                DB::raw('SUM(l.quantity) as quantity')
            )
            ->groupBy('i.source_receipt_id', 'l.ma_hh', 'l.internal_item_code', 'l.size', 'l.color', 'l.side')
            ->get()
            ->reduce(function ($carry, $line) {
                $key = $this->stockFlowKey($line);
                $receiptId = (int) $line->source_receipt_id;
                $carry[$receiptId][$key] = ($carry[$receiptId][$key] ?? 0) + (float) $line->quantity;
                return $carry;
            }, []);

        $consumedByLineId = [];
        $directRemaining = $directIssueQuantities;
        foreach ($allReceiptLines as $key => $lines) {
            foreach ($lines as $line) {
                $receiptId = (int) $line->receipt_id;
                $received = (float) $line->quantity;
                $remainingDirect = (float) ($directRemaining[$receiptId][$key] ?? 0);
                $consumed = min($received, max($remainingDirect, 0));
                $directRemaining[$receiptId][$key] = $remainingDirect - $consumed;
                $consumedByLineId[(int) $line->id] = $consumed;
            }
        }

        $fifoIssueQuantities = DB::connection('internal')->table('internal_material_issue_lines as l')
            ->join('internal_material_issues as i', 'i.id', '=', 'l.issue_id')
            ->whereNull('i.source_receipt_id')
            ->select(
                'l.ma_hh',
                'l.internal_item_code',
                'l.size',
                'l.color',
                'l.side',
                DB::raw('SUM(l.quantity) as quantity')
            )
            ->groupBy('l.ma_hh', 'l.internal_item_code', 'l.size', 'l.color', 'l.side')
            ->get()
            ->reduce(function ($carry, $line) {
                $key = $this->stockFlowKey($line);
                $carry[$key] = ($carry[$key] ?? 0) + (float) $line->quantity;
                return $carry;
            }, []);

        foreach ($allReceiptLines as $key => $lines) {
            $remainingIssue = (float) ($fifoIssueQuantities[$key] ?? 0);
            foreach ($lines as $line) {
                $lineId = (int) $line->id;
                $received = (float) $line->quantity;
                $directConsumed = (float) ($consumedByLineId[$lineId] ?? 0);
                $fifoConsumed = min(max($received - $directConsumed, 0), max($remainingIssue, 0));
                $remainingIssue -= $fifoConsumed;
                $consumedByLineId[$lineId] = $directConsumed + $fifoConsumed;
            }
        }

        $statuses = [];
        foreach ($receipts as $receipt) {
            $received = (float) $receipt->lines->sum('quantity');
            $issued = (float) $receipt->lines->sum(fn ($line) => $consumedByLineId[(int) $line->id] ?? 0);
            $remaining = $received - $issued;
            $status = $issued <= 0.0001
                ? 'not_exported'
                : ($remaining <= 0.0001 ? 'exported' : 'partial_exported');

            $statuses[$receipt->id] = [
                'issue_status' => $status,
                'issued_quantity' => $issued,
                'remaining_quantity' => max($remaining, 0),
            ];
        }

        return $statuses;
    }

    public function stockWarehouses()
    {
        $fromOpening = DB::connection('internal')->table('internal_opening_stocks')
            ->select(DB::raw("NULLIF(warehouse_code, '') as warehouse_code"))
            ->whereRaw("NULLIF(warehouse_code, '') IS NOT NULL");

        $fromReceipts = DB::connection('internal')->table('internal_material_receipts')
            ->select(DB::raw("NULLIF(warehouse_code, '') as warehouse_code"))
            ->whereRaw("NULLIF(warehouse_code, '') IS NOT NULL");

        $fromIssues = DB::connection('internal')->table('internal_material_issues')
            ->select(DB::raw("NULLIF(warehouse_code, '') as warehouse_code"))
            ->whereRaw("NULLIF(warehouse_code, '') IS NOT NULL");

        $warehouses = DB::connection('internal')
            ->query()
            ->fromSub($fromOpening->union($fromReceipts)->union($fromIssues), 'warehouses')
            ->select('warehouse_code')
            ->whereNotNull('warehouse_code')
            ->groupBy('warehouse_code')
            ->orderBy('warehouse_code')
            ->pluck('warehouse_code');

        return response()->json(['data' => $warehouses]);
    }

    public function stockData(Request $request)
    {
        $warehouseCode = strtoupper(trim((string) $request->query('warehouse_code', '')));
        $keyword = trim((string) $request->query('keyword', ''));
        $month = Carbon::parse($request->query('month', now()->format('Y-m')) . '-01')->startOfMonth();
        $monthStart = $month->format('Y-m-d');
        $monthEnd = $month->copy()->endOfMonth()->format('Y-m-d');

        $query = app(InternalStockLedger::class)
            ->query($monthStart, $monthEnd)
            ->select(
                'warehouse_code',
                'location_code',
                DB::raw('ma_hh as ma_sp'),
                'internal_item_code',
                'size',
                'color',
                'side',
                DB::raw('SUM(opening_quantity) as opening_quantity'),
                DB::raw('SUM(receipt_quantity) as receipt_quantity'),
                DB::raw('SUM(issue_quantity) as issue_quantity'),
                DB::raw('SUM(opening_quantity + receipt_quantity - issue_quantity) as total_quantity'),
                DB::raw('0 as package_count'),
                DB::raw("'" . $monthEnd . "' as latest_checked_at")
            );

        if ($warehouseCode !== '') {
            $query->where('warehouse_code', $warehouseCode);
        }

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('ma_hh', 'like', '%' . $keyword . '%')
                    ->orWhere('internal_item_code', 'like', '%' . $keyword . '%')
                    ->orWhere('size', 'like', '%' . $keyword . '%')
                    ->orWhere('color', 'like', '%' . $keyword . '%')
                    ->orWhere('location_code', 'like', '%' . $keyword . '%');
            });
        }

        $directOpeningKeys = InternalOpeningStock::query()
            ->whereDate('period_month', $monthStart)
            ->get([
                'warehouse_code',
                'location_code',
                'ma_hh',
                'internal_item_code',
                'size',
                'color',
                'side',
            ])
            ->mapWithKeys(function ($row) {
                return [$this->stockRowKey($row) => true];
            });

        $data = $query
            ->groupBy('warehouse_code', 'location_code', 'ma_hh', 'internal_item_code', 'size', 'color', 'side')
            ->havingRaw('SUM(opening_quantity + receipt_quantity - issue_quantity) != 0 OR SUM(opening_quantity) != 0 OR SUM(receipt_quantity) != 0 OR SUM(issue_quantity) != 0')
            ->orderBy('warehouse_code')
            ->orderBy('location_code')
            ->orderBy('ma_hh')
            ->get()
            ->map(function ($row) use ($directOpeningKeys) {
                $row->can_delete = $directOpeningKeys->has($this->stockRowKey($row))
                    && (float) $row->opening_quantity != 0.0
                    && (float) $row->receipt_quantity == 0.0
                    && (float) $row->issue_quantity == 0.0;
                $row->delete_reason = $row->can_delete
                    ? ''
                    : 'Dòng có phát sinh phiếu. Hãy xóa phiếu nhập/xuất nguồn.';

                return $row;
            });

        $catalogs = InternalItemCatalog::query()
            ->where('is_active', true)
            ->whereIn('item_code', $data->pluck('internal_item_code')->filter()->unique()->values())
            ->get()
            ->keyBy(fn ($item) => mb_strtoupper(trim((string) $item->item_code)));
        $matcher = app(PantoneColorMatcher::class);
        $data = $data->map(function ($row) use ($catalogs, $matcher) {
            $catalog = $catalogs->get(mb_strtoupper(trim((string) $row->internal_item_code)));
            $match = $matcher->matchValues([
                $row->internal_item_code,
                $row->ma_hh ?? $row->ma_sp ?? '',
                $row->size,
                $row->color,
                $row->side,
            ], $catalog);
            $row->pantone_code = $match['pantone'];
            $row->pantone_hex = $match['hex'];
            $row->color_name = $match['name'] ?? '';
            $row->pantone_source = $match['source'];
            return $row;
        });

        return response()->json([
            'data' => $data,
            'summary' => [
                'warehouse_code' => $warehouseCode,
                'month' => $month->format('Y-m'),
                'item_count' => $data->map(function ($row) {
                    return $row->internal_item_code ?: $row->ma_sp;
                })->filter()->unique()->count(),
                'line_count' => $data->count(),
                'opening_quantity' => (float) $data->sum('opening_quantity'),
                'receipt_quantity' => (float) $data->sum('receipt_quantity'),
                'issue_quantity' => (float) $data->sum('issue_quantity'),
                'package_count' => 0,
                'total_quantity' => (float) $data->sum('total_quantity'),
            ],
        ]);
    }

    public function stockFifoDetail(Request $request)
    {
        $month = Carbon::parse($request->query('month', now()->format('Y-m')) . '-01')->startOfMonth();
        $monthStart = $month->format('Y-m-d');
        $monthEnd = $month->copy()->endOfMonth()->format('Y-m-d');

        $filter = [
            'warehouse_code' => strtoupper(trim((string) $request->query('warehouse_code', ''))),
            'location_code' => strtoupper(trim((string) $request->query('location_code', ''))),
            'ma_hh' => strtoupper(trim((string) $request->query('ma_hh', ''))),
            'internal_item_code' => strtoupper(trim((string) $request->query('internal_item_code', ''))),
            'size' => strtoupper(trim((string) $request->query('size', ''))),
            'color' => strtoupper(trim((string) $request->query('color', ''))),
            'side' => strtoupper(trim((string) $request->query('side', ''))),
        ];

        if ($filter['internal_item_code'] !== '') {
            $filter['ma_hh'] = '';
        }

        if ($filter['ma_hh'] === '' && $filter['internal_item_code'] === '') {
            return response()->json(['message' => 'Thiếu mã kế toán hoặc mã nội bộ để xem chi tiết tồn.'], 422);
        }

        $receiptLots = collect();

        $openingLots = DB::connection('internal')->table('internal_opening_stocks')
            ->whereDate('period_month', '<=', $monthStart);
        $this->applyStockDetailFilter($openingLots, $filter, [
            'warehouse_code' => 'warehouse_code',
            'location_code' => 'location_code',
            'ma_hh' => 'ma_hh',
            'internal_item_code' => 'internal_item_code',
            'size' => 'size',
            'color' => 'color',
            'side' => 'side',
        ]);

        $openingLots->select(
            DB::raw('id as source_id'),
            DB::raw("'OPENING' as source_type"),
            DB::raw("CONCAT('TONDAU-', id) as document_code"),
            DB::raw('period_month as document_date'),
            'warehouse_code',
            'location_code',
            'ma_hh',
            'internal_item_code',
            'size',
            'color',
            'side',
            DB::raw('quantity as quantity'),
            DB::raw('note as note')
        )->orderBy('period_month')->orderBy('id')->get()->each(function ($row) use ($receiptLots) {
            $receiptLots->push($row);
        });

        $receiptQuery = DB::connection('internal')->table('internal_material_receipt_lines as l')
            ->join('internal_material_receipts as r', 'r.id', '=', 'l.receipt_id')
            ->where('r.source', 'Phieu nhap thanh pham')
            ->whereDate('r.receipt_date', '<=', $monthEnd);
        $this->applyStockDetailFilter($receiptQuery, $filter, [
            'warehouse_code' => 'r.warehouse_code',
            'location_code' => "COALESCE(l.location_code, r.location_code, '')",
            'ma_hh' => 'l.ma_hh',
            'internal_item_code' => 'l.internal_item_code',
            'size' => 'l.size',
            'color' => 'l.color',
            'side' => 'l.side',
        ]);

        $receiptQuery->select(
            DB::raw('l.id as source_id'),
            DB::raw("'RECEIPT' as source_type"),
            DB::raw('r.receipt_code as document_code'),
            DB::raw('r.receipt_date as document_date'),
            DB::raw("COALESCE(r.warehouse_code, '') as warehouse_code"),
            DB::raw("COALESCE(l.location_code, r.location_code, '') as location_code"),
            DB::raw("COALESCE(l.ma_hh, '') as ma_hh"),
            DB::raw("COALESCE(l.internal_item_code, '') as internal_item_code"),
            DB::raw("COALESCE(l.size, '') as size"),
            DB::raw("COALESCE(l.color, '') as color"),
            DB::raw("COALESCE(l.side, '') as side"),
            DB::raw('l.quantity as quantity'),
            DB::raw('l.note as note')
        )->orderBy('r.receipt_date')->orderBy('l.id')->get()->each(function ($row) use ($receiptLots) {
            $receiptLots->push($row);
        });

        $issueQuery = DB::connection('internal')->table('internal_material_issue_lines as l')
            ->join('internal_material_issues as i', 'i.id', '=', 'l.issue_id')
            ->whereRaw("COALESCE(i.issue_type, 'material') <> 'production'")
            ->whereDate('i.issue_date', '<=', $monthEnd);
        $this->applyStockDetailFilter($issueQuery, $filter, [
            'warehouse_code' => 'i.warehouse_code',
            'location_code' => 'l.location_code',
            'ma_hh' => 'l.ma_hh',
            'internal_item_code' => 'l.internal_item_code',
            'size' => 'l.size',
            'color' => 'l.color',
            'side' => 'l.side',
        ]);

        $issueRows = $issueQuery->select(
            DB::raw('l.id as line_id'),
            DB::raw('i.issue_code as document_code'),
            DB::raw('i.issue_date as document_date'),
            DB::raw('l.quantity as quantity'),
            DB::raw('i.receiver_name as receiver_name'),
            DB::raw('i.purpose as purpose')
        )->orderBy('i.issue_date')->orderBy('l.id')->get();

        $remainingIssue = (float) $issueRows->sum('quantity');
        $lots = $receiptLots->map(function ($lot) use (&$remainingIssue) {
            $received = (float) $lot->quantity;
            $consumed = min($received, max($remainingIssue, 0));
            $remainingIssue -= $consumed;

            return [
                'source_type' => $lot->source_type,
                'source_id' => (int) $lot->source_id,
                'document_code' => $lot->document_code,
                'document_date' => $lot->document_date,
                'location_code' => $lot->location_code ?: 'CHUA-XEP',
                'ma_hh' => $lot->ma_hh,
                'internal_item_code' => $lot->internal_item_code,
                'size' => $lot->size,
                'color' => $lot->color,
                'side' => $lot->side,
                'received_quantity' => $received,
                'issued_quantity' => $consumed,
                'remaining_quantity' => $received - $consumed,
                'is_fully_issued' => $received > 0 && ($received - $consumed) <= 0.0001,
                'note' => $lot->note,
            ];
        })->values();

        return response()->json([
            'data' => [
                'filter' => $filter,
                'month' => $month->format('Y-m'),
                'month_end' => $monthEnd,
                'lots' => $lots,
                'issues' => $issueRows->map(fn ($row) => [
                    'line_id' => (int) $row->line_id,
                    'document_code' => $row->document_code,
                    'document_date' => $row->document_date,
                    'quantity' => (float) $row->quantity,
                    'receiver_name' => $row->receiver_name,
                    'purpose' => $row->purpose,
                ])->values(),
                'summary' => [
                    'received_quantity' => (float) $receiptLots->sum('quantity'),
                    'issue_quantity' => (float) $issueRows->sum('quantity'),
                    'remaining_quantity' => (float) $lots->sum('remaining_quantity') - max($remainingIssue, 0),
                    'over_issued_quantity' => max($remainingIssue, 0),
                ],
            ],
        ]);
    }

    private function applyStockDetailFilter($query, array $filter, array $columns): void
    {
        foreach ($filter as $key => $value) {
            if ($key === 'location_code' && $value === 'CHUA-XEP') {
                $column = $columns[$key];
                $query->whereRaw("UPPER(TRIM(COALESCE({$column}, ''))) IN ('', 'CHUA-XEP')");
                continue;
            }

            if ($value === '') {
                continue;
            }

            $column = $columns[$key];
            $query->whereRaw('UPPER(TRIM(COALESCE(' . $column . ", ''))) = ?", [$value]);
        }
    }

    public function qualityData(Request $request)
    {
        $month = Carbon::parse($request->query('month', now()->format('Y-m')) . '-01')->startOfMonth();
        $monthStart = $month->format('Y-m-d');
        $monthEnd = $month->copy()->endOfMonth()->format('Y-m-d');

        $stockRows = app(InternalStockLedger::class)
            ->query($monthStart, $monthEnd)
            ->select(
                'location_code',
                DB::raw('ma_hh as ma_sp'),
                'internal_item_code',
                'size',
                'color',
                'side',
                DB::raw('SUM(opening_quantity) as opening_quantity'),
                DB::raw('SUM(receipt_quantity) as receipt_quantity'),
                DB::raw('SUM(issue_quantity) as issue_quantity'),
                DB::raw('SUM(opening_quantity + receipt_quantity - issue_quantity) as total_quantity')
            )
            ->groupBy('location_code', 'ma_hh', 'internal_item_code', 'size', 'color', 'side')
            ->havingRaw('SUM(opening_quantity + receipt_quantity - issue_quantity) != 0 OR SUM(opening_quantity) != 0 OR SUM(receipt_quantity) != 0 OR SUM(issue_quantity) != 0')
            ->get();

        $catalogCodes = InternalItemCatalog::query()
            ->where('is_active', true)
            ->pluck('item_code')
            ->map(fn ($value) => mb_strtoupper(trim((string) $value)))
            ->filter()
            ->flip();

        $rowPayload = function ($row) {
            return [
                'location_code' => $row->location_code ?: 'CHUA-XEP',
                'ma_sp' => $row->ma_sp,
                'internal_item_code' => $row->internal_item_code,
                'size' => $row->size,
                'color' => $row->color,
                'side' => $row->side,
                'quantity' => (float) $row->total_quantity,
                'opening_quantity' => (float) $row->opening_quantity,
                'receipt_quantity' => (float) $row->receipt_quantity,
                'issue_quantity' => (float) $row->issue_quantity,
            ];
        };

        $negativeStock = $stockRows
            ->filter(fn ($row) => (float) $row->total_quantity < 0)
            ->values()
            ->map($rowPayload);

        $unassignedStock = $stockRows
            ->filter(fn ($row) => (float) $row->total_quantity != 0.0 && in_array(strtoupper(trim((string) $row->location_code)), ['', 'CHUA-XEP'], true))
            ->values()
            ->map($rowPayload);

        $missingCatalog = $stockRows
            ->filter(function ($row) use ($catalogCodes) {
                $code = mb_strtoupper(trim((string) $row->internal_item_code));
                return $code !== '' && !$catalogCodes->has($code);
            })
            ->values()
            ->map($rowPayload);

        $multiLocation = $stockRows
            ->filter(fn ($row) => (float) $row->total_quantity != 0.0)
            ->groupBy(function ($row) {
                return implode('|', [
                    mb_strtoupper(trim((string) $row->ma_sp)),
                    mb_strtoupper(trim((string) $row->internal_item_code)),
                    mb_strtoupper(trim((string) $row->size)),
                    mb_strtoupper(trim((string) $row->color)),
                    mb_strtoupper(trim((string) $row->side)),
                ]);
            })
            ->filter(function ($rows) {
                return $rows->pluck('location_code')->map(fn ($value) => strtoupper(trim((string) $value)) ?: 'CHUA-XEP')->unique()->count() > 1;
            })
            ->map(function ($rows) {
                $first = $rows->first();
                return [
                    'ma_sp' => $first->ma_sp,
                    'internal_item_code' => $first->internal_item_code,
                    'size' => $first->size,
                    'color' => $first->color,
                    'side' => $first->side,
                    'quantity' => (float) $rows->sum('total_quantity'),
                    'locations' => $rows->map(fn ($row) => ($row->location_code ?: 'CHUA-XEP') . ' (' . (float) $row->total_quantity . ')')->values(),
                ];
            })
            ->values();

        $receiptNoLocation = InternalMaterialReceipt::query()
            ->withCount('lines')
            ->withSum('lines as total_quantity', 'quantity')
            ->where('source', 'Phieu nhap thanh pham')
            ->where(function ($query) {
                $query->whereNull('location_code')
                    ->orWhere('location_code', '')
                    ->orWhere('location_code', 'CHUA-XEP');
            })
            ->orderByDesc('receipt_date')
            ->limit(100)
            ->get()
            ->map(function ($receipt) {
                return [
                    'id' => $receipt->id,
                    'receipt_code' => $receipt->receipt_code,
                    'receipt_date' => optional($receipt->receipt_date)->format('Y-m-d'),
                    'location_code' => $receipt->location_code ?: 'CHUA-XEP',
                    'lines_count' => (int) $receipt->lines_count,
                    'quantity' => (float) ($receipt->total_quantity ?? 0),
                    'print_url' => url('/client/nhap-thanh-pham-noi-bo/' . $receipt->id . '/in'),
                ];
            });

        return response()->json([
            'summary' => [
                'month' => $month->format('Y-m'),
                'negative_stock' => $negativeStock->count(),
                'unassigned_stock' => $unassignedStock->count(),
                'missing_catalog' => $missingCatalog->count(),
                'multi_location' => $multiLocation->count(),
                'receipt_no_location' => $receiptNoLocation->count(),
            ],
            'data' => [
                'negative_stock' => $negativeStock,
                'unassigned_stock' => $unassignedStock,
                'missing_catalog' => $missingCatalog,
                'multi_location' => $multiLocation,
                'receipt_no_location' => $receiptNoLocation,
            ],
        ]);
    }

    public function dailyFlow(Request $request)
    {
        $days = min(max((int) $request->query('days', 7), 1), 31);
        $end = Carbon::parse($request->query('to', now()->format('Y-m-d')))->startOfDay();
        $start = $end->copy()->subDays($days - 1);

        $receipts = DB::connection('internal')->table('internal_material_receipts as r')
            ->join('internal_material_receipt_lines as l', 'l.receipt_id', '=', 'r.id')
            ->where('r.source', 'Phieu nhap thanh pham')
            ->whereBetween('r.receipt_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->select('r.receipt_date as date', DB::raw('SUM(l.quantity) as quantity'), DB::raw('COUNT(DISTINCT r.id) as document_count'))
            ->groupBy('r.receipt_date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->date)->format('Y-m-d'));

        $issues = DB::connection('internal')->table('internal_material_issues as i')
            ->join('internal_material_issue_lines as l', 'l.issue_id', '=', 'i.id')
            ->whereRaw("COALESCE(i.issue_type, 'material') <> 'production'")
            ->whereBetween('i.issue_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->select('i.issue_date as date', DB::raw('SUM(l.quantity) as quantity'), DB::raw('COUNT(DISTINCT i.id) as document_count'))
            ->groupBy('i.issue_date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->date)->format('Y-m-d'));

        $rows = collect();
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->format('Y-m-d');
            $receipt = $receipts->get($key);
            $issue = $issues->get($key);
            $rows->push([
                'date' => $key,
                'label' => $date->format('d/m'),
                'receipt_quantity' => (float) ($receipt->quantity ?? 0),
                'issue_quantity' => (float) ($issue->quantity ?? 0),
                'receipt_count' => (int) ($receipt->document_count ?? 0),
                'issue_count' => (int) ($issue->document_count ?? 0),
            ]);
        }

        return response()->json([
            'data' => $rows,
            'summary' => [
                'from_date' => $start->format('Y-m-d'),
                'to_date' => $end->format('Y-m-d'),
                'receipt_quantity' => (float) $rows->sum('receipt_quantity'),
                'issue_quantity' => (float) $rows->sum('issue_quantity'),
                'receipt_count' => (int) $rows->sum('receipt_count'),
                'issue_count' => (int) $rows->sum('issue_count'),
            ],
        ]);
    }

    public function exportStock(Request $request)
    {
        $month = Carbon::parse($request->query('month', now()->format('Y-m')) . '-01')->startOfMonth();
        $monthStart = $month->format('Y-m-d');
        $monthEnd = $month->copy()->endOfMonth()->format('Y-m-d');
        $filename = 'ton-kho-noi-bo-' . $month->format('Y-m') . '.csv';

        return response()->streamDownload(function () use ($monthStart, $monthEnd) {
            echo "\xEF\xBB\xBF";
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Vi tri', 'Ma ke toan', 'Ma noi bo', 'Size', 'Mau', 'Side', 'Ton dau', 'Nhap', 'Xuat', 'Ton cuoi']);

            app(InternalStockLedger::class)
                ->query($monthStart, $monthEnd)
                ->select(
                    'location_code',
                    DB::raw('ma_hh as ma_sp'),
                    'internal_item_code',
                    'size',
                    'color',
                    'side',
                    DB::raw('SUM(opening_quantity) as opening_quantity'),
                    DB::raw('SUM(receipt_quantity) as receipt_quantity'),
                    DB::raw('SUM(issue_quantity) as issue_quantity'),
                    DB::raw('SUM(opening_quantity + receipt_quantity - issue_quantity) as total_quantity')
                )
                ->groupBy('location_code', 'ma_hh', 'internal_item_code', 'size', 'color', 'side')
                ->orderBy('location_code')
                ->orderBy('internal_item_code')
                ->chunk(500, function ($rows) use ($out) {
                    foreach ($rows as $row) {
                        fputcsv($out, [
                            $row->location_code ?: 'CHUA-XEP',
                            $row->ma_sp,
                            $row->internal_item_code,
                            $row->size,
                            $row->color,
                            $row->side,
                            (float) $row->opening_quantity,
                            (float) $row->receipt_quantity,
                            (float) $row->issue_quantity,
                            (float) $row->total_quantity,
                        ]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function destroyOpeningStock(Request $request)
    {
        $data = $request->validate([
            'month' => 'required|date_format:Y-m',
            'warehouse_code' => 'nullable|string|max:50',
            'location_code' => 'nullable|string|max:100',
            'ma_hh' => 'required|string|max:100',
            'internal_item_code' => 'nullable|string|max:100',
            'size' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:100',
            'side' => 'nullable|string|max:100',
        ]);

        $monthStart = Carbon::parse($data['month'] . '-01')->startOfMonth()->format('Y-m-d');
        $keys = [
            'warehouse_code' => strtoupper(trim($data['warehouse_code'] ?? '')),
            'location_code' => strtoupper(trim($data['location_code'] ?? '')),
            'ma_hh' => strtoupper(trim($data['ma_hh'])),
            'internal_item_code' => trim($data['internal_item_code'] ?? ''),
            'size' => trim($data['size'] ?? ''),
            'color' => trim($data['color'] ?? ''),
            'side' => trim($data['side'] ?? ''),
        ];

        $hasReceipts = DB::connection('internal')->table('internal_material_receipt_lines as l')
            ->join('internal_material_receipts as r', 'r.id', '=', 'l.receipt_id')
            ->whereRaw("UPPER(COALESCE(r.warehouse_code, '')) = ?", [$keys['warehouse_code']])
            ->whereRaw("UPPER(COALESCE(l.location_code, r.location_code, '')) = ?", [$keys['location_code']])
            ->whereRaw("UPPER(l.ma_hh) = ?", [$keys['ma_hh']])
            ->whereRaw("COALESCE(l.internal_item_code, '') = ?", [$keys['internal_item_code']])
            ->whereRaw("COALESCE(l.size, '') = ?", [$keys['size']])
            ->whereRaw("COALESCE(l.color, '') = ?", [$keys['color']])
            ->exists();

        $hasIssues = DB::connection('internal')->table('internal_material_issue_lines as l')
            ->join('internal_material_issues as i', 'i.id', '=', 'l.issue_id')
            ->whereRaw("UPPER(COALESCE(i.warehouse_code, '')) = ?", [$keys['warehouse_code']])
            ->whereRaw("UPPER(COALESCE(l.location_code, '')) = ?", [$keys['location_code']])
            ->whereRaw("UPPER(l.ma_hh) = ?", [$keys['ma_hh']])
            ->whereRaw("COALESCE(l.internal_item_code, '') = ?", [$keys['internal_item_code']])
            ->whereRaw("COALESCE(l.size, '') = ?", [$keys['size']])
            ->whereRaw("COALESCE(l.color, '') = ?", [$keys['color']])
            ->exists();

        if ($hasReceipts || $hasIssues) {
            return response()->json([
                'message' => 'Dòng tồn đã có phát sinh phiếu nhập/xuất. Hãy xóa chứng từ nguồn trong danh sách phiếu kho.',
            ], 422);
        }

        $openingRows = InternalOpeningStock::query()
            ->whereDate('period_month', $monthStart)
            ->where('warehouse_code', $keys['warehouse_code'])
            ->where('location_code', $keys['location_code'])
            ->whereRaw('UPPER(ma_hh) = ?', [$keys['ma_hh']])
            ->where('internal_item_code', $keys['internal_item_code'])
            ->where('size', $keys['size'])
            ->where('color', $keys['color'])
            ->where('side', $keys['side'])
            ->get();

        if ($openingRows->isEmpty()) {
            return response()->json([
                'message' => 'Không tìm thấy dòng tồn đầu nội bộ để xóa.',
            ], 404);
        }

        DB::connection('internal')->transaction(function () use ($openingRows) {
            foreach ($openingRows as $opening) {
                $package = $opening->inventory_package_id
                    ? InventoryPackage::query()->lockForUpdate()->find($opening->inventory_package_id)
                    : null;

                $opening->delete();

                if (!$package || $package->receiptLines()->exists()) {
                    continue;
                }

                $count = $package->inventory_count_id
                    ? InternalInventoryCount::query()->lockForUpdate()->find($package->inventory_count_id)
                    : null;
                $quantity = (float) $package->quantity;
                $package->delete();

                if (!$count) {
                    continue;
                }

                $remainingQuantity = (float) $count->counted_quantity - $quantity;
                $hasPackages = InventoryPackage::query()
                    ->where('inventory_count_id', $count->id)
                    ->exists();

                if ($remainingQuantity <= 0 && !$hasPackages) {
                    $count->delete();
                } else {
                    $count->counted_quantity = max(0, $remainingQuantity);
                    $count->save();
                }
            }
        });

        return response()->json([
            'message' => 'Đã xóa dòng tồn đầu nội bộ.',
        ]);
    }

    public function assignAccountingCode(Request $request)
    {
        $data = $request->validate([
            'internal_item_code' => 'required|string|max:100',
            'ma_hh' => 'required|string|max:100',
        ]);

        $internalCode = trim($data['internal_item_code']);
        $accountingCode = strtoupper(trim($data['ma_hh']));

        $catalogExists = DB::connection('sqlsrv')
            ->table('TSoft_NhanTG_kt_new.dbo.CodeHanghoa')
            ->where('Ma_hh', $accountingCode)
            ->exists();

        if (!$catalogExists) {
            return response()->json([
                'message' => 'Mã kế toán không tồn tại trong danh mục TSoft kế toán.',
            ], 422);
        }

        $updated = DB::connection('internal')->transaction(function () use ($internalCode, $accountingCode) {
            $packageIds = InventoryPackage::query()
                ->where('internal_item_code', $internalCode)
                ->where(function ($query) {
                    $query->whereNull('ma_sp')->orWhere('ma_sp', '');
                })
                ->pluck('id');

            $countRows = InternalInventoryCount::query()
                ->where('internal_item_code', $internalCode)
                ->where(function ($query) {
                    $query->whereNull('ma_sp')->orWhere('ma_sp', '');
                })
                ->lockForUpdate()
                ->get();

            foreach ($countRows as $count) {
                $target = InternalInventoryCount::query()
                    ->where('ma_sp', $accountingCode)
                    ->where('ma_ko', $count->ma_ko)
                    ->where('internal_item_code', $count->internal_item_code)
                    ->where('size', $count->size)
                    ->where('color', $count->color)
                    ->where('side', $count->side)
                    ->whereDate('checked_at', optional($count->checked_at)->format('Y-m-d'))
                    ->lockForUpdate()
                    ->first();

                if ($target) {
                    InventoryPackage::query()
                        ->where('inventory_count_id', $count->id)
                        ->update(['inventory_count_id' => $target->id, 'ma_sp' => $accountingCode]);
                    $target->counted_quantity = (float) $target->counted_quantity + (float) $count->counted_quantity;
                    $target->save();
                    $count->delete();
                } else {
                    $count->ma_sp = $accountingCode;
                    $count->save();
                }
            }

            InventoryPackage::query()
                ->whereIn('id', $packageIds)
                ->update(['ma_sp' => $accountingCode]);

            InternalOpeningStock::query()
                ->where('internal_item_code', $internalCode)
                ->where(function ($query) {
                    $query->whereNull('ma_hh')->orWhere('ma_hh', '');
                })
                ->update(['ma_hh' => $accountingCode]);

            $receiptLines = DB::connection('internal')->table('internal_material_receipt_lines')
                ->where('internal_item_code', $internalCode)
                ->where(function ($query) {
                    $query->whereNull('ma_hh')->orWhere('ma_hh', '');
                })
                ->update(['ma_hh' => $accountingCode, 'updated_at' => now()]);

            return $packageIds->count() + $receiptLines;
        });

        return response()->json([
            'message' => 'Đã gán mã kế toán cho mã nội bộ.',
            'updated' => $updated,
        ]);
    }

    public function assignStockLocation(Request $request)
    {
        $data = $request->validate([
            'month' => 'required|date_format:Y-m',
            'warehouse_code' => 'nullable|string|max:50',
            'location_code' => 'nullable|string|max:100',
            'target_location_code' => 'required|string|max:100',
            'ma_hh' => 'nullable|string|max:100',
            'internal_item_code' => 'nullable|string|max:100',
            'size' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:100',
            'side' => 'nullable|string|max:100',
        ]);

        $month = Carbon::parse($data['month'] . '-01')->startOfMonth();
        $monthStart = $month->format('Y-m-d');
        $monthEnd = $month->copy()->endOfMonth()->format('Y-m-d');
        $keys = [
            'warehouse_code' => strtoupper(trim($data['warehouse_code'] ?? '')),
            'location_code' => strtoupper(trim($data['location_code'] ?? '')),
            'target_location_code' => strtoupper(trim($data['target_location_code'])),
            'ma_hh' => strtoupper(trim($data['ma_hh'] ?? '')),
            'internal_item_code' => trim($data['internal_item_code'] ?? ''),
            'size' => trim($data['size'] ?? ''),
            'color' => trim($data['color'] ?? ''),
            'side' => trim($data['side'] ?? ''),
        ];

        if ($keys['target_location_code'] === '') {
            return response()->json(['message' => 'Chọn vị trí cần gán.'], 422);
        }

        $targetLocation = WarehouseLocation::query()->firstOrCreate(
            ['location_code' => $keys['target_location_code']],
            [
                'warehouse_code' => '',
                'shelf_code' => $this->inferShelfCode($keys['target_location_code']),
                'tier' => $this->inferTierFromLocationCode($keys['target_location_code']) ?: 1,
                'bay_code' => $this->inferBayCode($keys['target_location_code']) ?: null,
                'grid_x' => 1,
                'grid_y' => 1,
                'grid_w' => 4,
                'grid_h' => 2,
                'location_name' => $keys['target_location_code'],
                'status' => 'pending',
            ]
        );

        $hasIssues = DB::connection('internal')->table('internal_material_issue_lines as l')
            ->join('internal_material_issues as i', 'i.id', '=', 'l.issue_id')
            ->whereBetween('i.issue_date', [$monthStart, $monthEnd])
            ->whereRaw("UPPER(COALESCE(i.warehouse_code, '')) = ?", [$keys['warehouse_code']])
            ->whereRaw("UPPER(COALESCE(l.location_code, '')) = ?", [$keys['location_code']])
            ->whereRaw("UPPER(COALESCE(l.ma_hh, '')) = ?", [$keys['ma_hh']])
            ->whereRaw("COALESCE(l.internal_item_code, '') = ?", [$keys['internal_item_code']])
            ->whereRaw("COALESCE(l.size, '') = ?", [$keys['size']])
            ->whereRaw("COALESCE(l.color, '') = ?", [$keys['color']])
            ->exists();

        if ($hasIssues) {
            return response()->json([
                'message' => 'Dòng này đã có phiếu xuất trong tháng. Không tự đổi vị trí để tránh sai lịch sử xuất kho.',
            ], 422);
        }

        $updated = DB::connection('internal')->transaction(function () use ($keys, $monthStart, $monthEnd, $targetLocation) {
            $updated = 0;
            $movedPackageIds = [];

            $openingRows = InternalOpeningStock::query()
                ->whereDate('period_month', $monthStart)
                ->where('warehouse_code', $keys['warehouse_code'])
                ->where('location_code', $keys['location_code'])
                ->whereRaw("UPPER(COALESCE(ma_hh, '')) = ?", [$keys['ma_hh']])
                ->where('internal_item_code', $keys['internal_item_code'])
                ->where('size', $keys['size'])
                ->where('color', $keys['color'])
                ->where('side', $keys['side'])
                ->get();

            foreach ($openingRows as $opening) {
                if ($opening->inventory_package_id && !in_array((int) $opening->inventory_package_id, $movedPackageIds, true)) {
                    $package = InventoryPackage::query()->lockForUpdate()->find($opening->inventory_package_id);
                    if ($package) {
                        $this->movePackageRecord($package, $targetLocation);
                        $movedPackageIds[] = (int) $package->id;
                        $updated++;
                        continue;
                    }
                }

                $opening->location_code = $targetLocation->location_code;
                if ($targetLocation->warehouse_code) {
                    $opening->warehouse_code = $targetLocation->warehouse_code;
                }
                $opening->save();
                $updated++;
            }

            $receiptLines = DB::connection('internal')->table('internal_material_receipt_lines as l')
                ->join('internal_material_receipts as r', 'r.id', '=', 'l.receipt_id')
                ->whereBetween('r.receipt_date', [$monthStart, $monthEnd])
                ->where('r.source', 'Phieu nhap thanh pham')
                ->whereRaw("UPPER(COALESCE(r.warehouse_code, '')) = ?", [$keys['warehouse_code']])
                ->whereRaw("UPPER(COALESCE(l.location_code, r.location_code, '')) = ?", [$keys['location_code']])
                ->whereRaw("UPPER(COALESCE(l.ma_hh, '')) = ?", [$keys['ma_hh']])
                ->whereRaw("COALESCE(l.internal_item_code, '') = ?", [$keys['internal_item_code']])
                ->whereRaw("COALESCE(l.size, '') = ?", [$keys['size']])
                ->whereRaw("COALESCE(l.color, '') = ?", [$keys['color']])
                ->whereRaw("COALESCE(l.side, '') = ?", [$keys['side']])
                ->select('l.id', 'l.inventory_package_id')
                ->get();

            foreach ($receiptLines as $line) {
                if ($line->inventory_package_id && !in_array((int) $line->inventory_package_id, $movedPackageIds, true)) {
                    $package = InventoryPackage::query()->lockForUpdate()->find($line->inventory_package_id);
                    if ($package) {
                        $this->movePackageRecord($package, $targetLocation);
                        $movedPackageIds[] = (int) $package->id;
                        $updated++;
                        continue;
                    }
                }

                DB::connection('internal')->table('internal_material_receipt_lines')
                    ->where('id', $line->id)
                    ->update(['location_code' => $targetLocation->location_code]);
                $updated++;
            }

            return $updated;
        });

        app(InternalAudit::class)->record('stock.location_assigned', 'InternalStockRow', null, $targetLocation->location_code, [
            'source' => $keys,
            'target_location_code' => $targetLocation->location_code,
            'updated' => $updated,
        ], $request);

        return response()->json([
            'message' => $updated > 0 ? 'Đã gán vị trí cho dòng tồn nội bộ.' : 'Không tìm thấy dòng tồn nội bộ phù hợp để gán vị trí.',
            'updated' => $updated,
            'location_code' => $targetLocation->location_code,
        ], $updated > 0 ? 200 : 404);
    }

    public function locationContents(Request $request)
    {
        $locationCode = strtoupper(trim((string) $request->query('location_code')));
        $data = InventoryPackage::query()
            ->join('warehouse_locations as wl', 'inventory_packages.warehouse_location_id', '=', 'wl.id')
            ->join('inventory_counts as ic', 'inventory_packages.inventory_count_id', '=', 'ic.id')
            ->select(
                'inventory_packages.internal_item_code',
                'inventory_packages.ma_sp',
                'inventory_packages.size',
                'inventory_packages.color',
                'inventory_packages.side',
                DB::raw('SUM(inventory_packages.quantity) as total_quantity'),
                DB::raw('COUNT(*) as package_count'),
                DB::raw('MAX(inventory_packages.checked_at) as latest_checked_at')
            )
            ->where('wl.location_code', $locationCode)
            ->where('inventory_packages.quantity', '>', 0)
            ->when($request->filled('checked_at'), function ($query) use ($request) {
                $query->whereDate('inventory_packages.checked_at', $request->query('checked_at'));
            })
            ->groupBy(
                'inventory_packages.internal_item_code',
                'inventory_packages.ma_sp',
                'inventory_packages.size',
                'inventory_packages.color',
                'inventory_packages.side'
            )
            ->orderBy('inventory_packages.internal_item_code')
            ->get();

        $catalogsByItemCode = InternalItemCatalog::query()
            ->where('is_active', true)
            ->whereIn('item_code', $data->pluck('internal_item_code')->filter()->unique()->values())
            ->get()
            ->groupBy(fn ($item) => mb_strtoupper(trim((string) $item->item_code)))
            ->map(function ($rows) {
                return $rows->sortByDesc(fn ($item) => trim((string) $item->image_url) !== '' ? 1 : 0)->first();
            });
        $matcher = app(PantoneColorMatcher::class);
        $data = $data->map(function ($row) use ($catalogsByItemCode, $matcher) {
            $catalog = $catalogsByItemCode->get(mb_strtoupper(trim((string) $row->internal_item_code)));
            $match = $matcher->matchValues([
                $row->internal_item_code,
                $row->ma_sp,
                $row->size,
                $row->color,
                $row->side,
            ], $catalog);
            $row->pantone_code = $match['pantone'];
            $row->pantone_hex = $match['hex'];
            $row->color_name = $match['name'] ?? '';
            $row->pantone_source = $match['source'];
            $row->catalog_only = false;
            $row->catalog_item_name = $catalog->item_name ?? '';
            $row->catalog_unit = $catalog->unit ?? '';
            $row->catalog_shelf_code = $catalog->shelf_code ?? '';
            $row->image_url = trim((string) ($catalog->image_url ?? ''));
            $row->norms = $this->catalogDisplayNorms($catalog);
            return $row;
        })
            ->groupBy(fn ($row) => mb_strtoupper(trim((string) ($row->internal_item_code ?: $row->ma_sp))))
            ->map(function ($rows) use ($matcher) {
                $first = $rows->first();
                $variants = $rows->map(function ($row) {
                    return [
                        'ma_sp' => (string) $row->ma_sp,
                        'size' => (string) $row->size,
                        'color' => (string) $row->color,
                        'side' => (string) $row->side,
                        'quantity' => (float) $row->total_quantity,
                        'package_count' => (int) $row->package_count,
                    ];
                })->values();
                $match = $matcher->matchValues([
                    $first->internal_item_code,
                    $first->ma_sp,
                    $first->color,
                    $first->size,
                    $first->side,
                    $first->catalog_item_name,
                ]);

                return (object) [
                    'internal_item_code' => (string) $first->internal_item_code,
                    'ma_sp' => $rows->pluck('ma_sp')->filter()->unique()->implode(', '),
                    'size' => $rows->pluck('size')->filter()->unique()->implode(', '),
                    'color' => $rows->pluck('color')->filter()->unique()->implode(', '),
                    'side' => $rows->pluck('side')->filter()->unique()->implode(', '),
                    'total_quantity' => (float) $rows->sum('total_quantity'),
                    'package_count' => (int) $rows->sum('package_count'),
                    'latest_checked_at' => $rows->max('latest_checked_at'),
                    'pantone_code' => $first->pantone_code ?: $match['pantone'],
                    'pantone_hex' => $first->pantone_hex ?: $match['hex'],
                    'color_name' => $first->color_name ?: ($match['name'] ?? ''),
                    'pantone_source' => $first->pantone_source ?: $match['source'],
                    'catalog_only' => false,
                    'catalog_item_name' => (string) $first->catalog_item_name,
                    'catalog_unit' => (string) $first->catalog_unit,
                    'catalog_shelf_code' => (string) $first->catalog_shelf_code,
                    'image_url' => (string) $first->image_url,
                    'norms' => $first->norms ?: [],
                    'variants' => $variants,
                ];
            })
            ->values();

        $existingKeys = $data->mapWithKeys(function ($row) {
            return [mb_strtoupper(trim((string) $row->internal_item_code)) => true];
        });

        $catalogRows = InternalItemCatalog::query()
            ->where('is_active', true)
            ->whereNotNull('shelf_code')
            ->where('shelf_code', '<>', '')
            ->orderBy('item_code')
            ->get()
            ->filter(function ($item) use ($locationCode) {
                $shelves = preg_split('/[,;|\n\r]+/', (string) $item->shelf_code) ?: [];
                return collect($shelves)->contains(function ($shelf) use ($locationCode) {
                    return strtoupper(trim((string) $shelf)) === $locationCode;
                });
            })
            ->map(function ($item) use ($matcher) {
                $match = $matcher->matchCatalog($item);
                return (object) [
                    'internal_item_code' => trim((string) $item->item_code),
                    'ma_sp' => '',
                    'size' => trim((string) $item->size),
                    'color' => trim((string) $item->color),
                    'side' => trim((string) $item->side),
                    'total_quantity' => 0,
                    'package_count' => 0,
                    'latest_checked_at' => null,
                    'pantone_code' => $match['pantone'],
                    'pantone_hex' => $match['hex'],
                    'color_name' => $match['name'] ?? '',
                    'pantone_source' => $match['source'],
                    'catalog_only' => true,
                    'catalog_item_name' => trim((string) $item->item_name),
                    'catalog_unit' => trim((string) $item->unit),
                    'catalog_shelf_code' => trim((string) $item->shelf_code),
                    'image_url' => trim((string) $item->image_url),
                    'norms' => $this->catalogDisplayNorms($item),
                    'variants' => [],
                ];
            })
            ->groupBy(fn ($row) => mb_strtoupper(trim((string) $row->internal_item_code)))
            ->map(function ($rows) {
                return $rows->sortByDesc(fn ($row) => trim((string) $row->image_url) !== '' ? 1 : 0)->first();
            })
            ->filter(function ($row) use ($existingKeys) {
                return trim((string) $row->internal_item_code) !== ''
                    && !$existingKeys->has(mb_strtoupper(trim((string) $row->internal_item_code)));
            })
            ->values();

        $data = $data->concat($catalogRows)
            ->sortBy(fn ($row) => mb_strtoupper(trim((string) ($row->internal_item_code ?: $row->catalog_item_name))))
            ->values();

        return response()->json([
            'data' => $data,
            'summary' => [
                'item_count' => $data->pluck('internal_item_code')->filter()->unique()->count(),
                'package_count' => $data->sum('package_count'),
                'total_quantity' => $data->sum('total_quantity'),
            ],
        ]);
    }

    public function voiceLookup(Request $request)
    {
        $keyword = strtoupper(trim((string) $request->query('keyword', '')));
        $keyword = preg_replace('/\s+/', '', $keyword);

        if ($keyword === '' || strlen($keyword) < 2) {
            return response()->json([
                'message' => 'Hãy nói hoặc nhập mã hàng cần tìm.',
                'data' => [],
            ], 422);
        }

        $data = InventoryPackage::query()
            ->join('warehouse_locations as wl', 'inventory_packages.warehouse_location_id', '=', 'wl.id')
            ->select(
                'inventory_packages.ma_sp',
                'inventory_packages.internal_item_code',
                'inventory_packages.size',
                'inventory_packages.color',
                'wl.location_code',
                'wl.warehouse_code',
                DB::raw('SUM(inventory_packages.quantity) as total_quantity'),
                DB::raw('COUNT(inventory_packages.id) as package_count')
            )
            ->where('inventory_packages.quantity', '>', 0)
            ->where(function ($query) use ($keyword) {
                $query->whereRaw("UPPER(REPLACE(inventory_packages.ma_sp, ' ', '')) LIKE ?", ['%' . $keyword . '%'])
                    ->orWhereRaw("UPPER(REPLACE(inventory_packages.internal_item_code, ' ', '')) LIKE ?", ['%' . $keyword . '%']);
            })
            ->groupBy(
                'inventory_packages.ma_sp',
                'inventory_packages.internal_item_code',
                'inventory_packages.size',
                'inventory_packages.color',
                'wl.location_code',
                'wl.warehouse_code'
            )
            ->orderBy('wl.location_code')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $data,
            'summary' => [
                'keyword' => $keyword,
                'item_count' => $data->count(),
                'location_count' => $data->pluck('location_code')->filter()->unique()->count(),
                'total_quantity' => (float) $data->sum('total_quantity'),
            ],
        ]);
    }

    public function assistantHealth()
    {
        return response()->json([
            'ok' => true,
            'service' => 'ttv-internal-warehouse',
            'mode' => 'read_only',
            'cache' => Cache::has('assistant_stock_snapshot') ? 'warm' : 'cold',
            'time' => now()->toDateTimeString(),
        ]);
    }

    public function assistantStock(Request $request)
    {
        $keyword = strtoupper(trim((string) $request->query('keyword', '')));
        $keyword = preg_replace('/\s+/', '', $keyword);
        $locationCode = strtoupper(trim((string) $request->query('location_code', '')));
        $limit = min(max((int) $request->query('limit', 10), 1), 30);

        $allData = $this->assistantFilterSnapshot($keyword, $locationCode)->values();
        $data = $allData->take($limit)->values();

        return response()->json($this->assistantStockResponse($data, $keyword, $locationCode, $allData));
    }

    public function assistantLocation($locationCode)
    {
        $locationCode = strtoupper(trim((string) $locationCode));
        $limit = min(max((int) request()->query('limit', 15), 1), 30);
        $allData = $this->assistantFilterSnapshot('', $locationCode)->values();
        $data = $allData->take($limit)->values();

        return response()->json($this->assistantStockResponse($data, '', $locationCode, $allData));
    }

    public function assistantAsk(Request $request)
    {
        $text = strtoupper(trim((string) $request->query('text', $request->query('q', ''))));
        $normalized = preg_replace('/\s+/', '', $text);
        $plainText = $this->assistantNormalizeText($text);

        if ($normalized === '') {
            return response()->json([
                'ok' => false,
                'answer' => 'Bạn hỏi mã hàng hoặc vị trí, ví dụ: "Mã 8017465-B nằm đâu?" hoặc "Kệ A1 có gì?"',
                'data' => [],
            ], 422);
        }

        if (
            str_contains($plainText, 'PHIEU NHAP MOI')
            || str_contains($plainText, 'VUA MOI NHAP')
            || str_contains($plainText, 'MOI NHAP KHO')
            || (str_contains($plainText, 'NHAP KHO') && str_contains($plainText, 'MA GI'))
            || (str_contains($plainText, 'NHAP KHO') && str_contains($plainText, 'LUC NAO'))
        ) {
            return $this->assistantLatestReceipts($request);
        }

        $locations = $this->assistantStockSnapshot()->pluck('location_code')->filter()->unique()->values();
        preg_match_all('/\b[A-Z]{1,5}[-_]?\d{1,5}\b/u', $text, $matches);
        $locationCode = collect($matches[0] ?? [])
            ->map(fn ($value) => strtoupper(trim($value)))
            ->first(fn ($value) => $locations->contains($value));

        if ($locationCode) {
            return $this->assistantLocation($locationCode);
        }

        preg_match_all('/[A-Z0-9][A-Z0-9._-]{1,}/u', $text, $tokenMatches);
        $tokens = collect($tokenMatches[0] ?? [])
            ->map(fn ($value) => strtoupper(trim($value)))
            ->filter()
            ->values();
        $tokenLocation = $tokens->first(fn ($value) => $locations->contains($value));
        if ($tokenLocation) {
            return $this->assistantLocation($tokenLocation);
        }

        $stopWords = ['TON', 'TON', 'MA', 'MA', 'HANG', 'HANG', 'KE', 'KE', 'VI', 'VI', 'TRI', 'TRI', 'NAM', 'NAM', 'DAU', 'DAU', 'CON', 'CON', 'BAO', 'NHIEU', 'NHIEU', 'KIEM', 'KIEM', 'TRA'];
        $keyword = null;
        for ($index = 0; $index < $tokens->count() - 1; $index++) {
            $left = $tokens[$index];
            $right = $tokens[$index + 1];
            if (preg_match('/^[A-Z]{1,6}$/', $left) && preg_match('/^\d{1,6}$/', $right)) {
                $keyword = $left . $right;
                break;
            }
        }

        $keyword = $keyword ?: $tokens
            ->reject(fn ($value) => in_array($this->assistantNormalizeText($value), $stopWords, true))
            ->first(fn ($value) => preg_match('/\d/', $value) || strlen($value) >= 4);

        if (!$keyword) {
            $keyword = preg_replace('/^(TON|TN|MA|M�|HANG|H�NG|KE|K|VI|V|TRI|TR�)+/u', '', $normalized);
        }
        $request->query->set('keyword', $keyword ?: $normalized);

        return $this->assistantStock($request);
    }

    public function assistantChat(Request $request)
    {
        $data = $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $message = trim($data['message']);
        if ($this->assistantShouldUseInventoryApi($message)) {
            $request->query->set('text', $message);
            $request->query->set('limit', $request->query('limit', 3));

            return $this->assistantAsk($request);
        }

        $model = env('ASSISTANT_AI_MODEL', 'llama3.2:3b');
        $url = rtrim(env('ASSISTANT_AI_URL', 'http://127.0.0.1:11434'), '/') . '/api/chat';

        try {
            $response = Http::timeout(70)->post($url, [
                'model' => $model,
                'stream' => false,
                'keep_alive' => '30m',
                'options' => [
                    'temperature' => 0.2,
                    'num_predict' => 120,
                ],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => implode("\n", [
                            'Ban la tro ly kho noi bo TTV.',
                            'Tra loi ngan gon bang tieng Viet, toi da 4 dong, khong markdown dai dong.',
                            'Nguoi dung la thu kho, uu tien cac buoc lam ngay trong kho may mac.',
                            'Khong duoc huong dan sua/xoa/ghi du lieu TSoft.',
                            'Neu cau hoi can ton kho, vi tri, phieu nhap moi nhat, hay noi nguoi dung hoi ro ma hang hoac ke de he thong tra cuu API noi bo.',
                        ]),
                    ],
                    [
                        'role' => 'user',
                        'content' => $message,
                    ],
                ],
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'ok' => false,
                    'answer' => 'Ollama dang khong tra loi duoc. Kiem tra ollama serve/model ' . $model . '.',
                ], 502);
            }

            $payload = $response->json();
            $answer = trim((string) data_get($payload, 'message.content', ''));

            return response()->json([
                'ok' => true,
                'answer' => $answer !== '' ? $answer : 'Ollama khong co noi dung tra loi.',
                'source' => 'ollama',
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'ok' => false,
                'answer' => 'Khong ket noi duoc Ollama local. Hay mo Ollama va thu lai.',
            ], 502);
        }
    }

    public function assistantLatestReceipts(Request $request)
    {
        $limit = min(max((int) $request->query('limit', 1), 1), 5);

        $receipts = InternalMaterialReceipt::query()
            ->with(['lines' => function ($query) {
                $query->orderBy('id');
            }])
            ->where('source', 'Phieu nhap thanh pham')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        if ($receipts->isEmpty()) {
            return response()->json([
                'ok' => true,
                'answer' => 'Chưa thấy phiếu nhập thành phẩm nội bộ nào.',
                'data' => [],
            ]);
        }

        $answers = $receipts->map(function ($receipt) {
            $time = $receipt->created_at
                ? Carbon::parse($receipt->created_at)->format('H:i d/m/Y')
                : optional($receipt->receipt_date)->format('d/m/Y');

            $lines = $receipt->lines
                ->filter(fn ($line) => (float) $line->quantity > 0 || $line->internal_item_code || $line->ma_hh || $line->ten_hh)
                ->take(5)
                ->map(function ($line) {
                    $code = $line->internal_item_code ?: $line->ma_hh ?: $line->ten_hh ?: 'khong ma';
                    $parts = array_filter([
                        $code,
                        $line->size ? 'size ' . $line->size : null,
                        $line->color ? 'mau ' . $line->color : null,
                        $line->location_code ? 'ke ' . $line->location_code : null,
                        'SL ' . $this->assistantFormatQuantity((float) $line->quantity),
                    ]);

                    return implode(' - ', $parts);
                })
                ->values();

            $lineCount = $receipt->lines->count();
            $lineText = $lines->isNotEmpty() ? $lines->implode('; ') : 'chua co dong hang';

            return 'Phiếu ' . $receipt->receipt_code . ' lúc ' . $time . ', ' . $lineCount . ' dòng: ' . $lineText;
        });

        return response()->json([
            'ok' => true,
            'answer' => $answers->implode(' | '),
            'data' => $receipts->map(function ($receipt) {
                return [
                    'receipt_code' => $receipt->receipt_code,
                    'receipt_date' => optional($receipt->receipt_date)->format('Y-m-d'),
                    'created_at' => optional($receipt->created_at)->toDateTimeString(),
                    'location_code' => $receipt->location_code,
                    'line_count' => $receipt->lines->count(),
                    'lines' => $receipt->lines->map(function ($line) {
                        return [
                            'internal_item_code' => $line->internal_item_code,
                            'ma_hh' => $line->ma_hh,
                            'ten_hh' => $line->ten_hh,
                            'size' => $line->size,
                            'color' => $line->color,
                            'side' => $line->side,
                            'quantity' => (float) $line->quantity,
                            'location_code' => $line->location_code,
                            'note' => $line->note,
                        ];
                    })->values(),
                ];
            })->values(),
        ]);
    }

    private function assistantStockBaseQuery()
    {
        return InventoryPackage::query()
            ->join('warehouse_locations as wl', 'inventory_packages.warehouse_location_id', '=', 'wl.id')
            ->select(
                'wl.location_code',
                'inventory_packages.ma_sp',
                'inventory_packages.internal_item_code',
                'inventory_packages.size',
                'inventory_packages.color',
                'inventory_packages.side',
                'inventory_packages.ma_ko as warehouse_code',
                DB::raw('SUM(inventory_packages.quantity) as quantity'),
                DB::raw('COUNT(inventory_packages.id) as package_count')
            )
            ->where('inventory_packages.quantity', '>', 0)
            ->groupBy(
                'wl.location_code',
                'inventory_packages.ma_sp',
                'inventory_packages.internal_item_code',
                'inventory_packages.size',
                'inventory_packages.color',
                'inventory_packages.side',
                'inventory_packages.ma_ko'
            );
    }

    private function assistantStockSnapshot()
    {
        return Cache::remember('assistant_stock_snapshot', now()->addSeconds(15), function () {
            return $this->assistantStockBaseQuery()
                ->orderBy('wl.location_code')
                ->orderBy('inventory_packages.internal_item_code')
                ->get();
        });
    }

    private function assistantFilterSnapshot(string $keyword = '', string $locationCode = '')
    {
        $keyword = strtoupper(trim($keyword));
        $locationCode = strtoupper(trim($locationCode));

        return $this->assistantStockSnapshot()
            ->filter(function ($row) use ($keyword, $locationCode) {
                if ($locationCode !== '' && strtoupper((string) $row->location_code) !== $locationCode) {
                    return false;
                }

                if ($keyword === '') {
                    return true;
                }

                $haystack = strtoupper(str_replace(' ', '', implode(' ', [
                    $row->ma_sp,
                    $row->internal_item_code,
                    $row->size,
                    $row->color,
                    $row->side,
                    $row->location_code,
                ])));

                return str_contains($haystack, $keyword);
            })
            ->sortBy([
                ['location_code', 'asc'],
                ['internal_item_code', 'asc'],
            ])
            ->values();
    }

    private function assistantNormalizeText(string $text): string
    {
        return trim(preg_replace('/[^A-Z0-9]+/', ' ', strtoupper(Str::ascii($text))));
    }

    private function assistantShouldUseInventoryApi(string $message): bool
    {
        $text = $this->assistantNormalizeText($message);

        if (
            str_contains($text, 'PHIEU NHAP')
            || str_contains($text, 'MOI NHAP')
            || str_contains($text, 'VUA MOI NHAP')
        ) {
            return true;
        }

        if (preg_match('/\b(KE|VI TRI)\s+[A-Z]{0,5}\d{1,5}\b/', $text)) {
            return true;
        }

        if (
            preg_match('/\b(TON|MA|NAM DAU|O DAU|TIM)\s+[A-Z]{1,8}\s*\d{1,8}\b/', $text)
            || preg_match('/\b[A-Z]{1,8}\d{1,8}[A-Z0-9._-]*\b/', $text)
        ) {
            return true;
        }

        return false;
    }

    private function assistantFormatQuantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 3, ',', '.'), '0'), ',');
    }

    private function assistantStockResponse($data, string $keyword = '', string $locationCode = '', $allData = null): array
    {
        $allData = $allData ?: $data;
        $total = (float) $allData->sum('quantity');
        $shownTotal = $data->count();
        $fullTotal = $allData->count();
        $moreText = $fullTotal > $shownTotal ? '; còn ' . ($fullTotal - $shownTotal) . ' dòng nữa' : '';
        $lines = $data->map(function ($row) {
            $code = $row->internal_item_code ?: $row->ma_sp ?: 'không mã';
            $parts = array_filter([
                $code,
                $row->size ? 'size ' . $row->size : null,
                $row->color ? 'màu ' . $row->color : null,
                $row->side ? 'mặt ' . $row->side : null,
                'kệ ' . $row->location_code,
                'SL ' . $this->assistantFormatQuantity((float) $row->quantity),
            ]);

            return implode(' - ', $parts);
        })->values();

        if ($data->isEmpty()) {
            $target = $locationCode ?: $keyword;
            $answer = $target
                ? 'Khong thay ton noi bo cho ' . $target . '.'
                : 'Khong thay ton noi bo phu hop.';
        } elseif ($locationCode) {
            $answer = 'Ke ' . $locationCode . ' co ' . $fullTotal . ' dong, tong SL ' . $this->assistantFormatQuantity($total) . ': ' . $lines->implode('; ') . $moreText . '.';
        } else {
            $locations = $allData->pluck('location_code')->filter()->unique()->values()->implode(', ');
            $answer = 'Tim thay ' . $fullTotal . ' dong, tong SL ' . $this->assistantFormatQuantity($total) . '. Vi tri: ' . $locations . '. ' . $lines->implode('; ') . $moreText . '.';
        }

        return [
            'ok' => true,
            'answer' => $answer,
            'summary' => [
                'keyword' => $keyword,
                'location_code' => $locationCode,
                'line_count' => $fullTotal,
                'shown_count' => $shownTotal,
                'location_count' => $allData->pluck('location_code')->filter()->unique()->count(),
                'total_quantity' => $total,
            ],
            'data' => $data->values(),
        ];
    }

    public function storePackage(Request $request)
    {
        $data = $request->validate([
            'location_code' => 'nullable|string|max:100',
            'ma_sp' => 'nullable|string|max:100',
            'ma_ko' => 'nullable|string|max:50',
            'internal_item_code' => 'nullable|string|max:100',
            'size' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:100',
            'side' => 'nullable|string|max:100',
            'quantity' => 'required|numeric|min:0',
            'checked_at' => 'required|date',
            'entry_type' => 'nullable|in:opening,receipt',
            'note' => 'nullable|string|max:500',
        ]);
        $data = $this->normalizeDateFields($data, ['checked_at']);

        if (trim((string) ($data['internal_item_code'] ?? '')) === '') {
            return response()->json([
                'message' => 'Mã nội bộ là bắt buộc. Mã kế toán có thể gán sau.',
            ], 422);
        }

        $catalogValidator = app(InternalCatalogValidator::class);
        $catalogErrors = $catalogValidator->errorsForLines(collect([
            ['internal_item_code' => $data['internal_item_code'] ?? ''],
        ]));
        if (!empty($catalogErrors)) {
            return $catalogValidator->responseForErrors($catalogErrors);
        }

        $locationCode = strtoupper(trim($data['location_code'] ?? '')) ?: 'CHUA-XEP';
        $warehouseCode = '';
        $location = WarehouseLocation::query()->firstOrCreate(
            ['location_code' => $locationCode],
            [
                'warehouse_code' => $warehouseCode,
                'shelf_code' => $this->inferShelfCode($locationCode) ?: 'CX',
                'tier' => $this->inferTierFromLocationCode($locationCode) ?: 1,
                'bay_code' => $this->inferBayCode($locationCode),
                'grid_x' => 1,
                'grid_y' => 1,
                'grid_w' => 4,
                'grid_h' => 2,
                'location_name' => 'Chưa xếp vị trí',
                'note' => 'Vị trí tạm để ghi nhận tồn trước khi xếp kệ.',
            ]
        );

        $accountingCode = strtoupper(trim((string) ($data['ma_sp'] ?? '')));
        $catalogItem = $accountingCode !== ''
            ? DB::connection('sqlsrv')->table('TSoft_NhanTG_kt_new.dbo.CodeHanghoa')
                ->where('Ma_hh', $accountingCode)
                ->select('Ten_hh', 'Dvt')
                ->first()
            : null;
        $internalCatalogItem = InternalItemCatalog::query()
            ->where('is_active', true)
            ->whereRaw('UPPER(TRIM(item_code)) = ?', [mb_strtoupper(trim((string) $data['internal_item_code']))])
            ->first();

        $attributes = [
            'ma_sp' => $accountingCode,
            'ma_ko' => '',
            'internal_item_code' => trim($data['internal_item_code'] ?? ''),
            'size' => trim($data['size'] ?? ''),
            'color' => trim($data['color'] ?? ''),
            'side' => trim($data['side'] ?? ''),
            'checked_at' => $data['checked_at'],
        ];

        $entryType = $data['entry_type'] ?? 'opening';

        [$package, $receipt] = DB::connection('internal')->transaction(function () use ($attributes, $data, $location, $catalogItem, $internalCatalogItem, $entryType) {
            $count = InternalInventoryCount::query()->firstOrCreate(
                [
                    'ma_sp' => $attributes['ma_sp'],
                    'ma_ko' => $attributes['ma_ko'],
                    'internal_item_code' => $attributes['internal_item_code'],
                    'size' => $attributes['size'],
                    'color' => $attributes['color'],
                    'side' => $attributes['side'],
                    'checked_at' => $attributes['checked_at'],
                ],
                [
                    'counted_quantity' => 0,
                    'note' => $data['note'] ?? null,
                ]
            );

            $count->side = $attributes['side'];
            $count->counted_quantity = (float) $count->counted_quantity + (float) $data['quantity'];
            $count->note = $data['note'] ?? $count->note;
            $count->save();

            $package = InventoryPackage::query()->create(array_merge($attributes, [
                'package_code' => $this->nextPackageCode(),
                'warehouse_location_id' => $location->id,
                'inventory_count_id' => $count->id,
                'quantity' => $data['quantity'],
                'note' => $data['note'] ?? null,
            ]));

            $location->status = 'counting';
            $location->save();

            $receipt = null;

            if ($entryType === 'opening') {
                InternalOpeningStock::query()->create([
                    'inventory_package_id' => $package->id,
                    'period_month' => Carbon::parse($attributes['checked_at'])->startOfMonth()->format('Y-m-d'),
                    'warehouse_code' => $attributes['ma_ko'],
                    'location_code' => $location->location_code,
                    'ma_hh' => $attributes['ma_sp'],
                    'internal_item_code' => $attributes['internal_item_code'],
                    'size' => $attributes['size'],
                    'color' => $attributes['color'],
                    'side' => $attributes['side'],
                    'quantity' => $data['quantity'],
                    'note' => $data['note'] ?? null,
                ]);
            } else {
                $receipt = InternalMaterialReceipt::query()->create([
                    'receipt_code' => $this->nextMaterialReceiptCode(),
                    'receipt_date' => $attributes['checked_at'],
                    'warehouse_code' => $attributes['ma_ko'],
                    'location_code' => $location->location_code,
                    'receiver_name' => '',
                    'source' => 'Phieu nhap thanh pham',
                    'status' => 'posted',
                    'note' => $data['note'] ?? null,
                ]);

                $receipt->lines()->create([
                    'inventory_package_id' => $package->id,
                    'ma_hh' => $attributes['ma_sp'],
                    'ten_hh' => $internalCatalogItem->item_name ?? ($catalogItem->Ten_hh ?? ''),
                    'dvt' => $internalCatalogItem->unit ?? ($catalogItem->Dvt ?? ''),
                    'quantity' => $data['quantity'],
                    'location_code' => $location->location_code,
                    'internal_item_code' => $attributes['internal_item_code'],
                    'size' => $attributes['size'],
                    'color' => $attributes['color'],
                    'side' => $attributes['side'],
                    'note' => $data['note'] ?? null,
                ]);
            }

            return [$package, $receipt];
        });

        return response()->json([
            'message' => 'Đã lưu kiện nội bộ.',
            'data' => $package->load('location:id,location_code'),
            'print_url' => url('/client/kiem-ton-kho/tem-kien/' . $package->id),
            'receipt_print_url' => $receipt ? url('/client/nhap-thanh-pham-noi-bo/' . $receipt->id . '/in') : null,
        ]);
    }

    public function updatePackage(Request $request, InventoryPackage $inventoryPackage)
    {
        $data = $request->validate([
            'location_code' => 'required|string|max:100',
            'ma_sp' => 'nullable|string|max:100',
            'internal_item_code' => 'required|string|max:100',
            'size' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:100',
            'side' => 'nullable|string|max:100',
            'quantity' => 'required|numeric|min:0',
            'checked_at' => 'required|date',
            'note' => 'nullable|string|max:500',
        ]);
        $data = $this->normalizeDateFields($data, ['checked_at']);

        $catalogValidator = app(InternalCatalogValidator::class);
        $catalogErrors = $catalogValidator->errorsForLines(collect([
            ['internal_item_code' => $data['internal_item_code'] ?? ''],
        ]));
        if (!empty($catalogErrors)) {
            return $catalogValidator->responseForErrors($catalogErrors);
        }

        $locationCode = strtoupper(trim((string) $data['location_code'])) ?: 'CHUA-XEP';
        $location = WarehouseLocation::query()->firstOrCreate(
            ['location_code' => $locationCode],
            [
                'warehouse_code' => '',
                'shelf_code' => $this->inferShelfCode($locationCode) ?: 'CX',
                'tier' => $this->inferTierFromLocationCode($locationCode) ?: 1,
                'bay_code' => $this->inferBayCode($locationCode),
                'grid_x' => 1,
                'grid_y' => 1,
                'grid_w' => 4,
                'grid_h' => 2,
                'location_name' => $locationCode === 'CHUA-XEP' ? 'Chua xep vi tri' : 'Ke ' . $locationCode,
            ]
        );

        $newAttributes = [
            'ma_sp' => strtoupper(trim((string) ($data['ma_sp'] ?? ''))),
            'ma_ko' => '',
            'internal_item_code' => trim((string) $data['internal_item_code']),
            'size' => trim((string) ($data['size'] ?? '')),
            'color' => trim((string) ($data['color'] ?? '')),
            'side' => trim((string) ($data['side'] ?? '')),
            'checked_at' => $data['checked_at'],
        ];
        $newQuantity = (float) $data['quantity'];

        DB::connection('internal')->transaction(function () use ($inventoryPackage, $location, $locationCode, $newAttributes, $newQuantity, $data) {
            $oldCount = $inventoryPackage->inventory_count_id
                ? InternalInventoryCount::query()->lockForUpdate()->find($inventoryPackage->inventory_count_id)
                : null;

            if ($oldCount) {
                $remainingQuantity = (float) $oldCount->counted_quantity - (float) $inventoryPackage->quantity;
                $hasOtherPackages = InventoryPackage::query()
                    ->where('inventory_count_id', $oldCount->id)
                    ->where('id', '!=', $inventoryPackage->id)
                    ->exists();

                if ($remainingQuantity <= 0 && !$hasOtherPackages) {
                    $oldCount->delete();
                } else {
                    $oldCount->counted_quantity = max(0, $remainingQuantity);
                    $oldCount->save();
                }
            }

            $newCount = InternalInventoryCount::query()->firstOrCreate(
                [
                    'ma_sp' => $newAttributes['ma_sp'],
                    'ma_ko' => $newAttributes['ma_ko'],
                    'internal_item_code' => $newAttributes['internal_item_code'],
                    'size' => $newAttributes['size'],
                    'color' => $newAttributes['color'],
                    'side' => $newAttributes['side'],
                    'checked_at' => $newAttributes['checked_at'],
                ],
                [
                    'counted_quantity' => 0,
                    'note' => $data['note'] ?? null,
                ]
            );
            $newCount->counted_quantity = (float) $newCount->counted_quantity + $newQuantity;
            $newCount->note = $data['note'] ?? $newCount->note;
            $newCount->save();

            $inventoryPackage->fill(array_merge($newAttributes, [
                'warehouse_location_id' => $location->id,
                'inventory_count_id' => $newCount->id,
                'quantity' => $newQuantity,
                'note' => $data['note'] ?? null,
            ]));
            $inventoryPackage->save();

            InternalOpeningStock::query()
                ->where('inventory_package_id', $inventoryPackage->id)
                ->update([
                    'period_month' => Carbon::parse($newAttributes['checked_at'])->startOfMonth()->format('Y-m-d'),
                    'warehouse_code' => $newAttributes['ma_ko'],
                    'location_code' => $locationCode,
                    'ma_hh' => $newAttributes['ma_sp'],
                    'internal_item_code' => $newAttributes['internal_item_code'],
                    'size' => $newAttributes['size'],
                    'color' => $newAttributes['color'],
                    'side' => $newAttributes['side'],
                    'quantity' => $newQuantity,
                    'note' => $data['note'] ?? null,
                ]);

            $inventoryPackage->receiptLines()->update([
                'ma_hh' => $newAttributes['ma_sp'],
                'quantity' => $newQuantity,
                'location_code' => $locationCode,
                'internal_item_code' => $newAttributes['internal_item_code'],
                'size' => $newAttributes['size'],
                'color' => $newAttributes['color'],
                'side' => $newAttributes['side'],
                'note' => $data['note'] ?? null,
            ]);

            $location->status = 'counting';
            $location->save();
        });

        return response()->json([
            'message' => 'Da cap nhat kien trong ke.',
            'data' => $inventoryPackage->fresh()->load('location:id,location_code'),
        ]);
    }

    private function catalogDisplayNorms(?InternalItemCatalog $catalog): array
    {
        if (!$catalog) {
            return [];
        }

        $raw = collect((array) $catalog->raw_data)->mapWithKeys(function ($value, $key) {
            $normalized = strtolower(Str::ascii((string) $key));
            $normalized = trim(preg_replace('/[^a-z0-9]+/', ' ', $normalized));
            $normalized = preg_replace('/\s+/', ' ', $normalized);

            return [$normalized => $value];
        });

        $read = function (array $aliases) use ($raw) {
            foreach ($aliases as $alias) {
                if ($raw->has($alias) && trim((string) $raw->get($alias)) !== '') {
                    return trim((string) $raw->get($alias));
                }
            }

            return null;
        };

        $perBox = $read([
            'dinh muc thung', 'dinh muc 1 thung', 'so luong thung', 'sl thung',
            'quantity per box', 'pcs per box', 'so luong hop', 'sl hop',
        ]);
        $gramsPerMeter = $this->displayNormNumber($read([
            'gam met', 'g met', 'g m', 'gram met', 'grams per meter',
            'gram per meter', 'dinh muc gam met', 'dinh muc g m',
        ]));
        $gramsPerYard = $this->displayNormNumber($read([
            'gam yard', 'g yard', 'g yd', 'gram yard', 'grams per yard',
            'gram per yard', 'dinh muc gam yard', 'dinh muc g yard',
        ]));

        if ($gramsPerMeter !== null && $gramsPerYard === null) {
            $gramsPerYard = round($gramsPerMeter * 0.9144, 4);
        } elseif ($gramsPerYard !== null && $gramsPerMeter === null) {
            $gramsPerMeter = round($gramsPerYard / 0.9144, 4);
        }

        return collect([
            $perBox !== null ? [
                'label' => 'Định mức 1 thùng',
                'value' => $perBox,
                'unit' => trim((string) $catalog->unit),
            ] : null,
            $gramsPerMeter !== null ? ['label' => 'Định mức mét', 'value' => $gramsPerMeter, 'unit' => 'g/m'] : null,
            $gramsPerYard !== null ? ['label' => 'Định mức yard', 'value' => $gramsPerYard, 'unit' => 'g/yard'] : null,
        ])->filter()->values()->all();
    }

    private function displayNormNumber($value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $normalized = str_replace(',', '.', preg_replace('/[^0-9,\.\-]/', '', (string) $value));

        return is_numeric($normalized) && (float) $normalized > 0 ? (float) $normalized : null;
    }

    public function storeReceiptBatch(Request $request)
    {
        $data = $request->validate([
            'receipt_kind' => 'nullable|in:finished,semi_finished',
            'location_code' => 'nullable|string|max:100',
            'ma_ko' => 'nullable|string|max:50',
            'checked_at' => 'required|date',
            'note' => 'nullable|string|max:500',
            'send_to_production' => 'nullable|boolean',
            'export_to_customer' => 'nullable|boolean',
            'customer_name' => 'nullable|string|max:150|required_if:export_to_customer,1',
            'issue_date' => 'nullable|date|after_or_equal:checked_at|required_if:export_to_customer,1',
            'lines' => 'required|array|min:1|max:500',
            'lines.*.category' => 'nullable|string|max:255',
            'lines.*.ma_sp' => 'nullable|string|max:100',
            'lines.*.internal_item_code' => 'nullable|string|max:100',
            'lines.*.size' => 'nullable|string|max:100',
            'lines.*.color' => 'nullable|string|max:100',
            'lines.*.side' => 'nullable|string|max:100',
            'lines.*.dvt' => 'nullable|string|max:50',
            'lines.*.ordered_quantity' => 'nullable|numeric|min:0',
            'lines.*.quantity' => 'nullable|numeric|min:0',
            'lines.*.logo_color' => 'nullable|string|max:100',
            'lines.*.location_code' => 'nullable|string|max:100',
            'lines.*.note' => 'nullable|string|max:500',
            'lines.*.production_order_id' => 'nullable|integer',
            'lines.*.production_order' => 'nullable|string|max:100',
            'lines.*.purchase_order' => 'nullable|string|max:1000',
            'lines.*.customer' => 'nullable|string|max:200',
        ]);
        $data = $this->normalizeDateFields($data, ['checked_at', 'issue_date']);

        if (!empty($data['export_to_customer']) && ($data['receipt_kind'] ?? 'finished') !== 'finished') {
            return response()->json([
                'message' => 'Chỉ phiếu nhập thành phẩm mới có thể xuất ngay cho khách hàng.',
            ], 422);
        }

        $lines = collect($data['lines'])
            ->map(function ($line) {
                return [
                    'ma_sp' => trim((string) ($line['ma_sp'] ?? '')),
                    'category' => trim((string) ($line['category'] ?? '')),
                    'internal_item_code' => trim((string) ($line['internal_item_code'] ?? '')),
                    'size' => trim((string) ($line['size'] ?? '')),
                    'color' => trim((string) ($line['color'] ?? '')),
                    'side' => trim((string) ($line['side'] ?? '')),
                    'dvt' => trim((string) ($line['dvt'] ?? '')),
                    'ordered_quantity' => (float) ($line['ordered_quantity'] ?? 0),
                    'quantity' => (float) ($line['quantity'] ?? 0),
                    'logo_color' => trim((string) ($line['logo_color'] ?? '')),
                    'location_code' => strtoupper(trim((string) ($line['location_code'] ?? ''))),
                    'note' => trim((string) ($line['note'] ?? '')),
                    'production_order_id' => $line['production_order_id'] ?? null,
                    'production_order' => trim((string) ($line['production_order'] ?? '')),
                    'purchase_order' => trim((string) ($line['purchase_order'] ?? '')),
                    'customer' => trim((string) ($line['customer'] ?? '')),
                ];
            })
            ->filter(function ($line) {
                return $line['internal_item_code'] !== '' && $line['quantity'] > 0;
            })
            ->values();

        if ($lines->isEmpty()) {
            return response()->json([
                'message' => 'Nhập ít nhất 1 dòng có Mã nội bộ và Số lượng lớn hơn 0. Mã kế toán có thể gán sau.',
            ], 422);
        }

        $catalogValidator = app(InternalCatalogValidator::class);
        $catalogErrors = $catalogValidator->errorsForLines($lines);
        if (!empty($catalogErrors)) {
            return $catalogValidator->responseForErrors($catalogErrors);
        }

        $locationCode = strtoupper(trim($data['location_code'] ?? '')) ?: 'CHUA-XEP';
        $warehouseCode = '';
        $lineLocationCodes = $lines->pluck('location_code')->filter()->unique()->values();
        $receiptLocationCode = $lineLocationCodes->count() === 1
            ? $lineLocationCodes->first()
            : ($lineLocationCodes->isEmpty() ? $locationCode : '');

        $accountingCodes = $lines->pluck('ma_sp')->filter()->unique()->all();
        $catalogItems = collect();
        if (!empty($accountingCodes)) {
            try {
                $catalogItems = DB::connection('sqlsrv')->table('TSoft_NhanTG_kt_new.dbo.CodeHanghoa')
                    ->whereIn('Ma_hh', $accountingCodes)
                    ->select('Ma_hh', 'Ten_hh', 'Dvt')
                    ->get()
                    ->keyBy('Ma_hh');
            } catch (\Throwable $error) {
                // Internal warehouse operations must remain available when TSoft is offline.
                $catalogItems = collect();
            }
        }
        $internalCatalogItems = InternalItemCatalog::query()
            ->where('is_active', true)
            ->whereIn('item_code', $lines->pluck('internal_item_code')->filter()->unique()->values())
            ->get()
            ->groupBy(function ($item) {
                return mb_strtoupper(trim((string) $item->item_code));
            });

        $unitConverter = app(InternalUnitConverter::class);

        [$receipt, $packages] = DB::connection('internal')->transaction(function () use ($data, $lines, $locationCode, $receiptLocationCode, $warehouseCode, $catalogItems, $internalCatalogItems, $unitConverter) {
            $location = WarehouseLocation::query()->firstOrCreate(
                ['location_code' => $locationCode],
                [
                    'warehouse_code' => $warehouseCode,
                    'shelf_code' => 'CX',
                    'tier' => 1,
                    'bay_code' => null,
                    'grid_x' => 1,
                    'grid_y' => 1,
                    'grid_w' => 4,
                    'grid_h' => 2,
                    'location_name' => 'Chưa xếp vị trí',
                    'note' => 'Vị trí tạm để ghi nhận tồn trước khi xếp kệ.',
                ]
            );

            if ($warehouseCode !== '' && !$location->warehouse_code) {
                $location->warehouse_code = $warehouseCode;
            }

            $isSemiFinished = ($data['receipt_kind'] ?? 'finished') === 'semi_finished';
            $receipt = InternalMaterialReceipt::query()->create([
                'receipt_code' => $isSemiFinished ? $this->nextBtpReceiptCode() : $this->nextMaterialReceiptCode(),
                'receipt_date' => $data['checked_at'],
                'warehouse_code' => $warehouseCode,
                'location_code' => $receiptLocationCode,
                'receiver_name' => '',
                'source' => $isSemiFinished ? 'Phieu nhap ban thanh pham' : 'Phieu nhap thanh pham',
                'status' => 'posted',
                'note' => $data['note'] ?? null,
            ]);

            $packages = collect();

            foreach ($lines as $line) {
                if ($line['production_order'] === '' && ($data['receipt_kind'] ?? 'finished') !== 'semi_finished') {
                    $matchedBtpLine = app(InternalBtpOrderMatcher::class)->find([
                        'ps_number' => $line['purchase_order'],
                        'purchase_order' => $line['purchase_order'],
                        'internal_item_code' => $line['internal_item_code'],
                        'ma_hh' => $line['ma_sp'],
                        'size' => $line['size'],
                        'color' => $line['color'],
                        'logo_color' => $line['logo_color'],
                        'side' => $line['side'],
                        'ordered_quantity' => $line['ordered_quantity'],
                        'note' => $line['note'],
                    ]);
                    if ($matchedBtpLine) {
                        $line['production_order'] = trim((string) $matchedBtpLine->btp_order_code);
                        $line['production_order_id'] = null;
                    }
                }

                $line['production_order_id'] = app(InternalProductionOrderLineResolver::class)->resolve($line);

                $lineLocation = $location;
                if ($line['location_code'] !== '' && $line['location_code'] !== $location->location_code) {
                    $lineLocation = WarehouseLocation::query()->firstOrCreate(
                        ['location_code' => $line['location_code']],
                        [
                            'warehouse_code' => $warehouseCode,
                            'shelf_code' => 'CX',
                            'tier' => 1,
                            'bay_code' => null,
                            'grid_x' => 1,
                            'grid_y' => 1,
                            'grid_w' => 4,
                            'grid_h' => 2,
                            'location_name' => $line['location_code'],
                        ]
                    );
                    if ($warehouseCode !== '' && !$lineLocation->warehouse_code) {
                        $lineLocation->warehouse_code = $warehouseCode;
                        $lineLocation->save();
                    }
                }

                $attributes = [
                    'ma_sp' => $line['ma_sp'],
                    'ma_ko' => $warehouseCode,
                    'internal_item_code' => $line['internal_item_code'],
                    'size' => $line['size'],
                    'color' => $line['color'],
                    'side' => $line['side'],
                    'checked_at' => $data['checked_at'],
                ];

                $base = $unitConverter->toBase(
                    $line['internal_item_code'],
                    (float) $line['quantity'],
                    $line['dvt'],
                    $line['dvt']
                );

                $count = InternalInventoryCount::query()->firstOrCreate(
                    $attributes,
                    [
                        'counted_quantity' => 0,
                        'note' => $line['note'] ?: ($data['note'] ?? null),
                    ]
                );

                $count->counted_quantity = (float) $count->counted_quantity + (float) $base['quantity'];
                $count->note = $line['note'] ?: $count->note;
                $count->save();

                $package = InventoryPackage::query()->create(array_merge($attributes, [
                    'package_code' => $this->nextPackageCode(),
                    'warehouse_location_id' => $lineLocation->id,
                    'inventory_count_id' => $count->id,
                    'quantity' => $base['quantity'],
                    'note' => $line['note'] ?: null,
                ]));

                $catalogItem = $catalogItems->get($line['ma_sp']);
                $internalCatalogGroup = $internalCatalogItems->get(mb_strtoupper(trim((string) $line['internal_item_code'])), collect());
                $internalCatalogItem = $internalCatalogGroup->first(function ($item) use ($line) {
                    foreach (['size', 'color', 'side'] as $field) {
                        $catalogValue = mb_strtoupper(trim((string) $item->{$field}));
                        $lineValue = mb_strtoupper(trim((string) $line[$field]));
                        if ($catalogValue !== '' && $lineValue !== '' && $catalogValue !== $lineValue) {
                            return false;
                        }
                    }
                    return true;
                }) ?: $internalCatalogGroup->first();

                $receipt->lines()->create([
                    'inventory_package_id' => $package->id,
                    'production_order_id' => $line['production_order_id'],
                    'production_order' => $line['production_order'],
                    'purchase_order' => $line['purchase_order'],
                    'customer' => $line['customer'],
                    'ma_hh' => $line['ma_sp'],
                    'ten_hh' => $line['category'] ?: ($internalCatalogItem->item_name ?? ($catalogItem->Ten_hh ?? '')),
                    'dvt' => $line['dvt'] ?: ($internalCatalogItem->unit ?? ($catalogItem->Dvt ?? '')),
                    'ordered_quantity' => $line['ordered_quantity'] ?: null,
                    'quantity' => $line['quantity'],
                    'base_quantity' => $base['quantity'],
                    'base_dvt' => $base['unit'],
                    'unit_factor' => $base['factor'],
                    'location_code' => $lineLocation->location_code,
                    'internal_item_code' => $line['internal_item_code'],
                    'size' => $line['size'],
                    'color' => $line['color'],
                    'logo_color' => $line['logo_color'],
                    'side' => $line['side'],
                    'note' => $line['note'] ?: null,
                ]);

                $packages->push($package);

                $lineLocation->status = 'counting';
                $lineLocation->save();
            }

            $location->status = 'counting';
            $location->save();

            return [$receipt, $packages];
        });

        $responseBody = [
            'message' => 'Đã lưu phiếu nhập thành phẩm nội bộ.',
            'data' => $receipt->load('lines'),
            'packages' => $packages->map(function ($package) {
                return [
                    'id' => $package->id,
                    'package_code' => $package->package_code,
                    'print_url' => url('/client/kiem-ton-kho/tem-kien/' . $package->id),
                ];
            })->values(),
            'receipt_print_url' => url('/client/nhap-thanh-pham-noi-bo/' . $receipt->id . '/in'),
        ];

        if (!empty($data['send_to_production'])) {
            $issueRequest = Request::create('/api/xuat-vat-tu-noi-bo/gui-san-xuat/' . $receipt->id, 'POST', [
                'issue_date' => $data['checked_at'],
                'receiver_name' => 'San xuat',
                'department' => 'San xuat',
                'purpose' => 'Xuat BTP di san xuat',
                'note' => 'Tu dong gui san xuat tu phieu nhap ' . $receipt->receipt_code,
            ]);
            $issueRequest->setUserResolver($request->getUserResolver());

            $issueResponse = app(InternalMaterialIssueController::class)->sendReceiptToProduction($issueRequest, $receipt);
            $issueBody = json_decode($issueResponse->getContent(), true) ?: [];
            if ($issueResponse->getStatusCode() >= 400) {
                $responseBody['message'] = 'Da luu phieu nhap nhung khong gui san xuat duoc: ' . ($issueBody['message'] ?? 'Loi khong ro.');
                $responseBody['production_failed'] = true;
                $responseBody['production_error'] = $issueBody;
            } else {
                $responseBody['message'] = 'Da luu phieu nhap va gui BTP sang san xuat.';
                $responseBody['production_issue'] = $issueBody['data'] ?? null;
                $responseBody['production_issue_print_url'] = $issueBody['print_url'] ?? null;
                $responseBody['production_message'] = $issueBody['message'] ?? null;
            }
        }

        if (!empty($data['export_to_customer'])) {
            $customerName = trim((string) ($data['customer_name'] ?? ''));
            $issueRequest = Request::create('/api/xuat-vat-tu-noi-bo/tu-phieu-nhap/' . $receipt->id, 'POST', [
                'issue_date' => $data['issue_date'] ?? $data['checked_at'],
                'receiver_name' => $customerName,
                'department' => 'Kinh doanh',
                'purpose' => 'Xuat thanh pham cho khach hang',
                'note' => 'Tao tu dong sau khi nhap kho - phieu ' . $receipt->receipt_code
                    . ($customerName !== '' ? ' - Khach hang: ' . $customerName : ''),
            ]);
            $issueRequest->setUserResolver($request->getUserResolver());

            $issueResponse = app(InternalMaterialIssueController::class)->createFromReceipt($issueRequest, $receipt);
            $issueBody = json_decode($issueResponse->getContent(), true) ?: [];
            if ($issueResponse->getStatusCode() >= 400) {
                $responseBody['message'] = 'Đã lưu phiếu nhập nhưng chưa tạo được phiếu xuất: '
                    . ($issueBody['message'] ?? 'Lỗi không rõ.');
                $responseBody['customer_issue_failed'] = true;
                $responseBody['customer_issue_error'] = $issueBody;
            } else {
                $responseBody['message'] = 'Đã nhập kho và xuất thành phẩm cho ' . $customerName . '.';
                $responseBody['customer_issue'] = $issueBody['data'] ?? null;
                $responseBody['customer_issue_print_url'] = $issueBody['print_url'] ?? null;
            }
        }

        $response = response()->json($responseBody);

        app(InternalAudit::class)->model('receipt.created', $receipt, [
            'line_count' => $receipt->lines->count(),
            'total_quantity' => (float) $receipt->lines->sum('quantity'),
            'location_code' => $receipt->location_code,
        ], $request);

        return $response;
    }

    public function checkReceiptDuplicates(Request $request)
    {
        $data = $request->validate([
            'checked_at' => 'required|date',
            'exclude_receipt_id' => 'nullable|integer',
            'lines' => 'required|array|min:1|max:500',
            'lines.*.internal_item_code' => 'nullable|string|max:100',
            'lines.*.quantity' => 'nullable|numeric|min:0',
            'lines.*.production_order' => 'nullable|string|max:100',
            'lines.*.size' => 'nullable|string|max:255',
            'lines.*.color' => 'nullable|string|max:1000',
            'lines.*.side' => 'nullable|string|max:255',
        ]);
        $data = $this->normalizeDateFields($data, ['checked_at']);

        $duplicates = collect($data['lines'])
            ->map(function ($line, $index) {
                return [
                    'index' => $index,
                    'internal_item_code' => trim((string) ($line['internal_item_code'] ?? '')),
                    'quantity' => (float) ($line['quantity'] ?? 0),
                    'production_order' => trim((string) ($line['production_order'] ?? '')),
                    'size' => trim((string) ($line['size'] ?? '')),
                    'color' => trim((string) ($line['color'] ?? '')),
                    'side' => trim((string) ($line['side'] ?? '')),
                ];
            })
            ->filter(fn ($line) => $line['internal_item_code'] !== '' && $line['quantity'] > 0)
            ->map(function ($line) use ($data) {
                $query = InternalMaterialReceiptLine::query()
                    ->join('internal_material_receipts as r', 'r.id', '=', 'internal_material_receipt_lines.receipt_id')
                    ->whereDate('r.receipt_date', $data['checked_at'])
                    ->where('r.source', 'Phieu nhap thanh pham')
                    ->whereRaw('UPPER(TRIM(internal_material_receipt_lines.internal_item_code)) = ?', [mb_strtoupper($line['internal_item_code'])])
                    ->whereRaw('ABS(internal_material_receipt_lines.quantity - ?) < 0.0001', [$line['quantity']]);

                foreach (['production_order', 'size', 'color', 'side'] as $field) {
                    if ($line[$field] !== '') {
                        $query->whereRaw(
                            "UPPER(TRIM(COALESCE(internal_material_receipt_lines.{$field}, ''))) = ?",
                            [mb_strtoupper($line[$field])]
                        );
                    }
                }

                if (!empty($data['exclude_receipt_id'])) {
                    $query->where('r.id', '!=', (int) $data['exclude_receipt_id']);
                }

                $matches = $query
                    ->select([
                        'r.id as receipt_id',
                        'r.receipt_code',
                        'r.receipt_date',
                        'internal_material_receipt_lines.internal_item_code',
                        'internal_material_receipt_lines.production_order',
                        'internal_material_receipt_lines.size',
                        'internal_material_receipt_lines.color',
                        'internal_material_receipt_lines.side',
                        'internal_material_receipt_lines.quantity',
                    ])
                    ->orderByDesc('r.id')
                    ->limit(5)
                    ->get();

                if ($matches->isEmpty()) {
                    return null;
                }

                return [
                    'line_index' => $line['index'],
                    'internal_item_code' => $line['internal_item_code'],
                    'quantity' => $line['quantity'],
                    'production_order' => $line['production_order'],
                    'size' => $line['size'],
                    'color' => $line['color'],
                    'side' => $line['side'],
                    'matches' => $matches,
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'duplicates' => $duplicates,
            'duplicate_count' => $duplicates->count(),
        ]);
    }

    public function showReceipt(InternalMaterialReceipt $receipt)
    {
        return response()->json([
            'data' => $receipt->load('lines'),
        ]);
    }

    public function updateReceiptBatch(Request $request, InternalMaterialReceipt $receipt)
    {
        $data = $request->validate([
            'force' => 'nullable|boolean',
            'location_code' => 'nullable|string|max:100',
            'ma_ko' => 'nullable|string|max:50',
            'checked_at' => 'required|date',
            'note' => 'nullable|string|max:500',
            'lines' => 'required|array|min:1|max:500',
            'lines.*.category' => 'nullable|string|max:255',
            'lines.*.ma_sp' => 'nullable|string|max:100',
            'lines.*.internal_item_code' => 'nullable|string|max:100',
            'lines.*.size' => 'nullable|string|max:100',
            'lines.*.color' => 'nullable|string|max:100',
            'lines.*.side' => 'nullable|string|max:100',
            'lines.*.dvt' => 'nullable|string|max:50',
            'lines.*.ordered_quantity' => 'nullable|numeric|min:0',
            'lines.*.quantity' => 'nullable|numeric|min:0',
            'lines.*.logo_color' => 'nullable|string|max:100',
            'lines.*.location_code' => 'nullable|string|max:100',
            'lines.*.note' => 'nullable|string|max:500',
            'lines.*.production_order_id' => 'nullable|integer',
            'lines.*.production_order' => 'nullable|string|max:100',
            'lines.*.purchase_order' => 'nullable|string|max:1000',
            'lines.*.customer' => 'nullable|string|max:200',
        ]);
        $data = $this->normalizeDateFields($data, ['checked_at']);
        $force = $request->boolean('force') || $request->boolean('cascade');
        $linkSummary = $this->receiptLinkSummary($receipt);
        $linkedIssues = InternalMaterialIssue::query()
            ->whereIn('id', collect($linkSummary['issues'])->pluck('id')->filter()->values())
            ->get();

        if ($linkedIssues->isNotEmpty() && !$force) {
            return response()->json([
                'message' => 'Phiếu nhập có phiếu xuất liên quan. Nếu chắc chắn sửa, hệ thống sẽ xóa phiếu xuất liên quan trước rồi cập nhật lại phiếu nhập.',
                'force_required' => true,
                'cascade_required' => true,
                'links' => $linkSummary,
                'linked_issues' => collect($linkSummary['issues'])->values(),
            ], 409);
        }

        if ($force && !($linkSummary['can_cascade_delete'] ?? true)) {
            return response()->json([
                'message' => $linkSummary['block_reason'] ?: 'Khong the xoa day chuyen phieu nay.',
                'links' => $linkSummary,
            ], 422);
        }

        if ($linkedIssues->isNotEmpty() && $force) {
            $this->resetBtpOrdersForIssues($linkedIssues);
            foreach ($linkedIssues as $issue) {
                app(InternalMaterialIssueController::class)->destroy($issue);
            }
        }

        $receipt->load('lines');
        foreach ($receipt->lines as $receiptLine) {
            $package = $this->resolveReceiptLinePackage($receiptLine);
            if (!$package && !$force) {
                return response()->json([
                    'message' => 'Phiếu nhập không còn đủ kiện tồn liên kết. Nếu chắc chắn sửa, hệ thống sẽ cập nhật phiếu theo dữ liệu mới.',
                    'force_required' => true,
                    'linked_issues' => [],
                ], 409);
            }

            if ($package && (float) $package->quantity + 0.0001 < (float) ($receiptLine->base_quantity ?: $receiptLine->quantity) && !$force) {
                return response()->json([
                    'message' => 'Không thể sửa phiếu nhập vì một phần hàng đã được xuất kho hoặc thay đổi. Nếu chắc chắn sửa, hệ thống sẽ cập nhật theo số tồn còn lại và dữ liệu mới.',
                    'force_required' => true,
                    'linked_issues' => [],
                ], 409);
            }
        }

        $lines = collect($data['lines'])
            ->map(function ($line) {
                return [
                    'ma_sp' => trim((string) ($line['ma_sp'] ?? '')),
                    'category' => trim((string) ($line['category'] ?? '')),
                    'internal_item_code' => trim((string) ($line['internal_item_code'] ?? '')),
                    'size' => trim((string) ($line['size'] ?? '')),
                    'color' => trim((string) ($line['color'] ?? '')),
                    'side' => trim((string) ($line['side'] ?? '')),
                    'dvt' => trim((string) ($line['dvt'] ?? '')),
                    'ordered_quantity' => (float) ($line['ordered_quantity'] ?? 0),
                    'quantity' => (float) ($line['quantity'] ?? 0),
                    'logo_color' => trim((string) ($line['logo_color'] ?? '')),
                    'location_code' => strtoupper(trim((string) ($line['location_code'] ?? ''))),
                    'note' => trim((string) ($line['note'] ?? '')),
                    'production_order_id' => $line['production_order_id'] ?? null,
                    'production_order' => trim((string) ($line['production_order'] ?? '')),
                    'purchase_order' => trim((string) ($line['purchase_order'] ?? '')),
                    'customer' => trim((string) ($line['customer'] ?? '')),
                ];
            })
            ->filter(fn ($line) => $line['internal_item_code'] !== '' && $line['quantity'] > 0)
            ->values();

        if ($lines->isEmpty()) {
            return response()->json([
                'message' => 'Nhập ít nhất 1 dòng có Mã nội bộ và Số lượng lớn hơn 0.',
            ], 422);
        }

        $catalogValidator = app(InternalCatalogValidator::class);
        $catalogErrors = $catalogValidator->errorsForLines($lines);
        if (!empty($catalogErrors)) {
            return $catalogValidator->responseForErrors($catalogErrors);
        }

        $warehouseCode = '';
        $locationCode = strtoupper(trim($data['location_code'] ?? '')) ?: 'CHUA-XEP';
        $lineLocationCodes = $lines->pluck('location_code')->filter()->unique()->values();
        $receiptLocationCode = $lineLocationCodes->count() === 1
            ? $lineLocationCodes->first()
            : ($lineLocationCodes->isEmpty() ? $locationCode : '');

        $unitConverter = app(InternalUnitConverter::class);

        DB::connection('internal')->transaction(function () use ($receipt, $data, $lines, $warehouseCode, $locationCode, $receiptLocationCode, $unitConverter) {
            $receipt->load('lines');
            $oldLocationIds = [];

            foreach ($receipt->lines as $line) {
            $package = $this->resolveReceiptLinePackage($line, true);
                $line->delete();

                if (!$package) {
                    continue;
                }

                $oldLocationIds[] = $package->warehouse_location_id;
                $count = $package->inventory_count_id
                    ? InternalInventoryCount::query()->lockForUpdate()->find($package->inventory_count_id)
                    : null;
                $quantity = (float) $package->quantity;
                $package->delete();

                if ($count) {
                    $remainingQuantity = (float) $count->counted_quantity - $quantity;
                    $hasPackages = InventoryPackage::query()->where('inventory_count_id', $count->id)->exists();
                    if ($remainingQuantity <= 0 && !$hasPackages) {
                        $count->delete();
                    } else {
                        $count->counted_quantity = max(0, $remainingQuantity);
                        $count->save();
                    }
                }
            }

            $receipt->update([
                'receipt_date' => $data['checked_at'],
                'warehouse_code' => $warehouseCode,
                'location_code' => $receiptLocationCode,
                'receiver_name' => '',
                'source' => 'Phieu nhap thanh pham',
                'status' => 'posted',
                'note' => $data['note'] ?? null,
            ]);

            foreach ($lines as $line) {
                if ($line['production_order'] === '') {
                    $matchedBtpLine = app(InternalBtpOrderMatcher::class)->find([
                        'ps_number' => $line['purchase_order'],
                        'purchase_order' => $line['purchase_order'],
                        'internal_item_code' => $line['internal_item_code'],
                        'ma_hh' => $line['ma_sp'],
                        'size' => $line['size'],
                        'color' => $line['color'],
                        'logo_color' => $line['logo_color'],
                        'side' => $line['side'],
                        'ordered_quantity' => $line['ordered_quantity'],
                        'note' => $line['note'],
                    ]);
                    if ($matchedBtpLine) {
                        $line['production_order'] = trim((string) $matchedBtpLine->btp_order_code);
                        $line['production_order_id'] = null;
                    }
                }

                $line['production_order_id'] = app(InternalProductionOrderLineResolver::class)->resolve($line);

                $targetLocationCode = $line['location_code'] ?: $locationCode;
                $lineLocation = WarehouseLocation::query()->firstOrCreate(
                    ['location_code' => $targetLocationCode],
                    [
                        'warehouse_code' => $warehouseCode,
                        'shelf_code' => $this->inferShelfCode($targetLocationCode) ?: 'CX',
                        'tier' => $this->inferTierFromLocationCode($targetLocationCode) ?: 1,
                        'bay_code' => $this->inferBayCode($targetLocationCode) ?: null,
                        'grid_x' => 1,
                        'grid_y' => 1,
                        'grid_w' => 4,
                        'grid_h' => 2,
                        'location_name' => $targetLocationCode,
                    ]
                );

                $attributes = [
                    'ma_sp' => $line['ma_sp'],
                    'ma_ko' => $warehouseCode,
                    'internal_item_code' => $line['internal_item_code'],
                    'size' => $line['size'],
                    'color' => $line['color'],
                    'side' => $line['side'],
                    'checked_at' => $data['checked_at'],
                ];
                $base = $unitConverter->toBase(
                    $line['internal_item_code'],
                    (float) $line['quantity'],
                    $line['dvt'],
                    $line['dvt']
                );
                $count = InternalInventoryCount::query()->firstOrCreate($attributes, [
                    'counted_quantity' => 0,
                    'note' => $line['note'] ?: ($data['note'] ?? null),
                ]);
                $count->counted_quantity = (float) $count->counted_quantity + (float) $base['quantity'];
                $count->save();

                $package = InventoryPackage::query()->create(array_merge($attributes, [
                    'package_code' => $this->nextPackageCode(),
                    'warehouse_location_id' => $lineLocation->id,
                    'inventory_count_id' => $count->id,
                    'quantity' => $base['quantity'],
                    'note' => $line['note'] ?: null,
                ]));

                $receipt->lines()->create([
                    'inventory_package_id' => $package->id,
                    'production_order_id' => $line['production_order_id'],
                    'production_order' => $line['production_order'],
                    'purchase_order' => $line['purchase_order'],
                    'customer' => $line['customer'],
                    'ma_hh' => $line['ma_sp'],
                    'ten_hh' => $line['category'],
                    'dvt' => $line['dvt'],
                    'ordered_quantity' => $line['ordered_quantity'] ?: null,
                    'quantity' => $line['quantity'],
                    'base_quantity' => $base['quantity'],
                    'base_dvt' => $base['unit'],
                    'unit_factor' => $base['factor'],
                    'location_code' => $lineLocation->location_code,
                    'internal_item_code' => $line['internal_item_code'],
                    'size' => $line['size'],
                    'color' => $line['color'],
                    'logo_color' => $line['logo_color'],
                    'side' => $line['side'],
                    'note' => $line['note'] ?: null,
                ]);

                $lineLocation->status = 'counting';
                $lineLocation->save();
            }

            foreach (array_unique(array_filter($oldLocationIds)) as $locationId) {
                $location = WarehouseLocation::query()->find($locationId);
                if ($location && !InventoryPackage::query()->where('warehouse_location_id', $location->id)->exists()) {
                    $location->status = 'pending';
                    $location->save();
                }
            }
        });

        $receipt = $receipt->fresh()->load('lines');
        app(InternalAudit::class)->model('receipt.updated', $receipt, [
            'line_count' => $receipt->lines->count(),
            'total_quantity' => (float) $receipt->lines->sum('quantity'),
        ], $request);

        return response()->json([
            'message' => 'Đã cập nhật phiếu nhập thành phẩm nội bộ.',
            'data' => $receipt,
            'receipt_print_url' => url('/client/nhap-thanh-pham-noi-bo/' . $receipt->id . '/in'),
        ]);
    }

    public function destroyReceipt(Request $request, InternalMaterialReceipt $receipt)
    {
        $auditPayload = [
            'receipt_code' => $receipt->receipt_code,
            'receipt_date' => optional($receipt->receipt_date)->format('Y-m-d'),
            'location_code' => $receipt->location_code,
        ];

        $force = $request->boolean('force') || $request->boolean('cascade');
        $linkSummary = $this->receiptLinkSummary($receipt);
        $linkedIssues = InternalMaterialIssue::query()
            ->whereIn('id', collect($linkSummary['issues'])->pluck('id')->filter()->values())
            ->get();

        if ($linkedIssues->isNotEmpty() && !$force) {
            return response()->json([
                'message' => 'Phiếu nhập có phiếu xuất liên quan. Nếu chắc chắn xóa, hệ thống sẽ xóa phiếu xuất liên quan trước rồi mới xóa phiếu nhập.',
                'force_required' => true,
                'cascade_required' => true,
                'links' => $linkSummary,
                'linked_issues' => collect($linkSummary['issues'])->values(),
            ], 409);
        }

        if ($force && !($linkSummary['can_cascade_delete'] ?? true)) {
            return response()->json([
                'message' => $linkSummary['block_reason'] ?: 'Khong the xoa day chuyen phieu nay.',
                'links' => $linkSummary,
            ], 422);
        }

        if ($linkedIssues->isNotEmpty() && $force) {
            $this->resetBtpOrdersForIssues($linkedIssues);
            foreach ($linkedIssues as $issue) {
                app(InternalMaterialIssueController::class)->destroy($issue);
            }
        }

        $receipt->load('lines');
        foreach ($receipt->lines as $line) {
            $package = $this->resolveReceiptLinePackage($line);

            if (!$package) {
                continue;
            }

            if ((float) $package->quantity + 0.0001 < (float) ($line->base_quantity ?: $line->quantity)) {
                return response()->json([
                    'message' => 'Không thể xóa phiếu vì một phần hàng của phiếu đã được xuất kho hoặc thay đổi. Hãy hoàn/xóa phiếu xuất liên quan trước.',
                ], 422);
            }
        }

        DB::connection('internal')->transaction(function () use ($receipt) {
            $locationIds = [];

            foreach ($receipt->lines as $line) {
                $package = $this->resolveReceiptLinePackage($line, true);

                $line->delete();

                if (!$package) {
                    continue;
                }

                $locationIds[] = $package->warehouse_location_id;
                $count = $package->inventory_count_id
                    ? InternalInventoryCount::query()->lockForUpdate()->find($package->inventory_count_id)
                    : null;
                $quantity = (float) $package->quantity;

                $package->delete();

                if ($count) {
                    $remainingQuantity = (float) $count->counted_quantity - $quantity;
                    $hasPackages = InventoryPackage::query()
                        ->where('inventory_count_id', $count->id)
                        ->exists();

                    if ($remainingQuantity <= 0 && !$hasPackages) {
                        $count->delete();
                    } else {
                        $count->counted_quantity = max(0, $remainingQuantity);
                        $count->save();
                    }
                }
            }

            $receipt->delete();

            foreach (array_unique(array_filter($locationIds)) as $locationId) {
                $location = WarehouseLocation::query()->find($locationId);
                if ($location && !InventoryPackage::query()->where('warehouse_location_id', $location->id)->exists()) {
                    $location->status = 'pending';
                    $location->save();
                }
            }
        });

        app(InternalAudit::class)->record(
            'receipt.deleted',
            'InternalMaterialReceipt',
            (int) $receipt->id,
            $receipt->receipt_code,
            $auditPayload,
            request()
        );

        return response()->json([
            'message' => 'Đã xóa phiếu nhập kho nội bộ.',
        ]);
    }

    public function receiptLinks(Request $request, InternalMaterialReceipt $receipt)
    {
        return response()->json([
            'data' => $this->receiptLinkSummary($receipt),
        ]);
    }

    public function updateReceiptLocation(Request $request, InternalMaterialReceipt $receipt)
    {
        $data = $request->validate([
            'location_code' => 'required|string|max:100',
        ]);

        $locationCode = strtoupper(trim($data['location_code']));
        $location = WarehouseLocation::query()
            ->where('location_code', $locationCode)
            ->first();

        if (!$location) {
            return response()->json([
                'message' => 'Vị trí không tồn tại. Hãy tạo vị trí trước khi gán cho phiếu.',
            ], 422);
        }

        $oldLocationIds = [];
        DB::connection('internal')->transaction(function () use ($receipt, $location, &$oldLocationIds) {
            $receipt->load('lines');

            foreach ($receipt->lines as $line) {
                if (!$line->inventory_package_id) {
                    $line->location_code = $location->location_code;
                    $line->save();
                    continue;
                }

                $package = InventoryPackage::query()
                    ->lockForUpdate()
                    ->find($line->inventory_package_id);

                if ($package) {
                    $oldLocationIds[] = $package->warehouse_location_id;
                    $this->movePackageRecord($package, $location);
                }
            }

            $receipt->location_code = $location->location_code;
            if ($location->warehouse_code) {
                $receipt->warehouse_code = $location->warehouse_code;
            }
            $receipt->save();

            $location->status = 'counting';
            $location->save();
        });

        foreach (array_unique(array_filter($oldLocationIds)) as $locationId) {
            if ((int) $locationId === (int) $location->id) {
                continue;
            }
            $oldLocation = WarehouseLocation::query()->find($locationId);
            if ($oldLocation && !InventoryPackage::query()->where('warehouse_location_id', $oldLocation->id)->exists()) {
                $oldLocation->status = 'pending';
                $oldLocation->save();
            }
        }

        app(InternalAudit::class)->model('receipt.location_updated', $receipt, [
            'location_code' => $location->location_code,
            'warehouse_code' => $receipt->warehouse_code,
        ], $request);

        return response()->json([
            'message' => 'Đã cập nhật vị trí cho phiếu nhập.',
            'data' => [
                'receipt_id' => $receipt->id,
                'location_code' => $location->location_code,
                'warehouse_code' => $receipt->warehouse_code,
            ],
        ]);
    }

    public function printPackage(InventoryPackage $inventoryPackage)
    {
        $inventoryPackage->load('location:id,location_code');

        return view('client.labels.package', ['package' => $inventoryPackage]);
    }

    public function destroyPackage(InventoryPackage $inventoryPackage)
    {
        DB::connection('internal')->transaction(function () use ($inventoryPackage) {
            $location = WarehouseLocation::query()->lockForUpdate()->find($inventoryPackage->warehouse_location_id);
            $count = $inventoryPackage->inventory_count_id
                ? InternalInventoryCount::query()->lockForUpdate()->find($inventoryPackage->inventory_count_id)
                : null;
            $quantity = (float) $inventoryPackage->quantity;

            InternalOpeningStock::query()
                ->where('inventory_package_id', $inventoryPackage->id)
                ->delete();

            $receiptLines = $inventoryPackage->receiptLines()->with('receipt.lines')->get();
            foreach ($receiptLines as $line) {
                $receipt = $line->receipt;
                $line->delete();

                if ($receipt && !$receipt->lines()->exists()) {
                    $receipt->delete();
                }
            }

            $inventoryPackage->delete();

            if ($count) {
                $remainingQuantity = (float) $count->counted_quantity - $quantity;
                $hasPackages = InventoryPackage::query()
                    ->where('inventory_count_id', $count->id)
                    ->exists();

                if ($remainingQuantity <= 0 && !$hasPackages) {
                    $count->delete();
                } else {
                    $count->counted_quantity = max(0, $remainingQuantity);
                    $count->save();
                }
            }

            if ($location && !InventoryPackage::query()->where('warehouse_location_id', $location->id)->exists()) {
                $location->status = 'pending';
                $location->save();
            }
        });

        return response()->json([
            'message' => 'Đã xóa kiện và cập nhật lại tổng nội bộ.',
        ]);
    }

    public function movePackage(Request $request, InventoryPackage $inventoryPackage)
    {
        $data = $request->validate([
            'warehouse_location_id' => 'required|integer|exists:internal.warehouse_locations,id',
        ]);

        $targetLocation = WarehouseLocation::query()->findOrFail($data['warehouse_location_id']);

        DB::connection('internal')->transaction(function () use ($inventoryPackage, $targetLocation) {
            $this->movePackageRecord($inventoryPackage, $targetLocation);
        });

        app(InternalAudit::class)->model('package.moved', $inventoryPackage, [
            'target_location_code' => $targetLocation->location_code,
            'target_warehouse_code' => $targetLocation->warehouse_code,
        ], $request);

        return response()->json([
            'message' => 'Đã chuyển kiện sang vị trí mới.',
            'data' => $inventoryPackage->fresh()->load('location:id,location_code'),
        ]);
    }

    public function printLocation(WarehouseLocation $warehouseLocation)
    {
        return view('client.labels.location', ['location' => $warehouseLocation]);
    }

    public function printLocations(Request $request)
    {
        $data = $request->validate([
            'shelf_from' => ['nullable', 'string', 'max:2', 'regex:/^[A-Za-z]{1,2}$/'],
            'shelf_to' => ['nullable', 'string', 'max:2', 'regex:/^[A-Za-z]{1,2}$/'],
            'number_from' => 'nullable|integer|min:1|max:999',
            'number_to' => 'nullable|integer|min:1|max:999',
            'codes' => 'nullable|string|max:5000',
        ]);

        $codes = collect();
        if (!empty($data['codes'])) {
            $codes = collect(explode(',', (string) $data['codes']))
                ->map(fn ($code) => strtoupper(trim($code)))
                ->filter()
                ->unique()
                ->values();
        } else {
            $rackCode = app(WarehouseRackCode::class);
            $fromShelf = $rackCode->labelToIndex((string) ($data['shelf_from'] ?? 'A'));
            $toShelf = $rackCode->labelToIndex((string) ($data['shelf_to'] ?? 'D'));
            $fromNumber = (int) ($data['number_from'] ?? 1);
            $toNumber = (int) ($data['number_to'] ?? 100);

            $totalLocations = ($toShelf - $fromShelf + 1) * ($toNumber - $fromNumber + 1);
            if ($fromShelf < 0 || $toShelf < 0 || $fromShelf > $toShelf || $fromNumber > $toNumber || $totalLocations > 10000) {
                abort(422, 'Dãy vị trí không hợp lệ.');
            }

            for ($shelfIndex = $fromShelf; $shelfIndex <= $toShelf; $shelfIndex++) {
                for ($number = $fromNumber; $number <= $toNumber; $number++) {
                    $codes->push($rackCode->indexToLabel($shelfIndex) . $number);
                }
            }
        }

        $locations = WarehouseLocation::query()
            ->whereIn('location_code', $codes)
            ->get()
            ->sortBy(fn ($location) => $codes->search($location->location_code))
            ->values();

        return view('client.labels.locations', [
            'locations' => $locations,
            'missingCodes' => $codes->diff($locations->pluck('location_code'))->values(),
        ]);
    }

    private function locationLabelColors($locationIds)
    {
        $ids = collect($locationIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        $rows = InventoryPackage::query()
            ->whereIn('warehouse_location_id', $ids)
            ->where('quantity', '>', 0)
            ->select('warehouse_location_id', 'internal_item_code', 'ma_sp', 'size', 'color', 'side')
            ->get();

        $catalogs = InternalItemCatalog::query()
            ->whereIn('item_code', $rows->pluck('internal_item_code')->filter()->unique())
            ->get()
            ->keyBy(fn ($catalog) => mb_strtoupper(trim((string) $catalog->item_code)));
        $matcher = app(PantoneColorMatcher::class);

        return $rows
            ->groupBy('warehouse_location_id')
            ->map(function ($locationRows) use ($catalogs, $matcher) {
                return $locationRows
                    ->map(function ($row) use ($catalogs, $matcher) {
                        $catalog = $catalogs->get(mb_strtoupper(trim((string) $row->internal_item_code)));
                        $match = $matcher->matchValues([
                            $row->internal_item_code,
                            $row->ma_sp,
                            $row->color,
                            $row->size,
                            $row->side,
                        ], $catalog);

                        return trim((string) ($match['name'] ?? ''))
                            ?: trim((string) $row->color)
                            ?: trim((string) $row->internal_item_code);
                    })
                    ->filter()
                    ->unique(fn ($color) => mb_strtoupper($color))
                    ->take(3)
                    ->values();
            });
    }

    public function printMaterialReceipt(InternalMaterialReceipt $receipt)
    {
        return view('client.internal-material-receipt-print', [
            'receipt' => $receipt->load('lines'),
        ]);
    }

    private function nextPackageCode()
    {
        return app(InternalDocumentNumber::class)->next('PK', 5);
    }

    private function nextMaterialReceiptCode()
    {
        return app(InternalDocumentNumber::class)->next('PNTP', 4);
    }

    private function nextBtpReceiptCode()
    {
        return app(InternalDocumentNumber::class)->next('PNBTP', 4);
    }

    private function movePackageRecord(InventoryPackage $package, WarehouseLocation $targetLocation): void
    {
        $sourceLocationId = $package->warehouse_location_id;
        $sourceCount = $package->inventory_count_id
            ? InternalInventoryCount::query()->lockForUpdate()->find($package->inventory_count_id)
            : null;
        $quantity = (float) $package->quantity;
        $targetWarehouseCode = $targetLocation->warehouse_code ?: $package->ma_ko;

        if ((string) $package->ma_ko !== (string) $targetWarehouseCode) {
            if ($sourceCount) {
                $remainingQuantity = (float) $sourceCount->counted_quantity - $quantity;
                $hasOtherPackages = InventoryPackage::query()
                    ->where('inventory_count_id', $sourceCount->id)
                    ->where('id', '!=', $package->id)
                    ->exists();

                if ($remainingQuantity <= 0 && !$hasOtherPackages) {
                    $sourceCount->delete();
                } else {
                    $sourceCount->counted_quantity = max(0, $remainingQuantity);
                    $sourceCount->save();
                }
            }

            $targetCount = InternalInventoryCount::query()->firstOrCreate(
                [
                    'ma_sp' => $package->ma_sp,
                    'ma_ko' => $targetWarehouseCode,
                    'internal_item_code' => $package->internal_item_code,
                    'size' => $package->size,
                    'color' => $package->color,
                    'side' => $package->side,
                    'checked_at' => $package->checked_at,
                ],
                [
                    'counted_quantity' => 0,
                    'note' => $package->note,
                ]
            );
            $targetCount->counted_quantity = (float) $targetCount->counted_quantity + $quantity;
            $targetCount->save();

            $package->ma_ko = $targetWarehouseCode;
            $package->inventory_count_id = $targetCount->id;
        }

        $package->warehouse_location_id = $targetLocation->id;
        $package->save();

        InternalOpeningStock::query()
            ->where('inventory_package_id', $package->id)
            ->update([
                'warehouse_code' => $targetWarehouseCode,
                'location_code' => $targetLocation->location_code,
            ]);

        $package->receiptLines()->update([
            'location_code' => $targetLocation->location_code,
        ]);

        $targetLocation->status = 'counting';
        $targetLocation->save();

        $sourceLocation = WarehouseLocation::query()->find($sourceLocationId);
        if ($sourceLocation && !InventoryPackage::query()->where('warehouse_location_id', $sourceLocation->id)->exists()) {
            $sourceLocation->status = 'pending';
            $sourceLocation->save();
        }
    }

    private function inferShelfCode($locationCode)
    {
        $parsed = app(WarehouseRackCode::class)->parseLocation((string) $locationCode);
        if ($parsed) {
            return (string) $parsed['bay'];
        }

        $code = strtoupper(trim((string) $locationCode));

        preg_match('/[A-Z]/', $code, $matches);

        return $matches[0] ?? null;
    }

    private function inferBayCode($locationCode): string
    {
        return preg_match('/(\d+)$/', strtoupper(trim((string) $locationCode)), $matches)
            ? (ltrim($matches[1], '0') ?: '0')
            : '';
    }

    private function inferTierFromLocationCode($locationCode): int
    {
        $parsed = app(WarehouseRackCode::class)->parseLocation((string) $locationCode);

        return $parsed ? (int) $parsed['tier'] : 0;
    }

    private function resolveReceiptLinePackage($line, bool $lock = false): ?InventoryPackage
    {
        if ($line->inventory_package_id) {
            $query = InventoryPackage::query();
            if ($lock) {
                $query->lockForUpdate();
            }
            $package = $query->find($line->inventory_package_id);
            if ($package) {
                return $package;
            }
        }

        $query = InventoryPackage::query()
            ->where('quantity', '>=', max(0, (float) $line->quantity - 0.0001))
            ->where('ma_sp', trim((string) $line->ma_hh))
            ->where('internal_item_code', trim((string) $line->internal_item_code))
            ->where('size', trim((string) $line->size))
            ->where('color', trim((string) $line->color))
            ->where('side', trim((string) $line->side));

        $locationCode = strtoupper(trim((string) $line->location_code));
        if ($locationCode !== '') {
            $query->whereHas('location', function ($locationQuery) use ($locationCode) {
                $locationQuery->whereRaw('UPPER(TRIM(location_code)) = ?', [$locationCode]);
            });
        }

        if ($lock) {
            $query->lockForUpdate();
        }

        $package = $query->orderByDesc('id')->first();
        if ($package && (int) $line->inventory_package_id !== (int) $package->id) {
            $line->inventory_package_id = $package->id;
            $line->save();
        }

        return $package;
    }

    private function receiptLinkSummary(InternalMaterialReceipt $receipt): array
    {
        $issues = InternalMaterialIssue::query()
            ->where(function ($query) use ($receipt) {
                $query->where('source_receipt_id', $receipt->id)
                    ->orWhere('note', 'like', '%' . $receipt->receipt_code . '%');
            })
            ->withCount('lines')
            ->get();

        $issueIds = $issues->pluck('id')->filter()->values();
        $btpOrders = $issueIds->isEmpty()
            ? collect()
            : InternalBtpProductionOrder::query()
                ->withCount('lines')
                ->whereIn('issue_id', $issueIds)
                ->get();

        $hasCompletedBtp = $btpOrders->contains(fn ($order) => trim((string) $order->status) === 'completed');

        return [
            'receipt' => [
                'id' => $receipt->id,
                'receipt_code' => $receipt->receipt_code,
                'receipt_date' => optional($receipt->receipt_date)->format('Y-m-d'),
            ],
            'has_links' => $issues->isNotEmpty() || $btpOrders->isNotEmpty(),
            'can_cascade_delete' => !$hasCompletedBtp,
            'block_reason' => $hasCompletedBtp ? 'Co lenh BTP da hoan thanh san xuat. Hay tao phieu dieu chinh thay vi xoa day chuyen.' : '',
            'issues' => $issues->map(fn ($issue) => [
                'id' => $issue->id,
                'issue_code' => $issue->issue_code,
                'issue_type' => $issue->issue_type,
                'issue_date' => optional($issue->issue_date)->format('Y-m-d'),
                'status' => $issue->status,
                'line_count' => (int) ($issue->lines_count ?? 0),
            ])->values(),
            'btp_orders' => $btpOrders->map(fn ($order) => [
                'id' => $order->id,
                'btp_order_code' => $order->btp_order_code,
                'status' => $order->status,
                'issue_code' => $order->issue_code,
                'line_count' => (int) ($order->lines_count ?? 0),
            ])->values(),
        ];
    }

    private function resetBtpOrdersForIssues($issues): void
    {
        $issueIds = collect($issues)->pluck('id')->filter()->unique()->values();
        if ($issueIds->isEmpty()) {
            return;
        }

        InternalBtpProductionOrder::query()
            ->whereIn('issue_id', $issueIds)
            ->update([
                'status' => 'draft',
                'issue_id' => null,
                'issue_code' => null,
                'issued_at' => null,
            ]);
    }

    private function stockRowKey($row): string
    {
        return implode('|', [
            strtoupper(trim((string) $row->warehouse_code)),
            strtoupper(trim((string) $row->location_code)),
            strtoupper(trim((string) ($row->ma_hh ?? $row->ma_sp ?? ''))),
            trim((string) $row->internal_item_code),
            trim((string) $row->size),
            trim((string) $row->color),
            trim((string) $row->side),
        ]);
    }

    private function locationContentKey($row): string
    {
        return implode('|', [
            strtoupper(trim((string) $row->internal_item_code)),
            strtoupper(trim((string) ($row->ma_sp ?? ''))),
            strtoupper(trim((string) $row->size)),
            strtoupper(trim((string) $row->color)),
            strtoupper(trim((string) $row->side)),
        ]);
    }

    private function stockFlowKey($row): string
    {
        $internalCode = strtoupper(trim((string) $row->internal_item_code));
        if ($internalCode !== '') {
            return 'INTERNAL|' . $internalCode;
        }

        return implode('|', [
            'ACCOUNTING',
            strtoupper(trim((string) ($row->ma_hh ?? $row->ma_sp ?? ''))),
            strtoupper(trim((string) $row->size)),
            strtoupper(trim((string) $row->color)),
            strtoupper(trim((string) $row->side)),
        ]);
    }
}
