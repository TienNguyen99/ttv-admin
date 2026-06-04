<?php

namespace App\Http\Controllers;

use App\Models\InternalInventoryCount;
use App\Models\InternalMaterialReceipt;
use App\Models\InventoryPackage;
use App\Models\WarehouseLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WarehouseCountController extends Controller
{
    public function index()
    {
        return view('client.warehouse-count');
    }

    public function stockIndex()
    {
        return view('client.internal-stock');
    }

    public function showLocation(WarehouseLocation $warehouseLocation)
    {
        return view('client.warehouse-location-detail', [
            'location' => $warehouseLocation,
        ]);
    }

    public function locations()
    {
        return response()->json([
            'data' => WarehouseLocation::query()->orderBy('location_code')->get(),
        ]);
    }

    public function storeLocation(Request $request)
    {
        $data = $request->validate([
            'location_code' => 'required|string|max:100',
            'warehouse_code' => 'nullable|string|max:50',
            'shelf_code' => 'nullable|string|max:20',
            'tier' => 'nullable|integer|min:1|max:2',
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
        $data['tier'] = (int) ($data['tier'] ?? 1);
        $data['bay_code'] = strtoupper(trim($data['bay_code'] ?? ''));

        $location = WarehouseLocation::query()->updateOrCreate(
            ['location_code' => $data['location_code']],
            [
                'warehouse_code' => $data['warehouse_code'],
                'shelf_code' => $data['shelf_code'] ?: $this->inferShelfCode($data['location_code']),
                'tier' => in_array($data['tier'], [1, 2], true) ? $data['tier'] : 1,
                'bay_code' => $data['bay_code'] ?: null,
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

        return response()->json([
            'data' => $query->limit($limit)->get(),
            'summary' => [
                'package_count' => $summaryQuery->count(),
                'total_quantity' => (float) (clone $summaryQuery)->sum('quantity'),
            ],
        ]);
    }

    public function stockWarehouses()
    {
        $fromPackages = InventoryPackage::query()
            ->select(DB::raw("NULLIF(ma_ko, '') as warehouse_code"))
            ->whereRaw("NULLIF(ma_ko, '') IS NOT NULL");

        $fromLocations = WarehouseLocation::query()
            ->select(DB::raw("NULLIF(warehouse_code, '') as warehouse_code"))
            ->whereRaw("NULLIF(warehouse_code, '') IS NOT NULL");

        $warehouses = DB::connection('internal')
            ->query()
            ->fromSub($fromPackages->union($fromLocations), 'warehouses')
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

        $query = InventoryPackage::query()
            ->join('warehouse_locations as wl', 'inventory_packages.warehouse_location_id', '=', 'wl.id')
            ->select(
                DB::raw("COALESCE(NULLIF(inventory_packages.ma_ko, ''), NULLIF(wl.warehouse_code, ''), '') as warehouse_code"),
                'wl.location_code',
                'inventory_packages.ma_sp',
                'inventory_packages.internal_item_code',
                'inventory_packages.size',
                'inventory_packages.color',
                'inventory_packages.side',
                DB::raw('SUM(inventory_packages.quantity) as total_quantity'),
                DB::raw('COUNT(*) as package_count'),
                DB::raw('MAX(inventory_packages.checked_at) as latest_checked_at')
            )
            ->where('inventory_packages.quantity', '>', 0);

        if ($warehouseCode !== '') {
            $query->where(function ($q) use ($warehouseCode) {
                $q->where('inventory_packages.ma_ko', $warehouseCode)
                    ->orWhere(function ($sub) use ($warehouseCode) {
                        $sub->where('inventory_packages.ma_ko', '')
                            ->where('wl.warehouse_code', $warehouseCode);
                    });
            });
        }

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('inventory_packages.ma_sp', 'like', '%' . $keyword . '%')
                    ->orWhere('inventory_packages.internal_item_code', 'like', '%' . $keyword . '%')
                    ->orWhere('inventory_packages.size', 'like', '%' . $keyword . '%')
                    ->orWhere('inventory_packages.color', 'like', '%' . $keyword . '%')
                    ->orWhere('wl.location_code', 'like', '%' . $keyword . '%');
            });
        }

        $data = $query
            ->groupBy(
                'inventory_packages.ma_ko',
                'wl.warehouse_code',
                'wl.location_code',
                'inventory_packages.ma_sp',
                'inventory_packages.internal_item_code',
                'inventory_packages.size',
                'inventory_packages.color',
                'inventory_packages.side'
            )
            ->orderBy('warehouse_code')
            ->orderBy('wl.location_code')
            ->orderBy('inventory_packages.ma_sp')
            ->get();

        return response()->json([
            'data' => $data,
            'summary' => [
                'warehouse_code' => $warehouseCode,
                'item_count' => $data->pluck('ma_sp')->filter()->unique()->count(),
                'line_count' => $data->count(),
                'package_count' => $data->sum('package_count'),
                'total_quantity' => (float) $data->sum('total_quantity'),
            ],
        ]);
    }

    public function locationContents(Request $request)
    {
        $data = InventoryPackage::query()
            ->join('warehouse_locations as wl', 'inventory_packages.warehouse_location_id', '=', 'wl.id')
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
            ->where('wl.location_code', strtoupper(trim((string) $request->query('location_code'))))
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

        return response()->json([
            'data' => $data,
            'summary' => [
                'item_count' => $data->pluck('internal_item_code')->filter()->unique()->count(),
                'package_count' => $data->sum('package_count'),
                'total_quantity' => $data->sum('total_quantity'),
            ],
        ]);
    }

    public function storePackage(Request $request)
    {
        $data = $request->validate([
            'location_code' => 'nullable|string|max:100',
            'ma_sp' => 'required|string|max:100',
            'ma_ko' => 'nullable|string|max:50',
            'internal_item_code' => 'nullable|string|max:100',
            'size' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:100',
            'side' => 'nullable|string|max:100',
            'quantity' => 'required|numeric|min:0',
            'checked_at' => 'required|date',
            'note' => 'nullable|string|max:500',
        ]);

        $locationCode = strtoupper(trim($data['location_code'] ?? '')) ?: 'CHUA-XEP';
        $warehouseCode = strtoupper(trim($data['ma_ko'] ?? ''));
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

        $catalogItem = DB::table('TSoft_NhanTG_kt_new.dbo.CodeHanghoa')
            ->where('Ma_hh', trim($data['ma_sp']))
            ->select('Ten_hh', 'Dvt')
            ->first();

        $attributes = [
            'ma_sp' => trim($data['ma_sp']),
            'ma_ko' => trim($data['ma_ko'] ?? ''),
            'internal_item_code' => trim($data['internal_item_code'] ?? ''),
            'size' => trim($data['size'] ?? ''),
            'color' => trim($data['color'] ?? ''),
            'side' => trim($data['side'] ?? ''),
            'checked_at' => $data['checked_at'],
        ];

        [$package, $receipt] = DB::connection('internal')->transaction(function () use ($attributes, $data, $location, $catalogItem) {
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

            $receipt = InternalMaterialReceipt::query()->create([
                'receipt_code' => $this->nextMaterialReceiptCode(),
                'receipt_date' => $attributes['checked_at'],
                'warehouse_code' => $attributes['ma_ko'],
                'location_code' => $location->location_code,
                'receiver_name' => '',
                'source' => 'Nhap thanh pham tai cho',
                'status' => 'posted',
                'note' => $data['note'] ?? null,
            ]);

            $receipt->lines()->create([
                'inventory_package_id' => $package->id,
                'ma_hh' => $attributes['ma_sp'],
                'ten_hh' => $catalogItem->Ten_hh ?? '',
                'dvt' => $catalogItem->Dvt ?? '',
                'quantity' => $data['quantity'],
                'location_code' => $location->location_code,
                'internal_item_code' => $attributes['internal_item_code'],
                'size' => $attributes['size'],
                'color' => $attributes['color'],
                'side' => $attributes['side'],
                'note' => $data['note'] ?? null,
            ]);

            return [$package, $receipt];
        });

        return response()->json([
            'message' => 'Đã lưu kiện nội bộ.',
            'data' => $package->load('location:id,location_code'),
            'print_url' => url('/client/kiem-ton-kho/tem-kien/' . $package->id),
            'receipt_print_url' => url('/client/nhap-thanh-pham-noi-bo/' . $receipt->id . '/in'),
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
            $sourceLocationId = $inventoryPackage->warehouse_location_id;
            $sourceCount = $inventoryPackage->inventory_count_id
                ? InternalInventoryCount::query()->lockForUpdate()->find($inventoryPackage->inventory_count_id)
                : null;
            $quantity = (float) $inventoryPackage->quantity;
            $targetWarehouseCode = $targetLocation->warehouse_code ?: $inventoryPackage->ma_ko;

            if ((string) $inventoryPackage->ma_ko !== (string) $targetWarehouseCode) {
                if ($sourceCount) {
                    $remainingQuantity = (float) $sourceCount->counted_quantity - $quantity;
                    $hasOtherPackages = InventoryPackage::query()
                        ->where('inventory_count_id', $sourceCount->id)
                        ->where('id', '!=', $inventoryPackage->id)
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
                        'ma_sp' => $inventoryPackage->ma_sp,
                        'ma_ko' => $targetWarehouseCode,
                        'internal_item_code' => $inventoryPackage->internal_item_code,
                        'size' => $inventoryPackage->size,
                        'color' => $inventoryPackage->color,
                        'side' => $inventoryPackage->side,
                        'checked_at' => $inventoryPackage->checked_at,
                    ],
                    [
                        'counted_quantity' => 0,
                        'note' => $inventoryPackage->note,
                    ]
                );
                $targetCount->counted_quantity = (float) $targetCount->counted_quantity + $quantity;
                $targetCount->save();

                $inventoryPackage->ma_ko = $targetWarehouseCode;
                $inventoryPackage->inventory_count_id = $targetCount->id;
            }

            $inventoryPackage->warehouse_location_id = $targetLocation->id;
            $inventoryPackage->save();

            $targetLocation->status = 'counting';
            $targetLocation->save();

            $sourceLocation = WarehouseLocation::query()->find($sourceLocationId);
            if ($sourceLocation && !InventoryPackage::query()->where('warehouse_location_id', $sourceLocation->id)->exists()) {
                $sourceLocation->status = 'pending';
                $sourceLocation->save();
            }
        });

        return response()->json([
            'message' => 'Đã chuyển kiện sang vị trí mới.',
            'data' => $inventoryPackage->fresh()->load('location:id,location_code'),
        ]);
    }

    public function printLocation(WarehouseLocation $warehouseLocation)
    {
        return view('client.labels.location', ['location' => $warehouseLocation]);
    }

    public function printMaterialReceipt(InternalMaterialReceipt $receipt)
    {
        return view('client.internal-material-receipt-print', [
            'receipt' => $receipt->load('lines'),
        ]);
    }

    private function nextPackageCode()
    {
        $prefix = 'PK-' . now()->format('Ymd') . '-';
        $last = InventoryPackage::query()
            ->where('package_code', 'like', $prefix . '%')
            ->orderByDesc('package_code')
            ->value('package_code');

        $number = $last ? ((int) substr($last, -5)) + 1 : 1;

        return $prefix . str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }

    private function nextMaterialReceiptCode()
    {
        $prefix = 'PNTP-' . now()->format('Ymd') . '-';
        $last = InternalMaterialReceipt::query()
            ->where('receipt_code', 'like', $prefix . '%')
            ->orderByDesc('receipt_code')
            ->value('receipt_code');

        $number = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }

    private function inferShelfCode($locationCode)
    {
        preg_match('/[A-Z]/', strtoupper((string) $locationCode), $matches);

        return $matches[0] ?? null;
    }
}
