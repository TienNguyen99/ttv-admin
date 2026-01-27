<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon" />
    <title>XEM DỮ LIỆU SX VÀ TÍNH TRUNG BÌNH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="{{ asset('css/tivicss.css') }}" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .search-container {
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

        .summary-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .summary-box h5 {
            font-weight: 600;
            margin-bottom: 10px;
        }

        .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .stat-item:last-child {
            border-bottom: none;
        }

        .stat-label {
            font-weight: 500;
        }

        .stat-value {
            font-weight: bold;
            font-size: 1.1em;
        }

        .average-table {
            margin-top: 30px;
        }

        .average-table h5 {
            color: #667eea;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        .detail-table {
            margin-top: 30px;
        }

        .detail-table h5 {
            color: #764ba2;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #764ba2;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 40px;
        }

        .spinner-border {
            color: #667eea;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .highlight-row {
            background-color: #f0f0ff;
        }

        .table-hover tbody tr:hover {
            background-color: #f5f5ff;
        }

        .badge-info {
            background-color: #667eea;
        }

        .input-group {
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .table-container {
                overflow-x: auto;
            }

            table {
                font-size: 0.9em;
            }
        }
    </style>
</head>

<body>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="mb-4">
            <h1 class="text-center mb-3">
                <i class="bi bi-bar-chart"></i> DỮ LIỆU TRUNG BÌNH SẢN XUẤT THUN SIV
            </h1>

        </div>

        <!-- Search Container -->
        <div class="search-container">
            <div class="row">
                <div class="col-12 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" class="form-control" id="maHhInput"
                            placeholder="Nhập mã hàng hóa (Ma_hh)...">
                        <button class="btn btn-primary" type="button" id="searchBtn">
                            <i class="bi bi-search"></i> Tìm kiếm
                        </button>
                        <button class="btn btn-secondary" type="button" id="resetBtn">
                            <i class="bi bi-arrow-clockwise"></i> Xóa lọc
                        </button>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="loading" id="loading">
                        <div class="spinner-border spinner-border-sm" role="status">
                            <span class="visually-hidden">Đang tải...</span>
                        </div>
                        <p class="ms-2">Đang tải dữ liệu...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Statistics -->
        <div class="summary-box" id="summaryBox" style="display: none;">
            <h5><i class="bi bi-graph-up"></i> Thống kê tổng hợp</h5>
            <div class="stat-item">
                <span class="stat-label">Tổng số bản ghi:</span>
                <span class="stat-value" id="totalRecords">0</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Số hàng hóa khác nhau:</span>
                <span class="stat-value" id="uniqueProducts">0</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">NĂNG SUẤT TRUNG BÌNH chung:</span>
                <span class="stat-value" id="avgDgbanvnd">0</span>
            </div>
        </div>

        <!-- Average by Ma_hh Table -->
        <div class="table-container">
            <div class="average-table">
                <h5>
                    <i class="bi bi-calculator"></i> Năng suất THEO MÃ HÀNG HÓA
                </h5>
                <div class="table-responsive">
                    <table class="table table-hover table-bordered" id="averageTable">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width: 50px;">STT</th>
                                <th>Mã HH</th>
                                <th>Tên Hàng Hóa</th>
                                {{-- <th>Đơn vị tính</th> --}}
                                <th class="text-center">Số ca sản xuất</th>
                                <th class="text-end">Tổng số đã sản xuất</th>
                                <th class="text-end">Năng suất Trung bình</th>
                                <th class="text-end">1 ca sản xuất ít nhất</th>
                                <th class="text-end">1 ca sản xuất nhiều nhất </th>
                            </tr>
                        </thead>
                        <tbody id="averageTableBody">
                            <tr>
                                <td colspan="9" class="no-data">
                                    <i class="bi bi-inbox"></i> Nhập mã hàng hóa và click tìm kiếm
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Detail Data Table -->
        <div class="table-container">
            <div class="detail-table">
                <h5>
                    <i class="bi bi-list-ul"></i> CHI TIẾT DỮ LIỆU SX
                </h5>
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-bordered" id="detailTable">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width: 50px;">STT</th>
                                <th>Mã HH</th>
                                <th>Tên Hàng Hóa</th>
                                <th>Số ĐH</th>
                                <th>Mã NV</th>
                                <th>Tên NV</th>
                                <th class="text-center">Số lượng</th>
                                <th class="text-end">Dgbanvnd</th>
                                <th class="text-end">Tien_vnd</th>
                                <th>Ngày CT</th>
                            </tr>
                        </thead>
                        <tbody id="detailTableBody">
                            <tr>
                                <td colspan="10" class="no-data">
                                    <i class="bi bi-inbox"></i> Chưa có dữ liệu
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Variables
        let allData = [];
        let averageData = [];

        // Event Listeners
        document.getElementById('searchBtn').addEventListener('click', searchData);
        document.getElementById('resetBtn').addEventListener('click', resetData);
        document.getElementById('maHhInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchData();
            }
        });

        // Search Function
        async function searchData() {
            const maHh = document.getElementById('maHhInput').value.trim();
            const loading = document.getElementById('loading');
            const summaryBox = document.getElementById('summaryBox');

            loading.style.display = 'block';

            try {
                const params = new URLSearchParams();
                if (maHh) {
                    params.append('ma_hh', maHh);
                }

                const response = await fetch(`/api/tivi/sx-data-with-average?${params.toString()}`);
                const result = await response.json();

                if (!result.success) {
                    showNoData();
                    return;
                }

                allData = result.data;
                averageData = result.averageByMaHh;

                // Show summary
                summaryBox.style.display = 'block';
                document.getElementById('totalRecords').textContent = result.total_records;
                document.getElementById('uniqueProducts').textContent = averageData.length;

                // Calculate overall average (chia cho 1000 để chuyển từ gram sang kg)
                const overallAvg = (allData.reduce((sum, item) => sum + item.Dgbanvnd, 0) / (allData.length || 1)) /
                    1000;
                document.getElementById('avgDgbanvnd').textContent = overallAvg.toFixed(2);

                // Display data
                displayAverageTable();
                displayDetailTable();

            } catch (error) {
                console.error('Error:', error);
                alert('Lỗi khi tải dữ liệu: ' + error.message);
            } finally {
                loading.style.display = 'none';
            }
        }

        // Display Average Table
        function displayAverageTable() {
            const tbody = document.getElementById('averageTableBody');
            tbody.innerHTML = '';

            if (averageData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="no-data">Không tìm thấy dữ liệu</td></tr>';
                return;
            }

            averageData.forEach((item, index) => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td class="text-center"><strong>${index + 1}</strong></td>
                    <td><span class="badge badge-info">${item.ma_hh}</span></td>
                    <td>${item.ten_hh}</td>
                    <td class="text-center"><span class="badge bg-info">${item.count}</span></td>
                    <td class="text-end">${parseFloat(item.total_dgbanvnd).toLocaleString('vi-VN', {maximumFractionDigits: 2})}</td>
                    <td class="text-end"><strong>${parseFloat(item.average_dgbanvnd).toLocaleString('vi-VN', {maximumFractionDigits: 2})}</strong></td>
                    <td class="text-end">${parseFloat(item.min_dgbanvnd).toLocaleString('vi-VN', {maximumFractionDigits: 2})}</td>
                    <td class="text-end">${parseFloat(item.max_dgbanvnd).toLocaleString('vi-VN', {maximumFractionDigits: 2})}</td>
                `;
                tbody.appendChild(row);
            });
        }

        // Display Detail Table
        function displayDetailTable() {
            const tbody = document.getElementById('detailTableBody');
            tbody.innerHTML = '';

            if (allData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="10" class="no-data">Không tìm thấy dữ liệu</td></tr>';
                return;
            }

            allData.forEach((item, index) => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td class="text-center">${index + 1}</td>
                    <td><strong>${item.Ma_hh}</strong></td>
                    <td>${item.hangHoa?.Ten_hh || '-'}</td>
                    <td>${item.So_dh}</td>
                    <td>${item.Ma_nv}</td>
                    <td>${item.nhanVien?.Ten_nv || '-'}</td>
                    <td class="text-center">${item.Soluong}</td>
                    <td class="text-end">${(parseFloat(item.Dgbanvnd) / 1000).toLocaleString('vi-VN', {maximumFractionDigits: 2})}</td>
                    <td class="text-end">${parseFloat(item.Tien_vnd).toLocaleString('vi-VN', {maximumFractionDigits: 2})}</td>
                    <td>${item.Ngay_ct ? new Date(item.Ngay_ct).toLocaleDateString('vi-VN') : '-'}</td>
                `;
                tbody.appendChild(row);
            });
        }

        // Show No Data
        function showNoData() {
            document.getElementById('averageTableBody').innerHTML =
                '<tr><td colspan="9" class="no-data">Không tìm thấy dữ liệu</td></tr>';
            document.getElementById('detailTableBody').innerHTML =
                '<tr><td colspan="10" class="no-data">Không tìm thấy dữ liệu</td></tr>';
            document.getElementById('summaryBox').style.display = 'none';
        }

        // Reset Data
        function resetData() {
            document.getElementById('maHhInput').value = '';
            allData = [];
            averageData = [];
            showNoData();
        }

        // Load data on page load (all data without filter)
        window.addEventListener('load', function() {
            searchData();
        });
    </script>
</body>

</html>
