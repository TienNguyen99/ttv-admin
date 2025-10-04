<?php

namespace App\Http\Controllers;

use App\Models\CodeHangHoa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\DataKetoanOder;
use App\Models\DataKetoanData;
use App\Models\CodeDanhMuc;
use App\Models\DataKetoan2025;
use App\Models\EditHanghoa;
use App\Models\EditketoanEdit;
use App\Models\DataCdLO2025;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;

class DanhMucController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // Lấy dữ liệu lênh sản xuất
        $data = DataKetoanData::all();
        // Hiển thị view với dữ liệu
        return view('danhmuc', compact('data'));
    }
    public function doinl()
    {
        // Lấy dữ liệu lênh sản xuất
        $data = DataKetoanData::all();
        // Hiển thị view với dữ liệu
        return view('doinl', compact('data'));
    }
    public function checkUpdateMaHH(Request $request)
    {
        $old = trim($request->input('old_code'));
        $new = trim($request->input('new_code'));
        $lenhsx = trim($request->input('lenhsx'));

        if ($old === $new) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mã mới và mã cũ không được giống nhau!'
            ]);
        }

        $log = [];

        if (!empty($lenhsx)) {
            // 🔹 Nếu có lệnh sản xuất → chỉ check bảng có filter trong updateMaHH
            $log = [
                'DataKetoanData (Ma_hh|So_ct)' => DataKetoanData::where('Ma_hh', $old)
                    ->where('So_ct', $lenhsx)->count(),
                'DataKetoanData (Ma_sp|So_ct)' => DataKetoanData::where('Ma_sp', $old)
                    ->where('So_ct', $lenhsx)->count(),
                'DataKetoanData (Ma_hh|So_dh)' => DataKetoanData::where('Ma_hh', $old)
                    ->where('So_dh', $lenhsx)->count(),
                'DataKetoanData (Ma_sp|So_dh)' => DataKetoanData::where('Ma_sp', $old)
                    ->where('So_dh', $lenhsx)->count(),
                'DataKetoan2025 (Ma_hh|So_dh)' => DataKetoan2025::where('Ma_hh', $old)
                    ->where('So_dh', $lenhsx)->count(),
            ];
        } else {
            // 🔹 Nếu không có lệnh sản xuất → check tất cả như hiện tại
            $log = [
                'DataKetoanOder (Ma_hh)'   => DataKetoanOder::where('Ma_hh', $old)->count(),
                'DataKetoanData (Ma_hh)'   => DataKetoanData::where('Ma_hh', $old)->count(),
                'DataKetoanData (Ma_sp)'   => DataKetoanData::where('Ma_sp', $old)->count(),
                'DataKetoan2025 (Ma_hh)'   => DataKetoan2025::where('Ma_hh', $old)->count(),
                'CodeDanhMuc (Codeid1)'    => CodeDanhMuc::where('Codeid1', $old)->count(),
                'CodeHangHoa (Ma_so)'      => CodeHangHoa::where('Ma_so', $old)->count(),
                'DataCdLO2025 (Ma_hh)'     => DataCdLO2025::where('Ma_hh', $old)->count(),
            ];
        }

        return response()->json([
            'status' => 'ok',
            'log'    => $log
        ]);
    }



    public function updateMaHH(Request $request)
    {
        $old = trim($request->input('old_code'));
        $new = trim($request->input('new_code'));
        $lenhsx = trim($request->input('lenhsx'));

        if ($old === $new) {
            return back()->with('error', 'Mã mới và mã cũ không được giống nhau!');
        }

        // Nếu có lệnh sản xuất thì update có điều kiện
        if (!empty($lenhsx)) {

            DataKetoanData::where('Ma_hh', $old)->where('So_ct', $lenhsx)->update(['Ma_hh' => $new]);
            DataKetoanData::where('Ma_sp', $old)->where('So_ct', $lenhsx)->update(['Ma_sp' => $new]);
            DataKetoanData::where('Ma_hh', $old)->where('So_dh', $lenhsx)->update(['Ma_hh' => $new]);
            DataKetoanData::where('Ma_sp', $old)->where('So_dh', $lenhsx)->update(['Ma_sp' => $new]);
            DataKetoan2025::where('Ma_hh', $old)->where('So_dh', $lenhsx)->update(['Ma_hh' => $new]);
        } else {
            // Update toàn bộ như hiện tại
            DataKetoanOder::where('Ma_hh', $old)->update(['Ma_hh' => $new]);
            DataKetoanData::where('Ma_hh', $old)->update(['Ma_hh' => $new]);
            DataKetoanData::where('Ma_sp', $old)->update(['Ma_sp' => $new]);
            CodeDanhMuc::where('Codeid1', $old)->update(['Codeid1' => $new]);
            DataCdLO2025::where('Ma_hh', $old)->update(['Ma_hh' => $new]);
            DataKetoan2025::where('Ma_hh', $old)->update(['Ma_hh' => $new]);
            // CodeHangHoa::where('Ma_so', $old)->update(['Ma_so' => $new]);
        }

        return redirect()->route('danhmuc')->with('success', 'Cập nhật Mã HH thành công!');
    }
    // update mã nguyên liệu theo lệnh sản xuất So_dh
    public function updateMaNL(Request $request)
    {
        $old = trim($request->input('old_code'));
        $new = trim($request->input('new_code'));

        if ($old === $new) {
            return back()->with('error', 'Mã mới và mã cũ không được giống nhau!');
        }

        // DataKetoanOder::where('Ma_hh', $old)->update(['Ma_hh' => $new]);
        DataKetoanData::where('Ma_hh', $old)->update(['Ma_hh' => $new]);
        DataKetoan2025::where('Ma_hh', $old)->update(['Ma_hh' => $new]);
        CodeDanhMuc::where('Ma_ch', $old)->update(['Ma_ch' => $new]);
        // DataKetoanData::where('Ma_sp', $old)->update(['Ma_sp' => $new]);
        // CodeDanhMuc::where('Codeid1', $old)->update(['Codeid1' => $new]);
        // //EditketoanEdit::where('Ma_sp', $old)->update(['Ma_sp' => $new]);
        // DataKetoanData::where('Ma_hh', $old)->update(['Ma_hh' => $new]);
        // //EditHanghoa::where('Ma_hh', $old)->update(['Ma_hh' => $new]);
        // //EditHanghoa::where('Ma_sp', $old)->update(['Ma_sp' => $new]);
        // // CodeHangHoa::where('Ma_so', $old)->update(['Ma_so' => $new]);
        // DataCdLO2025::where('Ma_hh', $old)->update(['Ma_hh' => $new]);
        // DataKetoan2025::where('Ma_hh', $old)->update(['Ma_hh' => $new]);

        return redirect()->route('doinl')->with('success', 'Cập nhật Mã Nguyên liệu thành công!');
    }



    public function suggestMaHH(Request $request)
    {
        $term = $request->input('term');

        // $results = CodeHangHoa::where('Ma_hh', 'like', '%' . $term . '%')
        $results = DB::table('TSoft_NhanTG_kt_new.dbo.CodeHanghoa')->where('Ma_hh', 'like', '%' . $term . '%')
            ->orWhere('Ten_hh', 'like', '%' . $term . '%')
            ->limit(20)
            ->get(['Ma_hh', 'Ten_hh', 'Dvt','Dgbanvnd']);
        return response()->json($results);
    }
}
