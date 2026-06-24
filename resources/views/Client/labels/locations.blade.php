<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>In QR vị trí hàng loạt</title>
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
            page-break-after: always;
            break-after: page;
        }
        .label:last-child { page-break-after: auto; break-after: auto; }
        .qr img { width: 24mm; height: 24mm; display: block; }
        .code { font-size: 26px; font-weight: 900; letter-spacing: 0; line-height: 1; }
        .name { max-width: 34mm; font-size: 10px; font-weight: 700; color: #374151; line-height: 1.15; }
        .hint { max-width: 34mm; font-size: 8px; color: #6b7280; line-height: 1.2; }
        .empty { padding: 12mm 6mm; font-size: 14px; }
        .missing {
            width: 40mm;
            min-height: 58mm;
            padding: 6mm;
            font-size: 12px;
            page-break-before: always;
            break-before: page;
        }
        @media screen {
            body { padding: 12px; background: #f3f4f6; }
            .label, .empty, .missing {
                margin: 0 0 3mm;
                border: 1px dashed #9ca3af;
                background: #fff;
            }
        }
        @media print {
            .missing.is-hidden-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">
@forelse($locations as $location)
    <section class="label">
        <div class="qr">
            <img src="{{ url('/qr-code') }}?size=180&text={{ urlencode(request()->root() . '/client/kiem-ton-kho/vi-tri/' . $location->id) }}" alt="QR {{ $location->location_code }}">
        </div>
        <div>
            <div class="code">{{ $location->location_code }}</div>
            <div class="name">{{ $location->location_name ?: 'Vị trí kho' }}</div>
            <div class="hint">Quét để xem mã hàng tại vị trí</div>
        </div>
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
