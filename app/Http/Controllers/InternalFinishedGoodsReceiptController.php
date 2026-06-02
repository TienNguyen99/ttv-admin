<?php

namespace App\Http\Controllers;

use App\Models\InternalFinishedGoodsReceipt;
use Illuminate\Http\Request;

class InternalFinishedGoodsReceiptController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'receipt_date' => 'required|date',
            'ma_sp' => 'required|string|max:100',
            'ma_ko' => 'nullable|string|max:50',
            'ten_hh' => 'nullable|string|max:255',
            'dvt' => 'nullable|string|max:50',
            'quantity' => 'required|numeric|min:0.001',
            'note' => 'nullable|string|max:500',
        ]);

        $receipt = InternalFinishedGoodsReceipt::query()->create([
            'receipt_code' => $this->nextReceiptCode(),
            'receipt_date' => $data['receipt_date'],
            'ma_sp' => trim($data['ma_sp']),
            'ma_ko' => strtoupper(trim($data['ma_ko'] ?? '')),
            'ten_hh' => trim($data['ten_hh'] ?? ''),
            'dvt' => trim($data['dvt'] ?? ''),
            'quantity' => $data['quantity'],
            'status' => 'draft',
            'note' => trim($data['note'] ?? ''),
        ]);

        return response()->json([
            'message' => 'Đã tạo phiếu nhập thành phẩm nội bộ.',
            'data' => $receipt,
            'print_url' => url('/client/phieu-nhap-thanh-pham-noi-bo/' . $receipt->id . '/in'),
        ]);
    }

    public function print(InternalFinishedGoodsReceipt $receipt)
    {
        return view('client.internal-finished-goods-receipt-print', [
            'receipt' => $receipt,
        ]);
    }

    private function nextReceiptCode()
    {
        $prefix = 'PNTP-' . now()->format('Ymd') . '-';
        $last = InternalFinishedGoodsReceipt::query()
            ->where('receipt_code', 'like', $prefix . '%')
            ->orderByDesc('receipt_code')
            ->value('receipt_code');

        $number = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
