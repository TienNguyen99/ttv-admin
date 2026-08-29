<?php

namespace App\Http\Controllers;

use App\Services\InternalProductionOrderLinkReconciler;
use Illuminate\Http\Request;

class InternalProductionOrderLinkController extends Controller
{
    public function index(Request $request, InternalProductionOrderLinkReconciler $reconciler)
    {
        $limit = min(max((int) $request->query('limit', 500), 1), 2000);

        return response()->json([
            'data' => $reconciler->preview($limit),
        ]);
    }

    public function store(Request $request, InternalProductionOrderLinkReconciler $reconciler)
    {
        $data = $request->validate([
            'confirm' => 'required|accepted',
            'limit' => 'nullable|integer|min:1|max:2000',
        ]);

        return response()->json([
            'message' => 'Đã cập nhật các liên kết có độ tin cậy cao trong database nội bộ.',
            'data' => $reconciler->apply((int) ($data['limit'] ?? 500)),
        ]);
    }
}
