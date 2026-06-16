<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Xuất vật tư nội bộ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/warehouse-wms.css') }}" rel="stylesheet">
    <style>
        body { background: #f6f7f9; color: #111827; }
        .panel { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; }
        .page-title { font-size: 24px; font-weight: 700; }
        .section-title { font-size: 16px; font-weight: 700; margin: 0; }
        .hint { color: #6b7280; font-size: 13px; }
        .metric { font-size: 20px; font-weight: 700; }
        .line-table input { min-width: 90px; }
        .product-search { position: relative; }
        .product-results {
            position: absolute;
            left: 0;
            right: 0;
            top: 100%;
            z-index: 20;
            max-height: 260px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.16);
        }
        .product-option {
            display: block;
            width: 100%;
            padding: 10px 12px;
            border: 0;
            border-bottom: 1px solid #eef2f7;
            background: transparent;
            text-align: left;
        }
        .product-option:hover { background: #eff6ff; }
        .product-code { font-weight: 700; }
        .product-name { color: #6b7280; font-size: 13px; }
        .table td, .table th { vertical-align: middle; }
        .order-load-status { min-height: 20px; margin-top: 5px; font-size: 12px; }
        .reference-value { min-width: 92px; background: #f8fafc !important; color: #475569; }
        .stock-warning { color: #b91c1c; font-size: 11px; font-weight: 700; white-space: nowrap; }
        .paste-dialog {
            width: min(1120px, calc(100vw - 32px));
            max-height: calc(100vh - 32px);
            padding: 0;
            border: 0;
            border-radius: 8px;
            box-shadow: 0 24px 70px rgba(15, 23, 42, .28);
        }
        .paste-dialog::backdrop { background: rgba(15, 23, 42, .55); }
        .paste-dialog__header, .paste-dialog__footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 16px;
            border-bottom: 1px solid #dbe2ea;
        }
        .paste-dialog__footer { border-top: 1px solid #dbe2ea; border-bottom: 0; }
        .paste-dialog__body { padding: 16px; overflow: auto; max-height: calc(100vh - 170px); }
        .paste-box {
            min-height: 180px;
            resize: vertical;
            font-family: Consolas, "Courier New", monospace;
            font-size: 12px;
            line-height: 1.45;
            white-space: pre;
        }
        .column-guide {
            padding: 10px 12px;
            background: #f0f6ff;
            border: 1px solid #bed5f4;
            border-radius: 5px;
            color: #17365d;
            font-size: 12px;
        }
        .paste-summary { display: flex; flex-wrap: wrap; gap: 8px; }
        .paste-summary span {
            padding: 5px 8px;
            border: 1px solid #dbe2ea;
            border-radius: 4px;
            background: #fff;
            font-size: 12px;
            font-weight: 700;
        }
        .paste-preview { min-width: 900px; font-size: 12px; }
        .paste-preview td, .paste-preview th { padding: 7px 8px; }
        .paste-row-warning td { background: #fff8e8 !important; }
        .paste-warning { color: #9a5b00; font-size: 11px; }
        @media (max-width: 767.98px) {
            .line-table { min-width: 980px; }
        }
        .panel { border-color: var(--wms-line); border-radius: 7px; box-shadow: none; }
        .page-title { color: var(--wms-ink); font-size: 28px; font-weight: 800; }
        .table thead th { background: var(--wms-navy); color: #fff; font-size: 12px; white-space: nowrap; }
        .table tbody tr:hover td { background: #edf5ff; }
        .metric { color: var(--wms-ink); font-size: 27px; font-weight: 800; }
        .form-control, .form-select { min-height: 40px; border-color: #bdc8d8; border-radius: 5px; }
        .btn { border-radius: 5px; }
    </style>
</head>

<body>
    @include('layouts.partials.sidebar')

    <header class="wms-topbar">
        <h1 class="wms-topbar__title">WMS May Mặc</h1>
        <div class="wms-global-search">
            <i data-lucide="search"></i>
            <input id="topIssueKeyword" aria-label="Tìm phiếu xuất" placeholder="Tìm phiếu xuất, mã vật tư hoặc người nhận...">
        </div>
        <div class="wms-topbar__actions">
            <a class="wms-btn" href="{{ url('/client/ton-kho-noi-bo') }}"><i data-lucide="boxes"></i> Xem tồn</a>
        </div>
    </header>

    <main class="wms-page">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
            <div>
                <h1 id="pageTitle" class="page-title mb-1">Phiếu xuất kho nội bộ</h1>
                <div id="pageHint" class="hint">TSoft kế toán chỉ đọc danh mục, không ghi dữ liệu.</div>
            </div>
            <div class="d-flex gap-2">
                <button id="reloadBtn" type="button" class="wms-btn"><i data-lucide="refresh-cw"></i>Tải lại</button>
                <button id="saveBtn" type="button" class="wms-btn wms-btn--primary"><i data-lucide="printer"></i>Xuất và in phiếu</button>
            </div>
        </div>

        <section class="wms-kpis">
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="clipboard-list"></i></div><div><div class="wms-kpi__label">Phiếu trong danh sách</div><div id="issueCount" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Theo bộ lọc</div></div></article>
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="list-ordered"></i></div><div><div class="wms-kpi__label">Dòng vật tư</div><div id="lineCount" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Chi tiết các phiếu</div></div></article>
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="package-minus"></i></div><div><div class="wms-kpi__label">Tổng số lượng</div><div id="totalQuantity" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Đã xuất nội bộ</div></div></article>
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="factory"></i></div><div><div class="wms-kpi__label">Luồng xuất</div><div id="issueTypeMetric" class="wms-kpi__value" style="font-size:18px">BTP sản xuất</div><div class="wms-kpi__meta">Chọn tại thông tin phiếu</div></div></article>
        </section>

        <section class="panel mb-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h2 class="section-title">Thông tin phiếu</h2>
                    <div class="hint">Tạo phiếu sẽ trừ tồn nội bộ theo mã, vị trí, mã nội bộ, size và màu nếu có nhập.</div>
                </div>
                <div class="d-flex gap-2">
                    <button id="openPasteImportBtn" type="button" class="btn btn-outline-primary btn-sm"><i data-lucide="clipboard-paste"></i> Paste Excel</button>
                    <button id="addLineBtn" type="button" class="btn btn-outline-primary btn-sm">Thêm dòng</button>
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-md-2"><label class="form-label">Nghiệp vụ</label><select id="issueType" class="form-select"><option value="production">Xuất BTP đi sản xuất</option><option value="material">Xuất vật tư</option></select></div>
                <div class="col-md-2"><label class="form-label">Ngày xuất</label><input id="issueDate" type="date" class="form-control" value="{{ now()->format('Y-m-d') }}"></div>
                <div class="col-md-2"><label class="form-label">Kho xuất</label><input id="warehouseCode" class="form-control" placeholder="KTPHAM"></div>
                <div class="col-md-2"><label class="form-label">Người nhận</label><input id="receiverName" class="form-control"></div>
                <div class="col-md-2"><label class="form-label">Bộ phận</label><input id="department" class="form-control"></div>
                <div class="col-md-2"><label class="form-label">Mục đích</label><input id="purpose" class="form-control" placeholder="Sản xuất / bù hao..."></div>
                <div class="col-12"><label class="form-label">Ghi chú phiếu</label><input id="issueNote" class="form-control"></div>
            </div>

            <datalist id="productionOrderOptions"></datalist>
            <datalist id="internalCatalogOptions"></datalist>
            <div id="productionOrderStatus" class="order-load-status hint">Gõ Lệnh SX trực tiếp tại dòng hàng, nhấn Enter để lấy thông tin.</div>
            <div class="table-responsive">
                <table class="table table-bordered line-table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:130px">Lệnh SX</th>
                            <th style="width:220px">Mã vật tư</th>
                            <th>Tên vật tư</th>
                            <th style="width:80px">ĐVT</th>
                            <th style="width:105px">SL theo lệnh</th>
                            <th style="width:105px">Tồn khả dụng</th>
                            <th style="width:120px">SL thực xuất *</th>
                            <th style="width:120px">Vị trí</th>
                            <th style="width:140px">Mã nội bộ</th>
                            <th style="width:90px">Size</th>
                            <th style="width:120px">Màu</th>
                            <th style="width:160px">Ghi chú</th>
                            <th style="width:50px"></th>
                        </tr>
                    </thead>
                    <tbody id="lineRows"></tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <div class="row g-2 align-items-end mb-3">
                <div class="col-md-2"><label class="form-label">Từ ngày</label><input id="fromDate" type="date" class="form-control"></div>
                <div class="col-md-2"><label class="form-label">Đến ngày</label><input id="toDate" type="date" class="form-control"></div>
                <div class="col-md-5"><label class="form-label">Tìm phiếu / mã vật tư / người nhận</label><input id="keyword" class="form-control"></div>
                <div class="col-md-3"><button id="clearFilterBtn" type="button" class="btn btn-outline-secondary w-100">Xóa lọc</button></div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr><th>Số phiếu</th><th>Ngày</th><th>Kho</th><th>Người nhận</th><th>Bộ phận</th><th>Lệnh</th><th>Mục đích</th><th class="text-end">Dòng</th><th class="text-end">Tổng SL</th><th></th></tr>
                    </thead>
                    <tbody id="issueRows"></tbody>
                </table>
            </div>
        </section>
    </main>

    <dialog id="pasteImportDialog" class="paste-dialog">
        <div class="paste-dialog__header">
            <div>
                <h2 class="section-title">Nhập phiếu khách hàng từ Excel</h2>
                <div class="hint">Chọn khách, copy các dòng trong Excel rồi dán vào ô bên dưới.</div>
            </div>
            <button id="closePasteImportBtn" type="button" class="btn btn-outline-secondary btn-sm" aria-label="Đóng"><i data-lucide="x"></i></button>
        </div>
        <div class="paste-dialog__body">
            <div class="row g-2 mb-3">
                <div class="col-md-3">
                    <label class="form-label" for="pasteCustomer">Khách hàng</label>
                    <select id="pasteCustomer" class="form-select">
                        <option value="UNIPAX">UNIPAX</option>
                        <option value="ELITE">ELITE</option>
                        <option value="CUSTOM">Khách khác / có tiêu đề cột</option>
                    </select>
                </div>
                <div class="col-md-9">
                    <label class="form-label">Thứ tự cột</label>
                    <div id="pasteColumnGuide" class="column-guide"></div>
                </div>
            </div>
            <label class="form-label" for="pasteExcelData">Dữ liệu copy từ Excel</label>
            <textarea id="pasteExcelData" class="form-control paste-box" spellcheck="false" placeholder="Dán các dòng Excel tại đây..."></textarea>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2 mb-3">
                <div class="hint">Có thể dán cả hàng tiêu đề. Dòng trống sẽ tự bỏ qua.</div>
                <button id="analyzePasteBtn" type="button" class="btn btn-primary"><i data-lucide="scan-search"></i> Kiểm tra dữ liệu</button>
            </div>
            <div id="pasteResultArea" class="d-none">
                <div id="pasteSummary" class="paste-summary mb-2"></div>
                <div class="table-responsive border rounded">
                    <table class="table table-bordered paste-preview mb-0">
                        <thead>
                            <tr>
                                <th>Dòng</th><th>Lệnh/PS#</th><th>Mã nội bộ</th><th>Mã kế toán</th>
                                <th>Size</th><th>Màu</th><th class="text-end">SL</th>
                                <th>Vị trí</th><th>Kiểm tra</th>
                            </tr>
                        </thead>
                        <tbody id="pastePreviewRows"></tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="paste-dialog__footer">
            <div id="pasteFooterHint" class="hint">Chưa có dữ liệu kiểm tra.</div>
            <div class="d-flex gap-2">
                <button id="cancelPasteImportBtn" type="button" class="btn btn-outline-secondary">Hủy</button>
                <button id="applyPastedLinesBtn" type="button" class="btn btn-primary" disabled><i data-lucide="list-plus"></i> <span id="applyPastedLinesLabel">Tạo phiếu nhập UNIPAX</span></button>
            </div>
        </div>
    </dialog>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const lineRows = document.getElementById('lineRows');
        const issueRows = document.getElementById('issueRows');
        let searchTimers = {};
        let productionOrderSearchTimer = null;
        let internalCatalogSearchTimer = null;
        let internalCatalogItems = [];
        let analyzedPastedLines = [];

        const pastePresets = {
            UNIPAX: {
                guide: 'Mã vật tư (mã nội bộ) | PS# | Size | Màu | Logo color (màu in) | ĐVT | Q’TY đơn hàng | Đạt | Lỗi | Ghi chú | Vị trí',
                columns: ['internal_item_code','ps_number','size','color','logo_color','dvt','order_reference','quantity','error_quantity','note','location_code']
            },
            ELITE: {
                guide: 'Ngày xuất | ITEM# (mã nội bộ) | PS# / Lệnh | Size | Màu | Logo color | ĐVT | SL đơn hàng | SL xuất | Ghi chú | Vị trí',
                columns: ['issue_date','internal_item_code','production_order','size','color','logo_color','dvt','ordered_quantity','quantity','note','location_code']
            },
            CUSTOM: {
                guide: 'Dán kèm hàng tiêu đề. Nhận các cột: Mã kế toán, ITEM/Mã nội bộ, PS#/Lệnh SX, Size, Màu, ĐVT, Số lượng/Đạt, Vị trí, Ghi chú.',
                columns: []
            }
        };

        const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
        const value = id => document.getElementById(id).value.trim();
        const num = value => Number(value || 0).toLocaleString('vi-VN', { maximumFractionDigits: 3 });

        function jsonOrError(response, fallback) {
            if (response.ok) return response.json();
            return response.json().then(result => { throw new Error(result.message || fallback); });
        }

        function addLine(data = {}) {
            const rowId = `line-${Date.now()}-${Math.random().toString(16).slice(2)}`;
            const tr = document.createElement('tr');
            tr.dataset.rowId = rowId;
            tr.dataset.productionOrderId = data.production_order_id || '';
            tr.dataset.purchaseOrder = data.purchase_order || '';
            tr.dataset.customer = data.customer || '';
            tr.innerHTML = `
                <td><input class="form-control line-production-order" list="productionOrderOptions" autocomplete="off" value="${esc(data.production_order || '')}" placeholder="Gõ lệnh SX"></td>
                <td class="product-search">
                    <input class="form-control ma-hh" autocomplete="off" value="${esc(data.ma_hh || '')}" placeholder="Gõ mã/tên">
                    <div class="product-results d-none"></div>
                </td>
                <td><input class="form-control ten-hh" value="${esc(data.ten_hh || '')}"></td>
                <td><input class="form-control dvt" value="${esc(data.dvt || '')}"></td>
                <td><input class="form-control ordered-quantity text-end reference-value" value="${esc(data.ordered_quantity || '')}" readonly></td>
                <td>
                    <input class="form-control available-quantity text-end reference-value" value="${esc(data.available_quantity || '')}" readonly>
                    ${data.production_order && !Number(data.available_quantity || 0) ? '<div class="stock-warning">Chưa khớp tồn</div>' : ''}
                </td>
                <td><input class="form-control quantity text-end" type="number" step="0.001" min="0" value="${esc(data.quantity || '')}"></td>
                <td><input class="form-control location-code" value="${esc(data.location_code || '')}" placeholder="A01"></td>
                <td><input class="form-control internal-code" list="internalCatalogOptions" autocomplete="off" value="${esc(data.internal_item_code || '')}" placeholder="Mã DANH MỤC"></td>
                <td><input class="form-control size" value="${esc(data.size || '')}"></td>
                <td><input class="form-control color" value="${esc(data.color || '')}"></td>
                <td><input class="form-control line-note" value="${esc(data.note || '')}"></td>
                <td><button type="button" class="btn btn-sm btn-outline-danger remove-line">X</button></td>
            `;
            lineRows.appendChild(tr);
        }

        function collectLines() {
            return Array.from(lineRows.querySelectorAll('tr')).map(row => ({
                ma_hh: row.querySelector('.ma-hh').value.trim(),
                ten_hh: row.querySelector('.ten-hh').value.trim(),
                dvt: row.querySelector('.dvt').value.trim(),
                production_order_id: row.dataset.productionOrderId || null,
                production_order: row.querySelector('.line-production-order').value.trim(),
                purchase_order: row.dataset.purchaseOrder || '',
                customer: row.dataset.customer || '',
                ordered_quantity: row.querySelector('.ordered-quantity').value || null,
                quantity: row.querySelector('.quantity').value,
                location_code: row.querySelector('.location-code').value.trim(),
                internal_item_code: row.querySelector('.internal-code').value.trim(),
                size: row.querySelector('.size').value.trim(),
                color: row.querySelector('.color').value.trim(),
                note: row.querySelector('.line-note').value.trim(),
            })).filter(line => line.ma_hh || line.quantity);
        }

        function searchProductionOrders(input) {
            const keyword = input.value.trim();
            clearTimeout(productionOrderSearchTimer);
            if (keyword.length < 2) return;

            productionOrderSearchTimer = setTimeout(() => {
                fetch(`/api/lenh-san-xuat-sheet?keyword=${encodeURIComponent(keyword)}&limit=30`)
                    .then(response => jsonOrError(response, 'Không tải được lệnh sản xuất'))
                    .then(result => {
                        const uniqueOrders = Array.from(new Map(
                            (result.data || []).map(order => [order.production_order, order])
                        ).values());
                        document.getElementById('productionOrderOptions').innerHTML = uniqueOrders.map(order => {
                            const label = [
                                order.customer,
                                order.purchase_order,
                                order.item_code,
                                order.size ? `Size ${order.size}` : '',
                                order.color ? `Màu ${order.color}` : ''
                            ].filter(Boolean).join(' · ');
                            return `<option value="${esc(order.production_order)}" label="${esc(label)}"></option>`;
                        }).join('');
                    })
                    .catch(() => {});
            }, 220);
        }

        function searchInternalCatalog(input) {
            const keyword = input.value.trim();
            clearTimeout(internalCatalogSearchTimer);
            if (keyword.length < 1) return;

            internalCatalogSearchTimer = setTimeout(() => {
                fetch(`/api/ma-noi-bo-danh-muc?keyword=${encodeURIComponent(keyword)}&limit=30`)
                    .then(response => jsonOrError(response, 'Không tải được DANH MỤC'))
                    .then(result => {
                        internalCatalogItems = result.data || [];
                        document.getElementById('internalCatalogOptions').innerHTML = internalCatalogItems.map(item => {
                            const label = [item.name, item.unit, item.shelf ? `Kệ ${item.shelf}` : ''].filter(Boolean).join(' · ');
                            return `<option value="${esc(item.code)}" label="${esc(label)}"></option>`;
                        }).join('');
                    })
                    .catch(() => {});
            }, 180);
        }

        function applyInternalCatalog(input) {
            const code = input.value.trim().toUpperCase();
            const item = internalCatalogItems.find(row => String(row.code || '').trim().toUpperCase() === code);
            if (!item) return;

            const row = input.closest('tr');
            if (!row.querySelector('.ten-hh').value.trim()) row.querySelector('.ten-hh').value = item.name || '';
            if (!row.querySelector('.dvt').value.trim()) row.querySelector('.dvt').value = item.unit || '';
            if (!row.querySelector('.location-code').value.trim() && item.shelf) {
                row.querySelector('.location-code').value = item.shelf;
            }
        }

        function fillIssueLine(row, data) {
            row.dataset.productionOrderId = data.production_order_id || '';
            row.dataset.purchaseOrder = data.purchase_order || '';
            row.dataset.customer = data.customer || '';
            row.querySelector('.line-production-order').value = data.production_order || '';
            row.querySelector('.ma-hh').value = data.ma_hh || '';
            row.querySelector('.ten-hh').value = data.ten_hh || '';
            row.querySelector('.dvt').value = data.dvt || '';
            row.querySelector('.ordered-quantity').value = data.ordered_quantity || '';
            row.querySelector('.available-quantity').value = data.available_quantity || '';
            row.querySelector('.quantity').value = '';
            row.querySelector('.location-code').value = data.location_code || '';
            row.querySelector('.internal-code').value = data.internal_item_code || '';
            row.querySelector('.size').value = data.size || '';
            row.querySelector('.color').value = data.color || '';

            const availableCell = row.querySelector('.available-quantity').parentElement;
            availableCell.querySelector('.stock-warning')?.remove();
            if (!Number(data.available_quantity || 0)) {
                availableCell.insertAdjacentHTML('beforeend', '<div class="stock-warning">Chưa khớp tồn</div>');
            }
        }

        function loadProductionOrder(input) {
            const code = input.value.trim();
            if (!code) return alert('Nhập Lệnh sản xuất cần nạp.');

            const status = document.getElementById('productionOrderStatus');
            const currentRow = input.closest('tr');
            status.textContent = `Đang tải ${code}...`;
            input.disabled = true;

            const params = new URLSearchParams({ production_order: code });
            if (value('warehouseCode')) params.set('warehouse_code', value('warehouseCode'));

            fetch(`/api/xuat-vat-tu-noi-bo/lenh-san-xuat?${params.toString()}`)
                .then(response => jsonOrError(response, 'Không tải được chi tiết lệnh sản xuất'))
                .then(result => {
                    const rows = result.data || [];
                    if (!rows.length) throw new Error(`Không tìm thấy lệnh ${code}.`);

                    const existingKeys = new Set(Array.from(lineRows.querySelectorAll('tr'))
                        .filter(row => row !== currentRow)
                        .map(row => [
                        row.querySelector('.line-production-order').value.trim().toUpperCase(),
                        row.querySelector('.internal-code').value.trim().toUpperCase(),
                        row.querySelector('.size').value.trim().toUpperCase(),
                        row.querySelector('.color').value.trim().toUpperCase()
                    ].join('|')));
                    const newRows = [];

                    rows.forEach(data => {
                        const key = [
                            String(data.production_order || '').trim().toUpperCase(),
                            String(data.internal_item_code || '').trim().toUpperCase(),
                            String(data.size || '').trim().toUpperCase(),
                            String(data.color || '').trim().toUpperCase()
                        ].join('|');
                        if (existingKeys.has(key)) return;
                        newRows.push(data);
                        existingKeys.add(key);
                    });

                    if (!newRows.length) {
                        throw new Error(`Các dòng của lệnh ${code} đã có trong phiếu.`);
                    }

                    fillIssueLine(currentRow, newRows.shift());
                    newRows.forEach(data => addLine(data));
                    status.textContent = `${code}: đã nạp ${newRows.length + 1} dòng size/màu từ dòng đang chọn. SL thực xuất để trống.`;
                })
                .catch(error => {
                    status.textContent = error.message;
                    alert(error.message);
                })
                .finally(() => input.disabled = false);
        }

        function applyIssueType(type) {
            const isProduction = type === 'production';
            document.getElementById('issueTypeMetric').textContent = isProduction ? 'BTP sản xuất' : 'Vật tư';
            document.getElementById('pageTitle').textContent = isProduction ? 'Xuất bán thành phẩm đi sản xuất' : 'Xuất vật tư nội bộ';
            document.getElementById('pageHint').textContent = isProduction
                ? 'Xuất BTP khỏi kho nội bộ để giao sản xuất. Khi hoàn thành, nhập lại bằng Phiếu nhập thành phẩm.'
                : 'Xuất vật tư khỏi tồn nội bộ theo mã, vị trí, size và màu.';
            document.getElementById('saveBtn').textContent = isProduction ? 'Xuất BTP + in phiếu' : 'Xuất vật tư + in phiếu';

            if (isProduction) {
                if (!value('department')) document.getElementById('department').value = 'Sản xuất';
                if (!value('purpose') || value('purpose') === 'Xuất vật tư') document.getElementById('purpose').value = 'Xuất BTP đi sản xuất';
            } else if (value('purpose') === 'Xuất BTP đi sản xuất') {
                document.getElementById('purpose').value = 'Xuất vật tư';
            }
        }

        function suggestMaterial(input) {
            const keyword = input.value.trim();
            const cell = input.closest('.product-search');
            const results = cell.querySelector('.product-results');
            clearTimeout(searchTimers[cell.parentElement.dataset.rowId]);

            if (keyword.length < 2) {
                results.classList.add('d-none');
                results.innerHTML = '';
                return;
            }

            searchTimers[cell.parentElement.dataset.rowId] = setTimeout(() => {
                fetch(`/api/vat-tu-ke-toan/goi-y?keyword=${encodeURIComponent(keyword)}`)
                    .then(response => jsonOrError(response, 'Không tải được danh mục vật tư'))
                    .then(result => {
                        results.innerHTML = (result.data || []).map(item => `
                            <button type="button" class="product-option" data-code="${esc(item.Ma_hh)}" data-name="${esc(item.Ten_hh || '')}" data-dvt="${esc(item.Dvt || '')}">
                                <div class="product-code">${esc(item.Ma_hh)}</div>
                                <div class="product-name">${esc(item.Ten_hh || '')} ${item.Dvt ? '· ' + esc(item.Dvt) : ''}</div>
                            </button>
                        `).join('') || '<div class="p-3 hint">Không có mã phù hợp</div>';
                        results.classList.remove('d-none');
                    })
                    .catch(error => {
                        results.innerHTML = `<div class="p-3 text-danger small">${esc(error.message)}</div>`;
                        results.classList.remove('d-none');
                    });
            }, 250);
        }

        function normalizeHeader(text) {
            return String(text || '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, ' ')
                .trim();
        }

        function headerField(text) {
            const header = normalizeHeader(text);
            const aliases = [
                ['issue_date', ['ngay xuat', 'export date', 'date']],
                ['ma_hh', ['ma ke toan', 'sku ke toan']],
                ['internal_item_code', ['item', 'item code', 'ma hang', 'ma noi bo', 'ma vat tu', 'sku']],
                ['production_order', ['ps', 'ps sub', 'lenh sx', 'lenh san xuat', 'don hang']],
                ['size', ['size', 'kich co']],
                ['color', ['mau', 'fabric color', 'mau vai']],
                ['dvt', ['dvt', 'unit']],
                ['ordered_quantity', ['qty don hang', 'quantity order', 'so luong dat hang']],
                ['quantity', ['quantity dat', 'sl dat', 'so luong', 'quantity', 'sl xuat', 'dat']],
                ['location_code', ['vi tri', 'location']],
                ['note', ['ghi chu', 'note']],
            ];

            const match = aliases.find(([, names]) => names.some(name => header === name || header.includes(name)));
            return match ? match[0] : null;
        }

        function parsePasteNumber(value) {
            let text = String(value ?? '').trim().replace(/\s/g, '');
            if (!text) return 0;
            if (/^-?\d{1,3}([.,]\d{3})+$/.test(text)) text = text.replace(/[.,]/g, '');
            else if (text.includes(',') && !text.includes('.')) text = text.replace(',', '.');
            else if (text.includes(',') && text.includes('.')) text = text.replace(/\./g, '').replace(',', '.');
            const number = Number(text.replace(/[^\d.-]/g, ''));
            return Number.isFinite(number) ? number : 0;
        }

        function parsePasteDate(value) {
            const text = String(value || '').trim();
            const match = text.match(/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})$/);
            if (match) return `${match[3]}-${match[2].padStart(2, '0')}-${match[1].padStart(2, '0')}`;
            return /^\d{4}-\d{2}-\d{2}$/.test(text) ? text : '';
        }

        function parseExcelPaste() {
            const customer = value('pasteCustomer');
            const preset = pastePresets[customer];
            const rows = document.getElementById('pasteExcelData').value
                .split(/\r?\n/)
                .map(row => row.split('\t').map(cell => cell.trim()))
                .filter(row => row.some(Boolean));

            if (!rows.length) throw new Error('Chưa có dữ liệu Excel để kiểm tra.');

            const detectedHeaders = rows[0].map(headerField);
            const hasHeader = detectedHeaders.filter(Boolean).length >= 2;
            let columns = preset.columns;
            if (hasHeader) {
                columns = detectedHeaders;
                if (customer === 'UNIPAX') {
                    columns = columns.map(field => field === 'production_order' ? 'ps_number' : field);
                }
                rows.shift();
            } else if (customer === 'CUSTOM') {
                throw new Error('Khách khác cần dán kèm hàng tiêu đề.');
            }

            return rows.map(cells => {
                const line = { customer };
                columns.forEach((field, index) => {
                    if (field) line[field] = cells[index] || '';
                });
                line.issue_date = parsePasteDate(line.issue_date);
                line.quantity = parsePasteNumber(line.quantity);
                line.ordered_quantity = parsePasteNumber(line.ordered_quantity);
                const extraNotes = [];
                if (line.order_reference) {
                    const orderedNumber = parsePasteNumber(line.order_reference);
                    if (orderedNumber > 0) line.ordered_quantity = orderedNumber;
                    else extraNotes.push(`Q'TY đơn hàng: ${line.order_reference}`);
                }
                if (line.logo_color) extraNotes.push(`Màu in: ${line.logo_color}`);
                if (parsePasteNumber(line.error_quantity) > 0) extraNotes.push(`Lỗi: ${line.error_quantity}`);
                if (line.ps_number) extraNotes.push(`PS#: ${line.ps_number}`);
                line.note = [...extraNotes, line.note].filter(Boolean).join(' - ');
                line.ma_hh = String(line.ma_hh || '').toUpperCase();
                line.location_code = String(line.location_code || '').toUpperCase();
                line.internal_item_code = String(line.internal_item_code || '').trim();
                return line;
            }).filter(line => line.ma_hh || line.internal_item_code || line.quantity);
        }

        function renderPastePreview(result) {
            analyzedPastedLines = result.data || [];
            const summary = result.summary || {};
            document.getElementById('pasteResultArea').classList.remove('d-none');
            document.getElementById('pasteSummary').innerHTML = `
                <span>${num(summary.line_count)} dòng</span>
                <span>${num(summary.total_quantity)} tổng SL</span>
                <span class="text-success">${num(summary.valid_count)} hợp lệ</span>
                <span class="text-warning">${num(summary.warning_count)} cần kiểm tra</span>
            `;
            document.getElementById('pastePreviewRows').innerHTML = analyzedPastedLines.map(line => `
                <tr class="${line.is_valid ? '' : 'paste-row-warning'}">
                    <td>${num(line.source_row)}</td>
                    <td>${esc(line.purchase_order || line.production_order)}</td>
                    <td>${esc(line.internal_item_code)}</td>
                    <td>${esc(line.ma_hh || 'Chưa khớp')}</td>
                    <td>${esc(line.size)}</td>
                    <td>${esc(line.color)}</td>
                    <td class="text-end">${num(line.quantity)}</td>
                    <td>${esc(line.location_code || 'Chưa chọn')}</td>
                    <td>${line.warnings?.length
                        ? `<div class="paste-warning">${line.warnings.map(esc).join('<br>')}</div>`
                        : '<span class="text-success">Sẵn sàng</span>'}</td>
                </tr>
            `).join('');
            document.getElementById('pasteFooterHint').textContent = summary.warning_count
                ? 'Các dòng cảnh báo vẫn được đưa vào phiếu để bạn sửa trực tiếp.'
                : 'Dữ liệu đã sẵn sàng đưa vào phiếu.';
            document.getElementById('applyPastedLinesBtn').disabled = !analyzedPastedLines.length;
            if (value('pasteCustomer') === 'UNIPAX') {
                const receiptCount = Math.ceil(analyzedPastedLines.length / 20);
                document.getElementById('applyPastedLinesLabel').textContent =
                    `Tạo ${receiptCount} phiếu nhập UNIPAX`;
            }
        }

        function analyzePastedData() {
            let lines;
            try {
                lines = parseExcelPaste();
            } catch (error) {
                return alert(error.message);
            }
            if (!lines.length) return alert('Không đọc được dòng dữ liệu nào.');

            const button = document.getElementById('analyzePasteBtn');
            button.disabled = true;
            fetch('/api/xuat-vat-tu-noi-bo/phan-tich-paste', {
                method: 'POST',
                headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify({ customer: value('pasteCustomer'), lines })
            }).then(response => jsonOrError(response, 'Không kiểm tra được dữ liệu paste'))
              .then(renderPastePreview)
              .catch(error => alert(error.message))
              .finally(() => button.disabled = false);
        }

        function applyPastedLines() {
            if (!analyzedPastedLines.length) return;
            if (value('pasteCustomer') === 'UNIPAX') {
                saveUnipaxReceipt();
                return;
            }
            lineRows.innerHTML = '';
            analyzedPastedLines.forEach(line => addLine(line));
            const firstDate = analyzedPastedLines.find(line => line.issue_date)?.issue_date;
            if (firstDate) document.getElementById('issueDate').value = firstDate;
            if (!value('issueNote')) {
                document.getElementById('issueNote').value = `Paste phiếu ${value('pasteCustomer')}`;
            }
            document.getElementById('pasteImportDialog').close();
            lineRows.querySelector('input')?.focus();
        }

        function saveUnipaxReceipt() {
            const warehouseCode = value('warehouseCode');
            if (!warehouseCode) return alert('Nhập Kho xuất/nhập ở phần thông tin phiếu trước.');
            if (!value('issueDate')) return alert('Chọn ngày nhập kho.');

            const lines = analyzedPastedLines.map(line => ({
                ma_sp: line.ma_hh || '',
                internal_item_code: line.internal_item_code || '',
                size: line.size || '',
                color: line.color || '',
                side: '',
                dvt: line.dvt || '',
                quantity: line.quantity || 0,
                location_code: line.location_code || '',
                purchase_order: line.purchase_order || '',
                customer: 'UNIPAX',
                note: line.note || '',
            }));
            if (lines.some(line => !line.internal_item_code || !Number(line.quantity))) {
                return alert('Mỗi dòng UNIPAX cần Mã vật tư nội bộ và số lượng Đạt.');
            }

            const batches = [];
            for (let index = 0; index < lines.length; index += 20) {
                batches.push(lines.slice(index, index + 20));
            }

            const button = document.getElementById('applyPastedLinesBtn');
            button.disabled = true;
            const printWindows = batches.map(() => window.open('', '_blank'));
            const createdReceipts = [];

            batches.reduce((promise, batchLines, index) => promise.then(() => {
                button.querySelector('span').textContent = `Đang tạo phiếu ${index + 1}/${batches.length}`;
                return fetch('/api/kiem-ton-kho/phieu-nhap-tp', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                    body: JSON.stringify({
                        location_code: '',
                        ma_ko: warehouseCode,
                        checked_at: value('issueDate'),
                        note: `Nhập kho từ phiếu xuất UNIPAX - phần ${index + 1}/${batches.length}`,
                        lines: batchLines
                    })
                }).then(response => jsonOrError(response, `Không tạo được phiếu nhập UNIPAX phần ${index + 1}`))
                  .then(result => {
                      createdReceipts.push(result);
                      if (result.receipt_print_url && printWindows[index]) {
                          printWindows[index].location.href = result.receipt_print_url;
                      }
                  });
            }), Promise.resolve())
              .then(() => {
                  document.getElementById('pasteImportDialog').close();
                  document.getElementById('pasteExcelData').value = '';
                  analyzedPastedLines = [];
                  alert(`Đã tạo ${createdReceipts.length} phiếu nhập kho UNIPAX và cộng tồn nội bộ.`);
              })
              .catch(error => {
                  printWindows.slice(createdReceipts.length).forEach(printWindow => printWindow?.close());
                  const completed = createdReceipts.length
                      ? ` Đã tạo thành công ${createdReceipts.length} phiếu trước khi gặp lỗi.`
                      : '';
                  alert(error.message + completed);
              })
              .finally(() => {
                  button.disabled = false;
                  button.querySelector('span').textContent =
                      `Tạo ${Math.max(1, batches.length)} phiếu nhập UNIPAX`;
              });
        }

        function saveIssue() {
            const lines = collectLines();
            if (!lines.length) return alert('Nhập ít nhất một dòng vật tư.');
            if (lines.some(line => !line.ma_hh || !Number(line.quantity))) return alert('Mỗi dòng cần mã vật tư và số lượng.');

            fetch('/api/xuat-vat-tu-noi-bo', {
                method: 'POST',
                headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify({
                    issue_type: value('issueType'),
                    issue_date: value('issueDate'),
                    warehouse_code: value('warehouseCode'),
                    receiver_name: value('receiverName'),
                    department: value('department'),
                    production_order: Array.from(new Set(lines.map(line => line.production_order).filter(Boolean))).join(', '),
                    purpose: value('purpose'),
                    note: value('issueNote'),
                    lines
                })
            }).then(response => jsonOrError(response, 'Không tạo được phiếu xuất vật tư'))
              .then(result => {
                  window.open(result.print_url, '_blank');
                  lineRows.innerHTML = '';
                  addLine();
                  loadIssues();
              })
              .catch(error => alert(error.message));
        }

        function loadIssues() {
            const params = new URLSearchParams();
            if (value('fromDate')) params.set('from_date', value('fromDate'));
            if (value('toDate')) params.set('to_date', value('toDate'));
            if (value('keyword')) params.set('keyword', value('keyword'));

            fetch(`/api/xuat-vat-tu-noi-bo?${params.toString()}`)
                .then(response => jsonOrError(response, 'Không tải được danh sách phiếu'))
                .then(result => {
                    document.getElementById('issueCount').textContent = num(result.summary?.total_issues || 0);
                    document.getElementById('lineCount').textContent = num(result.summary?.total_lines || 0);
                    document.getElementById('totalQuantity').textContent = num(result.summary?.total_quantity || 0);
                    issueRows.innerHTML = (result.data || []).map(issue => `
                        <tr>
                            <td>${esc(issue.issue_code)}</td>
                            <td>${esc(issue.issue_date)}</td>
                            <td>${esc(issue.warehouse_code)}</td>
                            <td>${esc(issue.receiver_name)}</td>
                            <td>${esc(issue.department)}</td>
                            <td>${esc(issue.production_order)}</td>
                            <td>${esc(issue.purpose)}</td>
                            <td class="text-end">${num(issue.lines_count)}</td>
                            <td class="text-end">${num(issue.lines_sum_quantity)}</td>
                            <td class="text-nowrap text-end">
                                <a class="btn btn-sm btn-outline-primary" target="_blank" href="/client/xuat-vat-tu-noi-bo/${issue.id}/in">In</a>
                                <button class="btn btn-sm btn-outline-danger delete-issue" data-id="${issue.id}">Xóa</button>
                            </td>
                        </tr>
                    `).join('') || '<tr><td colspan="10" class="text-center hint">Chưa có phiếu</td></tr>';
                })
                .catch(error => alert(error.message));
        }

        lineRows.addEventListener('input', event => {
            if (event.target.classList.contains('ma-hh')) suggestMaterial(event.target);
            if (event.target.classList.contains('line-production-order')) searchProductionOrders(event.target);
            if (event.target.classList.contains('internal-code')) searchInternalCatalog(event.target);
        });

        lineRows.addEventListener('change', event => {
            if (event.target.classList.contains('line-production-order')) loadProductionOrder(event.target);
            if (event.target.classList.contains('internal-code')) applyInternalCatalog(event.target);
        });

        lineRows.addEventListener('keydown', event => {
            if (event.target.classList.contains('line-production-order') && event.key === 'Enter') {
                event.preventDefault();
                loadProductionOrder(event.target);
            }
        });

        lineRows.addEventListener('click', event => {
            const option = event.target.closest('.product-option');
            if (option) {
                const row = option.closest('tr');
                row.querySelector('.ma-hh').value = option.dataset.code || '';
                row.querySelector('.ten-hh').value = option.dataset.name || '';
                row.querySelector('.dvt').value = option.dataset.dvt || '';
                option.closest('.product-results').classList.add('d-none');
                return;
            }

            const remove = event.target.closest('.remove-line');
            if (remove) {
                if (lineRows.querySelectorAll('tr').length === 1) return;
                remove.closest('tr').remove();
            }
        });

        issueRows.addEventListener('click', event => {
            const button = event.target.closest('.delete-issue');
            if (!button || !confirm('Xóa phiếu xuất vật tư nội bộ này?')) return;

            fetch(`/api/xuat-vat-tu-noi-bo/${button.dataset.id}`, {
                method: 'DELETE',
                headers: {'Accept':'application/json','X-CSRF-TOKEN':csrfToken}
            }).then(response => jsonOrError(response, 'Không xóa được phiếu'))
              .then(loadIssues)
              .catch(error => alert(error.message));
        });

        document.getElementById('addLineBtn').addEventListener('click', () => addLine());
        document.getElementById('openPasteImportBtn').addEventListener('click', () => {
            document.getElementById('pasteColumnGuide').textContent = pastePresets[value('pasteCustomer')].guide;
            document.getElementById('pasteImportDialog').showModal();
            setTimeout(() => document.getElementById('pasteExcelData').focus(), 0);
        });
        document.getElementById('closePasteImportBtn').addEventListener('click', () => document.getElementById('pasteImportDialog').close());
        document.getElementById('cancelPasteImportBtn').addEventListener('click', () => document.getElementById('pasteImportDialog').close());
        document.getElementById('pasteCustomer').addEventListener('change', event => {
            document.getElementById('pasteColumnGuide').textContent = pastePresets[event.target.value].guide;
            document.getElementById('applyPastedLinesLabel').textContent = event.target.value === 'UNIPAX'
                ? 'Tạo phiếu nhập UNIPAX'
                : 'Đưa vào phiếu xuất';
            analyzedPastedLines = [];
            document.getElementById('pasteResultArea').classList.add('d-none');
            document.getElementById('applyPastedLinesBtn').disabled = true;
            document.getElementById('pasteFooterHint').textContent = 'Chưa có dữ liệu kiểm tra.';
        });
        document.getElementById('analyzePasteBtn').addEventListener('click', analyzePastedData);
        document.getElementById('applyPastedLinesBtn').addEventListener('click', applyPastedLines);
        document.getElementById('saveBtn').addEventListener('click', saveIssue);
        document.getElementById('issueType').addEventListener('change', event => applyIssueType(event.target.value));
        document.getElementById('reloadBtn').addEventListener('click', loadIssues);
        document.getElementById('clearFilterBtn').addEventListener('click', () => {
            ['fromDate','toDate','keyword'].forEach(id => document.getElementById(id).value = '');
            document.getElementById('topIssueKeyword').value = '';
            loadIssues();
        });
        ['fromDate','toDate','keyword'].forEach(id => document.getElementById(id).addEventListener('input', loadIssues));
        let topIssueSearchTimer = null;
        document.getElementById('topIssueKeyword').addEventListener('input', event => {
            document.getElementById('keyword').value = event.target.value;
            clearTimeout(topIssueSearchTimer);
            topIssueSearchTimer = setTimeout(loadIssues, 250);
        });
        document.getElementById('keyword').addEventListener('input', event => {
            document.getElementById('topIssueKeyword').value = event.target.value;
        });

        const requestedType = new URLSearchParams(window.location.search).get('type');
        document.getElementById('pasteColumnGuide').textContent = pastePresets.UNIPAX.guide;
        document.getElementById('issueType').value = requestedType === 'material' ? 'material' : 'production';
        applyIssueType(document.getElementById('issueType').value);
        addLine();
        loadIssues();
    </script>
</body>
</html>
