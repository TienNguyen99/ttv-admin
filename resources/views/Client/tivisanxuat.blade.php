<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CÁC LỆNH SẢN XUẤT 24 GIỜ QUA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/tivicss.css') }}" rel="stylesheet">

</head>

<body>
    <div class="refresh-indicator" id="refreshIndicator">
        Đang cập nhật...
    </div>

    <div class="container-fluid mt-4">
        <h1 class="text-center mb-3">LỆNH ĐANG SẢN XUẤT TRONG 24 GIỜ QUA</h1>
        <p class="text-center text-muted">
            <small>Tự động cập nhật mỗi 10 giây | Lần cập nhật cuối: <span id="lastUpdate">---</span></small>
        </p>

        <table class="table table-bordered table-striped text-center align-middle" id="sxTable">
            <thead>
                <tr>
                    <th>STT</th>
                    <th>Ngày nhập phiếu</th>
                    <th>Lệnh</th>
                    <th>Mã HH</th>
                    <th>Hình ảnh</th>
                    <th>Tên Hàng</th>
                    <th>Công đoạn</th>
                    <th>Tên công nhân</th>
                    <th>Số lượng đơn</th>
                    <th>Số lượng đơn vị khác(mm,g)</th>
                    <th>Sản xuất</th>
                    <th>Tổng SX</th>
                    <th>Lỗi</th>
                    <th>ĐVT</th>
                    <th>%</th>
                    <th>Ghi chú</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="16">Đang tải dữ liệu...</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Modal Phóng to ảnh -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-transparent border-0 shadow-none position-relative">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3"
                    data-bs-dismiss="modal" aria-label="Close"></button>
                <img id="modalImage" src="" alt="Ảnh phóng to" class="w-100 rounded-3">
            </div>
        </div>
    </div>

    <!-- Modal Chi Tiết Lệnh -->
    <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailModalTitle">Chi Tiết Lệnh Sản Xuất</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailModalBody">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Đang tải...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let isRefreshing = false;
        let refreshInterval = null;

        function tvFetch(url, callback) {
            const xhr = new XMLHttpRequest();
            xhr.open("GET", url, true);
            xhr.timeout = 8000; // 8 giây timeout

            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            const json = JSON.parse(xhr.responseText);
                            callback(json, null);
                        } catch (e) {
                            console.error("TV JSON parse error:", e);
                            callback(null, e);
                        }
                    } else {
                        callback(null, new Error(`HTTP ${xhr.status}`));
                    }
                }
            };

            xhr.onerror = function() {
                callback(null, new Error('Network error'));
            };

            xhr.ontimeout = function() {
                callback(null, new Error('Request timeout'));
            };

            xhr.send();
        }

        /**
         * Hàm cập nhật thời gian refresh
         */
        function updateLastRefreshTime() {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('vi-VN');
            document.getElementById('lastUpdate').textContent = timeStr;
        }

        /**
         * Hàm tải chi tiết lệnh sản xuất
         */
        function loadDetailLenh(soCt) {
            const modal = new bootstrap.Modal(document.getElementById('detailModal'));
            const modalBody = document.getElementById('detailModalBody');
            const modalTitle = document.getElementById('detailModalTitle');

            modalTitle.textContent = `Chi Tiết Lệnh: ${soCt}`;
            modalBody.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Đang tải...</span>
                    </div>
                </div>
            `;

            modal.show();

            tvFetch(`/api/tivi/sx-detail/${soCt}`, function(response, error) {
                if (error || !response || !response.success) {
                    modalBody.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i> ${error?.message || response?.message || 'Không thể tải dữ liệu'}
                        </div>
                    `;
                    return;
                }

                const {
                    orderInfo,
                    sxDetails,
                    summary,
                    nxDetails,
                    ckDetails,
                    nxDetails2025

                } = response.data;

                // Render summary card
                let summaryHtml = `
                    <div class="summary-card">
                        <h5 class="mb-3">Tổng Quan Lệnh ${soCt}</h5>
                        <div class="summary-item">
                            <span>Khách hàng:</span>
                            <strong>${orderInfo.khach_hang?.Ten_kh || 'N/A'}</strong>
                        </div>
                        <div class="summary-item">
                            <span>Sản phẩm:</span>
                            <strong>${orderInfo.hang_hoa?.Ten_hh || 'N/A'}</strong>
                        </div>
                        <div class="summary-item">
                            <span>Số lượng đơn:</span>
                            <strong>${Number(summary.so_luong_don).toLocaleString('vi-VN')} ${orderInfo.hang_hoa?.Dvt || ''}</strong>
                        </div>
                        <div class="summary-item">
                            <span>Đã sản xuất:</span>
                            <strong class="text-success">${Number(summary.total_sx).toLocaleString('vi-VN')}</strong>
                        </div>
                        <div class="summary-item">
                            <span>Còn thiếu:</span>
                            <strong class="text-warning">${Number(summary.con_thieu).toLocaleString('vi-VN')}</strong>
                        </div>
                        <div class="summary-item">
                            <span>Tổng lỗi:</span>
                            <strong class="text-danger">${Number(summary.total_loi).toLocaleString('vi-VN')}</strong>
                        </div>
                        <div class="summary-item">
                            <span>📈 Tiến độ:</span>
                            <strong>${summary.percent_complete}%</strong>
                        </div>
                    </div>
                `;

                // Render summary by công đoạn
                if (summary.by_cong_doan && summary.by_cong_doan.length > 0) {
                    summaryHtml += `
                        <div class="mb-4">
                            <h6 class="mb-3">Tổng Hợp Theo Công Đoạn</h6>
                            <div class="row">
                    `;

                    summary.by_cong_doan.forEach(cd => {
                        summaryHtml += `
                            <div class="col-md-4 mb-2">
                                <div class="card">
                                    <div class="card-body">
                                        <span class="congdoan-badge">${cd.Ma_ko}</span>
                                        <div class="mt-2">
                                            <small>Số lượng:</small> <strong>${Number(cd.total_sx).toLocaleString('vi-VN')}</strong><br>
                                            <small>Số lượng khác (mm,g):</small> <strong>${Number(cd.total_soluongkhac).toLocaleString('vi-VN')}</strong><br>
                                            <small>Lỗi:</small> <strong class="text-danger">${Number(cd.total_loi).toLocaleString('vi-VN')}</strong><br>
                                            <small>Số ca SX:</small> <strong>${cd.count}</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });

                    summaryHtml += `
                            </div>
                        </div>
                    `;
                }
                // Render NX details gồm STT,Ma_ko,Ma_hh,Ma_sp,Soluong
                // Thay thế phần render NX details trong function loadDetailLenh

                // Lấy số lượng đơn từ summary
                const soLuongDon = Number(summary.so_luong_don || 0);

                // Tạo map xuất kho theo Ma_hh (không cần Ma_ko) để tra cứu nhanh
                const ckMap = {};
                ckDetails.forEach(ck => {
                    const key = ck.Ma_hh;
                    if (!ckMap[key]) {
                        ckMap[key] = {
                            total: 0,
                            items: []
                        };
                    }
                    ckMap[key].total += Number(ck.Soluong || 0);
                    ckMap[key].items.push(ck);
                });

                // Tạo map định mức để check sau
                const nxMap = {};
                nxDetails.forEach(nx => {
                    const key = nx.Ma_hh;
                    nxMap[key] = nx;
                });
                // ← THÊM ĐOẠN CODE NÀY
                // Tạo map đã sử dụng từ nxDetails2025
                const daSuDungMap = {};
                nxDetails2025.forEach(nx => {
                    const key = nx.Ma_hh;
                    if (!daSuDungMap[key]) {
                        daSuDungMap[key] = 0;
                    }
                    daSuDungMap[key] += Number(nx.Soluong || 0);
                });

                // ===== BƯỚC 3: Cập nhật header table (dòng ~240) =====
                let nxDetailsHtml = `
    <h6 class="mb-3">Chi Tiết Nhập Xuất (${nxDetails.length} định mức, ${ckDetails.length} xuất kho, ${nxDetails2025.length} đã sử dụng)</h6>
    
    <div class="table-responsive">
        <table class="table table-bordered table-hover detail-table">
            <thead>
                <tr>
                    <th>STT</th>
                    <th>Mã HH</th>
                    <th>Tên hàng</th>
                    <th>Công đoạn</th>
                    <th>Số đề xuất</th>
                    <th>Đã xuất</th>
                    <th>Đã sử dụng</th>  <!-- ← THÊM CỘT NÀY -->
                    <th>Dư/Thiếu</th>
                    <th>ĐVT</th>
                </tr>
            </thead>
            <tbody>
`;

                // ===== BƯỚC 4: Thêm cột "Đã sử dụng" trong vòng lặp nxDetails (dòng ~260) =====
                nxDetails.forEach((item, idx) => {
                    const dinhMucDonVi = Number(item.Soluong || 0);
                    const dinhMucDeXuat = dinhMucDonVi * soLuongDon;
                    const key = item.Ma_hh;
                    const daXuat = ckMap[key]?.total || 0;
                    const daSuDung = daSuDungMap[key] || 0; // ← THÊM DÒNG NÀY
                    const duThieu = daXuat - dinhMucDeXuat;

                    // Xác định class cho cột dư/thiếu
                    let duThieuClass = '';
                    let duThieuText = '';
                    if (duThieu > 0) {
                        duThieuClass = 'text-success fw-bold';
                        duThieuText = `+${duThieu.toLocaleString('vi-VN')}`;
                    } else if (duThieu < 0) {
                        duThieuClass = 'text-danger fw-bold';
                        duThieuText = duThieu.toLocaleString('vi-VN');
                    } else {
                        duThieuClass = 'text-muted';
                        duThieuText = '0';
                    }

                    nxDetailsHtml += `
        <tr>
            <td>${idx + 1}</td>
            <td>${item.Ma_hh}</td>
            <td>${item.hang_hoa?.Ten_hh || ''}</td>
            <td><span class="congdoan-badge">${item.Ma_ko || ''}</span></td>
            <td>
                ${dinhMucDonVi.toLocaleString('vi-VN')} 
                <small class="text-muted">× ${soLuongDon.toLocaleString('vi-VN')}</small>
                <br>
                <strong class="text-primary">= ${dinhMucDeXuat.toLocaleString('vi-VN')}</strong>
            </td>
            <td>
                <strong class="text-success">${daXuat.toLocaleString('vi-VN')}</strong>
                ${daXuat === 0 ? '<br><small class="text-muted">(chưa xuất)</small>' : ''}
            </td>
            <!-- ← THÊM CELL NÀY -->
            <td>
                <strong class="text-info">${daSuDung.toLocaleString('vi-VN')}</strong>
                ${daSuDung === 0 ? '<br><small class="text-muted">(chưa dùng)</small>' : ''}
            </td>
            <td class="${duThieuClass}">${duThieuText}</td>
            <td>${item.hang_hoa?.Dvt || ''}</td>
        </tr>
    `;
                });

                // ===== BƯỚC 5: Thêm cột cho dòng xuất thêm (dòng ~300) =====
                Object.entries(ckMap).forEach(([key, data], idx) => {
                    if (!nxMap[key]) {
                        const firstCK = data.items[0];
                        const soLuong = data.total;
                        const daSuDung = daSuDungMap[key] || 0; // ← THÊM DÒNG NÀY

                        nxDetailsHtml += `
            <tr class="table-warning">
                <td>${nxDetails.length + idx + 1}</td>
                <td>${firstCK.Ma_hh}</td>
                <td>${firstCK.hang_hoa?.Ten_hh || ''}</td>
                <td><span class="congdoan-badge">${firstCK.Ma_ko || ''}</span></td>
                <td class="text-muted">
                    <small>(không có định mức)</small><br>
                    <strong>0</strong>
                </td>
                <td>
                    <strong class="text-success">${soLuong.toLocaleString('vi-VN')}</strong>
                    <br><small class="text-warning">(xuất thêm)</small>
                </td>
                <!-- ← THÊM CELL NÀY -->
                <td>
                    <strong class="text-info">${daSuDung.toLocaleString('vi-VN')}</strong>
                    ${daSuDung === 0 ? '<br><small class="text-muted">(chưa dùng)</small>' : ''}
                </td>
                <td class="text-warning fw-bold">+${soLuong.toLocaleString('vi-VN')}</td>
                <td>${firstCK.hang_hoa?.Dvt || ''}</td>
            </tr>
        `;
                    }
                });

                // ===== CUỐI CÙNG: Đóng tbody và table =====
                nxDetailsHtml += `
            </tbody>
        </table>
    </div>
`;


                // Render detail table
                let detailTableHtml = `
                    <h6 class="mb-3">Chi Tiết Sản Xuất (${sxDetails.length} bản ghi)</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover detail-table">
                            <thead>
                                <tr>
                                    <th>STT</th>
                                    <th>Số CT</th>
                                    <th>Mã HH</th>
                                    <th>Tên hàng</th>
                                    <th>Công đoạn</th>
                                    <th>Công nhân</th>
                                    <th>Số lượng</th>
                                    <th>Số lượng khác (mm,g)</th>
                                    <th>Lỗi</th>
                                    <th>ĐVT</th>
                                    <th>Ghi chú</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                sxDetails.forEach((item, idx) => {
                    detailTableHtml += `
                        <tr>
                            <td>${idx + 1}</td>
                            <td>${item.So_ct || ''}</td>
                            <td>${item.Ma_hh || ''}</td>
                            <td>${item.hang_hoa?.Ten_hh || ''}</td>
                            <td><span class="congdoan-badge">${item.Ma_ko || ''}</span></td>
                            <td>${item.nhan_vien?.Ten_nv || ''}</td>
                            <td><strong>${Number(item.Soluong || 0).toLocaleString('vi-VN')}</strong></td>
                            <td><strong>${Number(item.Dgbanvnd || 0).toLocaleString('vi-VN')}</strong></td>
                            <td class="text-danger">${Math.round(item.Tien_vnd || 0)}</td>
                            <td>${item.hang_hoa?.Dvt || ''}</td>
                            <td>${item.DgiaiV || ''}</td>
                        </tr>
                    `;
                });

                detailTableHtml += `
                            </tbody>
                        </table>
                    </div>
                `;

                modalBody.innerHTML = summaryHtml + nxDetailsHtml + detailTableHtml;
            });
        }

        /**
         * Hàm tải dữ liệu sản xuất
         */
        function loadSXData() {
            // Tránh gọi API nếu lần trước chưa xong
            if (isRefreshing) {
                return;
            }

            // console.log('🔄 Bắt đầu refresh lúc:', new Date().toLocaleTimeString('vi-VN'));
            isRefreshing = true;

            const table = document.querySelector('#sxTable');
            const tbody = table.querySelector('tbody');
            const refreshIndicator = document.getElementById('refreshIndicator');

            table.classList.add('refreshing');
            refreshIndicator.classList.add('active');

            tvFetch("/api/tivi/sx-data", function(response, error) {
                try {
                    if (error || !response) {
                        console.error("Lỗi tải dữ liệu:", error);
                        tbody.innerHTML =
                            `<tr><td colspan="16" class="text-danger text-center">Lỗi tải dữ liệu: ${error?.message || 'Unknown error'}</td></tr>`;
                        return;
                    }

                    const data = response.data || [];
                    const totalBySoct = response.totalBySoct || {};

                    const now = new Date();
                    const cutoff = new Date(now.getTime() - 24 * 60 * 60 * 1000);
                    const filteredData = data.filter(item => {
                        const ngay = new Date(item.UserNgE);
                        return ngay >= cutoff && ngay <= now;
                    });

                    tbody.innerHTML = "";

                    if (filteredData.length === 0) {
                        tbody.innerHTML =
                            `<tr><td colspan="16" class="text-center text-warning">Không có lệnh SX trong 24h qua</td></tr>`;
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

                        rows.forEach((item, index) => {
                            tongSX = Number(totalBySoct?.[item.So_dh] ?? 0);
                            const pct = soluongGO > 0 ? (item.Soluong / soluongGO * 100).toFixed(
                                1) : 0;
                            const barColor = pct >= 90 ? 'bg-success' : pct >= 60 ? 'bg-warning' :
                                'bg-danger';

                            const imageHtml = `
                                <img src="/hinh_hh/HH_${item.hang_hoa.Ma_hh}/${item.hang_hoa.Pngpath}" 
                                     alt="${item.hang_hoa.Ten_hh}" class="clickable-image">
                            `;

                            const lenhHtml =
                                `<span class="clickable-lenh" onclick="loadDetailLenh('${item.So_dh}')">${item.So_ct_go ?? ''}</span>`;

                            const row = `
                                <tr>
                                    <td>${index + 1}</td>
                                    <td>${item.UserNgE ? new Date(item.UserNgE).toLocaleDateString('vi-VN') : ''}</td>
                                    <td>${lenhHtml}</td>
                                    <td>${item.Ma_hh ?? ''}</td>
                                    <td>${imageHtml}</td>
                                    <td>${item.hang_hoa?.Ten_hh ?? ''}</td>
                                    <td>${item.Ma_ko ?? ''}</td>
                                    <td>${item.nhan_vien?.Ten_nv ?? ''}</td>
                                    <td>${soluongGO.toLocaleString('vi-VN')}</td>
                                    <td>${Number(item.Dgbanvnd ?? 0).toLocaleString('vi-VN')}</td>
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
                        const soThieu = soluongGO - tongSX;

                        const subtotalRow = `
    <tr class="subtotal-row ${soThieu >= 0 ? 'table-danger' : 'table-success'}">
        <td colspan="16">
            LỆNH ${soct}: ĐÃ SẢN XUẤT ĐƯỢC ${Number(tongSX ?? 0).toLocaleString('vi-VN')} 
            CÒN THIẾU ${Number(soThieu ?? 0).toLocaleString('vi-VN')}
        </td>
    </tr>
`;
                        tbody.insertAdjacentHTML('beforeend', subtotalRow);
                    });

                    // console.log('Refresh thành công, tải được', filteredData.length, 'bản ghi');
                    updateLastRefreshTime();

                } catch (err) {
                    console.error("Lỗi xử lý dữ liệu:", err);
                    tbody.innerHTML =
                        `<tr><td colspan="16" class="text-danger text-center">Lỗi xử lý dữ liệu!</td></tr>`;
                } finally {
                    table.classList.remove('refreshing');
                    refreshIndicator.classList.remove('active');
                    isRefreshing = false;
                }
            });
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

        // Khởi động
        loadSXData();
        // Set interval và lưu reference để có thể clear nếu cần
        refreshInterval = setInterval(function() {

            loadSXData();
        }, 10000);

        // Cleanup khi đóng trang
        window.addEventListener('beforeunload', function() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        });
    </script>
</body>

</html>
