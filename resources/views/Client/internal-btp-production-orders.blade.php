<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lệnh BTP nội bộ</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/warehouse-wms.css') }}?v={{ filemtime(public_path('css/warehouse-wms.css')) }}" rel="stylesheet">
    <style>
        .btp-line-meta { display:flex; flex-wrap:wrap; gap:6px; margin-top:4px; }
        .btp-chip { border:1px solid #d7e2f2; border-radius:6px; padding:2px 6px; font-size:12px; color:#334155; background:#f8fbff; }
        .btp-dialog { width:min(980px, calc(100vw - 32px)); border:0; border-radius:12px; padding:0; box-shadow:0 24px 80px rgba(15,23,42,.24); }
        .btp-dialog::backdrop { background:rgba(15,23,42,.35); }
        .btp-dialog__head { display:flex; align-items:center; justify-content:space-between; padding:16px 18px; border-bottom:1px solid #e2e8f0; }
        .btp-dialog__body { padding:18px; }
        .btp-dialog__foot { display:flex; justify-content:flex-end; gap:8px; padding:14px 18px; border-top:1px solid #e2e8f0; background:#f8fafc; }
        .btp-grid { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:12px; }
        .btp-grid--line { grid-template-columns:repeat(5, minmax(0, 1fr)); }
        .btp-grid .span-2 { grid-column:span 2; }
        .btp-grid .span-4, .btp-grid--line .span-5 { grid-column:1 / -1; }
        @media (max-width: 900px) {
            .btp-grid, .btp-grid--line { grid-template-columns:1fr 1fr; }
            .btp-grid .span-2, .btp-grid .span-4, .btp-grid--line .span-5 { grid-column:1 / -1; }
        }
    </style>
</head>
<body>
@include('layouts.partials.sidebar')

<header class="wms-topbar">
    <h1 class="wms-topbar__title">WMS May Mặc</h1>
    <form class="wms-global-search" onsubmit="return false">
        <i data-lucide="search"></i>
        <input id="topKeyword" aria-label="Tìm lệnh BTP" placeholder="Tìm lệnh BTP, phiếu xuất, mã hàng, size hoặc màu...">
    </form>
    <div class="wms-topbar__actions">
        <a class="wms-btn wms-btn--primary" href="{{ url('/client/xuat-vat-tu-noi-bo?type=production') }}"><i data-lucide="package-minus"></i>Tạo lệnh + xuất BTP</a>
    </div>
</header>

<main class="wms-page">
    <div class="wms-heading">
        <div>
            <h1>Lệnh BTP nội bộ</h1>
            <p>Mỗi dòng bán thành phẩm là một lệnh BTP riêng. Dữ liệu chỉ nằm trong database nội bộ, không ghi vào TSoft.</p>
        </div>
        <button id="reloadBtn" class="wms-btn" type="button"><i data-lucide="refresh-cw"></i>Tải lại</button>
    </div>

    <section class="wms-kpis">
        <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="clipboard-list"></i></div><div><div class="wms-kpi__label">Tổng lệnh</div><div id="orderCount" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Theo bộ lọc hiện tại</div></div></article>
        <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="list-ordered"></i></div><div><div class="wms-kpi__label">Dòng BTP</div><div id="lineCount" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Mỗi dòng là một lệnh</div></div></article>
        <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="boxes"></i></div><div><div class="wms-kpi__label">Tổng số lượng</div><div id="totalQuantity" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Số lượng trong lệnh</div></div></article>
        <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="send"></i></div><div><div class="wms-kpi__label">Đã xuất SX</div><div id="issuedCount" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Đã sinh phiếu PXBTP</div></div></article>
    </section>

    <section class="wms-panel">
        <div class="wms-panel__header">
            <h2>Danh sách lệnh</h2>
            <div class="wms-actions">
                <select id="statusFilter" class="form-select form-select-sm" style="width:160px">
                    <option value="">Tất cả trạng thái</option>
                    <option value="draft">Mới tạo</option>
                    <option value="issued">Đã xuất SX</option>
                    <option value="completed">Hoàn tất</option>
                </select>
            </div>
        </div>
        <div class="wms-table-wrap">
            <table class="wms-table">
                <thead>
                <tr>
                    <th>Lệnh BTP</th>
                    <th>Ngày</th>
                    <th>Hàng BTP</th>
                    <th class="text-end">SL</th>
                    <th>Vị trí</th>
                    <th>Phiếu xuất</th>
                    <th>Người nhận / Bộ phận</th>
                    <th>Trạng thái</th>
                    <th>Ghi chú</th>
                    <th class="text-end">Thao tác</th>
                </tr>
                </thead>
                <tbody id="btpRows"><tr><td colspan="10" class="wms-loading">Đang tải dữ liệu...</td></tr></tbody>
            </table>
        </div>
    </section>
</main>

<dialog id="btpEditDialog" class="btp-dialog">
    <form id="btpEditForm" method="dialog">
        <div class="btp-dialog__head">
            <div>
                <h3 class="m-0 fs-5">Sửa lệnh BTP</h3>
                <div id="editOrderCode" class="text-muted small"></div>
            </div>
            <button type="button" class="wms-btn" id="closeEditDialog"><i data-lucide="x"></i></button>
        </div>
        <div class="btp-dialog__body">
            <input type="hidden" id="editOrderId">
            <div class="btp-grid mb-3">
                <label>Ngày
                    <input id="editOrderDate" class="form-control" type="date">
                </label>
                <label>Người nhận
                    <input id="editReceiver" class="form-control" type="text">
                </label>
                <label>Bộ phận
                    <input id="editDepartment" class="form-control" type="text">
                </label>
                <label>Mục đích
                    <input id="editPurpose" class="form-control" type="text">
                </label>
                <label class="span-4">Ghi chú lệnh
                    <input id="editOrderNote" class="form-control" type="text">
                </label>
            </div>
            <div class="btp-grid btp-grid--line">
                <label>Mã nội bộ
                    <input id="editInternalCode" class="form-control" type="text">
                </label>
                <label>Mã hàng
                    <input id="editMaHh" class="form-control" type="text">
                </label>
                <label>Tên hàng
                    <input id="editTenHh" class="form-control" type="text">
                </label>
                <label>ĐVT
                    <input id="editDvt" class="form-control" type="text">
                </label>
                <label>Số lượng
                    <input id="editQuantity" class="form-control" type="number" step="0.001" min="0.001">
                </label>
                <label>SL theo lệnh
                    <input id="editOrderedQuantity" class="form-control" type="number" step="0.001" min="0">
                </label>
                <label>Vị trí
                    <input id="editLocation" class="form-control" type="text">
                </label>
                <label>Size
                    <input id="editSize" class="form-control" type="text">
                </label>
                <label>Màu
                    <input id="editColor" class="form-control" type="text">
                </label>
                <label>Mặt
                    <input id="editSide" class="form-control" type="text">
                </label>
                <label class="span-5">Ghi chú dòng
                    <input id="editLineNote" class="form-control" type="text">
                </label>
            </div>
        </div>
        <div class="btp-dialog__foot">
            <button type="button" class="wms-btn" id="cancelEditBtn">Hủy</button>
            <button type="submit" class="wms-btn wms-btn--primary"><i data-lucide="save"></i>Lưu</button>
        </div>
    </form>
</dialog>

<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const fmt = value => Number(value || 0).toLocaleString('vi-VN', {maximumFractionDigits: 3});
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
    const firstLine = row => Array.isArray(row.lines) && row.lines.length ? row.lines[0] : {};
    let timer = null;

    function formatDate(value) {
        if (!value) return '';
        const parts = String(value).slice(0, 10).split('-');
        return parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : value;
    }

    function statusBadge(status) {
        if (status === 'issued') return '<span class="wms-badge">Đã xuất SX</span>';
        if (status === 'completed') return '<span class="wms-badge">Hoàn tất</span>';
        return '<span class="wms-badge wms-badge--warning">Mới tạo</span>';
    }

    function jsonOrError(response, fallback) {
        return response.json().catch(() => ({})).then(result => {
            if (response.ok) return result;
            const errors = result.errors ? Object.values(result.errors).flat().join('\n') : '';
            throw new Error([result.message || fallback, errors].filter(Boolean).join('\n'));
        });
    }

    function renderLineInfo(line) {
        return `
            <strong>${esc(line.internal_item_code || line.ma_hh || '-')}</strong>
            <div class="text-muted small">${esc(line.ten_hh || '')}</div>
            <div class="btp-line-meta">
                ${line.size ? `<span class="btp-chip">Size ${esc(line.size)}</span>` : ''}
                ${line.color ? `<span class="btp-chip">Màu ${esc(line.color)}</span>` : ''}
                ${line.side ? `<span class="btp-chip">Mặt ${esc(line.side)}</span>` : ''}
            </div>
        `;
    }

    function loadBtpOrders() {
        const params = new URLSearchParams();
        const keyword = document.getElementById('topKeyword').value.trim();
        const status = document.getElementById('statusFilter').value;
        if (keyword) params.set('keyword', keyword);
        if (status) params.set('status', status);

        fetch('/api/lenh-btp?' + params.toString())
            .then(response => jsonOrError(response, 'Không tải được lệnh BTP.'))
            .then(result => {
                const summary = result.summary || {};
                document.getElementById('orderCount').textContent = fmt(summary.order_count);
                document.getElementById('lineCount').textContent = fmt(summary.line_count);
                document.getElementById('totalQuantity').textContent = fmt(summary.total_quantity);
                document.getElementById('issuedCount').textContent = fmt(summary.issued_count);

                document.getElementById('btpRows').innerHTML = (result.data || []).map(row => {
                    const line = firstLine(row);
                    const locked = row.status !== 'draft' || row.issue_id;
                    return `
                    <tr>
                        <td class="wms-code">${esc(row.btp_order_code)}</td>
                        <td>${formatDate(row.order_date)}</td>
                        <td>${renderLineInfo(line)}</td>
                        <td class="wms-number">${fmt(line.quantity)}</td>
                        <td>${esc(line.location_code || '-')}</td>
                        <td>${row.issue_id ? `<a class="wms-link" target="_blank" href="/client/xuat-vat-tu-noi-bo/${row.issue_id}/in">${esc(row.issue_code || '')}</a>` : '-'}</td>
                        <td><strong>${esc(row.receiver_name || '-')}</strong><div class="text-muted small">${esc(row.department || '')}</div></td>
                        <td>${statusBadge(row.status)}</td>
                        <td>${esc(row.note || line.note || row.purpose || '')}</td>
                        <td class="text-end text-nowrap">
                            <button class="btn btn-sm btn-outline-secondary edit-btp-order" data-id="${row.id}" ${locked ? 'disabled title="Lệnh đã xuất, xóa phiếu xuất liên quan trước nếu cần sửa"' : ''}>Sửa</button>
                            <button class="btn btn-sm btn-outline-danger delete-btp-order" data-id="${row.id}" data-code="${esc(row.btp_order_code)}" ${locked ? 'disabled title="Lệnh đã xuất, không xóa trực tiếp"' : ''}>Xóa</button>
                        </td>
                    </tr>
                `}).join('') || '<tr><td colspan="10" class="wms-empty">Chưa có lệnh BTP.</td></tr>';

                if (window.lucide) lucide.createIcons();
            })
            .catch(error => {
                document.getElementById('btpRows').innerHTML = `<tr><td colspan="10" class="wms-empty text-danger">${esc(error.message)}</td></tr>`;
            });
    }

    function setValue(id, value) {
        document.getElementById(id).value = value ?? '';
    }

    function openEditOrder(id) {
        fetch(`/api/lenh-btp/${id}`)
            .then(response => jsonOrError(response, 'Không tải được lệnh BTP.'))
            .then(result => {
                const order = result.data || {};
                const line = firstLine(order);
                setValue('editOrderId', order.id);
                document.getElementById('editOrderCode').textContent = order.btp_order_code || '';
                setValue('editOrderDate', String(order.order_date || '').slice(0, 10));
                setValue('editReceiver', order.receiver_name);
                setValue('editDepartment', order.department);
                setValue('editPurpose', order.purpose);
                setValue('editOrderNote', order.note);
                setValue('editInternalCode', line.internal_item_code);
                setValue('editMaHh', line.ma_hh);
                setValue('editTenHh', line.ten_hh);
                setValue('editDvt', line.dvt);
                setValue('editQuantity', line.quantity);
                setValue('editOrderedQuantity', line.ordered_quantity);
                setValue('editLocation', line.location_code);
                setValue('editSize', line.size);
                setValue('editColor', line.color);
                setValue('editSide', line.side);
                setValue('editLineNote', line.note);
                document.getElementById('btpEditDialog').showModal();
            })
            .catch(error => alert(error.message));
    }

    function saveEditOrder(event) {
        event.preventDefault();
        const id = document.getElementById('editOrderId').value;
        const payload = {
            order_date: document.getElementById('editOrderDate').value,
            receiver_name: document.getElementById('editReceiver').value,
            department: document.getElementById('editDepartment').value,
            purpose: document.getElementById('editPurpose').value,
            note: document.getElementById('editOrderNote').value,
            lines: [{
                internal_item_code: document.getElementById('editInternalCode').value,
                ma_hh: document.getElementById('editMaHh').value,
                ten_hh: document.getElementById('editTenHh').value,
                dvt: document.getElementById('editDvt').value,
                ordered_quantity: document.getElementById('editOrderedQuantity').value || null,
                quantity: document.getElementById('editQuantity').value,
                location_code: document.getElementById('editLocation').value,
                size: document.getElementById('editSize').value,
                color: document.getElementById('editColor').value,
                side: document.getElementById('editSide').value,
                note: document.getElementById('editLineNote').value,
            }]
        };

        fetch(`/api/lenh-btp/${id}`, {
            method: 'PUT',
            headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
            body: JSON.stringify(payload)
        }).then(response => jsonOrError(response, 'Không lưu được lệnh BTP.'))
          .then(() => {
              document.getElementById('btpEditDialog').close();
              loadBtpOrders();
          })
          .catch(error => alert(error.message));
    }

    function deleteOrder(id, code) {
        if (!confirm(`Xóa lệnh BTP ${code}?`)) return;
        fetch(`/api/lenh-btp/${id}`, {
            method: 'DELETE',
            headers: {'Accept':'application/json','X-CSRF-TOKEN':csrfToken}
        }).then(response => jsonOrError(response, 'Không xóa được lệnh BTP.'))
          .then(loadBtpOrders)
          .catch(error => alert(error.message));
    }

    document.getElementById('topKeyword').addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(loadBtpOrders, 250);
    });
    document.getElementById('statusFilter').addEventListener('change', loadBtpOrders);
    document.getElementById('reloadBtn').addEventListener('click', loadBtpOrders);
    document.getElementById('closeEditDialog').addEventListener('click', () => document.getElementById('btpEditDialog').close());
    document.getElementById('cancelEditBtn').addEventListener('click', () => document.getElementById('btpEditDialog').close());
    document.getElementById('btpEditForm').addEventListener('submit', saveEditOrder);
    document.getElementById('btpRows').addEventListener('click', event => {
        const editButton = event.target.closest('.edit-btp-order');
        if (editButton) openEditOrder(editButton.dataset.id);
        const deleteButton = event.target.closest('.delete-btp-order');
        if (deleteButton) deleteOrder(deleteButton.dataset.id, deleteButton.dataset.code);
    });

    loadBtpOrders();
    if (window.lucide) lucide.createIcons();
</script>
</body>
</html>
