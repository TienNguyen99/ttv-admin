<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tổng quan kho nội bộ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/warehouse-wms.css') }}" rel="stylesheet">
    <style>
        .wms-chart-grid { display: grid; grid-template-columns: 1.1fr .9fr; gap: 14px; margin-bottom: 16px; }
        .wms-chart-card { border: 1px solid var(--wms-line); border-radius: 7px; background: #fff; overflow: hidden; }
        .wms-chart-card__head { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 14px; border-bottom: 1px solid var(--wms-line); }
        .wms-chart-card__title { margin: 0; color: var(--wms-ink); font-size: 15px; font-weight: 850; }
        .wms-chart-card__meta { color: #64748b; font-size: 12px; white-space: nowrap; }
        .wms-chart-card__body { padding: 14px; }
        .wms-bar-row { display: grid; grid-template-columns: minmax(120px, 170px) minmax(0, 1fr) 90px; gap: 10px; align-items: center; margin: 10px 0; font-size: 13px; }
        .wms-bar-label { color: #0f172a; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .wms-bar-track { height: 12px; border-radius: 999px; background: #e8eef6; overflow: hidden; }
        .wms-bar-fill { height: 100%; min-width: 2px; border-radius: inherit; background: #0f5fa8; }
        .wms-bar-fill--good { background: #15803d; }
        .wms-bar-fill--warn { background: #d97706; }
        .wms-bar-fill--bad { background: #b91c1c; }
        .wms-bar-value { color: #0f172a; font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-weight: 800; text-align: right; }
        .wms-segment { display: flex; width: 100%; height: 18px; border-radius: 999px; background: #e8eef6; overflow: hidden; }
        .wms-segment span { min-width: 2px; }
        .wms-chart-legend { display: flex; flex-wrap: wrap; gap: 8px 14px; margin-top: 12px; color: #475569; font-size: 12px; }
        .wms-chart-legend i { display: inline-block; width: 9px; height: 9px; border-radius: 999px; margin-right: 5px; }
        .wms-flow-pair { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .wms-flow-box { border: 1px solid #dbe2ea; border-radius: 7px; padding: 12px; background: #f8fafc; }
        .wms-flow-box__label { color: #64748b; font-size: 12px; font-weight: 800; text-transform: uppercase; }
        .wms-flow-box__value { color: #0f172a; font-size: 24px; font-weight: 850; line-height: 1.15; }
        .wms-flow-box__sub { color: #64748b; font-size: 12px; }
        @media (max-width: 1100px) { .wms-chart-grid { grid-template-columns: 1fr; } }
        @media (max-width: 640px) {
            .wms-bar-row { grid-template-columns: 1fr; gap: 5px; }
            .wms-bar-value { text-align: left; }
            .wms-flow-pair { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    @include('layouts.partials.sidebar')

    <header class="wms-topbar">
        <h1 class="wms-topbar__title">WMS May Mặc</h1>
        <form class="wms-global-search" action="{{ url('/client/ton-kho-noi-bo') }}" method="get">
            <i data-lucide="search"></i>
            <input name="keyword" aria-label="Tìm mã hàng trong kho" placeholder="Tìm mã nội bộ, mã kế toán hoặc vị trí...">
        </form>
        <div class="wms-topbar__actions">
            <a class="wms-btn" href="{{ url('/client/kiem-ton-kho') }}"><i data-lucide="scan-line"></i> Quét kho</a>
        </div>
    </header>

    <main class="wms-page">
        <div class="wms-heading">
            <div>
                <h1>Tổng quan kho nội bộ</h1>
                <p>Dữ liệu vận hành nội bộ. TSoft chỉ được sử dụng để đọc và đối chiếu.</p>
            </div>
            <div class="wms-actions">
                <a class="wms-btn" href="{{ url('/client/doi-chieu-ton') }}"><i data-lucide="file-spreadsheet"></i> Đối chiếu TSoft</a>
                <a class="wms-btn wms-btn--primary" href="{{ url('/client/kiem-ton-kho?view=entry') }}"><i data-lucide="file-plus-2"></i> Tạo phiếu nhập</a>
            </div>
        </div>

        <section class="wms-kpis" aria-label="Chỉ số kho">
            <article class="wms-kpi">
                <div class="wms-kpi__icon"><i data-lucide="boxes"></i></div>
                <div><div class="wms-kpi__label">Mã đang có phát sinh</div><div id="dashboardItems" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Trong tháng hiện tại</div></div>
            </article>
            <article class="wms-kpi wms-kpi--danger">
                <div class="wms-kpi__icon"><i data-lucide="map-pin-off"></i></div>
                <div><div class="wms-kpi__label">Chưa xếp vị trí</div><div id="dashboardUnassigned" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Cần bố trí lên kệ</div></div>
            </article>
            <article class="wms-kpi">
                <div class="wms-kpi__icon"><i data-lucide="package-plus"></i></div>
                <div><div class="wms-kpi__label">Nhập kho hôm nay</div><div id="dashboardReceipts" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Số lượng đã ghi nhận</div></div>
            </article>
            <article class="wms-kpi">
                <div class="wms-kpi__icon"><i data-lucide="package-minus"></i></div>
                <div><div class="wms-kpi__label">Xuất kho hôm nay</div><div id="dashboardIssues" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Số lượng đã xuất</div></div>
            </article>
        </section>

        <section class="wms-chart-grid" aria-label="Biểu đồ kho">
            <article class="wms-chart-card">
                <div class="wms-chart-card__head">
                    <h2 class="wms-chart-card__title">Tình trạng tồn kho</h2>
                    <span id="stockStatusMeta" class="wms-chart-card__meta">Đang tải...</span>
                </div>
                <div class="wms-chart-card__body">
                    <div id="stockStatusSegment" class="wms-segment" aria-label="Tình trạng tồn kho"></div>
                    <div id="stockStatusLegend" class="wms-chart-legend"></div>
                </div>
            </article>

            <article class="wms-chart-card">
                <div class="wms-chart-card__head">
                    <h2 class="wms-chart-card__title">Nhập / xuất hôm nay</h2>
                    <span id="todayFlowMeta" class="wms-chart-card__meta">{{ now()->format('d/m/Y') }}</span>
                </div>
                <div class="wms-chart-card__body">
                    <div class="wms-flow-pair">
                        <div class="wms-flow-box">
                            <div class="wms-flow-box__label">Nhập TP</div>
                            <div id="todayReceiptQty" class="wms-flow-box__value">0</div>
                            <div id="todayReceiptDocs" class="wms-flow-box__sub">0 phiếu</div>
                        </div>
                        <div class="wms-flow-box">
                            <div class="wms-flow-box__label">Xuất kho</div>
                            <div id="todayIssueQty" class="wms-flow-box__value">0</div>
                            <div id="todayIssueDocs" class="wms-flow-box__sub">0 phiếu</div>
                        </div>
                    </div>
                    <div id="todayFlowBars" class="mt-2"></div>
                </div>
            </article>

            <article class="wms-chart-card">
                <div class="wms-chart-card__head">
                    <h2 class="wms-chart-card__title">Top vị trí đang chứa hàng</h2>
                    <a class="wms-link" href="{{ url('/client/kiem-ton-kho?view=overview') }}">Xem vị trí</a>
                </div>
                <div id="locationChart" class="wms-chart-card__body"></div>
            </article>

            <article class="wms-chart-card">
                <div class="wms-chart-card__head">
                    <h2 class="wms-chart-card__title">BTP đang sản xuất</h2>
                    <a class="wms-link" href="{{ url('/client/theo-doi-san-xuat') }}">Xem chi tiết</a>
                </div>
                <div id="wipChart" class="wms-chart-card__body"></div>
            </article>
        </section>

        <div class="wms-dashboard-grid">
            <section class="wms-panel">
                <div class="wms-panel__header">
                    <h2>Tồn kho cần chú ý</h2>
                    <a class="wms-link" href="{{ url('/client/ton-kho-noi-bo') }}">Xem toàn bộ</a>
                </div>
                <div class="wms-table-wrap">
                    <table class="wms-table">
                        <thead><tr><th>Mã kế toán</th><th>Mã nội bộ</th><th>Vị trí</th><th>Size / Màu</th><th class="text-end">Tồn</th><th>Trạng thái</th></tr></thead>
                        <tbody id="dashboardStockRows"><tr><td colspan="6" class="wms-loading">Đang tải dữ liệu...</td></tr></tbody>
                    </table>
                </div>
            </section>

            <section class="wms-panel">
                <div class="wms-panel__header">
                    <h2>Hoạt động gần đây</h2>
                    <a class="wms-link" href="{{ url('/client/xuat-vat-tu-noi-bo') }}">Xem phiếu kho</a>
                </div>
                <div id="dashboardActivity" class="wms-panel__body wms-activity">
                    <div class="wms-loading">Đang tải hoạt động...</div>
                </div>
            </section>
        </div>

        <section class="wms-panel">
            <div class="wms-panel__header">
                <h2>Thao tác nhanh</h2>
            </div>
            <div class="wms-panel__body wms-actions">
                <a class="wms-btn" href="{{ url('/client/ton-kho-noi-bo') }}"><i data-lucide="warehouse"></i> Kiểm tra tồn</a>
                <a class="wms-btn" href="{{ url('/client/kiem-ton-kho') }}"><i data-lucide="map"></i> Sơ đồ và vị trí</a>
                <a class="wms-btn" href="{{ url('/client/theo-doi-san-xuat') }}"><i data-lucide="workflow"></i> Hàng đang sản xuất</a>
                <a class="wms-btn" href="{{ url('/client/kiem-ton-kho?view=entry') }}"><i data-lucide="package-plus"></i> Nhập thành phẩm</a>
                <a class="wms-btn" href="{{ url('/client/xuat-vat-tu-noi-bo?type=production') }}"><i data-lucide="factory"></i> Xuất BTP sản xuất</a>
                <a class="wms-btn" href="{{ url('/client/canh-bao-kho') }}"><i data-lucide="triangle-alert"></i> Cảnh báo kho</a>
            </div>
        </section>
    </main>

    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
    <script>
        const dashboardNum = value => Number(value || 0).toLocaleString('vi-VN', {maximumFractionDigits: 3});
        const dashboardEsc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
        const today = '{{ now()->format('Y-m-d') }}';
        const month = '{{ now()->format('Y-m') }}';

        function dashboardJson(response) {
            if (!response.ok) throw new Error('Không tải được dữ liệu kho');
            return response.json();
        }

        function pct(value, total) {
            return total > 0 ? Math.max(1, Math.round((Number(value || 0) / total) * 100)) : 0;
        }

        function renderBarRows(targetId, rows, options = {}) {
            const max = Math.max(...rows.map(row => Number(row.value || 0)), 0);
            document.getElementById(targetId).innerHTML = rows.length ? rows.map(row => `
                <div class="wms-bar-row">
                    <div class="wms-bar-label" title="${dashboardEsc(row.label)}">${dashboardEsc(row.label)}</div>
                    <div class="wms-bar-track"><div class="wms-bar-fill ${row.className || ''}" style="width:${max ? Math.max(2, Math.round(Number(row.value || 0) / max * 100)) : 0}%"></div></div>
                    <div class="wms-bar-value">${options.rawValue ? dashboardEsc(row.value) : dashboardNum(row.value)}</div>
                </div>
            `).join('') : '<div class="wms-empty">Chưa có dữ liệu biểu đồ.</div>';
        }

        function renderStockStatusChart(stockRows, quality) {
            const positiveAssigned = stockRows.filter(row => Number(row.total_quantity) > 0 && row.location_code && row.location_code !== 'CHUA-XEP').length;
            const unassigned = Number(quality.summary?.unassigned_stock || 0);
            const negative = Number(quality.summary?.negative_stock || 0);
            const missingCatalog = Number(quality.summary?.missing_catalog || 0);
            const total = Math.max(positiveAssigned + unassigned + negative + missingCatalog, 1);
            const segments = [
                { label: 'Có vị trí', value: positiveAssigned, color: '#15803d' },
                { label: 'Chưa xếp', value: unassigned, color: '#d97706' },
                { label: 'Âm tồn', value: negative, color: '#b91c1c' },
                { label: 'Thiếu danh mục', value: missingCatalog, color: '#0f5fa8' },
            ];
            document.getElementById('stockStatusMeta').textContent = `${dashboardNum(stockRows.length)} dòng tồn`;
            document.getElementById('stockStatusSegment').innerHTML = segments
                .filter(item => item.value > 0)
                .map(item => `<span style="width:${pct(item.value, total)}%; background:${item.color}" title="${dashboardEsc(item.label)}: ${dashboardNum(item.value)}"></span>`)
                .join('') || '<span style="width:100%; background:#e8eef6"></span>';
            document.getElementById('stockStatusLegend').innerHTML = segments.map(item =>
                `<span><i style="background:${item.color}"></i>${dashboardEsc(item.label)}: <strong>${dashboardNum(item.value)}</strong></span>`
            ).join('');
        }

        function renderTodayFlow(receipts, issues) {
            const receiptQty = Number(receipts.summary?.total_quantity || 0);
            const issueQty = Number(issues.summary?.total_quantity || 0);
            document.getElementById('todayReceiptQty').textContent = dashboardNum(receiptQty);
            document.getElementById('todayIssueQty').textContent = dashboardNum(issueQty);
            document.getElementById('todayReceiptDocs').textContent = `${dashboardNum(receipts.summary?.receipt_count || 0)} phiếu`;
            document.getElementById('todayIssueDocs').textContent = `${dashboardNum(issues.summary?.total_issues || 0)} phiếu`;
            renderBarRows('todayFlowBars', [
                { label: 'Nhập thành phẩm', value: receiptQty, className: 'wms-bar-fill--good' },
                { label: 'Xuất kho', value: issueQty, className: 'wms-bar-fill--warn' },
            ]);
        }

        function renderLocationChart(stockRows) {
            const groups = new Map();
            stockRows.forEach(row => {
                const location = row.location_code || 'CHUA-XEP';
                const quantity = Math.max(0, Number(row.total_quantity || 0));
                if (quantity <= 0) return;
                groups.set(location, (groups.get(location) || 0) + quantity);
            });
            const rows = Array.from(groups.entries())
                .map(([label, value]) => ({ label, value, className: label === 'CHUA-XEP' ? 'wms-bar-fill--warn' : '' }))
                .sort((a, b) => b.value - a.value)
                .slice(0, 6);
            renderBarRows('locationChart', rows);
        }

        function renderWipChart(wip) {
            const summary = wip.summary || {};
            const rows = [
                { label: 'Đã xuất BTP', value: summary.issued_quantity || 0, className: 'wms-bar-fill--warn' },
                { label: 'Đã nhập lại', value: summary.returned_quantity || 0, className: 'wms-bar-fill--good' },
                { label: 'Còn ngoài sản xuất', value: summary.outstanding_quantity || 0, className: 'wms-bar-fill--bad' },
            ];
            const hasValue = rows.some(row => Number(row.value || 0) > 0);
            document.getElementById('wipChart').innerHTML = hasValue
                ? ''
                : '<div class="wms-empty">Chưa có BTP đang sản xuất.</div>';
            if (hasValue) renderBarRows('wipChart', rows);
        }

        Promise.all([
            fetch('/api/ton-kho-noi-bo?month=' + month).then(dashboardJson),
            fetch('/api/kiem-ton-kho/phieu-nhap-tp?receipt_date=' + today + '&limit=50').then(dashboardJson),
            fetch('/api/xuat-vat-tu-noi-bo?from_date=' + today + '&to_date=' + today).then(dashboardJson),
            fetch('/api/canh-bao-kho?month=' + month).then(dashboardJson),
            fetch('/api/theo-doi-san-xuat').then(dashboardJson)
        ]).then(([stock, receipts, issues, quality, wip]) => {
            const stockRows = stock.data || [];
            const receiptRows = receipts.data || [];
            const issueRows = issues.data || [];
            const unassigned = stockRows.filter(row => !row.location_code || row.location_code === 'CHUA-XEP');

            document.getElementById('dashboardItems').textContent = dashboardNum(stock.summary?.item_count);
            document.getElementById('dashboardUnassigned').textContent = dashboardNum(unassigned.length);
            document.getElementById('dashboardReceipts').textContent = dashboardNum(receipts.summary?.total_quantity);
            document.getElementById('dashboardIssues').textContent = dashboardNum(issues.summary?.total_quantity);
            renderStockStatusChart(stockRows, quality);
            renderTodayFlow(receipts, issues);
            renderLocationChart(stockRows);
            renderWipChart(wip);

            const attention = stockRows
                .filter(row => Number(row.total_quantity) <= 0 || !row.location_code || row.location_code === 'CHUA-XEP')
                .slice(0, 8);
            document.getElementById('dashboardStockRows').innerHTML = attention.map(row => {
                const unlocated = !row.location_code || row.location_code === 'CHUA-XEP';
                const status = unlocated
                    ? '<span class="wms-badge wms-badge--warning">Chưa xếp</span>'
                    : '<span class="wms-badge wms-badge--danger">Tồn không dương</span>';
                return `<tr>
                    <td class="wms-code">${dashboardEsc(row.ma_sp || '-')}</td>
                    <td class="wms-code">${dashboardEsc(row.internal_item_code || '-')}</td>
                    <td>${dashboardEsc(row.location_code || 'CHUA-XEP')}</td>
                    <td>${dashboardEsc([row.size, row.color].filter(Boolean).join(' / ') || '-')}</td>
                    <td class="wms-number">${dashboardNum(row.total_quantity)}</td>
                    <td>${status}</td>
                </tr>`;
            }).join('') || '<tr><td colspan="6" class="wms-empty">Không có dữ liệu cần chú ý.</td></tr>';

            const activities = [
                ...receiptRows.map(row => ({date: row.receipt_date, title: `Nhập kho: ${row.receipt_code}`, meta: `${dashboardNum(row.total_quantity)} · ${row.location_code || 'CHUA-XEP'}`})),
                ...issueRows.map(row => ({date: row.issue_date, title: `Xuất kho: ${row.issue_code}`, meta: `${dashboardNum(row.lines_sum_quantity)} · ${row.department || row.receiver_name || 'Nội bộ'}`}))
            ].sort((a, b) => String(b.date).localeCompare(String(a.date))).slice(0, 7);

            document.getElementById('dashboardActivity').innerHTML = activities.map(item => `
                <div class="wms-activity__item">
                    <div class="wms-activity__title">${dashboardEsc(item.title)}</div>
                    <div class="wms-activity__meta">${dashboardEsc(item.date || '')} · ${dashboardEsc(item.meta)}</div>
                </div>
            `).join('') || '<div class="wms-empty">Chưa có hoạt động hôm nay.</div>';
        }).catch(error => {
            document.getElementById('dashboardStockRows').innerHTML = `<tr><td colspan="6" class="wms-empty text-danger">${dashboardEsc(error.message)}</td></tr>`;
            document.getElementById('dashboardActivity').innerHTML = `<div class="wms-empty text-danger">${dashboardEsc(error.message)}</div>`;
        });

        if (window.lucide) lucide.createIcons();
    </script>
</body>
</html>
