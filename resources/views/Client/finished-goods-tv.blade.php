<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đã nhập thành phẩm</title>
    <style>
        :root {
            --bg: #071427;
            --panel: #0d2038;
            --panel-2: #102844;
            --line: rgba(203, 213, 225, .18);
            --text: #f8fafc;
            --muted: #9fb0c6;
            --ok: #34d399;
            --blue: #60a5fa;
            --warn: #fbbf24;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            height: 100vh;
            overflow: hidden;
            background: var(--bg);
            color: var(--text);
            font-family: Arial, "Helvetica Neue", sans-serif;
        }
        .screen {
            height: 100vh;
            display: grid;
            grid-template-rows: 72px 118px 1fr;
            gap: 14px;
            padding: 18px;
        }
        .topbar {
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: center;
            gap: 18px;
            padding: 0 4px;
        }
        .stats {
            display: flex;
            gap: 10px;
            align-items: center;
            min-width: 0;
        }
        .stat {
            min-width: 172px;
            padding: 12px 16px;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: var(--panel);
        }
        .stat-label {
            color: var(--muted);
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .stat-value {
            margin-top: 4px;
            font-size: 30px;
            line-height: 1;
            font-weight: 900;
            font-variant-numeric: tabular-nums;
        }
        .clock {
            text-align: right;
            font-weight: 900;
            font-variant-numeric: tabular-nums;
        }
        .clock-time { font-size: 44px; line-height: 1; }
        .clock-date { margin-top: 5px; color: var(--muted); font-size: 14px; font-weight: 700; }
        .latest {
            display: grid;
            grid-template-columns: auto 1fr;
            align-items: center;
            gap: 18px;
            padding: 18px 22px;
            border: 1px solid rgba(52, 211, 153, .36);
            border-radius: 12px;
            background: linear-gradient(90deg, rgba(52, 211, 153, .16), rgba(96, 165, 250, .08));
            transform-origin: center;
        }
        .latest-badge {
            padding: 10px 14px;
            border-radius: 8px;
            background: rgba(52, 211, 153, .18);
            color: #bbf7d0;
            border: 1px solid rgba(52, 211, 153, .42);
            font-size: 18px;
            font-weight: 900;
            white-space: nowrap;
        }
        .latest-text {
            min-width: 0;
            font-size: clamp(30px, 3.4vw, 56px);
            line-height: 1.05;
            font-weight: 900;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .groups {
            min-height: 0;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }
        .group-card {
            min-height: 0;
            display: grid;
            grid-template-rows: auto 1fr;
            border: 1px solid var(--line);
            border-radius: 12px;
            overflow: hidden;
            background: var(--panel);
        }
        .group-head {
            display: grid;
            grid-template-columns: 1fr auto auto;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border-bottom: 1px solid var(--line);
            background: var(--panel-2);
        }
        .customer {
            min-width: 0;
            font-size: 30px;
            line-height: 1.05;
            font-weight: 950;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .status {
            padding: 7px 10px;
            border-radius: 7px;
            background: rgba(52, 211, 153, .16);
            color: #bbf7d0;
            border: 1px solid rgba(52, 211, 153, .38);
            font-size: 13px;
            font-weight: 900;
            white-space: nowrap;
        }
        .total {
            color: var(--warn);
            font-size: 28px;
            font-weight: 950;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }
        .rows {
            min-height: 0;
            overflow: hidden;
        }
        .row {
            display: grid;
            grid-template-columns: 82px minmax(0, 1fr) 166px;
            gap: 12px;
            align-items: center;
            padding: 12px 16px;
            border-bottom: 1px solid rgba(203, 213, 225, .1);
        }
        .row.is-new { background: rgba(52, 211, 153, .08); }
        .time {
            color: var(--blue);
            font-size: 24px;
            font-weight: 950;
            font-variant-numeric: tabular-nums;
        }
        .item-code {
            min-width: 0;
            font-size: 26px;
            line-height: 1.08;
            font-weight: 950;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .item-meta {
            margin-top: 4px;
            color: var(--muted);
            font-size: 14px;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .qty {
            text-align: right;
            color: var(--warn);
            font-size: 28px;
            font-weight: 950;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }
        .empty {
            display: grid;
            place-items: center;
            min-height: 260px;
            border: 1px dashed var(--line);
            border-radius: 12px;
            color: var(--muted);
            font-size: 30px;
            font-weight: 850;
        }
        @media (prefers-reduced-motion: reduce) {
            .group-card, .latest, .row { will-change: auto; }
        }
        @media (max-width: 1180px) {
            body { overflow: auto; height: auto; }
            .screen { height: auto; min-height: 100vh; grid-template-rows: auto auto 1fr; }
            .topbar { grid-template-columns: 1fr; }
            .clock { text-align: left; }
            .stats { flex-wrap: wrap; }
            .groups { grid-template-columns: 1fr; }
            .latest-text { white-space: normal; }
        }
    </style>
</head>
<body>
<main class="screen">
    <header class="topbar">
        <section class="stats">
            <div class="stat">
                <div class="stat-label">Khách đã nhập</div>
                <div id="customerCount" class="stat-value">0</div>
            </div>
            <div class="stat">
                <div class="stat-label">Dòng nhập</div>
                <div id="lineCount" class="stat-value">0</div>
            </div>
            <div class="stat">
                <div class="stat-label">Tổng SL</div>
                <div id="totalQuantity" class="stat-value">0</div>
            </div>
        </section>
        <section class="clock">
            <div id="clockTime" class="clock-time">--:--</div>
            <div id="clockDate" class="clock-date">--/--/----</div>
        </section>
    </header>

    <section class="latest">
        <div class="latest-badge">ĐÃ NHẬP MỚI</div>
        <div id="heroText" class="latest-text">Đang tải dữ liệu...</div>
    </section>

    <section id="customerGrid" class="groups">
        <div class="empty">Đang tải dữ liệu...</div>
    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/animejs@3.2.2/lib/anime.min.js"></script>
<script>
    const num = value => Number(value || 0).toLocaleString('vi-VN', {maximumFractionDigits: 3});
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
    const canMotion = window.matchMedia('(prefers-reduced-motion: no-preference)').matches;
    let firstRender = true;
    let lastHeroSentence = '';

    function todayIso() {
        const now = new Date();
        return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    }

    function tickClock() {
        const now = new Date();
        document.getElementById('clockTime').textContent = now.toLocaleTimeString('vi-VN', {hour:'2-digit', minute:'2-digit'});
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
            duration: 320,
            easing: 'easeOutCubic',
            update(anim) {
                el.textContent = num(anim.animatables[0].target.value);
            }
        });
    }

    function animateScreen(hasNewHero) {
        if (!canMotion || !window.anime) return;
        if (firstRender) {
            anime.timeline({easing: 'easeOutCubic'})
                .add({targets: '.stat, .clock, .latest', opacity: [0, 1], translateY: [-12, 0], duration: 320, delay: anime.stagger(45)})
                .add({targets: '.group-card', opacity: [0, 1], translateY: [18, 0], duration: 360, delay: anime.stagger(45)}, '-=120');
            return;
        }
        anime({targets: '.group-card', opacity: [0.82, 1], translateY: [6, 0], duration: 220, easing: 'easeOutCubic', delay: anime.stagger(28)});
        if (hasNewHero) {
            anime({targets: '.latest', scale: [1.01, 1], duration: 360, easing: 'easeOutCubic'});
            anime({targets: '.row.is-new', backgroundColor: ['rgba(52, 211, 153, .24)', 'rgba(52, 211, 153, .08)'], duration: 760, easing: 'easeOutCubic'});
        }
    }

    function renderCustomer(group, groupIndex) {
        const rows = (group.items || []).slice(0, 8).map((item, index) => `
            <div class="row ${groupIndex === 0 && index === 0 ? 'is-new' : ''}">
                <div class="time">${esc(item.time)}</div>
                <div>
                    <div class="item-code">${esc(item.display_code)}</div>
                    <div class="item-meta">${esc([item.ten_hh, item.size ? 'Size ' + item.size : '', item.color, item.production_order].filter(Boolean).join(' · '))}</div>
                </div>
                <div class="qty">${num(item.quantity)} ${esc(item.dvt)}</div>
            </div>
        `).join('');

        return `
            <article class="group-card">
                <div class="group-head">
                    <div class="customer">${esc(group.customer)}</div>
                    <div class="status">ĐÃ NHẬP</div>
                    <div class="total">${num(group.total_quantity)}</div>
                </div>
                <div class="rows">${rows}</div>
            </article>
        `;
    }

    function render(data) {
        const groups = data.data || [];
        const flat = data.flat || [];
        const summary = data.summary || {};
        const newest = flat[0];
        const nextHero = newest
            ? `Lúc ${newest.time} · ${newest.customer} · ${newest.display_code} · ${num(newest.quantity)} ${newest.dvt}`
            : 'Hôm nay chưa có nhập thành phẩm.';
        const hasNewHero = nextHero !== lastHeroSentence;
        lastHeroSentence = nextHero;

        document.getElementById('heroText').textContent = nextHero;
        animateNumber('customerCount', summary.customer_count);
        animateNumber('lineCount', summary.line_count);
        animateNumber('totalQuantity', summary.total_quantity);

        document.getElementById('customerGrid').innerHTML = groups.length
            ? groups.slice(0, 6).map(renderCustomer).join('')
            : '<div class="empty">Hôm nay chưa có nhập thành phẩm.</div>';

        animateScreen(hasNewHero);
        firstRender = false;
    }

    function loadTvData() {
        fetch('/api/tivi-nhap-thanh-pham?date=' + todayIso() + '&limit=240')
            .then(response => {
                if (!response.ok) throw new Error('Không tải được dữ liệu.');
                return response.json();
            })
            .then(render)
            .catch(error => {
                document.getElementById('heroText').textContent = error.message;
            });
    }

    tickClock();
    loadTvData();
    setInterval(tickClock, 1000);
    setInterval(loadTvData, 15000);
</script>
</body>
</html>
