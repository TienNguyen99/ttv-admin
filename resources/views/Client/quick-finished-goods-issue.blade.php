<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Xuất thành phẩm nhanh</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --blue: #2563eb;
            --blue-dark: #15366d;
            --blue-soft: #eaf4ff;
            --line: #c7d7ee;
            --ink: #0f172a;
            --muted: #64748b;
            --bg: #f5faff;
            --good: #15803d;
            --danger: #b91c1c;
        }
        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .issue-shell {
            width: min(1500px, calc(100% - 28px));
            margin: 0 auto;
            padding: 12px 0 28px;
        }
        .issue-header,
        .issue-panel {
            border: 1px solid #dbeafe;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 10px 26px rgba(37, 99, 235, .07);
        }
        .issue-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
            padding: 12px 14px;
        }
        .issue-title {
            margin: 0;
            color: var(--blue-dark);
            font-size: 24px;
            font-weight: 800;
        }
        .issue-actions,
        .panel-tools {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        .btn-quick {
            min-height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 0 14px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            color: var(--blue-dark);
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-quick:hover,
        .btn-quick:focus-visible {
            border-color: #93c5fd;
            background: var(--blue-soft);
            color: var(--blue);
            outline: none;
        }
        .btn-primary-quick {
            min-width: 150px;
            border-color: var(--blue);
            background: var(--blue);
            color: #fff;
        }
        .btn-primary-quick:hover { background: #1d4ed8; color: #fff; }
        .btn-quick:disabled { cursor: wait; opacity: .65; }
        .issue-panel { overflow: hidden; }
        .panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 14px;
            border-bottom: 1px solid #e2e8f0;
        }
        .panel-title { margin: 0; font-size: 16px; font-weight: 800; }
        .pill {
            min-height: 34px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 0 10px;
            border: 1px solid #dbeafe;
            border-radius: 999px;
            background: #f8fbff;
            color: var(--blue-dark);
            font-size: 12px;
            font-weight: 800;
        }
        .issue-meta {
            display: grid;
            grid-template-columns: 180px minmax(180px, .8fr) minmax(180px, .8fr) minmax(240px, 1.5fr);
            gap: 10px;
            padding: 12px 14px;
            border-bottom: 1px solid #e2e8f0;
        }
        label {
            margin-bottom: 5px;
            color: #334155;
            font-size: 12px;
            font-weight: 800;
        }
        .form-control,
        .form-select {
            min-height: 40px;
            border-color: #b8c7dc;
            border-radius: 8px;
            color: var(--ink);
            font-weight: 600;
        }
        .form-control:focus,
        .form-select:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .14);
        }
        .table-wrap {
            overflow: auto;
            padding: 0 14px 10px;
        }
        .issue-table {
            width: 100%;
            min-width: 1290px;
            border-collapse: separate;
            border-spacing: 0;
        }
        .issue-table th {
            position: sticky;
            top: 0;
            z-index: 2;
            padding: 9px 8px;
            background: #092a50;
            color: #fff;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .issue-table td {
            padding: 7px 5px;
            border-bottom: 1px solid #dbe3ef;
            vertical-align: top;
        }
        .issue-table .form-control { min-height: 38px; padding: 7px 9px; font-size: 13px; }
        .col-stt { width: 42px; text-align: center; }
        .col-order { width: 130px; }
        .col-code { width: 155px; }
        .col-name { width: 210px; }
        .col-variant { width: 125px; }
        .col-qty { width: 110px; }
        .col-unit { width: 90px; }
        .col-location { width: 100px; }
        .col-note { width: 160px; }
        .col-remove { width: 42px; }
        .suggest-wrap { position: relative; }
        .suggestions {
            position: absolute;
            z-index: 20;
            top: calc(100% + 4px);
            left: 0;
            width: min(440px, 82vw);
            max-height: 260px;
            overflow: auto;
            border: 1px solid #93c5fd;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 18px 45px rgba(15, 47, 99, .2);
        }
        .suggestion {
            width: 100%;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 4px 12px;
            padding: 9px 11px;
            border: 0;
            border-bottom: 1px solid #e2e8f0;
            background: #fff;
            color: var(--ink);
            text-align: left;
        }
        .suggestion:hover,
        .suggestion:focus-visible { background: var(--blue-soft); outline: none; }
        .suggestion strong { color: #1d4ed8; font-size: 13px; }
        .suggestion small { color: var(--muted); font-size: 11px; }
        .suggestion span { grid-row: 1 / 3; grid-column: 2; align-self: center; color: #475569; font-size: 11px; }
        .row-state { min-height: 16px; margin-top: 3px; color: var(--good); font-size: 10px; font-weight: 700; }
        .remove-row {
            width: 34px;
            height: 34px;
            display: inline-grid;
            place-items: center;
            border: 1px solid #fecaca;
            border-radius: 8px;
            background: #fff;
            color: var(--danger);
        }
        .status {
            min-height: 42px;
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 14px 12px;
            padding: 9px 11px;
            border: 1px solid #dbeafe;
            border-radius: 8px;
            background: #f8fbff;
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
        }
        .status.is-error { border-color: #fecaca; background: #fff1f2; color: var(--danger); }
        .status.is-success { border-color: #bbf7d0; background: #f0fdf4; color: var(--good); }
        .status svg { width: 17px; height: 17px; flex: 0 0 auto; }
        .confirm-dialog {
            width: min(440px, calc(100% - 24px));
            padding: 0;
            border: 0;
            border-radius: 8px;
            box-shadow: 0 24px 70px rgba(15, 23, 42, .3);
        }
        .confirm-dialog::backdrop { background: rgba(15, 23, 42, .48); }
        .confirm-body { padding: 18px; }
        .confirm-body h2 { margin: 0 0 8px; color: var(--blue-dark); font-size: 18px; font-weight: 800; }
        .confirm-body p { margin: 0; color: #475569; white-space: pre-line; }
        .confirm-actions { display: flex; justify-content: flex-end; gap: 8px; padding: 12px 18px; border-top: 1px solid #e2e8f0; }
        .spin { animation: spin .8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (max-width: 900px) {
            .issue-meta { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 620px) {
            .issue-shell { width: calc(100% - 16px); padding-top: 8px; }
            .issue-header { align-items: stretch; flex-direction: column; }
            .issue-title { font-size: 21px; }
            .issue-actions .btn-quick { flex: 1; }
            .issue-meta { grid-template-columns: 1fr; }
            .panel-head { align-items: flex-start; flex-direction: column; }
        }
    </style>
</head>
<body>
<main class="issue-shell">
    <header class="issue-header">
        <h1 class="issue-title">Xuất thành phẩm</h1>
        <div class="issue-actions">
            <a class="btn-quick" href="{{ url('/client/nhap-thanh-pham-nhanh') }}"><i data-lucide="arrow-left"></i>Chọn lại</a>
            <button id="saveIssueBtn" class="btn-quick btn-primary-quick" type="button"><i data-lucide="printer"></i>Lưu + in</button>
        </div>
    </header>

    <section class="issue-panel">
        <div class="panel-head">
            <h2 class="panel-title">Phiếu xuất</h2>
            <div class="panel-tools">
                <span class="pill">Dòng <strong id="lineCount">0</strong></span>
                <span class="pill">SL <strong id="totalQuantity">0</strong></span>
                <button id="addRowBtn" class="btn-quick" type="button"><i data-lucide="plus"></i>Thêm dòng</button>
                <button id="resetBtn" class="btn-quick" type="button"><i data-lucide="rotate-ccw"></i>Mới</button>
            </div>
        </div>

        <div class="issue-meta">
            <div>
                <label for="issueDate">Ngày xuất *</label>
                <input id="issueDate" class="form-control" type="text" inputmode="numeric" autocomplete="off" placeholder="dd/mm/yyyy">
            </div>
            <div>
                <label for="customerName">Khách hàng *</label>
                <input id="customerName" class="form-control" autocomplete="off" placeholder="UNIPAX, ELITE...">
            </div>
            <div>
                <label for="receiverName">Người nhận</label>
                <input id="receiverName" class="form-control" autocomplete="off" placeholder="Tên người nhận">
            </div>
            <div>
                <label for="issueNote">Ghi chú phiếu</label>
                <input id="issueNote" class="form-control" autocomplete="off" placeholder="Giao hàng, xuất tại chỗ...">
            </div>
        </div>

        <div class="table-wrap">
            <table class="issue-table">
                <thead>
                    <tr>
                        <th class="col-stt">STT</th>
                        <th class="col-order">Lệnh / PS</th>
                        <th class="col-code">Mã nội bộ *</th>
                        <th class="col-name">Tên hàng</th>
                        <th class="col-variant">Size</th>
                        <th class="col-variant">Màu</th>
                        <th class="col-qty">Số lượng *</th>
                        <th class="col-unit">ĐVT</th>
                        <th class="col-location">Vị trí</th>
                        <th class="col-note">Ghi chú</th>
                        <th class="col-remove"></th>
                    </tr>
                </thead>
                <tbody id="issueRows"></tbody>
            </table>
        </div>
        <div id="formStatus" class="status"><i data-lucide="info"></i><span>Chọn mã trong danh mục rồi nhập số lượng.</span></div>
    </section>
</main>

<dialog id="confirmDialog" class="confirm-dialog">
    <div class="confirm-body">
        <h2>Xác nhận xuất thành phẩm</h2>
        <p id="confirmText"></p>
    </div>
    <div class="confirm-actions">
        <button id="cancelConfirmBtn" class="btn-quick" type="button">Kiểm tra lại</button>
        <button id="acceptConfirmBtn" class="btn-quick btn-primary-quick" type="button"><i data-lucide="check"></i>Xác nhận xuất</button>
    </div>
</dialog>

<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
<script>
(() => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const rowsBody = document.getElementById('issueRows');
    const statusBox = document.getElementById('formStatus');
    const saveButton = document.getElementById('saveIssueBtn');
    let rowSequence = 0;
    const suggestionTimers = new WeakMap();

    const escapeHtml = value => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const numberFormat = value => new Intl.NumberFormat('vi-VN', {
        maximumFractionDigits: 3
    }).format(Number(value || 0));

    const localIsoDate = () => new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Ho_Chi_Minh',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    }).format(new Date());

    const isoToDisplayDate = iso => {
        const [year, month, day] = String(iso || '').split('-');
        return year && month && day ? `${day}/${month}/${year}` : '';
    };

    const displayToIsoDate = value => {
        const match = String(value || '').trim().match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
        if (!match) return '';
        const day = Number(match[1]);
        const month = Number(match[2]);
        const year = Number(match[3]);
        const date = new Date(Date.UTC(year, month - 1, day));
        if (date.getUTCFullYear() !== year || date.getUTCMonth() !== month - 1 || date.getUTCDate() !== day) return '';
        return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    };

    function setStatus(message, type = '') {
        statusBox.className = `status${type ? ` is-${type}` : ''}`;
        statusBox.innerHTML = `<i data-lucide="${type === 'error' ? 'circle-alert' : type === 'success' ? 'circle-check' : 'info'}"></i><span>${escapeHtml(message)}</span>`;
        window.lucide?.createIcons();
    }

    async function jsonOrError(response) {
        const payload = await response.json().catch(() => ({}));
        if (response.ok) return payload;
        const validation = payload.errors
            ? Object.values(payload.errors).flat().join(' ')
            : '';
        throw new Error(validation || payload.message || 'Không tạo được phiếu xuất thành phẩm.');
    }

    function rowTemplate(index) {
        return `
            <tr data-row-id="${index}">
                <td class="col-stt">${rowsBody.children.length + 1}</td>
                <td>
                    <div class="suggest-wrap">
                        <input class="form-control production-order" autocomplete="off" placeholder="Gõ lệnh / PS">
                        <div class="suggestions order-suggestions d-none"></div>
                    </div>
                </td>
                <td>
                    <div class="suggest-wrap">
                        <input class="form-control internal-code" autocomplete="off" placeholder="Tìm mã">
                        <div class="suggestions catalog-suggestions d-none"></div>
                    </div>
                    <div class="row-state"></div>
                </td>
                <td><input class="form-control item-name" autocomplete="off" placeholder="Tự điền"></td>
                <td><input class="form-control size" autocomplete="off"></td>
                <td><input class="form-control color" autocomplete="off"></td>
                <td><input class="form-control quantity" type="number" min="0" step="0.001" inputmode="decimal" value="0"></td>
                <td><input class="form-control unit" autocomplete="off" value="Cái"></td>
                <td><input class="form-control location" autocomplete="off" placeholder="Kệ"></td>
                <td><input class="form-control line-note" autocomplete="off"></td>
                <td><button class="remove-row" type="button" title="Xóa dòng" aria-label="Xóa dòng"><i data-lucide="x"></i></button></td>
            </tr>`;
    }

    function addRow(focus = false) {
        rowSequence += 1;
        rowsBody.insertAdjacentHTML('beforeend', rowTemplate(rowSequence));
        const row = rowsBody.lastElementChild;
        bindRow(row);
        renumberRows();
        if (focus) row.querySelector('.internal-code').focus();
        window.lucide?.createIcons();
    }

    function bindRow(row) {
        const orderInput = row.querySelector('.production-order');
        const codeInput = row.querySelector('.internal-code');
        orderInput.addEventListener('input', () => debounceSuggestions(orderInput, () => loadOrderSuggestions(row)));
        orderInput.addEventListener('focus', () => {
            if (orderInput.value.trim().length >= 2) loadOrderSuggestions(row);
        });
        orderInput.addEventListener('keydown', event => chooseFirstSuggestion(event, row.querySelector('.order-suggestions')));
        codeInput.addEventListener('input', () => {
            row.querySelector('.row-state').textContent = '';
            debounceSuggestions(codeInput, () => loadSuggestions(row));
            updateSummary();
        });
        codeInput.addEventListener('focus', () => {
            if (codeInput.value.trim().length >= 1) loadSuggestions(row);
        });
        codeInput.addEventListener('change', () => applyExactCatalog(row));
        codeInput.addEventListener('keydown', event => chooseFirstSuggestion(event, row.querySelector('.catalog-suggestions')));
        row.querySelector('.quantity').addEventListener('input', updateSummary);
        row.querySelector('.quantity').addEventListener('keydown', event => {
            if (event.key !== 'Enter') return;
            event.preventDefault();
            const next = row.nextElementSibling || (addRow(), rowsBody.lastElementChild);
            next.querySelector('.internal-code').focus();
        });
        row.querySelector('.remove-row').addEventListener('click', () => {
            if (rowsBody.children.length === 1) {
                clearRow(row);
                return;
            }
            row.remove();
            renumberRows();
            updateSummary();
        });
    }

    function debounceSuggestions(input, callback) {
        clearTimeout(suggestionTimers.get(input));
        suggestionTimers.set(input, setTimeout(callback, 140));
    }

    function chooseFirstSuggestion(event, panel) {
        if (event.key !== 'Enter' || panel.classList.contains('d-none')) return;
        const first = panel.querySelector('.suggestion');
        if (!first) return;
        event.preventDefault();
        first.click();
    }

    function clearRow(row) {
        row.querySelectorAll('input').forEach(input => {
            input.value = input.classList.contains('quantity') ? '0' : (input.classList.contains('unit') ? 'Cái' : '');
        });
        row.querySelector('.row-state').textContent = '';
        row.querySelectorAll('.suggestions').forEach(panel => panel.classList.add('d-none'));
        updateSummary();
    }

    function renumberRows() {
        [...rowsBody.children].forEach((row, index) => {
            row.querySelector('.col-stt').textContent = index + 1;
        });
    }

    async function loadSuggestions(row) {
        const keyword = row.querySelector('.internal-code').value.trim();
        const panel = row.querySelector('.suggestions');
        if (!keyword) {
            panel.classList.add('d-none');
            return;
        }
        try {
            const result = await fetch(`/api/ma-noi-bo-danh-muc?keyword=${encodeURIComponent(keyword)}&limit=20&with_color=0`)
                .then(jsonOrError);
            const items = result.data || [];
            row._catalogSuggestions = items;
            panel.innerHTML = items.map((item, index) => `
                <button class="suggestion" type="button" data-index="${index}">
                    <strong>${escapeHtml(item.code || item.value)}</strong>
                    <small>${escapeHtml(item.name || '')}</small>
                    <span>${escapeHtml([item.size, item.color, item.shelf].filter(Boolean).join(' · '))}</span>
                </button>
            `).join('');
            panel.classList.toggle('d-none', items.length === 0);
            panel.querySelectorAll('.suggestion').forEach(button => {
                button.addEventListener('click', () => applySuggestion(row, items[Number(button.dataset.index)]));
            });
        } catch (error) {
            panel.classList.add('d-none');
            setStatus(error.message, 'error');
        }
    }

    async function applyExactCatalog(row) {
        const input = row.querySelector('.internal-code');
        const code = input.value.trim().toUpperCase();
        if (!code) return;
        let items = row._catalogSuggestions || [];
        let item = items.find(candidate => String(candidate.code || candidate.value || '').trim().toUpperCase() === code);
        if (!item) {
            try {
                const result = await fetch(`/api/ma-noi-bo-danh-muc?keyword=${encodeURIComponent(code)}&limit=10&with_color=0`)
                    .then(jsonOrError);
                items = result.data || [];
                row._catalogSuggestions = items;
                item = items.find(candidate => String(candidate.code || candidate.value || '').trim().toUpperCase() === code);
            } catch (error) {
                setStatus(error.message, 'error');
                return;
            }
        }
        if (item) {
            applySuggestion(row, item);
            return;
        }
        row.querySelector('.row-state').textContent = 'Mã chưa có trong danh mục';
        setStatus(`Không tìm thấy mã ${code} trong DANH MỤC.`, 'error');
    }

    async function loadOrderSuggestions(row) {
        const input = row.querySelector('.production-order');
        const keyword = input.value.trim();
        const panel = row.querySelector('.order-suggestions');
        if (keyword.length < 2) {
            panel.classList.add('d-none');
            return;
        }
        const params = new URLSearchParams({
            keyword,
            with_progress: '1',
            limit: '30',
        });
        const issueDate = displayToIsoDate(document.getElementById('issueDate').value);
        if (issueDate) params.set('order_date_to', issueDate);
        try {
            const result = await fetch(`/api/lenh-san-xuat-sheet?${params.toString()}`).then(jsonOrError);
            const seen = new Set();
            const items = (result.data || []).filter(item => {
                const key = [
                    item.production_order,
                    item.item_code,
                    item.size,
                    item.color,
                ].map(value => String(value || '').trim().toUpperCase()).join('|');
                if (!item.production_order || seen.has(key)) return false;
                seen.add(key);
                return true;
            }).slice(0, 20);
            row._orderSuggestions = items;
            panel.innerHTML = items.map((item, index) => {
                const remaining = Number(item.remaining_quantity || 0);
                const detail = [
                    item.item_code,
                    item.size ? `Size ${item.size}` : '',
                    item.color ? `Màu ${item.color}` : '',
                    item.customer,
                ].filter(Boolean).join(' · ');
                return `
                    <button class="suggestion" type="button" data-index="${index}">
                        <strong>${escapeHtml(item.production_order)}</strong>
                        <small>${escapeHtml(detail)}</small>
                        <span>${remaining > 0 ? `Còn ${numberFormat(remaining)}` : 'Đã đủ'}</span>
                    </button>`;
            }).join('');
            panel.classList.toggle('d-none', items.length === 0);
            panel.querySelectorAll('.suggestion').forEach(button => {
                button.addEventListener('click', () => applyOrderSuggestion(row, items[Number(button.dataset.index)]));
            });
        } catch (error) {
            panel.classList.add('d-none');
            setStatus(error.message, 'error');
        }
    }

    function applyOrderSuggestion(row, item) {
        row.querySelector('.production-order').value = item.production_order || '';
        row.querySelector('.internal-code').value = item.item_code || '';
        row.querySelector('.item-name').value = item.description || item.specification || '';
        row.querySelector('.size').value = item.size || '';
        row.querySelector('.color').value = item.color || '';
        row.querySelector('.unit').value = item.unit || row.querySelector('.unit').value || 'Cái';
        if (!document.getElementById('customerName').value.trim() && item.customer) {
            document.getElementById('customerName').value = item.customer;
        }
        row.querySelector('.order-suggestions').classList.add('d-none');
        row.querySelector('.row-state').textContent = 'Từ lệnh sản xuất';
        applyExactCatalog(row).finally(() => row.querySelector('.quantity').focus());
        updateSummary();
    }

    function applySuggestion(row, item) {
        row.querySelector('.internal-code').value = item.code || item.value || '';
        row.querySelector('.item-name').value = item.name || '';
        row.querySelector('.size').value = item.size || '';
        row.querySelector('.color').value = item.color || '';
        row.querySelector('.unit').value = item.unit || 'Cái';
        row.querySelector('.location').value = item.shelf || '';
        row.querySelector('.row-state').textContent = 'Đúng danh mục';
        row.querySelectorAll('.suggestions').forEach(panel => panel.classList.add('d-none'));
        row.querySelector('.quantity').focus();
        updateSummary();
    }

    function collectLines() {
        return [...rowsBody.children].map(row => {
            const code = row.querySelector('.internal-code').value.trim();
            const order = row.querySelector('.production-order').value.trim();
            return {
                ma_hh: code,
                internal_item_code: code,
                ten_hh: row.querySelector('.item-name').value.trim(),
                production_order: order,
                ps_number: order,
                size: row.querySelector('.size').value.trim(),
                color: row.querySelector('.color').value.trim(),
                quantity: Number(row.querySelector('.quantity').value || 0),
                dvt: row.querySelector('.unit').value.trim(),
                location_code: row.querySelector('.location').value.trim(),
                note: row.querySelector('.line-note').value.trim(),
                customer: document.getElementById('customerName').value.trim(),
            };
        }).filter(line => line.internal_item_code || line.quantity > 0);
    }

    function updateSummary() {
        const lines = collectLines().filter(line => line.internal_item_code && line.quantity > 0);
        document.getElementById('lineCount').textContent = lines.length;
        document.getElementById('totalQuantity').textContent = numberFormat(lines.reduce((sum, line) => sum + line.quantity, 0));
    }

    function confirmIssue(message) {
        return new Promise(resolve => {
            const dialog = document.getElementById('confirmDialog');
            document.getElementById('confirmText').textContent = message;
            const accept = document.getElementById('acceptConfirmBtn');
            const cancel = document.getElementById('cancelConfirmBtn');
            const finish = value => {
                accept.onclick = null;
                cancel.onclick = null;
                dialog.close();
                resolve(value);
            };
            accept.onclick = () => finish(true);
            cancel.onclick = () => finish(false);
            dialog.showModal();
        });
    }

    async function saveIssue() {
        const customer = document.getElementById('customerName').value.trim();
        const issueDate = displayToIsoDate(document.getElementById('issueDate').value);
        const lines = collectLines();
        if (!issueDate) {
            setStatus('Ngày xuất phải đúng định dạng dd/mm/yyyy.', 'error');
            document.getElementById('issueDate').focus();
            return;
        }
        if (!customer) {
            setStatus('Nhập khách hàng.', 'error');
            document.getElementById('customerName').focus();
            return;
        }
        if (!lines.length || lines.some(line => !line.internal_item_code || line.quantity <= 0)) {
            setStatus('Mỗi dòng sử dụng cần mã nội bộ và số lượng lớn hơn 0.', 'error');
            return;
        }

        const total = lines.reduce((sum, line) => sum + line.quantity, 0);
        const confirmed = await confirmIssue(
            `Khách hàng: ${customer}\n${lines.length} dòng · Tổng SL ${numberFormat(total)}\nHệ thống sẽ kiểm tồn, trừ FIFO và tạo phiếu xuất.`
        );
        if (!confirmed) return;

        const printWindow = window.open('', '_blank');
        saveButton.disabled = true;
        saveButton.innerHTML = '<i class="spin" data-lucide="loader-circle"></i>Đang lưu';
        setStatus('Đang kiểm tồn và tạo phiếu xuất...');
        window.lucide?.createIcons();

        try {
            const result = await fetch('/api/xuat-vat-tu-noi-bo', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    issue_type: 'customer',
                    issue_date: issueDate,
                    warehouse_code: '',
                    receiver_name: document.getElementById('receiverName').value.trim(),
                    department: 'Kinh doanh',
                    production_order: [...new Set(lines.map(line => line.production_order).filter(Boolean))].join(', '),
                    purpose: 'Xuất thành phẩm cho khách hàng',
                    note: document.getElementById('issueNote').value.trim(),
                    lines,
                })
            }).then(jsonOrError);

            if (printWindow && result.print_url) printWindow.location.href = result.print_url;
            else printWindow?.close();
            setStatus(`Đã tạo ${result.data?.issue_code || 'phiếu xuất thành phẩm'}.`, 'success');
            resetForm(false);
        } catch (error) {
            printWindow?.close();
            setStatus(error.message, 'error');
        } finally {
            saveButton.disabled = false;
            saveButton.innerHTML = '<i data-lucide="printer"></i>Lưu + in';
            window.lucide?.createIcons();
        }
    }

    function resetForm(clearHeader = true) {
        if (clearHeader) {
            document.getElementById('customerName').value = '';
            document.getElementById('receiverName').value = '';
            document.getElementById('issueNote').value = '';
        }
        rowsBody.innerHTML = '';
        for (let index = 0; index < 8; index += 1) addRow();
        updateSummary();
        rowsBody.querySelector('.internal-code')?.focus();
    }

    document.addEventListener('click', event => {
        if (event.target.closest('.suggest-wrap')) return;
        document.querySelectorAll('.suggestions').forEach(panel => panel.classList.add('d-none'));
    });
    document.getElementById('addRowBtn').addEventListener('click', () => addRow(true));
    document.getElementById('resetBtn').addEventListener('click', () => resetForm(true));
    document.getElementById('saveIssueBtn').addEventListener('click', saveIssue);
    document.getElementById('issueDate').value = isoToDisplayDate(localIsoDate());
    resetForm(false);
    window.lucide?.createIcons();
})();
</script>
</body>
</html>
