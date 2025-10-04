<?php

namespace App\Http\Controllers;

use App\Models\DataKetoanData;
use Illuminate\Http\Request;

class SanXuatController extends Controller
{
    public function index()
    {
        return view('Client.sanxuat');
    }

    public function getData()
    {
        $data = DataKetoanData::with(['khachHang', 'hangHoa'])
            ->select('SttRecN','Ngay_ct','So_ct','Ma_nv','Ma_ko','Ma_hh','Soluong','Tien_vnd','So_dh')
            ->where('Ma_ct', '=', 'SX')
            ->get();

        return response()->json($data);
    }

public function update(Request $request, $SttRecN)
{
    $data = DataKetoanData::where('SttRecN', $SttRecN)->firstOrFail();

    $data->Ngay_ct  = $request->Ngay_ct;
    $data->So_ct    = $request->So_ct;
    $data->Ma_nv    = $request->Ma_nv;
    $data->Ma_ko    = $request->Ma_ko;
    $data->Ma_hh    = $request->Ma_hh;
    $data->Soluong  = $request->Soluong;
    $data->Tien_vnd = $request->Tien_vnd;
    $data->So_dh    = $request->So_dh;

    $data->save();

    return response()->json(['success' => true, 'data' => $data]);
}

    public function destroy($SttRecN)
    {
    DataKetoanData::where('SttRecN', $SttRecN)->delete();

    return response()->json(['success' => true]);
    }
}
