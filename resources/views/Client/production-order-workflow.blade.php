<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Lệnh SX trung tâm</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/warehouse-wms.css') }}?v={{ filemtime(public_path('css/warehouse-wms.css')) }}" rel="stylesheet">
    <style>
        body { background: #f6f9fc; color: #08233f; }
        .workflow-page { padding: 22px 28px 42px; }
        .workflow-topbar {
            position: sticky; top: 0; z-index: 20; display: flex; align-items: center; gap: 16px;
            min-height: 66px; padding: 12px 28px; background: rgba(255,255,255,.92);
            border-bottom: 1px solid #dbe7f3; backdrop-filter: blur(10px);
        }
        .workflow-title { margin: 0; font-size: 22px; font-weight: 800; letter-spacing: 0; }
        .workflow-search { position: relative; flex: 1; max-width: 560px; }
        .workflow-search input { height: 42px; padding-left: 42px; border-radius: 999px; border-color: #bfd0e4; background: #f8fbff; }
        .workflow-search i { position: absolute; left: 15px; top: 11px; width: 18px; height: 18px; color: #2563eb; }
        .workflow-heading { display: flex; justify-content: space-between; gap: 18px; align-items: flex-start; margin-bottom: 20px; }
        .workflow-heading h1 { margin: 0; font-size: 30px; font-weight: 850; letter-spacing: 0; }
        .workflow-heading p { margin: 4px 0 0; color: #55708c; font-size: 13px; }
        .kpi-grid { display: grid; grid-template-columns: repeat(5, minmax(150px, 1fr)); gap: 14px; margin-bottom: 18px; }
        .kpi-card { display: flex; gap: 12px; align-items: center; padding: 16px; border: 1px solid #dbe7f3; border-radius: 12px; background: #fff; box-shadow: 0 8px 24px rgba(15, 45, 80, .05); }
        .kpi-icon { display: grid; place-items: center; width: 42px; height: 42px; flex: 0 0 42px; border-radius: 10px; background: #edf5ff; color: #2563eb; }
        .kpi-label { color: #5c7895; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; }
        .kpi-value { font-size: 24px; line-height: 1.05; font-weight: 850; }
        .filter-panel, .workflow-panel { border: 1px solid #dbe7f3; border-radius: 14px; background: #fff; box-shadow: 0 8px 24px rgba(15, 45, 80, .05); }
        .filter-panel { display: grid; grid-template-columns: minmax(220px, 1fr) 220px 140px; gap: 12px; padding: 16px; margin-bottom: 18px; }
        .filter-panel label { color: #39536f; font-size: 12px; font-weight: 750; margin-bottom: 6px; }
        .workflow-panel__header { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 16px 18px; border-bottom: 1px solid #e3edf7; }
        .workflow-panel__header h2 { margin: 0; font-size: 17px; font-weight: 850; }
        .table-wrap { overflow-x: auto; }
        .workflow-table { min-width: 1320px; margin: 0; }
        .workflow-table thead th { background: #06233f; color: #fff; font-size: 12px; font-weight: 800; white-space: nowrap; vertical-align: middle; }
        .workflow-table tbody td { vertical-align: middle; font-size: 13px; }
        .code { font-family: Consolas, "SFMono-Regular", monospace; font-weight: 800; color: #0b5fc7; }
        .muted { color: #66809a; font-size: 12px; }
        .item-stack { display: flex; flex-wrap: wrap; gap: 6px; max-width: 380px; }
        .item-chip { padding: 5px 7px; border: 1px solid #d7e5f4; border-radius: 8px; background: #f8fbff; color: #14314f; font-size: 12px; }
        .item-chip__edit { padding: 0 3px; border: 0; background: transparent; color: #2563eb; font-weight: 800; }
        .item-chip__source { display: block; margin-top: 2px; color: #64748b; font-size: 10px; }
        .item-chip__image { margin-left:4px; vertical-align:middle; }
        .status-pill { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 850; white-space: nowrap; }
        .status-pill::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
        .status-planned { background: #eef4fb; color: #45627f; }
        .status-received { background: #e8fff4; color: #087f5b; }
        .status-in_production { background: #fff7e6; color: #b36b00; }
        .status-production_done { background: #eaf7ff; color: #0b6db3; }
        .status-shipped_customer { background: #edf2ff; color: #2846a0; }
        .progress-track { height: 8px; width: 150px; border-radius: 999px; background: #e8eff7; overflow: hidden; }
        .progress-fill { height: 100%; border-radius: inherit; background: linear-gradient(90deg, #60a5fa, #2563eb); }
        .action-row { display: flex; gap: 6px; flex-wrap: wrap; }
        .empty-row { padding: 34px; color: #64748b; text-align: center; }
        .group-list { display: grid; gap: 12px; padding: 14px; }
        .order-group { border: 1px solid #dbe7f3; border-radius: 12px; overflow: hidden; background: #fff; }
        .order-group[open] { box-shadow: 0 12px 28px rgba(15, 45, 80, .06); }
        .order-group__summary {
            display: grid; grid-template-columns: minmax(190px, 1fr) repeat(4, minmax(90px, auto)) 24px;
            align-items: center; gap: 14px; padding: 14px 16px; cursor: pointer; list-style: none;
            background: linear-gradient(180deg, #ffffff, #f8fbff); transition: background .2s ease;
        }
        .order-group__summary:hover { background: #f1f7ff; }
        .order-group__summary::-webkit-details-marker { display: none; }
        .order-group__summary::after {
            content: ""; width: 9px; height: 9px; border-right: 2px solid #2563eb; border-bottom: 2px solid #2563eb;
            transform: rotate(45deg); transition: transform .2s ease;
        }
        .order-group[open] > .order-group__summary::after { transform: rotate(225deg); }
        .group-title { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .group-title strong { font-size: 15px; }
        .group-dot { width: 10px; height: 10px; border-radius: 50%; background: #94a3b8; box-shadow: 0 0 0 4px rgba(148, 163, 184, .14); }
        .group-stat { text-align: right; }
        .group-stat__label { color: #6b8199; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; }
        .group-stat__value { color: #092846; font-size: 14px; font-weight: 850; }
        .order-group__body { border-top: 1px solid #e3edf7; }
        .group-planned .group-dot { background: #64748b; box-shadow: 0 0 0 4px rgba(100,116,139,.14); }
        .group-received .group-dot { background: #10b981; box-shadow: 0 0 0 4px rgba(16,185,129,.14); }
        .group-in_production .group-dot { background: #f59e0b; box-shadow: 0 0 0 4px rgba(245,158,11,.16); }
        .group-production_done .group-dot { background: #0ea5e9; box-shadow: 0 0 0 4px rgba(14,165,233,.14); }
        .group-shipped_customer .group-dot { background: #4f46e5; box-shadow: 0 0 0 4px rgba(79,70,229,.14); }
        @media (max-width: 1100px) {
            .workflow-page { padding: 18px; }
            .workflow-heading { flex-direction: column; }
            .kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .filter-panel { grid-template-columns: 1fr; }
            .order-group__summary { grid-template-columns: 1fr 24px; }
            .group-stat { display: none; }
        }
    </style>
</head>
<body>
    @include('layouts.partials.sidebar')

    <header class="workflow-topbar">
        <h1 class="workflow-title">WMS May Mặc</h1>
        <div class="workflow-search">
            <i data-lucide="search"></i>
            <input id="topKeyword" class="form-control" placeholder="Tìm lệnh SX, khách, mã hàng, size hoặc màu...">
        </div>
        <a class="btn btn-outline-primary" href="{{ url('/client/lenh-san-xuat-sheet') }}">Dữ liệu gốc</a>
    </header>

    <main class="workflow-page">
        <div class="workflow-heading">
            <div>
                <h1>Lệnh SX trung tâm</h1>
                <p>Một lệnh là một trục quản lý: kế hoạch, nhập kho, xuất sản xuất, hoàn tất và xuất khách.</p>
            </div>
            <div class="action-row">
                <a class="btn btn-primary" href="{{ url('/client/nhap-thanh-pham-nhanh') }}">Nhập TP nhanh</a>
                <a class="btn btn-outline-primary" href="{{ url('/client/xuat-vat-tu-noi-bo') }}">Xuất kho</a>
                <a class="btn btn-outline-primary" href="{{ url('/client/theo-doi-san-xuat') }}">Đang sản xuất</a>
            </div>
        </div>

        <section class="kpi-grid">
            <article class="kpi-card"><div class="kpi-icon"><i data-lucide="clipboard-list"></i></div><div><div class="kpi-label">Lệnh đang tải</div><div id="kpiOrders" class="kpi-value">0</div><div class="muted">Tối đa 100, tìm kiếm toàn bộ</div></div></article>
            <article class="kpi-card"><div class="kpi-icon"><i data-lucide="package-check"></i></div><div><div class="kpi-label">Đã nhập</div><div id="kpiReceived" class="kpi-value">0</div></div></article>
            <article class="kpi-card"><div class="kpi-icon"><i data-lucide="send"></i></div><div><div class="kpi-label">Đang SX</div><div id="kpiProduction" class="kpi-value">0</div></div></article>
            <article class="kpi-card"><div class="kpi-icon"><i data-lucide="badge-check"></i></div><div><div class="kpi-label">SX xong</div><div id="kpiDone" class="kpi-value">0</div></div></article>
            <article class="kpi-card"><div class="kpi-icon"><i data-lucide="truck"></i></div><div><div class="kpi-label">Đã xuất khách</div><div id="kpiShipped" class="kpi-value">0</div></div></article>
        </section>

        <section class="filter-panel">
            <div>
                <label for="keyword">Tìm kiếm</label>
                <input id="keyword" class="form-control" placeholder="Lệnh, khách, PO, mã hàng, màu...">
            </div>
            <div>
                <label for="status">Trạng thái</label>
                <select id="status" class="form-select">
                    <option value="">Tất cả</option>
                    <option value="planned">Chưa phát sinh</option>
                    <option value="received">Đã nhập kho</option>
                    <option value="in_production">Đang sản xuất</option>
                    <option value="production_done">Sản xuất xong</option>
                    <option value="shipped_customer">Đã xuất khách</option>
                </select>
            </div>
            <div class="d-flex align-items-end">
                <button id="clearFilter" class="btn btn-outline-secondary w-100">Xóa lọc</button>
            </div>
        </section>

        <section class="workflow-panel">
            <div class="workflow-panel__header">
                <h2>Danh sách theo lệnh</h2>
                <span id="resultLabel" class="muted">Đang tải...</span>
            </div>
            <div id="groups" class="group-list">
                <div class="empty-row">Đang tải dữ liệu...</div>
            </div>
        </section>
    </main>

    <div class="modal fade" id="standardItemModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="standardItemForm" class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5">Chu&#7849;n h&#243;a m&#227; h&#224;ng trong l&#7879;nh</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="&#272;&#243;ng"></button>
                </div>
                <div class="modal-body">
                    <input id="standardLineId" type="hidden">
                    <div class="mb-3">
                        <label class="form-label">L&#7879;nh s&#7843;n xu&#7845;t</label>
                        <input id="standardProductionOrder" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">M&#227; ngu&#7891;n t&#7915; Google Sheet</label>
                        <input id="sourceItemCode" class="form-control" readonly>
                    </div>
                    <div>
                        <label for="standardItemCode" class="form-label">T&#236;m d&#242;ng Danh m&#7909;c chu&#7849;n</label>
                        <input id="standardCatalogId" type="hidden">
                        <input id="standardItemCode" class="form-control" autocomplete="off" placeholder="G&#245; m&#227;, t&#234;n h&#224;ng ho&#7863;c m&#224;u">
                        <select id="standardCatalogResults" class="form-select mt-2" size="6"></select>
                        <div class="form-text">C&#249;ng m&#7897;t m&#227; c&#243; th&#7875; c&#243; nhi&#7873;u d&#242;ng m&#224;u. H&#227;y ch&#7885;n &#273;&#250;ng t&#234;n h&#224;ng v&#224; m&#224;u.</div>
                    </div>
                    <div id="standardItemStatus" class="small mt-2"></div>
                </div>
                <div class="modal-footer">
                    <button id="resetStandardItem" type="button" class="btn btn-outline-secondary">D&#249;ng m&#227; g&#7889;c</button>
                    <button type="submit" class="btn btn-primary">L&#432;u m&#227; chu&#7849;n</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    @include('layouts.partials.catalog-image-paste-modal')
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script>
        const groupsEl = document.getElementById('groups');
        const keywordEl = document.getElementById('keyword');
        const topKeywordEl = document.getElementById('topKeyword');
        const statusEl = document.getElementById('status');
        let timer = null;
        let catalogTimer = null;
        const standardModal = new bootstrap.Modal(document.getElementById('standardItemModal'));
        const standardItemCodeEl = document.getElementById('standardItemCode');
        const standardCatalogIdEl = document.getElementById('standardCatalogId');
        const standardCatalogResultsEl = document.getElementById('standardCatalogResults');
        const standardItemStatusEl = document.getElementById('standardItemStatus');
        const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
        const num = value => Number(value || 0).toLocaleString('vi-VN', { maximumFractionDigits: 3 });
        const statuses = {
            planned: ['Chưa phát sinh', 'status-planned'],
            received: ['Đã nhập kho', 'status-received'],
            in_production: ['Đang sản xuất', 'status-in_production'],
            production_done: ['Sản xuất xong', 'status-production_done'],
            shipped_customer: ['Đã xuất khách', 'status-shipped_customer'],
            empty: ['Chưa có dữ liệu', 'status-planned'],
        };

        function setSummary(summary) {
            document.getElementById('kpiOrders').textContent = num(summary.order_count);
            document.getElementById('kpiReceived').textContent = num(summary.received_count);
            document.getElementById('kpiProduction').textContent = num(summary.in_production_count);
            document.getElementById('kpiDone').textContent = num(summary.production_done_count);
            document.getElementById('kpiShipped').textContent = num(summary.shipped_customer_count);
        }

        function chips(items) {
            if (!items.length) return '<span class="muted">Chưa có dòng hàng</span>';
            return `<div class="item-stack">${items.map(item => {
                const image = imageUrl(item.image_url || '');
                const imageAction = item.item_code ? `<button type="button" class="catalog-image-trigger item-chip__image" title="${image ? 'Xem hoặc thay ảnh danh mục' : 'Paste ảnh vào danh mục'}" data-catalog-image-open data-catalog-id="${esc(item.catalog_id || '')}" data-item-code="${esc(item.item_code || '')}" data-item-name="${esc(item.description || '')}" data-unit="${esc(item.unit || '')}" data-size="${esc(item.size || '')}" data-color="${esc(item.color || '')}" data-image-url="${esc(image)}">
                    ${image ? `<img loading="lazy" src="${esc(image)}" alt="${esc(item.item_code || 'Ảnh danh mục')}">` : '<i data-lucide="image-plus"></i> Ảnh'}
                </button>` : '';
                return `<span class="item-chip">
                <strong>${esc(item.item_code || 'Mã trống')}</strong>
                <button type="button" class="item-chip__edit" title="Sửa mã chuẩn" data-edit-standard="${esc(item.id)}" data-production-order="${esc(item.production_order || '')}" data-source-code="${esc(item.source_item_code || '')}" data-standard-code="${esc(item.standard_item_code || '')}" data-standard-catalog-id="${esc(item.standard_catalog_id || '')}">&#9998;</button>
                ${imageAction}
                ${item.standard_item_code ? `<span class="item-chip__source">Gốc: ${esc(item.source_item_code || '-')}</span>` : ''}
                ${item.size ? ` · Size ${esc(item.size)}` : ''}
                ${item.color ? ` · ${esc(item.color)}` : ''}
                ${item.quantity ? ` · ${num(item.quantity)} ${esc(item.unit || '')}` : ''}
            </span>`;
            }).join('')}</div>`;
        }

        function imageUrl(value) {
            const url = String(value || '').trim();
            return /^(https?:)?\/\//i.test(url) || url.startsWith('/') ? url : '';
        }

        function codeList(codes) {
            return (codes || []).slice(0, 4).map(code => `<div class="code">${esc(code)}</div>`).join('') || '<span class="muted">-</span>';
        }

        function openStandardItemEditor(button) {
            document.getElementById('standardLineId').value = button.dataset.editStandard || '';
            document.getElementById('standardProductionOrder').value = button.dataset.productionOrder || '';
            document.getElementById('sourceItemCode').value = button.dataset.sourceCode || '';
            standardCatalogIdEl.value = button.dataset.standardCatalogId || '';
            standardItemCodeEl.value = button.dataset.standardCode || button.dataset.sourceCode || '';
            standardCatalogResultsEl.innerHTML = '';
            standardItemStatusEl.textContent = '';
            standardModal.show();
            loadCatalogOptions();
            setTimeout(() => {
                standardItemCodeEl.focus();
                standardItemCodeEl.select();
            }, 180);
        }

        function loadCatalogOptions() {
            const keyword = standardItemCodeEl.value.trim();
            clearTimeout(catalogTimer);
            if (keyword.length < 2) return;
            catalogTimer = setTimeout(() => {
                fetch(`/api/danh-muc-noi-bo?keyword=${encodeURIComponent(keyword)}&limit=50`)
                    .then(response => response.json())
                    .then(result => {
                        const selectedId = String(standardCatalogIdEl.value || '');
                        standardCatalogResultsEl.innerHTML = (result.data || []).filter(item => item.item_code).map(item => {
                            const detail = [item.item_code, item.item_name, item.color, item.size, item.source_row ? `D\u00f2ng ${item.source_row}` : ''].filter(Boolean).join(' - ');
                            return `<option value="${esc(item.id)}" data-code="${esc(item.item_code || '')}" ${String(item.id) === selectedId ? 'selected' : ''}>${esc(detail)}</option>`;
                        }).join('');
                    })
                    .catch(() => {});
            }, 120);
        }

        function saveStandardItem(event) {
            event.preventDefault();
            const lineId = document.getElementById('standardLineId').value;
            standardItemStatusEl.className = 'small mt-2 text-primary';
            standardItemStatusEl.textContent = 'Đang lưu...';
            fetch(`/api/lenh-san-xuat-trung-tam/dong/${encodeURIComponent(lineId)}`, {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ standard_catalog_id: standardCatalogIdEl.value ? Number(standardCatalogIdEl.value) : null }),
            })
                .then(async response => {
                    const result = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(result.message || 'Không lưu được mã chuẩn.');
                    return result;
                })
                .then(() => {
                    standardModal.hide();
                    load();
                })
                .catch(error => {
                    standardItemStatusEl.className = 'small mt-2 text-danger';
                    standardItemStatusEl.textContent = error.message;
                });
        }

        function rowHtml(row) {
            const status = statuses[row.status] || statuses.empty;
            const progressBase = Math.max(row.planned_quantity || 0, row.received_quantity || 0, row.production_issue_quantity || 0, row.customer_issue_quantity || 0, 1);
            const progress = Math.min(100, Math.round(((row.customer_issue_quantity || row.production_issue_quantity || row.received_quantity || 0) / progressBase) * 100));
            return `<tr>
                <td><div class="code">${esc(row.production_order)}</div><div class="muted">${esc(row.promised_date || '-')}</div></td>
                <td><strong>${esc(row.customer || '-')}</strong><div class="muted">${esc(row.purchase_order || '-')}</div></td>
                <td>${chips(row.items || [])}<div class="muted mt-1">${num(row.line_count)} dòng</div></td>
                <td class="text-end fw-bold">${num(row.planned_quantity)}</td>
                <td class="text-end">${num(row.received_quantity)}<div class="muted">${num(row.receipt_document_count)} phiếu</div></td>
                <td class="text-end">${num(row.production_issue_quantity)}<div class="muted">${num(row.production_document_count)} phiếu</div></td>
                <td class="text-end">${num(row.customer_issue_quantity)}<div class="muted">${num(row.customer_document_count)} phiếu</div></td>
                <td><div class="progress-track"><div class="progress-fill" style="width:${progress}%"></div></div><div class="muted mt-1">${progress}%</div></td>
                <td><span class="status-pill ${status[1]}">${status[0]}</span></td>
                <td>
                    <div class="muted">Nhập</div>${codeList(row.receipt_codes)}
                    <div class="muted mt-1">Xuất SX</div>${codeList(row.production_issue_codes)}
                    <div class="muted mt-1">Xuất khách</div>${codeList(row.customer_issue_codes)}
                </td>
                <td>
                    <div class="action-row">
                        <a class="btn btn-sm btn-outline-primary" href="/client/nhap-thanh-pham-nhanh?production_order=${encodeURIComponent(row.production_order)}">Nhập</a>
                        <a class="btn btn-sm btn-outline-primary" href="/client/xuat-vat-tu-noi-bo?production_order=${encodeURIComponent(row.production_order)}">Xuất</a>
                        <a class="btn btn-sm btn-outline-secondary" href="/client/theo-doi-san-xuat?keyword=${encodeURIComponent(row.production_order)}">Theo dõi</a>
                    </div>
                </td>
            </tr>`;
        }

        function groupTable(rows) {
            return `<div class="table-wrap order-group__body">
                <table class="table workflow-table">
                    <thead>
                        <tr>
                            <th>Lệnh SX</th>
                            <th>Khách / PO</th>
                            <th>Hàng trong lệnh</th>
                            <th class="text-end">SL kế hoạch</th>
                            <th class="text-end">Đã nhập</th>
                            <th class="text-end">Xuất SX</th>
                            <th class="text-end">Xuất khách</th>
                            <th>Tiến độ</th>
                            <th>Trạng thái</th>
                            <th>Phiếu liên quan</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>${rows.map(rowHtml).join('')}</tbody>
                </table>
            </div>`;
        }

        function groupSummary(key, rows) {
            const status = statuses[key] || statuses.empty;
            const planned = rows.reduce((sum, row) => sum + Number(row.planned_quantity || 0), 0);
            const received = rows.reduce((sum, row) => sum + Number(row.received_quantity || 0), 0);
            const production = rows.reduce((sum, row) => sum + Number(row.production_issue_quantity || 0), 0);
            const customer = rows.reduce((sum, row) => sum + Number(row.customer_issue_quantity || 0), 0);
            return `<summary class="order-group__summary">
                <div class="group-title"><span class="group-dot"></span><div><strong>${status[0]}</strong><div class="muted">${num(rows.length)} lệnh</div></div></div>
                <div class="group-stat"><div class="group-stat__label">Kế hoạch</div><div class="group-stat__value">${num(planned)}</div></div>
                <div class="group-stat"><div class="group-stat__label">Đã nhập</div><div class="group-stat__value">${num(received)}</div></div>
                <div class="group-stat"><div class="group-stat__label">Xuất SX</div><div class="group-stat__value">${num(production)}</div></div>
                <div class="group-stat"><div class="group-stat__label">Xuất khách</div><div class="group-stat__value">${num(customer)}</div></div>
            </summary>`;
        }

        function renderRows(data) {
            document.getElementById('resultLabel').textContent = `${num(data.length)} lệnh`;
            if (!data.length) {
                groupsEl.innerHTML = '<div class="empty-row">Không có lệnh phù hợp.</div>';
                return;
            }

            const order = ['in_production', 'received', 'planned', 'production_done', 'shipped_customer', 'empty'];
            const grouped = data.reduce((bucket, row) => {
                const key = row.status || 'empty';
                bucket[key] = bucket[key] || [];
                bucket[key].push(row);
                return bucket;
            }, {});

            groupsEl.innerHTML = order
                .filter(key => grouped[key]?.length)
                .map((key, index) => `<details class="order-group group-${esc(key)}" ${index < 2 ? 'open' : ''}>
                    ${groupSummary(key, grouped[key])}
                    ${groupTable(grouped[key])}
                </details>`)
                .join('');
            if (window.lucide) lucide.createIcons();
        }

        function load() {
            const params = new URLSearchParams({ limit: 100 });
            if (keywordEl.value.trim()) params.set('keyword', keywordEl.value.trim());
            if (statusEl.value) params.set('status', statusEl.value);
            groupsEl.innerHTML = '<div class="empty-row">Đang tải dữ liệu...</div>';
            fetch('/api/lenh-san-xuat-trung-tam?' + params.toString(), { headers: { Accept: 'application/json' } })
                .then(async response => {
                    const json = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(json.message || 'Không tải được dữ liệu lệnh');
                    return json;
                })
                .then(result => {
                    setSummary(result.summary || {});
                    renderRows(result.data || []);
                })
                .catch(error => {
                    groupsEl.innerHTML = `<div class="empty-row text-danger">${esc(error.message)}</div>`;
                });
        }

        function queue(source) {
            if (source === topKeywordEl) keywordEl.value = topKeywordEl.value;
            if (source === keywordEl) topKeywordEl.value = keywordEl.value;
            clearTimeout(timer);
            timer = setTimeout(load, 250);
        }

        keywordEl.addEventListener('input', () => queue(keywordEl));
        topKeywordEl.addEventListener('input', () => queue(topKeywordEl));
        statusEl.addEventListener('change', load);
        document.getElementById('clearFilter').addEventListener('click', () => {
            keywordEl.value = '';
            topKeywordEl.value = '';
            statusEl.value = '';
            load();
        });
        groupsEl.addEventListener('click', event => {
            const imageButton = event.target.closest('[data-catalog-image-open]');
            if (imageButton) {
                window.CatalogImagePaste?.open({
                    catalogId: imageButton.dataset.catalogId,
                    itemCode: imageButton.dataset.itemCode,
                    itemName: imageButton.dataset.itemName,
                    unit: imageButton.dataset.unit,
                    size: imageButton.dataset.size,
                    color: imageButton.dataset.color,
                    imageUrl: imageButton.dataset.imageUrl,
                });
                return;
            }
            const button = event.target.closest('[data-edit-standard]');
            if (button) openStandardItemEditor(button);
        });
        document.addEventListener('catalog-image-ready', event => {
            const data = event.detail || {};
            document.querySelectorAll(`[data-catalog-image-open][data-item-code="${CSS.escape(String(data.item_code || ''))}"]`).forEach(button => {
                button.dataset.catalogId = String(data.id || '');
            });
        });
        document.addEventListener('catalog-image-uploaded', event => {
            const data = event.detail || {};
            const url = imageUrl(data.image_url || '');
            const selector = [
                `[data-catalog-image-open][data-catalog-id="${CSS.escape(String(data.id || ''))}"]`,
                `[data-catalog-image-open][data-item-code="${CSS.escape(String(data.item_code || ''))}"]`,
            ].join(',');
            document.querySelectorAll(selector).forEach(button => {
                button.dataset.catalogId = String(data.id || '');
                button.dataset.imageUrl = url;
                button.title = 'Xem hoặc thay ảnh danh mục';
                button.innerHTML = `<img loading="lazy" src="${esc(url)}" alt="${esc(data.item_code || 'Ảnh danh mục')}">`;
            });
        });
        standardItemCodeEl.addEventListener('input', loadCatalogOptions);
        standardCatalogResultsEl.addEventListener('change', () => {
            const option = standardCatalogResultsEl.selectedOptions[0];
            standardCatalogIdEl.value = option?.value || '';
            if (option?.dataset.code) standardItemCodeEl.value = option.dataset.code;
        });
        document.getElementById('standardItemForm').addEventListener('submit', saveStandardItem);
        document.getElementById('resetStandardItem').addEventListener('click', () => {
            standardCatalogIdEl.value = '';
            standardItemCodeEl.value = '';
            document.getElementById('standardItemForm').requestSubmit();
        });

        if (window.lucide) lucide.createIcons();
        load();
    </script>
</body>
</html>
