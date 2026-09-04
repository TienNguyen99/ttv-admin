<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nhu cầu mua vật tư</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/warehouse-wms.css') }}?v={{ filemtime(public_path('css/warehouse-wms.css')) }}" rel="stylesheet">
    <style>
        .demand-page { --demand-blue: #2563eb; min-width: 0; }
        .demand-page .wms-panel { min-width: 0; }
        .demand-toolbar { display: grid; grid-template-columns: minmax(220px, 1fr) 170px auto; gap: 12px; align-items: end; }
        .demand-window { display: inline-flex; padding: 3px; gap: 2px; border: 1px solid var(--wms-line); border-radius: 7px; background: #f8fafc; }
        .demand-window button { min-width: 42px; height: 34px; border: 0; border-radius: 5px; background: transparent; color: #475569; font-weight: 700; }
        .demand-window button.is-active { color: #fff; background: var(--demand-blue); box-shadow: 0 2px 8px rgba(37, 99, 235, .22); }
        .demand-advanced { border-top: 1px solid var(--wms-line); margin-top: 12px; padding-top: 10px; }
        .demand-advanced summary { width: max-content; cursor: pointer; color: #475569; font-size: 13px; font-weight: 700; }
        .demand-settings { display: grid; grid-template-columns: repeat(3, minmax(130px, 180px)); gap: 12px; margin-top: 10px; }
        .demand-kpis { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .demand-table-wrap { max-width: 100%; }
        .demand-table { min-width: 820px; table-layout: auto; font-size: 14px; }
        .demand-table th, .demand-table td { padding: 13px 12px; }
        .demand-table td { overflow-wrap: anywhere; }
        .demand-table tbody tr { cursor: pointer; }
        .demand-table tbody tr:hover { background: #eff6ff; }
        .demand-code { color: #1554ad; font-weight: 800; }
        .demand-name { min-width: 170px; max-width: 260px; white-space: normal; line-height: 1.35; }
        .demand-number { font-variant-numeric: tabular-nums; white-space: nowrap; }
        .demand-average { min-width: 72px; }
        .demand-average.is-selected { color: #1554ad; background: #eaf2ff; font-weight: 800; }
        .demand-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 8px; border-radius: 999px; font-size: 11px; font-weight: 800; white-space: nowrap; }
        .demand-badge--buy { color: #b42318; background: #fff0ee; border: 1px solid #ffc9c3; }
        .demand-badge--ok { color: #087443; background: #ecfdf3; border: 1px solid #bbefd3; }
        .demand-badge--low-data { color: #946200; background: #fffae8; border: 1px solid #f5dfa0; }
        .demand-trend-up { color: #c2410c; }
        .demand-trend-down { color: #087443; }
        .demand-loader { position: fixed; inset: 0; z-index: 1080; display: none; place-items: center; background: rgba(241, 247, 255, .72); backdrop-filter: blur(2px); }
        .demand-loader.is-visible { display: grid; }
        .demand-loader__box { display: flex; align-items: center; gap: 12px; padding: 14px 18px; background: #fff; border: 1px solid #cfe0f7; border-radius: 8px; box-shadow: 0 16px 40px rgba(15, 54, 100, .14); font-weight: 700; }
        .demand-bars { display: grid; grid-template-columns: repeat(12, minmax(34px, 1fr)); align-items: end; gap: 8px; height: 210px; padding: 16px 4px 0; border-bottom: 1px solid #cbd5e1; }
        .demand-bar { position: relative; min-height: 2px; border-radius: 4px 4px 0 0; background: #77aaf7; animation: demandGrow .45s ease-out both; }
        .demand-bar span { position: absolute; left: 50%; bottom: calc(100% + 5px); transform: translateX(-50%); font-size: 10px; color: #475569; white-space: nowrap; }
        .demand-months { display: grid; grid-template-columns: repeat(12, minmax(34px, 1fr)); gap: 8px; padding: 7px 4px 0; text-align: center; font-size: 10px; color: #64748b; }
        .demand-chart-scroll { max-width: 100%; overflow-x: auto; padding-bottom: 6px; }
        .demand-chart-scroll .demand-bars, .demand-chart-scroll .demand-months { min-width: 620px; }
        .demand-average-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 8px; margin-bottom: 16px; }
        .demand-average-item { padding: 10px; border: 1px solid #dbe7f5; border-radius: 7px; background: #f8fbff; text-align: center; }
        .demand-average-item span { display: block; color: #64748b; font-size: 11px; font-weight: 700; }
        .demand-average-item strong { display: block; margin-top: 3px; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
        @keyframes demandGrow { from { height: 0; opacity: .3; } }
        @media (max-width: 1399.98px) {
            .demand-page { padding-inline: 20px; }
            .demand-table { min-width: 100%; }
            .demand-table thead { display: none; }
            .demand-table tbody { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; padding: 12px; }
            .demand-table tbody tr { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); align-content: start; border: 1px solid #d8e5f4; border-radius: 8px; background: #fff; overflow: hidden; }
            .demand-table tbody tr:hover { background: #fff; border-color: #9ec5fe; }
            .demand-table tbody td { display: grid; grid-template-columns: minmax(88px, .8fr) minmax(0, 1.2fr); gap: 8px; align-items: start; min-width: 0; padding: 9px 11px; border-right: 0; border-bottom: 1px solid #edf2f7; text-align: left !important; white-space: normal; }
            .demand-table tbody td::before { content: attr(data-label); color: #64748b; font-size: 10px; font-weight: 800; line-height: 1.35; text-transform: uppercase; }
            .demand-table tbody td.demand-card-wide { grid-column: 1 / -1; }
            .demand-table tbody td:last-child { border-bottom: 0; }
            .demand-table tbody td[colspan] { display: block; grid-column: 1 / -1; text-align: center !important; }
            .demand-table tbody td[colspan]::before { display: none; }
            .demand-name { min-width: 0; max-width: none; }
            .demand-number { white-space: normal; }
        }
        @media (max-width: 991.98px) {
            .demand-toolbar { grid-template-columns: 1fr 1fr; }
            .demand-toolbar__search { grid-column: 1 / -1; }
            .demand-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .demand-table tbody { grid-template-columns: minmax(0, 1fr); }
        }
        @media (max-width: 767.98px) {
            .demand-page { padding: 20px 12px 72px; }
            .demand-table tbody { padding: 8px; }
            .demand-average-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .demand-average-grid .demand-average-item:last-child { grid-column: 1 / -1; }
            .demand-page .wms-panel__header { align-items: flex-start; }
        }
        @media (max-width: 575.98px) {
            .demand-toolbar, .demand-settings { grid-template-columns: 1fr; }
            .demand-window { width: 100%; }
            .demand-window button { flex: 1; }
            .demand-table tbody tr { grid-template-columns: minmax(0, 1fr); }
            .demand-table tbody td.demand-card-wide { grid-column: auto; }
            .demand-table tbody td { grid-template-columns: minmax(82px, .75fr) minmax(0, 1.25fr); }
            .demand-page .wms-heading { gap: 10px; }
            .demand-page .wms-heading .wms-btn { width: 100%; justify-content: center; }
        }
        @media (max-width: 419.98px) {
            .demand-kpis { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
@include('layouts.partials.sidebar')

<header class="wms-topbar">
    <h1 class="wms-topbar__title">WMS May Mặc</h1>
    <div class="wms-global-search">
        <i data-lucide="search"></i>
        <input id="topKeyword" aria-label="Tìm vật tư" placeholder="Tìm mã hoặc tên vật tư...">
    </div>
    <div class="wms-topbar__actions">
        <button id="exportCsvBtn" type="button" class="wms-btn"><i data-lucide="download"></i> Xuất trang CSV</button>
    </div>
</header>

<main class="wms-page demand-page">
    <div class="wms-heading">
        <div>
            <h1>Nhu cầu mua vật tư</h1>
            <p>Phân tích phiếu kho nội bộ và lịch sử XNT đã đồng bộ. Không ghi dữ liệu sang Google Sheet hoặc TSoft.</p>
        </div>
        <button id="reloadBtn" type="button" class="wms-btn wms-btn--primary"><i data-lucide="refresh-cw"></i> Tải lại</button>
    </div>

    <section class="wms-panel mb-3">
        <div class="demand-toolbar">
            <div class="demand-toolbar__search">
                <label for="keyword">Mã hoặc tên vật tư</label>
                <input id="keyword" class="form-control" autocomplete="off" placeholder="Ví dụ: thùng carton, 80017315-B">
            </div>
            <div>
                <label for="statusFilter">Trạng thái</label>
                <select id="statusFilter" class="form-select">
                    <option value="all">Tất cả</option>
                    <option value="needs_purchase">Cần mua</option>
                    <option value="in_stock">Đang có tồn</option>
                </select>
            </div>
            <div>
                <label>Chu kỳ tính trung bình</label>
                <div id="windowButtons" class="demand-window" role="group" aria-label="Số tháng phân tích">
                    <button type="button" data-window="1">1T</button>
                    <button type="button" data-window="3" class="is-active">3T</button>
                    <button type="button" data-window="6">6T</button>
                    <button type="button" data-window="9">9T</button>
                    <button type="button" data-window="12">12T</button>
                </div>
            </div>
        </div>
        <details class="demand-advanced">
            <summary>Thiết lập đề xuất mua</summary>
            <div class="demand-settings">
                <div><label for="asOfDate">Tính đến ngày</label><input id="asOfDate" class="form-control" value="{{ now('Asia/Ho_Chi_Minh')->format('d/m/Y') }}" inputmode="numeric"></div>
                <div><label for="leadTimeDays">Thời gian chờ (ngày)</label><input id="leadTimeDays" type="number" min="0" max="365" class="form-control" value="14"></div>
                <div><label for="safetyPercent">Tồn an toàn (%)</label><input id="safetyPercent" type="number" min="0" max="500" step="1" class="form-control" value="20"></div>
                <div><label for="targetMonths">Mức tồn mục tiêu (tháng)</label><input id="targetMonths" type="number" min="0.1" max="24" step="0.1" class="form-control" value="2"></div>
            </div>
        </details>
    </section>

    <section class="wms-kpis demand-kpis">
        <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="barcode"></i></div><div><div class="wms-kpi__label">Mã có xuất vật tư</div><div id="itemCount" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Theo bộ lọc hiện tại</div></div></article>
        <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="shopping-cart"></i></div><div><div class="wms-kpi__label">Mã cần mua</div><div id="needsPurchaseCount" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Đã xuống điểm đặt hàng</div></div></article>
        <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="history"></i></div><div><div class="wms-kpi__label">Chưa đủ lịch sử</div><div id="insufficientHistoryCount" class="wms-kpi__value">0</div><div id="historyMeta" class="wms-kpi__meta">Chưa đủ chu kỳ đang chọn</div></div></article>
        <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="triangle-alert"></i></div><div><div class="wms-kpi__label">Tồn âm</div><div id="negativeStockCount" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Cần kiểm tra chứng từ</div></div></article>
    </section>

    <section class="wms-panel mt-3">
        <div class="wms-panel__header">
            <div><h2>Danh sách vật tư</h2><div class="small text-secondary">Bấm một dòng để xem lịch sử 12 tháng và công thức đề xuất.</div></div>
            <span id="resultLabel" class="small text-secondary">Đang tải...</span>
        </div>
        <div class="wms-table-wrap demand-table-wrap">
            <table class="wms-table demand-table">
                <thead><tr>
                    <th>Mã vật tư</th><th>Tên vật tư</th><th>ĐVT</th><th class="text-end">Tồn hiện tại</th>
                    <th id="averageHeader" class="text-end demand-average is-selected">TB 3T</th>
                    <th class="text-end">Đủ dùng</th><th class="text-end">Đề xuất mua</th><th>Trạng thái</th>
                </tr></thead>
                <tbody id="demandRows"><tr><td colspan="8" class="wms-loading">Đang tải dữ liệu...</td></tr></tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center gap-2 p-3 border-top">
            <span id="pageLabel" class="small text-secondary"></span>
            <div class="d-flex gap-2"><button id="prevBtn" class="wms-btn" type="button"><i data-lucide="chevron-left"></i> Trước</button><button id="nextBtn" class="wms-btn" type="button">Sau <i data-lucide="chevron-right"></i></button></div>
        </div>
    </section>
</main>

<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><div><h2 id="detailTitle" class="modal-title fs-5"></h2><div id="detailSubtitle" class="small text-secondary"></div></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-6 col-md-3"><div class="border rounded-2 p-3 h-100"><div class="small text-secondary">Tồn hiện tại</div><strong id="detailStock" class="fs-4"></strong></div></div>
                    <div class="col-6 col-md-3"><div class="border rounded-2 p-3 h-100"><div class="small text-secondary">Tiêu hao TB</div><strong id="detailAverage" class="fs-4"></strong></div></div>
                    <div class="col-6 col-md-3"><div class="border rounded-2 p-3 h-100"><div class="small text-secondary">Điểm đặt hàng</div><strong id="detailReorder" class="fs-4"></strong></div></div>
                    <div class="col-6 col-md-3"><div class="border rounded-2 p-3 h-100"><div class="small text-secondary">Đề xuất mua</div><strong id="detailSuggested" class="fs-4"></strong></div></div>
                </div>
                <div id="detailAverages" class="demand-average-grid"></div>
                <div id="detailMovement" class="alert alert-light border small"></div>
                <h3 class="fs-6">Tiêu hao thực tế 12 tháng</h3>
                <div class="demand-chart-scroll"><div id="detailBars" class="demand-bars"></div><div id="detailMonths" class="demand-months"></div></div>
                <div id="detailFormula" class="alert alert-primary mt-4 mb-0 small"></div>
            </div>
        </div>
    </div>
</div>

<div id="pageLoader" class="demand-loader" aria-hidden="true"><div class="demand-loader__box"><span class="spinner-border spinner-border-sm text-primary"></span><span>Đang phân tích dữ liệu kho...</span></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
    const apiUrl = @json(url('/api/nhu-cau-mua-vat-tu'));
    const state = { window: 3, page: 1, lastPage: 1, rows: [], timer: null };
    const el = id => document.getElementById(id);
    const number = value => new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 3 }).format(Number(value || 0));
    const trendText = value => value === null ? '-' : `${Number(value) > 0 ? '+' : ''}${number(value)}%`;
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));

    function apiDate(value) {
        const match = String(value || '').trim().match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
        if (!match) return '';
        return `${match[3]}-${match[2]}-${match[1]}`;
    }

    function setLoading(active) {
        el('pageLoader').classList.toggle('is-visible', active);
        el('pageLoader').setAttribute('aria-hidden', active ? 'false' : 'true');
    }

    async function loadData(fresh = false) {
        const params = new URLSearchParams({
            as_of: apiDate(el('asOfDate').value), window: state.window,
            lead_time_days: el('leadTimeDays').value, safety_percent: el('safetyPercent').value,
            target_months: el('targetMonths').value, keyword: el('keyword').value.trim(),
            status: el('statusFilter').value, page: state.page, per_page: 50
        });
        if (fresh) params.set('fresh', '1');
        setLoading(true);
        try {
            const response = await fetch(`${apiUrl}?${params.toString()}`, { headers: { Accept: 'application/json' } });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Không tải được dữ liệu.');
            state.rows = payload.data || [];
            state.page = payload.meta.page;
            state.lastPage = payload.meta.last_page;
            renderSummary(payload.summary);
            renderRows(state.rows);
            el('resultLabel').textContent = `${number(payload.meta.total)} mã vật tư`;
            el('pageLabel').textContent = `Trang ${payload.meta.page}/${payload.meta.last_page}`;
            el('prevBtn').disabled = state.page <= 1;
            el('nextBtn').disabled = state.page >= state.lastPage;
        } catch (error) {
            el('demandRows').innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">${escapeHtml(error.message)}</td></tr>`;
            el('resultLabel').textContent = 'Không tải được dữ liệu';
        } finally {
            setLoading(false);
        }
    }

    function renderSummary(summary) {
        el('itemCount').textContent = number(summary.item_count);
        el('needsPurchaseCount').textContent = number(summary.needs_purchase_count);
        el('negativeStockCount').textContent = number(summary.negative_stock_count);
        el('insufficientHistoryCount').textContent = number(summary.insufficient_history_count);
        el('historyMeta').textContent = `Chưa đủ ${summary.window_months} tháng dữ liệu`;
        el('averageHeader').textContent = `TB ${summary.window_months}T`;
    }

    function renderRows(rows) {
        if (!rows.length) {
            el('demandRows').innerHTML = '<tr><td colspan="8" class="text-center text-secondary py-4">Không có vật tư phù hợp.</td></tr>';
            return;
        }
        el('demandRows').innerHTML = rows.map((row, index) => {
            const cover = row.cover_months === null ? '-' : `${number(row.cover_months)} tháng`;
            const status = row.needs_purchase
                ? '<span class="demand-badge demand-badge--buy"><i data-lucide="triangle-alert"></i>Cần mua</span>'
                : '<span class="demand-badge demand-badge--ok"><i data-lucide="circle-check"></i>Đủ tồn</span>';
            const warnings = [];
            if (!row.history_complete) warnings.push('<span class="demand-badge demand-badge--low-data">Chưa đủ lịch sử</span>');
            if (row.unit_conflict) warnings.push('<span class="demand-badge demand-badge--buy">Sai đơn vị gốc</span>');
            const history = warnings.length ? `<div class="mt-1 d-flex flex-wrap gap-1">${warnings.join('')}</div>` : '';
            return `<tr data-row-index="${index}">
                <td data-label="Mã vật tư"><span class="demand-code">${escapeHtml(row.item_code)}</span></td>
                <td data-label="Tên vật tư" class="demand-name demand-card-wide">${escapeHtml(row.item_name || '-')}</td><td data-label="ĐVT">${escapeHtml(row.unit)}</td>
                <td data-label="Tồn hiện tại" class="text-end demand-number ${row.current_stock < 0 ? 'text-danger fw-bold' : 'fw-bold'}">${number(row.current_stock)}</td>
                <td data-label="Trung bình ${state.window} tháng" class="text-end demand-number demand-average is-selected">${number(row.monthly_average)}</td>
                <td data-label="Đủ dùng" class="text-end demand-number">${cover}</td>
                <td data-label="Đề xuất mua" class="text-end demand-number fw-bold ${row.needs_purchase ? 'text-danger' : ''}">${number(row.suggested_purchase)}</td>
                <td data-label="Trạng thái" class="demand-card-wide">${status}${history}</td></tr>`;
        }).join('');
        document.querySelectorAll('#demandRows tr[data-row-index]').forEach(node => node.addEventListener('click', () => showDetail(rows[Number(node.dataset.rowIndex)])));
        if (window.lucide) window.lucide.createIcons();
    }

    function showDetail(row) {
        el('detailTitle').textContent = row.item_code;
        el('detailSubtitle').textContent = `${row.item_name || 'Chưa có tên'} · ${row.unit} · Nguồn: ${(row.history_sources || []).join(', ') || 'Phiếu nội bộ'}`;
        el('detailStock').textContent = number(row.current_stock);
        el('detailAverage').textContent = `${number(row.monthly_average)} / tháng`;
        el('detailReorder').textContent = number(row.reorder_point);
        el('detailSuggested').textContent = number(row.suggested_purchase);
        el('detailAverages').innerHTML = [1, 3, 6, 9, 12].map(month => `<div class="demand-average-item"><span>TB ${month} tháng</span><strong>${number(row.averages[month])}</strong></div>`).join('');
        el('detailMovement').textContent = `Nhập ${number(row.receipt_quantity)} · Xuất ${number(row.gross_issue_quantity)} · Trả ${number(row.returned_quantity)} · Xu hướng ${trendText(row.trend_percent)} · Điểm đặt ${number(row.reorder_point)}`;
        const max = Math.max(1, ...row.monthly.map(item => Number(item.usage || 0)));
        el('detailBars').innerHTML = row.monthly.map(item => `<div class="demand-bar" style="height:${Math.max(2, Number(item.usage || 0) / max * 100)}%"><span>${number(item.usage)}</span></div>`).join('');
        el('detailMonths').innerHTML = row.monthly.map(item => `<span>${item.month.slice(5)}/${item.month.slice(2,4)}</span>`).join('');
        el('detailFormula').textContent = `Điểm đặt = tiêu hao trung bình ngày × ${el('leadTimeDays').value} ngày + ${el('safetyPercent').value}% tồn an toàn. Đề xuất mua để đạt ${el('targetMonths').value} tháng tồn; chỉ đề xuất khi tồn hiện tại đã xuống điểm đặt hàng.`;
        bootstrap.Modal.getOrCreateInstance(el('detailModal')).show();
    }

    function scheduleReload() { clearTimeout(state.timer); state.timer = setTimeout(() => { state.page = 1; loadData(); }, 350); }
    document.querySelectorAll('#windowButtons button').forEach(button => button.addEventListener('click', () => {
        state.window = Number(button.dataset.window);
        document.querySelectorAll('#windowButtons button').forEach(item => item.classList.toggle('is-active', item === button));
        state.page = 1; loadData();
    }));
    ['keyword', 'topKeyword'].forEach(id => el(id).addEventListener('input', event => {
        const other = id === 'keyword' ? el('topKeyword') : el('keyword'); other.value = event.target.value; scheduleReload();
    }));
    ['statusFilter', 'asOfDate', 'leadTimeDays', 'safetyPercent', 'targetMonths'].forEach(id => el(id).addEventListener('change', () => { state.page = 1; loadData(); }));
    el('reloadBtn').addEventListener('click', () => loadData(true));
    el('prevBtn').addEventListener('click', () => { if (state.page > 1) { state.page--; loadData(); } });
    el('nextBtn').addEventListener('click', () => { if (state.page < state.lastPage) { state.page++; loadData(); } });
    el('exportCsvBtn').addEventListener('click', () => {
        const headers = ['Mã vật tư','Tên vật tư','ĐVT','Tồn hiện tại','Nhập','Xuất vật tư','Trả lại','Tiêu hao TB tháng','Đủ dùng (tháng)','Điểm đặt','Đề xuất mua'];
        const lines = state.rows.map(row => [row.item_code,row.item_name,row.unit,row.current_stock,row.receipt_quantity,row.gross_issue_quantity,row.returned_quantity,row.monthly_average,row.cover_months ?? '',row.reorder_point,row.suggested_purchase]);
        const csv = '\uFEFF' + [headers, ...lines].map(line => line.map(value => `"${String(value ?? '').replace(/"/g, '""')}"`).join(';')).join('\r\n');
        const link = document.createElement('a'); link.href = URL.createObjectURL(new Blob([csv], {type:'text/csv;charset=utf-8'})); link.download = `nhu-cau-mua-vat-tu-${apiDate(el('asOfDate').value)}.csv`; link.click(); URL.revokeObjectURL(link.href);
    });
    loadData();
})();
</script>
</body>
</html>
