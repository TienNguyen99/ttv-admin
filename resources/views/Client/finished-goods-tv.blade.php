<!DOCTYPE html>
<html lang="vi" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đã nhập thành phẩm</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&family=JetBrains+Mono:wght@500;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <style>
        :root,
        [data-bs-theme="dark"] {
            --tv-bg: #071827;
            --tv-card: #10283c;
            --tv-card-soft: #0c2032;
            --tv-border: #2f5874;
            --tv-text: #e8f6ff;
            --tv-muted: #a9c7d8;
            --tv-accent: #7dd3fc;
            --tv-accent-strong: #38bdf8;
            --tv-warn: #f9c74f;
            --tv-button-text: #042338;
        }
        [data-bs-theme="light"] {
            --tv-bg: #eef8ff;
            --tv-card: #ffffff;
            --tv-card-soft: #e4f3fb;
            --tv-border: #b8d8ea;
            --tv-text: #0b2b40;
            --tv-muted: #527083;
            --tv-accent: #7dd3fc;
            --tv-accent-strong: #0284c7;
            --tv-warn: #b7791f;
            --tv-button-text: #082f49;
        }
        html, body {
            min-height: 100%;
            background: var(--tv-bg);
            color: var(--tv-text);
            font-family: Inter, Arial, sans-serif;
        }
        body {
            overflow: hidden;
        }
        .tv-page {
            height: 100dvh;
            min-height: 560px;
            padding: 12px;
        }
        .tv-card {
            background: var(--tv-card);
            border: 1px solid var(--tv-border);
            border-radius: 6px;
        }
        .tv-card-soft {
            background: var(--tv-card-soft);
        }
        .mono {
            font-family: "JetBrains Mono", monospace;
        }
        .text-accent {
            color: var(--tv-accent) !important;
        }
        .text-muted-tv {
            color: var(--tv-muted) !important;
        }
        .topbar {
            min-height: 42px;
            border-bottom: 1px solid var(--tv-border);
        }
        .clock-time {
            font-size: clamp(28px, 3.5vw, 48px);
            line-height: .9;
            font-weight: 900;
        }
        .metric-card {
            min-height: 90px;
        }
        .metric-value {
            font-size: clamp(28px, 3.2vw, 46px);
            line-height: 1;
            font-weight: 900;
        }
        .icon-box {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            background: color-mix(in srgb, var(--tv-accent) 18%, transparent);
            color: var(--tv-accent);
        }
        .hero-title {
            font-size: clamp(32px, 4.4vw, 64px);
            line-height: 1.05;
            font-weight: 900;
        }
        .hero-line {
            font-size: clamp(18px, 2vw, 30px);
            font-weight: 800;
        }
        .panel-title {
            font-size: 13px;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .activity-row {
            background: var(--tv-card-soft);
            border: 1px solid var(--tv-border);
            border-radius: 6px;
        }
        .activity-time {
            width: 58px;
            color: var(--tv-warn);
            flex: 0 0 58px;
        }
        .ticker {
            min-height: 28px;
            border: 1px solid var(--tv-border);
            border-radius: 6px;
            background: var(--tv-card-soft);
            overflow: hidden;
        }
        .ticker-label {
            background: var(--tv-accent);
            color: var(--tv-button-text);
            font-weight: 900;
        }
        .btn-theme {
            --bs-btn-color: var(--tv-accent-strong);
            --bs-btn-border-color: var(--tv-border);
            --bs-btn-hover-bg: var(--tv-accent);
            --bs-btn-hover-border-color: var(--tv-accent-strong);
            --bs-btn-hover-color: var(--tv-button-text);
            --bs-btn-active-bg: var(--tv-accent-strong);
            --bs-btn-active-border-color: var(--tv-accent-strong);
        }
        .bg-success {
            background-color: var(--tv-accent-strong) !important;
        }
        .text-bg-success {
            color: var(--tv-button-text) !important;
            background-color: var(--tv-accent) !important;
        }
        .border-success {
            border-color: var(--tv-accent-strong) !important;
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 500, 'GRAD' 0, 'opsz' 24;
        }
        @media (max-width: 1199.98px) {
            body {
                overflow: auto;
            }
            .tv-page {
                height: auto;
                min-height: 100dvh;
            }
        }
        @media (max-height: 660px) and (min-width: 1200px) {
            .tv-page {
                padding: 8px 12px;
            }
            .metric-card {
                min-height: 76px;
            }
            .metric-value {
                font-size: 28px;
            }
            .hero-title {
                font-size: clamp(30px, 3.6vw, 50px);
            }
            .hero-line {
                font-size: clamp(16px, 1.8vw, 24px);
            }
            .panel-title {
                font-size: 11px;
            }
            .activity-row {
                padding-top: .45rem !important;
                padding-bottom: .45rem !important;
            }
        }
    </style>
</head>
<body>
<main class="tv-page container-fluid d-flex flex-column gap-2">
    <header class="topbar d-flex align-items-center justify-content-between flex-shrink-0 pb-2">
        <div class="d-flex align-items-center gap-3 min-w-0 mono small text-uppercase text-muted-tv">
            <span class="rounded-circle d-inline-block bg-success" style="width:10px;height:10px;"></span>
            <span>Thành phẩm đã nhập kho</span>
            <span id="syncText">Đang đồng bộ</span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <button id="themeToggle" class="btn btn-theme btn-sm" type="button" aria-label="Đổi giao diện">
                <span id="themeIcon" class="material-symbols-outlined fs-5">dark_mode</span>
            </button>
            <div class="text-end mono">
                <div id="clockTime" class="clock-time">--:--:--</div>
                <div id="clockDate" class="small text-muted-tv">--/--/----</div>
            </div>
        </div>
    </header>

    <section class="row g-2 flex-shrink-0">
        <div class="col-12 col-md-4">
            <article class="tv-card metric-card h-100 p-3 d-flex align-items-center justify-content-between">
                <div>
                    <div class="panel-title mono text-muted-tv">Khách đã nhập</div>
                    <div id="customerCount" class="metric-value text-accent mono">0</div>
                    <div class="small text-muted-tv">Theo dữ liệu hôm nay</div>
                </div>
                <div class="icon-box"><span class="material-symbols-outlined">groups</span></div>
            </article>
        </div>
        <div class="col-12 col-md-4">
            <article class="tv-card metric-card h-100 p-3 d-flex align-items-center justify-content-between">
                <div>
                    <div class="panel-title mono text-muted-tv">Dòng nhập</div>
                    <div id="lineCount" class="metric-value text-accent mono">0</div>
                    <div class="small text-muted-tv">Chi tiết thành phẩm</div>
                </div>
                <div class="icon-box"><span class="material-symbols-outlined">receipt_long</span></div>
            </article>
        </div>
        <div class="col-12 col-md-4">
            <article class="tv-card metric-card h-100 p-3 d-flex align-items-center justify-content-between">
                <div>
                    <div class="panel-title mono text-muted-tv">Tổng SL</div>
                    <div id="totalQuantity" class="metric-value text-accent mono">0</div>
                    <div class="small text-muted-tv">Số lượng đã nhập</div>
                </div>
                <div class="icon-box"><span class="material-symbols-outlined">inventory</span></div>
            </article>
        </div>
    </section>

    <section class="row g-2 flex-grow-1 overflow-hidden">
        <div class="col-12 col-xl-8 d-flex flex-column gap-2 overflow-hidden">
            <section class="tv-card p-4 flex-shrink-0">
                <div class="badge text-bg-success rounded-1 mb-3">
                    <span class="material-symbols-outlined align-middle fs-6">add_circle</span>
                    Đã nhập mới
                </div>
                <div id="heroText" class="hero-title">Đang tải dữ liệu...</div>
                <div id="heroSub" class="mt-2 text-muted-tv">Tự cập nhật mỗi 15 giây</div>
            </section>

            <section class="tv-card flex-grow-1 overflow-hidden d-flex flex-column">
                <div class="px-3 py-2 border-bottom border-secondary-subtle d-flex align-items-center justify-content-between">
                    <div class="panel-title mono">BTP đang sản xuất</div>
                    <span id="btpTag" class="badge text-bg-success rounded-1">0 đang SX</span>
                </div>
                <div id="btpList" class="p-2 row g-2 overflow-hidden">
                    <div class="text-muted-tv text-center py-4">Đang tải BTP...</div>
                </div>
            </section>

            <section class="tv-card flex-shrink-0 overflow-hidden">
                <div class="px-3 py-2 border-bottom border-secondary-subtle d-flex align-items-center justify-content-between">
                    <div class="panel-title mono">Nhóm theo khách hàng</div>
                    <span class="badge text-bg-success rounded-1">DONE</span>
                </div>
                <div id="customerGrid" class="p-2 row g-2">
                    <div class="text-muted-tv text-center py-3">Đang tải dữ liệu...</div>
                </div>
            </section>
        </div>

        <div class="col-12 col-xl-4 d-flex overflow-hidden">
            <aside class="tv-card w-100 d-flex flex-column overflow-hidden">
                <div class="px-3 py-2 border-bottom border-secondary-subtle d-flex align-items-center justify-content-between">
                    <div class="panel-title mono">Hoạt động gần đây</div>
                    <span class="badge text-bg-success rounded-1">LIVE</span>
                </div>
                <div id="activityList" class="p-2 d-flex flex-column gap-2 overflow-hidden flex-grow-1">
                    <div class="text-muted-tv text-center py-4">Đang tải dữ liệu...</div>
                </div>
                <div class="px-3 py-2 border-top border-secondary-subtle d-flex justify-content-between small text-muted-tv mono">
                    <span id="lastSync">Last sync --</span>
                    <span>Internal DB</span>
                </div>
            </aside>
        </div>
    </section>

    <footer class="ticker d-flex align-items-center flex-shrink-0 mono small">
        <div class="ticker-label h-100 px-3 d-flex align-items-center">Trạng thái</div>
        <div id="tickerText" class="px-3 text-truncate flex-grow-1">Đang tải dữ liệu nhập thành phẩm...</div>
        <div class="px-3 d-none d-lg-flex gap-3 text-muted-tv">
            <span><span class="text-accent">●</span> API OK</span>
            <span><span class="text-accent">●</span> DB nội bộ</span>
        </div>
    </footer>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const num = value => Number(value || 0).toLocaleString('vi-VN', { maximumFractionDigits: 3 });
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;' }[c]));
    let lastHeroText = '';

    function applyTheme(theme) {
        const nextTheme = theme === 'light' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-bs-theme', nextTheme);
        document.getElementById('themeIcon').textContent = nextTheme === 'dark' ? 'dark_mode' : 'light_mode';
        localStorage.setItem('finishedGoodsTvTheme', nextTheme);
    }

    function initTheme() {
        const saved = localStorage.getItem('finishedGoodsTvTheme');
        applyTheme(saved || 'dark');
        document.getElementById('themeToggle').addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-bs-theme') || 'dark';
            applyTheme(currentTheme === 'light' ? 'dark' : 'light');
        });
    }

    function todayIso() {
        const now = new Date();
        return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    }

    function tickClock() {
        const now = new Date();
        document.getElementById('clockTime').textContent = now.toLocaleTimeString('vi-VN', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
        document.getElementById('clockDate').textContent = now.toLocaleDateString('vi-VN', { day:'2-digit', month:'2-digit', year:'numeric' });
    }

    function setNumber(id, value) {
        document.getElementById(id).textContent = num(value);
    }

    function renderCustomer(group) {
        return `
            <div class="col-12 col-md-6">
                <div class="activity-row p-2 d-flex align-items-center justify-content-between">
                    <div class="min-w-0">
                        <div class="fw-bold text-truncate">${esc(group.customer)}</div>
                        <div class="small text-muted-tv">${num(group.line_count)} dòng đã nhập</div>
                    </div>
                    <div class="text-accent mono fw-bold">${num(group.total_quantity)}</div>
                </div>
            </div>
        `;
    }

    function renderActivity(item, index) {
        const customer = String(item.customer || '');
        const visibleCustomer = customer.includes('Chưa xác định') ? '' : customer;
        const meta = [visibleCustomer, item.size ? 'Size ' + item.size : '', item.color, item.production_order].filter(Boolean).join(' · ');
        return `
            <div class="activity-row p-2 d-flex align-items-center gap-2 ${index === 0 ? 'border-success' : ''}">
                <div class="activity-time mono fw-bold">${esc(item.time)}</div>
                <div class="min-w-0 flex-grow-1">
                    <div class="fw-bold text-truncate">${esc(item.display_code)}</div>
                    <div class="small text-muted-tv text-truncate">${esc(meta)}</div>
                </div>
                <div class="text-accent mono fw-bold text-nowrap">${num(item.quantity)} ${esc(item.dvt)}</div>
            </div>
        `;
    }

    function renderBtpCard(item) {
        const meta = [item.display_code, item.size ? 'Size ' + item.size : '', item.color].filter(Boolean).join(' · ');
        return `
            <div class="col-12 col-md-4">
                <article class="activity-row p-2 h-100">
                    <div class="text-warning mono fw-bold text-truncate">${esc(item.btp_order_code)}</div>
                    <div class="small text-muted-tv text-truncate">${esc(meta || item.ten_hh || '-')}</div>
                    <div class="small text-muted-tv text-truncate">${num(item.quantity)} ${esc(item.dvt)} · ${esc(item.time)}</div>
                    <span class="badge text-bg-success rounded-1 mt-1">${esc(item.status_label)}</span>
                </article>
            </div>
        `;
    }

    function render(data) {
        const groups = data.data || [];
        const flat = data.flat || [];
        const btp = data.btp || { data: [], summary: {} };
        const btpRows = btp.data || [];
        const btpSummary = btp.summary || {};
        const summary = data.summary || {};
        const newest = flat[0];
        const heroKey = newest ? `${newest.customer}|${newest.display_code}|${newest.quantity}` : 'empty';
        lastHeroText = heroKey;

        document.getElementById('heroText').innerHTML = newest
            ? `<div>${esc(String(newest.customer || '').includes('Chưa xác định') ? 'Mã thành phẩm mới nhập' : newest.customer)}</div><div class="hero-line mono mt-2"><span>${esc(newest.display_code)}</span> <span class="text-accent">${num(newest.quantity)} ${esc(newest.dvt)}</span></div>`
            : 'Hôm nay chưa có nhập thành phẩm';
        document.getElementById('heroSub').textContent = newest ? `Lúc ${newest.time} - đã nhập kho` : 'Chờ phiếu nhập mới';
        document.getElementById('syncText').textContent = summary.last_updated_at ? `SYNC ${summary.last_updated_at}` : 'SYNC --';
        document.getElementById('lastSync').textContent = summary.last_updated_at ? `Last sync ${summary.last_updated_at}` : 'Last sync --';

        setNumber('customerCount', summary.customer_count);
        setNumber('lineCount', summary.line_count);
        setNumber('totalQuantity', summary.total_quantity);

        document.getElementById('customerGrid').innerHTML = groups.length
            ? groups.slice(0, 4).map(renderCustomer).join('')
            : '<div class="text-muted-tv text-center py-3">Hôm nay chưa có khách nào nhập thành phẩm</div>';

        document.getElementById('activityList').innerHTML = flat.length
            ? flat.slice(0, 8).map(renderActivity).join('')
            : '<div class="text-muted-tv text-center py-4">Chưa có hoạt động nhập thành phẩm</div>';

        document.getElementById('btpTag').textContent = `${num(btpSummary.issued_count || 0)} đang SX`;
        document.getElementById('btpList').innerHTML = btpRows.length
            ? btpRows.slice(0, 3).map(renderBtpCard).join('')
            : '<div class="text-muted-tv text-center py-4">Chưa có BTP đang sản xuất</div>';

        document.getElementById('tickerText').textContent = flat.length
            ? flat.slice(0, 8).map(item => `${item.time} ${item.customer} ${item.display_code} ${num(item.quantity)} ${item.dvt}`).join('  ·  ')
            : 'Chưa có dữ liệu nhập thành phẩm hôm nay';
    }

    function loadTvData() {
        fetch('/api/tivi-nhap-thanh-pham?date=' + todayIso() + '&limit=240')
            .then(response => {
                if (!response.ok) throw new Error('Không tải được dữ liệu');
                return response.json();
            })
            .then(render)
            .catch(error => {
                document.getElementById('heroText').textContent = error.message;
            });
    }

    initTheme();
    tickClock();
    loadTvData();
    setInterval(tickClock, 1000);
    setInterval(loadTvData, 15000);
</script>
</body>
</html>
