<!doctype html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Quản lý dệt nhanh | WMS May Mặc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/warehouse-wms.css') }}?v={{ filemtime(public_path('css/warehouse-wms.css')) }}" rel="stylesheet">
    <style>
        :root {
            --weave-ink: #12345a;
            --weave-muted: #667d99;
            --weave-blue: #356fe5;
            --weave-blue-dark: #245ac5;
            --weave-soft: #edf5ff;
            --weave-line: #cbdcf2;
            --weave-bg: #f3f8ff;
            --weave-green: #16845f;
            --weave-amber: #a36208;
            --weave-red: #c43f51;
        }
        * { box-sizing: border-box; }
        html, body { width: 100%; min-height: 100%; max-width: 100%; overflow-x: hidden; }
        body { margin: 0; background: var(--weave-bg); color: var(--weave-ink); }
        button, input { letter-spacing: 0; }
        .weave-topbar {
            position: sticky; top: 0; z-index: 40;
            display: flex; align-items: center; justify-content: space-between; gap: 16px;
            min-height: 62px; padding: 10px max(18px, calc((100vw - 1240px) / 2));
            border-bottom: 1px solid var(--weave-line); background: rgba(255,255,255,.96);
        }
        .weave-brand { display: flex; align-items: center; gap: 10px; min-width: 0; color: var(--weave-ink); text-decoration: none; }
        .weave-brand__icon { display: grid; place-items: center; width: 38px; height: 38px; border-radius: 8px; background: var(--weave-blue); color: #fff; }
        .weave-brand__icon svg { width: 20px; }
        .weave-brand strong { display: block; font-size: 15px; }
        .weave-brand small { display: block; color: var(--weave-muted); font-size: 10px; font-weight: 700; }
        .weave-top-actions { display: flex; align-items: center; gap: 8px; }
        .weave-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 7px;
            min-height: 38px; padding: 8px 12px; border: 1px solid #b9cde9; border-radius: 8px;
            background: #fff; color: #24476f; font-size: 12px; font-weight: 800; text-decoration: none;
            transition: border-color .18s ease, background-color .18s ease, color .18s ease;
        }
        .weave-btn:hover, .weave-btn:focus-visible { border-color: #77a4e9; background: #edf5ff; color: #174f9b; outline: 0; }
        .weave-btn svg { width: 16px; height: 16px; }
        .weave-btn--primary { border-color: var(--weave-blue); background: var(--weave-blue); color: #fff; }
        .weave-btn--primary:hover, .weave-btn--primary:focus-visible { border-color: var(--weave-blue-dark); background: var(--weave-blue-dark); color: #fff; }
        .weave-btn:disabled { opacity: .55; cursor: not-allowed; }
        .weave-icon-btn { width: 38px; padding: 0; }
        .weave-main { width: min(1240px, calc(100% - 28px)); max-width: calc(100vw - 28px); margin: 0 auto; padding: 22px 0 34px; }
        .weave-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 14px; }
        .weave-heading h1 { margin: 0; font-size: 24px; font-weight: 900; }
        .weave-heading__meta { color: var(--weave-muted); font-size: 11px; font-weight: 700; }
        .weave-shortcuts { display: grid; grid-template-columns: 1.2fr 1fr 1fr; gap: 10px; margin-bottom: 12px; }
        .weave-shortcut {
            display: flex; align-items: center; gap: 12px; min-height: 58px; padding: 10px 14px;
            border: 1px solid var(--weave-line); border-radius: 8px; background: #fff;
            color: var(--weave-ink); text-decoration: none; cursor: pointer;
            transition: border-color .18s ease, background-color .18s ease;
        }
        .weave-shortcut:hover, .weave-shortcut:focus-visible { border-color: #78a5eb; background: #f8fbff; outline: 0; }
        .weave-shortcut--primary { border-color: #91b8f6; background: #eaf3ff; }
        .weave-shortcut__icon { display: grid; place-items: center; flex: 0 0 auto; width: 38px; height: 38px; border-radius: 8px; background: var(--weave-soft); color: var(--weave-blue); }
        .weave-shortcut--primary .weave-shortcut__icon { background: var(--weave-blue); color: #fff; }
        .weave-shortcut__icon svg { width: 19px; }
        .weave-shortcut strong { display: block; font-size: 13px; }
        .weave-search-panel { border: 1px solid var(--weave-line); border-radius: 8px; background: #fff; overflow: hidden; }
        .weave-search-row { display: grid; grid-template-columns: minmax(260px, 1fr) auto; gap: 10px; padding: 12px; border-bottom: 1px solid #dce7f5; }
        .weave-search { position: relative; min-width: 0; }
        .weave-search > svg { position: absolute; top: 50%; left: 14px; width: 18px; color: #52759f; transform: translateY(-50%); pointer-events: none; }
        .weave-search input { width: 100%; min-height: 42px; padding: 8px 42px; border: 1px solid #9ebbe0; border-radius: 8px; color: var(--weave-ink); font-size: 14px; font-weight: 700; }
        .weave-search input:focus { border-color: var(--weave-blue); outline: 3px solid rgba(53,111,229,.13); }
        .weave-search-clear { position: absolute; top: 50%; right: 5px; border: 0; background: transparent; color: #647b96; transform: translateY(-50%); }
        .weave-search-clear[hidden] { display: none; }
        .weave-summary { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); border-bottom: 1px solid #dce7f5; }
        .weave-summary__item { min-width: 0; padding: 10px 14px; border-right: 1px solid #dce7f5; }
        .weave-summary__item:last-child { border-right: 0; }
        .weave-summary__item span { display: block; color: var(--weave-muted); font-size: 9px; font-weight: 800; text-transform: uppercase; }
        .weave-summary__item strong { display: block; margin-top: 1px; font-size: 19px; }
        .weave-filterbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 9px 12px; border-bottom: 1px solid #dce7f5; }
        .weave-segments { display: flex; gap: 4px; min-width: 0; overflow-x: auto; scrollbar-width: thin; }
        .weave-segment { min-height: 32px; padding: 6px 10px; border: 1px solid transparent; border-radius: 7px; background: transparent; color: #59718e; font-size: 11px; font-weight: 800; white-space: nowrap; }
        .weave-segment:hover { background: #f0f6ff; }
        .weave-segment.is-active { border-color: #aac7ee; background: #e7f1ff; color: #175ba9; }
        .weave-count { flex: 0 0 auto; color: var(--weave-muted); font-size: 10px; font-weight: 750; }
        .weave-list { min-height: 270px; }
        .weave-list-head, .weave-order {
            display: grid; grid-template-columns: minmax(150px,.8fr) minmax(240px,1.35fr) minmax(150px,.8fr) minmax(190px,1fr) minmax(120px,.65fr) auto;
            gap: 12px; align-items: center;
        }
        .weave-list-head { padding: 9px 14px; background: #0f3157; color: #fff; font-size: 9px; font-weight: 850; text-transform: uppercase; }
        .weave-order { min-height: 76px; padding: 10px 14px; border-bottom: 1px solid #e0e9f5; background: #fff; }
        .weave-order:last-child { border-bottom: 0; }
        .weave-order:hover { background: #f9fbff; }
        .weave-code { color: #155fae; font-family: Consolas, monospace; font-size: 12px; font-weight: 900; }
        .weave-sub { display: block; margin-top: 3px; color: var(--weave-muted); font-size: 10px; line-height: 1.35; }
        .weave-product strong { display: block; font-size: 12px; overflow-wrap: anywhere; }
        .weave-qty { font-size: 12px; font-weight: 900; }
        .weave-progress { height: 5px; margin-top: 6px; overflow: hidden; border-radius: 3px; background: #e5edf7; }
        .weave-progress span { display: block; height: 100%; border-radius: inherit; background: var(--weave-blue); }
        .weave-status { display: inline-flex; align-items: center; width: max-content; max-width: 100%; padding: 4px 8px; border-radius: 999px; font-size: 9px; font-weight: 900; }
        .weave-status--waiting { background: #eef2f7; color: #53657a; }
        .weave-status--producing { background: #e6f1ff; color: #1761b1; }
        .weave-status--partial { background: #fff2d9; color: var(--weave-amber); }
        .weave-status--completed { background: #ddf7ed; color: var(--weave-green); }
        .weave-overdue { display: block; margin-top: 4px; color: var(--weave-red); font-size: 9px; font-weight: 900; }
        .weave-row-actions { display: flex; justify-content: flex-end; gap: 6px; }
        .weave-row-actions .weave-btn { min-height: 34px; padding: 6px 9px; }
        .weave-empty { display: grid; place-items: center; min-height: 270px; padding: 28px; color: var(--weave-muted); text-align: center; }
        .weave-empty svg { width: 30px; margin-bottom: 8px; color: #7396c1; }
        .weave-skeleton { height: 76px; margin: 0 14px; border-bottom: 1px solid #e0e9f5; background: linear-gradient(90deg,#f6f9fd 25%,#eaf2fb 38%,#f6f9fd 63%); background-size: 400% 100%; animation: weave-loading 1.2s ease infinite; }
        .weave-pagination { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 12px; border-top: 1px solid #dce7f5; }
        .weave-pagination span { color: var(--weave-muted); font-size: 10px; font-weight: 750; }
        .weave-dialog { position: fixed; inset: 0; z-index: 100; display: grid; place-items: center; padding: 18px; background: rgba(15,49,87,.36); }
        .weave-dialog[hidden] { display: none; }
        .weave-dialog__panel { width: min(410px,100%); border: 1px solid #b6ccea; border-radius: 8px; background: #fff; box-shadow: 0 20px 60px rgba(18,52,90,.2); }
        .weave-dialog__body { padding: 18px; }
        .weave-dialog__body h2 { margin: 0 0 7px; font-size: 17px; }
        .weave-dialog__body p { margin: 0; color: var(--weave-muted); font-size: 12px; }
        .weave-dialog__actions { display: flex; justify-content: flex-end; gap: 8px; padding: 11px 14px; border-top: 1px solid #dce7f5; }
        .weave-toast { position: fixed; right: 18px; bottom: 18px; z-index: 120; max-width: min(390px, calc(100vw - 36px)); padding: 11px 14px; border-radius: 8px; background: #12345a; color: #fff; font-size: 12px; font-weight: 750; opacity: 0; transform: translateY(10px); pointer-events: none; transition: .18s ease; }
        .weave-toast.is-visible { opacity: 1; transform: translateY(0); }
        .weave-toast.is-error { background: #b62f42; }
        @keyframes weave-loading { 0% { background-position: 100% 0; } 100% { background-position: 0 0; } }
        @media (max-width: 900px) {
            .weave-list-head { display: none; }
            .weave-order { grid-template-columns: 1fr 1fr; gap: 9px 14px; }
            .weave-row-actions { justify-content: flex-start; }
        }
        @media (max-width: 680px) {
            .weave-topbar { width: 100%; max-width: 100vw; padding: 10px 62px 10px 12px; overflow: hidden; }
            .weave-brand { flex: 1 1 auto; }
            .weave-top-actions { position: absolute; top: 12px; right: 12px; width: 38px; }
            .weave-top-actions .weave-btn:not(.weave-btn--primary) { display: none; }
            .weave-top-actions .weave-btn--primary { width: 38px; padding: 0; gap: 0; overflow: hidden; font-size: 0; }
            .weave-main { width: min(100% - 20px, 1240px); padding-top: 14px; }
            .weave-heading h1 { font-size: 20px; }
            .weave-heading__meta { display: none; }
            .weave-shortcuts { grid-template-columns: 1fr; }
            .weave-shortcut { min-height: 54px; }
            .weave-search-row { grid-template-columns: 1fr; }
            .weave-search-row > .weave-btn { display: none; }
            .weave-summary { grid-template-columns: repeat(2,1fr); }
            .weave-summary__item:nth-child(2) { border-right: 0; }
            .weave-summary__item:nth-child(-n+2) { border-bottom: 1px solid #dce7f5; }
            .weave-filterbar { align-items: flex-start; flex-direction: column; }
            .weave-order { grid-template-columns: minmax(0,1fr) minmax(0,1fr); }
            .weave-order .weave-product { grid-column: 1 / -1; }
            .weave-order > :nth-child(4) { grid-column: 1 / -1; }
            .weave-row-actions { justify-content: flex-end; }
            .weave-row-actions .weave-btn { width: 34px; min-width: 34px; padding: 0; gap: 0; overflow: hidden; font-size: 0; }
        }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; } }
    </style>
</head>
<body>
<header class="weave-topbar">
    <a class="weave-brand" href="{{ url('/client/home') }}">
        <span class="weave-brand__icon"><i data-lucide="panels-top-left"></i></span>
        <span><strong>Quản lý dệt</strong><small>WMS May Mặc</small></span>
    </a>
    <div class="weave-top-actions">
        <a class="weave-btn" href="{{ route('weaving.tracking') }}"><i data-lucide="chart-no-axes-combined"></i>Theo dõi chi tiết</a>
        <a class="weave-btn weave-btn--primary" href="{{ route('weaving.orders.create') }}"><i data-lucide="plus"></i>Tạo lệnh</a>
    </div>
</header>

<main class="weave-main">
    <div class="weave-heading">
        <h1>Quản lý dệt nhanh</h1>
        <span class="weave-heading__meta">Lệnh năm {{ now('Asia/Ho_Chi_Minh')->format('Y') }}</span>
    </div>

    <nav class="weave-shortcuts" aria-label="Thao tác dệt">
        <a class="weave-shortcut weave-shortcut--primary" href="{{ route('weaving.orders.create') }}">
            <span class="weave-shortcut__icon"><i data-lucide="file-plus-2"></i></span>
            <span><strong>Tạo lệnh dệt</strong></span>
        </a>
        <a class="weave-shortcut" href="{{ route('weaving.bom') }}">
            <span class="weave-shortcut__icon"><i data-lucide="list-tree"></i></span>
            <span><strong>Định mức sợi</strong></span>
        </a>
        <a class="weave-shortcut" href="{{ route('weaving.exports.index') }}">
            <span class="weave-shortcut__icon"><i data-lucide="file-spreadsheet"></i></span>
            <span><strong>In / Xuất Excel</strong></span>
        </a>
    </nav>

    <section class="weave-search-panel" aria-label="Tìm và theo dõi lệnh dệt">
        <div class="weave-search-row">
            <div class="weave-search">
                <i data-lucide="search"></i>
                <input id="quickKeyword" autocomplete="off" autofocus placeholder="Nhập lệnh, mã hàng, PO, design hoặc khách hàng">
                <button id="clearKeyword" class="weave-search-clear" type="button" aria-label="Xóa tìm kiếm" title="Xóa tìm kiếm" hidden><i data-lucide="x"></i></button>
            </div>
            <button id="reloadButton" class="weave-btn weave-icon-btn" type="button" aria-label="Tải lại" title="Tải lại"><i data-lucide="refresh-cw"></i></button>
        </div>

        <div class="weave-summary" aria-label="Tóm tắt lệnh dệt">
            <div class="weave-summary__item"><span>Tổng lệnh</span><strong id="summaryTotal">0</strong></div>
            <div class="weave-summary__item"><span>Chờ sản xuất</span><strong id="summaryWaiting">0</strong></div>
            <div class="weave-summary__item"><span>Đang sản xuất</span><strong id="summaryProducing">0</strong></div>
            <div class="weave-summary__item"><span>Hoàn thành</span><strong id="summaryCompleted">0</strong></div>
        </div>

        <div class="weave-filterbar">
            <div class="weave-segments" role="tablist" aria-label="Lọc trạng thái">
                <button class="weave-segment is-active" type="button" data-status="">Tất cả</button>
                <button class="weave-segment" type="button" data-status="waiting">Chờ SX</button>
                <button class="weave-segment" type="button" data-status="producing">Đang SX</button>
                <button class="weave-segment" type="button" data-status="partial">Nhập một phần</button>
                <button class="weave-segment" type="button" data-status="completed">Hoàn thành</button>
                <button class="weave-segment" type="button" data-status="overdue">Trễ hạn</button>
            </div>
            <span id="resultCount" class="weave-count">0 kết quả</span>
        </div>

        <div class="weave-list-head" aria-hidden="true">
            <span>Lệnh dệt</span><span>Mã hàng</span><span>Khách / PO</span><span>Số lượng / tiến độ</span><span>Trạng thái</span><span>Thao tác</span>
        </div>
        <div id="orderList" class="weave-list"></div>
        <div class="weave-pagination">
            <span id="pageLabel">Trang 1 / 1</span>
            <div class="d-flex gap-2">
                <button id="previousPage" class="weave-btn weave-icon-btn" type="button" aria-label="Trang trước" title="Trang trước"><i data-lucide="chevron-left"></i></button>
                <button id="nextPage" class="weave-btn weave-icon-btn" type="button" aria-label="Trang sau" title="Trang sau"><i data-lucide="chevron-right"></i></button>
            </div>
        </div>
    </section>
</main>

<div id="sendDialog" class="weave-dialog" role="dialog" aria-modal="true" aria-labelledby="sendDialogTitle" hidden>
    <div class="weave-dialog__panel">
        <div class="weave-dialog__body">
            <h2 id="sendDialogTitle">Gửi lệnh xuống sản xuất?</h2>
            <p id="sendDialogText"></p>
        </div>
        <div class="weave-dialog__actions">
            <button id="cancelSend" class="weave-btn" type="button">Hủy</button>
            <button id="confirmSend" class="weave-btn weave-btn--primary" type="button"><i data-lucide="send"></i>Gửi sản xuất</button>
        </div>
    </div>
</div>
<div id="quickToast" class="weave-toast" role="status" aria-live="polite"></div>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
(() => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const currentYear = {{ (int) now('Asia/Ho_Chi_Minh')->format('Y') }};
    const createOrderUrl = @json(route('weaving.orders.create'));
    const state = { page: 1, totalPages: 1, status: '', keyword: '', timer: null, pendingSend: null, request: null };
    const list = document.getElementById('orderList');
    const keyword = document.getElementById('quickKeyword');

    const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    const num = value => Number(value || 0).toLocaleString('vi-VN', { maximumFractionDigits: 3 });
    const date = value => {
        const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
        return match ? `${match[3]}/${match[2]}/${match[1]}` : '-';
    };
    const statusLabels = { waiting: 'Chờ sản xuất', producing: 'Đang sản xuất', partial: 'Nhập một phần', completed: 'Hoàn thành' };

    function toast(message, error = false) {
        const element = document.getElementById('quickToast');
        element.textContent = message;
        element.classList.toggle('is-error', error);
        element.classList.add('is-visible');
        clearTimeout(element.timer);
        element.timer = setTimeout(() => element.classList.remove('is-visible'), 3200);
    }

    function renderLoading() {
        list.innerHTML = Array.from({ length: 5 }, () => '<div class="weave-skeleton"></div>').join('');
    }

    function renderSummary(summary = {}) {
        document.getElementById('summaryTotal').textContent = num(summary.total);
        document.getElementById('summaryWaiting').textContent = num(summary.waiting);
        document.getElementById('summaryProducing').textContent = num(Number(summary.producing || 0) + Number(summary.partial || 0));
        document.getElementById('summaryCompleted').textContent = num(summary.completed);
    }

    function renderRows(rows) {
        if (!rows.length) {
            list.innerHTML = '<div class="weave-empty"><div><i data-lucide="search-x"></i><strong class="d-block">Không tìm thấy lệnh dệt</strong><span class="weave-sub">Thử số lệnh, mã hàng, PO hoặc design khác.</span></div></div>';
            window.lucide?.createIcons();
            return;
        }
        list.innerHTML = rows.map(row => {
            const progress = Math.max(0, Math.min(Number(row.progress || 0), 100));
            return `<article class="weave-order">
                <div><span class="weave-code">${esc(row.order_code)}</span><span class="weave-sub">${date(row.order_date)} · Hạn ${date(row.due_date)}</span></div>
                <div class="weave-product"><strong>${esc(row.item_code || '-')}</strong><span class="weave-sub">${esc(row.item_name || '-')}</span></div>
                <div><strong>${esc(row.customer || '-')}</strong><span class="weave-sub">PO ${esc(row.po_number || '-')}</span></div>
                <div><span class="weave-qty">Đã nhập ${num(row.received_quantity)} ${esc(row.unit || '')}</span><div class="weave-progress" aria-label="Tiến độ ${progress}%"><span style="width:${progress}%"></span></div><span class="weave-sub">Kế hoạch ${num(row.order_quantity)} · Còn ${num(row.remaining_quantity)} · ${num(progress)}%</span></div>
                <div><span class="weave-status weave-status--${esc(row.workflow_status)}">${esc(statusLabels[row.workflow_status] || row.workflow_status)}</span>${row.is_overdue ? '<span class="weave-overdue">TRỄ HẠN</span>' : ''}</div>
                <div class="weave-row-actions">
                    ${row.workflow_status === 'waiting' ? `<button class="weave-btn weave-btn--primary" type="button" data-send-id="${esc(row.id)}" data-send-code="${esc(row.order_code)}" aria-label="Gửi lệnh ${esc(row.order_code)} xuống sản xuất" title="Gửi sản xuất"><i data-lucide="send"></i>Gửi SX</button>` : ''}
                    <a class="weave-btn" href="${createOrderUrl}?order=${encodeURIComponent(row.order_code)}" aria-label="Mở lệnh ${esc(row.order_code)}" title="Mở lệnh"><i data-lucide="pencil"></i>Mở</a>
                </div>
            </article>`;
        }).join('');
        window.lucide?.createIcons();
    }

    async function loadData() {
        if (state.request) state.request.abort();
        state.request = new AbortController();
        renderLoading();
        const params = new URLSearchParams({ compact: '1', year: currentYear, per_page: '25', page: state.page });
        if (state.keyword) params.set('keyword', state.keyword);
        if (state.status) params.set('status', state.status);
        try {
            const response = await fetch(`/api/lenh-det/designer-dashboard?${params}`, {
                headers: { Accept: 'application/json' }, signal: state.request.signal
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.message || 'Không tải được lệnh dệt.');
            renderSummary(result.summary);
            renderRows(result.data || []);
            const pagination = result.pagination || {};
            state.totalPages = Math.max(Number(pagination.last_page || 1), 1);
            document.getElementById('pageLabel').textContent = `Trang ${state.page} / ${state.totalPages}`;
            document.getElementById('resultCount').textContent = `${num(pagination.total || 0)} kết quả`;
            document.getElementById('previousPage').disabled = state.page <= 1;
            document.getElementById('nextPage').disabled = state.page >= state.totalPages;
        } catch (error) {
            if (error.name === 'AbortError') return;
            list.innerHTML = `<div class="weave-empty text-danger">${esc(error.message)}</div>`;
            toast(error.message, true);
        } finally {
            state.request = null;
        }
    }

    function queueSearch() {
        state.keyword = keyword.value.trim();
        state.page = 1;
        document.getElementById('clearKeyword').hidden = !state.keyword;
        clearTimeout(state.timer);
        state.timer = setTimeout(loadData, 260);
    }

    function openSendDialog(id, code) {
        state.pendingSend = { id, code };
        document.getElementById('sendDialogText').textContent = `Lệnh ${code} sẽ chuyển sang trạng thái Đang sản xuất.`;
        document.getElementById('sendDialog').hidden = false;
        document.getElementById('confirmSend').focus();
    }

    function closeSendDialog() {
        state.pendingSend = null;
        document.getElementById('sendDialog').hidden = true;
    }

    async function sendToProduction() {
        if (!state.pendingSend) return;
        const target = state.pendingSend;
        const button = document.getElementById('confirmSend');
        button.disabled = true;
        try {
            const response = await fetch(`/api/lenh-det/orders/${encodeURIComponent(target.id)}/gui-san-xuat`, {
                method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken }
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.message || 'Không gửi được lệnh.');
            closeSendDialog();
            toast(result.message || `Đã gửi lệnh ${target.code}.`);
            loadData();
        } catch (error) {
            toast(error.message, true);
        } finally {
            button.disabled = false;
        }
    }

    keyword.addEventListener('input', queueSearch);
    keyword.addEventListener('keydown', event => {
        if (event.key === 'Enter') { clearTimeout(state.timer); state.page = 1; loadData(); }
    });
    document.getElementById('clearKeyword').addEventListener('click', () => {
        keyword.value = ''; queueSearch(); keyword.focus();
    });
    document.getElementById('reloadButton').addEventListener('click', loadData);
    document.querySelector('.weave-segments').addEventListener('click', event => {
        const button = event.target.closest('[data-status]');
        if (!button) return;
        document.querySelectorAll('[data-status]').forEach(item => item.classList.toggle('is-active', item === button));
        state.status = button.dataset.status;
        state.page = 1;
        loadData();
    });
    document.getElementById('previousPage').addEventListener('click', () => { if (state.page > 1) { state.page--; loadData(); } });
    document.getElementById('nextPage').addEventListener('click', () => { if (state.page < state.totalPages) { state.page++; loadData(); } });
    list.addEventListener('click', event => {
        const button = event.target.closest('[data-send-id]');
        if (button) openSendDialog(button.dataset.sendId, button.dataset.sendCode);
    });
    document.getElementById('cancelSend').addEventListener('click', closeSendDialog);
    document.getElementById('confirmSend').addEventListener('click', sendToProduction);
    document.getElementById('sendDialog').addEventListener('click', event => { if (event.target.id === 'sendDialog') closeSendDialog(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') closeSendDialog(); });

    window.lucide?.createIcons();
    loadData();
})();
</script>
</body>
</html>
