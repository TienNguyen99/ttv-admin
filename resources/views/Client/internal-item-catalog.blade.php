<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Danh mục mã nội bộ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/warehouse-wms.css') }}?v={{ filemtime(public_path('css/warehouse-wms.css')) }}" rel="stylesheet">
    <style>
        .catalog-table { min-width: 1280px; }
        .invalid-code-table { min-width: 1320px; }
        .catalog-table .name-cell { min-width: 320px; white-space: normal; }
        .sync-note { color:#64748b; font-size:12px; }
        .color-chip { display:inline-flex; align-items:center; gap:6px; min-width:0; }
        .color-swatch { width:14px; height:14px; border:1px solid #cbd5e1; border-radius:3px; background:var(--swatch, transparent); box-shadow:inset 0 0 0 1px rgba(255,255,255,.35); }
        .invalid-code { color:#b91c1c; font-weight:800; }
        .compact-note { max-width:280px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
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
            <button id="syncShelvesBtn" class="wms-btn"><i data-lucide="map-pinned"></i> Đồng bộ kệ sang vị trí</button>
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

        <section class="wms-panel mb-3">
            <div class="wms-panel__header">
                <div>
                    <h2>Ma noi bo ngoai danh muc</h2>
                    <p class="sync-note mb-0">Quet phieu nhap / xuat de tim dong co ma noi bo chua ton tai trong DANH MUC.</p>
                </div>
                <span id="invalidCodeResultLabel" class="text-secondary small">Chua quet</span>
            </div>
            <section class="wms-filterbar" style="grid-template-columns:180px minmax(260px,1fr) auto">
                <div>
                    <label for="invalidCodeType">Loai phieu</label>
                    <select id="invalidCodeType" class="form-select">
                        <option value="all">Tat ca</option>
                        <option value="receipt">Phieu nhap</option>
                        <option value="issue">Phieu xuat</option>
                    </select>
                </div>
                <div>
                    <label for="invalidCodeKeyword">Tim trong phieu loi</label>
                    <input id="invalidCodeKeyword" class="form-control" placeholder="Ma noi bo, so phieu, size, mau...">
                </div>
                <div>
                    <button id="scanInvalidCodesBtn" class="wms-btn wms-btn--primary"><i data-lucide="search-check"></i> Quet ma loi</button>
                </div>
            </section>
            <div class="wms-table-wrap">
                <table class="wms-table invalid-code-table">
                    <thead><tr><th>Loai</th><th>So phieu</th><th>Ngay</th><th>Ma noi bo loi</th><th>Ma ke toan</th><th>Ten hang</th><th>Size</th><th>Mau</th><th>Mat</th><th class="text-end">SL</th><th>Vi tri</th><th>Ghi chu</th><th></th></tr></thead>
                    <tbody id="invalidCodeRows"><tr><td colspan="13" class="wms-empty">Bam Quet ma loi de kiem tra.</td></tr></tbody>
                </table>
            </div>
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
        const invalidCodeRowsEl = document.getElementById('invalidCodeRows');
        const keywordEl = document.getElementById('catalogKeyword');
        const topKeywordEl = document.getElementById('topCatalogKeyword');
        const invalidCodeTypeEl = document.getElementById('invalidCodeType');
        const invalidCodeKeywordEl = document.getElementById('invalidCodeKeyword');
        let searchTimer = null;
        let invalidCodeSearchTimer = null;
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
                    rowsEl.innerHTML = (result.data || []).map(row => {
                        const colorLabel = row.color || row.pantone_code || row.pantone_hex || '-';
                        return `<tr>
                        <td class="wms-code">${esc(row.item_code)}</td>
                        <td class="name-cell">${esc(row.item_name || '-')}</td>
                        <td>${esc(row.unit || '-')}</td>
                        <td>${esc(row.size || '-')}</td>
                        <td>${colorLabel !== '-' ? `<span class="color-chip">${row.pantone_hex ? `<span class="color-swatch" style="--swatch:${esc(row.pantone_hex)}"></span>` : ''}<span>${esc(colorLabel)}${row.pantone_code ? ` · ${esc(row.pantone_code)}` : ''}</span></span>` : '-'}</td>
                        <td>${esc(row.logo_color || '-')}</td>
                        <td>${esc(row.side || '-')}</td>
                        <td>${esc(row.shelf_code || '-')}</td>
                        <td class="wms-number">${num(row.opening_quantity)}</td>
                        <td>${num(row.source_row)}</td>
                    </tr>`;
                    }).join('') || '<tr><td colspan="10" class="wms-empty">Không có mã phù hợp.</td></tr>';
                })
                .catch(error => rowsEl.innerHTML = `<tr><td colspan="10" class="wms-empty text-danger">${esc(error.message)}</td></tr>`);
        }

        function loadInvalidCodes() {
            const params = new URLSearchParams({
                limit: 2000,
                type: invalidCodeTypeEl.value || 'all',
            });
            if (invalidCodeKeywordEl.value.trim()) params.set('keyword', invalidCodeKeywordEl.value.trim());
            invalidCodeRowsEl.innerHTML = '<tr><td colspan="13" class="wms-loading">Dang quet phieu nhap / xuat...</td></tr>';

            fetch('/api/danh-muc-noi-bo/loi-ma-phieu?' + params.toString())
                .then(response => jsonOrError(response, 'Khong quet duoc ma noi bo ngoai danh muc'))
                .then(result => {
                    const rows = result.data || [];
                    const summary = result.summary || {};
                    document.getElementById('invalidCodeResultLabel').textContent =
                        `${num(summary.total)} dong loi - ${num(summary.unique_code_count)} ma - Nhap ${num(summary.receipt_count)} / Xuat ${num(summary.issue_count)}`;
                    invalidCodeRowsEl.innerHTML = rows.map(row => `
                        <tr>
                            <td>${esc(row.document_label)}</td>
                            <td class="wms-code">${esc(row.document_code)}</td>
                            <td>${esc(row.document_date || '')}</td>
                            <td class="invalid-code">${esc(row.internal_item_code)}</td>
                            <td>${esc(row.ma_hh || '-')}</td>
                            <td>${esc(row.ten_hh || '-')}</td>
                            <td>${esc(row.size || '-')}</td>
                            <td>${esc(row.color || '-')}</td>
                            <td>${esc(row.side || '-')}</td>
                            <td class="wms-number">${num(row.quantity)}</td>
                            <td>${esc(row.location_code || '-')}</td>
                            <td class="compact-note" title="${esc(row.note || '')}">${esc(row.note || '-')}</td>
                            <td class="text-end"><a class="wms-btn" target="_blank" href="${esc(row.edit_url)}">Mo phieu</a></td>
                        </tr>
                    `).join('') || '<tr><td colspan="13" class="wms-empty">Khong co ma noi bo ngoai danh muc.</td></tr>';
                })
                .catch(error => {
                    document.getElementById('invalidCodeResultLabel').textContent = 'Loi quet';
                    invalidCodeRowsEl.innerHTML = `<tr><td colspan="13" class="wms-empty text-danger">${esc(error.message)}</td></tr>`;
                });
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

        function autoSyncCatalog() {
            const resultEl = document.getElementById('catalogSyncResult');
            resultEl.textContent = 'Dang kiem tra auto sync...';
            fetch('/api/danh-muc-noi-bo/tu-dong-dong-bo', {
                method:'POST',
                headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify({minutes: 30})
            }).then(response => jsonOrError(response, 'Khong auto sync duoc DANH MUC'))
              .then(result => {
                  const data = result.data || {};
                  if (result.skipped) {
                      resultEl.textContent = `Auto sync: chua den 30 phut. Lan cuoi ${data.last_synced_at ? new Date(data.last_synced_at).toLocaleString('vi-VN') : '-'}.`;
                      return;
                  }
                  resultEl.textContent = `Auto sync xong: them ${num(data.created)}, cap nhat ${num(data.updated)}, dang dung ${num(data.active)} ma.`;
                  loadCatalog();
                  loadInvalidCodes();
              })
              .catch(error => resultEl.textContent = error.message);
        }

        document.getElementById('syncShelvesBtn').addEventListener('click', () => {
            const button = document.getElementById('syncShelvesBtn');
            const resultEl = document.getElementById('catalogSyncResult');
            if (!confirm('Đồng bộ các giá trị cột Kệ hợp lệ từ DANH MỤC sang danh sách vị trí kho nội bộ?')) return;
            button.disabled = true;
            resultEl.textContent = 'Đang đồng bộ kệ sang vị trí...';
            fetch('/api/danh-muc-noi-bo/dong-bo-vi-tri', {
                method:'POST',
                headers:{'Accept':'application/json','X-CSRF-TOKEN':csrfToken}
            }).then(response => jsonOrError(response, 'Không đồng bộ được kệ sang vị trí'))
              .then(result => {
                  const data = result.data || {};
                  resultEl.textContent = `Vị trí: tạo ${num(data.created)}, cập nhật ${num(data.updated)}, bỏ qua ${num(data.skipped)}, hợp lệ ${num(data.total_valid_shelves)}.`;
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
        document.getElementById('scanInvalidCodesBtn').addEventListener('click', loadInvalidCodes);
        invalidCodeTypeEl.addEventListener('change', loadInvalidCodes);
        invalidCodeKeywordEl.addEventListener('input', () => {
            clearTimeout(invalidCodeSearchTimer);
            invalidCodeSearchTimer = setTimeout(loadInvalidCodes, 250);
        });
        loadCatalog();
        loadInvalidCodes();
        autoSyncCatalog();
        setInterval(autoSyncCatalog, 30 * 60 * 1000);
    </script>
</body>
</html>
