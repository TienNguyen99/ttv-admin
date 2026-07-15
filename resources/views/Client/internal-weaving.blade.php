<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Lệnh dệt và định mức sợi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/warehouse-wms.css') }}?v={{ filemtime(public_path('css/warehouse-wms.css')) }}" rel="stylesheet">
    <style>
        .weaving-grid { display:grid; grid-template-columns:minmax(0, 1fr) 420px; gap:16px; align-items:start; }
        .weaving-tabs { display:flex; gap:8px; flex-wrap:wrap; }
        .weaving-tab { border:1px solid var(--wms-line, #dbe3ef); background:#fff; color:#0f2747; border-radius:8px; padding:8px 12px; font-weight:800; }
        .weaving-tab.is-active { background:#2563eb; color:#fff; border-color:#2563eb; }
        .weaving-pane { display:none; }
        .weaving-pane.is-active { display:block; }
        .weaving-table { min-width:1080px; }
        .weaving-plan-table { min-width:920px; }
        .weaving-import { min-height:96px; font-family:Consolas, monospace; font-size:12px; }
        .weaving-location-list { display:flex; gap:4px; flex-wrap:wrap; }
        .weaving-location { border:1px solid #bfdbfe; background:#eff6ff; color:#1d4ed8; border-radius:999px; padding:2px 8px; font-size:12px; font-weight:800; }
        .weaving-ok { color:#047857; background:#ecfdf5; border:1px solid #a7f3d0; border-radius:999px; padding:3px 8px; font-size:12px; font-weight:900; }
        .weaving-short { color:#b91c1c; background:#fef2f2; border:1px solid #fecaca; border-radius:999px; padding:3px 8px; font-size:12px; font-weight:900; }
        .weaving-help { color:#64748b; font-size:12px; line-height:1.45; }
        @media (max-width: 1180px) { .weaving-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
@include('layouts.partials.sidebar')

<header class="wms-topbar">
    <h1 class="wms-topbar__title">WMS May Mặc</h1>
    <form class="wms-global-search" onsubmit="return false">
        <i data-lucide="search"></i>
        <input id="topKeyword" aria-label="Tìm lệnh sản xuất" placeholder="Tìm lệnh SX, mã hàng, khách, tên sợi...">
    </form>
    <a class="wms-btn" href="{{ url('/client/xuat-vat-tu-noi-bo') }}"><i data-lucide="package-minus"></i>Phiếu xuất</a>
</header>

<main class="wms-page">
    <div class="wms-heading">
        <div>
            <h1>Lệnh SX / định mức sợi</h1>
            <p>Dùng lệnh sản xuất đã đồng bộ sẵn. Chỉ cần khai báo định mức theo mã hàng, hệ thống tự tính sợi và kệ để xuất.</p>
        </div>
        <button id="reloadBtn" class="wms-btn" type="button"><i data-lucide="refresh-cw"></i>Tải lại</button>
    </div>

    <section class="wms-kpi-grid mb-3">
        <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="shirt"></i></div><div><div class="wms-kpi__label">Mã hàng dệt</div><div id="kpiItems" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Danh mục chuẩn</div></div></article>
        <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="list-tree"></i></div><div><div class="wms-kpi__label">Dòng định mức</div><div id="kpiBoms" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Sợi / mã hàng</div></div></article>
        <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="clipboard-list"></i></div><div><div class="wms-kpi__label">Lệnh SX</div><div id="kpiOrders" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Từ Google Sheet SX</div></div></article>
        <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="rows-3"></i></div><div><div class="wms-kpi__label">Dòng SX</div><div id="kpiIssued" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Chi tiết trong lệnh</div></div></article>
    </section>

    <div class="weaving-tabs mb-3" role="tablist">
        <button class="weaving-tab is-active" type="button" data-pane="ordersPane"><i data-lucide="clipboard-list"></i> Lệnh SX có sẵn</button>
        <button class="weaving-tab" type="button" data-pane="itemsPane"><i data-lucide="book-open"></i> Mã hàng dệt</button>
        <button class="weaving-tab" type="button" data-pane="bomPane"><i data-lucide="list-tree"></i> Định mức sợi</button>
    </div>

    <div class="weaving-grid">
        <div>
            <section id="ordersPane" class="weaving-pane is-active">
                <div class="wms-panel mb-3">
                    <div class="wms-panel__header">
                        <div><h2>Lệnh sản xuất đã đồng bộ</h2><p class="weaving-help mb-0">Nguồn lấy từ module Lệnh sản xuất. Không cần nhập lại lệnh, chỉ cần có định mức cho mã hàng trong lệnh.</p></div>
                    </div>
                    <div class="wms-filterbar" style="grid-template-columns:minmax(260px,1fr) 160px auto">
                        <div><label>Tìm lệnh / mã hàng</label><input id="orderKeyword" class="form-control"></div>
                        <div><label>Trạng thái SX</label><select id="orderStatus" class="form-select"><option value="">Tất cả</option><option value="pending">Pending</option><option value="late">Late</option><option value="due">Due</option></select></div>
                        <button id="clearOrderFilter" class="wms-btn" type="button">Xóa lọc</button>
                    </div>
                    <div class="wms-table-wrap">
                        <table class="wms-table weaving-table">
                            <thead><tr><th>Lệnh SX</th><th>Mã hàng</th><th>Tên hàng</th><th>Khách</th><th class="text-end">Tổng SL</th><th>Dòng</th><th>Ngày nhận</th><th>Hạn</th><th class="text-end">Thao tác</th></tr></thead>
                            <tbody id="orderRows"><tr><td colspan="9" class="wms-loading">Đang tải...</td></tr></tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span id="orderPageLabel" class="small text-secondary">Trang 1 / 1</span>
                        <div class="d-flex gap-2"><button id="orderPrev" class="wms-btn" type="button">Trước</button><button id="orderNext" class="wms-btn" type="button">Sau</button></div>
                    </div>
                </div>
            </section>

            <section id="itemsPane" class="weaving-pane">
                <div class="wms-panel mb-3">
                    <div class="wms-panel__header">
                        <div><h2>Mã hàng dệt</h2><p class="weaving-help mb-0">Paste Excel: Mã hàng dệt | Tên hàng | Khách | ĐVT | Ghi chú</p></div>
                        <button id="importItemsBtn" class="wms-btn wms-btn--primary" type="button"><i data-lucide="upload"></i>Import mã hàng</button>
                    </div>
                    <textarea id="itemsImportText" class="form-control weaving-import mb-2" placeholder="Paste danh mục mã hàng dệt..."></textarea>
                    <div class="wms-filterbar" style="grid-template-columns:minmax(260px,1fr) auto">
                        <div><label>Tìm mã hàng dệt</label><input id="itemKeyword" class="form-control"></div>
                        <button id="clearItemFilter" class="wms-btn" type="button">Xóa lọc</button>
                    </div>
                    <div class="wms-table-wrap">
                        <table class="wms-table weaving-table">
                            <thead><tr><th>Mã hàng</th><th>Tên hàng</th><th>Khách</th><th>ĐVT</th><th class="text-end">Dòng định mức</th><th>Ghi chú</th></tr></thead>
                            <tbody id="itemRows"><tr><td colspan="6" class="wms-loading">Đang tải...</td></tr></tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span id="itemPageLabel" class="small text-secondary">Trang 1 / 1</span>
                        <div class="d-flex gap-2"><button id="itemPrev" class="wms-btn" type="button">Trước</button><button id="itemNext" class="wms-btn" type="button">Sau</button></div>
                    </div>
                </div>
            </section>

            <section id="bomPane" class="weaving-pane">
                <div class="wms-panel mb-3">
                    <div class="wms-panel__header">
                        <div><h2>Định mức sợi</h2><p class="weaving-help mb-0">Paste Excel: Mã hàng dệt | Tên hàng dệt | Mã sợi | Tên sợi | Định mức | ĐVT | Hao hụt % | Ghi chú</p></div>
                        <button id="importBomBtn" class="wms-btn wms-btn--primary" type="button"><i data-lucide="upload"></i>Import định mức</button>
                    </div>
                    <textarea id="bomImportText" class="form-control weaving-import mb-2" placeholder="Paste định mức sợi..."></textarea>
                    <div class="wms-filterbar" style="grid-template-columns:minmax(260px,1fr) auto">
                        <div><label>Mã hàng dệt cần xem định mức</label><input id="bomItemCode" class="form-control" placeholder="VD: STYLE-001"></div>
                        <button id="loadBomBtn" class="wms-btn" type="button">Xem định mức</button>
                    </div>
                    <div class="wms-table-wrap">
                        <table class="wms-table weaving-table">
                            <thead><tr><th>Mã sợi</th><th>Tên sợi theo danh mục</th><th>ĐVT</th><th>Kệ DM</th><th class="text-end">Định mức</th><th class="text-end">Hao hụt %</th><th>Liên kết</th><th>Ghi chú</th></tr></thead>
                            <tbody id="bomRows"><tr><td colspan="8" class="wms-empty">Nhập mã hàng dệt để xem định mức.</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <aside class="wms-panel">
            <div class="wms-panel__header">
                <div><h2>Soạn sợi theo lệnh SX</h2><p id="planTitle" class="weaving-help mb-0">Chọn một lệnh sản xuất để xem sợi cần lấy.</p></div>
                <button id="createIssueBtn" class="wms-btn wms-btn--primary" type="button" disabled><i data-lucide="send"></i>Tạo phiếu xuất</button>
            </div>
            <div id="planSummary" class="d-flex flex-wrap gap-2 mb-2"></div>
            <div class="wms-table-wrap">
                <table class="wms-table weaving-plan-table">
                    <thead><tr><th>Mã sợi</th><th>Tên sợi theo danh mục</th><th class="text-end">Cần</th><th class="text-end">Tồn</th><th>Kệ</th><th>Trạng thái</th></tr></thead>
                    <tbody id="planRows"><tr><td colspan="6" class="wms-empty">Chưa chọn lệnh.</td></tr></tbody>
                </table>
            </div>
        </aside>
    </div>
</main>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
const num = value => Number(value || 0).toLocaleString('vi-VN', {maximumFractionDigits:3});
const dateText = value => value ? String(value).slice(0, 10).split('-').reverse().join('/') : '-';
let orderPage = 1, orderTotalPages = 1, itemPage = 1, itemTotalPages = 1, currentProductionOrder = null, timer = null;

function jsonOrError(response, fallback) {
    return response.json().catch(() => ({})).then(result => {
        if (response.ok) return result;
        const errors = result.errors ? Object.values(result.errors).flat().join('\n') : '';
        throw new Error([result.message || fallback, errors].filter(Boolean).join('\n'));
    });
}

function api(url, options = {}) {
    return fetch(url, {
        ...options,
        headers: {
            'Accept': 'application/json',
            ...(options.body ? {'Content-Type': 'application/json'} : {}),
            'X-CSRF-TOKEN': csrfToken,
            ...(options.headers || {})
        }
    }).then(response => jsonOrError(response, 'Không xử lý được yêu cầu.'));
}

function loadItems() {
    const params = new URLSearchParams({page: itemPage, per_page: 50});
    const keyword = document.getElementById('itemKeyword').value.trim();
    if (keyword) params.set('keyword', keyword);
    document.getElementById('itemRows').innerHTML = '<tr><td colspan="6" class="wms-loading">Đang tải...</td></tr>';
    api('/api/lenh-det/items?' + params).then(result => {
        const summary = result.summary || {};
        const pagination = result.pagination || {};
        itemPage = Number(pagination.page || 1);
        itemTotalPages = Number(pagination.total_pages || 1);
        document.getElementById('kpiItems').textContent = num(summary.item_count);
        document.getElementById('kpiBoms').textContent = num(summary.bom_count);
        document.getElementById('itemPageLabel').textContent = `Trang ${num(itemPage)} / ${num(itemTotalPages)}`;
        document.getElementById('itemPrev').disabled = itemPage <= 1;
        document.getElementById('itemNext').disabled = !pagination.has_more;
        document.getElementById('itemRows').innerHTML = (result.data || []).map(row => `
            <tr>
                <td class="wms-code">${esc(row.item_code)}</td>
                <td>${esc(row.item_name || '-')}</td>
                <td>${esc(row.customer || '-')}</td>
                <td>${esc(row.unit || '-')}</td>
                <td class="wms-number">${num(row.boms_count)}</td>
                <td>${esc(row.note || '')}</td>
            </tr>
        `).join('') || '<tr><td colspan="6" class="wms-empty">Không có mã hàng dệt.</td></tr>';
    }).catch(error => document.getElementById('itemRows').innerHTML = `<tr><td colspan="6" class="wms-empty text-danger">${esc(error.message)}</td></tr>`);
}

function loadOrders() {
    const params = new URLSearchParams({page: orderPage, per_page: 50});
    const keyword = document.getElementById('orderKeyword').value.trim();
    const topKeyword = document.getElementById('topKeyword').value.trim();
    const status = document.getElementById('orderStatus').value;
    if (keyword || topKeyword) params.set('keyword', keyword || topKeyword);
    if (status) params.set('status', status);
    document.getElementById('orderRows').innerHTML = '<tr><td colspan="9" class="wms-loading">Đang tải...</td></tr>';
    api('/api/lenh-det/production-orders?' + params).then(result => {
        const summary = result.summary || {};
        const pagination = result.pagination || {};
        orderPage = Number(pagination.page || 1);
        orderTotalPages = Number(pagination.total_pages || 1);
        document.getElementById('kpiOrders').textContent = num(summary.order_count);
        document.getElementById('kpiIssued').textContent = num(summary.line_count);
        document.getElementById('orderPageLabel').textContent = `Trang ${num(orderPage)} / ${num(orderTotalPages)}`;
        document.getElementById('orderPrev').disabled = orderPage <= 1;
        document.getElementById('orderNext').disabled = !pagination.has_more;
        document.getElementById('orderRows').innerHTML = (result.data || []).map(row => `
            <tr>
                <td class="wms-code">${esc(row.production_order)}</td>
                <td>${esc(row.item_code)}</td>
                <td>${esc(row.description || '-')}</td>
                <td>${esc(row.customer || '-')}</td>
                <td class="wms-number">${num(row.planned_quantity)} ${esc(row.unit || '')}</td>
                <td>${num(row.line_count)} dòng / ${num(row.item_count)} mã</td>
                <td>${dateText(row.received_date)}</td>
                <td>${dateText(row.promised_date)}</td>
                <td class="text-end"><button class="btn btn-sm btn-outline-primary plan-order-btn" data-code="${esc(row.production_order)}">Soạn sợi</button></td>
            </tr>
        `).join('') || '<tr><td colspan="9" class="wms-empty">Không có lệnh sản xuất.</td></tr>';
    }).catch(error => document.getElementById('orderRows').innerHTML = `<tr><td colspan="9" class="wms-empty text-danger">${esc(error.message)}</td></tr>`);
}

function loadBom() {
    const code = document.getElementById('bomItemCode').value.trim();
    if (!code) return;
    document.getElementById('bomRows').innerHTML = '<tr><td colspan="8" class="wms-loading">Đang tải...</td></tr>';
    api('/api/lenh-det/boms?item_code=' + encodeURIComponent(code)).then(result => {
        document.getElementById('bomRows').innerHTML = (result.data || []).map(row => `
            <tr>
                <td class="wms-code">${esc(row.material_code)}</td>
                <td>${esc(row.catalog_name || row.material_name || '-')}</td>
                <td>${esc(row.unit || '-')}</td>
                <td>${esc(row.catalog_shelf_code || '-')}</td>
                <td class="wms-number">${num(row.consumption_per_unit)}</td>
                <td class="wms-number">${num(row.waste_percent)}</td>
                <td><span class="${row.catalog_exists ? 'weaving-ok' : 'weaving-short'}">${row.catalog_exists ? 'Có DM' : 'Thiếu DM'}</span></td>
                <td>${esc(row.note || '')}</td>
            </tr>
        `).join('') || '<tr><td colspan="8" class="wms-empty">Mã hàng này chưa có định mức.</td></tr>';
    });
}

function loadPlan(code) {
    currentProductionOrder = code;
    document.getElementById('planTitle').textContent = `Lệnh ${code}`;
    document.getElementById('planRows').innerHTML = '<tr><td colspan="6" class="wms-loading">Đang tính tồn và kệ...</td></tr>';
    document.getElementById('createIssueBtn').disabled = true;
    api(`/api/lenh-det/production-order-plan?production_order=${encodeURIComponent(code)}`).then(result => {
        const summary = result.summary || {};
        document.getElementById('planSummary').innerHTML = `
            <span class="weaving-location">${num(summary.line_count)} dòng sợi</span>
            <span class="weaving-location">Cần ${num(summary.required_quantity)}</span>
            <span class="${summary.short_count ? 'weaving-short' : 'weaving-ok'}">${summary.short_count ? `Thiếu ${num(summary.short_count)} mã` : 'Đủ tồn'}</span>
            ${summary.missing_bom_items?.length ? `<span class="weaving-short">Thiếu định mức: ${esc(summary.missing_bom_items.slice(0, 5).join(', '))}</span>` : ''}
        `;
        const hasMissingCatalog = (result.data || []).some(row => !row.catalog_exists);
        document.getElementById('createIssueBtn').disabled = !(result.data || []).length || hasMissingCatalog;
        document.getElementById('planRows').innerHTML = (result.data || []).map(row => {
            const locations = (row.locations || []).slice(0, 4).map(location => `<span class="weaving-location">${esc(location.location_code)}: ${num(location.quantity)}</span>`).join('');
            return `
                <tr>
                    <td class="wms-code">${esc(row.material_code)}</td>
                    <td>${esc(row.catalog_name || row.material_name || '-')}</td>
                    <td class="wms-number">${num(row.required_quantity)} ${esc(row.unit || '')}</td>
                    <td class="wms-number">${num(row.stock_quantity)}</td>
                    <td><div class="weaving-location-list">${locations || '<span class="weaving-short">Chưa có kệ</span>'}</div></td>
                    <td><span class="${!row.catalog_exists || row.status !== 'enough' ? 'weaving-short' : 'weaving-ok'}">${!row.catalog_exists ? 'Thiếu danh mục' : (row.status === 'enough' ? 'Đủ' : `Thiếu ${num(row.shortage_quantity)}`)}</span></td>
                </tr>
            `;
        }).join('') || '<tr><td colspan="6" class="wms-empty">Lệnh này chưa có định mức.</td></tr>';
    }).catch(error => document.getElementById('planRows').innerHTML = `<tr><td colspan="6" class="wms-empty text-danger">${esc(error.message)}</td></tr>`);
}

function postImport(endpoint, textareaId, after) {
    const text = document.getElementById(textareaId).value.trim();
    if (!text) return alert('Chưa có dữ liệu paste.');
    api(endpoint, {method:'POST', body: JSON.stringify({text})})
        .then(result => {
            alert(result.message || 'Đã import.');
            document.getElementById(textareaId).value = '';
            after?.();
        })
        .catch(error => alert(error.message));
}

document.querySelectorAll('.weaving-tab').forEach(button => button.addEventListener('click', () => {
    document.querySelectorAll('.weaving-tab').forEach(x => x.classList.remove('is-active'));
    document.querySelectorAll('.weaving-pane').forEach(x => x.classList.remove('is-active'));
    button.classList.add('is-active');
    document.getElementById(button.dataset.pane).classList.add('is-active');
}));
document.getElementById('importItemsBtn').addEventListener('click', () => postImport('/api/lenh-det/items/import', 'itemsImportText', loadItems));
document.getElementById('importBomBtn').addEventListener('click', () => postImport('/api/lenh-det/boms/import', 'bomImportText', () => { loadItems(); loadBom(); }));
document.getElementById('loadBomBtn').addEventListener('click', loadBom);
document.getElementById('orderRows').addEventListener('click', event => {
    const button = event.target.closest('.plan-order-btn');
    if (button) loadPlan(button.dataset.code);
});
document.getElementById('createIssueBtn').addEventListener('click', () => {
    if (!currentProductionOrder) return;
    if (!confirm('Tạo phiếu xuất sợi cho lệnh sản xuất này?')) return;
    api('/api/lenh-det/production-order-issue', {method:'POST', body: JSON.stringify({production_order: currentProductionOrder})})
        .then(result => {
            alert(result.message || 'Đã tạo phiếu xuất.');
            if (result.print_url) window.open(result.print_url, '_blank');
            loadOrders();
        })
        .catch(error => alert(error.message));
});
document.getElementById('orderKeyword').addEventListener('input', () => { clearTimeout(timer); orderPage = 1; timer = setTimeout(loadOrders, 250); });
document.getElementById('topKeyword').addEventListener('input', event => { document.getElementById('orderKeyword').value = event.target.value; clearTimeout(timer); orderPage = 1; timer = setTimeout(loadOrders, 250); });
document.getElementById('orderStatus').addEventListener('change', () => { orderPage = 1; loadOrders(); });
document.getElementById('itemKeyword').addEventListener('input', () => { clearTimeout(timer); itemPage = 1; timer = setTimeout(loadItems, 250); });
document.getElementById('clearOrderFilter').addEventListener('click', () => { document.getElementById('orderKeyword').value = ''; document.getElementById('topKeyword').value = ''; document.getElementById('orderStatus').value = ''; orderPage = 1; loadOrders(); });
document.getElementById('clearItemFilter').addEventListener('click', () => { document.getElementById('itemKeyword').value = ''; itemPage = 1; loadItems(); });
document.getElementById('orderPrev').addEventListener('click', () => { if (orderPage > 1) { orderPage--; loadOrders(); } });
document.getElementById('orderNext').addEventListener('click', () => { if (orderPage < orderTotalPages) { orderPage++; loadOrders(); } });
document.getElementById('itemPrev').addEventListener('click', () => { if (itemPage > 1) { itemPage--; loadItems(); } });
document.getElementById('itemNext').addEventListener('click', () => { if (itemPage < itemTotalPages) { itemPage++; loadItems(); } });
document.getElementById('reloadBtn').addEventListener('click', () => { loadItems(); loadOrders(); if (currentProductionOrder) loadPlan(currentProductionOrder); });

loadItems();
loadOrders();
if (window.lucide) lucide.createIcons();
</script>
</body>
</html>
