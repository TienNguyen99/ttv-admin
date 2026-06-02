<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Đối chiếu tồn nội bộ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f6f7f9; }
        .panel { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; }
        .metric { font-size: 20px; font-weight: 700; }
    </style>
</head>

<body>
    @include('layouts.partials.sidebar')

    <div class="container-fluid py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h3 class="mb-1">Đối chiếu tồn nội bộ</h3>
                <div class="text-muted">Kế toán lưu tổng. Nội bộ tách size, màu và cộng lại để đối chiếu.</div>
            </div>
            <div class="d-flex gap-2">
                <span id="refreshStatus" class="align-self-center text-muted small"></span>
                <button id="missingReceiptBtn" type="button" class="btn btn-outline-warning">Xuất chưa có nhập <span id="missingReceiptBadge" class="badge text-bg-warning ms-1">0</span></button>
                <a href="/client/phieu-nhap-thanh-pham" class="btn btn-outline-secondary">Phiếu nhập TP</a>
                <button id="exportBtn" type="button" class="btn btn-success">Xuất Excel</button>
                <button id="reloadBtn" type="button" class="btn btn-primary">Tải lại</button>
            </div>
        </div>

        <div class="panel mb-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="checkedAt" class="form-label">Ngày kiểm kê</label>
                    <input id="checkedAt" type="date" class="form-control" value="{{ now()->format('Y-m-d') }}">
                </div>
                <div class="col-md-5">
                    <label for="keyword" class="form-label">Tìm mã thành phẩm, tên hàng hoặc kho</label>
                    <input id="keyword" type="text" class="form-control" placeholder="Nhập từ khóa">
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-4"><div class="panel"><div class="text-muted">Tổng mã theo kho</div><div id="totalItems" class="metric">0</div></div></div>
            <div class="col-md-4"><div class="panel"><div class="text-muted">Đã kiểm kê</div><div id="checkedItems" class="metric text-success">0</div></div></div>
            <div class="col-md-4"><div class="panel"><div class="text-muted">Có chênh lệch</div><div id="differentItems" class="metric text-danger">0</div></div></div>
        </div>

        <div class="panel">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Mã thành phẩm</th><th>Tên hàng</th><th>Kho</th><th>ĐVT</th>
                            <th class="text-end">Tổng nhập</th><th class="text-end">Tổng xuất</th>
                            <th class="text-end">Tồn kế toán</th><th class="text-end">Tổng nội bộ</th>
                            <th class="text-end">Chênh lệch</th><th></th>
                        </tr>
                    </thead>
                    <tbody id="comparisonRows"></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Chi tiết size, màu nội bộ</h5>
                        <div class="text-muted" id="detailTitle"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light"><tr><th>Mã hàng nội bộ</th><th>Size</th><th>Màu</th><th>Side</th><th>Số lượng</th><th>Ghi chú</th><th></th></tr></thead>
                            <tbody id="detailRows"></tbody>
                        </table>
                    </div>
                    <div class="row g-2 align-items-end">
                        <div class="col-md-2"><label class="form-label">Mã hàng nội bộ</label><input id="newInternalItemCode" class="form-control"></div>
                        <div class="col-md-1"><label class="form-label">Size</label><input id="newSize" class="form-control"></div>
                        <div class="col-md-2"><label class="form-label">Màu</label><input id="newColor" class="form-control"></div>
                        <div class="col-md-1"><label class="form-label">Side</label><input id="newSide" class="form-control"></div>
                        <div class="col-md-2"><label class="form-label">Số lượng</label><input id="newQuantity" type="number" step="0.001" class="form-control"></div>
                        <div class="col-md-2"><label class="form-label">Ghi chú</label><input id="newNote" class="form-control" maxlength="500"></div>
                        <div class="col-md-2"><button id="addDetailBtn" type="button" class="btn btn-primary w-100">Thêm dòng</button></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="receiptModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Tạo phiếu nhập thành phẩm</h5>
                        <div class="text-muted small">Phiếu nội bộ để in và bàn giao kế toán nhập phần mềm.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Mã thành phẩm</label><input id="receiptMaSp" class="form-control" readonly></div>
                    <div class="mb-3"><label class="form-label">Tên hàng</label><input id="receiptTenHh" class="form-control" readonly></div>
                    <div class="row g-2">
                        <div class="col-md-6"><label class="form-label">Kho nhận</label><input id="receiptMaKo" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Ngày phiếu</label><input id="receiptDate" type="date" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">ĐVT</label><input id="receiptDvt" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Số lượng nhập</label><input id="receiptQuantity" type="number" min="0.001" step="0.001" class="form-control"></div>
                    </div>
                    <div class="mt-3"><label class="form-label">Ghi chú</label><input id="receiptNote" class="form-control" maxlength="500" value="Bổ sung phiếu nhập theo rà soát xuất kho chưa có nhập."></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button id="createReceiptBtn" type="button" class="btn btn-primary">Tạo và in phiếu</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const checkedAtEl = document.getElementById('checkedAt');
        const keywordEl = document.getElementById('keyword');
        const rowsEl = document.getElementById('comparisonRows');
        const detailRowsEl = document.getElementById('detailRows');
        const detailModal = new bootstrap.Modal('#detailModal');
        const receiptModal = new bootstrap.Modal('#receiptModal');
        let rows = [];
        let selectedRow = null;
        let receiptRow = null;
        let missingReceiptOnly = false;

        const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
        const num = value => value === null || value === undefined ? '' : Number(value).toLocaleString('vi-VN', { maximumFractionDigits: 3 });
        const rowKey = row => `${row.ma_sp}|${row.ma_ko}`;

        function renderRows() {
            const keyword = keywordEl.value.trim().toLowerCase();
            rowsEl.innerHTML = rows.filter(row => {
                if (missingReceiptOnly && !row.missing_receipt) return false;
                return `${row.ma_sp} ${row.ma_ko} ${row.ten_hh || ''}`.toLowerCase().includes(keyword);
            }).map(row => {
                const differenceClass = row.difference === null || Number(row.difference) === 0 ? '' : 'text-danger fw-bold';
                return `<tr>
                    <td>${esc(row.ma_sp)}${row.internal_only ? '<div><span class="badge text-bg-warning">Chỉ có nội bộ</span></div>' : ''}${row.missing_receipt ? '<div><span class="badge text-bg-danger">Xuất chưa có nhập</span></div>' : ''}</td><td>${esc(row.ten_hh)}</td><td>${esc(row.ma_ko)}</td><td>${esc(row.dvt)}</td>
                    <td class="text-end">${num(row.tong_nhap)}</td><td class="text-end">${num(row.tong_xuat)}</td>
                    <td class="text-end">${num(row.source_quantity)}</td><td class="text-end">${num(row.counted_quantity)}</td>
                    <td class="text-end ${differenceClass}">${num(row.difference)}</td>
                    <td class="text-nowrap">
                        ${row.missing_receipt ? `<button class="btn btn-sm btn-outline-danger receipt-btn" data-key="${esc(rowKey(row))}">Tạo phiếu nhập</button>` : ''}
                        <button class="btn btn-sm btn-outline-primary detail-btn" data-key="${esc(rowKey(row))}">Chi tiết</button>
                    </td>
                </tr>`;
            }).join('');
        }

        function renderDetails() {
            document.getElementById('detailTitle').textContent = `${selectedRow.ma_sp} | Kho ${selectedRow.ma_ko || '-'}`;
            detailRowsEl.innerHTML = (selectedRow.details || []).map(detail => `<tr>
                <td>${esc(detail.internal_item_code)}</td><td>${esc(detail.size)}</td><td>${esc(detail.color)}</td><td>${esc(detail.side)}</td><td class="text-end">${num(detail.counted_quantity)}</td>
                <td>${esc(detail.note)}</td>
                <td><button class="btn btn-sm btn-outline-danger delete-detail-btn" data-id="${detail.id}">Xóa</button></td>
            </tr>`).join('') || '<tr><td colspan="7" class="text-center text-muted">Chưa có chi tiết nội bộ</td></tr>';
        }

        function loadData() {
            document.getElementById('refreshStatus').textContent = 'Đang cập nhật...';
            fetch(`/api/doi-chieu-ton?checked_at=${encodeURIComponent(checkedAtEl.value)}`)
                .then(response => { if (!response.ok) throw new Error('Không tải được dữ liệu'); return response.json(); })
                .then(result => {
                    rows = result.data || [];
                    document.getElementById('totalItems').textContent = num(result.summary?.total_items || 0);
                    document.getElementById('checkedItems').textContent = num(result.summary?.checked_items || 0);
                    document.getElementById('differentItems').textContent = num(result.summary?.different_items || 0);
                    document.getElementById('missingReceiptBadge').textContent = num(result.summary?.missing_receipt_items || 0);
                    renderRows();
                    if (selectedRow) {
                        selectedRow = rows.find(row => rowKey(row) === rowKey(selectedRow));
                        if (selectedRow) renderDetails();
                    }
                    document.getElementById('refreshStatus').textContent = `Cập nhật ${new Date().toLocaleTimeString('vi-VN')}`;
                })
                .catch(error => {
                    document.getElementById('refreshStatus').textContent = 'Không cập nhật được';
                    alert(error.message);
                });
        }

        rowsEl.addEventListener('click', event => {
            const receiptButton = event.target.closest('.receipt-btn');
            if (receiptButton) {
                receiptRow = rows.find(row => rowKey(row) === receiptButton.dataset.key);
                document.getElementById('receiptMaSp').value = receiptRow.ma_sp || '';
                document.getElementById('receiptTenHh').value = receiptRow.ten_hh || '';
                document.getElementById('receiptMaKo').value = receiptRow.ma_ko || '';
                document.getElementById('receiptDvt').value = receiptRow.dvt || '';
                document.getElementById('receiptQuantity').value = receiptRow.tong_xuat || '';
                document.getElementById('receiptDate').value = new Date().toISOString().slice(0, 10);
                receiptModal.show();
                return;
            }
            const button = event.target.closest('.detail-btn');
            if (!button) return;
            selectedRow = rows.find(row => rowKey(row) === button.dataset.key);
            renderDetails();
            detailModal.show();
        });

        document.getElementById('createReceiptBtn').addEventListener('click', () => {
            if (!receiptRow) return;
            fetch('/api/phieu-nhap-thanh-pham-noi-bo', {
                method: 'POST',
                headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify({
                    receipt_date: document.getElementById('receiptDate').value,
                    ma_sp: document.getElementById('receiptMaSp').value,
                    ma_ko: document.getElementById('receiptMaKo').value,
                    ten_hh: document.getElementById('receiptTenHh').value,
                    dvt: document.getElementById('receiptDvt').value,
                    quantity: document.getElementById('receiptQuantity').value,
                    note: document.getElementById('receiptNote').value
                })
            }).then(response => {
                if (!response.ok) return response.json().then(result => { throw new Error(result.message || 'Không tạo được phiếu nhập'); });
                return response.json();
            }).then(result => {
                receiptModal.hide();
                window.open(result.print_url, '_blank');
            }).catch(error => alert(error.message));
        });

        document.getElementById('addDetailBtn').addEventListener('click', () => {
            const quantity = document.getElementById('newQuantity').value;
            if (!selectedRow || quantity === '') return;
            fetch('/api/doi-chieu-ton', {
                method: 'POST',
                headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify({
                    ma_sp: selectedRow.ma_sp, ma_ko: selectedRow.ma_ko,
                    internal_item_code: document.getElementById('newInternalItemCode').value,
                    size: document.getElementById('newSize').value, color: document.getElementById('newColor').value,
                    side: document.getElementById('newSide').value,
                    counted_quantity: quantity, note: document.getElementById('newNote').value, checked_at: checkedAtEl.value
                })
            }).then(response => { if (!response.ok) throw new Error('Không lưu được chi tiết'); return response.json(); })
              .then(() => {
                  ['newInternalItemCode','newSize','newColor','newSide','newQuantity','newNote'].forEach(id => document.getElementById(id).value = '');
                  loadData();
              }).catch(error => alert(error.message));
        });

        detailRowsEl.addEventListener('click', event => {
            const button = event.target.closest('.delete-detail-btn');
            if (!button || !confirm('Xóa dòng kiểm kê nội bộ này?')) return;
            fetch(`/api/doi-chieu-ton/${button.dataset.id}`, {
                method: 'DELETE', headers: {'Accept':'application/json','X-CSRF-TOKEN':csrfToken}
            }).then(response => { if (!response.ok) throw new Error('Không xóa được chi tiết'); return response.json(); })
              .then(loadData).catch(error => alert(error.message));
        });

        document.getElementById('exportBtn').addEventListener('click', () => {
            const summaryRows = rows.map(row => ({
                'Mã thành phẩm': row.ma_sp, 'Tên hàng': row.ten_hh, 'Kho': row.ma_ko, 'ĐVT': row.dvt,
                'Tổng nhập kế toán': row.tong_nhap, 'Tổng xuất kế toán': row.tong_xuat,
                'Tồn kế toán': row.source_quantity, 'Tổng nội bộ': row.counted_quantity, 'Chênh lệch': row.difference
            }));
            const detailRows = rows.flatMap(row => (row.details || []).map(detail => ({
                'Ngày kiểm kê': checkedAtEl.value, 'Mã thành phẩm': row.ma_sp, 'Tên hàng': row.ten_hh,
                'Kho': row.ma_ko, 'Mã hàng nội bộ': detail.internal_item_code, 'Size': detail.size, 'Màu': detail.color,
                'Side': detail.side, 'Số lượng nội bộ': detail.counted_quantity, 'Ghi chú': detail.note
            })));
            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, XLSX.utils.json_to_sheet(summaryRows), 'Tong doi chieu');
            XLSX.utils.book_append_sheet(workbook, XLSX.utils.json_to_sheet(detailRows), 'Chi tiet size mau');
            XLSX.writeFile(workbook, `doi-chieu-ton-${checkedAtEl.value}.xlsx`);
        });

        document.getElementById('reloadBtn').addEventListener('click', loadData);
        document.getElementById('missingReceiptBtn').addEventListener('click', function() {
            missingReceiptOnly = !missingReceiptOnly;
            this.classList.toggle('btn-warning', missingReceiptOnly);
            this.classList.toggle('btn-outline-warning', !missingReceiptOnly);
            renderRows();
        });
        checkedAtEl.addEventListener('change', loadData);
        keywordEl.addEventListener('input', renderRows);
        loadData();
        setInterval(() => {
            if (!document.hidden) loadData();
        }, 10000);
    </script>
</body>
</html>
