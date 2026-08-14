<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>In tem vị trí hàng loạt</title>
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
            page-break-after: always;
            break-after: page;
        }
        .label:last-child { page-break-after: auto; break-after: auto; }
        .qr { display: block; width: 28mm; height: 28mm; object-fit: contain; image-rendering: pixelated; }
        .code { align-self: center; font-size: 19pt; font-weight: 900; line-height: 1; letter-spacing: 0; text-align: center; white-space: nowrap; }
        .empty, .missing { width: 58mm; min-height: 40mm; padding: 7mm 5mm; font-size: 12px; text-align: center; }
        .missing { page-break-before: always; break-before: page; }
        @media screen {
            body { padding: 12px; background: #e5e7eb; }
            .label, .empty, .missing { margin-bottom: 3mm; background: #fff; border: 1px dashed #94a3b8; }
        }
        @media print { .missing.is-hidden-print { display: none; } }
    </style>
</head>
<body onload="window.print()">
@forelse($locations as $location)
    @php($detailUrl = url('/client/kiem-ton-kho/vi-tri/' . $location->id))
    <section class="label">
        <img class="qr" src="{{ url('/qr-code') }}?text={{ urlencode($detailUrl) }}&size=420&margin=2" alt="QR {{ $location->location_code }}">
        <div class="code">{{ $location->location_code }}</div>
    </section>
@empty
    <div class="empty">Không tìm thấy vị trí nào để in. Hãy tạo vị trí trước.</div>
@endforelse

@if($missingCodes->isNotEmpty())
    <section class="missing is-hidden-print">
        <strong>Chưa có trong danh sách vị trí:</strong>
        {{ $missingCodes->join(', ') }}
    </section>
@endif
</body>
</html>
