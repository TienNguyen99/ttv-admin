<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Danh mục PANEL chuẩn hóa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/warehouse-wms.css') }}?v={{ filemtime(public_path('css/warehouse-wms.css')) }}" rel="stylesheet">
    <style>
        .panel-rule-table { min-width: 920px; }
        .panel-rule-aliases { display:flex; flex-wrap:wrap; gap:6px; max-width:460px; }
        .panel-rule-alias { padding:4px 8px; border:1px solid #cfe0f5; border-radius:6px; background:#f4f8ff; font-size:12px; }
        .panel-rule-actions { display:flex; gap:6px; white-space:nowrap; }
        .panel-rule-modal textarea { min-height:138px; resize:vertical; }
        @media (max-width: 991.98px) { .wms-page { padding-top:76px !important; } }
        @media (max-width: 767.98px) { .wms-topbar__actions .wms-btn span { display:none; } }
    </style>
</head>
<body>
@include('layouts.partials.sidebar')

<header class="wms-topbar">
    <h1 class="wms-topbar__title">WMS May Mặc</h1>
    <div class="wms-global-search"><i data-lucide="search"></i><input id="topKeyword" placeholder="Tìm PANEL hoặc cách viết..." aria-label="Tìm PANEL"></div>
    <div class="wms-topbar__actions">
        <a class="wms-btn" href="{{ route('panel-receipts.page') }}"><i data-lucide="package-check"></i><span>Hàng về PANEL</span></a>
        <button id="newRuleBtn" type="button" class="wms-btn wms-btn--primary"><i data-lucide="plus"></i><span>Thêm quy tắc</span></button>
    </div>
</header>

<main class="wms-page">
    <div class="wms-heading">
        <div><h1>Danh mục PANEL chuẩn hóa</h1><p>Mỗi cách viết chỉ thuộc một PANEL chuẩn trong database nội bộ.</p></div>
    </div>

    <section class="wms-kpis mb-3">
        <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="layers-3"></i></div><div><div class="wms-kpi__label">Tổng quy tắc</div><div id="totalCount" class="wms-kpi__value">0</div></div></article>
        <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="wand-sparkles"></i></div><div><div class="wms-kpi__label">Tự động</div><div id="autoCount" class="wms-kpi__value">0</div></div></article>
        <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="circle-help"></i></div><div><div class="wms-kpi__label">Chờ duyệt</div><div id="pendingCount" class="wms-kpi__value">0</div></div></article>
        <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="circle-off"></i></div><div><div class="wms-kpi__label">Đã tắt</div><div id="inactiveCount" class="wms-kpi__value">0</div></div></article>
    </section>

    <section class="wms-filterbar mb-3">
        <div><label for="keyword">Tìm kiếm</label><input id="keyword" class="form-control" placeholder="PANEL chuẩn hoặc alias"></div>
        <div><label for="statusFilter">Trạng thái</label><select id="statusFilter" class="form-select"><option value="">Tất cả</option><option value="AUTO">Tự động</option><option value="PENDING">Chờ duyệt</option></select></div>
        <div><label for="activeFilter">Sử dụng</label><select id="activeFilter" class="form-select"><option value="">Tất cả</option><option value="1">Đang dùng</option><option value="0">Đã tắt</option></select></div>
        <div><button id="clearFilterBtn" class="wms-btn" type="button"><i data-lucide="filter-x"></i>Xóa lọc</button></div>
    </section>

    <div id="statusBar" class="alert d-none" role="status"></div>

    <section class="wms-panel">
        <div class="wms-panel__header"><h2>Quy tắc PANEL</h2><span id="resultLabel" class="text-secondary small">Đang tải...</span></div>
        <div class="wms-table-wrap">
            <table class="wms-table panel-rule-table">
                <thead><tr><th>Thứ tự</th><th>PANEL chuẩn</th><th>Cách viết</th><th>Trạng thái</th><th>Sử dụng</th><th>Ghi chú</th><th>Thao tác</th></tr></thead>
                <tbody id="ruleRows"><tr><td colspan="7" class="wms-loading">Đang tải dữ liệu...</td></tr></tbody>
            </table>
        </div>
        <div class="d-flex align-items-center justify-content-between gap-2 p-3 border-top">
            <button id="prevBtn" type="button" class="wms-btn"><i data-lucide="chevron-left"></i>Trước</button>
            <span id="pageLabel" class="text-secondary small"></span>
            <button id="nextBtn" type="button" class="wms-btn">Sau<i data-lucide="chevron-right"></i></button>
        </div>
    </section>
</main>

<div class="modal fade panel-rule-modal" id="ruleModal" tabindex="-1" aria-labelledby="ruleModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
        <div class="modal-header"><h2 id="ruleModalTitle" class="modal-title fs-5">Thêm quy tắc PANEL</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div>
        <div class="modal-body">
            <input id="ruleId" type="hidden">
            <div class="row g-3">
                <div class="col-md-7"><label class="form-label" for="standardName">PANEL chuẩn</label><input id="standardName" class="form-control" placeholder="Ví dụ: LEFT BACK"></div>
                <div class="col-md-5"><label class="form-label" for="ruleStatus">Trạng thái</label><select id="ruleStatus" class="form-select"><option value="AUTO">Tự động chuẩn hóa</option><option value="PENDING">Chờ duyệt</option></select></div>
                <div class="col-12"><label class="form-label" for="aliases">Các cách viết *</label><textarea id="aliases" class="form-control" placeholder="Mỗi dòng một cách viết, ví dụ:&#10;LB&#10;L/B&#10;LEFT/BACK"></textarea><div class="form-text">Có thể nhập mỗi alias một dòng hoặc ngăn cách bằng dấu phẩy.</div></div>
                <div class="col-md-4"><label class="form-label" for="sortOrder">Thứ tự</label><input id="sortOrder" type="number" min="0" class="form-control" value="0"></div>
                <div class="col-md-4 d-flex align-items-end"><div class="form-check form-switch mb-2"><input id="isActive" class="form-check-input" type="checkbox" checked><label class="form-check-label" for="isActive">Đang sử dụng</label></div></div>
                <div class="col-12"><label class="form-label" for="ruleNote">Ghi chú</label><input id="ruleNote" class="form-control" maxlength="5000"></div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button><button id="saveRuleBtn" type="button" class="btn btn-primary"><span class="spinner-border spinner-border-sm d-none" aria-hidden="true"></span>Lưu quy tắc</button></div>
    </div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const modalElement = document.getElementById('ruleModal');
    const modal = new bootstrap.Modal(modalElement);
    const rows = document.getElementById('ruleRows');
    const keyword = document.getElementById('keyword');
    const topKeyword = document.getElementById('topKeyword');
    let page = 1, lastPage = 1, timer = null, currentRows = [];
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
    const json = async (response, fallback) => { const body = await response.json().catch(() => ({})); if (!response.ok) throw new Error(Object.values(body.errors || {}).flat()[0] || body.message || fallback); return body; };
    const notify = (message, type = 'success') => { const bar = document.getElementById('statusBar'); bar.className = `alert alert-${type}`; bar.textContent = message; bar.classList.remove('d-none'); setTimeout(() => bar.classList.add('d-none'), 4500); };

    async function load() {
        rows.innerHTML = '<tr><td colspan="7" class="wms-loading">Đang tải dữ liệu...</td></tr>';
        const params = new URLSearchParams({page, per_page:30});
        if (keyword.value.trim()) params.set('keyword', keyword.value.trim());
        if (document.getElementById('statusFilter').value) params.set('status', document.getElementById('statusFilter').value);
        if (document.getElementById('activeFilter').value !== '') params.set('active', document.getElementById('activeFilter').value);
        try {
            const result = await fetch(`/api/panel-chuan-hoa?${params}`).then(r => json(r, 'Không tải được danh mục PANEL'));
            currentRows = result.data || [];
            const meta = result.meta || {}, stats = result.stats || {};
            lastPage = Number(meta.last_page || 1);
            document.getElementById('totalCount').textContent = Number(stats.total || 0).toLocaleString('vi-VN');
            document.getElementById('autoCount').textContent = Number(stats.automatic || 0).toLocaleString('vi-VN');
            document.getElementById('pendingCount').textContent = Number(stats.pending || 0).toLocaleString('vi-VN');
            document.getElementById('inactiveCount').textContent = Number(stats.inactive || 0).toLocaleString('vi-VN');
            document.getElementById('resultLabel').textContent = `${Number(meta.total || 0).toLocaleString('vi-VN')} quy tắc`;
            document.getElementById('pageLabel').textContent = `Trang ${meta.current_page || 1}/${lastPage}`;
            document.getElementById('prevBtn').disabled = page <= 1;
            document.getElementById('nextBtn').disabled = page >= lastPage;
            rows.innerHTML = currentRows.map(row => `<tr>
                <td class="wms-number">${Number(row.sort_order || 0).toLocaleString('vi-VN')}</td>
                <td><strong>${esc(row.standard_name || 'Chưa đặt tên')}</strong></td>
                <td><div class="panel-rule-aliases">${(row.aliases || []).map(alias => `<span class="panel-rule-alias">${esc(alias.alias)}</span>`).join('')}</div></td>
                <td><span class="wms-badge ${row.status === 'PENDING' ? 'wms-badge--warning' : ''}">${row.status === 'AUTO' ? 'Tự động' : 'Chờ duyệt'}</span></td>
                <td>${row.is_active ? '<span class="text-success">Đang dùng</span>' : '<span class="text-secondary">Đã tắt</span>'}</td>
                <td>${esc(row.note || '-')}</td>
                <td><div class="panel-rule-actions"><button class="btn btn-sm btn-outline-primary edit-rule" data-id="${row.id}" title="Sửa"><i data-lucide="pencil"></i></button><button class="btn btn-sm btn-outline-danger delete-rule" data-id="${row.id}" title="Xóa"><i data-lucide="trash-2"></i></button></div></td>
            </tr>`).join('') || '<tr><td colspan="7" class="wms-empty">Không có quy tắc phù hợp.</td></tr>';
            window.lucide?.createIcons();
        } catch (error) { rows.innerHTML = `<tr><td colspan="7" class="wms-empty text-danger">${esc(error.message)}</td></tr>`; }
    }

    function openRule(row = null) {
        document.getElementById('ruleId').value = row?.id || '';
        document.getElementById('standardName').value = row?.standard_name || '';
        document.getElementById('ruleStatus').value = row?.status || 'AUTO';
        document.getElementById('aliases').value = (row?.aliases || []).map(alias => alias.alias).join('\n');
        document.getElementById('sortOrder').value = row?.sort_order || 0;
        document.getElementById('isActive').checked = row ? Boolean(row.is_active) : true;
        document.getElementById('ruleNote').value = row?.note || '';
        document.getElementById('ruleModalTitle').textContent = row ? 'Sửa quy tắc PANEL' : 'Thêm quy tắc PANEL';
        modal.show();
    }

    document.getElementById('newRuleBtn').addEventListener('click', () => openRule());
    document.getElementById('saveRuleBtn').addEventListener('click', async event => {
        const button = event.currentTarget, id = document.getElementById('ruleId').value;
        const aliases = document.getElementById('aliases').value.split(/[\n,]+/).map(v => v.trim()).filter(Boolean);
        button.disabled = true; button.querySelector('.spinner-border').classList.remove('d-none');
        try {
            const result = await fetch(id ? `/api/panel-chuan-hoa/${id}` : '/api/panel-chuan-hoa', {
                method: id ? 'PUT' : 'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},
                body:JSON.stringify({standard_name:document.getElementById('standardName').value, status:document.getElementById('ruleStatus').value, aliases, sort_order:Number(document.getElementById('sortOrder').value || 0), is_active:document.getElementById('isActive').checked, note:document.getElementById('ruleNote').value})
            }).then(r => json(r, 'Không lưu được quy tắc PANEL'));
            modal.hide(); notify(result.message); await load();
        } catch (error) { notify(error.message, 'danger'); }
        finally { button.disabled = false; button.querySelector('.spinner-border').classList.add('d-none'); }
    });

    rows.addEventListener('click', async event => {
        const edit = event.target.closest('.edit-rule'), remove = event.target.closest('.delete-rule');
        if (edit) openRule(currentRows.find(row => String(row.id) === edit.dataset.id));
        if (remove && confirm('Xóa quy tắc PANEL này?')) {
            try { const result = await fetch(`/api/panel-chuan-hoa/${remove.dataset.id}`, {method:'DELETE', headers:{'Accept':'application/json','X-CSRF-TOKEN':csrf}}).then(r => json(r, 'Không xóa được quy tắc')); notify(result.message); await load(); }
            catch (error) { notify(error.message, 'danger'); }
        }
    });
    const queue = source => { if (source === topKeyword) keyword.value = source.value; else topKeyword.value = source.value; clearTimeout(timer); timer = setTimeout(() => {page=1; load();}, 220); };
    keyword.addEventListener('input', () => queue(keyword)); topKeyword.addEventListener('input', () => queue(topKeyword));
    document.getElementById('statusFilter').addEventListener('change', () => {page=1; load();});
    document.getElementById('activeFilter').addEventListener('change', () => {page=1; load();});
    document.getElementById('clearFilterBtn').addEventListener('click', () => {keyword.value=''; topKeyword.value=''; document.getElementById('statusFilter').value=''; document.getElementById('activeFilter').value=''; page=1; load();});
    document.getElementById('prevBtn').addEventListener('click', () => {if(page>1){page--;load();}}); document.getElementById('nextBtn').addEventListener('click', () => {if(page<lastPage){page++;load();}});
    load(); window.lucide?.createIcons();
})();
</script>
</body>
</html>
