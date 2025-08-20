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

        <!-- 🔍 Bộ lọc -->
        <div class="row mb-3">
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
                    <option value="⛔ Chưa nhập kho">⛔ Chưa nhập kho</option>
                    <option value="📦 Chưa đủ số lượng">📦 Chưa đủ số lượng</option>
                </select>
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
                    <th>Nhập kho</th>
                    <th>Nhập thành phẩm Kế toán</th>
                    <th>Mã kế toán</th>
                    <th>Tồn</th>
                    <th>Tình trạng</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <!-- Modal Chi tiết nhập kho -->
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
            fetch("http://192.168.1.89:8888/api/production-orders")
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
                        nhaptpketoan,
                        datamahhketoan,
                        tongnhapkhoketoan,
                        tongxuatkhoketoan,
                        xuatKho
                    } = response;

                    const rows = data.map((row, index) => {
                        const key = `${row.So_ct}|${row.Ma_hh}`;
                        const keyketoan = `${row.So_dh}|${row.Ma_hh}`;
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
                        const sum = Math.round(sumSoLuong[row.So_ct] ?? 0);

                        const nhap = Math.round(nhapKho[key]?.total_nhap ?? 0);
                        const nhaptp = Math.round(nhaptpketoan[keyketoan]?.total_nhaptpketoan ?? 0);
                        const xuat = Math.round(xuatKho[key]?.total_xuat ?? 0);
                        const tongnhap = Math.round(tongnhapkhoketoan[row.Ma_hh]?.totalnhapkho_ketoan ?? 0);
                        const tongxuat = Math.round(tongxuatkhoketoan[row.Ma_hh]?.totalxuatkho_ketoan ?? 0);
                        const tongton = Math.round(tongnhap - tongxuat);

                        let statusLabel = '';
                        if (xuat >= sum && sum > 0) {
                            statusLabel = '<span class="text-success">✔️ Hoàn thành</span>';
                        } else if (nhap >= sum && xuat === 0) {
                            statusLabel = '<span class="text-primary">📦 Chưa xuất kho</span>';
                        } else if (nhap === 0) {
                            statusLabel = '<span class="text-danger">⛔ Chưa nhập kho</span>';
                        } else if (nhap > 0 && nhap < sum) {
                            statusLabel = '<span class="text-warning">📦 Chưa đủ số lượng</span>';
                        }

                        return [
                            index + 1,
                            row.So_hd,
                            `<span class="copy-text" data-text="${row.So_ct}" style="cursor:pointer; color:blue;">
                                ${row.So_ct}
                            </span>`,
                            row.So_dh,
                            row.khach_hang?.Ten_kh ?? '',
                            row.Soseri,
                            row.Ma_hh,
                            row.hang_hoa?.Ten_hh ?? '',
                            row.Msize,
                            row.Ma_ch,
                            Math.round(row.Dgbannte),
                            sum,
                            `<span class="text-primary">${label}</span>`,
                            row.hang_hoa?.Dvt ?? '',
                            new Date(row.Ngay_ct).toLocaleDateString(),
                            new Date(row.Date).toLocaleDateString(),
                            nx.includes(row.So_ct) ? '✅' : '❌',
                            xv.includes(row.So_ct) ? '✅' : '❌',
                            `<button class="btn btn-link p-0 text-primary show-nhap" 
                                    data-key="${row.So_ct}|${row.Ma_hh}">
                                ${nhap}
                             </button>`,
                            Math.round(nhaptp),
                            datamahhketoan[row.So_dh] ?
                            `<span class="text-success">✅ ${datamahhketoan[row.So_dh].join(", ")}</span>` :
                            '<span class="text-danger">❌ Chưa có</span>',
                            tongton,
                            statusLabel
                        ];
                    });

                    if (!dataTable) {
                        dataTable = $('#productionTable').DataTable({
                            data: rows,
                            columns: Array(23).fill().map((_, i) => ({
                                title: $('thead th').eq(i).text()
                            })),
                            pageLength: 25,
                            language: {
                                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/vi.json'
                            },
                            dom: 'Bfrtip',
                            buttons: [{
                                extend: 'excelHtml5',
                                text: '📤 Xuất Excel',
                                className: 'btn btn-success',
                                exportOptions: {
                                    columns: ':visible'
                                },
                                title: 'Bang_Lenh_San_Xuat',
                            }]
                        });

                        $('#filterKhachHang, #filterMaHH, #filterTinhTrang, #filterNgayGiao,#filterLenhSanXuat, #filterMaKinhDoanh')
                            .on(
                                'input change',
                                function() {
                                    dataTable.draw();
                                });
                        $('#clearFilters').on('click', function() {
                            $('#filterKhachHang').val('');
                            $('#filterMaHH').val('');
                            $('#filterTinhTrang').val('');
                            $('#filterNgayGiao').val('');
                            $('#filterLenhSanXuat').val('');
                            $('#filterMaKinhDoanh').val('');
                            dataTable.draw();
                        });

                        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                            const khachHang = $('#filterKhachHang').val().toLowerCase();
                            const maHH = $('#filterMaHH').val().toLowerCase();
                            const tinhTrang = $('#filterTinhTrang').val();
                            const ngayGiao = $('#filterNgayGiao').val();
                            const lenhSanXuat = $('#filterLenhSanXuat').val();
                            const maKinhDoanh = $('#filterMaKinhDoanh').val().toLowerCase();

                            const khachHangCol = data[4].toLowerCase();
                            const maHHCol = data[5].toLowerCase();
                            const tinhTrangCol = $('<div>').html(data[22]).text();
                            const ngayGiaoCol = data[15];
                            const lenhSanXuatCol = data[3];
                            const maKinhDoanhCol = data[5].toLowerCase();


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
                    `http://192.168.1.89:8888/api/nhapkho-chi-tiet?so_dh=${encodeURIComponent(so_dh)}&ma_hh=${encodeURIComponent(ma_hh)}`
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
                              
                              <td>${new Date(d.Ngay_ct).toLocaleDateString()}</td>
                              <td>${d.So_ct}</td>
                              <td>${d.Ma_hh}</td>
                              <td>${d.Soluong}</td>
                            </tr>
                          `);
                        });
                    }

                    new bootstrap.Modal(document.getElementById("nhapModal")).show();
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
