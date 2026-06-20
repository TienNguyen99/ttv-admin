<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Cảnh báo kho nội bộ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/warehouse-wms.css') }}" rel="stylesheet">
    <style>
        .quality-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; }
        .quality-card { border: 1px solid var(--wms-line); border-radius: 7px; background: #fff; padding: 14px; }
        .quality-card__label { color: #64748b; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .quality-card__value { color: var(--wms-ink); font-size: 28px; font-weight: 850; line-height: 1.1; }
        .quality-section { border: 1px solid var(--wms-line); border-radius: 7px; background: #fff; margin-top: 14px; overflow: hidden; }
        .quality-section__head { display: flex; justify-content: space-between; align-items: center; padding: 12px 14px; border-bottom: 1px solid var(--wms-line); }
        .quality-section__title { margin: 0; font-size: 15px; font-weight: 800; color: var(--wms-ink); }
        .quality-section__body { max-height: 320px; overflow: auto; }
        .quality-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .quality-table th { position: sticky; top: 0; background: var(--wms-navy); color: #fff; padding: 8px; white-space: nowrap; }
        .quality-table td { border-top: 1px solid #e5ebf2; padding: 8px; vertical-align: top; }
        .quality-empty { padding: 18px; color: #64748b; text-align: center; }
        .quality-code { font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-weight: 700; }
        @media (max-width: 1000px) { .quality-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    </style>
</head>
<body>
    @include('layouts.partials.sidebar')

    <header class="wms-topbar">
        <h1 class="wms-topbar__title">WMS May Mặc</h1>
        <div class="wms-global-search">
            <i data-lucide="search"></i>
            <input id="topKeyword" aria-label="Tìm cảnh báo" placeholder="Tìm mã nội bộ, mã kế toán hoặc vị trí...">
        </div>
        <div class="wms-topbar__actions">
            <a id="exportStockBtn" class="wms-btn" href="#"><i data-lucide="download"></i> Xuất CSV tồn</a>
            <button id="reloadBtn" type="button" class="wms-btn wms-btn--primary"><i data-lucide="refresh-cw"></i> Tải lại</button>
        </div>
    </header>

    <main class="wms-page">
        <div class="wms-heading">
            <div>
                <h1>Cảnh báo kho nội bộ</h1>
                <p>Kiểm tra lỗi dữ liệu trước khi đối chiếu TSoft hoặc in phiếu. Chỉ đọc dữ liệu nội bộ.</p>
            </div>
            <div class="wms-actions">
                <input id="qualityMonth" type="month" class="form-control" value="{{ now()->format('Y-m') }}" style="width:160px">
            </div>
        </div>

        <section class="quality-grid">
            <article class="quality-card"><div class="quality-card__label">Âm tồn</div><div id="negativeCount" class="quality-card__value">0</div></article>
            <article class="quality-card"><div class="quality-card__label">Chưa xếp vị trí</div><div id="unassignedCount" class="quality-card__value">0</div></article>
            <article class="quality-card"><div class="quality-card__label">Thiếu danh mục</div><div id="catalogCount" class="quality-card__value">0</div></article>
            <article class="quality-card"><div class="quality-card__label">Nhiều vị trí</div><div id="multiLocationCount" class="quality-card__value">0</div></article>
            <article class="quality-card"><div class="quality-card__label">Phiếu chưa có vị trí</div><div id="receiptLocationCount" class="quality-card__value">0</div></article>
        </section>

        <div id="qualitySections"></div>
    </main>

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script>
        const monthEl = document.getElementById('qualityMonth');
        const sectionsEl = document.getElementById('qualitySections');
        const topKeywordEl = document.getElementById('topKeyword');
        const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
        const num = value => Number(value || 0).toLocaleString('vi-VN', { maximumFractionDigits: 3 });
        let currentData = null;

        function stockRowsTable(rows) {
            if (!rows.length) return '<div class="quality-empty">Không có lỗi trong nhóm này.</div>';
            return `<div class="quality-section__body"><table class="quality-table">
                <thead><tr><th>Vị trí</th><th>Mã nội bộ</th><th>Mã kế toán</th><th>Size</th><th>Màu</th><th>Side</th><th class="text-end">SL</th></tr></thead>
                <tbody>${rows.map(row => `<tr>
                    <td class="quality-code">${esc(row.location_code)}</td>
                    <td class="quality-code">${esc(row.internal_item_code)}</td>
                    <td class="quality-code">${esc(row.ma_sp)}</td>
                    <td>${esc(row.size)}</td>
                    <td>${esc(row.color)}</td>
                    <td>${esc(row.side)}</td>
                    <td class="text-end">${num(row.quantity)}</td>
                </tr>`).join('')}</tbody>
            </table></div>`;
        }

        function multiLocationTable(rows) {
            if (!rows.length) return '<div class="quality-empty">Không có mã bị tách nhiều vị trí.</div>';
            return `<div class="quality-section__body"><table class="quality-table">
                <thead><tr><th>Mã nội bộ</th><th>Mã kế toán</th><th>Size</th><th>Màu</th><th>Side</th><th>Vị trí</th><th class="text-end">SL</th></tr></thead>
                <tbody>${rows.map(row => `<tr>
                    <td class="quality-code">${esc(row.internal_item_code)}</td>
                    <td class="quality-code">${esc(row.ma_sp)}</td>
                    <td>${esc(row.size)}</td>
                    <td>${esc(row.color)}</td>
                    <td>${esc(row.side)}</td>
                    <td>${(row.locations || []).map(esc).join('<br>')}</td>
                    <td class="text-end">${num(row.quantity)}</td>
                </tr>`).join('')}</tbody>
            </table></div>`;
        }

        function receiptTable(rows) {
            if (!rows.length) return '<div class="quality-empty">Không có phiếu nhập thiếu vị trí.</div>';
            return `<div class="quality-section__body"><table class="quality-table">
                <thead><tr><th>Phiếu</th><th>Ngày</th><th>Vị trí</th><th class="text-end">Dòng</th><th class="text-end">SL</th><th></th></tr></thead>
                <tbody>${rows.map(row => `<tr>
                    <td class="quality-code">${esc(row.receipt_code)}</td>
                    <td>${esc(row.receipt_date)}</td>
                    <td class="quality-code">${esc(row.location_code)}</td>
                    <td class="text-end">${num(row.lines_count)}</td>
                    <td class="text-end">${num(row.quantity)}</td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" target="_blank" href="${esc(row.print_url)}">In</a></td>
                </tr>`).join('')}</tbody>
            </table></div>`;
        }

        function section(title, count, body) {
            return `<section class="quality-section">
                <div class="quality-section__head"><h2 class="quality-section__title">${esc(title)}</h2><span class="badge text-bg-light">${num(count)} dòng</span></div>
                ${body}
            </section>`;
        }

        function filterRows(rows) {
            const keyword = topKeywordEl.value.trim().toLowerCase();
            if (!keyword) return rows;
            return rows.filter(row => JSON.stringify(row).toLowerCase().includes(keyword));
        }

        function render() {
            if (!currentData) return;
            const data = currentData.data || {};
            document.getElementById('negativeCount').textContent = num(currentData.summary?.negative_stock);
            document.getElementById('unassignedCount').textContent = num(currentData.summary?.unassigned_stock);
            document.getElementById('catalogCount').textContent = num(currentData.summary?.missing_catalog);
            document.getElementById('multiLocationCount').textContent = num(currentData.summary?.multi_location);
            document.getElementById('receiptLocationCount').textContent = num(currentData.summary?.receipt_no_location);

            const negative = filterRows(data.negative_stock || []);
            const unassigned = filterRows(data.unassigned_stock || []);
            const missing = filterRows(data.missing_catalog || []);
            const multi = filterRows(data.multi_location || []);
            const receipts = filterRows(data.receipt_no_location || []);

            sectionsEl.innerHTML =
                section('Tồn âm cần kiểm tra phiếu nhập/xuất', negative.length, stockRowsTable(negative)) +
                section('Tồn chưa xếp vị trí', unassigned.length, stockRowsTable(unassigned)) +
                section('Mã nội bộ chưa có trong danh mục', missing.length, stockRowsTable(missing)) +
                section('Cùng mã/size/màu/mặt đang nằm nhiều vị trí', multi.length, multiLocationTable(multi)) +
                section('Phiếu nhập thành phẩm chưa có vị trí rõ', receipts.length, receiptTable(receipts));
        }

        function loadQuality() {
            sectionsEl.innerHTML = '<section class="quality-section"><div class="quality-empty">Đang tải dữ liệu...</div></section>';
            document.getElementById('exportStockBtn').href = `/api/ton-kho-noi-bo/export?month=${encodeURIComponent(monthEl.value)}`;
            fetch(`/api/canh-bao-kho?month=${encodeURIComponent(monthEl.value)}`)
                .then(response => response.json())
                .then(result => {
                    currentData = result;
                    render();
                    if (window.lucide) window.lucide.createIcons();
                })
                .catch(error => {
                    sectionsEl.innerHTML = `<section class="quality-section"><div class="quality-empty text-danger">${esc(error.message)}</div></section>`;
                });
        }

        document.getElementById('reloadBtn').addEventListener('click', loadQuality);
        monthEl.addEventListener('change', loadQuality);
        topKeywordEl.addEventListener('input', render);
        loadQuality();
        if (window.lucide) window.lucide.createIcons();
    </script>
</body>
</html>
