<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>THEO DÕI LỆNH SẢN XUẤT TAGTIME - CẢI TIẾN</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <!-- DataTables Buttons CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    <!-- RowGroup CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/rowgroup/1.4.1/css/rowGroup.bootstrap5.min.css">
</head>

<body>
    <div class="container-fluid mt-4">
        <h3 class="mb-3">Bảng dữ liệu kế toán (rút gọn) - Phiên bản tối ưu</h3>

        <!-- Filter So_hd -->
        <div class="mb-3">
            <label for="filter-sohd" class="form-label">Lọc theo Hóa đơn (So_hd):</label>
            <input type="text" id="filter-sohd" class="form-control" placeholder="Nhập số hóa đơn cần tìm...">
        </div>

        <!-- Filter Khách hàng -->
        <div class="mb-3">
            <label for="filter-kh" class="form-label">Lọc theo Khách hàng:</label>
            <input type="text" id="filter-kh" class="form-control" placeholder="Nhập tên khách hàng cần tìm...">
        </div>

        <!-- Filter Tháng -->
        <div class="mb-3">
            <label for="filter-month" class="form-label">Chọn Tháng:</label>
            <select id="filter-month" class="form-select" style="width:160px;">
                <option value="">-- Tất cả --</option>
                <option value="1">Tháng 1</option>
                <option value="2">Tháng 2</option>
                <option value="3">Tháng 3</option>
                <option value="4">Tháng 4</option>
                <option value="5">Tháng 5</option>
                <option value="6">Tháng 6</option>
                <option value="7">Tháng 7</option>
                <option value="8">Tháng 8</option>
                <option value="9" selected>Tháng 9</option>
                <option value="10">Tháng 10</option>
                <option value="11">Tháng 11</option>
                <option value="12">Tháng 12</option>
            </select>
        </div>

        <table id="ketoan-table" class="table table-bordered table-striped" style="width:100%">
            <thead class="table-light">
                <tr>
                    <th>STT</th>
                    <th>Ngày chứng từ</th>
                    <th>Khách hàng</th>
                    <th>Hóa đơn</th>
                    <th>Mã hàng hóa</th>
                    <th>Tên hàng hóa</th>
                    <th>Số lượng</th>
                    <th>Đơn vị tính</th>
                    <th>Diễn giải</th>
                    <th>Loại hàng</th>
                    <th>Lệnh sản xuất ( Vụ việc )</th>
                    <th>Ghi chú</th>
                    <th>Đơn giá bán</th>
                    <th>Thành tiền</th>
                </tr>
            </thead>
            <tbody></tbody>
            <tfoot>
                <tr>
                    <th colspan="13" class="text-end">Tổng Thành tiền:</th>
                    <th id="total-thanh-tien">0</th>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <!-- Buttons + JSZip (Excel) -->
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <!-- RowGroup JS -->
    <script src="https://cdn.datatables.net/rowgroup/1.4.1/js/dataTables.rowGroup.min.js"></script>

    <script>
        let dataTable;

        // Custom search: lọc theo tháng (dựa trên cột Ngày chứng từ - cột index = 1)
        $.fn.dataTable.ext.search.push(function(settings, dataRow) {
            // only apply to our table
            if (!settings || !settings.nTable || settings.nTable.id !== 'ketoan-table') return true;

            const selectedMonth = parseInt($('#filter-month').val());
            if (!selectedMonth) return true; // chọn tất cả

            const ngay = dataRow[1] || ''; // định dạng dd/mm/yyyy
            const parts = ngay.split('/');
            const thang = parts.length >= 2 ? parseInt(parts[1]) : NaN;
            return thang === selectedMonth;
        });

        function loadData() {
            fetch("http://192.168.1.13:8888/api/ketoan-today")
                .then(response => response.json())
                .then(result => {
                    const raw = result.data || [];

                    // chuyển thành mảng rows (array of arrays)
                    const rows = raw.map((row, idx) => {
                        const stt = idx + 1;
                        const ngay = row.Ngay_ct ? new Date(row.Ngay_ct).toLocaleDateString("vi-VN") : '';
                        const ten_kh = row.Ten_kh ?? '';
                        const so_hd = row.So_hd ?? '';
                        const ma_hh = row.Ma_hh ?? '';
                        const ten_hh = row.Ten_hh ?? '';
                        const soluong = Math.round(row.Soluong ?? 0);
                        const dvt = row.Dvt ?? '';
                        const dgiaiV = row.DgiaiV ?? '';
                        const dgiaiE = row.DgiaiE ?? '';
                        const ma_vv = row.Ma_vv ?? '';
                        const ghichu = row.Ghichu ?? '';
                        const dgbanvnd = Math.round(row.Dgbanvnd ?? 0);
                        const tien_vnd = Math.round(row.Tien_vnd ?? 0);

                        return [
                            stt,
                            ngay,
                            ten_kh,
                            so_hd,
                            ma_hh,
                            ten_hh,
                            soluong,
                            dvt,
                            dgiaiV,
                            dgiaiE,
                            ma_vv,
                            ghichu,
                            dgbanvnd,
                            tien_vnd
                        ];
                    });

                    if (!dataTable) {
                        // Khởi tạo DataTable lần đầu với data
                        dataTable = $('#ketoan-table').DataTable({
                            data: rows,
                            paging: true,
                            searching: true,
                            ordering: true,
                            info: true,
                            responsive: true,
                            dom: 'Bfrtip',
                            buttons: [{
                                extend: 'excelHtml5',
                                text: 'Xuất Excel',
                                className: 'btn btn-success btn-sm'
                            }],
                            rowGroup: {
                                dataSrc: 1 // group theo Ngay_ct (cột index 1)
                            },
                            language: {
                                emptyTable: "Không có dữ liệu",
                                search: "Tìm:",
                                lengthMenu: "Hiển thị _MENU_ dòng"
                            },
                            footerCallback: function(row, data, start, end, display) {
                                const api = this.api();
                                let total = 0;
                                // duyệt các hàng đang hiển thị (đã qua filter/search)
                                api.rows({
                                    search: 'applied'
                                }).data().each(function(rowData) {
                                    const ngay = rowData[1] || '';
                                    const tien = parseFloat(rowData[13]) || 0;
                                    // nếu có filter tháng, ext.search đã lọc rồi, nhưng để an toàn: nếu muốn tính theo tháng đã chọn, có thể kiểm tra thêm
                                    total += tien;
                                });
                                document.getElementById('total-thanh-tien').innerText = total
                                    .toLocaleString('vi-VN');
                            }
                        });

                        // Filter theo So_hd + Khách hàng
                        $('#filter-sohd').on('keyup change', function() {
                            dataTable.column(3).search(this.value || '').draw();
                        });
                        $('#filter-kh').on('keyup change', function() {
                            dataTable.column(2).search(this.value || '').draw();
                        });

                        // Khi đổi tháng -> redraw (ext.search sẽ apply)
                        $('#filter-month').on('change', function() {
                            dataTable.draw();
                        });

                    } else {
                        // Cập nhật lại dữ liệu hiệu quả (không thao tác innerHTML)
                        dataTable.clear();
                        dataTable.rows.add(rows);
                        // giữ nguyên search/filter hiện có -> chỉ vẽ lại
                        dataTable.draw(false);
                    }
                })
                .catch(error => {
                    console.error("Lỗi load dữ liệu:", error);
                    // nếu lỗi: nếu DataTable tồn tại thì clear; DataTables sẽ hiển thị thông báo rỗng
                    if (dataTable) {
                        dataTable.clear().draw();
                    }
                });
        }

        // Load lần đầu
        loadData();

        // Reload mỗi 10 giây
        setInterval(loadData, 10000);
    </script>
</body>

</html>
