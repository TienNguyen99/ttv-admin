<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $data = DB::table('DataKetoanData')
        ->join('codekhachang', 'DataKetoanData.Ma_kh', '=', 'codekhachang.Ma_kh')
        ->join('codehanghoa', 'DataKetoanData.Ma_hh', '=', 'codehanghoa.Ma_hh')
        ->where('Ma_ct', '=', 'GO')
        ->get();
        
        $sumCongDoan1 = DB::table('DataKetoanData')
        ->select('So_dh', DB::raw('SUM(Soluong) as total_sx'))
        ->where('Ma_ct', '=', 'SX')
        ->where('Ma_ko', '=', '01')
        ->groupBy('So_dh')
        ->pluck('total_sx', 'So_dh');
        $sumCongDoan2 = DB::table('DataKetoanData')
        ->select('So_dh', DB::raw('SUM(Soluong) as total_sx'))
        ->where('Ma_ct', '=', 'SX')
        ->where('Ma_ko', '=', '02')
        ->groupBy('So_dh')
        ->pluck('total_sx', 'So_dh');
        $sumCongDoan3 = DB::table('DataKetoanData')
        ->select('So_dh', DB::raw('SUM(Soluong) as total_sx'))
        ->where('Ma_ct', '=', 'SX')
        ->where('Ma_ko', '=', '03')
        ->groupBy('So_dh')
        ->pluck('total_sx', 'So_dh');
        $sumCongDoan4 = DB::table('DataKetoanData')
        ->select('So_dh', DB::raw('SUM(Soluong) as total_sx'))
        ->where('Ma_ct', '=', 'SX')
        ->where('Ma_ko', '=', '04')
        ->groupBy('So_dh')
        ->pluck('total_sx', 'So_dh');
        //Số lượng nhập kho
        $sumNhapKho = DB::table('DataKetoan2025')
        ->select('So_dh', DB::raw('SUM(Soluong) as total_nv'))
        ->where('Ma_ct', '=', 'NV')
        ->groupBy('So_dh')
        ->pluck('total_nv', 'So_dh');
        //So lượng xuất kho
        $sumXuatKho = DB::table('DataKetoan2025')
        ->select('So_dh', DB::raw('SUM(Soluong) as total_xv'))
        ->where('Ma_ct', '=', 'XV')
        ->groupBy('So_dh')
        ->pluck('total_xv', 'So_dh');   
        $nxSoDhs = DB::table('DataKetoanData')
        ->where('Ma_ct', '=', 'NX')
        ->pluck('So_dh')
        ->toArray();

        $xvSoDhs = DB::table('DataKetoanData')
        ->where('Ma_ct', '=', 'XV')
        ->pluck('So_dh')
        ->toArray();
        // Kiểm tra nhập kho
        $checkNhapKho = DB::table('DataKetoan2025')
        ->where('Ma_ct', '=', 'NV')
        ->pluck('So_dh')
        ->toArray();
        
        // Kiểm tra xuất kho
        $checkXuatKho = DB::table('DataKetoan2025')
        ->where('Ma_ct', '=', 'XV')
        ->pluck('So_dh')
        ->toArray();
        return view('dashboard', [
            'data' => $data,
            'sumCongDoan1' => $sumCongDoan1,
            'sumCongDoan2' => $sumCongDoan2,
            'sumCongDoan3' => $sumCongDoan3,
            'sumCongDoan4' => $sumCongDoan4,
            'sumNhapKho' => $sumNhapKho,
            'sumXuatKho' => $sumXuatKho,
            'nxSoDhs' => $nxSoDhs,
            'xvSoDhs' => $xvSoDhs,
            'checkNhapKho' => $checkNhapKho,
            'checkXuatKho' => $checkXuatKho
        ]);
        
    }
}
