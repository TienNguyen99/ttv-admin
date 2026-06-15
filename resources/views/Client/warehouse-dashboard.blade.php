<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tổng quan kho nội bộ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/warehouse-wms.css') }}" rel="stylesheet">
</head>
<body>
    @include('layouts.partials.sidebar')

    <header class="wms-topbar">
        <h1 class="wms-topbar__title">WMS May Mặc</h1>
        <form class="wms-global-search" action="{{ url('/client/ton-kho-noi-bo') }}" method="get">
            <i data-lucide="search"></i>
            <input name="keyword" aria-label="Tìm mã hàng trong kho" placeholder="Tìm mã nội bộ, mã kế toán hoặc vị trí...">
        </form>
        <div class="wms-topbar__actions">
            <a class="wms-btn" href="{{ url('/client/kiem-ton-kho') }}"><i data-lucide="scan-line"></i> Quét kho</a>
        </div>
    </header>

    <main class="wms-page">
        <div class="wms-heading">
            <div>
                <h1>Tổng quan kho nội bộ</h1>
                <p>Dữ liệu vận hành nội bộ. TSoft chỉ được sử dụng để đọc và đối chiếu.</p>
            </div>
            <div class="wms-actions">
                <a class="wms-btn" href="{{ url('/client/doi-chieu-ton') }}"><i data-lucide="file-spreadsheet"></i> Đối chiếu TSoft</a>
                <a class="wms-btn wms-btn--primary" href="{{ url('/client/kiem-ton-kho?view=entry') }}"><i data-lucide="file-plus-2"></i> Tạo phiếu nhập</a>
            </div>
        </div>

        <section class="wms-kpis" aria-label="Chỉ số kho">
            <article class="wms-kpi">
                <div class="wms-kpi__icon"><i data-lucide="boxes"></i></div>
                <div><div class="wms-kpi__label">Mã đang có phát sinh</div><div id="dashboardItems" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Trong tháng hiện tại</div></div>
            </article>
            <article class="wms-kpi wms-kpi--danger">
                <div class="wms-kpi__icon"><i data-lucide="map-pin-off"></i></div>
                <div><div class="wms-kpi__label">Chưa xếp vị trí</div><div id="dashboardUnassigned" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Cần bố trí lên kệ</div></div>
            </article>
            <article class="wms-kpi">
                <div class="wms-kpi__icon"><i data-lucide="package-plus"></i></div>
                <div><div class="wms-kpi__label">Nhập kho hôm nay</div><div id="dashboardReceipts" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Số lượng đã ghi nhận</div></div>
            </article>
            <article class="wms-kpi">
                <div class="wms-kpi__icon"><i data-lucide="package-minus"></i></div>
                <div><div class="wms-kpi__label">Xuất kho hôm nay</div><div id="dashboardIssues" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Số lượng đã xuất</div></div>
            </article>
        </section>

        <div class="wms-dashboard-grid">
            <section class="wms-panel">
                <div class="wms-panel__header">
                    <h2>Tồn kho cần chú ý</h2>
                    <a class="wms-link" href="{{ url('/client/ton-kho-noi-bo') }}">Xem toàn bộ</a>
                </div>
                <div class="wms-table-wrap">
                    <table class="wms-table">
                        <thead><tr><th>Mã kế toán</th><th>Mã nội bộ</th><th>Kho</th><th>Vị trí</th><th>Size / Màu</th><th class="text-end">Tồn</th><th>Trạng thái</th></tr></thead>
                        <tbody id="dashboardStockRows"><tr><td colspan="7" class="wms-loading">Đang tải dữ liệu...</td></tr></tbody>
                    </table>
                </div>
            </section>

            <section class="wms-panel">
                <div class="wms-panel__header">
                    <h2>Hoạt động gần đây</h2>
                    <a class="wms-link" href="{{ url('/client/xuat-vat-tu-noi-bo') }}">Xem phiếu kho</a>
                </div>
                <div id="dashboardActivity" class="wms-panel__body wms-activity">
                    <div class="wms-loading">Đang tải hoạt động...</div>
                </div>
            </section>
        </div>

        <section class="wms-panel">
            <div class="wms-panel__header">
                <h2>Thao tác nhanh</h2>
            </div>
            <div class="wms-panel__body wms-actions">
                <a class="wms-btn" href="{{ url('/client/ton-kho-noi-bo') }}"><i data-lucide="warehouse"></i> Kiểm tra tồn</a>
                <a class="wms-btn" href="{{ url('/client/kiem-ton-kho') }}"><i data-lucide="map"></i> Sơ đồ và vị trí</a>
                <a class="wms-btn" href="{{ url('/client/theo-doi-san-xuat') }}"><i data-lucide="workflow"></i> Hàng đang sản xuất</a>
                <a class="wms-btn" href="{{ url('/client/kiem-ton-kho?view=entry') }}"><i data-lucide="package-plus"></i> Nhập thành phẩm</a>
                <a class="wms-btn" href="{{ url('/client/xuat-vat-tu-noi-bo?type=production') }}"><i data-lucide="factory"></i> Xuất BTP sản xuất</a>
                <a class="wms-btn" href="{{ url('/client/xuat-vat-tu-noi-bo?type=material') }}"><i data-lucide="package-minus"></i> Xuất vật tư</a>
            </div>
        </section>
    </main>

    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
    <script>
        const dashboardNum = value => Number(value || 0).toLocaleString('vi-VN', {maximumFractionDigits: 3});
        const dashboardEsc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
        const today = '{{ now()->format('Y-m-d') }}';

        function dashboardJson(response) {
            if (!response.ok) throw new Error('Không tải được dữ liệu kho');
            return response.json();
        }

        Promise.all([
            fetch('/api/ton-kho-noi-bo?month={{ now()->format('Y-m') }}').then(dashboardJson),
            fetch('/api/kiem-ton-kho/phieu-nhap-tp?receipt_date=' + today + '&limit=50').then(dashboardJson),
            fetch('/api/xuat-vat-tu-noi-bo?from_date=' + today + '&to_date=' + today).then(dashboardJson)
        ]).then(([stock, receipts, issues]) => {
            const stockRows = stock.data || [];
            const receiptRows = receipts.data || [];
            const issueRows = issues.data || [];
            const unassigned = stockRows.filter(row => !row.location_code || row.location_code === 'CHUA-XEP');

            document.getElementById('dashboardItems').textContent = dashboardNum(stock.summary?.item_count);
            document.getElementById('dashboardUnassigned').textContent = dashboardNum(unassigned.length);
            document.getElementById('dashboardReceipts').textContent = dashboardNum(receipts.summary?.total_quantity);
            document.getElementById('dashboardIssues').textContent = dashboardNum(issues.summary?.total_quantity);

            const attention = stockRows
                .filter(row => Number(row.total_quantity) <= 0 || !row.location_code || row.location_code === 'CHUA-XEP')
                .slice(0, 8);
            document.getElementById('dashboardStockRows').innerHTML = attention.map(row => {
                const unlocated = !row.location_code || row.location_code === 'CHUA-XEP';
                const status = unlocated
                    ? '<span class="wms-badge wms-badge--warning">Chưa xếp</span>'
                    : '<span class="wms-badge wms-badge--danger">Tồn không dương</span>';
                return `<tr>
                    <td class="wms-code">${dashboardEsc(row.ma_sp || '-')}</td>
                    <td class="wms-code">${dashboardEsc(row.internal_item_code || '-')}</td>
                    <td>${dashboardEsc(row.warehouse_code || '-')}</td>
                    <td>${dashboardEsc(row.location_code || 'CHUA-XEP')}</td>
                    <td>${dashboardEsc([row.size, row.color].filter(Boolean).join(' / ') || '-')}</td>
                    <td class="wms-number">${dashboardNum(row.total_quantity)}</td>
                    <td>${status}</td>
                </tr>`;
            }).join('') || '<tr><td colspan="7" class="wms-empty">Không có dữ liệu cần chú ý.</td></tr>';

            const activities = [
                ...receiptRows.map(row => ({date: row.receipt_date, title: `Nhập kho: ${row.receipt_code}`, meta: `${dashboardNum(row.total_quantity)} · ${row.warehouse_code || 'Chưa chọn kho'}`})),
                ...issueRows.map(row => ({date: row.issue_date, title: `Xuất kho: ${row.issue_code}`, meta: `${dashboardNum(row.lines_sum_quantity)} · ${row.department || row.receiver_name || 'Nội bộ'}`}))
            ].sort((a, b) => String(b.date).localeCompare(String(a.date))).slice(0, 7);

            document.getElementById('dashboardActivity').innerHTML = activities.map(item => `
                <div class="wms-activity__item">
                    <div class="wms-activity__title">${dashboardEsc(item.title)}</div>
                    <div class="wms-activity__meta">${dashboardEsc(item.date || '')} · ${dashboardEsc(item.meta)}</div>
                </div>
            `).join('') || '<div class="wms-empty">Chưa có hoạt động hôm nay.</div>';
        }).catch(error => {
            document.getElementById('dashboardStockRows').innerHTML = `<tr><td colspan="7" class="wms-empty text-danger">${dashboardEsc(error.message)}</td></tr>`;
            document.getElementById('dashboardActivity').innerHTML = `<div class="wms-empty text-danger">${dashboardEsc(error.message)}</div>`;
        });

        if (window.lucide) lucide.createIcons();
    </script>
</body>
</html>
