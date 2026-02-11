<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon" />
    <title>TOÀN BỘ DỮ LIỆU SẢN XUẤT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="{{ asset('css/tivicss.css') }}" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .header-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .header-section h1 {
            font-weight: 700;
            margin-bottom: 10px;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.2);
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid white;
        }

        .stat-card-label {
            font-size: 12px;
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .stat-card-value {
            font-size: 24px;
            font-weight: 700;
        }

        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .table-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        table {
            font-size: 13px;
        }

        thead {
            background-color: #667eea;
            color: white;
        }

        tbody tr:hover {
            background-color: #f5f5f5;
        }

        .badge-status {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }

        .loading-spinner {
            text-align: center;
            padding: 40px;
        }

        .loading-spinner .spinner-border {
            width: 3rem;
            height: 3rem;
            color: #667eea;
        }

        .export-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.2s;
        }

        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.4);
        }

        .filter-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .form-group-inline {
            margin-bottom: 0;
            flex: 1;
            min-width: 200px;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .no-data i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 15px;
        }
    </style>
</head>

<body>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="header-section">
            <h1><i class="bi bi-graph-up"></i> Toàn Bộ Dữ Liệu Sản Xuất</h1>
            <p class="mb-0">Xem tất cả các phiếu sản xuất từ ngày 01/01/2026 đến hiện tại</p>
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-card-label">Tổng Bản Ghi</div>
                    <div class="stat-card-value" id="totalRecords">0</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-label">Tổng Số Lượng</div>
                    <div class="stat-card-value" id="totalQuantity">0</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-label">Hàng Hóa</div>
                    <div class="stat-card-value" id="uniqueProducts">0</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-label">Công Đoạn</div>
                    <div class="stat-card-value" id="uniqueProcess">0</div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="filter-group">
                <div class="form-group-inline">
                    <label for="filterMaHh" class="form-label">Mã Hàng Hóa</label>
                    <input type="text" class="form-control" id="filterMaHh" placeholder="Tìm kiếm...">
                </div>
                <div class="form-group-inline">
                    <label for="filterMaKo" class="form-label">Công Đoạn</label>
                    <select class="form-control" id="filterMaKo">
                        <option value="">-- Tất cả --</option>
                    </select>
                </div>
                <div class="form-group-inline">
                    <label for="filterMaKh" class="form-label">Khách Hàng</label>
                    <select class="form-control" id="filterMaKh">
                        <option value="">-- Tất cả --</option>
                    </select>
                </div>
                <button class="btn btn-outline-secondary" id="resetFilter">
                    <i class="bi bi-arrow-clockwise"></i> Reset
                </button>
                <button class="export-btn" id="exportBtn">
                    <i class="bi bi-download"></i> Export Excel
                </button>
            </div>
        </div>

        <!-- Table Section -->
        <div class="table-container">
            <div id="loadingSpinner" class="loading-spinner" style="display:none;">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3">Đang tải dữ liệu...</p>
            </div>

            <div id="noDataMessage" class="no-data" style="display:none;">
                <i class="bi bi-inbox"></i>
                <p>Không có dữ liệu</p>
            </div>

            <div id="tableWrapper" style="display:none; overflow-x:auto;">
                <table class="table table-striped table-hover" id="dataTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Lệnh SX</th>
                            <th>Lệnh GO</th>
                            <th>Mã HH</th>

                            <th>Số Lượng</th>
                            <th>Ghi Chú</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.min.js"></script>

    <script>
        let allData = [];
        let originalData = [];
        let dataTable = null;

        $(document).ready(function() {
            loadData();
            attachFilterHandlers();
        });

        function loadData() {
            $('#loadingSpinner').show();
            $('#tableWrapper').hide();
            $('#noDataMessage').hide();

            $.ajax({
                url: '/api/tivi/all-sx-data',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        allData = response.data;
                        originalData = JSON.parse(JSON.stringify(response.data));
                        updateStatistics();
                        populateFilters();
                        renderTable();
                        $('#tableWrapper').show();
                    } else {
                        showNoData();
                    }
                },
                error: function() {
                    showNoData();
                    alert('Lỗi khi tải dữ liệu!');
                },
                complete: function() {
                    $('#loadingSpinner').hide();
                }
            });
        }

        function updateStatistics() {
            const totalSoluong = allData.reduce((sum, item) => sum + (item.Soluong || 0), 0);
            const uniqueMaHh = [...new Set(allData.map(item => item.Ma_hh))].length;
            const uniqueMaKo = [...new Set(allData.map(item => item.Ma_ko))].length;

            $('#totalRecords').text(allData.length.toLocaleString());
            $('#totalQuantity').text(totalSoluong.toLocaleString());
            $('#uniqueProducts').text(uniqueMaHh);
            $('#uniqueProcess').text(uniqueMaKo);
        }

        function populateFilters() {
            // Populate Công Đoạn
            const maKoList = [...new Set(allData.map(item => item.Ma_ko))].sort();
            maKoList.forEach(maKo => {
                $('#filterMaKo').append(`<option value="${maKo}">${maKo}</option>`);
            });

            // Populate Khách Hàng
            const maKhList = [...new Set(allData
                .filter(item => item.khachHang)
                .map(item => item.khachHang.Ma_kh))].sort();
            maKhList.forEach(maKh => {
                const khachHang = allData.find(item => item.khachHang?.Ma_kh === maKh);
                const tenKh = khachHang?.khachHang?.Ten_kh || '';
                $('#filterMaKh').append(`<option value="${maKh}">${maKh} - ${tenKh}</option>`);
            });
        }

        function renderTable() {
            const tableBody = $('#tableBody');
            tableBody.empty();

            if (allData.length === 0) {
                showNoData();
                return;
            }

            allData.forEach((item, index) => {
                const row = `
                    <tr>
                        <td>${index + 1}</td>
                        <td><strong>${item.So_dh || ''}</strong></td>
                        <td><strong>${item.So_dh_go || ''}</strong></td>
                        <td>${item.Ma_hh || ''}</td>

                        <td class="text-center">${Math.round(item.Soluong).toLocaleString()}</td>

                        <td>${item.DgiaiV || ''}</td>
                    </tr>
                `;
                tableBody.append(row);
            });

            if (dataTable) {
                dataTable.destroy();
            }

            dataTable = $('#dataTable').DataTable({
                pageLength: 25,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.8/i18n/vi.json',
                },
                columnDefs: [{
                    orderable: false,
                    targets: 0
                }]
            });
        }

        function applyFilters() {
            const maHh = $('#filterMaHh').val().toLowerCase();
            const maKo = $('#filterMaKo').val();
            const maKh = $('#filterMaKh').val();

            allData = originalData.filter(item => {
                let match = true;

                if (maHh && !item.Ma_hh.toLowerCase().includes(maHh)) {
                    match = false;
                }
                if (maKo && item.Ma_ko !== maKo) {
                    match = false;
                }
                if (maKh && item.khachHang?.Ma_kh !== maKh) {
                    match = false;
                }

                return match;
            });

            renderTable();
        }

        function attachFilterHandlers() {
            $('#filterMaHh').on('keyup', applyFilters);
            $('#filterMaKo').on('change', applyFilters);
            $('#filterMaKh').on('change', applyFilters);

            $('#resetFilter').on('click', function() {
                $('#filterMaHh').val('');
                $('#filterMaKo').val('');
                $('#filterMaKh').val('');
                allData = JSON.parse(JSON.stringify(originalData));
                renderTable();
            });

            $('#exportBtn').on('click', exportToExcel);
        }

        function exportToExcel() {
            if (allData.length === 0) {
                alert('Không có dữ liệu để xuất!');
                return;
            }

            const ws = XLSX.utils.json_to_sheet(allData.map((item, index) => ({
                'STT': index + 1,
                'Lệnh SX': item.So_dh,
                'Lệnh GO': item.So_dh_go || '',
                'Mã HH': item.Ma_hh,
                'Tên Hàng Hóa': item.hangHoa?.Ten_hh || '',
                'Công Đoạn': item.Ma_ko,
                'Số Lượng': item.Soluong,
                'Đơn Vị': item.hangHoa?.Dvt || '',
                'Khách Hàng': item.khachHang?.Ten_kh || '',
                'Nhân Viên': item.nhanVien?.Ten_nv || '',
                'Ngày Tạo': formatDate(item.Ngay_ct),
                'Ghi Chú': item.DgiaiV || ''
            })));

            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'SX Data');
            XLSX.writeFile(wb, `SanXuat_${new Date().getTime()}.xlsx`);
        }

        function showNoData() {
            $('#tableWrapper').hide();
            $('#noDataMessage').show();
        }

        function formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('vi-VN', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit'
            });
        }
    </script>
</body>

</html>
