<!doctype html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Tạo lệnh dệt | WMS May Mặc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/warehouse-wms.css') }}?v={{ filemtime(public_path('css/warehouse-wms.css')) }}" rel="stylesheet">
    <style>
        :root { --designer-blue:#2563eb; --designer-soft:#eff6ff; --designer-border:#cbdcf4; --designer-text:#102a4c; }
        html,body { max-width:100%; overflow-x:hidden; }
        .designer-create { max-width:1600px; min-width:0; margin:0 auto; }
        .designer-find { position:relative; z-index:1040; padding:16px; overflow:visible; border:1px solid var(--designer-border); background:#fff; border-radius:8px; isolation:isolate; }
        .designer-find:focus-within { z-index:1060; }
        .designer-find [hidden] { display:none !important; }
        .designer-find-row { display:grid; grid-template-columns:minmax(280px,1fr) auto; gap:10px; }
        .designer-find-input { position:relative; z-index:1; min-width:0; overflow:visible; }
        .designer-find-input input,.designer-form-panel,.designer-preview,.designer-field { width:100%; min-width:0; }
        .designer-find-input > svg { position:absolute; left:13px; top:50%; width:18px; transform:translateY(-50%); color:#4f77a8; pointer-events:none; }
        .designer-find-input input { min-height:46px; padding-left:42px; font-weight:750; border-color:#9fbae0; }
        .designer-suggestions { position:fixed; z-index:2140; top:0; left:0; width:320px; max-height:420px; overflow-y:auto; overflow-x:hidden; overscroll-behavior:contain; border:1px solid #9fbce3; border-radius:8px; background:#fff; box-shadow:0 22px 50px rgba(18,55,100,.24); }
        .designer-suggestion { width:100%; display:grid; grid-template-columns:minmax(150px,.7fr) minmax(220px,1.2fr) auto; gap:12px; align-items:center; padding:11px 13px; border:0; border-bottom:1px solid #e4edf9; background:#fff; text-align:left; color:var(--designer-text); }
        .designer-suggestion:hover,.designer-suggestion:focus { background:#eff6ff; outline:0; }
        .designer-suggestion:last-child { border-bottom:0; }
        .designer-suggestion strong { color:#155fc0; }
        .designer-workspace { display:block; margin-top:12px; }
        .designer-workspace[hidden],.designer-actions[hidden] { display:none !important; }
        [data-wizard-step][hidden] { display:none !important; }
        .designer-wizard { position:relative; display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); width:min(980px,100%); margin:0 auto 12px; padding:13px 16px; border:1px solid var(--designer-border); border-radius:8px; background:#fff; overflow:hidden; }
        .designer-wizard__track { position:absolute; top:31px; right:17%; left:17%; height:4px; border-radius:999px; background:#dfe9f6; overflow:hidden; }
        .designer-wizard__track span { display:block; width:0; height:100%; border-radius:inherit; background:#3b82f6; transition:width .24s ease; }
        .designer-wizard__step { position:relative; z-index:1; display:grid; justify-items:center; gap:2px; padding:0 8px; border:0; background:transparent; color:#6a7f98; cursor:pointer; }
        .designer-wizard__step > span { display:grid; place-items:center; width:36px; height:36px; margin-bottom:3px; border:3px solid #fff; border-radius:50%; background:#e7eef8; color:#58708d; font-weight:900; box-shadow:0 0 0 1px #c9d8eb; transition:background-color .2s ease,color .2s ease,box-shadow .2s ease; }
        .designer-wizard__step b { font-size:12px; }
        .designer-wizard__step small { font-size:10px; }
        .designer-wizard__step.is-active,.designer-wizard__step.is-done { color:#174f91; }
        .designer-wizard__step.is-active > span { background:#2563eb; color:#fff; box-shadow:0 0 0 3px #dbeafe; }
        .designer-wizard__step.is-done > span { background:#dff6ed; color:#087a55; box-shadow:0 0 0 1px #a9dfca; }
        .designer-form-panel,.designer-preview { width:min(1200px,100%); margin:0 auto; border:1px solid var(--designer-border); border-radius:8px; background:#fff; overflow:hidden; }
        .designer-form-panel[hidden] { display:none !important; }
        .designer-section { border-bottom:1px solid #e1eaf6; }
        .designer-section:last-child { border-bottom:0; }
        .designer-section-title { display:flex; align-items:center; justify-content:space-between; gap:10px; width:100%; padding:13px 16px; border:0; background:#f8fbff; color:#17375e; font-size:13px; font-weight:850; text-align:left; }
        .designer-section-title svg { width:17px; }
        .designer-section-body { padding:15px 16px; }
        .designer-field-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; }
        .designer-field-grid .span-2 { grid-column:span 2; }
        .designer-field-grid .span-3 { grid-column:span 3; }
        .designer-field-grid .span-4 { grid-column:1 / -1; }
        .designer-field label { display:block; margin-bottom:5px; color:#486681; font-size:11px; font-weight:800; }
        .designer-field input { min-height:39px; }
        .designer-field input[readonly] { background:#f3f7fc; color:#49657f; }
        .designer-formula-box { grid-column:1 / -1; margin-top:2px; padding:12px; border:1px solid #c9dcf5; border-radius:8px; background:#f7fbff; }
        .designer-formula-box summary { display:flex; align-items:center; gap:8px; color:#244f7e; font-size:12px; font-weight:850; cursor:pointer; list-style:none; }
        .designer-formula-box summary::-webkit-details-marker { display:none; }
        .designer-formula-box summary svg { width:16px; height:16px; }
        .designer-formula-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin-top:12px; }
        .designer-bom-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px; }
        .designer-bom-title { display:flex; align-items:center; gap:10px; min-width:0; }
        .designer-bom-summary { padding:4px 8px; border:1px solid #c9dcf5; border-radius:999px; background:#f4f8ff; color:#315f95; font-size:11px; font-weight:800; white-space:nowrap; }
        .designer-bom-editor { overflow-x:auto; border:1px solid #cbdcf4; border-radius:8px; background:#fff; scrollbar-color:#9bb9df #edf4fc; scrollbar-width:thin; }
        .designer-bom-columns,.designer-bom-row { display:grid; grid-template-columns:34px 64px 112px 66px minmax(140px,1fr) 82px 82px 34px; min-width:700px; }
        .designer-bom-columns { position:sticky; top:0; z-index:3; background:#eaf3ff; color:#284c75; border-bottom:1px solid #c2d5ed; }
        .designer-bom-columns > span { padding:9px 8px; border-right:1px solid #d2e0f2; font-size:10px; font-weight:900; line-height:1.2; text-transform:uppercase; }
        .designer-bom-columns > span:last-child { border-right:0; }
        .designer-bom-list { min-width:700px; }
        .designer-bom-row { align-items:center; border-bottom:1px solid #e1eaf6; background:#fff; transition:background-color .16s ease; }
        .designer-bom-row:last-child { border-bottom:0; }
        .designer-bom-row:focus-within { background:#f4f8ff; box-shadow:inset 3px 0 0 #3b82f6; }
        .designer-bom-cell { min-width:0; padding:7px 5px; }
        .designer-bom-cell .form-control { min-height:38px; padding:7px 8px; border-color:#b8cce7; font-size:12px; }
        .designer-bom-cell .form-control:focus { border-color:#4f8fe8; box-shadow:0 0 0 2px rgba(59,130,246,.12); }
        .designer-bom-index { justify-self:center; display:grid; place-items:center; width:25px; height:25px; border-radius:6px; background:#e7f1ff; color:#1764c0; font-size:11px; font-weight:900; }
        .designer-stock { margin-top:12px; border:1px solid #cbdcf4; border-radius:8px; overflow:hidden; background:#fff; }
        .designer-stock.is-short { border-color:#f3b5b5; background:#fffafa; }
        .designer-stock__head { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:10px 12px; background:#f3f8ff; border-bottom:1px solid #dce8f7; }
        .designer-stock.is-short .designer-stock__head { background:#fff0f0; border-color:#f7cccc; }
        .designer-stock__head strong { display:flex; align-items:center; gap:7px; font-size:12px; }
        .designer-stock__head svg { width:16px; height:16px; }
        .designer-stock__state { color:#16794b; font-size:11px; font-weight:850; }
        .designer-stock.is-short .designer-stock__state { color:#c0392b; }
        .designer-stock__rows { display:grid; }
        .designer-stock__row { display:grid; grid-template-columns:minmax(130px,1.4fr) repeat(3,minmax(72px,.6fr)) minmax(80px,.7fr); gap:8px; align-items:center; padding:9px 12px; border-top:1px solid #e7eef8; font-size:11px; }
        .designer-stock__row:first-child { border-top:0; }
        .designer-stock__row b { color:#163e68; }
        .designer-stock__row .is-short { color:#c62828; font-weight:850; }
        .designer-stock__empty { padding:12px; color:#607892; font-size:11px; }
        .designer-icon-btn { display:grid; place-items:center; width:32px; height:34px; margin:auto; border:1px solid #f1b6bd; border-radius:6px; background:#fff; color:#c93646; }
        .designer-icon-btn svg { width:16px; }
        .designer-color-field { position:relative; }
        .designer-color-field .form-control { padding-left:39px; }
        .designer-color-swatch { position:absolute; z-index:2; left:7px; top:50%; width:24px; height:24px; transform:translateY(-50%); border:1px solid #8fa5bf; border-radius:5px; background-color:var(--swatch-color,#fff); box-shadow:inset 0 0 0 1px rgba(255,255,255,.7); }
        .designer-color-swatch.is-empty { background:repeating-linear-gradient(135deg,#eef3f9 0,#eef3f9 5px,#cbd8e8 5px,#cbd8e8 7px); }
        .designer-preview-swatch { display:inline-block; width:18px; height:18px; margin-right:5px; border:1px solid #8295aa; border-radius:4px; background-color:var(--swatch-color,#fff); vertical-align:middle; }
        .designer-preview-swatch.is-empty { background:repeating-linear-gradient(135deg,#eef3f9 0,#eef3f9 4px,#cbd8e8 4px,#cbd8e8 6px); }
        .designer-preview { position:static; }
        .designer-preview-head { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:13px 15px; border-bottom:1px solid #dbe7f5; background:#f6faff; }
        .designer-preview-head h2 { margin:0; color:#17375e; font-size:14px; font-weight:900; }
        .designer-completeness { padding:4px 8px; border-radius:999px; background:#fff1d6; color:#915600; font-size:10px; font-weight:900; }
        .designer-completeness.is-ready { background:#ddf8ed; color:#087a55; }
        .designer-review-stock { margin:12px 12px 0; padding:10px 12px; border:1px solid #cde3d8; border-radius:7px; background:#effaf4; color:#16744b; font-size:11px; font-weight:800; }
        .designer-review-stock.is-short { border-color:#f0b9b9; background:#fff0f0; color:#b42335; }
        .designer-sheet { margin:12px; border:1px solid #8194ad; color:#111827; font-family:Arial,sans-serif; font-size:10px; }
        .designer-sheet-title { padding:7px; border-bottom:1px solid #8194ad; text-align:center; font-size:15px; font-weight:900; }
        .designer-sheet-meta { display:grid; grid-template-columns:1fr 1fr; }
        .designer-sheet-cell { display:flex; justify-content:space-between; gap:8px; min-height:30px; padding:6px 8px; border-right:1px solid #8194ad; border-bottom:1px solid #8194ad; }
        .designer-sheet-cell:nth-child(2n) { border-right:0; }
        .designer-sheet-cell b { color:#17375e; }
        .designer-sheet-main { display:grid; grid-template-columns:.78fr 1.22fr; }
        .designer-sheet-ops { padding:7px; border-right:1px solid #8194ad; }
        .designer-sheet-op { display:flex; justify-content:space-between; gap:8px; padding:3px 0; border-bottom:1px dotted #c1ccd9; }
        .designer-sheet table { width:100%; border-collapse:collapse; }
        .designer-sheet th,.designer-sheet td { padding:4px; border:1px solid #9aa9bb; vertical-align:top; }
        .designer-sheet th { background:#edf4fc; font-size:9px; }
        .designer-sheet-image { display:grid; place-items:center; min-height:150px; padding:10px; border-top:1px solid #8194ad; }
        .designer-sheet-image img { max-width:100%; max-height:145px; object-fit:contain; }
        .designer-sheet-empty { color:#718096; text-align:center; }
        .designer-sheet-image .catalog-image-trigger { width:100%; min-height:130px; justify-content:center; flex-direction:column; border:0; background:transparent; }
        .designer-sheet-image .catalog-image-trigger img { width:auto; height:auto; max-width:100%; max-height:145px; }
        .designer-actions { position:sticky; bottom:0; z-index:20; display:flex; justify-content:flex-end; gap:8px; width:min(1200px,100%); margin:14px auto 0; padding:11px; border:1px solid #c9daef; border-radius:8px; background:rgba(255,255,255,.96); box-shadow:0 -6px 22px rgba(31,73,125,.08); backdrop-filter:blur(8px); }
        .designer-wizard-nav { display:grid; grid-template-columns:auto 1fr auto; align-items:center; gap:10px; width:min(1200px,100%); margin:12px auto 0; padding:10px 12px; border:1px solid #c9daef; border-radius:8px; background:#fff; }
        .designer-wizard-nav[hidden] { display:none !important; }
        .designer-wizard-nav > span { color:#5a718d; font-size:11px; font-weight:800; text-align:center; }
        .designer-toast { position:fixed; right:18px; bottom:18px; z-index:1080; max-width:420px; padding:12px 15px; border-radius:8px; background:#17375e; color:#fff; box-shadow:0 16px 40px rgba(15,42,76,.25); opacity:0; transform:translateY(12px); pointer-events:none; transition:opacity .2s ease,transform .2s ease; }
        .designer-toast.is-visible { opacity:1; transform:translateY(0); }
        .designer-toast.is-error { background:#b42335; }
        .designer-loader { position:fixed; inset:0; z-index:2100; display:grid; place-items:center; padding:20px; background:rgba(226,239,255,.7); backdrop-filter:blur(3px); }
        .designer-loader[hidden] { display:none; }
        .designer-loader__panel { display:flex; align-items:center; gap:13px; min-width:min(340px,calc(100vw - 32px)); padding:15px 17px; border:1px solid #a9c8ef; border-radius:8px; background:#fff; color:#17375e; box-shadow:0 18px 50px rgba(37,99,235,.16); }
        .designer-loader__panel .spinner-border { width:25px; height:25px; border-width:3px; color:#2563eb; flex:0 0 auto; }
        .designer-loader__panel strong { display:block; font-size:13px; }
        .designer-loader__panel small { display:block; margin-top:2px; color:#647b96; font-size:11px; }
        body.is-designer-loading { overflow:hidden; }
        @media (max-width:1250px) { .designer-preview { position:static; } }
        @media (max-width:900px) { .designer-field-grid,.designer-formula-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media (max-width:620px) {
            .designer-create { width:100% !important; padding:80px 12px 120px !important; }
            .designer-create > *,.designer-create .wms-heading > div { min-width:0; max-width:100%; }
            .designer-create .wms-heading { padding-left:46px; }
            .designer-create .wms-heading h1 { font-size:25px; }
            .designer-create .wms-heading p { overflow-wrap:anywhere; }
            .designer-find { max-width:100%; overflow:visible; }
            .designer-form-panel,.designer-preview { max-width:100%; overflow:hidden; }
            .designer-find-row,.designer-field-grid,.designer-formula-grid { grid-template-columns:minmax(0,1fr); }
            .designer-field-grid .span-2,.designer-field-grid .span-3,.designer-field-grid .span-4 { grid-column:auto; }
            .designer-suggestion { grid-template-columns:minmax(0,1fr); gap:3px; }
            .designer-bom-head { align-items:flex-start; }
            .designer-bom-title { align-items:flex-start; flex-direction:column; gap:4px; }
            .designer-wizard { padding:11px 6px; }
            .designer-wizard__track { top:29px; }
            .designer-wizard__step { padding:0 2px; }
            .designer-wizard__step > span { width:34px; height:34px; }
            .designer-wizard__step small { display:none; }
            .designer-stock__rows { overflow-x:auto; }
            .designer-stock__row { grid-template-columns:minmax(130px,1fr) repeat(3,minmax(72px,.6fr)); min-width:520px; }
            .designer-stock__row > :last-child { display:none; }
            .designer-actions { position:sticky; right:auto; bottom:8px; left:auto; display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); width:100%; margin:12px 0 0; }
            .designer-actions .wms-btn { width:100%; min-width:0; padding-inline:8px; justify-content:center; white-space:normal; }
            .designer-actions .wms-btn:last-child { grid-column:1 / -1; }
            .designer-wizard-nav { position:sticky; bottom:8px; z-index:19; }
        }
        @media (prefers-reduced-motion:reduce) { * { scroll-behavior:auto !important; transition-duration:.01ms !important; } }
    </style>
</head>
<body>
@include('layouts.partials.sidebar')

<header class="wms-topbar">
    <h1 class="wms-topbar__title">WMS May Mặc</h1>
    <div class="flex-grow-1"></div>
    <a class="wms-btn" href="{{ route('weaving.dashboard') }}"><i data-lucide="layout-dashboard"></i>Quản lý dệt</a>
    <a class="wms-btn" href="{{ route('weaving.bom') }}"><i data-lucide="settings-2"></i>Định mức</a>
</header>

<main class="wms-page designer-create">
    <div class="wms-heading">
        <div><h1>Tạo lệnh dệt</h1><p>Chọn lệnh sản xuất trung tâm, hoàn thiện thông số và gửi xuống sản xuất.</p></div>
        <span class="wms-chip">Lệnh /{{ now('Asia/Ho_Chi_Minh')->format('y') }}</span>
    </div>

    <section class="designer-find">
        <div class="designer-find-row">
            <div class="designer-find-input">
                <i data-lucide="search"></i>
                <input id="orderSearch" class="form-control" autocomplete="off" placeholder="Gõ số lệnh, mã hàng, PO hoặc khách hàng...">
                <div id="orderSuggestions" class="designer-suggestions d-none"></div>
            </div>
            <button id="clearOrderBtn" class="wms-btn" type="button" hidden><i data-lucide="rotate-ccw"></i>Chọn lệnh khác</button>
        </div>
    </section>

    <div id="designerWorkspace" class="designer-workspace" hidden>
        <nav id="designerWizard" class="designer-wizard" aria-label="Các bước tạo lệnh dệt">
            <div class="designer-wizard__track"><span id="wizardProgress"></span></div>
            <button type="button" class="designer-wizard__step is-active" data-wizard-go="1" aria-current="step">
                <span>1</span><b>Thông tin</b><small>Quy cách và máy</small>
            </button>
            <button type="button" class="designer-wizard__step" data-wizard-go="2">
                <span>2</span><b>Định mức & tồn</b><small id="bomStepState">Kiểm tra vật tư</small>
            </button>
            <button type="button" class="designer-wizard__step" data-wizard-go="3">
                <span>3</span><b>Kiểm tra & in</b><small>Hoàn tất lệnh</small>
            </button>
        </nav>
        <section class="designer-form-panel">
            <div class="designer-section" data-wizard-step="1">
                <button class="designer-section-title" type="button" data-bs-toggle="collapse" data-bs-target="#generalSection">
                    <span><i data-lucide="clipboard-list"></i> Thông tin lệnh</span><i data-lucide="chevron-down"></i>
                </button>
                <div id="generalSection" class="collapse show">
                    <div class="designer-section-body">
                        <div class="designer-field-grid">
                            <div class="designer-field"><label>Số lệnh</label><input id="production_order" class="form-control" readonly></div>
                            <div class="designer-field"><label>Mã hàng</label><input id="item_code" class="form-control" readonly></div>
                            <div class="designer-field span-2"><label>Tên hàng</label><input id="item_name" class="form-control" readonly></div>
                            <div class="designer-field"><label>Khách hàng</label><input id="customer" class="form-control" data-preview></div>
                            <div class="designer-field"><label>PO</label><input id="po_number" class="form-control" data-preview></div>
                            <div class="designer-field"><label>Mã design</label><input id="design_code" class="form-control" data-preview></div>
                            <div class="designer-field"><label>Job #</label><input id="job_number" class="form-control" data-metadata data-preview></div>
                            <div class="designer-field"><label>Ngày ra lệnh</label><input id="order_date" inputmode="numeric" placeholder="dd/mm/yyyy" class="form-control" data-preview></div>
                            <div class="designer-field"><label>Hạn giao từ Lệnh SX</label><input id="due_date" inputmode="numeric" placeholder="dd/mm/yyyy" class="form-control" data-preview readonly></div>
                            <div class="designer-field"><label>Số lượng</label><input id="order_quantity" type="number" min="0" step="0.001" class="form-control" data-preview></div>
                            <div class="designer-field"><label>ĐVT</label><input id="unit" class="form-control" data-preview></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="designer-section" data-wizard-step="1">
                <button class="designer-section-title" type="button" data-bs-toggle="collapse" data-bs-target="#technicalSection">
                    <span><i data-lucide="ruler"></i> Tên label và quy cách</span><i data-lucide="chevron-down"></i>
                </button>
                <div id="technicalSection" class="collapse show">
                    <div class="designer-section-body">
                        <div class="designer-field-grid">
                            <div class="designer-field span-4"><label>Tên label</label><input id="label_name" class="form-control" data-metadata data-preview></div>
                            <div class="designer-field span-2"><label>Ủi keo</label><input id="op_ui_keo" class="form-control" data-operation data-preview></div>
                            <div class="designer-field span-2"><label>Loop</label><input id="op_loop" class="form-control" data-operation data-preview></div>
                            <div class="designer-field span-2"><label>Phần trên</label><input id="op_phan_tren" class="form-control" data-operation data-preview></div>
                            <div class="designer-field span-2"><label>Phần dưới</label><input id="op_phan_duoi" class="form-control" data-operation data-preview></div>
                            <div class="designer-field"><label>Chiều dài</label><input id="length" class="form-control" data-metadata data-preview></div>
                            <div class="designer-field"><label>Hoàn chỉnh</label><input id="finished_size" class="form-control" data-metadata data-preview></div>
                            <div class="designer-field"><label>Mã số hộp</label><input id="box_code" class="form-control" data-metadata data-preview></div>
                            <div class="designer-field"><label>SL/hộp</label><input id="quantity_per_box" class="form-control" data-metadata data-preview></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="designer-section" data-wizard-step="1">
                <button class="designer-section-title" type="button" data-bs-toggle="collapse" data-bs-target="#machineSection">
                    <span><i data-lucide="settings"></i> Thông số sản xuất <small id="machineDefaultsSource" class="text-secondary"></small></span><i data-lucide="chevron-down"></i>
                </button>
                <div id="machineSection" class="collapse show">
                    <div class="designer-section-body">
                        <div class="designer-field-grid">
                            <div class="designer-field"><label>Số pick</label><input id="pick" class="form-control" data-metadata placeholder="Ví dụ: 714"></div>
                            <div class="designer-field"><label>Mật độ</label><input id="density" class="form-control" data-metadata placeholder="Ví dụ: 26"></div>
                            <div class="designer-field"><label>Máy</label><input id="machine" class="form-control" data-metadata placeholder="Ví dụ: TRẮNG"></div>
                            <div class="designer-field"><label>Số dòng</label><input id="row_count" type="number" min="0" step="1" class="form-control" data-metadata placeholder="Nhập số dòng"></div>
                            <div class="designer-field"><label>Số lượng +10%</label><input id="quantity_plus_10" type="number" min="0" step="0.001" class="form-control" data-metadata placeholder="Tự tính từ số lượng lệnh"></div>
                            <div class="designer-field"><label>Số cuộn Muller</label><input id="roll_count_small" type="number" min="0" step="0.001" class="form-control" data-metadata placeholder="Ví dụ: 15"></div>
                            <div class="designer-field"><label>Số dòng +10% Muller</label><input id="row_count_plus_10" type="number" min="0" step="0.001" class="form-control" data-metadata placeholder="Ví dụ: 2"></div>
                            <div class="designer-field"><label>Ca Muller</label><input id="shift" class="form-control" data-metadata placeholder="Ví dụ: 0,0"></div>
                            <div class="designer-field"><label>Số cuộn Hi-Tex</label><input id="roll_count_large" type="number" min="0" step="0.001" class="form-control" data-metadata placeholder="Ví dụ: 24"></div>
                            <div class="designer-field"><label>Số dòng +10% Hi-Tex</label><input id="row_count_plus_10_large" type="number" min="0" step="0.001" class="form-control" data-metadata placeholder="Ví dụ: 1"></div>
                            <div class="designer-field"><label>Ca Hi-Tex</label><input id="shift_large" class="form-control" data-metadata placeholder="Ví dụ: 0,0"></div>
                            <input id="roll_machine_small" type="hidden" value="Muller" data-metadata>
                            <input id="roll_machine_large" type="hidden" value="Hi-Tex" data-metadata>
                            <input id="row_machine_small" type="hidden" value="Muller" data-metadata>
                            <input id="row_machine_large" type="hidden" value="Hi-Tex" data-metadata>
                            <div class="designer-field"><label>Tên file</label><input id="file_name" class="form-control" data-metadata></div>
                            <div class="designer-field"><label>USB máy nhỏ</label><input id="usb_small" class="form-control" data-metadata></div>
                            <div class="designer-field"><label>USB máy lớn</label><input id="usb_large" class="form-control" data-metadata></div>
                            <details class="designer-formula-box">
                                <summary><i data-lucide="calculator"></i>Hệ số tính Excel</summary>
                                <div class="designer-formula-grid">
                                    <div class="designer-field"><label>Hao hụt chung (%)</label><input id="calculation_waste_percent" inputmode="decimal" class="form-control" data-metadata placeholder="10"></div>
                                    <div class="designer-field"><label>HS sợi màu</label><input id="color_weight_factor" inputmode="decimal" class="form-control" data-metadata placeholder="0,23"></div>
                                    <div class="designer-field"><label>HS nhân sợi màu</label><input id="color_weight_multiplier" inputmode="decimal" class="form-control" data-metadata placeholder="1,5"></div>
                                    <div class="designer-field"><label>HS sợi dọc</label><input id="warp_weight_factor" inputmode="decimal" class="form-control" data-metadata placeholder="0,532"></div>
                                    <div class="designer-field"><label>Hao hụt sợi dọc (%)</label><input id="warp_extra_waste_percent" inputmode="decimal" class="form-control" data-metadata placeholder="0"></div>
                                    <div class="designer-field"><label>Năng suất Muller</label><input id="muller_capacity" inputmode="decimal" class="form-control" data-metadata placeholder="210000"></div>
                                    <div class="designer-field"><label>Năng suất Hi-Tex</label><input id="hitex_capacity" inputmode="decimal" class="form-control" data-metadata placeholder="144000"></div>
                                </div>
                            </details>
                        </div>
                    </div>
                </div>
            </div>

            <div class="designer-section" data-wizard-step="2" hidden>
                <div class="designer-section-body">
                    <div class="designer-bom-head">
                        <div class="designer-bom-title"><strong>Định mức sợi</strong><span id="bomSummary" class="designer-bom-summary">0/7 dòng · 0 g</span></div>
                        <button id="addBomRowBtn" class="wms-btn" type="button"><i data-lucide="plus"></i>Thêm dòng</button>
                    </div>
                    <div class="designer-bom-editor" role="table" aria-label="Định mức sợi">
                        <div class="designer-bom-columns" role="row">
                            <span>STT</span><span>Loại</span><span>Mã sợi *</span><span>Số picks</span><span>Tên màu sợi</span><span title="Trọng lượng cho một sản phẩm">TL/1PCS (g) *</span><span title="Tổng trọng lượng">T.L (g)</span><span></span>
                        </div>
                        <div id="bomRows" class="designer-bom-list" role="rowgroup">
                            <div class="wms-empty">Chọn lệnh để nạp định mức.</div>
                        </div>
                    </div>
                    <datalist id="materialOptions"></datalist>
                    <div id="stockCheck" class="designer-stock" aria-live="polite">
                        <div class="designer-stock__head">
                            <strong><i data-lucide="package-check"></i>Kiểm tra tồn sợi</strong>
                            <span id="stockCheckState" class="designer-stock__state">Chờ định mức</span>
                        </div>
                        <div id="stockCheckRows" class="designer-stock__empty">Nhập mã sợi và TL/1PCS để kiểm tra.</div>
                    </div>
                </div>
            </div>
        </section>

        <aside class="designer-preview" data-wizard-step="3" hidden>
            <div class="designer-preview-head">
                <h2>Xem trước lệnh dệt</h2>
                <span id="completeness" class="designer-completeness">Chưa chọn lệnh</span>
            </div>
            <div id="reviewStockAlert" class="designer-review-stock">Chưa kiểm tra tồn sợi.</div>
            <div id="sheetPreview" class="designer-sheet">
                <div class="designer-sheet-title">LỆNH DỆT</div>
                <div class="designer-sheet-empty p-5">Chọn một lệnh sản xuất để bắt đầu.</div>
            </div>
        </aside>
    </div>

    <div id="wizardNavigation" class="designer-wizard-nav" hidden>
        <button id="wizardBackBtn" class="wms-btn" type="button"><i data-lucide="arrow-left"></i>Quay lại</button>
        <span id="wizardStepLabel">Bước 1/3</span>
        <button id="wizardNextBtn" class="wms-btn wms-btn--primary" type="button">Tiếp tục<i data-lucide="arrow-right"></i></button>
    </div>

    <div id="designerActions" class="designer-actions" hidden>
        <button id="saveDraftBtn" class="wms-btn" type="button" disabled><i data-lucide="save"></i>Lưu nháp</button>
        <button id="printPdfBtn" class="wms-btn" type="button" disabled><i data-lucide="printer"></i>Lưu + in PDF</button>
        <button id="exportBtn" class="wms-btn" type="button" disabled><i data-lucide="file-spreadsheet"></i>Xuất Excel</button>
        <button id="issueBtn" class="wms-btn wms-btn--primary" type="button" disabled><i data-lucide="send"></i>Lưu + gửi sản xuất</button>
    </div>
</main>

<div id="toast" class="designer-toast" role="status" aria-live="polite"></div>
<div id="designerLoader" class="designer-loader" role="status" aria-live="polite" aria-busy="true" hidden>
    <div class="designer-loader__panel">
        <span class="spinner-border" aria-hidden="true"></span>
        <span><strong id="designerLoaderText">Đang xử lý...</strong><small>Vui lòng không bấm lại thao tác.</small></span>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@include('layouts.partials.catalog-image-paste-modal')
<script src="https://unpkg.com/lucide@latest"></script>
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const currentYear = {{ (int) now('Asia/Ho_Chi_Minh')->format('Y') }};
const currentDateCompact = '{{ now('Asia/Ho_Chi_Minh')->format('Ymd') }}';
const metadataFields = ['job_number','label_name','length','finished_size','box_code','quantity_per_box','pick','density','machine','roll_machine_small','roll_count_small','roll_machine_large','roll_count_large','quantity_plus_10','row_machine_small','row_count_plus_10','row_machine_large','row_count_plus_10_large','shift','shift_large','row_count','file_name','usb_small','usb_large','calculation_waste_percent','color_weight_factor','color_weight_multiplier','warp_weight_factor','warp_extra_waste_percent','muller_capacity','hitex_capacity'];
const formulaDefaults = {
    calculation_waste_percent:10,
    color_weight_factor:0.23,
    color_weight_multiplier:1.5,
    warp_weight_factor:0.532,
    warp_extra_waste_percent:0,
    muller_capacity:210000,
    hitex_capacity:144000,
};
const formulaNumericFields = new Set(Object.keys(formulaDefaults));
const operationFields = {op_ui_keo:'UI_KEO',op_loop:'LOOP',op_phan_tren:'PHAN_TREN',op_phan_duoi:'PHAN_DUOI'};
let selectedPlan = null;
let selectedOrderCode = '';
let searchTimer = null;
let materialTimer = null;
let catalogRows = new Map();
let loaderDepth = 0;
let stockTimer = null;
let stockRequestId = 0;
let latestStockSummary = {short_count:0};
let currentWizardStep = 1;
const orderSearchInput = document.getElementById('orderSearch');
const orderSuggestions = document.getElementById('orderSuggestions');
document.body.appendChild(orderSuggestions);

function positionOrderSuggestions() {
    if (orderSuggestions.classList.contains('d-none')) return;
    const rect = orderSearchInput.getBoundingClientRect();
    const availableHeight = Math.max(140, window.innerHeight - rect.bottom - 16);
    orderSuggestions.style.left = `${Math.round(rect.left)}px`;
    orderSuggestions.style.top = `${Math.round(rect.bottom + 6)}px`;
    orderSuggestions.style.width = `${Math.round(rect.width)}px`;
    orderSuggestions.style.maxHeight = `${Math.min(420, availableHeight)}px`;
}

function showLoader(message = 'Đang xử lý...') {
    loaderDepth += 1;
    document.getElementById('designerLoaderText').textContent = message;
    document.getElementById('designerLoader').hidden = false;
    document.body.classList.add('is-designer-loading');
    document.querySelector('.designer-create')?.setAttribute('aria-busy', 'true');
}
function hideLoader() {
    loaderDepth = Math.max(0, loaderDepth - 1);
    if (loaderDepth > 0) return;
    document.getElementById('designerLoader').hidden = true;
    document.body.classList.remove('is-designer-loading');
    document.querySelector('.designer-create')?.setAttribute('aria-busy', 'false');
}

function esc(value) {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
}
function num(value) {
    return new Intl.NumberFormat('vi-VN', {maximumFractionDigits:3}).format(Number(value || 0));
}
async function api(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrfToken,...(options.headers || {})}
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(result.message || 'Không xử lý được yêu cầu.');
    return result;
}
function toast(message, error = false) {
    const box = document.getElementById('toast');
    box.textContent = message;
    box.classList.toggle('is-error', error);
    box.classList.add('is-visible');
    clearTimeout(box.timer);
    box.timer = setTimeout(() => box.classList.remove('is-visible'), 3500);
}
function operationValue(operations, key) {
    const wanted = key.replaceAll('_',' ');
    const entry = Object.entries(operations || {}).find(([name]) => name.replaceAll('_',' ').toUpperCase() === wanted.toUpperCase());
    return entry ? entry[1] : '';
}
function setValue(id, value) {
    const input = document.getElementById(id);
    if (input) input.value = value ?? '';
}
function setDefaultQuantityPlusTen(force = false) {
    const input = document.getElementById('quantity_plus_10');
    if (!input || (!force && input.dataset.autoDefault !== '1')) return;
    const orderQuantity = decimalValue(getValue('order_quantity'));
    input.value = orderQuantity > 0 ? Number((orderQuantity * 1.1).toFixed(3)) : '';
    input.dataset.autoDefault = '1';
}
function dateForInput(value) {
    const text = String(value || '').trim();
    const match = text.match(/^(\d{4})-(\d{2})-(\d{2})/);
    return match ? `${match[3]}/${match[2]}/${match[1]}` : text;
}
function professionalFileName(orderCode, itemCode, orderDate) {
    const safePart = value => String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toUpperCase()
        .replace(/[^A-Z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
    const dateParts = String(orderDate || '').match(/^(\d{2})[/-](\d{2})[/-](\d{4})$/);
    const dateSuffix = dateParts ? `${dateParts[3]}${dateParts[2]}${dateParts[1]}` : currentDateCompact;
    return ['LENH-DET', safePart(orderCode), safePart(itemCode), dateSuffix].filter(Boolean).join('_');
}
function getValue(id) {
    return String(document.getElementById(id)?.value || '').trim();
}
function setWizardStep(step) {
    currentWizardStep = Math.min(3, Math.max(1, Number(step) || 1));
    document.querySelectorAll('[data-wizard-step]').forEach(section => {
        section.hidden = Number(section.dataset.wizardStep) !== currentWizardStep;
    });
    const formPanel = document.querySelector('.designer-form-panel');
    if (formPanel) formPanel.hidden = currentWizardStep === 3;
    document.querySelectorAll('[data-wizard-go]').forEach(button => {
        const target = Number(button.dataset.wizardGo);
        button.classList.toggle('is-active', target === currentWizardStep);
        button.classList.toggle('is-done', target < currentWizardStep);
        if (target === currentWizardStep) button.setAttribute('aria-current', 'step');
        else button.removeAttribute('aria-current');
    });
    document.getElementById('wizardProgress').style.width = `${(currentWizardStep - 1) * 50}%`;
    document.getElementById('wizardStepLabel').textContent = `Bước ${currentWizardStep}/3`;
    document.getElementById('wizardBackBtn').disabled = currentWizardStep === 1;
    document.getElementById('wizardNextBtn').hidden = currentWizardStep === 3;
    document.getElementById('designerActions').hidden = currentWizardStep !== 3;
    document.getElementById('wizardNavigation').hidden = false;
    document.getElementById('designerWorkspace')?.scrollIntoView({behavior:'smooth',block:'start'});
}
function canOpenReview() {
    const missing = validatePayload(payload('draft'));
    if (!missing.length) return true;
    toast('Cần bổ sung: ' + missing.slice(0, 4).join(', ') + '.', true);
    return false;
}

async function searchOrders() {
    const keyword = getValue('orderSearch');
    const box = orderSuggestions;
    if (keyword.length < 2) {
        box.classList.add('d-none');
        return;
    }
    box.classList.remove('d-none');
    positionOrderSuggestions();
    box.innerHTML = '<div class="wms-loading p-3">Đang tìm lệnh /' + String(currentYear).slice(-2) + '...</div>';
    try {
        const result = await api(`/api/lenh-det/production-orders?year=${currentYear}&per_page=25&keyword=${encodeURIComponent(keyword)}`);
        const rows = result.data || [];
        box.innerHTML = rows.map(row => `
            <button class="designer-suggestion" type="button" data-order="${esc(row.production_order)}">
                <span><strong>${esc(row.production_order)}</strong><small class="d-block text-secondary">${esc(row.customer || '-')}</small></span>
                <span>${esc(row.item_code || '-')}<small class="d-block text-secondary">${esc(row.description || '')}</small></span>
                <span class="text-end fw-bold">${num(row.planned_quantity)} ${esc(row.unit || '')}</span>
            </button>
        `).join('') || '<div class="wms-empty p-3">Không tìm thấy lệnh /' + String(currentYear).slice(-2) + '.</div>';
    } catch (error) {
        box.innerHTML = `<div class="wms-empty text-danger p-3">${esc(error.message)}</div>`;
    }
}

async function selectOrder(code) {
    selectedOrderCode = code;
    setValue('orderSearch', code);
    document.getElementById('orderSuggestions').classList.add('d-none');
    document.getElementById('sheetPreview').innerHTML = '<div class="designer-sheet-title">LỆNH DỆT</div><div class="wms-loading p-5">Đang nạp dữ liệu...</div>';
    showLoader(`Đang tải lệnh ${code}...`);
    try {
        const result = await api(`/api/lenh-det/production-order-plan?production_order=${encodeURIComponent(code)}`);
        selectedPlan = result;
        populateForm(result);
        document.getElementById('designerWorkspace').hidden = false;
        document.getElementById('clearOrderBtn').hidden = false;
        document.querySelectorAll('#saveDraftBtn,#printPdfBtn,#exportBtn,#issueBtn').forEach(button => button.disabled = false);
        const hasBom = (result.source_items?.[0]?.materials || []).length > 0;
        document.getElementById('bomStepState').textContent = hasBom ? 'Đã có định mức' : 'Cần nhập định mức';
        setWizardStep(1);
        renderPreview();
        scheduleStockCheck(0);
    } catch (error) {
        selectedPlan = null;
        toast(error.message, true);
        clearForm(false);
    } finally {
        hideLoader();
    }
}

function populateForm(result) {
    const order = result.order || {};
    const item = (result.source_items || [])[0] || {};
    const metadata = {...(item.metadata || {}), ...(order.metadata || {})};
    const previousMachineDefaults = result.machine_defaults?.values || {};
    const operations = metadata.operations || {};
    setValue('production_order', order.production_order || order.order_code || selectedOrderCode);
    setValue('item_code', order.item_code || item.item_code);
    setValue('item_name', item.item_name);
    ['customer','po_number','design_code','unit'].forEach(key => setValue(key, order[key] ?? item[key] ?? ''));
    setValue('order_date', dateForInput(order.order_date));
    setValue('due_date', dateForInput(order.due_date));
    setValue('order_quantity', order.planned_quantity ?? item.order_quantity ?? '');
    let usedPreviousMachineDefaults = false;
    metadataFields.forEach(key => {
        const ownValue = metadata[key];
        const previousValue = previousMachineDefaults[key];
        const hasOwnValue = ownValue !== undefined && ownValue !== null && ownValue !== '';
        const hasPreviousValue = previousValue !== undefined && previousValue !== null && previousValue !== '';
        if (!hasOwnValue && hasPreviousValue) usedPreviousMachineDefaults = true;
        setValue(key, hasOwnValue ? ownValue : (hasPreviousValue ? previousValue : (formulaDefaults[key] ?? '')));
    });
    document.getElementById('machineDefaultsSource').textContent = usedPreviousMachineDefaults && result.machine_defaults?.source_order
        ? `· theo ${result.machine_defaults.source_order}`
        : '';
    if (!getValue('quantity_plus_10')) {
        setDefaultQuantityPlusTen(true);
    } else {
        document.getElementById('quantity_plus_10').dataset.autoDefault = '0';
    }
    setValue('roll_machine_small', 'Muller');
    setValue('roll_machine_large', 'Hi-Tex');
    setValue('row_machine_small', 'Muller');
    setValue('row_machine_large', 'Hi-Tex');
    if (!getValue('file_name')) {
        setValue('file_name', professionalFileName(
            getValue('production_order'),
            getValue('item_code'),
            getValue('order_date')
        ));
    }
    Object.entries(operationFields).forEach(([id,key]) => setValue(id, operationValue(operations, key)));
    const lines = item.materials?.length ? item.materials : [];
    renderBomRows(lines.length ? lines : [{}]);
}

function renderBomRows(lines) {
    const box = document.getElementById('bomRows');
    box.innerHTML = lines.slice(0, 7).map((line, index) => bomRow(line, index)).join('');
    document.getElementById('addBomRowBtn').disabled = lines.length >= 7;
    updateBomSummary();
    if (window.lucide) lucide.createIcons();
}
function bomRow(line = {}, index) {
    const total = Number(line.total_grams) > 0 ? line.total_grams : (line.required_quantity_raw || line.required_quantity || '');
    const colorHex = normalizeHex(line.color_hex);
    const colorLabel = swatchLabel(line);
    return `
        <div class="designer-bom-row" data-bom-row data-total-manual="${Number(total) > 0 ? '1' : '0'}" role="row">
            <div class="designer-bom-index">${index + 1}</div>
            <div class="designer-bom-cell"><input class="form-control" data-bom="type" value="${esc(line.type || '')}" aria-label="Loại sợi" placeholder="75D"></div>
            <div class="designer-bom-cell"><input class="form-control" list="materialOptions" data-bom="material_code" data-material-code value="${esc(line.material_code || '')}" aria-label="Mã sợi" placeholder="Mã sợi"></div>
            <div class="designer-bom-cell"><input class="form-control" data-bom="pick_count" value="${esc(line.pick_count || '')}" aria-label="Số picks" placeholder="Số picks"></div>
            <div class="designer-bom-cell"><div class="designer-color-field">
                <span class="designer-color-swatch${colorHex ? '' : ' is-empty'}" data-color-swatch style="${colorHex ? `--swatch-color:${colorHex}` : ''}" title="${esc(colorLabel)}" aria-label="${esc(colorLabel)}"></span>
                <input class="form-control" data-bom="material_name" value="${esc(line.catalog_name || line.material_name || '')}" aria-label="Tên màu sợi" placeholder="Tự điền theo mã">
            </div></div>
            <div class="designer-bom-cell bom-consumption"><input type="text" inputmode="decimal" class="form-control" data-bom="consumption_per_unit" value="${esc(line.consumption_per_unit || '')}" placeholder="0,38" aria-label="Trọng lượng trên một sản phẩm"></div>
            <div class="designer-bom-cell bom-total"><input type="text" inputmode="decimal" class="form-control" data-bom="total_grams" value="${esc(total)}" placeholder="Tự tính" title="Có thể nhập tổng gram để tính ngược TL/1PCS" aria-label="Tổng trọng lượng gram"></div>
            <button class="designer-icon-btn" type="button" data-remove-row title="Xóa dòng"><i data-lucide="x"></i></button>
            <input type="hidden" data-bom="line_role" value="${esc(line.line_role || `DONG-${index + 1}`)}">
            <input type="hidden" data-bom="unit" value="${esc(line.bom_unit || line.unit || 'gam')}">
            <input type="hidden" data-bom="waste_percent" value="${esc(line.waste_percent || 0)}">
            <input type="hidden" data-bom="shelf_hint" value="${esc(line.first_location || line.catalog_shelf_code || line.shelf_hint || '')}">
            <input type="hidden" data-color-hex value="${esc(colorHex)}">
            <input type="hidden" data-pantone-code value="${esc(line.pantone_code || '')}">
            <input type="hidden" data-color-name value="${esc(line.color_name || line.catalog_color || '')}">
        </div>
    `;
}

function updateBomSummary() {
    const rows = Array.from(document.querySelectorAll('[data-bom-row]'));
    const used = rows.filter(row => row.querySelector('[data-bom="material_code"]')?.value.trim()).length;
    const total = rows.reduce((sum, row) => sum + decimalValue(row.querySelector('[data-bom="total_grams"]')?.value), 0);
    document.getElementById('bomSummary').textContent = `${used}/7 dòng · ${num(total)} g`;
}

function normalizeHex(value) {
    const text = String(value || '').trim().toLowerCase();
    return /^#[0-9a-f]{6}$/.test(text) ? text : '';
}
function swatchLabel(line = {}) {
    const parts = [
        line.color_name || line.catalog_color || '',
        line.pantone_code || '',
    ].map(value => String(value || '').trim()).filter(Boolean);
    return parts.length ? parts.join(' · ') : 'Chưa nhận diện màu';
}
function setBomSwatch(row, color = {}) {
    const hex = normalizeHex(color.color_hex);
    const swatch = row.querySelector('[data-color-swatch]');
    if (!swatch) return;
    swatch.classList.toggle('is-empty', !hex);
    swatch.style.setProperty('--swatch-color', hex || '#ffffff');
    const label = swatchLabel(color);
    swatch.title = label;
    swatch.setAttribute('aria-label', label);
    row.querySelector('[data-color-hex]').value = hex;
    row.querySelector('[data-pantone-code]').value = color.pantone_code || '';
    row.querySelector('[data-color-name]').value = color.color_name || color.color || '';
}

function collectBomLines() {
    return Array.from(document.querySelectorAll('[data-bom-row]')).map(row => {
        const line = {};
        row.querySelectorAll('[data-bom]').forEach(input => line[input.dataset.bom] = input.value.trim());
        line.consumption_per_unit = decimalValue(line.consumption_per_unit);
        line.waste_percent = decimalValue(line.waste_percent);
        line.total_grams = decimalValue(line.total_grams);
        return line;
    }).filter(line => line.material_code || line.consumption_per_unit);
}
function decimalValue(value) {
    const text = String(value ?? '').trim().replace(/\s+/g, '');
    if (!text) return 0;
    const comma = text.lastIndexOf(',');
    const dot = text.lastIndexOf('.');
    let normalized = text;
    if (comma >= 0 && dot >= 0) {
        const decimal = comma > dot ? ',' : '.';
        normalized = text
            .replace(decimal === ',' ? /\./g : /,/g, '')
            .replace(decimal, '.');
    } else if (comma >= 0) {
        normalized = text.replace(',', '.');
    }
    const number = Number(normalized);
    return Number.isFinite(number) ? number : 0;
}
function calculateConsumptionFromTotal(row) {
    const quantity = decimalValue(getValue('order_quantity'));
    const total = decimalValue(row.querySelector('[data-bom="total_grams"]').value);
    const waste = decimalValue(row.querySelector('[data-bom="waste_percent"]').value);
    if (!(quantity > 0) || !(total > 0)) return;
    const consumption = total / quantity / (1 + waste / 100);
    row.querySelector('[data-bom="consumption_per_unit"]').value = consumption
        .toFixed(6)
        .replace(/\.?0+$/, '');
}
function recalculateBom(skipTotalRow = null) {
    const quantity = decimalValue(getValue('order_quantity'));
    document.querySelectorAll('[data-bom-row]').forEach(row => {
        const consumption = decimalValue(row.querySelector('[data-bom="consumption_per_unit"]').value);
        const waste = decimalValue(row.querySelector('[data-bom="waste_percent"]').value);
        if (row !== skipTotalRow && row.dataset.totalManual !== '1') {
            row.querySelector('[data-bom="total_grams"]').value = consumption > 0 ? (quantity * consumption * (1 + waste / 100)).toFixed(3).replace(/\.?0+$/, '') : '';
        }
    });
    updateBomSummary();
}

async function loadMaterialSuggestions(keyword) {
    if (keyword.length < 1) return;
    try {
        const result = await api(`/api/lenh-det/material-suggestions?keyword=${encodeURIComponent(keyword)}`);
        const options = result.data || [];
        options.forEach(row => catalogRows.set(row.item_code.toUpperCase(), row));
        document.getElementById('materialOptions').innerHTML = options.map(row =>
            `<option value="${esc(row.item_code)}">${esc(row.item_name)} · ${esc(row.color_name || row.color || 'Chưa có màu')} · Kệ ${esc(row.shelf_code || '-')}</option>`
        ).join('');
        document.querySelectorAll('[data-material-code]').forEach(input => applyMaterial(input, false));
    } catch (_) {}
}
function applyMaterial(input, refresh = true) {
    const row = catalogRows.get(input.value.trim().toUpperCase());
    if (!row) return;
    const wrapper = input.closest('[data-bom-row]');
    wrapper.querySelector('[data-bom="material_name"]').value = row.item_name || row.color || '';
    wrapper.querySelector('[data-bom="unit"]').value = 'gam';
    if (row.shelf_code) wrapper.querySelector('[data-bom="shelf_hint"]').value = row.shelf_code;
    setBomSwatch(wrapper, row);
    if (refresh) renderPreview();
}

function metadataPayload() {
    const metadata = {operations:{}};
    metadataFields.forEach(key => metadata[key] = formulaNumericFields.has(key)
        ? decimalValue(getValue(key))
        : getValue(key));
    Object.entries(operationFields).forEach(([id,key]) => metadata.operations[key] = getValue(id));
    return metadata;
}
function scheduleStockCheck(delay = 350) {
    clearTimeout(stockTimer);
    stockTimer = setTimeout(checkStock, delay);
}
async function checkStock() {
    const quantity = Number(getValue('order_quantity') || 0);
    const lines = collectBomLines().filter(line => line.material_code && Number(line.consumption_per_unit) > 0);
    const panel = document.getElementById('stockCheck');
    const state = document.getElementById('stockCheckState');
    const rows = document.getElementById('stockCheckRows');
    if (!(quantity > 0) || !lines.length) {
        latestStockSummary = {short_count:0};
        panel.classList.remove('is-short');
        state.textContent = 'Chờ định mức';
        rows.className = 'designer-stock__empty';
        rows.textContent = 'Nhập mã sợi và TL/1PCS để kiểm tra.';
        const review = document.getElementById('reviewStockAlert');
        review.classList.remove('is-short');
        review.textContent = 'Chưa có đủ định mức để kiểm tra tồn sợi.';
        return;
    }

    const requestId = ++stockRequestId;
    state.textContent = 'Đang kiểm tra...';
    try {
        const result = await api('/api/lenh-det/check-stock', {
            method:'POST',
            body:JSON.stringify({order_quantity:quantity,lines}),
        });
        if (requestId !== stockRequestId) return;
        latestStockSummary = result.summary || {short_count:0};
        const shortage = Number(latestStockSummary.short_count || 0) > 0;
        panel.classList.toggle('is-short', shortage);
        state.textContent = shortage ? `Thiếu ${latestStockSummary.short_count} mã sợi` : 'Đủ tồn để sản xuất';
        const review = document.getElementById('reviewStockAlert');
        review.classList.toggle('is-short', shortage);
        review.textContent = shortage
            ? `Cảnh báo: thiếu ${latestStockSummary.short_count} mã sợi, tổng thiếu ${num(latestStockSummary.shortage_quantity)} theo đơn vị từng mã.`
            : `Đủ tồn cho ${latestStockSummary.material_count || 0} mã sợi trong định mức.`;
        rows.className = 'designer-stock__rows';
        rows.innerHTML = (result.data || []).map(line => `
            <div class="designer-stock__row">
                <span><b>${esc(line.material_code)}</b><small class="d-block text-secondary">${esc(line.material_name || '')}</small></span>
                <span>Cần <b>${num(line.required_quantity)} ${esc(line.unit)}</b></span>
                <span>Tồn <b>${num(line.stock_quantity)} ${esc(line.unit)}</b></span>
                <span class="${line.status === 'short' ? 'is-short' : ''}">${line.status === 'short' ? `Thiếu ${num(line.shortage_quantity)}` : 'Đủ tồn'}</span>
                <span>Kệ ${esc(line.first_location || 'CHƯA XẾP')}</span>
            </div>
        `).join('');
    } catch (error) {
        if (requestId !== stockRequestId) return;
        panel.classList.add('is-short');
        state.textContent = 'Không kiểm tra được tồn';
        const review = document.getElementById('reviewStockAlert');
        review.classList.add('is-short');
        review.textContent = 'Không kiểm tra được tồn sợi: ' + error.message;
        rows.className = 'designer-stock__empty';
        rows.textContent = error.message;
    }
}
function payload(action) {
    return {
        action,
        production_order:getValue('production_order'),
        item_code:getValue('item_code'),
        item_name:getValue('item_name'),
        customer:getValue('customer'),
        po_number:getValue('po_number'),
        design_code:getValue('design_code'),
        order_quantity:Number(getValue('order_quantity') || 0),
        unit:getValue('unit'),
        order_date:getValue('order_date') || null,
        due_date:getValue('due_date') || null,
        metadata:metadataPayload(),
        lines:collectBomLines(),
    };
}
function validatePayload(data) {
    const missing = [];
    if (!data.production_order) missing.push('số lệnh');
    if (!data.item_code) missing.push('mã hàng');
    if (!(data.order_quantity > 0)) missing.push('số lượng');
    if (!data.lines.length) missing.push('định mức sợi');
    data.lines.forEach((line,index) => {
        if (!line.material_code) missing.push(`mã sợi dòng ${index + 1}`);
        if (!(line.consumption_per_unit > 0)) missing.push(`TL/1PCS dòng ${index + 1}`);
    });
    if (data.lines.length > 7) missing.push('BOM vượt 7 dòng');
    return [...new Set(missing)];
}

async function save(action, silent = false) {
    const data = payload(action);
    const missing = validatePayload(data);
    if (missing.length) throw new Error('Cần bổ sung: ' + missing.join(', ') + '.');
    showLoader(action === 'issued' ? 'Đang gửi lệnh xuống sản xuất...' : 'Đang lưu lệnh dệt...');
    try {
        const result = await api('/api/lenh-det/designer-save', {method:'POST',body:JSON.stringify(data)});
        if (!silent) toast(result.message);
        return result;
    } finally {
        hideLoader();
    }
}
async function saveWithButton(button, action) {
    button.disabled = true;
    try {
        if (action === 'issued' && latestStockSummary.short_count > 0 && !confirm(`Đang thiếu ${latestStockSummary.short_count} mã sợi. Vẫn lưu và gửi xuống sản xuất?`)) return;
        if (action === 'issued' && latestStockSummary.short_count === 0 && !confirm('Lưu lệnh và gửi xuống sản xuất? BOM sẽ được chụp phiên bản tại thời điểm gửi.')) return;
        await save(action);
    } catch (error) {
        toast(error.message, true);
    } finally {
        button.disabled = false;
    }
}

function renderPreview(skipTotalRow = null) {
    if (!selectedPlan) return;
    recalculateBom(skipTotalRow);
    const data = payload('draft');
    const missing = validatePayload(data);
    const badge = document.getElementById('completeness');
    badge.textContent = missing.length ? `Thiếu ${missing.length} mục` : 'Sẵn sàng';
    badge.classList.toggle('is-ready', !missing.length);
    const operations = data.metadata.operations;
    const image = selectedPlan.order?.image_url || selectedPlan.source_items?.[0]?.image_url || '';
    const catalogId = selectedPlan.order?.catalog_id || selectedPlan.source_items?.[0]?.catalog_id || '';
    const imageControl = data.item_code
        ? `<button type="button" class="catalog-image-trigger" data-catalog-image-open data-catalog-id="${esc(catalogId)}" data-item-code="${esc(data.item_code)}" data-item-name="${esc(data.item_name)}" data-unit="${esc(data.unit)}" data-image-url="${esc(image)}" title="${image ? 'Xem hoặc thay ảnh danh mục' : 'Paste ảnh vào danh mục'}">
            ${image ? `<img loading="lazy" src="${esc(image)}" alt="Ảnh ${esc(data.item_code)}">` : '<span><i data-lucide="image-plus"></i><strong class="d-block">Paste ảnh vào danh mục</strong></span>'}
        </button>`
        : '<div class="designer-sheet-empty">Mã hàng chưa có trong Danh mục nội bộ</div>';
    document.getElementById('sheetPreview').innerHTML = `
        <div class="designer-sheet-title">LỆNH DỆT</div>
        <div class="designer-sheet-meta">
            ${previewCell('Khách hàng',data.customer)}
            ${previewCell('Lệnh in',data.production_order)}
            ${previewCell('PO',data.po_number)}
            ${previewCell('Mã hàng',data.item_code)}
            ${previewCell('Ngày ra lệnh',data.order_date)}
            ${previewCell('Mã design',data.design_code)}
            ${previewCell('Job #',data.metadata.job_number)}
            ${previewCell('Ngày giao',data.due_date)}
        </div>
        <div class="designer-sheet-main">
            <div class="designer-sheet-ops">
                ${previewOp('Tên label',data.metadata.label_name || data.item_name)}
                ${previewOp('Ủi keo',operations.UI_KEO)}
                ${previewOp('Loop',operations.LOOP)}
                ${previewOp('Phần trên',operations.PHAN_TREN)}
                ${previewOp('Phần dưới',operations.PHAN_DUOI)}
                ${previewOp('Chiều dài',data.metadata.length)}
                ${previewOp('Hoàn chỉnh',data.metadata.finished_size)}
                ${previewOp('Số pick',data.metadata.pick)}
                ${previewOp('Mật độ',data.metadata.density)}
                ${previewOp('Máy',data.metadata.machine)}
                ${previewOp('Cuộn Muller',data.metadata.roll_count_small)}
                ${previewOp('Dòng +10% Muller',data.metadata.row_count_plus_10)}
                ${previewOp('Cuộn Hi-Tex',data.metadata.roll_count_large)}
                ${previewOp('Dòng +10% Hi-Tex',data.metadata.row_count_plus_10_large)}
            </div>
            <div><table><thead><tr><th>Loại</th><th>Mã sợi</th><th>Số picks</th><th>Tên màu</th><th>TL/PCS</th><th>T.L(g)</th></tr></thead><tbody>
                ${data.lines.map((line,index) => {
                    const sourceRow = document.querySelectorAll('[data-bom-row]')[index];
                    const colorHex = normalizeHex(sourceRow?.querySelector('[data-color-hex]')?.value);
                    const pantone = sourceRow?.querySelector('[data-pantone-code]')?.value || '';
                    const colorName = sourceRow?.querySelector('[data-color-name]')?.value || '';
                    const label = [colorName,pantone].filter(Boolean).join(' · ') || 'Chưa nhận diện màu';
                    return `<tr><td>${esc(line.type)}</td><td><b>${esc(line.material_code)}</b></td><td>${esc(line.pick_count)}</td><td><span class="designer-preview-swatch${colorHex ? '' : ' is-empty'}" style="${colorHex ? `--swatch-color:${colorHex}` : ''}" title="${esc(label)}"></span>${esc(line.material_name)}</td><td class="text-end">${num(line.consumption_per_unit)}</td><td class="text-end">${num(line.total_grams)}</td></tr>`;
                }).join('') || '<tr><td colspan="6" class="text-center">Chưa có định mức</td></tr>'}
            </tbody></table></div>
        </div>
        <div class="designer-sheet-image">${imageControl}</div>
        <div class="p-2 d-flex justify-content-between"><b>SL: ${num(data.order_quantity)} ${esc(data.unit)}</b><span>${missing.length ? 'Còn thiếu: ' + esc(missing.slice(0,3).join(', ')) : 'Đủ thông tin bắt buộc'}</span></div>
    `;
}
function previewCell(label,value) {
    return `<div class="designer-sheet-cell"><span>${esc(label)}</span><b>${esc(value || '-')}</b></div>`;
}
function previewOp(label,value) {
    return `<div class="designer-sheet-op"><span>${esc(label)}</span><b>${esc(value || '-')}</b></div>`;
}
function clearForm(clearSearch = true) {
    selectedPlan = null;
    selectedOrderCode = '';
    if (clearSearch) setValue('orderSearch','');
    document.querySelectorAll('.designer-form-panel input').forEach(input => input.value = '');
    document.getElementById('bomRows').innerHTML = '<div class="wms-empty">Chọn lệnh để nạp định mức.</div>';
    document.getElementById('bomSummary').textContent = '0/7 dòng · 0 g';
    document.getElementById('sheetPreview').innerHTML = '<div class="designer-sheet-title">LỆNH DỆT</div><div class="designer-sheet-empty p-5">Chọn một lệnh sản xuất để bắt đầu.</div>';
    document.getElementById('completeness').textContent = 'Chưa chọn lệnh';
    document.getElementById('completeness').classList.remove('is-ready');
    document.querySelectorAll('#saveDraftBtn,#printPdfBtn,#exportBtn,#issueBtn').forEach(button => button.disabled = true);
    latestStockSummary = {short_count:0};
    document.getElementById('stockCheck')?.classList.remove('is-short');
    if (document.getElementById('stockCheckState')) document.getElementById('stockCheckState').textContent = 'Chờ định mức';
    if (document.getElementById('stockCheckRows')) {
        document.getElementById('stockCheckRows').className = 'designer-stock__empty';
        document.getElementById('stockCheckRows').textContent = 'Nhập mã sợi và TL/1PCS để kiểm tra.';
    }
    document.getElementById('reviewStockAlert').classList.remove('is-short');
    document.getElementById('reviewStockAlert').textContent = 'Chưa kiểm tra tồn sợi.';
    document.getElementById('machineDefaultsSource').textContent = '';
    document.getElementById('designerWorkspace').hidden = true;
    document.getElementById('designerActions').hidden = true;
    document.getElementById('wizardNavigation').hidden = true;
    document.getElementById('clearOrderBtn').hidden = true;
    currentWizardStep = 1;
}

document.getElementById('orderSearch').addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(searchOrders, 280);
});
document.getElementById('orderSearch').addEventListener('focus', () => {
    if (getValue('orderSearch').length >= 2) searchOrders();
});
document.getElementById('orderSearch').addEventListener('keydown', event => {
    if (event.key !== 'Enter') return;
    event.preventDefault();
    const first = document.querySelector('[data-order]');
    if (first) selectOrder(first.dataset.order);
});
orderSuggestions.addEventListener('click', event => {
    const button = event.target.closest('[data-order]');
    if (button) selectOrder(button.dataset.order);
});
document.addEventListener('click', event => {
    if (!event.target.closest('.designer-find-input') && !event.target.closest('#orderSuggestions')) {
        orderSuggestions.classList.add('d-none');
    }
});
window.addEventListener('resize', positionOrderSuggestions);
window.addEventListener('scroll', positionOrderSuggestions, true);
document.getElementById('clearOrderBtn').addEventListener('click', () => clearForm(true));
document.getElementById('designerWizard').addEventListener('click', event => {
    const button = event.target.closest('[data-wizard-go]');
    if (!button) return;
    const target = Number(button.dataset.wizardGo);
    if (target === 3 && !canOpenReview()) return;
    setWizardStep(target);
    if (target >= 2) scheduleStockCheck(0);
});
document.getElementById('wizardBackBtn').addEventListener('click', () => setWizardStep(currentWizardStep - 1));
document.getElementById('wizardNextBtn').addEventListener('click', () => {
    if (currentWizardStep === 2 && !canOpenReview()) return;
    setWizardStep(currentWizardStep + 1);
    if (currentWizardStep >= 2) scheduleStockCheck(0);
});
document.getElementById('addBomRowBtn').addEventListener('click', () => {
    const box = document.getElementById('bomRows');
    const count = document.querySelectorAll('[data-bom-row]').length;
    if (count >= 7) return toast('Mẫu chỉ có 7 dòng sợi đầy đủ.', true);
    box.insertAdjacentHTML('beforeend', bomRow({}, count));
    document.getElementById('addBomRowBtn').disabled = count + 1 >= 7;
    updateBomSummary();
    if (window.lucide) lucide.createIcons();
    box.querySelector('[data-bom-row]:last-child [data-material-code]')?.focus();
    renderPreview();
    scheduleStockCheck();
});
document.getElementById('bomRows').addEventListener('click', event => {
    const button = event.target.closest('[data-remove-row]');
    if (!button) return;
    button.closest('[data-bom-row]').remove();
    Array.from(document.querySelectorAll('[data-bom-row]')).forEach((row,index) => row.querySelector('.designer-bom-index').textContent = index + 1);
    document.getElementById('addBomRowBtn').disabled = document.querySelectorAll('[data-bom-row]').length >= 7;
    updateBomSummary();
    renderPreview();
    scheduleStockCheck();
});
document.getElementById('bomRows').addEventListener('keydown', event => {
    if (event.key !== 'Enter' || !event.target.matches('input:not([type="hidden"])')) return;
    event.preventDefault();
    const inputs = Array.from(document.querySelectorAll('#bomRows [data-bom-row] input:not([type="hidden"])'));
    const next = inputs[inputs.indexOf(event.target) + 1];
    if (next) return next.focus();
    document.getElementById('addBomRowBtn').click();
});
document.getElementById('bomRows').addEventListener('input', event => {
    if (event.target.matches('[data-material-code]')) {
        clearTimeout(materialTimer);
        materialTimer = setTimeout(() => loadMaterialSuggestions(event.target.value.trim()), 250);
    }
    const totalRow = event.target.matches('[data-bom="total_grams"]')
        ? event.target.closest('[data-bom-row]')
        : null;
    if (totalRow) {
        totalRow.dataset.totalManual = '1';
        calculateConsumptionFromTotal(totalRow);
    }
    if (event.target.matches('[data-bom="consumption_per_unit"]')) {
        event.target.closest('[data-bom-row]').dataset.totalManual = '0';
    }
    renderPreview(totalRow);
    scheduleStockCheck();
});
document.getElementById('bomRows').addEventListener('change', event => {
    if (event.target.matches('[data-material-code]')) applyMaterial(event.target);
    renderPreview();
    scheduleStockCheck();
});
document.querySelector('.designer-form-panel').addEventListener('input', event => {
    if (event.target.matches('input') && !event.target.closest('#bomRows')) {
        if (event.target.id === 'quantity_plus_10') event.target.dataset.autoDefault = '0';
        if (event.target.id === 'order_quantity') setDefaultQuantityPlusTen();
        renderPreview();
        if (event.target.id === 'order_quantity') scheduleStockCheck();
    }
});
document.getElementById('saveDraftBtn').addEventListener('click', event => saveWithButton(event.currentTarget,'draft'));
document.getElementById('issueBtn').addEventListener('click', event => saveWithButton(event.currentTarget,'issued'));
document.getElementById('printPdfBtn').addEventListener('click', async event => {
    const button = event.currentTarget;
    const printWindow = window.open('about:blank','_blank');
    button.disabled = true;
    showLoader('Đang lưu và chuẩn bị bản in PDF...');
    try {
        const result = await save('draft', true);
        const orderId = result.data?.id;
        if (!orderId) throw new Error('Không lấy được mã lệnh vừa lưu.');
        const url = `/client/quan-ly-det/lenh/${encodeURIComponent(orderId)}/in`;
        if (printWindow) printWindow.location.href = url;
        else window.location.href = url;
        toast('Đã lưu. Chọn “Save as PDF” trong cửa sổ in.');
    } catch (error) {
        if (printWindow) printWindow.close();
        toast(error.message, true);
    } finally {
        hideLoader();
        button.disabled = false;
    }
});
document.getElementById('exportBtn').addEventListener('click', async event => {
    const button = event.currentTarget;
    const sheetWindow = window.open('about:blank','_blank');
    button.disabled = true;
    showLoader('Đang chuẩn bị file Excel...');
    try {
        await save('draft', true);
        const url = `/api/lenh-det/export-excel?production_order=${encodeURIComponent(selectedOrderCode)}`;
        if (sheetWindow) sheetWindow.location.href = url;
        else window.location.href = url;
        toast('Đang tải file Excel.');
    } catch (error) {
        if (sheetWindow) sheetWindow.close();
        toast(error.message,true);
    } finally {
        hideLoader();
        button.disabled = false;
    }
});
document.getElementById('sheetPreview').addEventListener('click', event => {
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
    if (!selectedPlan || !data.id) return;
    const order = selectedPlan.order || {};
    const item = (selectedPlan.source_items || [])[0] || {};
    if (String(order.item_code || item.item_code || '').toUpperCase() !== String(data.item_code || '').toUpperCase()) return;
    order.catalog_id = Number(data.id);
    item.catalog_id = Number(data.id);
    renderPreview();
    if (window.lucide) lucide.createIcons();
});
document.addEventListener('catalog-image-uploaded', event => {
    const data = event.detail || {};
    if (!selectedPlan || !data.id) return;
    const order = selectedPlan.order || {};
    const item = (selectedPlan.source_items || [])[0] || {};
    if (String(order.catalog_id || item.catalog_id || '') !== String(data.id)) return;
    order.image_url = data.image_url || '';
    item.image_url = data.image_url || '';
    renderPreview();
    if (window.lucide) lucide.createIcons();
});

const requestedOrder = new URLSearchParams(window.location.search).get('order');
if (requestedOrder) selectOrder(requestedOrder);
if (window.lucide) lucide.createIcons();
</script>
</body>
</html>
