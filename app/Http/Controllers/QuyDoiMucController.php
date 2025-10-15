<?php

namespace App\Http\Controllers;

use App\Models\DataKetoan2025;
use Illuminate\Http\Request;
use App\Models\CodeHangHoa;

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

    // 🔹 Lấy danh sách tất cả Ma_sp duy nhất
    $maSPs = $data->pluck('Ma_sp')->unique()->filter()->values();

    // 🔹 Truy vấn tên hàng cho các Ma_sp này
    $tenHangBySP = CodeHangHoa::whereIn('Ma_hh', $maSPs)
        ->pluck('Ten_hh', 'Ma_hh'); // ['NPL5' => '14085550', ...]

    // 🔹 Gắn tên hàng vào từng dòng (dưới key 'ten_sp')
    $data->transform(function ($item) use ($tenHangBySP) {
        $item->ten_sp = $tenHangBySP[$item->Ma_sp] ?? null;
        return $item;
    });

    return response()->json($data);
}
}
