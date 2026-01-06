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
        return view('Client.tivi');
    }
    
    public function tiviSanxuat()
    {
        return view('Client.tivisanxuat');
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
            // Lấy tất cả chi tiết sản xuất của lệnh này MA_CT = NX (PHÂN TÍCH)
            $nxDetails = DataKetoanData::with([
                'hangHoa:Ma_hh,Ten_hh,Dvt,Pngpath,Ma_so',
                'nhanVien:Ma_nv,Ten_nv',
            ])
            ->select('DataKetoanData.*')
            ->where('DataKetoanData.Ma_ct', 'NX')
            ->where('DataKetoanData.So_dh', $soCt)
            ->get();
            // Lấy tất cả chi tiết sản xuất của lệnh này MA_CT = CK nhưng table DataKetoan2025 (PHIẾU CHUYỂN KHO NỘI BỘ)
            $ckDetails = DataKetoan2025::with([
                'hangHoa:Ma_hh,Ten_hh,Dvt,Pngpath,Ma_so',
                'nhanVien:Ma_nv,Ten_nv',
            ])
            ->select('DataKetoan2025.*')
            ->where('DataKetoan2025.Ma_ct', 'CK')
            ->where('DataKetoan2025.So_dh', $soCt)
            ->get();
            // LẤY TẤT CẢ CHI TIẾT CỦA LỆNH SẢN XUẤT MA_CT = NX TABLE DATAKETOAN2025 (PHIẾU NHẬP THÀNH PHẨM)
            $nxDetails2025 = DataKetoan2025::with([
                'hangHoa:Ma_hh,Ten_hh,Dvt,Pngpath,Ma_so',
                'nhanVien:Ma_nv,Ten_nv',
            ])
            ->select('DataKetoan2025.*')
            ->where('DataKetoan2025.Ma_ct', 'NX')
            ->where('DataKetoan2025.So_dh', $soCt)
            ->get();
            // LẤY TẤT CẢ CHI TIẾT CỦA LỆNH SẢN XUẤT MA_CT = XU TABLE DATAKETOAN2025 ( PHIẾU XUẤT BÁN HÀNG)
            $xuDetails2025 = DataKetoan2025::with([
                'hangHoa:Ma_hh,Ten_hh,Dvt,Pngpath,Ma_so',
                'nhanVien:Ma_nv,Ten_nv',
            ])
            ->select('DataKetoan2025.*')
            ->where('DataKetoan2025.Ma_ct', 'XU')
            ->where('DataKetoan2025.So_dh', $soCt)
            ->get();
            // Lấy tất cả chi tiết sản xuất của lệnh này MA_CT = SX ( PHIẾU SẢN XUẤT )
            $sxDetails = DataKetoanData::with([
                'hangHoa:Ma_hh,Ten_hh,Dvt,Pngpath,Ma_so',
                'nhanVien:Ma_nv,Ten_nv',
            ])
            ->select('DataKetoanData.*')
            ->where('DataKetoanData.Ma_ct', 'SX')
            ->where('DataKetoanData.So_dh', $soCt)
            ->orderBy('DataKetoanData.Ma_ko')
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
            // Tính tổng xuất kho (XU) - CÔNG ĐOẠN CUỐI CÙNG
$totalXuatKho = $xuDetails2025->sum('Soluong');
            // Lấy công đoạn có Ma_ko lớn nhất (công đoạn cuối cùng)
            $congDoanCuoi = $summaryByCongDoan->sortByDesc('Ma_ko')->first();
            
            // Tổng sản xuất của lệnh = Số lượng công đoạn cuối
            $totalSX = is_array($congDoanCuoi) ? ($congDoanCuoi['total_sx'] ?? 0) : 0;
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
                        'so_dh' => $orderInfo->So_dh,
                        'so_luong_don' => $soluongDon,
                        'total_sx' => $totalSX,
                        'total_loi' => $totalLoi,
                        'con_thieu' => $soluongDon - $totalSX,
                        'percent_complete' => $percentComplete,
                        'by_cong_doan' => $summaryByCongDoan,
                        'total_xuat_kho' => $totalXuatKho, // THÊM DÒNG NÀY
                        'con_thieu_xuat_kho' => $soluongDon - $totalXuatKho  // THÊM DÒNG NÀY
                    ],
                    'nxDetails' => $nxDetails,
                    'ckDetails' => $ckDetails,
                    'nxDetails2025' => $nxDetails2025,
                    'xuDetails2025' => $xuDetails2025
                    
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
        'hangHoa:Ma_hh,Ten_hh,Dvt,Pngpath,Ma_so,Nhom1',
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

    // Tính tổng theo lệnh - CHỈ LẤY CÔNG ĐOẠN CUỐI (OPTIMIZED: single query)
    $totalBySoct = DB::table('DataKetoanData as dkd1')
        ->select(
            'dkd1.So_dh',
            DB::raw('SUM(dkd1.Soluong) as total_sx')
        )
        ->where('dkd1.Ma_ct', '=', 'SX')
        ->joinSub(
            DB::table('DataKetoanData')
                ->select('So_dh', DB::raw('MAX(Ma_ko) as Ma_ko_cuoi'))
                ->where('Ma_ct', 'SX')
                ->groupBy('So_dh'),
            'dkd2',
            function ($join) {
                $join->on('dkd1.So_dh', '=', 'dkd2.So_dh')
                     ->on('dkd1.Ma_ko', '=', 'dkd2.Ma_ko_cuoi');
            }
        )
        ->groupBy('dkd1.So_dh')
        ->get()
        ->mapWithKeys(function ($item) {
            return [$item->So_dh => $item->total_sx];
        });

    // ===== KIỂM TRA TRẠNG THÁI =====
    
    $allSoDh = $data->pluck('So_dh')->unique();
    
    // Kiểm tra có định mức (NX trong DataKetoanData)
    $dinhMucStatus = DB::table('DataKetoanData')
        ->select('So_dh')
        ->where('Ma_ct', 'NX')
        ->whereIn('So_dh', $allSoDh)
        ->groupBy('So_dh')
        ->pluck('So_dh')
        ->flip()
        ->toArray();
    
    // Kiểm tra đã xuất vật tư (CK trong DataKetoan2025)
    $xuatVatTuStatus = DB::table('DataKetoan2025')
        ->select('So_dh')
        ->where('Ma_ct', 'CK')
        ->whereIn('So_dh', $allSoDh)
        ->groupBy('So_dh')
        ->pluck('So_dh')
        ->flip()
        ->toArray();
    
    // Kiểm tra đã nhập kho (NX trong DataKetoan2025)
    $nhapKhoStatus = DB::table('DataKetoan2025')
        ->select('So_dh')
        ->where('Ma_ct', 'NX')
        ->whereIn('So_dh', $allSoDh)
        ->groupBy('So_dh')
        ->pluck('So_dh')
        ->flip()
        ->toArray();
    
    // Kiểm tra đã xuất kho (XU trong DataKetoan2025)
    $xuatKhoStatus = DB::table('DataKetoan2025')
        ->select('So_dh')
        ->where('Ma_ct', 'XU')
        ->whereIn('So_dh', $allSoDh)
        ->groupBy('So_dh')
        ->pluck('So_dh')
        ->flip()
        ->toArray();

    // ===== KIỂM TRA XUẤT DƯ VẬT TƯ =====
    $xuatDuVatTu = [];
    foreach ($allSoDh as $soDh) {
        // Lấy số lượng đơn
        $soLuongDon = DB::table('DataKetoanData')
            ->where('Ma_ct', 'GO')
            ->where('So_ct', $soDh)
            ->sum('Soluong');

        if ($soLuongDon > 0) {
            // Lấy định mức
            $dinhMuc = DB::table('DataKetoanData')
                ->select('Ma_hh', DB::raw('SUM(Soluong) as total_dinh_muc'))
                ->where('Ma_ct', 'NX')
                ->where('So_dh', $soDh)
                ->groupBy('Ma_hh')
                ->get()
                ->keyBy('Ma_hh');

            // Lấy đã xuất
            $daXuat = DB::table('DataKetoan2025')
                ->select('Ma_hh', DB::raw('SUM(Soluong) as total_xuat'))
                ->where('Ma_ct', 'CK')
                ->where('So_dh', $soDh)
                ->groupBy('Ma_hh')
                ->get()
                ->keyBy('Ma_hh');

            // Kiểm tra có xuất dư không
            $coDu = false;
            foreach ($daXuat as $maHh => $xuat) {
                $dinhMucDeXuat = ($dinhMuc[$maHh]->total_dinh_muc ?? 0) * $soLuongDon;
                $daXuatTotal = $xuat->total_xuat ?? 0;
                
                if ($daXuatTotal > $dinhMucDeXuat) {
                    $coDu = true;
                    break;
                }
            }
            
            if ($coDu) {
                $xuatDuVatTu[] = $soDh;
            }
        }
    }
    
    // Tạo map trạng thái cho từng lệnh
    $statusMap = [];
    foreach ($allSoDh as $soDh) {
        $statusMap[$soDh] = [
            'co_dinh_muc' => isset($dinhMucStatus[$soDh]),
            'da_xuat_vat_tu' => isset($xuatVatTuStatus[$soDh]),
            'da_nhap_kho' => isset($nhapKhoStatus[$soDh]),
            'da_xuat_kho' => isset($xuatKhoStatus[$soDh]),
            'xuat_du_vat_tu' => in_array($soDh, $xuatDuVatTu)
        ];
    }

    return response()->json([
        'data' => $data,
        'totalBySoct' => $totalBySoct,
        'statusMap' => $statusMap
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