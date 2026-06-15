<?php

namespace App\Http\Controllers;

use App\Models\InternalInventoryCount;
use App\Models\InternalOpeningStock;
use App\Models\InventoryPackage;
use App\Services\InternalStockLedger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryComparisonController extends Controller
{
    public function index()
    {
        return view('client.inventory-comparison');
    }

    public function data(Request $request)
    {
        $month = Carbon::parse($request->query('month', now()->format('Y-m')) . '-01')->startOfMonth();
        $monthStart = $month->format('Y-m-d');
        $monthEnd = $month->copy()->endOfMonth()->format('Y-m-d');
        $warehouseCode = strtoupper(trim((string) $request->query('warehouse_code', '')));
        $keyword = trim((string) $request->query('keyword', ''));

        $subNhap = DB::connection('sqlsrv')->table('TSoft_NhanTG_kt_new.dbo.DataKetoan2026')
            ->select('Ma_sp', DB::raw("COALESCE(Ma3ko, Ma_ko, '') as Ma_ko"), 'Noluong', 'SttRecN')
            ->where('Ma_ct', '=', 'NX')
            ->whereNotNull('Ma_sp')
            ->whereDate('Ngay_ct', '<=', $monthEnd)
            ->distinct();

        $nhap = DB::connection('sqlsrv')->query()
            ->fromSub($subNhap, 'sub')
            ->select('Ma_sp', DB::raw("COALESCE(Ma_ko, '') as Ma_ko"), DB::raw('SUM(Noluong) as tong_nhap'))
            ->groupBy('Ma_sp', 'Ma_ko');

        $xuat = DB::connection('sqlsrv')->table('TSoft_NhanTG_kt_new.dbo.DataKetoan2026')
            ->select('Ma_hh as Ma_sp', DB::raw("COALESCE(Ma_ko, Ma3ko, '') as Ma_ko"), DB::raw('SUM(Soluong) as tong_xuat'))
            ->where('Ma_ct', '=', 'XU')
            ->whereNotNull('Ma_hh')
            ->whereDate('Ngay_ct', '<=', $monthEnd)
            ->groupBy('Ma_hh', DB::raw("COALESCE(Ma_ko, Ma3ko, '')"));

        if ($warehouseCode !== '') {
            $nhap->where('Ma_ko', $warehouseCode);
            $xuat->where(DB::raw("COALESCE(Ma_ko, Ma3ko, '')"), $warehouseCode);
        }

        $maHang = DB::connection('sqlsrv')->query()
            ->fromSub((clone $nhap)->union(clone $xuat), 'codes')
            ->select('Ma_sp', 'Ma_ko')
            ->groupBy('Ma_sp', 'Ma_ko');

        $source = DB::connection('sqlsrv')->query()
            ->fromSub($maHang, 'mh')
            ->leftJoinSub($nhap, 'n', function ($join) {
                $join->on('mh.Ma_sp', '=', 'n.Ma_sp')->on('mh.Ma_ko', '=', 'n.Ma_ko');
            })
            ->leftJoinSub($xuat, 'x', function ($join) {
                $join->on('mh.Ma_sp', '=', 'x.Ma_sp')->on('mh.Ma_ko', '=', 'x.Ma_ko');
            })
            ->leftJoin('TSoft_NhanTG_kt_new.dbo.CodeHanghoa as c', 'mh.Ma_sp', '=', 'c.Ma_hh')
            ->select(
                'mh.Ma_sp',
                'mh.Ma_ko',
                'c.Ten_hh',
                'c.Dvt',
                DB::raw('COALESCE(n.tong_nhap, 0) as tong_nhap'),
                DB::raw('COALESCE(x.tong_xuat, 0) as tong_xuat'),
                DB::raw('COALESCE(n.tong_nhap, 0) - COALESCE(x.tong_xuat, 0) as source_quantity')
            )
            ->orderBy('mh.Ma_sp')
            ->orderBy('mh.Ma_ko')
            ->get();

        $internalRows = $this->internalStockRows($monthStart, $monthEnd, $warehouseCode);
        $internal = $internalRows->groupBy(fn ($item) => $item->ma_sp . '|' . $item->warehouse_code);

        $mapDetails = function ($details) {
            return $details->map(fn ($detail) => [
                'location_code' => $detail->location_code,
                'internal_item_code' => $detail->internal_item_code,
                'size' => $detail->size,
                'color' => $detail->color,
                'side' => $detail->side,
                'opening_quantity' => (float) $detail->opening_quantity,
                'receipt_quantity' => (float) $detail->receipt_quantity,
                'issue_quantity' => (float) $detail->issue_quantity,
                'counted_quantity' => (float) $detail->total_quantity,
            ])->values();
        };

        $sourceKeys = $source->mapWithKeys(function ($item) {
            return [$item->Ma_sp . '|' . $item->Ma_ko => true];
        });

        $data = $source->map(function ($item) use ($internal, $mapDetails) {
            $details = $internal->get($item->Ma_sp . '|' . $item->Ma_ko, collect());
            $countedQuantity = $details->isEmpty() ? null : (float) $details->sum('total_quantity');
            $sourceQuantity = (float) $item->source_quantity;
            $missingReceipt = (float) $item->tong_xuat > 0 && (float) $item->tong_nhap <= 0;

            return [
                'ma_sp' => $item->Ma_sp,
                'ma_ko' => $item->Ma_ko,
                'ten_hh' => $item->Ten_hh,
                'dvt' => $item->Dvt,
                'tong_nhap' => (float) $item->tong_nhap,
                'tong_xuat' => (float) $item->tong_xuat,
                'source_quantity' => $sourceQuantity,
                'counted_quantity' => $countedQuantity,
                'difference' => $countedQuantity === null ? null : $countedQuantity - $sourceQuantity,
                'missing_receipt' => $missingReceipt,
                'internal_only' => false,
                'details' => $mapDetails($details),
            ];
        });

        foreach ($internal as $key => $details) {
            if ($sourceKeys->has($key)) {
                continue;
            }

            [$maSp, $maKo] = array_pad(explode('|', $key, 2), 2, '');
            $countedQuantity = (float) $details->sum('total_quantity');

            $data->push([
                'ma_sp' => $maSp,
                'ma_ko' => $maKo,
                'ten_hh' => null,
                'dvt' => null,
                'tong_nhap' => 0,
                'tong_xuat' => 0,
                'source_quantity' => 0,
                'counted_quantity' => $countedQuantity,
                'difference' => $countedQuantity,
                'missing_receipt' => false,
                'internal_only' => true,
                'details' => $mapDetails($details),
            ]);
        }

        if ($keyword !== '') {
            $existingKeys = $data->mapWithKeys(function ($item) {
                return [$item['ma_sp'] . '|' . $item['ma_ko'] => true];
            });

            $catalogItems = DB::connection('sqlsrv')->table('TSoft_NhanTG_kt_new.dbo.CodeHanghoa')
                ->where(function ($query) use ($keyword) {
                    $query->where('Ma_hh', 'like', '%' . $keyword . '%')
                        ->orWhere('Ten_hh', 'like', '%' . $keyword . '%');
                })
                ->select('Ma_hh', 'Ten_hh', 'Dvt')
                ->orderBy('Ma_hh')
                ->limit(50)
                ->get();

            foreach ($catalogItems as $catalogItem) {
                $key = $catalogItem->Ma_hh . '|';

                if ($existingKeys->has($key)) {
                    continue;
                }

                $data->push([
                    'ma_sp' => $catalogItem->Ma_hh,
                    'ma_ko' => '',
                    'ten_hh' => $catalogItem->Ten_hh,
                    'dvt' => $catalogItem->Dvt,
                    'tong_nhap' => 0,
                    'tong_xuat' => 0,
                    'source_quantity' => 0,
                    'counted_quantity' => null,
                    'difference' => null,
                    'missing_receipt' => false,
                    'internal_only' => false,
                    'catalog_only' => true,
                    'details' => [],
                ]);

                $existingKeys->put($key, true);
            }
        }

        if ($keyword !== '') {
            $lowerKeyword = mb_strtolower($keyword);
            $data = $data->filter(function ($item) use ($lowerKeyword) {
                $text = mb_strtolower(implode(' ', [
                    $item['ma_sp'] ?? '',
                    $item['ma_ko'] ?? '',
                    $item['ten_hh'] ?? '',
                    collect($item['details'] ?? [])->pluck('internal_item_code')->implode(' '),
                    collect($item['details'] ?? [])->pluck('location_code')->implode(' '),
                ]));

                return str_contains($text, $lowerKeyword);
            });
        }

        $data = $data->sortBy([
            ['ma_sp', 'asc'],
            ['ma_ko', 'asc'],
        ])->values();

        $tsoftQuantity = (float) $data->sum('source_quantity');
        $internalQuantity = (float) $data->sum('counted_quantity');

        return response()->json([
            'month' => $month->format('Y-m'),
            'data' => $data,
            'summary' => [
                'total_items' => $data->count(),
                'unique_items' => $data->pluck('ma_sp')->filter()->unique()->count(),
                'checked_items' => $data->whereNotNull('counted_quantity')->count(),
                'different_items' => $data->filter(fn ($item) => $item['difference'] !== null && (float) $item['difference'] !== 0.0)->count(),
                'missing_receipt_items' => $data->where('missing_receipt', true)->count(),
                'tsoft_quantity' => $tsoftQuantity,
                'internal_quantity' => $internalQuantity,
                'difference_quantity' => $internalQuantity - $tsoftQuantity,
            ],
        ]);
    }

    private function internalStockRows(string $monthStart, string $monthEnd, string $warehouseCode)
    {
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
                DB::raw('SUM(opening_quantity + receipt_quantity - issue_quantity) as total_quantity')
            );

        if ($warehouseCode !== '') {
            $query->where('warehouse_code', $warehouseCode);
        }

        return $query
            ->groupBy('warehouse_code', 'location_code', 'ma_hh', 'internal_item_code', 'size', 'color', 'side')
            ->havingRaw('SUM(opening_quantity + receipt_quantity - issue_quantity) != 0 OR SUM(opening_quantity) != 0 OR SUM(receipt_quantity) != 0 OR SUM(issue_quantity) != 0')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'ma_sp' => 'required|string|max:100',
            'ma_ko' => 'nullable|string|max:50',
            'internal_item_code' => 'nullable|string|max:100',
            'size' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:100',
            'side' => 'nullable|string|max:100',
            'counted_quantity' => 'required|numeric',
            'checked_at' => 'required|date',
            'note' => 'nullable|string|max:500',
        ]);

        $data['ma_ko'] = $data['ma_ko'] ?? '';
        $data['internal_item_code'] = trim($data['internal_item_code'] ?? '');
        $data['size'] = trim($data['size'] ?? '');
        $data['color'] = trim($data['color'] ?? '');
        $data['side'] = trim($data['side'] ?? '');

        $count = InternalInventoryCount::query()->updateOrCreate(
            [
                'ma_sp' => $data['ma_sp'],
                'ma_ko' => $data['ma_ko'],
                'internal_item_code' => $data['internal_item_code'],
                'size' => $data['size'],
                'color' => $data['color'],
                'side' => $data['side'],
                'checked_at' => $data['checked_at'],
            ],
            [
                'counted_quantity' => $data['counted_quantity'],
                'note' => $data['note'] ?? null,
            ]
        );

        return response()->json([
            'message' => 'Đã lưu số kiểm kê nội bộ.',
            'data' => $count,
        ]);
    }

    public function destroy(InternalInventoryCount $inventoryCount)
    {
        DB::connection('internal')->transaction(function () use ($inventoryCount) {
            $packages = InventoryPackage::query()
                ->where('inventory_count_id', $inventoryCount->id)
                ->get();

            foreach ($packages as $package) {
                InternalOpeningStock::query()
                    ->where('inventory_package_id', $package->id)
                    ->delete();

                $receiptLines = $package->receiptLines()->with('receipt.lines')->get();
                foreach ($receiptLines as $line) {
                    $receipt = $line->receipt;
                    $line->delete();

                    if ($receipt && !$receipt->lines()->exists()) {
                        $receipt->delete();
                    }
                }

                $package->delete();
            }

            $inventoryCount->delete();
        });

        return response()->json([
            'message' => 'Đã xóa dòng kiểm kê nội bộ.',
        ]);
    }
}
