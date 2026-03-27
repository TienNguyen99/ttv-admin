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
     * Trả dữ liệu JSON cho DataTables (Server-side processing)
     * Mỗi lần chỉ load 1 trang → không bao giờ vượt giới hạn 2100 parameter của SQL Server
     */
    public function getData(Request $request)
    {
        $draw = intval($request->input('draw', 1));
        $start = intval($request->input('start', 0));
        $length = intval($request->input('length', 10));
        $search = $request->input('search.value', '');
        $orderColIndex = intval($request->input('order.0.column', 0));
        $orderDir = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';

        // Map column index → cột có thể sort trên DB
        $sortableColumns = [
            0 => 'Ngay_ct',
            1 => 'So_ct',
            2 => 'Soseri',
            3 => 'DgiaiV',
            5 => 'Ma_hh',
            7 => 'Ma_ch',
            8 => 'Msize',
            9 => 'Ma_so',
            11 => 'Soluong',
        ];

        $baseQuery = DataKetoanOder::where('Ngay_ct', '>=', '2025-11-01');

        // Tổng số records (chưa search)
        $totalRecords = (clone $baseQuery)->count();

        // Tìm kiếm toàn cục
        if ($search !== '') {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('So_ct', 'like', "%{$search}%")
                  ->orWhere('Soseri', 'like', "%{$search}%")
                  ->orWhere('DgiaiV', 'like', "%{$search}%")
                  ->orWhere('Ma_hh', 'like', "%{$search}%")
                  ->orWhere('Ma_ch', 'like', "%{$search}%")
                  ->orWhere('Msize', 'like', "%{$search}%")
                  ->orWhere('Ma_so', 'like', "%{$search}%")
                  ->orWhereHas('khachHang', fn($sub) => $sub->where('Ten_kh', 'like', "%{$search}%"))
                  ->orWhereHas('hangHoa', fn($sub) => $sub->where('Ten_hh', 'like', "%{$search}%"));
            });
        }

        // Tổng sau khi search
        $filteredRecords = (clone $baseQuery)->count();

        // Sắp xếp + phân trang (dùng TOP thay vì OFFSET để tương thích SQL Server cũ)
        $sortColumn = $sortableColumns[$orderColIndex] ?? 'Ngay_ct';

        $data = $baseQuery
            ->select('Ngay_ct', 'So_ct', 'Soseri', 'DgiaiV', 'Ma_kh', 'Ma_hh', 'Soluong', 'So_dh', 'Ngay_dh', 'Ma_ch', 'Msize', 'Ma_so')
            ->orderBy($sortColumn, $orderDir)
            ->limit($start + $length)
            ->get()
            ->slice($start, $length)
            ->values();

        // Eager load relationships — chỉ cho trang hiện tại (10-50 dòng, không bao giờ chạm 2100)
        $data->load([
            'khachHang:Ma_kh,Ten_kh',
            'hangHoa:Ma_hh,Ten_hh,Dvt,Pngpath,Ma_so',
            'lenhSanxuat:So_hd,So_dh,So_ct',
        ]);

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }
}
