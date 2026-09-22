<!DOCTYPE html>
<html lang=vi>
<head>
    <meta charset=UTF-8>
    <title>In QR v&#7883; tr&#237; v&#224; m&#227; h&#224;ng</title>
    <style>
        @page { size: A4 portrait; margin: 7mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; color: #111827; background: #fff; }
        .sheet {
            width: 195mm;
            height: 280mm;
            display: grid;
            grid-template-columns: repeat(3, 65mm);
            grid-template-rows: repeat(7, 40mm);
            align-content: start;
            page-break-after: always;
            break-after: page;
        }
        .sheet:last-of-type { page-break-after: auto; break-after: auto; }
        .label {
            width: 65mm;
            height: 40mm;
            display: grid;
            grid-template-rows: 5.5mm 25.5mm 7mm;
            place-items: center;
            padding: 1mm 2mm;
            overflow: hidden;
            border: .2mm dashed #64748b;
            break-inside: avoid;
        }
        .location { font-size: 15pt; font-weight: 900; line-height: 1; text-align: center; }
        .qr { display: block; width: 25mm; height: 25mm; object-fit: contain; image-rendering: pixelated; }
        .item-code { width: 100%; align-self: center; font-size: 14pt; font-weight: 900; line-height: 1.05; text-align: center; overflow-wrap: anywhere; }
        .item-code.is-long { font-size: 11pt; }
        .item-code.is-very-long { font-size: 9pt; }
        .missing { width: 58mm; min-height: 40mm; padding: 7mm 5mm; font-size: 12px; text-align: center; }
        @media screen {
            body { padding: 12px; background: #e5e7eb; }
            .sheet { margin: 0 auto 12px; background: #fff; box-shadow: 0 4px 18px rgba(15, 23, 42, .14); }
        }
        @media print { .missing { display: none; } }
    </style>
</head>
<body onload=window.print()>
@foreach($labels->chunk(21) as $sheetLabels)
<section class=sheet>
@foreach($sheetLabels as $label)
    @php($location = $label['location'])
    @php($itemCode = $label['item_code'])
    @php($detailUrl = url('/client/kiem-ton-kho/vi-tri/' . $location->id) . ($itemCode !== '' ? '?' . http_build_query(['item_code' => $itemCode]) : ''))
    @php($qrUrl = url('/qr-code') . '?' . http_build_query(['text' => $detailUrl, 'size' => 420, 'margin' => 2]))
    @php($codeLength = mb_strlen($itemCode))
    @php($codeClass = $codeLength > 30 ? 'is-very-long' : ($codeLength > 20 ? 'is-long' : ''))
    <article class=label>
        <div class=location>{{ $location->location_code }}</div>
        <img class="qr" src="{{ $qrUrl }}" alt="QR {{ $location->location_code }} {{ $itemCode }}">
        <div class=@json(trim('item-code ' . $codeClass))>{{ $itemCode }}</div>
    </article>
@endforeach
</section>
@endforeach
@if($missingCodes->isNotEmpty())
    <section class=missing>
        <strong>Ch&#432;a c&#243; trong danh s&#225;ch v&#7883; tr&#237;:</strong>
        {{ $missingCodes->join(', ') }}
    </section>
@endif
</body>
</html>
