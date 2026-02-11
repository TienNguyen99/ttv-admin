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

    // View xem dữ liệu SX và tính trung bình Dgbanvnd
    public function viewSXData()
    {
        return view('Client.view-sx-data');
    }

    // View xem toàn bộ dữ liệu SX
    public function viewAllSXData()
    {
        return view('Client.view-all-sx-data');
    }

    // API lấy dữ liệu SX SIV và UNIQLO ANH TÚ
    public function getSXDataWithAverage(Request $request)
    {
        try {
            $maHh = $request->get('ma_hh', '');
            $soDhGo = $request->get('so_dh_go', '');

            $query = DataKetoanData::with([
                'hangHoa:Ma_hh,Ten_hh,Dvt,Pngpath,Ma_so',
                'nhanVien:Ma_nv,Ten_nv',
            ])
            ->select('DataKetoanData.*')
            ->where('DataKetoanData.Ma_ct', 'SX')
            ->where('DataKetoanData.Ngay_ct', '>=', '2026-01-01')
            ->whereIn('DataKetoanData.Ma_kh', ['KHNN000053', 'KHTN000015'])
            ->whereIn('DataKetoanData.Ma_ko', ['01', '02','05'])
            
            ->whereHas('hangHoa', function ($query) {
                $query->where('Nhom1', 'like', '%THUNBAN%');
            });
            // ->where('DataKetoanData.Ma_ko', '01');

            // Filter theo Ma_hh nếu có
            if (!empty($maHh)) {
                $query->where('DataKetoanData.Ma_hh', 'like', '%' . $maHh . '%');
            }

            // Filter theo So_dh_go nếu có (join với bảng GO)
            if (!empty($soDhGo)) {
                $query->whereHas('dataKetoan', function ($subQuery) use ($soDhGo) {
                    // Hoặc nếu không có relation, dùng direct query:
                })->whereExists(function ($subQuery) use ($soDhGo) {
                    $subQuery->selectRaw('1')
                        ->from('DataKetoanData as go')
                        ->whereRaw('go.So_ct = DataKetoanData.So_dh')
                        ->where('go.Ma_ct', 'GO')
                        ->where('go.So_dh', 'like', '%' . $soDhGo . '%');
                });
            }

            $data = $query->orderBy('DataKetoanData.Ma_hh')
                ->orderBy('DataKetoanData.So_dh')
                ->get()
                ->sortBy(function ($item) {
                    // Sắp xếp theo ngày từ DgiaiV (dd/mm/yyyy)
                    // Nếu không hợp lệ, xếp xuống cuối cùng
                    if (empty($item->DgiaiV)) {
                        return '9999-12-31';
                    }
                    $pattern = '/^\d{1,2}\/\d{1,2}\/\d{4}$/';
                    if (!preg_match($pattern, $item->DgiaiV)) {
                        return '9999-12-31';
                    }
                    list($day, $month, $year) = explode('/', $item->DgiaiV);
                    return "{$year}-{$month}-{$day}";
                })
                ->values();


            // Lấy So_dh từ bảng GO (Ma_ct = 'GO', So_ct = So_dh của SX)
            $soDhList = $data->pluck('So_dh')->unique();
            $goData = DataKetoanData::select('So_ct', 'So_dh')
                ->where('Ma_ct', 'GO')
                ->whereIn('So_ct', $soDhList)
                ->get()
                ->keyBy('So_ct');

            // Tính trung bình Dgbanvnd theo Ma_hh (chia cho 1000 để chuyển từ gram sang kg)
            $averageByMaHh = $data->groupBy('Ma_hh')->map(function ($items) {
                $hangHoa = $items->first()->hangHoa;
                return [
                    'ma_hh' => $items->first()->Ma_hh,
                    'ten_hh' => ($hangHoa && isset($hangHoa->Ten_hh)) ? $hangHoa->Ten_hh : '',
                    'dvt' => ($hangHoa && isset($hangHoa->Dvt)) ? $hangHoa->Dvt : '',
                    'count' => $items->count(),
                    'total_dgbanvnd' => round($items->sum('Dgbanvnd') / 1000, 2),
                    'average_dgbanvnd' => round($items->avg('Dgbanvnd') / 1000, 2),
                    'min_dgbanvnd' => round($items->min('Dgbanvnd') / 1000, 2),
                    'max_dgbanvnd' => round($items->max('Dgbanvnd') / 1000, 2),
                ];
            })->values();

            // Format data để đảm bảo relationships được include
            $formattedData = $data->map(function ($item) use ($goData) {
                $hangHoa = $item->hangHoa;
                $nhanVien = $item->nhanVien;
                $soDhGo = isset($goData[$item->So_dh]) ? $goData[$item->So_dh]->So_dh : null;
                
                return [
                    'Ma_hh' => $item->Ma_hh,
                    'So_dh' => $item->So_dh,
                    'So_dh_go' => $soDhGo,
                    'Ma_nv' => $item->Ma_nv,
                    'Ma_ko' => $item->Ma_ko ?? null,
                    'Ma3ko' => $item->Ma3ko ?? null,
                    'Dgbanvnd' => $item->Dgbanvnd,
                    'Ngay_ct' => $item->Ngay_ct,
                    'DgiaiV' => $item->DgiaiV,
                    'hangHoa' => [
                        'Ma_hh' => ($hangHoa && isset($hangHoa->Ma_hh)) ? $hangHoa->Ma_hh : null,
                        'Ten_hh' => ($hangHoa && isset($hangHoa->Ten_hh)) ? $hangHoa->Ten_hh : null,
                    ],
                    'nhanVien' => [
                        'Ma_nv' => ($nhanVien && isset($nhanVien->Ma_nv)) ? $nhanVien->Ma_nv : null,
                        'Ten_nv' => ($nhanVien && isset($nhanVien->Ten_nv)) ? $nhanVien->Ten_nv : null,
                    ],
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedData,
                'averageByMaHh' => $averageByMaHh,
                'total_records' => $data->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
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
            
            // Lấy tất cả chi tiết sản xuất của lệnh này MA_CT = NX (PHÂN TÍCH - ĐỊNH MỨC)
            $nxDetails = DataKetoanData::with([
                'hangHoa:Ma_hh,Ten_hh,Dvt,Pngpath,Ma_so',
                'nhanVien:Ma_nv,Ten_nv',
            ])
            ->select('DataKetoanData.*')
            ->where('DataKetoanData.Ma_ct', 'NX')
            ->where('DataKetoanData.So_dh', $soCt)
            ->get();
           
            // Lấy tất cả chi tiết sản xuất của lệnh này MA_CT = CK (PHIẾU CHUYỂN KHO NỘI BỘ - ĐÃ XUẤT VẬT TƯ)
            $ckDetails = DataKetoan2025::with([
                'hangHoa:Ma_hh,Ten_hh,Dvt,Pngpath,Ma_so',
                'nhanVien:Ma_nv,Ten_nv',
            ])
            ->select('DataKetoan2026.*')
            ->where('DataKetoan2026.Ma_ct', 'CK')
            ->where('DataKetoan2026.So_dh', $soCt)
            ->get();
            
            // ===== FIX: LẤY "ĐÃ SỬ DỤNG VẬT TƯ" =====
            // Query từ DataKetoan2025 với Ma_ct = 'NX' (Phiếu xuất sử dụng nội bộ)
            $daSuDungVatTu = DataKetoan2025::select(
                    'Ma_hh',
                    DB::raw('SUM(Soluong) as total_su_dung')
                )
                ->where('Ma_ct', 'NX')
                ->where('So_dh', $soCt)
                ->groupBy('Ma_hh')
                ->get();
            
            // LẤY "ĐÃ NHẬP KHO THÀNH PHẨM" (từ DB Kế Toán)
            $nhapTPKeToan = DB::table('TSoft_NhanTG_kt_new.dbo.DataKetoan2026')
                ->select('Ma_vv', 'Ma_sp', DB::raw('SUM(DISTINCT Noluong) as Noluong'))
                ->where('Ma_ct', 'NX')
                ->where('Ma_vv', $orderInfo->So_dh)
                ->groupBy('Ma_vv', 'Ma_sp')
                ->get();

            // LẤY "ĐÃ XUẤT KHO BÁN HÀNG"
            $xuDetails2025 = DB::table('TSoft_NhanTG_kt_new.dbo.DataKetoan2026 as dk')
                ->join('CodeHangHoa as hh', 'dk.Ma_hh', '=', 'hh.Ma_hh')
                ->select('dk.Ma_vv', 'hh.Ma_so', DB::raw('SUM(dk.Soluong) as Soluong'))
                ->where('dk.Ma_ct', 'XU')
                ->where('dk.Ma_vv', $orderInfo->So_dh)
                ->groupBy('dk.Ma_vv', 'hh.Ma_so')
                ->get();
                
            // Lấy tất cả chi tiết sản xuất của lệnh này MA_CT = SX (PHIẾU SẢN XUẤT)
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
            
            // Tính tổng xuất kho (XU)
            $totalXuatKho = $xuDetails2025->sum('Soluong') ?? 0;
            
            // Tính tổng nhập kho (NX trong ketoan DB)
            $totalNhapKho = $nhapTPKeToan->sum('Noluong') ?? 0;
            
            // Lấy công đoạn cuối cùng
            $congDoanCuoi = $summaryByCongDoan->sortByDesc('Ma_ko')->first();
            
            // Tổng sản xuất
            $totalSX = is_array($congDoanCuoi) ? ($congDoanCuoi['total_sx'] ?? 0) : 0;
            $totalLoi = $sxDetails->sum('Tien_vnd');
            
            // Tính % hoàn thành
            $soluongDon = DataKetoanData::where('Ma_ct', 'GO')
                ->where('So_ct', $soCt)
                ->sum('Soluong');
            $percentComplete = $soluongDon > 0 ? round(($totalSX / $soluongDon) * 100, 2) : 0;
            $percentXuatKho = $soluongDon > 0 ? round(($totalXuatKho / $soluongDon) * 100, 2) : 0;
            $percentNhapKho = $soluongDon > 0 ? round(($totalNhapKho / $soluongDon) * 100, 2) : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'orderInfo' => $orderInfo,
                    'sxDetails' => $sxDetails,
                    'congDoanCuoi' => $congDoanCuoi,
                    'summary' => [
                        'so_ct' => $soCt,
                        'so_dh' => $orderInfo->So_dh,
                        'so_luong_don' => $soluongDon,
                        'total_sx' => $totalSX,
                        'total_loi' => $totalLoi,
                        'con_thieu' => $soluongDon - $totalSX,
                        'percent_complete' => $percentComplete,
                        'by_cong_doan' => $summaryByCongDoan,
                        'total_nhap_kho' => $totalNhapKho,
                        'percent_nhap_kho' => $percentNhapKho,
                        'total_xuat_kho' => $totalXuatKho,
                        'percent_xuat_kho' => $percentXuatKho,
                        'con_thieu_xuat_kho' => $soluongDon - $totalXuatKho
                    ],
                    'nxDetails' => $nxDetails,
                    'ckDetails' => $ckDetails,
                    'daSuDungVatTu' => $daSuDungVatTu, // ← FIX: Đổi tên rõ ràng hơn
                    'nhapTPKeToan' => $nhapTPKeToan,   // ← FIX: Đổi tên rõ ràng hơn
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
        DB::raw('go.Soluong_go as Soluong_go'),
        DB::raw('go.Soseri_go as Soseri_go')
    )
    ->leftJoin(DB::raw("
        (
            SELECT So_ct, So_dh, SUM(Soluong) AS Soluong_go, Soseri AS Soseri_go
            FROM DataKetoanData
            WHERE Ma_ct = 'GO'
            GROUP BY So_ct, So_dh, Soseri
        ) AS go
    "), 'go.So_ct', '=', 'DataKetoanData.So_dh')
    ->where('DataKetoanData.Ma_ct', '=', 'SX')
    // ->where('DataKetoanData.Ngay_ct', '>=', '2026-01-01')
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
    $dinhMucStatus = DataKetoanData::select('So_dh')
        ->where('Ma_ct', 'NX')
        ->whereIn('So_dh', $allSoDh)
        ->groupBy('So_dh')
        ->pluck('So_dh')
        ->flip()
        ->toArray();
    
    // Kiểm tra đã xuất vật tư (CK trong DataKetoan2025)
    $xuatVatTuStatus = DataKetoan2025::select('So_dh')
        ->where('Ma_ct', 'CK')
        ->whereIn('So_dh', $allSoDh)
        ->groupBy('So_dh')
        ->pluck('So_dh')
        ->flip()
        ->toArray();
    
    // Kiểm tra đã nhập kho (Ma_ko = 6 trong DataKetoanData)
    $nhapKhoStatus = DataKetoanData::select('So_dh')
        ->where('Ma_ko', '06')
        ->whereIn('So_dh', $allSoDh)
        ->groupBy('So_dh')
        ->pluck('So_dh')
        ->flip()
        ->toArray();
    
    // Kiểm tra đã xuất kho (Ma_ko = 9 trong DataKetoanData)
    $xuatKhoStatus = DataKetoanData::select('So_dh')
        ->where('Ma_ko', '09')
        ->whereIn('So_dh', $allSoDh)
        ->groupBy('So_dh')
        ->pluck('So_dh')
        ->flip()
        ->toArray();

    // ===== KIỂM TRA TỒN KHO (cd 09 - cd 06) =====
    $tonKho = DB::query()
        ->fromSub(
            DB::table('DataKetoanData')
                ->select('Ma_hh', DB::raw('SUM(Soluong) as Soluong'))
                ->where('Ma_ko', '09')
                ->groupBy('Ma_hh'),
            'cd9'
        )
        ->rightJoinSub(
            DB::table('DataKetoanData')
                ->select('Ma_hh', DB::raw('SUM(Soluong) as Soluong'))
                ->where('Ma_ko', '06')
                ->groupBy('Ma_hh'),
            'cd6',
            'cd9.Ma_hh',
            '=',
            'cd6.Ma_hh'
        )
        ->select(
            DB::raw('COALESCE(cd9.Ma_hh, cd6.Ma_hh) as Ma_hh'),
            DB::raw('COALESCE(cd9.Soluong, 0) as xuat_kho'),
            DB::raw('COALESCE(cd6.Soluong, 0) as nhap_kho'),
            DB::raw('CAST(COALESCE(cd6.Soluong, 0) - COALESCE(cd9.Soluong, 0) AS INT) as ton_kho')
        )
        ->get()
        ->keyBy('Ma_hh');

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
        'statusMap' => $statusMap,
        'tonKho' => $tonKho
    ]);
}

    // API lọc dữ liệu theo DgiaiV (Phiếu chuyển kho nội bộ)
    public function getDataByDgiaiV(Request $request)
    {
        $dgiaiV = $request->get('dgiaiV', '');
        
        if (empty($dgiaiV)) {
            return response()->json([
                'success' => false,
                'message' => 'Vui lòng nhập DgiaiV',
                'data' => []
            ]);
        }

        $data = DataKetoan2025::select(
                'Ma_hh',
                'Soluong',
                'So_dh',
                'Ngay_ct',
                'DgiaiV'
            )
            ->where('Ma_ct', 'CK')
            ->where('DgiaiV', 'like', '%' . $dgiaiV . '%')
            ->orderBy('Ngay_ct')
            ->get()
            ->sortBy(function ($item) {
                // Sắp xếp theo ngày từ DgiaiV (dd/mm/yyyy)
                // Nếu không hợp lệ, xếp xuống cuối cùng
                if (empty($item->DgiaiV)) {
                    return '9999-12-31';
                }
                $pattern = '/^\d{1,2}\/\d{1,2}\/\d{4}$/';
                if (!preg_match($pattern, $item->DgiaiV)) {
                    return '9999-12-31';
                }
                list($day, $month, $year) = explode('/', $item->DgiaiV);
                return "{$year}-{$month}-{$day}";
            })
            ->values()
            ->map(function ($item, $index) {
                $item->Stt = $index + 1;
                return $item;
            });

        return response()->json([
            'success' => true,
            'count' => $data->count(),
            'data' => $data
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

    // API lấy toàn bộ dữ liệu SX từ 2026-01-01 đến nay để làm nhập phiếu 
    public function getAllSXData(Request $request)
    {
        try {
            $data = DataKetoanData::with([
                'hangHoa:Ma_hh,Ten_hh,Dvt,Pngpath,Ma_so',
                'nhanVien:Ma_nv,Ten_nv',
                'khachHang:Ma_kh,Ten_kh'
            ])
            ->select(
                'DataKetoanData.So_dh',
                'DataKetoanData.Ma_hh',
                'DataKetoanData.Ma_ct',
                'DataKetoanData.Ngay_ct',
                'DataKetoanData.Ma_ko',
                'DataKetoanData.Soluong',
                'DataKetoanData.Ma_nv',
                'DataKetoanData.Ma_kh',
                'DataKetoanData.Soseri',
                'DataKetoanData.DgiaiV',
                'DataKetoanData.UserNgE',
                'DataKetoanData.Date',
                DB::raw('go.So_dh as So_dh_go')
            )
            ->leftJoin(DB::raw("
                (
                    SELECT So_ct, So_dh
                    FROM DataKetoanData
                    WHERE Ma_ct = 'GO'
                    GROUP BY So_ct, So_dh
                ) AS go
            "), 'go.So_ct', '=', 'DataKetoanData.So_dh')
            ->where('DataKetoanData.Ma_ct', '=', 'SX')
            ->where('DataKetoanData.Ngay_ct', '>=', '2026-01-01')
            ->where('DataKetoanData.Ma_ko', '=', '06')
            ->groupBy('DataKetoanData.So_dh', 'DataKetoanData.Ma_hh', 'DataKetoanData.Ma_ct', 
                      'DataKetoanData.Ngay_ct', 'DataKetoanData.Ma_ko', 'DataKetoanData.Soluong',
                      'DataKetoanData.Ma_nv', 'DataKetoanData.Ma_kh',
                      'DataKetoanData.Soseri', 'DataKetoanData.DgiaiV', 'DataKetoanData.UserNgE',
                      'DataKetoanData.Date', 'go.So_dh')
            ->orderBy('DataKetoanData.Ngay_ct', 'asc')
            ->orderBy('DataKetoanData.So_dh')
            ->get();

            return response()->json([
                'success' => true,
                'data' => $data,
                'total_records' => $data->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    // View hiển thị phân tích (NX)
    public function viewNXData()
    {
        return view('Client.view-nx-data');
    }

    // API lấy dữ liệu phân tích (NX - PHÂN TÍCH ĐỊNH MỨC)
    public function getNXData(Request $request)
    {
        try {
            $data = DataKetoanData::with([
                'hangHoa:Ma_hh,Ten_hh,Dvt,Pngpath,Ma_so',
                'nhanVien:Ma_nv,Ten_nv',
                'khachHang:Ma_kh,Ten_kh'
            ])
            ->select(
                'DataKetoanData.So_dh',
                'DataKetoanData.Ma_hh',
                'DataKetoanData.Ma_nv',
                'DataKetoanData.Soluong',
                'DataKetoanData.Soseri',
                'DataKetoanData.Ma_ko',
                'DataKetoanData.Ngay_ct',
                'DataKetoanData.Ghichu',
                'DataKetoanData.Ma_kh',
                DB::raw('go.Soseri as Soseri_go'),
                DB::raw('go.Soluong as Soluong_go'),
                DB::raw('go.So_dh as So_dh_go')
            )
            ->leftJoin(DB::raw("
                (
                    SELECT So_ct, Soseri, SUM(Soluong) AS Soluong,So_dh
                    FROM DataKetoanData
                    WHERE Ma_ct = 'GO'
                    GROUP BY So_ct, Soseri, So_dh
                ) AS go
            "), 'go.So_ct', '=', 'DataKetoanData.So_dh')
            ->where('DataKetoanData.Ma_ct', '=', 'NX')
            ->where('DataKetoanData.Ngay_ct', '>=', '2026-01-01')
            ->orderBy('DataKetoanData.So_dh')
            ->orderBy('DataKetoanData.Ma_hh')
            ->get()
            ->map(function ($item) {
                $item->Soluong_go = $item->Soluong_go ?? 0;
                $item->Soseri = $item->Soseri_go ?? $item->Soseri;
                $item->So_dh = $item->So_dh_go ?? $item->So_dh;
                return $item;
            });

            // Tính tổng định mức theo lệnh
            $summaryBySoDh = $data->groupBy('So_dh')->map(function ($items) {
                return [
                    'so_dh' => $items->first()->So_dh,
                    'total_items' => $items->count(),
                    'total_soluong' => $items->sum('Soluong'),
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $data,
                'summaryBySoDh' => $summaryBySoDh,
                'total_records' => $data->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }

    public function exportTonKho(Request $request)
    {
        try {
            // ===== KIỂM TRA TỒN KHO (cd 09 - cd 06) =====
            $tonKho = DB::query()
                ->fromSub(
                    DB::table('DataKetoanData')
                        ->select('Ma_hh', DB::raw('SUM(Soluong) as Soluong'))
                        ->where('Ma_ko', '09')
                        ->groupBy('Ma_hh'),
                    'cd9'
                )
                ->rightJoinSub(
                    DB::table('DataKetoanData')
                        ->select('Ma_hh', DB::raw('SUM(Soluong) as Soluong'))
                        ->where('Ma_ko', '06')
                        ->groupBy('Ma_hh'),
                    'cd6',
                    'cd9.Ma_hh',
                    '=',
                    'cd6.Ma_hh'
                )
                ->leftJoin('CodeHangHoa', function($join) {
                    $join->on(DB::raw('COALESCE(cd9.Ma_hh, cd6.Ma_hh)'), '=', 'CodeHangHoa.Ma_hh');
                })
                ->select(
                    DB::raw('COALESCE(cd9.Ma_hh, cd6.Ma_hh) as Ma_hh'),
                    'CodeHangHoa.Ten_hh',
                    DB::raw('COALESCE(cd9.Soluong, 0) as xuat_kho'),
                    DB::raw('COALESCE(cd6.Soluong, 0) as nhap_kho'),
                    DB::raw('CAST(COALESCE(cd6.Soluong, 0) - COALESCE(cd9.Soluong, 0) AS INT) as ton_kho')
                )
                ->orderBy('Ma_hh')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $tonKho,
                'count' => $tonKho->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }
}