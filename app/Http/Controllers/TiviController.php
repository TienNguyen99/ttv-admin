<?php

namespace App\Http\Controllers;

use App\Models\DataKetoan2025;
use App\Models\DataKetoanData;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class TiviController extends Controller
{
    public function tiviIndex()
    {
        return view('client.tivi');
    }
    
    public function tiviSanxuat()
    {
        return view('client.tivisanxuat');
    }
    
    // API chi tiết lệnh sản xuất
    public function getSXDetailBySoCt(Request $request, $soCt)
    {
        try {
            // Lấy thông tin lệnh GO (Đơn hàng gốc)
            $orderInfo = DataKetoanData::with([
                'hangHoa:Ma_hh,Ten_hh,Dvt,Pngpath,Ma_so',
                'khachHang:Ma_kh,Ten_kh'
            ])
            ->where('Ma_ct', 'GO')
            ->where('So_ct', $soCt)
            ->first();

            if (!$orderInfo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy lệnh'
                ], 404);
            }
            // Lấy tất cả chi tiết sản xuất của lệnh này MA_CT = NX
            $nxDetails = DataKetoanData::with([
                'hangHoa:Ma_hh,Ten_hh,Dvt,Pngpath,Ma_so',
                'nhanVien:Ma_nv,Ten_nv',
            ])
            ->select('DataKetoanData.*')
            ->where('DataKetoanData.Ma_ct', 'NX')
            ->where('DataKetoanData.So_dh', $soCt)
            ->get();
            // Lấy tất cả chi tiết sản xuất của lệnh này MA_CT = CK nhưng table DataKetoan2025
            $ckDetails = DataKetoan2025::with([
                'hangHoa:Ma_hh,Ten_hh,Dvt,Pngpath,Ma_so',
                'nhanVien:Ma_nv,Ten_nv',
            ])
            ->select('DataKetoan2025.*')
            ->where('DataKetoan2025.Ma_ct', 'CK')
            ->where('DataKetoan2025.So_dh', $soCt)

            ->get();
            // Lấy tất cả chi tiết sản xuất của lệnh này MA_CT = SX
            $sxDetails = DataKetoanData::with([
                'hangHoa:Ma_hh,Ten_hh,Dvt,Pngpath,Ma_so',
                'nhanVien:Ma_nv,Ten_nv',
            ])
            ->select('DataKetoanData.*')
            ->where('DataKetoanData.Ma_ct', 'SX')
            ->where('DataKetoanData.So_dh', $soCt)
            ->get();

            // Tính tổng sản xuất theo công đoạn
            $summaryByCongDoan = $sxDetails->groupBy('Ma_ko')->map(function ($items) {
                return [
                    'Ma_ko' => $items->first()->Ma_ko,
                    'total_sx' => $items->sum('Soluong'),
                    'total_loi' => $items->sum('Tien_vnd'),
                    'total_soluongkhac' => $items->sum('Dgbanvnd'),
                    'count' => $items->count()
                ];
            })->values();

            // Lấy công đoạn có Ma_ko lớn nhất (công đoạn cuối cùng)
            $congDoanCuoi = $summaryByCongDoan->sortByDesc('Ma_ko')->first();
            
            // Tổng sản xuất của lệnh = Số lượng công đoạn cuối
            $totalSX = $congDoanCuoi['total_sx'] ?? 0;
            $totalLoi = $sxDetails->sum('Tien_vnd');
            
            // Tính % hoàn thành
            $soluongDon = DataKetoanData::where('Ma_ct', 'GO')
    ->where('So_ct', $soCt)
    ->sum('Soluong');
            $percentComplete = $soluongDon > 0 ? round(($totalSX / $soluongDon) * 100, 2) : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'orderInfo' => $orderInfo,
                    'sxDetails' => $sxDetails,
                    'congDoanCuoi' => $congDoanCuoi, // Thêm thông tin công đoạn cuối
                    'summary' => [
                        'so_ct' => $soCt,
                        'so_luong_don' => $soluongDon,
                        'total_sx' => $totalSX,
                        'total_loi' => $totalLoi,
                        
                        'con_thieu' => $soluongDon - $totalSX,
                        'percent_complete' => $percentComplete,
                        'by_cong_doan' => $summaryByCongDoan
                    ],
                    'nxDetails' => $nxDetails,
                    'ckDetails' => $ckDetails
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getSXData(Request $request)
    {
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
            'DataKetoanData.UserNgE',
            'DataKetoanData.Ma_kh',
            'DataKetoanData.Dgbanvnd',
            'DataKetoanData.Tien_vnd',
            'DataKetoanData.DgiaiV',
            DB::raw('go.So_dh as So_ct_go'),
            DB::raw('go.Soluong_go as Soluong_go')
        )
        ->leftJoin(DB::raw("
            (
                SELECT So_ct, So_dh, SUM(Soluong) AS Soluong_go
                FROM DataKetoanData
                WHERE Ma_ct = 'GO'
                GROUP BY So_ct, So_dh
            ) AS go
        "), 'go.So_ct', '=', 'DataKetoanData.So_dh')
        ->where('DataKetoanData.Ma_ct', '=', 'SX')
        ->orderBy('DataKetoanData.So_dh')
        ->get();

        // Tính tổng theo lệnh - CHỈ LẤY CÔNG ĐOẠN CUỐI
        $totalBySoct = DB::table('DataKetoanData')
            ->select(
                'So_dh',
                DB::raw('MAX(Ma_ko) as Ma_ko_cuoi')
            )
            ->where('Ma_ct', '=', 'SX')
            ->groupBy('So_dh')
            ->get()
            ->mapWithKeys(function ($item) {
                // Lấy tổng số lượng của công đoạn cuối cùng
                $total = DB::table('DataKetoanData')
                    ->where('Ma_ct', 'SX')
                    ->where('So_dh', $item->So_dh)
                    ->where('Ma_ko', $item->Ma_ko_cuoi)
                    ->sum('Soluong');
                
                return [$item->So_dh => $total];
            });

        return response()->json([
            'data' => $data,
            'totalBySoct' => $totalBySoct
        ]);
    }

    // API hiển thị Tivi
    public function getTiviData(Request $request)
    {
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

        $xuatkhotheomavvketoan = DB::table('DataKetoan2025 as dk')
            ->join('CodeHangHoa as hh','dk.Ma_hh','=','hh.Ma_hh')
            ->select('dk.Ma_vv','hh.Ma_so',DB::raw('SUM(dk.Soluong) as xuatkhotheomavv_ketoan'))
            ->where('dk.Ma_ct','XU')->groupBy('dk.Ma_vv','hh.Ma_so')
            ->get()->keyBy(fn($i)=>$i->Ma_vv.'|'.$i->Ma_so);

        $nhapKho = DB::table('DataKetoan2025')
            ->select('So_dh','Ma_hh',DB::raw('SUM(Soluong) as total_nhap'))
            ->where('Ma_ct','NV')->groupBy('So_dh','Ma_hh')
            ->get()->keyBy(fn($i)=>$i->So_dh.'|'.$i->Ma_hh);

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