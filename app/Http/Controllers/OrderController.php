<?php

namespace App\Http\Controllers;

use App\Models\DataKetoanOder;
use Illuminate\Http\Request;

class OrderController extends Controller
{
        public function index()
    {
        return view('client.order');
    }

    public function getData()
    {
        $data = DataKetoanOder::with(['khachHang:Ma_kh,Ten_kh', 'hangHoa:Ma_hh,Ten_hh,Dvt,Pngpath,Ma_so', 'nhanVien:Ma_nv,Ten_nv','lenhSanxuat:So_hd,So_dh,So_ct'])
            ->select('Ngay_ct', 'So_ct','Soseri','DgiaiV','Ma_kh','Ma_hh','Soluong','So_dh','Ngay_dh','Ma_ch','Msize','Ma_so')
            ->orderBy('Ngay_ct', 'desc')
            ->get();

        return response()->json($data);
    }
}
