<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Quản lý đơn hàng A/B</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/warehouse-wms.css') }}" rel="stylesheet">
    <style>
        .order-tabs { display:flex; gap:4px; margin-bottom:18px; border-bottom:1px solid var(--wms-line); }
        .order-tab { min-width:120px; padding:10px 16px; border:0; border-bottom:3px solid transparent; background:transparent; color:#64748b; font-weight:800; }
        .order-tab.is-active { border-bottom-color:var(--wms-blue); color:var(--wms-blue); }
        .order-import { display:none; margin-bottom:18px; }
        .order-import.is-open { display:block; }
        .order-file-drop { display:flex; align-items:center; gap:14px; padding:16px; border:1px dashed #8fa4bd; background:#f8fbff; }
        .order-file-drop input { min-width:0; }
        .order-progress { height:6px; margin-top:10px; overflow:hidden; border-radius:3px; background:#e2e8f0; }
        .order-progress > span { display:block; height:100%; background:var(--wms-blue); }
        .order-table { min-width:1700px; }
        .order-table td { white-space:nowrap; }
        .order-table .wrap { min-width:160px; max-width:260px; white-space:normal; }
        .order-status-dot { width:7px; height:7px; border-radius:50%; background:currentColor; }
        .order-import-result { color:#475569; font-size:13px; }
        @media (max-width:760px) { .order-file-drop { align-items:stretch; flex-direction:column; } }
    </style>
</head>
<body>
    @include('layouts.partials.sidebar')

    <header class="wms-topbar">
        <h1 class="wms-topbar__title">WMS May Mặc</h1>
        <div class="wms-global-search">
            <i data-lucide="search"></i>
            <input id="topOrderKeyword" aria-label="Tìm đơn hàng" placeholder="Tìm mã hàng, đơn hàng, số phiếu, màu hoặc size...">
        </div>
        <div class="wms-topbar__actions">
            <button id="toggleImportBtn" type="button" class="wms-btn wms-btn--primary"><i data-lucide="file-up"></i> Nhập Excel</button>
        </div>
    </header>

    <main class="wms-page">
        <div class="wms-heading">
            <div>
                <h1>Quản lý đơn hàng A/B</h1>
                <p>Đồng bộ hai sheet từ file Excel. Nhập lại file để cập nhật tiến độ mới nhất.</p>
            </div>
            <div class="wms-actions">
                <button id="reloadOrdersBtn" type="button" class="wms-btn"><i data-lucide="refresh-cw"></i> Tải lại</button>
            </div>
        </div>

        <section id="orderImportPanel" class="wms-panel order-import">
            <div class="wms-panel__header">
                <div>
                    <h2>Đồng bộ file Excel</h2>
                    <div class="text-secondary small mt-1">Hai sheet đầu tiên được nhận là Sheet A và Sheet B. File mới sẽ cập nhật dòng cũ và lưu trữ dòng không còn xuất hiện.</div>
                </div>
            </div>
            <div class="wms-panel__body">
                <form id="orderImportForm" class="order-file-drop">
                    <i data-lucide="sheet" style="width:28px;height:28px;color:#1769aa"></i>
                    <input id="orderExcelFile" name="file" type="file" class="form-control" accept=".xlsx,.xls,.csv" required>
                    <button id="importOrdersBtn" type="submit" class="wms-btn wms-btn--primary"><i data-lucide="upload"></i> Đồng bộ</button>
                    <div id="orderImportResult" class="order-import-result"></div>
                </form>
            </div>
        </section>

        <section class="wms-kpis">
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="rows-3"></i></div><div><div class="wms-kpi__label">Dòng đơn hàng</div><div id="orderRowCount" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Theo sheet và bộ lọc</div></div></article>
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="shopping-cart"></i></div><div><div class="wms-kpi__label">Số lượng đặt</div><div id="orderQuantity" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Tổng quantity order</div></div></article>
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="package-check"></i></div><div><div class="wms-kpi__label">Đã nhận</div><div id="receivedQuantity" class="wms-kpi__value">0</div><div id="receivedProgress" class="order-progress"><span style="width:0"></span></div></div></article>
            <article class="wms-kpi wms-kpi--danger"><div class="wms-kpi__icon"><i data-lucide="calendar-clock"></i></div><div><div class="wms-kpi__label">Trễ giao còn thiếu</div><div id="lateCount" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Đã qua ngày giao</div></div></article>
        </section>

        <nav class="order-tabs" aria-label="Chọn sheet">
            <button type="button" class="order-tab is-active" data-sheet="A">Sheet A</button>
            <button type="button" class="order-tab" data-sheet="B">Sheet B</button>
        </nav>

        <section class="wms-filterbar" style="grid-template-columns:minmax(220px,1.3fr) minmax(150px,.7fr) minmax(150px,.7fr) minmax(160px,.7fr) auto">
            <div><label for="orderKeyword">Tìm đơn hàng</label><input id="orderKeyword" class="form-control" placeholder="Mã hàng, PS#/SUB, số phiếu..."></div>
            <div><label for="orderFromDate">Giao từ ngày</label><input id="orderFromDate" type="date" class="form-control"></div>
            <div><label for="orderToDate">Đến ngày</label><input id="orderToDate" type="date" class="form-control"></div>
            <div><label for="orderStatus">Trạng thái</label><select id="orderStatus" class="form-select"><option value="">Tất cả</option><option value="pending">Chưa nhận</option><option value="partial">Đang nhận</option><option value="completed">Hoàn thành</option><option value="late">Trễ giao</option></select></div>
            <div><button id="clearOrderFilter" type="button" class="wms-btn"><i data-lucide="filter-x"></i> Xóa lọc</button></div>
        </section>

        <section class="wms-panel">
            <div class="wms-panel__header">
                <h2 id="orderTableTitle">Danh sách Sheet A</h2>
                <span id="orderResultLabel" class="text-secondary small">Đang tải...</span>
            </div>
            <div class="wms-table-wrap">
                <table class="wms-table order-table">
                    <thead>
                        <tr>
                            <th>STT</th><th>Export date</th><th>Mã hàng</th><th>PS# / SUB</th><th>Size</th>
                            <th>Fabric color</th><th>Logo color</th><th>Date out panel</th><th>Số phiếu</th>
                            <th class="text-end">SL đặt</th><th class="text-end">Quantity</th><th class="text-end">Front</th><th class="text-end">Back</th>
                            <th>Delivery date</th><th class="text-end">Đạt trước</th><th class="text-end">Lỗi trước</th>
                            <th class="text-end">Đạt sau</th><th class="text-end">Lỗi sau</th><th class="text-end">Còn lại</th><th>Trạng thái</th><th>Ghi chú</th>
                        </tr>
                    </thead>
                    <tbody id="orderRows"><tr><td colspan="21" class="wms-loading">Chưa có dữ liệu. Hãy nhập file Excel.</td></tr></tbody>
                </table>
            </div>
        </section>
    </main>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const orderRows = document.getElementById('orderRows');
        const keywordEl = document.getElementById('orderKeyword');
        const topKeywordEl = document.getElementById('topOrderKeyword');
        let activeSheet = 'A';
        let searchTimer = null;

        const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
        const num = value => Number(value || 0).toLocaleString('vi-VN', {maximumFractionDigits:3});
        const date = value => value ? new Date(value + 'T00:00:00').toLocaleDateString('vi-VN') : '-';

        function jsonOrError(response, fallback) {
            if (response.ok) return response.json();
            return response.json().then(result => { throw new Error(result.message || fallback); });
        }

        function statusBadge(status) {
            const labels = {pending:'Chưa nhận', partial:'Đang nhận', completed:'Hoàn thành', late:'Trễ giao'};
            const css = status === 'completed' ? '' : status === 'late' ? ' wms-badge--danger' : ' wms-badge--warning';
            return `<span class="wms-badge${css}"><span class="order-status-dot"></span>${labels[status] || status}</span>`;
        }

        function loadOrders() {
            orderRows.innerHTML = '<tr><td colspan="21" class="wms-loading">Đang tải dữ liệu...</td></tr>';
            const params = new URLSearchParams({sheet:activeSheet, limit:1000});
            if (keywordEl.value.trim()) params.set('keyword', keywordEl.value.trim());
            if (document.getElementById('orderFromDate').value) params.set('from_date', document.getElementById('orderFromDate').value);
            if (document.getElementById('orderToDate').value) params.set('to_date', document.getElementById('orderToDate').value);
            if (document.getElementById('orderStatus').value) params.set('status', document.getElementById('orderStatus').value);

            fetch('/api/don-hang-noi-bo?' + params.toString())
                .then(response => jsonOrError(response, 'Không tải được danh sách đơn hàng'))
                .then(result => {
                    const summary = result.summary || {};
                    document.getElementById('orderRowCount').textContent = num(summary.row_count);
                    document.getElementById('orderQuantity').textContent = num(summary.order_quantity);
                    document.getElementById('receivedQuantity').textContent = num(summary.received_quantity);
                    document.getElementById('lateCount').textContent = num(summary.late_count);
                    document.getElementById('orderResultLabel').textContent = `${num((result.data || []).length)} / ${num(summary.row_count)} dòng`;
                    const progress = Number(summary.order_quantity) > 0 ? Math.min(100, Number(summary.received_quantity) / Number(summary.order_quantity) * 100) : 0;
                    document.querySelector('#receivedProgress span').style.width = progress.toFixed(1) + '%';

                    orderRows.innerHTML = (result.data || []).map(row => `<tr>
                        <td>${esc(row.sequence_no || row.source_row || '-')}</td>
                        <td>${date(row.export_date)}</td>
                        <td class="wms-code">${esc(row.item_code || '-')}</td>
                        <td class="wms-code">${esc(row.order_number || '-')}</td>
                        <td>${esc(row.size || '-')}</td>
                        <td class="wrap">${esc(row.fabric_color || '-')}</td>
                        <td class="wrap">${esc(row.logo_color || '-')}</td>
                        <td>${date(row.panel_out_date)}</td>
                        <td>${esc(row.voucher_number || '-')}</td>
                        <td class="wms-number">${num(row.order_quantity)}</td>
                        <td class="wms-number">${num(row.quantity)}</td>
                        <td class="wms-number">${num(row.quantity_front)}</td>
                        <td class="wms-number">${num(row.quantity_back)}</td>
                        <td>${date(row.delivery_date)}</td>
                        <td class="wms-number">${num(row.front_pass)}</td>
                        <td class="wms-number ${Number(row.front_fail) ? 'text-danger' : ''}">${num(row.front_fail)}</td>
                        <td class="wms-number">${num(row.back_pass)}</td>
                        <td class="wms-number ${Number(row.back_fail) ? 'text-danger' : ''}">${num(row.back_fail)}</td>
                        <td class="wms-number ${Number(row.remaining_quantity) ? 'text-danger' : ''}">${num(row.remaining_quantity)}</td>
                        <td>${statusBadge(row.status)}</td>
                        <td class="wrap">${esc(row.note || '-')}</td>
                    </tr>`).join('') || '<tr><td colspan="21" class="wms-empty">Sheet này chưa có dữ liệu phù hợp.</td></tr>';
                })
                .catch(error => {
                    orderRows.innerHTML = `<tr><td colspan="21" class="wms-empty text-danger">${esc(error.message)}</td></tr>`;
                });
        }

        document.querySelectorAll('.order-tab').forEach(button => button.addEventListener('click', () => {
            activeSheet = button.dataset.sheet;
            document.querySelectorAll('.order-tab').forEach(tab => tab.classList.toggle('is-active', tab === button));
            document.getElementById('orderTableTitle').textContent = `Danh sách Sheet ${activeSheet}`;
            loadOrders();
        }));

        document.getElementById('toggleImportBtn').addEventListener('click', () => {
            document.getElementById('orderImportPanel').classList.toggle('is-open');
        });

        document.getElementById('orderImportForm').addEventListener('submit', event => {
            event.preventDefault();
            const file = document.getElementById('orderExcelFile').files[0];
            if (!file) return;
            const button = document.getElementById('importOrdersBtn');
            const resultEl = document.getElementById('orderImportResult');
            const body = new FormData();
            body.append('file', file);
            button.disabled = true;
            resultEl.textContent = 'Đang đọc và đồng bộ file...';

            fetch('/api/don-hang-noi-bo/import', {
                method:'POST',
                headers:{'Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body
            }).then(response => jsonOrError(response, 'Không nhập được file Excel'))
              .then(result => {
                  const data = result.data || {};
                  resultEl.textContent = `Thêm ${num(data.created)}, cập nhật ${num(data.updated)}, lưu trữ ${num(data.archived)} dòng.`;
                  loadOrders();
              })
              .catch(error => resultEl.textContent = error.message)
              .finally(() => button.disabled = false);
        });

        function queueSearch(source) {
            if (source === topKeywordEl) keywordEl.value = topKeywordEl.value;
            if (source === keywordEl) topKeywordEl.value = keywordEl.value;
            clearTimeout(searchTimer);
            searchTimer = setTimeout(loadOrders, 250);
        }

        keywordEl.addEventListener('input', () => queueSearch(keywordEl));
        topKeywordEl.addEventListener('input', () => queueSearch(topKeywordEl));
        ['orderFromDate','orderToDate','orderStatus'].forEach(id => document.getElementById(id).addEventListener('change', loadOrders));
        document.getElementById('reloadOrdersBtn').addEventListener('click', loadOrders);
        document.getElementById('clearOrderFilter').addEventListener('click', () => {
            keywordEl.value = '';
            topKeywordEl.value = '';
            document.getElementById('orderFromDate').value = '';
            document.getElementById('orderToDate').value = '';
            document.getElementById('orderStatus').value = '';
            loadOrders();
        });

        loadOrders();
    </script>
</body>
</html>
