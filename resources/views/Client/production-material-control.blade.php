<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Vật tư theo lệnh sản xuất</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/warehouse-wms.css') }}?v={{ filemtime(public_path('css/warehouse-wms.css')) }}" rel="stylesheet">
    <style>
        .pm-filter { display:grid; grid-template-columns:minmax(260px,1fr) 190px auto; gap:10px; align-items:end; }
        .pm-summary { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin:14px 0; }
        .pm-stat { padding:14px 16px; border:1px solid #cbdcf2; border-radius:8px; background:#fff; }
        .pm-stat small { display:block; color:#64748b; font-size:11px; font-weight:800; text-transform:uppercase; }
        .pm-stat strong { display:block; margin-top:5px; color:#0f2f63; font-size:24px; line-height:1; }
        .pm-order { color:#075aa5; font-weight:850; }
        .pm-sub { margin-top:3px; color:#64748b; font-size:11px; }
        .pm-badge { display:inline-flex; padding:4px 8px; border-radius:999px; font-size:11px; font-weight:800; }
        .pm-badge--open { color:#9a3412; background:#ffedd5; }
        .pm-badge--done { color:#166534; background:#dcfce7; }
        .pm-badge--draft { color:#1d4ed8; background:#dbeafe; }
        .pm-materials { margin:0; padding:0; list-style:none; }
        .pm-materials li { display:flex; justify-content:space-between; gap:12px; padding:8px 0; border-bottom:1px solid #e2e8f0; }
        .pm-materials li:last-child { border-bottom:0; }
        .pm-material-main { min-width:0; }
        .pm-material-code { color:#0f2f63; font-weight:800; }
        .pm-material-qty { flex:0 0 auto; text-align:right; }
        .pm-detail-row td { padding:0 !important; background:#f8fbff !important; }
        .pm-detail { padding:12px 18px 16px; }
        .pm-actions { display:flex; flex-wrap:wrap; gap:6px; }
        .pm-loading { display:none; position:fixed; inset:0; z-index:2000; place-items:center; background:rgba(239,246,255,.72); backdrop-filter:blur(2px); }
        .pm-loading.is-visible { display:grid; }
        .pm-loading__box { display:flex; align-items:center; gap:10px; padding:12px 16px; border:1px solid #bfdbfe; border-radius:8px; background:#fff; color:#0f2f63; font-weight:800; }
        .pm-empty { padding:48px 20px; color:#64748b; text-align:center; }
        @media (max-width:900px) { .pm-summary { grid-template-columns:repeat(2,1fr); } .pm-filter { grid-template-columns:1fr; } }
        @media (max-width:560px) { .pm-summary { grid-template-columns:1fr; } }
    </style>
</head>
<body>
@include('layouts.partials.sidebar')

<header class="wms-topbar">
    <h1 class="wms-topbar__title">WMS May Mặc</h1>
    <div class="wms-global-search">
        <i data-lucide="search"></i>
        <input id="topKeyword" placeholder="Tìm lệnh, PO, mã vật tư..." aria-label="Tìm vật tư theo lệnh">
    </div>
    <a class="wms-btn" href="{{ url('/client/xuat-chi-lenh-sx') }}"><i data-lucide="file-spreadsheet"></i>Nguồn XNT</a>
</header>

<main class="wms-page">
    <div class="wms-heading">
        <div>
            <h1>Vật tư theo lệnh</h1>
            <p>Xuất, hoàn trả và tiêu hao thực tế trong database nội bộ.</p>
        </div>
        <a class="wms-btn wms-btn--primary" href="{{ url('/client/xuat-vat-tu-noi-bo?type=material') }}"><i data-lucide="package-minus"></i>Tạo phiếu xuất</a>
    </div>

    <section class="wms-panel">
        <div class="wms-panel__body">
            <div class="pm-filter">
                <label class="wms-field"><span>Tìm kiếm</span><input id="keyword" placeholder="Lệnh SX, PO, mã thành phẩm hoặc vật tư"></label>
                <label class="wms-field"><span>Trạng thái</span><select id="status"><option value="open">Còn tại sản xuất</option><option value="closed">Đã xử lý hết</option><option value="all">Tất cả</option></select></label>
                <button id="reloadBtn" class="wms-btn" type="button"><i data-lucide="refresh-cw"></i>Tải lại</button>
            </div>
        </div>
    </section>

    <div class="pm-summary">
        <div class="pm-stat"><small>Lệnh có vật tư</small><strong id="sumOrders">0</strong></div>
        <div class="pm-stat"><small>Dòng vật tư</small><strong id="sumIssued">0</strong></div>
        <div class="pm-stat"><small>Phiếu trả</small><strong id="sumReturned">0</strong></div>
        <div class="pm-stat"><small>Dòng còn tại sản xuất</small><strong id="sumOpen">0</strong></div>
    </div>

    <section class="wms-panel">
        <div class="wms-panel__head"><div><strong>Danh sách theo lệnh sản xuất</strong><div class="pm-sub" id="resultMeta"></div></div></div>
        <div class="wms-table-wrap">
            <table class="wms-table">
                <thead><tr><th>Lệnh SX</th><th>Thành phẩm</th><th>Khách / PO</th><th>Dòng vật tư</th><th>Phiếu xuất</th><th>Còn tại SX</th><th>BOM</th><th>Thao tác</th></tr></thead>
                <tbody id="orderBody"><tr><td colspan="8" class="pm-empty">Đang tải dữ liệu...</td></tr></tbody>
            </table>
        </div>
    </section>
</main>

<div class="modal fade" id="returnModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><div><h2 class="modal-title fs-5">Trả vật tư</h2><div id="returnOrder" class="pm-sub"></div></div><button class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div>
        <div class="modal-body">
            <div class="row g-2 mb-3">
                <div class="col-md-3"><label class="form-label">Ngày trả</label><input id="returnDate" type="date" class="form-control"></div>
                <div class="col-md-3"><label class="form-label">Người trả</label><input id="returnedBy" class="form-control"></div>
                <div class="col-md-3"><label class="form-label">Người nhận</label><input id="receivedBy" class="form-control"></div>
                <div class="col-md-3"><label class="form-label">Ghi chú</label><input id="returnNote" class="form-control"></div>
            </div>
            <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Vật tư</th><th>Còn tại SX</th><th>Trả dùng lại</th><th>Phế</th><th>Kệ nhận lại</th></tr></thead><tbody id="returnLines"></tbody></table></div>
            <div id="modalMessage" class="alert d-none mt-3" role="alert"></div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button><button id="saveReturnBtn" class="btn btn-primary"><i data-lucide="save"></i> Lưu phiếu trả</button></div>
    </div></div>
</div>

<div class="modal fade" id="bomModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><div><h2 class="modal-title fs-5">BOM tiêu hao thực tế</h2><div id="bomOrder" class="pm-sub"></div></div><button class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div>
        <div class="modal-body"><div class="alert alert-info py-2">BOM này được suy ra từ xuất vật tư trừ phần trả dùng lại, chia cho sản lượng tốt đã nhập kho. Cần kiểm tra trước khi duyệt.</div><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Vật tư</th><th>Vai trò</th><th>Đã xuất</th><th>Trả</th><th>Phế</th><th>Tiêu hao</th><th>Sản lượng tốt</th><th>Định mức / 1 TP</th><th>Trạng thái</th></tr></thead><tbody id="bomLines"></tbody></table></div></div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button><button id="approveBomBtn" class="btn btn-primary"><i data-lucide="badge-check"></i> Duyệt BOM nháp</button></div>
    </div></div>
</div>

<div class="pm-loading" id="loading"><div class="pm-loading__box"><span class="spinner-border spinner-border-sm"></span><span id="loadingText">Đang xử lý...</span></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const state = { rows: [], returnOrder: null, bomOrder: null };
    const modal = new bootstrap.Modal(document.getElementById('returnModal'));
    const bomModal = new bootstrap.Modal(document.getElementById('bomModal'));
    const esc = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
    const number = value => new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 6 }).format(Number(value || 0));
    const loading = (show, text = 'Đang xử lý...') => {
        document.getElementById('loadingText').textContent = text;
        document.getElementById('loading').classList.toggle('is-visible', show);
    };
    const json = async response => {
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(payload.message || 'Không xử lý được yêu cầu.');
        return payload;
    };
    const post = (url, body) => fetch(url, { method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf}, body:JSON.stringify(body) }).then(json);

    function render(payload) {
        state.rows = payload.data || [];
        const summary = payload.summary || {};
        document.getElementById('sumOrders').textContent = number(summary.order_count);
        document.getElementById('sumIssued').textContent = number(summary.material_line_count);
        document.getElementById('sumReturned').textContent = number(summary.return_count);
        document.getElementById('sumOpen').textContent = number(summary.open_material_count);
        document.getElementById('resultMeta').textContent = summary.filtered_order_count > state.rows.length
            ? `Hiển thị ${state.rows.length}/${summary.filtered_order_count} lệnh. Gõ mã lệnh để tìm nhanh.`
            : `${state.rows.length} lệnh theo bộ lọc hiện tại`;
        const body = document.getElementById('orderBody');
        if (!state.rows.length) {
            body.innerHTML = '<tr><td colspan="8" class="pm-empty">Không có lệnh phù hợp.</td></tr>';
            return;
        }
        body.innerHTML = state.rows.map((row, index) => `
            <tr>
                <td><div class="pm-order">${esc(row.production_order)}</div><div class="pm-sub">${esc((row.issue_codes || []).join(', '))}</div></td>
                <td><strong>${esc(row.finished_item_code || '-')}</strong><div class="pm-sub">${esc(row.finished_item_name)}</div></td>
                <td>${esc(row.customer || '-')}<div class="pm-sub">${esc(row.purchase_order || '')}</div></td>
                <td class="text-end">${number((row.materials || []).length)}</td>
                <td class="text-end">${number((row.issue_codes || []).length)}</td>
                <td><span class="pm-badge ${row.open_quantity > 0 ? 'pm-badge--open' : 'pm-badge--done'}">${number((row.materials || []).filter(item => item.remaining_quantity > 0).length)} dòng</span></td>
                <td><span class="pm-badge ${row.bom_status === 'draft' ? 'pm-badge--draft' : 'pm-badge--done'}">${row.bom_status === 'none' ? 'Chưa có' : row.bom_status === 'draft' ? 'Nháp' : 'Đã duyệt'}</span></td>
                <td><div class="pm-actions"><button class="wms-btn wms-btn--sm" data-detail="${index}"><i data-lucide="list"></i>Chi tiết</button><button class="wms-btn wms-btn--sm" data-return="${index}" ${row.open_quantity <= 0 ? 'disabled' : ''}><i data-lucide="undo-2"></i>Trả</button><button class="wms-btn wms-btn--sm" data-bom="${index}"><i data-lucide="wand-sparkles"></i>Suy BOM</button>${row.bom_count ? `<button class="wms-btn wms-btn--sm" data-view-bom="${index}"><i data-lucide="clipboard-list"></i>Xem BOM</button>` : ''}</div></td>
            </tr>
            <tr class="pm-detail-row d-none" id="detail-${index}"><td colspan="8"><div class="pm-detail"><ul class="pm-materials">${(row.materials || []).map(material => `<li><div class="pm-material-main"><div class="pm-material-code">${esc(material.material_item_code)} · ${esc(material.component_role)}</div><div class="pm-sub">${esc(material.material_name)} · ${esc(material.issue_code)} · ${esc(material.issue_date || '')}</div></div><div class="pm-material-qty">Xuất ${number(material.allocated_quantity)} ${esc(material.unit)}<div class="pm-sub">Trả ${number(material.returned_quantity)} · Phế ${number(material.scrap_quantity)} · Còn ${number(material.remaining_quantity)}</div></div></li>`).join('')}</ul></div></td></tr>
        `).join('');
        lucide.createIcons();
    }

    async function load() {
        loading(true, 'Đang tải vật tư theo lệnh...');
        const params = new URLSearchParams({ keyword:document.getElementById('keyword').value.trim(), status:document.getElementById('status').value });
        try { render(await fetch('/api/vat-tu-san-xuat?' + params).then(json)); }
        catch (error) { document.getElementById('orderBody').innerHTML = `<tr><td colspan="8" class="pm-empty text-danger">${esc(error.message)}</td></tr>`; }
        finally { loading(false); }
    }

    function openReturn(index) {
        const row = state.rows[index];
        state.returnOrder = row;
        document.getElementById('returnOrder').textContent = `${row.production_order} · ${row.finished_item_code || ''}`;
        document.getElementById('returnDate').value = new Date().toLocaleDateString('en-CA', { timeZone:'Asia/Ho_Chi_Minh' });
        document.getElementById('returnLines').innerHTML = row.materials.filter(item => item.remaining_quantity > 0).map(item => `
            <tr data-allocation="${item.allocation_id}"><td><strong>${esc(item.material_item_code)}</strong><div class="pm-sub">${esc(item.material_name)} · ${esc(item.component_role)} · ${esc(item.unit)}</div></td><td>${number(item.remaining_quantity)}</td><td><input class="form-control form-control-sm js-reusable" type="number" min="0" max="${item.remaining_quantity}" step="0.001" value="0"></td><td><input class="form-control form-control-sm js-scrap" type="number" min="0" max="${item.remaining_quantity}" step="0.001" value="0"></td><td><input class="form-control form-control-sm js-location" placeholder="CHUA-XEP"></td></tr>
        `).join('');
        document.getElementById('modalMessage').className = 'alert d-none mt-3';
        modal.show();
    }

    async function saveReturn() {
        const lines = [...document.querySelectorAll('#returnLines tr')].map(row => ({
            allocation_id:Number(row.dataset.allocation), reusable_quantity:Number(row.querySelector('.js-reusable').value || 0), scrap_quantity:Number(row.querySelector('.js-scrap').value || 0), location_code:row.querySelector('.js-location').value.trim()
        })).filter(line => line.reusable_quantity > 0 || line.scrap_quantity > 0);
        loading(true, 'Đang lưu phiếu trả...');
        try {
            const payload = await post('/api/vat-tu-san-xuat/tra-lai', { return_date:document.getElementById('returnDate').value, production_order:state.returnOrder.production_order, returned_by:document.getElementById('returnedBy').value, received_by:document.getElementById('receivedBy').value, note:document.getElementById('returnNote').value, lines });
            modal.hide(); await load(); window.alert(payload.message);
        } catch (error) {
            const message = document.getElementById('modalMessage'); message.textContent = error.message; message.className = 'alert alert-danger mt-3';
        } finally { loading(false); }
    }

    async function inferBom(index) {
        const row = state.rows[index];
        loading(true, 'Đang tính tiêu hao thực tế...');
        try { await post('/api/vat-tu-san-xuat/suy-bom', { production_order:row.production_order }); await load(); await openBomByOrder(row.production_order); }
        catch (error) { window.alert(error.message); }
        finally { loading(false); }
    }

    async function openBomByOrder(orderCode) {
        loading(true, 'Đang tải BOM...');
        try {
            const payload = await fetch('/api/vat-tu-san-xuat/bom?production_order=' + encodeURIComponent(orderCode)).then(json);
            state.bomOrder = orderCode;
            document.getElementById('bomOrder').textContent = orderCode;
            document.getElementById('bomLines').innerHTML = (payload.data || []).map(row => `<tr><td><strong>${esc(row.material_item_code)}</strong><div class="pm-sub">${esc(row.unit || '')}</div></td><td>${esc(row.component_role)}</td><td>${number(row.issued_quantity)}</td><td>${number(row.returned_quantity)}</td><td>${number(row.scrap_quantity)}</td><td>${number(row.actual_consumed_quantity)}</td><td>${number(row.good_output_quantity)}</td><td><strong>${number(row.consumption_per_unit)}</strong></td><td><span class="pm-badge ${row.status === 'approved' ? 'pm-badge--done' : 'pm-badge--draft'}">${row.status === 'approved' ? 'Đã duyệt' : 'Nháp'}</span></td></tr>`).join('') || '<tr><td colspan="9" class="pm-empty">Chưa có BOM.</td></tr>';
            document.getElementById('approveBomBtn').disabled = !(payload.data || []).some(row => row.status === 'draft');
            bomModal.show(); lucide.createIcons();
        } catch (error) { window.alert(error.message); }
        finally { loading(false); }
    }

    async function approveBom() {
        if (!state.bomOrder || !window.confirm('Duyệt BOM nháp của lệnh ' + state.bomOrder + '?')) return;
        loading(true, 'Đang duyệt BOM...');
        try { const payload = await post('/api/vat-tu-san-xuat/duyet-bom', { production_order:state.bomOrder }); await openBomByOrder(state.bomOrder); await load(); window.alert(payload.message); }
        catch (error) { window.alert(error.message); }
        finally { loading(false); }
    }

    document.getElementById('orderBody').addEventListener('click', event => {
        const detail = event.target.closest('[data-detail]'); if (detail) document.getElementById('detail-' + detail.dataset.detail).classList.toggle('d-none');
        const returning = event.target.closest('[data-return]'); if (returning) openReturn(Number(returning.dataset.return));
        const bom = event.target.closest('[data-bom]'); if (bom) inferBom(Number(bom.dataset.bom));
        const viewBom = event.target.closest('[data-view-bom]'); if (viewBom) openBomByOrder(state.rows[Number(viewBom.dataset.viewBom)].production_order);
    });
    document.getElementById('saveReturnBtn').addEventListener('click', saveReturn);
    document.getElementById('approveBomBtn').addEventListener('click', approveBom);
    document.getElementById('reloadBtn').addEventListener('click', load);
    document.getElementById('status').addEventListener('change', load);
    let timer; ['keyword','topKeyword'].forEach(id => document.getElementById(id).addEventListener('input', event => { document.getElementById(id === 'keyword' ? 'topKeyword' : 'keyword').value = event.target.value; clearTimeout(timer); timer=setTimeout(load,300); }));
    lucide.createIcons(); load();
})();
</script>
</body>
</html>
