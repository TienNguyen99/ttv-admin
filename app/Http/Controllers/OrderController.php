<?php

namespace App\Http\Controllers;

use App\Models\DataKetoanOder;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Hiển thị trang danh sách Order
     */
    public function index()
    {
        return view('client.order');
    }

    /**
     * Trả dữ liệu JSON cho DataTables
     * - Load các quan hệ chính
     * - Chia nhỏ để tránh giới hạn 2100 tham số của SQL Server
     */
    public function getData()
    {
        // ⚙️ Bước 1: Lấy dữ liệu chính cùng các quan hệ cần thiết (ngoại trừ lenhSanxuat)
        $data = DataKetoanOder::with([
                'khachHang:Ma_kh,Ten_kh',
                'hangHoa:Ma_hh,Ten_hh,Dvt,Pngpath,Ma_so',
                
            ])
            ->select(
                'Ngay_ct',
                'So_ct',
                'Soseri',
                'DgiaiV',
                'Ma_kh',
                'Ma_hh',
                'Soluong',
                'So_dh',
                'Ngay_dh',
                'Ma_ch',
                'Msize',
                'Ma_so'
            )
            ->orderBy('Ngay_ct', 'desc')
            ->where('Ngay_ct', '>=', '2025-11-01')
            
            ->get();

        // ⚙️ Bước 2: Lazy load lenhSanxuat theo từng batch 2000 phần tử
        $data->chunk(2000)->each(function ($chunk) {
            $chunk->load('lenhSanxuat:So_hd,So_dh,So_ct');
        });

        // ⚙️ Bước 3: Trả về JSON cho DataTables
        return response()->json($data);
    }
}
