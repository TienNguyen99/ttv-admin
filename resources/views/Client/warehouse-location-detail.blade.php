<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $location->location_code }} - Tồn tại vị trí</title>
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
        .item-card { padding: 11px; border: 1px solid var(--line); border-radius: 7px; }
        .item-head { display: flex; justify-content: space-between; gap: 10px; }
        .item-code { color: var(--accent); font-size: 15px; font-weight: 800; overflow-wrap: anywhere; }
        .item-quantity { color: #166534; font-size: 17px; font-weight: 800; white-space: nowrap; }
        .item-accounting { margin-top: 3px; color: #475569; font-size: 12px; overflow-wrap: anywhere; }
        .item-meta { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 9px; }
        .meta-chip { padding: 3px 6px; border-radius: 4px; background: #f1f5f9; color: #475569; font-size: 11px; }
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
                <i data-lucide="arrow-left"></i>Kiểm tồn kho
            </a>
            <span class="text-muted small">Chi tiết vị trí</span>
        </div>

        <section class="location-card">
            <div class="text-uppercase text-primary small fw-bold">Vị trí kho</div>
            <h1 class="location-code">{{ $location->location_code }}</h1>
            <div class="location-name">{{ $location->location_name ?: 'Chưa đặt tên vị trí' }}</div>
            <span class="warehouse-badge">Kho {{ $location->warehouse_code ?: '-' }}</span>
        </section>

        <section class="summary-grid">
            <div class="summary-box"><div id="itemCount" class="summary-value">0</div><div class="summary-label">Mã nội bộ</div></div>
            <div class="summary-box"><div id="packageCount" class="summary-value">0</div><div class="summary-label">Kiện hàng</div></div>
            <div class="summary-box"><div id="totalQuantity" class="summary-value">0</div><div class="summary-label">Tổng số lượng</div></div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div class="search-wrap">
                    <i data-lucide="search"></i>
                    <input id="itemSearch" class="form-control" placeholder="Tìm mã nội bộ, mã kế toán, size hoặc màu">
                </div>
            </div>
            <div id="itemList" class="item-list"><div class="loading">Đang tải hàng trong vị trí...</div></div>
        </section>
    </main>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        const locationCode = @json($location->location_code);
        let items = [];

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }

        function formatNumber(value) {
            return Number(value || 0).toLocaleString('vi-VN', { maximumFractionDigits: 3 });
        }

        function renderItems() {
            const keyword = document.getElementById('itemSearch').value.trim().toLowerCase();
            const filtered = items.filter(item => `${item.internal_item_code || ''} ${item.ma_sp || ''} ${item.size || ''} ${item.color || ''} ${item.side || ''}`.toLowerCase().includes(keyword));
            document.getElementById('itemList').innerHTML = filtered.map(item => `
                <article class="item-card">
                    <div class="item-head">
                        <div class="item-code">${escapeHtml(item.internal_item_code || 'Chưa có mã nội bộ')}</div>
                        <div class="item-quantity">${formatNumber(item.total_quantity)}</div>
                    </div>
                    <div class="item-accounting">Mã kế toán: ${escapeHtml(item.ma_sp || '-')}</div>
                    <div class="item-meta">
                        <span class="meta-chip">${formatNumber(item.package_count)} kiện</span>
                        ${item.size ? `<span class="meta-chip">Size ${escapeHtml(item.size)}</span>` : ''}
                        ${item.color ? `<span class="meta-chip">Màu ${escapeHtml(item.color)}</span>` : ''}
                        ${item.side ? `<span class="meta-chip">Side ${escapeHtml(item.side)}</span>` : ''}
                    </div>
                </article>`).join('') || '<div class="empty-state">Không có mặt hàng phù hợp trong vị trí này.</div>';
        }

        fetch(`/api/kiem-ton-kho/noi-dung-vi-tri?location_code=${encodeURIComponent(locationCode)}`)
            .then(response => response.json())
            .then(result => {
                items = result.data || [];
                document.getElementById('itemCount').textContent = formatNumber(result.summary?.item_count);
                document.getElementById('packageCount').textContent = formatNumber(result.summary?.package_count);
                document.getElementById('totalQuantity').textContent = formatNumber(result.summary?.total_quantity);
                renderItems();
            })
            .catch(() => {
                document.getElementById('itemList').innerHTML = '<div class="empty-state text-danger">Không tải được dữ liệu vị trí.</div>';
            });

        document.getElementById('itemSearch').addEventListener('input', renderItems);
        if (window.lucide) lucide.createIcons();
    </script>
</body>
</html>
