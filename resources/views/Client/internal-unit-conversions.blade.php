<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Quy đổi đơn vị tính</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/warehouse-wms.css') }}?v={{ filemtime(public_path('css/warehouse-wms.css')) }}" rel="stylesheet">
    <style>
        .unit-table { min-width: 980px; }
        .hint { color:#64748b; font-size:12px; }
    </style>
</head>
<body>
    @include('layouts.partials.sidebar')

    <header class="wms-topbar">
        <h1 class="wms-topbar__title">WMS May Mặc</h1>
        <div class="wms-global-search">
            <i data-lucide="search"></i>
            <input id="topKeyword" aria-label="Tìm quy đổi" placeholder="Tìm mã nội bộ, M, YARD, KG...">
        </div>
    </header>

    <main class="wms-page">
        <div class="wms-heading">
            <div>
                <h1>Quy đổi đơn vị tính</h1>
                <p>Quy đổi theo mã nội bộ. Để trống mã nội bộ nghĩa là áp dụng chung toàn hệ thống.</p>
            </div>
        </div>

        <section class="wms-panel mb-3">
            <div class="wms-panel__header">
                <div>
                    <h2>Thêm / cập nhật quy đổi</h2>
                    <p class="hint mb-0">Ví dụ: mã TT01, từ YARD sang M, hệ số 0.9144. Tồn sẽ tính theo ĐVT chuẩn trong danh mục.</p>
                </div>
                <button id="saveBtn" class="wms-btn wms-btn--primary"><i data-lucide="save"></i>Lưu quy đổi</button>
            </div>
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Mã nội bộ</label>
                    <input id="itemCode" class="form-control" placeholder="Để trống = áp dụng chung">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Từ ĐVT</label>
                    <input id="fromUnit" class="form-control" placeholder="YARD">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Sang ĐVT</label>
                    <input id="toUnit" class="form-control" placeholder="M">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Hệ số</label>
                    <input id="factor" type="number" step="0.0000000001" min="0" class="form-control" placeholder="0.9144">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Ghi chú</label>
                    <input id="note" class="form-control" placeholder="Yard sang mét">
                </div>
            </div>
        </section>

        <section class="wms-filterbar" style="grid-template-columns:minmax(260px,1fr) auto">
            <div><label for="keyword">Tìm kiếm</label><input id="keyword" class="form-control" placeholder="Mã nội bộ hoặc đơn vị"></div>
            <div><button id="clearBtn" class="wms-btn"><i data-lucide="filter-x"></i>Xóa lọc</button></div>
        </section>

        <section class="wms-panel">
            <div class="wms-panel__header">
                <h2>Danh sách quy đổi</h2>
                <span id="resultLabel" class="text-secondary small">Đang tải...</span>
            </div>
            <div class="wms-table-wrap">
                <table class="wms-table unit-table">
                    <thead><tr><th>Phạm vi</th><th>Từ ĐVT</th><th>Sang ĐVT</th><th class="text-end">Hệ số</th><th>Ghi chú</th><th></th></tr></thead>
                    <tbody id="rows"><tr><td colspan="6" class="wms-loading">Đang tải...</td></tr></tbody>
                </table>
            </div>
        </section>
    </main>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const rowsEl = document.getElementById('rows');
        const keywordEl = document.getElementById('keyword');
        const topKeywordEl = document.getElementById('topKeyword');
        const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
        const num = value => Number(value || 0).toLocaleString('vi-VN', {maximumFractionDigits:10});
        let timer = null;

        function jsonOrError(response, fallback) {
            if (response.ok) return response.json();
            return response.json().then(result => { throw new Error(result.message || fallback); });
        }

        function loadRows() {
            const params = new URLSearchParams();
            if (keywordEl.value.trim()) params.set('keyword', keywordEl.value.trim());
            rowsEl.innerHTML = '<tr><td colspan="6" class="wms-loading">Đang tải...</td></tr>';
            fetch('/api/quy-doi-don-vi?' + params.toString())
                .then(response => jsonOrError(response, 'Không tải được quy đổi đơn vị'))
                .then(result => {
                    const rows = result.data || [];
                    document.getElementById('resultLabel').textContent = `${num(rows.length)} quy đổi`;
                    rowsEl.innerHTML = rows.map(row => `
                        <tr>
                            <td>${row.item_code ? esc(row.item_code) : '<span class="badge text-bg-primary">Áp dụng chung</span>'}</td>
                            <td class="wms-code">${esc(row.from_unit)}</td>
                            <td class="wms-code">${esc(row.to_unit)}</td>
                            <td class="wms-number">${num(row.factor)}</td>
                            <td>${esc(row.note || '-')}</td>
                            <td class="text-end"><button class="btn btn-sm btn-outline-danger delete-btn" data-id="${row.id}">Xóa</button></td>
                        </tr>
                    `).join('') || '<tr><td colspan="6" class="wms-empty">Chưa có quy đổi.</td></tr>';
                })
                .catch(error => rowsEl.innerHTML = `<tr><td colspan="6" class="wms-empty text-danger">${esc(error.message)}</td></tr>`);
        }

        function saveRow() {
            const payload = {
                item_code: document.getElementById('itemCode').value.trim(),
                from_unit: document.getElementById('fromUnit').value.trim(),
                to_unit: document.getElementById('toUnit').value.trim(),
                factor: document.getElementById('factor').value,
                note: document.getElementById('note').value.trim(),
            };
            fetch('/api/quy-doi-don-vi', {
                method: 'POST',
                headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify(payload),
            }).then(response => jsonOrError(response, 'Không lưu được quy đổi'))
              .then(() => {
                  document.getElementById('factor').value = '';
                  document.getElementById('note').value = '';
                  loadRows();
              })
              .catch(error => alert(error.message));
        }

        function queueSearch(source) {
            if (source === topKeywordEl) keywordEl.value = topKeywordEl.value;
            if (source === keywordEl) topKeywordEl.value = keywordEl.value;
            clearTimeout(timer);
            timer = setTimeout(loadRows, 250);
        }

        document.getElementById('saveBtn').addEventListener('click', saveRow);
        document.getElementById('clearBtn').addEventListener('click', () => {
            keywordEl.value = '';
            topKeywordEl.value = '';
            loadRows();
        });
        keywordEl.addEventListener('input', () => queueSearch(keywordEl));
        topKeywordEl.addEventListener('input', () => queueSearch(topKeywordEl));
        rowsEl.addEventListener('click', event => {
            const button = event.target.closest('.delete-btn');
            if (!button || !confirm('Xóa quy đổi này?')) return;
            fetch(`/api/quy-doi-don-vi/${button.dataset.id}`, {
                method:'DELETE',
                headers:{'Accept':'application/json','X-CSRF-TOKEN':csrfToken},
            }).then(response => jsonOrError(response, 'Không xóa được quy đổi'))
              .then(loadRows)
              .catch(error => alert(error.message));
        });

        loadRows();
        lucide.createIcons();
    </script>
</body>
</html>
