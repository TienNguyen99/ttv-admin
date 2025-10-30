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
        $data = DataKetoanData::where('SttRecN', $SttRecN)
        ->where('Ma_ct', '=', 'SX')
        ->firstOrFail();

        // chỉ update các field có trong request
        foreach (['Ngay_ct','So_ct','Ma_nv','Ma_ko','Ma_hh','Soluong','Tien_vnd','So_dh'] as $field) {
            if ($request->has($field)) {
                $data->$field = $request->$field;
            }
        }

        $data->save();
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function destroy($SttRecN)
    {
        DataKetoanData::where('SttRecN', $SttRecN)->delete();
        return response()->json(['success' => true]);
    }
}
