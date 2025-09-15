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
        $data = DB::table('TSoft_NhanTG_kt_new.dbo.DataKetoan2025 as d')
            ->join('TSoft_NhanTG_kt_new.dbo.CodeHanghoa as c', 'd.Ma_hh', '=', 'c.Ma_hh')
            ->where('d.Ma_ct', '=', 'XU')
            ->orderBy('d.Ngay_ct', 'desc')
            ->limit(250)
            ->select(
                'd.*',
                'c.Dvt',
                'c.Ten_hh' // đơn vị tính từ bảng codehanghoa
            )
            ->get();

        return response()->json([
            'data' => $data,
        ]);
    }
}
