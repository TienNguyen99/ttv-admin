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
            --quick-blue: #2563eb;
            --quick-blue-soft: #eaf4ff;
            --quick-line: #c7d7ee;
            --quick-ink: #0f172a;
            --quick-muted: #64748b;
            --quick-bg: #f5faff;
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
            width: min(1500px, calc(100% - 28px));
            margin: 0 auto;
            padding: 12px 0 24px;
        }

        .quick-choice {
            width: min(760px, calc(100% - 28px));
            min-height: 100vh;
            margin: 0 auto;
            display: grid;
            place-content: center;
            gap: 18px;
            padding: 24px 0;
            text-align: center;
        }

        .quick-choice h1 {
            margin: 0;
            color: #0f2f63;
            font-size: 30px;
            font-weight: 800;
            letter-spacing: 0;
        }

        .quick-choice-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .quick-choice-btn {
            min-height: 132px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 20px;
            border: 2px solid #bfdbfe;
            border-radius: 16px;
            background: #ffffff;
            color: #0f2f63;
            font-size: 18px;
            font-weight: 800;
            cursor: pointer;
            transition: border-color 180ms ease, background-color 180ms ease, color 180ms ease, transform 180ms ease;
        }

        .quick-choice-btn:hover,
        .quick-choice-btn:focus-visible {
            border-color: #2563eb;
            background: #eaf4ff;
            color: #1d4ed8;
            transform: translateY(-2px);
            outline: none;
        }

        .quick-choice-btn svg { width: 34px; height: 34px; }

        .quick-header {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 14px;
            align-items: center;
            margin-bottom: 12px;
            padding: 12px 14px;
            border: 1px solid #dbeafe;
            border-radius: 16px;
            background: rgba(255,255,255,.92);
            box-shadow: 0 12px 30px rgba(37, 99, 235, 0.08);
        }

        .quick-title {
            margin: 0;
            color: #0f2f63;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: 0;
        }

        .quick-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-end;
        }

        .quick-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 40px;
            padding: 0 14px;
            border: 1px solid var(--quick-line);
            border-radius: 12px;
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
            min-width: 160px;
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
            grid-template-columns: 1fr;
            gap: 12px;
            align-items: start;
        }

        .quick-panel {
            border: 1px solid #dbe3ef;
            border-radius: 16px;
            background: #ffffff;
            box-shadow: 0 10px 24px rgba(37, 99, 235, 0.06);
            overflow: hidden;
        }

        .quick-panel-header {
            display: flex;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            padding: 12px 14px;
            border-bottom: 1px solid #e2e8f0;
        }

        .quick-panel-title {
            margin: 0;
            font-size: 17px;
            font-weight: 800;
        }

        .quick-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            justify-content: flex-end;
        }

        .quick-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-height: 36px;
            padding: 0 12px;
            border: 1px solid #dbeafe;
            border-radius: 999px;
            background: #f8fbff;
            color: #0f2f63;
            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;
        }

        .quick-pill strong {
            color: #1d4ed8;
            font-size: 16px;
        }

        .quick-form-row {
            display: grid;
            grid-template-columns: 190px minmax(280px, 1fr);
            gap: 10px;
            padding: 12px 14px;
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
            min-height: 40px;
            border-color: #b8c7dc;
            border-radius: 10px;
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
            padding: 0 14px 12px;
        }

        .quick-table {
            width: 100%;
            min-width: 1120px;
            border-collapse: separate;
            border-spacing: 0;
        }

        .quick-table th {
            position: sticky;
            top: 0;
            z-index: 1;
            padding: 9px 8px;
            background: #08213d;
            color: #ffffff;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .quick-table td {
            padding: 6px;
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

        .quick-order { min-width: 145px; }
        .quick-code { min-width: 180px; }
        .quick-name { min-width: 230px; }
        .quick-size { min-width: 96px; }
        .quick-color { min-width: 170px; }
        .quick-qty { min-width: 118px; }
        .quick-unit { min-width: 96px; }
        .quick-note { min-width: 180px; }

        .quick-name-cell {
            display: grid;
            grid-template-columns: 42px minmax(0, 1fr);
            gap: 8px;
            align-items: center;
        }

        .quick-item-image {
            display: grid;
            width: 42px;
            height: 42px;
            place-items: center;
            overflow: hidden;
            border: 1px solid #dbeafe;
            border-radius: 10px;
            background: #f8fbff;
            color: #94a3b8;
        }

        .quick-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .quick-item-image.is-empty::before {
            content: "Ảnh";
            font-size: 10px;
            font-weight: 800;
        }

        .row-state {
            min-height: 18px;
            margin-top: 4px;
            color: var(--quick-muted);
            font-size: 11px;
            font-weight: 700;
        }

        .row-state.is-ok { color: var(--quick-good); }
        .row-state.is-warn { color: var(--quick-warn); }

        .quick-recent {
            padding: 0 14px 14px;
        }

        .quick-recent summary {
            cursor: pointer;
            color: #0f2f63;
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
            padding: 0 14px 10px;
            color: var(--quick-muted);
            font-size: 13px;
            font-weight: 700;
        }

        .quick-status.is-error { color: #b91c1c; }
        .quick-status.is-ok { color: var(--quick-good); }

        @media (max-width: 1100px) {
            .quick-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 720px) {
            .quick-choice-actions { grid-template-columns: 1fr; }
            .quick-choice-btn { min-height: 104px; }
            .quick-shell { width: min(100% - 16px, 1440px); padding-top: 8px; }
            .quick-header { grid-template-columns: 1fr; padding: 14px; }
            .quick-title { font-size: 24px; }
            .quick-actions { justify-content: stretch; }
            .quick-btn { flex: 1 1 auto; }
            .quick-form-row { grid-template-columns: 1fr; padding: 12px; }
            .quick-panel-header { align-items: flex-start; flex-direction: column; }
            .quick-toolbar { justify-content: flex-start; }
        }
    </style>
</head>
<body>
    <section id="modeChooser" class="quick-choice">
        <h1>Chọn loại phiếu nhập</h1>
        <div class="quick-choice-actions">
            <button class="quick-choice-btn" type="button" data-receipt-kind="finished">
                <i data-lucide="package-check"></i>
                Nhập thành phẩm
            </button>
            <button class="quick-choice-btn" type="button" data-receipt-kind="semi_finished">
                <i data-lucide="factory"></i>
                Nhập bán thành phẩm
            </button>
        </div>
    </section>

    <main id="receiptWorkspace" class="quick-shell d-none">
        <header class="quick-header">
            <div>
                <h1 id="workspaceTitle" class="quick-title">Nhập thành phẩm</h1>
            </div>
            <div class="quick-actions">
                <button id="changeModeBtn" class="quick-btn" type="button"><i data-lucide="arrow-left"></i>Chọn lại</button>
                <button id="savePrintBtn" class="quick-btn quick-btn-primary" type="button"><i data-lucide="printer"></i>Lưu + in</button>
            </div>
        </header>

        <div class="quick-grid">
            <section class="quick-panel">
                <div class="quick-panel-header">
                    <h2 class="quick-panel-title">Phiếu nhập</h2>
                    <div class="quick-toolbar">
                        <span class="quick-pill">Dòng <strong id="lineCount">0</strong></span>
                        <span class="quick-pill">SL <strong id="totalQty">0</strong></span>
                        <span class="quick-pill">CHUA-XEP</span>
                        <button id="clearFormBtn" class="quick-btn" type="button"><i data-lucide="rotate-ccw"></i>Mới</button>
                    </div>
                </div>
                <div class="quick-form-row">
                    <div>
                        <label for="receiptDate">Ngày nhập</label>
                        <input id="receiptDate" class="form-control" type="date">
                    </div>
                    <div>
                        <label for="receiptNote">Ghi chú</label>
                        <input id="receiptNote" class="form-control" autocomplete="off" placeholder="KCS giao kho, ca sáng">
                    </div>
                </div>

                <div class="quick-table-wrap">
                    <table class="quick-table">
                        <thead>
                            <tr>
                                <th>Stt</th>
                                <th>Lệnh</th>
                                <th>Mã nội bộ *</th>
                                <th>Tên hàng</th>
                                <th>Size</th>
                                <th>Màu</th>
                                <th>SL *</th>
                                <th>ĐVT</th>
                                <th>Ghi chú</th>
                            </tr>
                        </thead>
                        <tbody id="quickRows"></tbody>
                    </table>
                </div>
                <div id="formStatus" class="quick-status">Nhập mã và số lượng, bấm Enter để xuống dòng.</div>
                <details class="quick-recent">
                    <summary class="quick-panel-title mb-2">Phiếu vừa nhập</summary>
                    <div id="recentReceipts" class="pt-2"></div>
                </details>
            </section>
        </div>
    </main>

    <datalist id="internalCatalogOptions"></datalist>
    <datalist id="productionOrderOptions"></datalist>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const rowCount = 10;
        let selectedReceiptKind = '';
        let internalCatalogItems = [];
        let catalogSearchTimer = null;
        let catalogSearchCache = new Map();
        let catalogExactCache = new Map();
        let latestCatalogSearchKey = '';
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

        function selectedReceiptDate() {
            return document.getElementById('receiptDate').value || localIsoDate();
        }

        function openOrderQuery(keyword, limit) {
            const params = new URLSearchParams({
                keyword,
                unfinished: '1',
                order_date_to: selectedReceiptDate(),
                limit: String(limit),
            });
            return `/api/lenh-san-xuat-sheet?${params.toString()}`;
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
                <td class="quick-order">
                    <input class="form-control production-order" list="productionOrderOptions" autocomplete="off" placeholder="G&#245; l&#7879;nh SX ho&#7863;c m&#227; h&#224;ng">
                    <select class="form-select order-suggestions d-none mt-1" aria-label="L&#7879;nh s&#7843;n xu&#7845;t ch&#432;a ho&#224;n t&#7845;t"></select>
                </td>
                <td class="quick-code">
                    <input class="form-control internal-code" list="internalCatalogOptions" autocomplete="off" placeholder="Mã nội bộ">
                    <div class="row-state"></div>
                </td>
                <td class="quick-name">
                    <div class="quick-name-cell">
                        <div class="quick-item-image is-empty"></div>
                        <input class="form-control item-name" autocomplete="off" placeholder="Tự điền">
                    </div>
                </td>
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
            document.querySelectorAll('.order-suggestions').forEach(select => select.addEventListener('change', () => {
                if (!select.value) return;
                const orderInput = select.closest('tr').querySelector('.production-order');
                orderInput.value = select.value;
                applyProductionOrder(orderInput);
            }));
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

        function loadOpenOrdersForItem(input) {
            const code = String(input.value || '').trim().toUpperCase();
            const select = input.closest('tr').querySelector('.order-suggestions');
            if (!code || !select) return Promise.resolve(0);

            return fetch(openOrderQuery(code, 100))
                .then(response => jsonOrError(response, 'Khong tai duoc lenh chua hoan tat'))
                .then(result => {
                    const byOrder = new Map();
                    (result.data || []).forEach(order => {
                        if (String(order.item_code || '').trim().toUpperCase() !== code) return;
                        const orderCode = String(order.production_order || '').trim();
                        if (orderCode && !byOrder.has(orderCode)) byOrder.set(orderCode, order);
                    });
                    const orders = Array.from(byOrder.values());
                    if (!orders.length) {
                        select.innerHTML = '';
                        select.classList.add('d-none');
                        return 0;
                    }
                    select.innerHTML = `<option value="">${orders.length} l&#7879;nh ch&#432;a ho&#224;n t&#7845;t - ch&#7885;n l&#7879;nh</option>` + orders.map(order => {
                        const orderDate = order.received_date ? `Nh\u1eadn l\u1ec7nh ${String(order.received_date).split('-').reverse().join('/')}` : '';
                        const remaining = `C\u00f2n ${fmt(order.remaining_quantity || 0)}`;
                        const detail = [order.production_order, remaining, orderDate, order.customer, order.purchase_order].filter(Boolean).join(' - ');
                        return `<option value="${esc(order.production_order || '')}">${esc(detail)}</option>`;
                    }).join('');
                    select.classList.remove('d-none');
                    return orders.length;
                })
                .catch(() => {
                    select.innerHTML = '';
                    select.classList.add('d-none');
                    return 0;
                });
        }
        function searchProductionOrders(input) {
            const keyword = input.value.trim();
            if (input.dataset.appliedOrder !== keyword.toUpperCase()) {
                delete input.dataset.appliedOrder;
            }
            clearTimeout(productionOrderSearchTimer);
            if (keyword.length < 2) return;

            productionOrderSearchTimer = setTimeout(() => {
                fetch(openOrderQuery(keyword, 20))
                    .then(response => jsonOrError(response, 'Không tải được lệnh sản xuất'))
                    .then(result => {
                        productionOrderOptions = result.data || [];
                        document.getElementById('productionOrderOptions').innerHTML = productionOrderOptions.map(order => {
                            const label = [
                                `C\u00f2n ${fmt(order.remaining_quantity || 0)}`,
                                order.received_date ? `Nh\u1eadn l\u1ec7nh ${String(order.received_date).split('-').reverse().join('/')}` : '',
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
                    const progress = result.summary?.receipt_progress || {};
                    const orderDate = String(progress.order_date || '');
                    const receiptDataStartDate = String(progress.receipt_data_start_date || '');
                    if (orderDate && receiptDataStartDate && orderDate < receiptDataStartDate && !progress.has_linked_finished_receipt) {
                        const displayOrderDate = orderDate.split('-').reverse().join('/');
                        const displayStartDate = receiptDataStartDate.split('-').reverse().join('/');
                        setStatus(`L\u1ec7nh ${input.value} c\u00f3 ng\u00e0y ${displayOrderDate}, tr\u01b0\u1edbc phi\u1ebfu nh\u1eadp th\u00e0nh ph\u1ea9m \u0111\u1ea7u ti\u00ean ${displayStartDate}.`, 'error');
                        return;
                    }
                    if (orderDate && orderDate > selectedReceiptDate()) {
                        const displayOrderDate = orderDate.split('-').reverse().join('/');
                        const displayReceiptDate = selectedReceiptDate().split('-').reverse().join('/');
                        setStatus(`L\u1ec7nh ${input.value} c\u00f3 ng\u00e0y ${displayOrderDate}, sau ng\u00e0y nh\u1eadp kho ${displayReceiptDate}.`, 'error');
                        return;
                    }
                    expandProductionOrder(input, variants, progress);
                    const excess = Number(progress.excess_quantity || 0);
                    if (excess > 0.0001) {
                        const warning = `L\u1ec7nh ${input.value} \u0111\u00e3 nh\u1eadp d\u01b0 ${fmt(excess)}. K\u1ebf ho\u1ea1ch ${fmt(progress.planned_quantity)}, \u0111\u00e3 nh\u1eadp ${fmt(progress.received_quantity)} (k\u1ec3 c\u1ea3 FIFO).`;
                        alert(warning);
                        setStatus(warning, 'error');
                    } else {
                        setStatus(`\u0110\u00e3 n\u1ea1p ${variants.length} d\u00f2ng t\u1eeb l\u1ec7nh ${input.value}. C\u00f2n c\u00f3 th\u1ec3 nh\u1eadp ${fmt(progress.remaining_quantity || 0)}.`);
                    }
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
            row.querySelector('.quantity').value = '';
            row.querySelector('.row-state').textContent = 'Từ lệnh sản xuất';
        }

        function expandProductionOrder(input, variants, progress = {}) {
            const currentRow = input.closest('tr');
            const rows = Array.from(document.querySelectorAll('#quickRows tr'));
            const currentIndex = rows.indexOf(currentRow);
            const targets = [currentRow];

            for (let index = currentIndex + 1; index < rows.length && targets.length < variants.length; index++) {
                if (quickRowIsEmpty(rows[index])) targets.push(rows[index]);
            }

            const remainingQuantity = variants.length === 1 ? Number(progress.remaining_quantity || 0) : null;
            variants.slice(0, targets.length).forEach((variant, index) => {
                fillQuickRow(targets[index], variant);
                if (remainingQuantity !== null) {
                    targets[index].querySelector('.row-state').textContent = `C\u00f2n ${fmt(remainingQuantity)} theo l\u1ec7nh`;
                }
            });
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

            chooseReceiptKind('finished');

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

        function catalogKey(value) {
            return String(value || '').trim().toUpperCase();
        }

        function rememberCatalogItems(items) {
            const known = new Map(internalCatalogItems.map(item => [catalogKey(item.code || item.value || item.name), item]));
            (items || []).forEach(item => {
                const key = catalogKey(item.code || item.value || item.name);
                if (key && !known.has(key)) known.set(key, item);
            });
            internalCatalogItems = Array.from(known.values());
            internalCatalogItems.forEach(item => {
                [item.code, item.value, item.name].forEach(value => {
                    const key = catalogKey(value);
                    if (key) catalogExactCache.set(key, item);
                });
            });
        }

        function searchInternalCatalog(input) {
            const keyword = input.value.trim();
            clearTimeout(catalogSearchTimer);
            if (!keyword) return;
            const key = catalogKey(keyword);
            if (catalogSearchCache.has(key)) {
                internalCatalogItems = catalogSearchCache.get(key);
                renderInternalCatalogOptions();
                return;
            }
            catalogSearchTimer = setTimeout(() => {
                latestCatalogSearchKey = key;
                fetch(`/api/ma-noi-bo-danh-muc?keyword=${encodeURIComponent(keyword)}&limit=30&with_color=0`)
                    .then(response => jsonOrError(response, 'Không tải được danh mục'))
                    .then(result => {
                        if (latestCatalogSearchKey !== key) return;
                        catalogSearchCache.set(key, result.data || []);
                        rememberCatalogItems(result.data || []);
                        renderInternalCatalogOptions();
                    })
                    .catch(() => {});
            }, 90);
        }

        function findCatalogItem(code) {
            const normalized = catalogKey(code);
            if (catalogExactCache.has(normalized)) return catalogExactCache.get(normalized);
            return internalCatalogItems.find(item => {
                return [item.code, item.value, item.name].some(value => catalogKey(value) === normalized);
            });
        }

        function fetchCatalogExact(code) {
            const normalized = String(code || '').trim();
            if (!normalized) return Promise.resolve(null);
            const found = findCatalogItem(normalized);
            if (found) return Promise.resolve(found);
            const key = catalogKey(normalized);
            if (catalogSearchCache.has(key)) {
                internalCatalogItems = catalogSearchCache.get(key);
                renderInternalCatalogOptions();
                return Promise.resolve(findCatalogItem(normalized));
            }
            return fetch(`/api/ma-noi-bo-danh-muc?keyword=${encodeURIComponent(normalized)}&limit=10&with_color=0`)
                .then(response => jsonOrError(response, 'Không tải được danh mục'))
                .then(result => {
                    catalogSearchCache.set(key, result.data || []);
                    rememberCatalogItems(result.data || []);
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
            renderRowImage(row, item.image_url || '');
            if (item.shelf && !row.querySelector('.line-note').value.trim()) {
                row.querySelector('.line-note').value = `Kệ danh mục: ${item.shelf}`;
            }
            const state = row.querySelector('.row-state');
            state.className = 'row-state is-ok';
            state.textContent = item.shelf ? `Đúng danh mục - kệ ${item.shelf}` : 'Đúng danh mục';
        }

        function renderRowImage(row, imageUrl) {
            const box = row.querySelector('.quick-item-image');
            if (!box) return;
            const url = String(imageUrl || '').trim();
            if (!url) {
                box.className = 'quick-item-image is-empty';
                box.innerHTML = '';
                return;
            }
            box.className = 'quick-item-image';
            box.innerHTML = `<img src="${esc(url)}" alt="">`;
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
                return loadOpenOrdersForItem(input).then(orderCount => {
                    if (!moveToQty) return;
                    const row = input.closest('tr');
                    if (orderCount > 0) {
                        row.querySelector('.order-suggestions').focus();
                    } else {
                        row.querySelector('.quantity').focus();
                    }
                });
            });
        }

        function collectLines() {
            const isSemiFinished = selectedReceiptKind === 'semi_finished';
            return Array.from(document.querySelectorAll('#quickRows tr')).map(row => {
                const orderInput = row.querySelector('.production-order');
                const sourceOrder = orderInput.value.trim();
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
                    production_order: isSemiFinished ? '' : sourceOrder,
                    purchase_order: isSemiFinished
                        ? (sourceOrder || orderInput.dataset.purchaseOrder || '')
                        : (orderInput.dataset.purchaseOrder || ''),
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

        async function warnProductionOrderOverages(lines) {
            if (selectedReceiptKind !== 'finished') return true;
            const proposedByOrder = new Map();
            lines.forEach(line => {
                const order = String(line.production_order || '').trim();
                if (!order) return;
                proposedByOrder.set(order, (proposedByOrder.get(order) || 0) + Number(line.quantity || 0));
            });
            if (!proposedByOrder.size) return true;

            const warnings = [];
            for (const [order, proposed] of proposedByOrder.entries()) {
                const response = await fetch(`/api/lenh-san-xuat-sheet?production_order=${encodeURIComponent(order)}&limit=500`);
                const result = await jsonOrError(response, `Khong kiem tra duoc lenh ${order}`);
                const progress = result.summary?.receipt_progress || {};
                const planned = Number(progress.planned_quantity || 0);
                const received = Number(progress.received_quantity || 0);
                const afterSave = received + proposed;
                if (planned > 0 && afterSave > planned + 0.0001) {
                    warnings.push(`${order}: k\u1ebf ho\u1ea1ch ${fmt(planned)}, \u0111\u00e3 nh\u1eadp ${fmt(received)}, phi\u1ebfu n\u00e0y ${fmt(proposed)}, s\u1ebd d\u01b0 ${fmt(afterSave - planned)}`);
                }
            }
            if (!warnings.length) return true;
            return confirm(`C\u1ea2NH B\u00c1O NH\u1eacP D\u01af THEO L\u1ec6NH\n\n${warnings.join('\n')}\n\nFIFO \u0111\u00e3 \u0111\u01b0\u1ee3c t\u00ednh trong s\u1ed1 \u0111\u00e3 nh\u1eadp. V\u1eabn l\u01b0u phi\u1ebfu?`);
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
            if (!await warnProductionOrderOverages(lines)) {
                setStatus('Da huy luu vi so luong vuot lenh san xuat.', 'error');
                return;
            }

            const isBtp = selectedReceiptKind === 'semi_finished';
            const total = lines.reduce((sum, line) => sum + line.quantity, 0);
            const confirmation = isBtp
                ? `Tạo 1 phiếu nhập BTP gồm ${lines.length} dòng, ${lines.length} lệnh BTP con và 1 phiếu xuất nhóm sang sản xuất?\n\nTổng số lượng: ${fmt(total)}`
                : `Lưu 1 phiếu nhập thành phẩm gồm ${lines.length} dòng?\n\nTổng số lượng: ${fmt(total)}`;
            if (!confirm(confirmation)) {
                setStatus('Chưa lưu. Có thể bấm Chọn lại để đổi loại phiếu.');
                return;
            }

            const printWindow = window.open('', '_blank');
            setStatus('Đang lưu phiếu nhập...');
            fetch('/api/kiem-ton-kho/phieu-nhap-tp', {
                method: 'POST',
                headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify({
                    receipt_kind: selectedReceiptKind,
                    send_to_production: isBtp,
                    location_code: 'CHUA-XEP',
                    ma_ko: '',
                    checked_at: document.getElementById('receiptDate').value,
                    note: document.getElementById('receiptNote').value.trim(),
                    lines,
                })
            })
                .then(response => jsonOrError(response, 'Không lưu được phiếu nhập'))
                .then(result => {
                    const productionCodes = result.production_issue?.lines
                        ? [...new Set(result.production_issue.lines.map(line => line.production_order).filter(Boolean))]
                        : [];
                    const suffix = productionCodes.length ? ` Đã tạo ${productionCodes.length} lệnh BTP và gửi sản xuất.` : '';
                    setStatus(`Đã lưu ${result.data?.receipt_code || 'phiếu nhập'}.${suffix} Đang mở phiếu in...`, 'ok');
                    if (result.receipt_print_url && printWindow) {
                        printWindow.location.href = result.receipt_print_url;
                    } else if (printWindow) {
                        printWindow.close();
                    }
                    returnToChooser();
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

        function chooseReceiptKind(kind) {
            selectedReceiptKind = kind;
            const isBtp = kind === 'semi_finished';
            document.getElementById('modeChooser').classList.add('d-none');
            document.getElementById('receiptWorkspace').classList.remove('d-none');
            document.getElementById('workspaceTitle').textContent = isBtp ? 'Nhập bán thành phẩm' : 'Nhập thành phẩm';
            document.getElementById('savePrintBtn').innerHTML = isBtp
                ? '<i data-lucide="send"></i>Lưu + gửi sản xuất'
                : '<i data-lucide="printer"></i>Lưu + in';
            setStatus(isBtp
                ? 'Các dòng sẽ nằm chung một phiếu xuất BTP; mỗi dòng có một lệnh BTP riêng.'
                : 'Nhập mã và số lượng, bấm Enter để xuống dòng.');
            if (window.lucide) lucide.createIcons();
            setTimeout(() => document.querySelector('.production-order')?.focus(), 0);
        }

        function returnToChooser() {
            selectedReceiptKind = '';
            document.getElementById('receiptWorkspace').classList.add('d-none');
            document.getElementById('modeChooser').classList.remove('d-none');
            document.getElementById('receiptNote').value = '';
            renderRows();
            updateSummary();
        }

        function loadRecentReceipts() {
            const params = new URLSearchParams({
                receipt_date: document.getElementById('receiptDate').value,
                receipt_kind: selectedReceiptKind || 'finished',
            });
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
        document.getElementById('receiptDate').addEventListener('change', () => {
            document.getElementById('productionOrderOptions').innerHTML = '';
            document.querySelectorAll('.order-suggestions').forEach(select => {
                select.innerHTML = '';
                select.classList.add('d-none');
            });
            document.querySelectorAll('.internal-code').forEach(input => {
                if (input.value.trim()) loadOpenOrdersForItem(input);
            });
            loadRecentReceipts();
        });
        document.getElementById('changeModeBtn').addEventListener('click', () => {
            if (validLines().length && !confirm('Bỏ dữ liệu đang nhập để chọn lại loại phiếu?')) return;
            returnToChooser();
        });
        document.querySelectorAll('[data-receipt-kind]').forEach(button => {
            button.addEventListener('click', () => chooseReceiptKind(button.dataset.receiptKind));
        });

        document.getElementById('receiptDate').value = localIsoDate();
        renderRows();
        loadProductionOrderFromQuery();
        updateSummary();
        loadRecentReceipts();
        if (window.lucide) lucide.createIcons();
    </script>
</body>
</html>
