<?php

namespace App\Http\Controllers;

use App\Models\InternalInventoryCount;
use App\Models\InternalMaterialReceipt;
use App\Models\InternalOpeningStock;
use App\Models\InventoryPackage;
use App\Models\WarehouseLocation;
use Carbon\Carbon;
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

    public function receipts(Request $request)
    {
        $query = InternalMaterialReceipt::query()
            ->withCount('lines')
            ->withSum('lines as total_quantity', 'quantity')
            ->where('source', 'Phieu nhap thanh pham')
            ->orderByDesc('receipt_date')
            ->orderByDesc('id');

        if ($request->filled('receipt_date')) {
            $query->whereDate('receipt_date', $request->query('receipt_date'));
        }

        if ($request->filled('warehouse_code')) {
            $query->where('warehouse_code', strtoupper(trim((string) $request->query('warehouse_code'))));
        }

        if ($request->filled('location_code')) {
            $query->where('location_code', strtoupper(trim((string) $request->query('location_code'))));
        }

        $limit = min(max((int) $request->query('limit', 100), 1), 500);
        $receipts = $query->limit($limit)->get();

        return response()->json([
            'data' => $receipts->map(function ($receipt) {
                return [
                    'id' => $receipt->id,
                    'receipt_code' => $receipt->receipt_code,
                    'receipt_date' => optional($receipt->receipt_date)->format('Y-m-d'),
                    'warehouse_code' => $receipt->warehouse_code,
                    'location_code' => $receipt->location_code,
                    'note' => $receipt->note,
                    'lines_count' => (int) $receipt->lines_count,
                    'total_quantity' => (float) ($receipt->total_quantity ?? 0),
                    'print_url' => url('/client/nhap-thanh-pham-noi-bo/' . $receipt->id . '/in'),
                ];
            }),
            'summary' => [
                'receipt_count' => $receipts->count(),
                'line_count' => (int) $receipts->sum('lines_count'),
                'total_quantity' => (float) $receipts->sum('total_quantity'),
            ],
        ]);
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

        $opening = DB::connection('internal')->table('internal_opening_stocks')
            ->select(
                'warehouse_code',
                'location_code',
                'ma_hh',
                'internal_item_code',
                'size',
                'color',
                'side',
                DB::raw('SUM(quantity) as opening_quantity'),
                DB::raw('0 as receipt_quantity'),
                DB::raw('0 as issue_quantity')
            )
            ->whereDate('period_month', $monthStart)
            ->groupBy('warehouse_code', 'location_code', 'ma_hh', 'internal_item_code', 'size', 'color', 'side');

        $receipts = DB::connection('internal')->table('internal_material_receipt_lines as l')
            ->join('internal_material_receipts as r', 'r.id', '=', 'l.receipt_id')
            ->select(
                DB::raw("COALESCE(r.warehouse_code, '') as warehouse_code"),
                DB::raw("COALESCE(l.location_code, r.location_code, '') as location_code"),
                'l.ma_hh',
                DB::raw("COALESCE(l.internal_item_code, '') as internal_item_code"),
                DB::raw("COALESCE(l.size, '') as size"),
                DB::raw("COALESCE(l.color, '') as color"),
                DB::raw("COALESCE(l.side, '') as side"),
                DB::raw('0 as opening_quantity'),
                DB::raw('SUM(l.quantity) as receipt_quantity'),
                DB::raw('0 as issue_quantity')
            )
            ->whereBetween('r.receipt_date', [$monthStart, $monthEnd])
            ->where('r.source', 'Phieu nhap thanh pham')
            ->groupBy('r.warehouse_code', DB::raw("COALESCE(l.location_code, r.location_code, '')"), 'l.ma_hh', 'l.internal_item_code', 'l.size', 'l.color', 'l.side');

        $issues = DB::connection('internal')->table('internal_material_issue_lines as l')
            ->join('internal_material_issues as i', 'i.id', '=', 'l.issue_id')
            ->select(
                DB::raw("COALESCE(i.warehouse_code, '') as warehouse_code"),
                DB::raw("COALESCE(l.location_code, '') as location_code"),
                'l.ma_hh',
                DB::raw("COALESCE(l.internal_item_code, '') as internal_item_code"),
                DB::raw("COALESCE(l.size, '') as size"),
                DB::raw("COALESCE(l.color, '') as color"),
                DB::raw("'' as side"),
                DB::raw('0 as opening_quantity'),
                DB::raw('0 as receipt_quantity'),
                DB::raw('SUM(l.quantity) as issue_quantity')
            )
            ->whereBetween('i.issue_date', [$monthStart, $monthEnd])
            ->groupBy('i.warehouse_code', 'l.location_code', 'l.ma_hh', 'l.internal_item_code', 'l.size', 'l.color');

        $query = DB::connection('internal')->query()
            ->fromSub($opening->unionAll($receipts)->unionAll($issues), 'ledger')
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

        $data = $query
            ->groupBy('warehouse_code', 'location_code', 'ma_hh', 'internal_item_code', 'size', 'color', 'side')
            ->havingRaw('SUM(opening_quantity + receipt_quantity - issue_quantity) != 0 OR SUM(opening_quantity) != 0 OR SUM(receipt_quantity) != 0 OR SUM(issue_quantity) != 0')
            ->orderBy('warehouse_code')
            ->orderBy('location_code')
            ->orderBy('ma_hh')
            ->get();

        return response()->json([
            'data' => $data,
            'summary' => [
                'warehouse_code' => $warehouseCode,
                'month' => $month->format('Y-m'),
                'item_count' => $data->pluck('ma_sp')->filter()->unique()->count(),
                'line_count' => $data->count(),
                'opening_quantity' => (float) $data->sum('opening_quantity'),
                'receipt_quantity' => (float) $data->sum('receipt_quantity'),
                'issue_quantity' => (float) $data->sum('issue_quantity'),
                'package_count' => 0,
                'total_quantity' => (float) $data->sum('total_quantity'),
            ],
        ]);
    }

    public function locationContents(Request $request)
    {
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
            'entry_type' => 'nullable|in:opening,receipt',
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

        $catalogItem = DB::connection('sqlsrv')->table('TSoft_NhanTG_kt_new.dbo.CodeHanghoa')
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

        $entryType = $data['entry_type'] ?? 'opening';

        [$package, $receipt] = DB::connection('internal')->transaction(function () use ($attributes, $data, $location, $catalogItem, $entryType) {
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

    public function storeReceiptBatch(Request $request)
    {
        $data = $request->validate([
            'location_code' => 'nullable|string|max:100',
            'ma_ko' => 'nullable|string|max:50',
            'checked_at' => 'required|date',
            'note' => 'nullable|string|max:500',
            'lines' => 'required|array|min:1|max:50',
            'lines.*.category' => 'nullable|string|max:255',
            'lines.*.ma_sp' => 'nullable|string|max:100',
            'lines.*.internal_item_code' => 'nullable|string|max:100',
            'lines.*.size' => 'nullable|string|max:100',
            'lines.*.color' => 'nullable|string|max:100',
            'lines.*.side' => 'nullable|string|max:100',
            'lines.*.dvt' => 'nullable|string|max:50',
            'lines.*.quantity' => 'nullable|numeric|min:0',
            'lines.*.note' => 'nullable|string|max:500',
        ]);

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
                    'quantity' => (float) ($line['quantity'] ?? 0),
                    'note' => trim((string) ($line['note'] ?? '')),
                ];
            })
            ->filter(function ($line) {
                return $line['ma_sp'] !== '' && $line['quantity'] > 0;
            })
            ->values();

        if ($lines->isEmpty()) {
            return response()->json([
                'message' => 'Nhập ít nhất 1 dòng có Mã TP kế toán và Số lượng lớn hơn 0.',
            ], 422);
        }

        $locationCode = strtoupper(trim($data['location_code'] ?? '')) ?: 'CHUA-XEP';
        $warehouseCode = strtoupper(trim($data['ma_ko'] ?? ''));

        $catalogItems = DB::connection('sqlsrv')->table('TSoft_NhanTG_kt_new.dbo.CodeHanghoa')
            ->whereIn('Ma_hh', $lines->pluck('ma_sp')->unique()->all())
            ->select('Ma_hh', 'Ten_hh', 'Dvt')
            ->get()
            ->keyBy('Ma_hh');

        [$receipt, $packages] = DB::connection('internal')->transaction(function () use ($data, $lines, $locationCode, $warehouseCode, $catalogItems) {
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

            $receipt = InternalMaterialReceipt::query()->create([
                'receipt_code' => $this->nextMaterialReceiptCode(),
                'receipt_date' => $data['checked_at'],
                'warehouse_code' => $warehouseCode,
                'location_code' => $location->location_code,
                'receiver_name' => '',
                'source' => 'Phieu nhap thanh pham',
                'status' => 'posted',
                'note' => $data['note'] ?? null,
            ]);

            $packages = collect();

            foreach ($lines as $line) {
                $attributes = [
                    'ma_sp' => $line['ma_sp'],
                    'ma_ko' => $warehouseCode,
                    'internal_item_code' => $line['internal_item_code'],
                    'size' => $line['size'],
                    'color' => $line['color'],
                    'side' => $line['side'],
                    'checked_at' => $data['checked_at'],
                ];

                $count = InternalInventoryCount::query()->firstOrCreate(
                    $attributes,
                    [
                        'counted_quantity' => 0,
                        'note' => $line['note'] ?: ($data['note'] ?? null),
                    ]
                );

                $count->counted_quantity = (float) $count->counted_quantity + (float) $line['quantity'];
                $count->note = $line['note'] ?: $count->note;
                $count->save();

                $package = InventoryPackage::query()->create(array_merge($attributes, [
                    'package_code' => $this->nextPackageCode(),
                    'warehouse_location_id' => $location->id,
                    'inventory_count_id' => $count->id,
                    'quantity' => $line['quantity'],
                    'note' => $line['note'] ?: null,
                ]));

                $catalogItem = $catalogItems->get($line['ma_sp']);

                $receipt->lines()->create([
                    'inventory_package_id' => $package->id,
                    'ma_hh' => $line['ma_sp'],
                    'ten_hh' => $catalogItem->Ten_hh ?? $line['category'],
                    'dvt' => $catalogItem->Dvt ?? $line['dvt'],
                    'quantity' => $line['quantity'],
                    'location_code' => $location->location_code,
                    'internal_item_code' => $line['internal_item_code'],
                    'size' => $line['size'],
                    'color' => $line['color'],
                    'side' => $line['side'],
                    'note' => $line['note'] ?: null,
                ]);

                $packages->push($package);
            }

            $location->status = 'counting';
            $location->save();

            return [$receipt, $packages];
        });

        return response()->json([
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
        ]);
    }

    public function destroyReceipt(InternalMaterialReceipt $receipt)
    {
        DB::connection('internal')->transaction(function () use ($receipt) {
            $receipt->load('lines');
            $locationIds = [];

            foreach ($receipt->lines as $line) {
                $package = $line->inventory_package_id
                    ? InventoryPackage::query()->lockForUpdate()->find($line->inventory_package_id)
                    : null;

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

        return response()->json([
            'message' => 'Đã xóa phiếu nhập kho nội bộ.',
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
