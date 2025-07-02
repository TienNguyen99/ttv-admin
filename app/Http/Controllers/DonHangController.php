<?php

namespace App\Http\Controllers;

use App\Models\CodeDanhMuc;
use Illuminate\Http\Request;
use App\Models\DataKetoanData;
use App\Models\DataKetoanOder;
use App\Models\DataKetoan2025;
use App\Models\CodeHangHoa;
use Dflydev\DotAccessData\Data;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;

class DonHangController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $data = DataKetoanOder::with(['khachHang', 'hangHoa'])->get();
        //->paginate(1000);

        return view('DonHang.donhang', compact('data'));
    }
    public function ordertolsx()
    {
        $data = DataKetoanOder::with(['khachHang', 'hangHoa'])->get();
        //->paginate(1000);

        return view('DonHang.ordertolsx', compact('data'));
    }
    public function updateMaHH(Request $request)
    {
        foreach ($request->input('mahh') as $so_ct => $ma_hh_moi) {

            // Kiểm tra nếu mã mới đã tồn tại trong hệ thống thì bỏ qua
            $isDuplicate = DataKetoanOder::where('Ma_hh', $ma_hh_moi)->exists()
                || DataKetoanData::where(function ($query) use ($ma_hh_moi) {
                    $query->where('Ma_hh', $ma_hh_moi)
                        ->orWhere('Ma_sp', $ma_hh_moi);
                })->exists()
                || CodeDanhMuc::where('Codeid1', $ma_hh_moi)->exists();

            if ($isDuplicate) {
                // Ghi log hoặc thông báo lỗi (nếu cần)
                continue; // bỏ qua cập nhật nếu mã mới bị trùng
            }

            // Cập nhật nếu không trùng
            DataKetoanOder::where('Ma_hh', $so_ct)->update(['Ma_hh' => $ma_hh_moi]);
            DataKetoanData::where(function ($query) use ($so_ct) {
                $query->where('Ma_hh', $so_ct)->orWhere('Ma_sp', $so_ct);
            })->update([
                'Ma_hh' => $ma_hh_moi,
                'Ma_sp' => $ma_hh_moi
            ]);
            CodeDanhMuc::where('Codeid1', $so_ct)->update(['Codeid1' => $ma_hh_moi]);
        }

        return redirect()->route('donhang')->with('success', 'Cập nhật Mã HH thành công (đã bỏ qua mã bị trùng)!');
    }

    public function suggestMaHH(Request $request)
    {
        $term = $request->input('term');

        $results = CodeHangHoa::where('Ma_hh', 'like', '%' . $term . '%')
            ->orWhere('Ten_hh', 'like', '%' . $term . '%')
            ->limit(20)
            ->get(['Ma_hh', 'Ten_hh', 'Dvt']);

        // $results = DB::table('codehanghoa')
        //     ->where('Ten_hh', 'like', '%' . $term . '%')
        //     ->orWhere('Ma_hh', 'like', '%' . $term . '%')
        //     ->limit(20)
        //     ->get(['Ma_hh', 'Ten_hh']);


        return response()->json($results);
    }
    public function guiPO(Request $request)
    {
        $selectedPOs = $request->input('selected_po', []);

        if (empty($selectedPOs)) {
            return back()->with('error', 'Vui lòng chọn ít nhất một PO.');
        }

        $jsonPath = storage_path('app/po_list.json');
        file_put_contents($jsonPath, json_encode($selectedPOs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return redirect()->route('ordertolsx')->with('success', 'Đã gửi danh sách PO đã chọn đến máy trạm.');
    }
}
