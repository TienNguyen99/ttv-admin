<?php

namespace App\Http\Controllers;

use App\Models\InternalInventoryCount;
use App\Models\InternalMaterialIssue;
use App\Models\InventoryPackage;
use App\Models\WarehouseLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InternalMaterialIssueController extends Controller
{
    public function index()
    {
        return view('client.internal-material-issue');
    }

    public function list(Request $request)
    {
        $query = InternalMaterialIssue::query()
            ->withCount('lines')
            ->withSum('lines', 'quantity')
            ->orderByDesc('issue_date')
            ->orderByDesc('id');

        if ($request->filled('from_date')) {
            $query->whereDate('issue_date', '>=', $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('issue_date', '<=', $request->query('to_date'));
        }

        $keyword = trim((string) $request->query('keyword', ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('issue_code', 'like', '%' . $keyword . '%')
                    ->orWhere('warehouse_code', 'like', '%' . $keyword . '%')
                    ->orWhere('receiver_name', 'like', '%' . $keyword . '%')
                    ->orWhere('department', 'like', '%' . $keyword . '%')
                    ->orWhere('production_order', 'like', '%' . $keyword . '%')
                    ->orWhereHas('lines', function ($lineQuery) use ($keyword) {
                        $lineQuery->where('ma_hh', 'like', '%' . $keyword . '%')
                            ->orWhere('ten_hh', 'like', '%' . $keyword . '%')
                            ->orWhere('location_code', 'like', '%' . $keyword . '%');
                    });
            });
        }

        $data = $query->limit(200)->get();

        return response()->json([
            'data' => $data,
            'summary' => [
                'total_issues' => $data->count(),
                'total_lines' => $data->sum('lines_count'),
                'total_quantity' => (float) $data->sum('lines_sum_quantity'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'issue_date' => 'required|date',
            'warehouse_code' => 'nullable|string|max:50',
            'receiver_name' => 'nullable|string|max:150',
            'department' => 'nullable|string|max:150',
            'production_order' => 'nullable|string|max:100',
            'purpose' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
            'lines' => 'required|array|min:1',
            'lines.*.ma_hh' => 'required|string|max:100',
            'lines.*.ten_hh' => 'nullable|string|max:255',
            'lines.*.dvt' => 'nullable|string|max:50',
            'lines.*.quantity' => 'required|numeric|min:0.001',
            'lines.*.location_code' => 'nullable|string|max:100',
            'lines.*.internal_item_code' => 'nullable|string|max:100',
            'lines.*.size' => 'nullable|string|max:100',
            'lines.*.color' => 'nullable|string|max:100',
            'lines.*.note' => 'nullable|string|max:500',
        ]);

        $issue = DB::connection('internal')->transaction(function () use ($data) {
            $issue = InternalMaterialIssue::query()->create([
                'issue_code' => $this->nextIssueCode(),
                'issue_date' => $data['issue_date'],
                'warehouse_code' => strtoupper(trim($data['warehouse_code'] ?? '')),
                'receiver_name' => trim($data['receiver_name'] ?? ''),
                'department' => trim($data['department'] ?? ''),
                'production_order' => trim($data['production_order'] ?? ''),
                'purpose' => trim($data['purpose'] ?? ''),
                'status' => 'posted',
                'note' => trim($data['note'] ?? ''),
            ]);

            foreach ($data['lines'] as $line) {
                $this->decreaseInternalStock($line, strtoupper(trim($data['warehouse_code'] ?? '')));

                $issue->lines()->create([
                    'ma_hh' => strtoupper(trim($line['ma_hh'])),
                    'ten_hh' => trim($line['ten_hh'] ?? ''),
                    'dvt' => trim($line['dvt'] ?? ''),
                    'quantity' => $line['quantity'],
                    'location_code' => strtoupper(trim($line['location_code'] ?? '')),
                    'internal_item_code' => trim($line['internal_item_code'] ?? ''),
                    'size' => trim($line['size'] ?? ''),
                    'color' => trim($line['color'] ?? ''),
                    'note' => trim($line['note'] ?? ''),
                ]);
            }

            return $issue->load('lines');
        });

        return response()->json([
            'message' => 'Đã tạo phiếu xuất vật tư nội bộ.',
            'data' => $issue,
            'print_url' => url('/client/xuat-vat-tu-noi-bo/' . $issue->id . '/in'),
        ]);
    }

    public function show(InternalMaterialIssue $issue)
    {
        return response()->json([
            'data' => $issue->load('lines'),
        ]);
    }

    public function destroy(InternalMaterialIssue $issue)
    {
        DB::connection('internal')->transaction(function () use ($issue) {
            $issue->load('lines');

            foreach ($issue->lines as $line) {
                $this->increaseInternalStock([
                    'ma_hh' => $line->ma_hh,
                    'quantity' => $line->quantity,
                    'location_code' => $line->location_code,
                    'internal_item_code' => $line->internal_item_code,
                    'size' => $line->size,
                    'color' => $line->color,
                    'note' => 'Hoan phieu xuat ' . $issue->issue_code,
                ], $issue->warehouse_code, $issue->issue_date);
            }

            $issue->delete();
        });

        return response()->json([
            'message' => 'Đã xóa phiếu xuất vật tư nội bộ.',
        ]);
    }

    public function print(InternalMaterialIssue $issue)
    {
        return view('client.internal-material-issue-print', [
            'issue' => $issue->load('lines'),
        ]);
    }

    public function materialSuggestions(Request $request)
    {
        $keyword = trim((string) $request->query('keyword', ''));

        if ($keyword === '') {
            return response()->json(['data' => []]);
        }

        $data = DB::connection('sqlsrv')->table('TSoft_NhanTG_kt_new.dbo.CodeHanghoa as c')
            ->where(function ($query) use ($keyword) {
                $query->where('c.Ma_hh', 'like', '%' . $keyword . '%')
                    ->orWhere('c.Ten_hh', 'like', '%' . $keyword . '%');
            })
            ->select('c.Ma_hh', 'c.Ten_hh', 'c.Dvt')
            ->orderBy('c.Ma_hh')
            ->limit(20)
            ->get();

        return response()->json(['data' => $data]);
    }

    private function nextIssueCode()
    {
        $prefix = 'PXVT-' . now()->format('Ymd') . '-';
        $last = InternalMaterialIssue::query()
            ->where('issue_code', 'like', $prefix . '%')
            ->orderByDesc('issue_code')
            ->value('issue_code');

        $number = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }

    private function decreaseInternalStock(array $line, string $warehouseCode): void
    {
        $requestedQuantity = (float) $line['quantity'];
        $remaining = $requestedQuantity;
        $maHh = strtoupper(trim($line['ma_hh']));
        $locationCode = strtoupper(trim($line['location_code'] ?? ''));
        $internalCode = trim($line['internal_item_code'] ?? '');
        $size = trim($line['size'] ?? '');
        $color = trim($line['color'] ?? '');

        $query = InventoryPackage::query()
            ->where('ma_sp', $maHh)
            ->where('quantity', '>', 0)
            ->orderBy('checked_at')
            ->orderBy('id')
            ->lockForUpdate();

        if ($warehouseCode !== '') {
            $query->where('ma_ko', $warehouseCode);
        }

        if ($locationCode !== '') {
            $query->whereHas('location', function ($q) use ($locationCode) {
                $q->where('location_code', $locationCode);
            });
        }

        if ($internalCode !== '') {
            $query->where('internal_item_code', $internalCode);
        }

        if ($size !== '') {
            $query->where('size', $size);
        }

        if ($color !== '') {
            $query->where('color', $color);
        }

        $packages = $query->get();
        $available = (float) $packages->sum('quantity');

        if ($available + 0.0001 < $requestedQuantity) {
            throw ValidationException::withMessages([
                'lines' => "Ton noi bo khong du cho ma {$maHh}. Can {$requestedQuantity}, hien co {$available}.",
            ]);
        }

        foreach ($packages as $package) {
            if ($remaining <= 0) {
                break;
            }

            $takeQuantity = min((float) $package->quantity, $remaining);
            $remaining -= $takeQuantity;
            $package->quantity = (float) $package->quantity - $takeQuantity;

            $count = $package->inventory_count_id
                ? InternalInventoryCount::query()->lockForUpdate()->find($package->inventory_count_id)
                : null;

            if ($count) {
                $count->counted_quantity = max(0, (float) $count->counted_quantity - $takeQuantity);
                $hasOtherPackages = InventoryPackage::query()
                    ->where('inventory_count_id', $count->id)
                    ->where('id', '!=', $package->id)
                    ->exists();

                if ((float) $count->counted_quantity <= 0 && !$hasOtherPackages && (float) $package->quantity <= 0) {
                    $count->delete();
                } else {
                    $count->save();
                }
            }

            if ((float) $package->quantity <= 0) {
                $location = $package->warehouse_location_id
                    ? WarehouseLocation::query()->find($package->warehouse_location_id)
                    : null;

                $package->delete();

                if ($location && !InventoryPackage::query()->where('warehouse_location_id', $location->id)->exists()) {
                    $location->status = 'pending';
                    $location->save();
                }
            } else {
                $package->save();
            }
        }
    }

    private function increaseInternalStock(array $line, string $warehouseCode, $checkedAt): void
    {
        $locationCode = strtoupper(trim($line['location_code'] ?? '')) ?: 'CHUA-XEP';
        $location = WarehouseLocation::query()->firstOrCreate(
            ['location_code' => $locationCode],
            [
                'warehouse_code' => strtoupper(trim($warehouseCode)),
                'shelf_code' => 'CX',
                'tier' => 1,
                'grid_x' => 1,
                'grid_y' => 1,
                'grid_w' => 4,
                'grid_h' => 2,
                'location_name' => 'Chua xep vi tri',
            ]
        );

        $attributes = [
            'ma_sp' => strtoupper(trim($line['ma_hh'])),
            'ma_ko' => strtoupper(trim($warehouseCode)),
            'internal_item_code' => trim($line['internal_item_code'] ?? ''),
            'size' => trim($line['size'] ?? ''),
            'color' => trim($line['color'] ?? ''),
            'side' => '',
            'checked_at' => $checkedAt,
        ];

        $count = InternalInventoryCount::query()->firstOrCreate($attributes, [
            'counted_quantity' => 0,
            'note' => $line['note'] ?? null,
        ]);
        $count->counted_quantity = (float) $count->counted_quantity + (float) $line['quantity'];
        $count->save();

        InventoryPackage::query()->create(array_merge($attributes, [
            'package_code' => $this->nextPackageCode(),
            'warehouse_location_id' => $location->id,
            'inventory_count_id' => $count->id,
            'quantity' => $line['quantity'],
            'note' => $line['note'] ?? null,
        ]));

        $location->status = 'counting';
        $location->save();
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
