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
    public function getTiviData()
    {
        $today = now();
        $upcoming = $today->copy()->addDays(7);

        $data = DataKetoanData::with(['khachHang', 'hangHoa'])
            ->where('Ma_ct', '=', 'GO')
            ->where('Loaisx', '!=', 'M')
            // ->whereBetween('Date', [$today, $upcoming])
            // ->where('Date', '<=', $upcoming)
            ->orderby('Date', 'desc')
            ->limit(50)
            ->get();
                    $xuatkhotheomavvketoan = DB::table('DataKetoan2025 as dk')
            ->join('CodeHangHoa as hh', 'dk.Ma_hh', '=', 'hh.Ma_hh')
            ->select('dk.Ma_vv', 'hh.Ma_so', DB::raw('SUM(dk.Soluong) as xuatkhotheomavv_ketoan'))
            ->where('dk.Ma_ct', '=', 'XU')
            ->groupBy('dk.Ma_vv', 'hh.Ma_so')
            ->get()
            ->keyBy(fn($i) => $i->Ma_vv . '|' . $i->Ma_so);
            $nhapKho = DB::table('DataKetoan2025')
            ->select('So_dh', 'Ma_hh', DB::raw('SUM(Soluong) as total_nhap'))
            ->where('Ma_ct', '=', 'NV')
            ->groupBy('So_dh', 'Ma_hh')
            ->get()
            ->keyBy(fn($i) => $i->So_dh . '|' . $i->Ma_hh);

        return response()->json([
            'data' => $data,
            'xuatkhotheomavvketoan' => $xuatkhotheomavvketoan,
            'nhapKho' => $nhapKho
        ]);
    }
}
