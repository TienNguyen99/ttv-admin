<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Xuất thành phẩm nhanh</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --blue: #2563eb;
            --blue-dark: #15366d;
            --blue-soft: #eaf4ff;
            --line: #c7d7ee;
            --ink: #0f172a;
            --muted: #64748b;
            --bg: #f5faff;
            --good: #15803d;
            --danger: #b91c1c;
        }
        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .issue-shell {
            width: min(1500px, calc(100% - 28px));
            margin: 0 auto;
            padding: 12px 0 28px;
        }
        .issue-header,
        .issue-panel {
            border: 1px solid #dbeafe;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 10px 26px rgba(37, 99, 235, .07);
        }
        .issue-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
            padding: 12px 14px;
        }
        .issue-title {
            margin: 0;
            color: var(--blue-dark);
            font-size: 24px;
            font-weight: 800;
        }
        .issue-actions,
        .panel-tools {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        .btn-quick {
            min-height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 0 14px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            color: var(--blue-dark);
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-quick:hover,
        .btn-quick:focus-visible {
            border-color: #93c5fd;
            background: var(--blue-soft);
            color: var(--blue);
            outline: none;
        }
        .btn-primary-quick {
            min-width: 150px;
            border-color: var(--blue);
            background: var(--blue);
            color: #fff;
        }
        .btn-primary-quick:hover { background: #1d4ed8; color: #fff; }
        .btn-quick:disabled { cursor: wait; opacity: .65; }
        .issue-panel { overflow: hidden; }
        .panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 14px;
            border-bottom: 1px solid #e2e8f0;
        }
        .panel-title { margin: 0; font-size: 16px; font-weight: 800; }
        .pill {
            min-height: 34px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 0 10px;
            border: 1px solid #dbeafe;
            border-radius: 999px;
            background: #f8fbff;
            color: var(--blue-dark);
            font-size: 12px;
            font-weight: 800;
        }
        .issue-meta {
            display: grid;
            grid-template-columns: 180px minmax(180px, .8fr) minmax(180px, .8fr) minmax(240px, 1.5fr);
            gap: 10px;
            padding: 12px 14px;
            border-bottom: 1px solid #e2e8f0;
        }
        label {
            margin-bottom: 5px;
            color: #334155;
            font-size: 12px;
            font-weight: 800;
        }
        .form-control,
        .form-select {
            min-height: 40px;
            border-color: #b8c7dc;
            border-radius: 8px;
            color: var(--ink);
            font-weight: 600;
        }
        .form-control:focus,
        .form-select:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .14);
        }
        .table-wrap {
            overflow: auto;
            padding: 0 14px 10px;
        }
        .issue-table {
            width: 100%;
            min-width: 1290px;
            border-collapse: separate;
            border-spacing: 0;
        }
        .issue-table th {
            position: sticky;
            top: 0;
            z-index: 2;
            padding: 9px 8px;
            background: #092a50;
            color: #fff;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .issue-table td {
            padding: 7px 5px;
            border-bottom: 1px solid #dbe3ef;
            vertical-align: top;
        }
        .issue-table .form-control { min-height: 38px; padding: 7px 9px; font-size: 13px; }
        .col-stt { width: 42px; text-align: center; }
        .col-order { width: 130px; }
        .col-code { width: 155px; }
        .col-name { width: 210px; }
        .col-variant { width: 125px; }
        .col-qty { width: 110px; }
        .col-unit { width: 90px; }
        .col-location { width: 250px; }
        .col-note { width: 160px; }
        .col-remove { width: 42px; }
        .suggest-wrap { position: relative; }
        .suggestions {
            position: absolute;
            z-index: 20;
            top: calc(100% + 4px);
            left: 0;
            width: min(440px, 82vw);
            max-height: 260px;
            overflow: auto;
            border: 1px solid #93c5fd;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 18px 45px rgba(15, 47, 99, .2);
        }
        .suggestion {
            width: 100%;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 4px 12px;
            padding: 9px 11px;
            border: 0;
            border-bottom: 1px solid #e2e8f0;
            background: #fff;
            color: var(--ink);
            text-align: left;
        }
        .suggestion:hover,
        .suggestion:focus-visible { background: var(--blue-soft); outline: none; }
        .suggestion strong { color: #1d4ed8; font-size: 13px; }
        .suggestion small { color: var(--muted); font-size: 11px; }
        .suggestion span { grid-row: 1 / 3; grid-column: 2; align-self: center; color: #475569; font-size: 11px; }
        .row-state { min-height: 16px; margin-top: 3px; color: var(--good); font-size: 10px; font-weight: 700; }
        .row-state.is-new { color: #a16207; }
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
        .remove-row {
            width: 34px;
            height: 34px;
            display: inline-grid;
            place-items: center;
            border: 1px solid #fecaca;
            border-radius: 8px;
            background: #fff;
            color: var(--danger);
        }
        .status {
            min-height: 42px;
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 14px 12px;
            padding: 9px 11px;
            border: 1px solid #dbeafe;
            border-radius: 8px;
            background: #f8fbff;
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
        }
        .status.is-error { border-color: #fecaca; background: #fff1f2; color: var(--danger); }
        .status.is-success { border-color: #bbf7d0; background: #f0fdf4; color: var(--good); }
        .status svg { width: 17px; height: 17px; flex: 0 0 auto; }
        .confirm-dialog {
            width: min(440px, calc(100% - 24px));
            padding: 0;
            border: 0;
            border-radius: 8px;
            box-shadow: 0 24px 70px rgba(15, 23, 42, .3);
        }
        .confirm-dialog::backdrop { background: rgba(15, 23, 42, .48); }
        .confirm-body { padding: 18px; }
        .confirm-body h2 { margin: 0 0 8px; color: var(--blue-dark); font-size: 18px; font-weight: 800; }
        .confirm-body p { margin: 0; color: #475569; white-space: pre-line; }
        .confirm-actions { display: flex; justify-content: flex-end; gap: 8px; padding: 12px 18px; border-top: 1px solid #e2e8f0; }
        .location-trigger {
            width: 100%;
            min-height: 38px;
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 7px 9px;
            border: 1px solid #b8c7dc;
            border-radius: 8px;
            background: #fff;
            color: #1e3a5f;
            font-size: 12px;
            font-weight: 800;
            text-align: left;
        }
        .location-trigger:hover { border-color: #60a5fa; background: #eff6ff; }
        .location-trigger:focus-visible { outline: 3px solid rgba(37, 99, 235, .18); border-color: var(--blue); }
        .location-trigger svg { width: 16px; height: 16px; flex: 0 0 auto; color: var(--blue); }
        .location-trigger span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .location-trigger.is-empty { color: #64748b; font-weight: 700; }
        .location-trigger.is-warning { border-color: #fdba74; background: #fff7ed; color: #9a3412; }
        .location-dialog { width: min(620px, calc(100% - 24px)); }
        .location-dialog-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .location-dialog-head p { margin-top: 4px; font-size: 13px; }
        .location-close {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
            color: #475569;
        }
        .location-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 16px;
            padding: 10px 12px;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            background: #eff6ff;
            color: #1e3a5f;
            font-size: 13px;
            font-weight: 800;
        }
        .location-list { display: grid; gap: 8px; max-height: min(420px, 52vh); margin-top: 10px; overflow: auto; }
        .location-option {
            display: grid;
            grid-template-columns: auto minmax(80px, .7fr) minmax(130px, 1fr) auto;
            align-items: center;
            gap: 10px;
            min-height: 50px;
            padding: 9px 11px;
            border: 1px solid #dbe3ef;
            border-radius: 8px;
            background: #fff;
            cursor: pointer;
        }
        .location-option:hover { border-color: #93c5fd; background: #f8fbff; }
        .location-option:has(input:checked) { border-color: #60a5fa; background: #eff6ff; }
        .location-option input { width: 18px; height: 18px; accent-color: var(--blue); }
        .location-option strong { color: #0f2f63; font-size: 15px; }
        .location-option span { color: #334155; font-size: 13px; font-weight: 800; }
        .location-option small { color: #64748b; font-size: 11px; text-align: right; }
        .location-empty { padding: 24px 12px; color: #64748b; text-align: center; }
        .picking-guide {
            margin: 4px 14px 12px;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            overflow: hidden;
            background: #f8fbff;
        }
        .picking-guide[hidden] { display: none; }
        .picking-guide__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 12px;
            border-bottom: 1px solid #dbeafe;
            background: #eff6ff;
        }
        .picking-guide__head strong { color: #123b6d; font-size: 14px; }
        .picking-guide__head span { color: #64748b; font-size: 11px; font-weight: 700; }
        .picking-guide__list { display: grid; gap: 8px; padding: 10px; }
        .pick-row { border: 1px solid #dbe3ef; border-radius: 7px; background: #fff; }
        .pick-row__title {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            padding: 8px 10px;
            color: #0f2f63;
            font-size: 12px;
            font-weight: 800;
        }
        .pick-step {
            display: grid;
            grid-template-columns: 22px 72px minmax(130px, 1fr) auto;
            align-items: center;
            gap: 8px;
            padding: 7px 10px;
            border-top: 1px solid #eef2f7;
            color: #334155;
            font-size: 12px;
        }
        .pick-step input { width: 17px; height: 17px; accent-color: #168a55; }
        .pick-step strong { color: #0f2f63; }
        .pick-step small { color: #64748b; }
        .pick-step__qty { color: #137a4b; font-weight: 900; white-space: nowrap; }
        .pick-step.is-done { background: #f0fdf4; color: #64748b; }
        .pick-step.is-done strong, .pick-step.is-done .pick-step__qty { text-decoration: line-through; opacity: .7; }
        .picking-guide__actions { display: flex; align-items: center; gap: 10px; }
        .picking-dialog {
            width: min(720px, calc(100vw - 24px));
            max-height: min(760px, calc(100vh - 24px));
            overflow: hidden;
        }
        .picking-dialog__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 18px 12px;
        }
        .picking-dialog__head strong { display: block; color: var(--blue-dark); font-size: 18px; }
        .picking-dialog__head span { color: #64748b; font-size: 12px; font-weight: 700; }
        .picking-progress { height: 6px; background: #e2e8f0; }
        .picking-progress span {
            display: block;
            width: 0;
            height: 100%;
            border-radius: 0 999px 999px 0;
            background: #2563eb;
            transition: width 180ms ease;
        }
        .picking-dialog__body { display: grid; gap: 14px; padding: 20px 18px; }
        .picking-row-label {
            overflow: hidden;
            color: #475569;
            font-size: 13px;
            font-weight: 800;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .picking-main {
            display: grid;
            grid-template-columns: minmax(150px, .6fr) minmax(0, 1.4fr);
            gap: 14px;
        }
        .picking-shelf {
            display: grid;
            min-height: 156px;
            place-items: center;
            border: 2px solid #60a5fa;
            border-radius: 8px;
            background: #eff6ff;
            color: #123b6d;
            font-size: 64px;
            font-weight: 900;
            line-height: 1;
        }
        .picking-instruction {
            display: flex;
            min-width: 0;
            flex-direction: column;
            justify-content: center;
            gap: 7px;
            padding: 16px;
            border: 1px solid #dbe3ef;
            border-radius: 8px;
            background: #fff;
        }
        .picking-instruction small { color: #64748b; font-size: 12px; font-weight: 700; }
        .picking-instruction strong { color: #0f2f63; font-size: 24px; line-height: 1.25; }
        .picking-instruction span { color: #137a4b; font-size: 20px; font-weight: 900; }
        .picking-return {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border: 1px solid #f6c453;
            border-radius: 8px;
            background: #fff8e6;
            color: #7c4a03;
        }
        .picking-return[hidden] { display: none; }
        .picking-return svg { width: 22px; height: 22px; flex: 0 0 auto; }
        .picking-return strong { display: block; font-size: 13px; }
        .picking-return span { display: block; margin-top: 2px; font-size: 15px; font-weight: 900; }
        .picking-dialog .confirm-actions { justify-content: space-between; }
        .paste-dialog { width: min(960px, calc(100vw - 24px)); }
        .paste-area {
            width: 100%;
            min-height: 260px;
            resize: vertical;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 12px;
            background: #f8fbff;
            color: #102a43;
            font: 13px/1.5 Consolas, monospace;
        }
        .paste-area:focus { border-color: #3b82f6; outline: 3px solid rgba(59, 130, 246, .14); }
        .paste-summary { min-height: 24px; margin-top: 10px; color: #475569; font-size: 13px; font-weight: 700; }
        .draft-search { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 8px; margin-top: 12px; }
        .draft-results { display: grid; gap: 7px; max-height: min(430px, 55vh); margin-top: 10px; overflow: auto; }
        .draft-result {
            display: grid;
            grid-template-columns: minmax(150px, .7fr) minmax(180px, 1fr) auto;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border: 1px solid #dbe3ef;
            border-radius: 8px;
            background: #fff;
            color: #334155;
            text-align: left;
        }
        .draft-result:hover { border-color: #60a5fa; background: #eff6ff; }
        .draft-result strong { color: #0f2f63; }
        .draft-result small { color: #64748b; }
        .draft-result__main { min-width: 0; }
        .draft-result__main strong, .draft-result__main small { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .draft-result__status { color: #b45309; font-size: 11px; font-weight: 900; text-align: right; }
        .draft-count {
            display: inline-grid;
            min-width: 20px;
            height: 20px;
            place-items: center;
            padding: 0 5px;
            border-radius: 999px;
            background: #dbeafe;
            color: #1d4ed8;
            font-size: 11px;
            font-weight: 900;
        }
        @media (max-width: 640px) {
            .location-option { grid-template-columns: auto 1fr auto; }
            .location-option small { grid-column: 2 / 4; text-align: left; }
            .picking-guide__head { align-items: flex-start; flex-direction: column; }
            .picking-guide__actions { width: 100%; justify-content: space-between; }
            .picking-main { grid-template-columns: 1fr; }
            .picking-shelf { min-height: 105px; font-size: 48px; }
            .picking-instruction strong { font-size: 20px; }
        }
        .spin { animation: spin .8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
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
            border-top-color: var(--blue);
            border-radius: 50%;
            animation: spin 760ms linear infinite;
        }
        .operation-loader__card strong { color: var(--blue-dark); font-size: 15px; }
        .operation-loader__card small { color: var(--muted); font-size: 12px; }
        @media (max-width: 900px) {
            .issue-meta { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 620px) {
            .issue-shell { width: calc(100% - 16px); padding-top: 8px; }
            .issue-header { align-items: stretch; flex-direction: column; }
            .issue-title { font-size: 21px; }
            .issue-actions .btn-quick { flex: 1; }
            .issue-meta { grid-template-columns: 1fr; }
            .panel-head { align-items: flex-start; flex-direction: column; }
        }
        @media (prefers-reduced-motion: reduce) {
            .operation-loader,
            .operation-loader__spinner,
            .spin { transition: none; animation: none; }
        }
    </style>
</head>
<body>
<main class="issue-shell">
    <header class="issue-header">
        <h1 class="issue-title">Xuất thành phẩm</h1>
        <div class="issue-actions">
            <a class="btn-quick" href="{{ url('/client/nhap-thanh-pham-nhanh') }}"><i data-lucide="arrow-left"></i>Chọn lại</a>
            <button id="findDraftBtn" class="btn-quick" type="button"><i data-lucide="search"></i>Phiếu chờ <span id="draftCountBadge" class="draft-count">0</span></button>
            <button id="startPickingBtn" class="btn-quick btn-primary-quick" type="button" hidden><i data-lucide="play"></i>Bắt đầu soạn</button>
            <button id="saveDraftBtn" class="btn-quick" type="button"><i data-lucide="save"></i>Lưu chờ soạn</button>
            <button id="saveIssueBtn" class="btn-quick btn-primary-quick" type="button"><i data-lucide="printer"></i>Lưu + in</button>
        </div>
    </header>

    <section class="issue-panel">
        <div class="panel-head">
            <h2 class="panel-title">Phiếu xuất</h2>
            <div class="panel-tools">
                <span class="pill">Dòng <strong id="lineCount">0</strong></span>
                <span class="pill">SL <strong id="totalQuantity">0</strong></span>
                <button id="pasteExcelBtn" class="btn-quick" type="button"><i data-lucide="clipboard-paste"></i>Dán Excel</button>
                <button id="addRowBtn" class="btn-quick" type="button"><i data-lucide="plus"></i>Thêm dòng</button>
                <button id="resetBtn" class="btn-quick" type="button"><i data-lucide="rotate-ccw"></i>Mới</button>
            </div>
        </div>

        <div class="issue-meta">
            <div>
                <label for="issueDate">Ngày xuất *</label>
                <input id="issueDate" class="form-control" type="text" inputmode="numeric" maxlength="10" autocomplete="off" placeholder="dd/mm/yyyy">
            </div>
            <div>
                <label for="customerName">Khách hàng *</label>
                <input id="customerName" class="form-control" list="quickCustomerOptions" autocomplete="off" placeholder="Gõ để chọn khách hàng">
                <datalist id="quickCustomerOptions"></datalist>
            </div>
            <div>
                <label for="receiverName">Người nhận</label>
                <input id="receiverName" class="form-control" autocomplete="off" placeholder="Tên người nhận">
            </div>
            <div>
                <label for="issueNote">Ghi chú phiếu</label>
                <input id="issueNote" class="form-control" autocomplete="off" placeholder="Giao hàng, xuất tại chỗ...">
            </div>
        </div>

        <div class="table-wrap">
            <table class="issue-table">
                <thead>
                    <tr>
                        <th class="col-stt">STT</th>
                        <th class="col-order">Lệnh / PS</th>
                        <th class="col-code">Mã nội bộ *</th>
                        <th class="col-name">Tên hàng</th>
                        <th class="col-variant">Size</th>
                        <th class="col-variant">Màu</th>
                        <th class="col-qty">Số lượng *</th>
                        <th class="col-unit">ĐVT</th>
                        <th class="col-location">Vị trí</th>
                        <th class="col-note">Ghi chú</th>
                        <th class="col-remove"></th>
                    </tr>
                </thead>
                <tbody id="issueRows"></tbody>
            </table>
        </div>
        <section id="pickingGuide" class="picking-guide" hidden>
            <div class="picking-guide__head">
                <strong>Hướng dẫn soạn hàng</strong>
                <div class="picking-guide__actions">
                    <span id="pickingGuideSummary"></span>
                </div>
            </div>
            <div id="pickingGuideList" class="picking-guide__list"></div>
        </section>
        <div id="formStatus" class="status"><i data-lucide="info"></i><span>Chọn mã trong danh mục rồi nhập số lượng.</span></div>
    </section>
</main>

<dialog id="confirmDialog" class="confirm-dialog">
    <div class="confirm-body">
        <h2>Xác nhận xuất thành phẩm</h2>
        <p id="confirmText"></p>
    </div>
    <div class="confirm-actions">
        <button id="cancelConfirmBtn" class="btn-quick" type="button">Kiểm tra lại</button>
        <button id="acceptConfirmBtn" class="btn-quick btn-primary-quick" type="button"><i data-lucide="check"></i>Xác nhận xuất</button>
    </div>
</dialog>

<dialog id="pickingDialog" class="confirm-dialog picking-dialog" tabindex="-1">
    <div class="picking-dialog__head">
        <div>
            <strong>Soạn hàng</strong>
            <span id="pickingProgressText">Bước 1/1</span>
        </div>
        <button id="closePickingDialogBtn" class="location-close" type="button" aria-label="Đóng"><i data-lucide="x"></i></button>
    </div>
    <div class="picking-progress" aria-hidden="true"><span id="pickingProgressBar"></span></div>
    <div class="picking-dialog__body">
        <div id="pickingRowLabel" class="picking-row-label"></div>
        <div class="picking-main">
            <div id="pickingShelfCode" class="picking-shelf"></div>
            <div class="picking-instruction">
                <small>LẤY HÀNG</small>
                <strong id="pickingInstruction"></strong>
                <span id="pickingTotal"></span>
                <small id="pickingPackageHint"></small>
            </div>
        </div>
        <div id="pickingReturnBox" class="picking-return" hidden>
            <i data-lucide="undo-2"></i>
            <div>
                <strong>Đặt phần còn lại về đúng vị trí</strong>
                <span id="pickingReturnText"></span>
            </div>
        </div>
    </div>
    <div class="confirm-actions">
        <button id="previousPickingBtn" class="btn-quick" type="button"><i data-lucide="arrow-left"></i>Trước</button>
        <button id="completePickingBtn" class="btn-quick btn-primary-quick" type="button"><i data-lucide="check"></i>Xong, bước tiếp</button>
    </div>
</dialog>

<dialog id="locationDialog" class="confirm-dialog location-dialog">
    <div class="confirm-body">
        <div class="location-dialog-head">
            <div>
                <h2>Chọn kệ xuất</h2>
                <p id="locationDialogSubtitle">Chọn một hoặc nhiều kệ đang có tồn.</p>
            </div>
            <button id="closeLocationDialogBtn" class="location-close" type="button" aria-label="Đóng"><i data-lucide="x"></i></button>
        </div>
        <div class="location-summary">
            <label><input id="selectAllLocations" type="checkbox"> Chọn tất cả</label>
            <span id="locationDialogTotal">0 kệ · 0</span>
        </div>
        <div id="locationOptions" class="location-list"></div>
    </div>
    <div class="confirm-actions">
        <button id="cancelLocationBtn" class="btn-quick" type="button">Hủy</button>
        <button id="applyLocationBtn" class="btn-quick btn-primary-quick" type="button"><i data-lucide="check"></i>Áp dụng</button>
    </div>
</dialog>

<dialog id="draftSearchDialog" class="confirm-dialog location-dialog">
    <div class="confirm-body">
        <div class="location-dialog-head">
            <div>
                <h2>Phiếu chờ soạn <span id="draftDialogCount">(0)</span></h2>
                <p>Tất cả phiếu chờ mới nhất được hiển thị sẵn. Chọn phiếu để tiếp tục soạn và sửa vị trí.</p>
            </div>
            <button id="closeDraftSearchBtn" class="location-close" type="button" aria-label="Đóng"><i data-lucide="x"></i></button>
        </div>
        <div class="draft-search">
            <input id="draftSearchKeyword" class="form-control" autocomplete="off" placeholder="Nhập số phiếu, PO, lệnh hoặc mã hàng">
            <button id="runDraftSearchBtn" class="btn-quick btn-primary-quick" type="button"><i data-lucide="search"></i>Tìm</button>
        </div>
        <div id="draftSearchResults" class="draft-results"></div>
    </div>
</dialog>

<dialog id="pasteExcelDialog" class="confirm-dialog paste-dialog">
    <div class="confirm-body">
        <div class="location-dialog-head">
            <div>
                <h2>Dán phiếu xuất từ Excel</h2>
                <p>Dán cả hàng tiêu đề. Hệ thống tự nhận PO, ITEM#, size, màu, số lượng, carton và vị trí.</p>
            </div>
            <button id="closePasteExcelBtn" class="location-close" type="button" aria-label="Đóng"><i data-lucide="x"></i></button>
        </div>
        <textarea id="pasteExcelData" class="paste-area" spellcheck="false" placeholder="Bấm vào đây rồi Ctrl+V dữ liệu Excel..."></textarea>
        <div id="pasteExcelSummary" class="paste-summary">Chưa có dữ liệu.</div>
    </div>
    <div class="confirm-actions">
        <button id="cancelPasteExcelBtn" class="btn-quick" type="button">Hủy</button>
        <button id="applyPasteExcelBtn" class="btn-quick btn-primary-quick" type="button"><i data-lucide="list-plus"></i>Đưa vào phiếu xuất</button>
    </div>
</dialog>

<div id="operationLoader" class="operation-loader" role="status" aria-live="polite" aria-hidden="true">
    <div class="operation-loader__card">
        <span class="operation-loader__spinner" aria-hidden="true"></span>
        <strong id="operationLoaderTitle">Đang xử lý phiếu xuất</strong>
        <small id="operationLoaderDetail">Vui lòng chờ, không đóng trang.</small>
    </div>
</div>

<div id="orderHistoryToast" class="order-history-toast" role="status" aria-live="polite">
    <strong id="orderHistoryToastTitle"></strong>
    <span id="orderHistoryToastText"></span>
</div>

<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
<script>
(() => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const rowsBody = document.getElementById('issueRows');
    const statusBox = document.getElementById('formStatus');
    const saveButton = document.getElementById('saveIssueBtn');
    const customerNameInput = document.getElementById('customerName');
    let rowSequence = 0;
    let orderHistoryToastTimer = null;
    let customerSuggestionTimer = null;
    let activeLocationRow = null;
    let locationDraft = new Set();
    let editingIssueId = null;
    const suggestionTimers = new WeakMap();
    const locationTimers = new WeakMap();
    const stockLocationRequests = new Map();
    const pastedCatalogSearchRequests = new Map();
    const pastedCatalogResolutions = new Map();
    const completedPickingSteps = new Set();
    let activePickingStepIndex = 0;

    function setOperationLoading(loading, title = 'Đang xử lý phiếu xuất', detail = 'Vui lòng chờ, không đóng trang.') {
        const loader = document.getElementById('operationLoader');
        document.getElementById('operationLoaderTitle').textContent = title;
        document.getElementById('operationLoaderDetail').textContent = detail;
        loader.classList.toggle('is-visible', loading);
        loader.setAttribute('aria-hidden', loading ? 'false' : 'true');
    }

    const escapeHtml = value => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const numberFormat = value => new Intl.NumberFormat('vi-VN', {
        maximumFractionDigits: 3
    }).format(Number(value || 0));

    function parseClipboardNumber(value) {
        let normalized = String(value ?? '').replace(/\s+/g, '').replace(/[^0-9,.-]/g, '');
        if (!normalized) return 0;
        if (normalized.includes(',') && normalized.includes('.')) {
            normalized = normalized.lastIndexOf(',') > normalized.lastIndexOf('.')
                ? normalized.replaceAll('.', '').replace(',', '.')
                : normalized.replaceAll(',', '');
        } else if (normalized.includes(',')) {
            normalized = normalized.replaceAll('.', '').replace(',', '.');
        } else if (/^-?\d{1,3}(\.\d{3})+$/.test(normalized)) {
            normalized = normalized.replaceAll('.', '');
        }
        const parsed = Number(normalized);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function parseTsv(text) {
        const rows = [];
        let row = [];
        let cell = '';
        let quoted = false;
        const source = String(text || '').replace(/^\uFEFF/, '');
        for (let index = 0; index < source.length; index += 1) {
            const character = source[index];
            if (character === '"') {
                if (quoted && source[index + 1] === '"') {
                    cell += '"';
                    index += 1;
                } else {
                    quoted = !quoted;
                }
            } else if (character === '\t' && !quoted) {
                row.push(cell);
                cell = '';
            } else if ((character === '\n' || character === '\r') && !quoted) {
                if (character === '\r' && source[index + 1] === '\n') index += 1;
                row.push(cell);
                if (row.some(value => String(value).trim() !== '')) rows.push(row);
                row = [];
                cell = '';
            } else {
                cell += character;
            }
        }
        row.push(cell);
        if (row.some(value => String(value).trim() !== '')) rows.push(row);
        return rows;
    }

    function cleanClipboardCell(value) {
        return String(value ?? '')
            .replace(/\\_/g, '_')
            .replace(/&#x(?:9|20);/gi, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function normalizePasteHeader(value) {
        return cleanClipboardCell(value)
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toUpperCase()
            .replace(/[^A-Z0-9#]+/g, ' ')
            .trim();
    }

    function pastedHeaderIndex(headers, aliases) {
        return headers.findIndex(header => aliases.some(alias => header === alias || header.startsWith(`${alias} `)));
    }

    function parseFinishedGoodsPaste(text) {
        const rows = parseTsv(text);
        const headerRowIndex = rows.findIndex(values => {
            const headers = values.map(normalizePasteHeader);
            return headers.some(header => header === 'ITEM#' || header.startsWith('ITEM# '))
                && headers.some(header => header === 'QUANTITY' || header.startsWith('QUANTITY '));
        });
        if (headerRowIndex < 0) throw new Error('Không tìm thấy hàng tiêu đề có ITEM# và QUANTITY.');

        const headers = rows[headerRowIndex].map(normalizePasteHeader);
        const columns = {
            po: pastedHeaderIndex(headers, ['PO NO', 'PO', 'PS#']),
            code: pastedHeaderIndex(headers, ['ITEM#', 'ITEM', 'MA HANG']),
            name: pastedHeaderIndex(headers, ['DESCRIPTION OF GOODS', 'DESCRIPTION', 'TEN HANG']),
            size: pastedHeaderIndex(headers, ['SIZE']),
            color: pastedHeaderIndex(headers, ['COLOR', 'MAU']),
            quantity: pastedHeaderIndex(headers, ['QUANTITY', 'SL']),
            unit: pastedHeaderIndex(headers, ['UNIT', 'DVT']),
            remark: pastedHeaderIndex(headers, ['REMARK', 'GHI CHU']),
            carton: pastedHeaderIndex(headers, ['CARTON NO', 'CARTON']),
            netWeight: pastedHeaderIndex(headers, ['N WEIGHT']),
            grossWeight: pastedHeaderIndex(headers, ['G WEIGHT']),
            location: pastedHeaderIndex(headers, ['VI TRI', 'POSITION', 'LOCATION']),
        };
        const valueAt = (values, index) => index >= 0 ? cleanClipboardCell(values[index]) : '';
        const parsed = rows.slice(headerRowIndex + 1).map((values, index) => {
            const code = valueAt(values, columns.code);
            const quantity = parseClipboardNumber(valueAt(values, columns.quantity));
            const carton = valueAt(values, columns.carton);
            const netWeight = valueAt(values, columns.netWeight);
            const grossWeight = valueAt(values, columns.grossWeight);
            return {
                sourceRow: headerRowIndex + index + 2,
                po: valueAt(values, columns.po),
                code,
                name: valueAt(values, columns.name),
                size: valueAt(values, columns.size),
                color: valueAt(values, columns.color),
                quantity,
                carton,
                unit: valueAt(values, columns.unit) || 'Cái',
                note: [
                    valueAt(values, columns.remark),
                    carton ? `Carton ${carton}` : '',
                    netWeight ? `NW ${netWeight} kg` : '',
                    grossWeight ? `GW ${grossWeight} kg` : '',
                ].filter(Boolean).join(' · '),
                location: valueAt(values, columns.location).toUpperCase(),
            };
        }).filter(item => item.code && item.code.toUpperCase() !== 'TOTAL' && item.quantity > 0);

        if (!parsed.length) throw new Error('Không có dòng ITEM# và QUANTITY hợp lệ để đưa vào phiếu.');
        return parsed;
    }

    function showOrderHistoryToast(item = {}) {
        const toast = document.getElementById('orderHistoryToast');
        const planned = Number(item.planned_quantity || item.order_quantity || 0);
        const received = Number(item.received_quantity || 0);
        const customerIssued = Number(item.customer_issue_quantity || 0);
        const receiptCodes = item.receipt_codes || [];
        const customerIssueCodes = item.customer_issue_codes || [];
        const productionIssueCodes = item.production_issue_codes || [];
        const details = [
            item.has_planned_quantity === false ? 'Đơn hàng: chưa có số lượng' : `Đơn hàng: ${numberFormat(planned)}`,
            `Đã nhập: ${numberFormat(received)}${receiptCodes.length ? ` · ${receiptCodes.join(', ')}` : ' · chưa có phiếu nhập'}`,
            `Đã xuất khách: ${numberFormat(customerIssued)}${customerIssueCodes.length ? ` · ${customerIssueCodes.join(', ')}` : ' · chưa có phiếu xuất'}`,
        ];
        if (productionIssueCodes.length) details.push(`Xuất sản xuất: ${productionIssueCodes.join(', ')}`);
        document.getElementById('orderHistoryToastTitle').textContent = `Lệnh ${item.production_order || ''}`;
        document.getElementById('orderHistoryToastText').textContent = details.join('\n');
        toast.classList.add('is-visible');
        clearTimeout(orderHistoryToastTimer);
        orderHistoryToastTimer = setTimeout(() => toast.classList.remove('is-visible'), 7500);
    }

    const localIsoDate = () => new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Ho_Chi_Minh',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    }).format(new Date());

    const isoToDisplayDate = iso => {
        const [year, month, day] = String(iso || '').split('-');
        return year && month && day ? `${day}/${month}/${year}` : '';
    };

    const displayToIsoDate = value => {
        const match = String(value || '').trim().match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
        if (!match) return '';
        const day = Number(match[1]);
        const month = Number(match[2]);
        const year = Number(match[3]);
        const date = new Date(Date.UTC(year, month - 1, day));
        if (date.getUTCFullYear() !== year || date.getUTCMonth() !== month - 1 || date.getUTCDate() !== day) return '';
        return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    };

    function setStatus(message, type = '') {
        statusBox.className = `status${type ? ` is-${type}` : ''}`;
        statusBox.innerHTML = `<i data-lucide="${type === 'error' ? 'circle-alert' : type === 'success' ? 'circle-check' : 'info'}"></i><span>${escapeHtml(message)}</span>`;
        window.lucide?.createIcons();
    }

    async function jsonOrError(response) {
        const payload = await response.json().catch(() => ({}));
        if (response.ok) return payload;
        const validation = payload.errors
            ? Object.values(payload.errors).flat().join(' ')
            : '';
        const error = new Error(validation || payload.message || 'Không tạo được phiếu xuất thành phẩm.');
        error.payload = payload;
        error.status = response.status;
        throw error;
    }

    function loadCustomerSuggestions(keyword = '') {
        clearTimeout(customerSuggestionTimer);
        customerSuggestionTimer = setTimeout(async () => {
            try {
                const result = await fetch(`/api/khach-hang-noi-bo/goi-y?keyword=${encodeURIComponent(keyword.trim())}&limit=50`).then(jsonOrError);
                document.getElementById('quickCustomerOptions').innerHTML = (result.data || []).map(customer => {
                    const label = [customer.customer_code, customer.customer_group].filter(Boolean).join(' · ');
                    return `<option value="${escapeHtml(customer.name)}">${escapeHtml(label)}</option>`;
                }).join('');
            } catch (error) {
                document.getElementById('quickCustomerOptions').innerHTML = '';
            }
        }, keyword ? 120 : 0);
    }

    async function requireCatalogCustomer(name) {
        const result = await fetch(`/api/khach-hang-noi-bo/kiem-tra?name=${encodeURIComponent(name.trim())}`).then(jsonOrError);
        if (!result.valid || !result.data) {
            throw new Error('Khách hàng chưa có trong Danh mục khách hàng. Hãy chọn đúng gợi ý hoặc thêm khách hàng trước.');
        }
        return result.data;
    }

    function rowTemplate(index) {
        return `
            <tr data-row-id="${index}">
                <td class="col-stt">${rowsBody.children.length + 1}</td>
                <td>
                    <div class="suggest-wrap">
                        <input class="form-control production-order" autocomplete="off" placeholder="Gõ lệnh / PS">
                        <div class="suggestions order-suggestions d-none"></div>
                    </div>
                </td>
                <td>
                    <div class="suggest-wrap">
                        <input class="form-control internal-code" autocomplete="off" placeholder="Tìm mã">
                        <div class="suggestions catalog-suggestions d-none"></div>
                    </div>
                    <div class="row-state"></div>
                </td>
                <td><input class="form-control item-name" autocomplete="off" placeholder="Tự điền"></td>
                <td><input class="form-control size" autocomplete="off"></td>
                <td><input class="form-control color" autocomplete="off"></td>
                <td><input class="form-control quantity" type="number" min="0" step="0.001" inputmode="decimal" value="0"></td>
                <td><input class="form-control unit" autocomplete="off" value="Cái"></td>
                <td>
                    <button class="location-trigger is-empty" type="button" title="Chọn kệ xuất">
                        <i data-lucide="map-pin"></i><span>Chọn kệ</span>
                    </button>
                    <input class="location" type="hidden">
                </td>
                <td><input class="form-control line-note" autocomplete="off"></td>
                <td><button class="remove-row" type="button" title="Xóa dòng" aria-label="Xóa dòng"><i data-lucide="x"></i></button></td>
            </tr>`;
    }

    function addRow(focus = false) {
        rowSequence += 1;
        rowsBody.insertAdjacentHTML('beforeend', rowTemplate(rowSequence));
        const row = rowsBody.lastElementChild;
        bindRow(row);
        renumberRows();
        if (focus) row.querySelector('.internal-code').focus();
        window.lucide?.createIcons();
    }

    function pastedCodeCandidates(value, color = '') {
        const code = cleanClipboardCell(value).toUpperCase();
        const normalizedColor = cleanClipboardCell(color).toUpperCase();
        const colorSuffix = /(^|\s)(001A\s+)?WHITE(\s|$)/.test(normalizedColor) ? 'W'
            : /(^|\s)(095A\s+)?BLACK(\s|$)/.test(normalizedColor) ? 'B'
                : '';
        return [...new Set([
            code,
            code.replaceAll('_', '-'),
            colorSuffix && !/[-_]\w+$/.test(code) ? `${code}-${colorSuffix}` : '',
        ].filter(Boolean))];
    }

    function searchPastedCatalog(keyword) {
        const searchKey = cleanClipboardCell(keyword).toUpperCase();
        if (!pastedCatalogSearchRequests.has(searchKey)) {
            pastedCatalogSearchRequests.set(searchKey,
                fetch(`/api/ma-noi-bo-danh-muc?keyword=${encodeURIComponent(searchKey)}&limit=30&with_color=0`)
                    .then(jsonOrError)
                    .then(result => result.data || [])
                    .catch(() => [])
            );
        }
        return pastedCatalogSearchRequests.get(searchKey);
    }

    async function resolvePastedCatalogItem(rawCode, color = '') {
        const cacheKey = `${cleanClipboardCell(rawCode).toUpperCase()}|${cleanClipboardCell(color).toUpperCase()}`;
        if (!pastedCatalogResolutions.has(cacheKey)) {
            const candidates = pastedCodeCandidates(rawCode, color);
            const request = searchPastedCatalog(candidates[0])
                .then(async primaryItems => {
                    const findMatch = items => {
                        for (const candidate of candidates) {
                            const match = items.find(item =>
                            String(item.code || item.value || '').trim().toUpperCase() === candidate
                            );
                            if (match) return match;
                        }
                        return null;
                    };
                    const primaryMatch = findMatch(primaryItems);
                    if (primaryMatch || candidates.length === 1) return primaryMatch;

                    const fallbackItems = (await Promise.all(
                        candidates.slice(1).map(searchPastedCatalog)
                    )).flat();
                    return findMatch(fallbackItems);
                })
                .catch(() => null);
            pastedCatalogResolutions.set(cacheKey, request);
        }
        return pastedCatalogResolutions.get(cacheKey);
    }

    function pastedLocationCodes(value) {
        return String(value || '')
            .split(/[,;|]+/)
            .map(code => code.trim().toUpperCase())
            .filter(Boolean);
    }

    function assignPastedLocationsByFifo(rows) {
        const balancesByVariant = new Map();
        const packageBalancesByVariant = new Map();
        const quantitiesByVariant = new Map();
        rows.forEach(row => {
            const key = String(row.querySelector('.internal-code').value || '').trim().toUpperCase();
            const quantity = Number(row.querySelector('.quantity').value || 0);
            if (!key || quantity <= 0) return;
            if (!quantitiesByVariant.has(key)) quantitiesByVariant.set(key, new Map());
            const frequencies = quantitiesByVariant.get(key);
            frequencies.set(quantity, Number(frequencies.get(quantity) || 0) + 1);
        });
        const cartonNorms = new Map([...quantitiesByVariant.entries()].map(([key, frequencies]) => {
            const ranked = [...frequencies.entries()].sort((left, right) => right[1] - left[1] || right[0] - left[0]);
            return [key, Number(ranked[0]?.[0] || 0)];
        }));

        rows.forEach(row => {
            const variantKey = String(row.querySelector('.internal-code').value || '').trim().toUpperCase();
            if (!balancesByVariant.has(variantKey)) balancesByVariant.set(variantKey, new Map());
            if (!packageBalancesByVariant.has(variantKey)) packageBalancesByVariant.set(variantKey, new Map());
            const balances = balancesByVariant.get(variantKey);
            const packageBalances = packageBalancesByVariant.get(variantKey);
            const stockLocations = row._stockLocations || [];
            row._fifoLocationAvailability = new Map();
            row._fifoAllocations = new Map();
            row._fifoPackageAllocations = [];
            stockLocations.forEach(location => {
                if (!balances.has(location.location_code)) {
                    balances.set(location.location_code, Number(location.available_quantity || 0));
                }
                (location.packages || []).forEach(packageItem => {
                    const packageKey = String(packageItem.id || packageItem.package_code || '');
                    if (!packageBalances.has(packageKey)) {
                        packageBalances.set(packageKey, Number(packageItem.quantity || 0));
                    }
                });
                row._fifoLocationAvailability.set(
                    location.location_code,
                    Math.max(0, Number(balances.get(location.location_code) || 0))
                );
            });

            const explicit = pastedLocationCodes(row.dataset.explicitPastedLocation || '');
            const preferred = [...(row._preferredLocations || new Set())];
            const isManualSelection = row.dataset.manualLocationSelection === '1';
            const requestedQuantity = Number(row.querySelector('.quantity').value || 0);
            const cartonNorm = Number(cartonNorms.get(variantKey) || requestedQuantity || 0);
            const isPartialCarton = cartonNorm > 0 && requestedQuantity + 0.0001 < cartonNorm;
            row._cartonNorm = cartonNorm;
            row._isPartialCarton = isPartialCarton;
            const isLooseLocation = location => {
                const available = Math.max(0, Number(balances.get(location.location_code) || 0));
                if (cartonNorm <= 0 || available <= 0) return false;
                const remainder = available % cartonNorm;
                return available + 0.0001 < cartonNorm
                    || (remainder > 0.0001 && Math.abs(remainder - cartonNorm) > 0.0001);
            };
            const prioritizedLocations = isPartialCarton
                ? [
                    ...stockLocations.filter(location => !location.is_outsourced && isLooseLocation(location)),
                    ...stockLocations.filter(location => !location.is_outsourced && !isLooseLocation(location)),
                    ...stockLocations.filter(location => location.is_outsourced),
                ]
                : [
                    ...stockLocations.filter(location => location.is_outsourced),
                    ...stockLocations.filter(location => !location.is_outsourced && !isLooseLocation(location)),
                    ...stockLocations.filter(location => !location.is_outsourced && isLooseLocation(location)),
                ];
            const orderedCodes = isManualSelection
                ? preferred
                : [...new Set([
                    ...explicit,
                    ...preferred,
                    ...prioritizedLocations.map(location => location.location_code),
                ])];
            let remaining = requestedQuantity;
            const selected = [];
            orderedCodes.forEach(code => {
                if (remaining <= 0.0001) return;
                const available = Math.max(0, Number(balances.get(code) || 0));
                if (available <= 0) return;
                selected.push(code);
                const taken = Math.min(available, remaining);
                const after = available - taken;
                balances.set(code, after);
                row._fifoAllocations.set(code, {before: available, taken, after});
                let packageQuantityToTake = taken;
                const location = stockLocations.find(item => item.location_code === code);
                (location?.packages || []).forEach(packageItem => {
                    if (packageQuantityToTake <= 0.0001) return;
                    const packageKey = String(packageItem.id || packageItem.package_code || '');
                    const packageBefore = Math.max(0, Number(packageBalances.get(packageKey) || 0));
                    if (packageBefore <= 0) return;
                    const packageTaken = Math.min(packageBefore, packageQuantityToTake);
                    const packageAfter = packageBefore - packageTaken;
                    packageBalances.set(packageKey, packageAfter);
                    row._fifoPackageAllocations.push({
                        id: packageKey,
                        packageCode: packageItem.package_code || `Kiện ${packageKey}`,
                        locationCode: code,
                        before: packageBefore,
                        taken: packageTaken,
                        after: packageAfter,
                    });
                    packageQuantityToTake -= packageTaken;
                });
                remaining -= taken;
            });
            if (!selected.length && explicit.length) selected.push(...explicit);
            row._selectedLocations = new Set(selected);
            row._fifoUnallocated = Math.max(0, remaining);
            delete row.dataset.explicitPastedLocation;
            updateLocationTrigger(row);
        });
        renderPickingGuide();
    }

    async function applyPastedLines(lines) {
        rowsBody.innerHTML = '';
        setOperationLoading(true, 'Đang đưa dữ liệu vào phiếu', 'Đang đối chiếu mã và tìm kệ có tồn theo FIFO.');
        const tasks = lines.map(async item => {
            addRow(false);
            const row = rowsBody.lastElementChild;
            const catalogItem = await resolvePastedCatalogItem(item.code, item.color);
            const code = catalogItem?.code || catalogItem?.value || pastedCodeCandidates(item.code, item.color)[0];
            row.querySelector('.production-order').value = item.po;
            row.dataset.purchaseOrder = item.po;
            row.dataset.pastedCarton = item.carton || '';
            row.querySelector('.internal-code').value = code;
            row.querySelector('.item-name').value = item.name || catalogItem?.name || code;
            row.querySelector('.size').value = item.size || catalogItem?.size || '';
            row.querySelector('.color').value = item.color || catalogItem?.color || '';
            row.querySelector('.quantity').value = item.quantity;
            row.querySelector('.unit').value = item.unit || catalogItem?.unit || 'Cái';
            row.querySelector('.line-note').value = item.note;
            row.dataset.pastedLocation = item.location;
            row.dataset.explicitPastedLocation = item.location;
            row.dataset.matchByCodeOnly = '1';
            row.dataset.pastedFifo = '1';
            row.querySelector('.row-state').className = catalogItem ? 'row-state' : 'row-state is-new';
            row.querySelector('.row-state').textContent = catalogItem
                ? 'Đã dán · đúng danh mục'
                : 'Mã chưa có trong danh mục';
            await loadStockLocations(row);
            return row;
        });

        try {
            await Promise.all(tasks);
            assignPastedLocationsByFifo([...rowsBody.children]);
            const withoutStock = [...rowsBody.children].filter(row => !(row._stockLocations || []).length).length;
            updateSummary();
            setStatus(
                `Đã đưa ${lines.length} dòng vào phiếu. Thùng nguyên ưu tiên hàng gia công; thùng lẻ ưu tiên vị trí lẻ, sau đó mới áp dụng FIFO.`
                + (withoutStock ? ` Có ${withoutStock} dòng chưa tìm thấy tồn đúng mã, size và màu.` : ''),
                withoutStock ? 'error' : 'success'
            );
            rowsBody.firstElementChild?.scrollIntoView({block: 'nearest'});
        } finally {
            setOperationLoading(false);
        }
    }

    function bindRow(row) {
        const orderInput = row.querySelector('.production-order');
        const codeInput = row.querySelector('.internal-code');
        orderInput.addEventListener('input', () => {
            if (row.dataset.appliedOrder
                && row.dataset.appliedOrder !== orderInput.value.trim().toUpperCase()) {
                delete row.dataset.appliedOrder;
                delete row.dataset.productionOrderId;
                delete row.dataset.purchaseOrder;
                delete row.dataset.orderCustomer;
                syncIssueRowPurchaseOrderNote(row, '');
            }
            delete row.dataset.catalogLookup;
            row.querySelector('.catalog-suggestions').classList.add('d-none');
            debounceSuggestions(orderInput, () => loadOrderSuggestions(row));
        });
        orderInput.addEventListener('focus', () => {
            delete row.dataset.catalogLookup;
            row.querySelector('.catalog-suggestions').classList.add('d-none');
            if (orderInput.value.trim().length >= 2) loadOrderSuggestions(row);
        });
        orderInput.addEventListener('keydown', event => chooseFirstSuggestion(event, row.querySelector('.order-suggestions')));
        codeInput.addEventListener('input', () => {
            delete row.dataset.matchByCodeOnly;
            resetRowLocations(row);
            delete row.dataset.orderLookup;
            row.querySelector('.order-suggestions').classList.add('d-none');
            const selectedOrderCode = String(row.dataset.orderItemCode || '').trim().toUpperCase();
            if (selectedOrderCode && codeInput.value.trim().toUpperCase() !== selectedOrderCode) {
                orderInput.value = '';
                delete row.dataset.orderItemCode;
                delete row.dataset.productionOrderId;
                delete row.dataset.purchaseOrder;
                delete row.dataset.orderCustomer;
            }
            row.querySelector('.row-state').textContent = '';
            debounceSuggestions(codeInput, () => loadSuggestions(row));
            updateSummary();
        });
        codeInput.addEventListener('focus', () => {
            delete row.dataset.orderLookup;
            row.querySelector('.order-suggestions').classList.add('d-none');
            if (codeInput.value.trim().length >= 1) loadSuggestions(row);
        });
        codeInput.addEventListener('change', () => applyExactCatalog(row));
        codeInput.addEventListener('keydown', event => chooseFirstSuggestion(event, row.querySelector('.catalog-suggestions')));
        row.querySelectorAll('.size, .color').forEach(input => {
            input.addEventListener('change', () => scheduleStockLocationLoad(row));
        });
        row.querySelector('.location-trigger').addEventListener('click', () => openLocationDialog(row));
        row.querySelector('.quantity').addEventListener('input', () => {
            updateSummary();
            if (row.dataset.pastedFifo === '1') {
                assignPastedLocationsByFifo([...rowsBody.children].filter(item => item.dataset.pastedFifo === '1'));
            }
        });
        row.querySelector('.quantity').addEventListener('keydown', event => {
            if (event.key !== 'Enter') return;
            event.preventDefault();
            const next = row.nextElementSibling || (addRow(), rowsBody.lastElementChild);
            next.querySelector('.internal-code').focus();
        });
        row.querySelector('.remove-row').addEventListener('click', () => {
            if (rowsBody.children.length === 1) {
                clearRow(row);
                return;
            }
            row.remove();
            renumberRows();
            updateSummary();
            assignPastedLocationsByFifo([...rowsBody.children].filter(item => item.dataset.pastedFifo === '1'));
        });
    }

    function debounceSuggestions(input, callback) {
        clearTimeout(suggestionTimers.get(input));
        suggestionTimers.set(input, setTimeout(callback, 140));
    }

    function chooseFirstSuggestion(event, panel) {
        if (event.key !== 'Enter' || panel.classList.contains('d-none')) return;
        const first = panel.querySelector('.suggestion');
        if (!first) return;
        event.preventDefault();
        first.click();
    }

    function clearRow(row) {
        delete row.dataset.orderItemCode;
        delete row.dataset.productionOrderId;
        delete row.dataset.purchaseOrder;
        delete row.dataset.orderCustomer;
        delete row.dataset.orderLookup;
        delete row.dataset.matchByCodeOnly;
        delete row.dataset.pastedFifo;
        delete row.dataset.explicitPastedLocation;
        delete row.dataset.manualLocationSelection;
        row._preferredLocations = new Set();
        row.querySelectorAll('input').forEach(input => {
            input.value = input.classList.contains('quantity') ? '0' : (input.classList.contains('unit') ? 'Cái' : '');
        });
        row.querySelector('.row-state').className = 'row-state';
        row.querySelector('.row-state').textContent = '';
        row.querySelectorAll('.suggestions').forEach(panel => panel.classList.add('d-none'));
        resetRowLocations(row);
        updateSummary();
    }

    function selectedLocationCodes(row) {
        return [...new Set([...(row._selectedLocations || new Set())]
            .map(value => String(value || '').trim().toUpperCase())
            .filter(Boolean))];
    }

    function resetRowLocations(row) {
        row._stockLocations = [];
        row._selectedLocations = new Set();
        row._fifoLocationAvailability = new Map();
        row._fifoAllocations = new Map();
        row._fifoPackageAllocations = [];
        row._fifoUnallocated = 0;
        row._preferredLocations = new Set();
        delete row.dataset.manualLocationSelection;
        delete row.dataset.locationLookup;
        const input = row.querySelector('.location');
        if (input) input.value = '';
        updateLocationTrigger(row);
    }

    function updateLocationTrigger(row) {
        const trigger = row.querySelector('.location-trigger');
        if (!trigger) return;
        const selected = selectedLocationCodes(row);
        const locations = row._stockLocations || [];
        const selectedRows = locations.filter(item => selected.includes(item.location_code));
        const allocations = row._fifoAllocations || new Map();
        const allocated = selected.reduce((sum, code) => sum + Number(allocations.get(code)?.taken || 0), 0);
        const remainingAfter = selected.reduce((sum, code) => sum + Number(allocations.get(code)?.after || 0), 0);
        const total = selectedRows.reduce((sum, item) => sum + Number(item.available_quantity || 0), 0);
        const loading = row.dataset.locationLoading === '1';
        const singleAllocation = selected.length === 1 ? allocations.get(selected[0]) : null;
        let label = loading
            ? 'Đang tải kệ...'
            : selected.length === 1
                ? singleAllocation
                    ? `${selected[0]} · lấy ${numberFormat(singleAllocation.taken)} · còn ${numberFormat(singleAllocation.after)}`
                    : `${selected[0]} · ${numberFormat(total)}`
                : selected.length > 1
                    ? allocations.size
                        ? `${selected.length} kệ · lấy ${numberFormat(allocated)} · còn ${numberFormat(remainingAfter)}`
                        : `${selected.length} kệ · ${numberFormat(total)}`
                    : locations.length
                        ? 'Chưa chọn kệ'
                        : 'Không có tồn';
        if (!loading && Number(row._fifoUnallocated || 0) > 0) {
            label += ` · thiếu ${numberFormat(row._fifoUnallocated)}`;
        }
        trigger.querySelector('span').textContent = label;
        trigger.title = label;
        trigger.classList.toggle('is-empty', !selected.length && !locations.length);
        trigger.classList.toggle('is-warning', !loading && !selected.length);
        row.querySelector('.location').value = selected.join(', ');
    }

    function scheduleStockLocationLoad(row) {
        clearTimeout(locationTimers.get(row));
        locationTimers.set(row, setTimeout(() => loadStockLocations(row), 120));
    }

    async function loadStockLocations(row) {
        const code = row.querySelector('.internal-code').value.trim();
        if (!code) {
            resetRowLocations(row);
            return;
        }
        const params = new URLSearchParams({
            internal_item_code: code,
            size: row.querySelector('.size').value.trim(),
            color: row.querySelector('.color').value.trim(),
            include_packages: '1',
        });
        if (row.dataset.matchByCodeOnly === '1') params.set('match_by_code_only', '1');
        const requestKey = params.toString().toUpperCase();
        if (row.dataset.locationLookup && row.dataset.locationLookup !== requestKey) {
            row._preferredLocations = new Set();
            delete row.dataset.manualLocationSelection;
        }
        row.dataset.locationLookup = requestKey;
        row.dataset.locationLoading = '1';
        updateLocationTrigger(row);
        try {
            if (!stockLocationRequests.has(requestKey)) {
                const request = fetch(`/api/xuat-vat-tu-noi-bo/vi-tri-ton?${params.toString()}`)
                    .then(jsonOrError)
                    .catch(error => {
                        stockLocationRequests.delete(requestKey);
                        throw error;
                    });
                stockLocationRequests.set(requestKey, request);
            }
            const result = await stockLocationRequests.get(requestKey);
            if (row.dataset.locationLookup !== requestKey) return;
            row._stockLocations = result.data || [];
            const requestedLocations = pastedLocationCodes(row.dataset.pastedLocation || '');
            row._selectedLocations = new Set(requestedLocations.length
                ? requestedLocations
                : row._stockLocations.map(item => item.location_code));
            delete row.dataset.pastedLocation;
        } catch (error) {
            if (row.dataset.locationLookup !== requestKey) return;
            row._stockLocations = [];
            row._selectedLocations = new Set();
            setStatus(error.message, 'error');
        } finally {
            if (row.dataset.locationLookup === requestKey) {
                delete row.dataset.locationLoading;
                updateLocationTrigger(row);
                if (row.dataset.pastedFifo === '1') {
                    assignPastedLocationsByFifo([...rowsBody.children].filter(item => item.dataset.pastedFifo === '1'));
                }
            }
        }
    }

    function fifoLocationState(row, item) {
        const code = item.location_code;
        const before = Number(row?._fifoLocationAvailability?.get(code) ?? item.available_quantity ?? 0);
        const allocation = row?._fifoAllocations?.get(code) || null;
        return {
            before,
            taken: Number(allocation?.taken || 0),
            after: Number(allocation?.after ?? before),
        };
    }

    function renderLocationDialog() {
        const options = activeLocationRow?._stockLocations || [];
        const list = document.getElementById('locationOptions');
        list.innerHTML = options.length ? options.map((item, index) => {
            const fifo = fifoLocationState(activeLocationRow, item);
            return `
            <label class="location-option">
                <input type="checkbox" data-index="${index}" ${locationDraft.has(item.location_code) ? 'checked' : ''}>
                <strong>${escapeHtml(item.location_code)}</strong>
                <span>Còn trước dòng: ${numberFormat(fifo.before)}</span>
                <small>${fifo.taken > 0 ? `Lấy ${numberFormat(fifo.taken)} · còn sau ${numberFormat(fifo.after)} · ` : ''}${numberFormat(item.package_count)} kiện${item.fifo_date ? ` · FIFO ${escapeHtml(isoToDisplayDate(String(item.fifo_date).slice(0, 10)))}` : ''}</small>
            </label>
        `;}).join('') : '<div class="location-empty">Không có tồn phù hợp với đúng mã, size và màu của dòng này.</div>';
        list.querySelectorAll('input[type="checkbox"]').forEach(input => {
            input.addEventListener('change', () => {
                const item = options[Number(input.dataset.index)];
                if (input.checked) locationDraft.add(item.location_code);
                else locationDraft.delete(item.location_code);
                updateLocationDialogSummary();
            });
        });
        updateLocationDialogSummary();
    }

    function updateLocationDialogSummary() {
        const options = activeLocationRow?._stockLocations || [];
        const selectedRows = options.filter(item => locationDraft.has(item.location_code));
        const total = selectedRows.reduce((sum, item) => sum + fifoLocationState(activeLocationRow, item).before, 0);
        document.getElementById('locationDialogTotal').textContent = `${selectedRows.length} kệ · còn trước dòng ${numberFormat(total)}`;
        const selectAll = document.getElementById('selectAllLocations');
        selectAll.checked = options.length > 0 && selectedRows.length === options.length;
        selectAll.indeterminate = selectedRows.length > 0 && selectedRows.length < options.length;
    }

    async function openLocationDialog(row) {
        if (row.dataset.locationLoading === '1') return;
        if (!(row._stockLocations || []).length && row.querySelector('.internal-code').value.trim()) {
            await loadStockLocations(row);
        }
        activeLocationRow = row;
        locationDraft = new Set(selectedLocationCodes(row));
        document.getElementById('locationDialogSubtitle').textContent = [
            row.querySelector('.internal-code').value.trim(),
            row.querySelector('.size').value.trim(),
            row.querySelector('.color').value.trim(),
        ].filter(Boolean).join(' · ') || 'Chưa chọn mã hàng';
        renderLocationDialog();
        document.getElementById('locationDialog').showModal();
        window.lucide?.createIcons();
    }

    function renumberRows() {
        [...rowsBody.children].forEach((row, index) => {
            row.querySelector('.col-stt').textContent = index + 1;
        });
    }

    async function loadSuggestions(row) {
        const keyword = row.querySelector('.internal-code').value.trim();
        const panel = row.querySelector('.catalog-suggestions');
        if (!keyword) {
            panel.classList.add('d-none');
            row.querySelector('.order-suggestions').classList.add('d-none');
            return;
        }
        const requestKey = keyword.toUpperCase();
        row.dataset.catalogLookup = requestKey;
        row.querySelector('.order-suggestions').classList.add('d-none');
        try {
            const result = await fetch(`/api/ma-noi-bo-danh-muc?keyword=${encodeURIComponent(keyword)}&limit=20&with_color=0`)
                .then(jsonOrError);
            if (row.dataset.catalogLookup !== requestKey) return;
            const items = result.data || [];
            row._catalogSuggestions = items;
            panel.innerHTML = items.map((item, index) => `
                <button class="suggestion" type="button" data-index="${index}">
                    <strong>${escapeHtml(item.code || item.value)}</strong>
                    <small>${escapeHtml(item.name || '')}</small>
                    <span>${escapeHtml([item.size, item.color, item.shelf].filter(Boolean).join(' · '))}</span>
                </button>
            `).join('');
            panel.classList.toggle('d-none', items.length === 0);
            panel.querySelectorAll('.suggestion').forEach(button => {
                button.addEventListener('click', () => applySuggestion(row, items[Number(button.dataset.index)]));
            });
        } catch (error) {
            if (row.dataset.catalogLookup !== requestKey) return;
            panel.classList.add('d-none');
            setStatus(error.message, 'error');
        }
    }

    async function applyExactCatalog(row) {
        const input = row.querySelector('.internal-code');
        const code = input.value.trim().toUpperCase();
        if (!code) return;
        let items = row._catalogSuggestions || [];
        let item = items.find(candidate => String(candidate.code || candidate.value || '').trim().toUpperCase() === code);
        if (!item) {
            try {
                const result = await fetch(`/api/ma-noi-bo-danh-muc?keyword=${encodeURIComponent(code)}&limit=10&with_color=0`)
                    .then(jsonOrError);
                items = result.data || [];
                row._catalogSuggestions = items;
                item = items.find(candidate => String(candidate.code || candidate.value || '').trim().toUpperCase() === code);
            } catch (error) {
                setStatus(error.message, 'error');
                return;
            }
        }
        if (item) {
            applySuggestion(row, item);
            return;
        }
        row.querySelector('.row-state').className = 'row-state is-new';
        row.querySelector('.row-state').textContent = 'Mã mới - sẽ append khi lưu';
        setStatus(`Mã ${code} sẽ được append vào DANH MỤC trước khi lưu phiếu.`);
    }

    function orderQuery(keyword, limit = 60) {
        const params = new URLSearchParams({
            keyword,
            with_progress: '1',
            limit: String(limit),
        });
        return `/api/lenh-san-xuat-sheet?${params.toString()}`;
    }

    function uniqueProductionOrders(rows, keyword = '', exactItemCode = false) {
        const normalizedKeyword = String(keyword || '').trim().toUpperCase();
        const seen = new Set();
        return (rows || []).filter(item => {
            const itemCode = String(item.item_code || '').trim().toUpperCase();
            const sourceCode = String(item.source_item_code || '').trim().toUpperCase();
            if (exactItemCode && normalizedKeyword && itemCode !== normalizedKeyword && sourceCode !== normalizedKeyword) {
                return false;
            }
            const key = [
                item.production_order,
                item.item_code,
                item.size,
                item.color,
            ].map(value => String(value || '').trim().toUpperCase()).join('|');
            if (!item.production_order || seen.has(key)) return false;
            seen.add(key);
            return true;
        }).sort((left, right) => {
            const leftReceived = Number(left.received_quantity || 0) > 0 ? 0 : 1;
            const rightReceived = Number(right.received_quantity || 0) > 0 ? 0 : 1;
            if (leftReceived !== rightReceived) return leftReceived - rightReceived;
            return String(right.received_date || '').localeCompare(String(left.received_date || ''));
        });
    }

    function renderOrderSuggestions(row, items) {
        const panel = row.querySelector('.order-suggestions');
        row.querySelector('.catalog-suggestions').classList.add('d-none');
        row._orderSuggestions = items;
        panel.innerHTML = items.map((item, index) => {
            const planned = Number(item.planned_quantity || item.order_quantity || 0);
            const received = Number(item.received_quantity || 0);
            const issued = Number(item.customer_issue_quantity || 0);
            const date = item.received_date
                ? String(item.received_date).slice(0, 10).split('-').reverse().join('/')
                : '';
            const detail = [
                item.customer,
                date ? `Nhận lệnh ${date}` : '',
                item.item_code,
                item.size ? `Size ${item.size}` : '',
                item.color ? `Màu ${item.color}` : '',
            ].filter(Boolean).join(' · ');
            const progress = [
                item.has_planned_quantity === false ? 'ĐH chưa có SL' : `ĐH ${numberFormat(planned)}`,
                `Nhập ${numberFormat(received)}`,
                `Xuất ${numberFormat(issued)}`,
                `Còn ${numberFormat(item.available_quantity || 0)}`,
            ].join(' · ');
            return `
                <button class="suggestion" type="button" data-index="${index}">
                    <strong>${escapeHtml(item.production_order)}</strong>
                    <small>${escapeHtml(detail)}</small>
                    <span>${escapeHtml(progress)}</span>
                </button>`;
        }).join('');
        panel.classList.toggle('d-none', items.length === 0);
        panel.querySelectorAll('.suggestion').forEach(button => {
            button.addEventListener('click', () => applyOrderSuggestion(row, items[Number(button.dataset.index)]));
        });
    }

    async function loadOrdersForInternalCode(row, rawCode) {
        const code = String(rawCode || '').trim().toUpperCase();
        if (code.length < 2) return;
        const requestKey = code;
        row.dataset.orderLookup = requestKey;
        try {
            const result = await fetch(orderQuery(code, 100)).then(jsonOrError);
            if (row.dataset.orderLookup !== requestKey) return;
            const exactCatalogCode = (row._catalogSuggestions || []).some(item =>
                String(item.code || item.value || '').trim().toUpperCase() === code
            );
            const items = uniqueProductionOrders(result.data || [], code, exactCatalogCode).slice(0, 30);
            renderOrderSuggestions(row, items);
            if (exactCatalogCode) {
                row.querySelector('.row-state').textContent = items.length
                    ? `Đúng danh mục · ${items.length} lệnh liên quan`
                    : 'Đúng danh mục · Chưa thấy lệnh liên quan';
            }
        } catch (error) {
            if (row.dataset.orderLookup !== requestKey) return;
            row.querySelector('.order-suggestions').classList.add('d-none');
        }
    }

    async function loadOrderSuggestions(row) {
        const input = row.querySelector('.production-order');
        const keyword = input.value.trim();
        const panel = row.querySelector('.order-suggestions');
        if (keyword.length < 2) {
            panel.classList.add('d-none');
            return;
        }
        const requestKey = `manual|${keyword.toUpperCase()}`;
        row.dataset.orderLookup = requestKey;
        row.querySelector('.catalog-suggestions').classList.add('d-none');
        try {
            const result = await fetch(orderQuery(keyword, 60)).then(jsonOrError);
            if (row.dataset.orderLookup !== requestKey) return;
            renderOrderSuggestions(row, uniqueProductionOrders(result.data || [], keyword, false).slice(0, 30));
        } catch (error) {
            if (row.dataset.orderLookup !== requestKey) return;
            panel.classList.add('d-none');
            setStatus(error.message, 'error');
        }
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

    function syncIssueRowPurchaseOrderNote(row, purchaseOrder) {
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

    function applyOrderSuggestion(row, item) {
        row.querySelector('.production-order').value = item.production_order || '';
        row.dataset.appliedOrder = String(item.production_order || '').trim().toUpperCase();
        row.querySelector('.internal-code').value = item.item_code || '';
        row.dataset.orderItemCode = String(item.item_code || '').trim().toUpperCase();
        row.dataset.productionOrderId = item.id || '';
        row.dataset.purchaseOrder = purchaseOrderFromOrder(item);
        syncIssueRowPurchaseOrderNote(row, row.dataset.purchaseOrder);
        row.dataset.orderCustomer = item.customer || '';
        row.querySelector('.item-name').value = item.description || item.specification || '';
        row.querySelector('.size').value = item.size || '';
        row.querySelector('.color').value = item.color || '';
        row.querySelector('.unit').value = item.unit || row.querySelector('.unit').value || 'Cái';
        if (!document.getElementById('customerName').value.trim() && item.customer) {
            document.getElementById('customerName').value = item.customer;
        }
        row.querySelector('.order-suggestions').classList.add('d-none');
        row.querySelector('.row-state').textContent = 'Từ lệnh sản xuất';
        showOrderHistoryToast(item);
        applyExactCatalog(row).finally(() => row.querySelector('.quantity').focus());
        updateSummary();
    }

    function applySuggestion(row, item) {
        const hasSelectedOrder = Boolean(row.dataset.orderItemCode);
        row.querySelector('.internal-code').value = item.code || item.value || '';
        row.querySelector('.item-name').value = item.name || row.querySelector('.item-name').value || '';
        row.querySelector('.size').value = item.size || row.querySelector('.size').value || '';
        row.querySelector('.color').value = item.color || row.querySelector('.color').value || '';
        row.querySelector('.unit').value = item.unit || row.querySelector('.unit').value || 'Cái';
        row.querySelector('.row-state').textContent = hasSelectedOrder ? 'Từ lệnh sản xuất · Đúng danh mục' : 'Đúng danh mục';
        row.querySelectorAll('.suggestions').forEach(panel => panel.classList.add('d-none'));
        row.querySelector('.quantity').focus();
        if (!hasSelectedOrder) loadOrdersForInternalCode(row, item.code || item.value || '');
        loadStockLocations(row);
        updateSummary();
    }

    function catalogCodeKey(value) {
        return String(value || '').trim().toUpperCase();
    }

    function applyCatalogItemToIssueRows(rows, item) {
        const savedCode = item.code || item.value || '';
        rows.forEach(row => {
            row.querySelector('.internal-code').value = savedCode;
            row.querySelector('.item-name').value = item.name || row.querySelector('.item-name').value || savedCode;
            row.querySelector('.size').value = row.querySelector('.size').value || item.size || '';
            row.querySelector('.color').value = row.querySelector('.color').value || item.color || '';
            row.querySelector('.unit').value = item.unit || row.querySelector('.unit').value || 'Cái';
            row.querySelector('.row-state').textContent = 'Đúng danh mục';
            row.querySelectorAll('.suggestions').forEach(panel => panel.classList.add('d-none'));
            loadStockLocations(row);
        });
    }

    async function inspectIssueCatalog() {
        const rows = [...rowsBody.children].filter(row => {
            return row.querySelector('.internal-code').value.trim()
                && Number(row.querySelector('.quantity').value || 0) > 0;
        });
        const groupedRows = new Map();
        rows.forEach(row => {
            const code = row.querySelector('.internal-code').value.trim();
            const key = catalogCodeKey(code);
            if (!groupedRows.has(key)) groupedRows.set(key, {code, rows: []});
            groupedRows.get(key).rows.push(row);
        });

        const missing = [];
        for (const group of groupedRows.values()) {
            const result = await fetch(`/api/ma-noi-bo-danh-muc?keyword=${encodeURIComponent(group.code)}&limit=20&with_color=0`)
                .then(jsonOrError);
            const item = (result.data || []).find(candidate => {
                return catalogCodeKey(candidate.code || candidate.value) === catalogCodeKey(group.code);
            });
            if (item) {
                applyCatalogItemToIssueRows(group.rows, item);
            } else {
                missing.push(group);
            }
        }
        return missing;
    }

    async function createMissingIssueCatalog(missingGroups) {
        let appendedCount = 0;
        for (const group of missingGroups) {
            const row = group.rows[0];
            const orderInput = row.querySelector('.production-order');
            const itemName = row.querySelector('.item-name').value.trim() || group.code;
            const unit = row.querySelector('.unit').value.trim() || 'Cái';
            const size = row.querySelector('.size').value.trim();
            const color = row.querySelector('.color').value.trim();
            const response = await fetch('/api/danh-muc-noi-bo/tao-tu-lenh', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    item_code: group.code,
                    source_item_code: row.dataset.orderItemCode || group.code,
                    production_order_line_id: Number(row.dataset.productionOrderId || 0) || null,
                    item_name: itemName,
                    unit,
                    size,
                    color,
                    item_group: 'TP',
                }),
            });
            const result = await jsonOrError(response);
            applyCatalogItemToIssueRows(group.rows, {
                code: result.data?.item_code || group.code,
                name: result.data?.item_name || itemName,
                unit,
                size,
                color,
            });
            if (result.data?.appended_to_sheet) appendedCount += 1;
        }
        return appendedCount;
    }

    function collectLines() {
        return [...rowsBody.children].map(row => {
            const code = row.querySelector('.internal-code').value.trim();
            const order = row.querySelector('.production-order').value.trim();
            const locationCodes = selectedLocationCodes(row);
            return {
                ma_hh: code,
                internal_item_code: code,
                ten_hh: row.querySelector('.item-name').value.trim(),
                production_order_id: Number(row.dataset.productionOrderId || 0) || null,
                production_order: order,
                ps_number: order,
                purchase_order: row.dataset.purchaseOrder || '',
                size: row.querySelector('.size').value.trim(),
                color: row.querySelector('.color').value.trim(),
                quantity: Number(row.querySelector('.quantity').value || 0),
                dvt: row.querySelector('.unit').value.trim(),
                location_codes: locationCodes,
                location_code: locationCodes.join(', '),
                match_by_code_only: row.dataset.matchByCodeOnly === '1',
                note: row.querySelector('.line-note').value.trim(),
                customer: row.dataset.orderCustomer || document.getElementById('customerName').value.trim(),
            };
        }).filter(line => line.internal_item_code || line.quantity > 0);
    }

    function buildPickingPlan() {
        return [...rowsBody.children].flatMap((row, index) => {
            const packageAllocations = row._fifoPackageAllocations || [];
            const allocations = packageAllocations.length
                ? packageAllocations
                : [...(row._fifoAllocations || new Map()).entries()]
                    .filter(([, allocation]) => Number(allocation?.taken || 0) > 0.0001)
                    .map(([locationCode, allocation]) => ({
                        id: `stock-${locationCode}`,
                        packageCode: 'Tồn tại kệ',
                        locationCode,
                        before: Number(allocation.before || 0),
                        taken: Number(allocation.taken || 0),
                        after: Number(allocation.after || 0),
                    }));
            if (!allocations.length) return [];

            const code = row.querySelector('.internal-code').value.trim();
            const order = row.querySelector('.production-order').value.trim();
            const po = String(row.dataset.purchaseOrder || '').trim();
            const color = row.querySelector('.color').value.trim();
            const unit = row.querySelector('.unit').value.trim();
            const cartonNorm = Number(row._cartonNorm || 0);
            const title = [`Dòng ${index + 1}`, po ? `PO ${po}` : '', order, code, color].filter(Boolean).join(' · ');
            const locationGroups = [...allocations.reduce((groups, item) => {
                if (!groups.has(item.locationCode)) groups.set(item.locationCode, []);
                groups.get(item.locationCode).push(item);
                return groups;
            }, new Map()).entries()];

            return locationGroups.map(([locationCode, packages]) => {
                const totalTaken = packages.reduce((sum, item) => sum + Number(item.taken || 0), 0);
                const wholeCartons = cartonNorm > 0 ? Math.floor((totalTaken + 0.0001) / cartonNorm) : 0;
                const looseTaken = cartonNorm > 0 ? Math.max(0, totalTaken - (wholeCartons * cartonNorm)) : totalTaken;
                const partiallyUsedPackages = packages.filter(item => item.taken > 0.0001 && item.after > 0.0001);
                const returnQuantity = looseTaken > 0.0001
                    ? partiallyUsedPackages.reduce((sum, item) => sum + Number(item.after || 0), 0)
                    : 0;
                const instructions = [];
                if (wholeCartons > 0) instructions.push(`${wholeCartons} thùng nguyên`);
                if (looseTaken > 0.0001) instructions.push(`${numberFormat(looseTaken)} ${unit} lẻ`);
                if (!instructions.length) instructions.push(`${numberFormat(totalTaken)} ${unit}`);
                const packageHint = packages.length <= 3
                    ? packages.map(item => item.packageCode).join(', ')
                    : `${packages.length} kiện · ${packages[0].packageCode} … ${packages[packages.length - 1].packageCode}`;

                return {
                    key: `${row.dataset.rowId}:${locationCode}:${totalTaken}`,
                    rowIndex: index,
                    title,
                    locationCode,
                    totalTaken,
                    unit,
                    instruction: instructions.join(' + '),
                    packageHint,
                    returnQuantity,
                };
            });
        });
    }

    function renderPickingGuide() {
        const guide = document.getElementById('pickingGuide');
        const list = document.getElementById('pickingGuideList');
        const plan = buildPickingPlan();
        const startButton = document.getElementById('startPickingBtn');
        const rowGroups = [...plan.reduce((groups, step) => {
            if (!groups.has(step.rowIndex)) groups.set(step.rowIndex, []);
            groups.get(step.rowIndex).push(step);
            return groups;
        }, new Map()).values()];

        guide.hidden = plan.length === 0;
        startButton.hidden = plan.length === 0 && !editingIssueId;
        startButton.disabled = plan.length === 0;
        startButton.title = plan.length ? `${plan.length} bước soạn hàng` : 'Chưa có dòng nào đủ tồn và vị trí để soạn';
        if (!plan.length) {
            list.innerHTML = '';
            document.getElementById('pickingGuideSummary').textContent = '';
            return;
        }

        const completedCount = plan.filter(step => completedPickingSteps.has(step.key)).length;
        document.getElementById('pickingGuideSummary').textContent = `${completedCount}/${plan.length} bước đã xong`;
        list.innerHTML = rowGroups.map(steps => {
            const rowTotal = steps.reduce((sum, step) => sum + step.totalTaken, 0);
            return `
                <article class="pick-row">
                    <div class="pick-row__title">
                        <span>${escapeHtml(steps[0].title)}</span>
                        <span>${numberFormat(rowTotal)} ${escapeHtml(steps[0].unit)}</span>
                    </div>
                    ${steps.map(step => {
                        const checked = completedPickingSteps.has(step.key);
                        return `
                            <label class="pick-step${checked ? ' is-done' : ''}">
                                <input type="checkbox" data-pick-key="${escapeHtml(step.key)}" ${checked ? 'checked' : ''}>
                                <strong>${escapeHtml(step.locationCode)}</strong>
                                <span>${escapeHtml(step.packageHint)}<br><small>${step.returnQuantity > 0 ? `Trả lại: ${numberFormat(step.returnQuantity)} ${escapeHtml(step.unit)}` : 'Không có hàng lẻ trả lại'}</small></span>
                                <span class="pick-step__qty">Lấy ${escapeHtml(step.instruction)}<br>${numberFormat(step.totalTaken)} ${escapeHtml(step.unit)}</span>
                            </label>
                        `;
                    }).join('')}
                </article>
            `;
        }).join('');

        list.querySelectorAll('[data-pick-key]').forEach(input => {
            input.addEventListener('change', () => {
                if (input.checked) completedPickingSteps.add(input.dataset.pickKey);
                else completedPickingSteps.delete(input.dataset.pickKey);
                input.closest('.pick-step')?.classList.toggle('is-done', input.checked);
                renderPickingGuide();
            });
        });
    }

    function renderPickingDialog() {
        const plan = buildPickingPlan();
        const dialog = document.getElementById('pickingDialog');
        if (!plan.length) {
            if (dialog.open) dialog.close();
            return;
        }

        activePickingStepIndex = Math.max(0, Math.min(activePickingStepIndex, plan.length - 1));
        const step = plan[activePickingStepIndex];
        const isLast = activePickingStepIndex === plan.length - 1;
        document.getElementById('pickingProgressText').textContent = `Bước ${activePickingStepIndex + 1}/${plan.length}`;
        document.getElementById('pickingProgressBar').style.width = `${((activePickingStepIndex + 1) / plan.length) * 100}%`;
        document.getElementById('pickingRowLabel').textContent = step.title;
        document.getElementById('pickingShelfCode').textContent = step.locationCode;
        document.getElementById('pickingInstruction').textContent = step.instruction;
        document.getElementById('pickingTotal').textContent = `Tổng ${numberFormat(step.totalTaken)} ${step.unit}`;
        document.getElementById('pickingPackageHint').textContent = step.packageHint;
        const returnBox = document.getElementById('pickingReturnBox');
        returnBox.hidden = step.returnQuantity <= 0.0001;
        document.getElementById('pickingReturnText').textContent = `Trả ${numberFormat(step.returnQuantity)} ${step.unit} về kệ ${step.locationCode}`;
        document.getElementById('previousPickingBtn').disabled = activePickingStepIndex === 0;
        document.getElementById('completePickingBtn').innerHTML = isLast
            ? '<i data-lucide="check-check"></i>Hoàn tất soạn'
            : '<i data-lucide="check"></i>Xong, bước tiếp';
        window.lucide?.createIcons();
    }

    function openPickingDialog() {
        const plan = buildPickingPlan();
        if (!plan.length) {
            setStatus('Chưa có vị trí FIFO để hướng dẫn soạn hàng.', 'error');
            return;
        }
        const firstIncomplete = plan.findIndex(step => !completedPickingSteps.has(step.key));
        activePickingStepIndex = firstIncomplete >= 0 ? firstIncomplete : 0;
        renderPickingDialog();
        const dialog = document.getElementById('pickingDialog');
        dialog.showModal();
        dialog.focus();
    }

    function completeCurrentPickingStep() {
        const plan = buildPickingPlan();
        const step = plan[activePickingStepIndex];
        if (!step) return;
        completedPickingSteps.add(step.key);
        renderPickingGuide();
        if (activePickingStepIndex < plan.length - 1) {
            activePickingStepIndex += 1;
            renderPickingDialog();
            return;
        }
        document.getElementById('pickingDialog').close();
        setStatus(`Đã hoàn tất ${plan.length}/${plan.length} bước soạn hàng.`, 'success');
    }

    function updateSummary() {
        const lines = collectLines().filter(line => line.internal_item_code && line.quantity > 0);
        document.getElementById('lineCount').textContent = lines.length;
        document.getElementById('totalQuantity').textContent = numberFormat(lines.reduce((sum, line) => sum + line.quantity, 0));
        renderPickingGuide();
    }

    function confirmIssue(message, options = {}) {
        return new Promise(resolve => {
            const dialog = document.getElementById('confirmDialog');
            dialog.querySelector('h2').textContent = options.title || 'Xác nhận xuất thành phẩm';
            document.getElementById('confirmText').textContent = message;
            const accept = document.getElementById('acceptConfirmBtn');
            const cancel = document.getElementById('cancelConfirmBtn');
            accept.textContent = options.acceptText || 'Xác nhận xuất';
            cancel.textContent = options.cancelText || 'Kiểm tra lại';
            const finish = value => {
                accept.onclick = null;
                cancel.onclick = null;
                dialog.close();
                resolve(value);
            };
            accept.onclick = () => finish(true);
            cancel.onclick = () => finish(false);
            dialog.showModal();
        });
    }

    async function saveIssue(allowNegative = false, saveAsDraft = false) {
        let customer = document.getElementById('customerName').value.trim();
        const issueDate = displayToIsoDate(document.getElementById('issueDate').value);
        let lines = collectLines();
        if (!issueDate) {
            setStatus('Ngày xuất phải đúng định dạng dd/mm/yyyy.', 'error');
            document.getElementById('issueDate').focus();
            return;
        }
        if (!customer) {
            setStatus('Nhập khách hàng.', 'error');
            document.getElementById('customerName').focus();
            return;
        }
        try {
            const customerRecord = await requireCatalogCustomer(customer);
            customer = customerRecord.name;
            customerNameInput.value = customerRecord.name;
            lines = collectLines();
        } catch (error) {
            setStatus(error.message, 'error');
            customerNameInput.focus();
            return;
        }
        if (!lines.length || lines.some(line => !line.internal_item_code || line.quantity <= 0)) {
            setStatus('Mỗi dòng sử dụng cần mã nội bộ và số lượng lớn hơn 0.', 'error');
            return;
        }
        const missingLocationRow = [...rowsBody.children].find(row =>
            row.querySelector('.internal-code').value.trim()
            && Number(row.querySelector('.quantity').value || 0) > 0
            && selectedLocationCodes(row).length === 0
        );
        if (!saveAsDraft && missingLocationRow) {
            setStatus('Có dòng chưa có kệ nên chưa thể xuất. Chọn kệ hoặc bấm Lưu chờ soạn để cập nhật sau.', 'error');
            missingLocationRow.querySelector('.location-trigger')?.focus();
            return;
        }

        setStatus('Đang kiểm tra mã trong danh mục...');
        let missingCatalogGroups = [];
        try {
            missingCatalogGroups = await inspectIssueCatalog();
            lines = collectLines();
        } catch (error) {
            setStatus(error.message, 'error');
            return;
        }

        const total = lines.reduce((sum, line) => sum + line.quantity, 0);
        const missingCatalogNotice = missingCatalogGroups.length
            ? `\n\n${missingCatalogGroups.length} mã chưa có trong DANH MỤC và sẽ được append:\n${missingCatalogGroups.map(group => group.code).join(', ')}`
            : '';
        const confirmed = await confirmIssue(
            saveAsDraft
                ? `Lưu phiếu chờ soạn gồm ${lines.length} dòng · Tổng SL ${numberFormat(total)}?\n\nPhiếu chưa trừ tồn cho đến khi chọn đủ kệ và xác nhận xuất.${missingCatalogNotice}`
                : `Khách hàng: ${customer}\n${lines.length} dòng · Tổng SL ${numberFormat(total)}\nHệ thống sẽ kiểm tồn, trừ FIFO và tạo phiếu xuất.${missingCatalogNotice}`,
            saveAsDraft ? {title: 'Lưu phiếu chờ soạn', acceptText: 'Lưu phiếu chờ'} : {}
        );
        if (!confirmed) return;

        const printWindow = saveAsDraft ? null : window.open('', '_blank');
        const activeButton = saveAsDraft ? document.getElementById('saveDraftBtn') : saveButton;
        saveButton.disabled = true;
        document.getElementById('saveDraftBtn').disabled = true;
        activeButton.innerHTML = '<i class="spin" data-lucide="loader-circle"></i>Đang lưu';
        window.lucide?.createIcons();
        setOperationLoading(
            true,
            missingCatalogGroups.length ? 'Đang chuẩn hóa danh mục' : 'Đang kiểm tồn FIFO',
            missingCatalogGroups.length ? 'Đang thêm mã còn thiếu trước khi xuất.' : 'Đang kiểm tra tồn và chuẩn bị phiếu xuất.'
        );

        if (missingCatalogGroups.length) {
            setStatus('Đang thêm mã mới vào Google Sheet DANH MỤC...');
            try {
                const appendedCount = await createMissingIssueCatalog(missingCatalogGroups);
                lines = collectLines();
                setStatus(`Đã chuẩn hóa danh mục (${appendedCount} mã mới). Đang kiểm tồn FIFO...`);
            } catch (error) {
                printWindow?.close();
                saveButton.disabled = false;
                document.getElementById('saveDraftBtn').disabled = false;
                saveButton.innerHTML = '<i data-lucide="printer"></i>Lưu + in';
                document.getElementById('saveDraftBtn').innerHTML = '<i data-lucide="save"></i>Lưu chờ soạn';
                window.lucide?.createIcons();
                setOperationLoading(false);
                setStatus(error.message, 'error');
                return;
            }
        } else {
            setStatus('Đang kiểm tồn và tạo phiếu xuất...');
        }

        try {
            setOperationLoading(
                true,
                saveAsDraft ? 'Đang lưu phiếu chờ' : 'Đang tạo phiếu xuất',
                saveAsDraft ? 'Phiếu chưa tác động đến tồn kho.' : 'Đang trừ tồn FIFO và ghi dữ liệu phiếu.'
            );
            const result = await fetch(editingIssueId ? `/api/xuat-vat-tu-noi-bo/${editingIssueId}` : '/api/xuat-vat-tu-noi-bo', {
                method: editingIssueId ? 'PUT' : 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    issue_type: 'customer',
                    issue_date: issueDate,
                    warehouse_code: '',
                    receiver_name: document.getElementById('receiverName').value.trim(),
                    department: 'Kinh doanh',
                    production_order: [...new Set(lines.map(line => line.production_order).filter(Boolean))].join(', '),
                    purpose: 'Xuất thành phẩm cho khách hàng',
                    note: document.getElementById('issueNote').value.trim(),
                    allow_negative: allowNegative,
                    save_as_draft: saveAsDraft,
                    lines,
                })
            }).then(jsonOrError);

            if (printWindow && result.print_url) printWindow.location.href = result.print_url;
            else printWindow?.close();
            const negativeCount = Array.isArray(result.stock_warnings) ? result.stock_warnings.length : 0;
            setStatus(
                saveAsDraft
                    ? `Đã lưu ${result.data?.issue_code || 'phiếu chờ soạn'}. Chưa trừ tồn kho.`
                    : `Đã tạo ${result.data?.issue_code || 'phiếu xuất thành phẩm'}.`
                + (negativeCount ? ` Có ${negativeCount} dòng đã xác nhận xuất âm.` : ''),
                'success'
            );
            if (saveAsDraft) {
                localStorage.setItem('lastFinishedGoodsDraftCode', result.data?.issue_code || '');
                await searchDraftIssues();
            }
            stockLocationRequests.clear();
            resetForm(false);
        } catch (error) {
            printWindow?.close();
            const payload = error.payload || {};
            const stockWarnings = Array.isArray(payload.stock_warnings) ? payload.stock_warnings : [];
            const stockErrors = Array.isArray(payload.errors?.stock) ? payload.errors.stock : [];
            if (!allowNegative && (payload.requires_negative_confirmation || stockWarnings.length || stockErrors.length)) {
                setOperationLoading(false);
                const warningLines = stockWarnings.length
                    ? stockWarnings.map(item => item.message)
                    : stockErrors;
                const confirmedNegative = await confirmIssue(
                    `Tồn không đủ cho ${warningLines.length} dòng:\n\n${warningLines.join('\n\n')}\n\nChỉ tiếp tục khi bạn đã kiểm tra phiếu nhập, size, màu, mặt và vị trí.`,
                    {
                        title: 'Cảnh báo xuất âm tồn',
                        acceptText: 'Vẫn xuất âm',
                        cancelText: 'Kiểm tra lại'
                    }
                );
                if (confirmedNegative) {
                    saveButton.disabled = false;
                    await saveIssue(true, false);
                } else {
                    setStatus('Chưa xuất. Phiếu được giữ nguyên để kiểm tra tồn hoặc phiếu nhập.', 'error');
                }
                return;
            }
            setStatus(error.message, 'error');
        } finally {
            saveButton.disabled = false;
            document.getElementById('saveDraftBtn').disabled = false;
            saveButton.innerHTML = '<i data-lucide="printer"></i>Lưu + in';
            document.getElementById('saveDraftBtn').innerHTML = '<i data-lucide="save"></i>Lưu chờ soạn';
            setOperationLoading(false);
            window.lucide?.createIcons();
        }
    }

    function resetForm(clearHeader = true) {
        editingIssueId = null;
        if (clearHeader) {
            document.getElementById('customerName').value = '';
            document.getElementById('receiverName').value = '';
            document.getElementById('issueNote').value = '';
        }
        rowsBody.innerHTML = '';
        for (let index = 0; index < 8; index += 1) addRow();
        updateSummary();
        rowsBody.querySelector('.internal-code')?.focus();
    }

    async function searchDraftIssues() {
        const keyword = document.getElementById('draftSearchKeyword').value.trim();
        const results = document.getElementById('draftSearchResults');
        results.innerHTML = '<div class="location-empty">Đang tìm phiếu chờ...</div>';
        try {
            const params = new URLSearchParams({status: 'draft', issue_type: 'customer'});
            if (keyword) params.set('keyword', keyword);
            const payload = await fetch(`/api/xuat-vat-tu-noi-bo?${params.toString()}`).then(jsonOrError);
            const issues = payload.data || [];
            document.getElementById('draftCountBadge').textContent = numberFormat(issues.length);
            document.getElementById('draftDialogCount').textContent = `(${numberFormat(issues.length)})`;
            results.innerHTML = issues.length ? issues.map(issue => `
                <button class="draft-result" type="button" data-draft-id="${Number(issue.id)}">
                    <span><strong>${escapeHtml(issue.issue_code || '')}</strong><br><small>${escapeHtml(isoToDisplayDate(String(issue.issue_date || '').slice(0, 10)))} · lưu ${escapeHtml(String(issue.created_at || '').slice(11, 16))}</small></span>
                    <span class="draft-result__main"><strong>${escapeHtml(issue.customer_label || 'Chưa ghi khách hàng')}</strong><small>${escapeHtml((issue.item_preview || []).join(', ') || issue.production_order || 'Chưa có mã hàng')}</small></span>
                    <span class="draft-result__status">${numberFormat(issue.lines_count || 0)} dòng<br>${Number(issue.missing_location_count || 0) > 0 ? `${numberFormat(issue.missing_location_count)} chưa có kệ` : 'Đã chọn kệ'}</span>
                </button>
            `).join('') : '<div class="location-empty">Hiện không có phiếu chờ trong database nội bộ.</div>';
            results.querySelectorAll('[data-draft-id]').forEach(button => {
                button.addEventListener('click', () => loadDraftIssue(Number(button.dataset.draftId)));
            });
        } catch (error) {
            results.innerHTML = `<div class="location-empty">${escapeHtml(error.message)}</div>`;
        }
    }

    async function loadDraftIssue(issueId) {
        setOperationLoading(true, 'Đang mở phiếu chờ', 'Đang kiểm tra lại tồn và vị trí hiện tại.');
        try {
            const payload = await fetch(`/api/xuat-vat-tu-noi-bo/${issueId}`).then(jsonOrError);
            const issue = payload.data || {};
            if (issue.status !== 'draft') throw new Error('Phiếu này không còn ở trạng thái chờ soạn.');
            const lines = issue.lines || [];

            resetForm(true);
            editingIssueId = Number(issue.id);
            document.getElementById('issueDate').value = isoToDisplayDate(String(issue.issue_date || '').slice(0, 10));
            document.getElementById('receiverName').value = issue.receiver_name || '';
            document.getElementById('issueNote').value = issue.note || '';
            customerNameInput.value = lines.find(line => String(line.customer || '').trim())?.customer || '';

            while (rowsBody.children.length < lines.length) addRow();
            const rows = [...rowsBody.children];
            lines.forEach((line, index) => {
                const row = rows[index];
                row.querySelector('.production-order').value = line.production_order || '';
                row.querySelector('.internal-code').value = line.internal_item_code || line.ma_hh || '';
                row.querySelector('.item-name').value = line.ten_hh || '';
                row.querySelector('.size').value = line.size || '';
                row.querySelector('.color').value = line.color || '';
                row.querySelector('.quantity').value = Number(line.quantity || 0);
                row.querySelector('.unit').value = line.dvt || 'Cái';
                row.querySelector('.line-note').value = line.note || '';
                row.dataset.productionOrderId = line.production_order_id || '';
                row.dataset.purchaseOrder = line.purchase_order || '';
                row.dataset.orderCustomer = line.customer || '';
                row.dataset.pastedLocation = line.location_code || '';
                row.dataset.pastedFifo = '1';
            });
            await Promise.all(lines.map((line, index) => loadStockLocations(rows[index])));
            assignPastedLocationsByFifo(rows.filter(row => row.dataset.pastedFifo === '1'));
            updateSummary();
            document.getElementById('draftSearchDialog').close();
            const pickingStepCount = buildPickingPlan().length;
            const missingLocationCount = rows.slice(0, lines.length)
                .filter(row => selectedLocationCodes(row).length === 0)
                .length;
            setStatus(
                `Đã mở ${issue.issue_code} · ${pickingStepCount} bước soạn`
                + (missingLocationCount ? ` · ${missingLocationCount} dòng chưa có kệ.` : '.'),
                missingLocationCount ? 'error' : 'success'
            );
        } catch (error) {
            setStatus(error.message, 'error');
        } finally {
            setOperationLoading(false);
        }
    }

    function previewPastedExcel() {
        const summary = document.getElementById('pasteExcelSummary');
        const text = document.getElementById('pasteExcelData').value;
        if (!text.trim()) {
            summary.textContent = 'Chưa có dữ liệu.';
            return;
        }
        try {
            const lines = parseFinishedGoodsPaste(text);
            const total = lines.reduce((sum, line) => sum + line.quantity, 0);
            const locations = new Set(lines.flatMap(line => pastedLocationCodes(line.location)));
            summary.textContent = `${numberFormat(lines.length)} dòng · Tổng ${numberFormat(total)} · ${locations.size ? `${locations.size} vị trí có sẵn` : 'sẽ tự dò kệ FIFO'}`;
        } catch (error) {
            summary.textContent = error.message;
        }
    }

    function openPasteExcelDialog() {
        const dialog = document.getElementById('pasteExcelDialog');
        document.getElementById('pasteExcelData').value = '';
        previewPastedExcel();
        dialog.showModal();
        setTimeout(() => document.getElementById('pasteExcelData').focus(), 0);
        window.lucide?.createIcons();
    }

    document.addEventListener('click', event => {
        if (event.target.closest('.suggest-wrap')) return;
        document.querySelectorAll('.suggestions').forEach(panel => panel.classList.add('d-none'));
    });
    document.getElementById('selectAllLocations').addEventListener('change', event => {
        const options = activeLocationRow?._stockLocations || [];
        locationDraft = event.currentTarget.checked
            ? new Set(options.map(item => item.location_code))
            : new Set();
        renderLocationDialog();
    });
    document.getElementById('applyLocationBtn').addEventListener('click', () => {
        if (!activeLocationRow) return;
        activeLocationRow._selectedLocations = new Set(locationDraft);
        activeLocationRow._preferredLocations = new Set(locationDraft);
        activeLocationRow.dataset.manualLocationSelection = '1';
        if (activeLocationRow.dataset.pastedFifo === '1') {
            assignPastedLocationsByFifo([...rowsBody.children].filter(item => item.dataset.pastedFifo === '1'));
        } else {
            updateLocationTrigger(activeLocationRow);
        }
        document.getElementById('locationDialog').close();
    });
    ['closeLocationDialogBtn', 'cancelLocationBtn'].forEach(id => {
        document.getElementById(id).addEventListener('click', () => document.getElementById('locationDialog').close());
    });
    document.getElementById('startPickingBtn').addEventListener('click', openPickingDialog);
    document.getElementById('closePickingDialogBtn').addEventListener('click', () => document.getElementById('pickingDialog').close());
    document.getElementById('previousPickingBtn').addEventListener('click', () => {
        activePickingStepIndex = Math.max(0, activePickingStepIndex - 1);
        renderPickingDialog();
    });
    document.getElementById('completePickingBtn').addEventListener('click', completeCurrentPickingStep);
    document.addEventListener('keydown', event => {
        const dialog = document.getElementById('pickingDialog');
        if (!dialog.open || event.code !== 'Space' || event.target.matches('input, textarea, select, button')) return;
        event.preventDefault();
        completeCurrentPickingStep();
    });
    document.getElementById('addRowBtn').addEventListener('click', () => addRow(true));
    document.getElementById('pasteExcelBtn').addEventListener('click', openPasteExcelDialog);
    document.getElementById('pasteExcelData').addEventListener('input', previewPastedExcel);
    ['closePasteExcelBtn', 'cancelPasteExcelBtn'].forEach(id => {
        document.getElementById(id).addEventListener('click', () => document.getElementById('pasteExcelDialog').close());
    });
    document.getElementById('applyPasteExcelBtn').addEventListener('click', async event => {
        const button = event.currentTarget;
        try {
            const lines = parseFinishedGoodsPaste(document.getElementById('pasteExcelData').value);
            button.disabled = true;
            document.getElementById('pasteExcelDialog').close();
            await applyPastedLines(lines);
        } catch (error) {
            document.getElementById('pasteExcelSummary').textContent = error.message;
        } finally {
            button.disabled = false;
        }
    });
    document.getElementById('resetBtn').addEventListener('click', () => resetForm(true));
    document.getElementById('saveIssueBtn').addEventListener('click', () => saveIssue(false));
    document.getElementById('saveDraftBtn').addEventListener('click', () => saveIssue(false, true));
    document.getElementById('findDraftBtn').addEventListener('click', () => {
        document.getElementById('draftSearchDialog').showModal();
        document.getElementById('draftSearchKeyword').value = '';
        searchDraftIssues();
        setTimeout(() => document.getElementById('draftSearchKeyword').focus(), 0);
    });
    document.getElementById('closeDraftSearchBtn').addEventListener('click', () => document.getElementById('draftSearchDialog').close());
    document.getElementById('runDraftSearchBtn').addEventListener('click', searchDraftIssues);
    document.getElementById('draftSearchKeyword').addEventListener('keydown', event => {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        searchDraftIssues();
    });
    customerNameInput.addEventListener('focus', () => loadCustomerSuggestions(customerNameInput.value));
    customerNameInput.addEventListener('input', () => loadCustomerSuggestions(customerNameInput.value));
    document.getElementById('issueDate').addEventListener('blur', event => {
        const iso = displayToIsoDate(event.currentTarget.value);
        if (iso) event.currentTarget.value = isoToDisplayDate(iso);
    });
    document.getElementById('issueDate').value = isoToDisplayDate(localIsoDate());
    loadCustomerSuggestions();
    searchDraftIssues();
    resetForm(false);
    window.lucide?.createIcons();
})();
</script>
</body>
</html>
