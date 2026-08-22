<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Nhập thành phẩm nhanh</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --quick-blue: #2563eb;
            --quick-blue-soft: #eaf4ff;
            --quick-line: #c7d7ee;
            --quick-ink: #0f172a;
            --quick-muted: #64748b;
            --quick-bg: #f5faff;
            --quick-good: #15803d;
            --quick-warn: #b45309;
        }

        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            margin: 0;
            background: var(--quick-bg);
            color: var(--quick-ink);
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .quick-shell {
            width: min(1500px, calc(100% - 28px));
            margin: 0 auto;
            padding: 12px 0 24px;
        }

        .quick-choice {
            width: min(880px, calc(100% - 28px));
            min-height: 100vh;
            margin: 0 auto;
            display: grid;
            place-content: center;
            gap: 20px;
            padding: 24px 0;
        }

        .quick-choice h1 {
            margin: 0;
            color: #0f2f63;
            font-size: 30px;
            font-weight: 800;
            letter-spacing: 0;
            text-align: center;
        }

        .quick-choice-groups {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(260px, 1fr);
            gap: 12px;
            align-items: stretch;
        }

        .quick-choice-group {
            padding: 14px;
            border: 1px solid #d6e4f7;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 10px 28px rgba(37, 99, 235, 0.08);
        }

        .quick-choice-group-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 0 10px;
            color: #64748b;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .quick-choice-group-title svg {
            width: 16px;
            height: 16px;
        }

        .quick-choice-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .quick-choice-actions.is-single {
            grid-template-columns: 1fr;
            height: calc(100% - 28px);
        }

        .quick-choice-btn {
            min-height: 116px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 16px;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            background: #f8fbff;
            color: #0f2f63;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: border-color 180ms ease, background-color 180ms ease, color 180ms ease, transform 180ms ease;
        }

        .quick-choice-btn:hover,
        .quick-choice-btn:focus-visible {
            border-color: #2563eb;
            background: #eaf4ff;
            color: #1d4ed8;
            transform: translateY(-2px);
            outline: none;
        }

        .quick-choice-btn svg { width: 30px; height: 30px; }
        .quick-choice-btn strong { font-size: 17px; font-weight: 800; }
        .quick-choice-btn small { color: #64748b; font-size: 12px; font-weight: 600; }

        .quick-choice-btn.is-issue {
            height: 100%;
            border-color: #93c5fd;
            background: #eaf4ff;
            color: #1d4ed8;
        }

        .quick-header {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 14px;
            align-items: center;
            margin-bottom: 12px;
            padding: 12px 14px;
            border: 1px solid #dbeafe;
            border-radius: 16px;
            background: rgba(255,255,255,.92);
            box-shadow: 0 12px 30px rgba(37, 99, 235, 0.08);
        }

        .quick-title {
            margin: 0;
            color: #0f2f63;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: 0;
        }

        .quick-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-end;
        }

        .quick-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 40px;
            padding: 0 14px;
            border: 1px solid var(--quick-line);
            border-radius: 12px;
            background: #ffffff;
            color: #0f2f63;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 180ms ease, border-color 180ms ease, color 180ms ease, box-shadow 180ms ease;
        }

        .quick-btn:hover {
            border-color: #93c5fd;
            background: var(--quick-blue-soft);
            color: var(--quick-blue);
        }

        .quick-btn:disabled {
            cursor: wait;
            opacity: 0.65;
        }

        .quick-btn-primary {
            min-width: 160px;
            border-color: var(--quick-blue);
            background: var(--quick-blue);
            color: #ffffff;
            box-shadow: 0 12px 24px rgba(30, 64, 175, 0.22);
        }

        .quick-btn-primary:hover {
            background: #1d4ed8;
            color: #ffffff;
        }

        .quick-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            align-items: start;
        }

        .quick-panel {
            border: 1px solid #dbe3ef;
            border-radius: 16px;
            background: #ffffff;
            box-shadow: 0 10px 24px rgba(37, 99, 235, 0.06);
            overflow: hidden;
        }

        .quick-panel-header {
            display: flex;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            padding: 12px 14px;
            border-bottom: 1px solid #e2e8f0;
        }

        .quick-panel-title {
            margin: 0;
            font-size: 17px;
            font-weight: 800;
        }

        .quick-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            justify-content: flex-end;
        }

        .quick-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-height: 36px;
            padding: 0 12px;
            border: 1px solid #dbeafe;
            border-radius: 999px;
            background: #f8fbff;
            color: #0f2f63;
            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;
        }

        .quick-pill strong {
            color: #1d4ed8;
            font-size: 16px;
        }

        .quick-form-row {
            display: grid;
            grid-template-columns: 190px 190px minmax(280px, 1fr);
            gap: 10px;
            padding: 12px 14px;
            border-bottom: 1px solid #eef2f7;
        }

        .quick-export-panel {
            display: grid;
            grid-template-columns: minmax(230px, .7fr) minmax(220px, 1fr) 190px;
            gap: 12px;
            align-items: center;
            padding: 12px 14px;
            border-bottom: 1px solid #dbeafe;
            background: #eef7ff;
        }

        .quick-export-switch {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 40px;
            padding: 8px 12px;
            border: 1px solid #93c5fd;
            border-radius: 8px;
            background: #ffffff;
        }

        .quick-export-switch .form-check-input {
            width: 2.5rem;
            height: 1.35rem;
            margin: 0;
            cursor: pointer;
        }

        .quick-export-switch label {
            margin: 0;
            cursor: pointer;
            color: #0f2f63;
            font-size: 14px;
        }

        .quick-export-switch.is-active {
            border-color: #2563eb;
            background: #dbeafe;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        label {
            margin-bottom: 6px;
            color: #334155;
            font-size: 13px;
            font-weight: 800;
        }

        .form-control,
        .form-select {
            min-height: 40px;
            border-color: #b8c7dc;
            border-radius: 10px;
            color: var(--quick-ink);
            font-weight: 600;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .quick-table-wrap {
            overflow-x: auto;
            padding: 0 14px 12px;
        }

        .quick-table {
            width: 100%;
            min-width: 1120px;
            border-collapse: separate;
            border-spacing: 0;
        }

        .quick-table th {
            position: sticky;
            top: 0;
            z-index: 1;
            padding: 9px 8px;
            background: #08213d;
            color: #ffffff;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .quick-table td {
            padding: 6px;
            border-bottom: 1px solid #e2e8f0;
            background: #ffffff;
            vertical-align: top;
        }

        .quick-table tbody tr:focus-within td {
            background: #f8fbff;
        }

        .quick-row-index {
            width: 44px;
            color: #475569;
            font-weight: 800;
            text-align: center;
        }

        .quick-order { min-width: 145px; }
        .quick-code { min-width: 180px; }
        .quick-name { min-width: 230px; }
        .quick-size { min-width: 96px; }
        .quick-color { min-width: 170px; }
        .quick-qty { min-width: 118px; }
        .quick-unit { min-width: 96px; }
        .quick-note { min-width: 180px; }

        .quick-name-cell {
            display: grid;
            grid-template-columns: 42px minmax(0, 1fr);
            gap: 8px;
            align-items: center;
        }

        .quick-item-image {
            display: grid;
            width: 42px;
            height: 42px;
            place-items: center;
            overflow: hidden;
            border: 1px solid #dbeafe;
            border-radius: 10px;
            background: #f8fbff;
            color: #94a3b8;
        }

        .quick-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .quick-item-image.is-empty::before {
            content: "Ảnh";
            font-size: 10px;
            font-weight: 800;
        }

        .row-state {
            min-height: 18px;
            margin-top: 4px;
            color: var(--quick-muted);
            font-size: 11px;
            font-weight: 700;
        }

        .row-state.is-ok { color: var(--quick-good); }
        .row-state.is-warn { color: var(--quick-warn); }

        .quick-recent {
            padding: 0 14px 14px;
        }

        .quick-recent summary {
            cursor: pointer;
            color: #0f2f63;
        }

        .recent-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
        }

        .recent-code {
            color: #0f2f63;
            font-weight: 800;
        }

        .recent-main {
            min-width: 0;
        }

        .recent-meta,
        .recent-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
        }

        .recent-flow {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 2px 8px;
            border: 1px solid #bfdbfe;
            border-radius: 999px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 11px;
            font-weight: 800;
        }

        .recent-flow.is-issued {
            border-color: #a7f3d0;
            background: #ecfdf5;
            color: #047857;
        }

        .quick-status {
            min-height: 22px;
            padding: 0 14px 10px;
            color: var(--quick-muted);
            font-size: 13px;
            font-weight: 700;
        }

        .quick-status.is-error { color: #b91c1c; }
        .quick-status.is-ok { color: var(--quick-good); }
        .order-history-toast {
            position: fixed;
            right: 18px;
            bottom: 18px;
            z-index: 2100;
            width: min(460px, calc(100vw - 36px));
            padding: 13px 15px;
            border: 1px solid #bfdbfe;
            border-left: 4px solid #2563eb;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 18px 48px rgba(15, 46, 90, .22);
            color: #17375e;
            opacity: 0;
            transform: translateY(12px);
            visibility: hidden;
            transition: opacity .18s ease, transform .18s ease, visibility .18s ease;
        }
        .order-history-toast.is-visible { opacity: 1; transform: translateY(0); visibility: visible; }
        .order-history-toast strong { display: block; margin-bottom: 5px; color: #0f2f63; font-size: 14px; }
        .order-history-toast span { display: block; white-space: pre-line; font-size: 12px; line-height: 1.55; }
        .operation-loader {
            position: fixed;
            inset: 0;
            z-index: 2000;
            display: grid;
            place-items: center;
            padding: 20px;
            background: rgba(229, 241, 255, 0.68);
            backdrop-filter: blur(4px);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity 180ms ease, visibility 180ms ease;
        }
        .operation-loader.is-visible {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }
        .operation-loader__card {
            display: grid;
            grid-template-columns: 42px minmax(0, 1fr);
            gap: 3px 12px;
            width: min(390px, 100%);
            padding: 18px;
            border: 1px solid #bfdbfe;
            border-radius: 14px;
            background: #ffffff;
            box-shadow: 0 24px 60px rgba(15, 47, 99, 0.2);
        }
        .operation-loader__spinner {
            grid-row: 1 / 3;
            width: 38px;
            height: 38px;
            align-self: center;
            border: 4px solid #dbeafe;
            border-top-color: var(--quick-blue);
            border-radius: 50%;
            animation: operation-spin 760ms linear infinite;
        }
        .operation-loader__card strong { color: #0f2f63; font-size: 15px; }
        .operation-loader__card small { color: var(--quick-muted); font-size: 12px; }
        @keyframes operation-spin { to { transform: rotate(360deg); } }
        .variant-dialog { width:min(920px,calc(100% - 24px)); max-height:min(760px,calc(100vh - 32px)); padding:0; border:0; border-radius:16px; background:#fff; box-shadow:0 24px 70px rgba(15,47,99,.24); }
        .variant-dialog::backdrop { background:rgba(15,23,42,.48); backdrop-filter:blur(2px); }
        .variant-dialog__head,.variant-dialog__foot { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 16px; }
        .variant-dialog__head { border-bottom:1px solid #dbeafe; }
        .variant-dialog__head h2 { margin:0; color:#0f2f63; font-size:18px; font-weight:800; }
        .variant-dialog__head p { margin:3px 0 0; color:#64748b; font-size:12px; }
        .variant-dialog__body { max-height:520px; overflow:auto; padding:12px 16px; }
        .variant-size-entry { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:8px; margin-bottom:12px; padding:10px; border:1px solid #bfdbfe; border-radius:12px; background:#f8fbff; }
        .variant-size-entry small { grid-column:1 / -1; color:#64748b; }
        .variant-dialog__foot { border-top:1px solid #dbeafe; background:#f8fbff; }
        .variant-table { width:100%; min-width:680px; border-collapse:collapse; }
        .variant-table th { padding:8px; background:#eaf4ff; color:#0f2f63; font-size:11px; text-transform:uppercase; }
        .variant-table td { padding:7px 8px; border-bottom:1px solid #e2e8f0; vertical-align:middle; font-size:13px; }
        .variant-code-input { min-width:190px; font-family:Consolas,monospace; text-transform:uppercase; }
        .variant-existing { color:#15803d; font-size:11px; font-weight:800; }
        .variant-missing { color:#b45309; font-size:11px; font-weight:800; }

        @media (max-width: 1100px) {
            .quick-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 720px) {
            .quick-choice-groups { grid-template-columns: 1fr; }
            .quick-choice-actions { grid-template-columns: 1fr; }
            .quick-choice-btn { min-height: 104px; }
            .quick-shell { width: min(100% - 16px, 1440px); padding-top: 8px; }
            .quick-header { grid-template-columns: 1fr; padding: 14px; }
            .quick-title { font-size: 24px; }
            .quick-actions { justify-content: stretch; }
            .quick-btn { flex: 1 1 auto; }
            .quick-form-row { grid-template-columns: 1fr; padding: 12px; }
            .quick-export-panel { grid-template-columns: 1fr; padding: 12px; }
            .quick-panel-header { align-items: flex-start; flex-direction: column; }
            .quick-toolbar { justify-content: flex-start; }
        }
        @media (prefers-reduced-motion: reduce) {
            .operation-loader,
            .operation-loader__spinner { transition: none; animation: none; }
        }
    </style>
</head>
<body>
    <section id="modeChooser" class="quick-choice">
        <h1>Chọn nghiệp vụ kho</h1>
        <div class="quick-choice-groups">
            <section class="quick-choice-group" aria-labelledby="receiptGroupTitle">
                <h2 id="receiptGroupTitle" class="quick-choice-group-title"><i data-lucide="package-plus"></i>Nhập kho</h2>
                <div class="quick-choice-actions">
                    <button class="quick-choice-btn" type="button" data-receipt-kind="finished">
                        <i data-lucide="package-check"></i>
                        <strong>Nhập thành phẩm</strong>
                        <small>Lưu phiếu và in giao kế toán</small>
                    </button>
                    <button class="quick-choice-btn" type="button" data-receipt-kind="semi_finished">
                        <i data-lucide="factory"></i>
                        <strong>Nhập bán thành phẩm</strong>
                        <small>Tạo lệnh BTP và gửi sản xuất</small>
                    </button>
                </div>
            </section>
            <section class="quick-choice-group" aria-labelledby="issueGroupTitle">
                <h2 id="issueGroupTitle" class="quick-choice-group-title"><i data-lucide="package-minus"></i>Xuất kho</h2>
                <div class="quick-choice-actions is-single">
                    <a class="quick-choice-btn is-issue" href="{{ url('/client/xuat-thanh-pham-nhanh') }}">
                        <i data-lucide="truck"></i>
                        <strong>Xuất thành phẩm</strong>
                        <small>Soạn hàng, trừ tồn và in phiếu xuất</small>
                    </a>
                </div>
            </section>
        </div>
    </section>

    <main id="receiptWorkspace" class="quick-shell d-none">
        <header class="quick-header">
            <div>
                <h1 id="workspaceTitle" class="quick-title">Nhập thành phẩm</h1>
            </div>
            <div class="quick-actions">
                <button id="changeModeBtn" class="quick-btn" type="button"><i data-lucide="arrow-left"></i>Chọn lại</button>
                <button id="savePrintBtn" class="quick-btn quick-btn-primary" type="button"><i data-lucide="printer"></i>Lưu + in</button>
            </div>
        </header>

        <div class="quick-grid">
            <section class="quick-panel">
                <div class="quick-panel-header">
                    <h2 class="quick-panel-title">Phiếu nhập</h2>
                    <div class="quick-toolbar">
                        <span class="quick-pill">Dòng <strong id="lineCount">0</strong></span>
                        <span class="quick-pill">SL <strong id="totalQty">0</strong></span>
                        <span class="quick-pill">Vị trí <strong id="receiptLocationPill">CHUA-XEP</strong></span>
                        <button id="addRowBtn" class="quick-btn" type="button"><i data-lucide="plus"></i>Thêm dòng</button>
                        <button id="clearFormBtn" class="quick-btn" type="button"><i data-lucide="rotate-ccw"></i>Mới</button>
                    </div>
                </div>
                <div class="quick-form-row">
                    <div>
                        <label for="receiptDate">Ngày nhập</label>
                        <input id="receiptDate" class="form-control" type="text" inputmode="numeric" maxlength="10" autocomplete="off" placeholder="dd/mm/yyyy">
                    </div>
                    <div>
                        <label for="receiptLocation">Vị trí nhập</label>
                        <input id="receiptLocation" class="form-control text-uppercase" list="receiptLocationOptions" maxlength="100" autocomplete="off" placeholder="A1 hoặc CHUA-XEP" value="CHUA-XEP">
                        <datalist id="receiptLocationOptions"></datalist>
                    </div>
                    <div>
                        <label for="receiptNote">Ghi chú</label>
                        <input id="receiptNote" class="form-control" autocomplete="off" placeholder="KCS giao kho, ca sáng">
                    </div>
                </div>
                <div id="quickExportPanel" class="quick-export-panel d-none">
                    <div id="quickExportSwitch" class="quick-export-switch">
                        <input id="exportImmediately" class="form-check-input" type="checkbox" role="switch">
                        <label for="exportImmediately">Xuất ngay sau khi nhập</label>
                    </div>
                    <div id="customerField" class="d-none">
                        <label for="customerName">Khách hàng *</label>
                        <input id="customerName" class="form-control" list="receiptCustomerOptions" autocomplete="off" placeholder="Gõ để chọn khách hàng">
                        <datalist id="receiptCustomerOptions"></datalist>
                    </div>
                    <div id="issueDateField" class="d-none">
                        <label for="issueDate">Ngày xuất *</label>
                        <input id="issueDate" class="form-control" type="text" inputmode="numeric" maxlength="10" autocomplete="off" placeholder="dd/mm/yyyy">
                    </div>
                </div>

                <div class="quick-table-wrap">
                    <table class="quick-table">
                        <thead>
                            <tr>
                                <th>Stt</th>
                                <th>Lệnh</th>
                                <th>Mã nội bộ *</th>
                                <th>Tên hàng</th>
                                <th>Size</th>
                                <th>Màu</th>
                                <th>SL *</th>
                                <th>ĐVT</th>
                                <th>Ghi chú</th>
                            </tr>
                        </thead>
                        <tbody id="quickRows"></tbody>
                    </table>
                </div>
                <div id="formStatus" class="quick-status">Nhập mã và số lượng, bấm Enter để xuống dòng.</div>
                <details class="quick-recent">
                    <summary class="quick-panel-title mb-2">Phiếu vừa nhập</summary>
                    <div id="recentReceipts" class="pt-2"></div>
                </details>
            </section>
        </div>
    </main>

    <dialog id="variantDialog" class="variant-dialog">
        <div class="variant-dialog__head">
            <div><h2>Tách mã theo size / màu</h2><p id="variantDialogSummary"></p></div>
            <button id="closeVariantDialog" class="quick-btn" type="button" aria-label="Đóng"><i data-lucide="x"></i></button>
        </div>
        <div class="variant-dialog__body">
            <div id="variantSizeEntry" class="variant-size-entry d-none">
                <input id="variantSizeInput" class="form-control" autocomplete="off" placeholder="Dán danh sách size: 35, 36, 37, 38...">
                <button id="previewVariantSizesBtn" class="quick-btn" type="button">Tách size</button>
                <small>Lệnh nguồn chưa tách biến thể. Có thể dán size từ Excel; màu được lấy theo từng dòng phiếu.</small>
            </div>
            <div class="table-responsive">
                <table class="variant-table">
                    <thead><tr><th>Size</th><th>Màu</th><th>Mã đề xuất</th><th>Trạng thái</th></tr></thead>
                    <tbody id="variantPreviewRows"></tbody>
                </table>
            </div>
        </div>
        <div class="variant-dialog__foot">
            <span id="variantDialogNote" class="text-muted small"></span>
            <div class="d-flex gap-2">
                <button id="cancelVariantDialog" class="quick-btn" type="button">Để sau</button>
                <button id="applyVariantsBtn" class="quick-btn quick-btn-primary" type="button"><i data-lucide="list-plus"></i>Tạo mã còn thiếu</button>
            </div>
        </div>
    </dialog>

    <datalist id="internalCatalogOptions"></datalist>

    <div id="operationLoader" class="operation-loader" role="status" aria-live="polite" aria-hidden="true">
        <div class="operation-loader__card">
            <span class="operation-loader__spinner" aria-hidden="true"></span>
            <strong id="operationLoaderTitle">Đang xử lý phiếu</strong>
            <small id="operationLoaderDetail">Vui lòng chờ, không đóng trang.</small>
        </div>
    </div>

    <div id="orderHistoryToast" class="order-history-toast" role="status" aria-live="polite">
        <strong id="orderHistoryToastTitle"></strong>
        <span id="orderHistoryToastText"></span>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const initialRowCount = 10;
        let selectedReceiptKind = '';
        let internalCatalogItems = [];
        let catalogSearchTimer = null;
        let catalogSearchCache = new Map();
        let catalogExactCache = new Map();
        let latestCatalogSearchKey = '';
        const productionOrderSearchTimers = new WeakMap();
        const productionOrderSearchRequests = new WeakMap();
        const productionOrderSuggestionCache = new Map();
        let pendingVariantOrder = '';
        let pendingVariantPlans = [];
        let pendingVariantSizes = [];
        let pendingVariantInputs = [];
        let pendingCrossOrderVariants = [];
        let pendingVariantNeedsLink = false;
        let variantCheckPromise = Promise.resolve();
        let variantCheckError = null;
        let receiptRequestPending = false;
        let orderHistoryToastTimer = null;
        let customerSuggestionTimer = null;

        function selectedReceiptLocation() {
            return document.getElementById('receiptLocation').value.trim().toUpperCase() || 'CHUA-XEP';
        }

        function updateReceiptLocation(commitFallback = false) {
            const input = document.getElementById('receiptLocation');
            const normalized = input.value.trim().toUpperCase();
            input.value = commitFallback ? (normalized || 'CHUA-XEP') : input.value.toUpperCase();
            document.getElementById('receiptLocationPill').textContent = normalized || 'CHUA-XEP';
        }

        function loadReceiptLocations() {
            fetch('/api/kiem-ton-kho/vi-tri')
                .then(response => jsonOrError(response, 'Không tải được danh sách vị trí'))
                .then(result => {
                    document.getElementById('receiptLocationOptions').innerHTML = (result.data || [])
                        .map(location => String(location.location_code || '').trim().toUpperCase())
                        .filter(Boolean)
                        .map(code => `<option value="${esc(code)}"></option>`)
                        .join('');
                })
                .catch(() => {});
        }

        function showOrderHistoryToast(orderCode, progress = {}) {
            const toast = document.getElementById('orderHistoryToast');
            const planned = Number(progress.planned_quantity || 0);
            const received = Number(progress.received_quantity || 0);
            const customerIssued = Number(progress.customer_issue_quantity || 0);
            const receiptCodes = progress.receipt_codes || [];
            const customerIssueCodes = progress.customer_issue_codes || [];
            const productionIssueCodes = progress.production_issue_codes || [];
            const details = [
                progress.has_planned_quantity === false ? 'Đơn hàng: chưa có số lượng' : `Đơn hàng: ${fmt(planned)}`,
                `Đã nhập: ${fmt(received)}${receiptCodes.length ? ` · ${receiptCodes.join(', ')}` : ' · chưa có phiếu nhập'}`,
                `Đã xuất khách: ${fmt(customerIssued)}${customerIssueCodes.length ? ` · ${customerIssueCodes.join(', ')}` : ' · chưa có phiếu xuất'}`,
            ];
            if (productionIssueCodes.length) details.push(`Xuất sản xuất: ${productionIssueCodes.join(', ')}`);
            document.getElementById('orderHistoryToastTitle').textContent = `Lệnh ${orderCode}`;
            document.getElementById('orderHistoryToastText').textContent = details.join('\n');
            toast.classList.add('is-visible');
            clearTimeout(orderHistoryToastTimer);
            orderHistoryToastTimer = setTimeout(() => toast.classList.remove('is-visible'), 7500);
        }

        function setOperationLoading(loading, title = 'Đang xử lý phiếu', detail = 'Vui lòng chờ, không đóng trang.') {
            const loader = document.getElementById('operationLoader');
            document.getElementById('operationLoaderTitle').textContent = title;
            document.getElementById('operationLoaderDetail').textContent = detail;
            loader.classList.toggle('is-visible', loading);
            loader.setAttribute('aria-hidden', loading ? 'false' : 'true');
        }

        function localIsoDate() {
            const parts = new Intl.DateTimeFormat('en-GB', {
                timeZone: 'Asia/Ho_Chi_Minh',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit'
            }).formatToParts(new Date());
            const values = Object.fromEntries(parts.map(part => [part.type, part.value]));
            return `${values.year}-${values.month}-${values.day}`;
        }

        function isoToDisplayDate(value) {
            const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
            return match ? `${match[3]}/${match[2]}/${match[1]}` : '';
        }

        function displayToIsoDate(value) {
            const match = String(value || '').trim().match(/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4})$/);
            if (!match) return '';
            const day = Number(match[1]);
            const month = Number(match[2]);
            const year = Number(match[3]);
            const date = new Date(Date.UTC(year, month - 1, day));
            if (date.getUTCFullYear() !== year || date.getUTCMonth() !== month - 1 || date.getUTCDate() !== day) return '';
            return `${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        }

        function esc(value) {
            return String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
        }

        function num(value) {
            const normalized = String(value ?? '').trim().replace(/\s/g, '').replace(',', '.');
            return Number(normalized || 0);
        }

        function fmt(value) {
            return Number(value || 0).toLocaleString('vi-VN', { maximumFractionDigits: 3 });
        }

        function selectedReceiptDate() {
            return displayToIsoDate(document.getElementById('receiptDate').value);
        }

        function loadCustomerSuggestions(keyword = '') {
            clearTimeout(customerSuggestionTimer);
            customerSuggestionTimer = setTimeout(() => {
                fetch(`/api/khach-hang-noi-bo/goi-y?keyword=${encodeURIComponent(keyword.trim())}&limit=50`)
                    .then(response => jsonOrError(response, 'Không tải được danh mục khách hàng'))
                    .then(result => {
                        document.getElementById('receiptCustomerOptions').innerHTML = (result.data || []).map(customer => {
                            const label = [customer.customer_code, customer.customer_group].filter(Boolean).join(' · ');
                            return `<option value="${esc(customer.name)}">${esc(label)}</option>`;
                        }).join('');
                    })
                    .catch(() => document.getElementById('receiptCustomerOptions').innerHTML = '');
            }, keyword ? 120 : 0);
        }

        async function requireCatalogCustomer(name) {
            const result = await fetch(`/api/khach-hang-noi-bo/kiem-tra?name=${encodeURIComponent(name.trim())}`)
                .then(response => jsonOrError(response, 'Không kiểm tra được khách hàng'));
            if (!result.valid || !result.data) {
                throw new Error('Khách hàng chưa có trong Danh mục khách hàng. Hãy chọn đúng gợi ý hoặc thêm khách hàng trước.');
            }
            return result.data;
        }

        function normalizeDateField(input) {
            const iso = displayToIsoDate(input.value);
            if (iso) input.value = isoToDisplayDate(iso);
        }

        function allOrderQuery(keyword, limit) {
            const params = new URLSearchParams({
                keyword,
                with_progress: '1',
                order_date_to: selectedReceiptDate(),
                limit: String(limit),
            });
            return `/api/lenh-san-xuat-sheet?${params.toString()}`;
        }

        function setStatus(message, type = '') {
            const status = document.getElementById('formStatus');
            status.className = `quick-status ${type ? 'is-' + type : ''}`;
            status.textContent = message;
        }

        function jsonOrError(response, fallback) {
            return response.json().catch(() => ({})).then(result => {
                if (!response.ok) {
                    const errors = result.errors ? Object.values(result.errors).flat().join('\n') : '';
                    throw new Error(errors || result.message || `${fallback} (HTTP ${response.status})`);
                }
                return result;
            });
        }

        function rowTemplate(index) {
            return `<tr>
                <td class="quick-row-index">${index + 1}</td>
                <td class="quick-order">
                    <input class="form-control production-order" autocomplete="off" placeholder="G&#245; l&#7879;nh SX ho&#7863;c m&#227; h&#224;ng">
                    <select class="form-select order-suggestions d-none mt-1" aria-label="L&#7879;nh s&#7843;n xu&#7845;t ch&#432;a ho&#224;n t&#7845;t"></select>
                </td>
                <td class="quick-code">
                    <input class="form-control internal-code" list="internalCatalogOptions" autocomplete="off" placeholder="Mã nội bộ">
                    <div class="row-state"></div>
                </td>
                <td class="quick-name">
                    <div class="quick-name-cell">
                        <div class="quick-item-image is-empty"></div>
                        <input class="form-control item-name" autocomplete="off" placeholder="Tự điền">
                    </div>
                </td>
                <td class="quick-size"><input class="form-control item-size" autocomplete="off"></td>
                <td class="quick-color"><input class="form-control item-color" autocomplete="off"></td>
                <td class="quick-qty"><input class="form-control quantity" type="text" inputmode="decimal" autocomplete="off" placeholder="0"></td>
                <td class="quick-unit"><input class="form-control item-unit" autocomplete="off" placeholder="Cái"></td>
                <td class="quick-note"><input class="form-control line-note" autocomplete="off" placeholder="Ghi chú nếu có"></td>
            </tr>`;
        }

        function bindQuickRow(row) {
            row.querySelector('.production-order').addEventListener('input', event => searchProductionOrders(event.target));
            row.querySelector('.production-order').addEventListener('focus', event => {
                if (event.target.value.trim().length >= 2) searchProductionOrders(event.target, true);
            });
            row.querySelector('.production-order').addEventListener('change', event => {
                const input = event.target;
                const value = input.value.trim().toUpperCase();
                const select = input.closest('tr').querySelector('.order-suggestions');
                const hasExactSuggestion = Array.from(select.options).some(option => {
                    return option.value && option.value.trim().toUpperCase() === value;
                });
                if (hasExactSuggestion) applyProductionOrder(input);
            });
            row.querySelector('.order-suggestions').addEventListener('change', event => {
                const select = event.target;
                if (!select.value) return;
                const orderInput = select.closest('tr').querySelector('.production-order');
                orderInput.value = select.value;
                applyProductionOrder(orderInput);
            });
            row.querySelector('.internal-code').addEventListener('input', event => searchInternalCatalog(event.target));
            row.querySelector('.internal-code').addEventListener('change', event => applyInternalCatalog(event.target, true));
            row.querySelector('.quantity').addEventListener('input', updateSummary);
            row.querySelectorAll('input').forEach(input => {
                input.addEventListener('keydown', event => {
                    if (event.key !== 'Enter') return;
                    event.preventDefault();
                    focusNext(input);
                });
            });
        }

        function renderRows() {
            document.getElementById('quickRows').innerHTML = Array.from({ length: initialRowCount }, (_, index) => rowTemplate(index)).join('');
            document.querySelectorAll('#quickRows tr').forEach(bindQuickRow);
        }

        function appendQuickRows(count) {
            if (count <= 0) return;
            const body = document.getElementById('quickRows');
            const start = body.querySelectorAll('tr').length;
            body.insertAdjacentHTML('beforeend', Array.from({length:count}, (_, offset) => rowTemplate(start + offset)).join(''));
            Array.from(body.querySelectorAll('tr')).slice(start).forEach(bindQuickRow);
        }

        function addQuickRow() {
            appendQuickRows(1);
            const row = document.querySelector('#quickRows tr:last-child');
            row?.scrollIntoView({behavior: 'smooth', block: 'center'});
            window.setTimeout(() => row?.querySelector('.production-order')?.focus(), 180);
        }

        function focusNext(input) {
            if (input.classList.contains('production-order') && applyProductionOrder(input)) return;
            if (input.classList.contains('quantity')) {
                const nextRow = input.closest('tr')?.nextElementSibling;
                const nextOrder = nextRow?.querySelector('.production-order');
                if (nextOrder) {
                    nextOrder.focus();
                    nextOrder.select();
                    return;
                }
            }
            const inputs = Array.from(document.querySelectorAll('input:not([type="hidden"])'));
            const index = inputs.indexOf(input);
            if (index >= 0 && inputs[index + 1]) inputs[index + 1].focus();
        }

        function productionOrderMatchesItemCode(order, itemCode) {
            const code = String(itemCode || '').trim().toUpperCase();
            if (!code) return false;
            return [order.item_code, order.source_item_code, order.standard_item_code]
                .map(value => String(value || '').trim().toUpperCase())
                .filter(Boolean)
                .some(candidate => {
                    return candidate === code
                        || candidate.startsWith(`${code}-`)
                        || candidate.startsWith(`${code}_`)
                        || code.startsWith(`${candidate}-`)
                        || code.startsWith(`${candidate}_`);
                });
        }

        function loadOpenOrdersForItem(input) {
            const code = String(input.value || '').trim().toUpperCase();
            const select = input.closest('tr').querySelector('.order-suggestions');
            if (!code || !select) return Promise.resolve(0);

            select.disabled = true;
            select.innerHTML = '<option value="">Đang tìm lệnh liên quan...</option>';
            select.classList.remove('d-none');

            return fetch(allOrderQuery(code, 100))
                .then(response => jsonOrError(response, 'Không tải được lệnh chưa hoàn tất'))
                .then(result => {
                    const byOrder = new Map();
                    (result.data || []).forEach(order => {
                        if (!productionOrderMatchesItemCode(order, code)) return;
                        if (order.has_planned_quantity !== false && Number(order.remaining_quantity || 0) <= 0) return;
                        const orderCode = String(order.production_order || '').trim();
                        if (orderCode && !byOrder.has(orderCode)) byOrder.set(orderCode, order);
                    });
                    const orders = Array.from(byOrder.values());
                    orders.forEach(order => {
                        const orderCode = String(order.production_order || '').trim().toUpperCase();
                        if (orderCode) productionOrderSuggestionCache.set(orderCode, order);
                    });
                    if (!orders.length) {
                        select.disabled = true;
                        select.innerHTML = '<option value="">Không có lệnh chưa hoàn tất cho mã này</option>';
                        select.classList.remove('d-none');
                        return 0;
                    }
                    select.disabled = false;
                    select.innerHTML = `<option value="">${orders.length} l&#7879;nh ch&#432;a ho&#224;n t&#7845;t - ch&#7885;n l&#7879;nh</option>` + orders.map(order => {
                        const orderDate = order.received_date ? `Nh\u1eadn l\u1ec7nh ${String(order.received_date).split('-').reverse().join('/')}` : '';
                        const orderQuantity = order.has_planned_quantity === false
                            ? '\u0110H ch\u01b0a c\u00f3 SL'
                            : `\u0110H ${fmt(order.planned_quantity || 0)}`;
                        const received = `\u0110\u00e3 nh\u1eadp ${fmt(order.received_quantity || 0)}`;
                        const remaining = order.has_planned_quantity === false
                            ? 'Ch\u01b0a c\u00f3 SL k\u1ebf ho\u1ea1ch'
                            : `C\u00f2n ${fmt(order.remaining_quantity || 0)}`;
                        const detail = [order.production_order, orderQuantity, received, remaining, orderDate, order.customer, order.purchase_order].filter(Boolean).join(' - ');
                        return `<option value="${esc(order.production_order || '')}">${esc(detail)}</option>`;
                    }).join('');
                    select.classList.remove('d-none');
                    return orders.length;
                })
                .catch(error => {
                    select.disabled = true;
                    select.innerHTML = '<option value="">Không tải được lệnh liên quan</option>';
                    select.classList.remove('d-none');
                    setStatus(error.message, 'error');
                    return 0;
                });
        }
        function searchProductionOrders(input, immediate = false) {
            const keyword = input.value.trim();
            const select = input.closest('tr')?.querySelector('.order-suggestions');
            if (input.dataset.appliedOrder && input.dataset.appliedOrder !== keyword.toUpperCase()) {
                delete input.dataset.productionOrderId;
                delete input.dataset.purchaseOrder;
                delete input.dataset.customer;
                delete input.dataset.sourceItemCode;
                syncReceiptRowPurchaseOrderNote(input.closest('tr'), '');
                delete input.dataset.appliedOrder;
            }

            clearTimeout(productionOrderSearchTimers.get(input));
            productionOrderSearchRequests.get(input)?.abort();
            if (keyword.length < 2) {
                input.dataset.orderSearchKey = '';
                if (select) {
                    select.innerHTML = '';
                    select.classList.add('d-none');
                }
                return;
            }

            const requestKey = keyword.toUpperCase();
            input.dataset.orderSearchKey = requestKey;
            if (select) {
                select.disabled = true;
                select.innerHTML = '<option value="">Đang tìm lệnh sản xuất...</option>';
                select.classList.remove('d-none');
            }

            const timer = setTimeout(() => {
                const controller = new AbortController();
                productionOrderSearchRequests.set(input, controller);
                fetch(allOrderQuery(keyword, 30), {signal: controller.signal})
                    .then(response => jsonOrError(response, 'Không tải được lệnh sản xuất'))
                    .then(result => {
                        if (input.dataset.orderSearchKey !== requestKey || !select) return;
                        const uniqueOrders = new Map();
                        (result.data || []).forEach(order => {
                            const code = String(order.production_order || '').trim();
                            if (!code || uniqueOrders.has(code)) return;
                            uniqueOrders.set(code, {
                                ...order,
                                suggestion_state: order.has_planned_quantity === false || Number(order.remaining_quantity || 0) > 0
                                    ? 'unfinished'
                                    : 'completed',
                            });
                        });
                        const options = Array.from(uniqueOrders.values()).sort((left, right) => {
                            const leftMatchesOrder = String(left.production_order || '').toUpperCase().includes(requestKey) ? 0 : 1;
                            const rightMatchesOrder = String(right.production_order || '').toUpperCase().includes(requestKey) ? 0 : 1;
                            if (leftMatchesOrder !== rightMatchesOrder) return leftMatchesOrder - rightMatchesOrder;
                            if (left.suggestion_state !== right.suggestion_state) {
                                return left.suggestion_state === 'unfinished' ? -1 : 1;
                            }
                            return Number(right.remaining_quantity || 0) - Number(left.remaining_quantity || 0);
                        });
                        options.forEach(order => {
                            const orderCode = String(order.production_order || '').trim().toUpperCase();
                            if (orderCode) productionOrderSuggestionCache.set(orderCode, order);
                        });

                        if (!options.length) {
                            select.disabled = true;
                            select.innerHTML = `<option value="">Không tìm thấy lệnh trước ngày ${esc(selectedReceiptDate().split('-').reverse().join('/'))}</option>`;
                            select.classList.remove('d-none');
                            return;
                        }

                        select.disabled = false;
                        select.innerHTML = `<option value="">Chọn 1 trong ${options.length} lệnh tìm thấy</option>` + options.map(order => {
                            const progressLabel = order.has_planned_quantity === false
                                ? 'Chưa có SL kế hoạch'
                                : order.suggestion_state === 'completed'
                                    ? 'Đã nhập đủ/dư'
                                    : `Còn ${fmt(order.remaining_quantity || 0)}`;
                            const label = [
                                order.production_order,
                                order.has_planned_quantity === false ? '\u0110H ch\u01b0a c\u00f3 SL' : `\u0110H ${fmt(order.planned_quantity || 0)}`,
                                `\u0110\u00e3 nh\u1eadp ${fmt(order.received_quantity || 0)}`,
                                progressLabel,
                                order.received_date ? `Nhận lệnh ${String(order.received_date).split('-').reverse().join('/')}` : '',
                                order.customer,
                                order.item_code,
                                order.size ? `Size ${order.size}` : '',
                                order.color ? `Màu ${order.color}` : ''
                            ].filter(Boolean).join(' - ');
                            return `<option value="${esc(order.production_order || '')}">${esc(label)}</option>`;
                        }).join('');
                        select.classList.remove('d-none');
                    })
                    .catch(error => {
                        if (error.name === 'AbortError' || input.dataset.orderSearchKey !== requestKey || !select) return;
                        select.disabled = true;
                        select.innerHTML = '<option value="">Không tải được gợi ý lệnh</option>';
                        select.classList.remove('d-none');
                        setStatus(error.message, 'error');
                    })
                    .finally(() => {
                        if (productionOrderSearchRequests.get(input) === controller) {
                            productionOrderSearchRequests.delete(input);
                        }
                    });
            }, immediate ? 0 : 180);
            productionOrderSearchTimers.set(input, timer);
        }

        function applyProductionOrder(input) {
            const code = input.value.trim().toUpperCase();
            if (!code || input.dataset.appliedOrder === code || input.dataset.loadingOrder === code) return false;
            input.dataset.loadingOrder = code;
            const cachedOrder = productionOrderSuggestionCache.get(code);
            if (cachedOrder) {
                // Fill the selected order immediately. Progress/catalog lookups must not clear this data.
                fillQuickRow(input.closest('tr'), cachedOrder);
            }
            fetch(`/api/lenh-san-xuat-sheet?production_order=${encodeURIComponent(input.value.trim())}&limit=500`)
                .then(response => jsonOrError(response, 'Không tải được chi tiết lệnh sản xuất'))
                .then(result => {
                    const variants = result.data || [];
                    if (!variants.length) {
                        setStatus(`Không tìm thấy lệnh SX ${input.value}. Có thể nhập tay mã nội bộ.`, 'error');
                        return;
                    }
                    const progress = result.summary?.receipt_progress || {};
                    const orderDate = String(progress.order_date || '');
                    const receiptDataStartDate = String(progress.receipt_data_start_date || '');
                    if (orderDate && receiptDataStartDate && orderDate < receiptDataStartDate && !progress.has_linked_finished_receipt) {
                        const displayOrderDate = orderDate.split('-').reverse().join('/');
                        const displayStartDate = receiptDataStartDate.split('-').reverse().join('/');
                        setStatus(`L\u1ec7nh ${input.value} c\u00f3 ng\u00e0y ${displayOrderDate}, tr\u01b0\u1edbc phi\u1ebfu nh\u1eadp th\u00e0nh ph\u1ea9m \u0111\u1ea7u ti\u00ean ${displayStartDate}.`, 'error');
                        return;
                    }
                    if (orderDate && orderDate > selectedReceiptDate()) {
                        const displayOrderDate = orderDate.split('-').reverse().join('/');
                        const displayReceiptDate = selectedReceiptDate().split('-').reverse().join('/');
                        setStatus(`L\u1ec7nh ${input.value} c\u00f3 ng\u00e0y ${displayOrderDate}, sau ng\u00e0y nh\u1eadp kho ${displayReceiptDate}.`, 'error');
                        return;
                    }
                    expandProductionOrder(input, variants, progress);
                    showOrderHistoryToast(input.value.trim(), progress);
                    if (selectedReceiptKind === 'finished') {
                        variantCheckError = null;
                        variantCheckPromise = prepareProductionVariants(input.value.trim())
                            .catch(error => {
                                variantCheckError = error;
                                setStatus(error.message, 'error');
                            });
                    }
                    const excess = Number(progress.excess_quantity || 0);
                    if (excess > 0.0001) {
                        const warning = `L\u1ec7nh ${input.value} \u0111\u00e3 nh\u1eadp d\u01b0 ${fmt(excess)}. K\u1ebf ho\u1ea1ch ${fmt(progress.planned_quantity)}, \u0111\u00e3 nh\u1eadp ${fmt(progress.received_quantity)} (k\u1ec3 c\u1ea3 FIFO).`;
                        alert(warning);
                        setStatus(warning, 'error');
                    } else {
                        setStatus(`\u0110\u00e3 n\u1ea1p ${variants.length} d\u00f2ng t\u1eeb l\u1ec7nh ${input.value}. C\u00f2n c\u00f3 th\u1ec3 nh\u1eadp ${fmt(progress.remaining_quantity || 0)}.`);
                    }
                })
                .catch(error => {
                    if (cachedOrder) {
                        setStatus(`Đã điền mã ${cachedOrder.item_code || ''} từ lệnh ${cachedOrder.production_order}. Chưa tải được tiến độ: ${error.message}`, 'error');
                        return;
                    }
                    setStatus(error.message, 'error');
                })
                .finally(() => delete input.dataset.loadingOrder);
            return true;
        }

        function quickRowIsEmpty(row) {
            return !Array.from(row.querySelectorAll('input')).some(input => input.value.trim() !== '');
        }

        function purchaseOrderFromOrder(order) {
            const raw = order?.raw_data || {};
            return String(
                order?.purchase_order
                || raw['purchase order po']
                || raw['purchase order']
                || raw['po']
                || raw['ps / sub']
                || ''
            ).trim();
        }

        function syncReceiptRowPurchaseOrderNote(row, purchaseOrder) {
            const noteInput = row.querySelector('.line-note');
            const previousAutoNote = String(noteInput.dataset.purchaseOrderNote || '').trim();
            let manualNote = noteInput.value.trim();
            if (previousAutoNote && manualNote === previousAutoNote) {
                manualNote = '';
            } else if (previousAutoNote && manualNote.endsWith(` | ${previousAutoNote}`)) {
                manualNote = manualNote.slice(0, -(` | ${previousAutoNote}`).length).trim();
            }

            const poNote = purchaseOrder ? `PO: ${purchaseOrder}` : '';
            noteInput.dataset.purchaseOrderNote = poNote;
            noteInput.value = [manualNote, poNote].filter(Boolean).join(' | ');
        }

        function fillQuickRow(row, order) {
            delete row.dataset.variantKey;
            const orderInput = row.querySelector('.production-order');
            orderInput.value = order.production_order || '';
            orderInput.dataset.appliedOrder = String(order.production_order || '').trim().toUpperCase();
            orderInput.dataset.productionOrderId = order.id || '';
            orderInput.dataset.purchaseOrder = purchaseOrderFromOrder(order);
            orderInput.dataset.customer = order.customer || '';
            orderInput.dataset.sourceItemCode = order.item_code || '';
            if (!document.getElementById('customerName').value.trim() && order.customer) {
                document.getElementById('customerName').value = order.customer;
            }
            row.querySelector('.internal-code').value = order.item_code || '';
            row.querySelector('.item-name').value = order.description || order.specification || '';
            row.querySelector('.item-size').value = order.size || '';
            row.querySelector('.item-color').value = order.color || '';
            row.querySelector('.item-unit').value = order.unit || row.querySelector('.item-unit').value || 'Cái';
            row.querySelector('.quantity').value = '';
            syncReceiptRowPurchaseOrderNote(row, orderInput.dataset.purchaseOrder);
            row.querySelector('.row-state').textContent = 'Từ lệnh sản xuất';
        }

        function expandProductionOrder(input, variants, progress = {}) {
            const currentRow = input.closest('tr');
            let rows = Array.from(document.querySelectorAll('#quickRows tr'));
            const currentIndex = rows.indexOf(currentRow);
            const emptyAfterCurrent = rows.slice(currentIndex + 1).filter(quickRowIsEmpty).length;
            const rowsNeeded = Math.max(0, variants.length - 1 - emptyAfterCurrent);
            if (rowsNeeded > 0) {
                appendQuickRows(rowsNeeded);
                rows = Array.from(document.querySelectorAll('#quickRows tr'));
            }
            const targets = [currentRow];

            for (let index = currentIndex + 1; index < rows.length && targets.length < variants.length; index++) {
                if (quickRowIsEmpty(rows[index])) targets.push(rows[index]);
            }

            const remainingQuantity = variants.length === 1 ? Number(progress.remaining_quantity || 0) : null;
            variants.slice(0, targets.length).forEach((variant, index) => {
                fillQuickRow(targets[index], variant);
                if (remainingQuantity !== null) {
                    targets[index].querySelector('.row-state').textContent = `C\u00f2n ${fmt(remainingQuantity)} theo l\u1ec7nh`;
                }
            });
            if (targets.length < variants.length) {
                alert(`Lệnh ${input.value} có ${variants.length} dòng size/màu, màn hình nhanh chỉ nhận ${targets.length} dòng trống. Dùng form nhập chính nếu cần nhiều hơn.`);
            }
            updateSummary();
            targets[0].querySelector('.quantity')?.focus();
        }

        function applyVariantPlansToRows(plans, final = false) {
            (plans || []).forEach(plan => {
                const matchingRows = Array.from(document.querySelectorAll('#quickRows tr')).filter(item => {
                    if (plan.manual) {
                        return item.dataset.variantKey === String(plan.variant_key);
                    }
                    return Number(item.querySelector('.production-order')?.dataset.productionOrderId || 0) === Number(plan.order_id);
                });
                if (!matchingRows.length) return;
                const code = final ? plan.final_code : (plan.exists ? plan.proposed_code : '');
                if (!code) {
                    matchingRows.forEach(row => {
                        const state = row.querySelector('.row-state');
                        state.className = 'row-state is-warn';
                        state.textContent = `Chưa có mã riêng cho size/màu ${plan.size || '-'} / ${plan.color || '-'}`;
                    });
                    return;
                }
                matchingRows.forEach(row => {
                    row.querySelector('.internal-code').value = code;
                    if (final && plan.production_order_variant_id) {
                        row.querySelector('.production-order').dataset.productionOrderId = plan.production_order_variant_id;
                    }
                    row.querySelector('.item-name').value = plan.item_name || row.querySelector('.item-name').value;
                    row.querySelector('.item-size').value = plan.size || row.querySelector('.item-size').value;
                    row.querySelector('.item-color').value = plan.color || row.querySelector('.item-color').value;
                    row.querySelector('.item-unit').value = plan.unit || row.querySelector('.item-unit').value;
                    const state = row.querySelector('.row-state');
                    state.className = 'row-state is-ok';
                    state.textContent = final ? 'Đã tạo và liên kết danh mục' : 'Đã có trong danh mục';
                });
            });
        }

        function expandManualVariantPlans(plans) {
            if (!(plans || []).length || !plans[0].manual) return;
            const sourceRow = Array.from(document.querySelectorAll('#quickRows tr')).find(row => {
                return Number(row.querySelector('.production-order')?.dataset.productionOrderId || 0) === Number(plans[0].order_id);
            });
            if (!sourceRow) return;
            let rows = Array.from(document.querySelectorAll('#quickRows tr'));
            const sourceIndex = rows.indexOf(sourceRow);
            const emptyRows = rows.slice(sourceIndex + 1).filter(quickRowIsEmpty);
            if (emptyRows.length < plans.length - 1) {
                appendQuickRows(plans.length - 1 - emptyRows.length);
                rows = Array.from(document.querySelectorAll('#quickRows tr'));
            }
            const targets = [sourceRow, ...rows.slice(sourceIndex + 1).filter(quickRowIsEmpty).slice(0, plans.length - 1)];
            const sourceOrder = sourceRow.querySelector('.production-order');
            plans.forEach((plan, index) => {
                const target = targets[index];
                fillQuickRow(target, {
                    id:plan.order_id,
                    production_order:plan.production_order,
                    purchase_order:sourceOrder.dataset.purchaseOrder || '',
                    customer:sourceOrder.dataset.customer || '',
                    item_code:'',
                    description:plan.item_name,
                    size:plan.size,
                    color:plan.color,
                    unit:plan.unit,
                });
                target.dataset.variantKey = String(plan.variant_key);
            });
        }

        async function prepareProductionVariants(orderCode, sizes = [], manualVariants = [], expandRows = true, crossOrderVariants = []) {
            const response = await fetch('/api/danh-muc-noi-bo/bien-the-lenh-san-xuat', {
                method: 'POST',
                headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify({
                    production_order:orderCode,
                    apply:false,
                    sizes,
                    manual_variants:manualVariants,
                    cross_order_variants:crossOrderVariants
                }),
            });
            const result = await jsonOrError(response, 'Không kiểm tra được biến thể danh mục');
            const plans = result.data || [];
            pendingVariantSizes = sizes;
            pendingVariantInputs = manualVariants;
            pendingCrossOrderVariants = crossOrderVariants;
            if (expandRows && Number(result.summary?.manual_size_count || 0) > 0) {
                expandManualVariantPlans(plans);
            }
            applyVariantPlansToRows(plans, false);
            const missing = plans.filter(plan => !plan.exists);
            const needsProductionLink = Boolean(result.summary?.needs_production_link);
            pendingVariantNeedsLink = needsProductionLink;
            if (!missing.length && !needsProductionLink) {
                pendingVariantOrder = '';
                pendingVariantPlans = [];
                pendingVariantSizes = [];
                pendingVariantInputs = [];
                pendingCrossOrderVariants = [];
                pendingVariantNeedsLink = false;
                if (document.getElementById('variantDialog').open) {
                    document.getElementById('variantDialog').close();
                }
                setStatus(`Lệnh ${orderCode} có ${plans.length} biến thể và tất cả đã có trong danh mục.`, 'ok');
                return;
            }

            const splitRequired = Number(result.summary?.split_required || 0) > 0;
            if (!splitRequired && !needsProductionLink) {
                pendingVariantOrder = '';
                pendingVariantPlans = [];
                pendingVariantSizes = [];
                pendingVariantInputs = [];
                pendingCrossOrderVariants = [];
                pendingVariantNeedsLink = false;
                if (document.getElementById('variantDialog').open) {
                    document.getElementById('variantDialog').close();
                }
                setStatus(`Mã từ lệnh ${orderCode} chưa khớp DANH MỤC. Chọn mã đúng ở cột Mã nội bộ rồi nhập bình thường.`, 'error');
                return;
            }

            pendingVariantOrder = orderCode;
            pendingVariantPlans = plans;
            document.getElementById('variantDialogSummary').textContent = missing.length
                ? `${orderCode} · ${plans.length} biến thể · thiếu ${missing.length} mã`
                : `${orderCode} · ${plans.length} biến thể · cần liên kết vào lệnh trung tâm`;
            const requiresSizes = Boolean(result.summary?.requires_size_input);
            const showSizeEntry = requiresSizes || Number(result.summary?.manual_size_count || 0) > 0;
            document.getElementById('variantSizeEntry').classList.toggle('d-none', !showSizeEntry);
            document.getElementById('variantPreviewRows').innerHTML = requiresSizes ? '' : plans.map(plan => `
                <tr data-variant-order-id="${Number(plan.order_id)}" data-variant-key="${esc(plan.variant_key)}">
                    <td><strong>${esc(plan.size || '-')}</strong></td>
                    <td>${esc(plan.color || '-')}</td>
                    <td>${plan.exists
                        ? `<span class="font-monospace fw-bold">${esc(plan.proposed_code)}</span>`
                        : `<input class="form-control variant-code-input" value="${esc(plan.proposed_code)}" maxlength="200">`}</td>
                    <td>${plan.exists
                        ? '<span class="variant-existing">Đã có</span>'
                        : '<span class="variant-missing">Sẽ tạo</span>'}</td>
                </tr>`).join('');
            document.getElementById('variantDialogNote').textContent = requiresSizes
                ? 'Dán danh sách size để tạo đúng biến thể; hệ thống không tự đoán size.'
                : !missing.length && needsProductionLink
                ? 'Các mã đã có trong danh mục. Bấm liên kết để tạo đủ dòng trong lệnh trung tâm.'
                : result.summary?.write_configured
                ? 'Chỉ các dòng “Sẽ tạo” được append vào Google Sheet.'
                : 'Chưa cấu hình quyền ghi Google Sheet.';
            document.getElementById('applyVariantsBtn').disabled = requiresSizes
                || (missing.length > 0 && !result.summary?.write_configured);
            updateVariantApplyButtonLabel();
            setStatus(missing.length
                ? `Lệnh ${orderCode} có ${missing.length} mã size/màu cần tách. Bấm Lưu + in để kiểm tra và tạo mã.`
                : `Lệnh ${orderCode} đã có mã size/màu nhưng chưa tách dòng ở lệnh trung tâm.`, 'error');
            if (window.lucide) lucide.createIcons();
        }

        function previewManualVariantSizes() {
            const sizes = document.getElementById('variantSizeInput').value
                .split(/[\t,;|\n\r]+/)
                .map(value => value.trim())
                .filter((value, index, all) => value && all.indexOf(value) === index);
            if (sizes.length < 2) {
                setStatus('Cần dán ít nhất 2 size để tách mã.', 'error');
                return;
            }
            prepareProductionVariants(pendingVariantOrder, sizes).catch(error => setStatus(error.message, 'error'));
        }

        function updateVariantApplyButtonLabel() {
            const button = document.getElementById('applyVariantsBtn');
            const missingCount = pendingVariantPlans.filter(plan => !plan.exists).length;
            if (missingCount > 0) {
                button.innerHTML = `<i data-lucide="list-plus"></i>Tạo ${missingCount} mã + liên kết`;
            } else {
                button.innerHTML = `<i data-lucide="link-2"></i>Liên kết ${pendingVariantPlans.length} dòng`;
            }
            if (window.lucide) lucide.createIcons();
        }

        async function applyProductionVariants() {
            if (!pendingVariantOrder) return;
            const button = document.getElementById('applyVariantsBtn');
            const missingCount = pendingVariantPlans.filter(plan => !plan.exists).length;
            const variants = Array.from(document.querySelectorAll('#variantPreviewRows tr')).map(row => {
                const input = row.querySelector('.variant-code-input');
                return input ? {
                    order_id:Number(row.dataset.variantOrderId),
                    variant_key:String(row.dataset.variantKey || ''),
                    new_code:input.value.trim().toUpperCase(),
                } : null;
            }).filter(Boolean);
            if (variants.some(item => !item.new_code)) {
                setStatus('Mã biến thể không được để trống.', 'error');
                return;
            }
            button.disabled = true;
            button.innerHTML = `<span class="spinner-border spinner-border-sm"></span>${missingCount > 0 ? 'Đang tạo và liên kết...' : 'Đang liên kết...'}`;
            try {
                const response = await fetch('/api/danh-muc-noi-bo/bien-the-lenh-san-xuat', {
                    method:'POST',
                    headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                    body:JSON.stringify({
                        production_order:pendingVariantOrder,
                        apply:true,
                        sizes:pendingVariantSizes,
                        manual_variants:pendingVariantInputs,
                        cross_order_variants:pendingCrossOrderVariants,
                        variants
                    }),
                });
                const result = await jsonOrError(response, 'Không tạo được mã biến thể');
                applyVariantPlansToRows(result.data || [], true);
                rememberCatalogItems((result.data || []).map(plan => ({
                    code:plan.final_code,
                    value:plan.final_code,
                    name:plan.item_name,
                    size:plan.size,
                    color:plan.color,
                    unit:plan.unit,
                })));
                renderInternalCatalogOptions();
                const created = Number(result.summary?.created || 0);
                const linked = Number(result.summary?.linked || 0);
                pendingVariantOrder = '';
                pendingVariantPlans = [];
                pendingVariantSizes = [];
                pendingVariantInputs = [];
                pendingCrossOrderVariants = [];
                pendingVariantNeedsLink = false;
                document.getElementById('variantDialog').close();
                setStatus(`Đã tạo ${created} mã mới và liên kết ${linked} biến thể của lệnh. Nhập số lượng theo từng size.`, 'ok');
            } catch (error) {
                setStatus(error.message, 'error');
            } finally {
                button.disabled = false;
                updateVariantApplyButtonLabel();
            }
        }

        function loadProductionOrderFromQuery() {
            const params = new URLSearchParams(window.location.search);
            const productionOrder = params.get('production_order');
            if (!productionOrder) return;

            chooseReceiptKind('finished');

            const firstInput = document.querySelector('.production-order');
            if (!firstInput) return;
            firstInput.value = productionOrder;
            applyProductionOrder(firstInput);
        }

        function renderInternalCatalogOptions() {
            document.getElementById('internalCatalogOptions').innerHTML = internalCatalogItems.map(item => {
                const label = [
                    item.name,
                    item.size ? `Size ${item.size}` : '',
                    item.color ? `Màu ${item.color}` : '',
                    item.unit,
                    item.shelf ? `Kệ ${item.shelf}` : '',
                ].filter(Boolean).join(' - ');
                return `<option value="${esc(item.value || item.code || item.name || '')}" label="${esc(label)}"></option>`;
            }).join('');
        }

        function catalogKey(value) {
            return String(value || '').trim().toUpperCase();
        }

        function rememberCatalogItems(items) {
            const known = new Map(internalCatalogItems.map(item => [catalogKey(item.code || item.value || item.name), item]));
            (items || []).forEach(item => {
                const key = catalogKey(item.code || item.value || item.name);
                if (key && !known.has(key)) known.set(key, item);
            });
            internalCatalogItems = Array.from(known.values());
            internalCatalogItems.forEach(item => {
                [item.code, item.value, item.name].forEach(value => {
                    const key = catalogKey(value);
                    if (key) catalogExactCache.set(key, item);
                });
            });
        }

        function searchInternalCatalog(input) {
            const keyword = input.value.trim();
            clearTimeout(catalogSearchTimer);
            if (!keyword) return;
            const key = catalogKey(keyword);
            if (catalogSearchCache.has(key)) {
                internalCatalogItems = catalogSearchCache.get(key);
                renderInternalCatalogOptions();
                return;
            }
            catalogSearchTimer = setTimeout(() => {
                latestCatalogSearchKey = key;
                fetch(`/api/ma-noi-bo-danh-muc?keyword=${encodeURIComponent(keyword)}&limit=30&with_color=0`)
                    .then(response => jsonOrError(response, 'Không tải được danh mục'))
                    .then(result => {
                        if (latestCatalogSearchKey !== key) return;
                        catalogSearchCache.set(key, result.data || []);
                        rememberCatalogItems(result.data || []);
                        renderInternalCatalogOptions();
                    })
                    .catch(() => {});
            }, 90);
        }

        function findCatalogItem(code) {
            const normalized = catalogKey(code);
            if (catalogExactCache.has(normalized)) return catalogExactCache.get(normalized);
            return internalCatalogItems.find(item => {
                return [item.code, item.value, item.name].some(value => catalogKey(value) === normalized);
            });
        }

        function fetchCatalogExact(code) {
            const normalized = String(code || '').trim();
            if (!normalized) return Promise.resolve(null);
            const found = findCatalogItem(normalized);
            if (found) return Promise.resolve(found);
            const key = catalogKey(normalized);
            if (catalogSearchCache.has(key)) {
                internalCatalogItems = catalogSearchCache.get(key);
                renderInternalCatalogOptions();
                return Promise.resolve(findCatalogItem(normalized));
            }
            return fetch(`/api/ma-noi-bo-danh-muc?keyword=${encodeURIComponent(normalized)}&limit=10&with_color=0`)
                .then(response => jsonOrError(response, 'Không tải được danh mục'))
                .then(result => {
                    catalogSearchCache.set(key, result.data || []);
                    rememberCatalogItems(result.data || []);
                    renderInternalCatalogOptions();
                    return findCatalogItem(normalized);
                })
                .catch(() => null);
        }

        function applyCatalogItem(input, item) {
            const row = input.closest('tr');
            input.value = item.code || item.value || input.value;
            row.querySelector('.item-name').value = item.name || row.querySelector('.item-name').value;
            row.querySelector('.item-size').value = item.size || row.querySelector('.item-size').value;
            row.querySelector('.item-color').value = item.color || row.querySelector('.item-color').value;
            row.querySelector('.item-unit').value = item.unit || row.querySelector('.item-unit').value || 'Cái';
            renderRowImage(row, item.image_url || '');
            if (item.shelf && !row.querySelector('.line-note').value.trim()) {
                row.querySelector('.line-note').value = `Kệ danh mục: ${item.shelf}`;
            }
            const state = row.querySelector('.row-state');
            state.className = 'row-state is-ok';
            state.textContent = item.shelf ? `Đúng danh mục - kệ ${item.shelf}` : 'Đúng danh mục';
        }

        function renderRowImage(row, imageUrl) {
            const box = row.querySelector('.quick-item-image');
            if (!box) return;
            const url = String(imageUrl || '').trim();
            if (!url) {
                box.className = 'quick-item-image is-empty';
                box.innerHTML = '';
                return;
            }
            box.className = 'quick-item-image';
            box.innerHTML = `<img src="${esc(url)}" alt="">`;
        }

        function applyInternalCatalog(input, moveToQty = false) {
            const code = input.value.trim();
            if (!code) return Promise.resolve();
            return fetchCatalogExact(code).then(item => {
                const state = input.closest('tr').querySelector('.row-state');
                if (!item) {
                    state.className = 'row-state is-warn';
                    state.textContent = 'Mã mới - sẽ append khi lưu';
                    return;
                }
                applyCatalogItem(input, item);
                return loadOpenOrdersForItem(input).then(orderCount => {
                    if (!moveToQty) return;
                    const row = input.closest('tr');
                    if (orderCount > 0) {
                        row.querySelector('.order-suggestions').focus();
                    } else {
                        row.querySelector('.quantity').focus();
                    }
                });
            });
        }

        function collectLines() {
            const isSemiFinished = selectedReceiptKind === 'semi_finished';
            const locationCode = selectedReceiptLocation();
            return Array.from(document.querySelectorAll('#quickRows tr')).map(row => {
                const orderInput = row.querySelector('.production-order');
                const sourceOrder = orderInput.value.trim();
                return {
                    category: row.querySelector('.item-name').value.trim(),
                    ma_sp: '',
                    internal_item_code: row.querySelector('.internal-code').value.trim(),
                    size: row.querySelector('.item-size').value.trim(),
                    color: row.querySelector('.item-color').value.trim(),
                    side: '',
                    dvt: row.querySelector('.item-unit').value.trim() || 'Cái',
                    quantity: num(row.querySelector('.quantity').value),
                    location_code: locationCode,
                    note: row.querySelector('.line-note').value.trim(),
                    production_order_id: orderInput.dataset.productionOrderId || null,
                    production_order: isSemiFinished ? '' : sourceOrder,
                    purchase_order: isSemiFinished
                        ? (sourceOrder || orderInput.dataset.purchaseOrder || '')
                        : (orderInput.dataset.purchaseOrder || ''),
                    customer: orderInput.dataset.customer || '',
                };
            }).filter(line => line.internal_item_code || line.quantity);
        }

        function validLines() {
            return collectLines().filter(line => line.internal_item_code && line.quantity > 0);
        }

        async function prepareFormVariantSplit() {
            if (selectedReceiptKind !== 'finished'
                || pendingVariantNeedsLink
                || pendingVariantPlans.some(plan => plan.requires_split && !plan.exists)) {
                return false;
            }

            const groups = new Map();
            Array.from(document.querySelectorAll('#quickRows tr')).forEach(row => {
                const quantity = num(row.querySelector('.quantity').value);
                const orderInput = row.querySelector('.production-order');
                const order = orderInput.value.trim();
                const orderId = Number(orderInput.dataset.productionOrderId || 0);
                const code = row.querySelector('.internal-code').value.trim();
                const size = row.querySelector('.item-size').value.trim();
                const color = row.querySelector('.item-color').value.trim();
                if (quantity <= 0 || !order || !code) return;
                const key = catalogKey(code);
                if (!groups.has(key)) groups.set(key, {code, rows:[]});
                groups.get(key).rows.push({row, order, orderId, size, color, quantity});
            });

            const variantGroups = Array.from(groups.values()).filter(item => {
                const signatures = new Set(item.rows.map(entry => `${catalogKey(entry.size)}|${catalogKey(entry.color)}`));
                return signatures.size > 1;
            });
            const sameOrderGroup = variantGroups.map(group => {
                const rowsByOrder = new Map();
                group.rows.forEach(entry => {
                    const key = entry.orderId || catalogKey(entry.order);
                    if (!rowsByOrder.has(key)) rowsByOrder.set(key, []);
                    rowsByOrder.get(key).push(entry);
                });
                const rows = Array.from(rowsByOrder.values()).find(entries => {
                    return new Set(entries.map(entry => `${catalogKey(entry.size)}|${catalogKey(entry.color)}`)).size > 1;
                });
                return rows ? {code:group.code, rows} : null;
            }).find(Boolean);
            const group = sameOrderGroup || variantGroups[0];
            if (!group) return false;

            const orderKeys = new Set(group.rows.map(entry => entry.orderId || catalogKey(entry.order)));
            if (orderKeys.size > 1) {
                const crossVariantsByOrder = new Map();
                group.rows.forEach(entry => {
                    const orderKey = entry.orderId || catalogKey(entry.order);
                    if (!crossVariantsByOrder.has(orderKey)) {
                        crossVariantsByOrder.set(orderKey, {
                            order_id: entry.orderId || null,
                            production_order: entry.order,
                            variant_key: `CROSS:${orderKey}`,
                            base_code: group.code,
                            size: entry.size,
                            color: entry.color,
                            quantity: 0,
                        });
                    }
                    crossVariantsByOrder.get(orderKey).quantity += entry.quantity;
                });
                const crossOrderVariants = Array.from(crossVariantsByOrder.values());
                await prepareProductionVariants(
                    group.rows[0].order,
                    [],
                    [],
                    false,
                    crossOrderVariants
                );
                return pendingVariantNeedsLink
                    || pendingVariantPlans.some(plan => plan.requires_split && !plan.exists);
            }

            const variantsBySignature = new Map();
            group.rows.forEach(entry => {
                const signature = `${catalogKey(entry.size)}|${catalogKey(entry.color)}`;
                if (!variantsBySignature.has(signature)) {
                    variantsBySignature.set(signature, {
                        variant_key: `FORM:${variantsBySignature.size + 1}`,
                        size: entry.size,
                        color: entry.color,
                        quantity: 0,
                    });
                }
                const variant = variantsBySignature.get(signature);
                variant.quantity += num(entry.row.querySelector('.quantity').value);
                entry.row.dataset.variantKey = variant.variant_key;
            });

            const manualVariants = Array.from(variantsBySignature.values());
            await prepareProductionVariants(group.rows[0].order, [], manualVariants, false);
            return pendingVariantNeedsLink
                || pendingVariantPlans.some(plan => plan.requires_split && !plan.exists);
        }

        function updateSummary() {
            const lines = validLines();
            document.getElementById('lineCount').textContent = fmt(lines.length);
            document.getElementById('totalQty').textContent = fmt(lines.reduce((sum, line) => sum + line.quantity, 0));
        }

        async function checkAllCatalog() {
            const rows = Array.from(document.querySelectorAll('#quickRows tr'));
            const invalidCodes = [];
            for (const row of rows) {
                const codeInput = row.querySelector('.internal-code');
                const qty = num(row.querySelector('.quantity').value);
                if (!codeInput.value.trim() && !qty) continue;
                await applyInternalCatalog(codeInput, false);
                if (qty > 0 && row.querySelector('.row-state').classList.contains('is-warn')) {
                    invalidCodes.push(codeInput.value.trim() || `Dòng ${rows.indexOf(row) + 1}`);
                }
            }
            return [...new Set(invalidCodes)];
        }

        async function ensureMissingCatalogCodes(codes) {
            const missingKeys = new Set((codes || []).map(catalogKey).filter(Boolean));
            const processed = new Set();
            const rows = Array.from(document.querySelectorAll('#quickRows tr'));
            let appendedCount = 0;

            for (const row of rows) {
                const codeInput = row.querySelector('.internal-code');
                const originalCode = codeInput.value.trim();
                const key = catalogKey(originalCode);
                if (!missingKeys.has(key) || processed.has(key) || num(row.querySelector('.quantity').value) <= 0) {
                    continue;
                }
                processed.add(key);

                const matchingRows = rows.filter(candidate => {
                    return catalogKey(candidate.querySelector('.internal-code').value) === key
                        && num(candidate.querySelector('.quantity').value) > 0;
                });
                const orderInput = row.querySelector('.production-order');
                const itemName = row.querySelector('.item-name').value.trim() || originalCode;
                const unit = row.querySelector('.item-unit').value.trim() || 'Cái';
                const size = row.querySelector('.item-size').value.trim();
                const color = row.querySelector('.item-color').value.trim();
                const response = await fetch('/api/danh-muc-noi-bo/tao-tu-lenh', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                    body: JSON.stringify({
                        item_code: originalCode,
                        source_item_code: orderInput.dataset.sourceItemCode || originalCode,
                        production_order_line_id: Number(orderInput.dataset.productionOrderId || 0) || null,
                        item_name: itemName,
                        unit,
                        size,
                        color,
                        item_group: selectedReceiptKind === 'semi_finished' ? 'BTP' : 'TP',
                    })
                });
                const result = await jsonOrError(response, `Không thêm được mã ${originalCode} vào DANH MỤC`);
                const savedCode = result.data?.item_code || originalCode;
                const catalogItem = {
                    code: savedCode,
                    value: savedCode,
                    name: result.data?.item_name || itemName,
                    unit,
                    size,
                    color,
                    image_url: result.data?.image_url || '',
                };
                rememberCatalogItems([catalogItem]);
                matchingRows.forEach(target => applyCatalogItem(target.querySelector('.internal-code'), catalogItem));
                if (result.data?.appended_to_sheet) appendedCount += 1;
            }

            renderInternalCatalogOptions();
            return {createdCount: processed.size, appendedCount};
        }

        async function warnDuplicates(lines) {
            const response = await fetch('/api/kiem-ton-kho/phieu-nhap-tp/kiem-tra-trung', {
                method: 'POST',
                headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify({
                    checked_at: selectedReceiptDate(),
                    lines,
                })
            });
            const result = await response.json().catch(() => ({}));
            const first = (result.duplicates || [])[0];
            if (!first) return true;
            const variant = [
                first.production_order ? `Lệnh ${first.production_order}` : '',
                first.size ? `size ${first.size}` : '',
                first.color ? `màu ${first.color}` : '',
                first.side ? `mặt ${first.side}` : '',
            ].filter(Boolean).join(' · ');
            const receipts = (first.matches || []).map(item => item.receipt_code).filter(Boolean).join(', ');
            return confirm(
                `CẢNH BÁO NGHI NHẬP TRÙNG\n\n${first.internal_item_code}`
                + `${variant ? ` · ${variant}` : ''}\nSL ${fmt(first.quantity)}`
                + ` đã có trong ${receipts || 'phiếu nhập cũ'} cùng ngày.\n\nVẫn lưu phiếu này?`
            );
        }

        async function warnProductionOrderOverages(lines) {
            if (selectedReceiptKind !== 'finished') return true;
            const proposedByOrder = new Map();
            lines.forEach(line => {
                const order = String(line.production_order || '').trim();
                if (!order) return;
                proposedByOrder.set(order, (proposedByOrder.get(order) || 0) + Number(line.quantity || 0));
            });
            if (!proposedByOrder.size) return true;

            const warnings = [];
            for (const [order, proposed] of proposedByOrder.entries()) {
                const response = await fetch(`/api/lenh-san-xuat-sheet?production_order=${encodeURIComponent(order)}&limit=500`);
                const result = await jsonOrError(response, `Khong kiem tra duoc lenh ${order}`);
                const progress = result.summary?.receipt_progress || {};
                const planned = Number(progress.planned_quantity || 0);
                const received = Number(progress.received_quantity || 0);
                const afterSave = received + proposed;
                if (planned > 0 && afterSave > planned + 0.0001) {
                    warnings.push(`${order}: k\u1ebf ho\u1ea1ch ${fmt(planned)}, \u0111\u00e3 nh\u1eadp ${fmt(received)}, phi\u1ebfu n\u00e0y ${fmt(proposed)}, s\u1ebd d\u01b0 ${fmt(afterSave - planned)}`);
                }
            }
            if (!warnings.length) return true;
            return confirm(`C\u1ea2NH B\u00c1O NH\u1eacP D\u01af THEO L\u1ec6NH\n\n${warnings.join('\n')}\n\nFIFO \u0111\u00e3 \u0111\u01b0\u1ee3c t\u00ednh trong s\u1ed1 \u0111\u00e3 nh\u1eadp. V\u1eabn l\u01b0u phi\u1ebfu?`);
        }
        async function saveAndPrint() {
            if (receiptRequestPending) return;
            const receiptDate = selectedReceiptDate();
            if (!receiptDate) {
                setStatus('Ngày nhập phải đúng định dạng dd/mm/yyyy.', 'error');
                document.getElementById('receiptDate').focus();
                return;
            }
            receiptRequestPending = true;
            const saveButton = document.getElementById('savePrintBtn');
            saveButton.disabled = true;
            let receiptPrintWindow = null;
            let issuePrintWindow = null;
            setOperationLoading(true, 'Đang kiểm tra phiếu', 'Đang kiểm tra lệnh sản xuất, mã nội bộ và dữ liệu danh mục.');

            try {
            await variantCheckPromise;
            if (variantCheckError) {
                setStatus(`Chưa kiểm tra được size/màu: ${variantCheckError.message}`, 'error');
                return;
            }
            try {
                await prepareFormVariantSplit();
            } catch (error) {
                setStatus(`Không kiểm tra được size/màu trên phiếu: ${error.message}`, 'error');
                return;
            }
            if (pendingVariantNeedsLink || pendingVariantPlans.some(plan => plan.requires_split && !plan.exists)) {
                const dialog = document.getElementById('variantDialog');
                if (!dialog.open) dialog.showModal();
                setStatus('Cùng lệnh có nhiều size/màu. Tạo các mã còn thiếu trước khi lưu phiếu.', 'error');
                return;
            }
            setStatus('Đang kiểm tra mã danh mục...');
            setOperationLoading(true, 'Đang kiểm tra danh mục', 'Đối chiếu mã nội bộ trước khi lưu phiếu.');
            const invalidCatalogCodes = await checkAllCatalog();
            let lines = validLines();
            updateSummary();
            if (!lines.length) {
                setStatus('Cần ít nhất 1 dòng có mã nội bộ và số lượng lớn hơn 0.', 'error');
                return;
            }
            let catalogCreation = {createdCount:0, appendedCount:0};
            if (invalidCatalogCodes.length) {
                setStatus(`Đang thêm ${invalidCatalogCodes.length} mã mới vào DANH MỤC...`);
                setOperationLoading(true, 'Đang thêm mã mới', `Đang append ${invalidCatalogCodes.length} mã vào Google Sheet DANH MỤC.`);
                try {
                    catalogCreation = await ensureMissingCatalogCodes(invalidCatalogCodes);
                } catch (error) {
                    setStatus(error.message, 'error');
                    document.querySelector('#quickRows tr .row-state.is-warn')?.closest('tr')?.querySelector('.internal-code')?.focus();
                    return;
                }
                const unresolvedCodes = await checkAllCatalog();
                if (unresolvedCodes.length) {
                    setStatus(`Chưa tạo được mã trong DANH MỤC: ${unresolvedCodes.join(', ')}. Phiếu chưa được lưu.`, 'error');
                    return;
                }
                lines = validLines();
                updateSummary();
                setStatus(`Đã tạo ${catalogCreation.createdCount} mã danh mục; ${catalogCreation.appendedCount} mã được append vào Google Sheet.`);
            }
            setOperationLoading(true, 'Đang kiểm tra phiếu', 'Kiểm tra trùng và số lượng theo lệnh sản xuất.');
            if (!await warnDuplicates(lines)) {
                setStatus('Đã hủy lưu vì phiếu có dòng nghi trùng.', 'error');
                return;
            }
            if (!await warnProductionOrderOverages(lines)) {
                setStatus('Da huy luu vi so luong vuot lenh san xuat.', 'error');
                return;
            }

            const isBtp = selectedReceiptKind === 'semi_finished';
            const exportImmediately = !isBtp && document.getElementById('exportImmediately').checked;
            let customerName = document.getElementById('customerName').value.trim();
            const issueDate = displayToIsoDate(document.getElementById('issueDate').value);
            if (exportImmediately && (!customerName || !issueDate)) {
                setStatus(!customerName ? 'Chọn khách hàng trước khi nhập + xuất.' : 'Ngày xuất phải đúng định dạng dd/mm/yyyy.', 'error');
                (!customerName ? document.getElementById('customerName') : document.getElementById('issueDate')).focus();
                return;
            }
            if (exportImmediately) {
                try {
                    const customer = await requireCatalogCustomer(customerName);
                    customerName = customer.name;
                    document.getElementById('customerName').value = customer.name;
                } catch (error) {
                    setStatus(error.message, 'error');
                    document.getElementById('customerName').focus();
                    return;
                }
            }

            const total = lines.reduce((sum, line) => sum + line.quantity, 0);
            const confirmation = isBtp
                ? `Tạo 1 phiếu nhập BTP gồm ${lines.length} dòng, ${lines.length} lệnh BTP con và 1 phiếu xuất nhóm sang sản xuất?\n\nTổng số lượng: ${fmt(total)}`
                : exportImmediately
                    ? `Nhập kho và xuất ngay ${lines.length} dòng cho ${customerName}?\n\nNgày xuất: ${isoToDisplayDate(issueDate)}\nTổng số lượng: ${fmt(total)}`
                    : `Lưu 1 phiếu nhập thành phẩm gồm ${lines.length} dòng?\n\nTổng số lượng: ${fmt(total)}`;
            if (!confirm(confirmation)) {
                setStatus('Chưa lưu. Có thể bấm Chọn lại để đổi loại phiếu.');
                return;
            }

            setOperationLoading(
                true,
                exportImmediately ? 'Đang nhập và xuất thành phẩm' : (isBtp ? 'Đang tạo phiếu BTP' : 'Đang lưu phiếu nhập'),
                exportImmediately ? 'Đang ghi tồn kho, trừ FIFO và tạo phiếu xuất.' : 'Đang ghi dữ liệu và chuẩn bị phiếu in.'
            );

            receiptPrintWindow = window.open('', '_blank');
            issuePrintWindow = exportImmediately ? window.open('', '_blank') : null;
            setStatus(exportImmediately ? 'Đang nhập kho và tạo phiếu xuất...' : 'Đang lưu phiếu nhập...');
            await fetch('/api/kiem-ton-kho/phieu-nhap-tp', {
                method: 'POST',
                headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify({
                    receipt_kind: selectedReceiptKind,
                    send_to_production: isBtp,
                    export_to_customer: exportImmediately,
                    customer_name: exportImmediately ? customerName : null,
                    issue_date: exportImmediately ? issueDate : null,
                    location_code: selectedReceiptLocation(),
                    ma_ko: '',
                    checked_at: receiptDate,
                    note: document.getElementById('receiptNote').value.trim(),
                    lines,
                })
            })
                .then(response => jsonOrError(response, 'Không lưu được phiếu nhập'))
                .then(result => {
                    const productionCodes = result.production_issue?.lines
                        ? [...new Set(result.production_issue.lines.map(line => line.production_order).filter(Boolean))]
                        : [];
                    const suffix = productionCodes.length ? ` Đã tạo ${productionCodes.length} lệnh BTP và gửi sản xuất.` : '';
                    const receiptCode = result.data?.receipt_code || 'phiếu nhập';
                    if (result.customer_issue_failed) {
                        setStatus(`${result.message} Mở Danh sách phiếu để bấm Xuất TP lại.`, 'error');
                        alert(`${result.message}\n\nPhiếu nhập ${receiptCode} đã được lưu. Vào Danh sách phiếu để tạo lại phiếu xuất.`);
                    } else if (result.customer_issue) {
                        setStatus(`Đã tạo ${receiptCode} và ${result.customer_issue.issue_code || 'phiếu xuất thành phẩm'}.`, 'ok');
                    } else {
                        setStatus(`Đã lưu ${receiptCode}.${suffix} Đang mở phiếu in...`, 'ok');
                    }
                    if (result.receipt_print_url && receiptPrintWindow) {
                        receiptPrintWindow.location.href = result.receipt_print_url;
                    } else if (receiptPrintWindow) {
                        receiptPrintWindow.close();
                    }
                    if (result.customer_issue_print_url && issuePrintWindow) {
                        issuePrintWindow.location.href = result.customer_issue_print_url;
                    } else if (issuePrintWindow) {
                        issuePrintWindow.close();
                    }
                    returnToChooser();
                });
            } catch (error) {
                if (receiptPrintWindow) receiptPrintWindow.close();
                if (issuePrintWindow) issuePrintWindow.close();
                setStatus(error.message, 'error');
                alert(error.message);
            } finally {
                receiptRequestPending = false;
                saveButton.disabled = false;
                setOperationLoading(false);
            }
        }

        function resetRows(clearHeader = true) {
            pendingVariantOrder = '';
            pendingVariantPlans = [];
            pendingVariantSizes = [];
            pendingVariantInputs = [];
            pendingCrossOrderVariants = [];
            pendingVariantNeedsLink = false;
            variantCheckPromise = Promise.resolve();
            variantCheckError = null;
            document.getElementById('variantSizeInput').value = '';
            if (clearHeader) document.getElementById('receiptNote').value = '';
            renderRows();
            updateSummary();
            document.querySelector('.internal-code')?.focus();
        }

        function chooseReceiptKind(kind) {
            selectedReceiptKind = kind;
            const isBtp = kind === 'semi_finished';
            document.getElementById('modeChooser').classList.add('d-none');
            document.getElementById('receiptWorkspace').classList.remove('d-none');
            document.getElementById('quickExportPanel').classList.toggle('d-none', isBtp);
            document.getElementById('exportImmediately').checked = false;
            toggleImmediateExportFields();
            document.getElementById('workspaceTitle').textContent = isBtp ? 'Nhập bán thành phẩm' : 'Nhập thành phẩm';
            document.getElementById('savePrintBtn').innerHTML = isBtp
                ? '<i data-lucide="send"></i>Lưu + gửi sản xuất'
                : '<i data-lucide="printer"></i>Lưu + in';
            setStatus(isBtp
                ? 'Các dòng sẽ nằm chung một phiếu xuất BTP; mỗi dòng có một lệnh BTP riêng.'
                : 'Nhập mã và số lượng, bấm Enter để xuống dòng.');
            loadRecentReceipts();
            if (window.lucide) lucide.createIcons();
            setTimeout(() => document.querySelector('.production-order')?.focus(), 0);
        }

        function returnToChooser() {
            selectedReceiptKind = '';
            pendingVariantOrder = '';
            pendingVariantPlans = [];
            pendingVariantSizes = [];
            pendingVariantInputs = [];
            pendingCrossOrderVariants = [];
            pendingVariantNeedsLink = false;
            variantCheckPromise = Promise.resolve();
            variantCheckError = null;
            document.getElementById('variantSizeInput').value = '';
            document.getElementById('receiptWorkspace').classList.add('d-none');
            document.getElementById('modeChooser').classList.remove('d-none');
            document.getElementById('receiptNote').value = '';
            document.getElementById('receiptLocation').value = 'CHUA-XEP';
            updateReceiptLocation(true);
            document.getElementById('exportImmediately').checked = false;
            document.getElementById('customerName').value = '';
            document.getElementById('issueDate').value = document.getElementById('receiptDate').value || isoToDisplayDate(localIsoDate());
            delete document.getElementById('issueDate').dataset.changedByUser;
            toggleImmediateExportFields();
            renderRows();
            updateSummary();
        }

        function toggleImmediateExportFields() {
            const enabled = selectedReceiptKind === 'finished'
                && document.getElementById('exportImmediately').checked;
            document.getElementById('quickExportSwitch').classList.toggle('is-active', enabled);
            document.getElementById('customerField').classList.toggle('d-none', !enabled);
            document.getElementById('issueDateField').classList.toggle('d-none', !enabled);
            document.getElementById('savePrintBtn').innerHTML = enabled
                ? '<i data-lucide="send"></i>Nhập + xuất + in'
                : (selectedReceiptKind === 'semi_finished'
                    ? '<i data-lucide="send"></i>Lưu + gửi sản xuất'
                    : '<i data-lucide="printer"></i>Lưu + in');
            if (window.lucide) lucide.createIcons();
        }

        function loadRecentReceipts() {
            const params = new URLSearchParams({
                receipt_date: selectedReceiptDate(),
                receipt_kind: selectedReceiptKind || 'finished',
            });
            fetch(`/api/kiem-ton-kho/phieu-nhap-tp?${params}`)
                .then(response => jsonOrError(response, 'Không tải được phiếu vừa nhập'))
                .then(result => {
                    const rows = (result.data || []).slice(0, 5);
                    document.getElementById('recentReceipts').innerHTML = rows.map(row => `
                        <div class="recent-item">
                            <div class="recent-main">
                                <div class="recent-code">${esc(row.receipt_code)}</div>
                                <div class="recent-meta">
                                    <span class="text-muted">${esc(row.location_code || 'CHUA-XEP')} - ${fmt(row.lines_count || 0)} dòng</span>
                                    <span class="recent-flow ${row.receipt_flow === 'receipt_and_customer_issue' ? 'is-issued' : ''}">${esc(row.operation_label || 'Chỉ nhập kho')}</span>
                                    ${row.issue_code ? `<span class="text-muted">${esc(row.issue_code)}</span>` : ''}
                                </div>
                            </div>
                            <div class="recent-actions">
                                <a class="quick-btn py-1 px-2 min-h-0" target="_blank" href="${esc(row.print_url || '#')}">In nhập</a>
                                ${row.issue_print_url ? `<a class="quick-btn quick-btn-primary py-1 px-2 min-h-0" target="_blank" href="${esc(row.issue_print_url)}">In xuất</a>` : ''}
                            </div>
                        </div>
                    `).join('') || '<div class="text-muted">Chưa có phiếu hôm nay.</div>';
                })
                .catch(() => {});
        }

        document.getElementById('savePrintBtn').addEventListener('click', saveAndPrint);
        document.getElementById('applyVariantsBtn').addEventListener('click', applyProductionVariants);
        document.getElementById('previewVariantSizesBtn').addEventListener('click', previewManualVariantSizes);
        document.getElementById('closeVariantDialog').addEventListener('click', () => document.getElementById('variantDialog').close());
        document.getElementById('cancelVariantDialog').addEventListener('click', () => document.getElementById('variantDialog').close());
        document.getElementById('addRowBtn').addEventListener('click', addQuickRow);
        document.getElementById('clearFormBtn').addEventListener('click', () => resetRows(true));
        document.getElementById('exportImmediately').addEventListener('change', () => {
            if (!document.getElementById('issueDate').value) {
                document.getElementById('issueDate').value = document.getElementById('receiptDate').value || isoToDisplayDate(localIsoDate());
            }
            toggleImmediateExportFields();
            if (document.getElementById('exportImmediately').checked) {
                document.getElementById('customerName').focus();
            }
        });
        document.getElementById('issueDate').addEventListener('change', event => {
            event.currentTarget.dataset.changedByUser = '1';
        });
        document.getElementById('receiptDate').addEventListener('change', () => {
            const issueDateInput = document.getElementById('issueDate');
            if (issueDateInput.dataset.changedByUser !== '1') {
                issueDateInput.value = document.getElementById('receiptDate').value;
            }
            document.querySelectorAll('.order-suggestions').forEach(select => {
                select.innerHTML = '';
                select.classList.add('d-none');
            });
            document.querySelectorAll('.internal-code').forEach(input => {
                if (input.value.trim()) loadOpenOrdersForItem(input);
            });
            loadRecentReceipts();
        });
        document.getElementById('changeModeBtn').addEventListener('click', () => {
            if (validLines().length && !confirm('Bỏ dữ liệu đang nhập để chọn lại loại phiếu?')) return;
            returnToChooser();
        });
        document.querySelectorAll('[data-receipt-kind]').forEach(button => {
            button.addEventListener('click', () => chooseReceiptKind(button.dataset.receiptKind));
        });
        const customerNameInput = document.getElementById('customerName');
        customerNameInput.addEventListener('focus', () => loadCustomerSuggestions(customerNameInput.value));
        customerNameInput.addEventListener('input', () => loadCustomerSuggestions(customerNameInput.value));
        ['receiptDate', 'issueDate'].forEach(id => {
            document.getElementById(id).addEventListener('blur', event => normalizeDateField(event.currentTarget));
        });

        document.getElementById('receiptDate').value = isoToDisplayDate(localIsoDate());
        document.getElementById('issueDate').value = document.getElementById('receiptDate').value;
        document.getElementById('receiptLocation').addEventListener('input', () => updateReceiptLocation(false));
        document.getElementById('receiptLocation').addEventListener('blur', () => updateReceiptLocation(true));
        updateReceiptLocation(true);
        renderRows();
        loadProductionOrderFromQuery();
        updateSummary();
        loadRecentReceipts();
        loadCustomerSuggestions();
        loadReceiptLocations();
        if (window.lucide) lucide.createIcons();
    </script>
</body>
</html>
