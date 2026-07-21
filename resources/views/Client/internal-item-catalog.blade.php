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
        .catalog-image-drop { width:92px; min-height:64px; border:1px dashed #9db7d6; border-radius:10px; background:#f8fbff; color:#45617f; display:flex; align-items:center; justify-content:center; text-align:center; font-size:11px; font-weight:800; cursor:pointer; overflow:hidden; padding:4px; }
        .catalog-image-drop:focus, .catalog-image-drop.is-active { outline:2px solid #2563eb; outline-offset:2px; border-color:#2563eb; background:#eff6ff; }
        .catalog-image-drop.is-uploading { opacity:.65; pointer-events:none; }
        .catalog-image-drop img { width:100%; height:58px; object-fit:cover; border-radius:7px; display:block; }
        .catalog-image-help { font-size:10px; line-height:1.2; }
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
        <input id="catalogImageInput" type="file" accept="image/*" hidden>
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
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-2">
                <span id="invalidCodePageLabel" class="text-secondary small">Trang 1 / 1</span>
                <div class="d-flex align-items-center gap-2">
                    <select id="invalidCodePerPage" class="form-select form-select-sm" style="width:110px">
                        <option value="50">50 dong</option>
                        <option value="100" selected>100 dong</option>
                        <option value="200">200 dong</option>
                    </select>
                    <button id="invalidCodePrevPage" class="wms-btn" type="button">Truoc</button>
                    <button id="invalidCodeNextPage" class="wms-btn" type="button">Sau</button>
                </div>
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
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-2">
                <span id="catalogPageLabel" class="text-secondary small">Trang 1 / 1</span>
                <div class="d-flex align-items-center gap-2">
                    <select id="catalogPerPage" class="form-select form-select-sm" style="width:110px">
                        <option value="50">50 dong</option>
                        <option value="100" selected>100 dong</option>
                        <option value="200">200 dong</option>
                    </select>
                    <button id="catalogPrevPage" class="wms-btn" type="button">Truoc</button>
                    <button id="catalogNextPage" class="wms-btn" type="button">Sau</button>
                </div>
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
        let catalogPage = 1;
        let catalogTotalPages = 1;
        let invalidCodePage = 1;
        let invalidCodeTotalPages = 1;
        let activeImageCatalogId = null;
        const catalogImageInput = document.getElementById('catalogImageInput');
        const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
        const num = value => Number(value || 0).toLocaleString('vi-VN', {maximumFractionDigits:3});

        function jsonOrError(response, fallback) {
            if (response.ok) return response.json();
            return response.json().then(result => { throw new Error(result.message || fallback); });
        }

        function catalogImageDropHtml(row) {
            const image = imageUrl(row.image_url || '');
            return `<button type="button" class="catalog-image-drop" data-catalog-image-id="${esc(row.id)}" title="Click để chọn ảnh, kéo thả hoặc Ctrl+V ảnh">
                ${image ? `<img src="${esc(image)}" alt="${esc(row.item_code || row.item_name || 'Ảnh danh mục')}">` : '<span class="catalog-image-help">Kéo / paste<br>ảnh</span>'}
            </button>`;
        }

        function imageUrl(value) {
            const url = String(value || '').trim();
            if (!url) return '';
            if (/^(https?:)?\/\//i.test(url) || url.startsWith('data:')) return url;
            return url.startsWith('/') ? url : '/' + url;
        }

        function catalogImageDrop(catalogId) {
            return Array.from(document.querySelectorAll('[data-catalog-image-id]'))
                .find(drop => String(drop.dataset.catalogImageId) === String(catalogId));
        }

        function setImageDropState(catalogId, state) {
            const drop = catalogImageDrop(catalogId);
            if (!drop) return;
            drop.classList.toggle('is-uploading', state === 'uploading');
            drop.classList.toggle('is-active', state === 'active');
            if (state === 'uploading') drop.innerHTML = '<span class="catalog-image-help">Đang lưu...</span>';
        }

        function uploadCatalogImage(catalogId, file) {
            if (!catalogId || !file) return;
            if (!String(file.type || '').startsWith('image/')) {
                alert('Chọn đúng file ảnh.');
                return;
            }
            const form = new FormData();
            form.append('image', file);
            setImageDropState(catalogId, 'uploading');
            fetch(`/api/danh-muc-noi-bo/${encodeURIComponent(catalogId)}/anh`, {
                method: 'POST',
                headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken},
                body: form,
            }).then(response => jsonOrError(response, 'Không lưu được ảnh danh mục.'))
              .then(result => {
                  const drop = catalogImageDrop(catalogId);
                  const url = imageUrl(result.data?.image_url || '');
                  if (drop && url) {
                      drop.classList.remove('is-uploading');
                      drop.innerHTML = `<img src="${esc(url)}" alt="Ảnh danh mục">`;
                  }
              })
              .catch(error => {
                  alert(error.message);
                  loadCatalog();
              });
        }

        function loadCatalog() {
            const params = new URLSearchParams({
                page: catalogPage,
                per_page: document.getElementById('catalogPerPage').value || 100,
            });
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
                    const pagination = result.pagination || {};
                    catalogPage = Number(pagination.page || catalogPage || 1);
                    catalogTotalPages = Math.max(1, Number(pagination.total_pages || 1));
                    document.getElementById('catalogResultLabel').textContent = `${num(pagination.total || summary.item_count)} mã`;
                    document.getElementById('catalogPageLabel').textContent = `Trang ${num(catalogPage)} / ${num(catalogTotalPages)}`;
                    document.getElementById('catalogPrevPage').disabled = catalogPage <= 1;
                    document.getElementById('catalogNextPage').disabled = !pagination.has_more;
                    rowsEl.innerHTML = (result.data || []).map(row => {
                        const colorLabel = row.color_name || row.color || row.pantone_code || row.pantone_hex || '-';
                        return `<tr>
                        <td class="wms-code">${esc(row.item_code)}</td>
                        <td class="name-cell">
                            <div class="d-flex align-items-center gap-2">
                                ${catalogImageDropHtml(row)}
                                <div>${esc(row.item_name || '-')}</div>
                            </div>
                        </td>
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
                page: invalidCodePage,
                per_page: document.getElementById('invalidCodePerPage').value || 100,
                type: invalidCodeTypeEl.value || 'all',
            });
            if (invalidCodeKeywordEl.value.trim()) params.set('keyword', invalidCodeKeywordEl.value.trim());
            invalidCodeRowsEl.innerHTML = '<tr><td colspan="13" class="wms-loading">Dang quet phieu nhap / xuat...</td></tr>';

            fetch('/api/danh-muc-noi-bo/loi-ma-phieu?' + params.toString())
                .then(response => jsonOrError(response, 'Khong quet duoc ma noi bo ngoai danh muc'))
                .then(result => {
                    const rows = result.data || [];
                    const summary = result.summary || {};
                    const pagination = result.pagination || {};
                    invalidCodePage = Number(pagination.page || invalidCodePage || 1);
                    invalidCodeTotalPages = Math.max(1, Number(pagination.total_pages || 1));
                    document.getElementById('invalidCodeResultLabel').textContent =
                        `${num(summary.total)} dong loi - ${num(summary.unique_code_count)} ma - Nhap ${num(summary.receipt_count)} / Xuat ${num(summary.issue_count)}`;
                    document.getElementById('invalidCodePageLabel').textContent = `Trang ${num(invalidCodePage)} / ${num(invalidCodeTotalPages)}`;
                    document.getElementById('invalidCodePrevPage').disabled = invalidCodePage <= 1;
                    document.getElementById('invalidCodeNextPage').disabled = !pagination.has_more;
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
                  catalogPage = 1;
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
                  catalogPage = 1;
                  invalidCodePage = 1;
                  loadCatalog();
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
            catalogPage = 1;
            clearTimeout(searchTimer);
            searchTimer = setTimeout(loadCatalog, 250);
        }

        keywordEl.addEventListener('input', () => queueSearch(keywordEl));
        topKeywordEl.addEventListener('input', () => queueSearch(topKeywordEl));
        document.getElementById('clearCatalogFilter').addEventListener('click', () => {
            keywordEl.value = '';
            topKeywordEl.value = '';
            catalogPage = 1;
            loadCatalog();
        });
        document.getElementById('scanInvalidCodesBtn').addEventListener('click', () => {
            invalidCodePage = 1;
            loadInvalidCodes();
        });
        invalidCodeTypeEl.addEventListener('change', () => {
            invalidCodePage = 1;
            loadInvalidCodes();
        });
        invalidCodeKeywordEl.addEventListener('input', () => {
            invalidCodePage = 1;
            clearTimeout(invalidCodeSearchTimer);
            invalidCodeSearchTimer = setTimeout(loadInvalidCodes, 250);
        });
        document.getElementById('catalogPerPage').addEventListener('change', () => {
            catalogPage = 1;
            loadCatalog();
        });
        document.getElementById('catalogPrevPage').addEventListener('click', () => {
            if (catalogPage > 1) {
                catalogPage -= 1;
                loadCatalog();
            }
        });
        document.getElementById('catalogNextPage').addEventListener('click', () => {
            if (catalogPage < catalogTotalPages) {
                catalogPage += 1;
                loadCatalog();
            }
        });
        rowsEl.addEventListener('click', event => {
            const drop = event.target.closest('[data-catalog-image-id]');
            if (!drop) return;
            activeImageCatalogId = drop.dataset.catalogImageId;
            document.querySelectorAll('.catalog-image-drop').forEach(item => item.classList.remove('is-active'));
            drop.classList.add('is-active');
            catalogImageInput.click();
        });
        rowsEl.addEventListener('dragover', event => {
            const drop = event.target.closest('[data-catalog-image-id]');
            if (!drop) return;
            event.preventDefault();
            drop.classList.add('is-active');
        });
        rowsEl.addEventListener('dragleave', event => {
            const drop = event.target.closest('[data-catalog-image-id]');
            if (drop) drop.classList.remove('is-active');
        });
        rowsEl.addEventListener('drop', event => {
            const drop = event.target.closest('[data-catalog-image-id]');
            if (!drop) return;
            event.preventDefault();
            activeImageCatalogId = drop.dataset.catalogImageId;
            const file = Array.from(event.dataTransfer?.files || []).find(item => String(item.type || '').startsWith('image/'));
            if (file) uploadCatalogImage(activeImageCatalogId, file);
        });
        catalogImageInput.addEventListener('change', event => {
            const file = event.target.files?.[0];
            if (activeImageCatalogId && file) uploadCatalogImage(activeImageCatalogId, file);
            event.target.value = '';
        });
        document.addEventListener('paste', event => {
            if (!activeImageCatalogId) return;
            const file = Array.from(event.clipboardData?.items || [])
                .find(item => String(item.type || '').startsWith('image/'))
                ?.getAsFile();
            if (file) uploadCatalogImage(activeImageCatalogId, file);
        });
        document.getElementById('invalidCodePerPage').addEventListener('change', () => {
            invalidCodePage = 1;
            loadInvalidCodes();
        });
        document.getElementById('invalidCodePrevPage').addEventListener('click', () => {
            if (invalidCodePage > 1) {
                invalidCodePage -= 1;
                loadInvalidCodes();
            }
        });
        document.getElementById('invalidCodeNextPage').addEventListener('click', () => {
            if (invalidCodePage < invalidCodeTotalPages) {
                invalidCodePage += 1;
                loadInvalidCodes();
            }
        });
        document.getElementById('catalogPrevPage').disabled = true;
        document.getElementById('catalogNextPage').disabled = true;
        document.getElementById('invalidCodePrevPage').disabled = true;
        document.getElementById('invalidCodeNextPage').disabled = true;
        loadCatalog();
        autoSyncCatalog();
        setInterval(autoSyncCatalog, 30 * 60 * 1000);
    </script>
</body>
</html>
