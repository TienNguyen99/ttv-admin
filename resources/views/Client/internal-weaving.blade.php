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
        .weaving-grid { display:grid; grid-template-columns:minmax(0, 1fr); gap:16px; align-items:start; }
        .weaving-tabs { display:flex; gap:8px; flex-wrap:wrap; }
        .weaving-tab { border:1px solid var(--wms-line, #dbe3ef); background:#fff; color:#0f2747; border-radius:8px; padding:8px 12px; font-weight:800; }
        .weaving-tab.is-active { background:#2563eb; color:#fff; border-color:#2563eb; }
        .weaving-pane { display:none; }
        .weaving-pane.is-active { display:block; }
        .weaving-table { min-width:1080px; }
        .weaving-plan-table { min-width:840px; }
        .weaving-order-picker { display:grid; grid-template-columns:minmax(240px, 1fr); gap:10px; align-items:end; }
        .weaving-order-results { display:grid; gap:8px; margin-top:12px; }
        .weaving-order-card { display:grid; grid-template-columns:minmax(170px,.9fr) minmax(180px,1.2fr) minmax(120px,.6fr) auto; gap:12px; align-items:center; border:1px solid #d8e8fb; background:#fff; border-radius:14px; padding:10px 12px; box-shadow:0 8px 20px rgba(64,111,170,.06); }
        .weaving-order-card:hover { border-color:#7db5ff; background:#f8fbff; }
        .weaving-order-title { color:#123b70; font-weight:900; font-family:Menlo,Consolas,monospace; }
        .weaving-order-meta { color:#64748b; font-size:12px; margin-top:2px; }
        #ordersPane .wms-panel__header, #ordersPane .wms-table-wrap, #ordersPane .d-flex.justify-content-between.align-items-center.mt-2, #orderStatus, #directPlanBtn, #clearOrderFilter { display:none !important; }
        #ordersPane .weaving-order-picker label { display:none; }
        #ordersPane .wms-panel { padding:12px; }
        #ordersPane .weaving-order-picker > div:nth-child(2) { display:none; }
        .weaving-import { min-height:96px; font-family:Consolas, monospace; font-size:12px; }
        .weaving-location-list { display:flex; gap:4px; flex-wrap:wrap; }
        .weaving-location { border:1px solid #bfdbfe; background:#eff6ff; color:#1d4ed8; border-radius:999px; padding:2px 8px; font-size:12px; font-weight:800; }
        .weaving-location-link { display:inline-flex; align-items:center; gap:4px; text-decoration:none; cursor:pointer; transition:background-color 180ms ease, border-color 180ms ease, color 180ms ease; }
        .weaving-location-link:hover, .weaving-location-link:focus-visible { border-color:#2563eb; background:#2563eb; color:#fff; outline:0; }
        .weaving-location-link svg { width:13px; height:13px; }
        .weaving-ok { color:#047857; background:#ecfdf5; border:1px solid #a7f3d0; border-radius:999px; padding:3px 8px; font-size:12px; font-weight:900; }
        .weaving-short { color:#b91c1c; background:#fef2f2; border:1px solid #fecaca; border-radius:999px; padding:3px 8px; font-size:12px; font-weight:900; }
        .weaving-help { color:#64748b; font-size:12px; line-height:1.45; }
        .weaving-plan-panel { border-color:#b7d8ff; box-shadow:0 18px 42px rgba(31,98,180,.10); }
        .weaving-plan-panel .wms-panel__header { align-items:flex-start; gap:14px; }
        .weaving-plan-header-main { min-width:260px; }
        .weaving-plan-actions { display:flex; flex-wrap:wrap; align-items:center; justify-content:flex-end; gap:8px; }
        .weaving-ticket { display:none; border:1px solid #cfe1f7; border-radius:14px; background:#fff; overflow:hidden; margin-bottom:12px; }
        .weaving-ticket.is-visible { display:block; }
        .weaving-ticket-head { display:grid; grid-template-columns:1fr 1fr; border-bottom:1px solid #dbeafe; }
        .weaving-ticket-cell { display:flex; justify-content:space-between; gap:10px; padding:8px 10px; border-right:1px solid #dbeafe; border-bottom:1px solid #edf4ff; font-size:12px; }
        .weaving-ticket-cell:nth-child(2n) { border-right:0; }
        .weaving-ticket-label { color:#64748b; font-weight:800; text-transform:uppercase; }
        .weaving-ticket-value { color:#0f2747; font-weight:900; text-align:right; }
        .weaving-ticket-body { display:grid; grid-template-columns:.85fr 1.15fr; gap:0; }
        .weaving-ticket-specs { border-right:1px solid #dbeafe; padding:10px; display:grid; gap:5px; font-size:12px; }
        .weaving-ticket-thread { padding:10px; min-width:0; }
        .weaving-ticket-thread table { width:100%; border-collapse:collapse; font-size:11px; }
        .weaving-ticket-thread th, .weaving-ticket-thread td { border:1px solid #dbeafe; padding:5px 6px; vertical-align:top; }
        .weaving-ticket-thread th { background:#eef6ff; color:#123b70; font-weight:900; }
        .weaving-ticket-bottom { display:grid; grid-template-columns:1fr 1fr; border-top:1px solid #dbeafe; }
        .weaving-ticket-box { min-height:86px; padding:10px; border-right:1px solid #dbeafe; }
        .weaving-ticket-box:last-child { border-right:0; }
        .weaving-ticket-image { min-height:140px; display:flex; align-items:center; justify-content:center; background:#f8fbff; border:1px dashed #b8cee8; border-radius:10px; overflow:hidden; }
        .weaving-ticket-image img { max-width:100%; max-height:220px; object-fit:contain; display:block; }
        .weaving-ticket-image-empty { color:#64748b; font-size:12px; font-weight:800; text-align:center; }
        .weaving-ticket-image .catalog-image-trigger { width:100%; min-height:138px; justify-content:center; flex-direction:column; border:0; background:transparent; }
        .weaving-ticket-image .catalog-image-trigger img { width:auto; height:auto; max-width:100%; max-height:220px; }
        .weaving-source-list { display:grid; grid-template-columns:repeat(auto-fit, minmax(330px, 1fr)); gap:12px; margin-bottom:14px; }
        .weaving-source-card { border:1px solid #d8e8fb; border-radius:16px; background:linear-gradient(180deg,#fff,#f7fbff); box-shadow:0 10px 24px rgba(64,111,170,.08); overflow:hidden; }
        .weaving-source-head { display:flex; justify-content:space-between; gap:12px; padding:12px 14px; border-bottom:1px solid #e6f0fb; }
        .weaving-source-code { color:#174679; font-weight:900; font-family:Menlo,Consolas,monospace; }
        .weaving-source-meta { color:#64748b; font-size:12px; margin-top:2px; }
        .weaving-material-list { display:grid; gap:0; }
        .weaving-material-row { display:grid; grid-template-columns:minmax(180px,1.4fr) minmax(100px,.7fr) minmax(120px,.8fr) minmax(90px,.5fr); gap:10px; align-items:center; padding:10px 14px; border-top:1px solid #eef5ff; font-size:12px; }
        .weaving-material-row:first-child { border-top:0; }
        .weaving-material-name { min-width:0; color:#10233f; }
        .weaving-material-name small { display:block; color:#6b7f99; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .weaving-bom-editor { display:grid; gap:8px; padding:12px 14px; border-top:1px solid #e6f0fb; background:#fbfdff; }
        .weaving-bom-edit-row { display:grid; grid-template-columns:minmax(120px,1.1fr) minmax(100px,.8fr) 82px 70px 70px minmax(120px,1fr) 34px; gap:6px; align-items:center; }
        .weaving-bom-edit-row input { min-width:0; height:34px; font-size:12px; padding:6px 8px; }
        .weaving-bom-edit-row .wms-btn { width:34px; height:34px; padding:0; justify-content:center; }
        .weaving-bom-editor-head { display:flex; align-items:center; justify-content:space-between; gap:8px; }
        .weaving-bom-editor-title { font-size:11px; font-weight:900; color:#375a82; text-transform:uppercase; letter-spacing:.02em; }
        .weaving-save-state { font-size:11px; color:#64748b; font-weight:800; }
        .weaving-save-state.is-saving { color:#1d4ed8; }
        .weaving-save-state.is-saved { color:#047857; }
        .weaving-save-state.is-error { color:#b91c1c; }
        .weaving-bom-add { width:max-content; }
        .weaving-bom-modal { max-width: 880px; }
        .weaving-bom-grid { display:grid; grid-template-columns:minmax(180px,1.2fr) 120px 100px 110px minmax(140px,1fr) 42px; gap:8px; align-items:end; }
        .weaving-bom-grid + .weaving-bom-grid { margin-top:8px; }
        .weaving-bom-grid label { display:block; margin-bottom:4px; color:#64748b; font-size:11px; font-weight:800; text-transform:uppercase; }
        .weaving-bom-row-remove { width:38px; height:38px; padding:0; }
        .weaving-source-action { margin-top:10px; }
        .weaving-import-debug { display:none; margin-top:10px; border:1px solid #bfdbfe; background:#f8fbff; border-radius:12px; padding:12px; color:#10233f; }
        .weaving-import-debug.is-visible { display:block; }
        .weaving-import-debug h3 { margin:0 0 8px; font-size:14px; font-weight:900; }
        .weaving-import-debug pre { max-height:260px; overflow:auto; margin:8px 0 0; padding:10px; border-radius:10px; background:#0f172a; color:#dbeafe; font-size:12px; white-space:pre-wrap; }
        .weaving-import-stats { display:flex; flex-wrap:wrap; gap:8px; }
        .weaving-import-stats span { border:1px solid #d8e8fb; background:#fff; border-radius:999px; padding:4px 9px; font-size:12px; font-weight:800; }
        @media (max-width: 620px) {
            .weaving-plan-panel .wms-panel__header { display:block; }
            .weaving-plan-actions { justify-content:flex-start; margin-top:10px; }
            .weaving-ticket-head, .weaving-ticket-body, .weaving-ticket-bottom { grid-template-columns:1fr; }
            .weaving-ticket-specs, .weaving-ticket-cell, .weaving-ticket-box { border-right:0; }
            .weaving-source-list { grid-template-columns:1fr; }
            .weaving-material-row { grid-template-columns:1fr; }
            .weaving-bom-edit-row { grid-template-columns:1fr 1fr; }
            .weaving-order-picker, .weaving-order-card { grid-template-columns:1fr; }
        }
        @media (max-width: 760px) { .weaving-bom-grid { grid-template-columns:1fr 1fr; } .weaving-bom-row-remove { width:100%; } }
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
                    <div class="weaving-order-picker">
                        <div><label>Tìm lệnh / mã hàng</label><input id="orderKeyword" class="form-control" list="orderOptions" autocomplete="off" placeholder="Gõ lệnh SX rồi Enter..."><datalist id="orderOptions"></datalist></div>
                        <div><label>Trạng thái SX</label><select id="orderStatus" class="form-select"><option value="">Tất cả</option><option value="pending">Pending</option><option value="late">Late</option><option value="due">Due</option></select></div>
                        <button id="directPlanBtn" class="wms-btn wms-btn--primary" type="button"><i data-lucide="search-check"></i>Soạn lệnh</button>
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
                        <div><h2>Định mức sợi</h2><p class="weaving-help mb-0">Paste Excel: Mã hàng dệt | Mã sợi | Vai trò | Định mức | ĐVT | Hao hụt % | Ghi chú. Ví dụ vai trò: sợi ngang, sợi dọc.</p></div>
                        <div class="d-flex gap-2 flex-wrap">
                            <input id="designWorkbookFile" type="file" accept=".xlsx,.xls,.xlsm,.ods" hidden>
                            <button id="importWorkbookBtn" class="wms-btn" type="button"><i data-lucide="file-spreadsheet"></i>Import file Excel</button>
                            <button id="importBomBtn" class="wms-btn wms-btn--primary" type="button"><i data-lucide="upload"></i>Import định mức</button>
                        </div>
                    </div>
                    <textarea id="bomImportText" class="form-control weaving-import mb-2" placeholder="Paste nguyên phiếu lệnh dệt Excel, hoặc BOM dạng: Mã hàng | Mã sợi | Vai trò | Định mức | ĐVT | Hao hụt %"></textarea>
                    <div id="workbookImportDebug" class="weaving-import-debug"></div>
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

        <aside class="wms-panel weaving-plan-panel">
            <div class="wms-panel__header">
                <div class="weaving-plan-header-main"><h2>Soạn sợi theo lệnh SX</h2><p id="planTitle" class="weaving-help mb-0">Chọn một lệnh sản xuất để xem sợi cần lấy.</p></div>
                <div class="weaving-plan-actions">
                    <div id="planSummary" class="d-flex flex-wrap gap-2"></div>
                    <button id="openShelfMapBtn" class="wms-btn" type="button" disabled><i data-lucide="map-pin"></i>Xem mặt kệ</button>
                    <button id="editWeavingTemplateBtn" class="wms-btn" type="button" disabled><i data-lucide="settings-2"></i>Thông tin mẫu</button>
                    <button id="exportWeavingSheetBtn" class="wms-btn" type="button" disabled><i data-lucide="file-spreadsheet"></i>Xuất Excel</button>
                    <button id="createIssueBtn" class="wms-btn wms-btn--primary" type="button" disabled><i data-lucide="send"></i>Tạo phiếu xuất</button>
                </div>
            </div>
            <div id="weavingTicket" class="weaving-ticket"></div>
            <div id="sourceItemRows" class="weaving-source-list"></div>
            <div class="wms-table-wrap">
                <table class="wms-table weaving-plan-table">
                    <thead><tr><th>Mã sợi</th><th>Tên sợi theo danh mục</th><th class="text-end">Cần</th><th class="text-end">Tồn</th><th>Kệ</th><th>Trạng thái</th></tr></thead>
                    <tbody id="planRows"><tr><td colspan="6" class="wms-empty">Chưa chọn lệnh.</td></tr></tbody>
                </table>
            </div>
        </aside>
    </div>
</main>

<div class="modal fade" id="bomQuickModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered weaving-bom-modal">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Thêm định mức BOM</h5>
                    <div id="bomQuickMeta" class="weaving-help"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Dong"></button>
            </div>
            <div class="modal-body">
                <div id="bomQuickRows"></div>
                <button id="bomQuickAddRow" type="button" class="wms-btn mt-2"><i data-lucide="plus"></i>Thêm dòng vật tư</button>
                <datalist id="bomMaterialOptions"></datalist>
            </div>
            <div class="modal-footer">
                <button type="button" class="wms-btn" data-bs-dismiss="modal">Hủy</button>
                <button id="bomQuickSave" type="button" class="wms-btn wms-btn--primary"><i data-lucide="save"></i>Lưu định mức</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="weavingTemplateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Thông tin mẫu LENH_DET</h5>
                    <div id="weavingTemplateMeta" class="weaving-help"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <div class="accordion" id="weavingTemplateAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#templateGeneral">Thông tin chung</button></h2>
                        <div id="templateGeneral" class="accordion-collapse collapse show" data-bs-parent="#weavingTemplateAccordion">
                            <div class="accordion-body">
                                <div class="row g-3">
                                    <div class="col-md-3"><label class="form-label">Khách hàng</label><input class="form-control" data-basic-field="customer"></div>
                                    <div class="col-md-3"><label class="form-label">PO</label><input class="form-control" data-basic-field="po_number"></div>
                                    <div class="col-md-3"><label class="form-label">Mã design</label><input class="form-control" data-basic-field="design_code"></div>
                                    <div class="col-md-3"><label class="form-label">Job #</label><input class="form-control" data-template-field="job_number"></div>
                                    <div class="col-md-3"><label class="form-label">Ngày ra lệnh</label><input class="form-control" type="date" data-basic-field="order_date"></div>
                                    <div class="col-md-3"><label class="form-label">Ngày giao</label><input class="form-control" type="date" data-basic-field="due_date"></div>
                                    <div class="col-md-2"><label class="form-label">Số lượng</label><input class="form-control" type="number" min="0" step="0.001" data-basic-field="order_quantity"></div>
                                    <div class="col-md-2"><label class="form-label">ĐVT</label><input class="form-control" data-basic-field="unit"></div>
                                    <div class="col-md-2"><label class="form-label">Tên label</label><input class="form-control" data-template-field="label_name"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#templateOperations">Công đoạn và quy cách</button></h2>
                        <div id="templateOperations" class="accordion-collapse collapse" data-bs-parent="#weavingTemplateAccordion">
                            <div class="accordion-body">
                                <div class="row g-3">
                                    <div class="col-md-6"><label class="form-label">Ui keo</label><input class="form-control" data-operation-field="UI KEO"></div>
                                    <div class="col-md-6"><label class="form-label">Loop</label><input class="form-control" data-operation-field="LOOP"></div>
                                    <div class="col-md-6"><label class="form-label">Phần trên</label><input class="form-control" data-operation-field="PHAN TREN"></div>
                                    <div class="col-md-6"><label class="form-label">Phần dưới</label><input class="form-control" data-operation-field="PHAN DUOI"></div>
                                    <div class="col-md-3"><label class="form-label">Chiều dài</label><input class="form-control" data-template-field="length"></div>
                                    <div class="col-md-3"><label class="form-label">Hoàn chỉnh</label><input class="form-control" data-template-field="finished_size"></div>
                                    <div class="col-md-2"><label class="form-label">Mã số hộp</label><input class="form-control" data-template-field="box_code"></div>
                                    <div class="col-md-2"><label class="form-label">SL/hộp</label><input class="form-control" data-template-field="quantity_per_box"></div>
                                    <div class="col-md-2"><label class="form-label">Sợi dọc (g)</label><input class="form-control" type="number" min="0" step="0.001" data-template-field="warp_grams"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#templateMachine">Máy, cuộn và cộng 10%</button></h2>
                        <div id="templateMachine" class="accordion-collapse collapse" data-bs-parent="#weavingTemplateAccordion">
                            <div class="accordion-body">
                                <div class="row g-3">
                                    <div class="col-md-2"><label class="form-label">Số pick</label><input class="form-control" data-template-field="pick"></div>
                                    <div class="col-md-2"><label class="form-label">Mật độ</label><input class="form-control" data-template-field="density"></div>
                                    <div class="col-md-2"><label class="form-label">Máy</label><input class="form-control" data-template-field="machine"></div>
                                    <div class="col-md-3"><label class="form-label">Máy cuộn nhỏ</label><input class="form-control" data-template-field="roll_machine_small"></div>
                                    <div class="col-md-3"><label class="form-label">Số cuộn nhỏ</label><input class="form-control" type="number" min="0" step="0.001" data-template-field="roll_count_small"></div>
                                    <div class="col-md-3"><label class="form-label">Máy cuộn lớn</label><input class="form-control" data-template-field="roll_machine_large"></div>
                                    <div class="col-md-3"><label class="form-label">Số cuộn lớn</label><input class="form-control" type="number" min="0" step="0.001" data-template-field="roll_count_large"></div>
                                    <div class="col-md-3"><label class="form-label">Số lượng +10%</label><input class="form-control" type="number" min="0" step="0.001" data-template-field="quantity_plus_10"></div>
                                    <div class="col-md-3"><label class="form-label">Số dòng</label><input class="form-control" type="number" min="0" step="1" data-template-field="row_count"></div>
                                    <div class="col-md-3"><label class="form-label">Máy dòng nhỏ</label><input class="form-control" data-template-field="row_machine_small"></div>
                                    <div class="col-md-3"><label class="form-label">Số dòng +10% nhỏ</label><input class="form-control" type="number" min="0" step="0.001" data-template-field="row_count_plus_10"></div>
                                    <div class="col-md-3"><label class="form-label">Máy dòng lớn</label><input class="form-control" data-template-field="row_machine_large"></div>
                                    <div class="col-md-3"><label class="form-label">Số dòng +10% lớn</label><input class="form-control" type="number" min="0" step="0.001" data-template-field="row_count_plus_10_large"></div>
                                    <div class="col-md-3"><label class="form-label">Ca nhỏ</label><input class="form-control" data-template-field="shift"></div>
                                    <div class="col-md-3"><label class="form-label">Ca lớn</label><input class="form-control" data-template-field="shift_large"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#templateFiles">File và USB</button></h2>
                        <div id="templateFiles" class="accordion-collapse collapse" data-bs-parent="#weavingTemplateAccordion">
                            <div class="accordion-body">
                                <div class="row g-3">
                                    <div class="col-md-4"><label class="form-label">Tên file</label><input class="form-control" data-template-field="file_name"></div>
                                    <div class="col-md-4"><label class="form-label">USB máy nhỏ</label><input class="form-control" data-template-field="usb_small"></div>
                                    <div class="col-md-4"><label class="form-label">USB máy lớn</label><input class="form-control" data-template-field="usb_large"></div>
                                </div>
                                <div class="weaving-help mt-2">Ảnh lấy theo mã hàng trong Danh mục nội bộ. QR được tạo tự động từ số lệnh.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="wms-btn" data-bs-dismiss="modal">Hủy</button>
                <button id="saveWeavingTemplateBtn" type="button" class="wms-btn wms-btn--primary"><i data-lucide="save"></i>Lưu thông tin</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@include('layouts.partials.catalog-image-paste-modal')
<script src="https://unpkg.com/lucide@latest"></script>
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
const num = value => Number(value || 0).toLocaleString('vi-VN', {maximumFractionDigits:3});
const dateText = value => value ? String(value).slice(0, 10).split('-').reverse().join('/') : '-';
const imageUrl = value => {
    const url = String(value || '').trim();
    if (!url) return '';
    if (/^(https?:)?\/\//i.test(url) || url.startsWith('data:')) return url;
    return url.startsWith('/') ? url : '/' + url;
};
const shelfMapUrl = (locationCode = '', productionOrder = '') => {
    const params = new URLSearchParams();
    if (productionOrder) params.set('production_order', productionOrder);
    if (locationCode) params.set('location_code', locationCode);
    const query = params.toString();
    return `/client/mat-ke-kho${query ? `?${query}` : ''}`;
};
const rowShelfLocations = row => {
    const locations = Array.isArray(row?.locations) ? row.locations.filter(location => location?.location_code) : [];
    if (locations.length) return locations;
    const fallback = row?.first_location || row?.catalog_shelf_code || '';
    return fallback ? [{location_code: fallback, quantity: Number(row?.stock_quantity || 0)}] : [];
};
let orderPage = 1, orderTotalPages = 1, itemPage = 1, itemTotalPages = 1, currentProductionOrder = null, timer = null;
let bomQuickItem = null, bomQuickModal = null, catalogTimer = null;
let currentSourceItems = [], currentPlanResult = null, bomSaveTimers = {};
let weavingTemplateModal = null;

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
    const params = new URLSearchParams({page: orderPage, per_page: 12});
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
        document.getElementById('orderOptions').innerHTML = (result.data || []).map(row => {
            const label = [row.item_code, row.customer, row.description].filter(Boolean).join(' - ');
            return `<option value="${esc(row.production_order)}" label="${esc(label)}"></option>`;
        }).join('');
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
                <td>${row.catalog_shelf_code ? `<a class="weaving-location weaving-location-link" href="${shelfMapUrl(row.catalog_shelf_code)}" title="Xem ${esc(row.catalog_shelf_code)} trên mặt kệ"><i data-lucide="map-pin"></i>${esc(row.catalog_shelf_code)}</a>` : '-'}</td>
                <td class="wms-number">${num(row.consumption_per_unit)}</td>
                <td class="wms-number">${num(row.waste_percent)}</td>
                <td><span class="${row.catalog_exists ? 'weaving-ok' : 'weaving-short'}">${row.catalog_exists ? 'Có DM' : 'Thiếu DM'}</span></td>
                <td>${esc(row.note || '')}</td>
            </tr>
        `).join('') || '<tr><td colspan="8" class="wms-empty">Mã hàng này chưa có định mức.</td></tr>';
        if (window.lucide) lucide.createIcons();
    });
}

function loadPlan(code) {
    currentProductionOrder = code;
    currentPlanResult = null;
    document.getElementById('planTitle').textContent = `Lệnh ${code}`;
    document.getElementById('planRows').innerHTML = '<tr><td colspan="6" class="wms-loading">Đang tính tồn và kệ...</td></tr>';
    document.getElementById('sourceItemRows').innerHTML = '<div class="wms-loading">Đang đọc BOM theo từng mã hàng...</div>';
    document.getElementById('weavingTicket').classList.remove('is-visible');
    document.getElementById('createIssueBtn').disabled = true;
    document.getElementById('editWeavingTemplateBtn').disabled = true;
    document.getElementById('exportWeavingSheetBtn').disabled = true;
    document.getElementById('openShelfMapBtn').disabled = true;
    return api(`/api/lenh-det/production-order-plan?production_order=${encodeURIComponent(code)}`).then(result => {
        currentPlanResult = result;
        const summary = result.summary || {};
        document.getElementById('planSummary').innerHTML = `
            <span class="weaving-location">${num(summary.line_count)} dòng sợi</span>
            <span class="weaving-location">Cần ${num(summary.required_quantity)}</span>
            <span class="${summary.short_count ? 'weaving-short' : 'weaving-ok'}">${summary.short_count ? `Thiếu ${num(summary.short_count)} mã` : 'Đủ tồn'}</span>
            ${summary.missing_bom_items?.length ? `<span class="weaving-short">Thiếu định mức: ${esc(summary.missing_bom_items.slice(0, 5).join(', '))}</span>` : ''}
        `;
        currentSourceItems = result.source_items || [];
        renderWeavingTicket(result);
        document.getElementById('sourceItemRows').innerHTML = renderSourceItems(currentSourceItems);
        if (window.lucide) lucide.createIcons();
        const hasMissingCatalog = (result.data || []).some(row => !row.catalog_exists);
        document.getElementById('createIssueBtn').disabled = !(result.data || []).length || hasMissingCatalog;
        document.getElementById('editWeavingTemplateBtn').disabled = false;
        document.getElementById('exportWeavingSheetBtn').disabled = !(result.data || []).length;
        document.getElementById('openShelfMapBtn').disabled = false;
        document.getElementById('planRows').innerHTML = (result.data || []).map(row => {
            const locations = rowShelfLocations(row).slice(0, 6).map(location => `
                <a class="weaving-location weaving-location-link" href="${shelfMapUrl(location.location_code, code)}" title="Mở kệ ${esc(location.location_code)}">
                    <i data-lucide="map-pin"></i>${esc(location.location_code)}: ${num(location.quantity)}
                </a>
            `).join('');
            return `
                <tr>
                    <td class="wms-code">${esc(row.material_code)}</td>
                    <td>${esc(row.catalog_name || row.material_name || '-')}</td>
                    <td class="wms-number">
                        ${num(row.required_quantity)} ${esc(row.unit || '')}
                        ${row.converted ? `<small class="d-block text-secondary">${num(row.required_quantity_raw)} ${esc(row.bom_unit || '')}</small>` : ''}
                    </td>
                    <td class="wms-number">${num(row.stock_quantity)}</td>
                    <td><div class="weaving-location-list">${locations || '<span class="weaving-short">Chưa có kệ</span>'}</div></td>
                    <td><span class="${!row.catalog_exists || row.status !== 'enough' ? 'weaving-short' : 'weaving-ok'}">${!row.catalog_exists ? 'Thiếu danh mục' : (row.status === 'enough' ? 'Đủ' : `Thiếu ${num(row.shortage_quantity)}`)}</span></td>
                </tr>
            `;
        }).join('') || '<tr><td colspan="6" class="wms-empty">Lệnh này chưa có định mức.</td></tr>';
        if (window.lucide) lucide.createIcons();
    }).catch(error => {
        currentPlanResult = null;
        document.getElementById('weavingTicket').classList.remove('is-visible');
        document.getElementById('editWeavingTemplateBtn').disabled = true;
        document.getElementById('exportWeavingSheetBtn').disabled = true;
        document.getElementById('openShelfMapBtn').disabled = true;
        document.getElementById('sourceItemRows').innerHTML = '';
        document.getElementById('planRows').innerHTML = `<tr><td colspan="6" class="wms-empty text-danger">${esc(error.message)}</td></tr>`;
    });
}

function renderWeavingTicket(result) {
    const box = document.getElementById('weavingTicket');
    const order = result.order || {};
    const sourceItem = (result.source_items || [])[0] || {};
    const metadata = order.metadata || sourceItem.metadata || {};
    const operations = metadata.operations || {};
    const lines = sourceItem.materials?.length ? sourceItem.materials : (result.data || []);
    const ticketImage = imageUrl(order.image_url || sourceItem.image_url || metadata.image_url || '');
    const ticketCatalogId = order.catalog_id || sourceItem.catalog_id || '';
    const ticketItemCode = order.item_code || sourceItem.item_code || '';
    const ticketImageControl = ticketItemCode
        ? `<button type="button" class="catalog-image-trigger" data-catalog-image-open data-catalog-id="${esc(ticketCatalogId)}" data-item-code="${esc(ticketItemCode)}" data-item-name="${esc(sourceItem.item_name || '')}" data-unit="${esc(sourceItem.unit || order.unit || '')}" data-image-url="${esc(ticketImage)}" title="${ticketImage ? 'Xem hoặc thay ảnh danh mục' : 'Paste ảnh vào danh mục'}">
            ${ticketImage ? `<img loading="lazy" src="${esc(ticketImage)}" alt="${esc(order.item_code || sourceItem.item_code || 'Hình lệnh dệt')}">` : '<span><i data-lucide="image-plus"></i><strong class="d-block">Paste ảnh vào danh mục</strong></span>'}
        </button>`
        : '<div class="weaving-ticket-image-empty">Mã hàng chưa có trong Danh mục nội bộ</div>';
    const operationRows = [
        ['Tên label', metadata.label_name || sourceItem.item_name || '-'],
        ['Ui Keo', operations.UI_KEO || operations['UI KEO'] || '-'],
        ['Loop', operations.LOOP || '-'],
        ['Phần trên', operations.PHAN_TREN || operations['PHAN TREN'] || '-'],
        ['Phần dưới', operations.PHAN_DUOI || operations['PHAN DUOI'] || '-'],
        ['Chiều dài', metadata.length || operations.CHIEU_DAI || operations['CHIEU DAI'] || '-'],
        ['Hoàn chỉnh', metadata.finished_size || operations.HOAN_CHINH || operations['HOAN CHINH'] || '-'],
        ['Mã số hộp', metadata.box_code || '-'],
    ];

    box.classList.add('is-visible');
    box.innerHTML = `
        <div class="weaving-ticket-head">
            ${ticketCell('Khách hàng', order.customer || sourceItem.customer || '-')}
            ${ticketCell('Lệnh in', order.production_order || order.order_code || '-')}
            ${ticketCell('PO', order.po_number || sourceItem.po_number || '-')}
            ${ticketCell('Mã hàng', order.item_code || sourceItem.item_code || '-')}
            ${ticketCell('Ngày ra lệnh', dateText(order.order_date))}
            ${ticketCell('File', order.design_code || sourceItem.design_code || metadata.sheet_name || '-')}
            ${ticketCell('Ngày giao', dateText(order.due_date))}
            ${ticketCell('Số lượng', `${num(order.planned_quantity || sourceItem.order_quantity || 0)} ${esc(sourceItem.unit || order.unit || 'PCS')}`)}
        </div>
        <div class="weaving-ticket-body">
            <div class="weaving-ticket-specs">
                ${operationRows.map(row => `<div class="d-flex justify-content-between gap-2"><span class="weaving-ticket-label">${esc(row[0])}</span><strong>${esc(row[1])}</strong></div>`).join('')}
                <div class="d-flex justify-content-between gap-2"><span class="weaving-ticket-label">Số pick</span><strong>${esc(metadata.pick || '-')}</strong></div>
                <div class="d-flex justify-content-between gap-2"><span class="weaving-ticket-label">Mật độ</span><strong>${esc(metadata.density || '-')}</strong></div>
                <div class="d-flex justify-content-between gap-2"><span class="weaving-ticket-label">Máy</span><strong>${esc(metadata.machine || '-')}</strong></div>
                <div class="d-flex justify-content-between gap-2"><span class="weaving-ticket-label">${esc(metadata.roll_machine_small || 'Muller')}</span><strong>${esc(metadata.roll_count_small || '-')} cuộn</strong></div>
                <div class="d-flex justify-content-between gap-2"><span class="weaving-ticket-label">${esc(metadata.roll_machine_large || 'Hi-Tex')}</span><strong>${esc(metadata.roll_count_large || '-')} cuộn</strong></div>
                <div class="d-flex justify-content-between gap-2"><span class="weaving-ticket-label">Số lượng +10%</span><strong>${esc(metadata.quantity_plus_10 || '-')}</strong></div>
                <div class="d-flex justify-content-between gap-2"><span class="weaving-ticket-label">Số dòng +10%</span><strong>${esc(metadata.row_count_plus_10 || '-')} / ${esc(metadata.row_count_plus_10_large || '-')}</strong></div>
            </div>
            <div class="weaving-ticket-thread">
                <table>
                    <thead><tr><th>Loại</th><th>Mã số chỉ</th><th>Kệ</th><th>Tên màu chỉ</th><th>TL/1PCS</th><th>T.L(g)</th></tr></thead>
                    <tbody>
                        ${lines.map(row => `
                            <tr>
                                <td>${esc(row.type || '')}</td>
                                <td class="wms-code">${esc(row.material_code || '')}</td>
                                <td>${esc(row.first_location || row.catalog_shelf_code || row.shelf_hint || '')}</td>
                                <td>${esc(row.catalog_name || row.material_name || '')}</td>
                                <td class="text-end">${num(row.consumption_per_unit || 0)}</td>
                                <td class="text-end">${num(Number(row.total_grams) > 0 ? row.total_grams : (row.required_quantity_raw || row.required_quantity || 0))}</td>
                            </tr>
                        `).join('') || '<tr><td colspan="6" class="text-center text-secondary">Chưa có định mức</td></tr>'}
                    </tbody>
                </table>
            </div>
        </div>
        <div class="weaving-ticket-bottom">
            <div class="weaving-ticket-box">
                <div class="weaving-ticket-label mb-1">USB / Tên file</div>
                <strong>${esc(order.design_code || metadata.sheet_name || '-')}</strong>
            </div>
            <div class="weaving-ticket-box">
                <div class="weaving-ticket-label mb-1">Hình ảnh</div>
                <div class="weaving-ticket-image">
                    ${ticketImageControl}
                </div>
            </div>
        </div>
    `;
}

function ticketCell(label, value) {
    return `<div class="weaving-ticket-cell"><span class="weaving-ticket-label">${esc(label)}</span><span class="weaving-ticket-value">${esc(value || '-')}</span></div>`;
}

function metadataOperation(operations, name) {
    const normalize = value => String(value || '').toUpperCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^A-Z0-9]+/g, ' ').trim();
    const wanted = normalize(name);
    const entry = Object.entries(operations || {}).find(([key]) => normalize(key) === wanted);
    return entry ? entry[1] : '';
}

function openWeavingTemplateEditor() {
    if (!currentPlanResult) return;
    const order = currentPlanResult.order || {};
    const sourceItem = (currentPlanResult.source_items || [])[0] || {};
    const metadata = {...(sourceItem.metadata || {}), ...(order.metadata || {})};
    const operations = metadata.operations || {};
    document.getElementById('weavingTemplateMeta').textContent = `${order.production_order || currentProductionOrder} · ${order.item_code || sourceItem.item_code || ''}`;

    document.querySelectorAll('[data-basic-field]').forEach(input => {
        const key = input.dataset.basicField;
        input.value = order[key] ?? sourceItem[key] ?? '';
    });
    document.querySelectorAll('[data-template-field]').forEach(input => {
        input.value = metadata[input.dataset.templateField] ?? '';
    });
    document.querySelectorAll('[data-operation-field]').forEach(input => {
        input.value = metadataOperation(operations, input.dataset.operationField);
    });
    weavingTemplateModal.show();
}

function saveWeavingTemplateDetails() {
    if (!currentProductionOrder || !currentPlanResult) return;
    const order = currentPlanResult.order || {};
    const sourceItem = (currentPlanResult.source_items || [])[0] || {};
    const payload = {
        production_order: currentProductionOrder,
        item_code: order.item_code || sourceItem.item_code || '',
        metadata: {operations: {}},
    };
    document.querySelectorAll('[data-basic-field]').forEach(input => {
        payload[input.dataset.basicField] = input.value.trim();
    });
    document.querySelectorAll('[data-template-field]').forEach(input => {
        payload.metadata[input.dataset.templateField] = input.value.trim();
    });
    document.querySelectorAll('[data-operation-field]').forEach(input => {
        payload.metadata.operations[input.dataset.operationField.replace(/\s+/g, '_')] = input.value.trim();
    });

    const button = document.getElementById('saveWeavingTemplateBtn');
    button.disabled = true;
    api('/api/lenh-det/template-details', {
        method: 'POST',
        body: JSON.stringify(payload),
    }).then(result => {
        weavingTemplateModal.hide();
        return loadPlan(currentProductionOrder);
    }).catch(error => alert(error.message))
      .finally(() => { button.disabled = false; });
}

function printWeavingTicket() {
    const ticket = document.getElementById('weavingTicket');
    if (!currentPlanResult || !ticket?.innerHTML.trim()) return;
    const win = window.open('', '_blank', 'width=960,height=720');
    if (!win) return alert('Trình duyệt đang chặn popup in.');
    win.document.write(`
        <!doctype html><html><head><meta charset="utf-8"><title>In lệnh dệt</title>
        <style>
            body{font-family:Arial,Helvetica,sans-serif;margin:18px;color:#111827}
            .weaving-ticket{display:block;border:2px solid #111827}
            .weaving-ticket-head{display:grid;grid-template-columns:1fr 1fr}
            .weaving-ticket-cell{display:flex;justify-content:space-between;gap:10px;padding:8px;border-right:1px solid #111827;border-bottom:1px solid #111827;font-size:13px}
            .weaving-ticket-cell:nth-child(2n){border-right:0}
            .weaving-ticket-label{font-weight:700;text-transform:uppercase}
            .weaving-ticket-value{font-weight:800;text-align:right}
            .weaving-ticket-body{display:grid;grid-template-columns:.85fr 1.15fr}
            .weaving-ticket-specs{border-right:1px solid #111827;padding:10px;display:grid;gap:6px;font-size:13px}
            .d-flex{display:flex}.justify-content-between{justify-content:space-between}.gap-2{gap:8px}
            table{width:100%;border-collapse:collapse;font-size:12px}th,td{border:1px solid #111827;padding:6px;vertical-align:top}th{font-weight:800}
            .weaving-ticket-thread{padding:10px}.text-end{text-align:right}.text-center{text-align:center}.text-secondary{color:#64748b}
            .weaving-ticket-bottom{display:grid;grid-template-columns:1fr 1fr;border-top:1px solid #111827}
            .weaving-ticket-box{min-height:90px;padding:10px;border-right:1px solid #111827}.weaving-ticket-box:last-child{border-right:0}
            .weaving-ticket-image{min-height:140px;display:flex;align-items:center;justify-content:center;border:1px dashed #111827;overflow:hidden}
            .weaving-ticket-image img{max-width:100%;max-height:220px;object-fit:contain}
            .weaving-ticket-image-empty{text-align:center;color:#64748b;font-weight:700}
            @media print{body{margin:8mm}.no-print{display:none}}
        </style></head><body>${ticket.outerHTML}<script>window.onload=()=>{window.print();setTimeout(()=>window.close(),300)}<\/script></body></html>
    `);
    win.document.close();
}

function renderSourceItems(items) {
    if (!items.length) {
        return '<div class="wms-empty">Lệnh này chưa có dòng mã hàng.</div>';
    }

    return items.map(item => {
        const materials = item.materials || [];
        const editorRows = (materials.length ? materials : [{}]).map(material => renderSourceBomEditRow(material)).join('');

        return `
            <div class="weaving-source-card" data-source-item-code="${esc(item.item_code || '')}">
                <div class="weaving-source-head">
                    <div>
                        <div class="weaving-source-code">${esc(item.item_code || '-')}</div>
                        <div class="weaving-source-meta">${esc(item.item_name || '-')}</div>
                    </div>
                    <div class="text-end">
                        <div class="wms-number">${num(item.order_quantity)} ${esc(item.unit || '')}</div>
                        <div class="weaving-source-meta">${num(item.material_count || 0)} vật liệu BOM</div>
                    </div>
                </div>
                <div class="weaving-bom-editor">
                    <div class="weaving-bom-editor-head">
                        <div>
                            <div class="weaving-bom-editor-title">Sửa định mức trực tiếp</div>
                            <div class="weaving-help">Nhập mã vật tư và định mức, hệ thống tự lưu sau khi dừng gõ.</div>
                        </div>
                        <span class="weaving-save-state" data-save-state>Sẵn sàng</span>
                    </div>
                    <div class="weaving-bom-edit-row weaving-help">
                        <span>Mã vật tư</span><span>Vai trò</span><span>ĐM</span><span>ĐVT</span><span>Hao hụt</span><span>Ghi chú</span><span></span>
                    </div>
                    <div data-bom-editor-rows>${editorRows}</div>
                    <button type="button" class="wms-btn weaving-bom-add" data-add-source-bom-row><i data-lucide="plus"></i>Thêm dòng</button>
                </div>
            </div>
        `;
    }).join('');
}

function renderSourceBomEditRow(material = {}) {
    return `
        <div class="weaving-bom-edit-row" data-bom-edit-row>
            <input class="form-control text-uppercase" data-bom-field="material_code" list="bomMaterialOptions" autocomplete="off" value="${esc(material.material_code || '')}" placeholder="Mã vật tư">
            <input class="form-control" data-bom-field="line_role" value="${esc(material.line_role || '')}" placeholder="Ngang/dọc">
            <input class="form-control text-end" data-bom-field="consumption_per_unit" type="text" inputmode="decimal" value="${esc(material.consumption_per_unit ?? '')}" placeholder="0,3">
            <input class="form-control" data-bom-field="unit" value="${esc(material.bom_unit || material.unit || 'gam')}" placeholder="gam">
            <input class="form-control text-end" data-bom-field="waste_percent" type="text" inputmode="decimal" value="${esc(material.waste_percent ?? 0)}" placeholder="%">
            <input class="form-control" data-bom-field="note" value="${esc(material.note || '')}" placeholder="Ghi chú">
            <button type="button" class="wms-btn" data-remove-source-bom-row title="Xóa dòng">×</button>
        </div>
    `;
}

function sourceCardFromElement(element) {
    return element.closest('[data-source-item-code]');
}

function sourceItemByCode(code) {
    const clean = String(code || '').trim().toUpperCase();
    return currentSourceItems.find(item => String(item.item_code || '').trim().toUpperCase() === clean) || {};
}

function collectSourceBomLines(card) {
    return Array.from(card.querySelectorAll('[data-bom-edit-row]')).map(row => {
        const value = field => row.querySelector(`[data-bom-field="${field}"]`)?.value?.trim() || '';
        return {
            material_code: value('material_code').toUpperCase(),
            line_role: value('line_role').toUpperCase(),
            consumption_per_unit: parseNumberInput(value('consumption_per_unit')),
            unit: value('unit') || 'gam',
            waste_percent: parseNumberInput(value('waste_percent')),
            note: value('note'),
        };
    }).filter(line => line.material_code || line.consumption_per_unit || line.line_role || line.note);
}

function setSourceSaveState(card, text, state = '') {
    const badge = card.querySelector('[data-save-state]');
    if (!badge) return;
    badge.className = `weaving-save-state ${state ? 'is-' + state : ''}`;
    badge.textContent = text;
}

function scheduleSourceBomSave(card) {
    const itemCode = card.dataset.sourceItemCode || '';
    clearTimeout(bomSaveTimers[itemCode]);
    setSourceSaveState(card, 'Đang nhập...', 'saving');
    bomSaveTimers[itemCode] = setTimeout(() => saveSourceBom(card), 700);
}

function saveSourceBom(card) {
    const itemCode = card.dataset.sourceItemCode || '';
    const item = sourceItemByCode(itemCode);
    const lines = collectSourceBomLines(card);

    if (!itemCode) {
        setSourceSaveState(card, 'Thiếu mã hàng', 'error');
        return;
    }
    if (!lines.length) {
        setSourceSaveState(card, 'Cần ít nhất 1 dòng', 'error');
        return;
    }
    const invalid = lines.find(line => !line.material_code || Number(line.consumption_per_unit || 0) <= 0);
    if (invalid) {
        setSourceSaveState(card, 'Chưa đủ mã/ĐM', 'error');
        return;
    }

    setSourceSaveState(card, 'Đang lưu...', 'saving');
    api('/api/lenh-det/boms', {
        method: 'POST',
        body: JSON.stringify({
            item_code: itemCode,
            item_name: item.item_name || '',
            customer: item.customer || '',
            unit: item.unit || '',
            lines,
        }),
    }).then(() => {
        setSourceSaveState(card, 'Đã lưu', 'saved');
        if (currentProductionOrder) {
            setTimeout(() => loadPlan(currentProductionOrder), 250);
        }
    }).catch(error => {
        setSourceSaveState(card, error.message || 'Lưu lỗi', 'error');
    });
}

function openBomQuickModal(item) {
    bomQuickItem = item;
    document.getElementById('bomQuickMeta').textContent = `${item.item_code || '-'} - ${item.item_name || ''} - SL lenh ${num(item.order_quantity)} ${item.unit || ''}`;
    document.getElementById('bomQuickRows').innerHTML = '';
    addBomQuickRow();
    addBomQuickRow();
    if (!bomQuickModal) bomQuickModal = new bootstrap.Modal(document.getElementById('bomQuickModal'));
    bomQuickModal.show();
    setTimeout(() => document.querySelector('#bomQuickRows .bom-material-code')?.focus(), 150);
    if (window.lucide) lucide.createIcons();
}

function addBomQuickRow(data = {}) {
    const wrap = document.createElement('div');
    wrap.className = 'weaving-bom-grid';
    wrap.innerHTML = `
        <div>
            <label>Mã vật tư</label>
            <input class="form-control bom-material-code text-uppercase" list="bomMaterialOptions" autocomplete="off" value="${esc(data.material_code || '')}" placeholder="Gõ mã/tên vật tư">
        </div>
        <div>
            <label>Định mức</label>
            <input class="form-control bom-consumption" type="text" inputmode="decimal" value="${esc(data.consumption_per_unit || '')}" placeholder="0,3">
        </div>
        <div>
            <label>ĐVT</label>
            <input class="form-control bom-unit" value="${esc(data.unit || 'gam')}" placeholder="gam">
        </div>
        <div>
            <label>Hao hụt %</label>
            <input class="form-control bom-waste" type="text" inputmode="decimal" value="${esc(data.waste_percent || 0)}">
        </div>
        <div>
            <label>Vai trò</label>
            <input class="form-control bom-role" value="${esc(data.line_role || '')}" placeholder="Ngang / dọc">
        </div>
        <button type="button" class="wms-btn weaving-bom-row-remove" title="Xóa dòng">×</button>
    `;
    document.getElementById('bomQuickRows').appendChild(wrap);
}

function collectBomQuickLines() {
    return Array.from(document.querySelectorAll('#bomQuickRows .weaving-bom-grid')).map(row => ({
        material_code: row.querySelector('.bom-material-code').value.trim().toUpperCase(),
        line_role: row.querySelector('.bom-role').value.trim().toUpperCase(),
        consumption_per_unit: parseNumberInput(row.querySelector('.bom-consumption').value),
        unit: row.querySelector('.bom-unit').value.trim(),
        waste_percent: parseNumberInput(row.querySelector('.bom-waste').value),
        note: '',
    })).filter(line => line.material_code || line.consumption_per_unit);
}

function parseNumberInput(value) {
    const normalized = String(value || '').replace(',', '.').replace(/[^\d.\-]/g, '');
    return Number(normalized || 0);
}

function searchBomMaterials(keyword) {
    clearTimeout(catalogTimer);
    catalogTimer = setTimeout(() => {
        const q = String(keyword || '').trim();
        if (q.length < 1) return;
        fetch('/api/danh-muc-noi-bo?limit=30&keyword=' + encodeURIComponent(q), {headers:{'Accept':'application/json'}})
            .then(response => response.json())
            .then(result => {
                document.getElementById('bomMaterialOptions').innerHTML = (result.data || []).map(item =>
                    `<option value="${esc(item.item_code || '')}">${esc([item.item_name, item.unit, item.shelf_code].filter(Boolean).join(' - '))}</option>`
                ).join('');
            })
            .catch(() => {});
    }, 180);
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

function importWeavingBomText() {
    const textarea = document.getElementById('bomImportText');
    const text = textarea.value.trim();
    if (!text) return alert('Chưa có dữ liệu paste.');
    const normalized = text.toUpperCase();
    const looksLikeDesignSheet = normalized.includes('LỆNH IN') || normalized.includes('LENH IN') || normalized.includes('MÃ HÀNG') || normalized.includes('MA HANG');
    const endpoint = looksLikeDesignSheet ? '/api/lenh-det/design-sheet/import' : '/api/lenh-det/boms/import';

    api(endpoint, {method:'POST', body: JSON.stringify({text})})
        .then(result => {
            alert(result.message || 'Đã import.');
            textarea.value = '';
            loadItems();
            if (result.parsed?.item_code) {
                document.getElementById('bomItemCode').value = result.parsed.item_code;
                loadBom();
            } else {
                loadBom();
            }
            if (result.parsed?.order_code) {
                document.getElementById('orderKeyword').value = result.parsed.order_code;
                loadPlan(result.parsed.order_code);
            }
        })
        .catch(error => alert(error.message));
}

function importDesignWorkbook(file) {
    if (!file) return;
    renderWorkbookImportDebug(null);
    const button = document.getElementById('importWorkbookBtn');
    const oldText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = 'Đang đọc file...';

    uploadDesignWorkbookInChunks(file, progress => {
        button.innerHTML = `Đang upload ${progress}%...`;
        renderWorkbookImportDebug({
            message: `Đang upload file Excel ${progress}%`,
            summary: {},
            samples: [],
            errors: [],
            debug: {
                file: file.name,
                size_mb: (file.size / 1024 / 1024).toFixed(1),
                progress: progress + '%',
            },
        });
    })
        .then(result => {
            renderWorkbookImportDebug(result);
            loadItems();
            loadOrders();
        })
        .catch(error => renderWorkbookImportDebug({
            message: error.message || 'Không import được file Excel.',
            error: true,
            summary: error.summary || {},
            errors: error.errors ? Object.entries(error.errors).map(([field, messages]) => ({sheet: field, message: Array.isArray(messages) ? messages.join('; ') : String(messages)})) : (error.errors_list || []),
            debug: error.debug || {http_status: error.http_status || '', raw: error},
        }))
        .finally(() => {
            button.disabled = false;
            button.innerHTML = oldText;
            document.getElementById('designWorkbookFile').value = '';
            if (window.lucide) lucide.createIcons();
        });
}

async function uploadDesignWorkbookInChunks(file, onProgress) {
    const chunkSize = 4 * 1024 * 1024;
    const totalChunks = Math.max(1, Math.ceil(file.size / chunkSize));
    const uploadId = `${Date.now()}-${Math.random().toString(36).slice(2)}-${file.name}`
        .replace(/[^A-Za-z0-9_-]/g, '')
        .slice(0, 90);
    let finalResult = null;

    for (let index = 0; index < totalChunks; index++) {
        const start = index * chunkSize;
        const chunk = file.slice(start, Math.min(start + chunkSize, file.size));
        const form = new FormData();
        form.append('upload_id', uploadId);
        form.append('chunk_index', String(index));
        form.append('total_chunks', String(totalChunks));
        form.append('file_name', file.name);
        form.append('skip_missing_catalog', '1');
        form.append('chunk', chunk, `${file.name}.part${index}`);

        const response = await fetch('/api/lenh-det/design-workbook/chunk', {
            method: 'POST',
            headers: {'Accept':'application/json', 'X-CSRF-TOKEN': csrfToken},
            body: form,
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) {
            result.error = true;
            result.http_status = response.status;
            throw result;
        }
        finalResult = result;
        onProgress?.(Math.round(((index + 1) / totalChunks) * 100));
    }

    return finalResult;
}

function renderWorkbookImportDebug(result) {
    const box = document.getElementById('workbookImportDebug');
    if (!box) return;
    if (!result) {
        box.classList.remove('is-visible');
        box.innerHTML = '';
        return;
    }
    const s = result.summary || {};
    const errors = result.errors || [];
    const samples = result.samples || [];
    const debug = result.debug || null;
    box.classList.add('is-visible');
    box.innerHTML = `
        <h3>${esc(result.message || (result.error ? 'Import lỗi' : 'Kết quả import'))}</h3>
        <div class="weaving-import-stats">
            <span>Sheet: ${num(s.total_sheets || 0)}</span>
            <span>Import: ${num(s.imported || 0)}</span>
            <span>Bỏ qua: ${num(s.skipped || 0)}</span>
            <span>Lỗi: ${num(s.errors || 0)}</span>
            <span>Thiếu danh mục: ${num(s.missing_catalog_count || 0)}</span>
        </div>
        ${samples.length ? `<pre>${esc(samples.map(x => `${x.sheet}: ${x.order_code || '-'} | ${x.item_code || '-'} | ${x.line_count || 0} dòng${x.warnings?.length ? ' | ' + x.warnings.join('; ') : ''}`).join('\n'))}</pre>` : ''}
        ${errors.length ? `<pre>${esc(errors.map(x => `${x.sheet || '-'}: ${x.message || '-'}${x.missing_catalog ? ' | Thiếu: ' + x.missing_catalog.join(', ') : ''}`).join('\n'))}</pre>` : ''}
        ${debug ? `<pre>${esc(JSON.stringify(debug, null, 2))}</pre>` : ''}
        ${result.error && !errors.length && !debug ? `<pre>${esc(result.message || '')}</pre>` : ''}
    `;
}

document.querySelectorAll('.weaving-tab').forEach(button => button.addEventListener('click', () => {
    document.querySelectorAll('.weaving-tab').forEach(x => x.classList.remove('is-active'));
    document.querySelectorAll('.weaving-pane').forEach(x => x.classList.remove('is-active'));
    button.classList.add('is-active');
    document.getElementById(button.dataset.pane).classList.add('is-active');
}));
document.getElementById('importItemsBtn').addEventListener('click', () => postImport('/api/lenh-det/items/import', 'itemsImportText', loadItems));
document.getElementById('importBomBtn').addEventListener('click', importWeavingBomText);
document.getElementById('importWorkbookBtn').addEventListener('click', () => document.getElementById('designWorkbookFile').click());
document.getElementById('designWorkbookFile').addEventListener('change', event => importDesignWorkbook(event.target.files?.[0]));
document.getElementById('loadBomBtn').addEventListener('click', loadBom);
document.getElementById('orderRows').addEventListener('click', event => {
    const button = event.target.closest('.plan-order-btn');
    if (button) loadPlan(button.dataset.code);
});
document.getElementById('sourceItemRows').addEventListener('click', event => {
    const addButton = event.target.closest('[data-add-source-bom-row]');
    if (addButton) {
        const card = sourceCardFromElement(addButton);
        const rows = card?.querySelector('[data-bom-editor-rows]');
        rows?.insertAdjacentHTML('beforeend', renderSourceBomEditRow({unit: 'gam'}));
        if (window.lucide) lucide.createIcons();
        return;
    }

    const removeButton = event.target.closest('[data-remove-source-bom-row]');
    if (removeButton) {
        const card = sourceCardFromElement(removeButton);
        const rows = card?.querySelectorAll('[data-bom-edit-row]');
        if (!card || rows.length <= 1) return;
        removeButton.closest('[data-bom-edit-row]')?.remove();
        scheduleSourceBomSave(card);
    }
});
document.getElementById('sourceItemRows').addEventListener('input', event => {
    const field = event.target.closest('[data-bom-field]');
    if (!field) return;
    if (field.dataset.bomField === 'material_code') searchBomMaterials(field.value);
    const card = sourceCardFromElement(field);
    if (card) scheduleSourceBomSave(card);
});
document.getElementById('bomQuickAddRow').addEventListener('click', () => {
    addBomQuickRow();
    if (window.lucide) lucide.createIcons();
});
document.getElementById('bomQuickRows').addEventListener('click', event => {
    const button = event.target.closest('.weaving-bom-row-remove');
    if (!button) return;
    const rows = document.querySelectorAll('#bomQuickRows .weaving-bom-grid');
    if (rows.length <= 1) return;
    button.closest('.weaving-bom-grid')?.remove();
});
document.getElementById('bomQuickRows').addEventListener('input', event => {
    if (event.target.classList.contains('bom-material-code')) searchBomMaterials(event.target.value);
});
document.getElementById('bomQuickSave').addEventListener('click', () => {
    if (!bomQuickItem?.item_code) return alert('Chưa có mã hàng để lưu định mức.');
    const lines = collectBomQuickLines();
    if (!lines.length) return alert('Nhập ít nhất 1 dòng vật tư và định mức.');
    const invalid = lines.find(line => !line.material_code || Number(line.consumption_per_unit || 0) <= 0);
    if (invalid) return alert('Mỗi dòng cần có mã vật tư và định mức lớn hơn 0.');

    const button = document.getElementById('bomQuickSave');
    button.disabled = true;
    api('/api/lenh-det/boms', {
        method: 'POST',
        body: JSON.stringify({
            item_code: bomQuickItem.item_code,
            lines,
        })
    }).then(() => {
        bomQuickModal?.hide();
        loadItems();
        document.getElementById('bomItemCode').value = bomQuickItem.item_code;
        loadBom();
        if (currentProductionOrder) loadPlan(currentProductionOrder);
    }).catch(error => alert(error.message))
      .finally(() => { button.disabled = false; });
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
document.getElementById('editWeavingTemplateBtn').addEventListener('click', openWeavingTemplateEditor);
document.getElementById('saveWeavingTemplateBtn').addEventListener('click', saveWeavingTemplateDetails);
document.getElementById('exportWeavingSheetBtn').addEventListener('click', () => {
    if (!currentProductionOrder || !currentPlanResult) return;
    const button = document.getElementById('exportWeavingSheetBtn');
    const sheetWindow = window.open('about:blank', '_blank');
    button.disabled = true;
    const url = `/api/lenh-det/export-excel?production_order=${encodeURIComponent(currentProductionOrder)}`;
    if (sheetWindow) sheetWindow.location.href = url;
    else window.location.href = url;
    window.setTimeout(() => { button.disabled = false; }, 800);
});
document.getElementById('openShelfMapBtn').addEventListener('click', () => {
    if (!currentProductionOrder || !currentPlanResult) return;
    const firstLocation = (currentPlanResult.data || []).flatMap(rowShelfLocations)[0]?.location_code || '';
    window.location.href = shelfMapUrl(firstLocation, currentProductionOrder);
});
document.getElementById('directPlanBtn').addEventListener('click', () => {
    const code = (document.getElementById('orderKeyword').value || document.getElementById('topKeyword').value || '').trim();
    if (!code) return alert('Nhập lệnh sản xuất cần soạn.');
    loadPlan(code);
});
document.getElementById('orderKeyword').addEventListener('keydown', event => {
    if (event.key === 'Enter') {
        event.preventDefault();
        document.getElementById('directPlanBtn').click();
    }
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
document.getElementById('weavingTicket').addEventListener('click', event => {
    const button = event.target.closest('[data-catalog-image-open]');
    if (!button) return;
    window.CatalogImagePaste?.open({
        catalogId: button.dataset.catalogId,
        itemCode: button.dataset.itemCode,
        itemName: button.dataset.itemName,
        unit: button.dataset.unit,
        imageUrl: button.dataset.imageUrl,
    });
});
document.addEventListener('catalog-image-ready', event => {
    const data = event.detail || {};
    if (!currentPlanResult || !data.id) return;
    const order = currentPlanResult.order || {};
    const sourceItem = (currentPlanResult.source_items || [])[0] || {};
    if (String(order.item_code || sourceItem.item_code || '').toUpperCase() !== String(data.item_code || '').toUpperCase()) return;
    order.catalog_id = Number(data.id);
    sourceItem.catalog_id = Number(data.id);
    renderWeavingTicket(currentPlanResult);
    if (window.lucide) lucide.createIcons();
});
document.addEventListener('catalog-image-uploaded', event => {
    const data = event.detail || {};
    if (!currentPlanResult || !data.id) return;
    const order = currentPlanResult.order || {};
    const sourceItem = (currentPlanResult.source_items || [])[0] || {};
    if (String(order.catalog_id || sourceItem.catalog_id || '') !== String(data.id)) return;
    order.image_url = data.image_url || '';
    sourceItem.image_url = data.image_url || '';
    renderWeavingTicket(currentPlanResult);
    if (window.lucide) lucide.createIcons();
});

const requestedOrder = new URLSearchParams(window.location.search).get('order');
weavingTemplateModal = new bootstrap.Modal(document.getElementById('weavingTemplateModal'));
if (requestedOrder) {
    document.getElementById('orderKeyword').value = requestedOrder;
    document.getElementById('topKeyword').value = requestedOrder;
}
loadItems();
loadOrders();
if (requestedOrder) loadPlan(requestedOrder);
if (window.lucide) lucide.createIcons();
</script>
</body>
</html>
