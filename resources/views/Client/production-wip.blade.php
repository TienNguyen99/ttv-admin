<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Theo dõi hàng đang sản xuất</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/warehouse-wms.css') }}?v={{ filemtime(public_path('css/warehouse-wms.css')) }}" rel="stylesheet">
    <style>
        .flow-board { display:grid; grid-template-columns:1fr 52px 1fr 52px 1fr; align-items:stretch; margin-bottom:18px; }
        .flow-stage { min-height:148px; padding:18px; border:1px solid var(--wms-line); background:#fff; }
        .flow-stage:first-child { border-radius:7px 0 0 7px; }
        .flow-stage:last-child { border-radius:0 7px 7px 0; }
        .flow-stage--active { border-color:#f0b36e; background:#fff8ed; }
        .flow-stage__head { display:flex; align-items:center; gap:10px; color:#475569; font-size:13px; font-weight:800; text-transform:uppercase; }
        .flow-stage__head svg { width:20px; height:20px; }
        .flow-stage__value { margin-top:16px; color:var(--wms-ink); font-size:34px; font-weight:850; line-height:1; }
        .flow-stage__meta { margin-top:8px; color:var(--wms-muted); font-size:12px; }
        .flow-arrow { display:grid; place-items:center; border-top:1px solid var(--wms-line); border-bottom:1px solid var(--wms-line); background:#f8fafc; color:var(--wms-blue); }
        .flow-arrow svg { width:25px; height:25px; }
        .tracking-filters { display:grid; grid-template-columns:minmax(240px,1fr) 180px 180px auto; gap:10px; align-items:end; }
        .wip-order { color:#075aa5; font-weight:800; }
        .wip-main { font-weight:700; }
        .wip-sub { margin-top:3px; color:var(--wms-muted); font-size:11px; }
        .progress-track { width:150px; height:8px; border-radius:4px; overflow:hidden; background:#e5e7eb; }
        .progress-fill { height:100%; background:#16834b; }
        .aging-badge { display:inline-flex; align-items:center; gap:5px; padding:4px 7px; border-radius:5px; font-size:11px; font-weight:800; white-space:nowrap; }
        .aging-badge--normal { background:#dcfce7; color:#166534; }
        .aging-badge--warning { background:#fef3c7; color:#92400e; }
        .aging-badge--overdue { background:#fee2e2; color:#b91c1c; }
        .wip-status { display:inline-flex; align-items:center; gap:5px; padding:4px 7px; border-radius:5px; font-size:11px; font-weight:800; white-space:nowrap; }
        .wip-status--draft { background:#e0f2fe; color:#075985; }
        .wip-status--issued { background:#dcfce7; color:#166534; }
        .tracking-empty { padding:50px 20px; color:var(--wms-muted); text-align:center; }
        .wip-bulk-bar { position:fixed; left:50%; bottom:20px; z-index:1080; width:min(680px,calc(100% - 32px)); display:none; align-items:center; justify-content:space-between; gap:12px; padding:10px 12px; border:1px solid #93c5fd; border-radius:8px; background:#ffffff; box-shadow:0 16px 38px rgba(15,47,99,.2); transform:translateX(-50%); }
        .wip-bulk-bar.is-visible { display:flex; animation:bulkReveal 180ms ease-out; }
        .wip-bulk-count { color:#0f2f63; font-size:13px; font-weight:800; }
        .wip-bulk-actions { display:flex; align-items:center; gap:8px; }
        .wip-group-row td { padding:9px 12px !important; border-top:2px solid #93c5fd; background:#eaf4ff !important; color:#0f2f63; font-weight:800; animation:groupReveal 180ms ease-out both; }
        .wip-group-row--1 td { border-top-color:#a7f3d0; background:#ecfdf5 !important; }
        .wip-group-row--2 td { border-top-color:#fde68a; background:#fffbeb !important; }
        .wip-group-meta { display:inline-flex; align-items:center; gap:12px; margin-left:10px; color:#64748b; font-size:11px; font-weight:700; }
        .wip-group-item { animation:groupReveal 180ms ease-out both; }
        .wip-group-item td:first-child { border-left:4px solid #93c5fd; }
        .wip-group-item--1 td:first-child { border-left-color:#6ee7b7; }
        .wip-group-item--2 td:first-child { border-left-color:#fcd34d; }
        .wip-group-item.is-selected td { background:#dbeafe !important; }
        .wip-select { width:17px; height:17px; cursor:pointer; accent-color:#2563eb; }
        .wip-card-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(340px,1fr)); gap:14px; padding:16px; }
        .wip-card { min-width:0; overflow:hidden; border:1px solid #cbdcf2; border-radius:8px; background:#fff; box-shadow:0 5px 18px rgba(30,64,175,.07); transition:border-color .2s ease,box-shadow .2s ease; animation:groupReveal 180ms ease-out both; }
        .wip-card:hover { border-color:#7eaeef; box-shadow:0 10px 25px rgba(30,64,175,.12); }
        .wip-card.is-completed { border-color:#cbd5e1; background:#f8fafc; opacity:.82; }
        .wip-card__header { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; padding:14px; border-bottom:1px solid #dbe7f5; background:#eff6ff; }
        .wip-card.is-completed .wip-card__header { background:#f1f5f9; }
        .wip-card__identity { display:flex; align-items:flex-start; gap:10px; min-width:0; }
        .wip-card__code { color:#123f7a; font-size:17px; font-weight:850; line-height:1.2; overflow-wrap:anywhere; }
        .wip-card__meta { margin-top:5px; color:#64748b; font-size:11px; }
        .wip-card__body { padding:14px; }
        .wip-card__item { min-height:40px; color:#0f2746; font-size:13px; font-weight:750; }
        .wip-card__facts { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; margin:12px 0; }
        .wip-card__fact { padding:9px; border:1px solid #dbe7f5; border-radius:6px; background:#f8fbff; }
        .wip-card__fact span { display:block; color:#64748b; font-size:10px; font-weight:700; text-transform:uppercase; }
        .wip-card__fact strong { display:block; margin-top:3px; color:#102a4c; font-size:15px; }
        .wip-card__fact--remaining strong { color:#b45309; }
        .wip-card__footer { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-top:11px; }
        .wip-card__details { border-top:1px solid #e2e8f0; }
        .wip-card__details summary { padding:10px 14px; color:#2563eb; font-size:12px; font-weight:750; cursor:pointer; list-style:none; }
        .wip-card__details summary::-webkit-details-marker { display:none; }
        .wip-card__details summary::after { float:right; content:'+'; font-size:16px; }
        .wip-card__details[open] summary::after { content:'−'; }
        .wip-detail-row { display:grid; grid-template-columns:22px minmax(0,1fr) auto; gap:8px; align-items:center; padding:9px 14px; border-top:1px solid #edf2f7; font-size:12px; }
        .wip-detail-row.is-selected { background:#dbeafe; }
        .wip-detail-row__variant { min-width:0; color:#475569; overflow-wrap:anywhere; }
        .wip-detail-row__qty { color:#0f2746; font-weight:800; white-space:nowrap; }
        .wip-source { display:inline-flex; align-items:center; gap:5px; padding:3px 7px; border-radius:5px; background:#dbeafe; color:#1d4ed8; font-size:10px; font-weight:800; }
        .wip-source--weaving { background:#ede9fe; color:#6d28d9; }
        @keyframes groupReveal { from { opacity:0; transform:translateY(5px); } to { opacity:1; transform:translateY(0); } }
        @keyframes bulkReveal { from { opacity:0; transform:translate(-50%,8px); } to { opacity:1; transform:translate(-50%,0); } }
        @media (prefers-reduced-motion: reduce) { .wip-group-row td, .wip-group-item, .wip-bulk-bar.is-visible { animation:none; } }
        @media (max-width:900px) {
            .wms-page { padding:80px 16px 32px !important; }
            .flow-board { grid-template-columns:1fr; }
            .flow-stage { border-radius:0 !important; }
            .flow-arrow { min-height:38px; border-left:1px solid var(--wms-line); border-right:1px solid var(--wms-line); }
            .flow-arrow svg { transform:rotate(90deg); }
            .wms-panel__header { display:block; }
            .tracking-filters { width:100%; margin-top:14px; grid-template-columns:1fr; }
            .tracking-filters > div { min-width:0; }
            .tracking-filters .form-control,
            .tracking-filters .form-select { width:100%; }
            .wip-card-grid { grid-template-columns:1fr; padding:10px; }
        }
        @media (max-width:480px) {
            .wip-card__facts { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .wip-card__fact--remaining { grid-column:1 / -1; }
        }
    </style>
</head>
<body>
    @include('layouts.partials.sidebar')

    <header class="wms-topbar">
        <h1 class="wms-topbar__title">WMS May Mặc</h1>
        <div class="wms-global-search">
            <i data-lucide="search"></i>
            <input id="topTrackingKeyword" aria-label="Tìm hàng đang sản xuất" placeholder="Tìm lệnh SX, mã hàng, size hoặc màu...">
        </div>
        <div class="wms-topbar__actions">
            <a class="wms-btn" href="{{ url('/client/xuat-vat-tu-noi-bo?type=production') }}"><i data-lucide="package-minus"></i> Xuất BTP</a>
        </div>
    </header>

    <main class="wms-page">
        <div class="wms-heading">
            <div>
                <h1>Hàng đang ở sản xuất</h1>
                <p>Mặc định chỉ hiển thị các lệnh còn đang sản xuất. Lệnh hoàn thành được lưu trong bộ lọc Tất cả.</p>
            </div>
            <button id="reloadTrackingBtn" class="wms-btn" type="button"><i data-lucide="refresh-cw"></i> Tải lại</button>
        </div>

        <section class="flow-board" aria-label="Luồng hàng sản xuất">
            <article class="flow-stage">
                <div class="flow-stage__head"><i data-lucide="package-minus"></i>Đã xuất khỏi kho</div>
                <div id="flowIssued" class="flow-stage__value">0</div>
                <div class="flow-stage__meta">Tổng BTP của các lệnh còn đang treo</div>
            </article>
            <div class="flow-arrow"><i data-lucide="arrow-right"></i></div>
            <article class="flow-stage flow-stage--active">
                <div class="flow-stage__head"><i data-lucide="factory"></i>Đang ở sản xuất</div>
                <div id="flowOutstanding" class="flow-stage__value">0</div>
                <div id="flowOrders" class="flow-stage__meta">0 lệnh sản xuất chưa hoàn tất</div>
            </article>
            <div class="flow-arrow"><i data-lucide="arrow-right"></i></div>
            <article class="flow-stage">
                <div class="flow-stage__head"><i data-lucide="package-check"></i>Đã nhập lại</div>
                <div id="flowReturned" class="flow-stage__value">0</div>
                <div class="flow-stage__meta">Thành phẩm đã ghi nhận về kho</div>
            </article>
        </section>

        <section class="wms-kpis" aria-label="Cảnh báo đang sản xuất">
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="workflow"></i></div><div><div class="wms-kpi__label">Dòng đang treo</div><div id="trackingLines" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Theo size và màu</div></div></article>
            <article class="wms-kpi wms-kpi--danger"><div class="wms-kpi__icon"><i data-lucide="timer-off"></i></div><div><div class="wms-kpi__label">Quá 7 ngày</div><div id="trackingOverdue" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Cần kiểm tra với sản xuất</div></div></article>
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="clipboard-list"></i></div><div><div class="wms-kpi__label">Lệnh chưa hoàn tất</div><div id="trackingOrders" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Không tính lệnh đã nhập đủ</div></div></article>
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="boxes"></i></div><div><div class="wms-kpi__label">Số lượng còn ngoài kho</div><div id="trackingOutstanding" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Xuất trừ nhập lại</div></div></article>
        </section>

        <section class="wms-panel">
            <div class="wms-panel__header">
                <div>
                    <h2>Lệnh sản xuất</h2>
                    <div class="wip-sub">Mỗi card là một lệnh, sắp xếp lệnh lâu ngày lên trước.</div>
                </div>
                <div class="tracking-filters">
                    <div>
                        <label class="form-label">Tìm kiếm</label>
                        <input id="trackingKeyword" class="form-control" placeholder="Lệnh SX, PO, khách hàng, mã hàng...">
                    </div>
                    <div>
                        <label class="form-label">Trạng thái</label>
                        <select id="trackingStatus" class="form-select">
                            <option value="active">Đang sản xuất</option>
                            <option value="completed">Đã hoàn thành</option>
                            <option value="all">Tất cả</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Tuổi phiếu</label>
                        <select id="trackingAging" class="form-select">
                            <option value="">Tất cả thời gian</option>
                            <option value="normal">0 - 3 ngày</option>
                            <option value="warning">4 - 7 ngày</option>
                            <option value="overdue">Trên 7 ngày</option>
                        </select>
                    </div>
                    <button id="clearTrackingFilter" class="wms-btn" type="button">Xóa lọc</button>
                </div>
            </div>
            <div id="bulkCompleteBar" class="wip-bulk-bar">
                <div class="wip-bulk-count"><span id="bulkSelectedCount">0</span> dòng đã chọn</div>
                <div class="wip-bulk-actions">
                    <button id="clearBulkSelectionBtn" class="wms-btn" type="button">Bỏ chọn</button>
                    <button id="bulkCompleteBtn" class="wms-btn wms-btn--primary" type="button"><i data-lucide="package-check"></i> <span id="bulkCompleteLabel">Nhập + xuất TP</span></button>
                </div>
            </div>
            <div id="trackingCards" class="wip-card-grid"><div class="wms-loading">Đang tải dữ liệu...</div></div>
        </section>
    </main>

    <script>
        const trackingNum = value => Number(value || 0).toLocaleString('vi-VN', {maximumFractionDigits: 3});
        const trackingEsc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
        const trackingLocalDate = () => {
            const date = new Date();
            date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
            return date.toISOString().slice(0, 10);
        };
        let trackingTimer = null;
        let selectedProductionLineIds = new Set();

        function updateBulkSelection() {
            document.querySelectorAll('.row-select').forEach(input => {
                const ids = String(input.dataset.lineIds || '').split(',').map(Number).filter(Boolean);
                input.checked = ids.length > 0 && ids.every(id => selectedProductionLineIds.has(id));
                input.closest('.wip-detail-row')?.classList.toggle('is-selected', input.checked);
            });
            document.querySelectorAll('.group-select').forEach(input => {
                const ids = String(input.dataset.lineIds || '').split(',').map(Number).filter(Boolean);
                input.checked = ids.length > 0 && ids.every(id => selectedProductionLineIds.has(id));
                input.indeterminate = ids.some(id => selectedProductionLineIds.has(id)) && !input.checked;
            });
            document.getElementById('bulkSelectedCount').textContent = selectedProductionLineIds.size;
            document.getElementById('bulkCompleteLabel').textContent = `Nhập + xuất TP (${selectedProductionLineIds.size} dòng)`;
            document.getElementById('bulkCompleteBar').classList.toggle('is-visible', selectedProductionLineIds.size > 0);
            if (window.lucide) lucide.createIcons();
        }

        function trackingStatus(row) {
            const labels = {normal: '0 - 3 ngày', warning: '4 - 7 ngày', overdue: 'Quá 7 ngày'};
            return `<span class="aging-badge aging-badge--${row.aging_status}"><i data-lucide="clock-3"></i>${labels[row.aging_status]} · ${row.age_days} ngày</span>`;
        }

        function btpFlowStatus(row) {
            if (row.workflow_status === 'completed' || row.btp_status === 'completed') {
                return '<span class="wip-status wip-status--issued">Đã SX xong</span>';
            }
            if (row.btp_status === 'draft') {
                return '<span class="wip-status wip-status--draft">Chưa xuất</span>';
            }
            return '<span class="wip-status wip-status--issued">Đang SX</span>';
        }

        function loadProductionTracking() {
            const params = new URLSearchParams();
            const keyword = document.getElementById('trackingKeyword').value.trim();
            const aging = document.getElementById('trackingAging').value;
            const status = document.getElementById('trackingStatus').value;
            if (keyword) params.set('keyword', keyword);
            if (aging) params.set('aging', aging);
            params.set('status', status);

            fetch(`/api/theo-doi-san-xuat?${params.toString()}`)
                .then(response => {
                    if (!response.ok) throw new Error('Không tải được dữ liệu đang sản xuất');
                    return response.json();
                })
                .then(result => {
                    selectedProductionLineIds.clear();
                    const summary = result.summary || {};
                    document.getElementById('flowIssued').textContent = trackingNum(summary.issued_quantity);
                    document.getElementById('flowReturned').textContent = trackingNum(summary.returned_quantity);
                    document.getElementById('flowOutstanding').textContent = trackingNum(summary.outstanding_quantity);
                    document.getElementById('flowOrders').textContent = `${trackingNum(summary.active_order_count)} lệnh sản xuất chưa hoàn tất`;
                    document.getElementById('trackingLines').textContent = trackingNum(summary.line_count);
                    document.getElementById('trackingOverdue').textContent = trackingNum(summary.overdue_count);
                    document.getElementById('trackingOrders').textContent = trackingNum(summary.active_order_count);
                    document.getElementById('trackingOutstanding').textContent = trackingNum(summary.outstanding_quantity);

                    const groups = new Map();
                    (result.data || []).forEach(row => {
                        const groupKey = `${row.source_type || 'btp'}|${row.production_order}`;
                        if (!groups.has(groupKey)) groups.set(groupKey, []);
                        groups.get(groupKey).push(row);
                    });
                    let cardsHtml = '';
                    Array.from(groups.entries()).forEach(([groupKey, groupRows]) => {
                        const first = groupRows[0];
                        const groupCode = first.production_order;
                        const groupLineIds = groupRows.flatMap(row => row.issue_line_ids || []).map(Number).filter(Boolean);
                        const issued = groupRows.reduce((sum, row) => sum + Number(row.issued_quantity || 0), 0);
                        const returned = groupRows.reduce((sum, row) => sum + Number(row.returned_quantity || 0), 0);
                        const outstanding = groupRows.reduce((sum, row) => sum + Number(row.outstanding_quantity || 0), 0);
                        const progress = issued > 0 ? Math.min(100, (returned / issued) * 100) : 0;
                        const completed = groupRows.every(row => row.workflow_status === 'completed');
                        const issueCodes = Array.from(new Set(groupRows.flatMap(row => row.issue_codes || []))).join(', ');
                        const itemLabels = Array.from(new Set(groupRows.map(row => {
                            const code = row.internal_item_code || row.ma_hh || '-';
                            return row.item_name ? `${code} · ${row.item_name}` : code;
                        })));
                        const sourceClass = first.source_type === 'weaving' ? ' wip-source--weaving' : '';
                        const details = groupRows.map(row => {
                            const issueLineIds = (row.issue_line_ids || []).join(',');
                            const variant = [row.internal_item_code || row.ma_hh, row.size, row.color].filter(Boolean).join(' · ');
                            return `<div class="wip-detail-row">
                                <input class="wip-select row-select" type="checkbox" data-group="${trackingEsc(groupCode)}" data-line-ids="${trackingEsc(issueLineIds)}" ${issueLineIds && !completed ? '' : 'disabled'} aria-label="Chọn ${trackingEsc(variant)}">
                                <span class="wip-detail-row__variant">${trackingEsc(variant || '-')}</span>
                                <span class="wip-detail-row__qty">Còn ${trackingNum(row.outstanding_quantity)} ${trackingEsc(row.dvt || '')}</span>
                            </div>`;
                        }).join('');

                        cardsHtml += `<article class="wip-card${completed ? ' is-completed' : ''}">
                            <header class="wip-card__header">
                                <div class="wip-card__identity">
                                    <input class="wip-select group-select" type="checkbox" data-line-ids="${trackingEsc(groupLineIds.join(','))}" ${groupLineIds.length && !completed ? '' : 'disabled'} aria-label="Chọn lệnh ${trackingEsc(groupCode)}">
                                    <div>
                                        <div class="wip-card__code">${trackingEsc(groupCode)}</div>
                                        <div class="wip-card__meta">${trackingEsc([first.customer, first.purchase_order, issueCodes].filter(Boolean).join(' · ') || 'Không có thông tin phụ')}</div>
                                    </div>
                                </div>
                                ${btpFlowStatus(first)}
                            </header>
                            <div class="wip-card__body">
                                <div class="wip-card__item">${trackingEsc(itemLabels.join(', '))}</div>
                                <div class="wip-card__facts">
                                    <div class="wip-card__fact"><span>Đã xuất</span><strong>${trackingNum(issued)}</strong></div>
                                    <div class="wip-card__fact"><span>Đã nhập</span><strong>${trackingNum(returned)}</strong></div>
                                    <div class="wip-card__fact wip-card__fact--remaining"><span>Còn lại</span><strong>${trackingNum(outstanding)}</strong></div>
                                </div>
                                <div class="progress-track" title="${trackingEsc(progress.toFixed(1))}%" style="width:100%">
                                    <div class="progress-fill" style="width:${progress}%"></div>
                                </div>
                                <div class="wip-card__footer">
                                    <span class="wip-source${sourceClass}">${trackingEsc(first.source_label || 'Bán thành phẩm')}</span>
                                    ${trackingStatus(first)}
                                </div>
                            </div>
                            <details class="wip-card__details">
                                <summary>${groupRows.length} dòng chi tiết</summary>
                                ${details}
                            </details>
                        </article>`;
                    });
                    document.getElementById('trackingCards').innerHTML = cardsHtml || '<div class="tracking-empty">Không có lệnh theo bộ lọc hiện tại.</div>';

                    updateBulkSelection();
                    if (window.lucide) lucide.createIcons();
                })
                .catch(error => {
                    document.getElementById('trackingCards').innerHTML = `<div class="tracking-empty text-danger">${trackingEsc(error.message)}</div>`;
                });
        }

        document.getElementById('trackingKeyword').addEventListener('input', event => {
            document.getElementById('topTrackingKeyword').value = event.target.value;
            clearTimeout(trackingTimer);
            trackingTimer = setTimeout(loadProductionTracking, 250);
        });
        document.getElementById('topTrackingKeyword').addEventListener('input', event => {
            document.getElementById('trackingKeyword').value = event.target.value;
            clearTimeout(trackingTimer);
            trackingTimer = setTimeout(loadProductionTracking, 250);
        });
        document.getElementById('trackingStatus').addEventListener('change', loadProductionTracking);
        document.getElementById('trackingAging').addEventListener('change', loadProductionTracking);
        document.getElementById('reloadTrackingBtn').addEventListener('click', loadProductionTracking);
        document.getElementById('clearTrackingFilter').addEventListener('click', () => {
            document.getElementById('trackingKeyword').value = '';
            document.getElementById('topTrackingKeyword').value = '';
            document.getElementById('trackingStatus').value = 'active';
            document.getElementById('trackingAging').value = '';
            loadProductionTracking();
        });
        document.getElementById('trackingCards').addEventListener('change', event => {
            const input = event.target.closest('.row-select, .group-select');
            if (!input) return;
            const ids = String(input.dataset.lineIds || '').split(',').map(Number).filter(Boolean);
            ids.forEach(id => input.checked ? selectedProductionLineIds.add(id) : selectedProductionLineIds.delete(id));
            updateBulkSelection();
        });
        document.getElementById('clearBulkSelectionBtn').addEventListener('click', () => {
            selectedProductionLineIds.clear();
            updateBulkSelection();
        });
        document.getElementById('bulkCompleteBtn').addEventListener('click', () => {
            const lineIds = Array.from(selectedProductionLineIds);
            if (!lineIds.length) return;
            const groupCodes = Array.from(new Set(Array.from(document.querySelectorAll('.row-select:checked')).map(input => input.dataset.group).filter(Boolean)));
            if (!confirm(`Gom ${lineIds.length} dòng từ ${groupCodes.length} nhóm thành 1 phiếu nhập TP và 1 phiếu xuất TP?\n\nNhóm nguồn: ${groupCodes.join(', ')}`)) return;

            const button = document.getElementById('bulkCompleteBtn');
            const receiptWindow = window.open('', '_blank');
            const issueWindow = window.open('', '_blank');
            button.disabled = true;
            fetch('/api/xuat-vat-tu-noi-bo/nhap-xuat-thanh-pham-theo-dong', {
                method: 'POST',
                headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
                body: JSON.stringify({
                    checked_at: trackingLocalDate(),
                    location_code: 'CHUA-XEP',
                    line_ids: lineIds,
                    export_finished_goods: true
                })
            })
                .then(response => response.json().then(result => {
                    if (!response.ok) throw new Error(result.message || 'Không thể tạo phiếu nhập và xuất thành phẩm');
                    return result;
                }))
                .then(result => {
                    if (result.receipt_print_url && receiptWindow) receiptWindow.location.href = result.receipt_print_url;
                    else if (receiptWindow) receiptWindow.close();
                    if (result.customer_issue_print_url && issueWindow) issueWindow.location.href = result.customer_issue_print_url;
                    else if (issueWindow) issueWindow.close();
                    selectedProductionLineIds.clear();
                    loadProductionTracking();
                })
                .catch(error => {
                    if (receiptWindow) receiptWindow.close();
                    if (issueWindow) issueWindow.close();
                    alert(error.message);
                })
                .finally(() => { button.disabled = false; });
        });

        loadProductionTracking();
        if (window.lucide) lucide.createIcons();
    </script>
</body>
</html>
