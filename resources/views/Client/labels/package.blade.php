<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>{{ $package->package_code }}</title>
    <style>
        @page { size: 58mm 40mm; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; color: #111827; }
        .label {
            width: 58mm;
            height: 40mm;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 1.4mm;
            padding: 3.5mm;
            overflow: hidden;
        }
        .top { display: flex; justify-content: space-between; gap: 2mm; align-items: flex-start; }
        .package { font-size: 11px; font-weight: 900; line-height: 1.1; overflow-wrap: anywhere; }
        .location { font-size: 22px; font-weight: 900; line-height: .95; color: #1d4ed8; }
        .item { font-size: 10px; font-weight: 800; line-height: 1.15; overflow-wrap: anywhere; }
        .meta { font-size: 8px; line-height: 1.2; color: #374151; }
        .quantity { font-size: 15px; font-weight: 900; color: #166534; }
        @media screen {
            body { padding: 12px; background: #e5e7eb; }
            .label { background: #fff; border: 1px dashed #94a3b8; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="label">
        <div class="top">
            <div class="package">{{ $package->package_code }}</div>
            <div class="location">{{ optional($package->location)->location_code ?: '-' }}</div>
        </div>
        <div class="item">{{ $package->internal_item_code ?: $package->ma_sp }}</div>
        <div class="meta">Mã TP: {{ $package->ma_sp ?: '-' }}</div>
        <div class="meta">Size: {{ $package->size ?: '-' }} | Màu: {{ $package->color ?: '-' }}</div>
        <div class="meta">Mặt: {{ $package->side ?: '-' }} | SL: <span class="quantity">{{ rtrim(rtrim(number_format((float) $package->quantity, 3, ',', '.'), '0'), ',') }}</span></div>
    </div>
</body>
</html>
