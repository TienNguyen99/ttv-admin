<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Lệnh sản xuất Google Sheet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/warehouse-wms.css') }}?v={{ filemtime(public_path('css/warehouse-wms.css')) }}" rel="stylesheet">
    <style>
        .production-table { min-width: 1800px; }
        .production-table .wrap { min-width: 180px; max-width: 300px; white-space: normal; }
        .sync-note { color:#64748b; font-size:12px; }
        .status-dot { width:7px; height:7px; border-radius:50%; background:currentColor; }
    </style>
</head>
<body>
    @include('layouts.partials.sidebar')

    <header class="wms-topbar">
        <h1 class="wms-topbar__title">WMS May Mặc</h1>
        <div class="wms-global-search">
            <i data-lucide="search"></i>
            <input id="topProductionKeyword" aria-label="Tìm lệnh sản xuất" placeholder="Tìm trên tất cả cột: lệnh, size, màu, mã hàng...">
        </div>
        <div class="wms-topbar__actions">
            <button id="syncProductionBtn" class="wms-btn wms-btn--primary"><i data-lucide="refresh-cw"></i> Đồng bộ Google Sheet</button>
        </div>
    </header>

    <main class="wms-page">
        <div class="wms-heading">
            <div>
                <h1>Lệnh sản xuất</h1>
                <p>Đọc từ tab <strong>LENH_SAN_XUAT</strong> của file QUANLY-VATTU và lưu bản sao JSON vào database nội bộ.</p>
            </div>
            <div class="sync-note">
                Chế độ read-only · Không ghi ngược Google Sheet
                <div id="productionSyncResult"></div>
            </div>
        </div>

        <section class="wms-kpis">
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="clipboard-list"></i></div><div><div class="wms-kpi__label">Tổng lệnh</div><div id="productionCount" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Theo bộ lọc</div></div></article>
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="boxes"></i></div><div><div class="wms-kpi__label">Số lượng đặt</div><div id="productionQuantity" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Tổng số lượng</div></div></article>
            <article class="wms-kpi wms-kpi--danger"><div class="wms-kpi__icon"><i data-lucide="calendar-x"></i></div><div><div class="wms-kpi__label">Đã quá hẹn</div><div id="productionLate" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Theo ngày giao</div></div></article>
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="users"></i></div><div><div class="wms-kpi__label">Khách hàng</div><div id="productionCustomers" class="wms-kpi__value">0</div><div id="productionLastSync" class="wms-kpi__meta">Chưa đồng bộ</div></div></article>
        </section>

        <section class="wms-filterbar" style="grid-template-columns:minmax(240px,1.4fr) minmax(150px,.7fr) minmax(150px,.7fr) minmax(160px,.7fr) auto">
            <div><label for="productionKeyword">Tìm toàn bộ cột</label><input id="productionKeyword" class="form-control" placeholder="Lệnh SX, PO, size, màu, mô tả, vị trí, ngày..."></div>
            <div><label for="productionFromDate">Hẹn giao từ</label><input id="productionFromDate" type="date" class="form-control"></div>
            <div><label for="productionToDate">Đến ngày</label><input id="productionToDate" type="date" class="form-control"></div>
            <div><label for="productionStatus">Trạng thái</label><select id="productionStatus" class="form-select"><option value="">Tất cả</option><option value="late">Quá hẹn</option><option value="due">Sắp đến hạn</option><option value="scheduled">Đã lên lịch</option><option value="pending">Chưa có ngày</option></select></div>
            <div><button id="clearProductionFilter" class="wms-btn"><i data-lucide="filter-x"></i> Xóa lọc</button></div>
        </section>

        <section class="wms-panel">
            <div class="wms-panel__header"><h2>Danh sách lệnh sản xuất</h2><span id="productionResultLabel" class="text-secondary small">Đang tải...</span></div>
            <div class="wms-table-wrap">
                <table class="wms-table production-table">
                    <thead><tr><th>Lệnh SX</th><th>PO</th><th>Nhân viên</th><th>Khách hàng</th><th>Mã hàng</th><th>Quy cách</th><th>Mô tả/Tên nhãn</th><th>Size</th><th>Color</th><th>ĐVT</th><th class="text-end">SL đặt</th><th>Vị trí</th><th>Ngày nhận</th><th>Ngày hẹn giao</th><th>Ngày KH yêu cầu</th><th>Nơi giao</th><th>Trạng thái</th></tr></thead>
                    <tbody id="productionRows"><tr><td colspan="17" class="wms-loading">Chưa có dữ liệu. Bấm Đồng bộ Google Sheet.</td></tr></tbody>
                </table>
            </div>
        </section>
    </main>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const rowsEl = document.getElementById('productionRows');
        const keywordEl = document.getElementById('productionKeyword');
        const topKeywordEl = document.getElementById('topProductionKeyword');
        let searchTimer = null;
        const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
        const num = value => Number(value || 0).toLocaleString('vi-VN', {maximumFractionDigits:3});
        const date = value => value ? new Date(value + 'T00:00:00').toLocaleDateString('vi-VN') : '-';

        function jsonOrError(response, fallback) {
            if (response.ok) return response.json();
            return response.json().then(result => { throw new Error(result.message || fallback); });
        }

        function badge(status) {
            const labels = {late:'Quá hẹn', due:'Sắp đến hạn', scheduled:'Đã lên lịch', pending:'Chưa có ngày'};
            const css = status === 'late' ? ' wms-badge--danger' : status === 'due' ? ' wms-badge--warning' : '';
            return `<span class="wms-badge${css}"><span class="status-dot"></span>${labels[status] || status}</span>`;
        }

        function loadProductionOrders() {
            const params = new URLSearchParams({limit:1500});
            if (keywordEl.value.trim()) params.set('keyword', keywordEl.value.trim());
            if (document.getElementById('productionFromDate').value) params.set('from_date', document.getElementById('productionFromDate').value);
            if (document.getElementById('productionToDate').value) params.set('to_date', document.getElementById('productionToDate').value);
            if (document.getElementById('productionStatus').value) params.set('status', document.getElementById('productionStatus').value);
            rowsEl.innerHTML = '<tr><td colspan="17" class="wms-loading">Đang tải dữ liệu...</td></tr>';

            fetch('/api/lenh-san-xuat-sheet?' + params.toString())
                .then(response => jsonOrError(response, 'Không tải được lệnh sản xuất'))
                .then(result => {
                    const summary = result.summary || {};
                    document.getElementById('productionCount').textContent = num(summary.order_count);
                    document.getElementById('productionQuantity').textContent = num(summary.total_quantity);
                    document.getElementById('productionLate').textContent = num(summary.late_count);
                    document.getElementById('productionCustomers').textContent = num(summary.customer_count);
                    document.getElementById('productionLastSync').textContent = summary.last_synced_at ? 'Cập nhật ' + new Date(summary.last_synced_at).toLocaleString('vi-VN') : 'Chưa đồng bộ';
                    document.getElementById('productionResultLabel').textContent = `${num((result.data || []).length)} / ${num(summary.order_count)} lệnh`;
                    rowsEl.innerHTML = (result.data || []).map(row => `<tr>
                        <td class="wms-code">${esc(row.production_order)}</td>
                        <td class="wrap">${esc(row.purchase_order || '-')}</td>
                        <td>${esc(row.tracking_staff || '-')}</td>
                        <td>${esc(row.customer || '-')}</td>
                        <td class="wms-code">${esc(row.item_code || '-')}</td>
                        <td class="wrap">${esc(row.specification || '-')}</td>
                        <td class="wrap">${esc(row.description || '-')}</td>
                        <td class="wrap">${esc(row.size || '-')}</td>
                        <td class="wrap">${esc(row.color || '-')}</td>
                        <td>${esc(row.unit || '-')}</td>
                        <td class="wms-number">${num(row.order_quantity)}</td>
                        <td>${esc(row.location || '-')}</td>
                        <td>${date(row.received_date)}</td>
                        <td>${date(row.promised_date)}</td>
                        <td>${date(row.customer_requested_date)}</td>
                        <td class="wrap">${esc(row.delivery_place || '-')}</td>
                        <td>${badge(row.status)}</td>
                    </tr>`).join('') || '<tr><td colspan="17" class="wms-empty">Không có lệnh phù hợp.</td></tr>';
                })
                .catch(error => rowsEl.innerHTML = `<tr><td colspan="17" class="wms-empty text-danger">${esc(error.message)}</td></tr>`);
        }

        document.getElementById('syncProductionBtn').addEventListener('click', () => {
            const button = document.getElementById('syncProductionBtn');
            const resultEl = document.getElementById('productionSyncResult');
            button.disabled = true;
            resultEl.textContent = 'Đang đồng bộ...';
            fetch('/api/lenh-san-xuat-sheet/dong-bo', {
                method:'POST',
                headers:{'Accept':'application/json','X-CSRF-TOKEN':csrfToken}
            }).then(response => jsonOrError(response, 'Không đồng bộ được Google Sheet'))
              .then(result => {
                  const data = result.data || {};
                  resultEl.textContent = `Thêm ${num(data.created)}, cập nhật ${num(data.updated)}, đang dùng ${num(data.active)} lệnh.`;
                  loadProductionOrders();
              })
              .catch(error => resultEl.textContent = error.message)
              .finally(() => button.disabled = false);
        });

        function queueSearch(source) {
            if (source === topKeywordEl) keywordEl.value = topKeywordEl.value;
            if (source === keywordEl) topKeywordEl.value = keywordEl.value;
            clearTimeout(searchTimer);
            searchTimer = setTimeout(loadProductionOrders, 250);
        }
        keywordEl.addEventListener('input', () => queueSearch(keywordEl));
        topKeywordEl.addEventListener('input', () => queueSearch(topKeywordEl));
        ['productionFromDate','productionToDate','productionStatus'].forEach(id => document.getElementById(id).addEventListener('change', loadProductionOrders));
        document.getElementById('clearProductionFilter').addEventListener('click', () => {
            keywordEl.value = ''; topKeywordEl.value = '';
            document.getElementById('productionFromDate').value = '';
            document.getElementById('productionToDate').value = '';
            document.getElementById('productionStatus').value = '';
            loadProductionOrders();
        });
        loadProductionOrders();
    </script>
</body>
</html>
