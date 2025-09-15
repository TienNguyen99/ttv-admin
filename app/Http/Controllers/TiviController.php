<?php

namespace App\Http\Controllers;

use App\Models\DataKetoanData;
use Illuminate\Http\Request;

class TiviController extends Controller
{
    public function tiviIndex()
    {
        return view('client.tivi');
    }
    // API hiển thị Tivi
    public function getTiviData()
    {
        $today = now();
        $upcoming = $today->copy()->addDays(7);

        $data = DataKetoanData::with(['khachHang', 'hangHoa'])
            ->where('Ma_ct', '=', 'GO')
            ->where('Loaisx', '!=', 'M')
            ->whereBetween('Date', [$today, $upcoming])
            ->orWhere('Date', '<', $today)
            ->orderBy('Date', 'desc')
            ->limit(100)
            ->get();

        return response()->json($data);
    }
}
