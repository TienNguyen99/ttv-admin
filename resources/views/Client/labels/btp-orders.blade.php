<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>In tem lệnh BTP</title>
    <style>
        @page { size: 58mm 40mm; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; color: #111827; }
        .label {
            width: 58mm;
            height: 40mm;
            display: flex;
            flex-direction: column;
            gap: 1.2mm;
            padding: 3mm;
            overflow: hidden;
            page-break-after: always;
            break-after: page;
        }
        .label:last-child { page-break-after: auto; break-after: auto; }
        .top { display: flex; justify-content: space-between; gap: 2mm; align-items: flex-start; }
        .order { font-size: 13px; font-weight: 900; line-height: 1.05; overflow-wrap: anywhere; }
        .customer { font-size: 8px; font-weight: 800; color: #475569; text-align: right; max-width: 20mm; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .item { font-size: 10px; font-weight: 900; line-height: 1.15; max-height: 9mm; overflow: hidden; }
        .meta { font-size: 8px; line-height: 1.18; color: #374151; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .quantity { font-size: 13px; font-weight: 900; color: #166534; }
        .chips { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5mm; margin-top: auto; }
        .chip { border: .25mm solid #111827; border-radius: 1mm; min-height: 6.5mm; padding: 1mm; font-size: 8px; font-weight: 800; }
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
        $quantity = rtrim(rtrim(number_format((float) optional($line)->quantity, 3, ',', '.'), '0'), ',');
    @endphp
    <section class="label">
        <div class="top">
            <div class="order">{{ $order->btp_order_code }}</div>
            <div class="customer">{{ $order->customer ?: '-' }}</div>
        </div>
        <div class="item">{{ $displayCode ?: '-' }}{{ $itemName ? ' - ' . $itemName : '' }}</div>
        <div class="meta">Size {{ optional($line)->size ?: '-' }} | Màu {{ optional($line)->color ?: '-' }} | Mặt {{ optional($line)->side ?: '-' }}</div>
        <div class="meta">SL xuất: <span class="quantity">{{ $quantity }}</span> {{ optional($line)->dvt ?: 'pcs' }} | Kệ: {{ optional($line)->location_code ?: '-' }}</div>
        <div class="chips">
            <div class="chip">Đạt:</div>
            <div class="chip">Lỗi:</div>
        </div>
    </section>
@empty
    <div class="empty">Không có lệnh BTP để in tem.</div>
@endforelse
</body>
</html>
