<?php

namespace App\Http\Controllers;

use App\Models\DataKetoan2025;
use App\Models\DataKetoanData;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class TiviController extends Controller
{

    public function tiviSanxuat()
    {
        return view('Client.tivisanxuat');
    }


    // View xem toÃ n bá»™ dá»¯ liá»‡u SX
    public function viewAllSXData()
    {
        return view('Client.view-all-sx-data');
    }


    
    // API chi tiáº¿t lá»‡nh sáº£n xuáº¥t
    public function getSXDetailBySoCt(Request $request, $soCt)
    {
        try {
            // Láº¥y thÃ´ng tin lá»‡nh GO (ÄÆ¡n hÃ ng gá»‘c)
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
                    'message' => 'KhÃ´ng tÃ¬m tháº¥y lá»‡nh'
                ], 404);
            }
            
            // Láº¥y táº¥t cáº£ chi tiáº¿t sáº£n xuáº¥t cá»§a lá»‡nh nÃ y MA_CT = NX (PHÃ‚N TÃCH - Äá»ŠNH Má»¨C)
            $nxDetails = DataKetoanData::with([
                'hangHoa:Ma_hh,Ten_hh,Dvt,Pngpath,Ma_so',
                'nhanVien:Ma_nv,Ten_nv',
            ])
            ->select('DataKetoanData.*')
            ->where('DataKetoanData.Ma_ct', 'NX')
            ->where('DataKetoanData.So_dh', $soCt)
            ->get();
           
            // Láº¥y táº¥t cáº£ chi tiáº¿t sáº£n xuáº¥t cá»§a lá»‡nh nÃ y MA_CT = CK (PHIáº¾U CHUYá»‚N KHO Ná»˜I Bá»˜ - ÄÃƒ XUáº¤T Váº¬T TÆ¯)
            $ckDetails = DataKetoan2025::with([
                'hangHoa:Ma_hh,Ten_hh,Dvt,Pngpath,Ma_so',
                'nhanVien:Ma_nv,Ten_nv',
            ])
            ->select('DataKetoan2026.*')
            ->where('DataKetoan2026.Ma_ct', 'CK')
            ->where('DataKetoan2026.So_dh', $soCt)
            ->get();
            
            // ===== FIX: Láº¤Y "ÄÃƒ Sá»¬ Dá»¤NG Váº¬T TÆ¯" =====
            // Query tá»« DataKetoan2025 vá»›i Ma_ct = 'NX' (Phiáº¿u xuáº¥t sá»­ dá»¥ng ná»™i bá»™)
            $daSuDungVatTu = DataKetoan2025::select(
                    'Ma_hh',
                    DB::raw('SUM(Soluong) as total_su_dung')
                )
                ->where('Ma_ct', 'NX')
                ->where('So_dh', $soCt)
                ->groupBy('Ma_hh')
                ->get();
            
            // Láº¤Y "ÄÃƒ NHáº¬P KHO THÃ€NH PHáº¨M" (tá»« DB Káº¿ ToÃ¡n)
            $nhapTPKeToan = DB::table('TSoft_NhanTG_kt_new.dbo.DataKetoan2026')
                ->select('Ma_vv', 'Ma_sp', DB::raw('SUM(DISTINCT Noluong) as Noluong'))
                ->where('Ma_ct', 'NX')
                ->where('Ma_vv', $orderInfo->So_dh)
                ->groupBy('Ma_vv', 'Ma_sp')
                ->get();

            // Láº¤Y "ÄÃƒ XUáº¤T KHO BÃN HÃ€NG"
            $xuDetails2025 = DB::table('TSoft_NhanTG_kt_new.dbo.DataKetoan2026 as dk')
                ->join('CodeHangHoa as hh', 'dk.Ma_hh', '=', 'hh.Ma_hh')
                ->select('dk.Ma_vv', 'hh.Ma_so', DB::raw('SUM(dk.Soluong) as Soluong'))
                ->where('dk.Ma_ct', 'XU')
                ->where('dk.Ma_vv', $orderInfo->So_dh)
                ->groupBy('dk.Ma_vv', 'hh.Ma_so')
                ->get();
                
            // Láº¥y táº¥t cáº£ chi tiáº¿t sáº£n xuáº¥t cá»§a lá»‡nh nÃ y MA_CT = SX (PHIáº¾U Sáº¢N XUáº¤T)
            $sxDetails = DataKetoanData::with([
                'hangHoa:Ma_hh,Ten_hh,Dvt,Pngpath,Ma_so',
                'nhanVien:Ma_nv,Ten_nv',
            ])
            ->select('DataKetoanData.*')
            ->where('DataKetoanData.Ma_ct', 'SX')
            ->where('DataKetoanData.So_dh', $soCt)
            ->orderBy('DataKetoanData.Ma_ko')
            ->get();

            // TÃ­nh tá»•ng sáº£n xuáº¥t theo cÃ´ng Ä‘oáº¡n
            $summaryByCongDoan = $sxDetails->groupBy('Ma_ko')->map(function ($items) {
                return [
                    'Ma_ko' => $items->first()->Ma_ko,
                    'total_sx' => $items->sum('Soluong'),
                    'total_loi' => $items->sum('Tien_vnd'),
                    'total_soluongkhac' => $items->sum('Dgbanvnd'),
                    'count' => $items->count()
                ];
            })->values();
            
            // TÃ­nh tá»•ng xuáº¥t kho (XU)
            $totalXuatKho = $xuDetails2025->sum('Soluong') ?? 0;
            
            // TÃ­nh tá»•ng nháº­p kho (NX trong ketoan DB)
            $totalNhapKho = $nhapTPKeToan->sum('Noluong') ?? 0;
            
            // Láº¥y cÃ´ng Ä‘oáº¡n cuá»‘i cÃ¹ng
            $congDoanCuoi = $summaryByCongDoan->sortByDesc('Ma_ko')->first();
            
            // Tá»•ng sáº£n xuáº¥t
            $totalSX = is_array($congDoanCuoi) ? ($congDoanCuoi['total_sx'] ?? 0) : 0;
            $totalLoi = $sxDetails->sum('Tien_vnd');
            
            // TÃ­nh % hoÃ n thÃ nh
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
                    'daSuDungVatTu' => $daSuDungVatTu, // â† FIX: Äá»•i tÃªn rÃµ rÃ ng hÆ¡n
                    'nhapTPKeToan' => $nhapTPKeToan,   // â† FIX: Äá»•i tÃªn rÃµ rÃ ng hÆ¡n
                    'xuDetails2025' => $xuDetails2025
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lá»—i: ' . $e->getMessage()
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

    // TÃ­nh tá»•ng theo lá»‡nh - CHá»ˆ Láº¤Y CÃ”NG ÄOáº N CUá»I (OPTIMIZED: single query)
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

    // ===== KIá»‚M TRA TRáº NG THÃI =====
    
    $allSoDh = $data->pluck('So_dh')->unique();
    
    // Kiá»ƒm tra cÃ³ Ä‘á»‹nh má»©c (NX trong DataKetoanData)
    $dinhMucStatus = DataKetoanData::select('So_dh')
        ->where('Ma_ct', 'NX')
        ->whereIn('So_dh', $allSoDh)
        ->groupBy('So_dh')
        ->pluck('So_dh')
        ->flip()
        ->toArray();
    
    // Kiá»ƒm tra Ä‘Ã£ xuáº¥t váº­t tÆ° (CK trong DataKetoan2025)
    $xuatVatTuStatus = DataKetoan2025::select('So_dh')
        ->where('Ma_ct', 'CK')
        ->whereIn('So_dh', $allSoDh)
        ->groupBy('So_dh')
        ->pluck('So_dh')
        ->flip()
        ->toArray();
    
    // Kiá»ƒm tra Ä‘Ã£ nháº­p kho (Ma_ko = 6 trong DataKetoanData)
    $nhapKhoStatus = DataKetoanData::select('So_dh')
        ->where('Ma_ko', '06')
        ->whereIn('So_dh', $allSoDh)
        ->groupBy('So_dh')
        ->pluck('So_dh')
        ->flip()
        ->toArray();
    
    // Kiá»ƒm tra Ä‘Ã£ xuáº¥t kho (Ma_ko = 9 trong DataKetoanData)
    $xuatKhoStatus = DataKetoanData::select('So_dh')
        ->where('Ma_ko', '09')
        ->whereIn('So_dh', $allSoDh)
        ->groupBy('So_dh')
        ->pluck('So_dh')
        ->flip()
        ->toArray();

    // ===== KIá»‚M TRA Tá»’N KHO (cd 09 - cd 06) =====
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

    // ===== KIá»‚M TRA XUáº¤T DÆ¯ Váº¬T TÆ¯ (OPTIMIZED) =====
    $xuatDuVatTu = [];
    if ($allSoDh->isNotEmpty()) {
        // 1. Láº¥y sá»‘ lÆ°á»£ng Ä‘Æ¡n cho táº¥t cáº£ cÃ¡c lá»‡nh
        $soLuongDonMap = DB::table('DataKetoanData')
            ->where('Ma_ct', 'GO')
            ->whereIn('So_ct', $allSoDh)
            ->groupBy('So_ct')
            ->select('So_ct', DB::raw('SUM(Soluong) as total_soluong'))
            ->pluck('total_soluong', 'So_ct');

        // 2. Láº¥y Ä‘á»‹nh má»©c cho táº¥t cáº£ cÃ¡c lá»‡nh
        $dinhMucData = DB::table('DataKetoanData')
            ->where('Ma_ct', 'NX')
            ->whereIn('So_dh', $allSoDh)
            ->groupBy('So_dh', 'Ma_hh')
            ->select('So_dh', 'Ma_hh', DB::raw('SUM(Soluong) as total_dinh_muc'))
            ->get()
            ->groupBy('So_dh');

        // 3. Láº¥y Ä‘Ã£ xuáº¥t cho táº¥t cáº£ cÃ¡c lá»‡nh
        $daXuatData = DB::table('DataKetoan2025')
            ->where('Ma_ct', 'CK')
            ->whereIn('So_dh', $allSoDh)
            ->groupBy('So_dh', 'Ma_hh')
            ->select('So_dh', 'Ma_hh', DB::raw('SUM(Soluong) as total_xuat'))
            ->get()
            ->groupBy('So_dh');

        foreach ($allSoDh as $soDh) {
            $soLuongDon = $soLuongDonMap[$soDh] ?? 0;
            
            if ($soLuongDon > 0) {
                $dinhMucBySoDh = $dinhMucData[$soDh] ?? collect();
                $dinhMuc = $dinhMucBySoDh->keyBy('Ma_hh');
                
                $daXuatBySoDh = $daXuatData[$soDh] ?? collect();
                
                $coDu = false;
                foreach ($daXuatBySoDh as $xuat) {
                    $maHh = $xuat->Ma_hh;
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
    }
    
    // Táº¡o map tráº¡ng thÃ¡i cho tá»«ng lá»‡nh
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

    // API lá»c dá»¯ liá»‡u theo DgiaiV (Phiáº¿u chuyá»ƒn kho ná»™i bá»™)
    public function getDataByDgiaiV(Request $request)
    {
        $dgiaiV = $request->get('dgiaiV', '');
        
        if (empty($dgiaiV)) {
            return response()->json([
                'success' => false,
                'message' => 'Vui lÃ²ng nháº­p DgiaiV',
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
                // Sáº¯p xáº¿p theo ngÃ y tá»« DgiaiV (dd/mm/yyyy)
                // Náº¿u khÃ´ng há»£p lá»‡, xáº¿p xuá»‘ng cuá»‘i cÃ¹ng
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

    // API hiá»ƒn thá»‹ Tivi
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

    // API láº¥y toÃ n bá»™ dá»¯ liá»‡u SX tá»« 2026-01-01 Ä‘áº¿n nay Ä‘á»ƒ lÃ m nháº­p phiáº¿u 
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
                'message' => 'Lá»—i: ' . $e->getMessage()
            ], 500);
        }
    }

    // View hiá»ƒn thá»‹ phÃ¢n tÃ­ch (NX)
    public function viewNXData()
    {
        return view('Client.view-nx-data');
    }

    // API láº¥y dá»¯ liá»‡u phÃ¢n tÃ­ch (NX - PHÃ‚N TÃCH Äá»ŠNH Má»¨C)
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

            // TÃ­nh tá»•ng Ä‘á»‹nh má»©c theo lá»‡nh
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
                'message' => 'Lá»—i: ' . $e->getMessage()
            ], 500);
        }
    }

    public function exportTonKho(Request $request)
    {
        try {
            // ===== KIá»‚M TRA Tá»’N KHO (cd 09 - cd 06) =====
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
                'message' => 'Lá»—i: ' . $e->getMessage()
            ], 500);
        }
    }
}
