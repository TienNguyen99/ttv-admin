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
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 3mm;
            overflow: hidden;
            page-break-after: always;
            break-after: page;
        }
        .label:last-child { page-break-after: auto; break-after: auto; }
        .code { font-size: 32px; font-weight: 900; line-height: .95; letter-spacing: 0; text-align: center; overflow-wrap: anywhere; }
        .code.has-color { font-size: 22px; line-height: 1.08; }
        .empty { width: 58mm; min-height: 40mm; padding: 8mm 5mm; font-size: 12px; text-align: center; }
        .missing {
            width: 58mm;
            min-height: 40mm;
            padding: 6mm;
            font-size: 12px;
            page-break-before: always;
            break-before: page;
        }
        @media screen {
            body { padding: 12px; background: #e5e7eb; }
            .label, .empty, .missing { margin-bottom: 3mm; background: #fff; border: 1px dashed #94a3b8; }
        }
        @media print {
            .missing.is-hidden-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">
@forelse($locations as $location)
    @php
        $colors = collect($location->label_colors ?? [])->filter()->values();
        $label = $colors->isNotEmpty()
            ? $location->location_code . ' - ' . $colors->join(', ')
            : 'Kệ ' . $location->location_code;
    @endphp
    <section class="label">
        <div class="code {{ $colors->isNotEmpty() ? 'has-color' : '' }}">{{ $label }}</div>
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
