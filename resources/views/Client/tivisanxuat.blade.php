<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon" />
    <title>CÔNG TY TNHH NHÃN THỜI GIAN VIỆT TIẾN - BẢNG THEO DÕI SẢN XUẤT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="{{ asset('css/tivicss.css') }}" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    {{-- <div class="refresh-indicator" id="refreshIndicator">
        <div class="spinner-border" role="status"></div>
        <span>Đang cập nhật...</span>
    </div> --}}

    <div class="container-fluid">
        <div class="dashboard-header">
            <h1 class="text-center mb-3">
                BẢNG THEO DÕI LỆNH ĐANG SẢN XUẤT
            </h1>
            <div class="text-center mb-3">
                <span class="update-info">
                    <i class="bi bi-arrow-clockwise"></i>
                    Lần cập nhật cuối: <strong id="lastUpdate">---</strong>
                </span>
            </div>

            <!-- Time Range Switch -->
            <div class="text-center mb-3">
                <div class="btn-group time-range-switch" role="group">
                    <input type="radio" class="btn-check" name="timeRange" id="time24h" value="24h" checked
                        autocomplete="off">
                    <label class="btn btn-outline-light btn-sm" for="time24h">
                        <i class="bi bi-clock-history"></i> 24 Giờ
                    </label>

                    <input type="radio" class="btn-check" name="timeRange" id="timeAll" value="all"
                        autocomplete="off">
                    <label class="btn btn-outline-light btn-sm" for="timeAll">
                        <i class="bi bi-infinity"></i> Toàn Bộ
                    </label>
                </div>
            </div>

            <!-- Search by Order Number -->
            <div class="text-center mb-3">
                <div class="input-group" style="max-width: 300px; margin: 0 auto;">
                    <input type="text" class="form-control" id="searchOrderInput"
                        placeholder="Nhập số lệnh (VD: 4858)..."
                        style="background-color: rgba(255,255,255,0.95); color: #333; border-color: #ddd; font-weight: 600;">
                    <button class="btn btn-outline-light" type="button" id="clearSearchBtn" style="display:none;">
                        <i class="bi bi-x-circle"></i> Xóa
                    </button>
                </div>
            </div>

            <!-- Filter Buttons -->
            <div class="text-center">
                <div class="btn-group flex-wrap" role="group" aria-label="Filter buttons">
                    <button type="button" class="btn btn-sm btn-outline-light filter-btn active" data-filter="all">
                        <i class="bi bi-grid-3x3-gap"></i> Tất cả
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger filter-btn"
                        data-filter="chua-phan-tich">
                        <i class="bi bi-exclamation-circle"></i> Chưa phân tích
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger filter-btn"
                        data-filter="chua-xuat-vat-tu">
                        <i class="bi bi-box-arrow-right"></i> Chưa xuất vật tư
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger filter-btn" data-filter="chua-nhap-kho">
                        <i class="bi bi-box-arrow-in-down"></i> Chưa nhập kho
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger filter-btn" data-filter="chua-xuat-kho">
                        <i class="bi bi-truck"></i> Chưa xuất kho
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-warning filter-btn"
                        data-filter="xuat-du-vat-tu">
                        <i class="bi bi-arrow-up-circle"></i> Xuất dư vật tư
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger filter-btn" data-filter="thieu-hang">
                        <i class="bi bi-exclamation-diamond-fill"></i> Thiếu hàng
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success filter-btn" data-filter="du-hang">
                        <i class="bi bi-check2-circle"></i> Dư hàng
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success filter-btn" data-filter="hoan-tat">
                        <i class="bi bi-check-circle-fill"></i> Hoàn tất
                    </button>
                </div>
            </div>
        </div>

        <div id="cardsContainer" class="row g-4 px-3">
            <div class="col-12">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"
                        style="width: 3rem; height: 3rem; color: #3498db;">
                        <span class="visually-hidden">Đang tải...</span>
                    </div>
                    <p class="mt-3 fs-5" style="color: #2c3e50;">Đang tải dữ liệu...</p>
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

    <script src="{{ asset('js/updateLastRefreshTime.js') }}"></script>
    <script src="{{ asset('js/showImageModal.js') }}"></script>
    <script src="{{ asset('js/getCongDoanName.js') }}"></script>
    <script>
        let isRefreshing = false;
        let refreshInterval = null;
        let currentFilter = 'all';
        let currentTimeRange = '24h';
        let allCardsData = [];
        let searchQuery = '';
        let lastDataHash = null; // Lưu hash dữ liệu cũ để so sánh thay đổi

        // Search by order number
        const searchOrderInput = document.getElementById('searchOrderInput');
        const clearSearchBtn = document.getElementById('clearSearchBtn');

        if (searchOrderInput) {
            searchOrderInput.addEventListener('input', function(e) {
                searchQuery = this.value.trim();
                if (searchQuery.length > 0) {
                    clearSearchBtn.style.display = 'block';
                    searchOrderInput.style.borderColor = '#28a745';
                    searchOrderInput.style.boxShadow = '0 0 10px rgba(40, 167, 69, 0.3)';
                } else {
                    clearSearchBtn.style.display = 'none';
                    searchOrderInput.style.borderColor = 'rgba(255,255,255,0.3)';
                    searchOrderInput.style.boxShadow = 'none';
                }
                applyFilter();
            });

            // Add keyboard shortcut - Ctrl+K or Cmd+K to focus search
            document.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    searchOrderInput.focus();
                    searchOrderInput.select();
                }
            });

            // Auto focus search when typing numbers (global keyboard listener)
            document.addEventListener('keydown', function(e) {
                // Only trigger if focus is not on the search input and it's a number or alphanumeric
                if (document.activeElement !== searchOrderInput &&
                    (e.key.match(/[0-9]/) || e.key.match(/[a-zA-Z]/))) {
                    // Don't trigger if user is typing in another input
                    if (document.activeElement.tagName !== 'INPUT' &&
                        document.activeElement.tagName !== 'TEXTAREA' &&
                        !document.activeElement.closest('.modal')) {
                        searchOrderInput.focus();
                        searchOrderInput.value = '';
                        searchQuery = '';
                    }
                }
            });

            // Escape key to clear search
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && document.activeElement === searchOrderInput) {
                    searchOrderInput.value = '';
                    searchQuery = '';
                    clearSearchBtn.style.display = 'none';
                    searchOrderInput.style.borderColor = 'rgba(255,255,255,0.3)';
                    searchOrderInput.style.boxShadow = 'none';
                    applyFilter();
                    searchOrderInput.blur();
                }
            });
        }

        if (clearSearchBtn) {
            clearSearchBtn.addEventListener('click', function() {
                searchQuery = '';
                searchOrderInput.value = '';
                this.style.display = 'none';
                searchOrderInput.style.borderColor = 'rgba(255,255,255,0.3)';
                searchOrderInput.style.boxShadow = 'none';
                applyFilter();
            });
        }

        // Time Range Switch functionality
        document.querySelectorAll('input[name="timeRange"]').forEach(radio => {
            radio.addEventListener('change', function() {
                currentTimeRange = this.value;
                lastDataHash = null; // Reset hash để buộc load lại
                loadSXData();
            });
        });

        // Filter functionality
        document.addEventListener('click', function(e) {
            if (e.target.closest('.filter-btn')) {
                const btn = e.target.closest('.filter-btn');
                const filter = btn.getAttribute('data-filter');

                // Update active state
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                // Apply filter
                currentFilter = filter;
                applyFilter();
            }
        });

        function applyFilter() {
            const cards = document.querySelectorAll('#cardsContainer > div[data-status]');

            cards.forEach(card => {
                const status = card.getAttribute('data-status');
                const orderNumber = card.querySelector('.product-order')?.textContent.trim() || '';

                let shouldShow = true;

                // Check filter
                if (currentFilter !== 'all') {
                    if (!status.includes(currentFilter)) {
                        shouldShow = false;
                    }
                }

                // Check search query
                if (shouldShow && searchQuery) {
                    if (!orderNumber.includes(searchQuery)) {
                        shouldShow = false;
                    }
                }

                if (shouldShow) {
                    card.classList.remove('card-hidden');
                } else {
                    card.classList.add('card-hidden');
                }
            });
        }



        async function loadDetailLenh(soCt) {
            const modal = new bootstrap.Modal(document.getElementById('detailModal'));
            const modalBody = document.getElementById('detailModalBody');
            const modalTitle = document.getElementById('detailModalTitle');
            modalTitle.innerHTML = `<i class="bi bi-info-circle"></i> Chi Tiết Lệnh: ${soCt}`;
            modal.show();

            try {
                const res = await fetch(`/api/tivi/sx-detail/${soCt}`);
                const response = await res.json();

                if (!res.ok || !response || !response.success) {
                    modalBody.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i> ${response?.message || 'Không thể tải dữ liệu'}
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
                    daSuDungVatTu,
                    nhapTPKeToan
                } = response.data;

                // Cập nhật title với mã hàng
                modalTitle.innerHTML =
                    `<i class="bi bi-info-circle"></i> Chi Tiết Lệnh: ${soCt} - Mã hàng: ${orderInfo.hang_hoa?.Ma_so || 'N/A'} - Mã vụ việc: ${orderInfo.So_dh || 'N/A'}`;

                //SUMMARY CARD
                let summaryHtml = `<div class="summary-card">
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
                                        <span><i class="bi bi-box-arrow-in"></i> Đã nhập kho:</span>
                                        <strong class="${(Number(summary.total_nhap_kho || 0) >= Number(summary.so_luong_don || 0)) ? 'text-success' : 'text-warning'}">
                                            ${Number(summary.total_nhap_kho || 0).toLocaleString('vi-VN')}
                                        </strong>
                                        <small class="text-muted">(${Number(summary.percent_nhap_kho || 0).toFixed(2)}%)</small>
                                    </div>
                                    <div class="summary-item">
                                        <span><i class="bi bi-box-arrow-right"></i> Đã xuất hóa đơn:</span>
                                        <strong class="${(Number(summary.total_xuat_kho || 0) >= Number(summary.so_luong_don || 0)) ? 'text-success' : 'text-warning'}">
                                            ${Number(summary.total_xuat_kho || 0).toLocaleString('vi-VN')}
                                        </strong>
                                        <small class="text-muted">(${Number(summary.percent_xuat_kho || 0).toFixed(2)}%)</small>
                                    </div>
                                    <div class="summary-item">
                                        <span><i class="bi bi-clock-history"></i> Còn lại xuất hóa đơn:</span>
                                        <strong class="${summary.con_thieu_xuat_kho <= 0 ? 'text-success' : 'text-danger'}">
                                            ${Number(summary.con_thieu_xuat_kho || 0).toLocaleString('vi-VN')}
                                        </strong>
                                        ${summary.con_thieu_xuat_kho <= 0 ? '<span class="badge bg-success ms-2"><i class="bi bi-check-lg"></i> Đã xuất đủ</span>' : '<span class="badge bg-danger ms-2"><i class="bi bi-x-lg"></i> Chưa đủ</span>'}
                                    </div>
                                </div>
                            `;

                // Tổng hợp theo công đoạn
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
                                        <span class="congdoan-badge">${getCongDoanName(cd.Ma_ko)}</span>
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
                // CHI TIẾT NHẬP XUẤT KHI KHÔNG CÓ ĐỊNH MỨC
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

                }
                //-- CHI TIẾT NHẬP XUẤT KHI CÓ ĐỊNH MỨC
                else {
                    const soLuongDon = Number(summary.so_luong_don || 0);

                    // Map đã xuất vật tư (từ CK)
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

                    // Map định mức
                    const nxMap = {};
                    nxDetails.forEach(nx => {
                        nxMap[nx.Ma_hh] = nx;
                    });

                    // ===== FIX: Map đã sử dụng vật tư =====
                    const daSuDungMap = {};
                    daSuDungVatTu.forEach(item => {
                        daSuDungMap[item.Ma_hh] = Number(item.total_su_dung || 0);
                    });

                    nxDetailsHtml = `
                <h6 class="mb-3"><i class="bi bi-arrow-left-right"></i> Chi Tiết Nhập Xuất</h6>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> 
                    <strong>Tổng quan:</strong> ${nxDetails.length} định mức, ${ckDetails.length} phiếu xuất kho, ${daSuDungVatTu.length} loại vật tư đã sử dụng
                </div>
                
                <div class="table-responsive">
                    <table class="table table-bordered table-hover detail-table">
                        <thead>
                            <tr>
                                <th>STT</th>
                                <th>Mã HH</th>
                                <th>Tên hàng</th>
                                <th>Số đề xuất</th>
                                <th>Đã xuất vật tư</th>
                                <th>Xuất VT Dư/Thiếu</th>
                                <th>Đã sử dụng</th>
                                <th>VT dư cần trả lại</th>
                                <th>Đơn vị</th>
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
                        const xuatDuThieu = daXuat - dinhMucDeXuat;
                        const vatTuDu = daXuat - daSuDung;

                        // Class cho xuất dư/thiếu
                        let xuatDuThieuClass = '';
                        let xuatDuThieuText = '';
                        if (xuatDuThieu > 0) {
                            xuatDuThieuClass = 'text-warning fw-bold';
                            xuatDuThieuText = `+${xuatDuThieu.toLocaleString('vi-VN')}`;
                        } else if (xuatDuThieu < 0) {
                            xuatDuThieuClass = 'text-danger fw-bold';
                            xuatDuThieuText = xuatDuThieu.toLocaleString('vi-VN');
                        } else {
                            xuatDuThieuClass = 'text-success';
                            xuatDuThieuText = '0';
                        }

                        // Class cho vật tư dư
                        let vatTuDuClass = '';
                        let vatTuDuText = '';
                        if (vatTuDu > 0) {
                            vatTuDuClass = 'text-warning fw-bold';
                            vatTuDuText = `+${vatTuDu.toLocaleString('vi-VN')}`;
                        } else if (vatTuDu < 0) {
                            vatTuDuClass = 'text-danger fw-bold';
                            vatTuDuText = vatTuDu.toLocaleString('vi-VN');
                        } else {
                            vatTuDuClass = 'text-success';
                            vatTuDuText = '0';
                        }

                        nxDetailsHtml += `
                    <tr>
                        <td>${idx + 1}</td>
                        <td><code>${item.Ma_hh}</code></td>
                        <td>${item.hang_hoa?.Ten_hh || ''}</td>
                        <td>
                            ${dinhMucDonVi.toLocaleString('vi-VN')} 
                            <small class="text-muted">× ${soLuongDon.toLocaleString('vi-VN')}</small>
                            <br>
                            <strong class="text-primary">= ${dinhMucDeXuat.toLocaleString('vi-VN')}</strong>
                        </td>
                        <td>
                            <strong class="text-info">${daXuat.toLocaleString('vi-VN')}</strong>
                            ${daXuat === 0 ? '<br><small class="text-muted">(chưa xuất)</small>' : ''}
                        </td>
                        <td class="${xuatDuThieuClass}">
                            ${xuatDuThieuText}
                            ${xuatDuThieu > 0 ? '<br><small>(xuất thêm)</small>' : xuatDuThieu < 0 ? '<br><small>(xuất thiếu)</small>' : ''}
                        </td>
                        <td>
                            <strong class="text-success">${daSuDung.toLocaleString('vi-VN')}</strong>
                            ${daSuDung === 0 ? '<br><small class="text-muted">(chưa dùng)</small>' : ''}
                        </td>
                        <td class="${vatTuDuClass}">
                            ${vatTuDuText}
                            ${vatTuDu > 0 ? '<br><small>(cần trả lại)</small>' : vatTuDu < 0 ? '<br><small>(thiếu)</small>' : ''}
                        </td>
                        <td>${item.hang_hoa?.Dvt || ''}</td>
                    </tr>
                `;
                    });

                    // Xử lý các vật tư xuất thêm (không có trong định mức)
                    Object.entries(ckMap).forEach(([key, data], idx) => {
                        if (!nxMap[key]) {
                            const firstCK = data.items[0];
                            const soLuong = data.total;
                            const daSuDung = daSuDungMap[key] || 0;
                            const vatTuDu = soLuong - daSuDung;

                            nxDetailsHtml += `
                        <tr class="table-warning">
                            <td>${nxDetails.length + idx + 1}</td>
                            <td><code>${firstCK.Ma_hh}</code></td>
                            <td>${firstCK.hang_hoa?.Ten_hh || ''}</td>
                            <td class="text-muted">
                                <small>(không có định mức)</small><br>
                                <strong>0</strong>
                            </td>
                            <td>
                                <strong class="text-info">${soLuong.toLocaleString('vi-VN')}</strong>
                                <br><small class="text-warning">(xuất thêm)</small>
                            </td>
                            <td class="text-warning fw-bold">
                                +${soLuong.toLocaleString('vi-VN')}
                                <br><small>(xuất thêm)</small>
                            </td>
                            <td>
                                <strong class="text-success">${daSuDung.toLocaleString('vi-VN')}</strong>
                                ${daSuDung === 0 ? '<br><small class="text-muted">(chưa dùng)</small>' : ''}
                            </td>
                            <td class="text-warning fw-bold">
                                +${vatTuDu.toLocaleString('vi-VN')}
                                <br><small>(cần trả lại)</small>
                            </td>
                            <td>${firstCK.hang_hoa?.Dvt || ''}</td>
                        </tr>
                    `;
                        }
                    });

                    nxDetailsHtml += `</tbody></table></div>`;
                }
                //CHI TIẾT SẢN XUẤT
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
                // Duyệt qua sxDetails để tạo các dòng bảng
                sxDetails.forEach((item, idx) => {
                    detailTableHtml += `
                                        <tr>
                                            <td>${idx + 1}</td>
                                            <td>${item.Ngay_ct || ''}</td>
                                            <td>${item.So_ct || ''}</td>
                                            <td>${item.Ma_hh || ''}</td>
                                            <td>${item.hang_hoa?.Ten_hh || ''}</td>
                                            <td><span class="congdoan-badge">${getCongDoanName(item.Ma_ko || '')}</span></td>
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
                // Cập nhật nội dung modal
                modalBody.innerHTML = summaryHtml + nxDetailsHtml + detailTableHtml;
            } catch (err) {
                console.error('Lỗi tải chi tiết lệnh:', err);
                modalBody.innerHTML =
                    `<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> Lỗi tải dữ liệu</div>`;
            }
        }

        async function loadSXData() {
            if (isRefreshing) return;
            isRefreshing = true;
            const container = document.querySelector('#cardsContainer');
            // BẰNG SWEETALERT2 TOAST:
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer)
                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                }
            });

            Toast.fire({
                icon: 'info',
                title: 'Đang cập nhật dữ liệu...'
            });

            try {
                const res = await fetch("/api/tivi/sx-data");
                const response = await res.json();

                if (!res.ok || !response) {
                    // Thông báo lỗi
                    Toast.fire({
                        icon: 'error',
                        title: 'Lỗi tải dữ liệu!'
                    });
                    console.error("Lỗi tải dữ liệu:", response?.message || 'Unknown error');
                    container.innerHTML =
                        `<div class="col-12"><div class="alert alert-danger text-center">Lỗi tải dữ liệu: ${response?.message || 'Unknown error'}</div></div>`;
                    return;
                }

                // Tạo hash từ dữ liệu để so sánh thay đổi
                const currentDataHash = JSON.stringify(response);

                // Nếu dữ liệu không thay đổi, không update UI
                if (lastDataHash === currentDataHash) {
                    Toast.fire({
                        icon: 'info',
                        title: 'Không có dữ liệu mới'
                    });
                    isRefreshing = false;
                    return;
                }

                // Lưu hash dữ liệu mới
                lastDataHash = currentDataHash;

                const data = response.data || [];
                const totalBySoct = response.totalBySoct || {};
                const statusMap = response.statusMap || {};
                const tonKho = response.tonKho || {};

                const now = new Date();
                const cutoff = new Date(now.getTime() - 24 * 60 * 60 * 1000);

                // Lọc dữ liệu theo time range
                let filteredData;
                if (currentTimeRange === '24h') {
                    filteredData = data.filter(item => {
                        const ngay = new Date(item.UserNgE);
                        return ngay >= cutoff && ngay <= now;
                    });
                } else {
                    // Hiển thị toàn bộ
                    filteredData = data;
                }

                if (filteredData.length === 0) {
                    const message = currentTimeRange === '24h' ?
                        'Không có lệnh SX trong 24h qua' :
                        'Không có dữ liệu lệnh SX';
                    container.innerHTML =
                        `<div class="col-12"><div class="alert alert-warning text-center">${message}</div></div>`;
                    return;
                }

                // Nhóm theo Nhom1 trước, sau đó nhóm theo So_ct_go
                const groupsByNhom = {};
                filteredData.forEach(item => {
                    const nhom = item.hang_hoa?.Nhom1 || 'Khác';
                    if (!groupsByNhom[nhom]) {
                        groupsByNhom[nhom] = {};
                    }
                    const key = item.So_ct_go ?? 'Chưa có lệnh';
                    if (!groupsByNhom[nhom][key]) {
                        groupsByNhom[nhom][key] = [];
                    }
                    groupsByNhom[nhom][key].push(item);
                });

                container.innerHTML = "";

                // Duyệt theo từng danh mục
                Object.entries(groupsByNhom).forEach(([nhom, groups]) => {
                    // Tạo heading cho danh mục
                    const nhomHeader = `
                            <div class="row mb-4 mt-4">
                                <div class="col-12">
                                    <h5 class="text-primary border-bottom pb-2">
                                        <i class="bi bi-tag-fill"></i> Danh mục: <strong>${nhom}</strong>
                                    </h5>
                                </div>
                            </div>
                        `;
                    container.insertAdjacentHTML('beforeend', nhomHeader);

                    // Duyệt theo từng lệnh SX trong danh mục
                    Object.entries(groups).forEach(([soct, rows]) => {
                        const firstItem = rows[0];
                        const soDh = firstItem.So_dh;
                        const tongSX = Number(totalBySoct?.[soDh] ?? 0);
                        const soluongGO = Number(firstItem.Soluong_go ?? 0);
                        const soThieu = soluongGO - tongSX;
                        const pct = soluongGO > 0 ? ((tongSX / soluongGO) * 100).toFixed(1) : 0;

                        const barColor = pct >= 90 ? 'bg-success' : pct >= 60 ? 'bg-warning' :
                            'bg-danger';
                        const statusColor = soThieu > 0 ? 'danger' : 'success';
                        const statusIcon = soThieu > 0 ? 'exclamation-triangle-fill' :
                            'check-circle-fill';

                        // Lấy trạng thái từ statusMap
                        const status = statusMap[soDh] || {
                            co_dinh_muc: false,
                            da_xuat_vat_tu: false,
                            da_nhap_kho: false,
                            da_xuat_kho: false
                        };

                        // Tạo các badge cảnh báo và data-status cho filter
                        let warningHtml = '';
                        let statusClasses = [];

                        if (!status.co_dinh_muc) {
                            warningHtml +=
                                '<span class="badge bg-danger me-1 mb-1"><i class="bi bi-exclamation-circle"></i> Chưa phân tích</span>';
                            statusClasses.push('chua-phan-tich');
                        }
                        if (!status.da_xuat_vat_tu) {
                            warningHtml +=
                                '<span class="badge bg-danger me-1 mb-1"><i class="bi bi-box-arrow-right"></i> Chưa xuất VT</span>';
                            statusClasses.push('chua-xuat-vat-tu');
                        }
                        if (!status.da_nhap_kho) {
                            warningHtml +=
                                '<span class="badge bg-danger me-1 mb-1"><i class="bi bi-box-arrow-in-down"></i> Chưa nhập kho</span>';
                            statusClasses.push('chua-nhap-kho');
                        }
                        if (!status.da_xuat_kho) {
                            warningHtml +=
                                '<span class="badge bg-danger me-1 mb-1"><i class="bi bi-truck"></i> Chưa xuất kho</span>';
                            statusClasses.push('chua-xuat-kho');
                        }

                        // Kiểm tra xuất dư vật tư
                        if (status.xuat_du_vat_tu) {
                            warningHtml +=
                                '<span class="badge bg-warning text-dark me-1 mb-1"><i class="bi bi-arrow-up-circle"></i> Xuất dư VT</span>';
                            statusClasses.push('xuat-du-vat-tu');
                        }

                        // Kiểm tra soThieu > 0 (có thiếu hàng)
                        if (soThieu > 0) {
                            warningHtml +=
                                '<span class="badge bg-danger me-1 mb-1"><i class="bi bi-exclamation-diamond-fill"></i> Thiếu hàng</span>';
                            statusClasses.push('thieu-hang');
                        } else if (soThieu < 0) {
                            // soThieu < 0 (có dư hàng)
                            statusClasses.push('du-hang');
                        } else {
                            // soThieu = 0 (đủ hàng)
                            statusClasses.push('du-hang');
                        }

                        // Nếu không có cảnh báo, hiển thị trạng thái hoàn tất
                        if (warningHtml === '') {
                            warningHtml =
                                '<span class="badge bg-success"><i class="bi bi-check-circle-fill"></i> Hoàn tất</span>';
                            statusClasses.push('hoan-tat');
                        }

                        const card = `
                            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6" data-status="${statusClasses.join(' ')}">
                                <div class="product-card" onclick="loadDetailLenh('${soDh}')">
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
                                            ${tonKho[firstItem.hang_hoa.Ma_hh] ? `
                                                <div class="mt-2 pt-2 border-top">
                                                    <small class="text-muted">
                                                        <i class="bi bi-box-seam"></i> Tồn kho: <strong class="text-info">${Math.round(tonKho[firstItem.hang_hoa.Ma_hh].ton_kho || 0).toLocaleString('vi-VN')}</strong>
                                                    </small>
                                                </div>
                                                ` : ''}
                                        </div>
                                        <div class="warning-info mt-2">
                                            ${warningHtml}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                        container.insertAdjacentHTML('beforeend', card);
                    });
                });
                Toast.fire({
                    icon: 'success',
                    title: 'Cập nhật thành công!'
                });
                // Apply current filter after loading data
                applyFilter();

                updateLastRefreshTime();

            } catch (err) {
                Toast.fire({
                    icon: 'error',
                    title: 'Lỗi xử lý dữ liệu!'
                });
                console.error("Lỗi xử lý dữ liệu:", err);
                container.innerHTML =
                    `<div class="col-12"><div class="alert alert-danger text-center">Lỗi xử lý dữ liệu!</div></div>`;
            } finally {
                isRefreshing = false;
            }
        }

        loadSXData();
        refreshInterval = setInterval(function() {
            if (!isRefreshing) { // Chỉ gọi nếu không đang load
                loadSXData();
            }
        }, 30000); // Tăng lên 30s, chỉ gọi nếu request trước đã xong

        window.addEventListener('beforeunload', function() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        });
    </script>

</body>

</html>
