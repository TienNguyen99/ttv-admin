<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tổng quan kho nội bộ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/warehouse-wms.css') }}?v={{ filemtime(public_path('css/warehouse-wms.css')) }}" rel="stylesheet">
    <style>
        @keyframes chartEnter {
            from { opacity: 0; transform: translateY(10px) scale(.985); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        @keyframes donutPop {
            from { opacity: 0; transform: scale(.86) rotate(-18deg); }
            to { opacity: 1; transform: scale(1) rotate(0); }
        }
        @keyframes lineDraw {
            from { stroke-dashoffset: 900; }
            to { stroke-dashoffset: 0; }
        }
        @keyframes areaFade {
            from { opacity: 0; }
            to { opacity: .12; }
        }
        @keyframes barGrow {
            from { transform: scaleX(0); }
            to { transform: scaleX(1); }
        }
        @keyframes gaugeRise {
            from { transform: rotate(-5deg); opacity: .35; }
            to { transform: rotate(0); opacity: 1; }
        }
        .wms-chart-grid { display: grid; grid-template-columns: 1.1fr .9fr; gap: 20px; margin-bottom: 20px; }
        .wms-chart-card { border: 1px solid var(--wms-line); border-radius: var(--radius-lg, 16px); background: #fff; overflow: hidden; box-shadow: var(--shadow-md, 0 4px 6px -1px rgba(0,0,0,0.05)); animation: chartEnter .42s var(--wms-ease-out, cubic-bezier(.16,1,.3,1)) both; transition: transform var(--wms-transition-smooth, 240ms ease), box-shadow var(--wms-transition-smooth, 240ms ease), border-color var(--wms-transition-smooth, 240ms ease); }
        .wms-chart-card:nth-child(1) { animation-delay: 80ms; }
        .wms-chart-card:nth-child(2) { animation-delay: 130ms; }
        .wms-chart-card:nth-child(3) { animation-delay: 180ms; }
        .wms-chart-card:nth-child(4) { animation-delay: 230ms; }
        .wms-chart-card:hover { transform: translateY(-3px); border-color: rgba(37,99,235,.18); box-shadow: var(--shadow-lg, 0 10px 15px -3px rgba(0,0,0,0.05)), 0 14px 30px rgba(37,99,235,.06); }
        .wms-chart-card__head { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 16px 20px; border-bottom: 1px solid var(--wms-line); background: #fafafb; }
        .wms-chart-card__title { margin: 0; color: var(--wms-navy); font-size: 15px; font-weight: 800; letter-spacing: 0; }
        .wms-chart-card__meta { color: #64748b; font-size: 12px; white-space: nowrap; font-weight: 500; }
        .wms-chart-card__body { padding: 20px; }
        .wms-bar-row { display: grid; grid-template-columns: minmax(120px, 170px) minmax(0, 1fr) 90px; gap: 10px; align-items: center; margin: 12px 0; font-size: 13px; }
        .wms-bar-label { color: #0f172a; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .wms-bar-track { height: 12px; border-radius: 999px; background: #e8eef6; overflow: hidden; }
        .wms-bar-fill { height: 100%; min-width: 2px; border-radius: inherit; background: var(--wms-blue, #2563eb); transform-origin: left center; animation: barGrow .7s var(--wms-ease-out, cubic-bezier(.16,1,.3,1)) both; transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1); }
        .wms-bar-fill--good { background: var(--wms-success, #10b981); }
        .wms-bar-fill--warn { background: var(--wms-warning, #f59e0b); }
        .wms-bar-fill--bad { background: var(--wms-danger, #ef4444); }
        .wms-bar-value { color: #0f172a; font-family: Menlo, Monaco, Consolas, monospace; font-weight: 800; text-align: right; }
        .wms-segment { display: flex; width: 100%; height: 18px; border-radius: 999px; background: #e8eef6; overflow: hidden; }
        .wms-segment span { min-width: 2px; }
        .wms-donut-wrap { display: grid; grid-template-columns: 230px minmax(0, 1fr); gap: 18px; align-items: center; }
        .wms-apex-chart { min-height: 210px; }
        .wms-apex-chart .apexcharts-canvas { margin: 0 auto; }
        .wms-donut { width: 150px; aspect-ratio: 1; border-radius: 50%; display: grid; place-items: center; background: conic-gradient(#e8eef6 0 100%); position: relative; animation: donutPop .55s var(--wms-ease-spring, cubic-bezier(.34,1.56,.64,1)) both; }
        .wms-donut::after { content: ""; width: 92px; aspect-ratio: 1; border-radius: 50%; background: #fff; box-shadow: inset 0 0 0 1px #e5ebf2; position: absolute; }
        .wms-donut__center { position: relative; z-index: 1; text-align: center; color: var(--wms-navy); font-weight: 850; line-height: 1.05; }
        .wms-donut__center small { display: block; color: #64748b; font-size: 11px; font-weight: 700; margin-top: 3px; }
        .wms-chart-legend { display: flex; flex-wrap: wrap; gap: 8px 14px; margin-top: 16px; color: #475569; font-size: 12px; font-weight: 500; }
        .wms-chart-legend i { display: inline-block; width: 10px; height: 10px; border-radius: 999px; margin-right: 6px; }
        .wms-line-chart { width: 100%; height: 176px; display: block; }
        .wms-line-axis { stroke: #dbe2ea; stroke-width: 1; }
        .wms-line-grid { stroke: #edf2f7; stroke-width: 1; }
        .wms-line-receipt { fill: none; stroke: #15803d; stroke-width: 3; stroke-linecap: round; stroke-linejoin: round; stroke-dasharray: 900; animation: lineDraw .9s var(--wms-ease-out, cubic-bezier(.16,1,.3,1)) both; }
        .wms-line-issue { fill: none; stroke: #d97706; stroke-width: 3; stroke-linecap: round; stroke-linejoin: round; stroke-dasharray: 900; animation: lineDraw .9s var(--wms-ease-out, cubic-bezier(.16,1,.3,1)) .08s both; }
        .wms-line-area { opacity: .12; animation: areaFade .6s ease-out .25s both; }
        .wms-line-label { fill: #64748b; font-size: 10px; font-weight: 700; }
        .wms-flow-pair { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .wms-flow-box { border: 1px solid var(--wms-line, #e2e8f0); border-radius: var(--radius-md, 10px); padding: 14px; background: #f8fafc; transition: var(--wms-transition-smooth, all 0.25s ease); }
        .wms-flow-box:hover { background: #ffffff; border-color: var(--wms-blue-soft-border); box-shadow: var(--shadow-sm); }
        .wms-flow-box__label { color: #64748b; font-size: 11.5px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.02em; }
        .wms-flow-box__value { color: var(--wms-navy); font-size: 24px; font-weight: 800; line-height: 1.15; margin-top: 4px; }
        .wms-flow-box__sub { color: #64748b; font-size: 12px; margin-top: 4px; font-weight: 500; }
        .wms-gauge { display: grid; grid-template-columns: 160px minmax(0, 1fr); gap: 16px; align-items: center; }
        .wms-gauge__dial { width: 160px; height: 86px; overflow: hidden; position: relative; }
        .wms-gauge__arc { width: 160px; height: 160px; border-radius: 50%; background: conic-gradient(from 270deg, #15803d 0deg, #15803d var(--gauge-angle, 0deg), #e8eef6 var(--gauge-angle, 0deg), #e8eef6 180deg, transparent 180deg 360deg); position: absolute; inset: 0; animation: gaugeRise .55s var(--wms-ease-out, cubic-bezier(.16,1,.3,1)) both; }
        .wms-gauge__arc::after { content: ""; position: absolute; inset: 22px; border-radius: 50%; background: #fff; }
        .wms-gauge__value { position: absolute; left: 0; right: 0; bottom: 2px; text-align: center; color: var(--wms-navy); font-size: 24px; font-weight: 850; }
        .wms-gauge__stats { display: grid; gap: 8px; }
        .wms-stat-line { display: flex; justify-content: space-between; gap: 12px; border-bottom: 1px solid #edf2f7; padding-bottom: 6px; color: #475569; font-size: 13px; }
        .wms-stat-line strong { color: var(--wms-navy); font-family: Menlo, Monaco, Consolas, monospace; }
        @media (max-width: 1100px) { .wms-chart-grid { grid-template-columns: 1fr; } }
        @media (max-width: 640px) {
            .wms-bar-row { grid-template-columns: 1fr; gap: 5px; }
            .wms-bar-value { text-align: left; }
            .wms-flow-pair { grid-template-columns: 1fr; }
            .wms-donut-wrap, .wms-gauge { grid-template-columns: 1fr; }
            .wms-apex-chart { min-height: 190px; }
        }
        @media (prefers-reduced-motion: reduce) {
            .wms-chart-card,
            .wms-donut,
            .wms-bar-fill,
            .wms-line-receipt,
            .wms-line-issue,
            .wms-line-area,
            .wms-gauge__arc {
                animation: none !important;
            }
        }
    </style>
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

        <section class="wms-chart-grid" aria-label="Biểu đồ kho">
            <article class="wms-chart-card">
                <div class="wms-chart-card__head">
                    <h2 class="wms-chart-card__title">Tình trạng tồn kho</h2>
                    <span id="stockStatusMeta" class="wms-chart-card__meta">Đang tải...</span>
                </div>
                <div class="wms-chart-card__body">
                    <div class="wms-donut-wrap">
                        <div id="stockStatusDonut" class="wms-apex-chart" aria-label="Tình trạng tồn kho"></div>
                        <div id="stockStatusLegend" class="wms-chart-legend"></div>
                    </div>
                </div>
            </article>

            <article class="wms-chart-card">
                <div class="wms-chart-card__head">
                    <h2 class="wms-chart-card__title">Nhập / xuất hôm nay</h2>
                    <span id="todayFlowMeta" class="wms-chart-card__meta">{{ now()->format('d/m/Y') }}</span>
                </div>
                <div class="wms-chart-card__body">
                    <div class="wms-flow-pair">
                        <div class="wms-flow-box">
                            <div class="wms-flow-box__label">Nhập TP</div>
                            <div id="todayReceiptQty" class="wms-flow-box__value">0</div>
                            <div id="todayReceiptDocs" class="wms-flow-box__sub">0 phiếu</div>
                        </div>
                        <div class="wms-flow-box">
                            <div class="wms-flow-box__label">Xuất kho</div>
                            <div id="todayIssueQty" class="wms-flow-box__value">0</div>
                            <div id="todayIssueDocs" class="wms-flow-box__sub">0 phiếu</div>
                        </div>
                    </div>
                    <div id="todayFlowChart" class="mt-3"></div>
                </div>
            </article>

            <article class="wms-chart-card">
                <div class="wms-chart-card__head">
                    <h2 class="wms-chart-card__title">Top vị trí đang chứa hàng</h2>
                    <a class="wms-link" href="{{ url('/client/kiem-ton-kho?view=overview') }}">Xem vị trí</a>
                </div>
                <div id="locationChart" class="wms-chart-card__body"></div>
            </article>

            <article class="wms-chart-card">
                <div class="wms-chart-card__head">
                    <h2 class="wms-chart-card__title">BTP đang sản xuất</h2>
                    <a class="wms-link" href="{{ url('/client/theo-doi-san-xuat') }}">Xem chi tiết</a>
                </div>
                <div id="wipChart" class="wms-chart-card__body"></div>
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
                        <thead><tr><th>Mã kế toán</th><th>Mã nội bộ</th><th>Vị trí</th><th>Size / Màu</th><th class="text-end">Tồn</th><th>Trạng thái</th></tr></thead>
                        <tbody id="dashboardStockRows"><tr><td colspan="6" class="wms-loading">Đang tải dữ liệu...</td></tr></tbody>
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
                <a class="wms-btn" href="{{ url('/client/canh-bao-kho') }}"><i data-lucide="triangle-alert"></i> Cảnh báo kho</a>
            </div>
        </section>
    </main>

    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        const dashboardNum = value => Number(value || 0).toLocaleString('vi-VN', {maximumFractionDigits: 3});
        const dashboardEsc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
        const today = '{{ now()->format('Y-m-d') }}';
        const month = '{{ now()->format('Y-m') }}';
        const dashboardCharts = {};
        const chartFont = '"Plus Jakarta Sans", "Inter", "Segoe UI", Arial, sans-serif';

        function dashboardJson(response) {
            if (!response.ok) throw new Error('Không tải được dữ liệu kho');
            return response.json();
        }

        function pct(value, total) {
            return total > 0 ? Math.max(1, Math.round((Number(value || 0) / total) * 100)) : 0;
        }

        function renderApexChart(targetId, options) {
            if (!window.ApexCharts) {
                document.getElementById(targetId).innerHTML = '<div class="wms-empty">Không tải được thư viện biểu đồ.</div>';
                return;
            }
            if (dashboardCharts[targetId]) {
                dashboardCharts[targetId].destroy();
            }
            dashboardCharts[targetId] = new ApexCharts(document.getElementById(targetId), options);
            dashboardCharts[targetId].render();
        }

        function renderBarRows(targetId, rows, options = {}) {
            const max = Math.max(...rows.map(row => Number(row.value || 0)), 0);
            document.getElementById(targetId).innerHTML = rows.length ? rows.map(row => `
                <div class="wms-bar-row">
                    <div class="wms-bar-label" title="${dashboardEsc(row.label)}">${dashboardEsc(row.label)}</div>
                    <div class="wms-bar-track"><div class="wms-bar-fill ${row.className || ''}" style="width:${max ? Math.max(2, Math.round(Number(row.value || 0) / max * 100)) : 0}%"></div></div>
                    <div class="wms-bar-value">${options.rawValue ? dashboardEsc(row.value) : dashboardNum(row.value)}</div>
                </div>
            `).join('') : '<div class="wms-empty">Chưa có dữ liệu biểu đồ.</div>';
        }

        function renderStockStatusChart(stockRows, quality) {
            const positiveAssigned = stockRows.filter(row => Number(row.total_quantity) > 0 && row.location_code && row.location_code !== 'CHUA-XEP').length;
            const unassigned = Number(quality.summary?.unassigned_stock || 0);
            const negative = Number(quality.summary?.negative_stock || 0);
            const missingCatalog = Number(quality.summary?.missing_catalog || 0);
            const segments = [
                { label: 'Có vị trí', value: positiveAssigned, color: '#15803d' },
                { label: 'Chưa xếp', value: unassigned, color: '#d97706' },
                { label: '�m tn', value: negative, color: '#b91c1c' },
                { label: 'Thiếu danh mục', value: missingCatalog, color: '#0f5fa8' },
            ];
            document.getElementById('stockStatusMeta').textContent = `${dashboardNum(stockRows.length)} dòng tồn`;
            renderApexChart('stockStatusDonut', {
                chart: {
                    type: 'donut',
                    height: 230,
                    fontFamily: chartFont,
                    animations: {
                        enabled: true,
                        easing: 'easeout',
                        speed: 850,
                        animateGradually: { enabled: true, delay: 120 },
                    },
                    toolbar: { show: false },
                },
                series: segments.map(item => item.value),
                labels: segments.map(item => item.label),
                colors: segments.map(item => item.color),
                stroke: { width: 3, colors: ['#ffffff'] },
                legend: { show: false },
                dataLabels: { enabled: false },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '68%',
                            labels: {
                                show: true,
                                name: { show: true, fontSize: '12px', color: '#64748b', offsetY: 16 },
                                value: { show: true, fontSize: '24px', fontWeight: 800, color: '#0a2540', offsetY: -12, formatter: dashboardNum },
                                total: {
                                    show: true,
                                    label: 'dòng tồn',
                                    fontSize: '11px',
                                    color: '#64748b',
                                    formatter: () => dashboardNum(stockRows.length),
                                },
                            },
                        },
                    },
                },
                tooltip: { y: { formatter: value => `${dashboardNum(value)} dòng` } },
                states: { hover: { filter: { type: 'lighten', value: .08 } } },
            });
            document.getElementById('stockStatusLegend').innerHTML = segments.map(item =>
                `<span><i style="background:${item.color}"></i>${dashboardEsc(item.label)}: <strong>${dashboardNum(item.value)}</strong></span>`
            ).join('');
        }

        function renderLineChart(targetId, rows) {
            renderApexChart(targetId, {
                chart: {
                    type: 'area',
                    height: 230,
                    fontFamily: chartFont,
                    toolbar: { show: false },
                    zoom: { enabled: false },
                    animations: {
                        enabled: true,
                        easing: 'easeinout',
                        speed: 900,
                        animateGradually: { enabled: true, delay: 140 },
                        dynamicAnimation: { enabled: true, speed: 450 },
                    },
                },
                series: [
                    { name: 'Nhập thành phẩm', data: rows.map(row => Number(row.receipt || 0)) },
                    { name: 'Xuất kho', data: rows.map(row => Number(row.issue || 0)) },
                ],
                colors: ['#15803d', '#d97706'],
                xaxis: {
                    categories: rows.map(row => row.label),
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                    labels: { style: { colors: '#64748b', fontWeight: 700 } },
                },
                yaxis: {
                    labels: { formatter: dashboardNum, style: { colors: '#64748b' } },
                },
                stroke: { curve: 'smooth', width: 3 },
                fill: {
                    type: 'gradient',
                    gradient: { shadeIntensity: .2, opacityFrom: .28, opacityTo: .04, stops: [0, 90, 100] },
                },
                markers: { size: 4, strokeWidth: 2, hover: { size: 7 } },
                grid: { borderColor: '#edf2f7', strokeDashArray: 4 },
                dataLabels: { enabled: false },
                legend: { position: 'bottom', horizontalAlign: 'left', fontSize: '12px', fontWeight: 700, markers: { radius: 12 } },
                tooltip: { y: { formatter: value => dashboardNum(value) } },
            });
        }

        function renderTodayFlow(receipts, issues, weeklyFlow) {
            const receiptQty = Number(receipts.summary?.total_quantity || 0);
            const issueQty = Number(issues.summary?.total_quantity || 0);
            document.getElementById('todayReceiptQty').textContent = dashboardNum(receiptQty);
            document.getElementById('todayIssueQty').textContent = dashboardNum(issueQty);
            document.getElementById('todayReceiptDocs').textContent = `${dashboardNum(receipts.summary?.receipt_count || 0)} phiếu`;
            document.getElementById('todayIssueDocs').textContent = `${dashboardNum(issues.summary?.total_issues || 0)} phiếu`;
            renderLineChart('todayFlowChart', weeklyFlow);
        }

        function renderLocationChart(stockRows) {
            const groups = new Map();
            stockRows.forEach(row => {
                const location = row.location_code || 'CHUA-XEP';
                const quantity = Math.max(0, Number(row.total_quantity || 0));
                if (quantity <= 0) return;
                groups.set(location, (groups.get(location) || 0) + quantity);
            });
            const rows = Array.from(groups.entries())
                .map(([label, value]) => ({ label, value, className: label === 'CHUA-XEP' ? 'wms-bar-fill--warn' : '' }))
                .sort((a, b) => b.value - a.value)
                .slice(0, 6);
            if (!rows.length) {
                document.getElementById('locationChart').innerHTML = '<div class="wms-empty">Chưa có dữ liệu vị trí.</div>';
                return;
            }
            renderApexChart('locationChart', {
                chart: {
                    type: 'bar',
                    height: 255,
                    fontFamily: chartFont,
                    toolbar: { show: false },
                    animations: {
                        enabled: true,
                        easing: 'easeout',
                        speed: 850,
                        animateGradually: { enabled: true, delay: 90 },
                    },
                },
                series: [{ name: 'Số lượng', data: rows.map(row => Number(row.value || 0)) }],
                colors: ['#2563eb'],
                plotOptions: {
                    bar: {
                        horizontal: true,
                        borderRadius: 7,
                        barHeight: '58%',
                        distributed: true,
                    },
                },
                xaxis: {
                    categories: rows.map(row => row.label),
                    labels: { formatter: dashboardNum, style: { colors: '#64748b' } },
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                },
                yaxis: { labels: { style: { colors: '#0f172a', fontWeight: 800 } } },
                grid: { borderColor: '#edf2f7', strokeDashArray: 4 },
                dataLabels: { enabled: true, formatter: dashboardNum, style: { fontWeight: 800 } },
                legend: { show: false },
                tooltip: { y: { formatter: value => dashboardNum(value) } },
            });
        }

        function renderWipChart(wip) {
            const summary = wip.summary || {};
            const issued = Number(summary.issued_quantity || 0);
            const returned = Number(summary.returned_quantity || 0);
            const outstanding = Number(summary.outstanding_quantity || 0);
            if (issued <= 0) {
                document.getElementById('wipChart').innerHTML = '<div class="wms-empty">Chưa có BTP đang sản xuất.</div>';
                return;
            }
            const percent = Math.min(100, Math.round((returned / issued) * 100));
            document.getElementById('wipChart').innerHTML = '<div id="wipRadialChart" class="wms-apex-chart"></div><div class="wms-gauge__stats mt-2"></div>';
            renderApexChart('wipRadialChart', {
                chart: {
                    type: 'radialBar',
                    height: 240,
                    fontFamily: chartFont,
                    sparkline: { enabled: true },
                    animations: { enabled: true, easing: 'easeout', speed: 1050 },
                },
                series: [percent],
                colors: [outstanding > 0 ? '#d97706' : '#15803d'],
                plotOptions: {
                    radialBar: {
                        startAngle: -120,
                        endAngle: 120,
                        hollow: { size: '62%' },
                        track: { background: '#e8eef6', strokeWidth: '100%' },
                        dataLabels: {
                            name: { show: true, offsetY: 22, color: '#64748b', fontSize: '12px', fontWeight: 800 },
                            value: { show: true, offsetY: -12, color: '#0a2540', fontSize: '32px', fontWeight: 850, formatter: value => `${Math.round(value)}%` },
                        },
                    },
                },
                labels: ['đã nhập lại'],
            });
            document.querySelector('#wipChart .wms-gauge__stats').innerHTML = `
                <div class="wms-stat-line"><span>Đã xuất BTP</span><strong>${dashboardNum(issued)}</strong></div>
                <div class="wms-stat-line"><span>Đã nhập lại</span><strong>${dashboardNum(returned)}</strong></div>
                <div class="wms-stat-line"><span>Còn ngoài SX</span><strong>${dashboardNum(outstanding)}</strong></div>
            `;
        }

        Promise.all([
            fetch('/api/ton-kho-noi-bo?month=' + month).then(dashboardJson),
            fetch('/api/kiem-ton-kho/phieu-nhap-tp?receipt_date=' + today + '&limit=50').then(dashboardJson),
            fetch('/api/xuat-vat-tu-noi-bo?from_date=' + today + '&to_date=' + today).then(dashboardJson),
            fetch('/api/canh-bao-kho?month=' + month).then(dashboardJson),
            fetch('/api/theo-doi-san-xuat').then(dashboardJson),
            fetch('/api/kho-noi-bo/nhap-xuat-ngay?days=7&to=' + today).then(dashboardJson)
        ]).then(([stock, receipts, issues, quality, wip, flow]) => {
            const weeklyFlow = (flow.data || []).map(row => ({
                label: row.label,
                receipt: Number(row.receipt_quantity || 0),
                issue: Number(row.issue_quantity || 0),
            }));
            const stockRows = stock.data || [];
            const receiptRows = receipts.data || [];
            const issueRows = issues.data || [];
            const unassigned = stockRows.filter(row => !row.location_code || row.location_code === 'CHUA-XEP');

            document.getElementById('dashboardItems').textContent = dashboardNum(stock.summary?.item_count);
            document.getElementById('dashboardUnassigned').textContent = dashboardNum(unassigned.length);
            document.getElementById('dashboardReceipts').textContent = dashboardNum(receipts.summary?.total_quantity);
            document.getElementById('dashboardIssues').textContent = dashboardNum(issues.summary?.total_quantity);
            renderStockStatusChart(stockRows, quality);
            renderTodayFlow(receipts, issues, weeklyFlow);
            renderLocationChart(stockRows);
            renderWipChart(wip);

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
                    <td>${dashboardEsc(row.location_code || 'CHUA-XEP')}</td>
                    <td>${dashboardEsc([row.size, row.color].filter(Boolean).join(' / ') || '-')}</td>
                    <td class="wms-number">${dashboardNum(row.total_quantity)}</td>
                    <td>${status}</td>
                </tr>`;
            }).join('') || '<tr><td colspan="6" class="wms-empty">Không có dữ liệu cần chú ý.</td></tr>';

            const activities = [
                ...receiptRows.map(row => ({date: row.receipt_date, title: `Nhập kho: ${row.receipt_code}`, meta: `${dashboardNum(row.total_quantity)} · ${row.location_code || 'CHUA-XEP'}`})),
                ...issueRows.map(row => ({date: row.issue_date, title: `Xuất kho: ${row.issue_code}`, meta: `${dashboardNum(row.lines_sum_quantity)} · ${row.department || row.receiver_name || 'Nội bộ'}`}))
            ].sort((a, b) => String(b.date).localeCompare(String(a.date))).slice(0, 7);

            document.getElementById('dashboardActivity').innerHTML = activities.map(item => `
                <div class="wms-activity__item">
                    <div class="wms-activity__title">${dashboardEsc(item.title)}</div>
                    <div class="wms-activity__meta">${dashboardEsc(item.date || '')} · ${dashboardEsc(item.meta)}</div>
                </div>
            `).join('') || '<div class="wms-empty">Chưa có hoạt động hôm nay.</div>';
        }).catch(error => {
            document.getElementById('dashboardStockRows').innerHTML = `<tr><td colspan="6" class="wms-empty text-danger">${dashboardEsc(error.message)}</td></tr>`;
            document.getElementById('dashboardActivity').innerHTML = `<div class="wms-empty text-danger">${dashboardEsc(error.message)}</div>`;
        });

        if (window.lucide) lucide.createIcons();
    </script>
</body>
</html>
