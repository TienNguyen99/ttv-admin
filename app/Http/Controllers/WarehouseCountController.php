<?php

namespace App\Http\Controllers;

use App\Models\InternalInventoryCount;
use App\Models\InternalMaterialIssue;
use App\Models\InternalMaterialReceipt;
use App\Models\InternalOpeningStock;
use App\Models\InventoryPackage;
use App\Models\WarehouseLocation;
use App\Services\InternalAudit;
use App\Services\InternalDocumentNumber;
use App\Services\InternalStockLedger;
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

        $keyword = trim((string) $request->query('keyword', ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('receipt_code', 'like', '%' . $keyword . '%')
                    ->orWhere('note', 'like', '%' . $keyword . '%')
                    ->orWhereHas('lines', function ($lineQuery) use ($keyword) {
                        $lineQuery->where('ma_hh', 'like', '%' . $keyword . '%')
                            ->orWhere('internal_item_code', 'like', '%' . $keyword . '%')
                            ->orWhere('ten_hh', 'like', '%' . $keyword . '%');
                    });
            });
        }

        $limit = min(max((int) $request->query('limit', 100), 1), 500);
        $receipts = $query->limit($limit)->get();

        return response()->json([
            'data' => $receipts->map(function ($receipt) {
                $issue = InternalMaterialIssue::query()
                    ->where('source_receipt_id', $receipt->id)
                    ->orWhere('note', 'like', '%' . $receipt->receipt_code . '%')
                    ->orderByDesc('id')
                    ->first();

                return [
                    'id' => $receipt->id,
                    'receipt_code' => $receipt->receipt_code,
                    'receipt_date' => optional($receipt->receipt_date)->format('Y-m-d'),
                    'warehouse_code' => $receipt->warehouse_code,
                    'location_code' => $receipt->location_code,
                    'note' => $receipt->note,
                    'lines_count' => (int) $receipt->lines_count,
                    'total_quantity' => (float) ($receipt->total_quantity ?? 0),
                    'issue_status' => $issue ? 'exported' : 'not_exported',
                    'issue_code' => $issue->issue_code ?? null,
                    'issue_id' => $issue->id ?? null,
                    'issue_print_url' => $issue ? url('/client/xuat-vat-tu-noi-bo/' . $issue->id . '/in') : null,
                    'print_url' => url('/client/nhap-thanh-pham-noi-bo/' . $receipt->id . '/in'),
                ];
            }),
            'summary' => [
                'receipt_count' => $receipts->count(),
                'line_count' => (int) $receipts->sum('lines_count'),
                'total_quantity' => (float) $receipts->sum('total_quantity'),
                'exported_count' => $receipts->filter(function ($receipt) {
                    return InternalMaterialIssue::query()
                        ->where('source_receipt_id', $receipt->id)
                        ->orWhere('note', 'like', '%' . $receipt->receipt_code . '%')
                        ->exists();
                })->count(),
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

        if (trim((string) ($data['internal_item_code'] ?? '')) === '') {
            return response()->json([
                'message' => 'Mã nội bộ là bắt buộc. Mã kế toán có thể gán sau.',
            ], 422);
        }

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

        $accountingCode = strtoupper(trim((string) ($data['ma_sp'] ?? '')));
        $catalogItem = $accountingCode !== ''
            ? DB::connection('sqlsrv')->table('TSoft_NhanTG_kt_new.dbo.CodeHanghoa')
                ->where('Ma_hh', $accountingCode)
                ->select('Ten_hh', 'Dvt')
                ->first()
            : null;

        $attributes = [
            'ma_sp' => $accountingCode,
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
            'lines' => 'required|array|min:1|max:500',
            'lines.*.category' => 'nullable|string|max:255',
            'lines.*.ma_sp' => 'nullable|string|max:100',
            'lines.*.internal_item_code' => 'nullable|string|max:100',
            'lines.*.size' => 'nullable|string|max:100',
            'lines.*.color' => 'nullable|string|max:100',
            'lines.*.side' => 'nullable|string|max:100',
            'lines.*.dvt' => 'nullable|string|max:50',
            'lines.*.quantity' => 'nullable|numeric|min:0',
            'lines.*.location_code' => 'nullable|string|max:100',
            'lines.*.note' => 'nullable|string|max:500',
            'lines.*.production_order_id' => 'nullable|integer',
            'lines.*.production_order' => 'nullable|string|max:100',
            'lines.*.purchase_order' => 'nullable|string|max:1000',
            'lines.*.customer' => 'nullable|string|max:200',
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

        $locationCode = strtoupper(trim($data['location_code'] ?? '')) ?: 'CHUA-XEP';
        $warehouseCode = strtoupper(trim($data['ma_ko'] ?? ''));
        $lineLocationCodes = $lines->pluck('location_code')->filter()->unique()->values();
        $receiptLocationCode = $lineLocationCodes->count() === 1
            ? $lineLocationCodes->first()
            : ($lineLocationCodes->isEmpty() ? $locationCode : '');

        $accountingCodes = $lines->pluck('ma_sp')->filter()->unique()->all();
        $catalogItems = empty($accountingCodes)
            ? collect()
            : DB::connection('sqlsrv')->table('TSoft_NhanTG_kt_new.dbo.CodeHanghoa')
                ->whereIn('Ma_hh', $accountingCodes)
                ->select('Ma_hh', 'Ten_hh', 'Dvt')
                ->get()
                ->keyBy('Ma_hh');

        [$receipt, $packages] = DB::connection('internal')->transaction(function () use ($data, $lines, $locationCode, $receiptLocationCode, $warehouseCode, $catalogItems) {
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
                'location_code' => $receiptLocationCode,
                'receiver_name' => '',
                'source' => 'Phieu nhap thanh pham',
                'status' => 'posted',
                'note' => $data['note'] ?? null,
            ]);

            $packages = collect();

            foreach ($lines as $line) {
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
                    'warehouse_location_id' => $lineLocation->id,
                    'inventory_count_id' => $count->id,
                    'quantity' => $line['quantity'],
                    'note' => $line['note'] ?: null,
                ]));

                $catalogItem = $catalogItems->get($line['ma_sp']);

                $receipt->lines()->create([
                    'inventory_package_id' => $package->id,
                    'production_order_id' => $line['production_order_id'],
                    'production_order' => $line['production_order'],
                    'purchase_order' => $line['purchase_order'],
                    'customer' => $line['customer'],
                    'ma_hh' => $line['ma_sp'],
                    'ten_hh' => $catalogItem->Ten_hh ?? $line['category'],
                    'dvt' => $catalogItem->Dvt ?? $line['dvt'],
                    'quantity' => $line['quantity'],
                    'location_code' => $lineLocation->location_code,
                    'internal_item_code' => $line['internal_item_code'],
                    'size' => $line['size'],
                    'color' => $line['color'],
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

        $response = response()->json([
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

        app(InternalAudit::class)->model('receipt.created', $receipt, [
            'line_count' => $receipt->lines->count(),
            'total_quantity' => (float) $receipt->lines->sum('quantity'),
            'location_code' => $receipt->location_code,
        ], $request);

        return $response;
    }

    public function destroyReceipt(InternalMaterialReceipt $receipt)
    {
        $auditPayload = [
            'receipt_code' => $receipt->receipt_code,
            'receipt_date' => optional($receipt->receipt_date)->format('Y-m-d'),
            'location_code' => $receipt->location_code,
        ];

        $receipt->load('lines');
        foreach ($receipt->lines as $line) {
            $package = $line->inventory_package_id
                ? InventoryPackage::query()->find($line->inventory_package_id)
                : null;

            if (!$package || (float) $package->quantity + 0.0001 < (float) $line->quantity) {
                return response()->json([
                    'message' => 'Không thể xóa phiếu vì một phần hàng của phiếu đã được xuất kho hoặc thay đổi. Hãy hoàn/xóa phiếu xuất liên quan trước.',
                ], 422);
            }
        }

        DB::connection('internal')->transaction(function () use ($receipt) {
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
        preg_match('/[A-Z]/', strtoupper((string) $locationCode), $matches);

        return $matches[0] ?? null;
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
}
