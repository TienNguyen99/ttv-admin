    <!DOCTYPE html>
    <html lang="vi">

    <head>
        <meta charset="UTF-8">
        <title>THEO DÕI LỆNH SẢN XUẤT TAGTIME</title>

        <!-- Bootstrap CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

        <!-- DataTables CSS -->
        <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
        <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    </head>

    <body>
        <div class="container-fluid mt-4">
            <!-- Bộ lọc khoảng ngày -->
            <div class="mb-3">
                <label for="rangeSelect" class="form-label fw-bold">📅 Chọn khoảng thời gian:</label>
                <select id="rangeSelect" class="form-select" style="width: 200px; display:inline-block;">
                    <option value="7">Tuần này (7 ngày)</option>
                    <option value="14">2 tuần (14 ngày)</option>
                    <option value="21">3 tuần (21 ngày)</option>
                    <option value="30">1 tháng (30 ngày)</option>
                    <option value="overdue">Quá hạn</option>
                </select>
            </div>

            <!-- Bảng dữ liệu -->
            <table class="table table-bordered table-hover" id="productionTable" style="width: 100%;">
                <thead class="table-dark">
                    <tr>
                        <th>STT</th>
                        <th>Lệnh sản xuất</th>
                        <th>Khách hàng</th>
                        <th>Mã hàng</th>
                        <th>Mã hàng (tên)</th>
                        <th>Tên hàng</th>
                        <th>Số lượng đơn hàng</th>
                        <th>Nhập kho</th>
                        <th>Xuất kho</th>
                        <th>Ngày hẹn (xuất hàng)</th>
                        <th>Hình ảnh</th>
                        <th>Tình trạng</th>
                        <th>Sắp đến hạn</th>
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

            function fetchData(range = 7) {
                fetch(`http://192.168.1.13:8888/api/tivi?range=${range}`)
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

                            const xuatkhomavvkt = Math.round(xuatkhotheomavvketoan[keyketoan2]
                                ?.xuatkhotheomavv_ketoan ?? 0);
                            const nhap = Math.round(nhapKho[key]?.total_nhap ?? 0);

                            // 🔹 Tình trạng
                            let statusLabel = '';
                            if (xuatkhomavvkt >= row.Soluong || (row.Noibo && row.Noibo.includes("R"))) {
                                statusLabel = '<span class="text-success">✔️ Hoàn thành</span>';
                            } else if (xuatkhomavvkt < row.Soluong && xuatkhomavvkt > 0) {
                                statusLabel = '<span class="text-danger">📦 Xuất kho chưa đủ đơn hàng</span>';
                            } else if (nhap >= row.Soluong) {
                                statusLabel = '<span class="text-primary">📦 Chưa xuất kho</span>';
                            } else if (nhap === 0) {
                                statusLabel = '<span class="text-danger">⛔ Chưa nhập kho</span>';
                            } else if (nhap > 0 && nhap < row.Soluong) {
                                statusLabel = '<span class="text-warning">📦 Chưa đủ số lượng</span>';
                            }

                            // 🔹 Sắp đến hạn
                            let deadlineLabel = '';
                            if (row.Date) {
                                const today = new Date();
                                const deliveryDate = new Date(row.Date);
                                const diffDays = Math.ceil((deliveryDate - today) / (1000 * 60 * 60 * 24));

                                if (diffDays <= 7 && diffDays >= 0) {
                                    deadlineLabel =
                                        `<span class="text-danger fw-bold">⚠️ Còn ${diffDays} ngày</span>`;
                                } else if (diffDays < 0) {
                                    deadlineLabel =
                                        `<span class="text-dark fw-bold">❌ Quá hạn ${Math.abs(diffDays)} ngày</span>`;
                                } else {
                                    deadlineLabel = `Còn ${diffDays} ngày`;
                                }
                            }

                            return [
                                index++,
                                row.So_dh,
                                row.khach_hang?.Ten_kh ?? '',
                                row.Soseri,
                                row.Ma_hh,
                                row.hang_hoa?.Ten_hh ?? '',
                                Math.round(row.Soluong),
                                nhap,
                                xuatkhomavvkt,
                                row.Date ? new Date(row.Date).toLocaleDateString('vi-VN') : '',
                                row.hang_hoa?.Ma_so && row.hang_hoa?.Pngpath_fixed ?
                                `<img src="http://192.168.1.13:8888/hinh_hh/HH_${row.hang_hoa.Ma_so}/${row.hang_hoa.Pngpath_fixed}" 
                                        alt="Hình ảnh" width="100%" height="100%" onerror="this.style.display='none'">` :
                                '',
                                statusLabel,
                                deadlineLabel
                            ];
                        });

                        if (!dataTable) {
                            dataTable = $('#productionTable').DataTable({
                                data: rows,
                                pageLength: 25,
                                // order: [
                                //     [8, 'asc']
                                // ],
                                dom: 'Bfrtip',
                                buttons: [{
                                    extend: 'excelHtml5',
                                    text: '📤 Xuất Excel',
                                    className: 'btn btn-success',
                                    title: 'Bang_Lenh_San_Xuat',
                                }]
                            });
                        } else {
                            dataTable.clear();
                            dataTable.rows.add(rows);
                            dataTable.draw(false);
                        }
                    })
                    .catch(err => console.error("Lỗi khi tải dữ liệu:", err));
            }

            // 🔹 Load ban đầu
            fetchData();

            // 🔹 Khi đổi dropdown → load lại API
            document.getElementById('rangeSelect').addEventListener('change', function() {
                fetchData(this.value);
            });

            // 🔹 Tự refresh mỗi 10s theo range hiện tại
            setInterval(() => {
                const range = document.getElementById('rangeSelect').value;
                fetchData(range);
            }, 10000);
        </script>

        <!-- Buttons + JSZip (Excel) -->
        <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>

    </body>

    </html>
