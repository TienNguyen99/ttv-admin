<?php

namespace App\Http\Controllers;

use App\Models\DataKetoan2025;
use Illuminate\Http\Request;

class QuyDoiMucController extends Controller
{
    public function index()
    {
        return view('client.quydoimuc');
    }

    public function getKhomuc(Request $request)
    {
        $data = DataKetoan2025::with([
            'hangHoa:Ma_hh,Ten_hh,Dvt,Pngpath,Ma_so',
            'nhanVien:Ma_nv,Ten_nv',
            'khachHang:Ma_kh,Ten_kh'
        ])
        ->select(
            'DataKetoan2025.So_ct',
            'DataKetoan2025.Ma_hh',
            'DataKetoan2025.Ma_sp',
            'DataKetoan2025.Soluong',
            'DataKetoan2025.Noluong',
            'DataKetoan2025.So_dh'
        )
        ->where('DataKetoan2025.Ma_ct', '=', 'XN')
        ->get();

        return response()->json($data);
    }
}
