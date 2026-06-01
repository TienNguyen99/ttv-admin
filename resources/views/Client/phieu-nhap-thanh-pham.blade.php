<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Phiếu nhập thành phẩm</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    <style>
        body { background: #f6f7f9; }
        .panel {
            background: #fff;
            border: 1px solid #e6e8ec;
            border-radius: 8px;
            padding: 16px;
        }
        .print-sheet {
            width: 210mm;
            min-height: 148mm;
            margin: 0 auto;
            background: #fff;
            color: #111;
            padding: 16mm;
            font-size: 13px;
        }
        .print-title {
            font-size: 22px;
            font-weight: 700;
            text-align: center;
            text-transform: uppercase;
            margin: 12px 0 4px;
        }
        .print-subtitle { text-align: center; margin-bottom: 18px; }
        .print-table th,
        .print-table td {
            border: 1px solid #111 !important;
            padding: 6px 8px !important;
            vertical-align: middle;
        }
        .signature-cell {
            height: 90px;
            vertical-align: top;
            text-align: center;
            font-weight: 600;
        }
        @media print {
            body * { visibility: hidden; }
            #printArea, #printArea * { visibility: visible; }
            #printArea {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .modal, .modal-dialog, .modal-content, .modal-body {
                position: static !important;
                display: block !important;
                width: auto !important;
                max-width: none !important;
                margin: 0 !important;
                border: 0 !important;
                box-shadow: none !important;
            }
            .modal-header, .modal-footer { display: none !important; }
            .print-sheet { width: auto; min-height: auto; padding: 12mm; }
        }
    </style>
</head>

