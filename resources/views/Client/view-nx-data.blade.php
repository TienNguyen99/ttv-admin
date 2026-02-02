<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xem Phân Tích Định Mức</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.1.1/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
        }

        .table-hover tbody tr:hover {
            background-color: #f5f5f5;
        }

        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        #nxDataTable_wrapper {
            overflow-x: auto;
        }
    </style>
</head>

<body>
    <div class="container-fluid mt-4">
        <div class="row mb-3">
            <div class="col-12">
                <h2 class="mb-4">
                    <i class="fas fa-chart-bar"></i> Phân Tích Định Mức (NX)
                </h2>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-table"></i> Danh Sách Phân Tích
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Loading indicator -->
                        <div id="loading" class="text-center" style="display: none;">
                            <div class="spinner-border" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                        </div>

                        <!-- DataTable -->
                        <div id="tableContainer" style="display: none;">
                            <table id="nxDataTable"
                                class="table table-striped table-bordered table-hover dt-responsive nowrap"
                                style="width: 100%">
                                <thead class="table-dark">
                                    <tr>
                                        <th>STT</th>
                                        {{-- <th>Ngày nhập phân tích</th> --}}
                                        <th>Mã lệnh phần mềm</th>
                                        <th>Mã Kinh doanh</th>
                                        <th>Số lượng đơn hàng</th>
                                        <th>Định mức</th>
                                        <th>Đơn vị</th>
                                        <th>Mã Hàng Hóa</th>
                                        <th>Tên Hàng Hóa</th>

                                        <th>Ghi Chú</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.3/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.3/vfs_fonts.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.1/js/buttons.print.min.js"></script>
    {{-- API HIỂN THỊ DATA --}}
    <script>
        $(document).ready(function() {
            loadNXData();

            function loadNXData() {
                $('#loading').show();
                $('#tableContainer').hide();

                $.ajax({
                    url: '/api/tivi/nx-data',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            displayData(response.data, response.summaryBySoDh);
                            $('#loading').hide();
                            $('#tableContainer').show();
                        } else {
                            showAlert('danger', 'Lỗi: ' + response.message);
                            $('#loading').hide();
                        }
                    },
                    error: function(xhr, status, error) {
                        showAlert('danger', 'Lỗi khi tải dữ liệu: ' + error);
                        $('#loading').hide();
                    }
                });
            }

            function displayData(data, summary) {
                // Xử lý dữ liệu thành rows
                const rows = data.map((row, index) => [
                    index + 1,
                    // row.Ngay_ct ? new Date(row.Ngay_ct).toLocaleDateString('vi-VN') : '-',
                    row.So_dh_go || '-',
                    row.Soseri || '-',
                    row.Soluong_go ? Number(row.Soluong_go).toFixed(0).toLocaleString('vi-VN') : '-',
                    row.Soluong ? Number(row.Soluong).toFixed(3).toLocaleString('vi-VN') : '-',
                    row.hang_hoa?.Dvt || '-',
                    row.Ma_hh || '-',
                    row.hang_hoa?.Ten_hh || '-',


                    row.Ghichu || '-'
                ]);

                const table = $('#nxDataTable').DataTable({
                    destroy: true,
                    data: rows,
                    pageLength: 25,
                    lengthMenu: [
                        [10, 25, 50, 100, -1],
                        [10, 25, 50, 100, "Tất cả"]
                    ],
                    ordering: true,
                    searching: true,
                    paging: true,
                    responsive: true,
                    language: {
                        "sEmptyTable": "Không có dữ liệu",
                        "sInfo": "Hiển thị _START_ đến _END_ trong _TOTAL_ bản ghi",
                        "sInfoEmpty": "Không có dữ liệu",
                        "sInfoFiltered": "(lọc từ _MAX_ bản ghi)",
                        "sLengthMenu": "Hiển thị _MENU_ bản ghi",
                        "sLoadingRecords": "Đang tải...",
                        "sProcessing": "Đang xử lý...",
                        "sSearch": "Tìm kiếm:",
                        "sZeroRecords": "Không tìm thấy",
                        "oPaginate": {
                            "sFirst": "Đầu",
                            "sLast": "Cuối",
                            "sNext": "Tiếp",
                            "sPrevious": "Trước"
                        }
                    },
                    dom: 'Bfrtip',
                    buttons: [{
                            extend: 'excel',
                            text: '<i class="fas fa-file-excel"></i> Excel',
                            className: 'btn btn-success btn-sm',
                            title: 'Phân Tích Định Mức'
                        },
                        {
                            extend: 'pdf',
                            text: '<i class="fas fa-file-pdf"></i> PDF',
                            className: 'btn btn-danger btn-sm',
                            title: 'Phân Tích Định Mức'
                        },
                        {
                            extend: 'print',
                            text: '<i class="fas fa-print"></i> In',
                            className: 'btn btn-info btn-sm',
                            title: 'Phân Tích Định Mức'
                        }
                    ]
                });
            }

            function showAlert(type, message) {
                const alertHTML = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
                $('#tableContainer').before(alertHTML);
            }
        });
    </script>
</body>

</html>
