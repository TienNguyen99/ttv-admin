<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Đợt kiểm kê kho</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/warehouse-wms.css') }}?v={{ filemtime(public_path('css/warehouse-wms.css')) }}" rel="stylesheet">
    <style>
        .stocktake-page { max-width:1720px; }
        .stocktake-layout { display:grid; grid-template-columns:320px minmax(0,1fr); gap:14px; align-items:start; }
        .location-list { max-height:calc(100vh - 365px); min-height:420px; overflow:auto; }
        .location-row { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:8px; width:100%; padding:10px 12px; border:0; border-bottom:1px solid #e2e8f0; background:#fff; text-align:left; transition:background .16s ease, box-shadow .16s ease; }
        .location-row:hover { background:#f6faff; }
        .location-row.is-active { background:#eef6ff; box-shadow:inset 3px 0 0 #2563eb; }
        .location-row__code { color:#0f2747; font-weight:900; }
        .location-row__meta { margin-top:2px; color:#64748b; font-size:11px; }
        .status-badge { display:inline-flex; align-items:center; padding:3px 7px; border-radius:999px; font-size:11px; font-weight:800; white-space:nowrap; }
        .status-pending { background:#f1f5f9; color:#64748b; }
        .status-counting { background:#fff7ed; color:#c2410c; }
        .status-completed, .status-posted { background:#dcfce7; color:#15803d; }
        .stocktake-progress { height:8px; border-radius:999px; background:#e2e8f0; overflow:hidden; }
        .stocktake-progress > span { display:block; height:100%; border-radius:inherit; background:#2563eb; transition:width .3s ease; }
        .count-table { min-width:1160px; }
        .count-table .item-name { min-width:220px; white-space:normal; }
        .count-input { min-width:130px; font-weight:800; text-align:right; }
        .expected-hidden { color:#94a3b8; letter-spacing:2px; }
        .variance-positive { color:#15803d; font-weight:800; }
        .variance-negative { color:#dc2626; font-weight:800; }
        .catalog-warning { color:#dc2626; font-size:11px; font-weight:700; }
        .session-empty { display:grid; min-height:420px; place-items:center; padding:30px; text-align:center; }
        .stocktake-toolbar { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px; }
        .stocktake-toolbar__actions { display:flex; flex-wrap:wrap; gap:8px; }
        .new-line-grid { display:grid; grid-template-columns:1.2fr 1.5fr .65fr .8fr .65fr .65fr .7fr .7fr .7fr auto; gap:8px; align-items:end; }
        .weight-input { min-width:120px; font-weight:800; text-align:right; }
        .weight-norm { margin-top:3px; color:#64748b; font-size:10px; white-space:nowrap; }
        .count-entry-row td { background:#fff; vertical-align:middle; }
        .count-entry-row + .count-entry-row td { border-top-style:dashed; }
        .package-badge { display:inline-flex; padding:3px 7px; border-radius:6px; background:#eef6ff; color:#1d4ed8; font-size:11px; font-weight:800; }
        .subtotal-row td { background:#eaf4ff !important; border-top:2px solid #93c5fd; color:#0f2747; font-weight:900; }
        .subtotal-label { display:flex; align-items:center; justify-content:space-between; gap:8px; }
        .remove-entry { width:30px; height:30px; padding:0; border:1px solid #fecaca; border-radius:6px; background:#fff; color:#dc2626; }
        .entry-note-wrap { display:flex; align-items:center; gap:6px; min-width:190px; }
        .stocktake-loader { position:fixed; inset:0; z-index:3000; display:none; place-items:center; background:rgba(238,246,255,.74); backdrop-filter:blur(3px); }
        .stocktake-loader.is-visible { display:grid; }
        button, select { cursor:pointer; }
        button:focus-visible, input:focus-visible, select:focus-visible, textarea:focus-visible { outline:3px solid rgba(37,99,235,.32); outline-offset:2px; }
        @media (max-width:980px) { .stocktake-layout { grid-template-columns:1fr; } .location-list { min-height:0; max-height:300px; } }
        @media (max-width:760px) {
            .stocktake-page { max-width:100%; padding:80px 12px 28px !important; overflow:hidden; }
            .wms-heading { padding-left:52px; }
            .wms-heading h1 { font-size:25px; }
            .stocktake-toolbar { align-items:stretch; }
            .stocktake-toolbar > div:first-child, .stocktake-toolbar select { width:100%; min-width:0 !important; }
            .stocktake-toolbar__actions { display:grid; grid-template-columns:1fr; width:100%; }
            .stocktake-toolbar__actions .wms-btn { justify-content:center; width:100%; }
            .new-line-grid { grid-template-columns:1fr 1fr; }
            .session-empty { min-height:360px; padding:20px 14px; }
            .session-empty h2 { font-size:24px; }
        }
        @media (prefers-reduced-motion:reduce) { .location-row, .stocktake-progress > span { transition:none; } }
    </style>
</head>
<body>
@include('layouts.partials.sidebar')

<header class="wms-topbar">
    <h1 class="wms-topbar__title">WMS May Mặc</h1>
    <div class="wms-global-search"><i data-lucide="search"></i><input id="globalLocationSearch" placeholder="Tìm vị trí hoặc mã hàng..."></div>
    <div class="wms-topbar__actions"><button id="newSessionBtn" class="wms-btn wms-btn--primary"><i data-lucide="clipboard-plus"></i>Tạo đợt kiểm kê</button></div>
</header>

<main class="wms-page stocktake-page">
    <div class="wms-heading">
        <div><h1>Đợt kiểm kê kho</h1><p>Đếm thực tế theo vị trí. Tồn chỉ thay đổi sau khi hoàn tất và áp dụng chênh lệch.</p></div>
    </div>

    <section class="wms-panel mb-3">
        <div class="p-3 stocktake-toolbar">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <label for="sessionSelect" class="fw-bold small">Đợt kiểm kê</label>
                <select id="sessionSelect" class="form-select" style="min-width:280px"></select>
                <span id="sessionStatus"></span>
            </div>
            <div class="stocktake-toolbar__actions">
                <button id="completeSessionBtn" class="wms-btn"><i data-lucide="check-check"></i>Hoàn tất kiểm đếm</button>
                <button id="postSessionBtn" class="wms-btn wms-btn--primary"><i data-lucide="badge-check"></i>Chốt & áp dụng</button>
                <button id="deleteSessionBtn" class="wms-btn text-danger"><i data-lucide="trash-2"></i>Xóa đợt</button>
            </div>
        </div>
    </section>

    <section id="sessionWorkspace" class="d-none">
        <section class="wms-kpis mb-3">
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="map-pinned"></i></div><div><div class="wms-kpi__label">Vị trí hoàn tất</div><div id="completedLocations" class="wms-kpi__value">0/0</div><div class="wms-kpi__meta">Kiểm lần lượt từng kệ</div></div></article>
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="list-checks"></i></div><div><div class="wms-kpi__label">Dòng đã đếm</div><div id="countedLines" class="wms-kpi__value">0/0</div><div class="wms-kpi__meta">Gồm mã được tìm thấy thêm</div></div></article>
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="package-check"></i></div><div><div class="wms-kpi__label">Tổng thực tế</div><div id="actualQuantity" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Theo các dòng đã nhập</div></div></article>
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="scale"></i></div><div><div class="wms-kpi__label">Chênh lệch</div><div id="varianceQuantity" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Thực tế trừ tồn sổ</div></div></article>
        </section>
        <div class="stocktake-progress mb-3"><span id="sessionProgress" style="width:0"></span></div>

        <div class="stocktake-layout">
            <section class="wms-panel">
                <div class="wms-panel__header"><h2>Vị trí kiểm kê</h2><span id="locationCountLabel" class="text-secondary small"></span></div>
                <div class="p-2"><input id="locationSearch" class="form-control" placeholder="Tìm A1, B2..."></div>
                <div id="locationRows" class="location-list"></div>
            </section>

            <section class="wms-panel">
                <div class="wms-panel__header">
                    <div><h2 id="activeLocationTitle">Chọn vị trí</h2><div id="activeLocationMeta" class="text-secondary small"></div></div>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <label class="form-check form-switch mb-0"><input id="showExpected" class="form-check-input" type="checkbox"><span class="form-check-label small">Hiện tồn sổ</span></label>
                        <button id="reopenLocationBtn" class="wms-btn"><i data-lucide="rotate-ccw"></i>Mở lại</button>
                        <button id="saveLocationBtn" class="wms-btn"><i data-lucide="save"></i>Lưu nháp</button>
                        <button id="completeLocationBtn" class="wms-btn wms-btn--primary"><i data-lucide="circle-check"></i>Hoàn tất kệ</button>
                    </div>
                </div>
                <div class="wms-table-wrap">
                    <table class="wms-table count-table">
                        <thead><tr><th>Mã nội bộ</th><th>Tên hàng</th><th>Size</th><th>Màu</th><th>Mặt</th><th>ĐVT</th><th class="text-end">Tồn sổ</th><th class="text-end">Kiện / SL quy đổi</th><th class="text-end">Số KG</th><th class="text-end">Chênh</th><th>Ghi chú kiện</th></tr></thead>
                        <tbody id="countRows"><tr><td colspan="11" class="wms-empty">Chọn một vị trí để bắt đầu.</td></tr></tbody>
                    </table>
                </div>
                <div class="p-3 border-top">
                    <div class="fw-bold small mb-2">Tìm thấy mã chưa có trong danh sách kệ</div>
                    <div class="new-line-grid">
                        <div><label class="small">Mã nội bộ</label><input id="newItemCode" class="form-control" list="catalogOptions" autocomplete="off"></div>
                        <div><label class="small">Tên hàng</label><input id="newItemName" class="form-control"></div>
                        <div><label class="small">Size</label><input id="newItemSize" class="form-control"></div>
                        <div><label class="small">Màu</label><input id="newItemColor" class="form-control"></div>
                        <div><label class="small">Mặt</label><input id="newItemSide" class="form-control"></div>
                        <div><label class="small">ĐVT</label><input id="newItemUnit" class="form-control"></div>
                        <div><label class="small">ĐM g/ĐVT</label><input id="newItemWeight" class="form-control text-end" type="number" min="0" step="0.000001"></div>
                        <div><label class="small">Thêm SL</label><input id="newItemQuantity" class="form-control text-end" type="number" min="0" step="0.001" placeholder="Yard/mét..."></div>
                        <div><label class="small">Thêm KG</label><input id="newItemKg" class="form-control text-end" type="number" min="0" step="0.001" placeholder="Kg cân được"></div>
                        <button id="addCountLineBtn" class="wms-btn wms-btn--primary"><i data-lucide="plus"></i>Thêm</button>
                    </div>
                    <datalist id="catalogOptions"></datalist>
                </div>
            </section>
        </div>
    </section>

    <section id="emptyWorkspace" class="wms-panel session-empty">
        <div><i data-lucide="clipboard-list" style="width:46px;height:46px;color:#4f8df7"></i><h2 class="mt-3">Chưa có đợt kiểm kê</h2><p class="text-secondary">Tạo đợt để chụp tồn hệ thống và bắt đầu kiểm theo vị trí.</p><button class="wms-btn wms-btn--primary" onclick="openCreateModal()">Tạo đợt kiểm kê</button></div>
    </section>
</main>

<div class="modal fade" id="createSessionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Tạo đợt kiểm kê</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label">Tên đợt *</label><input id="sessionName" class="form-control" value="Kiểm kê toàn kho"></div>
            <div class="mb-3"><label class="form-label">Ngày chốt số liệu *</label><input id="sessionDate" class="form-control date-vn" inputmode="numeric" placeholder="dd/mm/yyyy" value="{{ now('Asia/Ho_Chi_Minh')->format('d/m/Y') }}"></div>
            <div><label class="form-label">Ghi chú</label><textarea id="sessionNote" class="form-control" rows="3" placeholder="Ví dụ: Ngừng nhập xuất từ 08:00"></textarea></div>
            <div class="alert alert-warning mt-3 mb-0 small">Trong lúc kiểm nên tạm dừng nhập/xuất. Hệ thống sẽ chụp tồn sổ tại ngày này.</div>
        </div>
        <div class="modal-footer"><button class="wms-btn" data-bs-dismiss="modal">Hủy</button><button id="createSessionBtn" class="wms-btn wms-btn--primary">Tạo và chụp tồn</button></div>
    </div></div>
</div>

<div id="pageLoader" class="stocktake-loader"><div class="text-center"><div class="spinner-border text-primary"></div><div id="loaderText" class="fw-bold mt-3">Đang xử lý...</div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const createModal = new bootstrap.Modal(document.getElementById('createSessionModal'));
    const num = value => Number(value || 0).toLocaleString('vi-VN', {maximumFractionDigits:3});
    const inputNumber = value => Number(Number(value || 0).toFixed(6));
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
    let sessions = [], activeSession = null, locations = [], activeLocation = null, activeLines = [], catalogItems = [], isDirty = false;

    function dateVnToIso(value) {
        const match = String(value || '').trim().match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
        return match ? `${match[3]}-${match[2].padStart(2,'0')}-${match[1].padStart(2,'0')}` : value;
    }
    function setLoading(visible, text='Đang xử lý...') {
        document.getElementById('loaderText').textContent = text;
        document.getElementById('pageLoader').classList.toggle('is-visible', visible);
    }
    async function api(url, options={}) {
        const response = await fetch(url, {headers:{'Accept':'application/json', ...(options.body ? {'Content-Type':'application/json'} : {}), ...(options.method && options.method !== 'GET' ? {'X-CSRF-TOKEN':csrfToken} : {})}, ...options});
        const result = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(result.message || Object.values(result.errors || {}).flat()[0] || 'Không xử lý được dữ liệu.');
        return result;
    }
    function statusLabel(status) {
        return {pending:'Chưa kiểm', counting:'Đang kiểm', completed:'Hoàn tất', posted:'Đã áp dụng'}[status] || status;
    }
    function statusHtml(status) { return `<span class="status-badge status-${esc(status)}">${esc(statusLabel(status))}</span>`; }
    function openCreateModal() { createModal.show(); }

    async function loadSessions(selectId=null) {
        const result = await api('/api/dot-kiem-ke');
        sessions = result.data || [];
        const select = document.getElementById('sessionSelect');
        select.innerHTML = sessions.map(item => `<option value="${item.id}">${esc(item.stocktake_code)} · ${esc(item.name)} · ${esc(item.count_date)}</option>`).join('');
        const preferred = selectId || activeSession?.id || sessions.find(item => ['counting','completed'].includes(item.status))?.id || sessions[0]?.id;
        if (preferred) select.value = preferred;
        document.getElementById('emptyWorkspace').classList.toggle('d-none', sessions.length > 0);
        document.getElementById('sessionWorkspace').classList.toggle('d-none', sessions.length === 0);
        if (preferred) {
            await loadSession(preferred);
        } else {
            activeSession = null;
            activeLocation = null;
            isDirty = false;
            document.getElementById('sessionStatus').innerHTML = '';
            ['completeSessionBtn','postSessionBtn','deleteSessionBtn'].forEach(id => document.getElementById(id).disabled = true);
        }
    }

    async function loadSession(id, preserveLocation=true) {
        const result = await api(`/api/dot-kiem-ke/${id}`);
        if (activeSession && Number(activeSession.id) !== Number(id)) {
            activeLocation = null;
            activeLines = [];
            isDirty = false;
        }
        activeSession = result.data.session;
        locations = result.data.locations || [];
        const summary = result.data.summary || {};
        document.getElementById('sessionStatus').innerHTML = statusHtml(activeSession.status);
        document.getElementById('completedLocations').textContent = `${num(summary.completed_location_count)}/${num(summary.location_count)}`;
        document.getElementById('countedLines').textContent = `${num(summary.counted_line_count)}/${num(summary.line_count)}`;
        document.getElementById('actualQuantity').textContent = num(summary.counted_quantity);
        const variance = Number(summary.variance_quantity || 0);
        const varianceEl = document.getElementById('varianceQuantity');
        varianceEl.textContent = `${variance > 0 ? '+' : ''}${num(variance)}`;
        varianceEl.className = `wms-kpi__value ${variance > 0 ? 'variance-positive' : variance < 0 ? 'variance-negative' : ''}`;
        const percent = summary.location_count ? summary.completed_location_count / summary.location_count * 100 : 0;
        document.getElementById('sessionProgress').style.width = `${percent}%`;
        const locked = activeSession.status === 'posted';
        const postButton = document.getElementById('postSessionBtn');
        document.getElementById('completeSessionBtn').disabled = locked || activeSession.status === 'completed';
        postButton.disabled = locked || Number(summary.counted_line_count || 0) === 0;
        postButton.innerHTML = locked
            ? '<i data-lucide="badge-check"></i>Đã chốt & áp dụng'
            : '<i data-lucide="badge-check"></i>Chốt & áp dụng';
        postButton.title = locked ? 'Đợt kiểm kê này đã được áp dụng vào tồn kho.' : '';
        document.getElementById('deleteSessionBtn').disabled = locked;
        if (window.lucide) window.lucide.createIcons();
        renderLocations();
        const wanted = preserveLocation && activeLocation ? locations.find(row => row.id === activeLocation.id) : null;
        const next = wanted || locations.find(row => row.status !== 'completed') || locations[0];
        if (next) await selectLocation(next.id);
    }

    function renderLocations() {
        const keyword = document.getElementById('locationSearch').value.trim().toUpperCase();
        const filtered = locations.filter(row => !keyword || String(row.location_code).toUpperCase().includes(keyword));
        document.getElementById('locationCountLabel').textContent = `${num(filtered.length)} vị trí`;
        document.getElementById('locationRows').innerHTML = filtered.map(row => `
            <button class="location-row${activeLocation?.id === row.id ? ' is-active' : ''}" data-location-id="${row.id}">
                <span><span class="location-row__code">${esc(row.location_code)}</span><span class="location-row__meta d-block">${num(row.counted_line_count)}/${num(row.line_count)} dòng · SL ${num(row.counted_quantity)}</span></span>
                ${statusHtml(row.status)}
            </button>`).join('') || '<div class="wms-empty">Không có vị trí phù hợp.</div>';
    }

    async function selectLocation(id) {
        if (activeLocation && Number(activeLocation.id) !== Number(id) && isDirty) {
            await saveLocation(false);
        }
        const result = await api(`/api/dot-kiem-ke/${activeSession.id}/vi-tri/${id}`);
        activeLocation = result.data.location;
        activeLines = result.data.lines || [];
        isDirty = false;
        renderLocations();
        renderCountLines();
    }

    function recalcCountLine(line) {
        line.entries = Array.isArray(line.entries) ? line.entries : [];
        const completedZero = !line.entries.length
            && Number(line.counted_quantity) === 0
            && (activeLocation?.status === 'completed' || activeSession?.status === 'posted');
        line.counted_quantity = line.entries.length
            ? line.entries.reduce((total, entry) => total + Number(entry.converted_quantity || 0), 0)
            : (completedZero ? 0 : null);
        line.counted_weight_kg = line.entries.length
            ? line.entries.reduce((total, entry) => total + Number(entry.weight_kg || 0), 0)
            : null;
        return line;
    }

    function renderCountLines() {
        document.getElementById('activeLocationTitle').textContent = `Vị trí ${activeLocation?.location_code || ''}`;
        document.getElementById('activeLocationMeta').innerHTML = activeLocation ? statusHtml(activeLocation.status) : '';
        const locked = !activeLocation || activeSession.status === 'posted' || activeLocation.status === 'completed';
        document.getElementById('saveLocationBtn').disabled = locked;
        document.getElementById('completeLocationBtn').disabled = locked;
        document.getElementById('addCountLineBtn').disabled = locked;
        ['newItemCode','newItemName','newItemSize','newItemColor','newItemSide','newItemUnit','newItemWeight','newItemQuantity','newItemKg'].forEach(id => document.getElementById(id).disabled = locked);
        document.getElementById('reopenLocationBtn').disabled = !activeLocation || activeSession.status === 'posted' || activeLocation.status !== 'completed';
        const showExpected = document.getElementById('showExpected').checked || activeLocation?.status === 'completed' || activeSession.status === 'posted';
        const rows = [];

        activeLines.forEach((line, lineIndex) => {
            line.entries = Array.isArray(line.entries) ? line.entries : [];
            recalcCountLine(line);
            const entries = line.entries.length ? line.entries : [{empty:true}];
            entries.forEach((entry, entryIndex) => {
                const isBase = entry.input_type !== 'kg';
                rows.push(`<tr class="count-entry-row" data-line-index="${lineIndex}" data-entry-index="${entryIndex}">
                    <td><span class="wms-code">${esc(line.internal_item_code)}</span>${line.catalog_exists === false ? '<div class="catalog-warning">Chưa có trong danh mục</div>' : ''}<div class="mt-1"><span class="package-badge">Kiện ${entryIndex + 1}</span></div></td>
                    <td class="item-name">${esc(line.item_name || '-')}</td><td>${esc(line.size || '-')}</td><td>${esc(line.color || '-')}</td><td>${esc(line.side || '-')}</td><td>${esc(line.unit || '-')}</td>
                    <td class="wms-number ${showExpected ? '' : 'expected-hidden'}">${entryIndex === 0 ? (showExpected ? num(line.expected_quantity) : '••••') : '-'}</td>
                    <td>${entry.empty ? '<span class="text-muted">Chưa nhập kiện</span>' : `<input class="form-control count-input entry-quantity" type="number" min="0.000001" step="0.001" value="${esc(isBase ? entry.input_quantity : entry.converted_quantity)}" ${locked || !isBase ? 'readonly' : ''}>`}</td>
                    <td>${entry.empty ? '-' : `<input class="form-control weight-input entry-weight" type="number" min="0.000001" step="0.001" value="${esc(isBase ? '' : entry.input_quantity)}" placeholder="-" ${locked || isBase ? 'readonly' : ''}>`}</td>
                    <td class="wms-number">-</td>
                    <td>${entry.empty ? '-' : `<div class="entry-note-wrap"><input class="form-control entry-note" value="${esc(entry.note || '')}" placeholder="Ghi chú kiện" ${locked ? 'disabled' : ''}><button type="button" class="remove-entry" title="Xóa kiện" ${locked ? 'disabled' : ''}>×</button></div>`}</td>
                </tr>`);
            });
            const subtotal = Number(line.counted_quantity || 0);
            const variance = line.counted_quantity === null ? null : subtotal - Number(line.expected_quantity || 0);
            rows.push(`<tr class="subtotal-row" data-subtotal-line="${lineIndex}">
                <td colspan="6"><div class="subtotal-label"><span>Subtotal ${esc(line.internal_item_code)}</span><span>${line.entries.length} kiện</span></div></td>
                <td class="wms-number">${showExpected ? num(line.expected_quantity) : '••••'}</td>
                <td class="wms-number">${line.counted_quantity === null ? '-' : `${num(subtotal)} ${esc(line.unit || '')}`}</td>
                <td class="wms-number">${line.counted_weight_kg ? `${num(line.counted_weight_kg)} KG` : '-'}</td>
                <td class="wms-number ${variance > 0 ? 'variance-positive' : variance < 0 ? 'variance-negative' : ''}">${showExpected && variance !== null ? `${variance > 0 ? '+' : ''}${num(variance)}` : '-'}</td>
                <td>${line.weight_per_unit_grams ? `ĐM ${num(line.weight_per_unit_grams)} g/${esc(line.unit || 'ĐVT')}` : ''}</td>
            </tr>`);
        });
        document.getElementById('countRows').innerHTML = rows.join('') || '<tr><td colspan="11" class="wms-empty">Kệ chưa có mã dự kiến. Có thể thêm mã bằng form bên dưới.</td></tr>';
    }

    function collectLines() {
        return activeLines.map(line => {
            recalcCountLine(line);
            return {id:line.id || null, ma_hh:line.ma_hh || '', internal_item_code:line.internal_item_code, item_name:line.item_name || '', unit:line.unit || '', weight_per_unit_grams:Number(line.weight_per_unit_grams || 0) || null, size:line.size || '', color:line.color || '', side:line.side || '', note:line.note || '', entries:line.entries.map(entry => ({id:entry.id || null, input_type:entry.input_type, input_quantity:Number(entry.input_quantity), note:entry.note || ''}))};
        });
    }

    async function saveLocation(showToast=true) {
        if (!activeLocation) return;
        const result = await api(`/api/dot-kiem-ke/${activeSession.id}/vi-tri/${activeLocation.id}`, {method:'PUT', body:JSON.stringify({lines:collectLines()})});
        isDirty = false;
        await selectLocation(activeLocation.id);
        if (showToast) Swal.fire({icon:'success', title:'Đã lưu', text:result.message, timer:1200, showConfirmButton:false});
    }

    async function completeLocation() {
        await saveLocation(false);
        const missing = activeLines.filter(line => line.counted_quantity === null || line.counted_quantity === undefined).length;
        const answer = await Swal.fire({icon:'question', title:`Hoàn tất kệ ${activeLocation.location_code}?`, text:missing ? `${missing} dòng còn trống sẽ được ghi nhận bằng 0.` : 'Sau khi hoàn tất sẽ hiện tồn sổ và chênh lệch.', showCancelButton:true, confirmButtonText:'Hoàn tất kệ', cancelButtonText:'Kiểm lại'});
        if (!answer.isConfirmed) return;
        await api(`/api/dot-kiem-ke/${activeSession.id}/vi-tri/${activeLocation.id}/hoan-tat`, {method:'POST', body:JSON.stringify({zero_unentered:true})});
        await loadSession(activeSession.id, false);
    }

    async function searchCatalog(keyword) {
        if (keyword.length < 2) return;
        const result = await api(`/api/ma-noi-bo-danh-muc?keyword=${encodeURIComponent(keyword)}&limit=50`);
        catalogItems = result.data || [];
        document.getElementById('catalogOptions').innerHTML = catalogItems.map(item => `<option value="${esc(item.code || item.value || '')}">${esc(item.name || '')}</option>`).join('');
    }

    document.getElementById('newSessionBtn').addEventListener('click', openCreateModal);
    document.getElementById('createSessionBtn').addEventListener('click', async () => {
        setLoading(true, 'Đang chụp tồn và danh sách vị trí...');
        try {
            const result = await api('/api/dot-kiem-ke', {method:'POST', body:JSON.stringify({name:document.getElementById('sessionName').value.trim(), count_date:dateVnToIso(document.getElementById('sessionDate').value), note:document.getElementById('sessionNote').value.trim()})});
            createModal.hide(); await loadSessions(result.data.id); Swal.fire({icon:'success', title:'Đã tạo đợt kiểm kê', text:result.message});
        } catch(error) { Swal.fire({icon:'error', title:'Không tạo được', text:error.message}); } finally { setLoading(false); }
    });
    document.getElementById('sessionSelect').addEventListener('change', async event => {
        try {
            if (isDirty) await saveLocation(false);
            await loadSession(event.target.value, false);
        } catch(error) {
            Swal.fire({icon:'error', title:'Không chuyển được đợt kiểm kê', text:error.message});
        }
    });
    document.getElementById('locationSearch').addEventListener('input', renderLocations);
    document.getElementById('globalLocationSearch').addEventListener('input', event => { document.getElementById('locationSearch').value=event.target.value; renderLocations(); });
    document.getElementById('locationRows').addEventListener('click', async event => { const row=event.target.closest('[data-location-id]'); if(!row)return; try { await selectLocation(row.dataset.locationId); } catch(error) { Swal.fire({icon:'error', title:'Không chuyển được vị trí', text:error.message}); } });
    document.getElementById('showExpected').addEventListener('change', renderCountLines);
    document.getElementById('saveLocationBtn').addEventListener('click', () => saveLocation().catch(error => Swal.fire({icon:'error', title:'Không lưu được', text:error.message})));
    document.getElementById('completeLocationBtn').addEventListener('click', () => completeLocation().catch(error => Swal.fire({icon:'error', title:'Không hoàn tất được', text:error.message})));
    document.getElementById('reopenLocationBtn').addEventListener('click', async () => { await api(`/api/dot-kiem-ke/${activeSession.id}/vi-tri/${activeLocation.id}/mo-lai`, {method:'POST'}); await loadSession(activeSession.id); });
    document.getElementById('completeSessionBtn').addEventListener('click', async () => { try { const result=await api(`/api/dot-kiem-ke/${activeSession.id}/hoan-tat`, {method:'POST'}); await loadSession(activeSession.id); Swal.fire({icon:'success', title:'Đã hoàn tất kiểm đếm', text:result.message}); } catch(error) { Swal.fire({icon:'warning', title:'Chưa thể hoàn tất', text:error.message}); } });
    document.getElementById('postSessionBtn').addEventListener('click', async () => {
        const answer = await Swal.fire({icon:'warning', title:'Chốt và áp dụng chênh lệch?', text:'Chỉ các vị trí đã hoàn tất kiểm kê được áp dụng. Vị trí chưa kiểm sẽ được giữ nguyên tồn.', showCancelButton:true, confirmButtonText:'Chốt & áp dụng', cancelButtonText:'Xem lại'});
        if (!answer.isConfirmed) return;
        setLoading(true, 'Đang chốt số kiểm kê...');
        try {
            if (activeSession.status === 'counting') {
                await api(`/api/dot-kiem-ke/${activeSession.id}/hoan-tat`, {method:'POST'});
            }
            const result = await api(`/api/dot-kiem-ke/${activeSession.id}/ap-dung`, {method:'POST'});
            await loadSessions(activeSession.id);
            Swal.fire({icon:'success', title:'Đã chốt tồn', text:result.message});
        } catch(error) {
            Swal.fire({icon:'error', title:'Không chốt được', text:error.message});
        } finally {
            setLoading(false);
        }
    });
    document.getElementById('deleteSessionBtn').addEventListener('click', async () => { const answer=await Swal.fire({icon:'warning',title:'Xóa đợt kiểm kê?',text:'Toàn bộ số đếm nháp sẽ bị xóa.',showCancelButton:true,confirmButtonText:'Xóa đợt',cancelButtonText:'Hủy'}); if(!answer.isConfirmed)return; await api(`/api/dot-kiem-ke/${activeSession.id}`,{method:'DELETE'}); activeSession=null;activeLocation=null;await loadSessions(); });
    document.getElementById('newItemCode').addEventListener('input', event => { clearTimeout(event.target._timer); event.target._timer=setTimeout(()=>searchCatalog(event.target.value.trim()),220); });
    document.getElementById('newItemCode').addEventListener('change', event => { const code=event.target.value.trim().toUpperCase(); const item=catalogItems.find(x=>String(x.code||x.value||'').toUpperCase()===code); if(item){document.getElementById('newItemName').value=item.name||'';document.getElementById('newItemUnit').value=item.unit||'';document.getElementById('newItemWeight').value=item.weight_per_unit_grams||'';document.getElementById('newItemSize').value=item.size||'';document.getElementById('newItemColor').value=item.color||'';document.getElementById('newItemSide').value=item.side||'';} });
    document.getElementById('addCountLineBtn').addEventListener('click', () => {
        if (!activeLocation) return;
        const field = id => document.getElementById(id);
        const code = field('newItemCode').value.trim().toUpperCase();
        const quantityInput = field('newItemQuantity').value;
        const kilogramsInput = field('newItemKg').value;
        if (!code || (quantityInput === '' && kilogramsInput === '')) return Swal.fire({icon:'warning', title:'Thiếu dữ liệu', text:'Cần mã nội bộ và nhập Thêm SL hoặc Thêm KG.'});
        if (quantityInput !== '' && kilogramsInput !== '') return Swal.fire({icon:'warning', title:'Chọn một cách nhập', text:'Mỗi lần chỉ nhập Số lượng hoặc KG để tránh tính trùng một kiện.'});

        const unit = field('newItemUnit').value.trim().toUpperCase();
        const norm = Number(field('newItemWeight').value || 0);
        const addedWeight = kilogramsInput === '' ? null : Number(kilogramsInput);
        if (addedWeight !== null && unit !== 'KG' && !(norm > 0)) {
            return Swal.fire({icon:'warning', title:'Thiếu định mức', text:`Mã ${code} chưa có DINH_MUC (gam/${unit || 'ĐVT'}), chưa thể đổi ${num(addedWeight)} kg.`});
        }
        const addedQuantity = quantityInput !== ''
            ? Number(quantityInput)
            : inputNumber(unit === 'KG' ? addedWeight : addedWeight * 1000 / norm);
        const newEntry = {
            id:null,
            input_type:addedWeight === null ? 'base' : 'kg',
            input_quantity:addedWeight === null ? Number(quantityInput) : addedWeight,
            input_unit:addedWeight === null ? unit : 'KG',
            converted_quantity:addedQuantity,
            weight_kg:addedWeight,
            note:''
        };

        const candidate = {
            id:null, internal_item_code:code, item_name:field('newItemName').value.trim(), unit, weight_per_unit_grams:norm || null,
            size:field('newItemSize').value.trim(), color:field('newItemColor').value.trim(), side:field('newItemSide').value.trim(),
            ma_hh:'', expected_quantity:0, counted_quantity:addedQuantity, counted_weight_kg:addedWeight, entries:[newEntry],
            catalog_exists:catalogItems.some(x => String(x.code || x.value || '').toUpperCase() === code)
        };
        const normalize = value => String(value || '').trim().toUpperCase();
        const existing = activeLines.find(line => normalize(line.internal_item_code) === normalize(candidate.internal_item_code)
            && normalize(line.size) === normalize(candidate.size)
            && normalize(line.color) === normalize(candidate.color)
            && normalize(line.side) === normalize(candidate.side));

        if (existing) {
            existing.entries = Array.isArray(existing.entries) ? existing.entries : [];
            existing.entries.push(newEntry);
            recalcCountLine(existing);
            existing.item_name = existing.item_name || candidate.item_name;
            existing.unit = existing.unit || candidate.unit;
            existing.weight_per_unit_grams = existing.weight_per_unit_grams || candidate.weight_per_unit_grams;
            const detail = addedWeight !== null
                ? `${num(addedWeight)} kg → ${num(addedQuantity)} ${unit}`
                : `${num(addedQuantity)} ${unit}`;
            Swal.fire({toast:true, position:'top-end', icon:'success', title:`Đã thêm ${detail}. Tổng: ${num(existing.counted_quantity)} ${unit}`, timer:2400, showConfirmButton:false});
        } else {
            activeLines.push(candidate);
        }
        isDirty = true;
        ['newItemCode','newItemName','newItemSize','newItemColor','newItemSide','newItemUnit','newItemWeight','newItemQuantity','newItemKg'].forEach(id => field(id).value = '');
        renderCountLines();
    });
    document.getElementById('countRows').addEventListener('input', event => {
        const row = event.target.closest('tr[data-line-index]');
        if (!row) return;
        const line = activeLines[Number(row.dataset.lineIndex)];
        const entry = line.entries[Number(row.dataset.entryIndex)];
        if (!entry) return;
        if (event.target.classList.contains('entry-quantity')) {
            entry.input_quantity = Number(event.target.value || 0);
            entry.converted_quantity = entry.input_quantity;
        }
        if (event.target.classList.contains('entry-weight')) {
            entry.input_quantity = Number(event.target.value || 0);
            entry.weight_kg = entry.input_quantity;
            const unit = String(line.unit || '').trim().toUpperCase();
            const norm = Number(line.weight_per_unit_grams || 0);
            entry.converted_quantity = unit === 'KG' ? entry.input_quantity : entry.input_quantity * 1000 / norm;
        }
        if (event.target.classList.contains('entry-note')) entry.note = event.target.value;
        recalcCountLine(line);
        isDirty = true;
    });
    document.getElementById('countRows').addEventListener('change', event => {
        if (event.target.matches('.entry-quantity,.entry-weight')) renderCountLines();
    });
    document.getElementById('countRows').addEventListener('click', event => {
        const button = event.target.closest('.remove-entry');
        if (!button) return;
        const row = button.closest('tr[data-line-index]');
        const line = activeLines[Number(row.dataset.lineIndex)];
        line.entries.splice(Number(row.dataset.entryIndex), 1);
        recalcCountLine(line);
        isDirty = true;
        renderCountLines();
    });
    document.getElementById('countRows').addEventListener('keydown', event => { if(event.key!=='Enter'||!event.target.classList.contains('count-input'))return; event.preventDefault(); const inputs=[...document.querySelectorAll('.count-input:not(:disabled)')]; const index=inputs.indexOf(event.target); if(inputs[index+1]){inputs[index+1].focus();inputs[index+1].select();}else document.getElementById('saveLocationBtn').focus(); });

    window.addEventListener('beforeunload', event => { if(!isDirty)return; event.preventDefault(); event.returnValue=''; });

    loadSessions().catch(error => Swal.fire({icon:'error', title:'Không tải được kiểm kê', text:error.message}));
</script>
</body>
</html>