<body>
    @include('layouts.partials.sidebar')
    <div class="container-fluid py-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <h3 class="mb-1">Phiếu nhập thành phẩm</h3>
                <div class="text-muted">Dữ liệu lấy từ bảng Kế toán, Ma_ct = NX</div>
            </div>
            <button id="reloadBtn" type="button" class="btn btn-primary">Tải lại dữ liệu</button>
        </div>

        <div class="panel mb-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="fromDate" class="form-label">Từ ngày</label>
                    <input type="date" id="fromDate" class="form-control">
                </div>
                <div class="col-md-3">
                    <label for="toDate" class="form-label">Đến ngày</label>
                    <input type="date" id="toDate" class="form-control">
                </div>
                <div class="col-md-4">
                    <label for="keyword" class="form-label">Tìm số phiếu, lệnh, mã TP, mã kho</label>
                    <input type="text" id="keyword" class="form-control" placeholder="Nhập So_ct, Ma_vv, Ma_sp hoặc Ma_ko">
                </div>
                <div class="col-md-2">
                    <button id="clearFilterBtn" type="button" class="btn btn-outline-secondary w-100">Xóa lọc</button>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <div class="fw-semibold" id="summaryText">0 dòng nhập thành phẩm</div>
            </div>
            <table id="phieu-table" class="table table-bordered table-striped table-hover align-middle" style="width:100%">
                <thead class="table-light">
                    <tr>
                        <th>STT</th>
                        <th>Ngày</th>
                        <th>Số phiếu</th>
                        <th>Chứng từ</th>
                        <th>Lệnh/Vụ việc</th>
                        <th>Mã kho</th>
                        <th>Mã TP</th>
                        <th>Tên hàng</th>
                        <th>ĐVT</th>
                        <th class="text-end">SL nhập</th>
                        <th>Diễn giải</th>
                        <th>Layout</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="printModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Layout phiếu nhập thành phẩm</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light">
                    <div id="printArea" class="print-sheet"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button type="button" class="btn btn-primary" onclick="window.print()">In phiếu</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>

    <script>
        let dataTable;
        let rowsByIndex = [];

        function formatNumber(value) {
            return Number(value || 0).toLocaleString('vi-VN', { maximumFractionDigits: 2 });
        }

        function formatDate(value) {
            return value ? new Date(value).toLocaleDateString('vi-VN') : '';
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, function(char) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                }[char];
            });
        }

        function buildApiUrl() {
            const params = new URLSearchParams();
            const fromDate = $('#fromDate').val();
            const toDate = $('#toDate').val();
            const keyword = $('#keyword').val().trim();

            if (fromDate) params.set('from_date', fromDate);
            if (toDate) params.set('to_date', toDate);
            if (keyword) params.set('keyword', keyword);

            const query = params.toString();
            return query ? `/api/phieu-nhap-thanh-pham?${query}` : '/api/phieu-nhap-thanh-pham';
        }

        function buildRows(data) {
            rowsByIndex = data;
            return data.map((row, index) => [
                index + 1,
                formatDate(row.Ngay_ct),
                row.So_ct || '',
                row.Chungtu || '',
                row.Ma_vv || '',
                row.Ma_ko || '',
                row.Ma_sp || '',
                row.Ten_hh || '',
                row.Dvt || '',
                Number(row.Noluong || 0),
                row.DgiaiV || '',
                `<button type="button" class="btn btn-sm btn-outline-primary show-layout" data-index="${index}">Xem phiếu</button>`
            ]);
        }

        function loadData() {
            $('#reloadBtn').prop('disabled', true).text('Đang tải...');

            fetch(buildApiUrl())
                .then(response => response.json())
                .then(result => {
                    const data = result.data || [];
                    $('#summaryText').text(`${formatNumber(result.summary?.total_rows || 0)} dòng, tổng nhập ${formatNumber(result.summary?.total_noluong || 0)}`);
                    const tableRows = buildRows(data);

                    if (!dataTable) {
                        dataTable = $('#phieu-table').DataTable({
                            data: tableRows,
                            pageLength: 50,
                            order: [[1, 'desc']],
                            dom: 'Bfrtip',
                            buttons: [{
                                extend: 'excelHtml5',
                                text: 'Xuất Excel',
                                className: 'btn btn-success btn-sm',
                                title: 'phieu-nhap-thanh-pham'
                            }],
                            columnDefs: [{
                                targets: [9],
                                className: 'text-end',
                                render: function(data, type) {
                                    if (type === 'sort' || type === 'type') return Number(data || 0);
                                    return formatNumber(data);
                                }
                            }],
                            language: {
                                emptyTable: 'Không có dữ liệu',
                                search: 'Tìm:',
                                info: 'Hiển thị _START_ đến _END_ trong _TOTAL_ dòng',
                                paginate: {
                                    first: 'Đầu',
                                    last: 'Cuối',
                                    next: 'Sau',
                                    previous: 'Trước'
                                }
                            }
                        });
                    } else {
                        dataTable.clear();
                        dataTable.rows.add(tableRows);
                        dataTable.draw(false);
                    }
                })
                .catch(error => {
                    console.error('Lỗi tải phiếu nhập thành phẩm:', error);
                    if (dataTable) dataTable.clear().draw();
                    alert('Không tải được dữ liệu phiếu nhập thành phẩm. Kiểm tra kết nối database hoặc log Laravel.');
                })
                .finally(() => {
                    $('#reloadBtn').prop('disabled', false).text('Tải lại dữ liệu');
                });
        }

        function renderPrintLayout(row) {
            const details = rowsByIndex.filter(item => {
                if (row.So_ct) return item.So_ct === row.So_ct;
                if (row.Chungtu) return item.Chungtu === row.Chungtu;
                return item.SttRecN === row.SttRecN;
            });
            const ngay = formatDate(row.Ngay_ct);
            const soPhieu = escapeHtml(row.So_ct || row.Chungtu || '');
            const maVv = escapeHtml(row.Ma_vv || '');
            const maKho = escapeHtml(row.Ma_ko || '');
            const ma3Kho = escapeHtml(row.Ma3ko || '');
            const dienGiai = escapeHtml(row.DgiaiV || row.Ghichu || '');
            const totalNoluong = details.reduce((total, item) => total + Number(item.Noluong || 0), 0);
            const detailRows = details.map((item, index) => `
                <tr>
                    <td class="text-center">${index + 1}</td>
                    <td class="text-center">${escapeHtml(item.Ma_ko || '')}</td>
                    <td>${escapeHtml(item.Ma_sp || '')}</td>
                    <td>${escapeHtml(item.Ten_hh || '')}</td>
                    <td class="text-center">${escapeHtml(item.Dvt || '')}</td>
                    <td class="text-end">${formatNumber(item.Noluong || 0)}</td>
                    <td>${escapeHtml(item.DgiaiV || item.Ghichu || '')}</td>
                </tr>
            `).join('');

            $('#printArea').html(`
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="fw-bold">CÔNG TY</div>
                        <div>Bộ phận: Kho thành phẩm</div>
                    </div>
                    <div class="text-end">
                        <div>Mẫu in nội bộ</div>
                        <div>Số phiếu: <strong>${soPhieu}</strong></div>
                    </div>
                </div>

                <div class="print-title">Phiếu nhập thành phẩm</div>
                <div class="print-subtitle">Ngày ${ngay}</div>

                <div class="row mb-3">
                    <div class="col-6"><strong>Lệnh/Vụ việc:</strong> ${maVv}</div>
                    <div class="col-3"><strong>Mã kho:</strong> ${maKho}</div>
                    <div class="col-3"><strong>Kho 3:</strong> ${ma3Kho}</div>
                    <div class="col-12 mt-2"><strong>Diễn giải:</strong> ${dienGiai}</div>
                </div>

                <table class="table print-table mb-4">
                    <thead>
                        <tr class="text-center">
                            <th style="width: 45px;">STT</th>
                            <th style="width: 80px;">Mã kho</th>
                            <th>Mã thành phẩm</th>
                            <th>Tên thành phẩm</th>
                            <th style="width: 80px;">ĐVT</th>
                            <th style="width: 120px;">Số lượng</th>
                            <th>Ghi chú</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${detailRows}
                        <tr>
                            <td colspan="5" class="text-end fw-bold">Tổng cộng</td>
                            <td class="text-end fw-bold">${formatNumber(totalNoluong)}</td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>

                <table class="table print-table">
                    <tr>
                        <td class="signature-cell">Người lập phiếu<br><span class="fw-normal">(Ký, ghi rõ họ tên)</span></td>
                        <td class="signature-cell">Người giao hàng<br><span class="fw-normal">(Ký, ghi rõ họ tên)</span></td>
                        <td class="signature-cell">Thủ kho<br><span class="fw-normal">(Ký, ghi rõ họ tên)</span></td>
                        <td class="signature-cell">Kế toán<br><span class="fw-normal">(Ký, ghi rõ họ tên)</span></td>
                    </tr>
                </table>
            `);
        }

        $(document).on('click', '.show-layout', function() {
            const row = rowsByIndex[Number(this.dataset.index)];
            if (!row) return;

            renderPrintLayout(row);
            new bootstrap.Modal('#printModal').show();
        });

        $('#reloadBtn').on('click', loadData);
        $('#fromDate, #toDate').on('change', loadData);
        $('#keyword').on('keyup', function(event) {
            if (event.key === 'Enter') loadData();
        });
        $('#clearFilterBtn').on('click', function() {
            $('#fromDate').val('');
            $('#toDate').val('');
            $('#keyword').val('');
            loadData();
        });
        loadData();
    </script>
</body>

</html>
