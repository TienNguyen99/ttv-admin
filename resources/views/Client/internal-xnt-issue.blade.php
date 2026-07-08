<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Xuất chỉ theo XNT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/warehouse-wms.css') }}?v={{ filemtime(public_path('css/warehouse-wms.css')) }}" rel="stylesheet">
    <style>
        .xnt-table { min-width: 1280px; }
        .xnt-item { max-width: 360px; white-space: normal; line-height: 1.35; }
        .xnt-locations { display:flex; flex-wrap:wrap; gap:4px; }
        .xnt-location { border:1px solid #bfdbfe; color:#1d4ed8; background:#eff6ff; border-radius:999px; padding:2px 8px; font-size:12px; }
        .xnt-bad { color:#b45309; background:#fffbeb; border:1px solid #fde68a; border-radius:999px; padding:3px 8px; font-size:12px; }
        .xnt-ok { color:#047857; background:#ecfdf5; border:1px solid #a7f3d0; border-radius:999px; padding:3px 8px; font-size:12px; }
        .xnt-toolbar { display:flex; gap:8px; align-items:center; justify-content:flex-end; flex-wrap:wrap; }
        .xnt-check { width:18px; height:18px; }
        .xnt-note { color:#64748b; font-size:12px; }
    </style>
</head>
<body>
    @include('layouts.partials.sidebar')

    <header class="wms-topbar">
        <h1 class="wms-topbar__title">WMS May Mặc</h1>
        <div class="wms-global-search">
            <i data-lucide="search"></i>
            <input id="topKeyword" aria-label="Tìm XNT" placeholder="Tìm lệnh SX, tên hàng, số phiếu...">
        </div>
        <a class="wms-btn" href="{{ url('/client/xuat-vat-tu-noi-bo') }}"><i data-lucide="package-minus"></i>Xem phiếu xuất</a>
    </header>

    <main class="wms-page">
        <div class="wms-heading">
            <div>
                <h1>Xuất chỉ theo Google Sheet XNT</h1>
                <p>XNT là phiếu xuất chỉ gốc. Khi đồng bộ, hệ thống group theo Số phiếu và ghi nhận vào kho nội bộ để trừ tồn.</p>
            </div>
            <div class="xnt-toolbar">
                <button id="syncBtn" class="wms-btn wms-btn--primary" type="button"><i data-lucide="refresh-cw"></i>Đồng bộ phiếu XNT</button>
                <button id="issueOrderBtn" class="wms-btn" type="button" disabled><i data-lucide="send-horizontal"></i>Xuất lệnh đang lọc</button>
                <button id="createIssueBtn" class="wms-btn" type="button" disabled><i data-lucide="send"></i>Xử lý dòng chọn</button>
            </div>
        </div>

        <section class="wms-kpi-grid mb-3">
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="rows-3"></i></div><div><div class="wms-kpi__label">Dòng XNT</div><div id="kpiLines" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Theo bộ lọc</div></div></article>
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="check-circle-2"></i></div><div><div class="wms-kpi__label">Đã khớp</div><div id="kpiMatched" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Có trong danh mục</div></div></article>
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="alert-triangle"></i></div><div><div class="wms-kpi__label">Chưa khớp</div><div id="kpiMissing" class="wms-kpi__value">0</div><div class="wms-kpi__meta">Cần sửa danh mục</div></div></article>
            <article class="wms-kpi"><div class="wms-kpi__icon"><i data-lucide="scale"></i></div><div><div class="wms-kpi__label">Tổng SL</div><div id="kpiQuantity" class="wms-kpi__value">0</div><div id="syncLabel" class="wms-kpi__meta">Chưa đồng bộ</div></div></article>
        </section>

        <section class="wms-filterbar" style="grid-template-columns:220px minmax(260px,1fr) 180px 180px auto">
            <div>
                <label for="productionOrder">Lệnh SX</label>
                <input id="productionOrder" class="form-control" placeholder="VD: T-00006/26">
            </div>
            <div>
                <label for="keyword">Tìm tên hàng / số phiếu</label>
                <input id="keyword" class="form-control" placeholder="Spandex, HAP 60, 2026/01091...">
            </div>
            <div>
                <label for="issueDate">Ngày xuất</label>
                <input id="issueDate" type="date" class="form-control" value="{{ now()->format('Y-m-d') }}">
            </div>
            <div>
                <label for="receiverName">Người nhận</label>
                <input id="receiverName" class="form-control" placeholder="Để trống lấy từ XNT">
            </div>
            <div>
                <button id="clearBtn" class="wms-btn" type="button"><i data-lucide="filter-x"></i>Xóa lọc</button>
            </div>
        </section>

        <section class="wms-panel">
            <div class="wms-panel__header">
                <div>
                    <h2>Danh sách XNT</h2>
                    <p class="xnt-note mb-0">Dòng có Số phiếu + Lệnh SX sẽ tự thành phiếu xuất chỉ khi đồng bộ. Dòng lỗi danh mục cần sửa rồi đồng bộ lại.</p>
                </div>
                <div class="xnt-toolbar">
                    <button id="selectMatchedBtn" class="btn btn-sm btn-outline-primary" type="button">Chọn dòng đã khớp</button>
                    <button id="clearSelectBtn" class="btn btn-sm btn-outline-secondary" type="button">Bỏ chọn</button>
                    <span id="selectedLabel" class="text-secondary small">0 dòng chọn</span>
                </div>
            </div>
            <div class="wms-table-wrap">
                <table class="wms-table xnt-table">
                    <thead>
                    <tr>
                        <th style="width:44px"><input id="selectAll" class="form-check-input xnt-check" type="checkbox"></th>
                        <th>Số phiếu</th>
                        <th>Ngày xuất</th>
                        <th>Lệnh SX</th>
                        <th>Tên hàng XNT</th>
                        <th>Mã nội bộ</th>
                        <th class="text-end">SL</th>
                        <th>ĐVT</th>
                        <th class="text-end">Tồn</th>
                        <th>Kệ gợi ý</th>
                        <th>Người nhận</th>
                        <th>Trạng thái phiếu</th>
                        <th>Kiểm tra</th>
                    </tr>
                    </thead>
                    <tbody id="rows">
                    <tr><td colspan="13" class="wms-loading">Đang tải...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const rowsEl = document.getElementById('rows');
        const selected = new Set();
        let currentRows = [];
        let timer = null;

        const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
        const num = value => Number(value || 0).toLocaleString('vi-VN', {maximumFractionDigits:3});
        const fmtDate = value => value ? value.split('-').reverse().join('/') : '-';

        function jsonOrError(response, fallback) {
            if (response.ok) return response.json();
            return response.json().then(result => { throw new Error(result.message || fallback); });
        }

        function params() {
            const p = new URLSearchParams();
            const keyword = document.getElementById('keyword').value.trim();
            const order = document.getElementById('productionOrder').value.trim();
            if (keyword) p.set('keyword', keyword);
            if (order) p.set('production_order', order);
            p.set('limit', '1000');
            return p;
        }

        function updateSelection() {
            document.getElementById('selectedLabel').textContent = `${selected.size} dòng chọn`;
            document.getElementById('createIssueBtn').disabled = selected.size === 0;
            const orderKeyword = document.getElementById('productionOrder').value.trim();
            const orderRows = eligibleRowsForCurrentOrder();
            document.getElementById('issueOrderBtn').disabled = !orderKeyword || orderRows.length === 0;
            document.querySelectorAll('.row-check').forEach(input => {
                input.checked = selected.has(Number(input.dataset.id));
            });
            const selectable = currentRows
                .filter(row => row.is_matched && row.production_order && !row.issue_id)
                .map(row => row.id);
            document.getElementById('selectAll').checked = selectable.length > 0 && selectable.every(id => selected.has(id));
        }

        function renderRows(rows) {
            currentRows = rows;
            if (!rows.length) {
                rowsEl.innerHTML = '<tr><td colspan="13" class="wms-empty">Chưa có dữ liệu. Bấm Đồng bộ XNT hoặc đổi bộ lọc.</td></tr>';
                updateSelection();
                return;
            }

            rowsEl.innerHTML = rows.map(row => {
                const locations = (row.locations || []).slice(0, 4).map(item => `<span class="xnt-location">${esc(item.location_code)} · ${num(item.quantity)}</span>`).join('');
                const checkStatus = row.is_matched
                    ? '<span class="xnt-ok">Đã khớp</span>'
                    : '<span class="xnt-bad">Chưa khớp danh mục</span>';
                const issueStatus = row.issue_id
                    ? `<a class="wms-link" href="/client/xuat-vat-tu-noi-bo?keyword=${encodeURIComponent(row.issue_code || '')}">${esc(row.issue_code || 'Đã có phiếu')}</a>`
                    : (row.production_order ? '<span class="xnt-bad">Có lệnh, chưa có phiếu</span>' : '<span class="text-secondary small">Không có lệnh</span>');
                const canSelect = row.is_matched && row.production_order && !row.issue_id;
                return `
                    <tr>
                        <td><input class="form-check-input xnt-check row-check" type="checkbox" data-id="${row.id}" ${canSelect ? '' : 'disabled'}></td>
                        <td class="wms-code">${esc(row.voucher_code || '-')}</td>
                        <td>${fmtDate(row.issue_date)}</td>
                        <td class="wms-code">${esc(row.production_order || '-')}</td>
                        <td class="xnt-item">
                            <strong>${esc(row.item_name || '-')}</strong>
                            ${row.item_code ? `<div class="text-secondary small">Mã sheet: ${esc(row.item_code)}</div>` : ''}
                        </td>
                        <td>
                            ${row.catalog_code ? `<a class="wms-link" href="/client/danh-muc-noi-bo?keyword=${encodeURIComponent(row.catalog_code)}">${esc(row.catalog_code)}</a>` : '-'}
                            ${row.catalog_name ? `<div class="text-secondary small">${esc(row.catalog_name)}</div>` : ''}
                        </td>
                        <td class="wms-number">${num(row.quantity)}</td>
                        <td>${esc(row.unit || '-')} ${row.converted ? `<div class="small text-secondary">= ${num(row.base_quantity)} ${esc(row.base_dvt)}</div>` : ''}</td>
                        <td class="wms-number ${Number(row.available_quantity || 0) < Number(row.base_quantity || row.quantity || 0) ? 'text-danger' : ''}">${num(row.available_quantity)}</td>
                        <td>${locations || esc(row.suggested_location || row.catalog_shelf || '-')}</td>
                        <td>${esc(row.receiver_name || '-')}</td>
                        <td>${issueStatus}</td>
                        <td>${checkStatus}</td>
                    </tr>
                `;
            }).join('');
            updateSelection();
        }

        function renderSummary(summary) {
            document.getElementById('kpiLines').textContent = num(summary.line_count);
            document.getElementById('kpiMatched').textContent = num(summary.matched_count);
            document.getElementById('kpiMissing').textContent = num(summary.missing_count);
            document.getElementById('kpiQuantity').textContent = num(summary.total_quantity);
            document.getElementById('syncLabel').textContent = summary.last_synced_at ? `Sync ${summary.last_synced_at}` : 'Chưa đồng bộ';
        }

        function loadRows() {
            rowsEl.innerHTML = '<tr><td colspan="13" class="wms-loading">Đang tải...</td></tr>';
            fetch('/api/xnt?' + params().toString(), {headers:{'Accept':'application/json'}})
                .then(response => jsonOrError(response, 'Không tải được XNT'))
                .then(result => {
                    renderSummary(result.summary || {});
                    renderRows(result.data || []);
                })
                .catch(error => {
                    rowsEl.innerHTML = `<tr><td colspan="13" class="wms-empty text-danger">${esc(error.message)}</td></tr>`;
                });
        }

        function syncXnt() {
            const btn = document.getElementById('syncBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang đồng bộ...';
            fetch('/api/xnt/dong-bo', {
                method: 'POST',
                headers: {'Accept':'application/json','X-CSRF-TOKEN':csrfToken},
            }).then(response => jsonOrError(response, 'Không đồng bộ được XNT'))
              .then(result => {
                  const data = result.data || {};
                  alert(`${result.message || 'Đã đồng bộ XNT'}\nTạo phiếu: ${num(data.issue_created || 0)}\nLink phiếu cũ: ${num(data.issue_linked || 0)}\nBỏ qua/lỗi: ${num(data.issue_skipped || 0)}`);
                  loadRows();
              })
              .catch(error => alert(error.message))
              .finally(() => {
                  btn.disabled = false;
                  btn.innerHTML = '<i data-lucide="refresh-cw"></i>Đồng bộ phiếu XNT';
                  lucide.createIcons();
              });
        }

        function eligibleRowsForCurrentOrder() {
            const orderKeyword = document.getElementById('productionOrder').value.trim().toLowerCase();
            return currentRows.filter(row => {
                if (!row.is_matched || !row.production_order || row.issue_id) return false;
                if (!orderKeyword) return false;
                return String(row.production_order).toLowerCase().includes(orderKeyword);
            });
        }

        function createIssueForIds(ids) {
            if (!ids.length) return;
            const payload = {
                row_ids: ids,
                issue_date: document.getElementById('issueDate').value,
                receiver_name: document.getElementById('receiverName').value.trim(),
                department: 'Sản xuất',
                note: 'Xuất chỉ theo Google Sheet XNT',
            };
            const btn = document.getElementById('createIssueBtn');
            btn.disabled = true;
            document.getElementById('issueOrderBtn').disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang xử lý...';
            fetch('/api/xnt/tao-phieu-xuat', {
                method: 'POST',
                headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify(payload),
            }).then(response => jsonOrError(response, 'Không tạo được phiếu xuất chỉ'))
              .then(result => {
                  const issueCode = result.data?.issue_code || '';
                  window.location.href = '/client/xuat-vat-tu-noi-bo?keyword=' + encodeURIComponent(issueCode);
              })
              .catch(error => alert(error.message))
              .finally(() => {
                  btn.innerHTML = '<i data-lucide="send"></i>Xử lý dòng chọn';
                  updateSelection();
                  lucide.createIcons();
              });
        }

        function createIssue() {
            createIssueForIds(Array.from(selected));
        }

        function issueCurrentOrder() {
            const rows = eligibleRowsForCurrentOrder();
            if (!rows.length) return alert('Lệnh đang lọc không có dòng chỉ hợp lệ để xuất, hoặc các dòng đã có phiếu.');
            selected.clear();
            rows.forEach(row => selected.add(row.id));
            updateSelection();
            createIssueForIds(rows.map(row => row.id));
        }

        function queueLoad(source) {
            if (source && source.id === 'topKeyword') {
                document.getElementById('keyword').value = source.value;
            }
            if (source && source.id === 'keyword') {
                document.getElementById('topKeyword').value = source.value;
            }
            clearTimeout(timer);
            timer = setTimeout(loadRows, 250);
        }

        document.getElementById('syncBtn').addEventListener('click', syncXnt);
        document.getElementById('issueOrderBtn').addEventListener('click', issueCurrentOrder);
        document.getElementById('createIssueBtn').addEventListener('click', createIssue);
        document.getElementById('keyword').addEventListener('input', event => queueLoad(event.target));
        document.getElementById('topKeyword').addEventListener('input', event => queueLoad(event.target));
        document.getElementById('productionOrder').addEventListener('input', () => queueLoad());
        document.getElementById('clearBtn').addEventListener('click', () => {
            document.getElementById('keyword').value = '';
            document.getElementById('topKeyword').value = '';
            document.getElementById('productionOrder').value = '';
            selected.clear();
            loadRows();
        });
        document.getElementById('selectMatchedBtn').addEventListener('click', () => {
            currentRows.filter(row => row.is_matched && row.production_order && !row.issue_id).forEach(row => selected.add(row.id));
            updateSelection();
        });
        document.getElementById('clearSelectBtn').addEventListener('click', () => {
            selected.clear();
            updateSelection();
        });
        document.getElementById('selectAll').addEventListener('change', event => {
            currentRows.filter(row => row.is_matched && row.production_order && !row.issue_id).forEach(row => {
                if (event.target.checked) selected.add(row.id);
                else selected.delete(row.id);
            });
            updateSelection();
        });
        rowsEl.addEventListener('change', event => {
            const input = event.target.closest('.row-check');
            if (!input) return;
            const id = Number(input.dataset.id);
            if (input.checked) selected.add(id);
            else selected.delete(id);
            updateSelection();
        });

        loadRows();
        lucide.createIcons();
    </script>
</body>
</html>
