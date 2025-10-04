<?php

namespace App\Http\Controllers;

use App\Models\DataKetoanData;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class TiviController extends Controller
{
    public function tiviIndex()
    {
        return view('client.tivi');
    }

    // API hiển thị Tivi
    public function getTiviData(Request $request)
    {
        $range = $request->get('range', 7);
        $today = now()->startOfDay(); // reset về 00:00:00

        $query = DataKetoanData::with(['khachHang', 'hangHoa'])
            ->where('Ma_ct', '=', 'GO')
            ->where('Loaisx', '!=', 'M');

        if ($range === 'overdue') {
            // 🔹 Quá hạn tất cả
            $query->whereDate('Date', '<', $today);

        } elseif ($range === 'overdue14') {
            // 🔹 Quá hạn trong vòng 14 ngày
            $twoWeeksAgo = $today->copy()->subDays(14);
            $query->whereBetween('Date', [$twoWeeksAgo, $today->copy()->subDay()]);

        } else {
            // 🔹 Đơn sắp đến hạn (today → today + range)
            $upcoming = $today->copy()->addDays((int)$range);
            $query->whereBetween('Date', [$today, $upcoming]);
        }

        $data = $query->orderBy('Date', 'asc')->get();

        // 🔹 Xuất kho kế toán
        $xuatkhotheomavvketoan = DB::table('DataKetoan2025 as dk')
            ->join('CodeHangHoa as hh', 'dk.Ma_hh', '=', 'hh.Ma_hh')
            ->select('dk.Ma_vv', 'hh.Ma_so', DB::raw('SUM(dk.Soluong) as xuatkhotheomavv_ketoan'))
            ->where('dk.Ma_ct', '=', 'XU')
            ->groupBy('dk.Ma_vv', 'hh.Ma_so')
            ->get()
            ->keyBy(fn($i) => $i->Ma_vv . '|' . $i->Ma_so);

        // 🔹 Nhập kho
        $nhapKho = DB::table('DataKetoan2025')
            ->select('So_dh', 'Ma_hh', DB::raw('SUM(Soluong) as total_nhap'))
            ->where('Ma_ct', '=', 'NV')
            ->groupBy('So_dh', 'Ma_hh')
            ->get()
            ->keyBy(fn($i) => $i->So_dh . '|' . $i->Ma_hh);

        // 🔹 Lấy ảnh duy nhất theo Ma_so
        $hinhAnh = DB::table('CodeHangHoa')
            ->select('Ma_so', DB::raw('MIN(Pngpath) as Pngpath'))
            ->groupBy('Ma_so')
            ->get()
            ->keyBy('Ma_so');

        // 🔹 Gán ảnh vào kết quả
        $data->transform(function ($item) use ($hinhAnh) {
            if ($item->hangHoa && $item->hangHoa->Ma_so) {
                $maSo = $item->hangHoa->Ma_so;
                $item->hangHoa->Pngpath_fixed = $hinhAnh[$maSo]->Pngpath ?? null;
            } else {
                $item->hangHoa->Pngpath_fixed = null;
            }
            return $item;
        });

        return response()->json([
            'data' => $data,
            'xuatkhotheomavvketoan' => $xuatkhotheomavvketoan,
            'nhapKho' => $nhapKho
        ]);
    }
}
