<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $location->location_code }} - Chi tiet vi tri</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --ink: #0f172a; --muted: #64748b; --line: #e2e8f0; --accent: #1d4ed8; }
        body { margin: 0; background: #f8fafc; color: var(--ink); font-family: Arial, sans-serif; }
        .page-shell { max-width: 1080px; margin: 0 auto; padding: 16px; }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
        .back-link { display: inline-flex; align-items: center; gap: 6px; color: #475569; font-size: 13px; text-decoration: none; }
        .back-link svg { width: 16px; height: 16px; }
        .location-card { padding: 18px; border: 1px solid #bfdbfe; border-radius: 8px; background: #eff6ff; }
        .location-code { margin: 0; color: #1e3a8a; font-size: clamp(26px, 7vw, 38px); font-weight: 800; }
        .location-name { margin-top: 3px; color: #475569; font-size: 14px; }
        .warehouse-badge { display: inline-block; margin-top: 10px; padding: 4px 8px; border: 1px solid #93c5fd; border-radius: 5px; color: #1d4ed8; font-size: 12px; font-weight: 700; }
        .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin: 12px 0; }
        .summary-box { padding: 12px; border: 1px solid var(--line); border-radius: 8px; background: #fff; }
        .summary-value { font-size: 23px; font-weight: 800; }
        .summary-label { color: var(--muted); font-size: 12px; }
        .panel { border: 1px solid var(--line); border-radius: 8px; background: #fff; }
        .panel-header { padding: 12px; border-bottom: 1px solid var(--line); }
        .search-wrap { position: relative; }
        .search-wrap svg { position: absolute; top: 12px; left: 11px; width: 18px; height: 18px; color: #94a3b8; }
        .form-control { min-height: 42px; padding-left: 37px; border-color: #cbd5e1; border-radius: 6px; font-size: 14px; }
        .item-list { display: grid; gap: 8px; padding: 10px; }
        .item-card { padding: 11px; border: 1px solid var(--line); border-radius: 7px; background: #fff; }
        .item-head { display: flex; justify-content: space-between; gap: 10px; }
        .item-code { color: var(--accent); font-size: 15px; font-weight: 800; overflow-wrap: anywhere; }
        .item-quantity { color: #166534; font-size: 17px; font-weight: 800; white-space: nowrap; }
        .item-quantity.is-catalog { color: #64748b; font-size: 13px; }
        .item-accounting { margin-top: 3px; color: #475569; font-size: 12px; overflow-wrap: anywhere; }
        .item-meta { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 9px; }
        .meta-chip { padding: 3px 6px; border-radius: 4px; background: #f1f5f9; color: #475569; font-size: 11px; }
        .color-chip { display: inline-flex; align-items: center; gap: 5px; }
        .color-swatch { width: 14px; height: 14px; border: 1px solid #cbd5e1; border-radius: 3px; background: var(--swatch, transparent); box-shadow: inset 0 0 0 1px rgba(255,255,255,.35); }
        .quick-stock { display: flex; gap: 6px; margin-top: 10px; }
        .quick-stock input { flex: 1 1 auto; min-width: 0; min-height: 34px; padding: 6px 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; }
        .quick-stock button { flex: 0 0 auto; min-height: 34px; padding: 6px 10px; border: 1px solid #1d4ed8; border-radius: 6px; background: #1d4ed8; color: #fff; font-size: 12px; font-weight: 700; }
        .quick-stock-status { margin-top: 6px; color: #64748b; font-size: 12px; }
        .variant-line { margin-top: 7px; color: #64748b; font-size: 12px; line-height: 1.35; }
        .empty-state { padding: 46px 16px; color: var(--muted); text-align: center; }
        .loading { padding: 34px 16px; color: var(--muted); text-align: center; }
        @media (min-width: 760px) {
            .page-shell { padding: 24px; }
            .item-list { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
    </style>
</head>
<body>
    <main class="page-shell">
        <div class="topbar">
            <a class="back-link" href="{{ url('/client/kiem-ton-kho?location_code=' . urlencode($location->location_code)) }}">
                <i data-lucide="arrow-left"></i>Kiem ton kho
            </a>
            <span class="text-muted small">Chi tiet vi tri</span>
        </div>

        <section class="location-card">
            <div class="text-uppercase text-primary small fw-bold">Vi tri kho</div>
            <h1 class="location-code">{{ $location->location_code }}</h1>
            <div class="location-name">{{ $location->location_name ?: 'Chua dat ten vi tri' }}</div>
            <span class="warehouse-badge">Kho {{ $location->warehouse_code ?: '-' }}</span>
        </section>

        <section class="summary-grid">
            <div class="summary-box"><div id="itemCount" class="summary-value">0</div><div class="summary-label">Ma noi bo</div></div>
            <div class="summary-box"><div id="packageCount" class="summary-value">0</div><div class="summary-label">Kien hang</div></div>
            <div class="summary-box"><div id="totalQuantity" class="summary-value">0</div><div class="summary-label">Tong so luong</div></div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div class="search-wrap">
                    <i data-lucide="search"></i>
                    <input id="itemSearch" class="form-control" placeholder="Tim ma noi bo, ma ke toan, size hoac mau">
                </div>
            </div>
            <div id="itemList" class="item-list"><div class="loading">Dang tai hang trong vi tri...</div></div>
        </section>
    </main>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        const locationCode = @json($location->location_code);
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        let items = [];

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }

        function formatNumber(value) {
            return Number(value || 0).toLocaleString('vi-VN', { maximumFractionDigits: 3 });
        }

        function todayIso() {
            const date = new Date();
            return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        }

        function quickStockPayload(item, quantity) {
            return {
                location_code: locationCode,
                internal_item_code: item.internal_item_code || '',
                ma_sp: item.ma_sp || '',
                size: item.size || '',
                color: item.color || '',
                side: item.side || '',
                quantity,
                checked_at: todayIso(),
                entry_type: 'opening',
                note: `Nhap ton nhanh tu QR vi tri ${locationCode}${item.catalog_unit ? ` - DVT ${item.catalog_unit}` : ''}`,
            };
        }

        function renderItems() {
            const keyword = document.getElementById('itemSearch').value.trim().toLowerCase();
            const filtered = items.filter(item => `${item.internal_item_code || ''} ${item.ma_sp || ''} ${item.size || ''} ${item.color || ''} ${item.pantone_code || ''} ${item.side || ''}`.toLowerCase().includes(keyword));
            document.getElementById('itemList').innerHTML = filtered.map(item => `
                <article class="item-card">
                    <div class="item-head">
                        <div class="item-code">${escapeHtml(item.internal_item_code || 'Chua co ma noi bo')}</div>
                        <div class="item-quantity ${item.catalog_only ? 'is-catalog' : ''}">${item.catalog_only ? 'Chua nhap ton' : formatNumber(item.total_quantity)}</div>
                    </div>
                    <div class="item-accounting">${item.catalog_item_name ? `${escapeHtml(item.catalog_item_name)} - ` : ''}Ma ke toan: ${escapeHtml(item.ma_sp || '-')}</div>
                    <div class="item-meta">
                        ${item.catalog_only ? '<span class="meta-chip">Danh muc</span>' : `<span class="meta-chip">${formatNumber(item.package_count)} kien</span>`}
                        ${item.size ? `<span class="meta-chip">Size ${escapeHtml(item.size)}</span>` : ''}
                        ${(item.color || item.pantone_hex) ? `<span class="meta-chip color-chip">${item.pantone_hex ? `<span class="color-swatch" style="--swatch:${escapeHtml(item.pantone_hex)}"></span>` : ''}Mau ${escapeHtml(item.color || item.pantone_code || item.pantone_hex)}${item.pantone_code ? ` - ${escapeHtml(item.pantone_code)}` : ''}</span>` : ''}
                        ${item.catalog_unit ? `<span class="meta-chip">DVT ${escapeHtml(item.catalog_unit)}</span>` : ''}
                        ${item.side ? `<span class="meta-chip">Side ${escapeHtml(item.side)}</span>` : ''}
                    </div>
                    ${!item.catalog_only && item.variants?.length > 1 ? `<div class="variant-line">${item.variants.length} dong chi tiet: ${escapeHtml(item.variants.slice(0, 3).map(v => `${v.size || '-'} ${v.color || ''} SL ${formatNumber(v.quantity)}`).join(' | '))}${item.variants.length > 3 ? '...' : ''}</div>` : ''}
                    ${item.catalog_only ? `<div class="quick-stock">
                        <input type="number" min="0" step="0.001" inputmode="decimal" placeholder="So luong ${escapeHtml(item.catalog_unit || '')}" data-quick-stock-input="${escapeHtml(item.internal_item_code)}">
                        <button type="button" data-quick-stock-code="${escapeHtml(item.internal_item_code)}">Nhap ton</button>
                    </div><div class="quick-stock-status" data-quick-stock-status="${escapeHtml(item.internal_item_code)}"></div>` : ''}
                </article>`).join('') || '<div class="empty-state">Khong co mat hang phu hop trong vi tri nay.</div>';
        }

        function loadItems() {
            return fetch(`/api/kiem-ton-kho/noi-dung-vi-tri?location_code=${encodeURIComponent(locationCode)}`)
                .then(response => response.json())
                .then(result => {
                    items = result.data || [];
                    document.getElementById('itemCount').textContent = formatNumber(result.summary?.item_count);
                    document.getElementById('packageCount').textContent = formatNumber(result.summary?.package_count);
                    document.getElementById('totalQuantity').textContent = formatNumber(result.summary?.total_quantity);
                    renderItems();
                })
                .catch(() => {
                    document.getElementById('itemList').innerHTML = '<div class="empty-state text-danger">Khong tai duoc du lieu vi tri.</div>';
                });
        }

        function saveQuickStock(code) {
            const item = items.find(row => String(row.internal_item_code || '') === String(code));
            const input = document.querySelector(`[data-quick-stock-input="${CSS.escape(code)}"]`);
            const status = document.querySelector(`[data-quick-stock-status="${CSS.escape(code)}"]`);
            const quantity = Number(input?.value || 0);
            if (!item || quantity <= 0) {
                if (status) status.textContent = 'Nhap so luong lon hon 0.';
                return;
            }
            if (status) status.textContent = 'Dang luu...';
            fetch('/api/kiem-ton-kho/kien', {
                method: 'POST',
                headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify(quickStockPayload(item, quantity)),
            })
                .then(response => response.ok ? response.json() : response.json().then(result => { throw new Error(result.message || 'Khong luu duoc ton.'); }))
                .then(() => loadItems())
                .catch(error => {
                    if (status) status.textContent = error.message;
                });
        }

        document.getElementById('itemList').addEventListener('click', event => {
            const button = event.target.closest('[data-quick-stock-code]');
            if (button) saveQuickStock(button.dataset.quickStockCode);
        });
        document.getElementById('itemSearch').addEventListener('input', renderItems);
        loadItems();
        if (window.lucide) lucide.createIcons();
    </script>
</body>
</html>
