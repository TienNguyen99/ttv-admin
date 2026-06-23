<!DOCTYPE html>
<html lang="vi" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đã nhập thành phẩm</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;600;700;800;900&family=JetBrains+Mono:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #031427;
            --surface: #102034;
            --surface-low: #0b1c30;
            --surface-high: #1b2b3f;
            --line: #26364a;
            --text: #d3e4fe;
            --muted: #c5c6cd;
            --dim: #8f9097;
            --green: #4edea3;
            --green-2: #00a572;
            --amber: #ffb95f;
            --danger: #ffb4ab;
            --glass: rgba(16, 32, 52, .72);
            --glass-row: rgba(3, 20, 39, .58);
            --head-bg: rgba(11, 28, 48, .72);
            --foot-bg: rgba(38, 54, 74, .3);
            --ticker-bg: #000f21;
            --shadow: rgba(0, 0, 0, .22);
            color-scheme: dark;
        }
        :root[data-theme="light"] {
            --bg: #f4f7fb;
            --surface: #ffffff;
            --surface-low: #eef3f9;
            --surface-high: #e2eaf4;
            --line: #c8d4e2;
            --text: #102034;
            --muted: #4b5f76;
            --dim: #64748b;
            --green: #057a55;
            --green-2: #059669;
            --amber: #b45309;
            --danger: #b42318;
            --glass: rgba(255, 255, 255, .84);
            --glass-row: rgba(241, 245, 249, .9);
            --head-bg: rgba(226, 234, 244, .74);
            --foot-bg: rgba(226, 234, 244, .64);
            --ticker-bg: #eaf0f7;
            --shadow: rgba(15, 23, 42, .12);
            color-scheme: light;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            height: 100vh;
            overflow: hidden;
            background:
                radial-gradient(circle at 12% 8%, rgba(78, 222, 163, .08), transparent 28%),
                radial-gradient(circle at 90% 0%, rgba(255, 185, 95, .08), transparent 24%),
                var(--bg);
            color: var(--text);
            font-family: "Hanken Grotesk", Arial, sans-serif;
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 500, 'GRAD' 0, 'opsz' 24;
        }
        .screen {
            height: 100vh;
            display: grid;
            grid-template-rows: 84px 152px 1fr 42px;
            gap: 18px;
            padding: 24px 30px;
        }
        .topbar {
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: center;
            gap: 20px;
            border-bottom: 1px solid var(--line);
        }
        .live-strip {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
            font-family: "JetBrains Mono", monospace;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .08em;
            font-size: 14px;
            font-weight: 800;
        }
        .live-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--green);
            box-shadow: 0 0 22px rgba(78, 222, 163, .8);
        }
        .clock {
            text-align: right;
            font-family: "JetBrains Mono", monospace;
            font-variant-numeric: tabular-nums;
        }
        .clock-wrap {
            display: flex;
            align-items: center;
            gap: 16px;
            justify-content: flex-end;
        }
        .theme-toggle {
            width: 48px;
            height: 48px;
            display: grid;
            place-items: center;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--glass);
            color: var(--green);
            cursor: pointer;
            transition: border-color .2s ease, background .2s ease, transform .2s ease;
        }
        .theme-toggle:hover {
            border-color: var(--green);
            transform: translateY(-1px);
        }
        .theme-toggle .material-symbols-outlined { font-size: 26px; }
        .clock-time {
            color: var(--text);
            font-size: 52px;
            line-height: .9;
            font-weight: 800;
        }
        .clock-date {
            margin-top: 8px;
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .metrics {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }
        .glass-card {
            background: var(--glass);
            border: 1px solid var(--line);
            border-radius: 8px;
            backdrop-filter: blur(12px);
            box-shadow: 0 20px 50px var(--shadow);
        }
        .metric {
            padding: 22px 26px;
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: center;
            gap: 18px;
        }
        .metric-label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--muted);
            font-family: "JetBrains Mono", monospace;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .metric-value {
            margin-top: 12px;
            color: var(--green);
            font-size: 64px;
            line-height: .9;
            font-weight: 900;
            font-variant-numeric: tabular-nums;
        }
        .metric-note {
            margin-top: 10px;
            color: var(--dim);
            font-family: "JetBrains Mono", monospace;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .metric-icon {
            width: 56px;
            height: 56px;
            display: grid;
            place-items: center;
            border-radius: 8px;
            background: rgba(78, 222, 163, .1);
            color: var(--green);
        }
        .metric-icon .material-symbols-outlined { font-size: 34px; }
        .content {
            min-height: 0;
            display: grid;
            grid-template-columns: 7fr 5fr;
            gap: 18px;
        }
        .main-panel {
            min-height: 0;
            display: grid;
            grid-template-rows: 230px 168px 1fr;
            gap: 18px;
        }
        .latest {
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 28px;
            border: 2px dashed var(--line);
        }
        .latest::before {
            content: "";
            position: absolute;
            inset: 14px;
            border-radius: 8px;
            background: linear-gradient(90deg, transparent, rgba(78, 222, 163, .05), transparent);
            pointer-events: none;
        }
        .latest-inner {
            position: relative;
            width: 100%;
            display: grid;
            gap: 18px;
            justify-items: center;
            text-align: center;
        }
        .latest-badge {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 9px 18px;
            border-radius: 999px;
            background: var(--green);
            color: #002113;
            font-family: "JetBrains Mono", monospace;
            font-size: 16px;
            font-weight: 900;
            letter-spacing: .06em;
            text-transform: uppercase;
        }
        .latest-text {
            max-width: 100%;
            color: var(--text);
            font-size: clamp(34px, 4.5vw, 78px);
            line-height: 1.05;
            font-weight: 900;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .latest-sub {
            color: var(--muted);
            font-size: 20px;
            font-weight: 700;
        }
        .customer-panel {
            min-height: 0;
            display: grid;
            grid-template-rows: auto 1fr;
        }
        .panel-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            padding: 18px 22px;
            border-bottom: 1px solid var(--line);
            background: var(--head-bg);
        }
        .panel-label {
            display: flex;
            align-items: center;
            gap: 9px;
            color: var(--text);
            font-family: "JetBrains Mono", monospace;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: .07em;
            text-transform: uppercase;
        }
        .panel-tag {
            padding: 5px 10px;
            border-radius: 4px;
            background: rgba(78, 222, 163, .1);
            color: var(--green);
            font-family: "JetBrains Mono", monospace;
            font-size: 12px;
            font-weight: 900;
        }
        .customers {
            min-height: 0;
            overflow: hidden;
            padding: 16px;
            display: grid;
            gap: 12px;
            align-content: start;
        }
        .customer-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 16px;
            align-items: center;
            padding: 16px;
            border: 1px solid rgba(38, 54, 74, .9);
            border-radius: 6px;
            background: var(--glass-row);
        }
        .customer-name {
            min-width: 0;
            color: var(--text);
            font-size: 30px;
            line-height: 1;
            font-weight: 900;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .customer-meta {
            margin-top: 7px;
            color: var(--muted);
            font-family: "JetBrains Mono", monospace;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .customer-total {
            color: var(--green);
            font-family: "JetBrains Mono", monospace;
            font-size: 30px;
            font-weight: 900;
            white-space: nowrap;
        }
        .activity {
            min-height: 0;
            display: grid;
            grid-template-rows: auto 1fr auto;
        }
        .activity-list {
            min-height: 0;
            overflow: hidden;
            padding: 18px;
            display: grid;
            gap: 10px;
            align-content: start;
        }
        .activity-item {
            display: grid;
            grid-template-columns: 76px 1fr auto;
            gap: 14px;
            align-items: center;
            padding: 14px 0;
            border-bottom: 1px solid rgba(38, 54, 74, .72);
        }
        .activity-item.is-new { background: rgba(78, 222, 163, .055); }
        .activity-time {
            color: var(--amber);
            font-family: "JetBrains Mono", monospace;
            font-size: 20px;
            font-weight: 900;
        }
        .activity-code {
            min-width: 0;
            color: var(--text);
            font-size: 24px;
            font-weight: 900;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .activity-meta {
            margin-top: 4px;
            color: var(--muted);
            font-size: 14px;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .activity-qty {
            color: var(--green);
            font-family: "JetBrains Mono", monospace;
            font-size: 22px;
            font-weight: 900;
            white-space: nowrap;
        }
        .activity-foot {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 22px;
            border-top: 1px solid var(--line);
            background: var(--foot-bg);
            color: var(--muted);
            font-family: "JetBrains Mono", monospace;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .btp-panel {
            min-height: 0;
            display: grid;
            grid-template-rows: auto 1fr;
            overflow: hidden;
        }
        .btp-list {
            min-height: 0;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            padding: 12px;
            overflow: hidden;
        }
        .btp-card {
            min-width: 0;
            padding: 12px;
            border: 1px solid rgba(255, 185, 95, .24);
            border-radius: 6px;
            background: rgba(255, 185, 95, .07);
        }
        .btp-card.is-draft {
            border-color: rgba(197, 198, 205, .24);
            background: rgba(197, 198, 205, .06);
        }
        .btp-code {
            color: var(--amber);
            font-family: "JetBrains Mono", monospace;
            font-size: 18px;
            font-weight: 900;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .btp-meta {
            margin-top: 6px;
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .btp-status {
            display: inline-flex;
            margin-top: 8px;
            padding: 4px 8px;
            border-radius: 999px;
            background: rgba(78, 222, 163, .12);
            color: var(--green);
            font-family: "JetBrains Mono", monospace;
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
        }
        .btp-card.is-draft .btp-status {
            background: rgba(197, 198, 205, .14);
            color: var(--muted);
        }
        .ticker {
            min-width: 0;
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 999px;
            background: var(--ticker-bg);
        }
        .ticker-label {
            height: 100%;
            display: flex;
            align-items: center;
            padding: 0 18px;
            background: var(--green);
            color: #002113;
            font-family: "JetBrains Mono", monospace;
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
        }
        .ticker-track {
            overflow: hidden;
            color: var(--muted);
            font-family: "JetBrains Mono", monospace;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .ticker-text {
            display: inline-block;
            padding-left: 100%;
            animation: ticker 34s linear infinite;
        }
        .ticker-status {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 0 18px;
            border-left: 1px solid var(--line);
            color: var(--muted);
            font-family: "JetBrains Mono", monospace;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .ok-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            margin-right: 6px;
            border-radius: 50%;
            background: var(--green);
        }
        .empty {
            height: 100%;
            min-height: 260px;
            display: grid;
            place-items: center;
            color: var(--dim);
            font-family: "JetBrains Mono", monospace;
            font-size: 18px;
            font-weight: 800;
            text-transform: uppercase;
            text-align: center;
            opacity: .65;
        }
        @keyframes ticker {
            0% { transform: translateX(0); }
            100% { transform: translateX(-100%); }
        }
        @media (prefers-reduced-motion: reduce) {
            .ticker-text { animation: none; padding-left: 16px; }
        }
        @media (max-width: 1180px) {
            body { height: auto; overflow: auto; }
            .screen { height: auto; min-height: 100vh; grid-template-rows: auto auto auto auto; }
            .topbar, .content { grid-template-columns: 1fr; }
            .metrics { grid-template-columns: 1fr; }
            .clock { text-align: left; }
            .latest-text { white-space: normal; }
            .ticker { grid-template-columns: auto 1fr; }
            .ticker-status { display: none; }
        }
    </style>
</head>
<body>
<main class="screen">
    <header class="topbar">
        <div class="live-strip">
            <span class="live-dot"></span>
            <span>Thành phẩm đã nhập kho</span>
            <span id="syncText">Đang đồng bộ</span>
        </div>
        <div class="clock-wrap">
            <button id="themeToggle" class="theme-toggle" type="button" aria-label="Doi giao dien sang toi">
                <span id="themeIcon" class="material-symbols-outlined">dark_mode</span>
            </button>
            <div class="clock">
                <div id="clockTime" class="clock-time">--:--:--</div>
                <div id="clockDate" class="clock-date">--/--/----</div>
            </div>
        </div>
    </header>

    <section class="metrics">
        <article class="glass-card metric">
            <div>
                <div class="metric-label"><span class="material-symbols-outlined">person_check</span>Khách đã nhập</div>
                <div id="customerCount" class="metric-value">0</div>
                <div class="metric-note">Theo dữ liệu hôm nay</div>
            </div>
            <div class="metric-icon"><span class="material-symbols-outlined">groups</span></div>
        </article>
        <article class="glass-card metric">
            <div>
                <div class="metric-label"><span class="material-symbols-outlined">list_alt</span>Dòng nhập</div>
                <div id="lineCount" class="metric-value">0</div>
                <div class="metric-note">Chi tiết thành phẩm</div>
            </div>
            <div class="metric-icon"><span class="material-symbols-outlined">receipt_long</span></div>
        </article>
        <article class="glass-card metric">
            <div>
                <div class="metric-label"><span class="material-symbols-outlined">inventory_2</span>Tổng SL</div>
                <div id="totalQuantity" class="metric-value">0</div>
                <div class="metric-note">Số lượng đã nhập</div>
            </div>
            <div class="metric-icon"><span class="material-symbols-outlined">inventory</span></div>
        </article>
    </section>

    <section class="content">
        <div class="main-panel">
            <section class="glass-card latest">
                <div class="latest-inner">
                    <div class="latest-badge"><span class="material-symbols-outlined">add_circle</span>Đã nhập mới</div>
                    <div id="heroText" class="latest-text">Đang tải dữ liệu...</div>
                    <div id="heroSub" class="latest-sub">Tự cập nhật mỗi 15 giây</div>
                </div>
            </section>

            <section class="glass-card btp-panel">
                <div class="panel-head">
                    <div class="panel-label"><span class="material-symbols-outlined">precision_manufacturing</span>BTP đang sản xuất</div>
                    <div id="btpTag" class="panel-tag">0 lệnh</div>
                </div>
                <div id="btpList" class="btp-list">
                    <div class="empty">Đang tải BTP...</div>
                </div>
            </section>

            <section class="glass-card customer-panel">
                <div class="panel-head">
                    <div class="panel-label"><span class="material-symbols-outlined">business_center</span>Nhóm theo khách hàng</div>
                    <div class="panel-tag">DONE</div>
                </div>
                <div id="customerGrid" class="customers">
                    <div class="empty">Đang tải dữ liệu...</div>
                </div>
            </section>
        </div>

        <aside class="glass-card activity">
            <div class="panel-head">
                <div class="panel-label"><span class="material-symbols-outlined">history</span>Hoạt động gần đây</div>
                <div class="panel-tag">LIVE</div>
            </div>
            <div id="activityList" class="activity-list">
                <div class="empty">Đang tải dữ liệu...</div>
            </div>
            <div class="activity-foot">
                <span id="lastSync">Last sync --</span>
                <span>Internal DB</span>
            </div>
        </aside>
    </section>

    <footer class="ticker">
        <div class="ticker-label">Trạng thái</div>
        <div class="ticker-track"><div id="tickerText" class="ticker-text">Đang tải dữ liệu nhập thành phẩm...</div></div>
        <div class="ticker-status"><span><i class="ok-dot"></i>API OK</span><span><i class="ok-dot"></i>DB nội bộ</span></div>
    </footer>
</main>

<script src="https://cdn.jsdelivr.net/npm/animejs@3.2.2/lib/anime.min.js"></script>
<script>
    const num = value => Number(value || 0).toLocaleString('vi-VN', {maximumFractionDigits: 3});
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
    const canMotion = window.matchMedia('(prefers-reduced-motion: no-preference)').matches;
    let firstRender = true;
    let lastHeroText = '';

    function applyTheme(theme) {
        const nextTheme = theme === 'light' ? 'light' : 'dark';
        document.documentElement.dataset.theme = nextTheme;
        document.documentElement.classList.toggle('dark', nextTheme === 'dark');
        document.getElementById('themeIcon').textContent = nextTheme === 'dark' ? 'dark_mode' : 'light_mode';
        localStorage.setItem('finishedGoodsTvTheme', nextTheme);
    }

    function initTheme() {
        const saved = localStorage.getItem('finishedGoodsTvTheme');
        const preferred = window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
        applyTheme(saved || preferred);
        document.getElementById('themeToggle').addEventListener('click', () => {
            applyTheme(document.documentElement.dataset.theme === 'light' ? 'dark' : 'light');
        });
    }

    function todayIso() {
        const now = new Date();
        return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    }

    function tickClock() {
        const now = new Date();
        document.getElementById('clockTime').textContent = now.toLocaleTimeString('vi-VN', {hour:'2-digit', minute:'2-digit', second:'2-digit'});
        document.getElementById('clockDate').textContent = now.toLocaleDateString('vi-VN', {day:'2-digit', month:'2-digit', year:'numeric'});
    }

    function animateNumber(id, nextValue) {
        const el = document.getElementById(id);
        const previous = Number(el.dataset.value || 0);
        const next = Number(nextValue || 0);
        el.dataset.value = next;
        if (!canMotion || !window.anime) {
            el.textContent = num(next);
            return;
        }
        anime({
            targets: {value: previous},
            value: next,
            duration: 360,
            easing: 'easeOutCubic',
            update(anim) {
                el.textContent = num(anim.animatables[0].target.value);
            }
        });
    }

    function animateUi(hasNewItem) {
        if (!canMotion || !window.anime) return;
        if (firstRender) {
            anime.timeline({easing: 'easeOutCubic'})
                .add({targets: '.topbar, .metric', opacity: [0, 1], translateY: [-14, 0], duration: 360, delay: anime.stagger(45)})
                .add({targets: '.latest, .btp-panel, .customer-panel, .activity, .ticker', opacity: [0, 1], translateY: [20, 0], duration: 420, delay: anime.stagger(60)}, '-=120');
            return;
        }
        anime({targets: '.customer-row, .activity-item, .btp-card', opacity: [0.82, 1], translateY: [6, 0], duration: 220, easing: 'easeOutCubic', delay: anime.stagger(20)});
        if (hasNewItem) {
            anime({targets: '.latest', scale: [1.01, 1], duration: 360, easing: 'easeOutCubic'});
            anime({targets: '.activity-item.is-new', backgroundColor: ['rgba(78, 222, 163, .18)', 'rgba(78, 222, 163, .055)'], duration: 820, easing: 'easeOutCubic'});
        }
    }

    function renderCustomer(group) {
        return `
            <div class="customer-row">
                <div>
                    <div class="customer-name">${esc(group.customer)}</div>
                    <div class="customer-meta">${num(group.line_count)} dòng đã nhập</div>
                </div>
                <div class="customer-total">${num(group.total_quantity)}</div>
            </div>
        `;
    }

    function renderActivity(item, index) {
        const meta = [item.customer, item.size ? 'Size ' + item.size : '', item.color, item.production_order].filter(Boolean).join(' · ');
        return `
            <div class="activity-item ${index === 0 ? 'is-new' : ''}">
                <div class="activity-time">${esc(item.time)}</div>
                <div>
                    <div class="activity-code">${esc(item.display_code)}</div>
                    <div class="activity-meta">${esc(meta)}</div>
                </div>
                <div class="activity-qty">${num(item.quantity)} ${esc(item.dvt)}</div>
            </div>
        `;
    }

    function renderBtpCard(item) {
        const meta = [item.display_code, item.size ? 'Size ' + item.size : '', item.color].filter(Boolean).join(' · ');
        return `
            <article class="btp-card ${item.status === 'draft' ? 'is-draft' : ''}">
                <div class="btp-code">${esc(item.btp_order_code)}</div>
                <div class="btp-meta">${esc(meta || item.ten_hh || '-')}</div>
                <div class="btp-meta">${num(item.quantity)} ${esc(item.dvt)} · ${esc(item.time)}</div>
                <span class="btp-status">${esc(item.status_label)}</span>
            </article>
        `;
    }

    function render(data) {
        const groups = data.data || [];
        const flat = data.flat || [];
        const btp = data.btp || {data: [], summary: {}};
        const btpRows = btp.data || [];
        const btpSummary = btp.summary || {};
        const summary = data.summary || {};
        const newest = flat[0];
        const nextHero = newest
            ? `${newest.customer} · ${newest.display_code} · ${num(newest.quantity)} ${newest.dvt}`
            : 'Hôm nay chưa có nhập thành phẩm';
        const hasNewItem = nextHero !== lastHeroText;
        lastHeroText = nextHero;

        document.getElementById('heroText').textContent = nextHero;
        document.getElementById('heroSub').textContent = newest ? `Lúc ${newest.time} · đã nhập kho` : 'Chờ phiếu nhập mới';
        document.getElementById('syncText').textContent = summary.last_updated_at ? `SYNC ${summary.last_updated_at}` : 'SYNC --';
        document.getElementById('lastSync').textContent = summary.last_updated_at ? `Last sync ${summary.last_updated_at}` : 'Last sync --';

        animateNumber('customerCount', summary.customer_count);
        animateNumber('lineCount', summary.line_count);
        animateNumber('totalQuantity', summary.total_quantity);

        document.getElementById('customerGrid').innerHTML = groups.length
            ? groups.slice(0, 7).map(renderCustomer).join('')
            : '<div class="empty">Hôm nay chưa có khách nào nhập thành phẩm</div>';

        document.getElementById('activityList').innerHTML = flat.length
            ? flat.slice(0, 10).map(renderActivity).join('')
            : '<div class="empty">Chưa có hoạt động nhập thành phẩm</div>';

        document.getElementById('btpTag').textContent = `${num(btpSummary.issued_count || 0)} đang SX`;
        document.getElementById('btpList').innerHTML = btpRows.length
            ? btpRows.slice(0, 3).map(renderBtpCard).join('')
            : '<div class="empty">Chưa có BTP đang sản xuất</div>';

        document.getElementById('tickerText').textContent = flat.length
            ? flat.slice(0, 8).map(item => `${item.time} ${item.customer} ${item.display_code} ${num(item.quantity)} ${item.dvt}`).join('  •  ')
            : 'Chưa có dữ liệu nhập thành phẩm hôm nay';

        animateUi(hasNewItem);
        firstRender = false;
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
