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
    $today = now()->startOfDay();

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
            
            DB::raw('go.So_dh as So_ct_go'),
            DB::raw('go.Soluong as Soluong_go')
        )
        ->leftJoin('DataKetoanData as go', function ($join) {
            $join->on('go.So_ct', '=', 'DataKetoanData.So_dh')
                 ->where('go.Ma_ct', '=', 'GO');
        })
        ->where('DataKetoanData.Ma_ct', '=', 'SX')
        ->whereDate('DataKetoanData.Ngay_ct', '=', $today)
        ->orderBy('DataKetoanData.So_dh')
        ->get();
    
    return response()->json($data);
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
        // $xuatkhotheomavvketoan = DB::table('DataKetoan2025 as dk')
        //     ->join('CodeHangHoa as hh', 'dk.Ma_hh', '=', 'hh.Ma_hh')
        //     ->select('dk.Ma_vv', 'hh.Ma_so', DB::raw('SUM(dk.Soluong) as xuatkhotheomavv_ketoan'))
        //     ->where('dk.Ma_ct', '=', 'XU')
        //     ->groupBy('dk.Ma_vv', 'hh.Ma_so')
        //     ->get()
        //     ->keyBy(fn($i) => $i->Ma_vv . '|' . $i->Ma_so);
        //Dùng cái này khi lấy database ketoan
        $xuatkhotheomavvketoan = DB::table('TSoft_NhanTG_kt_new.dbo.DataKetoan2025')
        ->select('Ma_vv', 'Ma_hh', DB::raw('SUM(Soluong) as xuatkhotheomavv_ketoan'))
            ->where('Ma_ct', '=', 'XU')
            ->groupBy('Ma_vv', 'Ma_hh')
            ->get()
            ->keyBy(fn($i) => $i->Ma_vv . '|' . $i->Ma_hh);

        // 🔹 Nhập kho
        $nhapKho = DB::table('DataKetoan2025')
            ->select('So_dh', 'Ma_hh', DB::raw('SUM(Soluong) as total_nhap'))
            ->where('Ma_ct', '=', 'NV')
            ->groupBy('So_dh', 'Ma_hh')
            ->get()
            ->keyBy(fn($i) => $i->So_dh . '|' . $i->Ma_hh);
        //Dùng cái này khi lấy database ketoan
        //Nhập thành phẩm kế toán 
        $sub = DB::table('TSoft_NhanTG_kt_new.dbo.DataKetoan2025')
            ->select('Ma_vv', 'Ma_sp', 'Noluong', 'SttRecN')
            ->where('Ma_ct', '=', 'NX')
            ->where('Ma3ko', '=', 'KTPHAM') // kho thành phẩm
            ->distinct();

        $nhaptpketoan = DB::query()
            ->fromSub($sub, 'sub')
            ->select('Ma_vv', 'Ma_sp', DB::raw('SUM(Noluong) as total_nhaptpketoan'))
            ->groupBy('Ma_vv', 'Ma_sp')
            ->get()
            ->keyBy(fn($i) => $i->Ma_vv . '|' . $i->Ma_sp);
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
            'nhapKho' => $nhapKho,
            'nhaptpketoan' => $nhaptpketoan
        ]);
    }
}
