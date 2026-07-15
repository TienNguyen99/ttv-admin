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
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 3mm;
            overflow: hidden;
        }
        .code { font-size: 32px; font-weight: 900; line-height: .95; letter-spacing: 0; text-align: center; overflow-wrap: anywhere; }
        .code.has-color { font-size: 22px; line-height: 1.08; }
        @media screen {
            body { padding: 12px; background: #e5e7eb; }
            .label { background: #fff; border: 1px dashed #94a3b8; }
        }
    </style>
</head>
<body onload="window.print()">
    @php
        $colors = collect($location->label_colors ?? [])->filter()->values();
        $label = $colors->isNotEmpty()
            ? $location->location_code . ' - ' . $colors->join(', ')
            : 'Kệ ' . $location->location_code;
    @endphp
    <div class="label">
        <div class="code {{ $colors->isNotEmpty() ? 'has-color' : '' }}">{{ $label }}</div>
    </div>
</body>
</html>
