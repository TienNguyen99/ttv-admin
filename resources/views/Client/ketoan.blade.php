<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>THEO DÕI LỆNH SẢN XUẤT TAGTIME</title>
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
        <h3 class="mb-3">Bảng dữ liệu kế toán (rút gọn)</h3>

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
            <select id="filter-month" class="form-select">
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

        <table id="ketoan-table" class="table table-bordered table-striped">
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
            <tbody id="ketoan-table-body">
                <tr>
                    <td colspan="6">Đang tải...</td>
                </tr>
            </tbody>
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

        function loadData() {
            fetch("http://192.168.1.13:8888/api/ketoan-today")
                .then(response => response.json())
                .then(result => {
                    const tbody = document.getElementById("ketoan-table-body");
                    tbody.innerHTML = "";

                    if (!result.data || result.data.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="6">Không có dữ liệu</td></tr>`;
                        return;
                    }

                    result.data.forEach((row, index) => {
                        tbody.innerHTML += `
                            <tr>
                                <td>${index + 1}</td>
                                <td>${new Date(row.Ngay_ct).toLocaleDateString("vi-VN")}</td>
                                <td>${row.Namekh ?? ''}</td>
                                <td>${row.So_hd ?? ''}</td>
                                <td>${row.Ma_hh ?? ''}</td>
                                <td>${row.Ten_hh ?? ''}</td>
                                <td>${Math.round(row.Soluong ?? 0)}</td>
                                <td>${row.Dvt ?? ''}</td>
                                <td>${row.DgiaiV ?? ''}</td>  
                                <td>${row.DgiaiE ?? ''}</td>
                                <td>${row.Ma_vv ?? ''}</td>
                                <td>${row.Ghichu ?? ''}</td>
                                <td>${Math.round(row.Dgbanvnd ?? 0)}</td>
                                <td>${Math.round(row.Tien_vnd ?? 0)}</td>
                            </tr>
                        `;
                    });

                    if (!dataTable) {
                        // Khởi tạo DataTable lần đầu
                        dataTable = $('#ketoan-table').DataTable({
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
                                dataSrc: 1 // group theo Ngay_ct
                            },
                            footerCallback: function(row, data, start, end, display) {
                                let api = this.api();
                                let selectedMonth = parseInt($('#filter-month').val());
                                let total = 0;

                                api.rows({ search: 'applied' }).every(function() {
                                    let rowData = this.data();
                                    let ngay = rowData[1]; // dd/mm/yyyy
                                    let tien = parseFloat(rowData[13]) || 0;

                                    let parts = ngay.split('/');
                                    let thang = parseInt(parts[1]);

                                    if (!selectedMonth || thang === selectedMonth) {
                                        total += tien;
                                    }
                                });

                                document.getElementById('total-thanh-tien').innerText =
                                    total.toLocaleString('vi-VN');
                            }
                        });

                        // Filter theo So_hd + Khách hàng + Tháng
                        $('#filter-sohd, #filter-kh, #filter-month').on('keyup change', function() {
                            dataTable.column(3).search($('#filter-sohd').val());
                            dataTable.column(2).search($('#filter-kh').val()).draw();
                        });

                    } else {
                        // Cập nhật lại dữ liệu
                        dataTable.clear();
                        $("#ketoan-table tbody tr").each(function() {
                            dataTable.row.add($(this));
                        });
                        dataTable.draw(false);
                    }
                })
                .catch(error => console.error("Lỗi load dữ liệu:", error));
        }

        // Load lần đầu
        loadData();

        // Reload mỗi 10 giây
        setInterval(loadData, 10000);
    </script>
</body>

</html>
