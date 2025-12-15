<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CÁC LỆNH SẢN XUẤT 24 GIỜ QUA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="{{ asset('css/tivicss.css') }}" rel="stylesheet">
</head>

<body>
    <div class="refresh-indicator" id="refreshIndicator">
        <div class="spinner-border" role="status"></div>
        <span>Đang cập nhật...</span>
    </div>

    <div class="container-fluid">
        <div class="dashboard-header">
            <h1 class="text-center mb-3">
                <i class="bi bi-clipboard-data"></i> LỆNH ĐANG SẢN XUẤT TRONG 24 GIỜ QUA
            </h1>
            <div class="text-center">
                <span class="update-info">
                    <i class="bi bi-arrow-clockwise"></i>
                    Tự động cập nhật mỗi 20 giây | Lần cập nhật cuối: <strong id="lastUpdate">---</strong>
                </span>
            </div>
        </div>

        <div id="cardsContainer" class="row g-4 px-3">
            <div class="col-12">
                <div class="text-center py-5">
                    <div class="spinner-border text-light" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Đang tải...</span>
                    </div>
                    <p class="mt-3 text-white fs-5">Đang tải dữ liệu...</p>
                </div>
            </div>
        </div>
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
                    <h5 class="modal-title" id="detailModalTitle">
                        <i class="bi bi-info-circle"></i> Chi Tiết Lệnh Sản Xuất
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailModalBody">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Đang tải...</span>
                        </div>
                        <p class="mt-3 text-muted">Đang tải dữ liệu...</p>
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
            xhr.timeout = 8000;

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

        function updateLastRefreshTime() {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('vi-VN');
            document.getElementById('lastUpdate').textContent = timeStr;
        }

        function loadDetailLenh(soCt) {
            const modal = new bootstrap.Modal(document.getElementById('detailModal'));
            const modalBody = document.getElementById('detailModalBody');
            const modalTitle = document.getElementById('detailModalTitle');

            modalTitle.innerHTML = `<i class="bi bi-info-circle"></i> Chi Tiết Lệnh: ${soCt}`;
            modalBody.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Đang tải...</span>
                    </div>
                    <p class="mt-3 text-muted">Đang tải dữ liệu...</p>
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

                let summaryHtml = `
    <div class="summary-card">
        <h5 class="mb-3"><i class="bi bi-clipboard-check"></i> Tổng Quan Lệnh ${soCt}</h5>
        <div class="summary-item">
            <span><i class="bi bi-person-badge"></i> Khách hàng:</span>
            <strong>${orderInfo.khach_hang?.Ten_kh || 'N/A'}</strong>
        </div>
        <div class="summary-item">
            <span><i class="bi bi-box-seam"></i> Sản phẩm:</span>
            <strong>${orderInfo.hang_hoa?.Ten_hh || 'N/A'}</strong>
        </div>
        <div class="summary-item">
            <span><i class="bi bi-cart-check"></i> Số lượng đơn:</span>
            <strong>${Number(summary.so_luong_don).toLocaleString('vi-VN')} ${orderInfo.hang_hoa?.Dvt || ''}</strong>
        </div>
        <div class="summary-item">
            <span><i class="bi bi-check-circle"></i> Đã sản xuất:</span>
            <strong class="text-success">${Number(summary.total_sx).toLocaleString('vi-VN')}</strong>
            <small class="text-muted">(${summary.percent_complete}%)</small>
        </div>
        <div class="summary-item">
            <span><i class="bi bi-hourglass-split"></i> Còn lại SX:</span>
            <strong class="text-warning">${Number(summary.con_thieu).toLocaleString('vi-VN')}</strong>
        </div>
        <div class="summary-item">
            <span><i class="bi bi-x-circle"></i> Tổng lỗi:</span>
            <strong class="text-danger">${Number(summary.total_loi).toLocaleString('vi-VN')}</strong>
        </div>
        <hr>
        <div class="summary-item">
            <span><i class="bi bi-box-arrow-right"></i> Đã xuất kho:</span>
            <strong class="${summary.total_xuat_kho >= summary.so_luong_don ? 'text-success' : 'text-warning'}">
                ${Number(summary.total_xuat_kho || 0).toLocaleString('vi-VN')}
            </strong>
            <small class="text-muted">(${summary.percent_xuat_kho || 0}%)</small>
        </div>
        <div class="summary-item">
            <span><i class="bi bi-clock-history"></i> Còn lại xuất kho:</span>
            <strong class="${summary.con_thieu_xuat_kho <= 0 ? 'text-success' : 'text-danger'}">
                ${Number(summary.con_thieu_xuat_kho || 0).toLocaleString('vi-VN')}
            </strong>
            ${summary.con_thieu_xuat_kho <= 0 ? '<span class="badge bg-success ms-2"><i class="bi bi-check-lg"></i> Đã xuất đủ</span>' : '<span class="badge bg-danger ms-2"><i class="bi bi-x-lg"></i> Chưa đủ</span>'}
        </div>
    </div>
`;

                if (summary.by_cong_doan && summary.by_cong_doan.length > 0) {
                    summaryHtml += `
                        <div class="mb-4">
                            <h6 class="mb-3"><i class="bi bi-diagram-3"></i> Tổng Hợp Theo Công Đoạn</h6>
                            <div class="row">
                    `;

                    summary.by_cong_doan.forEach(cd => {
                        summaryHtml += `
                            <div class="col-md-4 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <span class="congdoan-badge">${cd.Ma_ko}</span>
                                        <div class="mt-3">
                                            <small><i class="bi bi-check-square"></i> Số lượng:</small> <strong>${Number(cd.total_sx).toLocaleString('vi-VN')}</strong><br>
                                            <small><i class="bi bi-rulers"></i> SL khác (mm,g):</small> <strong>${Number(cd.total_soluongkhac).toLocaleString('vi-VN')}</strong><br>
                                            <small><i class="bi bi-exclamation-triangle"></i> Lỗi:</small> <strong class="text-danger">${Number(cd.total_loi).toLocaleString('vi-VN')}</strong><br>
                                            <small><i class="bi bi-calendar2-week"></i> Số ca SX:</small> <strong>${cd.count}</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });

                    summaryHtml += `</div></div>`;
                }

                let nxDetailsHtml = '';

                if (nxDetails.length === 0) {
                    nxDetailsHtml = `
                        <div class="alert alert-danger mb-4" role="alert">
                            <i class="bi bi-exclamation-triangle-fill"></i> <strong>Chưa có định mức!</strong> Vui lòng cập nhật định mức cho lệnh sản xuất này.
                        </div>
                        
                        <h6 class="mb-3 text-muted"><i class="bi bi-arrow-left-right"></i> Chi Tiết Nhập Xuất</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered detail-table">
                                <thead>
                                    <tr>
                                        <th>STT</th>
                                        <th>Mã HH</th>
                                        <th>Tên hàng</th>
                                        <th>Công đoạn</th>
                                        <th>Số đề xuất</th>
                                        <th>Đã xuất</th>
                                        <th>Đã sử dụng</th>
                                        <th>Dư/Thiếu</th>
                                        <th>ĐVT</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">
                                            <em>Không có dữ liệu định mức</em>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    `;

                    if (ckDetails.length > 0) {
                        nxDetailsHtml += `
                            <div class="alert alert-warning mt-3">
                                <i class="bi bi-info-circle"></i> <strong>Lưu ý:</strong> Có ${ckDetails.length} phiếu xuất kho nhưng chưa có định mức để so sánh.
                            </div>
                        `;
                    }
                } else {
                    const soLuongDon = Number(summary.so_luong_don || 0);

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

                    const nxMap = {};
                    nxDetails.forEach(nx => {
                        nxMap[nx.Ma_hh] = nx;
                    });

                    const daSuDungMap = {};
                    nxDetails2025.forEach(nx => {
                        const key = nx.Ma_hh;
                        daSuDungMap[key] = (daSuDungMap[key] || 0) + Number(nx.Soluong || 0);
                    });

                    nxDetailsHtml = `
                        <h6 class="mb-3"><i class="bi bi-arrow-left-right"></i> Chi Tiết Nhập Xuất (${nxDetails.length} định mức, ${ckDetails.length} xuất kho, ${nxDetails2025.length} đã sử dụng)</h6>
                        
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
                                        <th>Đã sử dụng</th>
                                        <th>Dư/Thiếu</th>
                                        <th>ĐVT</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;

                    nxDetails.forEach((item, idx) => {
                        const dinhMucDonVi = Number(item.Soluong || 0);
                        const dinhMucDeXuat = dinhMucDonVi * soLuongDon;
                        const key = item.Ma_hh;
                        const daXuat = ckMap[key]?.total || 0;
                        const daSuDung = daSuDungMap[key] || 0;
                        const duThieu = daXuat - dinhMucDeXuat;

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
                                <td>
                                    <strong class="text-info">${daSuDung.toLocaleString('vi-VN')}</strong>
                                    ${daSuDung === 0 ? '<br><small class="text-muted">(chưa dùng)</small>' : ''}
                                </td>
                                <td class="${duThieuClass}">${duThieuText}</td>
                                <td>${item.hang_hoa?.Dvt || ''}</td>
                            </tr>
                        `;
                    });

                    Object.entries(ckMap).forEach(([key, data], idx) => {
                        if (!nxMap[key]) {
                            const firstCK = data.items[0];
                            const soLuong = data.total;
                            const daSuDung = daSuDungMap[key] || 0;

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

                    nxDetailsHtml += `</tbody></table></div>`;
                }

                let detailTableHtml = `
                    <h6 class="mb-3"><i class="bi bi-list-check"></i> Chi Tiết Sản Xuất (${sxDetails.length} bản ghi)</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover detail-table">
                            <thead>
                                <tr>
                                    <th>STT</th>
                                    <th>Ngày ra phiếu</th>
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
                            <td>${item.Ngay_ct || ''}</td>
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

                detailTableHtml += `</tbody></table></div>`;

                modalBody.innerHTML = summaryHtml + nxDetailsHtml + detailTableHtml;
            });
        }

        function loadSXData() {
            if (isRefreshing) return;

            isRefreshing = true;

            const container = document.querySelector('#cardsContainer');
            const refreshIndicator = document.getElementById('refreshIndicator');

            refreshIndicator.classList.add('active');

            tvFetch("/api/tivi/sx-data", function(response, error) {
                try {
                    if (error || !response) {
                        console.error("Lỗi tải dữ liệu:", error);
                        container.innerHTML =
                            `<div class="col-12"><div class="alert alert-danger text-center">Lỗi tải dữ liệu: ${error?.message || 'Unknown error'}</div></div>`;
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

                    if (filteredData.length === 0) {
                        container.innerHTML =
                            `<div class="col-12"><div class="alert alert-warning text-center">Không có lệnh SX trong 24h qua</div></div>`;
                        return;
                    }

                    const groups = {};
                    filteredData.forEach(item => {
                        const key = item.So_ct_go ?? 'Chưa có lệnh';
                        if (!groups[key]) groups[key] = [];
                        groups[key].push(item);
                    });

                    container.innerHTML = "";

                    Object.entries(groups).forEach(([soct, rows]) => {
                        const firstItem = rows[0];
                        const tongSX = Number(totalBySoct?.[firstItem.So_dh] ?? 0);
                        const soluongGO = Number(firstItem.Soluong_go ?? 0);
                        const soThieu = soluongGO - tongSX;
                        const pct = soluongGO > 0 ? ((tongSX / soluongGO) * 100).toFixed(1) : 0;

                        const barColor = pct >= 90 ? 'bg-success' : pct >= 60 ? 'bg-warning' : 'bg-danger';
                        const statusColor = soThieu > 0 ? 'danger' : 'success';
                        const statusIcon = soThieu > 0 ? 'exclamation-triangle-fill' : 'check-circle-fill';

                        const card = `
                            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                <div class="product-card" onclick="loadDetailLenh('${firstItem.So_dh}')">
                                    <div class="product-image-wrapper">
                                        <img src="/hinh_hh/HH_${firstItem.hang_hoa.Ma_hh}/${firstItem.hang_hoa.Pngpath}" 
                                             alt="${firstItem.hang_hoa.Ten_hh}" 
                                             class="product-image"
                                             onclick="event.stopPropagation(); showImageModal(this.src)">
                                        <div class="status-badge badge-${statusColor}">
                                            <i class="bi bi-${statusIcon}"></i>
                                        </div>
                                    </div>
                                    <div class="product-info">
                                        <h6 class="product-order">${soct}</h6>
                                        <p class="product-name">${firstItem.hang_hoa?.Ten_hh || ''}</p>
                                        <div class="progress-info">
                                            <div class="progress-numbers">
                                                <span class="text-success fw-bold">${tongSX.toLocaleString('vi-VN')}</span>
                                                <span class="text-muted">/</span>
                                                <span class="fw-bold">${soluongGO.toLocaleString('vi-VN')}</span>
                                            </div>
                                            <div class="progress mt-2">
                                                <div class="progress-bar ${barColor}" style="width: ${Math.min(pct, 100)}%">
                                                    ${pct}%
                                                </div>
                                            </div>
                                        </div>
                                        <div class="remaining-info mt-2">
                                            <small class="text-${statusColor}">
                                                <i class="bi bi-${statusIcon}"></i> 
                                                ${soThieu > 0 ? 'Thiếu' : 'Dư'}: <strong>${Math.abs(soThieu).toLocaleString('vi-VN')}</strong>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                        container.insertAdjacentHTML('beforeend', card);
                    });

                    updateLastRefreshTime();

                } catch (err) {
                    console.error("Lỗi xử lý dữ liệu:", err);
                    container.innerHTML =
                        `<div class="col-12"><div class="alert alert-danger text-center">Lỗi xử lý dữ liệu!</div></div>`;
                } finally {
                    refreshIndicator.classList.remove('active');
                    isRefreshing = false;
                }
            });
        }

        function showImageModal(src) {
            const modalImg = document.getElementById('modalImage');
            modalImg.src = src;
            const modal = new bootstrap.Modal(document.getElementById('imageModal'));
            modal.show();
        }

        loadSXData();
        refreshInterval = setInterval(loadSXData, 20000);

        window.addEventListener('beforeunload', function() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        });
    </script>
</body>

</html>
