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
}
