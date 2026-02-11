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
        * {
            transition: all 0.3s ease;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        .header-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 0;
            margin-bottom: 40px;
            border-radius: 0 0 20px 20px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .header-section h1 {
            font-size: 2.5em;
            font-weight: 700;
            margin-bottom: 10px;
            letter-spacing: -0.5px;
        }

        .header-section p {
            font-size: 1.1em;
            opacity: 0.9;
            margin: 0;
        }

        .search-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            margin-bottom: 40px;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .input-group {
            margin-bottom: 20px;
        }

        .input-group-text {
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            color: #667eea;
        }

        .form-control {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 0.95em;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background-color: #e9ecef;
            border: 1px solid #dee2e6;
            color: #495057;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 8px;
        }

        .btn-secondary:hover {
            background-color: #dee2e6;
            transform: translateY(-2px);
        }

        .btn-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            border: none;
            padding: 10px 25px;
            font-weight: 600;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(17, 153, 142, 0.3);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(17, 153, 142, 0.4);
        }

        .table-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            margin-bottom: 40px;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .summary-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .summary-box h5 {
            font-weight: 700;
            margin-bottom: 25px;
            font-size: 1.3em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stat-item {
            display: grid;
            grid-template-columns: 1fr 1fr;
            padding: 15px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            gap: 15px;
        }

        .stat-item:last-child {
            border-bottom: none;
        }

        .stat-label {
            font-weight: 500;
            font-size: 0.95em;
            opacity: 0.9;
        }

        .stat-value {
            font-weight: 700;
            font-size: 1.3em;
            text-align: right;
        }

        .average-table,
        .detail-table {
            margin-top: 0;
        }

        .average-table h5,
        .detail-table h5 {
            font-weight: 700;
            margin-bottom: 25px;
            font-size: 1.3em;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #2d3748;
            border: none;
            padding-bottom: 0;
        }

        .average-table h5 {
            color: #667eea;
        }

        .detail-table h5 {
            color: #764ba2;
        }

        .table {
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table thead {
            background-color: #f8f9fa;
        }

        .table thead th {
            border: none;
            font-weight: 700;
            color: #2d3748;
            padding: 15px;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table tbody td {
            padding: 15px;
            border: none;
            border-bottom: 1px solid #e0e0e0;
            color: #495057;
        }

        .table tbody tr {
            background-color: white;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
            transform: scale(1.01);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .loading {
            display: none;
            text-align: center;
            padding: 40px;
            color: #667eea;
        }

        .spinner-border {
            color: #667eea;
            width: 40px;
            height: 40px;
        }

        .no-data {
            text-align: center;
            padding: 50px;
            color: #999;
            font-size: 1.1em;
        }

        .badge {
            padding: 6px 12px;
            font-weight: 600;
            border-radius: 6px;
            font-size: 0.85em;
        }

        .badge-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .badge.bg-danger {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%) !important;
        }

        .badge.bg-warning {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%) !important;
            color: #2d3748 !important;
        }

        .badge.bg-info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%) !important;
        }

        .form-control-sm {
            border-radius: 6px;
            border: 1px solid #e0e0e0;
            padding: 8px 12px;
        }

        .form-control-sm:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.1);
        }

        @media (max-width: 768px) {
            .header-section h1 {
                font-size: 1.8em;
            }

            .table-container {
                overflow-x: auto;
            }

            table {
                font-size: 0.85em;
            }

            .table thead th,
            .table tbody td {
                padding: 10px;
            }

            .stat-item {
                grid-template-columns: 1fr;
            }

            .stat-value {
                text-align: left;
            }
        }
    </style>
</head>

