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

        //sum số lượng tổng theo So_ct  
        $sumSoLuong = DB::table('DataKetoanData')
            ->select('So_ct', DB::raw('SUM(Soluong) as total'))
            ->where('Ma_ct', '=', 'GO')
            ->groupBy('So_ct')
            ->pluck('total', 'So_ct');
        $sumCongDoan1 = DB::table('DataKetoanData')
            ->select('So_dh', 'Ma_hh', DB::raw('SUM(Soluong) as total_sx1'))
            ->where('Ma_ct', '=', 'SX')
            ->where('Ma_ko', '=', '01')
            ->groupBy('So_dh', 'Ma_hh')
            ->get()
            ->keyBy(function ($item) {
                return $item->So_dh . '|' . $item->Ma_hh;
            });

        $sumCongDoan2 = DB::table('DataKetoanData')
            ->select('So_dh', 'Ma_hh', DB::raw('SUM(Soluong) as total_sx2'))
            ->where('Ma_ct', '=', 'SX')
            ->where('Ma_ko', '=', '02')
            ->groupBy('So_dh', 'Ma_hh')
            ->get()
            ->keyBy(function ($item) {
                return $item->So_dh . '|' . $item->Ma_hh;
            });

        $sumCongDoan3 = DB::table('DataKetoanData')
            ->select('So_dh', 'Ma_hh', DB::raw('SUM(Soluong) as total_sx3'))
            ->where('Ma_ct', '=', 'SX')
            ->where('Ma_ko', '=', '03')
            ->groupBy('So_dh', 'Ma_hh')
            ->get()
            ->keyBy(function ($item) {
                return $item->So_dh . '|' . $item->Ma_hh;
            });

        $sumCongDoan4 = DB::table('DataKetoanData')
            ->select('So_dh', 'Ma_hh', DB::raw('SUM(Soluong) as total_sx4'))
            ->where('Ma_ct', '=', 'SX')
            ->where('Ma_ko', '=', '04')
            ->groupBy('So_dh', 'Ma_hh')
            ->get()
            ->keyBy(function ($item) {
                return $item->So_dh . '|' . $item->Ma_hh;
            });
        // Kiễm tra đã phân tích chưa
        $nxSoDhs = DB::table('DataKetoanData')
            ->where('Ma_ct', '=', 'NX')
            ->pluck('So_dh')
            ->toArray();
        // Kiểm tra đã chuẩn bị chưa
        $xvSoDhs = DB::table('DataKetoan2025')
            ->where('Ma_ct', '=', 'XV')
            ->pluck('So_dh')
            ->toArray();
        // Kiểm tra nhập kho
        $checkNhapKho = DB::table('TSoft_NhanTG_kt_test.dbo.DataKetoan2024')
            ->select('So_dh', 'Ma_hh', DB::raw('SUM(Soluong) as total_nhap'))
            ->where('Ma_ct', '=', 'NV')
            ->groupBy('So_dh', 'Ma_hh')
            ->get()
            ->keyBy(function ($item) {
                return $item->So_dh . '|' . $item->Ma_hh;
            });

        // Kiểm tra tổng số lượng xuất kho của mã Ma_hh theo So_dh
        $checkXuatKho = DB::table('TSoft_NhanTG_kt_test.dbo.DataKetoan2024')
            ->select('So_dh', 'Ma_hh', DB::raw('SUM(Soluong) as total_xuat'))
            ->where('Ma_ct', '=', 'XU')
            ->groupBy('So_dh', 'Ma_hh')
            ->get()
            ->keyBy(function ($item) {
                return $item->So_dh . '|' . $item->Ma_hh;
            });

        return view('dashboard', [
            'data' => $data,
            'sumSoLuong' => $sumSoLuong,
            'sumCongDoan1' => $sumCongDoan1,
            'sumCongDoan2' => $sumCongDoan2,
            'sumCongDoan3' => $sumCongDoan3,
            'sumCongDoan4' => $sumCongDoan4,
            'nxSoDhs' => $nxSoDhs,
            'xvSoDhs' => $xvSoDhs,
            'checkNhapKho' => $checkNhapKho,
            'checkXuatKho' => $checkXuatKho
        ]);
    }
    public function showDetail($so_ct)
    {
        $so_ct = str_replace('-', '/', $so_ct);
        //Lệnh chi tiết
        $lenh = DB::table('DataKetoanData')
            ->join('codehanghoa', 'DataKetoanData.Ma_hh', '=', 'codehanghoa.Ma_hh')
            ->where('Ma_ct', '=', 'NX')
            ->where('So_dh', '=', $so_ct)
            ->orderBy('Ma_ko')
            ->get();

        //Tiến độ sản xuất
        $tiendoSanXuat = DB::table('DataKetoanData')
            ->join('codehanghoa', 'DataKetoanData.Ma_hh', '=', 'codehanghoa.Ma_hh')
            ->where('Ma_ct', '=', 'SX')
            ->where('So_dh', '=', $so_ct)
            ->orderBy('Ma_ko')
            ->get();
        return view('detail', [
            'lenh' => $lenh,
            'tiendoSanXuat' => $tiendoSanXuat

        ]);
    }
}
