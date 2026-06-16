<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $receipt->receipt_code }}</title>
    <style>
        @page { size: A4 portrait; margin: 6mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e5e7eb; color: #111; font-family: "Times New Roman", serif; }
        .toolbar { display: flex; justify-content: flex-end; gap: 8px; padding: 12px; }
        .toolbar button { padding: 8px 14px; border: 1px solid #9ca3af; border-radius: 4px; background: #fff; cursor: pointer; }
        .paper {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto 16px;
            padding: 6mm;
            background: #fff;
            page-break-after: always;
        }
        .paper:last-child { page-break-after: auto; }
        .receipt-copy {
            height: 139mm;
            overflow: hidden;
            padding: 2mm 1mm;
        }
        .cut-line {
            position: relative;
            height: 5mm;
            border-top: 1px dashed #555;
        }
        .cut-line span {
            position: absolute;
            top: -3mm;
            left: 50%;
            padding: 0 3mm;
            transform: translateX(-50%);
            background: #fff;
            color: #555;
            font-family: Arial, sans-serif;
            font-size: 8px;
        }
        .header { position: relative; height: 18mm; }
        .brand { position: absolute; top: 0; left: 2mm; width: 70mm; }
        .wordmark { font-size: 11px; font-weight: 900; font-style: italic; }
        .company { margin-top: 2px; font-size: 11px; font-weight: 700; }
        h1 { margin: 0; text-align: center; font-size: 18px; font-weight: 800; text-transform: uppercase; }
        .department { position: absolute; right: 15mm; top: 9mm; font-size: 11px; font-weight: 700; }
        .receipt-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 9px; }
        .receipt-table th,
        .receipt-table td {
            border: 1px solid #111;
            padding: 1px 2px;
            vertical-align: middle;
            overflow: visible;
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: break-word;
            line-height: 1.12;
        }
        .receipt-table th { height: 8mm; text-align: center; font-size: 9px; font-style: italic; font-weight: 700; }
        .receipt-table tbody td { min-height: 6.2mm; }
        .center { text-align: center; }
        .right { text-align: right; }
        .signatures { position: relative; height: 25mm; padding-top: 2px; font-size: 10px; font-weight: 700; }
        .date { position: absolute; right: 15mm; top: 2px; }
        .signature-grid { display: grid; grid-template-columns: repeat(4, 1fr); padding-top: 6mm; text-align: center; }
        .signature-grid > div { min-height: 16mm; }
        .receipt-code { margin-top: -3mm; color: #555; font-family: Arial, sans-serif; font-size: 7px; text-align: right; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .paper { width: auto; min-height: 0; margin: 0; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">In phiếu</button>
        <button type="button" onclick="window.close()">Đóng</button>
    </div>

    @php
        $pages = $receipt->lines->values()->chunk(10);
        if ($pages->isEmpty()) {
            $pages = collect([collect()]);
        }
        $formatQuantity = static function ($value) {
            return rtrim(rtrim(number_format((float) $value, 3, ',', '.'), '0'), ',');
        };
    @endphp

    @foreach ($pages as $pageIndex => $pageLines)
        <main class="paper">
            @for ($copy = 1; $copy <= 2; $copy++)
                <section class="receipt-copy">
                    <header class="header">
                        <div class="brand">
                            <div class="wordmark">TAGTIME<sup>®</sup></div>
                            <div class="company">Công ty Nhãn Thời Gian Việt Tiến</div>
                        </div>
                        <h1>Phiếu nhập kho</h1>
                        <div class="department">BỘ PHẬN: KCS</div>
                    </header>

                    <table class="receipt-table">
                        <colgroup>
                            <col style="width:4%">
                            <col style="width:15%">
                            <col style="width:14%">
                            <col style="width:14%">
                            <col style="width:10%">
                            <col style="width:7%">
                            <col style="width:9%">
                            <col style="width:5%">
                            <col style="width:13%">
                            <col style="width:9%">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Stt</th>
                                <th>Danh mục</th>
                                <th>Mã nội bộ</th>
                                <th>Mã kế toán</th>
                                <th>Màu sắc</th>
                                <th>Size</th>
                                <th>Số lượng</th>
                                <th>Đvt</th>
                                <th>Lệnh sản xuất</th>
                                <th>Ghi chú</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($pageLines as $index => $line)
                                <tr>
                                    <td class="center">{{ $index + 1 }}</td>
                                    <td>{{ $line->ten_hh }}</td>
                                    <td>{{ $line->internal_item_code }}</td>
                                    <td>{{ $line->ma_hh }}</td>
                                    <td>{{ $line->color }}</td>
                                    <td class="center">{{ $line->size }}</td>
                                    <td class="right">{{ $formatQuantity($line->quantity) }}</td>
                                    <td class="center">{{ $line->dvt }}</td>
                                    <td>{{ $line->note }}</td>
                                    <td>{{ $receipt->note }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="center">Chưa có dữ liệu</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <section class="signatures">
                        <div class="date">Ngày {{ optional($receipt->receipt_date)->format('d') }}/{{ optional($receipt->receipt_date)->format('m') }}/{{ optional($receipt->receipt_date)->format('Y') }}</div>
                        <div class="signature-grid">
                            <div>Người nhận</div>
                            <div>Thủ kho</div>
                            <div>Người nhập</div>
                            <div>Giám Đốc</div>
                        </div>
                    </section>
                    <div class="receipt-code">
                        Số phiếu: {{ $receipt->receipt_code }}
                        @if ($pages->count() > 1)
                            · Trang {{ $pageIndex + 1 }}/{{ $pages->count() }}
                        @endif
                        · Liên {{ $copy }}
                    </div>
                </section>

                @if ($copy === 1)
                    <div class="cut-line"><span>CẮT NGANG</span></div>
                @endif
            @endfor
        </main>
    @endforeach
</body>
</html>
