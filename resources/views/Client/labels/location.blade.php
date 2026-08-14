<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>{{ $location->location_code }}</title>
    <style>
        @page { size: 58mm 40mm; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; color: #111827; }
        .label {
            width: 58mm;
            height: 40mm;
            display: grid;
            grid-template-rows: 29mm 7mm;
            place-items: center;
            padding: 1.5mm 2mm 1mm;
            overflow: hidden;
        }
        .qr { display: block; width: 28mm; height: 28mm; object-fit: contain; image-rendering: pixelated; }
        .code { align-self: center; font-size: 19pt; font-weight: 900; line-height: 1; letter-spacing: 0; text-align: center; white-space: nowrap; }
        @media screen {
            body { padding: 12px; background: #e5e7eb; }
            .label { background: #fff; border: 1px dashed #94a3b8; }
        }
        @media print { body { width: 58mm; height: 40mm; } }
    </style>
</head>
<body onload="window.print()">
    @php($detailUrl = url('/client/kiem-ton-kho/vi-tri/' . $location->id))
    <div class="label">
        <img class="qr" src="{{ url('/qr-code') }}?text={{ urlencode($detailUrl) }}&size=420&margin=2" alt="QR {{ $location->location_code }}">
        <div class="code">{{ $location->location_code }}</div>
    </div>
</body>
</html>
