<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tivi Sản Xuất - Lệnh SX 24h qua</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f9fafc;
            color: #1e293b;
            font-size: 18px;
        }

        h1 {
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
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="12">Đang tải dữ liệu...</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Modal ảnh -->
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
        async function loadSXData() {
            const table = document.querySelector('#sxTable');
            const tbody = table.querySelector('tbody');
            table.classList.add('refreshing');

            try {
                const res = await fetch('http://192.168.1.13:8888/api/tivi/sx-data');
                const {
                    data,
                    totalBySoct
                } = await res.json();

                // ✅ Lọc dữ liệu trong vòng 24h qua
                const now = new Date();
                const cutoff = new Date(now.getTime() - 24 * 60 * 60 * 1000);
                const filteredData = data.filter(item => {
                    const ngay = new Date(item.Ngay_ct);
                    return ngay >= cutoff && ngay <= now;
                });

                tbody.innerHTML = '';

                if (filteredData.length === 0) {
                    tbody.innerHTML =
                        `<tr><td colspan="12" class="text-center text-warning">Không có lệnh SX trong 24h qua</td></tr>`;
                    return;
                }

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
                        tongSX += Number(item.Soluong ?? 0);
                        const pct = soluongGO > 0 ? (item.Soluong / soluongGO * 100).toFixed(1) : 0;
                        const barColor = pct >= 90 ? 'bg-success' : pct >= 60 ? 'bg-warning' :
                            'bg-danger';

                        const imageHtml = `
                            <img src="http://192.168.1.13:8888/hinh_hh/HH_${item.hang_hoa.Ma_so}/${item.hang_hoa.Pngpath}" 
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
                                <td>${Number(totalBySoct?.[item.So_dh] ?? 0).toLocaleString('vi-VN')}</td>

                                <td>${Math.round(item.Tien_vnd ?? 0)}</td>
                                <td>${item.hang_hoa?.Dvt ?? ''}</td>
                                <td>
                                    <div class="progress">
                                        <div class="progress-bar ${barColor}" style="width:${Math.min(pct, 100)}%">
                                            ${pct}%
                                        </div>
                                    </div>
                                </td>
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
                            <td colspan="2"></td>
                            <td>
                                <div class="progress">
                                    <div class="progress-bar ${barColor}" style="width:${Math.min(pctTong, 100)}%">
                                        ${pctTong}%
                                    </div>
                                </div>
                            </td>
                        </tr>
                    `;
                    tbody.insertAdjacentHTML('beforeend', subtotalRow);
                });

            } catch (error) {
                console.error(error);
                tbody.innerHTML = `<tr><td colspan="12" class="text-danger text-center">Lỗi tải dữ liệu!</td></tr>`;
            } finally {
                table.classList.remove('refreshing');
            }
        }

        // Click ảnh để phóng to
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
