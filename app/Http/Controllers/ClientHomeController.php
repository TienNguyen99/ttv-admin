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
        //Nhập kho data
        // Lấy tổng nhập kho theo Ma_hh và So_dh
        $nhapKho = DB::table('DataKetoan2025')
            ->select('So_dh', 'Ma_hh', DB::raw('SUM(Soluong) as total_nhap'))
            ->where('Ma_ct', '=', 'NV')
            ->groupBy('So_dh', 'Ma_hh')
            ->get()
            ->keyBy(fn($i) => $i->So_dh . '|' . $i->Ma_hh);
        //Hiển thị So  tạo nên tổng nhập kho Theo ma_hh và So_dh


        // $nhaptpketoan = DB::table('TSoft_NhanTG_kt_new.dbo.DataKetoan2025')
        //     ->selectdistinct('Ma_vv', 'Ma_sp', DB::raw('SUM(Noluong) as total_nhaptpketoan'))
        //     ->where('Ma_ct', '=', 'NX')
        //     ->groupBy('Ma_vv', 'Ma_sp')
        //     ->get()
        //     ->keyBy(fn($i) => $i->Ma_vv . '|' . $i->Ma_sp);
        //Phan ke toan
        //Nhập thành phẩm kế toán 
        $sub = DB::table('TSoft_NhanTG_kt_new.dbo.DataKetoan2025')
            ->select('Ma_vv', 'Ma_sp', 'Noluong', 'SttRecN')
            ->where('Ma_ct', '=', 'NX')
            ->whereBetween('Ngay_ct', ['2025-01-01', '2025-12-31'])
            ->distinct();
        $nhaptpketoan = DB::table('TSoft_NhanTG_kt_new.dbo.DataKetoan2025')
            ->mergeBindings($sub)
            ->select('Ma_vv', 'Ma_sp', DB::raw('SUM(Noluong) as total_nhaptpketoan'))
            ->groupBy('Ma_vv', 'Ma_sp')
            ->get()
            ->keyBy(fn($i) => $i->Ma_vv . '|' . $i->Ma_sp);
        // Tổng tồn kế toán theo Ma_sp
        $tongnhapkhoketoan = DB::table(DB::raw("({$sub->toSql()}) as sub"))
            ->mergeBindings($sub)
            ->select('Ma_sp', DB::raw('SUM(Noluong) as totalnhapkho_ketoan'))
            ->groupBy('Ma_sp')
            ->get()
            ->keyBy('Ma_sp');
        $tongxuatkhoketoan = DB::table('TSoft_NhanTG_kt_new.dbo.DataKetoan2025')
            ->select('Ma_hh', DB::raw('SUM(Soluong) as totalxuatkho_ketoan'))
            ->groupBy('Ma_hh')
            ->get()
            ->keyBy('Ma_hh');




        // Get Ma_sp from DataKetoan2025 Ketoan
        // $datamahhketoan = DB::table('TSoft_NhanTG_kt_new.dbo.DataKetoan2025')
        //     ->select('Ma_vv', 'Ma_sp')
        //     ->where('Ma_ct', '=', 'NX')
        //     ->distinct()
        //     ->get()
        //     ->keyBy('Ma_vv');
        // Lấy mã HH kế toán
        $rawData = DB::table('TSoft_NhanTG_kt_new.dbo.DataKetoan2025')
            ->select('Ma_vv', 'Ma_sp')
            ->where('Ma_ct', '=', 'NX')
            ->distinct()
            ->get();

        // Gộp theo Ma_vv => [Ma_sp1, Ma_sp2, ...]
        $datamahhketoan = [];

        foreach ($rawData as $item) {
            $datamahhketoan[$item->Ma_vv][] = $item->Ma_sp;
        }

        // XUAT KHO DATA KETOAN
        $xuatKho = DB::table('TSoft_NhanTG_kt_test.dbo.DataKetoan2024')
            ->select('So_dh', 'Ma_hh', DB::raw('SUM(Soluong) as total_xuat'))
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
            'nhaptpketoan' => $nhaptpketoan,
            'datamahhketoan' => $datamahhketoan,
            'tongnhapkhoketoan' => $tongnhapkhoketoan,
            'tongxuatkhoketoan' => $tongxuatkhoketoan,
            'xuatKho' => $xuatKho
        ]);
    }
}
