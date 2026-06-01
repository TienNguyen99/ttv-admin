<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DataKetoanData;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class KeToanController extends Controller
{
    public function index()
    {
        return view('client.ketoan');
    }

    public function tonKho()
    {
        return view('client.ketoan-ton');
    }

    public function nhapThanhPham()
    {
        return view('client.phieu-nhap-thanh-pham');
    }

    public function getDataToday()
    {
        $data = DB::table('TSoft_NhanTG_kt_new.dbo.DataKetoan2026 as d')
            ->join('TSoft_NhanTG_kt_new.dbo.CodeHanghoa as c', 'd.Ma_hh', '=', 'c.Ma_hh')
            ->join('TSoft_NhanTG_kt_new.dbo.CodeKhachang as kh', 'd.Ma_kh', '=', 'kh.Ma_kh')
            ->where('d.Ma_ct', '=', 'XU')
            ->orderBy('d.Ngay_ct', 'desc')
            
            ->select(
                'd.Ngay_ct',
                'd.So_ct',
                'd.Chungtu',
                'd.Ma_ct',
                'd.So_hd',
                'd.Ma_hh',
                'd.Soluong',
                'd.DgiaiV',
                'd.DgiaiE',
                'd.Ma_vv',
                'd.Dgbanvnd',
                'd.Ghichu',
                'd.Tien_vnd',
                'kh.Ten_kh',
                'c.Dvt',
                'c.Ten_hh' // đơn vị tính từ bảng codehanghoa
            )
            ->get();

        return response()->json([
            'data' => $data,
        ]);
    }

    public function getTonKho(Request $request)
    {
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        $applyDateFilter = function ($query, $column) use ($fromDate, $toDate) {
            if (!empty($fromDate)) {
                $query->whereDate($column, '>=', Carbon::parse($fromDate)->format('Y-m-d'));
            }

            if (!empty($toDate)) {
                $query->whereDate($column, '<=', Carbon::parse($toDate)->format('Y-m-d'));
            }
        };

        $subNhap = DB::table('TSoft_NhanTG_kt_new.dbo.DataKetoan2026')
            ->select('Ma_sp', 'Noluong', 'SttRecN')
            ->where('Ma_ct', '=', 'NX')
            ->whereNotNull('Ma_sp')
            ->distinct();
        $applyDateFilter($subNhap, 'Ngay_ct');

        $nhap = DB::query()
            ->fromSub($subNhap, 'sub')
            ->select('Ma_sp as Ma_hh', DB::raw('SUM(Noluong) as tong_nhap'))
            ->groupBy('Ma_sp');

        $xuat = DB::table('TSoft_NhanTG_kt_new.dbo.DataKetoan2026')
            ->select('Ma_hh', DB::raw('SUM(Soluong) as tong_xuat'))
            ->where('Ma_ct', '=', 'XU')
            ->whereNotNull('Ma_hh')
            ->groupBy('Ma_hh');
        $applyDateFilter($xuat, 'Ngay_ct');

        $maHang = DB::query()
            ->fromSub((clone $nhap)->union(clone $xuat), 'codes')
            ->select('Ma_hh')
            ->whereNotNull('Ma_hh')
            ->groupBy('Ma_hh');

        $data = DB::query()
            ->fromSub($maHang, 'mh')
            ->leftJoinSub($nhap, 'n', 'mh.Ma_hh', '=', 'n.Ma_hh')
            ->leftJoinSub($xuat, 'x', 'mh.Ma_hh', '=', 'x.Ma_hh')
            ->leftJoin('TSoft_NhanTG_kt_new.dbo.CodeHanghoa as c', 'mh.Ma_hh', '=', 'c.Ma_hh')
            ->select(
                'mh.Ma_hh',
                'c.Ten_hh',
                'c.Dvt',
                DB::raw('COALESCE(n.tong_nhap, 0) as tong_nhap'),
                DB::raw('COALESCE(x.tong_xuat, 0) as tong_xuat'),
                DB::raw('COALESCE(n.tong_nhap, 0) - COALESCE(x.tong_xuat, 0) as ton_kho')
            )
            ->orderBy('mh.Ma_hh')
            ->get();

        return response()->json([
            'data' => $data,
            'summary' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'total_items' => $data->count(),
                'positive_items' => $data->where('ton_kho', '>', 0)->count(),
                'zero_items' => $data->where('ton_kho', '=', 0)->count(),
                'negative_items' => $data->where('ton_kho', '<', 0)->count(),
                'total_nhap' => $data->sum('tong_nhap'),
                'total_xuat' => $data->sum('tong_xuat'),
                'total_ton' => $data->sum('ton_kho'),
            ],
        ]);
    }

    public function getNhapThanhPham(Request $request)
    {
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $keyword = trim((string) $request->query('keyword', ''));

        $query = DB::table('TSoft_NhanTG_kt_new.dbo.DataKetoan2026 as d')
            ->select(
                'd.Ngay_ct',
                'd.So_ct',
                'd.Chungtu',
                'd.Ma_vv',
                'd.Ma_sp',
                'd.Ma_ko',
                'd.Ma3ko',
                DB::raw('MAX(d.Noluong) as Noluong'),
                DB::raw('MAX(d.DgiaiV) as DgiaiV'),
                DB::raw('MAX(d.Ghichu) as Ghichu'),
                DB::raw('MAX(d.SttRecN) as SttRecN')
            )
            ->where('d.Ma_ct', '=', 'NX')
            ->whereNotNull('d.Ma_sp');

        if (!empty($fromDate)) {
            $query->whereDate('d.Ngay_ct', '>=', Carbon::parse($fromDate)->format('Y-m-d'));
        }

        if (!empty($toDate)) {
            $query->whereDate('d.Ngay_ct', '<=', Carbon::parse($toDate)->format('Y-m-d'));
        }

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('d.So_ct', 'like', '%' . $keyword . '%')
                    ->orWhere('d.Chungtu', 'like', '%' . $keyword . '%')
                    ->orWhere('d.Ma_vv', 'like', '%' . $keyword . '%')
                    ->orWhere('d.Ma_sp', 'like', '%' . $keyword . '%')
                    ->orWhere('d.Ma_ko', 'like', '%' . $keyword . '%');
            });
        }

        $subNhapThanhPham = $query->groupBy(
            'd.Ngay_ct',
            'd.So_ct',
            'd.Chungtu',
            'd.Ma_vv',
            'd.Ma_sp',
            'd.Ma_ko',
            'd.Ma3ko'
        );

        $data = DB::query()
            ->fromSub($subNhapThanhPham, 'tp')
            ->leftJoin('TSoft_NhanTG_kt_new.dbo.CodeHanghoa as c', 'tp.Ma_sp', '=', 'c.Ma_hh')
            ->select(
                'tp.Ngay_ct',
                'tp.So_ct',
                'tp.Chungtu',
                'tp.Ma_vv',
                'tp.Ma_sp',
                'tp.Ma_ko',
                'tp.Ma3ko',
                'tp.Noluong',
                'tp.DgiaiV',
                'tp.Ghichu',
                'tp.SttRecN',
                'c.Ten_hh',
                'c.Dvt'
            )
            ->orderBy('tp.Ngay_ct', 'desc')
            ->orderBy('tp.So_ct')
            ->orderBy('tp.Ma_sp')
            ->get();

        return response()->json([
            'data' => $data,
            'summary' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'keyword' => $keyword,
                'total_rows' => $data->count(),
                'total_noluong' => $data->sum('Noluong'),
            ],
        ]);
    }
}
