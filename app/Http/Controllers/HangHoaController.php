<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HangHoaController extends Controller
{
    public function editMaHH()
    {
        $data = DB::table('DataKetoanData')
            ->join('codekhachang', 'DataKetoanData.Ma_kh', '=', 'codekhachang.Ma_kh')
            ->leftJoin('codehanghoa', 'DataKetoanData.Ma_hh', '=', 'codehanghoa.Ma_hh')
            ->where('Ma_ct', '=', 'GO')

            ->get();

        return view('hanghoa', compact('data'));
    }
    //Cập nhật Mã HH của 2 bảng DataKetoanData và DataKetoanOder
    public function updateMaHH(Request $request)
    {
        foreach ($request->input('mahh') as $so_ct => $ma_hh_moi) {
            DB::table('DataKetoanData')
                ->where('So_ct', $so_ct)
                ->update(['Ma_hh' => $ma_hh_moi]);
            DB::table('DataKetoanOder')
                ->where('So_dh', $so_ct)
                ->update(['Ma_hh' => $ma_hh_moi]);
            DB::table('DataKetoan2025')
                ->where('So_dh', $so_ct)
                ->update(['Ma_hh' => $ma_hh_moi]);
        }

        return redirect()->route('mahh.edit')->with('success', 'Cập nhật Mã HH thành công!');
    }
    public function suggestMaHH(Request $request)
    {
        $term = $request->input('term');

        $results = DB::table('codehanghoa')
            ->where('Ten_hh', 'like', '%' . $term . '%')
            ->orWhere('Ma_hh', 'like', '%' . $term . '%')
            ->limit(20)
            ->get(['Ma_hh', 'Ten_hh']);

        return response()->json($results);
    }
}
