<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Tồn kho nội bộ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/warehouse-wms.css') }}" rel="stylesheet">
</head>
<body>
    @include('layouts.partials.sidebar')

    <header class="wms-topbar">
        <h1 class="wms-topbar__title">WMS May Mặc</h1>
        <div class="wms-global-search">
            <i data-lucide="search"></i>
            <input id="topKeyword" aria-label="Tìm trong tồn kho" placeholder="Tìm mã nội bộ, mã kế toán, size hoặc vị trí...">
        </div>
        <div class="wms-topbar__actions">
            <button id="voiceStockBtn" type="button" class="wms-btn" title="Tìm bằng giọng nói"><i data-lucide="mic"></i><span class="visually-hidden">Tìm bằng giọng nói</span></button>
            <a class="wms-btn" href="{{ url('/client/kiem-ton-kho') }}"><i data-lucide="scan-line"></i> Quét kho</a>
        </div>
    </header>

    <main class="wms-page">
        <div class="wms-heading">
            <div>
                <h1>Tồn kho nội bộ</h1>
                <p>Tồn đầu tháng + phiếu nhập - phiếu xuất. Không ghi dữ liệu sang TSoft.</p>
            </div>
            <div class="wms-actions">
                <a class="wms-btn" href="{{ url('/client/doi-chieu-ton') }}"><i data-lucide="scale"></i> Đối chiếu TSoft</a>
                <button id="reloadBtn" type="button" class="wms-btn wms-btn--primary"><i data-lucide="refresh-cw"></i> Tải lại</button>
            </div>
        </div>

        <section class="wms-kpis">
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="barcode"></i></div><div><div class="wms-kpi__label">Mã hàng</div><div id="itemCount" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Mã có phát sinh trong kỳ</div></div></article>
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="calendar-range"></i></div><div><div class="wms-kpi__label">Tồn đầu kỳ</div><div id="openingQuantity" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Số lượng đầu tháng</div></div></article>
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="arrow-down-up"></i></div><div><div class="wms-kpi__label">Nhập / Xuất</div><div class="wms-kpi__value"><span id="receiptQuantity">0</span> / <span id="issueQuantity">0</span></div><div class="wms-kpi__meta">Phát sinh trong kỳ</div></div></article>
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="boxes"></i></div><div><div class="wms-kpi__label">Tồn cuối kỳ</div><div id="totalQuantity" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Theo bộ lọc hiện tại</div></div></article>
        </section>

        <section class="wms-filterbar">
            <div><label for="warehouseSelect">Kho</label><select id="warehouseSelect" class="form-select"><option value="">Tất cả kho</option></select></div>
            <div><label for="stockMonth">Tháng tồn</label><input id="stockMonth" type="month" class="form-control" value="{{ now()->format('Y-m') }}"></div>
            <div><label for="keyword">Tìm mã hàng, mã nội bộ, size, màu hoặc vị trí</label><input id="keyword" class="form-control" value="{{ request('keyword') }}" placeholder="Nhập từ khóa hoặc quét mã"></div>
            <div><button id="clearFilterBtn" type="button" class="wms-btn"><i data-lucide="filter-x"></i> Xóa lọc</button></div>
        </section>

        <section class="wms-panel">
            <div class="wms-panel__header">
                <h2>Danh sách tồn kho</h2>
                <span id="stockResultLabel" class="text-secondary small">Đang tải...</span>
            </div>
            <div class="wms-table-wrap">
                <table class="wms-table">
                    <thead><tr><th>Kho</th><th>Vị trí</th><th>Mã kế toán</th><th>Mã nội bộ</th><th>Size</th><th>Màu</th><th>Side</th><th class="text-end">Tồn đầu</th><th class="text-end">Nhập</th><th class="text-end">Xuất</th><th class="text-end">Tồn cuối</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
                    <tbody id="stockRows"><tr><td colspan="13" class="wms-loading">Đang tải dữ liệu...</td></tr></tbody>
                </table>
            </div>
        </section>
    </main>

    <div class="modal fade" id="accountingCodeModal" tabindex="-1" aria-labelledby="accountingCodeModalTitle" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="accountingCodeModalTitle">Gán mã kế toán</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Mã nội bộ</label>
                        <input id="mappingInternalCode" class="form-control" readonly>
                    </div>
                    <div>
                        <label class="form-label">Tìm và chọn mã kế toán</label>
                        <input id="mappingAccountingCode" class="form-control" list="mappingAccountingOptions" autocomplete="off" placeholder="Gõ mã hoặc tên hàng">
                        <datalist id="mappingAccountingOptions"></datalist>
                        <div class="text-secondary small mt-2">Mã này chỉ dùng để đối chiếu với tồn TSoft. Hệ thống không ghi dữ liệu sang TSoft.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button id="saveAccountingCodeBtn" type="button" class="btn btn-primary">Lưu mã kế toán</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="stockLocationModal" tabindex="-1" aria-labelledby="stockLocationModalTitle" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="stockLocationModalTitle">Gán vị trí tồn kho</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Dòng tồn</label>
                        <input id="stockLocationSummary" class="form-control" readonly>
                    </div>
                    <div>
                        <label class="form-label">Vị trí mới</label>
                        <select id="stockTargetLocation" class="form-select"></select>
                        <div class="text-secondary small mt-2">Chỉ cập nhật dữ liệu kho nội bộ. Không ghi sang TSoft.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button id="saveStockLocationBtn" type="button" class="btn btn-primary">Lưu vị trí</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const warehouseSelect = document.getElementById('warehouseSelect');
        const stockMonthEl = document.getElementById('stockMonth');
        const keywordEl = document.getElementById('keyword');
        const topKeywordEl = document.getElementById('topKeyword');
        const rowsEl = document.getElementById('stockRows');
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const accountingCodeModal = new bootstrap.Modal(document.getElementById('accountingCodeModal'));
        const stockLocationModal = new bootstrap.Modal(document.getElementById('stockLocationModal'));
        let searchTimer = null;
        let mappingSearchTimer = null;
        let stockLocationPayload = null;
        let locationOptionsLoaded = false;

        const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
        const num = value => Number(value || 0).toLocaleString('vi-VN', {maximumFractionDigits: 3});

        function jsonOrError(response, fallback) {
            if (response.ok) return response.json();
            return response.json().then(result => { throw new Error(result.message || fallback); });
        }

        function loadWarehouses() {
            return fetch('/api/ton-kho-noi-bo/kho')
                .then(response => jsonOrError(response, 'Không tải được danh sách kho'))
                .then(result => {
                    const current = warehouseSelect.value;
                    warehouseSelect.innerHTML = '<option value="">Tất cả kho</option>' + (result.data || []).map(code => `<option value="${esc(code)}">${esc(code)}</option>`).join('');
                    warehouseSelect.value = current;
                });
        }

        function loadStock() {
            rowsEl.innerHTML = '<tr><td colspan="13" class="wms-loading">Đang tải dữ liệu...</td></tr>';
            const params = new URLSearchParams();
            if (warehouseSelect.value) params.set('warehouse_code', warehouseSelect.value);
            if (stockMonthEl.value) params.set('month', stockMonthEl.value);
            if (keywordEl.value.trim()) params.set('keyword', keywordEl.value.trim());

            fetch(`/api/ton-kho-noi-bo?${params.toString()}`)
                .then(response => jsonOrError(response, 'Không tải được tồn nội bộ'))
                .then(result => {
                    document.getElementById('itemCount').textContent = num(result.summary?.item_count);
                    document.getElementById('openingQuantity').textContent = num(result.summary?.opening_quantity);
                    document.getElementById('receiptQuantity').textContent = num(result.summary?.receipt_quantity);
                    document.getElementById('issueQuantity').textContent = num(result.summary?.issue_quantity);
                    document.getElementById('totalQuantity').textContent = num(result.summary?.total_quantity);
                    document.getElementById('stockResultLabel').textContent = `${num((result.data || []).length)} dòng tồn`;
                    rowsEl.innerHTML = (result.data || []).map(row => {
                        const quantity = Number(row.total_quantity || 0);
                        const unassigned = !row.location_code || row.location_code === 'CHUA-XEP';
                        const status = unassigned
                            ? '<span class="wms-badge wms-badge--warning">Chưa xếp</span>'
                            : quantity < 0
                                ? '<span class="wms-badge wms-badge--danger">Âm tồn</span>'
                                : '<span class="wms-badge">Có tồn</span>';
                        return `<tr>
                            <td>${esc(row.warehouse_code || '-')}</td>
                            <td>${esc(row.location_code || 'CHUA-XEP')}</td>
                            <td class="wms-code">${row.ma_sp ? esc(row.ma_sp) : '<span class="wms-badge wms-badge--warning">Chưa gán</span>'}</td>
                            <td class="wms-code">${esc(row.internal_item_code || '-')}</td>
                            <td>${esc(row.size || '-')}</td>
                            <td>${esc(row.color || '-')}</td>
                            <td>${esc(row.side || '-')}</td>
                            <td class="wms-number">${num(row.opening_quantity)}</td>
                            <td class="wms-number">${num(row.receipt_quantity)}</td>
                            <td class="wms-number">${num(row.issue_quantity)}</td>
                            <td class="wms-number ${quantity < 0 ? 'text-danger' : ''}">${num(quantity)}</td>
                            <td>${status}</td>
                            <td class="text-nowrap">
                                ${quantity > 0 && Number(row.issue_quantity || 0) === 0
                                    ? `<button type="button" class="btn btn-sm btn-outline-primary assign-stock-location"
                                        data-warehouse="${esc(row.warehouse_code || '')}"
                                        data-location="${esc(row.location_code || '')}"
                                        data-ma-hh="${esc(row.ma_sp || '')}"
                                        data-internal-code="${esc(row.internal_item_code || '')}"
                                        data-size="${esc(row.size || '')}"
                                        data-color="${esc(row.color || '')}"
                                        data-side="${esc(row.side || '')}"
                                        data-label="${esc(row.internal_item_code || row.ma_sp || '-')}"
                                        data-quantity="${esc(row.total_quantity || 0)}"><i data-lucide="map-pin"></i> Vị trí</button>`
                                    : ''}
                                ${!row.ma_sp && row.internal_item_code
                                    ? `<button type="button" class="btn btn-sm btn-outline-primary assign-accounting-code" data-internal-code="${esc(row.internal_item_code)}"><i data-lucide="link-2"></i> Gán mã</button>`
                                    : ''}
                                ${row.can_delete
                                    ? `<button type="button" class="btn btn-sm btn-outline-danger delete-stock"
                                        data-warehouse="${esc(row.warehouse_code || '')}"
                                        data-location="${esc(row.location_code || '')}"
                                        data-ma-hh="${esc(row.ma_sp || '')}"
                                        data-internal-code="${esc(row.internal_item_code || '')}"
                                        data-size="${esc(row.size || '')}"
                                        data-color="${esc(row.color || '')}"
                                        data-side="${esc(row.side || '')}"><i data-lucide="trash-2"></i> Xóa</button>`
                                    : `<a class="btn btn-sm btn-outline-secondary" href="${Number(row.receipt_quantity || 0) ? '/client/kiem-ton-kho?view=history' : '/client/xuat-vat-tu-noi-bo'}" title="${esc(row.delete_reason || '')}">Xem phiếu</a>`}
                            </td>
                        </tr>`;
                    }).join('') || '<tr><td colspan="13" class="wms-empty">Không có tồn phù hợp.</td></tr>';
                    if (window.lucide) window.lucide.createIcons();
                })
                .catch(error => {
                    rowsEl.innerHTML = `<tr><td colspan="13" class="wms-empty text-danger">${esc(error.message)}</td></tr>`;
                });
        }

        rowsEl.addEventListener('click', event => {
            const locationButton = event.target.closest('.assign-stock-location');
            if (locationButton) {
                stockLocationPayload = {
                    month: stockMonthEl.value,
                    warehouse_code: locationButton.dataset.warehouse,
                    location_code: locationButton.dataset.location,
                    ma_hh: locationButton.dataset.maHh,
                    internal_item_code: locationButton.dataset.internalCode,
                    size: locationButton.dataset.size,
                    color: locationButton.dataset.color,
                    side: locationButton.dataset.side,
                };
                document.getElementById('stockLocationSummary').value = `${locationButton.dataset.label} · ${num(locationButton.dataset.quantity)} · ${locationButton.dataset.location || 'CHUA-XEP'}`;
                openStockLocationModal(locationButton.dataset.location || '');
                return;
            }

            const assignButton = event.target.closest('.assign-accounting-code');
            if (assignButton) {
                document.getElementById('mappingInternalCode').value = assignButton.dataset.internalCode;
                document.getElementById('mappingAccountingCode').value = '';
                document.getElementById('mappingAccountingOptions').innerHTML = '';
                accountingCodeModal.show();
                return;
            }

            const button = event.target.closest('.delete-stock');
            if (!button) return;
            const code = button.dataset.internalCode || button.dataset.maHh;
            if (!confirm(`Xóa tồn đầu nội bộ của ${code}? Thao tác này không ảnh hưởng TSoft.`)) return;

            button.disabled = true;
            fetch('/api/ton-kho-noi-bo', {
                method: 'DELETE',
                headers: {'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify({
                    month: stockMonthEl.value,
                    warehouse_code: button.dataset.warehouse,
                    location_code: button.dataset.location,
                    ma_hh: button.dataset.maHh,
                    internal_item_code: button.dataset.internalCode,
                    size: button.dataset.size,
                    color: button.dataset.color,
                    side: button.dataset.side
                })
            }).then(response => jsonOrError(response, 'Không xóa được dòng tồn'))
              .then(() => loadStock())
              .catch(error => {
                  button.disabled = false;
                  alert(error.message);
              });
        });

        function loadLocationOptions() {
            if (locationOptionsLoaded) return Promise.resolve();
            return fetch('/api/kiem-ton-kho/vi-tri')
                .then(response => jsonOrError(response, 'Không tải được danh sách vị trí'))
                .then(result => {
                    document.getElementById('stockTargetLocation').innerHTML = (result.data || []).map(location =>
                        `<option value="${esc(location.location_code)}">${esc(location.location_code)}${location.location_name ? ' · ' + esc(location.location_name) : ''}</option>`
                    ).join('');
                    locationOptionsLoaded = true;
                });
        }

        function openStockLocationModal(currentLocation) {
            loadLocationOptions().then(() => {
                const select = document.getElementById('stockTargetLocation');
                if (currentLocation && Array.from(select.options).some(option => option.value === currentLocation)) {
                    select.value = currentLocation;
                }
                stockLocationModal.show();
            }).catch(error => alert(error.message));
        }

        document.getElementById('saveStockLocationBtn').addEventListener('click', () => {
            if (!stockLocationPayload) return;
            const targetLocation = document.getElementById('stockTargetLocation').value;
            if (!targetLocation) return alert('Chọn vị trí cần gán.');

            const button = document.getElementById('saveStockLocationBtn');
            button.disabled = true;
            fetch('/api/ton-kho-noi-bo/vi-tri', {
                method: 'PATCH',
                headers: {'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify({...stockLocationPayload, target_location_code: targetLocation})
            }).then(response => jsonOrError(response, 'Không gán được vị trí'))
              .then(() => {
                  stockLocationModal.hide();
                  loadStock();
              })
              .catch(error => alert(error.message))
              .finally(() => button.disabled = false);
        });

        document.getElementById('mappingAccountingCode').addEventListener('input', event => {
            const keyword = event.target.value.trim();
            clearTimeout(mappingSearchTimer);
            if (keyword.length < 2) return;
            mappingSearchTimer = setTimeout(() => {
                fetch(`/api/thanh-pham-ke-toan/goi-y?keyword=${encodeURIComponent(keyword)}`)
                    .then(response => jsonOrError(response, 'Không tìm được mã kế toán'))
                    .then(result => {
                        document.getElementById('mappingAccountingOptions').innerHTML = (result.data || []).map(item =>
                            `<option value="${esc(item.Ma_hh)}">${esc(item.Ten_hh || '')}</option>`
                        ).join('');
                    })
                    .catch(() => {});
            }, 250);
        });

        document.getElementById('saveAccountingCodeBtn').addEventListener('click', () => {
            const internalCode = document.getElementById('mappingInternalCode').value.trim();
            const accountingCode = document.getElementById('mappingAccountingCode').value.trim();
            if (!accountingCode) return alert('Chọn mã kế toán cần gán.');

            const button = document.getElementById('saveAccountingCodeBtn');
            button.disabled = true;
            fetch('/api/ton-kho-noi-bo/ma-ke-toan', {
                method: 'PATCH',
                headers: {'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify({internal_item_code: internalCode, ma_hh: accountingCode})
            }).then(response => jsonOrError(response, 'Không gán được mã kế toán'))
              .then(() => {
                  accountingCodeModal.hide();
                  loadStock();
              })
              .catch(error => alert(error.message))
              .finally(() => button.disabled = false);
        });

        function queueSearch(source) {
            if (source === topKeywordEl) keywordEl.value = topKeywordEl.value;
            if (source === keywordEl) topKeywordEl.value = keywordEl.value;
            clearTimeout(searchTimer);
            searchTimer = setTimeout(loadStock, 250);
        }

        warehouseSelect.addEventListener('change', loadStock);
        stockMonthEl.addEventListener('change', loadStock);
        keywordEl.addEventListener('input', () => queueSearch(keywordEl));
        topKeywordEl.addEventListener('input', () => queueSearch(topKeywordEl));
        document.getElementById('reloadBtn').addEventListener('click', () => loadWarehouses().then(loadStock));
        document.getElementById('clearFilterBtn').addEventListener('click', () => {
            warehouseSelect.value = '';
            keywordEl.value = '';
            topKeywordEl.value = '';
            loadStock();
        });

        document.getElementById('voiceStockBtn').addEventListener('click', () => {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SpeechRecognition) return alert('Trình duyệt này chưa hỗ trợ nhận diện giọng nói.');
            const recognition = new SpeechRecognition();
            recognition.lang = 'vi-VN';
            recognition.onresult = event => {
                keywordEl.value = event.results[0][0].transcript.replace(/\s+/g, '');
                topKeywordEl.value = keywordEl.value;
                loadStock();
            };
            recognition.start();
        });

        topKeywordEl.value = keywordEl.value;
        loadWarehouses().then(loadStock).catch(error => {
            rowsEl.innerHTML = `<tr><td colspan="13" class="wms-empty text-danger">${esc(error.message)}</td></tr>`;
        });
    </script>
</body>
</html>
