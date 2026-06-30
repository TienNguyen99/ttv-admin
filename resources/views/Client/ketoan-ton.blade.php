<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kiểm tra tồn mã hàng Kế toán</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    <style>
        body { background: #f6f7f9; }
        .summary-card {
            background: #fff;
            border: 1px solid #e6e8ec;
            border-radius: 8px;
            padding: 14px 16px;
            height: 100%;
        }
        .summary-label { color: #6c757d; font-size: 0.875rem; }
        .summary-value { font-size: 1.4rem; font-weight: 700; }
        .table-wrap {
            background: #fff;
            border: 1px solid #e6e8ec;
            border-radius: 8px;
            padding: 16px;
        }
    </style>
</head>

<body>
    @include('layouts.partials.sidebar')
    <div class="container-fluid py-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <h3 class="mb-1">Kiểm tra tồn mã hàng bảng Kế toán</h3>
                <div class="text-muted">Tồn = tổng nhập NX theo Ma_sp - tổng xuất XU theo Ma_hh</div>
            </div>
            <button id="reloadBtn" type="button" class="btn btn-primary">Tải lại dữ liệu</button>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-6 col-md-2">
                <div class="summary-card">
                    <div class="summary-label">Tổng mã</div>
                    <div class="summary-value" id="totalItems">0</div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="summary-card">
                    <div class="summary-label">Có tồn</div>
                    <div class="summary-value text-success" id="positiveItems">0</div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="summary-card">
                    <div class="summary-label">Hết tồn</div>
                    <div class="summary-value" id="zeroItems">0</div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="summary-card">
                    <div class="summary-label">�m tn</div>
                    <div class="summary-value text-danger" id="negativeItems">0</div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="summary-card">
                    <div class="summary-label">Tổng nhập</div>
                    <div class="summary-value" id="totalNhap">0</div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="summary-card">
                    <div class="summary-label">Tổng tồn</div>
                    <div class="summary-value" id="totalTon">0</div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <label for="fromDate" class="form-label">Từ ngày</label>
                <input type="date" id="fromDate" class="form-control">
            </div>
            <div class="col-md-3">
                <label for="toDate" class="form-label">Đến ngày</label>
                <input type="date" id="toDate" class="form-control">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button id="clearDateBtn" type="button" class="btn btn-outline-secondary w-100">Tất cả ngày</button>
            </div>
            <div class="col-md-3">
                <label for="filterStatus" class="form-label">Trạng thái tồn</label>
                <select id="filterStatus" class="form-select">
                    <option value="">Tất cả</option>
                    <option value="positive">Có tồn</option>
                    <option value="zero">Hết tồn</option>
                    <option value="negative">�m tn</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="filterMaHH" class="form-label">Mã hàng</label>
                <input type="text" id="filterMaHH" class="form-control" placeholder="Nhập mã hàng cần kiểm tra">
            </div>
        </div>

        <div class="table-wrap">
            <table id="ton-table" class="table table-bordered table-striped table-hover align-middle" style="width:100%">
                <thead class="table-light">
                    <tr>
                        <th>STT</th>
                        <th>Mã hàng</th>
                        <th>Tên hàng</th>
                        <th>ĐVT</th>
                        <th class="text-end">Tổng nhập</th>
                        <th class="text-end">Tổng xuất</th>
                        <th class="text-end">Tồn</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody></tbody>
                <tfoot>
                    <tr>
                        <th colspan="4" class="text-end">Tổng đang lọc:</th>
                        <th class="text-end" id="footerNhap">0</th>
                        <th class="text-end" id="footerXuat">0</th>
                        <th class="text-end" id="footerTon">0</th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
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
        let statusFilter = '';

        function formatNumber(value) {
            return Number(value || 0).toLocaleString('vi-VN', { maximumFractionDigits: 2 });
        }

        function rawNumber(value) {
            if (typeof value === 'number') return value;
            return Number(String(value || '0').replace(/\./g, '').replace(',', '.')) || 0;
        }

        function statusLabel(ton) {
            if (ton > 0) return '<span class="badge text-bg-success">Có tồn</span>';
            if (ton < 0) return '<span class="badge text-bg-danger">�m tn</span>';
            return '<span class="badge text-bg-secondary">Hết tồn</span>';
        }

        $.fn.dataTable.ext.search.push(function(settings, dataRow) {
            if (!settings || !settings.nTable || settings.nTable.id !== 'ton-table') return true;
            if (!statusFilter) return true;

            const ton = rawNumber(dataRow[6]);
            if (statusFilter === 'positive') return ton > 0;
            if (statusFilter === 'negative') return ton < 0;
            if (statusFilter === 'zero') return ton === 0;
            return true;
        });

        function buildApiUrl() {
            const params = new URLSearchParams();
            const fromDate = $('#fromDate').val();
            const toDate = $('#toDate').val();

            if (fromDate) params.set('from_date', fromDate);
            if (toDate) params.set('to_date', toDate);

            const query = params.toString();
            return query ? `/api/ketoan-ton?${query}` : '/api/ketoan-ton';
        }

        function updateSummary(summary) {
            $('#totalItems').text(formatNumber(summary.total_items));
            $('#positiveItems').text(formatNumber(summary.positive_items));
            $('#zeroItems').text(formatNumber(summary.zero_items));
            $('#negativeItems').text(formatNumber(summary.negative_items));
            $('#totalNhap').text(formatNumber(summary.total_nhap));
            $('#totalTon').text(formatNumber(summary.total_ton));
        }

        function buildRows(data) {
            return data.map((row, index) => {
                const tongNhap = Number(row.tong_nhap || 0);
                const tongXuat = Number(row.tong_xuat || 0);
                const tonKho = Number(row.ton_kho || 0);

                return [
                    index + 1,
                    row.Ma_hh || '',
                    row.Ten_hh || '',
                    row.Dvt || '',
                    tongNhap,
                    tongXuat,
                    tonKho,
                    statusLabel(tonKho)
                ];
            });
        }

        function loadData() {
            $('#reloadBtn').prop('disabled', true).text('Đang tải...');

            fetch(buildApiUrl())
                .then(response => response.json())
                .then(result => {
                    updateSummary(result.summary || {});
                    const rows = buildRows(result.data || []);

                    if (!dataTable) {
                        dataTable = $('#ton-table').DataTable({
                            data: rows,
                            pageLength: 50,
                            order: [[6, 'desc']],
                            dom: 'Bfrtip',
                            buttons: [{
                                extend: 'excelHtml5',
                                text: 'Xuất Excel',
                                className: 'btn btn-success btn-sm',
                                title: 'kiem-tra-ton-ketoan'
                            }],
                            columnDefs: [{
                                targets: [4, 5, 6],
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
                            },
                            footerCallback: function() {
                                const api = this.api();
                                const sumColumn = function(index) {
                                    let total = 0;
                                    api.column(index, { search: 'applied' }).data().each(function(value) {
                                        total += Number(value || 0);
                                    });
                                    return total;
                                };

                                $('#footerNhap').text(formatNumber(sumColumn(4)));
                                $('#footerXuat').text(formatNumber(sumColumn(5)));
                                $('#footerTon').text(formatNumber(sumColumn(6)));
                            }
                        });

                        $('#filterStatus').on('change', function() {
                            statusFilter = this.value;
                            dataTable.draw();
                        });

                        $('#filterMaHH').on('keyup change', function() {
                            dataTable.column(1).search(this.value || '').draw();
                        });
                    } else {
                        dataTable.clear();
                        dataTable.rows.add(rows);
                        dataTable.draw(false);
                    }
                })
                .catch(error => {
                    console.error('Lỗi tải tồn kế toán:', error);
                    if (dataTable) dataTable.clear().draw();
                    alert('Không tải được dữ liệu tồn kế toán. Kiểm tra lại kết nối database hoặc log Laravel.');
                })
                .finally(() => {
                    $('#reloadBtn').prop('disabled', false).text('Tải lại dữ liệu');
                });
        }

        $('#reloadBtn').on('click', loadData);
        $('#fromDate, #toDate').on('change', loadData);
        $('#clearDateBtn').on('click', function() {
            $('#fromDate').val('');
            $('#toDate').val('');
            loadData();
        });
        loadData();
    </script>
</body>

</html>
