<?php

namespace App\Http\Controllers;

use App\Models\InternalUnitConversion;
use App\Services\InternalUnitConverter;
use Illuminate\Http\Request;

class InternalUnitConversionController extends Controller
{
    public function index(Request $request)
    {
        $keyword = trim((string) $request->query('keyword', ''));

        return response()->json([
            'data' => app(InternalUnitConverter::class)->list($keyword),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'item_code' => 'nullable|string|max:100',
            'from_unit' => 'required|string|max:50',
            'to_unit' => 'required|string|max:50',
            'factor' => 'required|numeric|min:0.0000000001',
            'note' => 'nullable|string|max:500',
        ]);

        $row = app(InternalUnitConverter::class)->upsert($data);

        return response()->json([
            'message' => 'Đã lưu quy đổi đơn vị.',
            'data' => $row,
        ]);
    }

    public function destroy(InternalUnitConversion $unitConversion)
    {
        $unitConversion->delete();

        return response()->json([
            'message' => 'Đã xóa quy đổi đơn vị.',
        ]);
    }
}
