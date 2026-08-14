<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Lệnh dệt {{ $plan['order']['production_order'] ?? '' }}</title>
    <style>
        @page { size:A4 portrait; margin:8mm; }
        * { box-sizing:border-box; }
        body { margin:0; color:#111; background:#eef2f7; font:12px Arial,sans-serif; }
        .toolbar { position:sticky; top:0; display:flex; justify-content:flex-end; padding:10px; background:#fff; border-bottom:1px solid #ccd5df; }
        .toolbar button { padding:8px 14px; border:1px solid #2866b1; border-radius:6px; background:#2866b1; color:#fff; font-weight:700; cursor:pointer; }
        .sheet { width:194mm; min-height:277mm; margin:12px auto; padding:4mm; background:#fff; }
        h1 { margin:0 0 6px; text-align:center; font:700 18px "Times New Roman",serif; }
        table { width:100%; border-collapse:collapse; table-layout:fixed; }
        th,td { border:1px solid #222; padding:4px 5px; vertical-align:middle; overflow-wrap:anywhere; }
        th { font-weight:700; }
        .meta td { height:26px; }
        .label { width:14%; font-weight:700; }
        .value { font-weight:700; }
        .materials th { font-size:10px; }
        .materials td { height:27px; }
        .number { text-align:right; }
        .center { text-align:center; }
        .spec-grid { display:grid; grid-template-columns:1fr 1fr; border:1px solid #222; border-top:0; }
        .spec-grid > div { min-height:104px; padding:6px; border-right:1px solid #222; }
        .spec-grid > div:last-child { border-right:0; }
        .spec-row { display:grid; grid-template-columns:105px 1fr; gap:6px; margin-bottom:4px; }
        .spec-row span { font-weight:700; }
        .product-image { display:flex; align-items:center; justify-content:center; height:215px; border:1px solid #222; border-top:0; }
        .product-image img { max-width:90%; max-height:195px; object-fit:contain; }
        .signature td { height:60px; text-align:center; vertical-align:top; font-weight:700; border-top:0; }
        @media print { body { background:#fff; } .toolbar { display:none; } .sheet { width:auto; min-height:auto; margin:0; padding:0; } }
    </style>
</head>
@php
    $order = (array) ($plan['order'] ?? []);
    $item = (array) ($plan['source_items'][0] ?? []);
    $metadata = array_merge((array) ($item['metadata'] ?? []), (array) ($order['metadata'] ?? []));
    $operations = (array) ($metadata['operations'] ?? []);
    $materials = collect($item['materials'] ?? [])->take(7)->values();
    $operation = function ($key) use ($operations) {
        foreach ($operations as $name => $value) {
            if (mb_strtoupper(str_replace('_', ' ', $name)) === mb_strtoupper(str_replace('_', ' ', $key))) return $value;
        }
        return '';
    };
@endphp
<body onload="setTimeout(function(){ window.print(); }, 250)">
<div class="toolbar"><button type="button" onclick="window.print()">In / lưu PDF</button></div>
<main class="sheet">
    <h1>LỆNH DỆT</h1>
    <table class="meta">
        <tr><td class="label">Khách hàng</td><td class="value">{{ $order['customer'] ?? '' }}</td><td class="label">Lệnh in</td><td class="value">{{ $order['production_order'] ?? '' }}</td></tr>
        <tr><td class="label">PO</td><td class="value">{{ $order['po_number'] ?? '' }}</td><td class="label">Mã hàng</td><td class="value">{{ $order['item_code'] ?? '' }}</td></tr>
        <tr><td class="label">Ngày ra lệnh</td><td>{{ !empty($order['order_date']) ? \Carbon\Carbon::parse($order['order_date'])->format('d/m/Y') : '' }}</td><td class="label">Mã design</td><td>{{ $order['design_code'] ?? '' }}</td></tr>
        <tr><td class="label">Ngày giao</td><td>{{ !empty($order['due_date']) ? \Carbon\Carbon::parse($order['due_date'])->format('d/m/Y') : '' }}</td><td class="label">Số lượng</td><td>{{ number_format((float) ($order['planned_quantity'] ?? 0), 0, ',', '.') }} {{ $order['unit'] ?? '' }}</td></tr>
    </table>
    <div class="spec-grid">
        <div>
            <div class="spec-row"><span>Tên label</span><b>{{ $metadata['label_name'] ?? $item['item_name'] ?? '' }}</b></div>
            <div class="spec-row"><span>Ủi keo</span><b>{{ $operation('UI KEO') }}</b></div>
            <div class="spec-row"><span>Loop</span><b>{{ $operation('LOOP') }}</b></div>
            <div class="spec-row"><span>Phần trên</span><b>{{ $operation('PHAN TREN') }}</b></div>
            <div class="spec-row"><span>Phần dưới</span><b>{{ $operation('PHAN DUOI') }}</b></div>
            <div class="spec-row"><span>Chiều dài</span><b>{{ $metadata['length'] ?? '' }}</b></div>
            <div class="spec-row"><span>Hoàn chỉnh</span><b>{{ $metadata['finished_size'] ?? '' }}</b></div>
            <div class="spec-row"><span>Mã số hộp</span><b>{{ $metadata['box_code'] ?? '' }}</b></div>
            <div class="spec-row"><span>SL/hộp</span><b>{{ $metadata['quantity_per_box'] ?? '' }}</b></div>
        </div>
        <div>
            <div class="spec-row"><span>Số pick</span><b>{{ $metadata['pick'] ?? '' }}</b></div>
            <div class="spec-row"><span>Mật độ</span><b>{{ $metadata['density'] ?? '' }}</b></div>
            <div class="spec-row"><span>Máy</span><b>{{ $metadata['machine'] ?? '' }}</b></div>
            <div class="spec-row"><span>Cuộn Muller</span><b>{{ $metadata['roll_count_small'] ?? '' }}</b></div>
            <div class="spec-row"><span>Cuộn Hi-Tex</span><b>{{ $metadata['roll_count_large'] ?? '' }}</b></div>
            <div class="spec-row"><span>Số dòng</span><b>{{ $metadata['row_count'] ?? '' }}</b></div>
            <div class="spec-row"><span>Tên file</span><b>{{ $metadata['file_name'] ?? '' }}</b></div>
        </div>
    </div>
    <table class="materials">
        <thead><tr><th style="width:7%">STT</th><th style="width:10%">Loại</th><th style="width:18%">Mã sợi</th><th style="width:10%">Số picks</th><th>Tên màu sợi</th><th style="width:12%">TL/1PCS (g)</th><th style="width:12%">T.L (g)</th></tr></thead>
        <tbody>
        @for($i = 0; $i < 7; $i++)
            @php $line = (array) ($materials[$i] ?? []); @endphp
            <tr><td class="center">{{ $i + 1 }}</td><td>{{ $line['type'] ?? '' }}</td><td><b>{{ $line['material_code'] ?? '' }}</b></td><td>{{ $line['pick_count'] ?? '' }}</td><td>{{ $line['material_name'] ?? '' }}</td><td class="number">{{ isset($line['consumption_per_unit']) ? number_format((float) $line['consumption_per_unit'], 4, ',', '.') : '' }}</td><td class="number">{{ isset($line['total_grams']) ? number_format((float) $line['total_grams'], 3, ',', '.') : '' }}</td></tr>
        @endfor
        </tbody>
    </table>
    <div class="product-image">@if(!empty($order['image_url']))<img src="{{ $order['image_url'] }}" alt="Hình ảnh {{ $order['item_code'] ?? '' }}">@else<span>HÌNH ẢNH</span>@endif</div>
    <table class="signature"><tr><td>DESIGNER</td><td>SẢN XUẤT</td><td>THỦ KHO</td></tr></table>
</main>
</body>
</html>
