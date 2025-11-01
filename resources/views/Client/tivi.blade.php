<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>THEO DÕI LỆNH SẢN XUẤT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        h4#titleTable {
            padding: 10px 0;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            display: inline-block;
            width: 100%;
        }

        .mode-buttons {
            text-align: center;
            margin-bottom: 20px;
        }

        .mode-buttons button {
            margin: 0 8px;
            padding: 10px 18px;
            font-size: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .mode-buttons button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        table.dataTable thead {
            background-color: #343a40 !important;
            color: #fff;
            font-weight: bold;
        }

        table.dataTable tbody tr {
            background-color: #fff;
            transition: background-color 0.2s;
        }

        table.dataTable tbody tr:hover {
            background-color: #f1f3f5;
        }

        table.dataTable td,
        table.dataTable th {
            vertical-align: middle;
            text-align: center;
        }

        .clickable-image {
            border-radius: 6px;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
            max-width: 80px;
            max-height: 80px;
        }

        .clickable-image:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.25);
        }

        /* Modal ảnh */
        .modal-content {
            background-color: rgba(0, 0, 0, 0.95);
            border: none;
        }

        .text-danger {
            font-weight: bold;
        }

        .text-warning {
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="container-fluid mt-3">
        <div class="mode-buttons">
            <button id="btnOverdue" class="btn btn-danger fw-bold">❌ Trễ trong 2 tuần</button>
            <button id="btnUpcoming" class="btn btn-warning fw-bold">⚠️ Sắp đến hạn (≤ 7 ngày)</button>
        </div>

        <h4 id="titleTable" class="fw-bold text-center mb-3 text-danger">❌ Đơn hàng Trễ trong 2 tuần</h4>

        <table class="table table-bordered table-hover" id="productionTable" style="width: 100%;">
            <thead class="table-dark">
                <tr>
                    <th>STT</th>
                    <th>Lệnh SX</th>
                    <th>Khách hàng</th>
                    <th>Mã hàng</th>
                    <th>Mã kinh doanh</th>
                    <th>Tên hàng</th>
                    <th>SL đơn</th>
                    <th>Nhập kho</th>
                    <th>Xuất kho</th>
                    <th>Ngày hẹn</th>
                    <th>Hình ảnh</th>
                    <th>Tình trạng</th>
                    <th>Trạng thái</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <!-- 🔹 Modal xem ảnh to -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-body text-center">
                    <img id="modalImage" src="" class="img-fluid rounded" alt="Ảnh sản phẩm">
                </div>
            </div>
        </div>
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
            return fetch(`/api/tivi?range=${range}`)
                .then(res => res.json())
                .then(response => {
                    const {
                        data,
                        nhapKho,
                        xuatkhotheomavvketoan,
                        nhaptpketoan
                    } = response;

                    const rows = data.map((row, index) => {
                        const key = `${row.So_ct}|${row.Ma_hh}`;
                        const keyketoan2 = `${row.So_dh}|${row.hang_hoa?.Ma_so}`;
                        const xuat = Math.round(xuatkhotheomavvketoan[keyketoan2]?.xuatkhotheomavv_ketoan ?? 0);
                        // const nhap = Math.round(nhapKho[key]?.total_nhap ?? 0);
                        const nhap = Math.round(nhaptpketoan[keyketoan2]?.total_nhaptpketoan ?? 0);

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


                        const imageHtml = `
    <img src="/hinh_hh/HH_${row.hang_hoa?.Ma_so}/${row.hang_hoa?.Pngpath}"
         alt="${row.hang_hoa?.Ten_hh}" 
         class="clickable-image"
         onerror="this.src='https://upload.wikimedia.org/wikipedia/commons/a/ac/No_image_available.svg'">
`;


                        return [
                            index + 1,
                            row.So_dh,
                            row.khach_hang?.Ten_kh ?? '',
                            row.Ma_hh,
                            row.Soseri ?? '',
                            row.hang_hoa?.Ten_hh ?? '',
                            Math.round(row.Soluong),
                            nhap,
                            xuat,
                            row.Date ? new Date(row.Date).toLocaleDateString('vi-VN') : '',
                            imageHtml,
                            statusLabel,
                            deadlineLabel
                        ];
                    });

                    if (!dataTable) {
                        dataTable = $('#productionTable').DataTable({
                            data: rows,
                            pageLength: 10,
                            responsive: true,
                            columnDefs: [{
                                targets: 10,
                                orderable: false
                            }],
                        });
                    } else {
                        dataTable.clear().rows.add(rows).draw(false);
                    }

                    // Khi click ảnh → mở modal
                    $('#productionTable').off('click', '.clickable-image').on('click', '.clickable-image', function() {
                        const src = $(this).attr('src');
                        $('#modalImage').attr('src', src);
                        const modal = new bootstrap.Modal(document.getElementById('imageModal'));
                        modal.show();
                    });

                    // Cập nhật tiêu đề
                    if (range === "overdue14") {
                        $("#titleTable").text("❌ Đơn hàng Trễ trong 2 tuần").removeClass("text-warning").addClass(
                            "text-danger");
                    } else {
                        $("#titleTable").text("⚠️ Đơn hàng Sắp đến hạn (≤ 7 ngày)").removeClass("text-danger").addClass(
                            "text-warning");
                    }
                });
        }

        // lần đầu load
        loadTable(currentMode);

        // tự refresh
        setInterval(() => loadTable(currentMode), 30000);

        // bắt phím trái/phải để đổi mode (PC)
        document.addEventListener("keydown", function(e) {
            if (e.key === "ArrowRight" || e.key === "ArrowLeft") {
                toggleMode();
            }
        });

        // hai nút chuyển mode
        $("#btnOverdue").on("click", () => {
            currentMode = "overdue14";
            loadTable(currentMode);
        });
        $("#btnUpcoming").on("click", () => {
            currentMode = "7";
            loadTable(currentMode);
        });

        // 🔹 Vuốt trái/phải (cho mobile)
        let touchStartX = 0;
        let touchEndX = 0;

        document.addEventListener("touchstart", e => {
            touchStartX = e.changedTouches[0].screenX;
        });

        document.addEventListener("touchend", e => {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        });

        function handleSwipe() {
            const diff = touchEndX - touchStartX;
            if (Math.abs(diff) > 100) { // Vuốt đủ dài mới đổi
                toggleMode();
            }
        }

        function toggleMode() {
            currentMode = (currentMode === "overdue14") ? "7" : "overdue14";
            loadTable(currentMode);
        }
    </script>
</body>

</html>
