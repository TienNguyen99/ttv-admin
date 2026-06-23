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
        .tracking-filters { display:grid; grid-template-columns:minmax(260px,1fr) 190px auto; gap:10px; align-items:end; }
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
        @media (max-width:900px) {
            .flow-board { grid-template-columns:1fr; }
            .flow-stage { border-radius:0 !important; }
            .flow-arrow { min-height:38px; border-left:1px solid var(--wms-line); border-right:1px solid var(--wms-line); }
            .flow-arrow svg { transform:rotate(90deg); }
            .tracking-filters { grid-template-columns:1fr; }
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
            <a class="wms-btn wms-btn--primary" href="{{ url('/client/kiem-ton-kho?view=entry') }}"><i data-lucide="package-plus"></i> Nhập thành phẩm</a>
        </div>
    </header>

    <main class="wms-page">
        <div class="wms-heading">
            <div>
                <h1>Hàng đang ở sản xuất</h1>
                <p>Theo dõi BTP đã xuất nhưng chưa được nhập lại thành phẩm theo Lệnh SX, size và màu.</p>
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
                    <h2>Chi tiết chưa nhập kho</h2>
                    <div class="wip-sub">Sắp xếp lệnh lâu ngày lên trước.</div>
                </div>
                <div class="tracking-filters">
                    <div>
                        <label class="form-label">Tìm kiếm</label>
                        <input id="trackingKeyword" class="form-control" placeholder="Lệnh SX, PO, khách hàng, mã hàng...">
                    </div>
                    <div>
                        <label class="form-label">Tuổi phiếu</label>
                        <select id="trackingAging" class="form-select">
                            <option value="">Tất cả đang treo</option>
                            <option value="normal">0 - 3 ngày</option>
                            <option value="warning">4 - 7 ngày</option>
                            <option value="overdue">Trên 7 ngày</option>
                        </select>
                    </div>
                    <button id="clearTrackingFilter" class="wms-btn" type="button">Xóa lọc</button>
                </div>
            </div>
            <div class="wms-table-wrap">
                <table class="wms-table">
                    <thead>
                        <tr>
                            <th>Lệnh SX / Phiếu xuất</th>
                            <th>Trạng thái</th>
                            <th>Mã hàng</th>
                            <th>Size / Màu</th>
                            <th class="text-end">Đã xuất</th>
                            <th class="text-end">Đã nhập</th>
                            <th class="text-end">Còn treo</th>
                            <th>Tiến độ</th>
                            <th>Tuổi phiếu</th>
                        </tr>
                    </thead>
                    <tbody id="trackingRows"><tr><td colspan="9" class="wms-loading">Đang tải dữ liệu...</td></tr></tbody>
                </table>
            </div>
        </section>
    </main>

    <script>
        const trackingNum = value => Number(value || 0).toLocaleString('vi-VN', {maximumFractionDigits: 3});
        const trackingEsc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
        let trackingTimer = null;

        function trackingStatus(row) {
            const labels = {normal: '0 - 3 ngày', warning: '4 - 7 ngày', overdue: 'Quá 7 ngày'};
            return `<span class="aging-badge aging-badge--${row.aging_status}"><i data-lucide="clock-3"></i>${labels[row.aging_status]} · ${row.age_days} ngày</span>`;
        }

        function btpFlowStatus(row) {
            if (row.btp_status === 'draft') {
                return '<span class="wip-status wip-status--draft">Chưa xuất</span>';
            }
            return '<span class="wip-status wip-status--issued">Đang SX</span>';
        }

        function loadProductionTracking() {
            const params = new URLSearchParams();
            const keyword = document.getElementById('trackingKeyword').value.trim();
            const aging = document.getElementById('trackingAging').value;
            if (keyword) params.set('keyword', keyword);
            if (aging) params.set('aging', aging);

            fetch(`/api/theo-doi-san-xuat?${params.toString()}`)
                .then(response => {
                    if (!response.ok) throw new Error('Không tải được dữ liệu đang sản xuất');
                    return response.json();
                })
                .then(result => {
                    const summary = result.summary || {};
                    document.getElementById('flowIssued').textContent = trackingNum(summary.issued_quantity);
                    document.getElementById('flowReturned').textContent = trackingNum(summary.returned_quantity);
                    document.getElementById('flowOutstanding').textContent = trackingNum(summary.outstanding_quantity);
                    document.getElementById('flowOrders').textContent = `${trackingNum(summary.order_count)} lệnh sản xuất chưa hoàn tất`;
                    document.getElementById('trackingLines').textContent = trackingNum(summary.line_count);
                    document.getElementById('trackingOverdue').textContent = trackingNum(summary.overdue_count);
                    document.getElementById('trackingOrders').textContent = trackingNum(summary.order_count);
                    document.getElementById('trackingOutstanding').textContent = trackingNum(summary.outstanding_quantity);

                    document.getElementById('trackingRows').innerHTML = (result.data || []).map(row => `
                        <tr>
                            <td>
                                <div class="wip-order">${trackingEsc(row.production_order)}</div>
                                <div class="wip-sub">${trackingEsc((row.issue_codes || []).join(', '))}</div>
                                <div class="wip-sub">${trackingEsc([row.customer, row.purchase_order].filter(Boolean).join(' · '))}</div>
                            </td>
                            <td>${btpFlowStatus(row)}</td>
                            <td>
                                <div class="wip-main">${trackingEsc(row.internal_item_code || row.ma_hh || '-')}</div>
                                <div class="wip-sub">${trackingEsc(row.ma_hh || '')}</div>
                            </td>
                            <td>${trackingEsc([row.size, row.color].filter(Boolean).join(' / ') || '-')}</td>
                            <td class="wms-number">${trackingNum(row.issued_quantity)}</td>
                            <td class="wms-number">${trackingNum(row.returned_quantity)}</td>
                            <td class="wms-number"><strong>${trackingNum(row.outstanding_quantity)}</strong></td>
                            <td>
                                <div class="progress-track" title="${trackingEsc(row.progress_percent)}%">
                                    <div class="progress-fill" style="width:${Math.min(100, Number(row.progress_percent || 0))}%"></div>
                                </div>
                                <div class="wip-sub">${trackingNum(row.progress_percent)}% đã nhập</div>
                            </td>
                            <td>${trackingStatus(row)}</td>
                        </tr>
                    `).join('') || '<tr><td colspan="9" class="tracking-empty">Không có hàng đang treo theo bộ lọc hiện tại.</td></tr>';

                    if (window.lucide) lucide.createIcons();
                })
                .catch(error => {
                    document.getElementById('trackingRows').innerHTML = `<tr><td colspan="9" class="tracking-empty text-danger">${trackingEsc(error.message)}</td></tr>`;
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
        document.getElementById('trackingAging').addEventListener('change', loadProductionTracking);
        document.getElementById('reloadTrackingBtn').addEventListener('click', loadProductionTracking);
        document.getElementById('clearTrackingFilter').addEventListener('click', () => {
            document.getElementById('trackingKeyword').value = '';
            document.getElementById('topTrackingKeyword').value = '';
            document.getElementById('trackingAging').value = '';
            loadProductionTracking();
        });

        loadProductionTracking();
        if (window.lucide) lucide.createIcons();
    </script>
</body>
</html>
