<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tồn kho nội bộ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f6f7f9; color: #111827; }
        .panel { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; }
        .page-title { font-size: 24px; font-weight: 700; }
        .hint { color: #6b7280; font-size: 13px; }
        .metric { font-size: 20px; font-weight: 700; }
        .table td, .table th { vertical-align: middle; }
    </style>
</head>

<body>
    @include('layouts.partials.sidebar')

    <main class="container-fluid py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
            <div>
                <h1 class="page-title mb-1">Tồn kho nội bộ</h1>
                <div class="hint">Tồn thực tế từ phiếu nhập/xuất nội bộ và kiện đang còn trong kho. Không đọc/ghi tồn TSoft.</div>
            </div>
            <button id="reloadBtn" type="button" class="btn btn-primary">Tải lại</button>
        </div>

        <section class="panel mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">KHO</label>
                    <select id="warehouseSelect" class="form-select">
                        <option value="">Tất cả kho</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Tìm mã, mã nội bộ, size, màu hoặc vị trí</label>
                    <input id="keyword" class="form-control" placeholder="Nhập từ khóa">
                </div>
            </div>
        </section>

        <section class="row g-3 mb-3">
            <div class="col-md-3"><div class="panel"><div class="hint">Mã hàng</div><div id="itemCount" class="metric">0</div></div></div>
            <div class="col-md-3"><div class="panel"><div class="hint">Dòng tồn</div><div id="lineCount" class="metric">0</div></div></div>
            <div class="col-md-3"><div class="panel"><div class="hint">Số kiện</div><div id="packageCount" class="metric">0</div></div></div>
            <div class="col-md-3"><div class="panel"><div class="hint">Tổng số lượng</div><div id="totalQuantity" class="metric">0</div></div></div>
        </section>

        <section class="panel">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>KHO</th>
                            <th>Vị trí</th>
                            <th>Mã hàng</th>
                            <th>Mã nội bộ</th>
                            <th>Size</th>
                            <th>Màu</th>
                            <th>Side</th>
                            <th class="text-end">Số kiện</th>
                            <th class="text-end">Tồn</th>
                            <th>Ngày mới nhất</th>
                        </tr>
                    </thead>
                    <tbody id="stockRows"></tbody>
                </table>
            </div>
        </section>
    </main>

    <script>
        const warehouseSelect = document.getElementById('warehouseSelect');
        const keywordEl = document.getElementById('keyword');
        const rowsEl = document.getElementById('stockRows');
        let searchTimer = null;

        const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
        const num = value => Number(value || 0).toLocaleString('vi-VN', { maximumFractionDigits: 3 });

        function jsonOrError(response, fallback) {
            if (response.ok) return response.json();
            return response.json().then(result => { throw new Error(result.message || fallback); });
        }

        function loadWarehouses() {
            fetch('/api/ton-kho-noi-bo/kho')
                .then(response => jsonOrError(response, 'Không tải được danh sách kho'))
                .then(result => {
                    const current = warehouseSelect.value;
                    warehouseSelect.innerHTML = '<option value="">Tất cả kho</option>' + (result.data || []).map(code => `<option value="${esc(code)}">${esc(code)}</option>`).join('');
                    warehouseSelect.value = current;
                })
                .catch(error => alert(error.message));
        }

        function loadStock() {
            const params = new URLSearchParams();
            if (warehouseSelect.value) params.set('warehouse_code', warehouseSelect.value);
            if (keywordEl.value.trim()) params.set('keyword', keywordEl.value.trim());

            fetch(`/api/ton-kho-noi-bo?${params.toString()}`)
                .then(response => jsonOrError(response, 'Không tải được tồn nội bộ'))
                .then(result => {
                    document.getElementById('itemCount').textContent = num(result.summary?.item_count || 0);
                    document.getElementById('lineCount').textContent = num(result.summary?.line_count || 0);
                    document.getElementById('packageCount').textContent = num(result.summary?.package_count || 0);
                    document.getElementById('totalQuantity').textContent = num(result.summary?.total_quantity || 0);
                    rowsEl.innerHTML = (result.data || []).map(row => `
                        <tr>
                            <td>${esc(row.warehouse_code || '-')}</td>
                            <td>${esc(row.location_code)}</td>
                            <td>${esc(row.ma_sp)}</td>
                            <td>${esc(row.internal_item_code)}</td>
                            <td>${esc(row.size)}</td>
                            <td>${esc(row.color)}</td>
                            <td>${esc(row.side)}</td>
                            <td class="text-end">${num(row.package_count)}</td>
                            <td class="text-end fw-semibold">${num(row.total_quantity)}</td>
                            <td>${esc(row.latest_checked_at)}</td>
                        </tr>
                    `).join('') || '<tr><td colspan="10" class="text-center hint">Không có tồn phù hợp</td></tr>';
                })
                .catch(error => alert(error.message));
        }

        warehouseSelect.addEventListener('change', loadStock);
        keywordEl.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(loadStock, 250);
        });
        document.getElementById('reloadBtn').addEventListener('click', () => {
            loadWarehouses();
            loadStock();
        });

        loadWarehouses();
        loadStock();
    </script>
</body>
</html>
