<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $receipt->receipt_number }} - Phiếu hàng về PANEL</title>
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #111827; font-family: Arial, sans-serif; font-size: 11px; }
        .toolbar { display: flex; justify-content: flex-end; margin-bottom: 10px; }
        .toolbar button { padding: 8px 14px; border: 1px solid #1d4ed8; border-radius: 6px; background: #2563eb; color: #fff; font-weight: 700; cursor: pointer; }
        .header { position: relative; min-height: 72px; text-align: center; }
        .company { position: absolute; top: 0; left: 0; width: 190px; text-align: left; }
        .company strong { display: block; font-size: 12px; }
        h1 { margin: 10px 0 5px; font-size: 20px; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 5px 20px; margin: 12px 0 10px; }
        .meta div { min-width: 0; }
        .meta strong { display: inline-block; min-width: 92px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { padding: 6px 5px; border: 1px solid #111827; vertical-align: middle; overflow-wrap: anywhere; }
        th { background: #eaf4ff; font-size: 10px; text-align: center; text-transform: uppercase; }
        td.number { text-align: right; font-weight: 700; }
        .total td { background: #f8fafc; font-weight: 700; }
        .signatures { display: grid; grid-template-columns: repeat(3, 1fr); margin-top: 18px; text-align: center; }
        .signatures strong { display: block; margin-bottom: 54px; }
        .muted { color: #64748b; }
        @media print { .toolbar { display: none; } body { print-color-adjust: exact; -webkit-print-color-adjust: exact; } }
    </style>
</head>
<body>
<div class="toolbar"><button type="button" onclick="window.print()">In phiếu</button></div>
<header class="header">
    <div class="company"><strong>CÔNG TY TNHH NHÃN THỜI GIAN VIỆT TIẾN</strong><span class="muted">Bộ phận nhận PANEL</span></div>
    <h1>PHIẾU HÀNG VỀ PANEL</h1>
    <strong>{{ $receipt->receipt_number }}</strong>
</header>

<section class="meta">
    <div><strong>Ngày hàng về:</strong>{{ optional($receipt->received_date)->format('d/m/Y') }}</div>
    <div><strong>Nguồn:</strong>{{ $receipt->source_type === 'IMPORT' ? 'Import danh sách' : 'Nhập trực tiếp' }}</div>
    <div><strong>Số dòng:</strong>{{ number_format((int) $receipt->line_count, 0, ',', '.') }}</div>
    <div><strong>Tổng số lượng:</strong>{{ number_format((float) $receipt->total_quantity, 3, ',', '.') }}</div>
    @if($receipt->source_file_name)<div><strong>File nguồn:</strong>{{ $receipt->source_file_name }}</div>@endif
    @if($receipt->note)<div><strong>Ghi chú:</strong>{{ $receipt->note }}</div>@endif
</section>

<table>
    <colgroup>
        <col style="width: 5%"><col style="width: 15%"><col style="width: 15%"><col style="width: 13%">
        <col style="width: 18%"><col style="width: 12%"><col style="width: 10%"><col style="width: 12%">
    </colgroup>
    <thead><tr><th>STT</th><th>PS</th><th>Mã hàng</th><th>PANEL</th><th>Size / màu</th><th>SL đơn</th><th>SL về</th><th>Ghi chú</th></tr></thead>
    <tbody>
    @foreach($receipt->lines as $index => $line)
        <tr>
            <td style="text-align:center">{{ $index + 1 }}</td>
            <td>{{ $line->ps_number }}</td>
            <td>{{ $line->item_code ?: '-' }}</td>
            <td><strong>{{ $line->panel ?: '-' }}</strong></td>
            <td>{{ collect([optional($line->orderRow)->size, optional($line->orderRow)->fabric_color])->filter()->implode(' / ') ?: '-' }}</td>
            <td class="number">{{ number_format((float) optional($line->orderRow)->order_quantity, 3, ',', '.') }}</td>
            <td class="number">{{ number_format((float) $line->received_quantity, 3, ',', '.') }}</td>
            <td>{{ $line->note ?: '-' }}</td>
        </tr>
    @endforeach
        <tr class="total"><td colspan="6" style="text-align:right">TỔNG CỘNG</td><td class="number">{{ number_format((float) $receipt->total_quantity, 3, ',', '.') }}</td><td></td></tr>
    </tbody>
</table>

<footer class="signatures">
    <div><strong>Người giao</strong><span>Ngày ...../...../..........</span></div>
    <div><strong>Người nhận</strong><span>Ký, ghi rõ họ tên</span></div>
    <div><strong>Thủ kho</strong><span>Ký, ghi rõ họ tên</span></div>
</footer>
</body>
</html>
