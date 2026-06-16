<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $issue->issue_code }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f3f4f6; color: #111; font-family: Arial, sans-serif; font-size: 13px; }
        .toolbar { display: flex; justify-content: flex-end; gap: 8px; padding: 12px; }
        .btn { border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; padding: 8px 12px; cursor: pointer; }
        .sheet { width: 210mm; min-height: 297mm; margin: 0 auto 24px; background: #fff; padding: 16mm; }
        .top { display: flex; justify-content: space-between; gap: 20px; }
        .company { font-weight: 700; text-transform: uppercase; }
        .code-box { text-align: right; line-height: 1.6; }
        h1 { margin: 18px 0 4px; text-align: center; font-size: 22px; text-transform: uppercase; }
        .subtitle { text-align: center; margin-bottom: 18px; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 24px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #111; padding: 6px 7px; vertical-align: middle; }
        th { text-align: center; }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
        .signatures { margin-top: 28px; table-layout: fixed; }
        .signature { height: 92px; text-align: center; vertical-align: top; font-weight: 700; }
        .signature span { font-weight: 400; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { width: auto; min-height: auto; margin: 0; padding: 10mm; }
        }
    </style>
</head>

<body>
    @php
        $isProductionIssue = strpos((string) $issue->issue_code, 'PXBTP-') === 0
            || trim((string) $issue->purpose) === 'Xuất BTP đi sản xuất';
        $isCustomerFinishedGoodsIssue = (string) $issue->issue_type === 'customer'
            || strpos((string) $issue->issue_code, 'PXTP-') === 0
            || trim((string) $issue->purpose) === 'Xuất thành phẩm cho khách hàng';
        $documentTitle = $isCustomerFinishedGoodsIssue
            ? 'Phiếu Xuất Kho Thành Phẩm'
            : ($isProductionIssue ? 'Phiếu Xuất Bán Thành Phẩm' : 'Phiếu Xuất Vật Tư Nội Bộ');
        $documentNote = $isCustomerFinishedGoodsIssue
            ? 'Phiếu xuất thành phẩm cho khách hàng, dùng để trừ tồn kho nội bộ.'
            : ($isProductionIssue ? 'Phiếu giao bán thành phẩm cho bộ phận sản xuất.' : 'Phiếu nội bộ, dùng để thủ kho xuất vật tư và bàn giao chứng từ.');
        $materialColumnTitle = $isCustomerFinishedGoodsIssue ? 'Mã TP' : 'Mã vật tư';
        $nameColumnTitle = $isCustomerFinishedGoodsIssue ? 'Tên hàng' : 'Tên vật tư';
        $formatQuantity = static function ($value) {
            return rtrim(rtrim(number_format((float) $value, 3, ',', '.'), '0'), ',');
        };
    @endphp
    <div class="toolbar">
        <button class="btn" onclick="window.print()">In phiếu</button>
        <button class="btn" onclick="window.close()">Đóng</button>
    </div>

    <main class="sheet">
        <div class="top">
            <div>
                <div class="company">Công ty TNHH Nhãn Thời Gian Việt Tiến</div>
                <div>{{ $documentNote }}</div>
            </div>
            <div class="code-box">
                <div>Số phiếu: <strong>{{ $issue->issue_code }}</strong></div>
                <div>Ngày tạo: {{ optional($issue->created_at)->format('d/m/Y H:i') }}</div>
            </div>
        </div>

        <h1>{{ $documentTitle }}</h1>
        <div class="subtitle">Ngày {{ optional($issue->issue_date)->format('d/m/Y') }}</div>

        <section class="meta">
            <div><strong>Kho xuất:</strong> {{ $issue->warehouse_code }}</div>
            <div><strong>Người nhận:</strong> {{ $issue->receiver_name }}</div>
            <div><strong>Bộ phận:</strong> {{ $issue->department }}</div>
            <div><strong>Lệnh/Số việc:</strong> {{ $issue->production_order }}</div>
            <div style="grid-column: 1 / -1;"><strong>Mục đích:</strong> {{ $issue->purpose }}</div>
            <div style="grid-column: 1 / -1;"><strong>Ghi chú:</strong> {{ $issue->note }}</div>
        </section>

        <table>
            <thead>
                <tr>
                    <th style="width: 42px;">STT</th>
                    <th style="width: 82px;">Lệnh SX</th>
                    <th style="width: 110px;">{{ $materialColumnTitle }}</th>
                    <th>{{ $nameColumnTitle }}</th>
                    <th style="width: 58px;">ĐVT</th>
                    <th style="width: 70px;">SL lệnh</th>
                    <th style="width: 76px;">Thực xuất</th>
                    <th style="width: 82px;">Vị trí</th>
                    <th style="width: 100px;">Mã nội bộ / Size</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($issue->lines as $index => $line)
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td>{{ $line->production_order }}</td>
                        <td>{{ $line->ma_hh }}</td>
                        <td>{{ $line->ten_hh }}</td>
                        <td class="text-center">{{ $line->dvt }}</td>
                        <td class="text-end">{{ $line->ordered_quantity !== null ? $formatQuantity($line->ordered_quantity) : '' }}</td>
                        <td class="text-end">{{ $formatQuantity($line->quantity) }}</td>
                        <td>{{ $line->location_code }}</td>
                        <td>{{ $line->internal_item_code }}{{ $line->size ? ' / ' . $line->size : '' }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="6" class="text-end"><strong>Tổng cộng</strong></td>
                    <td class="text-end"><strong>{{ $formatQuantity($issue->lines->sum('quantity')) }}</strong></td>
                    <td colspan="2"></td>
                </tr>
            </tbody>
        </table>

        <table class="signatures">
            <tr>
                <td class="signature">Người lập phiếu<br><span>(Ký, ghi rõ họ tên)</span></td>
                <td class="signature">Người nhận<br><span>(Ký, ghi rõ họ tên)</span></td>
                <td class="signature">Thủ kho<br><span>(Ký, ghi rõ họ tên)</span></td>
                <td class="signature">Kế toán<br><span>(Ký, ghi rõ họ tên)</span></td>
            </tr>
        </table>
    </main>
</body>
</html>
