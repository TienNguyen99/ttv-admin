<?php

namespace App\Http\Controllers;

use App\Models\CodeHangHoa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\DataKetoanOder;
use App\Models\DataKetoanData;
use App\Models\CodeDanhMuc;
use App\Models\DataKetoan2025;

class DanhMucController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('danhmuc');
    }
    public function checkUpdateMaHH(Request $request)
    {
        $old = trim($request->input('old_code'));
        $new = trim($request->input('new_code'));

        if ($old === $new) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mã mới và mã cũ không được giống nhau!'
            ]);
        }

        $log = [
            'DataKetoanOder (Ma_hh)' => DataKetoanOder::where('Ma_hh', $old)->count(),
            'DataKetoanData (Ma_hh)' => DataKetoanData::where('Ma_hh', $old)->count(),
            'CodeDanhMuc (Codeid1)' => CodeDanhMuc::where('Codeid1', $old)->count(),
            'CodeHangHoa (Ma_so)' => CodeHangHoa::where('Ma_so', $old)->count(),
            'DataKetoanData (Ma_sp)' => DataKetoanData::where('Ma_sp', $old)->count(),
            'DataKetoan2025 (Ma_hh)' => DataKetoan2025::where('Ma_hh', $old)->count(),

        ];

        return response()->json([
            'status' => 'ok',
            'log' => $log
        ]);
    }

    public function updateMaHH(Request $request)
    {
        $old = trim($request->input('old_code'));
        $new = trim($request->input('new_code'));

        if ($old === $new) {
            return back()->with('error', 'Mã mới và mã cũ không được giống nhau!');
        }

        DataKetoanOder::where('Ma_hh', $old)->update(['Ma_hh' => $new]);
        DataKetoanData::where('Ma_hh', $old)->update(['Ma_hh' => $new]);
        DataKetoanData::where('Ma_sp', $old)->update(['Ma_sp' => $new]);
        CodeDanhMuc::where('Codeid1', $old)->update(['Codeid1' => $new]);

        CodeHangHoa::where('Ma_so', $old)->update(['Ma_so' => $new]);
        DataKetoan2025::where('Ma_hh', $old)->update(['Ma_hh' => $new]);


        return redirect()->route('danhmuc')->with('success', 'Cập nhật Mã HH thành công!');
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
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
