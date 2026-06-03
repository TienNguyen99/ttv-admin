<?php

namespace App\Http\Controllers;

use App\Models\InternalInventoryCount;
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
        $checkedAt = $request->query('checked_at', now()->format('Y-m-d'));

        $subNhap = DB::table('TSoft_NhanTG_kt_new.dbo.DataKetoan2026')
            ->select('Ma_sp', DB::raw("COALESCE(Ma3ko, Ma_ko, '') as Ma_ko"), 'Noluong', 'SttRecN')
            ->where('Ma_ct', '=', 'NX')
            ->whereNotNull('Ma_sp')
            ->distinct();

        $nhap = DB::query()
            ->fromSub($subNhap, 'sub')
            ->select('Ma_sp', DB::raw("COALESCE(Ma_ko, '') as Ma_ko"), DB::raw('SUM(Noluong) as tong_nhap'))
            ->groupBy('Ma_sp', 'Ma_ko');

        $xuat = DB::table('TSoft_NhanTG_kt_new.dbo.DataKetoan2026')
            ->select('Ma_hh as Ma_sp', DB::raw("COALESCE(Ma_ko, Ma3ko, '') as Ma_ko"), DB::raw('SUM(Soluong) as tong_xuat'))
            ->where('Ma_ct', '=', 'XU')
            ->whereNotNull('Ma_hh')
            ->groupBy('Ma_hh', DB::raw("COALESCE(Ma_ko, Ma3ko, '')"));

        $maHang = DB::query()
            ->fromSub((clone $nhap)->union(clone $xuat), 'codes')
            ->select('Ma_sp', 'Ma_ko')
            ->groupBy('Ma_sp', 'Ma_ko');

        $source = DB::query()
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

        $internal = InternalInventoryCount::query()
            ->whereDate('checked_at', Carbon::parse($checkedAt)->format('Y-m-d'))
            ->get()
            ->groupBy(fn ($item) => $item->ma_sp . '|' . $item->ma_ko);

        $mapDetails = function ($details) {
            return $details->map(function ($detail) {
                return [
                    'id' => $detail->id,
                    'internal_item_code' => $detail->internal_item_code,
                    'size' => $detail->size,
                    'color' => $detail->color,
                    'side' => $detail->side,
                    'counted_quantity' => (float) $detail->counted_quantity,
                    'note' => $detail->note,
                ];
            })->values();
        };

        $sourceKeys = $source->mapWithKeys(function ($item) {
            return [$item->Ma_sp . '|' . $item->Ma_ko => true];
        });

        $data = $source->map(function ($item) use ($internal, $mapDetails) {
            $details = $internal->get($item->Ma_sp . '|' . $item->Ma_ko, collect());
            $countedQuantity = $details->isEmpty() ? null : (float) $details->sum('counted_quantity');
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
            $countedQuantity = (float) $details->sum('counted_quantity');

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

        $data = $data->sortBy([
            ['ma_sp', 'asc'],
            ['ma_ko', 'asc'],
        ])->values();

        return response()->json([
            'checked_at' => $checkedAt,
            'data' => $data,
            'summary' => [
                'total_items' => $data->count(),
                'unique_items' => $data->pluck('ma_sp')->filter()->unique()->count(),
                'checked_items' => $data->whereNotNull('counted_quantity')->count(),
                'different_items' => $data->where('difference', '!=', 0)->count(),
                'missing_receipt_items' => $data->where('missing_receipt', true)->count(),
            ],
        ]);
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
        $inventoryCount->delete();

        return response()->json([
            'message' => 'Đã xóa dòng kiểm kê nội bộ.',
        ]);
    }
}
