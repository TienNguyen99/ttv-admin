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
        .voice-assistant { display: grid; grid-template-columns: auto minmax(220px, 420px) auto minmax(0, 1fr); gap: 8px; align-items: center; padding: 10px 12px; }
        .voice-button { width: 40px; height: 40px; padding: 0; justify-content: center; }
        .voice-button.is-listening { border-color: #dc2626; background: #fee2e2; color: #b91c1c; animation: voice-pulse 1.2s infinite; }
        .voice-result { min-width: 0; color: #334155; font-size: 13px; }
        .voice-result strong { color: #0f172a; }
        .voice-location { display: inline-flex; gap: 5px; margin: 2px 4px 2px 0; padding: 3px 7px; border: 1px solid #bfdbfe; border-radius: 5px; background: #eff6ff; color: #1d4ed8; font-size: 12px; font-weight: 700; }
        @keyframes voice-pulse { 50% { box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.12); } }
        .warehouse-map { padding: 14px; overflow-x: auto; }
        .warehouse-blueprint { position: relative; min-width: 980px; padding: 12px 14px 14px; border: 2px solid #cbd5e1; border-radius: 8px; background: #fff; }
        .blueprint-title { margin: 0 0 10px; text-align: center; color: #334155; font-size: 22px; font-weight: 900; letter-spacing: 0.06em; }
        .blueprint-top { display: grid; grid-template-columns: 118px 1fr 360px; gap: 10px; margin-bottom: 8px; }
        .blueprint-main { display: grid; grid-template-columns: 96px minmax(0, 1fr) 96px; gap: 10px; }
        .blueprint-bottom { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; margin-top: 10px; }
        .zone-box { display: grid; min-height: 46px; place-items: center; padding: 6px; border: 1px solid #94a3b8; background: rgba(248, 250, 252, 0.85); color: #334155; font-size: 11px; font-weight: 800; text-align: center; text-transform: uppercase; }
        .zone-stack { display: grid; align-content: space-between; gap: 10px; }
        .aisle-column { position: relative; min-height: 100%; border-left: 1px dashed #cbd5e1; border-right: 1px dashed #cbd5e1; }
        .aisle-column::before, .aisle-column::after { position: absolute; left: 50%; transform: translateX(-50%); color: #475569; font-size: 38px; font-weight: 900; line-height: 1; }
        .aisle-column::before { content: "↓"; top: 12px; }
        .aisle-column::after { content: "↓"; bottom: 12px; }
        .shelf-area { display: grid; gap: 8px; }
        .shelf-row { display: grid; grid-template-columns: 130px minmax(0, 1fr); gap: 8px; align-items: stretch; }
        .shelf-label { display: flex; flex-direction: column; justify-content: center; padding: 10px; border: 1px solid #94a3b8; border-radius: 0; background: rgba(248, 250, 252, 0.9); }
        .shelf-code { font-size: 18px; font-weight: 900; }
        .shelf-name { color: var(--muted); font-size: 12px; }
        .shelf-lanes { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
        .shelf-tier { min-height: 132px; border: 1px solid #94a3b8; border-radius: 0; background: rgba(255, 255, 255, 0.95); }
        .shelf-tier-title { position: relative; display: flex; justify-content: space-between; gap: 8px; padding: 7px 34px; border-bottom: 1px solid #edf2f7; color: #475569; font-size: 12px; font-weight: 800; }
        .shelf-tier-title::before { content: "←"; position: absolute; left: 10px; top: 4px; color: #475569; font-size: 18px; }
        .shelf-tier-title::after { content: "→"; position: absolute; right: 10px; top: 4px; color: #475569; font-size: 18px; }
        .shelf-tier-body { display: grid; gap: 8px; padding: 8px; }
        .map-card { border: 1px solid var(--line); border-radius: 8px; background: #fff; overflow: hidden; }
        .map-card.is-selected { border-color: #93c5fd; box-shadow: 0 0 0 3px #dbeafe; }
        .map-card.is-drop-target { border-color: #22c55e; box-shadow: 0 0 0 3px #dcfce7; }
        .map-card-header { display: flex; justify-content: space-between; gap: 10px; padding: 9px; border-bottom: 1px solid #edf2f7; background: #f8fafc; }
        .map-card-code { font-size: 14px; font-weight: 800; }
        .map-card-name { margin-top: 2px; color: var(--muted); font-size: 11px; }
        .map-card-summary { color: #1d4ed8; font-size: 11px; font-weight: 700; white-space: nowrap; }
        .map-package-list { display: grid; gap: 7px; min-height: 54px; padding: 8px; }
        .map-package { cursor: grab; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; background: #f8fafc; }
        .map-package:active { cursor: grabbing; }
        .map-package-code { color: #0f172a; font-size: 12px; font-weight: 800; overflow-wrap: anywhere; }
        .map-package-meta { margin-top: 2px; color: var(--muted); font-size: 11px; overflow-wrap: anywhere; }
        .map-empty { display: grid; min-height: 80px; place-items: center; color: #94a3b8; font-size: 12px; }
        .shelf-empty { padding: 16px 8px; color: #94a3b8; font-size: 12px; text-align: center; }
        .layout-editor-wrap { padding: 14px; overflow: auto; }
        .layout-editor {
            position: relative;
            display: grid;
            grid-template-columns: repeat(24, 40px);
            grid-template-rows: repeat(24, 32px);
            width: 960px;
            min-height: 768px;
            border: 1px solid #94a3b8;
            background-image:
                linear-gradient(to right, #e2e8f0 1px, transparent 1px),
                linear-gradient(to bottom, #e2e8f0 1px, transparent 1px);
            background-size: 40px 32px;
            background-color: #fff;
        }
        .layout-block {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-width: 0;
            padding: 7px 8px;
            border: 1px solid #2563eb;
            border-radius: 6px;
            background: #eff6ff;
            color: #1e3a8a;
            cursor: move;
            user-select: none;
            box-shadow: 0 3px 8px rgba(37, 99, 235, 0.12);
        }
        .layout-block.is-dragging { opacity: 0.72; z-index: 5; }
        .layout-block-code { font-size: 13px; font-weight: 900; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .layout-block-meta { margin-top: 2px; font-size: 11px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .layout-help { color: var(--muted); font-size: 12px; }
        @media (max-width: 1100px) { .workspace-grid { grid-template-columns: 1fr; } }
        @media (max-width: 900px) { .shelf-row { grid-template-columns: 1fr; } .shelf-lanes { grid-template-columns: 1fr; } }
        @media (max-width: 700px) { .kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .view-tabs { overflow-x: auto; } .view-tab { white-space: nowrap; } .voice-assistant { grid-template-columns: auto minmax(0, 1fr) auto; } .voice-result { grid-column: 1 / -1; } }
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
            <div class="d-flex gap-2 align-items-center">
                <button type="button" class="btn btn-outline-primary btn-icon" onclick="openLocationModal()"><i data-lucide="map-pin-plus"></i>Thêm vị trí</button>
                <select id="warehouseFlowTop" class="form-select" style="width:180px" onchange="handleWarehouseFlow(this.value)">
                    <option value="receipt">Nhập thành phẩm</option>
                    <option value="production">Xuất BTP sản xuất</option>
                    <option value="material">Xuất vật tư</option>
                </select>
                <button type="button" class="btn btn-primary btn-icon" onclick="handleWarehouseFlow(document.getElementById('warehouseFlowTop').value)"><i data-lucide="file-plus-2"></i>Mở phiếu</button>
            </div>
        </div>

        <section class="panel voice-assistant mb-3" aria-label="Trợ lý giọng nói kho">
            <button id="voiceLookupBtn" type="button" class="btn btn-outline-primary btn-icon voice-button" title="Nói mã hàng cần tìm"><i data-lucide="mic"></i></button>
            <input id="voiceLookupInput" class="form-control" placeholder="Nói hoặc nhập mã hàng, mã nội bộ">
            <button id="voiceSearchBtn" type="button" class="btn btn-outline-primary btn-icon"><i data-lucide="search"></i>Tìm</button>
            <div id="voiceLookupResult" class="voice-result">Bấm micro và nói: “Tìm mã BTPDAYHAIRB1-1”.</div>
        </section>

        <section class="kpi-grid">
            <div class="kpi-item"><div class="kpi-icon"><i data-lucide="map-pinned"></i></div><div><div id="kpiLocations" class="kpi-value">0</div><div class="kpi-label">Vị trí kho</div></div></div>
            <div class="kpi-item"><div class="kpi-icon"><i data-lucide="scan-line"></i></div><div><div id="kpiCountingLocations" class="kpi-value">0</div><div class="kpi-label">Vị trí đang kiểm</div></div></div>
            <div class="kpi-item"><div class="kpi-icon"><i data-lucide="package-check"></i></div><div><div id="kpiPackages" class="kpi-value">0</div><div class="kpi-label">Kiện trong ngày</div></div></div>
            <div class="kpi-item"><div class="kpi-icon"><i data-lucide="boxes"></i></div><div><div id="kpiQuantity" class="kpi-value">0</div><div class="kpi-label">Số lượng trong ngày</div></div></div>
        </section>

        <section class="panel context-bar mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-lg-5"><label class="form-label">Vị trí đang kiểm</label><input id="locationCode" list="locationOptions" class="form-control" placeholder="Để trống nếu chưa xếp vị trí"></div>
                <div class="col-lg-3"><label class="form-label">Mã kho</label><input id="warehouseCode" class="form-control" placeholder="KTPHAM"></div>
                <div class="col-lg-2"><label class="form-label">Ngày kiểm kê</label><input id="checkedAt" type="date" class="form-control" value="{{ now()->format('Y-m-d') }}"></div>
                <div class="col-lg-2"><button type="button" class="btn btn-outline-secondary btn-icon w-100 justify-content-center" onclick="openLocationModal(value('locationCode').toUpperCase())"><i data-lucide="settings-2"></i>Quản lý vị trí</button></div>
            </div>
            <datalist id="locationOptions"></datalist>
        </section>

        <nav class="view-tabs" aria-label="Khu vực quản lý kho">
            <button type="button" class="view-tab is-active" data-workspace-view="map" onclick="switchWorkspace('map')"><i data-lucide="map"></i>Sơ đồ kho</button>
            <button type="button" class="view-tab" data-workspace-view="editor" onclick="switchWorkspace('editor')"><i data-lucide="grid-3x3"></i>Editor layout</button>
            <button type="button" class="view-tab" data-workspace-view="overview" onclick="switchWorkspace('overview')"><i data-lucide="layout-dashboard"></i>Tổng quan vị trí</button>
            <button type="button" class="view-tab" data-workspace-view="entry" onclick="switchWorkspace('entry')"><i data-lucide="file-text"></i>Phiếu kho</button>
            <button type="button" class="view-tab" data-workspace-view="history" onclick="switchWorkspace('history')"><i data-lucide="history"></i>Lịch sử kiện</button>
        </nav>

        <section id="mapPanel" data-workspace-panel="map" class="panel mb-3">
            <div class="panel-header">
                <div><h2 class="panel-title">Sơ đồ kho kéo thả</h2><div class="section-hint mt-1">Kéo kiện sang kệ khác để chuyển vị trí trong database nội bộ.</div></div>
                <input id="mapSearch" class="form-control" style="max-width:280px" placeholder="Tìm kệ hoặc mã kiện">
            </div>
            <div id="warehouseMap" class="warehouse-map"></div>
        </section>

        <section id="editorPanel" data-workspace-panel="editor" class="panel mb-3 d-none">
            <div class="panel-header">
                <div><h2 class="panel-title">Editor sơ đồ kho</h2><div class="layout-help mt-1">Kéo vị trí trên lưới để tự dựng mặt bằng. Thả chuột là lưu vào database nội bộ.</div></div>
                <button type="button" class="btn btn-outline-primary btn-icon" onclick="renderLayoutEditor()"><i data-lucide="refresh-cw"></i>Tải lại</button>
            </div>
            <div class="layout-editor-wrap"><div id="layoutEditor" class="layout-editor"></div></div>
        </section>

        <div id="overviewPanel" data-workspace-panel="overview" class="workspace-grid mb-3 d-none">
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
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Phiếu kho</h2>
                    <div id="entryLocationContext" class="section-hint mt-1">Nếu chưa chọn vị trí, phiếu sẽ lưu vào CHUA-XEP để xếp kệ sau.</div>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <select id="warehouseFlowEntry" class="form-select" style="width:180px" onchange="handleWarehouseFlow(this.value)">
                        <option value="receipt">Nhập thành phẩm</option>
                        <option value="production">Xuất BTP sản xuất</option>
                        <option value="material">Xuất vật tư</option>
                    </select>
                    <button id="saveReceiptBatchBtn" class="btn btn-primary btn-icon"><i data-lucide="printer"></i>Lưu + in</button>
                </div>
            </div>
            <div class="panel-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-4"><label class="form-label">Ghi chú phiếu</label><input id="receiptHeaderNote" class="form-control" placeholder="Ví dụ: KCS giao kho, ca sáng"></div>
                    <div class="col-md-8 d-flex align-items-end justify-content-md-end"><span class="section-hint">Nhập tối đa 10 dòng. Mỗi dòng có Mã TP kế toán + Số lượng sẽ được lưu vào cùng một phiếu.</span></div>
                </div>
                <datalist id="receiptProductOptions"></datalist>
                <div class="table-responsive">
                    <table class="table align-middle receipt-entry-table">
                        <thead>
                            <tr>
                                <th style="width:48px">Stt</th>
                                <th style="min-width:160px">Danh mục</th>
                                <th style="min-width:180px">Mã hàng</th>
                                <th style="min-width:180px">Item code</th>
                                <th style="min-width:130px">Màu sắc</th>
                                <th style="min-width:110px">Size</th>
                                <th style="min-width:120px" class="text-end">Số lượng</th>
                                <th style="min-width:90px">Đvt</th>
                                <th style="min-width:160px">Lệnh sản xuất</th>
                            </tr>
                        </thead>
                        <tbody id="receiptEntryRows">
                            @for ($i = 1; $i <= 10; $i++)
                                <tr>
                                    <td class="text-muted fw-bold">{{ $i }}</td>
                                    <td><input class="form-control receipt-note" placeholder="TP / KCS"></td>
                                    <td><input class="form-control receipt-ma-sp" list="receiptProductOptions" autocomplete="off" placeholder="Mã TP kế toán"></td>
                                    <td><input class="form-control receipt-internal-code" placeholder="Mã nội bộ"></td>
                                    <td><input class="form-control receipt-color"></td>
                                    <td><input class="form-control receipt-size"></td>
                                    <td><input class="form-control receipt-quantity text-end" type="number" step="0.001" min="0"></td>
                                    <td><input class="form-control receipt-dvt" placeholder="Cái"></td>
                                    <td><input class="form-control receipt-order" placeholder="LSX / ghi chú"></td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section id="receiptsPanel" data-workspace-panel="entry" class="panel mb-3 d-none">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Danh sách phiếu nhập thành phẩm</h2>
                    <div id="receiptListSummary" class="section-hint mt-1">Theo ngày kiểm kê và kho đang chọn.</div>
                </div>
                <button type="button" class="btn btn-outline-primary btn-icon" onclick="loadReceipts()"><i data-lucide="refresh-cw"></i>Tải lại</button>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Số phiếu</th>
                            <th>Ngày</th>
                            <th>Kho</th>
                            <th>Vị trí</th>
                            <th class="text-end">Số dòng</th>
                            <th class="text-end">Tổng SL</th>
                            <th>Ghi chú</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="receiptRows"></tbody>
                </table>
            </div>
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
                    <div class="row g-2 mb-3">
                        <div class="col-4"><label class="form-label">Kệ</label><select id="editShelfCode" class="form-select"><option value="">Tự nhận</option><option>A</option><option>B</option><option>C</option><option>D</option><option>F</option><option>G</option></select></div>
                        <div class="col-4"><label class="form-label">Tầng</label><select id="editTier" class="form-select"><option value="1">Tầng 1</option><option value="2">Tầng 2</option></select></div>
                        <div class="col-4"><label class="form-label">Ô</label><input id="editBayCode" class="form-control" placeholder="01"></div>
                    </div>
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
    <div class="modal fade" id="movePackageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <div><h5 class="modal-title">Chuyển vị trí kiện</h5><div id="movePackageTitle" class="text-muted small"></div></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Vị trí đích</label>
                    <select id="moveTargetLocationId" class="form-select"></select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button id="confirmMovePackageBtn" type="button" class="btn btn-primary">Chuyển kiện</button>
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
        const movePackageModal = new bootstrap.Modal(document.getElementById('movePackageModal'));
        let locations = [];
        let mapPackages = [];
        let movingPackageId = null;
        let draggingLayout = null;
        let editingLocationId = null;
        let selectedAccountingProduct = '';
        let productSearchTimer;
        let voiceRecognition = null;

        function refreshIcons() {
            if (window.lucide) lucide.createIcons();
        }

        function formatNumber(value) {
            return Number(value || 0).toLocaleString('vi-VN', { maximumFractionDigits: 3 });
        }

        function updateSavePackageButton() {
            refreshIcons();
        }

        function setWarehouseFlow(value) {
            ['warehouseFlowTop', 'warehouseFlowEntry'].forEach(id => {
                const select = document.getElementById(id);
                if (select) select.value = value;
            });
        }

        function handleWarehouseFlow(value) {
            if (value === 'production' || value === 'material') {
                window.location.href = `/client/xuat-vat-tu-noi-bo?type=${value}`;
                return;
            }
            setWarehouseFlow('receipt');
            switchWorkspace('entry');
        }

        function normalizeVoiceKeyword(text) {
            let normalized = String(text || '').toUpperCase()
                .replace(/[.,?!:;]/g, ' ')
                .replace(/\s+/g, ' ');
            [
                'CHO TÔI BIẾT', 'CÒN BAO NHIÊU', 'VỊ TRÍ NÀO', 'MÃ NỘI BỘ',
                'KIỂM TRA', 'TRA CỨU', 'MÃ HÀNG', 'TỒN KHO', 'KỆ NÀO',
                'NẰM Ở', 'Ở ĐÂU', 'BAO NHIÊU', 'VỊ TRÍ', 'TÌM', 'MÃ', 'TỒN', 'NẰM', 'KỆ'
            ].forEach(phrase => {
                normalized = normalized.split(phrase).join(' ');
            });
            normalized = normalized.replace(/\s+/g, ' ').trim();

            const tokens = normalized.split(' ').filter(Boolean);
            return tokens.join('');
        }

        function speakWarehouseAnswer(text) {
            if (!('speechSynthesis' in window)) return;
            window.speechSynthesis.cancel();
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'vi-VN';
            utterance.rate = 0.95;
            window.speechSynthesis.speak(utterance);
        }

        function lookupWarehouseByVoice(rawText = '') {
            const input = document.getElementById('voiceLookupInput');
            const resultBox = document.getElementById('voiceLookupResult');
            const keyword = normalizeVoiceKeyword(rawText || input.value);
            input.value = keyword;

            if (!keyword) {
                resultBox.textContent = 'Không nhận được mã hàng. Hãy nói lại hoặc nhập mã.';
                speakWarehouseAnswer('Không nhận được mã hàng. Hãy nói lại.');
                return;
            }

            resultBox.textContent = `Đang tìm ${keyword}...`;
            fetch(`/api/kiem-ton-kho/tra-cuu-giong-noi?keyword=${encodeURIComponent(keyword)}`)
                .then(r => jsonOrError(r, 'Không tra cứu được tồn kho'))
                .then(result => {
                    const rows = result.data || [];
                    if (!rows.length) {
                        resultBox.innerHTML = `<strong>${escapeHtml(keyword)}</strong>: không có tồn trong kho nội bộ.`;
                        speakWarehouseAnswer(`Mã ${keyword} hiện không có tồn trong kho nội bộ.`);
                        return;
                    }

                    const byLocation = {};
                    rows.forEach(row => {
                        const location = row.location_code || 'CHUA-XEP';
                        byLocation[location] = (byLocation[location] || 0) + Number(row.total_quantity || 0);
                    });
                    const locationsText = Object.entries(byLocation)
                        .map(([location, quantity]) => `${location}: ${formatNumber(quantity)}`)
                        .join(', ');
                    const locationsHtml = Object.entries(byLocation)
                        .map(([location, quantity]) => `<span class="voice-location">${escapeHtml(location)} · ${formatNumber(quantity)}</span>`)
                        .join('');
                    const total = formatNumber(result.summary?.total_quantity || 0);

                    resultBox.innerHTML = `<strong>${escapeHtml(keyword)}</strong> · Tổng ${total} ${locationsHtml}`;
                    speakWarehouseAnswer(`Mã ${keyword} còn tổng ${total}. ${locationsText}.`);
                })
                .catch(error => {
                    resultBox.textContent = error.message;
                    speakWarehouseAnswer('Không tra cứu được tồn kho.');
                });
        }

        function startVoiceLookup() {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            const button = document.getElementById('voiceLookupBtn');
            const resultBox = document.getElementById('voiceLookupResult');

            if (!SpeechRecognition) {
                resultBox.textContent = 'Trình duyệt không hỗ trợ nhận diện giọng nói. Hãy dùng Chrome hoặc Edge.';
                return;
            }

            if (voiceRecognition) {
                voiceRecognition.stop();
                return;
            }

            voiceRecognition = new SpeechRecognition();
            voiceRecognition.lang = 'vi-VN';
            voiceRecognition.interimResults = false;
            voiceRecognition.maxAlternatives = 3;
            button.classList.add('is-listening');
            resultBox.textContent = 'Đang nghe...';

            voiceRecognition.onresult = event => {
                const transcript = event.results[0][0].transcript;
                document.getElementById('voiceLookupInput').value = transcript;
                lookupWarehouseByVoice(transcript);
            };
            voiceRecognition.onerror = event => {
                resultBox.textContent = event.error === 'not-allowed'
                    ? 'Chưa được cấp quyền micro cho trình duyệt.'
                    : 'Không nghe rõ. Hãy thử nói lại.';
            };
            voiceRecognition.onend = () => {
                button.classList.remove('is-listening');
                voiceRecognition = null;
            };
            voiceRecognition.start();
        }

        function switchWorkspace(view) {
            document.querySelectorAll('[data-workspace-panel]').forEach(panel => {
                panel.classList.toggle('d-none', panel.dataset.workspacePanel !== view);
            });
            document.querySelectorAll('[data-workspace-view]').forEach(tab => {
                tab.classList.toggle('is-active', tab.dataset.workspaceView === view);
            });
            if (view === 'editor') renderLayoutEditor();
            if (view === 'entry') loadReceipts();
            if (view === 'entry') {
                setWarehouseFlow('receipt');
                const firstReceiptInput = document.querySelector('.receipt-ma-sp');
                if (firstReceiptInput) firstReceiptInput.focus();
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }

        function hideProductResults() {
            const results = document.getElementById('maSpResults');
            if (results) results.classList.add('d-none');
        }

        function selectAccountingProduct(code) {
            const input = document.getElementById('maSp');
            if (!input) return;
            input.value = code;
            selectedAccountingProduct = code;
            hideProductResults();
        }

        function searchAccountingProducts() {
            if (!document.getElementById('maSp') || !document.getElementById('maSpResults')) return;
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

        function searchReceiptProducts(input) {
            const keyword = input.value.trim();
            const options = document.getElementById('receiptProductOptions');
            clearTimeout(productSearchTimer);
            if (!keyword || !options) return;

            productSearchTimer = setTimeout(() => {
                fetch(`/api/thanh-pham-ke-toan/goi-y?keyword=${encodeURIComponent(keyword)}`)
                    .then(r => jsonOrError(r, 'Không tải được mã TP kế toán'))
                    .then(result => {
                        options.innerHTML = (result.data || []).map(item => {
                            const code = escapeHtml(item.Ma_sp || '');
                            const name = escapeHtml(item.Ten_hh || '');
                            const dvt = escapeHtml(item.Dvt || '');
                            return `<option value="${code}" label="${name}${dvt ? ' - ' + dvt : ''}"></option>`;
                        }).join('');
                    })
                    .catch(() => {});
            }, 250);
        }

        function receiptLineNote(row) {
            return row.querySelector('.receipt-order')?.value.trim() || '';
        }

        function collectReceiptLines() {
            return Array.from(document.querySelectorAll('#receiptEntryRows tr'))
                .map(row => ({
                    category: row.querySelector('.receipt-note')?.value.trim() || '',
                    ma_sp: row.querySelector('.receipt-ma-sp')?.value.trim() || '',
                    internal_item_code: row.querySelector('.receipt-internal-code')?.value.trim() || '',
                    size: row.querySelector('.receipt-size')?.value.trim() || '',
                    color: row.querySelector('.receipt-color')?.value.trim() || '',
                    side: '',
                    dvt: row.querySelector('.receipt-dvt')?.value.trim() || '',
                    quantity: row.querySelector('.receipt-quantity')?.value || '',
                    note: receiptLineNote(row),
                }))
                .filter(line => line.ma_sp || line.internal_item_code || line.quantity);
        }

        function clearReceiptLines() {
            document.querySelectorAll('#receiptEntryRows input').forEach(input => input.value = '');
            document.getElementById('receiptHeaderNote').value = '';
        }

        function selectedLocation() {
            const code = value('locationCode').toUpperCase();
            return locations.find(location => location.location_code === code);
        }

        function fillSelectedLocation() {
            const location = selectedLocation();
            if (!location) {
                if (!value('locationCode')) {
                    document.getElementById('entryLocationContext').textContent = 'Chưa chọn vị trí: kiện sẽ lưu vào CHUA-XEP để xếp kệ sau.';
                }
                return;
            }
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
                renderLayoutEditor();
                fillSelectedLocation();
            });
        }

        function normalizeLayout(location, index) {
            return {
                x: Number(location.grid_x || ((index % 6) * 4 + 1)),
                y: Number(location.grid_y || (Math.floor(index / 6) * 3 + 1)),
                w: Number(location.grid_w || 4),
                h: Number(location.grid_h || 2),
            };
        }

        function renderLayoutEditor() {
            const editor = document.getElementById('layoutEditor');
            if (!editor) return;
            editor.innerHTML = locations.map((location, index) => {
                const layout = normalizeLayout(location, index);
                return `<div class="layout-block" data-location-id="${location.id}" style="grid-column:${layout.x} / span ${layout.w}; grid-row:${layout.y} / span ${layout.h};">
                    <div class="layout-block-code">${escapeHtml(location.location_code)}</div>
                    <div class="layout-block-meta">Kệ ${escapeHtml(location.shelf_code || shelfCodeForLocation(location.location_code))} · Tầng ${escapeHtml(location.tier || 1)}${location.bay_code ? ` · Ô ${escapeHtml(location.bay_code)}` : ''}</div>
                </div>`;
            }).join('');
        }

        function saveLocationLayout(locationId, gridX, gridY, gridW, gridH) {
            return fetch(`/api/kiem-ton-kho/vi-tri/${locationId}/layout`, {
                method: 'PATCH',
                headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify({ grid_x: gridX, grid_y: gridY, grid_w: gridW, grid_h: gridH })
            }).then(r => jsonOrError(r, 'Không lưu được layout vị trí'));
        }

        function loadWarehouseStats() {
            const params = new URLSearchParams({ checked_at: value('checkedAt') });
            return fetch(`/api/kiem-ton-kho/kien?${params}`).then(r => r.json()).then(result => {
                document.getElementById('kpiPackages').textContent = formatNumber(result.summary?.package_count);
                document.getElementById('kpiQuantity').textContent = formatNumber(result.summary?.total_quantity);
            });
        }

        const warehouseShelves = [
            { code: 'G', name: 'GRS' },
            { code: 'F', name: 'NPL khác' },
            { code: 'D', name: 'NPL khác' },
            { code: 'C', name: 'Thành phẩm' },
            { code: 'B', name: 'Thành phẩm' },
            { code: 'A', name: 'NPL sợi + su' },
        ];

        function shelfCodeForLocation(locationCode) {
            const match = String(locationCode || '').toUpperCase().match(/[A-Z]/);
            return match ? match[0] : 'KHAC';
        }

        function shelfForLocation(location) {
            return location?.shelf_code || shelfCodeForLocation(location?.location_code);
        }

        function tierForLocationModel(location) {
            return String(location?.tier || tierForLocation(location?.location_code));
        }

        function tierForLocation(locationCode) {
            const code = String(locationCode || '').toUpperCase();
            return /(^|[-_\s])T?2($|[-_\s])|TANG\s*2|TẦNG\s*2/.test(code) ? '2' : '1';
        }

        function loadWarehouseMap() {
            const params = new URLSearchParams({ checked_at: value('checkedAt'), limit: 1000 });
            return fetch(`/api/kiem-ton-kho/kien?${params}`).then(r => r.json()).then(result => {
                mapPackages = result.data || [];
                renderWarehouseMap();
            });
        }

        function renderWarehouseMap() {
            const keyword = value('mapSearch').toUpperCase();
            const selectedCode = value('locationCode').toUpperCase();
            const packagesByLocation = mapPackages.reduce((map, item) => {
                const code = item.location?.location_code || '';
                if (!map[code]) map[code] = [];
                map[code].push(item);
                return map;
            }, {});
            const visibleLocations = locations.filter(location => {
                const packages = packagesByLocation[location.location_code] || [];
                const text = `${location.location_code} ${location.warehouse_code || ''} ${location.location_name || ''} ${packages.map(item => `${item.package_code} ${item.ma_sp} ${item.internal_item_code}`).join(' ')}`.toUpperCase();
                return text.includes(keyword);
            });

            const renderLocationCard = location => {
                const packages = packagesByLocation[location.location_code] || [];
                const totalQuantity = packages.reduce((sum, item) => sum + Number(item.quantity || 0), 0);
                return `<article class="map-card ${location.location_code === selectedCode ? 'is-selected' : ''}" data-location-id="${location.id}" data-location-code="${location.location_code}">
                    <div class="map-card-header">
                        <button type="button" class="btn p-0 border-0 text-start" onclick="selectLocation('${location.location_code}')">
                            <div class="map-card-code">${escapeHtml(location.location_code)}</div>
                            <div class="map-card-name">${escapeHtml(location.warehouse_code || '-')}${location.location_name ? ` · ${escapeHtml(location.location_name)}` : ''}</div>
                        </button>
                        <div class="map-card-summary">${packages.length} kiện<br>SL ${formatNumber(totalQuantity)}</div>
                    </div>
                    <div class="map-package-list">
                        ${packages.map(item => `<div class="map-package" draggable="true" data-package-id="${item.id}">
                            <div class="map-package-code">${escapeHtml(item.internal_item_code || item.ma_sp || item.package_code)}</div>
                            <div class="map-package-meta">${escapeHtml(item.package_code)} · ${escapeHtml(item.ma_sp)} · SL ${formatNumber(item.quantity)}</div>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="openMovePackageModal('${item.id}')">Chuyển</button>
                        </div>`).join('') || '<div class="map-empty">Kệ trống</div>'}
                    </div>
                </article>`;
            };

            const rows = warehouseShelves.map(shelf => {
                const tierOne = visibleLocations.filter(location => shelfForLocation(location) === shelf.code && tierForLocationModel(location) === '1');
                const tierTwo = visibleLocations.filter(location => shelfForLocation(location) === shelf.code && tierForLocationModel(location) === '2');
                return `<section class="shelf-row">
                    <div class="shelf-label"><div class="shelf-code">Kệ ${shelf.code}</div><div class="shelf-name">${shelf.name}</div></div>
                    <div class="shelf-lanes">
                        <div class="shelf-tier"><div class="shelf-tier-title"><span>Tầng 1</span><span>${tierOne.length} vị trí</span></div><div class="shelf-tier-body">${tierOne.map(renderLocationCard).join('') || '<div class="shelf-empty">Chưa có vị trí tầng 1</div>'}</div></div>
                        <div class="shelf-tier"><div class="shelf-tier-title"><span>Tầng 2</span><span>${tierTwo.length} vị trí</span></div><div class="shelf-tier-body">${tierTwo.map(renderLocationCard).join('') || '<div class="shelf-empty">Chưa có vị trí tầng 2</div>'}</div></div>
                    </div>
                </section>`;
            });

            const knownShelfCodes = warehouseShelves.map(shelf => shelf.code);
            const otherLocations = visibleLocations.filter(location => !knownShelfCodes.includes(shelfForLocation(location)));
            if (otherLocations.length) {
                rows.push(`<section class="shelf-row">
                    <div class="shelf-label"><div class="shelf-code">Khác</div><div class="shelf-name">Chưa phân kệ</div></div>
                    <div class="shelf-lanes"><div class="shelf-tier" style="grid-column:1 / -1"><div class="shelf-tier-title"><span>Vị trí khác</span><span>${otherLocations.length} vị trí</span></div><div class="shelf-tier-body">${otherLocations.map(renderLocationCard).join('')}</div></div></div>
                </section>`);
            }

            document.getElementById('warehouseMap').innerHTML = `<div class="warehouse-blueprint">
                <h3 class="blueprint-title">SƠ ĐỒ KHO</h3>
                <div class="blueprint-top">
                    <div class="zone-box">KV để pallet</div>
                    <div></div>
                    <div class="d-grid gap-2" style="grid-template-columns: repeat(3, 1fr)">
                        <div class="zone-box">Khu vực hàng trả về chờ xử lý</div>
                        <div class="zone-box">KV vật tư không phù hợp</div>
                        <div class="zone-box">KV thành phẩm không phù hợp</div>
                    </div>
                </div>
                <div class="blueprint-main">
                    <aside class="zone-stack">
                        <div class="aisle-column"></div>
                        <div class="zone-box">KV để xe nâng</div>
                        <div class="zone-box">Bảng chờ lệnh kế</div>
                        <div class="zone-box">Hàng chờ sắp xếp</div>
                        <div class="zone-box">Bán lẻ</div>
                    </aside>
                    <section class="shelf-area">${rows.join('')}</section>
                    <aside class="zone-stack"><div class="aisle-column"></div></aside>
                </div>
                <div class="blueprint-bottom">
                    <div class="zone-box">TP nhận đợt việt tiến</div>
                    <div class="zone-box">TP nhận đợt khác</div>
                    <div class="zone-box">Bàn soạn hàng</div>
                    <div class="zone-box">Cổng cửa xe</div>
                    <div class="zone-box">Chi tiết label</div>
                </div>
            </div>`;
            refreshIcons();
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
            renderWarehouseMap();
            loadPackages();
            loadLocationContents();
        }

        function openLocationModal(locationCode = '') {
            const location = locations.find(item => item.location_code === locationCode) || selectedLocation();
            editingLocationId = location?.id || null;
            document.getElementById('locationModalTitle').textContent = location ? 'Chỉnh sửa vị trí kho' : 'Thêm vị trí kho';
            document.getElementById('editLocationCode').value = location?.location_code || '';
            document.getElementById('editWarehouseCode').value = location?.warehouse_code || value('warehouseCode');
            document.getElementById('editShelfCode').value = location?.shelf_code || '';
            document.getElementById('editTier').value = location?.tier || 1;
            document.getElementById('editBayCode').value = location?.bay_code || '';
            document.getElementById('editLocationName').value = location?.location_name || '';
            document.getElementById('deleteLocationBtn').classList.toggle('d-none', !location);
            document.getElementById('useLocationBtn').classList.toggle('d-none', !location);
            document.getElementById('printLocationBtn').classList.toggle('d-none', !location);
            setLocationStatus('');
            locationModal.show();
        }

        function openMovePackageModal(packageId) {
            const item = mapPackages.find(packageItem => String(packageItem.id) === String(packageId));
            movingPackageId = packageId;
            document.getElementById('movePackageTitle').textContent = item ? `${item.package_code} · ${item.internal_item_code || item.ma_sp}` : '';
            document.getElementById('moveTargetLocationId').innerHTML = locations.map(location => `<option value="${location.id}" ${item?.warehouse_location_id === location.id ? 'selected' : ''}>${escapeHtml(location.location_code)} · Kệ ${escapeHtml(location.shelf_code || shelfCodeForLocation(location.location_code))} · Tầng ${escapeHtml(location.tier || tierForLocation(location.location_code))}</option>`).join('');
            movePackageModal.show();
        }

        function movePackageToLocation(packageId, locationId) {
            return fetch(`/api/kiem-ton-kho/kien/${packageId}/chuyen-vi-tri`, {
                method: 'PATCH',
                headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify({ warehouse_location_id: locationId })
            }).then(r => jsonOrError(r, 'Không chuyển được kiện'));
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

        function loadReceipts() {
            const params = new URLSearchParams({ receipt_date: value('checkedAt') });
            if (value('warehouseCode')) params.set('warehouse_code', value('warehouseCode').toUpperCase());
            fetch(`/api/kiem-ton-kho/phieu-nhap-tp?${params}`).then(r => r.json()).then(result => {
                const rows = result.data || [];
                document.getElementById('receiptRows').innerHTML = rows.map(receipt => `<tr>
                    <td><strong>${escapeHtml(receipt.receipt_code)}</strong></td>
                    <td>${escapeHtml(receipt.receipt_date || '')}</td>
                    <td>${escapeHtml(receipt.warehouse_code || '')}</td>
                    <td>${escapeHtml(receipt.location_code || '')}</td>
                    <td class="text-end">${formatNumber(receipt.lines_count || 0)}</td>
                    <td class="text-end">${formatNumber(receipt.total_quantity || 0)}</td>
                    <td>${escapeHtml(receipt.note || '')}</td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary btn-icon" target="_blank" href="${receipt.print_url}"><i data-lucide="printer"></i>In lại</a>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-receipt-btn" data-id="${receipt.id}" data-code="${escapeHtml(receipt.receipt_code)}"><i data-lucide="trash-2"></i>Xóa</button>
                    </td>
                </tr>`).join('') || '<tr><td colspan="8" class="empty-state text-center">Chưa có phiếu nhập trong ngày/kho đang chọn</td></tr>';
                document.getElementById('receiptListSummary').textContent = `${formatNumber(result.summary?.receipt_count || 0)} phiếu · ${formatNumber(result.summary?.line_count || 0)} dòng · SL ${formatNumber(result.summary?.total_quantity || 0)}`;
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
                body: JSON.stringify({
                    location_code:value('editLocationCode'), warehouse_code:value('editWarehouseCode'),
                    shelf_code:value('editShelfCode'), tier:value('editTier'), bay_code:value('editBayCode'),
                    location_name:value('editLocationName')
                })
            }).then(r => jsonOrError(r, 'Không lưu được vị trí'))
              .then(result => {
                  setLocationStatus(`Đã lưu ${result.data.location_code} / ${result.data.warehouse_code || 'chưa có mã kho'}`);
                  document.getElementById('locationCode').value = result.data.location_code;
                  document.getElementById('warehouseCode').value = result.data.warehouse_code || '';
                  loadLocations();
                  loadPackages();
                  loadWarehouseStats();
                  loadWarehouseMap();
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
                  loadWarehouseMap();
                  loadLocationContents();
              }).catch(e => { setLocationStatus(e.message, true); alert(e.message); });
        });

        document.getElementById('saveReceiptBatchBtn').addEventListener('click', () => {
            const lines = collectReceiptLines();
            const validLines = lines.filter(line => line.ma_sp && Number(line.quantity || 0) > 0);
            if (!validLines.length) return alert('Nhập ít nhất 1 dòng có Mã hàng và Số lượng lớn hơn 0.');
            const printWindow = window.open('', '_blank');

            fetch('/api/kiem-ton-kho/phieu-nhap-tp', {
                method: 'POST', headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify({
                    location_code: value('locationCode'),
                    ma_ko: value('warehouseCode'),
                    checked_at: value('checkedAt'),
                    note: value('receiptHeaderNote'),
                    lines: validLines
                })
            }).then(r => jsonOrError(r, 'Không lưu được phiếu nhập'))
              .then(result => {
                  if (result.receipt_print_url && printWindow) {
                      printWindow.location.href = result.receipt_print_url;
                  } else if (result.receipt_print_url) {
                      window.location.href = result.receipt_print_url;
                  }
                  clearReceiptLines();
                  loadPackages();
                  loadLocations();
                  loadWarehouseStats();
                  loadWarehouseMap();
                  loadReceipts();
                  loadLocationContents();
              }).catch(e => {
                  if (printWindow) printWindow.close();
                  alert(e.message);
              });
        });

        document.getElementById('packageRows').addEventListener('click', event => {
            const button = event.target.closest('.delete-package-btn');
            if (!button || !confirm(`Xóa kiện ${button.dataset.code}? Số lượng sẽ được trừ khỏi đối chiếu tồn.`)) return;
            fetch(`/api/kiem-ton-kho/kien/${button.dataset.id}`, {
                method: 'DELETE', headers: {'Accept':'application/json','X-CSRF-TOKEN':csrfToken}
            }).then(r => jsonOrError(r, 'Không xóa được kiện'))
              .then(() => { loadPackages(); loadLocations(); loadWarehouseStats(); loadWarehouseMap(); loadLocationContents(); })
              .catch(e => alert(e.message));
        });

        document.getElementById('receiptRows').addEventListener('click', event => {
            const button = event.target.closest('.delete-receipt-btn');
            if (!button || !confirm(`Xóa phiếu nhập ${button.dataset.code}? Toàn bộ kiện và số tồn nội bộ tạo từ phiếu này sẽ bị trừ lại.`)) return;
            fetch(`/api/kiem-ton-kho/phieu-nhap-tp/${button.dataset.id}`, {
                method: 'DELETE', headers: {'Accept':'application/json','X-CSRF-TOKEN':csrfToken}
            }).then(r => jsonOrError(r, 'Không xóa được phiếu nhập'))
              .then(() => {
                  loadReceipts();
                  loadPackages();
                  loadLocations();
                  loadWarehouseStats();
                  loadWarehouseMap();
                  loadLocationContents();
              })
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
        document.getElementById('locationCode').addEventListener('change', () => { fillSelectedLocation(); renderLocations(); loadPackages(); loadReceipts(); loadLocationContents(); });
        document.getElementById('locationSearch').addEventListener('input', renderLocations);
        document.getElementById('mapSearch').addEventListener('input', renderWarehouseMap);
        document.getElementById('layoutEditor').addEventListener('pointerdown', event => {
            const block = event.target.closest('.layout-block');
            if (!block) return;
            const location = locations.find(item => String(item.id) === String(block.dataset.locationId));
            if (!location) return;
            const layout = normalizeLayout(location, 0);
            draggingLayout = {
                block,
                location,
                startX: event.clientX,
                startY: event.clientY,
                gridX: layout.x,
                gridY: layout.y,
                gridW: layout.w,
                gridH: layout.h,
            };
            block.classList.add('is-dragging');
            block.setPointerCapture(event.pointerId);
        });
        document.getElementById('layoutEditor').addEventListener('pointermove', event => {
            if (!draggingLayout) return;
            const deltaX = Math.round((event.clientX - draggingLayout.startX) / 40);
            const deltaY = Math.round((event.clientY - draggingLayout.startY) / 32);
            const nextX = Math.min(24, Math.max(1, draggingLayout.gridX + deltaX));
            const nextY = Math.min(40, Math.max(1, draggingLayout.gridY + deltaY));
            draggingLayout.block.style.gridColumn = `${nextX} / span ${draggingLayout.gridW}`;
            draggingLayout.block.style.gridRow = `${nextY} / span ${draggingLayout.gridH}`;
        });
        document.getElementById('layoutEditor').addEventListener('pointerup', event => {
            if (!draggingLayout) return;
            const deltaX = Math.round((event.clientX - draggingLayout.startX) / 40);
            const deltaY = Math.round((event.clientY - draggingLayout.startY) / 32);
            const nextX = Math.min(24, Math.max(1, draggingLayout.gridX + deltaX));
            const nextY = Math.min(40, Math.max(1, draggingLayout.gridY + deltaY));
            const currentDrag = draggingLayout;
            currentDrag.block.classList.remove('is-dragging');
            draggingLayout = null;
            saveLocationLayout(currentDrag.location.id, nextX, nextY, currentDrag.gridW, currentDrag.gridH)
                .then(result => {
                    const index = locations.findIndex(item => item.id === result.data.id);
                    if (index >= 0) locations[index] = result.data;
                    renderWarehouseMap();
                })
                .catch(error => {
                    alert(error.message);
                    renderLayoutEditor();
                });
        });
        document.getElementById('warehouseMap').addEventListener('dragstart', event => {
            const item = event.target.closest('.map-package');
            if (!item) return;
            event.dataTransfer.setData('text/plain', item.dataset.packageId);
            event.dataTransfer.effectAllowed = 'move';
        });
        document.getElementById('warehouseMap').addEventListener('dragover', event => {
            const card = event.target.closest('.map-card');
            if (!card) return;
            event.preventDefault();
            card.classList.add('is-drop-target');
        });
        document.getElementById('warehouseMap').addEventListener('dragleave', event => {
            const card = event.target.closest('.map-card');
            if (card) card.classList.remove('is-drop-target');
        });
        document.getElementById('warehouseMap').addEventListener('drop', event => {
            const card = event.target.closest('.map-card');
            const packageId = event.dataTransfer.getData('text/plain');
            if (!card || !packageId) return;
            event.preventDefault();
            card.classList.remove('is-drop-target');
            movePackageToLocation(packageId, card.dataset.locationId)
              .then(() => {
                  selectLocation(card.dataset.locationCode);
                  loadWarehouseStats();
                  loadWarehouseMap();
              }).catch(e => alert(e.message));
        });
        document.getElementById('confirmMovePackageBtn').addEventListener('click', () => {
            const locationId = document.getElementById('moveTargetLocationId').value;
            const location = locations.find(item => String(item.id) === String(locationId));
            if (!movingPackageId || !locationId) return;
            movePackageToLocation(movingPackageId, locationId)
                .then(() => {
                    movePackageModal.hide();
                    selectLocation(location.location_code);
                    loadWarehouseStats();
                    loadWarehouseMap();
                }).catch(e => alert(e.message));
        });
        document.getElementById('receiptEntryRows').addEventListener('input', event => {
            if (event.target.classList.contains('receipt-ma-sp')) searchReceiptProducts(event.target);
        });
        document.getElementById('voiceLookupBtn').addEventListener('click', startVoiceLookup);
        document.getElementById('voiceSearchBtn').addEventListener('click', () => lookupWarehouseByVoice());
        document.getElementById('voiceLookupInput').addEventListener('keydown', event => {
            if (event.key === 'Enter') lookupWarehouseByVoice();
        });
        document.addEventListener('click', event => {
            if (!event.target.closest('.product-search')) hideProductResults();
        });
        document.getElementById('checkedAt').addEventListener('change', () => { loadPackages(); loadReceipts(); loadWarehouseStats(); loadWarehouseMap(); loadLocationContents(); });
        const requestedLocation = new URLSearchParams(window.location.search).get('location_code');
        if (requestedLocation) document.getElementById('locationCode').value = requestedLocation.toUpperCase();
        loadLocations().then(() => { loadPackages(); loadReceipts(); loadWarehouseStats(); loadWarehouseMap(); loadLocationContents(); });
        updateSavePackageButton();
        refreshIcons();
    </script>
</body>
</html>
