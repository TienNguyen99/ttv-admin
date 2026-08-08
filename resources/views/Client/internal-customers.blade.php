<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Danh mục khách hàng</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/warehouse-wms.css') }}?v={{ filemtime(public_path('css/warehouse-wms.css')) }}" rel="stylesheet">
    <style>
        .customer-table { min-width: 980px; }
        .customer-table .form-control { min-width: 130px; }
        .customer-table .customer-name { min-width: 220px; }
    </style>
</head>
<body>
    @include('layouts.partials.sidebar')

    <header class="wms-topbar">
        <h1 class="wms-topbar__title">WMS May Mặc</h1>
        <div class="wms-global-search">
            <i data-lucide="search"></i>
            <input id="topKeyword" aria-label="Tìm khách hàng" placeholder="Tìm tên hoặc mã khách hàng...">
        </div>
        <div class="wms-topbar__actions">
            <button id="syncBtn" type="button" class="wms-btn"><i data-lucide="refresh-cw"></i>Đồng bộ từ lệnh SX</button>
            <button type="button" class="wms-btn wms-btn--primary" data-bs-toggle="modal" data-bs-target="#customerModal"><i data-lucide="user-plus"></i>Thêm khách</button>
        </div>
    </header>

    <main class="wms-page">
        <div class="wms-heading">
            <div>
                <h1>Danh mục khách hàng</h1>
                <p>Tên khách được lấy từ lệnh sản xuất. Nhóm khách được quản lý riêng trong database nội bộ.</p>
            </div>
        </div>

        <section class="wms-kpis mb-3">
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="users"></i></div><div><div class="wms-kpi__label">Khách đang dùng</div><div id="activeCount" class="wms-kpi__value">0</div></div></article>
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="tags"></i></div><div><div class="wms-kpi__label">Chưa phân nhóm</div><div id="unclassifiedCount" class="wms-kpi__value">0</div></div></article>
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="list-filter"></i></div><div><div class="wms-kpi__label">Kết quả lọc</div><div id="resultCount" class="wms-kpi__value">0</div></div></article>
        </section>

        <section class="wms-filterbar mb-3">
            <div><label for="keyword">Tìm khách hàng</label><input id="keyword" class="form-control" placeholder="Tên hoặc mã khách"></div>
            <div><label for="groupFilter">Nhóm khách</label><select id="groupFilter" class="form-select"><option value="">Tất cả nhóm</option></select></div>
            <div><button id="clearBtn" type="button" class="wms-btn"><i data-lucide="filter-x"></i>Xóa lọc</button></div>
        </section>

        <div id="statusBar" class="alert d-none" role="status"></div>

        <section class="wms-panel">
            <div class="wms-panel__header">
                <h2>Danh sách khách hàng</h2>
                <span id="pageLabel" class="text-secondary small">Đang tải...</span>
            </div>
            <div class="wms-table-wrap">
                <table class="wms-table customer-table">
                    <thead><tr><th>Mã khách</th><th>Tên khách hàng</th><th>Nhóm khách</th><th class="text-end">Số lệnh</th><th>Lệnh gần nhất</th><th>Nguồn</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
                    <tbody id="customerRows"><tr><td colspan="8" class="wms-loading">Đang tải dữ liệu...</td></tr></tbody>
                </table>
            </div>
            <div class="d-flex align-items-center justify-content-between gap-2 p-3 border-top">
                <button id="prevBtn" type="button" class="wms-btn"><i data-lucide="chevron-left"></i>Trước</button>
                <span id="paginationLabel" class="text-secondary small"></span>
                <button id="nextBtn" type="button" class="wms-btn">Sau<i data-lucide="chevron-right"></i></button>
            </div>
        </section>
    </main>

    <div class="modal fade" id="customerModal" tabindex="-1" aria-labelledby="customerModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h2 id="customerModalTitle" class="modal-title fs-5">Thêm khách hàng</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label" for="newCustomerName">Tên khách hàng *</label><input id="newCustomerName" class="form-control" autocomplete="off"></div>
                    <div class="row g-2">
                        <div class="col-md-6"><label class="form-label" for="newCustomerCode">Mã khách</label><input id="newCustomerCode" class="form-control" autocomplete="off"></div>
                        <div class="col-md-6"><label class="form-label" for="newCustomerGroup">Nhóm khách</label><input id="newCustomerGroup" class="form-control" list="customerGroupOptions" value="Chưa phân loại"></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button><button id="createBtn" type="button" class="btn btn-primary">Lưu khách hàng</button></div>
            </div>
        </div>
    </div>
    <datalist id="customerGroupOptions"><option value="Chưa phân loại"><option value="Xuất khẩu"><option value="Nội địa"><option value="Gia công"></datalist>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const rowsEl = document.getElementById('customerRows');
        const keywordEl = document.getElementById('keyword');
        const topKeywordEl = document.getElementById('topKeyword');
        const groupEl = document.getElementById('groupFilter');
        let page = 1;
        let lastPage = 1;
        let timer = null;

        const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
        const date = value => value ? new Intl.DateTimeFormat('vi-VN').format(new Date(`${String(value).slice(0, 10)}T00:00:00`)) : '-';

        function jsonOrError(response, fallback) {
            if (response.ok) return response.json();
            return response.json().then(result => {
                const validation = result.errors ? Object.values(result.errors).flat()[0] : '';
                throw new Error(validation || result.message || fallback);
            });
        }

        function status(message, type = 'success') {
            const bar = document.getElementById('statusBar');
            bar.className = `alert alert-${type}`;
            bar.textContent = message;
            bar.classList.remove('d-none');
            window.setTimeout(() => bar.classList.add('d-none'), 4500);
        }

        function loadRows() {
            rowsEl.innerHTML = '<tr><td colspan="8" class="wms-loading">Đang tải dữ liệu...</td></tr>';
            const params = new URLSearchParams({page, per_page: 50});
            if (keywordEl.value.trim()) params.set('keyword', keywordEl.value.trim());
            if (groupEl.value) params.set('customer_group', groupEl.value);
            fetch(`/api/khach-hang-noi-bo?${params}`)
                .then(response => jsonOrError(response, 'Không tải được danh mục khách hàng'))
                .then(result => {
                    const meta = result.meta || {};
                    lastPage = Number(meta.last_page || 1);
                    document.getElementById('activeCount').textContent = Number(meta.active || 0).toLocaleString('vi-VN');
                    document.getElementById('unclassifiedCount').textContent = Number(meta.unclassified || 0).toLocaleString('vi-VN');
                    document.getElementById('resultCount').textContent = Number(meta.total || 0).toLocaleString('vi-VN');
                    document.getElementById('pageLabel').textContent = `${Number(meta.total || 0).toLocaleString('vi-VN')} khách hàng`;
                    document.getElementById('paginationLabel').textContent = `Trang ${meta.current_page || 1}/${lastPage}`;
                    document.getElementById('prevBtn').disabled = page <= 1;
                    document.getElementById('nextBtn').disabled = page >= lastPage;
                    const selectedGroup = groupEl.value;
                    groupEl.innerHTML = '<option value="">Tất cả nhóm</option>' + (result.groups || []).map(group => `<option value="${esc(group)}">${esc(group)}</option>`).join('');
                    groupEl.value = selectedGroup;
                    rowsEl.innerHTML = (result.data || []).map(row => `<tr data-id="${row.id}">
                        <td><input class="form-control form-control-sm customer-code" value="${esc(row.customer_code || '')}" placeholder="Tùy chọn"></td>
                        <td><input class="form-control form-control-sm customer-name" value="${esc(row.name)}"></td>
                        <td><input class="form-control form-control-sm customer-group" list="customerGroupOptions" value="${esc(row.customer_group || 'Chưa phân loại')}"></td>
                        <td class="wms-number">${Number(row.order_count || 0).toLocaleString('vi-VN')}</td>
                        <td>${date(row.last_order_date)}</td>
                        <td>${esc(row.source === 'manual' ? 'Thêm tay' : 'Lệnh SX')}</td>
                        <td><span class="wms-badge ${row.is_active ? '' : 'wms-badge--secondary'}">${row.is_active ? 'Đang dùng' : 'Đã ngừng'}</span></td>
                        <td><div class="d-flex gap-1"><button type="button" class="btn btn-sm btn-outline-primary save-row"><i data-lucide="save"></i></button>${row.is_active ? '<button type="button" class="btn btn-sm btn-outline-danger disable-row" title="Ngừng sử dụng"><i data-lucide="user-x"></i></button>' : ''}</div></td>
                    </tr>`).join('') || '<tr><td colspan="8" class="wms-empty">Không có khách hàng phù hợp.</td></tr>';
                    window.lucide?.createIcons();
                })
                .catch(error => rowsEl.innerHTML = `<tr><td colspan="8" class="wms-empty text-danger">${esc(error.message)}</td></tr>`);
        }

        function queueSearch(source) {
            if (source === topKeywordEl) keywordEl.value = topKeywordEl.value;
            if (source === keywordEl) topKeywordEl.value = keywordEl.value;
            clearTimeout(timer);
            timer = setTimeout(() => { page = 1; loadRows(); }, 220);
        }

        document.getElementById('syncBtn').addEventListener('click', event => {
            const button = event.currentTarget;
            button.disabled = true;
            fetch('/api/khach-hang-noi-bo/dong-bo', {method:'POST', headers:{'Accept':'application/json','X-CSRF-TOKEN':csrfToken}})
                .then(response => jsonOrError(response, 'Không đồng bộ được khách hàng'))
                .then(result => { status(result.message); loadRows(); })
                .catch(error => status(error.message, 'danger'))
                .finally(() => button.disabled = false);
        });

        document.getElementById('createBtn').addEventListener('click', event => {
            const button = event.currentTarget;
            button.disabled = true;
            fetch('/api/khach-hang-noi-bo', {
                method:'POST',
                headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body:JSON.stringify({name:document.getElementById('newCustomerName').value.trim(), customer_code:document.getElementById('newCustomerCode').value.trim(), customer_group:document.getElementById('newCustomerGroup').value.trim()}),
            }).then(response => jsonOrError(response, 'Không thêm được khách hàng'))
              .then(result => { bootstrap.Modal.getInstance(document.getElementById('customerModal')).hide(); status(result.message); loadRows(); })
              .catch(error => status(error.message, 'danger'))
              .finally(() => button.disabled = false);
        });

        rowsEl.addEventListener('click', event => {
            const row = event.target.closest('tr[data-id]');
            if (!row) return;
            if (event.target.closest('.save-row')) {
                fetch(`/api/khach-hang-noi-bo/${row.dataset.id}`, {method:'PATCH', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken}, body:JSON.stringify({name:row.querySelector('.customer-name').value.trim(), customer_code:row.querySelector('.customer-code').value.trim(), customer_group:row.querySelector('.customer-group').value.trim()})})
                    .then(response => jsonOrError(response, 'Không cập nhật được khách hàng')).then(result => { status(result.message); loadRows(); }).catch(error => status(error.message, 'danger'));
            }
            if (event.target.closest('.disable-row') && confirm('Ngừng sử dụng khách hàng này?')) {
                fetch(`/api/khach-hang-noi-bo/${row.dataset.id}`, {method:'DELETE', headers:{'Accept':'application/json','X-CSRF-TOKEN':csrfToken}})
                    .then(response => jsonOrError(response, 'Không ngừng được khách hàng')).then(result => { status(result.message); loadRows(); }).catch(error => status(error.message, 'danger'));
            }
        });

        keywordEl.addEventListener('input', () => queueSearch(keywordEl));
        topKeywordEl.addEventListener('input', () => queueSearch(topKeywordEl));
        groupEl.addEventListener('change', () => { page = 1; loadRows(); });
        document.getElementById('clearBtn').addEventListener('click', () => { keywordEl.value = ''; topKeywordEl.value = ''; groupEl.value = ''; page = 1; loadRows(); });
        document.getElementById('prevBtn').addEventListener('click', () => { if (page > 1) { page--; loadRows(); } });
        document.getElementById('nextBtn').addEventListener('click', () => { if (page < lastPage) { page++; loadRows(); } });
        loadRows();
        window.lucide?.createIcons();
    </script>
</body>
</html>
