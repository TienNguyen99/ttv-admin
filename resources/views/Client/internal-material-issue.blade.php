<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Xuất kho nội bộ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/warehouse-wms.css') }}?v={{ filemtime(public_path('css/warehouse-wms.css')) }}" rel="stylesheet">
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
        .paste-column-map {
            display: none;
            margin-top: 12px;
            border: 1px solid #dbe2ea;
            border-radius: 6px;
            overflow: auto;
            background: #fff;
        }
        .paste-column-map.is-visible { display: block; }
        .paste-map-table {
            min-width: 980px;
            margin: 0;
            table-layout: fixed;
        }
        .paste-map-table th,
        .paste-map-table td {
            min-width: 150px;
            max-width: 220px;
            border-color: #e5ebf2 !important;
            font-size: 12px;
            vertical-align: top;
        }
        .paste-map-table th {
            background: #f8fafc;
            position: sticky;
            top: 0;
            z-index: 1;
            cursor: grab;
        }
        .paste-map-table th:active { cursor: grabbing; }
        .paste-map-cell {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .paste-map-tools {
            display: flex;
            gap: 4px;
        }
        .paste-map-tools button {
            width: 28px;
            height: 26px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background: #fff;
            color: #0f172a;
        }
        .paste-map-sample {
            min-height: 24px;
            color: #334155;
            word-break: break-word;
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
            <input id="topIssueKeyword" aria-label="Tìm phiếu xuất" placeholder="Tìm phiếu xuất, mã hàng hoặc người nhận...">
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
                <button id="cancelEditBtn" type="button" class="wms-btn d-none"><i data-lucide="x"></i>Hủy sửa</button>
                <button id="reloadBtn" type="button" class="wms-btn"><i data-lucide="refresh-cw"></i>Tải lại</button>
                <button id="createBtpAndIssueBtn" type="button" class="wms-btn"><i data-lucide="git-branch-plus"></i>Tạo lệnh BTP + xuất</button>
                <button id="saveBtn" type="button" class="wms-btn wms-btn--primary"><i data-lucide="printer"></i>Xuất và in phiếu</button>
            </div>
        </div>

        <section class="wms-kpis">
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="clipboard-list"></i></div><div><div class="wms-kpi__label">Phiếu trong danh sách</div><div id="issueCount" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Theo bộ lọc</div></div></article>
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="list-ordered"></i></div><div><div class="wms-kpi__label">Dòng hàng</div><div id="lineCount" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Chi tiết các phiếu</div></div></article>
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="package-minus"></i></div><div><div class="wms-kpi__label">Tổng số lượng</div><div id="totalQuantity" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Đã xuất nội bộ</div></div></article>
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="factory"></i></div><div><div class="wms-kpi__label">Luồng xuất</div><div id="issueTypeMetric" class="wms-kpi__value" style="font-size:18px">BTP sản xuất</div><div class="wms-kpi__meta">Chọn tại thông tin phiếu</div></div></article>
        </section>

        <section class="panel mb-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h2 class="section-title">Thông tin phiếu</h2>
                    <div class="hint">Tạo phiếu sẽ trừ tồn nội bộ theo mã, vị trí, mã nội bộ, size, màu và mặt nếu có nhập.</div>
                </div>
                <div class="d-flex gap-2">
                    <button id="openPasteImportBtn" type="button" class="btn btn-outline-primary btn-sm"><i data-lucide="clipboard-paste"></i> Paste 10-20 dòng</button>
                    <button id="addTwentyLinesBtn" type="button" class="btn btn-outline-secondary btn-sm"><i data-lucide="rows-3"></i> +20 dòng</button>
                    <button id="addLineBtn" type="button" class="btn btn-outline-primary btn-sm">Thêm dòng</button>
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-md-2"><label class="form-label">Nghiệp vụ</label><select id="issueType" class="form-select"><option value="production">Xuất BTP đi sản xuất</option><option value="customer">Xuất thành phẩm cho khách</option></select></div>
                <div class="col-md-2"><label class="form-label">Ngày xuất</label><input id="issueDate" type="text" class="form-control date-vn" inputmode="numeric" placeholder="dd/mm/yyyy" value="{{ now()->format('d/m/Y') }}"></div>
                <div class="col-md-2"><label class="form-label">Khách hàng</label><input id="customerName" class="form-control" placeholder="UNIPAX / ELITE"></div>
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
                            <th style="width:150px">Mã nội bộ *</th>
                            <th>Tên hàng</th>
                            <th style="width:105px">Size</th>
                            <th style="width:130px">Màu</th>
                            <th style="width:120px">SL thực xuất *</th>
                            <th style="width:120px">Vị trí</th>
                            <th style="width:130px">Lệnh BTP/SX</th>
                            <th style="width:180px">Mã đối chiếu</th>
                            <th style="width:80px">ĐVT</th>
                            <th style="width:105px">SL theo lệnh</th>
                            <th style="width:105px">Tồn khả dụng</th>
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
                <div class="col-md-2"><label class="form-label">Từ ngày</label><input id="fromDate" type="text" class="form-control date-vn" inputmode="numeric" placeholder="dd/mm/yyyy"></div>
                <div class="col-md-2"><label class="form-label">Đến ngày</label><input id="toDate" type="text" class="form-control date-vn" inputmode="numeric" placeholder="dd/mm/yyyy"></div>
                <div class="col-md-5"><label class="form-label">Tìm phiếu / mã hàng / người nhận</label><input id="keyword" class="form-control"></div>
                <div class="col-md-3"><button id="clearFilterBtn" type="button" class="btn btn-outline-secondary w-100">Xóa lọc</button></div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr><th>Số phiếu</th><th>Ngày</th><th>Người nhận</th><th>Bộ phận</th><th>Lệnh</th><th>Mục đích</th><th>Trạng thái</th><th class="text-end">Dòng</th><th class="text-end">Tổng SL</th><th></th></tr>
                    </thead>
                    <tbody id="issueRows"></tbody>
                </table>
            </div>
        </section>
    </main>

    <dialog id="pasteImportDialog" class="paste-dialog">
        <div class="paste-dialog__header">
            <div>
                <h2 id="pasteDialogTitle" class="section-title">Nhập dữ liệu từ Excel</h2>
                <div id="pasteDialogHint" class="hint">Copy các dòng trong Excel rồi dán vào ô bên dưới.</div>
            </div>
            <button id="closePasteImportBtn" type="button" class="btn btn-outline-secondary btn-sm" aria-label="Đóng"><i data-lucide="x"></i></button>
        </div>
        <div class="paste-dialog__body">
            <div class="row g-2 mb-3">
                <div class="col-md-3">
                    <label id="pasteCustomerLabel" class="form-label" for="pasteCustomer">Mẫu dữ liệu</label>
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
            <div id="pasteColumnMap" class="paste-column-map">
                <table class="table table-bordered paste-map-table">
                    <thead><tr id="pasteMapHeader"></tr></thead>
                    <tbody id="pasteMapRows"></tbody>
                </table>
            </div>
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
                                <th>Dòng</th><th>Lệnh/PS#</th><th>Mã nội bộ</th>
                                <th>Size</th><th>Màu</th><th>Mặt</th><th class="text-end">SL</th>
                                <th>Kệ kho</th><th>Kiểm tra</th>
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
                <button id="applyPastedLinesBtn" type="button" class="btn btn-primary" disabled><i data-lucide="list-plus"></i> <span id="applyPastedLinesLabel">Đưa vào phiếu xuất BTP</span></button>
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
        let editingIssueId = null;
        let pasteColumnMapping = [];

        const pasteFieldOptions = [
            ['', 'Bỏ qua'],
            ['issue_date', 'Ngày xuất'],
            ['internal_item_code', 'Mã nội bộ'],
            ['ma_hh', 'Mã kế toán'],
            ['production_order', 'PS# / Lệnh'],
            ['ps_number', 'PS# ghi chú'],
            ['size', 'Size'],
            ['color', 'Màu'],
            ['logo_color', 'Logo color'],
            ['dvt', 'ĐVT'],
            ['ordered_quantity', 'SL đơn hàng'],
            ['quantity', 'SL xuất'],
            ['error_quantity', 'SL lỗi'],
            ['note', 'Ghi chú'],
            ['side', 'Vị trí/mặt'],
            ['location_code', 'Kệ kho'],
        ];

        const pastePresets = {
            UNIPAX: {
                guide: 'STT | Ngày xuất | ITEM# (mã nội bộ) | PS# | Size | Màu | Logo color | ĐVT | QTY đơn hàng | Quantity Đạt | Quantity Lỗi | Ghi chú | Vị trí/mặt',
                columns: [null,'issue_date','internal_item_code','ps_number','size','color','logo_color','dvt','ordered_quantity','quantity','error_quantity','note','side']
            },
            ELITE: {
                guide: 'Dán 10-20 dòng: Ngày xuất | ITEM# (mã nội bộ) | PS# nếu có | Size | Màu | Logo color | ĐVT | SL đơn hàng | SL xuất | Ghi chú | Vị trí/mặt',
                columns: ['issue_date','internal_item_code','ps_number','size','color','logo_color','dvt','ordered_quantity','quantity','note','side']
            },
            CUSTOM: {
                guide: 'Dán kèm hàng tiêu đề. Tối thiểu cần Mã nội bộ và Số lượng. Các cột khác như Size, Màu, Vị trí, Ghi chú có thể có hoặc để trống.',
                columns: []
            }
        };

        const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
        function isoToDateVn(value) {
            const raw = String(value || '').slice(0, 10);
            const match = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
            return match ? `${match[3]}/${match[2]}/${match[1]}` : raw;
        }
        function dateVnToIso(value) {
            const raw = String(value || '').trim();
            const vn = raw.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
            if (vn) return `${vn[3]}-${vn[2].padStart(2, '0')}-${vn[1].padStart(2, '0')}`;
            return raw;
        }
        function setDateValue(id, value) {
            const input = document.getElementById(id);
            if (input) input.value = isoToDateVn(value);
        }
        const value = id => {
            const input = document.getElementById(id);
            if (!input) return '';
            const raw = input.value.trim();
            return input.classList.contains('date-vn') ? dateVnToIso(raw) : raw;
        };
        const num = value => Number(value || 0).toLocaleString('vi-VN', { maximumFractionDigits: 3 });

        function isProductionIssuePaste() {
            return value('issueType') === 'production';
        }

        function isUnipaxCustomerPaste() {
            return value('issueType') === 'customer' && value('pasteCustomer') === 'UNIPAX';
        }

        function updatePasteDialogMode() {
            const production = isProductionIssuePaste();
            document.getElementById('pasteDialogTitle').textContent = production
                ? 'Nhập BTP từ Excel'
                : 'Nhập phiếu khách hàng từ Excel';
            document.getElementById('pasteDialogHint').textContent = production
                ? 'Dán 10-20 dòng BTP. Dữ liệu sẽ đưa vào grid xuất BTP để bạn kiểm tra rồi bấm Tạo lệnh BTP + xuất.'
                : 'Chọn khách, copy các dòng trong Excel rồi dán vào ô bên dưới.';
            document.getElementById('pasteCustomerLabel').textContent = production ? 'Mẫu cột' : 'Khách hàng';
            document.getElementById('applyPastedLinesLabel').textContent = isUnipaxCustomerPaste()
                ? 'Tạo PNTP + PXTP UNIPAX'
                : 'Đưa vào phiếu xuất BTP';
        }

        function jsonOrError(response, fallback) {
            if (response.ok) return response.json();
            return response.json().then(result => {
                const errors = result.errors
                    ? Object.values(result.errors).flat().filter(Boolean).join('\n')
                    : '';
                throw new Error(errors || result.message || fallback);
            });
        }

        function addLine(data = {}) {
            const rowId = `line-${Date.now()}-${Math.random().toString(16).slice(2)}`;
            const tr = document.createElement('tr');
            tr.dataset.rowId = rowId;
            tr.dataset.productionOrderId = data.production_order_id || '';
            tr.dataset.purchaseOrder = data.purchase_order || '';
            tr.dataset.customer = data.customer || '';
            tr.dataset.psNumber = data.ps_number || data.purchase_order || '';
            tr.dataset.logoColor = data.logo_color || '';
            tr.dataset.side = data.side || '';
            tr.innerHTML = `
                <td><input class="form-control internal-code" list="internalCatalogOptions" autocomplete="off" value="${esc(data.internal_item_code || '')}" placeholder="Mã nội bộ"></td>
                <td><input class="form-control ten-hh" value="${esc(data.ten_hh || '')}"></td>
                <td><input class="form-control size" value="${esc(data.size || '')}"></td>
                <td><input class="form-control color" value="${esc(data.color || '')}"></td>
                <td><input class="form-control quantity text-end" type="number" step="0.001" min="0" value="${esc(data.quantity || '')}" placeholder="0"></td>
                <td><input class="form-control location-code" value="${esc(data.location_code || '')}" placeholder="A01"></td>
                <td><input class="form-control line-production-order" list="productionOrderOptions" autocomplete="off" value="${esc(data.production_order || '')}" placeholder="Tự sinh BTP"></td>
                <td class="product-search">
                    <input class="form-control ma-hh" autocomplete="off" value="${esc(data.ma_hh || '')}" placeholder="Có thể trống">
                    <div class="product-results d-none"></div>
                </td>
                <td><input class="form-control dvt" value="${esc(data.dvt || '')}"></td>
                <td><input class="form-control ordered-quantity text-end reference-value" value="${esc(data.ordered_quantity || '')}" readonly></td>
                <td>
                    <input class="form-control available-quantity text-end reference-value" value="${esc(data.available_quantity || '')}" readonly>
                    ${data.production_order && !Number(data.available_quantity || 0) ? '<div class="stock-warning">Chưa khớp tồn</div>' : ''}
                </td>
                <td><input class="form-control line-note" value="${esc(data.note || '')}"></td>
                <td><button type="button" class="btn btn-sm btn-outline-danger remove-line">X</button></td>
            `;
            lineRows.appendChild(tr);
        }

        function addBlankLines(count) {
            for (let index = 0; index < count; index++) addLine();
            lineRows.querySelector('tr:last-child .internal-code')?.focus();
        }

        function collectLines() {
            return Array.from(lineRows.querySelectorAll('tr')).map(row => ({
                ma_hh: row.querySelector('.ma-hh').value.trim(),
                ten_hh: row.querySelector('.ten-hh').value.trim(),
                dvt: row.querySelector('.dvt').value.trim(),
                production_order_id: row.dataset.productionOrderId || null,
                production_order: row.querySelector('.line-production-order').value.trim(),
                ps_number: row.dataset.psNumber || row.dataset.purchaseOrder || '',
                purchase_order: row.dataset.purchaseOrder || '',
                customer: row.dataset.customer || value('customerName'),
                ordered_quantity: row.querySelector('.ordered-quantity').value || null,
                quantity: row.querySelector('.quantity').value,
                location_code: row.querySelector('.location-code').value.trim(),
                internal_item_code: row.querySelector('.internal-code').value.trim(),
                size: row.querySelector('.size').value.trim(),
                color: row.querySelector('.color').value.trim(),
                logo_color: row.dataset.logoColor || '',
                side: row.dataset.side || '',
                note: row.querySelector('.line-note').value.trim(),
            })).filter(line => line.ma_hh || line.internal_item_code || line.quantity);
        }

        function setEditingIssue(issue) {
            editingIssueId = issue?.id || null;
            document.getElementById('cancelEditBtn').classList.toggle('d-none', !editingIssueId);
            if (editingIssueId) {
                document.getElementById('saveBtn').innerHTML = '<i data-lucide="save"></i>Cập nhật + in phiếu';
                document.getElementById('pageHint').textContent = `Đang sửa ${issue.issue_code}. Lưu sẽ hoàn tồn cũ và trừ lại theo dòng mới.`;
            } else {
                applyIssueType(value('issueType'));
            }
            if (window.lucide) window.lucide.createIcons();
        }

        function resetIssueForm() {
            editingIssueId = null;
            lineRows.innerHTML = '';
            addLine();
            document.getElementById('receiverName').value = '';
            document.getElementById('customerName').value = '';
            document.getElementById('issueNote').value = '';
            setEditingIssue(null);
        }

        function loadIssueForEdit(issueId) {
            fetch(`/api/xuat-vat-tu-noi-bo/${issueId}`)
                .then(response => jsonOrError(response, 'Không tải được phiếu cần sửa'))
                .then(result => {
                    const issue = result.data;
                    document.getElementById('issueType').value = issue.issue_type || 'production';
                    setDateValue('issueDate', issue.issue_date);
                    document.getElementById('customerName').value = (issue.lines || []).map(line => line.customer).filter(Boolean)[0] || '';
                    document.getElementById('receiverName').value = issue.receiver_name || '';
                    document.getElementById('department').value = issue.department || '';
                    document.getElementById('purpose').value = issue.purpose || '';
                    document.getElementById('issueNote').value = issue.note || '';
                    lineRows.innerHTML = '';
                    (issue.lines || []).forEach(line => addLine(line));
                    if (!(issue.lines || []).length) addLine();
                    setEditingIssue(issue);
                    window.scrollTo({top: 0, behavior: 'smooth'});
                })
                .catch(error => alert(error.message));
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

        function searchProductionOrders(input) {
            const keyword = input.value.trim();
            clearTimeout(productionOrderSearchTimer);
            if (keyword.length < 2) return;

            productionOrderSearchTimer = setTimeout(() => {
                Promise.all([
                    fetch(`/api/lenh-san-xuat-sheet?keyword=${encodeURIComponent(keyword)}&limit=30`)
                        .then(response => jsonOrError(response, 'Khong tai duoc lenh san xuat'))
                        .catch(() => ({data: []})),
                    fetch(`/api/lenh-btp?keyword=${encodeURIComponent(keyword)}&limit=30`)
                        .then(response => jsonOrError(response, 'Khong tai duoc lenh BTP'))
                        .catch(() => ({data: []}))
                ]).then(([productionResult, btpResult]) => {
                    const btpOrders = (btpResult.data || []).map(order => {
                        const line = Array.isArray(order.lines) && order.lines.length ? order.lines[0] : {};
                        return {
                            production_order: order.btp_order_code,
                            customer: order.status === 'draft' ? 'BTP chua xuat' : 'BTP dang SX',
                            purchase_order: order.issue_code || '',
                            item_code: line.internal_item_code || line.ma_hh || '',
                            size: line.size || '',
                            color: line.color || ''
                        };
                    });
                    const uniqueOrders = Array.from(new Map(
                        (productionResult.data || []).concat(btpOrders).map(order => [order.production_order, order])
                    ).values());
                    document.getElementById('productionOrderOptions').innerHTML = uniqueOrders.map(order => {
                        const label = [
                            order.customer,
                            order.purchase_order,
                            order.item_code,
                            order.size ? `Size ${order.size}` : '',
                            order.color ? `Mau ${order.color}` : ''
                        ].filter(Boolean).join(' - ');
                        return `<option value="${esc(order.production_order)}" label="${esc(label)}"></option>`;
                    }).join('');
                }).catch(() => {});
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
                            const label = [
                                item.name,
                                item.size ? `Size ${item.size}` : '',
                                item.color ? `Màu ${item.color}` : '',
                                item.logo_color ? `Màu in ${item.logo_color}` : '',
                                item.side ? `Mặt ${item.side}` : '',
                                item.unit,
                                item.shelf ? `Kệ ${item.shelf}` : '',
                                item.has_code ? '' : 'Chưa có mã'
                            ].filter(Boolean).join(' · ');
                            return `<option value="${esc(item.value || item.code || item.name || '')}" label="${esc(label)}"></option>`;
                        }).join('');
                    })
                    .catch(() => {});
            }, 180);
        }

        function applyInternalCatalog(input) {
            const code = input.value.trim().toUpperCase();
            const item = internalCatalogItems.find(row => {
                return [row.code, row.value, row.name].some(value => String(value || '').trim().toUpperCase() === code);
            });
            if (!item) return;

            const row = input.closest('tr');
            if (!row.querySelector('.ten-hh').value.trim()) row.querySelector('.ten-hh').value = item.name || '';
            if (!row.querySelector('.dvt').value.trim()) row.querySelector('.dvt').value = item.unit || '';
            if (!row.querySelector('.size').value.trim()) row.querySelector('.size').value = item.size || '';
            if (!row.querySelector('.color').value.trim()) row.querySelector('.color').value = item.color || '';
            if (!row.querySelector('.line-note').value.trim()) {
                row.querySelector('.line-note').value = [item.logo_color ? `Màu in: ${item.logo_color}` : '', item.side ? `Mặt: ${item.side}` : ''].filter(Boolean).join(' - ');
            }
            if (item.code) input.value = item.code;
            if (!row.querySelector('.location-code').value.trim() && item.shelf) {
                row.querySelector('.location-code').value = item.shelf;
            }
            if (item.shelf && !row.querySelector('.line-note').value.includes('Kệ danh mục')) {
                const currentNote = row.querySelector('.line-note').value.trim();
                row.querySelector('.line-note').value = [currentNote, `Kệ danh mục: ${item.shelf}`].filter(Boolean).join(' - ');
            }
            row.querySelector('.quantity')?.focus();
        }

        function fillIssueLine(row, data) {
            row.dataset.productionOrderId = data.production_order_id || '';
            row.dataset.purchaseOrder = data.purchase_order || '';
            row.dataset.psNumber = data.ps_number || data.purchase_order || '';
            row.dataset.logoColor = data.logo_color || '';
            row.dataset.side = data.side || '';
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
            const isCustomer = type === 'customer';
            document.getElementById('createBtpAndIssueBtn').classList.toggle('d-none', !isProduction || Boolean(editingIssueId));
            updatePasteDialogMode();
            document.getElementById('issueTypeMetric').textContent = isProduction ? 'BTP sản xuất' : 'TP khách hàng';
            document.getElementById('pageTitle').textContent = isProduction ? 'Xuất bán thành phẩm đi sản xuất' : 'Xuất thành phẩm cho khách';
            document.getElementById('pageHint').textContent = isProduction
                ? 'Xuất BTP khỏi kho nội bộ để giao sản xuất. Khi hoàn thành, nhập lại bằng Phiếu nhập thành phẩm.'
                : 'Xuất thành phẩm cho khách hàng và trừ tồn kho nội bộ theo mã, size, màu và mặt.';
            if (!editingIssueId) {
                document.getElementById('saveBtn').textContent = isProduction ? 'Xuất BTP + in phiếu' : 'Xuất TP + in phiếu';
            }

            if (isProduction) {
                if (!value('department') || value('department') === 'Kinh doanh') document.getElementById('department').value = 'Sản xuất';
                if (!value('purpose') || value('purpose') === 'Xuất thành phẩm cho khách hàng') document.getElementById('purpose').value = 'Xuất BTP đi sản xuất';
            } else if (isCustomer) {
                if (!value('department') || value('department') === 'Sản xuất') document.getElementById('department').value = 'Kinh doanh';
                if (!value('purpose') || value('purpose') === 'Xuất BTP đi sản xuất') document.getElementById('purpose').value = 'Xuất thành phẩm cho khách hàng';
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
                    .then(response => jsonOrError(response, 'Không tải được danh mục hàng nội bộ'))
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
                ['ps_number', ['ps']],
                ['size', ['size', 'kich co']],
                ['color', ['mau', 'fabric color', 'mau vai']],
                ['logo_color', ['logo color', 'mau in']],
                ['dvt', ['dvt', 'unit']],
                ['ordered_quantity', ['qty don hang', 'quantity order', 'so luong dat hang']],
                ['quantity', ['quantity dat', 'sl dat', 'so luong', 'quantity', 'sl xuat', 'dat']],
                ['error_quantity', ['quantity loi', 'sl loi', 'loi']],
                ['side', ['mat', 'side', 'position', 'vi tri in']],
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

        function getPastedRows() {
            return document.getElementById('pasteExcelData').value
                .split(/\r?\n/)
                .map(row => row.split('\t').map(cell => cell.trim()))
                .filter(row => row.some(Boolean));
        }

        function pasteMappingKey(customer) {
            return `ttvPasteMap:${customer}`;
        }

        function normalizePasteMapping(rows, customer) {
            const maxColumns = Math.max(...rows.map(row => row.length), 0);
            if (!maxColumns) return [];

            const saved = (() => {
                try {
                    const parsed = JSON.parse(localStorage.getItem(pasteMappingKey(customer)) || '[]');
                    return Array.isArray(parsed) ? parsed : [];
                } catch (_) {
                    return [];
                }
            })();

            const detectedHeaders = (rows[0] || []).map(headerField);
            const hasHeader = detectedHeaders.filter(Boolean).length >= 2;
            let mapping = saved.some(Boolean) ? saved : (hasHeader ? detectedHeaders : (pastePresets[customer]?.columns || []));

            if (['UNIPAX', 'ELITE'].includes(customer)) {
                mapping = mapping.map(field => {
                    if (field === 'production_order') return 'ps_number';
                    if (field === 'location_code') return 'side';
                    return field;
                });
            }

            return Array.from({ length: maxColumns }, (_, index) => mapping[index] || '');
        }

        function savePasteColumnMapping() {
            const customer = value('pasteCustomer');
            localStorage.setItem(pasteMappingKey(customer), JSON.stringify(pasteColumnMapping));
        }

        function movePasteMapping(fromIndex, toIndex) {
            if (toIndex < 0 || toIndex >= pasteColumnMapping.length || fromIndex === toIndex) return;
            const copy = [...pasteColumnMapping];
            [copy[fromIndex], copy[toIndex]] = [copy[toIndex], copy[fromIndex]];
            pasteColumnMapping = copy;
            savePasteColumnMapping();
            renderPasteColumnMap();
        }

        function renderPasteColumnMap() {
            const customer = value('pasteCustomer');
            const rows = getPastedRows();
            const wrapper = document.getElementById('pasteColumnMap');
            const header = document.getElementById('pasteMapHeader');
            const body = document.getElementById('pasteMapRows');

            if (!rows.length) {
                pasteColumnMapping = [];
                wrapper.classList.remove('is-visible');
                header.innerHTML = '';
                body.innerHTML = '';
                return;
            }

            pasteColumnMapping = normalizePasteMapping(rows, customer);
            wrapper.classList.add('is-visible');

            header.innerHTML = pasteColumnMapping.map((field, index) => {
                const options = pasteFieldOptions.map(([value, label]) =>
                    `<option value="${esc(value)}" ${value === field ? 'selected' : ''}>${esc(label)}</option>`
                ).join('');
                return `
                    <th draggable="true" data-index="${index}">
                        <div class="paste-map-cell">
                            <div class="d-flex justify-content-between align-items-center gap-1">
                                <strong>C${index + 1}</strong>
                                <div class="paste-map-tools">
                                    <button type="button" class="paste-map-left" data-index="${index}" title="Dich mapping sang trai">&larr;</button>
                                    <button type="button" class="paste-map-right" data-index="${index}" title="Dich mapping sang phai">&rarr;</button>
                                </div>
                            </div>
                            <select class="form-select form-select-sm paste-map-select" data-index="${index}">
                                ${options}
                            </select>
                        </div>
                    </th>
                `;
            }).join('');

            const detectedHeaders = (rows[0] || []).map(headerField);
            const hasHeader = detectedHeaders.filter(Boolean).length >= 2;
            const samples = (hasHeader ? rows.slice(1, 4) : rows.slice(0, 3));
            body.innerHTML = samples.map((row, rowIndex) => `
                <tr>
                    ${pasteColumnMapping.map((_, colIndex) => `
                        <td>
                            <div class="paste-map-sample">${esc(row[colIndex] || '')}</div>
                        </td>
                    `).join('')}
                </tr>
            `).join('') || `<tr><td colspan="${pasteColumnMapping.length}">Khong co dong mau.</td></tr>`;

            header.querySelectorAll('.paste-map-select').forEach(select => {
                select.addEventListener('change', event => {
                    pasteColumnMapping[Number(event.target.dataset.index)] = event.target.value;
                    savePasteColumnMapping();
                });
            });
            header.querySelectorAll('.paste-map-left').forEach(button => {
                button.addEventListener('click', event => movePasteMapping(Number(event.currentTarget.dataset.index), Number(event.currentTarget.dataset.index) - 1));
            });
            header.querySelectorAll('.paste-map-right').forEach(button => {
                button.addEventListener('click', event => movePasteMapping(Number(event.currentTarget.dataset.index), Number(event.currentTarget.dataset.index) + 1));
            });
            header.querySelectorAll('th[draggable="true"]').forEach(th => {
                th.addEventListener('dragstart', event => event.dataTransfer.setData('text/plain', event.currentTarget.dataset.index));
                th.addEventListener('dragover', event => event.preventDefault());
                th.addEventListener('drop', event => {
                    event.preventDefault();
                    movePasteMapping(Number(event.dataTransfer.getData('text/plain')), Number(event.currentTarget.dataset.index));
                });
            });
        }

        function parseExcelPaste() {
            const customer = value('pasteCustomer');
            const preset = pastePresets[customer];
            renderPasteColumnMap();
            const rows = getPastedRows();

            if (!rows.length) throw new Error('Chưa có dữ liệu Excel để kiểm tra.');

            const detectedHeaders = rows[0].map(headerField);
            const hasHeader = detectedHeaders.filter(Boolean).length >= 2;
            let columns = pasteColumnMapping.length ? pasteColumnMapping : preset.columns;
            if (hasHeader) {
                rows.shift();
            } else if (customer === 'CUSTOM' && !columns.some(Boolean)) {
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
                if (line.side) extraNotes.push(`Mặt: ${line.side}`);
                if (parsePasteNumber(line.error_quantity) > 0) extraNotes.push(`Lỗi: ${line.error_quantity}`);
                if (line.ps_number) extraNotes.push(`PS#: ${line.ps_number}`);
                line.note = [...extraNotes, line.note].filter(Boolean).join(' - ');
                line.ma_hh = String(line.ma_hh || '').toUpperCase();
                line.location_code = String(line.location_code || '').toUpperCase();
                line.side = String(line.side || '').toUpperCase();
                if (!line.side && /^(FRONT|BACK|UPPER|UNDER|L\/B|L\/S\+R\/S|LEFT|RIGHT|F|B)$/i.test(line.location_code)) {
                    line.side = line.location_code;
                    line.location_code = '';
                }
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
                    <td>${esc(line.size)}</td>
                    <td>${esc(line.color)}</td>
                    <td>${esc(line.side || '-')}</td>
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
            updatePasteDialogMode();
            if (isUnipaxCustomerPaste()) {
                const receiptCount = Math.ceil(analyzedPastedLines.length / 20);
                document.getElementById('applyPastedLinesLabel').textContent =
                    `Tạo ${receiptCount} PNTP + ${receiptCount} PXTP UNIPAX`;
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
            if (isUnipaxCustomerPaste()) {
                saveUnipaxReceipt();
                return;
            }
            lineRows.innerHTML = '';
            analyzedPastedLines.forEach(line => addLine(line));
            if (!value('issueNote')) {
                document.getElementById('issueNote').value = isProductionIssuePaste()
                    ? `Paste BTP từ Excel`
                    : `Paste phiếu ${value('pasteCustomer')}`;
            }
            document.getElementById('pasteImportDialog').close();
            lineRows.querySelector('.internal-code')?.focus();
        }

        function saveUnipaxReceipt() {
            if (!value('issueDate')) return alert('Chọn ngày nhập kho.');

            const lines = analyzedPastedLines.map(line => ({
                ma_sp: line.ma_hh || '',
                category: line.ten_hh || '',
                internal_item_code: line.internal_item_code || '',
                size: line.size || '',
                color: line.color || '',
                side: line.side || '',
                dvt: line.dvt || '',
                quantity: line.quantity || 0,
                location_code: line.location_code || '',
                purchase_order: line.purchase_order || line.ps_number || '',
                ordered_quantity: line.ordered_quantity || null,
                logo_color: line.logo_color || '',
                customer: 'UNIPAX',
                note: [line.issue_date ? `Ngay Excel: ${isoToDateVn(line.issue_date)}` : '', line.note || ''].filter(Boolean).join(' - '),
                issue_date: value('issueDate'),
            }));
            if (lines.some(line => !line.internal_item_code || !Number(line.quantity))) {
                return alert('Mỗi dòng UNIPAX cần mã nội bộ và số lượng Đạt.');
            }

            const batches = [];
            const byDate = lines.reduce((groups, line) => {
                const date = value('issueDate');
                groups[date] = groups[date] || [];
                groups[date].push(line);
                return groups;
            }, {});
            Object.entries(byDate).forEach(([date, dateLines]) => {
                for (let index = 0; index < dateLines.length; index += 20) {
                    batches.push({date, lines: dateLines.slice(index, index + 20)});
                }
            });

            const button = document.getElementById('applyPastedLinesBtn');
            button.disabled = true;
            const receiptPrintWindows = batches.map(() => window.open('', '_blank'));
            const issuePrintWindows = batches.map(() => window.open('', '_blank'));
            const createdReceipts = [];
            const createdIssues = [];

            batches.reduce((promise, batch, index) => promise.then(() => {
                button.querySelector('span').textContent = `Đang tạo PNTP ${index + 1}/${batches.length}`;
                return fetch('/api/kiem-ton-kho/phieu-nhap-tp', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                    body: JSON.stringify({
                        location_code: '',
                        ma_ko: '',
                        checked_at: batch.date,
                        note: `Nhập kho từ phiếu xuất UNIPAX - phần ${index + 1}/${batches.length}`,
                        lines: batch.lines
                    })
                }).then(response => jsonOrError(response, `Không tạo được phiếu nhập UNIPAX phần ${index + 1}`))
                  .then(result => {
                      createdReceipts.push(result);
                      if (result.receipt_print_url && receiptPrintWindows[index]) {
                          receiptPrintWindows[index].location.href = result.receipt_print_url;
                      }
                      button.querySelector('span').textContent = `Đang tạo PXTP ${index + 1}/${batches.length}`;
                      return fetch(`/api/xuat-vat-tu-noi-bo/tu-phieu-nhap/${result.data.id}`, {
                          method: 'POST',
                          headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                          body: JSON.stringify({
                              issue_date: batch.date,
                              receiver_name: 'UNIPAX',
                              department: 'Kinh doanh',
                              purpose: 'Xuất thành phẩm cho khách hàng',
                              note: `Tạo từ phiếu xuất UNIPAX - ${result.data.receipt_code}`
                          })
                      });
                  })
                  .then(response => jsonOrError(response, `Không tạo được phiếu xuất UNIPAX phần ${index + 1}`))
                  .then(result => {
                      createdIssues.push(result);
                      if (result.print_url && issuePrintWindows[index]) {
                          issuePrintWindows[index].location.href = result.print_url;
                      }
                  });
            }), Promise.resolve())
              .then(() => {
                  document.getElementById('pasteImportDialog').close();
                  document.getElementById('pasteExcelData').value = '';
                  analyzedPastedLines = [];
                  loadIssues();
                  alert(`Đã tạo ${createdReceipts.length} phiếu nhập kho và ${createdIssues.length} phiếu xuất UNIPAX.`);
              })
              .catch(error => {
                  receiptPrintWindows.slice(createdReceipts.length).forEach(printWindow => printWindow?.close());
                  issuePrintWindows.slice(createdIssues.length).forEach(printWindow => printWindow?.close());
                  const completed = createdReceipts.length
                      ? ` Đã tạo thành công ${createdReceipts.length} phiếu nhập và ${createdIssues.length} phiếu xuất trước khi gặp lỗi.`
                      : '';
                  alert(error.message + completed);
              })
              .finally(() => {
                  button.disabled = false;
                  button.querySelector('span').textContent =
                      `Tạo ${Math.max(1, batches.length)} PNTP + ${Math.max(1, batches.length)} PXTP UNIPAX`;
              });
        }

        function saveIssue() {
            const lines = collectLines();
            if (!lines.length) return alert('Nhập ít nhất một dòng hàng.');
            if (lines.some(line => (!line.ma_hh && !line.internal_item_code) || !Number(line.quantity))) return alert('Mỗi dòng cần mã nội bộ hoặc mã đối chiếu, và số lượng.');

            fetch(editingIssueId ? `/api/xuat-vat-tu-noi-bo/${editingIssueId}` : '/api/xuat-vat-tu-noi-bo', {
                method: editingIssueId ? 'PUT' : 'POST',
                headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify({
                    issue_type: value('issueType'),
                    issue_date: value('issueDate'),
                    warehouse_code: '',
                    receiver_name: value('receiverName'),
                    department: value('department'),
                    production_order: Array.from(new Set(lines.map(line => line.production_order).filter(Boolean))).join(', '),
                    purpose: value('purpose'),
                    note: value('issueNote'),
                    lines
                })
            }).then(response => jsonOrError(response, 'Không tạo được phiếu xuất kho'))
              .then(result => {
                  window.open(result.print_url, '_blank');
                  const autoCodes = Array.isArray(result.btp_order_codes) ? result.btp_order_codes : [];
                  if (autoCodes.length) {
                      alert(`Da tu tao ${autoCodes.length} lenh BTP: ${autoCodes.join(', ')}`);
                  }
                  resetIssueForm();
                  loadIssues();
              })
              .catch(error => alert(error.message));
        }

        function applyBtpOrderCodesToRows(orders) {
            const activeRows = Array.from(lineRows.querySelectorAll('tr')).filter(row =>
                row.querySelector('.ma-hh').value.trim()
                || row.querySelector('.internal-code').value.trim()
                || row.querySelector('.quantity').value.trim()
            );

            activeRows.forEach((row, index) => {
                const code = orders[index]?.btp_order_code || orders[index];
                row.dataset.productionOrderId = '';
                row.dataset.purchaseOrder = '';
                row.dataset.customer = '';
                const input = row.querySelector('.line-production-order');
                if (input && code) {
                    input.value = code;
                }
            });
        }

        function legacyCreateBtpOrderAndIssue() {
            if (editingIssueId) return alert('Đang sửa phiếu cũ, không tạo lệnh BTP tự động ở chế độ này.');
            if (value('issueType') !== 'production') return alert('Chỉ tạo lệnh BTP tự động cho nghiệp vụ Xuất BTP đi sản xuất.');
            const lines = collectLines();
            if (!lines.length) return alert('Nhập ít nhất một dòng BTP.');
            if (lines.some(line => (!line.ma_hh && !line.internal_item_code) || !Number(line.quantity))) return alert('Mỗi dòng cần mã nội bộ hoặc mã đối chiếu, và số lượng.');

            const existingOrders = Array.from(new Set(lines.map(line => line.production_order).filter(Boolean)));
            if (existingOrders.length && !confirm(`Các dòng đã có lệnh: ${existingOrders.join(', ')}. Tạo lệnh BTP mới và ghi đè lệnh trên các dòng?`)) {
                return;
            }
            const customers = Array.from(new Set(lines.map(line => String(line.customer || '').trim()).filter(Boolean)));

            const button = document.getElementById('createBtpAndIssueBtn');
            button.disabled = true;
            button.innerHTML = '<i data-lucide="loader-circle"></i>Đang tạo lệnh...';
            if (window.lucide) lucide.createIcons();

            fetch('/api/lenh-btp/hang-loat', {
                method: 'POST',
                headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify({
                    order_date: value('issueDate'),
                    customer: customers.join(', '),
                    receiver_name: value('receiverName'),
                    department: value('department') || 'Sản xuất',
                    purpose: value('purpose') || 'Xuất BTP đi sản xuất',
                    note: value('issueNote'),
                    lines
                })
            }).then(response => jsonOrError(response, 'Không tạo được lệnh BTP'))
              .then(result => {
                  const code = result.data?.btp_order_code;
                  if (!code) throw new Error('Không nhận được mã lệnh BTP.');
                  applyBtpOrderCodeToRows(code);
                  document.getElementById('issueNote').value = [value('issueNote'), `Lệnh BTP ${code}`].filter(Boolean).join(' · ');
                  saveIssue();
              })
              .catch(error => alert(error.message))
              .finally(() => {
                  button.disabled = false;
                  button.innerHTML = '<i data-lucide="git-branch-plus"></i>Tạo lệnh BTP + xuất';
                  if (window.lucide) lucide.createIcons();
              });
        }

        function createBtpOrderAndIssue() {
            if (editingIssueId) return alert('Dang sua phieu cu, khong tao lenh BTP tu dong o che do nay.');
            if (value('issueType') !== 'production') return alert('Chi tao lenh BTP tu dong cho nghiep vu Xuat BTP di san xuat.');
            const lines = collectLines();
            if (!lines.length) return alert('Nhap it nhat mot dong BTP.');
            if (lines.some(line => (!line.ma_hh && !line.internal_item_code) || !Number(line.quantity))) return alert('Moi dong can ma noi bo hoac ma doi chieu, va so luong.');

            const existingOrders = Array.from(new Set(lines.map(line => line.production_order).filter(Boolean)));
            if (existingOrders.length && !confirm(`Cac dong da co lenh: ${existingOrders.join(', ')}. Tao lenh BTP moi va ghi de lenh tren cac dong?`)) {
                return;
            }
            const customers = Array.from(new Set(lines.map(line => String(line.customer || '').trim()).filter(Boolean)));

            const button = document.getElementById('createBtpAndIssueBtn');
            button.disabled = true;
            button.innerHTML = '<i data-lucide="loader-circle"></i>Dang tao lenh va phieu...';
            if (window.lucide) lucide.createIcons();
            let createdBtpOrderIds = [];
            const labelPrintWindow = window.open('about:blank', '_blank');

            fetch('/api/lenh-btp/hang-loat', {
                method: 'POST',
                headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify({
                    order_date: value('issueDate'),
                    customer: customers.join(', '),
                    receiver_name: value('receiverName'),
                    department: value('department') || 'San xuat',
                    purpose: value('purpose') || 'Xuat BTP di san xuat',
                    note: value('issueNote'),
                    lines
                })
            }).then(response => jsonOrError(response, 'Khong tao duoc lenh BTP'))
              .then(result => {
                  const orders = Array.isArray(result.data) ? result.data : [];
                  if (!orders.length) throw new Error('Khong nhan duoc danh sach lenh BTP.');
                  if (orders.length !== lines.length) throw new Error(`So lenh BTP (${orders.length}) khong khop so dong (${lines.length}).`);
                  createdBtpOrderIds = orders.map(order => order.id).filter(Boolean);
                  applyBtpOrderCodesToRows(orders);
                  const codes = orders.map(order => order.btp_order_code).filter(Boolean);
                  document.getElementById('issueNote').value = [value('issueNote'), `Lenh BTP ${codes.join(', ')}`].filter(Boolean).join(' - ');
                  return fetch('/api/lenh-btp/tao-phieu-xuat', {
                      method: 'POST',
                      headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                      body: JSON.stringify({
                          order_ids: orders.map(order => order.id).filter(Boolean),
                          order_codes: orders.map(order => order.btp_order_code).filter(Boolean),
                          issue_date: value('issueDate'),
                          receiver_name: value('receiverName'),
                          department: value('department') || 'San xuat',
                          purpose: value('purpose') || 'Xuat BTP di san xuat',
                          note: value('issueNote')
                      })
                  }).then(response => jsonOrError(response, 'Khong tao duoc phieu xuat BTP.'));
              })
              .then(result => {
                  if (result?.print_url) window.open(result.print_url, '_blank');
                  if (createdBtpOrderIds.length) {
                      const labelUrl = `/client/lenh-btp/tem-qr?ids=${encodeURIComponent(createdBtpOrderIds.join(','))}`;
                      if (labelPrintWindow) {
                          labelPrintWindow.location.href = labelUrl;
                      } else {
                          window.open(labelUrl, '_blank');
                      }
                  } else {
                      labelPrintWindow?.close();
                  }
                  resetIssueForm();
                  loadIssues();
              })
              .catch(error => {
                  labelPrintWindow?.close();
                  alert(error.message);
              })
              .finally(() => {
                  button.disabled = false;
                  button.innerHTML = '<i data-lucide="git-branch-plus"></i>Tao lenh BTP + xuat';
                  if (window.lucide) lucide.createIcons();
              });
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
                    issueRows.innerHTML = (result.data || []).map(issue => {
                        const status = issue.issue_type === 'customer' || String(issue.issue_code || '').startsWith('PXTP-')
                            ? '<span class="badge text-bg-success">TP khách</span>'
                            : '<span class="badge text-bg-primary">BTP sản xuất</span>';
                        const labelPrintButton = issue.btp_label_print_url
                            ? `<a class="btn btn-sm btn-outline-secondary" target="_blank" href="${esc(issue.btp_label_print_url)}">In QR ${num(issue.btp_label_count || 0)}</a>`
                            : '';
                        const receiveFinishedGoodsButton = issue.issue_type === 'production' || String(issue.issue_code || '').startsWith('PXBTP-')
                            ? `<a class="btn btn-sm btn-outline-success" href="/client/kiem-ton-kho?view=entry&from_issue=${encodeURIComponent(issue.id)}">Nhập TP</a>`
                            : '';
                        return `
                        <tr>
                            <td>${esc(issue.issue_code)}</td>
                            <td>${esc(issue.issue_date)}</td>
                            <td>${esc(issue.receiver_name)}</td>
                            <td>${esc(issue.department)}</td>
                            <td>${esc(issue.production_order)}</td>
                            <td>${esc(issue.purpose)}</td>
                            <td>${status}</td>
                            <td class="text-end">${num(issue.lines_count)}</td>
                            <td class="text-end">${num(issue.lines_sum_quantity)}</td>
                            <td class="text-nowrap text-end">
                                <a class="btn btn-sm btn-outline-primary" target="_blank" href="/client/xuat-vat-tu-noi-bo/${issue.id}/in">In</a>
                                ${labelPrintButton}
                                ${receiveFinishedGoodsButton}
                                <button class="btn btn-sm btn-outline-secondary edit-issue" data-id="${issue.id}">Sửa</button>
                                <button class="btn btn-sm btn-outline-danger delete-issue" data-id="${issue.id}">Xóa</button>
                            </td>
                        </tr>
                    `}).join('') || '<tr><td colspan="10" class="text-center hint">Chưa có phiếu</td></tr>';
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
            if (event.target.classList.contains('internal-code') && event.key === 'Enter') {
                event.preventDefault();
                applyInternalCatalog(event.target);
                event.target.closest('tr').querySelector('.quantity')?.focus();
            }
            if (event.target.classList.contains('quantity') && event.key === 'Enter') {
                event.preventDefault();
                const row = event.target.closest('tr');
                const nextRow = row.nextElementSibling;
                if (nextRow) {
                    nextRow.querySelector('.internal-code')?.focus();
                } else {
                    addLine();
                    lineRows.querySelector('tr:last-child .internal-code')?.focus();
                }
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
            const editButton = event.target.closest('.edit-issue');
            if (editButton) {
                loadIssueForEdit(editButton.dataset.id);
                return;
            }

            const button = event.target.closest('.delete-issue');
            if (!button || !confirm('Xóa phiếu xuất kho nội bộ này?')) return;

            fetch(`/api/xuat-vat-tu-noi-bo/${button.dataset.id}`, {
                method: 'DELETE',
                headers: {'Accept':'application/json','X-CSRF-TOKEN':csrfToken}
            }).then(response => jsonOrError(response, 'Không xóa được phiếu'))
              .then(loadIssues)
              .catch(error => alert(error.message));
        });

        document.getElementById('addLineBtn').addEventListener('click', () => addLine());
        document.getElementById('addTwentyLinesBtn').addEventListener('click', () => addBlankLines(20));
        document.getElementById('openPasteImportBtn').addEventListener('click', () => {
            updatePasteDialogMode();
            document.getElementById('pasteColumnGuide').textContent = pastePresets[value('pasteCustomer')].guide;
            document.getElementById('pasteImportDialog').showModal();
            renderPasteColumnMap();
            setTimeout(() => document.getElementById('pasteExcelData').focus(), 0);
        });
        document.getElementById('closePasteImportBtn').addEventListener('click', () => document.getElementById('pasteImportDialog').close());
        document.getElementById('cancelPasteImportBtn').addEventListener('click', () => document.getElementById('pasteImportDialog').close());
        document.getElementById('pasteCustomer').addEventListener('change', event => {
            updatePasteDialogMode();
            document.getElementById('pasteColumnGuide').textContent = pastePresets[event.target.value].guide;
            analyzedPastedLines = [];
            document.getElementById('pasteResultArea').classList.add('d-none');
            document.getElementById('applyPastedLinesBtn').disabled = true;
            document.getElementById('pasteFooterHint').textContent = 'Chưa có dữ liệu kiểm tra.';
            renderPasteColumnMap();
        });
        document.getElementById('pasteExcelData').addEventListener('input', renderPasteColumnMap);
        document.getElementById('analyzePasteBtn').addEventListener('click', analyzePastedData);
        document.getElementById('applyPastedLinesBtn').addEventListener('click', applyPastedLines);
        document.getElementById('saveBtn').addEventListener('click', saveIssue);
        document.getElementById('createBtpAndIssueBtn').addEventListener('click', createBtpOrderAndIssue);
        document.getElementById('cancelEditBtn').addEventListener('click', resetIssueForm);
        document.getElementById('issueType').addEventListener('change', event => applyIssueType(event.target.value));
        document.querySelectorAll('.date-vn').forEach(input => {
            input.addEventListener('blur', () => {
                if (input.value.trim()) input.value = isoToDateVn(dateVnToIso(input.value));
            });
        });
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

        const pageParams = new URLSearchParams(window.location.search);
        const requestedType = pageParams.get('type');
        const requestedKeyword = pageParams.get('keyword');
        if (requestedKeyword) {
            document.getElementById('keyword').value = requestedKeyword;
            document.getElementById('topIssueKeyword').value = requestedKeyword;
        }
        document.getElementById('pasteColumnGuide').textContent = pastePresets.UNIPAX.guide;
        document.getElementById('issueType').value = requestedType === 'customer' ? 'customer' : 'production';
        applyIssueType(document.getElementById('issueType').value);
        updatePasteDialogMode();
        addLine();
        loadIssues();
    </script>
</body>
</html>






