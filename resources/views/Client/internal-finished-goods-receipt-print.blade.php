<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>{{ $receipt->receipt_code }}</title>
    <style>
        @page { size: A5 landscape; margin: 12mm; }
        body { margin: 0; color: #111; font-family: Arial, sans-serif; font-size: 13px; }
        .sheet { max-width: 190mm; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; gap: 16px; }
        .company { font-size: 13px; font-weight: 700; }
        .code { text-align: right; font-size: 12px; }
        h1 { margin: 18px 0 4px; text-align: center; font-size: 22px; text-transform: uppercase; }
        .subtitle { margin-bottom: 18px; text-align: center; font-size: 12px; font-style: italic; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 20px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; border: 1px solid #111; vertical-align: top; }
        th { background: #f2f2f2; text-align: left; }
        .number { text-align: right; }
        .note { margin-top: 12px; }
        .signatures { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 24px; text-align: center; font-weight: 700; }
        .signatures span { display: block; margin-top: 5px; font-size: 11px; font-style: italic; font-weight: 400; }
        @media screen { body { padding: 18px; background: #f4f4f4; } .sheet { padding: 14mm; background: #fff; } }
    </style>
</head>
<body onload="window.print()">
    <main class="sheet">
        <div class="header">
            <div class="company">TTV - QUẢN LÝ KHO NỘI BỘ</div>
            <div class="code"><strong>Số phiếu:</strong> {{ $receipt->receipt_code }}<br><strong>Ngày:</strong> {{ $receipt->receipt_date->format('d/m/Y') }}</div>
        </div>
        <h1>Phiếu nhập thành phẩm</h1>
        <div class="subtitle">Phiếu nội bộ để kế toán nhập lại trên phần mềm công ty</div>
        <div class="meta">
            <div><strong>Kho nhận:</strong> {{ $receipt->ma_ko ?: '-' }}</div>
            <div><strong>Trạng thái:</strong> Chờ kế toán nhập phần mềm</div>
        </div>
        <table>
            <thead><tr><th style="width:42px">STT</th><th>Mã thành phẩm</th><th>Tên hàng</th><th style="width:70px">ĐVT</th><th style="width:110px">Số lượng</th></tr></thead>
            <tbody><tr><td>1</td><td>{{ $receipt->ma_sp }}</td><td>{{ $receipt->ten_hh }}</td><td>{{ $receipt->dvt }}</td><td class="number">{{ number_format($receipt->quantity, 3, ',', '.') }}</td></tr></tbody>
        </table>
        <div class="note"><strong>Ghi chú:</strong> {{ $receipt->note ?: 'Bổ sung phiếu nhập thành phẩm theo rà soát xuất kho chưa có nhập.' }}</div>
        <div class="signatures">
            <div>Người lập phiếu<span>Ký, ghi rõ họ tên</span></div>
            <div>Thủ kho<span>Ký, ghi rõ họ tên</span></div>
            <div>Kế toán<span>Ký, ghi rõ họ tên</span></div>
        </div>
    </main>
</body>
</html>
