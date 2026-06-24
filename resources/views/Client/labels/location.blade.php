<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>{{ $location->location_code }}</title>
    <style>
        @page { size: 40mm 58mm; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; color: #111827; text-align: center; }
        .label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2mm;
            width: 40mm;
            height: 58mm;
            padding: 3mm;
        }
        img { width: 24mm; height: 24mm; display: block; }
        .code { font-size: 26px; font-weight: 900; line-height: 1; }
        .name { max-width: 34mm; font-size: 10px; font-weight: 700; color: #374151; line-height: 1.15; }
        .hint { max-width: 34mm; font-size: 8px; color: #6b7280; line-height: 1.2; }
        @media screen {
            body { padding: 10px; background: #f3f4f6; }
            .label { border: 1px dashed #9ca3af; background: #fff; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="label">
        <div class="code">{{ $location->location_code }}</div>
        <img src="{{ url('/qr-code') }}?size=180&text={{ urlencode(request()->root() . '/client/kiem-ton-kho/vi-tri/' . $location->id) }}" alt="QR {{ $location->location_code }}">
        <div class="name">{{ $location->location_name ?: 'Vị trí kho' }}</div>
        <div class="hint">Quét để xem mã hàng tại vị trí</div>
    </div>
</body>
</html>
