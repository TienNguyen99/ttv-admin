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
    <div class="container-fluid mt-4">
        <h3 class="mb-4">BẢNG THEO DÕI LỆNH SẢN XUẤT TAGTIME</h3>
        <!-- Danh sách 10 thay đổi gần nhất -->
        <div class="mb-3">
            <label class="form-label">Mã kế toán thay đổi gần nhất:</label>
            <table class="table table-sm table-bordered" id="last-changes-table" style="max-width:600px;">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Tìm số CT</th>
                        <th>Mã SP</th>
                        <th>Mã nguyên liệu</th>
                        <th>Vụ việc</th>
                        <th>Tiêu hao nguyên liệu</th>
                        <th>Số lượng nhập kho</th>
                        <th>Định mức</th>
                        <th>Ngày chỉnh sửa</th>
                        <th>Ngày nhập kho</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <!-- 🔍 Bộ lọc -->
        <div class="row mb-3">
            <div class="col-md-3">
                <label for="filterFromDate" class="form-label">Từ ngày (dd/mm/yyyy)</label>
                <input type="text" class="form-control" id="filterFromDate" placeholder="dd/mm/yyyy">
            </div>
            <div class="col-md-3">
                <label for="filterToDate" class="form-label">Đến ngày (dd/mm/yyyy)</label>
                <input type="text" class="form-control" id="filterToDate" placeholder="dd/mm/yyyy">
            </div>
            <div class="col-md-3">
                <label for="filterKhachHang" class="form-label">Khách hàng</label>
                <input type="text" class="form-control" id="filterKhachHang" placeholder="Nhập tên khách hàng">
            </div>
            <div class="col-md-3">
                <label for="filterMaHH" class="form-label">Mã HH</label>
                <input type="text" class="form-control" id="filterMaHH" placeholder="Nhập mã hàng hóa">
            </div>
            <div class="col-md-3">
                <label for="filterTinhTrang" class="form-label">Tình trạng</label>
                <select class="form-select" id="filterTinhTrang">
                    <option value="">Tất cả</option>
                    <option value="✔️ Hoàn thành">✔️ Hoàn thành</option>
                    <option value="📦 Chưa xuất kho">📦 Chưa xuất kho</option>
                    <option value="📦 Xuất kho chưa đủ đơn hàng">📦 Xuất kho chưa đủ đơn hàng</option>
                    <option value="⛔ Chưa nhập kho">⛔ Chưa nhập kho</option>
                    <option value="📦 Chưa đủ số lượng">📦 Chưa đủ số lượng</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="filterNgayRaLenh" class="form-label">Ngày ra lệnh</label>
                <input type="date" class="form-control" id="filterNgayRaLenh">
            </div>
            <div class="col-md-3">
                <label for="filterNgayGiao" class="form-label">Tháng giao</label>
                <input type="month" class="form-control" id="filterNgayGiao">
            </div>
            <div class="col-md-3">
                <label for="filterLenhSanXuat" class="form-label">Lệnh sản xuất</label>
                <input type="text" class="form-control" id="filterLenhSanXuat" placeholder="Nhập lệnh sản xuất">
            </div>
            <div class="col-md-3">
                <label for="filterMaKinhDoanh" class="form-label">Mã kinh doanh</label>
                <input type="text" class="form-control" id="filterMaKinhDoanh" placeholder="Nhập mã kinh doanh">
            </div>
            <div class="col-md-3">
                <label for="filterexcludeMaLenh" class="form-label">Loại trừ (ẩn)</label>
                <input type="text" class="form-control" id="filterexcludeMaLenh"
                    placeholder="Nhập từ khóa cần loại bỏ">
            </div>
            <div class="col-md-12 mt-2 text-end">
                <button class="btn btn-secondary" id="clearFilters">🧹 Xóa bộ lọc</button>
            </div>
        </div>

        <table class="table table-bordered table-hover" id="productionTable" style="width: 100%;">
            <thead class="table-dark">
                <tr>
                    <th>STT</th>
                    <th>SỐ ĐƠN HÀNG</th>
                    <th>TÊN PO</th>
                    <th>MÃ LỆNH</th>
                    <th>KHÁCH HÀNG</th>
                    <th>MÃ KINH DOANH</th>
                    <th>Mã HH</th>
                    <th>TÊN SP</th>
                    <th>SIZE</th>
                    <th>MÀU</th>
                    <th>SL ĐƠN HÀNG</th>
                    <th>Số lượng cần</th>
                    <th>SẢN XUẤT</th>
                    <th>ĐVT</th>
                    <th>Ngày nhận</th>
                    <th>Ngày giao</th>
                    <th>Phân tích</th>
                    <th>Chuẩn bị</th>
                    <th>Nhập kho (chị Nghiêm)</th>
                    <th>Xuất kho (kế toán)</th>
                    <th>Xuất VT</th>
                    <th>Nhập thành phẩm Kế toán</th>
                    <th>Mã kế toán</th>
                    <th>Tồn kế toán</th>
                    <th>Tình trạng</th>
                    <th>Mã kế toán xuất</th>
                    <th>Mã mẹ</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <!-- Modal Chi tiết nhập kho chị nghiêm ( phiếu nhập kho )-->
    <div class="modal fade" id="nhapModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Chi tiết nhập kho</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-bordered" id="nhapDetailTable">
                        <thead>
                            <tr>
                                <th>Ngày chứng từ</th>
                                <th>Số chứng từ</th>
                                <th>Mã hàng</th>
                                <th>Số lượng</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal Chi tiết xuất vật tư (mới) -->
    <div class="modal fade" id="xuatModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Chi tiết xuất vật tư</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-bordered" id="xuatDetailTable">
                        <thead>
                            <tr>
                                <th>Ngày chứng từ</th>
                                <th>Số chứng từ</th>
                                <th>Kho xuất</th>
                                <th>Kho nhập</th>
                                <th>Mã hàng</th>
                                <th>Tên hàng</th>
                                <th>Thực xuất</th>
                                <th>Nhu cầu</th>
                                <th>Tổng đã xuất</th>
                                <th>ĐVT</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal Chi tiết xuất kho kế toán -->
    <div class="modal fade" id="xuatKhoModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Chi tiết xuất kho kế toán (So sánh với nhập kho)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Phần tóm tắt -->
                    <div class="summary-box">
                        <h6 class="text-primary mb-3">📊 Tổng quan</h6>
                        <div class="summary-item">
                            <span class="summary-label">Tổng nhập kho:</span>
                            <span class="summary-value text-success" id="tongNhap">0</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Tổng xuất kho:</span>
                            <span class="summary-value text-primary" id="tongXuat">0</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Tồn kho:</span>
                            <span class="summary-value" id="tonKho">0</span>
                        </div>
                    </div>

                    <!-- Bảng nhập kho -->
                    <h6 class="text-success mb-2">📦 Chi tiết nhập kho</h6>
                    <table class="table table-bordered table-sm mb-4" id="nhapKhoCompareTable">
                        <thead class="table-success">
                            <tr>
                                <th>Mã vụ việc</th>
                                <th>Mã sản phẩm</th>
                                <th>Số lượng nhập</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>

                    <!-- Bảng xuất kho -->
                    <h6 class="text-primary mb-2">📤 Chi tiết xuất kho</h6>
                    <table class="table table-bordered" id="xuatKhoDetailTable">
                        <thead class="table-primary">
                            <tr>
                                <th>Ngày chứng từ</th>
                                <th>Số chứng từ</th>
                                <th>Mã hàng</th>
                                <th>Số lượng</th>
                                <th>Đơn giá vốn</th>
                                <th>Đơn giá bán</th>
                                <th>Đơn giá $</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal phân tích -->
    <div class="modal fade" id="phanTichModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Chi tiết phân tích</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-bordered" id="phanTichDetailTable">
                        <thead>
                            <tr>
                                <th>Ngày chứng từ</th>
                                <th>Số chứng từ</th>
                                <th>Mã hàng</th>
                                <th>Tên Hàng</th>
                                <th>Định mức</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal Vật tư thành phẩm kế toán -->
    <div class="modal fade" id="vatTuKetoanModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Chi tiết Vật tư của lệnh kế toán</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-bordered" id="vatTuKeToanDetailTable">
                        <thead>
                            <tr>
                                <th>Ngày nhập</th>
                                <th>Diễn giải</th>
                                <th>Mã sản phẩm</th>
                                <th>Mã vật tư</th>
                                <th>Số lượng</th>
                                <th>Tổng đơn hàng</th>
                                <th>Định mức</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <!-- Danh sách mã vật tư cần copy -->
                <div class="modal-footer flex-column align-items-start">
                    <label><strong>Danh sách mã vật tư cần copy:</strong></label>
                    <textarea id="uniqueMaVTList" class="form-control" rows="5" readonly></textarea>
                </div>
            </div>
        </div>
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
            // Lấy giá trị date từ input
            const fromDate = document.getElementById('filterFromDate').value;
            const toDate = document.getElementById('filterToDate').value;

            // Xây dựng URL với params
            let url = "/api/production-orders";
            const params = new URLSearchParams();

            if (fromDate) params.append('from_date', fromDate);
            if (toDate) params.append('to_date', toDate);

            if (params.toString()) {
                url += "?" + params.toString();
            }

            fetch(url)
                .then(res => res.json())
                .then(response => {
                    const {
                        data,
                        sumSoLuong,
                        cd1,
                        cd2,
                        cd3,
                        cd4,
                        cd5,
                        cd6,
                        cd7,
                        cd8,
                        cd9,
                        nx,
                        xv,
                        nhapKho,
                        nhaptpketoan,
                        datamahhketoan,
                        datamahhketoanxuat,
                        tongnhapkhoketoan,
                        tongxuatkhoketoan,
                        xuatkhotheomavvketoan,
                        lastChange
                    } = response;
                    // Hiển thị mã kế toán thay đổi gần nhất
                    const tbodyLast = document.querySelector("#last-changes-table tbody");
                    if (lastChange && lastChange.length > 0) {
                        tbodyLast.innerHTML = lastChange.map((item, idx) => `
                        <tr>
                            <td>${idx + 1}</td>
                            <td>${item.So_ct}</td>
                            <td>${item.Ma_sp}</td>
                            <td>${item.Ma_hh}</td>
                            <td>${item.Ma_vv}</td>
                            <td>${Number(item.Soluong).toFixed(2)}</td>
                            <td>${Number(item.Noluong).toFixed(2)}</td>
                            <td>${(item.Soluong / item.Noluong).toFixed(4)}</td>
                            <td>${new Date(item.UserNg0).toLocaleDateString("vi-VN")}</td>
                            <td>${new Date(item.Ngay_ct).toLocaleDateString("vi-VN")}</td>
                        </tr>`).join("");
                    } else {
                        tbodyLast.innerHTML = `<tr><td colspan="4" class="text-center">Không có dữ liệu</td></tr>`;
                    }
                    const rows = data.map((row, index) => {
                        const key = `${row.So_ct}|${row.Ma_hh}`;
                        const keyketoan = `${row.So_dh}|${row.Ma_hh}`;
                        const keyketoan2 = `${row.So_dh}|${row.hang_hoa?.Ma_so}`;
                        const cdSteps = [cd1, cd2, cd3, cd4, cd5, cd6, cd7, cd8, cd9];
                        let step = 0,
                            label = 'Chưa bắt đầu';
                        for (let i = 8; i >= 0; i--) {
                            if (cdSteps[i][key]) {
                                step = Math.round(cdSteps[i][key].total);
                                label = `Công đoạn${i + 1} - ${step}`;
                                break;
                            }
                        }
                        const sum = Math.round(sumSoLuong[row.So_ct] ?? 0);
                        const nhap = Math.round(nhapKho[key]?.total_nhap ?? 0);
                        const nhaptp = Math.round(nhaptpketoan[keyketoan2]?.total_nhaptpketoan ?? 0);
                        // const xuat = Math.round(xuatKho[key]?.total_xuat ?? 0);
                        // Xuất kho kế toán theo Ma_vv và Ma_hh
                        const xuatkhomavvkt = Math.round(xuatkhotheomavvketoan[keyketoan2]
                            ?.xuatkhotheomavv_ketoan ?? 0);
                        const tongnhap = Math.round(tongnhapkhoketoan[row.Ma_hh]?.totalnhapkho_ketoan ?? 0);
                        const tongxuat = Math.round(tongxuatkhoketoan[row.Ma_hh]?.totalxuatkho_ketoan ?? 0);
                        const tongton = Math.round(tongnhap - tongxuat);
                        // Kiểm tra Ma_hh có giống datamahhketoan không
                        const maHh = row.hang_hoa?.Ma_so ?? '';
                        const dsMaHHKeToan = datamahhketoan[row.So_dh] || [];
                        const isMismatch = !dsMaHHKeToan.includes(maHh);
                        // Kiểm tra độ dài mã hàng hóa
                        let maHhCell;
                        if (maHh && maHh.length > 16) {
                            // Nếu độ dài > 18 ký tự, hiển thị màu cam và in đậm
                            maHhCell =
                                `<span style="color:orange; font-weight:bold;" title="${maHh}">${maHh}</span>`;
                        } else if (isMismatch) {
                            // Nếu không khớp với kế toán, hiển thị màu đỏ
                            maHhCell = `<span style="color:red; font-weight:bold;">${row.Ma_hh ?? ''}</span>`;
                        } else {
                            // Bình thường
                            maHhCell = row.Ma_hh ?? '';
                        }
                        // Xác định tình trạng
                        let statusLabel = '';

                        if (xuatkhomavvkt >= sum || (row.Noibo && row.Noibo.includes("R"))) {
                            statusLabel = '<span class="text-success">✔️ Hoàn thành</span>';
                        } else if (xuatkhomavvkt < sum && xuatkhomavvkt > 0) {
                            const thieu = Math.round(row.Dgbannte) - xuatkhomavvkt;
                            statusLabel =
                                `<span class="text-danger">📦 Xuất kho chưa đủ đơn hàng (Thiếu: ${thieu})</span>`;
                        } else if (nhap >= sum && xuatkhomavvkt === 0) {
                            statusLabel = '<span class="text-primary">📦 Chưa xuất kho</span>';
                        } else if (nhap === 0) {
                            statusLabel = '<span class="text-danger">⛔ Chưa nhập kho</span>';
                        } else if (nhap > 0 && nhap < sum) {
                            statusLabel = '<span class="text-warning">📦 Chưa đủ số lượng</span>';
                        }
                        return [
                            index + 1,
                            row.So_hd,
                            `<span class="copy-text" data-text="${row.So_ct}" style="cursor:pointer; color:blue;">${row.So_ct}</span>`,
                            row.So_dh,
                            row.khach_hang?.Ten_kh ?? '',
                            row.Soseri,
                            maHhCell,
                            row.hang_hoa?.Ten_hh ?? '',
                            row.Msize,
                            row.Ma_ch,
                            Math.round(row.Dgbannte),
                            sum,
                            `<span class="text-primary">${label}</span>`,
                            row.hang_hoa?.Dvt ?? '',
                            new Date(row.Ngay_ct).toLocaleDateString("vi-VN"),
                            new Date(row.Date).toLocaleDateString("vi-VN"),
                            `<button class="btn btn-link p-0 show-phantich" data-so-dh="${row.So_ct}">${nx.includes(row.So_ct) ? '✅' : '❌'} </button>`,
                            xv.includes(row.So_ct) ? '✅' : '❌',
                            `<button class="btn btn-link p-0 text-primary show-nhap" data-key="${row.So_ct}|${row.Ma_hh}">${nhap}</button>`,
                            xuatkhomavvkt,
                            `<button class="btn btn-link p-0 text-danger show-xuat" data-so-dh="${row.So_ct}">Xem</button>`,
                            `<button class="btn btn-link p-0 text-success show-vattuketoan" data-ma-vv="${row.So_dh}">${Math.round(nhaptp)} </button>`,
                            datamahhketoan[row.So_dh] ?
                            `<span class="text-success">✅ ${datamahhketoan[row.So_dh].join(", ")}</span>` :
                            '<span class="text-danger">❌ Chưa có</span>',
                            `<button class="btn btn-link p-0 text-success show-xuatketoan" data-ma-hh="${row.hang_hoa?.Ma_so}">${tongton} </button>`,
                            statusLabel,
                            datamahhketoanxuat[row.So_dh] ? datamahhketoanxuat[row.So_dh].join(", ") : '',
                            row.hang_hoa?.Ma_so ?? ''
                        ];
                    });

                    if (!dataTable) {
                        dataTable = $('#productionTable').DataTable({
                            data: rows,
                            pageLength: 25,
                            language: {
                                search: "Tìm kiếm:",
                                lengthMenu: "Hiển thị _MENU_ dòng",
                                info: "Hiển thị _START_ đến _END_ của _TOTAL_ dòng",
                                paginate: {
                                    first: "Đầu",
                                    last: "Cuối",
                                    next: "Sau",
                                    previous: "Trước"
                                }
                            },
                            dom: 'Bfrtip',
                            buttons: [{
                                extend: 'excelHtml5',
                                text: '📤 Xuất Excel',
                                className: 'btn btn-success',
                                exportOptions: {
                                    columns: [3, 4, 5, 26, 7, 8, 9, 10, 11, 13, 14, 15, 18, 19, 23,
                                        24, 6
                                    ] // In
                                    //columns: [3, 4, 5, 7, 8, 9, 10, 13, 14, 15, 18, 19, 23, 24] //Để in báo cáo
                                },
                                title: 'Bang_Lenh_San_Xuat',
                            }]
                        });

                        $('#filterKhachHang, #filterMaHH, #filterTinhTrang, #filterNgayRaLenh,#filterLenhSanXuat, #filterMaKinhDoanh, #filterNgayGiao, #filterexcludeMaLenh')
                            .on(
                                'input change',
                                function() {
                                    dataTable.draw();
                                });
                        $('#clearFilters').on('click', function() {
                            $('#filterFromDate').val('');
                            $('#filterToDate').val('');
                            $('#filterKhachHang').val('');
                            $('#filterMaHH').val('');
                            $('#filterTinhTrang').val('');
                            $('#filterNgayGiao').val('');
                            $('#filterLenhSanXuat').val('');
                            $('#filterMaKinhDoanh').val('');
                            $('#filterNgayRaLenh').val('');
                            $('#filterexcludeMaLenh').val('');
                            fetchData();
                        });

                        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                            const khachHang = $('#filterKhachHang').val().toLowerCase();
                            const maHH = $('#filterMaHH').val().toLowerCase();
                            const tinhTrang = $('#filterTinhTrang').val();
                            const ngayGiao = $('#filterNgayGiao').val();
                            const ngayRaLenh = $('#filterNgayRaLenh').val();
                            const lenhSanXuat = $('#filterLenhSanXuat').val();
                            const maKinhDoanh = $('#filterMaKinhDoanh').val().toLowerCase();
                            const excludeText = $('#filterexcludeMaLenh').val().toLowerCase();
                            // CẬP NHẬT CHỈ SỐ CỘT (phù hợp với cấu trúc bảng hiện tại)
                            const khachHangCol = (data[4] || '').toLowerCase();
                            const maHHCol = (data[6] || '').toLowerCase();
                            const tinhTrangCol = $('<div>').html(data[24] || '').text();
                            const ngayGiaoCol = data[15] || '';
                            const ngayRaLenhCol = data[14] || '';
                            const lenhSanXuatCol = data[3] || '';
                            const maKinhDoanhCol = (data[5] || '').toLowerCase();
                            const excludeCol = (data[2] || '').toLowerCase();
                            //Ẩn mã lệnh
                            if (excludeText && excludeCol.includes(excludeText)) return false;
                            if (khachHang && !khachHangCol.includes(khachHang)) return false;
                            if (maHH && !maHHCol.includes(maHH)) return false;
                            if (tinhTrang && !tinhTrangCol.includes(tinhTrang)) return false;
                            if (lenhSanXuat && !lenhSanXuatCol.includes(lenhSanXuat)) return false;
                            if (maKinhDoanh && !maKinhDoanhCol.includes(maKinhDoanh)) return false;
                            if (ngayGiao) {
                                const [day, month, year] = ngayGiaoCol.split('/');
                                const tableMonth = `${year}-${month.padStart(2, '0')}`;
                                if (!tableMonth.startsWith(ngayGiao)) return false;
                            }
                            if (ngayRaLenh) {
                                // Chuyển ngày trong bảng thành đối tượng Date
                                const [day, month, year] = ngayRaLenhCol.split('/');
                                const tableDate = new Date(`${year}-${month}-${day}`);
                                // Ngày người dùng chọn
                                const filterDate = new Date(ngayRaLenh);
                                // Ngày hiện tại
                                const currentDate = new Date();
                                // Giữ lại các dòng có ngày >= ngày chọn và <= hiện tại
                                if (tableDate < filterDate || tableDate > currentDate) {
                                    return false;
                                }
                            }
                            return true;
                        });
                    } else {
                        dataTable.clear();
                        dataTable.rows.add(rows);
                        dataTable.draw(false);
                    }
                })
                .catch(err => {
                    console.error("Lỗi khi tải dữ liệu:", err);
                });
        }
        fetchData();
        setInterval(fetchData, 10000);
        // Xem chi tiết nhập kho
        $(document).on("click", ".show-nhap", function() {
            const key = $(this).data("key");
            const [so_dh, ma_hh] = key.split("|");

            fetch(
                    `/api/nhapkho-chi-tiet?so_dh=${encodeURIComponent(so_dh)}&ma_hh=${encodeURIComponent(ma_hh)}`
                )
                .then(res => res.json())
                .then(details => {
                    const tbody = $("#nhapDetailTable tbody");
                    tbody.empty();

                    if (details.length === 0) {
                        tbody.append(`<tr><td colspan="4" class="text-center">Không có dữ liệu</td></tr>`);
                    } else {
                        details.forEach(d => {
                            tbody.append(`
                            <tr>
                              
                              <td>${new Date(d.Ngay_ct).toLocaleDateString("vi-VN")}</td>
                              <td>${d.So_ct}</td>
                              <td>${d.Ma_hh}</td>
                              <td>${Math.round(d.Soluong, 0)}</td>
                              
                            
                            </tr>
                          `);
                        });
                    }

                    new bootstrap.Modal(document.getElementById("nhapModal")).show();
                });
        });
        // Xem chi tiết xuất vật tư (mới)
        $(document).on("click", ".show-xuat", function() {
            const so_dh = decodeURIComponent($(this).data("so-dh"));

            fetch(
                    `/api/xuat-vat-tu?so_dh=${encodeURIComponent(so_dh)}`
                )
                .then(res => res.json())
                .then(vat_tu => {
                    const tbody = $("#xuatDetailTable tbody");
                    tbody.empty();

                    if (vat_tu.length === 0) {
                        tbody.append(`<tr><td colspan="8" class="text-center">Không có dữ liệu</td></tr>`);
                    } else {
                        vat_tu.forEach(d => {
                            tbody.append(`
                            <tr>
                              <td>${new Date(d.Ngay_ct).toLocaleDateString("vi-VN")}</td>
                              <td>${d.So_ct}</td>
                              <td>${d.Ma_ko ?? ''}</td>
                              <td>${d.Ma3ko ?? ''}</td>
                              <td>${d.Ma_hh}</td>
                              <td>${d.Ten_hh ?? ''}</td>
                              <td>${Number(d.Soluong).toFixed(0)}</td>
                                <td>${Number(d.Nhu_cau).toFixed(0)}</td>
                                <td>${Number(d.Tong_da_xuat).toFixed(0)}</td>
                                <td>${d.Dvt}</td>
                                

                            </tr>
                          `);
                        });
                    }

                    new bootstrap.Modal(document.getElementById("xuatModal")).show();
                });
        });
        // Xem chi tiết xuất kho kế toán với so sánh nhập kho
        $(document).on("click", ".show-xuatketoan", function() {
            const ma_hh = $(this).data("ma-hh");

            fetch(`/api/xuatkhoketoan-chi-tiet?ma_hh=${encodeURIComponent(ma_hh)}`)
                .then(res => res.json())
                .then(data => {
                    // Cập nhật tổng quan
                    const tongNhap = Number(data.tong_nhap || 0);
                    const tongXuat = Number(data.tong_xuat || 0);
                    const tonKho = Number(data.ton_kho || 0);

                    $("#tongNhap").text(tongNhap.toFixed(2));
                    $("#tongXuat").text(tongXuat.toFixed(2));

                    // Màu sắc cho tồn kho
                    const tonKhoEl = $("#tonKho");
                    tonKhoEl.text(tonKho.toFixed(2));
                    if (tonKho > 0) {
                        tonKhoEl.removeClass("text-danger").addClass("text-success");
                    } else if (tonKho < 0) {
                        tonKhoEl.removeClass("text-success").addClass("text-danger");
                    } else {
                        tonKhoEl.removeClass("text-success text-danger");
                    }

                    // Hiển thị bảng nhập kho
                    const tbodyNhap = $("#nhapKhoCompareTable tbody");
                    tbodyNhap.empty();

                    if (data.nhap_kho && data.nhap_kho.length > 0) {
                        data.nhap_kho.forEach(n => {
                            tbodyNhap.append(`
                        <tr>
                            <td>${n.Ma_vv}</td>
                            <td>${n.Ma_sp}</td>
                            <td class="text-end">${Number(n.total_nhap).toFixed(2)}</td>
                        </tr>
                    `);
                        });
                    } else {
                        tbodyNhap.append(`<tr><td colspan="3" class="text-center">Chưa có nhập kho</td></tr>`);
                    }

                    // Hiển thị bảng xuất kho
                    const tbodyXuat = $("#xuatKhoDetailTable tbody");
                    tbodyXuat.empty();

                    if (data.xuat_kho && data.xuat_kho.length > 0) {
                        data.xuat_kho.forEach(d => {
                            tbodyXuat.append(`
                        <tr>
                            <td>${new Date(d.Ngay_ct).toLocaleDateString("vi-VN")}</td>
                            <td>${d.So_ct}</td>
                            <td>${d.Ma_hh}</td>
                            <td class="text-end">${Number(d.Soluong).toFixed(2)}</td>
                            <td class="text-end">${Number(d.Dgvonvnd).toLocaleString("vi-VN")}</td>
                            <td class="text-end">${Number(d.Dgbanvnd).toLocaleString("vi-VN")}</td>
                            <td class="text-end">${Number(d.Dgbannte).toLocaleString("en-US")}</td>
                        </tr>
                    `);
                        });
                    } else {
                        tbodyXuat.append(`<tr><td colspan="7" class="text-center">Chưa có xuất kho</td></tr>`);
                    }

                    new bootstrap.Modal(document.getElementById("xuatKhoModal")).show();
                })
                .catch(err => {
                    console.error("Lỗi khi tải dữ liệu:", err);
                    alert("Không thể tải dữ liệu. Vui lòng thử lại!");
                });
        });
        // Xem chi tiết phân tích
        $(document).on("click", ".show-phantich", function() {
            const so_dh = decodeURIComponent($(this).data("so-dh"));

            fetch(
                    `/api/phan-tich?so_dh=${encodeURIComponent(so_dh)}`
                )
                .then(res => res.json())
                .then(phantich => {
                    const tbody = $("#phanTichDetailTable tbody");
                    tbody.empty();

                    if (phantich.length === 0) {
                        tbody.append(`<tr><td colspan="5" class="text-center">Không có dữ liệu</td></tr>`);
                    } else {
                        phantich.forEach(d => {
                            tbody.append(`
                            <tr>
                              <td>${new Date(d.Ngay_ct).toLocaleDateString("vi-VN")}</td>
                              <td>${d.So_ct}</td>
                              <td>${d.Ma_hh}</td>
                              <td>${d.hang_hoa?.Ten_hh ?? ''}</td>
                              <td>${Number(d.Soluong).toFixed(4)}</td> 

                            </tr>
                          `);
                        });
                    }

                    new bootstrap.Modal(document.getElementById("phanTichModal")).show();
                });
        });
        // Xem chi tiết nhập vật tư thành phẩm kế toán
        $(document).on("click", ".show-vattuketoan", function() {
            const ma_vv = $(this).data("ma-vv");

            fetch(`/api/vat-tu-thanh-pham-ketoan?ma_vv=${encodeURIComponent(ma_vv)}`)
                .then(res => res.json())
                .then(vattu => {
                    const tbody = $("#vatTuKeToanDetailTable tbody");
                    tbody.empty();

                    if (vattu.length === 0) {
                        tbody.append(`<tr><td colspan="6" class="text-center">Không có dữ liệu</td></tr>`);
                        $("#uniqueMaVTList").val("");
                    } else {
                        let listMaVT = [];

                        vattu.forEach(d => {
                            let dinhmuc = Number(d.Soluong / d.Noluong).toFixed(4);

                            tbody.append(`
                        <tr>
                            <td>${new Date(d.Ngay_ct).toLocaleDateString("vi-VN")}</td>
                            <td>${d.DgiaiV}</td>
                            <td>${d.Ma_sp}</td>
                            <td>${d.Ma_hh}</td>
                            <td>${Number(d.Soluong).toFixed(4)}</td>
                            <td>${Math.round(d.Noluong)}</td>
                            <td>${dinhmuc}</td>
                        </tr>
                    `);

                            // Lưu cặp mã vật tư + định mức
                            listMaVT.push(`${d.Ma_hh} - ${dinhmuc}`);
                        });

                        // Lọc unique (chỉ lấy 1 dòng cho mỗi mã vật tư)
                        let unique = [...new Set(listMaVT)];
                        $("#uniqueMaVTList").val(unique.join("\n"));
                    }

                    new bootstrap.Modal(document.getElementById("vatTuKetoanModal")).show();
                });
        });
    </script>
    <!-- Buttons + JSZip (Excel) -->
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    {{-- Script copy --}}
    <script>
        // Event listener cho date filter
        document.getElementById('filterFromDate').addEventListener('change', () => {
            fetchData();
        });

        document.getElementById('filterToDate').addEventListener('change', () => {
            fetchData();
        });

        $(document).on('click', '.copy-text', function() {
            const text = $(this).data('text');
            const tempInput = document.createElement("input");
            document.body.appendChild(tempInput);
            tempInput.value = text;
            tempInput.select();
            tempInput.setSelectionRange(0, 99999);
            document.execCommand("copy");
            document.body.removeChild(tempInput);
        });
    </script>
</body>

</html>
