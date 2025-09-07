<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DataKetoanData;
use Illuminate\Support\Facades\DB;

class ClientHomeController extends Controller
{
    public function index()
    {
        return view('client.home');
    }

    public function getData()
    {
        $data = DataKetoanData::with(['khachHang', 'hangHoa'])
            ->where('Ma_ct', '=', 'GO')
            ->orderby('Ngay_ct', 'asc')


            ->get();

        $sumSoLuong = DB::table('DataKetoanData')
            ->select('So_ct', DB::raw('SUM(Soluong) as total'))
            ->where('Ma_ct', '=', 'GO')
            ->groupBy('So_ct')
            ->pluck('total', 'So_ct');

        $cd = fn($cd) => DB::table('DataKetoanData')
            ->select('So_dh', 'Ma_hh', DB::raw('SUM(Soluong) as total'))
            ->where('Ma_ct', '=', 'SX')
            ->where('Ma_ko', '=', $cd)
            ->groupBy('So_dh', 'Ma_hh')
            ->get()
            ->keyBy(fn($i) => $i->So_dh . '|' . $i->Ma_hh);

        $cd1 = $cd('01');
        $cd2 = $cd('02');
        $cd3 = $cd('03');
        $cd4 = $cd('04');

        $nx = DB::table('DataKetoanData')
            ->where('Ma_ct', '=', 'NX')
            ->pluck('So_dh')
            ->toArray();

        $xv = DB::table('DataKetoan2025')
            ->where('Ma_ct', '=', 'CK')
            ->pluck('So_dh')
            ->toArray();
        //Nhập kho data
        // Lấy tổng nhập kho theo Ma_hh và So_dh
        $nhapKho = DB::table('DataKetoan2025')
            ->select('So_dh', 'Ma_hh', DB::raw('SUM(Soluong) as total_nhap'))
            ->where('Ma_ct', '=', 'NV')
            ->groupBy('So_dh', 'Ma_hh')
            ->get()
            ->keyBy(fn($i) => $i->So_dh . '|' . $i->Ma_hh);
        //Hiển thị So  tạo nên tổng nhập kho Theo ma_hh và So_dh


        // $nhaptpketoan = DB::table('TSoft_NhanTG_kt_new.dbo.DataKetoan2025')
        //     ->selectdistinct('Ma_vv', 'Ma_sp', DB::raw('SUM(Noluong) as total_nhaptpketoan'))
        //     ->where('Ma_ct', '=', 'NX')
        //     ->groupBy('Ma_vv', 'Ma_sp')
        //     ->get()
        //     ->keyBy(fn($i) => $i->Ma_vv . '|' . $i->Ma_sp);
        //Phan ke toan
        //Nhập thành phẩm kế toán 
        $sub = DB::table('TSoft_NhanTG_kt_new.dbo.DataKetoan2025')
            ->select('Ma_vv', 'Ma_sp', 'Noluong', 'SttRecN')
            ->where('Ma_ct', '=', 'NX')
            ->distinct();

        $nhaptpketoan = DB::query()
            ->fromSub($sub, 'sub')
            ->select('Ma_vv', 'Ma_sp', DB::raw('SUM(Noluong) as total_nhaptpketoan'))
            ->groupBy('Ma_vv', 'Ma_sp')
            ->get()
            ->keyBy(fn($i) => $i->Ma_vv . '|' . $i->Ma_sp);
        // Tổng tồn kế toán theo Ma_sp
        $tongnhapkhoketoan = DB::table(DB::raw("({$sub->toSql()}) as sub"))
            ->mergeBindings($sub)
            ->select('Ma_sp', DB::raw('SUM(Noluong) as totalnhapkho_ketoan'))
            ->groupBy('Ma_sp')
            ->get()
            ->keyBy('Ma_sp');
        $tongxuatkhoketoan = DB::table('TSoft_NhanTG_kt_new.dbo.DataKetoan2025')
            ->select('Ma_hh', DB::raw('SUM(Soluong) as totalxuatkho_ketoan'))
            ->where('Ma_ct', '=', 'XU')
            ->groupBy('Ma_hh')
            ->get()
            ->keyBy('Ma_hh');
        // Tổng xuất kho của kế toán theo Ma_vv
        $xuatkhotheomavvketoan = DB::table('DataKetoan2025')
            ->select('Ma_vv', 'Ma_hh', DB::raw('SUM(Soluong) as xuatkhotheomavv_ketoan'))
            ->where('Ma_ct', '=', 'XV')
            ->groupBy('Ma_vv', 'Ma_hh')
            ->get()
            ->keyBy(fn($i) => $i->Ma_vv . '|' . $i->Ma_hh);
        // Chi tiết xuất kho API




        // Get Ma_sp from DataKetoan2025 Ketoan
        // $datamahhketoan = DB::table('TSoft_NhanTG_kt_new.dbo.DataKetoan2025')
        //     ->select('Ma_vv', 'Ma_sp')
        //     ->where('Ma_ct', '=', 'NX')
        //     ->distinct()
        //     ->get()
        //     ->keyBy('Ma_vv');
        // Lấy mã HH kế toán
        $rawData = DB::table('TSoft_NhanTG_kt_new.dbo.DataKetoan2025')
            ->select('Ma_vv', 'Ma_sp')
            ->where('Ma_ct', '=', 'NX')
            ->distinct()
            ->get();

        // Gộp theo Ma_vv => [Ma_sp1, Ma_sp2, ...]
        $datamahhketoan = [];

        foreach ($rawData as $item) {
            $datamahhketoan[$item->Ma_vv][] = $item->Ma_sp;
        }

        // XUAT KHO DATA KETOAN
        $xuatKho = DB::table('TSoft_NhanTG_kt_test.dbo.DataKetoan2024')
            ->select('So_dh', 'Ma_hh', DB::raw('SUM(Soluong) as total_xuat'))
            ->where('Ma_ct', '=', 'XU')
            ->groupBy('So_dh', 'Ma_hh')
            ->get()
            ->keyBy(fn($i) => $i->So_dh . '|' . $i->Ma_hh);


        return response()->json([
            'data' => $data,
            'sumSoLuong' => $sumSoLuong,
            'cd1' => $cd1,
            'cd2' => $cd2,
            'cd3' => $cd3,
            'cd4' => $cd4,
            'nx' => $nx,
            'xv' => $xv,
            'nhapKho' => $nhapKho,
            'nhaptpketoan' => $nhaptpketoan,
            'datamahhketoan' => $datamahhketoan,
            'tongnhapkhoketoan' => $tongnhapkhoketoan,
            'tongxuatkhoketoan' => $tongxuatkhoketoan,
            'xuatkhotheomavvketoan' => $xuatkhotheomavvketoan,
            'xuatKho' => $xuatKho
        ]);
    }
    // API riêng lấy chi tiết nhập kho
    public function getNhapKhoDetail(Request $request)
    {
        $so_dh = urldecode($request->query('so_dh'));
        $ma_hh = urldecode($request->query('ma_hh'));
        $details = DB::table('DataKetoan2025')
            ->select('Ngay_ct', 'So_ct', 'Ma_hh', 'Soluong')
            ->where('Ma_ct', '=', 'NV')
            ->where('So_dh', $so_dh)         // lọc theo số đơn hàng
            ->where('Ma_hh', $ma_hh)         // lọc theo mã hàng
            ->orderBy('Ngay_ct')
            ->get();

        return response()->json($details);
    }
    // API của DATA kế toán
    // API riêng lấy chi tiết xuất kho
    public function getXuatKhoKeToanDetail(Request $request)
    {
        $ma_hh = urldecode($request->query('ma_hh'));

        $details = DB::table('TSoft_NhanTG_kt_new.dbo.DataKetoan2025')
            ->select('Ngay_ct', 'So_ct', 'Ma_hh', 'Soluong')
            ->where('Ma_ct', '=', 'XU')
            ->where('Ma_hh', $ma_hh)
            ->orderBy('Ngay_ct')
            ->get();

        return response()->json($details);
    }
    // API lấy danh sách vật tư Nhập thành phẩm của kế toán để tìm nguyên liệu phân tích
    public function getVatTuThanhPhamKeToan(Request $request)
    {
        $ma_vv = urldecode($request->query('ma_vv'));

        $vattu = DB::table('TSoft_NhanTG_kt_new.dbo.DataKetoan2025')
            ->select('Ma_vv', 'Ma_sp', 'Ma_hh', 'Soluong', 'DgiaiV', 'Noluong')
            ->where('Ma_ct', '=', 'NX')
            ->where('Ma_vv', $ma_vv)         // lọc theo số đơn hàng
            ->orderBy('Ma_sp')
            ->get();

        return response()->json($vattu);
    }

