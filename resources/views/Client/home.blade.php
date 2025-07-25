<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Dashboard Đơn Sản Xuất</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <!-- DataTables Buttons CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

    <style>
        td,
        th {
            font-size: 13px;
            vertical-align: middle;
        }

        .text-danger {
            color: #dc3545;
            font-weight: bold;
        }

        .text-success {
            color: #28a745;
            font-weight: bold;
        }

        .text-warning {
            color: #ffc107;
            font-weight: bold;
        }

        .text-primary {
            color: #007bff;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="container mt-4">
        <h3 class="mb-4">📋 Theo dõi đơn sản xuất - Realtime</h3>

        <table class="table table-bordered table-hover" id="productionTable" style="width: 100%;">
            <thead class="table-dark">
                <tr>
                    <th>STT</th>
                    <th>SỐ ĐƠN HÀNG</th>
                    <th>MÃ LỆNH</th>
                    <th>TÊN PO</th>
                    <th>KHÁCH HÀNG</th>
                    <th>MÃ KINH DOANH</th>
                    <th>Mã HH</th>
                    <th>TÊN SP</th>
                    <th>SIZE</th>
                    <th>MÀU</th>
                    <th>SL ĐƠN HÀNG</th>
                    <th>SL CẦN SX</th>
                    <th>SẢN XUẤT</th>
                    <th>ĐVT</th>
                    <th>Ngày nhận</th>
                    <th>Ngày giao</th>
                    <th>Phân tích</th>
                    <th>Chuẩn bị</th>
                    <th>Nhập kho</th>
                    <th>Xuất kho</th>
                    <th>Tình trạng</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        let dataTable;

        function fetchData() {
            fetch("http://192.168.1.89:8080/api/production-orders")
                .then(res => res.json())
                .then(response => {
                    const {
                        data,
                        sumSoLuong,
                        cd1,
                        cd2,
                        cd3,
                        cd4,
                        nx,
                        xv,
                        nhapKho,
                        xuatKho
                    } = response;

                    const rows = data.map((row, index) => {
                        const key = `${row.So_ct}|${row.Ma_hh}`;
                        const cdSteps = [cd1, cd2, cd3, cd4];
                        let step = 0,
                            label = 'Chưa bắt đầu';
                        for (let i = 3; i >= 0; i--) {
                            if (cdSteps[i][key]) {
                                step = Math.round(cdSteps[i][key].total);
                                label = `CĐ${i + 1} - ${step}`;
                                break;
                            }
                        }

                        const nhap = nhapKho[key]?.total ?? 0;
                        const xuat = xuatKho[key]?.total ?? 0;

                        let statusLabel = '';
                        switch (true) {
                            case nhap >= row.Dgbannte && xuat == 0:
                                statusLabel = '<span class="text-primary">📦 Chưa xuất kho</span>';
                                break;
                            case xuat >= row.Dgbannte && row.Dgbannte > 0:
                                statusLabel = '<span class="text-success">✔️ Hoàn th2ành</span>';
                                break;
                            case nhap == 0:
                                statusLabel = '<span class="text-danger">⛔ Chưa nhập kho</span>';
                                break;
                            case nhap > 0 && nhap < row.Dgbannte:
                                statusLabel = '<span class="text-warning">📦 Chưa đủ số lượng</span>';
                        }

                        return [
                            index + 1,
                            row.So_hd,
                            row.So_ct,
                            row.So_dh,
                            row.khach_hang?.Ten_kh ?? '',
                            row.Soseri,
                            row.Ma_hh,
                            row.hang_hoa?.Ten_hh ?? '',
                            row.Msize,
                            row.Ma_ch,
                            Math.round(row.Dgbannte),
                            Math.round(sumSoLuong[row.So_ct]) ?? 0,
                            `<span class="text-primary">${label}</span>`,
                            row.hang_hoa?.Dvt ?? '',
                            new Date(row.Ngay_ct).toLocaleDateString(),
                            new Date(row.Date).toLocaleDateString(),
                            nx.includes(row.So_ct) ? '✅' : '❌',
                            xv.includes(row.So_ct) ? '✅' : '❌',
                            Math.round(nhap),
                            Math.round(xuat),
                            statusLabel
                        ];
                    });

                    if (!dataTable) {
                        dataTable = $('#productionTable').DataTable({
                            data: rows,
                            columns: [{
                                    title: "STT"
                                },
                                {
                                    title: "SỐ ĐƠN HÀNG"
                                },
                                {
                                    title: "MÃ LỆNH"
                                },
                                {
                                    title: "TÊN PO"
                                },
                                {
                                    title: "KHÁCH HÀNG"
                                },
                                {
                                    title: "MÃ KINH DOANH"
                                },
                                {
                                    title: "Mã HH"
                                },
                                {
                                    title: "TÊN SP"
                                },
                                {
                                    title: "SIZE"
                                },
                                {
                                    title: "MÀU"
                                },
                                {
                                    title: "SL ĐƠN HÀNG"
                                },
                                {
                                    title: "SL CẦN SX"
                                },
                                {
                                    title: "SẢN XUẤT"
                                },
                                {
                                    title: "ĐVT"
                                },
                                {
                                    title: "Ngày nhận"
                                },
                                {
                                    title: "Ngày giao"
                                },
                                {
                                    title: "Phân tích"
                                },
                                {
                                    title: "Chuẩn bị"
                                },
                                {
                                    title: "Nhập kho"
                                },
                                {
                                    title: "Xuất kho"
                                },
                                {
                                    title: "Tình trạng"
                                }
                            ],
                            pageLength: 25,
                            language: {
                                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/vi.json'
                            },
                            dom: 'Bfrtip', // 👈 thêm dòng này để hiển thị nút
                            buttons: [{
                                extend: 'excelHtml5',
                                text: '📤 Xuất Excel',
                                className: 'btn btn-success',
                                exportOptions: {
                                    columns: ':visible' // xuất tất cả cột đang hiển thị
                                },
                                title: 'Don_San_Xuat'
                            }]
                        });
                    } else {
                        dataTable.clear();
                        dataTable.rows.add(rows);
                        dataTable.draw(false); // Giữ lại trang và tìm kiếm hiện tại
                    }
                })
                .catch(err => {
                    console.error("Lỗi khi tải dữ liệu:", err);
                });
        }

        fetchData();
        setInterval(fetchData, 10000); // cập nhật mỗi 10 giây
    </script>
    <!-- Buttons + JSZip (Excel) -->
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>

</body>

</html>