<body>
    <!-- Header Section -->
    <div class="header-section mb-0">
        <div class="container-fluid text-center">
            <h1>
                <i class="bi bi-graph-up-arrow"></i> Dữ Liệu Sản Xuất Thun SIV
            </h1>
            <p>Quản lý và phân tích dữ liệu sản xuất chi tiết</p>
        </div>
    </div>

    <div class="container-fluid py-5">
        <!-- Search Container -->
        <div class="search-container">
            <div class="row">
                <div class="col-12 col-md-6">
                    <div class="input-group mb-3">
                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" class="form-control" id="maHhInput"
                            placeholder="Nhập mã hàng hóa (Ma_hh)...">
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="input-group mb-3">
                        <span class="input-group-text">
                            <i class="bi bi-funnel"></i>
                        </span>
                        <input type="text" class="form-control" id="soDhGoInput"
                            placeholder="LỌC THEO LỆNH SẢN XUẤT">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12 col-md-6">
                    <div class="input-group mb-3">
                        <span class="input-group-text">
                            <i class="bi bi-calendar"></i>
                        </span>
                        <input type="text" class="form-control" id="dgiaiVInput"
                            placeholder="NHẬP NGÀY BÁO CÁO (VD: 15/02/2026)...">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <button class="btn btn-primary" type="button" id="searchBtn">
                        <i class="bi bi-search"></i> Tìm kiếm
                    </button>
                    <button class="btn btn-secondary" type="button" id="resetBtn">
                        <i class="bi bi-arrow-clockwise"></i> Xóa lọc
                    </button>
                    <div class="loading d-inline-block ms-3" id="loading" style="display: none;">
                        <div class="spinner-border spinner-border-sm" role="status">
                            <span class="visually-hidden">Đang tải...</span>
                        </div>
                        <span class="ms-2">Đang tải dữ liệu...</span>
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
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <i class="bi bi-list-ul"></i> CHI TIẾT DỮ LIỆU SX
                    </h5>
                    <button class="btn btn-success btn-sm" type="button" id="exportCsvBtn" style="display: none;">
                        <i class="bi bi-download"></i> Xuất CSV
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-bordered" id="detailTable">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width: 50px;">STT</th>
                                <th>Mã HH</th>
                                <th>Tên Hàng Hóa</th>
                                <th>Số ĐH</th>
                                <th>Mã lệnh</th>
                                <th>Mã NV</th>
                                <th>Tên NV</th>
                                <th>CA</th>
                                {{-- <th>Công đoạn</th> --}}
                                <th class="text-end">Công đoạn</th>
                                <th class="text-end">Số lượng sản xuất (gram)</th>
                                <th class="text-end">Số yard/mét</th>
                                <th>Giai đoạn</th>
                                {{-- <th class="text-end">Tien_vnd</th> --}}
                                <th>Ngày nhập</th>
                            </tr>
                        </thead>
                        <tbody id="detailTableBody">
                            <tr>
                                <td colspan="12" class="no-data">
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

        // Export Detail Table to CSV
        function exportDetailTableToCSV() {
            if (allData.length === 0) {
                alert('Không có dữ liệu để xuất!');
                return;
            }

            // Header row for CSV
            const headers = ['STT', 'Mã HH', 'Tên Hàng Hóa', 'Số ĐH', 'Mã lệnh', 'Mã NV', 'Tên NV', 'CA', 'Công đoạn',
                'Số lượng', 'Số yard/mét', 'Giai đoạn', 'Ngày nhập'
            ];

            // Map công đoạn
            const congDoanMap = {
                '01': 'Dệt',
                '02': 'Định hình',
                '05': 'Quấn cuộn'
            };

            // Build CSV data with UTF-8 BOM for proper Vietnamese character encoding
            // Use semicolon (;) as delimiter for Excel with Vietnamese locale
            let csvContent = '\uFEFF' + headers.join(';') + '\n';

            let sttIndex = 1;
            allData.forEach((item) => {
                const soPhatSinhRam = Number(item.Dgbanvnd);
                const congDoan = congDoanMap[item.Ma_ko] || item.Ma_ko || '-';

                // Get norm input value if available
                const normInput = document.querySelector(`.norm-input[data-index="${sttIndex - 1}"]`);
                const norm = normInput ? normInput.value || '' : '';

                // Get yard result if available
                const yardSpan = document.querySelector(`.yard-result[data-index="${sttIndex - 1}"]`);
                const yard = yardSpan ? yardSpan.textContent : '-';

                const row = [
                    sttIndex,
                    item.Ma_hh,
                    item.hangHoa?.Ten_hh || '-',
                    item.So_dh,
                    item.So_dh_go || '-',
                    item.Ma_nv,
                    item.nhanVien?.Ten_nv || '-',
                    item.Ma3ko || '-',
                    congDoan,
                    soPhatSinhRam.toLocaleString('vi-VN', {
                        maximumFractionDigits: 2
                    }),
                    norm,
                    yard,
                    item.DgiaiV !== undefined ? item.DgiaiV : '-'
                ];

                // Escape and format CSV values - Quote all values for proper column separation
                const csvRow = row.map(val => {
                    const strVal = String(val).trim();
                    // Always quote values to ensure proper column separation
                    // Escape internal quotes by doubling them
                    return '"' + strVal.replace(/"/g, '""') + '"';
                }).join(';');

                csvContent += csvRow + '\n';
                sttIndex++;
            });

            // Create blob and download
            const blob = new Blob([csvContent], {
                type: 'text/csv;charset=utf-8;'
            });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);

            const timestamp = new Date().toLocaleString('vi-VN').replace(/[/:]/g, '-');
            link.setAttribute('href', url);
            link.setAttribute('download', `DULIEUSANXUAT.csv`);
            link.style.visibility = 'hidden';

            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Event Listeners
        document.getElementById('searchBtn').addEventListener('click', searchData);
        document.getElementById('resetBtn').addEventListener('click', resetData);
        document.getElementById('exportCsvBtn').addEventListener('click', exportDetailTableToCSV);
        document.getElementById('maHhInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchData();
            }
        });
        document.getElementById('soDhGoInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                filterDetailTable();
            }
        });
        document.getElementById('soDhGoInput').addEventListener('input', function() {
            filterDetailTable();
        });
        document.getElementById('dgiaiVInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                filterDetailTable();
            }
        });
        document.getElementById('dgiaiVInput').addEventListener('input', function() {
            filterDetailTable();
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

                // Calculate overall average
                const overallAvg = (allData.reduce((sum, item) => sum + Number(item.Dgbanvnd), 0) / (allData.length ||
                    1));
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

        // Filter Detail Table by So_dh_go and DgiaiV - Lọc toàn bộ cột
        function filterDetailTable() {
            const soDhGo = document.getElementById('soDhGoInput').value.trim().toLowerCase();
            const dgiaiV = document.getElementById('dgiaiVInput').value.trim().toLowerCase();
            const tbody = document.getElementById('detailTableBody');
            const rows = tbody.querySelectorAll('tr');

            rows.forEach(row => {
                // Lấy text từ tất cả các cell trong row
                const rowText = Array.from(row.querySelectorAll('td'))
                    .map(td => td.textContent.toLowerCase())
                    .join(' ');

                let showRow = true;

                // Filter by So_dh_go
                if (soDhGo !== '' && !rowText.includes(soDhGo)) {
                    showRow = false;
                }

                // Filter by DgiaiV
                if (dgiaiV !== '' && !rowText.includes(dgiaiV)) {
                    showRow = false;
                }

                row.style.display = showRow ? '' : 'none';
            });
        }

        // Display Average Table
        function displayAverageTable() {
            const tbody = document.getElementById('averageTableBody');
            tbody.innerHTML = '';

            if (averageData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="no-data">Không tìm thấy dữ liệu</td></tr>';
                return;
            }

            // Tính số CA khác nhau (CA1, CA2, CA3) cho mỗi sản phẩm
            const caCountByProduct = {};
            allData.forEach((item) => {
                const maHh = item.Ma_hh;
                if (!caCountByProduct[maHh]) {
                    caCountByProduct[maHh] = new Set();
                }
                if (['CA1', 'CA2', 'CA3'].includes(item.Ma3ko)) {
                    caCountByProduct[maHh].add(item.Ma3ko);
                }
            });

            averageData.forEach((item, index) => {
                const caCount = caCountByProduct[item.ma_hh]?.size || 0;
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td class="text-center"><strong>${index + 1}</strong></td>
                    <td><span class="badge badge-info">${item.ma_hh}</span></td>
                    <td>${item.ten_hh}</td>
                    <td class="text-center"><span class="badge bg-danger">${caCount}</span></td>
                    <td class="text-end">${Number(item.total_dgbanvnd).toLocaleString('vi-VN', {maximumFractionDigits: 2})}</td>
                    <td class="text-end"><strong>${Number(item.average_dgbanvnd).toLocaleString('vi-VN', {maximumFractionDigits: 2})}</strong></td>
                    <td class="text-end">${Number(item.min_dgbanvnd).toLocaleString('vi-VN', {maximumFractionDigits: 2})}</td>
                    <td class="text-end">${Number(item.max_dgbanvnd).toLocaleString('vi-VN', {maximumFractionDigits: 2})}</td>
                `;
                tbody.appendChild(row);
            });
        }

        // Display Detail Table
        function displayDetailTable() {
            const tbody = document.getElementById('detailTableBody');
            const exportBtn = document.getElementById('exportCsvBtn');
            tbody.innerHTML = '';

            if (allData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="14" class="no-data">Không tìm thấy dữ liệu</td></tr>';
                exportBtn.style.display = 'none';
                return;
            }

            // Show export button
            exportBtn.style.display = 'block';

            // Map công đoạn
            const congDoanMap = {
                '01': 'Dệt',
                '02': 'Định hình',
                '05': 'Quấn cuộn'
            };

            let sttIndex = 1;
            allData.forEach((item) => {
                const soPhatSinhRam = Number(item.Dgbanvnd);
                const congDoan = congDoanMap[item.Ma_ko] || item.Ma_ko || '-';

                const row = document.createElement('tr');
                row.innerHTML = `
                    <td class="text-center">${sttIndex}</td>
                    <td><strong>${item.Ma_hh}</strong></td>
                    <td>${item.hangHoa?.Ten_hh || '-'}</td>
                    <td>${item.So_dh}</td>
                    <td>${item.So_dh_go || '-'}</td>
                    <td>${item.Ma_nv}</td>
                    <td>${item.nhanVien?.Ten_nv || '-'}</td>
                    <td class="text-center"><span class="badge bg-warning text-dark">${item.Ma3ko || '-'}</span></td>
                    <td><span class="badge bg-info">${congDoan}</span></td>
                    <td class="text-end">${soPhatSinhRam.toLocaleString('vi-VN', {maximumFractionDigits: 2})}</td>
                    <td class="text-end"><input type="text" class="form-control form-control-sm norm-input" data-index="${sttIndex - 1}" placeholder="Nhập định mức"></td>
                    <td class="text-end"><span class="yard-result" data-index="${sttIndex - 1}">-</span></td>
                    <td>${item.DgiaiV !== undefined ? item.DgiaiV : '-'}</td>
                `;
                tbody.appendChild(row);
                sttIndex++;
            });

            // Add event listeners for norm inputs
            document.querySelectorAll('.norm-input').forEach((input, index) => {
                input.addEventListener('change', function() {
                    const item = allData[index];
                    if (item) {
                        // Chuyển đổi số thập phân (hỗ trợ cả . và ,)
                        const normStr = this.value.toString().replace(',', '.');
                        const norm = Number(normStr) || 0;
                        const maHh = item.Ma_hh;

                        // Tìm tất cả dòng có cùng Ma_hh và điền định mức
                        allData.forEach((dataItem, dataIndex) => {
                            if (dataItem.Ma_hh === maHh) {
                                const inputElement = document.querySelector(
                                    `.norm-input[data-index="${dataIndex}"]`);
                                if (inputElement) {
                                    inputElement.value = norm;

                                    // Tính toán yard cho dòng này
                                    const soPhatSinhRam = Number(dataItem.Dgbanvnd);
                                    const soYard = norm > 0 ? (soPhatSinhRam / norm).toFixed(2) :
                                        '-';
                                    const yardSpan = document.querySelector(
                                        `.yard-result[data-index="${dataIndex}"]`);
                                    if (yardSpan) {
                                        yardSpan.textContent = soYard !== '-' ? Number(soYard)
                                            .toLocaleString('vi-VN', {
                                                maximumFractionDigits: 2
                                            }) : '-';
                                    }
                                }
                            }
                        });
                    }
                });
                input.addEventListener('input', function() {
                    this.dispatchEvent(new Event('change'));
                });
            });
        }

        // Calculate Yard for individual row
        function calculateYard(e) {
            const index = e.target.dataset.index;
            const norm = Number(e.target.value) || 0;
            const soPhatSinhRam = Number(allData[index].Dgbanvnd);
            const soYard = norm > 0 ? (soPhatSinhRam / norm).toFixed(2) : '-';
            const yardSpan = document.querySelector(`.yard-result[data-index="${index}"]`);
            if (yardSpan) {
                yardSpan.textContent = soYard !== '-' ? Number(soYard).toLocaleString('vi-VN', {
                    maximumFractionDigits: 2
                }) : '-';
            }
        }

        // Show No Data
        function showNoData() {
            document.getElementById('averageTableBody').innerHTML =
                '<tr><td colspan="9" class="no-data">Không tìm thấy dữ liệu</td></tr>';
            document.getElementById('detailTableBody').innerHTML =
                '<tr><td colspan="12" class="no-data">Không tìm thấy dữ liệu</td></tr>';
            document.getElementById('summaryBox').style.display = 'none';
        }

        // Reset Data
        function resetData() {
            document.getElementById('maHhInput').value = '';
            document.getElementById('soDhGoInput').value = '';
            document.getElementById('dgiaiVInput').value = '';
            document.getElementById('exportCsvBtn').style.display = 'none';
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