    //API của DATA Sản xuất
    // API lấy danh sách phân tích
    public function getPhanTich(Request $request)
    {
        $so_dh = urldecode($request->query('so_dh'));

        $phantich = DataKetoanData::with(['hangHoa'])
            ->select('Ngay_ct', 'So_ct', 'Ma_hh', 'Soluong')
            ->where('Ma_ct', '=', 'NX')
            ->where('So_dh', $so_dh)         // lọc theo số đơn hàng
            ->orderBy('Ngay_ct')
            ->get();

        return response()->json($phantich);
    }

    // API lấy danh sách xuất vật tư
    public function getXuatVatTu(Request $request)
    {
        $so_dh = urldecode($request->query('so_dh'));

        // 1️⃣ Lấy tổng số lượng đơn hàng (GO)
        $soLuongDonHang = DB::table('DataKetoanData')
            ->where('Ma_ct', 'GO')
            ->where('So_ct', $so_dh)
            ->sum('Soluong');

        // 2️⃣ Lấy định mức NX theo mã NVL (Ma_hh)
        $dinhMuc = DB::table('DataKetoanData')
            ->where('Ma_ct', 'NX')
            ->where('So_dh', $so_dh)
            ->pluck('Soluong', 'Ma_hh');

        // 3️⃣ Lấy danh sách xuất vật tư (CK)
        $dsXuat = DB::table('DataKetoan2025')
            ->select('Ngay_ct', 'So_ct', 'Ma_ko', 'Ma3ko', 'Ma_hh', 'Soluong')
            ->where('Ma_ct', '=', 'CK')
            ->where('So_dh', $so_dh)
            ->orderBy('Ngay_ct')
            ->get();


        // 4️⃣ Tính thêm "Nhu cầu" và "Tổng đã xuất"
        $tongDaXuat = []; // cộng dồn
        $vat_tu = [];

        foreach ($dsXuat as $row) {
            $maHH = $row->Ma_hh;

            // Tính nhu cầu = định mức * số lượng đơn hàng
            $nhuCau = ($dinhMuc[$maHH] ?? 0) * $soLuongDonHang;

            // Cộng dồn tổng đã xuất
            if (!isset($tongDaXuat[$maHH])) {
                $tongDaXuat[$maHH] = 0;
            }
            $tongDaXuat[$maHH] += $row->Soluong;

            // Thêm vào danh sách kết quả
            $vat_tu[] = [
                'Ngay_ct'      => $row->Ngay_ct,
                'So_ct'        => $row->So_ct,
                'Ma_ko'        => $row->Ma_ko,
                'Ma3ko'        => $row->Ma3ko,
                'Ma_hh'        => $maHH,
                'Soluong'      => $row->Soluong,
                'Nhu_cau'      => $nhuCau,
                'Tong_da_xuat' => $tongDaXuat[$maHH],
            ];
        }

        return response()->json($vat_tu);
    }
}
