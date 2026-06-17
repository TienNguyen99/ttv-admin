<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Danh mục mã nội bộ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/warehouse-wms.css') }}" rel="stylesheet">
    <style>
        .catalog-table { min-width: 1280px; }
        .catalog-table .name-cell { min-width: 320px; white-space: normal; }
        .sync-note { color:#64748b; font-size:12px; }
    </style>
</head>
<body>
    @include('layouts.partials.sidebar')

    <header class="wms-topbar">
        <h1 class="wms-topbar__title">WMS May Mặc</h1>
        <div class="wms-global-search">
            <i data-lucide="search"></i>
            <input id="topCatalogKeyword" aria-label="Tìm mã nội bộ" placeholder="Tìm mã, tên hàng, đơn vị hoặc kệ...">
        </div>
        <div class="wms-topbar__actions">
            <button id="syncCatalogBtn" class="wms-btn wms-btn--primary"><i data-lucide="refresh-cw"></i> Đồng bộ DANH MỤC</button>
        </div>
    </header>

    <main class="wms-page">
        <div class="wms-heading">
            <div>
                <h1>Danh mục mã nội bộ</h1>
                <p>Đồng bộ tab <strong>DANH MỤC</strong> từ file QUANLY-VATTU vào database kho nội bộ.</p>
            </div>
            <div class="sync-note">
                Google Sheet chỉ đọc · Autocomplete đọc database nội bộ
                <div id="catalogSyncResult"></div>
            </div>
        </div>

        <section class="wms-kpis">
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="barcode"></i></div><div><div class="wms-kpi__label">Mã đang dùng</div><div id="catalogCount" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Theo bộ lọc</div></div></article>
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="map-pin"></i></div><div><div class="wms-kpi__label">Kệ khai báo</div><div id="catalogShelfCount" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Giá trị khác nhau</div></div></article>
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="ruler"></i></div><div><div class="wms-kpi__label">Có đơn vị</div><div id="catalogUnitCount" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Dòng có ĐVT</div></div></article>
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="clock-3"></i></div><div><div class="wms-kpi__label">Cập nhật</div><div class="wms-kpi__value" style="font-size:16px">Nội bộ</div><div id="catalogLastSync" class="wms-kpi__meta">Chưa đồng bộ</div></div></article>
        </section>

        <section class="wms-filterbar" style="grid-template-columns:minmax(260px,1fr) auto">
            <div><label for="catalogKeyword">Tìm kiếm</label><input id="catalogKeyword" class="form-control" placeholder="Mã hàng, tên hàng, ĐVT hoặc kệ..."></div>
            <div><button id="clearCatalogFilter" class="wms-btn"><i data-lucide="filter-x"></i> Xóa lọc</button></div>
        </section>

        <section class="wms-panel">
            <div class="wms-panel__header"><h2>Danh sách mã nội bộ</h2><span id="catalogResultLabel" class="text-secondary small">Đang tải...</span></div>
            <div class="wms-table-wrap">
                <table class="wms-table catalog-table">
                    <thead><tr><th>Mã hàng</th><th>Tên hàng</th><th>ĐVT</th><th>Size</th><th>Màu</th><th>Màu in</th><th>Mặt</th><th>Kệ</th><th class="text-end">Tồn đầu</th><th>Dòng nguồn</th></tr></thead>
                    <tbody id="catalogRows"><tr><td colspan="10" class="wms-loading">Chưa có dữ liệu. Bấm Đồng bộ DANH MỤC.</td></tr></tbody>
                </table>
            </div>
        </section>
    </main>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const rowsEl = document.getElementById('catalogRows');
        const keywordEl = document.getElementById('catalogKeyword');
        const topKeywordEl = document.getElementById('topCatalogKeyword');
        let searchTimer = null;
        const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
        const num = value => Number(value || 0).toLocaleString('vi-VN', {maximumFractionDigits:3});

        function jsonOrError(response, fallback) {
            if (response.ok) return response.json();
            return response.json().then(result => { throw new Error(result.message || fallback); });
        }

        function loadCatalog() {
            const params = new URLSearchParams({limit:2000});
            if (keywordEl.value.trim()) params.set('keyword', keywordEl.value.trim());
            rowsEl.innerHTML = '<tr><td colspan="10" class="wms-loading">Đang tải dữ liệu...</td></tr>';

            fetch('/api/danh-muc-noi-bo?' + params.toString())
                .then(response => jsonOrError(response, 'Không tải được danh mục nội bộ'))
                .then(result => {
                    const summary = result.summary || {};
                    document.getElementById('catalogCount').textContent = num(summary.item_count);
                    document.getElementById('catalogShelfCount').textContent = num(summary.shelf_count);
                    document.getElementById('catalogUnitCount').textContent = num(summary.with_unit_count);
                    document.getElementById('catalogLastSync').textContent = summary.last_synced_at
                        ? new Date(summary.last_synced_at).toLocaleString('vi-VN')
                        : 'Chưa đồng bộ';
                    document.getElementById('catalogResultLabel').textContent = `${num((result.data || []).length)} / ${num(summary.item_count)} mã`;
                    rowsEl.innerHTML = (result.data || []).map(row => `<tr>
                        <td class="wms-code">${esc(row.item_code)}</td>
                        <td class="name-cell">${esc(row.item_name || '-')}</td>
                        <td>${esc(row.unit || '-')}</td>
                        <td>${esc(row.size || '-')}</td>
                        <td>${esc(row.color || '-')}</td>
                        <td>${esc(row.logo_color || '-')}</td>
                        <td>${esc(row.side || '-')}</td>
                        <td>${esc(row.shelf_code || '-')}</td>
                        <td class="wms-number">${num(row.opening_quantity)}</td>
                        <td>${num(row.source_row)}</td>
                    </tr>`).join('') || '<tr><td colspan="10" class="wms-empty">Không có mã phù hợp.</td></tr>';
                })
                .catch(error => rowsEl.innerHTML = `<tr><td colspan="10" class="wms-empty text-danger">${esc(error.message)}</td></tr>`);
        }

        document.getElementById('syncCatalogBtn').addEventListener('click', () => {
            const button = document.getElementById('syncCatalogBtn');
            const resultEl = document.getElementById('catalogSyncResult');
            button.disabled = true;
            resultEl.textContent = 'Đang đồng bộ...';
            fetch('/api/danh-muc-noi-bo/dong-bo', {
                method:'POST',
                headers:{'Accept':'application/json','X-CSRF-TOKEN':csrfToken}
            }).then(response => jsonOrError(response, 'Không đồng bộ được DANH MỤC'))
              .then(result => {
                  const data = result.data || {};
                  resultEl.textContent = `Thêm ${num(data.created)}, cập nhật ${num(data.updated)}, đang dùng ${num(data.active)} mã.`;
                  loadCatalog();
              })
              .catch(error => resultEl.textContent = error.message)
              .finally(() => button.disabled = false);
        });

        function queueSearch(source) {
            if (source === topKeywordEl) keywordEl.value = topKeywordEl.value;
            if (source === keywordEl) topKeywordEl.value = keywordEl.value;
            clearTimeout(searchTimer);
            searchTimer = setTimeout(loadCatalog, 250);
        }

        keywordEl.addEventListener('input', () => queueSearch(keywordEl));
        topKeywordEl.addEventListener('input', () => queueSearch(topKeywordEl));
        document.getElementById('clearCatalogFilter').addEventListener('click', () => {
            keywordEl.value = '';
            topKeywordEl.value = '';
            loadCatalog();
        });
        loadCatalog();
    </script>
</body>
</html>
