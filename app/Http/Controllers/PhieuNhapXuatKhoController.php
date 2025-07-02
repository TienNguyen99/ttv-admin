<?php

namespace App\Http\Controllers;

use App\Models\DataKetoan2025;
use App\Models\CodeHangHoa;
use Illuminate\Http\Request;

class PhieuNhapXuatKhoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $data = DataKetoan2025::all();
        return view('phieunhapxuatkho', ['data' => $data]);
    }
    public function updateMaHH(Request $request)
    {
        foreach ($request->input('mahh') as $so_ct => $ma_hh_moi) {
            //DataKetoanData::where('So_ct', $so_ct)->update(['Ma_hh' => $ma_hh_moi]);
            DataKetoan2025::where('So_ct', $so_ct)->update(['Ma_hh' => $ma_hh_moi]);

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
