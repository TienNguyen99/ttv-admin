<?php

namespace App\Http\Controllers;

use App\Models\InternalInventoryCount;
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
            'location_name' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
        ]);

        $data['location_code'] = strtoupper(trim($data['location_code']));
        $data['warehouse_code'] = strtoupper(trim($data['warehouse_code'] ?? ''));

        $location = WarehouseLocation::query()->updateOrCreate(
            ['location_code' => $data['location_code']],
            [
                'warehouse_code' => $data['warehouse_code'],
                'location_name' => $data['location_name'] ?? null,
                'note' => $data['note'] ?? null,
            ]
        );

        return response()->json(['data' => $location]);
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

        return response()->json(['data' => $query->limit(100)->get()]);
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
            'location_code' => 'required|string|max:100',
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

        $locationCode = strtoupper(trim($data['location_code']));
        $warehouseCode = strtoupper(trim($data['ma_ko'] ?? ''));
        $location = WarehouseLocation::query()->firstOrCreate(
            ['location_code' => $locationCode],
            ['warehouse_code' => $warehouseCode]
        );

        $attributes = [
            'ma_sp' => trim($data['ma_sp']),
            'ma_ko' => trim($data['ma_ko'] ?? ''),
            'internal_item_code' => trim($data['internal_item_code'] ?? ''),
            'size' => trim($data['size'] ?? ''),
            'color' => trim($data['color'] ?? ''),
            'side' => trim($data['side'] ?? ''),
            'checked_at' => $data['checked_at'],
        ];

        $package = DB::connection('internal')->transaction(function () use ($attributes, $data, $location) {
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

            return $package;
        });

        return response()->json([
            'message' => 'Đã lưu kiện nội bộ.',
            'data' => $package->load('location:id,location_code'),
            'print_url' => url('/client/kiem-ton-kho/tem-kien/' . $package->id),
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

    public function printLocation(WarehouseLocation $warehouseLocation)
    {
        return view('client.labels.location', ['location' => $warehouseLocation]);
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
}
