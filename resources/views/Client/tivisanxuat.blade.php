<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tivi Sản Xuất - Lệnh SX 24h qua & Trạng thái Máy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        /* CSS gốc giữ nguyên */
        body {
            background-color: #f9fafc;
            color: #1e293b;
            font-size: 18px;
        }

        h1,
        h2 {
            color: #1e293b;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        table {
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
            position: relative;
            transition: opacity 0.4s ease-in-out;
            margin-bottom: 25px;
        }

        thead th {
            background: #f4f7fb;
            color: #2c3e50;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 15px;
            border-bottom: 2px solid #d0d7e2;
            padding: 12px 8px;
        }

        .progress {
            background-color: #e9ecef;
            height: 22px;
            border-radius: 10px;
        }

        .progress-bar {
            font-weight: bold;
            font-size: 14px;
        }

        .subtotal-row {
            background-color: #dbeafe !important;
            color: #1e3a8a;
            font-weight: 600;
        }

        .clickable-image {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: zoom-in;
            border-radius: 8px;
            max-width: 70px;
            max-height: 70px;
        }

        .clickable-image:hover {
            transform: scale(1.4);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
            z-index: 5;
        }

        table.refreshing {
            opacity: 0.4;
        }

        table.refreshing::after {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            width: 40px;
            height: 40px;
            border: 4px solid #93c5fd;
            border-top-color: transparent;
            border-radius: 50%;
            transform: translate(-50%, -50%);
            animation: spin 1s linear infinite;
            z-index: 20;
        }

        @keyframes spin {
            to {
                transform: translate(-50%, -50%) rotate(360deg);
            }
        }

        #modalImage {
            max-height: 90vh;
            object-fit: contain;
            background-color: rgba(0, 0, 0, 0.85);
            border-radius: 10px;
        }

        .modal-content {
            background: transparent;
            border: none;
            box-shadow: none;
        }

        /* CSS MỚI CHO SƠ ĐỒ & TRẠNG THÁI MÁY */
        .floor-section {
            padding: 20px;
            margin-top: 30px;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
        }

        .status-active {
            color: #15803d;
            /* Xanh lá đậm */
            font-weight: 600;
        }

        .status-inactive {
            color: #b91c1c;
            /* Đỏ đậm */
            font-weight: 600;
        }

        .floor-map-placeholder {
            min-height: 200px;
            background-color: #e2e8f0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            font-style: italic;
            font-size: 1.2em;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <div class="container-fluid mt-4">
        <h1 class="text-center mb-3">LỆNH ĐANG SẢN XUẤT TRONG 24 GIỜ QUA</h1>

        <div class="text-center mb-3">
            <button class="btn btn-primary" onclick="loadSXData()">🔄 Làm mới</button>
        </div>

        <table class="table table-bordered table-striped text-center align-middle" id="sxTable">
            <thead>
                <tr>
                    <th>Lệnh</th>
                    <th>Mã HH</th>
                    <th>Hình ảnh</th>
                    <th>Tên Hàng</th>
                    <th>Công đoạn</th>
                    <th>Tên NV</th>
                    <th>Số lượng đơn</th>
                    <th>Sản xuất</th>
                    <th>Tổng SX</th>
                    <th>Lỗi</th>
                    <th>ĐVT</th>
                    <th>%</th>
                    <th>Bộ phận</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="13">Đang tải dữ liệu...</td>
                </tr>
            </tbody>
        </table>

        <div id="machineStatusSection" class="floor-section">
            <h2 class="text-center mb-4">SƠ ĐỒ & TRẠNG THÁI MÁY MÓC</h2>

            <div class="row">
                <div class="col-lg-6">
                    <h3 class="h4 mb-3 text-primary">Tầng 1 - Máy Đang Hoạt Động</h3>
                    <div class="floor-map-placeholder">
                        <p>Placeholder: Sơ đồ tầng 1 (Máy)</p>
                    </div>
                    <table class="table table-bordered table-hover text-center align-middle" id="floor1Table">
                        <thead>
                            <tr>
                                <th>Tên Máy</th>
                                <th>Lệnh SX</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="3">Chờ dữ liệu sản xuất...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="col-lg-6">
                    <h3 class="h4 mb-3 text-primary">Tầng 2 - Máy Đang Hoạt Động</h3>
                    <div class="floor-map-placeholder">
                        <p>Placeholder: Sơ đồ tầng 2 (Máy)</p>
                    </div>
                    <table class="table table-bordered table-hover text-center align-middle" id="floor2Table">
                        <thead>
                            <tr>
                                <th>Tên Máy</th>
                                <th>Lệnh SX</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="3">Chờ dữ liệu sản xuất...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-transparent border-0 shadow-none position-relative">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3"
                    data-bs-dismiss="modal" aria-label="Close"></button>
                <img id="modalImage" src="" alt="Ảnh phóng to" class="w-100 rounded-3">
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        /**
         * Ánh xạ mã Bộ phận (DgiaiV) sang Tên Máy
         * Tạm thời giả định tất cả máy đều ở Tầng 1 cho dễ quản lý
         */
        const MACHINE_MAP = {
            'DET': {
                name: 'Máy Dệt',
                floor: 1
            },
            'BANGTAI': {
                name: 'Máy Băng Tải',
                floor: 2
            },
            'BANIN1': {
                name: 'Bàn In 1',
                floor: 1
            },
            'BANIN2': {
                name: 'Bàn In 2',
                floor: 2 // Ví dụ máy này ở Tầng 2
            },
            // Thêm các ánh xạ khác tại đây
        };

        /**
         * HÀM MỚI: Tổng hợp trạng thái máy từ dữ liệu sản xuất (SX)
         * @param {Array} filteredData - Dữ liệu SX đã lọc trong 24h qua
         */
        function deriveMachineStatusFromSXData(filteredData) {
            const table1 = document.querySelector('#floor1Table tbody');
            const table2 = document.querySelector('#floor2Table tbody');

            // Xóa nội dung cũ
            table1.innerHTML = '';
            table2.innerHTML = '';

            const activeMachines = {};

            filteredData.forEach(item => {
                const maBoPhan = item.DgiaiV?.toUpperCase();

                if (maBoPhan && MACHINE_MAP[maBoPhan]) {
                    const machine = MACHINE_MAP[maBoPhan];
                    const machineKey = `${machine.name}_${machine.floor}`;

                    // Giả định máy đang hoạt động nếu có lệnh SX trong 24h qua
                    if (!activeMachines[machineKey]) {
                        activeMachines[machineKey] = {
                            Ten_may: machine.name,
                            Lenh_sx: item.So_ct_go ?? 'N/A',
                            Trang_thai: 'Hoạt động',
                            Tang: machine.floor
                        };
                    } else {
                        // Nếu cùng một máy có nhiều lệnh trong 24h, hiển thị lệnh cuối cùng hoặc ghi đè
                        activeMachines[machineKey].Lenh_sx = item.So_ct_go ?? 'N/A';
                    }
                }
            });

            const floor1Data = Object.values(activeMachines).filter(m => m.Tang === 1);
            const floor2Data = Object.values(activeMachines).filter(m => m.Tang === 2);

            // Hàm render bảng trạng thái máy
            function renderMachineTable(tbodyElement, data, floor) {
                if (data.length === 0) {
                    tbodyElement.innerHTML =
                        `<tr><td colspan="3" class="text-muted">Tầng ${floor}: Không có máy nào đang chạy lệnh SX trong 24h.</td></tr>`;
                    return;
                }

                data.forEach(machine => {
                    const statusClass = 'status-active'; // Vì chỉ hiển thị máy đang hoạt động
                    const statusText = 'ĐANG CHẠY';

                    const row = `
                        <tr>
                            <td>${machine.Ten_may}</td>
                            <td>${machine.Lenh_sx}</td>
                            <td class="${statusClass}">${statusText}</td>
                        </tr>
                    `;
                    tbodyElement.insertAdjacentHTML('beforeend', row);
                });
            }

            renderMachineTable(table1, floor1Data, 1);
            renderMachineTable(table2, floor2Data, 2);
        }

        /**
         * HÀM GỐC: Tải dữ liệu sản xuất
         */
        async function loadSXData() {
            const table = document.querySelector('#sxTable');
            const tbody = table.querySelector('tbody');
            table.classList.add('refreshing');
            let filteredData = []; // Khai báo biến để lưu dữ liệu đã lọc

            try {
                const res = await fetch('/api/tivi/sx-data');
                const {
                    data,
                    totalBySoct
                } = await res.json();

                // 1. Lọc dữ liệu trong vòng 24h qua
                const now = new Date();
                const cutoff = new Date(now.getTime() - 24 * 60 * 60 * 1000);
                filteredData = data.filter(item => { // Gán vào biến filteredData
                    const ngay = new Date(item.UserNgE);
                    return ngay >= cutoff && ngay <= now;
                });

                tbody.innerHTML = '';

                if (filteredData.length === 0) {
                    tbody.innerHTML =
                        `<tr><td colspan="13" class="text-center text-warning">Không có lệnh SX trong 24h qua</td></tr>`;
                    // Dù không có lệnh, vẫn chạy hàm status để hiển thị thông báo
                    deriveMachineStatusFromSXData(filteredData);
                    return;
                }

                // 2. Render bảng sản xuất
                const groups = {};
                filteredData.forEach(item => {
                    const key = item.So_ct_go ?? 'Chưa có lệnh';
                    if (!groups[key]) groups[key] = [];
                    groups[key].push(item);
                });

                Object.entries(groups).forEach(([soct, rows]) => {
                    let tongSX = 0;
                    const soluongGO = Number(rows[0]?.Soluong_go ?? 0);

                    rows.forEach(item => {
                        // tongSX += Number(item.Soluong ?? 0);
                        tongSX = Number(totalBySoct?.[item.So_dh] ?? 0);
                        const pct = soluongGO > 0 ? (item.Soluong / soluongGO * 100).toFixed(1) : 0;
                        const barColor = pct >= 90 ? 'bg-success' : pct >= 60 ? 'bg-warning' :
                            'bg-danger';

                        const imageHtml = `
                            <img src="/hinh_hh/HH_${item.hang_hoa.Ma_hh}/${item.hang_hoa.Pngpath}" 
                                alt="${item.hang_hoa.Ten_hh}" class="clickable-image">
                        `;

                        const row = `
                            <tr>
                                <td>${item.So_ct_go ?? ''}</td>
                                <td>${item.Ma_hh ?? ''}</td>
                                <td>${imageHtml}</td>
                                <td>${item.hang_hoa?.Ten_hh ?? ''}</td>
                                <td>${item.Ma_ko ?? ''}</td>
                                <td>${item.nhan_vien?.Ten_nv ?? ''}</td>
                                <td>${soluongGO.toLocaleString('vi-VN')}</td>
                                <td>${Number(item.Soluong ?? 0).toLocaleString('vi-VN')}</td>
                                <td>${Number(tongSX ?? 0).toLocaleString('vi-VN')}</td>

                                <td>${Math.round(item.Tien_vnd ?? 0)}</td>
                                <td>${item.hang_hoa?.Dvt ?? ''}</td>
                                <td>
                                    <div class="progress">
                                        <div class="progress-bar ${barColor}" style="width:${Math.min(pct, 100)}%">
                                            ${pct}%
                                        </div>
                                    </div>
                                </td>
                                <td>${item.DgiaiV ?? ''}</td>
                            </tr>
                        `;
                        tbody.insertAdjacentHTML('beforeend', row);
                    });

                    const pctTong = soluongGO > 0 ? (tongSX / soluongGO * 100).toFixed(1) : 0;
                    const barColor = pctTong >= 90 ? 'bg-success' : pctTong >= 60 ? 'bg-warning' : 'bg-danger';

                    const subtotalRow = `
                        <tr class="subtotal-row">
                            <td colspan="7">${soct}</td>
                            <td colspan="1">${tongSX.toLocaleString('vi-VN')}</td>
                            <td colspan="3"></td>
                            <td>
                                <div class="progress">
                                    <div class="progress-bar ${barColor}" style="width:${Math.min(pctTong, 100)}%">
                                        ${pctTong}%
                                    </div>
                                </div>
                            </td>
                            <td></td>
                        </tr>
                    `;
                    tbody.insertAdjacentHTML('beforeend', subtotalRow);
                });

                // 3. Gọi hàm cập nhật trạng thái máy sau khi có dữ liệu SX
                deriveMachineStatusFromSXData(filteredData);

            } catch (error) {
                console.error("Lỗi tải dữ liệu SX:", error);
                tbody.innerHTML = `<tr><td colspan="13" class="text-danger text-center">Lỗi tải dữ liệu SX!</td></tr>`;
                // Nếu lỗi, vẫn gọi hàm status với dữ liệu rỗng để cập nhật bảng máy
                deriveMachineStatusFromSXData([]);
            } finally {
                table.classList.remove('refreshing');
            }
        }

        // Click ảnh để phóng to (Giữ nguyên)
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('clickable-image')) {
                const modalImg = document.getElementById('modalImage');
                modalImg.src = e.target.src;
                const modal = new bootstrap.Modal(document.getElementById('imageModal'));
                modal.show();
            }
        });

        // Gọi lần đầu & auto refresh mỗi 10s
        loadSXData();
        setInterval(loadSXData, 10000);
    </script>
</body>

</html>
