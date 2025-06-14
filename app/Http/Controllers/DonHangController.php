<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DataKetoanData;
use App\Models\DataKetoanOder;
use App\Models\DataKetoan2025;
use App\Models\CodeHangHoa;
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
            //DataKetoanData::where('So_ct', $so_ct)->update(['Ma_hh' => $ma_hh_moi]);
            DataKetoanOder::where('So_ct', $so_ct)->update(['Ma_hh' => $ma_hh_moi]);
            //DataKetoan2025::where('So_dh', $so_ct)->update(['Ma_hh' => $ma_hh_moi]);
        }

        return redirect()->route('donhang')->with('success', 'Cập nhật Mã HH thành công!');
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
