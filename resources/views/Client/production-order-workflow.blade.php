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
        .workflow-search { position: relative; display: flex; flex: 1; align-items: center; max-width: 560px; min-width: 240px; }
        .workflow-search input { width: 100%; height: 42px; padding: 0 16px 0 42px; border-radius: 999px; border-color: #bfd0e4; background: #f8fbff; }
        .workflow-search > i,
        .workflow-search > svg {
            position: absolute; z-index: 1; left: 15px; top: 50%; width: 18px; height: 18px;
            margin: 0; color: #2563eb; pointer-events: none; transform: translateY(-50%);
        }
        .workflow-heading { display: flex; justify-content: space-between; gap: 18px; align-items: flex-start; margin-bottom: 20px; }
        .workflow-heading h1 { margin: 0; font-size: 30px; font-weight: 850; letter-spacing: 0; }
        .workflow-heading p { margin: 4px 0 0; color: #55708c; font-size: 13px; }
        .kpi-grid { display: grid; grid-template-columns: repeat(6, minmax(140px, 1fr)); gap: 14px; margin-bottom: 18px; }
        .kpi-card { display: flex; gap: 12px; align-items: center; padding: 16px; border: 1px solid #dbe7f3; border-radius: 12px; background: #fff; box-shadow: 0 8px 24px rgba(15, 45, 80, .05); }
        .kpi-icon { display: grid; place-items: center; width: 42px; height: 42px; flex: 0 0 42px; border-radius: 10px; background: #edf5ff; color: #2563eb; }
        .kpi-label { color: #5c7895; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; }
        .kpi-value { font-size: 24px; line-height: 1.05; font-weight: 850; }
        .filter-panel, .workflow-panel { border: 1px solid #dbe7f3; border-radius: 14px; background: #fff; box-shadow: 0 8px 24px rgba(15, 45, 80, .05); }
        .filter-panel { display: grid; grid-template-columns: minmax(220px, 1fr) 220px 180px 140px; gap: 12px; padding: 16px; margin-bottom: 18px; }
        .filter-panel label { color: #39536f; font-size: 12px; font-weight: 750; margin-bottom: 6px; }
        .workflow-panel__header { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 16px 18px; border-bottom: 1px solid #e3edf7; }
        .workflow-panel__header h2 { margin: 0; font-size: 17px; font-weight: 850; }
        .table-wrap { overflow-x: auto; }
        .workflow-table { min-width: 1660px; margin: 0; }
        .workflow-table thead th { background: #06233f; color: #fff; font-size: 12px; font-weight: 800; white-space: nowrap; vertical-align: middle; }
        .workflow-table tbody td { vertical-align: middle; font-size: 13px; }
        .code { font-family: Consolas, "SFMono-Regular", monospace; font-weight: 800; color: #0b5fc7; }
        .muted { color: #66809a; font-size: 12px; }
        .item-stack { display: flex; flex-wrap: wrap; gap: 6px; max-width: 380px; }
        .item-chip { padding: 5px 7px; border: 1px solid #d7e5f4; border-radius: 8px; background: #f8fbff; color: #14314f; font-size: 12px; }
        .item-chip__edit { padding: 0 3px; border: 0; background: transparent; color: #2563eb; font-weight: 800; }
        .item-chip__source { display: block; margin-top: 2px; color: #64748b; font-size: 10px; }
        .item-chip__image { margin-left:4px; vertical-align:middle; }
        .standard-catalog-preview { display:grid; grid-template-columns:64px minmax(0,1fr); gap:12px; align-items:center; min-height:76px; margin-top:10px; padding:10px; border:1px solid #d7e5f4; border-radius:10px; background:#f8fbff; }
        .standard-catalog-preview[hidden] { display:none; }
        .standard-catalog-preview__image { display:grid; place-items:center; width:64px; height:64px; overflow:hidden; border:1px solid #c8d9ec; border-radius:8px; background:#fff; color:#7890aa; }
        .standard-catalog-preview__image img { width:100%; height:100%; object-fit:contain; }
        .standard-catalog-preview__code { color:#0b5fc7; font-family:Consolas,"SFMono-Regular",monospace; font-size:13px; font-weight:850; }
        .standard-catalog-preview__detail { margin-top:3px; color:#55708c; font-size:12px; overflow-wrap:anywhere; }
        .standard-catalog-create { margin-top:10px; padding:10px 12px; border:1px solid #f0c36a; border-radius:10px; background:#fff9e9; color:#704b00; }
        .standard-catalog-create[hidden] { display:none; }
        .standard-catalog-create .form-check-label { font-size:13px; font-weight:750; }
        .standard-catalog-create small { display:block; margin:3px 0 0 24px; color:#806229; }
        .status-pill { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 850; white-space: nowrap; }
        .status-pill::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
        .status-planned { background: #eef4fb; color: #45627f; }
        .status-received { background: #e8fff4; color: #087f5b; }
        .status-in_production { background: #fff7e6; color: #b36b00; }
        .status-production_done { background: #eaf7ff; color: #0b6db3; }
        .status-shipped_customer { background: #edf2ff; color: #2846a0; }
        .progress-track { height: 8px; width: 150px; border-radius: 999px; background: #e8eff7; overflow: hidden; }
        .progress-fill { height: 100%; border-radius: inherit; background: linear-gradient(90deg, #60a5fa, #2563eb); }
        .lifecycle { display:grid; grid-template-columns:repeat(6,minmax(70px,1fr)); min-width:500px; gap:0; }
        .lifecycle-stage { position:relative; min-width:0; padding:0 5px; text-align:center; }
        .lifecycle-stage:not(:last-child)::after { content:""; position:absolute; z-index:0; top:15px; left:calc(50% + 17px); right:calc(-50% + 17px); height:2px; background:#d8e3ef; }
        .lifecycle-stage.is-completed:not(:last-child)::after { background:#86d9b5; }
        .lifecycle-icon { position:relative; z-index:1; display:grid; place-items:center; width:32px; height:32px; margin:0 auto 5px; border:2px solid #d3deea; border-radius:50%; background:#fff; color:#8294a8; transition:transform .2s ease, box-shadow .2s ease; }
        .lifecycle-icon i { width:15px; height:15px; }
        .lifecycle-stage.is-completed .lifecycle-icon { border-color:#36b37e; background:#e7f9f1; color:#087f5b; }
        .lifecycle-stage.is-active .lifecycle-icon { border-color:#3b82f6; background:#eaf3ff; color:#1d4ed8; box-shadow:0 0 0 5px rgba(59,130,246,.13); animation:lifecyclePulse 1.8s ease-in-out infinite; }
        .lifecycle-label { overflow:hidden; color:#526a82; font-size:10px; font-weight:850; line-height:1.2; text-overflow:ellipsis; white-space:nowrap; }
        .lifecycle-stage.is-active .lifecycle-label { color:#1d4ed8; }
        .lifecycle-stage.is-completed .lifecycle-label { color:#087f5b; }
        .lifecycle-detail { margin-top:2px; overflow:hidden; color:#7a8da1; font-size:9px; line-height:1.2; text-overflow:ellipsis; white-space:nowrap; }
        .lifecycle-meta { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-top:8px; color:#607991; font-size:10px; }
        .operation-list { display:grid; gap:8px; }
        .operation-row { display:grid; grid-template-columns:36px minmax(150px,1fr) 150px; gap:10px; align-items:center; padding:10px 12px; border:1px solid #dbe7f3; border-radius:8px; }
        .operation-index { display:grid; place-items:center; width:28px; height:28px; border-radius:50%; background:#edf5ff; color:#1d4ed8; font-weight:850; }
        .warning-stack { display:flex; flex-wrap:wrap; gap:5px; margin-top:7px; }
        .warning-chip { display:inline-flex; align-items:center; gap:4px; padding:4px 7px; border:1px solid #f3c56c; border-radius:999px; background:#fff8e7; color:#8a5700; font-size:10px; font-weight:800; }
        .warning-chip.is-error { border-color:#f3a8ad; background:#fff0f1; color:#b4232f; }
        .lifecycle-detail-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; }
        .lifecycle-detail-stat { padding:12px; border:1px solid #dbe7f3; border-radius:10px; background:#f8fbff; }
        .lifecycle-detail-stat small { display:block; color:#66809a; }
        .lifecycle-detail-section { margin-top:18px; }
        .lifecycle-detail-section h3 { margin:0 0 8px; font-size:14px; font-weight:850; }
        .lifecycle-detail-table { margin-bottom:0; font-size:12px; }
        .lifecycle-detail-table th { white-space:nowrap; }
        .reconcile-summary { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin-bottom:14px; }
        .reconcile-summary > div { padding:12px; border:1px solid #dbe7f3; border-radius:10px; background:#f8fbff; }
        .reconcile-summary small { display:block; color:#66809a; }
        .reconcile-status { display:inline-flex; padding:4px 7px; border-radius:999px; font-size:10px; font-weight:850; }
        .reconcile-matchable { background:#e7f9f1; color:#087f5b; }
        .reconcile-ambiguous { background:#fff7e6; color:#9a5b00; }
        .reconcile-missing_order, .reconcile-missing_item, .reconcile-missing_variant { background:#fff0f1; color:#b4232f; }
        @keyframes lifecyclePulse { 50% { transform:scale(1.06); box-shadow:0 0 0 8px rgba(59,130,246,.06); } }
        @media (prefers-reduced-motion:reduce) { .lifecycle-stage.is-active .lifecycle-icon { animation:none; } }
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
            .workflow-topbar { flex-wrap: wrap; padding: 10px 18px; }
            .workflow-search { order: 3; flex-basis: 100%; max-width: none; }
            .workflow-page { padding: 18px; }
            .workflow-heading { flex-direction: column; }
            .kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .filter-panel { grid-template-columns: 1fr; }
            .order-group__summary { grid-template-columns: 1fr 24px; }
            .group-stat { display: none; }
            .lifecycle-detail-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .reconcile-summary { grid-template-columns:repeat(2,minmax(0,1fr)); }
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
                <button id="openLinkReconcile" type="button" class="btn btn-outline-danger"><i data-lucide="link-2"></i> Đối soát liên kết</button>
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
            <article class="kpi-card"><div class="kpi-icon text-danger"><i data-lucide="triangle-alert"></i></div><div><div class="kpi-label">Cần xử lý</div><div id="kpiWarnings" class="kpi-value">0</div><div id="kpiErrors" class="muted">0 lỗi nghiêm trọng</div></div></article>
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
                <div class="form-check form-switch mb-2">
                    <input id="exceptionsOnly" class="form-check-input" type="checkbox" role="switch">
                    <label class="form-check-label" for="exceptionsOnly">Chỉ lệnh cần xử lý</label>
                </div>
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

    <div class="modal fade" id="linkReconcileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div><h2 class="modal-title fs-5">Đối soát chứng từ với lệnh sản xuất</h2><div class="muted">Chỉ ghi database nội bộ khi một dòng khớp duy nhất theo lệnh, mã hàng và biến thể.</div></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div id="linkReconcileBody" class="modal-body"><div class="empty-row">Đang kiểm tra...</div></div>
                <div class="modal-footer justify-content-between">
                    <div class="form-check">
                        <input id="confirmLinkReconcile" class="form-check-input" type="checkbox">
                        <label class="form-check-label" for="confirmLinkReconcile">Tôi đã kiểm tra danh sách đề xuất</label>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
                        <button id="applyLinkReconcile" type="button" class="btn btn-primary" disabled>Áp dụng liên kết chắc chắn</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="lifecycleDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div><h2 id="lifecycleDetailTitle" class="modal-title fs-5">Chi tiết vòng đời</h2><div id="lifecycleDetailSubtitle" class="muted"></div></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div id="lifecycleDetailBody" class="modal-body"><div class="empty-row">Đang tải dữ liệu...</div></div>
            </div>
        </div>
    </div>

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
                        <div id="standardCatalogPreview" class="standard-catalog-preview" hidden></div>
                        <div id="standardCatalogCreate" class="standard-catalog-create" hidden>
                            <div class="form-check">
                                <input id="createStandardCatalog" class="form-check-input" type="checkbox">
                                <label id="createStandardCatalogLabel" class="form-check-label" for="createStandardCatalog"></label>
                            </div>
                            <small>Sao chép tên, ĐVT, size, màu và ảnh từ dòng lệnh hiện tại.</small>
                        </div>
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
        const exceptionsOnlyEl = document.getElementById('exceptionsOnly');
        const linkReconcileModal = new bootstrap.Modal(document.getElementById('linkReconcileModal'));
        const linkReconcileBodyEl = document.getElementById('linkReconcileBody');
        const confirmLinkReconcileEl = document.getElementById('confirmLinkReconcile');
        const applyLinkReconcileEl = document.getElementById('applyLinkReconcile');
        const lifecycleDetailModal = new bootstrap.Modal(document.getElementById('lifecycleDetailModal'));
        const lifecycleDetailTitleEl = document.getElementById('lifecycleDetailTitle');
        const lifecycleDetailSubtitleEl = document.getElementById('lifecycleDetailSubtitle');
        const lifecycleDetailBodyEl = document.getElementById('lifecycleDetailBody');
        let timer = null;
        let catalogTimer = null;
        const standardModal = new bootstrap.Modal(document.getElementById('standardItemModal'));
        const standardItemCodeEl = document.getElementById('standardItemCode');
        const standardCatalogIdEl = document.getElementById('standardCatalogId');
        const standardCatalogResultsEl = document.getElementById('standardCatalogResults');
        const standardCatalogPreviewEl = document.getElementById('standardCatalogPreview');
        const standardCatalogCreateEl = document.getElementById('standardCatalogCreate');
        const createStandardCatalogEl = document.getElementById('createStandardCatalog');
        const createStandardCatalogLabelEl = document.getElementById('createStandardCatalogLabel');
        const standardItemStatusEl = document.getElementById('standardItemStatus');
        let standardCatalogRows = [];
        let resetStandardRequested = false;
        let catalogRequestSequence = 0;
        let standardSourceItem = {};
        let workflowRows = [];
        let linkReconcileMatchable = 0;
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
            document.getElementById('kpiWarnings').textContent = num(summary.warning_count);
            document.getElementById('kpiErrors').textContent = `${num(summary.error_count)} lỗi nghiêm trọng`;
        }

        function chips(items) {
            if (!items.length) return '<span class="muted">Chưa có dòng hàng</span>';
            return `<div class="item-stack">${items.map(item => {
                const image = imageUrl(item.image_url || '');
                const sourceCode = item.source_item_code || item.item_code || '';
                const variantCode = item.variant_item_code || '';
                const imageAction = item.item_code ? `<button type="button" class="catalog-image-trigger item-chip__image" title="${image ? 'Xem hoặc thay ảnh danh mục' : 'Paste ảnh vào danh mục'}" data-catalog-image-open data-catalog-id="${esc(item.catalog_id || '')}" data-item-code="${esc(item.item_code || '')}" data-item-name="${esc(item.description || '')}" data-unit="${esc(item.unit || '')}" data-size="${esc(item.size || '')}" data-color="${esc(item.color || '')}" data-image-url="${esc(image)}">
                    ${image ? `<img loading="lazy" src="${esc(image)}" alt="${esc(item.item_code || 'Ảnh danh mục')}">` : '<i data-lucide="image-plus"></i> Ảnh'}
                </button>` : '';
                return `<span class="item-chip">
                ${variantCode ? `<span class="item-chip__source">Mã gốc: ${esc(sourceCode || '-')}</span><strong>${esc(variantCode)}</strong>` : `<strong>${esc(sourceCode || 'Mã trống')}</strong>`}
                <button type="button" class="item-chip__edit" title="Sửa mã chuẩn" data-edit-standard="${esc(item.id)}" data-production-order="${esc(item.production_order || '')}" data-source-code="${esc(item.source_item_code || '')}" data-standard-code="${esc(item.standard_item_code || '')}" data-standard-catalog-id="${esc(item.standard_catalog_id || '')}" data-item-name="${esc(item.description || '')}" data-unit="${esc(item.unit || '')}" data-size="${esc(item.size || '')}" data-color="${esc(item.color || '')}" data-image-url="${esc(image)}">&#9998;</button>
                ${imageAction}
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

        function lifecycleHtml(row) {
            const lifecycle = row.lifecycle || { percent:0, current_stage:'Chưa có dữ liệu', stages:[] };
            const icons = { order:'clipboard-list', bom:'network', material:'package-minus', production:'factory', receipt:'package-check', shipment:'truck' };
            const stages = (lifecycle.stages || []).map(stage => `
                <div class="lifecycle-stage is-${esc(stage.status || 'pending')}" title="${esc(stage.label)}: ${esc(stage.detail || '')}">
                    <div class="lifecycle-icon"><i data-lucide="${icons[stage.key] || 'circle'}"></i></div>
                    <div class="lifecycle-label">${esc(stage.label)}</div>
                    <div class="lifecycle-detail">${esc(stage.detail || '-')}</div>
                </div>`).join('');
            return `<div class="lifecycle">${stages}</div>
                <div class="lifecycle-meta"><span><strong>${esc(lifecycle.current_stage || '-')}</strong></span><span>${num(lifecycle.percent)}%</span></div>`;
        }

        function warningHtml(warnings) {
            if (!warnings?.length) return '';
            return `<div class="warning-stack">${warnings.slice(0, 3).map(warning =>
                `<span class="warning-chip ${warning.severity === 'error' ? 'is-error' : ''}" title="${esc(warning.message || '')}"><i data-lucide="${warning.severity === 'error' ? 'circle-alert' : 'triangle-alert'}"></i>${esc(warning.message || '')}</span>`
            ).join('')}</div>`;
        }

        function documentRows(rows, typeLabel) {
            if (!rows?.length) return `<tr><td colspan="6" class="text-center text-muted py-3">Chưa có ${esc(typeLabel)}.</td></tr>`;
            return rows.map(row => `<tr>
                <td class="code">${esc(row.code || '-')}</td><td>${esc(row.date || '-')}</td>
                <td>${esc(row.issue_type || row.source || '-')}</td><td class="text-end">${num(row.quantity)}</td>
                <td class="text-end">${num(row.line_count)}</td><td class="text-end ${Number(row.unlinked_lines) ? 'text-warning fw-bold' : ''}">${num(row.unlinked_lines)}</td>
            </tr>`).join('');
        }

        function renderLifecycleDetail(data) {
            const warnings = data.warnings || [];
            const materials = data.bom?.data || [];
            const operations = data.operations || [];
            lifecycleDetailTitleEl.textContent = `Vòng đời ${data.production_order || ''}`;
            lifecycleDetailSubtitleEl.textContent = [data.customer, data.purchase_order].filter(Boolean).join(' · ');
            lifecycleDetailBodyEl.innerHTML = `
                ${warnings.length ? `<div class="alert ${warnings.some(row => row.severity === 'error') ? 'alert-danger' : 'alert-warning'} mb-3"><strong>${num(warnings.length)} cảnh báo</strong><ul class="mb-0 mt-1">${warnings.map(row => `<li>${esc(row.message)}</li>`).join('')}</ul></div>` : '<div class="alert alert-success mb-3">Không phát hiện ngoại lệ trong dữ liệu hiện có.</div>'}
                <div class="lifecycle-detail-grid">
                    <div class="lifecycle-detail-stat"><small>Kế hoạch</small><strong>${num(data.planned_quantity)}</strong></div>
                    <div class="lifecycle-detail-stat"><small>Đã nhập thành phẩm</small><strong>${num(data.received_quantity)}</strong></div>
                    <div class="lifecycle-detail-stat"><small>Đã xuất khách</small><strong>${num(data.customer_issue_quantity)}</strong></div>
                    <div class="lifecycle-detail-stat"><small>Liên kết ID</small><strong>${num((data.link_integrity?.receipt?.linked || 0) + (data.link_integrity?.issue?.linked || 0))}/${num((data.link_integrity?.receipt?.total || 0) + (data.link_integrity?.issue?.total || 0))}</strong></div>
                </div>
                <section class="lifecycle-detail-section"><h3>BOM và vật tư</h3><div class="table-responsive"><table class="table table-sm lifecycle-detail-table"><thead><tr><th>Mã vật tư</th><th>Vai trò</th><th>ĐVT</th><th class="text-end">Cần</th><th class="text-end">Đã xuất</th><th class="text-end">Thiếu xuất</th><th class="text-end">Tồn khả dụng</th></tr></thead><tbody>${materials.length ? materials.map(row => `<tr><td class="code">${esc(row.material_code || '-')}</td><td>${esc(row.component_role || '-')}</td><td>${esc(row.unit || '-')}</td><td class="text-end">${num(row.required_quantity)}</td><td class="text-end">${num(row.issued_quantity)}</td><td class="text-end">${num(row.suggested_quantity)}</td><td class="text-end ${row.stock_status === 'short' ? 'text-danger fw-bold' : ''}">${num(row.available_quantity)}</td></tr>`).join('') : '<tr><td colspan="7" class="text-center text-muted py-3">Chưa có BOM.</td></tr>'}</tbody></table></div></section>
                <section class="lifecycle-detail-section"><h3>Công đoạn</h3><div class="operation-list">${operations.length ? operations.map((row, index) => `<div class="operation-row"><span class="operation-index">${index + 1}</span><div><strong>${esc(row.name || row.code)}</strong><div class="muted">Đạt ${num(row.good_quantity)} · Lỗi ${num(row.defect_quantity)} · ${num(row.document_count)} lượt ghi nhận</div></div><span class="status-pill status-${row.status === 'completed' ? 'production_done' : row.status === 'in_progress' ? 'in_production' : 'planned'}">${esc(row.status || 'pending')}</span></div>`).join('') : '<div class="empty-row">Chưa thiết lập tuyến công đoạn.</div>'}</div></section>
                <section class="lifecycle-detail-section"><h3>Phiếu nhập thành phẩm</h3><div class="table-responsive"><table class="table table-sm lifecycle-detail-table"><thead><tr><th>Số phiếu</th><th>Ngày</th><th>Nguồn</th><th class="text-end">Số lượng</th><th class="text-end">Dòng</th><th class="text-end">Chưa nối ID</th></tr></thead><tbody>${documentRows(data.receipts, 'phiếu nhập')}</tbody></table></div></section>
                <section class="lifecycle-detail-section"><h3>Phiếu xuất liên quan</h3><div class="table-responsive"><table class="table table-sm lifecycle-detail-table"><thead><tr><th>Số phiếu</th><th>Ngày</th><th>Loại</th><th class="text-end">Số lượng</th><th class="text-end">Dòng</th><th class="text-end">Chưa nối ID</th></tr></thead><tbody>${documentRows(data.issues, 'phiếu xuất')}</tbody></table></div></section>`;
            if (window.lucide) lucide.createIcons();
        }

        function openLifecycleDetail(orderId) {
            lifecycleDetailTitleEl.textContent = 'Chi tiết vòng đời';
            lifecycleDetailSubtitleEl.textContent = '';
            lifecycleDetailBodyEl.innerHTML = '<div class="empty-row">Đang tải dữ liệu...</div>';
            lifecycleDetailModal.show();
            fetch(`/api/lenh-san-xuat-trung-tam/${encodeURIComponent(orderId)}/vong-doi`, { headers: { Accept: 'application/json' } })
                .then(async response => {
                    const json = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(json.message || 'Không tải được chi tiết vòng đời.');
                    return json.data || {};
                })
                .then(renderLifecycleDetail)
                .catch(error => { lifecycleDetailBodyEl.innerHTML = `<div class="alert alert-danger">${esc(error.message)}</div>`; });
        }

        function reconcileStatusLabel(status) {
            return {
                matchable: 'Có thể liên kết',
                ambiguous: 'Nhiều biến thể',
                missing_order: 'Không có lệnh',
                missing_item: 'Sai mã hàng',
                missing_variant: 'Thiếu biến thể',
            }[status] || status;
        }

        function renderLinkReconcile(data) {
            const summary = data.summary || {};
            const rows = data.rows || [];
            linkReconcileMatchable = Number(summary.matchable || 0);
            confirmLinkReconcileEl.checked = false;
            applyLinkReconcileEl.disabled = true;
            linkReconcileBodyEl.innerHTML = `
                <div class="reconcile-summary">
                    <div><small>Dòng cần xem</small><strong>${num(summary.total)}</strong></div>
                    <div><small>Có thể liên kết</small><strong class="text-success">${num(summary.matchable)}</strong></div>
                    <div><small>Nhiều biến thể</small><strong class="text-warning">${num(summary.ambiguous)}</strong></div>
                    <div><small>Thiếu lệnh / mã / biến thể</small><strong class="text-danger">${num(Number(summary.missing_order || 0) + Number(summary.missing_item || 0) + Number(summary.missing_variant || 0))}</strong></div>
                </div>
                ${summary.truncated ? `<div class="alert alert-warning">Danh sách đang giới hạn ${num(summary.limit)} dòng. Hãy xử lý và tải lại theo từng đợt.</div>` : ''}
                <div class="table-responsive"><table class="table table-sm lifecycle-detail-table align-middle"><thead><tr><th>Loại</th><th>Phiếu / ngày</th><th>Lệnh</th><th>Mã nội bộ</th><th>Size / màu</th><th class="text-end">SL</th><th>Kết quả</th></tr></thead><tbody>
                    ${rows.length ? rows.map(row => `<tr>
                        <td>${row.line_type === 'receipt' ? 'Nhập' : 'Xuất'}</td>
                        <td><span class="code">${esc(row.document_code || '-')}</span><div class="muted">${esc(row.document_date || '-')}</div></td>
                        <td><span class="code">${esc(row.production_order || '-')}</span>${row.current_order_code ? `<div class="muted">ID hiện tại: ${esc(row.current_order_code)}</div>` : ''}</td>
                        <td><strong>${esc(row.internal_item_code || '-')}</strong>${row.source_item_code ? `<div class="muted">Mã gốc: ${esc(row.source_item_code)}</div>` : ''}${row.suggested_item_code ? `<div class="muted">Biến thể: ${esc(row.suggested_item_code)}</div>` : ''}</td>
                        <td>${esc(row.size || '-')}<div class="muted">${esc(row.color || '-')}</div></td>
                        <td class="text-end">${num(row.quantity)} ${esc(row.unit || '')}</td>
                        <td><span class="reconcile-status reconcile-${esc(row.status)}">${esc(reconcileStatusLabel(row.status))}</span><div class="muted mt-1">${esc(row.reason || '')}</div></td>
                    </tr>`).join('') : '<tr><td colspan="7" class="text-center text-success py-4">Không có liên kết bất thường.</td></tr>'}
                </tbody></table></div>`;
        }

        function loadLinkReconcile() {
            linkReconcileBodyEl.innerHTML = '<div class="empty-row">Đang kiểm tra liên kết...</div>';
            confirmLinkReconcileEl.checked = false;
            applyLinkReconcileEl.disabled = true;
            linkReconcileModal.show();
            return fetch('/api/lenh-san-xuat-trung-tam/doi-soat-lien-ket?limit=500', { headers: { Accept: 'application/json' } })
                .then(async response => {
                    const json = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(json.message || 'Không thể đối soát liên kết.');
                    return json.data || {};
                })
                .then(renderLinkReconcile)
                .catch(error => { linkReconcileBodyEl.innerHTML = `<div class="alert alert-danger">${esc(error.message)}</div>`; });
        }

        function applyLinkReconcile() {
            if (!confirmLinkReconcileEl.checked || linkReconcileMatchable < 1) return;
            applyLinkReconcileEl.disabled = true;
            applyLinkReconcileEl.textContent = 'Đang cập nhật...';
            fetch('/api/lenh-san-xuat-trung-tam/doi-soat-lien-ket', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ confirm: true, limit: 500 }),
            }).then(async response => {
                const json = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(json.message || 'Không cập nhật được liên kết.');
                return json.data || {};
            }).then(data => {
                renderLinkReconcile(data);
                load();
            }).catch(error => {
                linkReconcileBodyEl.insertAdjacentHTML('afterbegin', `<div class="alert alert-danger">${esc(error.message)}</div>`);
            }).finally(() => {
                applyLinkReconcileEl.textContent = 'Áp dụng liên kết chắc chắn';
                applyLinkReconcileEl.disabled = true;
            });
        }

        function openStandardItemEditor(button) {
            resetStandardRequested = false;
            standardSourceItem = {
                item_name: button.dataset.itemName || '',
                unit: button.dataset.unit || '',
                size: button.dataset.size || '',
                color: button.dataset.color || '',
                image_url: button.dataset.imageUrl || '',
                catalog_id: button.dataset.standardCatalogId || '',
            };
            document.getElementById('standardLineId').value = button.dataset.editStandard || '';
            document.getElementById('standardProductionOrder').value = button.dataset.productionOrder || '';
            document.getElementById('sourceItemCode').value = button.dataset.sourceCode || '';
            standardCatalogIdEl.value = button.dataset.standardCatalogId || '';
            standardItemCodeEl.value = button.dataset.standardCode || button.dataset.sourceCode || '';
            standardCatalogResultsEl.innerHTML = '';
            standardCatalogRows = [];
            renderStandardCatalogPreview(null);
            renderStandardCatalogCreate(false);
            standardItemStatusEl.textContent = '';
            standardModal.show();
            loadCatalogOptions();
            setTimeout(() => {
                standardItemCodeEl.focus();
                standardItemCodeEl.select();
            }, 180);
        }

        function renderStandardCatalogPreview(row) {
            if (!row) {
                standardCatalogPreviewEl.hidden = true;
                standardCatalogPreviewEl.innerHTML = '';
                return;
            }
            const image = imageUrl(row.image_url || '');
            const imageHtml = image
                ? `<img src="${esc(image)}" alt="${esc(row.item_code || 'Ảnh danh mục')}">`
                : '<i data-lucide="image-off"></i>';
            const detail = [
                row.item_name,
                row.color ? `Màu ${row.color}` : '',
                row.size ? `Size ${row.size}` : '',
                row.source_row ? `Dòng Sheet ${num(row.source_row)}` : '',
            ].filter(Boolean).join(' · ');
            standardCatalogPreviewEl.innerHTML = `
                <div class="standard-catalog-preview__image">${imageHtml}</div>
                <div>
                    <div class="standard-catalog-preview__code">${esc(row.item_code || '')}</div>
                    <div class="standard-catalog-preview__detail">${esc(detail || 'Chưa có mô tả')}</div>
                    <div class="standard-catalog-preview__detail">${image ? 'Đã có ảnh danh mục' : 'Chưa có ảnh danh mục'}</div>
                </div>`;
            standardCatalogPreviewEl.hidden = false;
            if (window.lucide) lucide.createIcons();
        }

        function renderStandardCatalogCreate(visible) {
            const code = standardItemCodeEl.value.trim();
            standardCatalogCreateEl.hidden = !visible;
            createStandardCatalogEl.checked = Boolean(visible);
            createStandardCatalogLabelEl.textContent = visible
                ? `Tạo mã ${code} ở dòng cuối Google Sheet DANH MỤC`
                : '';
        }

        function selectStandardCatalog(row) {
            if (!row) return false;
            standardCatalogIdEl.value = String(row.id || '');
            standardItemCodeEl.value = row.item_code || '';
            standardCatalogResultsEl.value = String(row.id || '');
            renderStandardCatalogPreview(row);
            renderStandardCatalogCreate(false);
            standardItemStatusEl.textContent = '';
            return Boolean(row.id);
        }

        function exactCatalogMatches() {
            const code = standardItemCodeEl.value.trim().toLocaleUpperCase('vi-VN');
            if (!code) return [];
            return standardCatalogRows.filter(row => String(row.item_code || '').trim().toLocaleUpperCase('vi-VN') === code);
        }

        function loadCatalogOptions() {
            const keyword = standardItemCodeEl.value.trim();
            clearTimeout(catalogTimer);
            if (keyword.length < 2) {
                catalogRequestSequence++;
                standardCatalogRows = [];
                standardCatalogResultsEl.innerHTML = '';
                renderStandardCatalogPreview(null);
                renderStandardCatalogCreate(false);
                return;
            }
            catalogTimer = setTimeout(() => {
                const requestSequence = ++catalogRequestSequence;
                fetch(`/api/danh-muc-noi-bo?keyword=${encodeURIComponent(keyword)}&limit=50`)
                    .then(response => response.json())
                    .then(result => {
                        if (requestSequence !== catalogRequestSequence) return;
                        const selectedId = String(standardCatalogIdEl.value || '');
                        standardCatalogRows = (result.data || []).filter(item => item.item_code);
                        standardCatalogResultsEl.innerHTML = '<option value="">-- Chọn đúng dòng danh mục --</option>' + standardCatalogRows.map(item => {
                            const detail = [item.item_code, item.item_name, item.color, item.size, item.image_url ? 'Có ảnh' : 'Chưa ảnh', item.source_row ? `Dòng ${item.source_row}` : ''].filter(Boolean).join(' - ');
                            return `<option value="${esc(item.id)}" ${String(item.id) === selectedId ? 'selected' : ''}>${esc(detail)}</option>`;
                        }).join('');
                        const selectedRow = standardCatalogRows.find(item => String(item.id) === selectedId);
                        if (selectedRow) {
                            selectStandardCatalog(selectedRow);
                            return;
                        }
                        const exactMatches = exactCatalogMatches();
                        if (exactMatches.length === 1) {
                            selectStandardCatalog(exactMatches[0]);
                            return;
                        }
                        standardCatalogIdEl.value = '';
                        renderStandardCatalogPreview(null);
                        renderStandardCatalogCreate(exactMatches.length === 0);
                        if (exactMatches.length > 1) {
                            standardItemStatusEl.className = 'small mt-2 text-warning';
                            standardItemStatusEl.textContent = `Có ${exactMatches.length} dòng cùng mã. Hãy chọn đúng dòng theo màu hoặc ảnh.`;
                        }
                    })
                    .catch(() => {
                        standardItemStatusEl.className = 'small mt-2 text-danger';
                        standardItemStatusEl.textContent = 'Không tải được Danh mục nội bộ.';
                    });
            }, 120);
        }

        function saveStandardItem(event) {
            event.preventDefault();
            const lineId = document.getElementById('standardLineId').value;
            if (!resetStandardRequested && !standardCatalogIdEl.value) {
                const exactMatches = exactCatalogMatches();
                if (exactMatches.length === 1) selectStandardCatalog(exactMatches[0]);
            }
            const createNewCatalog = !resetStandardRequested
                && !standardCatalogIdEl.value
                && !standardCatalogCreateEl.hidden
                && createStandardCatalogEl.checked;
            if (!resetStandardRequested && !standardCatalogIdEl.value && !createNewCatalog) {
                standardItemStatusEl.className = 'small mt-2 text-danger';
                standardItemStatusEl.textContent = exactCatalogMatches().length > 1
                    ? 'Mã đang bị trùng. Hãy chọn đúng một dòng theo tên, màu hoặc ảnh.'
                    : 'Mã chưa có trong Danh mục. Hãy xác nhận append dòng mới trước khi lưu.';
                return;
            }
            standardItemStatusEl.className = 'small mt-2 text-primary';
            standardItemStatusEl.textContent = createNewCatalog ? 'Đang append vào Google Sheet...' : 'Đang lưu...';
            const requestUrl = createNewCatalog
                ? '/api/danh-muc-noi-bo/tao-tu-lenh'
                : `/api/lenh-san-xuat-trung-tam/dong/${encodeURIComponent(lineId)}`;
            const requestBody = createNewCatalog
                ? {
                    item_code: standardItemCodeEl.value.trim(),
                    source_item_code: document.getElementById('sourceItemCode').value.trim(),
                    production_order_line_id: Number(lineId),
                    source_catalog_id: standardSourceItem.catalog_id ? Number(standardSourceItem.catalog_id) : null,
                    item_name: standardSourceItem.item_name || '',
                    unit: standardSourceItem.unit || '',
                    size: standardSourceItem.size || '',
                    color: standardSourceItem.color || '',
                    image_url: standardSourceItem.image_url || '',
                }
                : {
                    standard_catalog_id: standardCatalogIdEl.value ? Number(standardCatalogIdEl.value) : null,
                    standard_item_code: standardItemCodeEl.value.trim() || null,
                    reset: resetStandardRequested,
                };
            fetch(requestUrl, {
                method: createNewCatalog ? 'POST' : 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(requestBody),
            })
                .then(async response => {
                    const result = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(result.message || 'Không lưu được mã chuẩn.');
                    return result;
                })
                .then(() => {
                    resetStandardRequested = false;
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
            const bomItemCode = row.items?.find(item => item.item_code)?.item_code || '';
            return `<tr>
                <td><div class="code">${esc(row.production_order)}</div><div class="muted">${esc(row.promised_date || '-')}</div></td>
                <td><strong>${esc(row.customer || '-')}</strong><div class="muted">${esc(row.purchase_order || '-')}</div></td>
                <td>${chips(row.items || [])}<div class="muted mt-1">${num(row.line_count)} dòng</div></td>
                <td class="text-end fw-bold">${num(row.planned_quantity)}</td>
                <td class="text-end">${num(row.received_quantity)}<div class="muted">${num(row.receipt_document_count)} phiếu</div></td>
                <td class="text-end">${num(row.production_issue_quantity)}<div class="muted">${num(row.production_document_count)} phiếu</div></td>
                <td class="text-end">${num(row.customer_issue_quantity)}<div class="muted">${num(row.customer_document_count)} phiếu</div></td>
                <td>${lifecycleHtml(row)}</td>
                <td><span class="status-pill ${status[1]}">${status[0]}</span>${warningHtml(row.warnings)}</td>
                <td>
                    <div class="muted">Nhập</div>${codeList(row.receipt_codes)}
                    <div class="muted mt-1">Xuất SX</div>${codeList(row.production_issue_codes)}
                    <div class="muted mt-1">Xuất khách</div>${codeList(row.customer_issue_codes)}
                </td>
                <td>
                    <div class="action-row">
                        ${row.canonical_order_id ? `<button type="button" class="btn btn-sm btn-outline-dark" data-lifecycle-detail="${esc(row.canonical_order_id)}">Chi tiết</button>` : ''}
                        ${bomItemCode ? `<a class="btn btn-sm btn-outline-secondary" href="/client/dinh-muc-san-xuat?item_code=${encodeURIComponent(bomItemCode)}">BOM</a>` : ''}
                        <a class="btn btn-sm btn-outline-primary" href="/client/nhap-thanh-pham-nhanh?production_order=${encodeURIComponent(row.production_order)}">Nhập</a>
                        <a class="btn btn-sm btn-outline-primary" href="/client/xuat-vat-tu-noi-bo?production_order=${encodeURIComponent(row.production_order)}">Xuất</a>
                        <a class="btn btn-sm btn-outline-secondary" href="/client/theo-doi-san-xuat?keyword=${encodeURIComponent(row.production_order)}">Theo dõi</a>
                        <a class="btn btn-sm btn-outline-primary" href="/client/ghi-nhan-san-xuat?production_order=${encodeURIComponent(row.production_order)}">Ghi nhận SX</a>
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
            workflowRows = data;
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
            if (exceptionsOnlyEl.checked) params.set('exceptions', '1');
            groupsEl.innerHTML = '<div class="empty-row">Đang tải dữ liệu...</div>';
            return fetch('/api/lenh-san-xuat-trung-tam?' + params.toString(), { headers: { Accept: 'application/json' } })
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
        exceptionsOnlyEl.addEventListener('change', load);
        document.getElementById('openLinkReconcile').addEventListener('click', loadLinkReconcile);
        confirmLinkReconcileEl.addEventListener('change', () => {
            applyLinkReconcileEl.disabled = !confirmLinkReconcileEl.checked || linkReconcileMatchable < 1;
        });
        applyLinkReconcileEl.addEventListener('click', applyLinkReconcile);
        document.getElementById('clearFilter').addEventListener('click', () => {
            keywordEl.value = '';
            topKeywordEl.value = '';
            statusEl.value = '';
            exceptionsOnlyEl.checked = false;
            load();
        });
        groupsEl.addEventListener('click', event => {
            const lifecycleButton = event.target.closest('[data-lifecycle-detail]');
            if (lifecycleButton) {
                openLifecycleDetail(lifecycleButton.dataset.lifecycleDetail);
                return;
            }
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
        standardItemCodeEl.addEventListener('input', () => {
            resetStandardRequested = false;
            standardCatalogIdEl.value = '';
            standardCatalogResultsEl.value = '';
            renderStandardCatalogPreview(null);
            renderStandardCatalogCreate(false);
            loadCatalogOptions();
        });
        standardCatalogResultsEl.addEventListener('change', () => {
            const row = standardCatalogRows.find(item => String(item.id) === String(standardCatalogResultsEl.value || ''));
            if (row) {
                resetStandardRequested = false;
                selectStandardCatalog(row);
            } else {
                standardCatalogIdEl.value = '';
                renderStandardCatalogPreview(null);
                renderStandardCatalogCreate(false);
            }
        });
        document.getElementById('standardItemForm').addEventListener('submit', saveStandardItem);
        document.getElementById('resetStandardItem').addEventListener('click', () => {
            resetStandardRequested = true;
            standardCatalogIdEl.value = '';
            standardItemCodeEl.value = '';
            document.getElementById('standardItemForm').requestSubmit();
        });

        if (window.lucide) lucide.createIcons();
        load();
    </script>
</body>
</html>
