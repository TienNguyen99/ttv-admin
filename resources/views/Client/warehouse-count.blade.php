<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Kiểm tồn kho</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --line: #e2e8f0; --muted: #64748b; --ink: #0f172a; --accent: #2563eb; }
        body { background: #f8fafc; color: var(--ink); }
        .page-shell { max-width: 1680px; margin: 0 auto; padding: 22px; }
        .page-title { font-size: 24px; font-weight: 700; }
        .page-subtitle { color: var(--muted); font-size: 14px; }
        .panel { background: #fff; border: 1px solid var(--line); border-radius: 8px; }
        .panel-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 13px 14px; border-bottom: 1px solid var(--line); }
        .panel-title { margin: 0; font-size: 15px; font-weight: 700; }
        .panel-body { padding: 14px; }
        .context-bar { padding: 12px 14px; }
        .form-label { margin-bottom: 4px; color: #475569; font-size: 12px; font-weight: 700; }
        .form-control, .form-select { min-height: 40px; border-color: #cbd5e1; border-radius: 6px; font-size: 14px; }
        .btn { border-radius: 6px; }
        .btn-icon { display: inline-flex; align-items: center; gap: 6px; }
        .btn-icon svg { width: 16px; height: 16px; }
        .workspace-grid { display: grid; grid-template-columns: minmax(300px, 0.85fr) minmax(0, 2fr); gap: 14px; }
        .location-list { max-height: 460px; overflow: auto; }
        .location-item { display: flex; align-items: center; gap: 10px; width: 100%; padding: 11px 12px; border: 0; border-bottom: 1px solid #edf2f7; background: #fff; text-align: left; }
        .location-item:hover { background: #f8fafc; }
        .location-item.is-active { background: #eff6ff; box-shadow: inset 3px 0 0 var(--accent); }
        .location-code { font-size: 14px; font-weight: 700; }
        .location-meta { color: var(--muted); font-size: 12px; }
        .location-actions { margin-left: auto; white-space: nowrap; }
        .location-actions .btn { min-height: 32px; padding: 4px 7px; }
        .summary-strip { display: flex; flex-wrap: wrap; gap: 8px; }
        .summary-chip { padding: 4px 8px; border: 1px solid #bfdbfe; border-radius: 6px; background: #eff6ff; color: #1d4ed8; font-size: 12px; font-weight: 700; }
        .table { margin-bottom: 0; font-size: 13px; }
        .table > :not(caption) > * > * { padding: 9px 10px; border-color: var(--line); }
        .table thead th { background: #f8fafc; color: #475569; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .empty-state { padding: 34px 12px !important; color: var(--muted) !important; }
        .product-search { position: relative; }
        .product-results { position: absolute; inset: calc(100% + 4px) 0 auto; z-index: 1030; max-height: 260px; overflow-y: auto; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; box-shadow: 0 10px 24px rgba(15, 23, 42, 0.14); }
        .product-option { display: block; width: 100%; padding: 9px 10px; border: 0; border-bottom: 1px solid #edf2f7; background: #fff; text-align: left; }
        .product-option:hover, .product-option:focus { background: #eff6ff; outline: 0; }
        .product-option-code { color: #1d4ed8; font-size: 13px; font-weight: 700; }
        .product-option-name { color: var(--muted); font-size: 12px; }
        .kpi-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-bottom: 14px; }
        .kpi-item { display: flex; align-items: center; gap: 10px; padding: 12px; border: 1px solid var(--line); border-radius: 8px; background: #fff; }
        .kpi-icon { display: grid; width: 36px; height: 36px; place-items: center; border-radius: 7px; background: #eff6ff; color: #1d4ed8; }
        .kpi-icon svg { width: 18px; height: 18px; }
        .kpi-value { font-size: 19px; font-weight: 800; line-height: 1; }
        .kpi-label { margin-top: 4px; color: var(--muted); font-size: 12px; }
        .view-tabs { display: flex; gap: 4px; margin-bottom: 14px; padding: 4px; border: 1px solid var(--line); border-radius: 8px; background: #fff; }
        .view-tab { display: inline-flex; align-items: center; gap: 6px; min-height: 38px; padding: 7px 11px; border: 0; border-radius: 5px; background: transparent; color: #475569; font-size: 13px; font-weight: 700; }
        .view-tab svg { width: 16px; height: 16px; }
        .view-tab:hover { background: #f8fafc; }
        .view-tab.is-active { background: #eff6ff; color: #1d4ed8; }
        .section-hint { color: var(--muted); font-size: 12px; }
        @media (max-width: 1100px) { .workspace-grid { grid-template-columns: 1fr; } }
        @media (max-width: 700px) { .kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .view-tabs { overflow-x: auto; } .view-tab { white-space: nowrap; } }
        @media (max-width: 991.98px) { .page-shell { padding: 62px 12px 16px; } }
    </style>
</head>
<body>
    @include('layouts.partials.sidebar')
    <main class="page-shell">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <div>
                <h1 class="page-title mb-1">Quản lý kho</h1>
                <div class="page-subtitle">Theo dõi vị trí, ghi nhận kiện và kiểm soát tồn nội bộ.</div>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-primary btn-icon" onclick="openLocationModal()"><i data-lucide="map-pin-plus"></i>Thêm vị trí</button>
                <button type="button" class="btn btn-primary btn-icon" onclick="switchWorkspace('entry')"><i data-lucide="package-plus"></i>Ghi nhận kiện</button>
            </div>
        </div>

        <section class="kpi-grid">
            <div class="kpi-item"><div class="kpi-icon"><i data-lucide="map-pinned"></i></div><div><div id="kpiLocations" class="kpi-value">0</div><div class="kpi-label">Vị trí kho</div></div></div>
            <div class="kpi-item"><div class="kpi-icon"><i data-lucide="scan-line"></i></div><div><div id="kpiCountingLocations" class="kpi-value">0</div><div class="kpi-label">Vị trí đang kiểm</div></div></div>
            <div class="kpi-item"><div class="kpi-icon"><i data-lucide="package-check"></i></div><div><div id="kpiPackages" class="kpi-value">0</div><div class="kpi-label">Kiện trong ngày</div></div></div>
            <div class="kpi-item"><div class="kpi-icon"><i data-lucide="boxes"></i></div><div><div id="kpiQuantity" class="kpi-value">0</div><div class="kpi-label">Số lượng trong ngày</div></div></div>
        </section>

        <section class="panel context-bar mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-lg-5"><label class="form-label">Vị trí đang kiểm</label><input id="locationCode" list="locationOptions" class="form-control" placeholder="Chọn hoặc quét mã vị trí"></div>
                <div class="col-lg-3"><label class="form-label">Mã kho</label><input id="warehouseCode" class="form-control" placeholder="KTPHAM"></div>
                <div class="col-lg-2"><label class="form-label">Ngày kiểm kê</label><input id="checkedAt" type="date" class="form-control" value="{{ now()->format('Y-m-d') }}"></div>
                <div class="col-lg-2"><button type="button" class="btn btn-outline-secondary btn-icon w-100 justify-content-center" onclick="openLocationModal(value('locationCode').toUpperCase())"><i data-lucide="settings-2"></i>Quản lý vị trí</button></div>
            </div>
            <datalist id="locationOptions"></datalist>
        </section>

        <nav class="view-tabs" aria-label="Khu vực quản lý kho">
            <button type="button" class="view-tab is-active" data-workspace-view="overview" onclick="switchWorkspace('overview')"><i data-lucide="layout-dashboard"></i>Tổng quan vị trí</button>
            <button type="button" class="view-tab" data-workspace-view="entry" onclick="switchWorkspace('entry')"><i data-lucide="package-plus"></i>Ghi nhận kiện</button>
            <button type="button" class="view-tab" data-workspace-view="history" onclick="switchWorkspace('history')"><i data-lucide="history"></i>Lịch sử kiện</button>
        </nav>

        <div id="overviewPanel" data-workspace-panel="overview" class="workspace-grid mb-3">
            <section class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">Danh sách vị trí</h2>
                    <span id="locationCount" class="location-meta"></span>
                </div>
                <div class="panel-body pb-2">
                    <input id="locationSearch" class="form-control" placeholder="Tìm vị trí hoặc mã kho">
                </div>
                <div id="locationRows" class="location-list"></div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Hàng tại <span id="selectedLocationTitle">chưa chọn vị trí</span></h2>
                        <div id="selectedLocationName" class="location-meta mt-1"></div>
                    </div>
                    <div id="locationSummary" class="summary-strip"></div>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Mã nội bộ</th><th>Mã TP kế toán</th><th>Size</th><th>Màu</th><th>Side</th><th class="text-end">Số kiện</th><th class="text-end">Tổng SL</th></tr></thead>
                        <tbody id="locationContentRows"></tbody>
                    </table>
                </div>
            </section>
        </div>

        <section id="entryPanel" data-workspace-panel="entry" class="panel mb-3 d-none">
            <div class="panel-header"><div><h2 class="panel-title">Ghi nhận kiện hàng</h2><div id="entryLocationContext" class="section-hint mt-1">Chọn vị trí đang kiểm trước khi nhập kiện.</div></div></div>
            <div class="panel-body"><div class="row g-2">
                <div class="col-md-3 product-search"><label class="form-label">Mã TP kế toán</label><input id="maSp" class="form-control" autocomplete="off" placeholder="Gõ mã hoặc tên hàng"><div id="maSpResults" class="product-results d-none"></div></div>
                <div class="col-md-3"><label class="form-label">Mã hàng nội bộ</label><input id="internalItemCode" class="form-control"></div>
                <div class="col-md-1"><label class="form-label">Size</label><input id="size" class="form-control"></div>
                <div class="col-md-2"><label class="form-label">Màu</label><input id="color" class="form-control"></div>
                <div class="col-md-2"><label class="form-label">Side</label><input id="side" class="form-control"></div>
                <div class="col-md-2"><label class="form-label">Số lượng</label><input id="quantity" type="number" step="0.001" min="0" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Ghi chú</label><input id="note" class="form-control"></div>
                <div class="col-md-2 d-flex align-items-end"><button id="savePackageBtn" class="btn btn-primary btn-icon w-100 justify-content-center"><i data-lucide="printer"></i>Lưu và in tem</button></div>
            </div></div>
        </section>

        <section id="historyPanel" data-workspace-panel="history" class="panel d-none">
            <div class="panel-header"><h2 class="panel-title">Kiện vừa nhập</h2></div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Mã kiện</th><th>Vị trí</th><th>Mã TP</th><th>Mã nội bộ</th><th>Size</th><th>Màu</th><th>Side</th><th>SL</th><th></th></tr></thead>
                    <tbody id="packageRows"></tbody>
                </table>
            </div>
        </section>
    </main>
    <div class="modal fade" id="locationModal" tabindex="-1" aria-labelledby="locationModalTitle" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="locationModalTitle">Lưu vị trí kho</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Vị trí kho</label><input id="editLocationCode" class="form-control" placeholder="TP-A01-T01-O01"></div>
                    <div class="mb-3"><label class="form-label">Mã kho</label><input id="editWarehouseCode" class="form-control" placeholder="KTPHAM"></div>
                    <div class="mb-3"><label class="form-label">Tên vị trí</label><input id="editLocationName" class="form-control" placeholder="Kệ thành phẩm A01"></div>
                    <div id="locationSaveStatus" class="small text-muted"></div>
                </div>
                <div class="modal-footer">
                    <button id="deleteLocationBtn" type="button" class="btn btn-outline-danger btn-icon me-auto d-none"><i data-lucide="trash-2"></i>Xóa vị trí</button>
                    <button id="useLocationBtn" type="button" class="btn btn-outline-primary btn-icon"><i data-lucide="package-plus"></i>Nhập hàng tại vị trí này</button>
                    <button id="printLocationBtn" type="button" class="btn btn-outline-secondary btn-icon"><i data-lucide="printer"></i>In tem QR</button>
                    <button id="saveLocationBtn" type="button" class="btn btn-primary btn-icon"><i data-lucide="save"></i>Lưu vị trí</button>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const value = id => document.getElementById(id).value.trim();
        const locationModal = new bootstrap.Modal(document.getElementById('locationModal'));
        let locations = [];
        let editingLocationId = null;
        let selectedAccountingProduct = '';
        let productSearchTimer;

        function refreshIcons() {
            if (window.lucide) lucide.createIcons();
        }

        function formatNumber(value) {
            return Number(value || 0).toLocaleString('vi-VN', { maximumFractionDigits: 3 });
        }

        function switchWorkspace(view) {
            document.querySelectorAll('[data-workspace-panel]').forEach(panel => {
                panel.classList.toggle('d-none', panel.dataset.workspacePanel !== view);
            });
            document.querySelectorAll('[data-workspace-view]').forEach(tab => {
                tab.classList.toggle('is-active', tab.dataset.workspaceView === view);
            });
            if (view === 'entry') document.getElementById('maSp').focus();
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }

        function hideProductResults() {
            document.getElementById('maSpResults').classList.add('d-none');
        }

        function selectAccountingProduct(code) {
            document.getElementById('maSp').value = code;
            selectedAccountingProduct = code;
            hideProductResults();
        }

        function searchAccountingProducts() {
            const keyword = value('maSp');
            const results = document.getElementById('maSpResults');
            selectedAccountingProduct = '';
            clearTimeout(productSearchTimer);
            if (!keyword) {
                hideProductResults();
                return;
            }

            productSearchTimer = setTimeout(() => {
                fetch(`/api/thanh-pham-ke-toan/goi-y?keyword=${encodeURIComponent(keyword)}`)
                    .then(r => jsonOrError(r, 'Không tải được mã TP kế toán'))
                    .then(result => {
                        results.innerHTML = (result.data || []).map(item => `<button type="button" class="product-option" onclick="selectAccountingProduct('${String(item.Ma_sp || '').replace(/'/g, "\\'")}')">
                            <div class="product-option-code">${escapeHtml(item.Ma_sp)}</div>
                            <div class="product-option-name">${escapeHtml(item.Ten_hh || 'Chưa có tên hàng')}${item.Dvt ? ` · ${escapeHtml(item.Dvt)}` : ''}</div>
                        </button>`).join('') || '<div class="p-3 text-muted small">Không tìm thấy mã thành phẩm</div>';
                        results.classList.remove('d-none');
                    })
                    .catch(error => {
                        results.innerHTML = `<div class="p-3 text-danger small">${escapeHtml(error.message)}</div>`;
                        results.classList.remove('d-none');
                    });
            }, 250);
        }

        function selectedLocation() {
            const code = value('locationCode').toUpperCase();
            return locations.find(location => location.location_code === code);
        }

        function fillSelectedLocation() {
            const location = selectedLocation();
            if (!location) return;
            document.getElementById('locationCode').value = location.location_code;
            document.getElementById('warehouseCode').value = location.warehouse_code || '';
            document.getElementById('entryLocationContext').textContent = `Đang nhập tại ${location.location_code} · Kho ${location.warehouse_code || '-'}`;
        }

        function setLocationStatus(message, isError = false) {
            const status = document.getElementById('locationSaveStatus');
            status.textContent = message;
            status.className = `small ${isError ? 'text-danger' : 'text-success'}`;
        }

        function jsonOrError(response, fallback) {
            return response.json().catch(() => ({})).then(result => {
                if (response.ok) return result;
                const validation = result.errors ? Object.values(result.errors).flat().join('\n') : '';
                throw new Error(validation || result.message || fallback);
            });
        }

        function loadLocations() {
            return fetch('/api/kiem-ton-kho/vi-tri').then(r => r.json()).then(result => {
                locations = result.data || [];
                document.getElementById('locationOptions').innerHTML = locations.map(x => `<option value="${x.location_code}">${x.location_name || ''}</option>`).join('');
                document.getElementById('locationCount').textContent = `${locations.length} vị trí`;
                document.getElementById('kpiLocations').textContent = formatNumber(locations.length);
                document.getElementById('kpiCountingLocations').textContent = formatNumber(locations.filter(x => x.status === 'counting').length);
                renderLocations();
                fillSelectedLocation();
            });
        }

        function loadWarehouseStats() {
            const params = new URLSearchParams({ checked_at: value('checkedAt') });
            return fetch(`/api/kiem-ton-kho/kien?${params}`).then(r => r.json()).then(result => {
                document.getElementById('kpiPackages').textContent = formatNumber(result.summary?.package_count);
                document.getElementById('kpiQuantity').textContent = formatNumber(result.summary?.total_quantity);
            });
        }

        function renderLocations() {
            const keyword = value('locationSearch').toUpperCase();
            const selectedCode = value('locationCode').toUpperCase();
            const filtered = locations.filter(x => `${x.location_code} ${x.warehouse_code || ''} ${x.location_name || ''}`.toUpperCase().includes(keyword));
            document.getElementById('locationRows').innerHTML = filtered.map(x => `
                <div class="location-item ${x.location_code === selectedCode ? 'is-active' : ''}">
                    <button type="button" class="btn p-0 border-0 text-start flex-grow-1" onclick="selectLocation('${x.location_code}')">
                        <div class="location-code">${x.location_code}</div>
                        <div class="location-meta">${x.warehouse_code || 'Chưa có mã kho'}${x.location_name ? ` · ${x.location_name}` : ''}</div>
                    </button>
                    <div class="location-actions">
                        <a class="btn btn-outline-primary" title="Xem chi tiết vị trí" href="/client/kiem-ton-kho/vi-tri/${x.id}" target="_blank"><i data-lucide="eye"></i></a>
                        <button type="button" class="btn btn-outline-secondary" title="Quản lý vị trí" onclick="openLocationModal('${x.location_code}')"><i data-lucide="settings-2"></i></button>
                    </div>
                </div>`).join('') || '<div class="empty-state text-center">Không tìm thấy vị trí</div>';
            refreshIcons();
        }

        function selectLocation(locationCode) {
            document.getElementById('locationCode').value = locationCode;
            fillSelectedLocation();
            renderLocations();
            loadPackages();
            loadLocationContents();
        }

        function openLocationModal(locationCode = '') {
            const location = locations.find(item => item.location_code === locationCode) || selectedLocation();
            editingLocationId = location?.id || null;
            document.getElementById('locationModalTitle').textContent = location ? 'Chỉnh sửa vị trí kho' : 'Thêm vị trí kho';
            document.getElementById('editLocationCode').value = location?.location_code || '';
            document.getElementById('editWarehouseCode').value = location?.warehouse_code || value('warehouseCode');
            document.getElementById('editLocationName').value = location?.location_name || '';
            document.getElementById('deleteLocationBtn').classList.toggle('d-none', !location);
            document.getElementById('useLocationBtn').classList.toggle('d-none', !location);
            document.getElementById('printLocationBtn').classList.toggle('d-none', !location);
            setLocationStatus('');
            locationModal.show();
        }

        function loadPackages() {
            const params = new URLSearchParams({ checked_at: value('checkedAt') });
            if (value('locationCode')) params.set('location_code', value('locationCode').toUpperCase());
            fetch(`/api/kiem-ton-kho/kien?${params}`).then(r => r.json()).then(result => {
                document.getElementById('packageRows').innerHTML = (result.data || []).map(x => `<tr>
                    <td>${x.package_code}</td><td>${x.location?.location_code || ''}</td><td>${x.ma_sp}</td><td>${x.internal_item_code}</td>
                    <td>${x.size}</td><td>${x.color}</td><td>${x.side}</td><td>${x.quantity}</td>
                    <td class="text-nowrap">
                        <a class="btn btn-sm btn-outline-secondary btn-icon" target="_blank" href="/client/kiem-ton-kho/tem-kien/${x.id}"><i data-lucide="printer"></i>In</a>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-package-btn" data-id="${x.id}" data-code="${x.package_code}" title="Xóa kiện"><i data-lucide="trash-2"></i></button>
                    </td>
                </tr>`).join('');
                refreshIcons();
            });
        }

        function loadLocationContents() {
            const locationCode = value('locationCode').toUpperCase();
            const rows = document.getElementById('locationContentRows');
            const summary = document.getElementById('locationSummary');
            const location = selectedLocation();
            document.getElementById('selectedLocationTitle').textContent = locationCode || 'chưa chọn vị trí';
            document.getElementById('selectedLocationName').textContent = location?.location_name || '';
            if (!locationCode) {
                rows.innerHTML = '<tr><td colspan="7" class="empty-state text-center">Chọn vị trí để xem hàng đang chứa</td></tr>';
                summary.innerHTML = '';
                return;
            }

            const params = new URLSearchParams({ location_code: locationCode, checked_at: value('checkedAt') });
            fetch(`/api/kiem-ton-kho/noi-dung-vi-tri?${params}`).then(r => r.json()).then(result => {
                rows.innerHTML = (result.data || []).map(x => `<tr>
                    <td>${x.internal_item_code || ''}</td><td>${x.ma_sp || ''}</td><td>${x.size || ''}</td>
                    <td>${x.color || ''}</td><td>${x.side || ''}</td><td class="text-end">${x.package_count || 0}</td>
                    <td class="text-end">${x.total_quantity || 0}</td>
                </tr>`).join('') || '<tr><td colspan="7" class="empty-state text-center">Vị trí chưa có kiện trong ngày kiểm kê</td></tr>';
                summary.innerHTML = `<span class="summary-chip">${result.summary?.item_count || 0} mã</span>
                    <span class="summary-chip">${result.summary?.package_count || 0} kiện</span>
                    <span class="summary-chip">SL ${result.summary?.total_quantity || 0}</span>`;
            });
        }

        document.getElementById('saveLocationBtn').addEventListener('click', () => {
            fetch('/api/kiem-ton-kho/vi-tri', {
                method: 'POST', headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify({location_code:value('editLocationCode'), warehouse_code:value('editWarehouseCode'), location_name:value('editLocationName')})
            }).then(r => jsonOrError(r, 'Không lưu được vị trí'))
              .then(result => {
                  setLocationStatus(`Đã lưu ${result.data.location_code} / ${result.data.warehouse_code || 'chưa có mã kho'}`);
                  document.getElementById('locationCode').value = result.data.location_code;
                  document.getElementById('warehouseCode').value = result.data.warehouse_code || '';
                  loadLocations();
                  loadPackages();
                  loadWarehouseStats();
                  loadLocationContents();
              }).catch(e => { setLocationStatus(e.message, true); alert(e.message); });
        });

        document.getElementById('deleteLocationBtn').addEventListener('click', () => {
            const code = value('editLocationCode').toUpperCase();
            if (!editingLocationId || !confirm(`Xóa vị trí ${code}?`)) return;
            fetch(`/api/kiem-ton-kho/vi-tri/${editingLocationId}`, {
                method: 'DELETE', headers: {'Accept':'application/json','X-CSRF-TOKEN':csrfToken}
            }).then(r => jsonOrError(r, 'Không xóa được vị trí'))
              .then(() => {
                  if (value('locationCode').toUpperCase() === code) {
                      document.getElementById('locationCode').value = '';
                      document.getElementById('warehouseCode').value = '';
                  }
                  editingLocationId = null;
                  locationModal.hide();
                  loadLocations();
                  loadPackages();
                  loadWarehouseStats();
                  loadLocationContents();
              }).catch(e => { setLocationStatus(e.message, true); alert(e.message); });
        });

        document.getElementById('savePackageBtn').addEventListener('click', () => {
            if (!selectedAccountingProduct || selectedAccountingProduct !== value('maSp')) {
                return alert('Chọn mã TP kế toán từ danh sách gợi ý trước khi lưu kiện.');
            }
            fetch('/api/kiem-ton-kho/kien', {
                method: 'POST', headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify({
                    location_code:value('locationCode'), ma_ko:value('warehouseCode'), checked_at:value('checkedAt'),
                    ma_sp:value('maSp'), internal_item_code:value('internalItemCode'), size:value('size'),
                    color:value('color'), side:value('side'), quantity:value('quantity'), note:value('note')
                })
            }).then(r => jsonOrError(r, 'Không lưu được kiện'))
              .then(result => {
                  window.open(result.print_url, '_blank');
                  ['internalItemCode','size','color','side','quantity','note'].forEach(id => document.getElementById(id).value = '');
                  loadPackages();
                  loadLocations();
                  loadWarehouseStats();
                  loadLocationContents();
              }).catch(e => alert(e.message));
        });

        document.getElementById('packageRows').addEventListener('click', event => {
            const button = event.target.closest('.delete-package-btn');
            if (!button || !confirm(`Xóa kiện ${button.dataset.code}? Số lượng sẽ được trừ khỏi đối chiếu tồn.`)) return;
            fetch(`/api/kiem-ton-kho/kien/${button.dataset.id}`, {
                method: 'DELETE', headers: {'Accept':'application/json','X-CSRF-TOKEN':csrfToken}
            }).then(r => jsonOrError(r, 'Không xóa được kiện'))
              .then(() => { loadPackages(); loadLocations(); loadWarehouseStats(); loadLocationContents(); })
              .catch(e => alert(e.message));
        });

        document.getElementById('printLocationBtn').addEventListener('click', () => {
            const code = value('editLocationCode').toUpperCase();
            const location = locations.find(item => item.location_code === code);
            if (!location) return alert('Lưu vị trí trước khi in tem.');
            window.open(`/client/kiem-ton-kho/tem-vi-tri/${location.id}`, '_blank');
        });
        document.getElementById('useLocationBtn').addEventListener('click', () => {
            const code = value('editLocationCode').toUpperCase();
            const location = locations.find(item => item.location_code === code);
            if (!location) return alert('Lưu vị trí trước khi nhập hàng.');
            selectLocation(location.location_code);
            locationModal.hide();
            switchWorkspace('entry');
        });
        document.getElementById('locationCode').addEventListener('change', () => { fillSelectedLocation(); renderLocations(); loadPackages(); loadLocationContents(); });
        document.getElementById('locationSearch').addEventListener('input', renderLocations);
        document.getElementById('maSp').addEventListener('input', searchAccountingProducts);
        document.addEventListener('click', event => {
            if (!event.target.closest('.product-search')) hideProductResults();
        });
        document.getElementById('checkedAt').addEventListener('change', () => { loadPackages(); loadWarehouseStats(); loadLocationContents(); });
        const requestedLocation = new URLSearchParams(window.location.search).get('location_code');
        if (requestedLocation) document.getElementById('locationCode').value = requestedLocation.toUpperCase();
        loadLocations().then(() => { loadPackages(); loadWarehouseStats(); loadLocationContents(); });
        refreshIcons();
    </script>
</body>
</html>
