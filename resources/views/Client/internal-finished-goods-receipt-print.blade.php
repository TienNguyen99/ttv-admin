<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $receipt->receipt_code }}</title>
    <style>
        @page { size: A4 portrait; margin: 8mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef1f5; color: #111; font-family: "Times New Roman", serif; }
        .toolbar { display: flex; justify-content: flex-end; gap: 8px; padding: 12px; }
        .toolbar button { padding: 8px 14px; border: 1px solid #9ca3af; border-radius: 5px; background: #fff; cursor: pointer; }
        .sheet { width: 210mm; min-height: 297mm; margin: 0 auto 20px; padding: 10mm 8mm; background: #fff; }
        .header { position: relative; min-height: 26mm; }
        .brand { position: absolute; top: 0; left: 4mm; width: 75mm; }
        .wordmark { display: inline-block; font-size: 14px; font-weight: 900; font-style: italic; letter-spacing: 1px; }
        .company { margin-top: 5px; font-size: 15px; font-weight: 700; }
        h1 { margin: 0; text-align: center; font-size: 22px; font-weight: 800; text-transform: uppercase; }
        .department { position: absolute; right: 22mm; top: 15mm; font-size: 14px; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 12px; }
        th, td { border: 1px solid #111; padding: 4px 3px; vertical-align: middle; }
        th { height: 12mm; text-align: center; font-style: italic; }
        tbody td { height: 10mm; }
        .center { text-align: center; }
        .right { text-align: right; }
        .signatures { position: relative; min-height: 40mm; padding-top: 5px; font-size: 14px; font-weight: 700; }
        .date { position: absolute; right: 22mm; top: 4px; }
        .signature-grid { display: grid; grid-template-columns: repeat(4, 1fr); padding-top: 8mm; text-align: center; }
        .signature-grid > div { min-height: 28mm; }
        .receipt-code { color: #555; font-family: Arial, sans-serif; font-size: 9px; text-align: right; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { width: auto; min-height: auto; margin: 0; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">In phiếu</button>
        <button type="button" onclick="window.close()">Đóng</button>
    </div>

    <main class="sheet">
        <header class="header">
            <div class="brand">
                <div class="wordmark">TAGTIME<sup>®</sup></div>
                <div class="company">Công ty Nhãn Thời Gian Việt Tiến</div>
            </div>
            <h1>Phiếu nhập kho</h1>
            <div class="department">BỘ PHẬN: KCS</div>
        </header>

        <table>
            <colgroup>
                <col style="width:5%">
                <col style="width:17%">
                <col style="width:15%">
                <col style="width:11%">
                <col style="width:10%">
                <col style="width:7%">
                <col style="width:10%">
                <col style="width:5%">
                <col style="width:14%">
                <col style="width:11%">
            </colgroup>
            <thead>
                <tr>
                    <th>Stt</th>
                    <th>Danh mục</th>
                    <th>Mã hàng</th>
                    <th>Item code</th>
                    <th>Màu sắc</th>
                    <th>Size</th>
                    <th>Số lượng</th>
                    <th>Đvt</th>
                    <th>Lệnh sản xuất</th>
                    <th>Ghi chú</th>
                </tr>
            </thead>
            <tbody>
                @for ($index = 0; $index < 8; $index++)
                    <tr>
                        <td class="center">{{ $index + 1 }}</td>
                        <td>{{ $index === 0 ? $receipt->ten_hh : '' }}</td>
                        <td>{{ $index === 0 ? $receipt->ma_sp : '' }}</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td class="right">{{ $index === 0 ? number_format($receipt->quantity, 3, ',', '.') : '' }}</td>
                        <td class="center">{{ $index === 0 ? $receipt->dvt : '' }}</td>
                        <td></td>
                        <td>{{ $index === 0 ? $receipt->note : '' }}</td>
                    </tr>
                @endfor
            </tbody>
        </table>

        <section class="signatures">
            <div class="date">Ngày {{ $receipt->receipt_date->format('d') }}/{{ $receipt->receipt_date->format('m') }}/{{ $receipt->receipt_date->format('Y') }}</div>
            <div class="signature-grid">
                <div>Người nhận</div>
                <div>Thủ kho</div>
                <div>Người nhập</div>
                <div>Giám Đốc</div>
            </div>
        </section>
        <div class="receipt-code">Số phiếu: {{ $receipt->receipt_code }}</div>
    </main>
</body>
</html>
