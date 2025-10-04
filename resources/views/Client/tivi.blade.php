<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>THEO DÕI LỆNH SẢN XUẤT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        body {
            background: #f8f9fa;
        }

        .status-overdue {
            background-color: #f8d7da !important;
        }

        /* đỏ nhạt */
        .status-upcoming {
            background-color: #fff3cd !important;
        }

        /* vàng nhạt */
        img {
            border-radius: 6px;
        }
    </style>
</head>

<body>
    <div class="container-fluid mt-4">
        <h4 id="titleTable" class="fw-bold text-center mb-3 text-danger">❌ Đơn hàng Trễ trong 2 tuần</h4>
        <table class="table table-bordered table-hover" id="productionTable" style="width: 100%;">
            <thead class="table-dark">
                <tr>
                    <th>STT</th>
                    <th>Lệnh SX</th>
                    <th>Khách hàng</th>
                    <th>Mã hàng</th>
                    <th>Tên hàng</th>
                    <th>SL đơn</th>
                    <th>Nhập kho</th>
                    <th>Xuất kho</th>
                    <th>Ngày hẹn</th>
                    <th>Hình ảnh</th> <!-- thêm lại -->
                    <th>Tình trạng</th>
                    <th>Trạng thái</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <!-- jQuery + Bootstrap + DataTables -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        let dataTable;
        let currentMode = "overdue14"; // mặc định: trễ trong 2 tuần

        function loadTable(range) {
            return fetch(`http://192.168.1.13:8888/api/tivi?range=${range}`)
                .then(res => res.json())
                .then(response => {
                    const {
                        data,
                        nhapKho,
                        xuatkhotheomavvketoan
                    } = response;

                    const rows = data.map((row, index) => {
                        const key = `${row.So_ct}|${row.Ma_hh}`;
                        const keyketoan2 = `${row.So_dh}|${row.hang_hoa?.Ma_so}`;
                        const xuat = Math.round(xuatkhotheomavvketoan[keyketoan2]?.xuatkhotheomavv_ketoan ?? 0);
                        const nhap = Math.round(nhapKho[key]?.total_nhap ?? 0);

                        // Tình trạng
                        let statusLabel = '';
                        if (xuat >= row.Soluong) statusLabel = "✔️ Hoàn thành";
                        else if (xuat > 0 && xuat < row.Soluong) statusLabel = "📦 Xuất kho chưa đủ";
                        else if (nhap >= row.Soluong) statusLabel = "📦 Chưa xuất kho";
                        else if (nhap === 0) statusLabel = "⛔ Chưa nhập kho";
                        else if (nhap > 0 && nhap < row.Soluong) statusLabel = "📦 Chưa đủ số lượng";

                        // Hạn giao
                        let deadlineLabel = '';
                        if (row.Date) {
                            const today = new Date();
                            today.setHours(0, 0, 0, 0);
                            const deliveryDate = new Date(row.Date);
                            deliveryDate.setHours(0, 0, 0, 0);
                            const diffDays = Math.floor((deliveryDate - today) / (1000 * 60 * 60 * 24));
                            if (diffDays < 0) deadlineLabel = `❌ Quá hạn ${Math.abs(diffDays)} ngày`;
                            else if (diffDays <= 7) deadlineLabel = `⚠️ Còn ${diffDays} ngày`;
                        }

                        // Hình ảnh
                        const imageHtml = (row.hang_hoa?.Ma_so && row.hang_hoa?.Pngpath_fixed) ?
                            `<img src="http://192.168.1.13:8888/hinh_hh/HH_${row.hang_hoa.Ma_so}/${row.hang_hoa.Pngpath_fixed}" 
                       alt="Hình ảnh" style="max-width:80px;max-height:80px" 
                       onerror="this.style.display='none'">` :
                            '';

                        return [
                            index + 1,
                            row.So_dh,
                            row.khach_hang?.Ten_kh ?? '',
                            row.Ma_hh,
                            row.hang_hoa?.Ten_hh ?? '',
                            Math.round(row.Soluong),
                            nhap,
                            xuat,
                            row.Date ? new Date(row.Date).toLocaleDateString('vi-VN') : '',
                            imageHtml, // thêm cột hình
                            statusLabel,
                            deadlineLabel
                        ];
                    });

                    if (!dataTable) {
                        dataTable = $('#productionTable').DataTable({
                            data: rows,
                            pageLength: 15,
                            columnDefs: [{
                                    targets: 9,
                                    orderable: false
                                } // không sort cột hình ảnh
                            ]
                        });
                    } else {
                        dataTable.clear().rows.add(rows).draw(false);
                    }

                    // Cập nhật tiêu đề
                    if (range === "overdue14") {
                        document.getElementById("titleTable").innerHTML = "❌ Đơn hàng Trễ trong 2 tuần";
                        document.getElementById("titleTable").className = "fw-bold text-center mb-3 text-danger";
                    } else if (range === "7") {
                        document.getElementById("titleTable").innerHTML = "⚠️ Đơn hàng Sắp đến hạn (≤ 7 ngày)";
                        document.getElementById("titleTable").className = "fw-bold text-center mb-3 text-warning";
                    }
                });
        }

        // lần đầu load
        loadTable(currentMode);

        // auto refresh
        setInterval(() => loadTable(currentMode), 10000);

        // bắt phím xuống để đổi mode
        document.addEventListener("keydown", function(e) {
            if (e.key === "ArrowRight" || e.key === "ArrowLeft") {
                currentMode = (currentMode === "overdue14") ? "7" : "overdue14";
                loadTable(currentMode);
            }
        });
    </script>
</body>

</html>
