<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Designer theo dõi lệnh dệt</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/warehouse-wms.css') }}?v={{ filemtime(public_path('css/warehouse-wms.css')) }}" rel="stylesheet">
    <style>
        .designer-kpis { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:12px; }
        .designer-filter { display:grid; grid-template-columns:minmax(260px,1fr) minmax(170px,220px) minmax(170px,220px) auto; gap:10px; align-items:end; }
        .designer-table { min-width:1320px; }
        .designer-order { font-family:Menlo,Consolas,monospace; font-weight:900; color:#175ca8; }
        .designer-progress { min-width:130px; }
        .designer-progress .progress { height:7px; background:#e8f1fb; }
        .designer-progress .progress-bar { background:#4f8fe8; }
        .designer-progress small { display:block; margin-top:5px; color:#64748b; }
        .designer-charts { display:grid; grid-template-columns:minmax(250px,.85fr) minmax(360px,1.35fr) minmax(300px,1fr); gap:12px; }
        .designer-chart-card { min-width:0; padding:0; overflow:hidden; }
        .designer-chart-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; padding:14px 16px 8px; }
        .designer-chart-head h2 { margin:0; color:#17375e; font-size:14px; font-weight:850; }
        .designer-chart-head p { margin:3px 0 0; color:#718096; font-size:11px; }
        .designer-chart-badge { flex:0 0 auto; border:1px solid #cfe0f6; border-radius:999px; padding:4px 8px; background:#edf5ff; color:#3975bd; font-size:10px; font-weight:850; }
        .designer-chart { height:235px; padding:0 8px 4px; }
        .designer-chart .apexcharts-canvas { margin:0 auto; }
        .designer-status { display:inline-flex; align-items:center; gap:5px; border-radius:999px; padding:4px 9px; font-size:11px; font-weight:900; white-space:nowrap; }
        .designer-status--waiting { color:#6b7280; background:#f1f5f9; }
        .designer-status--producing { color:#175ca8; background:#e7f1ff; }
        .designer-status--partial { color:#9a5b00; background:#fff4d8; }
        .designer-status--completed { color:#087a55; background:#dcf8ec; }
        .designer-overdue { display:block; width:max-content; margin-top:5px; color:#c23b4a; background:#ffe8ec; border-radius:6px; padding:2px 6px; font-size:10px; font-weight:900; }
        @media (max-width:1180px) { .designer-charts { grid-template-columns:repeat(2,minmax(0,1fr)); } .designer-chart-card:last-child { grid-column:1 / -1; } }
        @media (max-width:1100px) { .designer-kpis { grid-template-columns:repeat(2,minmax(0,1fr)); } .designer-filter { grid-template-columns:1fr 1fr; } }
        @media (max-width:720px) { .designer-charts { grid-template-columns:1fr; } .designer-chart-card:last-child { grid-column:auto; } }
        @media (max-width:620px) { .designer-kpis,.designer-filter { grid-template-columns:1fr; } }
        @media (prefers-reduced-motion:reduce) { .designer-chart * { animation-duration:0.01ms !important; } }
    </style>
</head>
<body>
@include('layouts.partials.sidebar')

<header class="wms-topbar">
    <h1 class="wms-topbar__title">WMS May Mặc</h1>
    <form class="wms-global-search" onsubmit="return false">
        <i data-lucide="search"></i>
        <input id="topKeyword" aria-label="Tìm lệnh dệt" placeholder="Tìm lệnh, mã hàng, PO hoặc design...">
    </form>
    <a class="wms-btn" href="{{ url('/client/lenh-det') }}"><i data-lucide="list-tree"></i>Định mức</a>
</header>

<main class="wms-page">
    <div class="wms-heading">
        <div><h1>Theo dõi lệnh dệt</h1><p>Designer theo dõi lệnh đã gửi sản xuất và lượng thành phẩm đã nhập kho.</p></div>
        <button id="reloadBtn" class="wms-btn" type="button"><i data-lucide="refresh-cw"></i>Tải lại</button>
    </div>

    <section class="designer-kpis mb-3">
        <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="clipboard-list"></i></div><div><div class="wms-kpi__label">Tổng lệnh</div><div id="kpiTotal" class="wms-kpi__value">0</div></div></article>
        <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="clock-3"></i></div><div><div class="wms-kpi__label">Chờ sản xuất</div><div id="kpiWaiting" class="wms-kpi__value">0</div></div></article>
        <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="factory"></i></div><div><div class="wms-kpi__label">Đang sản xuất</div><div id="kpiProducing" class="wms-kpi__value">0</div></div></article>
        <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="package-check"></i></div><div><div class="wms-kpi__label">Đã nhập kho</div><div id="kpiCompleted" class="wms-kpi__value">0</div></div></article>
        <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="triangle-alert"></i></div><div><div class="wms-kpi__label">Trễ hạn</div><div id="kpiOverdue" class="wms-kpi__value">0</div></div></article>
    </section>

    <section class="designer-charts mb-3" aria-label="Biểu đồ tổng quan lệnh dệt">
        <article class="wms-panel designer-chart-card">
            <div class="designer-chart-head">
                <div><h2>Cơ cấu trạng thái</h2><p>Tỷ trọng lệnh trong phạm vi đang lọc</p></div>
                <span class="designer-chart-badge">Hiện tại</span>
            </div>
            <div id="statusChart" class="designer-chart" role="img" aria-label="Biểu đồ cơ cấu trạng thái lệnh dệt"></div>
        </article>
        <article class="wms-panel designer-chart-card">
            <div class="designer-chart-head">
                <div><h2>Xu hướng 8 tuần</h2><p>Lệnh mới và lệnh đã phát sinh nhập kho</p></div>
                <span class="designer-chart-badge">Theo tuần</span>
            </div>
            <div id="trendChart" class="designer-chart" role="img" aria-label="Biểu đồ xu hướng lệnh dệt theo tuần"></div>
        </article>
        <article class="wms-panel designer-chart-card">
            <div class="designer-chart-head">
                <div><h2>Trễ hạn theo khách</h2><p>7 khách có nhiều lệnh trễ nhất</p></div>
                <span class="designer-chart-badge">Cần xử lý</span>
            </div>
            <div id="overdueChart" class="designer-chart" role="img" aria-label="Biểu đồ lệnh trễ hạn theo khách hàng"></div>
        </article>
    </section>

    <section class="wms-panel">
        <div class="designer-filter mb-3">
            <div><label for="keyword">Tìm kiếm</label><input id="keyword" class="form-control" placeholder="Lệnh, mã hàng, khách, PO, design..."></div>
            <div><label for="customer">Khách hàng</label><select id="customer" class="form-select"><option value="">Tất cả khách hàng</option></select></div>
            <div><label for="status">Trạng thái</label><select id="status" class="form-select"><option value="">Tất cả trạng thái</option><option value="waiting">Chờ sản xuất</option><option value="producing">Đang sản xuất</option><option value="partial">Nhập một phần</option><option value="completed">Đã nhập kho</option><option value="overdue">Trễ hạn</option></select></div>
            <button id="clearFilter" class="wms-btn" type="button"><i data-lucide="filter-x"></i>Xóa lọc</button>
        </div>
        <div class="wms-table-wrap">
            <table class="wms-table designer-table">
                <thead><tr><th>Lệnh dệt</th><th>Mã hàng</th><th>Khách hàng</th><th>Ngày ra lệnh</th><th>Hạn</th><th class="text-end">SL lệnh</th><th class="text-end">Đã nhập</th><th class="text-end">Còn lại</th><th>Tiến độ</th><th>Trạng thái</th><th></th></tr></thead>
                <tbody id="rows"><tr><td colspan="11" class="wms-loading">Đang tải...</td></tr></tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center gap-2 mt-3">
            <span id="pageLabel" class="text-secondary small">Trang 1 / 1</span>
            <div class="d-flex gap-2"><button id="prevBtn" class="wms-btn" type="button">Trước</button><button id="nextBtn" class="wms-btn" type="button">Sau</button></div>
        </div>
    </section>
</main>

<script src="https://unpkg.com/lucide@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const rowsEl = document.getElementById('rows');
const keywordEl = document.getElementById('keyword');
const topKeywordEl = document.getElementById('topKeyword');
const customerEl = document.getElementById('customer');
const statusEl = document.getElementById('status');
let page = 1;
let totalPages = 1;
let timer = null;
const designerCharts = {};
const chartFont = '"Plus Jakarta Sans", "Inter", "Segoe UI", Arial, sans-serif';
const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
const num = value => Number(value || 0).toLocaleString('vi-VN', {maximumFractionDigits:3});
const date = value => value ? new Date(value + 'T00:00:00').toLocaleDateString('vi-VN') : '-';

function mountChart(targetId, options) {
    const target = document.getElementById(targetId);
    if (!target) return;
    if (!window.ApexCharts) {
        target.innerHTML = '<div class="wms-empty">Không tải được thư viện biểu đồ.</div>';
        return;
    }
    if (designerCharts[targetId]) designerCharts[targetId].destroy();
    designerCharts[targetId] = new ApexCharts(target, options);
    designerCharts[targetId].render();
}

function renderCharts(data) {
    const statusData = data.status || {labels:[], values:[]};
    const trend = data.trend || [];
    const overdue = data.overdue_customers || {labels:[], values:[]};
    const baseChart = {
        fontFamily: chartFont,
        toolbar: {show:false},
        animations: {enabled:true, easing:'easeinout', speed:550},
        foreColor:'#64748b',
    };

    mountChart('statusChart', {
        chart: {...baseChart, type:'donut', height:235},
        series:(statusData.values || []).map(Number),
        labels:statusData.labels || [],
        colors:['#8aa4c8','#5b8def','#f4b860','#55c6a9'],
        stroke:{width:3, colors:['#fff']},
        legend:{position:'bottom', fontSize:'11px', markers:{width:8,height:8,radius:8}, itemMargin:{horizontal:7,vertical:3}},
        dataLabels:{enabled:false},
        plotOptions:{pie:{donut:{size:'67%',labels:{show:true,name:{show:true,fontSize:'11px'},value:{show:true,fontSize:'24px',fontWeight:800,formatter:num},total:{show:true,label:'Tổng lệnh',fontSize:'11px',formatter:chart => num(chart.globals.seriesTotals.reduce((sum,value)=>sum+value,0))}}}}},
        tooltip:{y:{formatter:value => `${num(value)} lệnh`}},
        noData:{text:'Chưa có dữ liệu'},
    });

    mountChart('trendChart', {
        chart:{...baseChart,type:'area',height:235,zoom:{enabled:false}},
        series:[
            {name:'Lệnh mới',data:trend.map(item=>Number(item.orders || 0))},
            {name:'Có nhập kho',data:trend.map(item=>Number(item.receipts || 0))},
        ],
        colors:['#5b8def','#55c6a9'],
        stroke:{curve:'smooth',width:3},
        fill:{type:'gradient',gradient:{shadeIntensity:1,opacityFrom:.3,opacityTo:.04,stops:[0,90,100]}},
        xaxis:{categories:trend.map(item=>item.label),axisBorder:{show:false},axisTicks:{show:false},labels:{style:{fontSize:'10px'}}},
        yaxis:{min:0,forceNiceScale:true,labels:{formatter:value=>num(Math.round(value)),style:{fontSize:'10px'}}},
        grid:{borderColor:'#e5edf7',strokeDashArray:4,padding:{left:4,right:8}},
        legend:{position:'top',horizontalAlign:'right',fontSize:'11px'},
        dataLabels:{enabled:false},
        tooltip:{shared:true,y:{formatter:value=>`${num(value)} lệnh`}},
        noData:{text:'Chưa có dữ liệu'},
    });

    mountChart('overdueChart', {
        chart:{...baseChart,type:'bar',height:235},
        series:[{name:'Lệnh trễ',data:(overdue.values || []).map(Number)}],
        colors:['#ef7f8c'],
        plotOptions:{bar:{horizontal:true,borderRadius:5,barHeight:'52%',distributed:false}},
        xaxis:{categories:overdue.labels || [],min:0,labels:{formatter:value=>num(Math.round(value)),style:{fontSize:'10px'}}},
        yaxis:{labels:{maxWidth:112,style:{fontSize:'10px',fontWeight:700}}},
        grid:{borderColor:'#e5edf7',strokeDashArray:4,padding:{left:4,right:8}},
        dataLabels:{enabled:true,formatter:value=>num(value),style:{fontSize:'10px',fontWeight:800,colors:['#7f1d2d']},offsetX:4},
        tooltip:{y:{formatter:value=>`${num(value)} lệnh trễ`}},
        noData:{text:'Không có lệnh trễ'},
    });
}

function statusHtml(row) {
    const labels = {waiting:'Chờ sản xuất',producing:'Đang sản xuất',partial:'Nhập một phần',completed:'Đã nhập kho'};
    return `<span class="designer-status designer-status--${esc(row.workflow_status)}">${esc(labels[row.workflow_status] || row.workflow_status)}</span>${row.is_overdue ? '<span class="designer-overdue">TRỄ HẠN</span>' : ''}`;
}

function updateCustomers(values) {
    const selected = customerEl.value;
    customerEl.innerHTML = '<option value="">Tất cả khách hàng</option>' + (values || []).map(value => `<option value="${esc(value)}">${esc(value)}</option>`).join('');
    if (Array.from(customerEl.options).some(option => option.value === selected)) customerEl.value = selected;
}

async function loadData() {
    const params = new URLSearchParams({page, per_page:50});
    if (keywordEl.value.trim()) params.set('keyword', keywordEl.value.trim());
    if (customerEl.value) params.set('customer', customerEl.value);
    if (statusEl.value) params.set('status', statusEl.value);
    rowsEl.innerHTML = '<tr><td colspan="11" class="wms-loading">Đang tải...</td></tr>';
    try {
        const response = await fetch('/api/lenh-det/designer-dashboard?' + params.toString(), {headers:{Accept:'application/json'}});
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || 'Không tải được dashboard.');
        const summary = result.summary || {};
        document.getElementById('kpiTotal').textContent = num(summary.total);
        document.getElementById('kpiWaiting').textContent = num(summary.waiting);
        document.getElementById('kpiProducing').textContent = num((summary.producing || 0) + (summary.partial || 0));
        document.getElementById('kpiCompleted').textContent = num(summary.completed);
        document.getElementById('kpiOverdue').textContent = num(summary.overdue);
        renderCharts(result.charts || {});
        updateCustomers(result.customers);
        page = Number(result.pagination?.page || 1);
        totalPages = Math.max(1, Number(result.pagination?.total_pages || 1));
        document.getElementById('pageLabel').textContent = `Trang ${num(page)} / ${num(totalPages)} · ${num(result.pagination?.total || 0)} lệnh`;
        document.getElementById('prevBtn').disabled = page <= 1;
        document.getElementById('nextBtn').disabled = page >= totalPages;
        rowsEl.innerHTML = (result.data || []).map(row => `<tr>
            <td><div class="designer-order">${esc(row.order_code)}</div><small class="text-secondary">${esc(row.design_code || row.po_number || '')}</small></td>
            <td><strong>${esc(row.item_code || '-')}</strong><small class="d-block text-secondary">${esc(row.item_name || '')}</small></td>
            <td>${esc(row.customer || '-')}</td><td>${date(row.order_date)}</td><td>${date(row.due_date)}</td>
            <td class="text-end fw-bold">${num(row.order_quantity)} ${esc(row.unit || '')}</td><td class="text-end text-success fw-bold">${num(row.received_quantity)}</td><td class="text-end">${num(row.remaining_quantity)}</td>
            <td><div class="designer-progress"><div class="progress"><div class="progress-bar" style="width:${Math.max(0,Math.min(100,Number(row.progress || 0)))}%"></div></div><small>${num(row.progress)}% · ${num(row.receipt_count)} phiếu nhập</small></div></td>
            <td>${statusHtml(row)}</td>
            <td><div class="d-flex gap-1">${row.workflow_status === 'waiting' ? `<button class="wms-btn wms-btn--primary" type="button" data-send="${esc(row.id)}">Gửi SX</button>` : ''}<a class="wms-btn" href="/client/lenh-det?order=${encodeURIComponent(row.order_code)}">Xem lệnh</a></div></td>
        </tr>`).join('') || '<tr><td colspan="11" class="wms-empty">Không có lệnh phù hợp.</td></tr>';
        if (window.lucide) lucide.createIcons();
    } catch (error) {
        rowsEl.innerHTML = `<tr><td colspan="11" class="wms-empty text-danger">${esc(error.message)}</td></tr>`;
    }
}

function queueSearch(source) {
    if (source === topKeywordEl) keywordEl.value = topKeywordEl.value;
    if (source === keywordEl) topKeywordEl.value = keywordEl.value;
    page = 1; clearTimeout(timer); timer = setTimeout(loadData, 250);
}
keywordEl.addEventListener('input', () => queueSearch(keywordEl));
topKeywordEl.addEventListener('input', () => queueSearch(topKeywordEl));
customerEl.addEventListener('change', () => { page=1; loadData(); });
statusEl.addEventListener('change', () => { page=1; loadData(); });
document.getElementById('clearFilter').addEventListener('click', () => { keywordEl.value=''; topKeywordEl.value=''; customerEl.value=''; statusEl.value=''; page=1; loadData(); });
document.getElementById('reloadBtn').addEventListener('click', loadData);
document.getElementById('prevBtn').addEventListener('click', () => { if(page>1){page--;loadData();} });
document.getElementById('nextBtn').addEventListener('click', () => { if(page<totalPages){page++;loadData();} });
rowsEl.addEventListener('click', async event => {
    const button = event.target.closest('[data-send]');
    if (!button || !confirm('Chuyển lệnh này sang Đang sản xuất?')) return;
    button.disabled = true;
    try {
        const response = await fetch(`/api/lenh-det/orders/${encodeURIComponent(button.dataset.send)}/gui-san-xuat`, {method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':csrfToken}});
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || 'Không gửi được lệnh.');
        loadData();
    } catch (error) { alert(error.message); button.disabled=false; }
});
loadData();
setInterval(loadData, 60 * 1000);
if (window.lucide) lucide.createIcons();
</script>
</body>
</html>
