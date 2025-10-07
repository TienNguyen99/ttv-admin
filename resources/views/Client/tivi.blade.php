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

        img {
            border-radius: 6px;
            cursor: pointer;
            transition: transform 0.2s;
        }

        img:hover {
            transform: scale(1.05);
        }

        .mode-buttons {
            text-align: center;
            margin-bottom: 15px;
        }

        .mode-buttons button {
            margin: 5px;
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
            <div class="modal-content bg-dark">
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
                                class="clickable-image"
                                onerror="this.style.display='none'">` :
                            '';

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
                            pageLength: 15,
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
        setInterval(() => loadTable(currentMode), 10000);

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
