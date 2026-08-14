<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $location->location_code }} - Hàng tại vị trí</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --ink: #102a43;
            --muted: #627d98;
            --line: #d7e6f7;
            --blue: #2563eb;
            --blue-soft: #eaf3ff;
            --green: #087f5b;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f8fd; color: var(--ink); font-family: Arial, sans-serif; }
        .page-shell { width: min(1120px, 100%); margin: 0 auto; padding: 14px; }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 44px; }
        .back-link { display: inline-flex; align-items: center; gap: 7px; color: #486581; font-size: 14px; font-weight: 700; text-decoration: none; }
        .back-link svg { width: 18px; height: 18px; }
        .location-code { color: var(--blue); font-size: 15px; font-weight: 800; }
        .location-hero { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin: 8px 0 12px; padding: 18px; border: 1px solid #bdd8fb; border-radius: 12px; background: var(--blue-soft); }
        .location-eyebrow { color: #52749a; font-size: 12px; font-weight: 800; text-transform: uppercase; }
        .location-title { margin: 2px 0 0; color: #153e75; font-size: clamp(30px, 8vw, 46px); font-weight: 900; line-height: 1; }
        .location-name { margin-top: 7px; color: #486581; font-size: 14px; }
        .location-icon { display: grid; width: 52px; height: 52px; flex: 0 0 52px; place-items: center; border-radius: 12px; background: #fff; color: var(--blue); box-shadow: 0 8px 24px rgba(37, 99, 235, .1); }
        .location-icon svg { width: 27px; height: 27px; }
        .summary-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin-bottom: 12px; }
        .summary-box { min-width: 0; padding: 12px 14px; border: 1px solid var(--line); border-radius: 10px; background: #fff; }
        .summary-value { font-size: 22px; font-weight: 900; line-height: 1.15; overflow-wrap: anywhere; }
        .summary-label { margin-top: 4px; color: var(--muted); font-size: 12px; }
        .search-panel { position: sticky; top: 0; z-index: 5; margin-bottom: 12px; padding: 10px; border: 1px solid var(--line); border-radius: 10px; background: rgba(244, 248, 253, .96); backdrop-filter: blur(8px); }
        .search-wrap { position: relative; }
        .search-wrap svg { position: absolute; top: 12px; left: 12px; width: 19px; height: 19px; color: #829ab1; pointer-events: none; }
        .form-control { min-height: 44px; padding-left: 40px; border-color: #b8cce3; border-radius: 8px; font-size: 15px; }
        .form-control:focus { border-color: #60a5fa; box-shadow: 0 0 0 3px rgba(37, 99, 235, .14); }
        .item-list { display: grid; grid-template-columns: minmax(0, 1fr); gap: 12px; }
        .item-card { min-width: 0; overflow: hidden; border: 1px solid var(--line); border-radius: 12px; background: #fff; box-shadow: 0 5px 18px rgba(31, 78, 121, .06); }
        .item-media { position: relative; display: grid; width: 100%; height: 230px; place-items: center; overflow: hidden; border-bottom: 1px solid var(--line); background: #edf5ff; }
        .item-media img { width: 100%; height: 100%; padding: 10px; object-fit: contain; background: #fff; }
        .image-fallback { display: grid; width: 100%; height: 100%; place-items: center; color: #829ab1; background: var(--fallback, #edf5ff); }
        .image-fallback svg { width: 54px; height: 54px; opacity: .75; }
        .item-body { padding: 15px; }
        .item-code { color: #1454b8; font-size: 20px; font-weight: 900; line-height: 1.2; overflow-wrap: anywhere; }
        .item-name { min-height: 22px; margin-top: 5px; color: #334e68; font-size: 15px; font-weight: 700; line-height: 1.4; overflow-wrap: anywhere; }
        .stock-line { display: flex; align-items: end; justify-content: space-between; gap: 12px; margin-top: 14px; padding: 12px; border-radius: 9px; background: #eefbf6; }
        .stock-label { color: #52796f; font-size: 12px; font-weight: 700; }
        .stock-value { color: var(--green); font-size: 25px; font-weight: 900; line-height: 1; text-align: right; }
        .stock-unit { margin-left: 4px; color: #236a55; font-size: 13px; font-weight: 800; }
        .stock-empty { color: #b45309; }
        .norm-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 7px; margin-top: 10px; }
        .norm-item { padding: 9px 10px; border: 1px solid #cfe2f8; border-radius: 8px; background: #f7fbff; }
        .norm-label { color: var(--muted); font-size: 11px; }
        .norm-value { margin-top: 2px; color: #173f6b; font-size: 15px; font-weight: 900; }
        .item-meta { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
        .meta-chip { display: inline-flex; align-items: center; gap: 6px; min-height: 28px; padding: 4px 8px; border-radius: 6px; background: #f0f4f8; color: #486581; font-size: 12px; }
        .color-swatch { width: 18px; height: 18px; border: 1px solid rgba(15, 23, 42, .16); border-radius: 4px; background: var(--swatch, #e2e8f0); }
        .empty-state, .loading { grid-column: 1 / -1; padding: 52px 18px; border: 1px dashed #b8cce3; border-radius: 12px; color: var(--muted); text-align: center; background: #fff; }
        @media (min-width: 720px) {
            .page-shell { padding: 20px; }
            .item-list { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .item-media { height: 260px; }
        }
        @media (max-width: 480px) {
            .summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .summary-box:last-child { grid-column: 1 / -1; }
            .summary-box { padding: 10px; }
            .summary-value { font-size: 18px; }
            .summary-label { font-size: 11px; }
            .item-media { height: 210px; }
        }
        @media (prefers-reduced-motion: reduce) { * { scroll-behavior: auto !important; } }
    </style>
</head>
<body>
    <main class="page-shell">
        <header class="topbar">
            <a class="back-link" href="{{ url('/client/kiem-ton-kho?location_code=' . urlencode($location->location_code)) }}">
                <i data-lucide="arrow-left"></i>Kiểm tồn kho
            </a>
            <span class="location-code">{{ $location->location_code }}</span>
        </header>

        <section class="location-hero">
            <div>
                <div class="location-eyebrow">Vị trí kho</div>
                <h1 class="location-title">{{ $location->location_code }}</h1>
            </div>
            <div class="location-icon" aria-hidden="true"><i data-lucide="map-pin"></i></div>
        </section>

        <section class="summary-grid" aria-label="Tổng quan vị trí">
            <div class="summary-box"><div id="itemCount" class="summary-value">0</div><div class="summary-label">Mã hàng</div></div>
            <div class="summary-box"><div id="packageCount" class="summary-value">0</div><div class="summary-label">Kiện hàng</div></div>
            <div class="summary-box"><div id="totalQuantity" class="summary-value">0</div><div class="summary-label">Tổng số lượng</div></div>
        </section>

        <section class="search-panel">
            <div class="search-wrap">
                <i data-lucide="search"></i>
                <input id="itemSearch" class="form-control" autocomplete="off" placeholder="Tìm mã hoặc tên hàng">
            </div>
        </section>

        <section id="itemList" class="item-list" aria-live="polite">
            <div class="loading">Đang tải hàng tại vị trí...</div>
        </section>
    </main>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        const locationCode = @json($location->location_code);
        let items = [];

        function escapeHtml(value) {
            const element = document.createElement('div');
            element.textContent = value == null ? '' : String(value);
            return element.innerHTML;
        }

        function safeImageUrl(value) {
            const url = String(value || '').trim();
            return /^(https?:\/\/|\/)/i.test(url) ? escapeHtml(url) : '';
        }

        function formatNumber(value) {
            return Number(value || 0).toLocaleString('vi-VN', { maximumFractionDigits: 4 });
        }

        function renderNorms(norms) {
            if (!Array.isArray(norms) || !norms.length) return '';
            return `<div class="norm-list">${norms.map(norm => `
                <div class="norm-item">
                    <div class="norm-label">${escapeHtml(norm.label)}</div>
                    <div class="norm-value">${escapeHtml(norm.value)}${norm.unit ? ` ${escapeHtml(norm.unit)}` : ''}</div>
                </div>`).join('')}</div>`;
        }

        function renderItems() {
            const keyword = document.getElementById('itemSearch').value.trim().toLocaleLowerCase('vi');
            const filtered = items.filter(item => [
                item.internal_item_code, item.catalog_item_name, item.ma_sp,
                item.color_name, item.color, item.pantone_code, item.size,
            ].join(' ').toLocaleLowerCase('vi').includes(keyword));

            document.getElementById('itemList').innerHTML = filtered.map(item => {
                const image = safeImageUrl(item.image_url);
                const swatch = /^#[0-9a-f]{6}$/i.test(item.pantone_hex || '') ? item.pantone_hex : '#edf5ff';
                const hasStock = !item.catalog_only && Number(item.total_quantity || 0) !== 0;
                return `
                    <article class="item-card">
                        <div class="item-media">
                            ${image ? `<img src="${image}" alt="Hình ${escapeHtml(item.internal_item_code)}" loading="lazy" onerror="this.hidden=true;this.nextElementSibling.hidden=false">` : ''}
                            <div class="image-fallback" style="--fallback:${escapeHtml(swatch)}" ${image ? 'hidden' : ''}><i data-lucide="image"></i></div>
                        </div>
                        <div class="item-body">
                            <div class="item-code">${escapeHtml(item.internal_item_code || item.ma_sp || 'Chưa có mã')}</div>
                            <div class="item-name">${escapeHtml(item.catalog_item_name || 'Chưa có tên hàng trong danh mục')}</div>
                            <div class="stock-line">
                                <div class="stock-label">Số lượng tại vị trí</div>
                                <div class="stock-value ${hasStock ? '' : 'stock-empty'}">
                                    ${hasStock ? formatNumber(item.total_quantity) : 'Chưa nhập'}
                                    ${hasStock && item.catalog_unit ? `<span class="stock-unit">${escapeHtml(item.catalog_unit)}</span>` : ''}
                                </div>
                            </div>
                            ${renderNorms(item.norms)}
                            <div class="item-meta">
                                ${item.catalog_unit ? `<span class="meta-chip">ĐVT ${escapeHtml(item.catalog_unit)}</span>` : ''}
                                ${(item.color_name || item.color || item.pantone_code) ? `<span class="meta-chip"><span class="color-swatch" style="--swatch:${escapeHtml(swatch)}"></span>${escapeHtml(item.color_name || item.color || item.pantone_code)}</span>` : ''}
                                ${item.size ? `<span class="meta-chip">Size ${escapeHtml(item.size)}</span>` : ''}
                                ${item.package_count ? `<span class="meta-chip">${formatNumber(item.package_count)} kiện</span>` : ''}
                            </div>
                        </div>
                    </article>`;
            }).join('') || '<div class="empty-state">Không có mặt hàng phù hợp tại vị trí này.</div>';

            if (window.lucide) lucide.createIcons();
        }

        function loadItems() {
            fetch(`/api/kiem-ton-kho/noi-dung-vi-tri?location_code=${encodeURIComponent(locationCode)}`, {
                headers: { Accept: 'application/json' },
            })
                .then(response => {
                    if (!response.ok) throw new Error('Không tải được dữ liệu vị trí.');
                    return response.json();
                })
                .then(result => {
                    items = Array.isArray(result.data) ? result.data : [];
                    document.getElementById('itemCount').textContent = formatNumber(result.summary?.item_count);
                    document.getElementById('packageCount').textContent = formatNumber(result.summary?.package_count);
                    document.getElementById('totalQuantity').textContent = formatNumber(result.summary?.total_quantity);
                    renderItems();
                })
                .catch(error => {
                    document.getElementById('itemList').innerHTML = `<div class="empty-state text-danger">${escapeHtml(error.message)}</div>`;
                });
        }

        document.getElementById('itemSearch').addEventListener('input', renderItems);
        loadItems();
        if (window.lucide) lucide.createIcons();
    </script>
</body>
</html>
