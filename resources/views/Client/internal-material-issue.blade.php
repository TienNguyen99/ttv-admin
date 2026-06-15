<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Xuất vật tư nội bộ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/warehouse-wms.css') }}" rel="stylesheet">
    <style>
        body { background: #f6f7f9; color: #111827; }
        .panel { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; }
        .page-title { font-size: 24px; font-weight: 700; }
        .section-title { font-size: 16px; font-weight: 700; margin: 0; }
        .hint { color: #6b7280; font-size: 13px; }
        .metric { font-size: 20px; font-weight: 700; }
        .line-table input { min-width: 90px; }
        .product-search { position: relative; }
        .product-results {
            position: absolute;
            left: 0;
            right: 0;
            top: 100%;
            z-index: 20;
            max-height: 260px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.16);
        }
        .product-option {
            display: block;
            width: 100%;
            padding: 10px 12px;
            border: 0;
            border-bottom: 1px solid #eef2f7;
            background: transparent;
            text-align: left;
        }
        .product-option:hover { background: #eff6ff; }
        .product-code { font-weight: 700; }
        .product-name { color: #6b7280; font-size: 13px; }
        .table td, .table th { vertical-align: middle; }
        .order-load-status { min-height: 20px; margin-top: 5px; font-size: 12px; }
        .reference-value { min-width: 92px; background: #f8fafc !important; color: #475569; }
        .stock-warning { color: #b91c1c; font-size: 11px; font-weight: 700; white-space: nowrap; }
        @media (max-width: 767.98px) {
            .line-table { min-width: 980px; }
        }
        .panel { border-color: var(--wms-line); border-radius: 7px; box-shadow: none; }
        .page-title { color: var(--wms-ink); font-size: 28px; font-weight: 800; }
        .table thead th { background: var(--wms-navy); color: #fff; font-size: 12px; white-space: nowrap; }
        .table tbody tr:hover td { background: #edf5ff; }
        .metric { color: var(--wms-ink); font-size: 27px; font-weight: 800; }
        .form-control, .form-select { min-height: 40px; border-color: #bdc8d8; border-radius: 5px; }
        .btn { border-radius: 5px; }
    </style>
</head>

<body>
    @include('layouts.partials.sidebar')

    <header class="wms-topbar">
        <h1 class="wms-topbar__title">WMS May Mặc</h1>
        <div class="wms-global-search">
            <i data-lucide="search"></i>
            <input id="topIssueKeyword" aria-label="Tìm phiếu xuất" placeholder="Tìm phiếu xuất, mã vật tư hoặc người nhận...">
        </div>
        <div class="wms-topbar__actions">
            <a class="wms-btn" href="{{ url('/client/ton-kho-noi-bo') }}"><i data-lucide="boxes"></i> Xem tồn</a>
        </div>
    </header>

    <main class="wms-page">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
            <div>
                <h1 id="pageTitle" class="page-title mb-1">Phiếu xuất kho nội bộ</h1>
                <div id="pageHint" class="hint">TSoft kế toán chỉ đọc danh mục, không ghi dữ liệu.</div>
            </div>
            <div class="d-flex gap-2">
                <button id="reloadBtn" type="button" class="wms-btn"><i data-lucide="refresh-cw"></i>Tải lại</button>
                <button id="saveBtn" type="button" class="wms-btn wms-btn--primary"><i data-lucide="printer"></i>Xuất và in phiếu</button>
            </div>
        </div>

        <section class="wms-kpis">
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="clipboard-list"></i></div><div><div class="wms-kpi__label">Phiếu trong danh sách</div><div id="issueCount" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Theo bộ lọc</div></div></article>
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="list-ordered"></i></div><div><div class="wms-kpi__label">Dòng vật tư</div><div id="lineCount" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Chi tiết các phiếu</div></div></article>
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="package-minus"></i></div><div><div class="wms-kpi__label">Tổng số lượng</div><div id="totalQuantity" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Đã xuất nội bộ</div></div></article>
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="factory"></i></div><div><div class="wms-kpi__label">Luồng xuất</div><div id="issueTypeMetric" class="wms-kpi__value" style="font-size:18px">BTP sản xuất</div><div class="wms-kpi__meta">Chọn tại thông tin phiếu</div></div></article>
        </section>

        <section class="panel mb-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h2 class="section-title">Thông tin phiếu</h2>
                    <div class="hint">Tạo phiếu sẽ trừ tồn nội bộ theo mã, vị trí, mã nội bộ, size và màu nếu có nhập.</div>
                </div>
                <button id="addLineBtn" type="button" class="btn btn-outline-primary btn-sm">Thêm dòng</button>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-md-2"><label class="form-label">Nghiệp vụ</label><select id="issueType" class="form-select"><option value="production">Xuất BTP đi sản xuất</option><option value="material">Xuất vật tư</option></select></div>
                <div class="col-md-2"><label class="form-label">Ngày xuất</label><input id="issueDate" type="date" class="form-control" value="{{ now()->format('Y-m-d') }}"></div>
                <div class="col-md-2"><label class="form-label">Kho xuất</label><input id="warehouseCode" class="form-control" placeholder="KTPHAM"></div>
                <div class="col-md-2"><label class="form-label">Người nhận</label><input id="receiverName" class="form-control"></div>
                <div class="col-md-2"><label class="form-label">Bộ phận</label><input id="department" class="form-control"></div>
                <div class="col-md-2"><label class="form-label">Mục đích</label><input id="purpose" class="form-control" placeholder="Sản xuất / bù hao..."></div>
                <div class="col-12"><label class="form-label">Ghi chú phiếu</label><input id="issueNote" class="form-control"></div>
            </div>

            <datalist id="productionOrderOptions"></datalist>
            <div id="productionOrderStatus" class="order-load-status hint">Gõ Lệnh SX trực tiếp tại dòng hàng, nhấn Enter để lấy thông tin.</div>
            <div class="table-responsive">
                <table class="table table-bordered line-table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:130px">Lệnh SX</th>
                            <th style="width:220px">Mã vật tư</th>
                            <th>Tên vật tư</th>
                            <th style="width:80px">ĐVT</th>
                            <th style="width:105px">SL theo lệnh</th>
                            <th style="width:105px">Tồn khả dụng</th>
                            <th style="width:120px">SL thực xuất *</th>
                            <th style="width:120px">Vị trí</th>
                            <th style="width:140px">Mã nội bộ</th>
                            <th style="width:90px">Size</th>
                            <th style="width:120px">Màu</th>
                            <th style="width:160px">Ghi chú</th>
                            <th style="width:50px"></th>
                        </tr>
                    </thead>
                    <tbody id="lineRows"></tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <div class="row g-2 align-items-end mb-3">
                <div class="col-md-2"><label class="form-label">Từ ngày</label><input id="fromDate" type="date" class="form-control"></div>
                <div class="col-md-2"><label class="form-label">Đến ngày</label><input id="toDate" type="date" class="form-control"></div>
                <div class="col-md-5"><label class="form-label">Tìm phiếu / mã vật tư / người nhận</label><input id="keyword" class="form-control"></div>
                <div class="col-md-3"><button id="clearFilterBtn" type="button" class="btn btn-outline-secondary w-100">Xóa lọc</button></div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr><th>Số phiếu</th><th>Ngày</th><th>Kho</th><th>Người nhận</th><th>Bộ phận</th><th>Lệnh</th><th>Mục đích</th><th class="text-end">Dòng</th><th class="text-end">Tổng SL</th><th></th></tr>
                    </thead>
                    <tbody id="issueRows"></tbody>
                </table>
            </div>
        </section>
    </main>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const lineRows = document.getElementById('lineRows');
        const issueRows = document.getElementById('issueRows');
        let searchTimers = {};
        let productionOrderSearchTimer = null;

        const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
        const value = id => document.getElementById(id).value.trim();
        const num = value => Number(value || 0).toLocaleString('vi-VN', { maximumFractionDigits: 3 });

        function jsonOrError(response, fallback) {
            if (response.ok) return response.json();
            return response.json().then(result => { throw new Error(result.message || fallback); });
        }

        function addLine(data = {}) {
            const rowId = `line-${Date.now()}-${Math.random().toString(16).slice(2)}`;
            const tr = document.createElement('tr');
            tr.dataset.rowId = rowId;
            tr.dataset.productionOrderId = data.production_order_id || '';
            tr.dataset.purchaseOrder = data.purchase_order || '';
            tr.dataset.customer = data.customer || '';
            tr.innerHTML = `
                <td><input class="form-control line-production-order" list="productionOrderOptions" autocomplete="off" value="${esc(data.production_order || '')}" placeholder="Gõ lệnh SX"></td>
                <td class="product-search">
                    <input class="form-control ma-hh" autocomplete="off" value="${esc(data.ma_hh || '')}" placeholder="Gõ mã/tên">
                    <div class="product-results d-none"></div>
                </td>
                <td><input class="form-control ten-hh" value="${esc(data.ten_hh || '')}"></td>
                <td><input class="form-control dvt" value="${esc(data.dvt || '')}"></td>
                <td><input class="form-control ordered-quantity text-end reference-value" value="${esc(data.ordered_quantity || '')}" readonly></td>
                <td>
                    <input class="form-control available-quantity text-end reference-value" value="${esc(data.available_quantity || '')}" readonly>
                    ${data.production_order && !Number(data.available_quantity || 0) ? '<div class="stock-warning">Chưa khớp tồn</div>' : ''}
                </td>
                <td><input class="form-control quantity text-end" type="number" step="0.001" min="0" value="${esc(data.quantity || '')}"></td>
                <td><input class="form-control location-code" value="${esc(data.location_code || '')}" placeholder="A01"></td>
                <td><input class="form-control internal-code" value="${esc(data.internal_item_code || '')}"></td>
                <td><input class="form-control size" value="${esc(data.size || '')}"></td>
                <td><input class="form-control color" value="${esc(data.color || '')}"></td>
                <td><input class="form-control line-note" value="${esc(data.note || '')}"></td>
                <td><button type="button" class="btn btn-sm btn-outline-danger remove-line">X</button></td>
            `;
            lineRows.appendChild(tr);
        }

        function collectLines() {
            return Array.from(lineRows.querySelectorAll('tr')).map(row => ({
                ma_hh: row.querySelector('.ma-hh').value.trim(),
                ten_hh: row.querySelector('.ten-hh').value.trim(),
                dvt: row.querySelector('.dvt').value.trim(),
                production_order_id: row.dataset.productionOrderId || null,
                production_order: row.querySelector('.line-production-order').value.trim(),
                purchase_order: row.dataset.purchaseOrder || '',
                customer: row.dataset.customer || '',
                ordered_quantity: row.querySelector('.ordered-quantity').value || null,
                quantity: row.querySelector('.quantity').value,
                location_code: row.querySelector('.location-code').value.trim(),
                internal_item_code: row.querySelector('.internal-code').value.trim(),
                size: row.querySelector('.size').value.trim(),
                color: row.querySelector('.color').value.trim(),
                note: row.querySelector('.line-note').value.trim(),
            })).filter(line => line.ma_hh || line.quantity);
        }

        function searchProductionOrders(input) {
            const keyword = input.value.trim();
            clearTimeout(productionOrderSearchTimer);
            if (keyword.length < 2) return;

            productionOrderSearchTimer = setTimeout(() => {
                fetch(`/api/lenh-san-xuat-sheet?keyword=${encodeURIComponent(keyword)}&limit=30`)
                    .then(response => jsonOrError(response, 'Không tải được lệnh sản xuất'))
                    .then(result => {
                        const uniqueOrders = Array.from(new Map(
                            (result.data || []).map(order => [order.production_order, order])
                        ).values());
                        document.getElementById('productionOrderOptions').innerHTML = uniqueOrders.map(order => {
                            const label = [order.customer, order.purchase_order, order.item_code].filter(Boolean).join(' · ');
                            return `<option value="${esc(order.production_order)}" label="${esc(label)}"></option>`;
                        }).join('');
                    })
                    .catch(() => {});
            }, 220);
        }

        function fillIssueLine(row, data) {
            row.dataset.productionOrderId = data.production_order_id || '';
            row.dataset.purchaseOrder = data.purchase_order || '';
            row.dataset.customer = data.customer || '';
            row.querySelector('.line-production-order').value = data.production_order || '';
            row.querySelector('.ma-hh').value = data.ma_hh || '';
            row.querySelector('.ten-hh').value = data.ten_hh || '';
            row.querySelector('.dvt').value = data.dvt || '';
            row.querySelector('.ordered-quantity').value = data.ordered_quantity || '';
            row.querySelector('.available-quantity').value = data.available_quantity || '';
            row.querySelector('.quantity').value = '';
            row.querySelector('.location-code').value = data.location_code || '';
            row.querySelector('.internal-code').value = data.internal_item_code || '';
            row.querySelector('.size').value = data.size || '';
            row.querySelector('.color').value = data.color || '';

            const availableCell = row.querySelector('.available-quantity').parentElement;
            availableCell.querySelector('.stock-warning')?.remove();
            if (!Number(data.available_quantity || 0)) {
                availableCell.insertAdjacentHTML('beforeend', '<div class="stock-warning">Chưa khớp tồn</div>');
            }
        }

        function loadProductionOrder(input) {
            const code = input.value.trim();
            if (!code) return alert('Nhập Lệnh sản xuất cần nạp.');

            const status = document.getElementById('productionOrderStatus');
            const currentRow = input.closest('tr');
            status.textContent = `Đang tải ${code}...`;
            input.disabled = true;

            const params = new URLSearchParams({ production_order: code });
            if (value('warehouseCode')) params.set('warehouse_code', value('warehouseCode'));

            fetch(`/api/xuat-vat-tu-noi-bo/lenh-san-xuat?${params.toString()}`)
                .then(response => jsonOrError(response, 'Không tải được chi tiết lệnh sản xuất'))
                .then(result => {
                    const rows = result.data || [];
                    if (!rows.length) throw new Error(`Không tìm thấy lệnh ${code}.`);

                    const existingKeys = new Set(Array.from(lineRows.querySelectorAll('tr'))
                        .filter(row => row !== currentRow)
                        .map(row => [
                        row.querySelector('.line-production-order').value.trim().toUpperCase(),
                        row.querySelector('.internal-code').value.trim().toUpperCase(),
                        row.querySelector('.size').value.trim().toUpperCase(),
                        row.querySelector('.color').value.trim().toUpperCase()
                    ].join('|')));
                    const newRows = [];

                    rows.forEach(data => {
                        const key = [
                            String(data.production_order || '').trim().toUpperCase(),
                            String(data.internal_item_code || '').trim().toUpperCase(),
                            String(data.size || '').trim().toUpperCase(),
                            String(data.color || '').trim().toUpperCase()
                        ].join('|');
                        if (existingKeys.has(key)) return;
                        newRows.push(data);
                        existingKeys.add(key);
                    });

                    if (!newRows.length) {
                        throw new Error(`Các dòng của lệnh ${code} đã có trong phiếu.`);
                    }

                    fillIssueLine(currentRow, newRows.shift());
                    newRows.forEach(data => addLine(data));
                    status.textContent = `${code}: đã nạp ${newRows.length + 1} dòng size/màu từ dòng đang chọn. SL thực xuất để trống.`;
                })
                .catch(error => {
                    status.textContent = error.message;
                    alert(error.message);
                })
                .finally(() => input.disabled = false);
        }

        function applyIssueType(type) {
            const isProduction = type === 'production';
            document.getElementById('issueTypeMetric').textContent = isProduction ? 'BTP sản xuất' : 'Vật tư';
            document.getElementById('pageTitle').textContent = isProduction ? 'Xuất bán thành phẩm đi sản xuất' : 'Xuất vật tư nội bộ';
            document.getElementById('pageHint').textContent = isProduction
                ? 'Xuất BTP khỏi kho nội bộ để giao sản xuất. Khi hoàn thành, nhập lại bằng Phiếu nhập thành phẩm.'
                : 'Xuất vật tư khỏi tồn nội bộ theo mã, vị trí, size và màu.';
            document.getElementById('saveBtn').textContent = isProduction ? 'Xuất BTP + in phiếu' : 'Xuất vật tư + in phiếu';

            if (isProduction) {
                if (!value('department')) document.getElementById('department').value = 'Sản xuất';
                if (!value('purpose') || value('purpose') === 'Xuất vật tư') document.getElementById('purpose').value = 'Xuất BTP đi sản xuất';
            } else if (value('purpose') === 'Xuất BTP đi sản xuất') {
                document.getElementById('purpose').value = 'Xuất vật tư';
            }
        }

        function suggestMaterial(input) {
            const keyword = input.value.trim();
            const cell = input.closest('.product-search');
            const results = cell.querySelector('.product-results');
            clearTimeout(searchTimers[cell.parentElement.dataset.rowId]);

            if (keyword.length < 2) {
                results.classList.add('d-none');
                results.innerHTML = '';
                return;
            }

            searchTimers[cell.parentElement.dataset.rowId] = setTimeout(() => {
                fetch(`/api/vat-tu-ke-toan/goi-y?keyword=${encodeURIComponent(keyword)}`)
                    .then(response => jsonOrError(response, 'Không tải được danh mục vật tư'))
                    .then(result => {
                        results.innerHTML = (result.data || []).map(item => `
                            <button type="button" class="product-option" data-code="${esc(item.Ma_hh)}" data-name="${esc(item.Ten_hh || '')}" data-dvt="${esc(item.Dvt || '')}">
                                <div class="product-code">${esc(item.Ma_hh)}</div>
                                <div class="product-name">${esc(item.Ten_hh || '')} ${item.Dvt ? '· ' + esc(item.Dvt) : ''}</div>
                            </button>
                        `).join('') || '<div class="p-3 hint">Không có mã phù hợp</div>';
                        results.classList.remove('d-none');
                    })
                    .catch(error => {
                        results.innerHTML = `<div class="p-3 text-danger small">${esc(error.message)}</div>`;
                        results.classList.remove('d-none');
                    });
            }, 250);
        }

        function saveIssue() {
            const lines = collectLines();
            if (!lines.length) return alert('Nhập ít nhất một dòng vật tư.');
            if (lines.some(line => !line.ma_hh || !Number(line.quantity))) return alert('Mỗi dòng cần mã vật tư và số lượng.');

            fetch('/api/xuat-vat-tu-noi-bo', {
                method: 'POST',
                headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify({
                    issue_type: value('issueType'),
                    issue_date: value('issueDate'),
                    warehouse_code: value('warehouseCode'),
                    receiver_name: value('receiverName'),
                    department: value('department'),
                    production_order: Array.from(new Set(lines.map(line => line.production_order).filter(Boolean))).join(', '),
                    purpose: value('purpose'),
                    note: value('issueNote'),
                    lines
                })
            }).then(response => jsonOrError(response, 'Không tạo được phiếu xuất vật tư'))
              .then(result => {
                  window.open(result.print_url, '_blank');
                  lineRows.innerHTML = '';
                  addLine();
                  loadIssues();
              })
              .catch(error => alert(error.message));
        }

        function loadIssues() {
            const params = new URLSearchParams();
            if (value('fromDate')) params.set('from_date', value('fromDate'));
            if (value('toDate')) params.set('to_date', value('toDate'));
            if (value('keyword')) params.set('keyword', value('keyword'));

            fetch(`/api/xuat-vat-tu-noi-bo?${params.toString()}`)
                .then(response => jsonOrError(response, 'Không tải được danh sách phiếu'))
                .then(result => {
                    document.getElementById('issueCount').textContent = num(result.summary?.total_issues || 0);
                    document.getElementById('lineCount').textContent = num(result.summary?.total_lines || 0);
                    document.getElementById('totalQuantity').textContent = num(result.summary?.total_quantity || 0);
                    issueRows.innerHTML = (result.data || []).map(issue => `
                        <tr>
                            <td>${esc(issue.issue_code)}</td>
                            <td>${esc(issue.issue_date)}</td>
                            <td>${esc(issue.warehouse_code)}</td>
                            <td>${esc(issue.receiver_name)}</td>
                            <td>${esc(issue.department)}</td>
                            <td>${esc(issue.production_order)}</td>
                            <td>${esc(issue.purpose)}</td>
                            <td class="text-end">${num(issue.lines_count)}</td>
                            <td class="text-end">${num(issue.lines_sum_quantity)}</td>
                            <td class="text-nowrap text-end">
                                <a class="btn btn-sm btn-outline-primary" target="_blank" href="/client/xuat-vat-tu-noi-bo/${issue.id}/in">In</a>
                                <button class="btn btn-sm btn-outline-danger delete-issue" data-id="${issue.id}">Xóa</button>
                            </td>
                        </tr>
                    `).join('') || '<tr><td colspan="10" class="text-center hint">Chưa có phiếu</td></tr>';
                })
                .catch(error => alert(error.message));
        }

        lineRows.addEventListener('input', event => {
            if (event.target.classList.contains('ma-hh')) suggestMaterial(event.target);
            if (event.target.classList.contains('line-production-order')) searchProductionOrders(event.target);
        });

        lineRows.addEventListener('change', event => {
            if (event.target.classList.contains('line-production-order')) loadProductionOrder(event.target);
        });

        lineRows.addEventListener('keydown', event => {
            if (event.target.classList.contains('line-production-order') && event.key === 'Enter') {
                event.preventDefault();
                loadProductionOrder(event.target);
            }
        });

        lineRows.addEventListener('click', event => {
            const option = event.target.closest('.product-option');
            if (option) {
                const row = option.closest('tr');
                row.querySelector('.ma-hh').value = option.dataset.code || '';
                row.querySelector('.ten-hh').value = option.dataset.name || '';
                row.querySelector('.dvt').value = option.dataset.dvt || '';
                option.closest('.product-results').classList.add('d-none');
                return;
            }

            const remove = event.target.closest('.remove-line');
            if (remove) {
                if (lineRows.querySelectorAll('tr').length === 1) return;
                remove.closest('tr').remove();
            }
        });

        issueRows.addEventListener('click', event => {
            const button = event.target.closest('.delete-issue');
            if (!button || !confirm('Xóa phiếu xuất vật tư nội bộ này?')) return;

            fetch(`/api/xuat-vat-tu-noi-bo/${button.dataset.id}`, {
                method: 'DELETE',
                headers: {'Accept':'application/json','X-CSRF-TOKEN':csrfToken}
            }).then(response => jsonOrError(response, 'Không xóa được phiếu'))
              .then(loadIssues)
              .catch(error => alert(error.message));
        });

        document.getElementById('addLineBtn').addEventListener('click', () => addLine());
        document.getElementById('saveBtn').addEventListener('click', saveIssue);
        document.getElementById('issueType').addEventListener('change', event => applyIssueType(event.target.value));
        document.getElementById('reloadBtn').addEventListener('click', loadIssues);
        document.getElementById('clearFilterBtn').addEventListener('click', () => {
            ['fromDate','toDate','keyword'].forEach(id => document.getElementById(id).value = '');
            document.getElementById('topIssueKeyword').value = '';
            loadIssues();
        });
        ['fromDate','toDate','keyword'].forEach(id => document.getElementById(id).addEventListener('input', loadIssues));
        let topIssueSearchTimer = null;
        document.getElementById('topIssueKeyword').addEventListener('input', event => {
            document.getElementById('keyword').value = event.target.value;
            clearTimeout(topIssueSearchTimer);
            topIssueSearchTimer = setTimeout(loadIssues, 250);
        });
        document.getElementById('keyword').addEventListener('input', event => {
            document.getElementById('topIssueKeyword').value = event.target.value;
        });

        const requestedType = new URLSearchParams(window.location.search).get('type');
        document.getElementById('issueType').value = requestedType === 'material' ? 'material' : 'production';
        applyIssueType(document.getElementById('issueType').value);
        addLine();
        loadIssues();
    </script>
</body>
</html>
