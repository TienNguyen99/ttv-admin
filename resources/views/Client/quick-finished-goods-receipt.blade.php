<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Nhập thành phẩm nhanh</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --quick-blue: #1e40af;
            --quick-blue-soft: #eff6ff;
            --quick-line: #cbd5e1;
            --quick-ink: #0f172a;
            --quick-muted: #64748b;
            --quick-bg: #f8fafc;
            --quick-good: #15803d;
            --quick-warn: #b45309;
        }

        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            margin: 0;
            background: var(--quick-bg);
            color: var(--quick-ink);
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .quick-shell {
            width: min(1440px, calc(100% - 32px));
            margin: 0 auto;
            padding: 18px 0 28px;
        }

        .quick-header {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 18px;
            align-items: center;
            margin-bottom: 16px;
            padding: 18px 20px;
            border: 1px solid #dbeafe;
            border-radius: 10px;
            background: #ffffff;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
        }

        .quick-title {
            margin: 0;
            color: #0f2f63;
            font-size: 30px;
            font-weight: 800;
            letter-spacing: 0;
        }

        .quick-subtitle {
            margin: 4px 0 0;
            color: var(--quick-muted);
            font-size: 14px;
            font-weight: 500;
        }

        .quick-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: flex-end;
        }

        .quick-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 46px;
            padding: 0 18px;
            border: 1px solid var(--quick-line);
            border-radius: 8px;
            background: #ffffff;
            color: #0f2f63;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 180ms ease, border-color 180ms ease, color 180ms ease, box-shadow 180ms ease;
        }

        .quick-btn:hover {
            border-color: #93c5fd;
            background: var(--quick-blue-soft);
            color: var(--quick-blue);
        }

        .quick-btn-primary {
            min-width: 190px;
            border-color: var(--quick-blue);
            background: var(--quick-blue);
            color: #ffffff;
            box-shadow: 0 12px 24px rgba(30, 64, 175, 0.22);
        }

        .quick-btn-primary:hover {
            background: #1d4ed8;
            color: #ffffff;
        }

        .quick-grid {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 16px;
            align-items: start;
        }

        .quick-panel {
            border: 1px solid #dbe3ef;
            border-radius: 10px;
            background: #ffffff;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
        }

        .quick-panel-header {
            display: flex;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
            border-bottom: 1px solid #e2e8f0;
        }

        .quick-panel-title {
            margin: 0;
            font-size: 17px;
            font-weight: 800;
        }

        .quick-form-row {
            display: grid;
            grid-template-columns: 190px 1fr;
            gap: 12px;
            padding: 16px;
            border-bottom: 1px solid #eef2f7;
        }

        label {
            margin-bottom: 6px;
            color: #334155;
            font-size: 13px;
            font-weight: 800;
        }

        .form-control,
        .form-select {
            min-height: 44px;
            border-color: #b8c7dc;
            border-radius: 7px;
            color: var(--quick-ink);
            font-weight: 600;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .quick-table-wrap {
            overflow-x: auto;
            padding: 0 16px 16px;
        }

        .quick-table {
            width: 100%;
            min-width: 980px;
            border-collapse: separate;
            border-spacing: 0;
        }

        .quick-table th {
            position: sticky;
            top: 0;
            z-index: 1;
            padding: 10px 8px;
            background: #08213d;
            color: #ffffff;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .quick-table td {
            padding: 8px;
            border-bottom: 1px solid #e2e8f0;
            background: #ffffff;
            vertical-align: top;
        }

        .quick-table tbody tr:focus-within td {
            background: #f8fbff;
        }

        .quick-row-index {
            width: 44px;
            color: #475569;
            font-weight: 800;
            text-align: center;
        }

        .quick-order { min-width: 150px; }
        .quick-code { min-width: 180px; }
        .quick-name { min-width: 230px; }
        .quick-size { min-width: 105px; }
        .quick-color { min-width: 180px; }
        .quick-qty { min-width: 120px; }
        .quick-unit { min-width: 105px; }
        .quick-note { min-width: 230px; }

        .row-state {
            min-height: 18px;
            margin-top: 4px;
            color: var(--quick-muted);
            font-size: 11px;
            font-weight: 700;
        }

        .row-state.is-ok { color: var(--quick-good); }
        .row-state.is-warn { color: var(--quick-warn); }

        .quick-side-card {
            padding: 16px;
        }

        .quick-stat {
            display: grid;
            grid-template-columns: 46px 1fr;
            gap: 12px;
            align-items: center;
            padding: 14px;
            border: 1px solid #dbeafe;
            border-radius: 10px;
            background: #f8fbff;
        }

        .quick-stat + .quick-stat { margin-top: 10px; }
        .quick-stat-icon {
            display: grid;
            width: 46px;
            height: 46px;
            place-items: center;
            border-radius: 8px;
            background: #dbeafe;
            color: var(--quick-blue);
        }

        .quick-stat-label {
            color: var(--quick-muted);
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .quick-stat-value {
            color: #0f2f63;
            font-size: 24px;
            font-weight: 800;
            line-height: 1.1;
        }

        .quick-help {
            margin-top: 14px;
            padding: 14px;
            border: 1px solid #fde68a;
            border-radius: 10px;
            background: #fffbeb;
            color: #713f12;
            font-size: 13px;
            font-weight: 650;
            line-height: 1.5;
        }

        .quick-recent {
            margin-top: 16px;
        }

        .recent-item {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
        }

        .recent-code {
            color: #0f2f63;
            font-weight: 800;
        }

        .quick-status {
            min-height: 22px;
            padding: 0 16px 14px;
            color: var(--quick-muted);
            font-size: 13px;
            font-weight: 700;
        }

        .quick-status.is-error { color: #b91c1c; }
        .quick-status.is-ok { color: var(--quick-good); }

        @media (max-width: 1100px) {
            .quick-grid { grid-template-columns: 1fr; }
            .quick-side-card { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
            .quick-stat + .quick-stat { margin-top: 0; }
            .quick-help, .quick-recent { grid-column: 1 / -1; }
        }

        @media (max-width: 720px) {
            .quick-shell { width: min(100% - 16px, 1440px); padding-top: 8px; }
            .quick-header { grid-template-columns: 1fr; padding: 14px; }
            .quick-title { font-size: 24px; }
            .quick-actions { justify-content: stretch; }
            .quick-btn { flex: 1 1 auto; }
            .quick-form-row { grid-template-columns: 1fr; padding: 12px; }
            .quick-side-card { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <main class="quick-shell">
        <header class="quick-header">
            <div>
                <h1 class="quick-title">Nhập thành phẩm nhanh</h1>
                <p class="quick-subtitle">Màn hình đơn giản cho người trực thay: nhập mã, số lượng rồi in phiếu. Vị trí mặc định là CHUA-XEP.</p>
            </div>
            <div class="quick-actions">
                <a class="quick-btn" href="{{ url('/client/kiem-ton-kho') }}"><i data-lucide="arrow-left"></i>Quản lý kho</a>
                <button id="savePrintBtn" class="quick-btn quick-btn-primary" type="button"><i data-lucide="printer"></i>Lưu + In phiếu</button>
            </div>
        </header>

        <div class="quick-grid">
            <section class="quick-panel">
                <div class="quick-panel-header">
                    <h2 class="quick-panel-title">Thông tin phiếu</h2>
                    <button id="clearFormBtn" class="quick-btn" type="button"><i data-lucide="rotate-ccw"></i>Làm mới</button>
                </div>
                <div class="quick-form-row">
                    <div>
                        <label for="receiptDate">Ngày nhập</label>
                        <input id="receiptDate" class="form-control" type="date">
                    </div>
                    <div>
                        <label for="receiptNote">Ghi chú phiếu</label>
                        <input id="receiptNote" class="form-control" autocomplete="off" placeholder="Ví dụ: KCS giao kho, ca sáng">
                    </div>
                </div>

                <div class="quick-table-wrap">
                    <table class="quick-table">
                        <thead>
                            <tr>
                                <th>Stt</th>
                                <th>Lệnh SX</th>
                                <th>Mã nội bộ *</th>
                                <th>Tên hàng</th>
                                <th>Size</th>
                                <th>Màu</th>
                                <th>Số lượng *</th>
                                <th>ĐVT</th>
                                <th>Ghi chú dòng</th>
                            </tr>
                        </thead>
                        <tbody id="quickRows"></tbody>
                    </table>
                </div>
                <div id="formStatus" class="quick-status">Sẵn sàng nhập. Người dùng chỉ cần nhập dòng có mã nội bộ và số lượng.</div>
            </section>

            <aside class="quick-panel quick-side-card">
                <div class="quick-stat">
                    <div class="quick-stat-icon"><i data-lucide="rows-3"></i></div>
                    <div>
                        <div class="quick-stat-label">Dòng có dữ liệu</div>
                        <div id="lineCount" class="quick-stat-value">0</div>
                    </div>
                </div>
                <div class="quick-stat">
                    <div class="quick-stat-icon"><i data-lucide="boxes"></i></div>
                    <div>
                        <div class="quick-stat-label">Tổng số lượng</div>
                        <div id="totalQty" class="quick-stat-value">0</div>
                    </div>
                </div>
                <div class="quick-stat">
                    <div class="quick-stat-icon"><i data-lucide="map-pin"></i></div>
                    <div>
                        <div class="quick-stat-label">Vị trí nhập</div>
                        <div class="quick-stat-value">CHUA-XEP</div>
                    </div>
                </div>
                <div class="quick-help">
                    Gõ mã nội bộ để hệ thống tự điền tên, size, màu, ĐVT từ danh mục. Nếu mã không tự điền, dừng lại và báo thủ kho kiểm tra danh mục trước khi lưu.
                </div>
                <div class="quick-recent">
                    <div class="quick-panel-title mb-2">Phiếu vừa nhập</div>
                    <div id="recentReceipts"></div>
                </div>
            </aside>
        </div>
    </main>

    <datalist id="internalCatalogOptions"></datalist>
    <datalist id="productionOrderOptions"></datalist>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const rowCount = 10;
        let internalCatalogItems = [];
        let catalogSearchTimer = null;
        let productionOrderOptions = [];
        let productionOrderSearchTimer = null;

        function localIsoDate() {
            const date = new Date();
            date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
            return date.toISOString().slice(0, 10);
        }

        function esc(value) {
            return String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
        }

        function num(value) {
            const normalized = String(value ?? '').trim().replace(/\s/g, '').replace(',', '.');
            return Number(normalized || 0);
        }

        function fmt(value) {
            return Number(value || 0).toLocaleString('vi-VN', { maximumFractionDigits: 3 });
        }

        function setStatus(message, type = '') {
            const status = document.getElementById('formStatus');
            status.className = `quick-status ${type ? 'is-' + type : ''}`;
            status.textContent = message;
        }

        function jsonOrError(response, fallback) {
            return response.json().catch(() => ({})).then(result => {
                if (!response.ok) {
                    const errors = result.errors ? Object.values(result.errors).flat().join('\n') : '';
                    throw new Error(result.message || errors || fallback);
                }
                return result;
            });
        }

        function rowTemplate(index) {
            return `<tr>
                <td class="quick-row-index">${index + 1}</td>
                <td class="quick-order"><input class="form-control production-order" list="productionOrderOptions" autocomplete="off" placeholder="Gõ lệnh SX"></td>
                <td class="quick-code">
                    <input class="form-control internal-code" list="internalCatalogOptions" autocomplete="off" placeholder="Mã nội bộ">
                    <div class="row-state"></div>
                </td>
                <td class="quick-name"><input class="form-control item-name" autocomplete="off" placeholder="Tự điền"></td>
                <td class="quick-size"><input class="form-control item-size" autocomplete="off"></td>
                <td class="quick-color"><input class="form-control item-color" autocomplete="off"></td>
                <td class="quick-qty"><input class="form-control quantity" type="text" inputmode="decimal" autocomplete="off" placeholder="0"></td>
                <td class="quick-unit"><input class="form-control item-unit" autocomplete="off" placeholder="Cái"></td>
                <td class="quick-note"><input class="form-control line-note" autocomplete="off" placeholder="Ghi chú nếu có"></td>
            </tr>`;
        }

        function renderRows() {
            document.getElementById('quickRows').innerHTML = Array.from({ length: rowCount }, (_, index) => rowTemplate(index)).join('');
            document.querySelectorAll('.production-order').forEach(input => input.addEventListener('input', () => searchProductionOrders(input)));
            document.querySelectorAll('.production-order').forEach(input => input.addEventListener('change', () => applyProductionOrder(input)));
            document.querySelectorAll('.internal-code').forEach(input => input.addEventListener('input', () => searchInternalCatalog(input)));
            document.querySelectorAll('.internal-code').forEach(input => input.addEventListener('change', () => applyInternalCatalog(input, true)));
            document.querySelectorAll('.quantity').forEach(input => input.addEventListener('input', updateSummary));
            document.querySelectorAll('input').forEach(input => {
                input.addEventListener('keydown', event => {
                    if (event.key !== 'Enter') return;
                    event.preventDefault();
                    focusNext(input);
                });
            });
        }

        function focusNext(input) {
            if (input.classList.contains('production-order') && applyProductionOrder(input)) return;
            if (input.classList.contains('quantity')) {
                const nextRow = input.closest('tr')?.nextElementSibling;
                const nextOrder = nextRow?.querySelector('.production-order');
                if (nextOrder) {
                    nextOrder.focus();
                    nextOrder.select();
                    return;
                }
            }
            const inputs = Array.from(document.querySelectorAll('input:not([type="hidden"])'));
            const index = inputs.indexOf(input);
            if (index >= 0 && inputs[index + 1]) inputs[index + 1].focus();
        }

        function searchProductionOrders(input) {
            const keyword = input.value.trim();
            if (input.dataset.appliedOrder !== keyword.toUpperCase()) {
                delete input.dataset.appliedOrder;
            }
            clearTimeout(productionOrderSearchTimer);
            if (keyword.length < 2) return;

            productionOrderSearchTimer = setTimeout(() => {
                fetch(`/api/lenh-san-xuat-sheet?keyword=${encodeURIComponent(keyword)}&limit=20`)
                    .then(response => jsonOrError(response, 'Không tải được lệnh sản xuất'))
                    .then(result => {
                        productionOrderOptions = result.data || [];
                        document.getElementById('productionOrderOptions').innerHTML = productionOrderOptions.map(order => {
                            const label = [
                                order.customer,
                                order.item_code,
                                order.size ? `Size ${order.size}` : '',
                                order.color ? `Màu ${order.color}` : '',
                                order.description
                            ].filter(Boolean).join(' - ');
                            return `<option value="${esc(order.production_order || '')}" label="${esc(label)}"></option>`;
                        }).join('');
                    })
                    .catch(() => {});
            }, 180);
        }

        function applyProductionOrder(input) {
            const code = input.value.trim().toUpperCase();
            if (!code || input.dataset.appliedOrder === code || input.dataset.loadingOrder === code) return false;
            input.dataset.loadingOrder = code;
            fetch(`/api/lenh-san-xuat-sheet?production_order=${encodeURIComponent(input.value.trim())}&limit=500`)
                .then(response => jsonOrError(response, 'Không tải được chi tiết lệnh sản xuất'))
                .then(result => {
                    const variants = result.data || [];
                    if (!variants.length) {
                        setStatus(`Không tìm thấy lệnh SX ${input.value}. Có thể nhập tay mã nội bộ.`, 'error');
                        return;
                    }
                    expandProductionOrder(input, variants);
                    setStatus(`Đã nạp ${variants.length} dòng từ lệnh ${input.value}.`);
                })
                .catch(error => setStatus(error.message, 'error'))
                .finally(() => delete input.dataset.loadingOrder);
            return true;
        }

        function quickRowIsEmpty(row) {
            return !Array.from(row.querySelectorAll('input')).some(input => input.value.trim() !== '');
        }

        function fillQuickRow(row, order) {
            const orderInput = row.querySelector('.production-order');
            orderInput.value = order.production_order || '';
            orderInput.dataset.appliedOrder = String(order.production_order || '').trim().toUpperCase();
            orderInput.dataset.productionOrderId = order.id || '';
            orderInput.dataset.purchaseOrder = order.purchase_order || '';
            orderInput.dataset.customer = order.customer || '';
            row.querySelector('.internal-code').value = order.item_code || '';
            row.querySelector('.item-name').value = order.description || order.specification || '';
            row.querySelector('.item-size').value = order.size || '';
            row.querySelector('.item-color').value = order.color || '';
            row.querySelector('.item-unit').value = order.unit || row.querySelector('.item-unit').value || 'Cái';
            row.querySelector('.quantity').value = Number(order.order_quantity || 0) || '';
            row.querySelector('.row-state').textContent = 'Từ lệnh sản xuất';
        }

        function expandProductionOrder(input, variants) {
            const currentRow = input.closest('tr');
            const rows = Array.from(document.querySelectorAll('#quickRows tr'));
            const currentIndex = rows.indexOf(currentRow);
            const targets = [currentRow];

            for (let index = currentIndex + 1; index < rows.length && targets.length < variants.length; index++) {
                if (quickRowIsEmpty(rows[index])) targets.push(rows[index]);
            }

            variants.slice(0, targets.length).forEach((variant, index) => fillQuickRow(targets[index], variant));
            if (targets.length < variants.length) {
                alert(`Lệnh ${input.value} có ${variants.length} dòng size/màu, màn hình nhanh chỉ nhận ${targets.length} dòng trống. Dùng form nhập chính nếu cần nhiều hơn.`);
            }
            updateSummary();
            targets[0].querySelector('.quantity')?.focus();
        }

        function loadProductionOrderFromQuery() {
            const params = new URLSearchParams(window.location.search);
            const productionOrder = params.get('production_order');
            if (!productionOrder) return;

            const firstInput = document.querySelector('.production-order');
            if (!firstInput) return;
            firstInput.value = productionOrder;
            applyProductionOrder(firstInput);
        }

        function renderInternalCatalogOptions() {
            document.getElementById('internalCatalogOptions').innerHTML = internalCatalogItems.map(item => {
                const label = [
                    item.name,
                    item.size ? `Size ${item.size}` : '',
                    item.color ? `Màu ${item.color}` : '',
                    item.unit,
                    item.shelf ? `Kệ ${item.shelf}` : '',
                ].filter(Boolean).join(' - ');
                return `<option value="${esc(item.value || item.code || item.name || '')}" label="${esc(label)}"></option>`;
            }).join('');
        }

        function searchInternalCatalog(input) {
            const keyword = input.value.trim();
            clearTimeout(catalogSearchTimer);
            if (!keyword) return;
            catalogSearchTimer = setTimeout(() => {
                fetch(`/api/ma-noi-bo-danh-muc?keyword=${encodeURIComponent(keyword)}&limit=30`)
                    .then(response => jsonOrError(response, 'Không tải được danh mục'))
                    .then(result => {
                        internalCatalogItems = result.data || [];
                        renderInternalCatalogOptions();
                    })
                    .catch(() => {});
            }, 180);
        }

        function findCatalogItem(code) {
            const normalized = String(code || '').trim().toUpperCase();
            return internalCatalogItems.find(item => {
                return [item.code, item.value, item.name].some(value => String(value || '').trim().toUpperCase() === normalized);
            });
        }

        function fetchCatalogExact(code) {
            const normalized = String(code || '').trim();
            if (!normalized) return Promise.resolve(null);
            const found = findCatalogItem(normalized);
            if (found) return Promise.resolve(found);
            return fetch(`/api/ma-noi-bo-danh-muc?keyword=${encodeURIComponent(normalized)}&limit=10`)
                .then(response => jsonOrError(response, 'Không tải được danh mục'))
                .then(result => {
                    internalCatalogItems = [...(result.data || []), ...internalCatalogItems];
                    renderInternalCatalogOptions();
                    return findCatalogItem(normalized);
                })
                .catch(() => null);
        }

        function applyCatalogItem(input, item) {
            const row = input.closest('tr');
            input.value = item.code || item.value || input.value;
            row.querySelector('.item-name').value = item.name || row.querySelector('.item-name').value;
            row.querySelector('.item-size').value = item.size || row.querySelector('.item-size').value;
            row.querySelector('.item-color').value = item.color || row.querySelector('.item-color').value;
            row.querySelector('.item-unit').value = item.unit || row.querySelector('.item-unit').value || 'Cái';
            if (item.shelf && !row.querySelector('.line-note').value.trim()) {
                row.querySelector('.line-note').value = `Kệ danh mục: ${item.shelf}`;
            }
            const state = row.querySelector('.row-state');
            state.className = 'row-state is-ok';
            state.textContent = item.shelf ? `Đúng danh mục - kệ ${item.shelf}` : 'Đúng danh mục';
        }

        function applyInternalCatalog(input, moveToQty = false) {
            const code = input.value.trim();
            if (!code) return Promise.resolve();
            return fetchCatalogExact(code).then(item => {
                const state = input.closest('tr').querySelector('.row-state');
                if (!item) {
                    state.className = 'row-state is-warn';
                    state.textContent = 'Chưa thấy trong danh mục';
                    return;
                }
                applyCatalogItem(input, item);
                if (moveToQty) input.closest('tr').querySelector('.quantity').focus();
            });
        }

        function collectLines() {
            return Array.from(document.querySelectorAll('#quickRows tr')).map(row => {
                const orderInput = row.querySelector('.production-order');
                return {
                    category: row.querySelector('.item-name').value.trim(),
                    ma_sp: '',
                    internal_item_code: row.querySelector('.internal-code').value.trim(),
                    size: row.querySelector('.item-size').value.trim(),
                    color: row.querySelector('.item-color').value.trim(),
                    side: '',
                    dvt: row.querySelector('.item-unit').value.trim() || 'Cái',
                    quantity: num(row.querySelector('.quantity').value),
                    location_code: 'CHUA-XEP',
                    note: row.querySelector('.line-note').value.trim(),
                    production_order_id: orderInput.dataset.productionOrderId || null,
                    production_order: orderInput.value.trim(),
                    purchase_order: orderInput.dataset.purchaseOrder || '',
                    customer: orderInput.dataset.customer || '',
                };
            }).filter(line => line.internal_item_code || line.quantity);
        }

        function validLines() {
            return collectLines().filter(line => line.internal_item_code && line.quantity > 0);
        }

        function updateSummary() {
            const lines = validLines();
            document.getElementById('lineCount').textContent = fmt(lines.length);
            document.getElementById('totalQty').textContent = fmt(lines.reduce((sum, line) => sum + line.quantity, 0));
        }

        async function checkAllCatalog() {
            const rows = Array.from(document.querySelectorAll('#quickRows tr'));
            for (const row of rows) {
                const codeInput = row.querySelector('.internal-code');
                const qty = num(row.querySelector('.quantity').value);
                if (!codeInput.value.trim() && !qty) continue;
                await applyInternalCatalog(codeInput, false);
            }
        }

        async function warnDuplicates(lines) {
            const response = await fetch('/api/kiem-ton-kho/phieu-nhap-tp/kiem-tra-trung', {
                method: 'POST',
                headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify({
                    checked_at: document.getElementById('receiptDate').value,
                    lines,
                })
            });
            const result = await response.json().catch(() => ({}));
            const first = (result.duplicates || [])[0];
            if (!first) return true;
            return confirm(`Cảnh báo trùng: ${first.internal_item_code}, SL ${fmt(first.quantity)} đã có trong phiếu cũ.\n\nVẫn lưu phiếu này?`);
        }

        async function saveAndPrint() {
            setStatus('Đang kiểm tra mã danh mục...');
            await checkAllCatalog();
            const lines = validLines();
            updateSummary();
            if (!lines.length) {
                setStatus('Cần ít nhất 1 dòng có mã nội bộ và số lượng lớn hơn 0.', 'error');
                return;
            }
            if (!await warnDuplicates(lines)) {
                setStatus('Đã hủy lưu vì phiếu có dòng nghi trùng.', 'error');
                return;
            }

            const printWindow = window.open('', '_blank');
            setStatus('Đang lưu phiếu nhập...');
            fetch('/api/kiem-ton-kho/phieu-nhap-tp', {
                method: 'POST',
                headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify({
                    location_code: 'CHUA-XEP',
                    ma_ko: '',
                    checked_at: document.getElementById('receiptDate').value,
                    note: document.getElementById('receiptNote').value.trim(),
                    lines,
                })
            })
                .then(response => jsonOrError(response, 'Không lưu được phiếu nhập'))
                .then(result => {
                    setStatus(`Đã lưu ${result.data?.receipt_code || 'phiếu nhập'}. Đang mở phiếu in...`, 'ok');
                    if (result.receipt_print_url && printWindow) {
                        printWindow.location.href = result.receipt_print_url;
                    } else if (printWindow) {
                        printWindow.close();
                    }
                    resetRows(false);
                    loadRecentReceipts();
                })
                .catch(error => {
                    if (printWindow) printWindow.close();
                    setStatus(error.message, 'error');
                    alert(error.message);
                });
        }

        function resetRows(clearHeader = true) {
            if (clearHeader) document.getElementById('receiptNote').value = '';
            renderRows();
            updateSummary();
            document.querySelector('.internal-code')?.focus();
        }

        function loadRecentReceipts() {
            const params = new URLSearchParams({ receipt_date: document.getElementById('receiptDate').value });
            fetch(`/api/kiem-ton-kho/phieu-nhap-tp?${params}`)
                .then(response => jsonOrError(response, 'Không tải được phiếu vừa nhập'))
                .then(result => {
                    const rows = (result.data || []).slice(0, 5);
                    document.getElementById('recentReceipts').innerHTML = rows.map(row => `
                        <div class="recent-item">
                            <div>
                                <div class="recent-code">${esc(row.receipt_code)}</div>
                                <div class="text-muted">${esc(row.location_code || 'CHUA-XEP')} - ${fmt(row.lines_count || 0)} dòng</div>
                            </div>
                            <a class="quick-btn py-1 px-2 min-h-0" target="_blank" href="${esc(row.print_url || '#')}">In</a>
                        </div>
                    `).join('') || '<div class="text-muted">Chưa có phiếu hôm nay.</div>';
                })
                .catch(() => {});
        }

        document.getElementById('savePrintBtn').addEventListener('click', saveAndPrint);
        document.getElementById('clearFormBtn').addEventListener('click', () => resetRows(true));
        document.getElementById('receiptDate').addEventListener('change', loadRecentReceipts);

        document.getElementById('receiptDate').value = localIsoDate();
        renderRows();
        loadProductionOrderFromQuery();
        updateSummary();
        loadRecentReceipts();
        if (window.lucide) lucide.createIcons();
    </script>
</body>
</html>
