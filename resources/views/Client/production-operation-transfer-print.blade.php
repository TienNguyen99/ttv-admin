<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $activity->transfer_code }}</title>
    <style>
        * { box-sizing:border-box; }
        body { margin:0; color:#111; font:13px "Times New Roman",serif; background:#eef2f7; }
        .toolbar { display:flex; justify-content:flex-end; padding:12px; }
        .toolbar button { padding:9px 18px; border:0; border-radius:6px; color:#fff; background:#2563eb; font-weight:700; cursor:pointer; }
        .sheet { width:210mm; min-height:297mm; margin:0 auto 16px; padding:10mm 12mm; background:#fff; }
        .print-header { position:relative; min-height:27mm; padding-top:1mm; border-bottom:1px solid #111; }
        .brand { position:absolute; top:0; left:1mm; width:65mm; }
        .company-logo { display:block; width:17mm; height:auto; margin:0 0 1mm; object-fit:contain; }
        .company-name { font-size:12px; font-weight:700; white-space:nowrap; }
        .document-title { margin:4mm 52mm 0; text-align:center; font-size:21px; font-weight:800; text-transform:uppercase; }
        .document-side { position:absolute; top:2mm; right:1mm; width:51mm; text-align:right; line-height:1.55; }
        .department { font-size:12px; font-weight:700; text-transform:uppercase; }
        .document-code { margin-top:2mm; font:11px Arial,sans-serif; }
        .meta { display:grid; grid-template-columns:1fr 1fr; gap:7px 24px; margin-bottom:12px; }
        .document-meta { display:grid; grid-template-columns:1fr 1fr 1fr; gap:5px 14px; padding:3mm 0 2mm; }
        .route { padding:7px; border:1.5px solid #111; text-align:center; font-size:17px; font-weight:800; background:#f3f4f6; }
        table { width:100%; margin-top:12px; border-collapse:collapse; }
        th,td { padding:7px; border:1px solid #111827; text-align:left; }
        th { background:#f3f4f6; text-align:center; font-style:italic; }
        .number { text-align:right; }
        .signatures { display:grid; grid-template-columns:repeat(3,1fr); min-height:42mm; margin-top:24px; text-align:center; font-weight:700; }
        @page { size:A4 portrait; margin:0; }
        @media print { body { background:#fff; } .toolbar { display:none; } .sheet { margin:0; } }
    </style>
</head>
<body>
<div class="toolbar"><button type="button" onclick="window.print()">In phiếu</button></div>
<main class="sheet">
    <header class="print-header">
        <div class="brand">
            <img class="company-logo" src="https://i.ibb.co/fd36G56R/Untitled-removebg-preview.png" alt="TAGTIME">
            <div class="company-name">Công ty TNHH Nhãn Thời Gian Việt Tiến</div>
        </div>
        <h1 class="document-title">PHIẾU CHUYỂN CÔNG ĐOẠN</h1>
        <div class="document-side">
            <div class="department">Bộ phận: Sản xuất</div>
            <div class="document-code">Số phiếu: <strong>{{ $activity->transfer_code }}</strong></div>
        </div>
    </header>
    <div class="document-meta">
        <div><strong>Ngày:</strong> {{ optional($activity->activity_date)->format('d/m/Y') }}</div>
        <div><strong>Lệnh sản xuất:</strong> {{ $activity->production_order_code }}</div>
        <div><strong>Người giao:</strong> {{ $activity->operator_name ?: '-' }}</div>
    </div>
    <div class="route">{{ $activity->operation_name }} &rarr; {{ $activity->next_operation_name }}</div>
    <table>
        <thead><tr><th>STT</th><th>Mã hàng</th><th>Tên hàng</th><th>Size / màu</th><th class="number">Đạt</th><th class="number">Lỗi</th><th>ĐVT</th><th>Ghi chú</th></tr></thead>
        <tbody>
        @foreach($activity->lines as $line)
            <tr>
                <td>{{ $loop->iteration }}</td><td><strong>{{ $line->internal_item_code }}</strong></td>
                <td>{{ $line->item_name }}</td><td>{{ trim($line->size . ' ' . $line->color) ?: '-' }}</td>
                <td class="number">{{ number_format($line->good_quantity, 3, ',', '.') }}</td>
                <td class="number">{{ number_format($line->defect_quantity, 3, ',', '.') }}</td>
                <td>{{ $line->unit }}</td><td>{{ $line->note }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div class="signatures"><div>Người giao</div><div>Người nhận</div><div>Quản lý sản xuất</div></div>
</main>
</body>
</html>
