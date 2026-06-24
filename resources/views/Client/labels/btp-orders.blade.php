<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>In QR lệnh BTP</title>
    <style>
        @page { size: 58mm 40mm; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; color: #111827; }
        .label {
            width: 58mm;
            height: 40mm;
            display: grid;
            grid-template-columns: 19mm 1fr;
            gap: 2mm;
            padding: 2.2mm 2.4mm;
            page-break-after: always;
            break-after: page;
            overflow: hidden;
        }
        .label:last-child { page-break-after: auto; break-after: auto; }
        .qr { display: flex; flex-direction: column; align-items: center; gap: 1mm; }
        .qr img { width: 18mm; height: 18mm; display: block; }
        .qr-code { font-size: 8px; font-weight: 800; text-align: center; line-height: 1.05; overflow-wrap: anywhere; }
        .info { min-width: 0; display: flex; flex-direction: column; gap: .8mm; }
        .title { font-size: 11px; font-weight: 900; line-height: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .meta { font-size: 8px; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .item { font-size: 8.5px; font-weight: 700; line-height: 1.1; max-height: 9.5mm; overflow: hidden; }
        .chips { display: grid; grid-template-columns: 1fr 1fr; gap: 1mm; margin-top: .5mm; }
        .chip { border: .25mm solid #111827; border-radius: 1mm; padding: .9mm 1mm; font-size: 8px; min-height: 6mm; }
        .chip strong { display: inline-block; min-width: 7mm; }
        .empty { width: 58mm; min-height: 40mm; padding: 8mm 5mm; font-size: 12px; text-align: center; }
        @media screen {
            body { padding: 12px; background: #e5e7eb; }
            .label, .empty { margin-bottom: 3mm; background: #fff; border: 1px dashed #94a3b8; }
        }
    </style>
</head>
<body onload="window.print()">
@forelse($orders as $order)
    @php
        $line = $order->lines->first();
        $displayCode = trim((string) optional($line)->internal_item_code) ?: trim((string) optional($line)->ma_hh);
        $itemName = trim((string) optional($line)->ten_hh);
        $qrUrl = url('/client/lenh-btp') . '?keyword=' . urlencode($order->btp_order_code);
        $quantity = rtrim(rtrim(number_format((float) optional($line)->quantity, 3, ',', '.'), '0'), ',');
    @endphp
    <section class="label">
        <div class="qr">
            <img src="{{ url('/qr-code') }}?size=180&text={{ urlencode($qrUrl) }}" alt="QR {{ $order->btp_order_code }}">
            <div class="qr-code">{{ $order->btp_order_code }}</div>
        </div>
        <div class="info">
            <div class="title">{{ $order->btp_order_code }}</div>
            <div class="meta">Khách: {{ $order->customer ?: '-' }}</div>
            <div class="item">{{ $displayCode ?: '-' }}{{ $itemName ? ' - ' . $itemName : '' }}</div>
            <div class="meta">
                Size {{ optional($line)->size ?: '-' }}
                | Màu {{ optional($line)->color ?: '-' }}
                | Mặt {{ optional($line)->side ?: '-' }}
            </div>
            <div class="meta">SL xuất: <strong>{{ $quantity }}</strong> {{ optional($line)->dvt ?: 'pcs' }} | Kệ: {{ optional($line)->location_code ?: '-' }}</div>
            <div class="chips">
                <div class="chip"><strong>Đạt:</strong></div>
                <div class="chip"><strong>Lỗi:</strong></div>
            </div>
        </div>
    </section>
@empty
    <div class="empty">Không có lệnh BTP để in QR.</div>
@endforelse
</body>
</html>
