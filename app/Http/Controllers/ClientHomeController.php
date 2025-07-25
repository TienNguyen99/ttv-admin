<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DataKetoanData;
use Illuminate\Support\Facades\DB;

class ClientHomeController extends Controller
{
    public function index()
    {
        return view('client.home');
    }

    public function getData()
    {
        $data = DataKetoanData::with(['khachHang', 'hangHoa'])
            ->where('Ma_ct', '=', 'GO')
            ->get();

        $sumSoLuong = DB::table('DataKetoanData')
            ->select('So_ct', DB::raw('SUM(Soluong) as total'))
            ->where('Ma_ct', '=', 'GO')
            ->groupBy('So_ct')
            ->pluck('total', 'So_ct');

        $cd = fn($cd) => DB::table('DataKetoanData')
            ->select('So_dh', 'Ma_hh', DB::raw('SUM(Soluong) as total'))
            ->where('Ma_ct', '=', 'SX')
            ->where('Ma_ko', '=', $cd)
            ->groupBy('So_dh', 'Ma_hh')
            ->get()
            ->keyBy(fn($i) => $i->So_dh . '|' . $i->Ma_hh);

        $cd1 = $cd('01');
        $cd2 = $cd('02');
        $cd3 = $cd('03');
        $cd4 = $cd('04');

        $nx = DB::table('DataKetoanData')
            ->where('Ma_ct', '=', 'NX')
            ->pluck('So_dh')
            ->toArray();

        $xv = DB::table('DataKetoan2025')
            ->where('Ma_ct', '=', 'XV')
            ->pluck('So_dh')
            ->toArray();

        $nhapKho = DB::table('DataKetoan2025')
            ->select('So_dh', 'Ma_hh', DB::raw('SUM(Soluong) as total'))
            ->where('Ma_ct', '=', 'NV')
            ->groupBy('So_dh', 'Ma_hh')
            ->get()
            ->keyBy(fn($i) => $i->So_dh . '|' . $i->Ma_hh);

        $xuatKho = DB::table('TSoft_NhanTG_kt_test.dbo.DataKetoan2024')
            ->select('So_dh', 'Ma_hh', DB::raw('SUM(Soluong) as total'))
            ->where('Ma_ct', '=', 'XU')
            ->groupBy('So_dh', 'Ma_hh')
            ->get()
            ->keyBy(fn($i) => $i->So_dh . '|' . $i->Ma_hh);

        return response()->json([
            'data' => $data,
            'sumSoLuong' => $sumSoLuong,
            'cd1' => $cd1,
            'cd2' => $cd2,
            'cd3' => $cd3,
            'cd4' => $cd4,
            'nx' => $nx,
            'xv' => $xv,
            'nhapKho' => $nhapKho,
            'xuatKho' => $xuatKho
        ]);
    }
}
