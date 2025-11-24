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
        /** 🔹 Trang TV hiển thị sản xuất */
    public function tiviSanxuat()
    {
        return view('client.tivisanxuat');
    }
    // API hiển thị dữ liệu Ma_ct = 'SX' trong ngày
public function getSXData(Request $request)
{
    

    // Lấy dữ liệu SX, đồng thời join để lấy So_ct từ chứng từ GO cùng So_dh
    $data = DataKetoanData::with([
        'hangHoa:Ma_hh,Ten_hh,Dvt,Pngpath,Ma_so',
        'nhanVien:Ma_nv,Ten_nv',
        'khachHang:Ma_kh,Ten_kh'
    ])
        ->select(
            'DataKetoanData.So_dh',
            'DataKetoanData.Ma_hh',
            'DataKetoanData.Ma_ko',
            'DataKetoanData.Ma_nv',
            'DataKetoanData.Soluong',
            'DataKetoanData.Ngay_ct',
            'DataKetoanData.Ma_kh',
            'DataKetoanData.Dgbanvnd',
            'DataKetoanData.Tien_vnd',
            'DataKetoanData.DgiaiV',
            DB::raw('go.So_dh as So_ct_go'),
            DB::raw('go.Soluong as Soluong_go')
        )
        ->leftJoin('DataKetoanData as go', function ($join) {
            $join->on('go.So_ct', '=', 'DataKetoanData.So_dh')
                 ->where('go.Ma_ct', '=', 'GO');
        })
        ->where('DataKetoanData.Ma_ct', '=', 'SX')
        // ->whereDate('DataKetoanData.Ngay_ct', '=', $today)
        ->orderBy('DataKetoanData.So_dh')
        ->get();

//Tính tổng đơn đã sản xuất
$totalBySoct = DB::table('DataKetoanData')
    ->select('So_dh', DB::raw('SUM(Soluong) as total_sx'))
    ->where('Ma_ct', '=', 'SX')
    ->groupBy('So_dh')
    ->pluck('total_sx', 'So_dh');


    // ✅ Trả về cả dữ liệu và tổng theo lệnh
    return response()->json([
        'data' => $data,
        'totalBySoct' => $totalBySoct
    ]);
}






    // API hiển thị Tivi
public function getTiviData(Request $request){
    $range = $request->get('range','7');
    $today = now()->startOfDay();
    $query = DataKetoanData::with(['khachHang:Ma_kh,Ten_kh','hangHoa:Ma_hh,Ten_hh,Dvt,Pngpath,Ma_so'])
        ->select('So_dh','Ma_hh','Soseri','Soluong','Ma_kh','Date','Ngay_ct');

    if($range==='sxmonth'){
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();
        $query->where('Ma_ct','GO')->where('Loaisx','!=','M')->whereBetween('Date',[$start,$end]);
    } else {
        $query->where('Ma_ct','GO')->where('Loaisx','!=','M');
        if($range==='overdue14'){
            $twoWeeksAgo = $today->copy()->subDays(14);
            $query->whereBetween('Date',[$twoWeeksAgo,$today->copy()->subDay()]);
        } else {
            $upcoming = $today->copy()->addDays((int)$range);
            $query->whereBetween('Date',[$today,$upcoming]);
        }
    }

    $data = $query->orderBy('Date','asc')->get();

    // Xuất kho
    $xuatkhotheomavvketoan = DB::table('DataKetoan2025 as dk')
        ->join('CodeHangHoa as hh','dk.Ma_hh','=','hh.Ma_hh')
        ->select('dk.Ma_vv','hh.Ma_so',DB::raw('SUM(dk.Soluong) as xuatkhotheomavv_ketoan'))
        ->where('dk.Ma_ct','XU')->groupBy('dk.Ma_vv','hh.Ma_so')
        ->get()->keyBy(fn($i)=>$i->Ma_vv.'|'.$i->Ma_so);

    // Nhập kho
    $nhapKho = DB::table('DataKetoan2025')
        ->select('So_dh','Ma_hh',DB::raw('SUM(Soluong) as total_nhap'))
        ->where('Ma_ct','NV')->groupBy('So_dh','Ma_hh')
        ->get()->keyBy(fn($i)=>$i->So_dh.'|'.$i->Ma_hh);

    // Nhập TP kế toán
    $sub = DB::table('DataKetoan2025')->select('Ma_vv','Ma_sp','Noluong','SttRecN')
        ->where('Ma_ct','NX')->where('Ma3ko','KTPHAM')->distinct();

    $nhaptpketoan = DB::query()->fromSub($sub,'sub')
        ->select('Ma_vv','Ma_sp',DB::raw('SUM(Noluong) as total_nhaptpketoan'))
        ->groupBy('Ma_vv','Ma_sp')
        ->get()->keyBy(fn($i)=>$i->Ma_vv.'|'.$i->Ma_sp);

    return response()->json([
        'data'=>$data,
        'xuatkhotheomavvketoan'=>$xuatkhotheomavvketoan,
        'nhapKho'=>$nhapKho,
        'nhaptpketoan'=>$nhaptpketoan
    ]);
}

}
