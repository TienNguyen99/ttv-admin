<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Xuất Excel lệnh dệt</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/warehouse-wms.css') }}?v={{ filemtime(public_path('css/warehouse-wms.css')) }}" rel="stylesheet">
    <style>
        .export-filter { display:grid; grid-template-columns:150px minmax(190px,260px) minmax(260px,1fr) auto; gap:10px; align-items:end; }
        .export-toolbar { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:12px 16px; border-bottom:1px solid #dce8f6; background:#f8fbff; }
        .export-source { color:#316da9; font-size:12px; font-weight:750; }
        .export-table { min-width:980px; }
        .export-order { color:#145da7; font-family:Menlo,Consolas,monospace; font-weight:850; }
        .export-progress { display:none; padding:18px; border:1px solid #c9def7; border-radius:8px; background:#f4f9ff; }
        .export-progress.is-visible { display:block; }
        .export-progress .progress { height:10px; background:#dce9f8; }
        .export-progress .progress-bar { background:#4c8de8; transition:width .3s ease; }
        .export-result { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin-top:12px; }
        .export-stat { padding:10px 12px; border:1px solid #d8e5f4; border-radius:7px; background:#fff; }
        .export-stat span { display:block; color:#6d7f95; font-size:11px; font-weight:700; }
        .export-stat strong { display:block; margin-top:2px; color:#17375e; font-size:20px; }
        .export-errors { max-height:160px; overflow:auto; margin-top:12px; font-size:12px; }
        .export-empty { padding:36px 16px !important; color:#6d7f95 !important; text-align:center; }
        @media (max-width:900px) { .export-filter { grid-template-columns:1fr 1fr; } }
        @media (max-width:620px) { .export-filter,.export-result { grid-template-columns:1fr; } .export-toolbar { align-items:flex-start; flex-direction:column; } }
    </style>
</head>
<body>
@include('layouts.partials.sidebar')

<header class="wms-topbar">
    <h1 class="wms-topbar__title">WMS May Mặc</h1>
    <div class="wms-global-search">
        <i data-lucide="file-archive"></i>
        <span>Xuất Excel lệnh dệt</span>
    </div>
    <a class="wms-btn" href="{{ route('weaving.dashboard') }}"><i data-lucide="layout-dashboard"></i>Tổng quan</a>
</header>

<main class="wms-page">
    <div class="wms-heading">
        <div>
            <h1>Xuất lệnh dệt hàng loạt</h1>
            <p>Chỉ hiển thị lệnh đã có định mức sợi/vật tư hợp lệ và đóng ZIP theo khách hàng.</p>
        </div>
        <button id="exportBtn" class="wms-btn wms-btn--primary" type="button" disabled>
            <i data-lucide="archive"></i>Tạo ZIP
        </button>
    </div>

    <section class="wms-panel mb-3">
        <div class="export-filter">
            <div>
                <label for="year">Năm lệnh</label>
                <select id="year" class="form-select">
                    @for ($year = (int) now('Asia/Ho_Chi_Minh')->format('Y'); $year >= 2023; $year--)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <label for="customer">Khách hàng</label>
                <select id="customer" class="form-select"><option value="">Tất cả khách hàng</option></select>
            </div>
            <div>
                <label for="keyword">Tìm lệnh hoặc mã hàng</label>
                <input id="keyword" class="form-control" placeholder="Ví dụ M-01959/26 hoặc 302625-01">
            </div>
            <button id="reloadBtn" class="wms-btn" type="button"><i data-lucide="search"></i>Tìm</button>
        </div>
    </section>

    <section id="progressPanel" class="export-progress mb-3" aria-live="polite">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
            <strong id="progressTitle">Đang tạo file...</strong>
            <span id="progressPercent">0%</span>
        </div>
        <div class="progress"><div id="progressBar" class="progress-bar" style="width:0%"></div></div>
        <div class="export-result">
            <div class="export-stat"><span>Tổng lệnh</span><strong id="statTotal">0</strong></div>
            <div class="export-stat"><span>Đã xử lý</span><strong id="statProcessed">0</strong></div>
            <div class="export-stat"><span>Thành công</span><strong id="statSuccess">0</strong></div>
            <div class="export-stat"><span>Lỗi</span><strong id="statFailed">0</strong></div>
        </div>
        <div id="errorList" class="export-errors"></div>
        <a id="downloadBtn" class="wms-btn wms-btn--primary mt-3 d-none" href="#">
            <i data-lucide="download"></i>Tải file ZIP
        </a>
    </section>

    <section class="wms-panel p-0 overflow-hidden">
        <div class="export-toolbar">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <label class="d-flex align-items-center gap-2 mb-0">
                    <input id="selectPage" class="form-check-input mt-0" type="checkbox">
                    <span>Chọn trang này</span>
                </label>
                <label class="d-flex align-items-center gap-2 mb-0">
                    <input id="selectAllResults" class="form-check-input mt-0" type="checkbox">
                    <span>Chọn toàn bộ kết quả</span>
                </label>
                <strong id="selectionLabel">Đã chọn 0 lệnh</strong>
            </div>
            <span class="export-source"><i data-lucide="database"></i> Khách hàng: Lệnh SX trung tâm</span>
        </div>
        <div class="wms-table-wrap">
            <table class="wms-table export-table">
                <thead>
                    <tr>
                        <th style="width:42px"></th>
                        <th>Lệnh sản xuất</th>
                        <th>Khách hàng</th>
                        <th>Mã hàng</th>
                        <th>Tên hàng</th>
                        <th class="text-end">Số lượng</th>
                        <th>Ngày nhận</th>
                        <th>Hạn giao</th>
                    </tr>
                </thead>
                <tbody id="rows"><tr><td colspan="8" class="export-empty">Đang tải...</td></tr></tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center gap-2 p-3 border-top">
            <span id="pageLabel" class="text-secondary small">Trang 1 / 1</span>
            <div class="d-flex gap-2">
                <button id="prevBtn" class="wms-btn" type="button">Trước</button>
                <button id="nextBtn" class="wms-btn" type="button">Sau</button>
            </div>
        </div>
    </section>
</main>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const rowsEl = document.getElementById('rows');
const yearEl = document.getElementById('year');
const customerEl = document.getElementById('customer');
const keywordEl = document.getElementById('keyword');
const selectPageEl = document.getElementById('selectPage');
const selectAllResultsEl = document.getElementById('selectAllResults');
const exportBtn = document.getElementById('exportBtn');
const selected = new Set();
let page = 1;
let totalPages = 1;
let currentRows = [];
let loading = false;

const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
const number = value => Number(value || 0).toLocaleString('vi-VN', {maximumFractionDigits:3});
const date = value => value ? new Date(value + 'T00:00:00').toLocaleDateString('vi-VN') : '-';

async function request(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            ...(options.headers || {}),
        },
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.message || `Lỗi HTTP ${response.status}`);
    return data;
}

function queryString() {
    return new URLSearchParams({
        page,
        per_page: 100,
        year: yearEl.value,
        customer: customerEl.value,
        keyword: keywordEl.value.trim(),
        has_bom: 1,
    });
}

async function loadRows(resetSelection = false) {
    if (loading) return;
    loading = true;
    rowsEl.innerHTML = '<tr><td colspan="8" class="export-empty">Đang tải...</td></tr>';
    if (resetSelection) {
        selected.clear();
        selectAllResultsEl.checked = false;
    }
    try {
        const data = await request(`/api/lenh-det/production-orders?${queryString()}`);
        currentRows = data.data || [];
        totalPages = Math.max(1, Number(data.pagination?.last_page || 1));
        renderCustomers(data.customers || []);
        renderRows();
    } catch (error) {
        currentRows = [];
        rowsEl.innerHTML = `<tr><td colspan="8" class="export-empty text-danger">${esc(error.message)}</td></tr>`;
    } finally {
        loading = false;
        updateSelection();
    }
}

function renderCustomers(customers) {
    const current = customerEl.value;
    customerEl.innerHTML = '<option value="">Tất cả khách hàng</option>' +
        customers.map(customer => `<option value="${esc(customer)}">${esc(customer)}</option>`).join('');
    customerEl.value = customers.includes(current) ? current : '';
}

function renderRows() {
    if (!currentRows.length) {
        rowsEl.innerHTML = '<tr><td colspan="8" class="export-empty">Không có lệnh đã khai báo định mức sợi/vật tư.</td></tr>';
    } else {
        rowsEl.innerHTML = currentRows.map(row => `
            <tr>
                <td><input class="form-check-input order-check" type="checkbox" value="${esc(row.production_order)}" ${selected.has(row.production_order) ? 'checked' : ''}></td>
                <td><span class="export-order">${esc(row.production_order)}</span><div class="text-secondary small">${number(row.line_count)} dòng</div></td>
                <td><strong>${esc(row.customer || 'Chưa xác định')}</strong></td>
                <td>${esc(row.item_code || '-')}</td>
                <td>${esc(row.description || '-')}</td>
                <td class="text-end fw-bold">${number(row.planned_quantity)} ${esc(row.unit)}</td>
                <td>${date(row.received_date)}</td>
                <td>${date(row.promised_date)}</td>
            </tr>
        `).join('');
        document.querySelectorAll('.order-check').forEach(input => input.addEventListener('change', () => {
            input.checked ? selected.add(input.value) : selected.delete(input.value);
            selectAllResultsEl.checked = false;
            updateSelection();
        }));
    }
    document.getElementById('pageLabel').textContent = `Trang ${page} / ${totalPages}`;
    document.getElementById('prevBtn').disabled = page <= 1;
    document.getElementById('nextBtn').disabled = page >= totalPages;
    lucide.createIcons();
}

function updateSelection() {
    const pageCodes = currentRows.map(row => row.production_order);
    selectPageEl.checked = pageCodes.length > 0 && pageCodes.every(code => selected.has(code));
    const countText = selectAllResultsEl.checked ? 'toàn bộ kết quả' : `${selected.size} lệnh`;
    document.getElementById('selectionLabel').textContent = `Đã chọn ${countText}`;
    exportBtn.disabled = !selectAllResultsEl.checked && selected.size === 0;
}

function updateProgress(data) {
    document.getElementById('progressPanel').classList.add('is-visible');
    const percent = data.total ? Math.round(data.processed * 100 / data.total) : 0;
    document.getElementById('progressBar').style.width = `${percent}%`;
    document.getElementById('progressPercent').textContent = `${percent}%`;
    document.getElementById('statTotal').textContent = number(data.total);
    document.getElementById('statProcessed').textContent = number(data.processed);
    document.getElementById('statSuccess').textContent = number(data.success);
    document.getElementById('statFailed').textContent = number(data.failed);
    document.getElementById('progressTitle').textContent = data.status === 'completed' ? 'Đã tạo xong file ZIP' : 'Đang tạo file Excel...';
    document.getElementById('errorList').innerHTML = (data.errors || []).map(error =>
        `<div class="text-danger"><strong>${esc(error.production_order)}</strong> · ${esc(error.customer)}: ${esc(error.message)}</div>`
    ).join('');
    const download = document.getElementById('downloadBtn');
    if (data.download_url) {
        download.href = data.download_url;
        download.classList.remove('d-none');
    }
    lucide.createIcons();
}

async function startExport() {
    exportBtn.disabled = true;
    document.getElementById('downloadBtn').classList.add('d-none');
    try {
        let batch = await request('/api/lenh-det/batch-exports', {
            method: 'POST',
            body: JSON.stringify({
                select_all: selectAllResultsEl.checked,
                production_orders: selectAllResultsEl.checked ? [] : Array.from(selected),
                year: Number(yearEl.value),
                customer: customerEl.value,
                keyword: keywordEl.value.trim(),
            }),
        });
        updateProgress(batch);
        while (batch.status !== 'completed') {
            batch = await request(`/api/lenh-det/batch-exports/${batch.token}/process`, {
                method: 'POST',
                body: '{}',
            });
            updateProgress(batch);
        }
    } catch (error) {
        document.getElementById('progressPanel').classList.add('is-visible');
        document.getElementById('progressTitle').textContent = error.message;
        document.getElementById('progressTitle').classList.add('text-danger');
    } finally {
        exportBtn.disabled = false;
    }
}

selectPageEl.addEventListener('change', () => {
    currentRows.forEach(row => selectPageEl.checked ? selected.add(row.production_order) : selected.delete(row.production_order));
    selectAllResultsEl.checked = false;
    renderRows();
    updateSelection();
});
selectAllResultsEl.addEventListener('change', () => {
    if (selectAllResultsEl.checked) selected.clear();
    renderRows();
    updateSelection();
});
document.getElementById('reloadBtn').addEventListener('click', () => { page = 1; loadRows(true); });
keywordEl.addEventListener('keydown', event => { if (event.key === 'Enter') { page = 1; loadRows(true); } });
yearEl.addEventListener('change', () => { page = 1; loadRows(true); });
customerEl.addEventListener('change', () => { page = 1; loadRows(true); });
document.getElementById('prevBtn').addEventListener('click', () => { if (page > 1) { page--; loadRows(); } });
document.getElementById('nextBtn').addEventListener('click', () => { if (page < totalPages) { page++; loadRows(); } });
exportBtn.addEventListener('click', startExport);

lucide.createIcons();
loadRows();
</script>
</body>
</html>
