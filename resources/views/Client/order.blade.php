<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách Lệnh Sản Xuất</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdn.datatables.net/2.0.8/css/dataTables.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>

    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #e0e7ff;
            --secondary: #0ea5e9;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --background: #f8fafc;
            --surface: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            --card-hover-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.05);
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background);
            color: var(--text-main);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        .page-wrapper {
            max-width: 1600px;
            margin: 0 auto;
            padding: 32px 24px;
        }

        /* Header Area */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 32px;
            background: var(--surface);
            padding: 24px 32px;
            border-radius: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            border: 1px solid rgba(226, 232, 240, 0.8);
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .page-header h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 28px;
            font-weight: 700;
            color: var(--text-main);
            letter-spacing: -0.5px;
        }

        .badge-pulse {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #dcfce7;
            color: #166534;
            font-size: 13px;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 20px;
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            background-color: #22c55e;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.4); }
            70% { box-shadow: 0 0 0 6px rgba(34, 197, 94, 0); }
            100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
        }

        /* Card Grid Layout */
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 24px;
            margin-top: 24px;
        }

        /* Individual Card Design */
        .order-card {
            background: var(--surface);
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: var(--card-shadow);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
        }

        .order-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--card-hover-shadow);
            border-color: #cbd5e1;
        }

        /* Card Top Gradient Bar */
        .card-accent {
            height: 4px;
            width: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }

        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
        }

        .kd-code {
            font-family: 'Outfit', sans-serif;
            font-size: 18px;
            font-weight: 700;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .kd-code span {
            font-size: 12px;
            font-weight: 500;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .lenh-badge {
            background: linear-gradient(135deg, #f43f5e, #e11d48);
            color: white;
            padding: 6px 14px;
            border-radius: 8px;
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 15px;
            box-shadow: 0 2px 4px rgba(225, 29, 72, 0.2);
            letter-spacing: 0.5px;
        }

        .card-body {
            padding: 20px;
            display: flex;
            gap: 16px;
        }

        .product-image-container {
            flex-shrink: 0;
            width: 100px;
            height: 100px;
            border-radius: 12px;
            border: 1px solid var(--border);
            overflow: hidden;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .product-image-container:hover .product-image {
            transform: scale(1.1);
        }

        .no-image {
            color: #94a3b8;
            font-size: 12px;
            font-weight: 500;
        }

        .product-info {
            flex-grow: 1;
            min-width: 0;
        }

        .product-name {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 4px;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-code {
            font-size: 13px;
            color: var(--text-muted);
            font-family: monospace;
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 12px;
        }

        .info-row {
            display: flex;
            align-items: flex-start;
            margin-bottom: 6px;
            font-size: 13px;
        }

        .info-label {
            color: var(--text-muted);
            width: 80px;
            flex-shrink: 0;
            font-weight: 500;
        }

        .info-value {
            color: var(--text-main);
            font-weight: 500;
            word-break: break-word;
        }

        .card-details {
            padding: 16px 20px;
            background: #fafafa;
            border-top: 1px solid var(--border);
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .detail-label {
            font-size: 11px;
            text-transform: uppercase;
            color: var(--text-muted);
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .detail-value {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-main);
        }

        .card-footer {
            padding: 12px 20px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: var(--text-muted);
            background: white;
        }

        /* DataTables Controls Customization */
        .controls-container {
            background: var(--surface);
            padding: 20px 24px;
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            margin-bottom: 24px;
            border: 1px solid var(--border);
        }

        .dt-container .dt-search input {
            font-family: 'Inter', sans-serif;
            font-size: 15px;
            padding: 12px 20px 12px 40px !important;
            border: 2px solid #e2e8f0 !important;
            border-radius: 12px !important;
            width: 400px;
            max-width: 100%;
            transition: all 0.2s;
            background: #f8fafc url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z'%3E%3C/path%3E%3C/svg%3E") no-repeat 12px center;
            background-size: 20px;
        }

        .dt-container .dt-search input:focus {
            background-color: white;
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 4px var(--primary-light) !important;
            outline: none;
        }

        .dt-container .dt-length select {
            font-family: 'Inter', sans-serif;
            padding: 8px 32px 8px 16px !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 8px !important;
            background-color: white;
        }

        .dt-container .dt-info {
            font-size: 14px;
            color: var(--text-muted);
            padding-top: 24px !important;
        }

        .dt-container .dt-paging {
            padding-top: 16px !important;
        }

        .dt-paging-button {
            border-radius: 8px !important;
            margin: 0 4px !important;
            border: 1px solid #e2e8f0 !important;
            font-weight: 500 !important;
            color: var(--text-main) !important;
        }

        .dt-paging-button.current {
            background: var(--primary) !important;
            color: white !important;
            border-color: var(--primary) !important;
        }

        .dt-paging-button:hover:not(.disabled) {
            background: var(--primary-light) !important;
            border-color: var(--primary-light) !important;
            color: var(--primary) !important;
        }

        /* Hide the actual table */
        table.dataTable {
            display: none !important;
        }

        /* Image Modal */
        #imageModal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(4px);
            justify-content: center;
            align-items: center;
        }

        #imageModal.show {
            display: flex;
        }

        #modalImage {
            max-width: 90%;
            max-height: 90vh;
            border-radius: 8px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            border: 4px solid white;
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s;
        }

        .close-modal:hover {
            color: #ef4444;
        }

        @media (max-width: 768px) {
            .card-grid {
                grid-template-columns: 1fr;
            }
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
            .dt-container .dt-search input {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    @include('layouts.partials.sidebar')
    <div class="page-wrapper">
        <div class="page-header">
            <div class="header-title">
                <h1>Tra Cứu Lệnh Sản Xuất</h1>
                <span class="badge-pulse">
                    <span class="pulse-dot"></span>
                    Trực tuyến
                </span>
            </div>
            <div class="header-actions">
                <!-- Action buttons can go here -->
            </div>
        </div>

        <div class="controls-container">
            <!-- DataTables elements will be injected here, but table is hidden -->
            <table id="ordersTable" class="display" style="width:100%">
                <thead>
                    <tr>
                        <!-- Keep the original columns for DataTables to maintain the same index mapping to backend -->
                        <th>Ảnh</th>
                        <th>Ngày CT</th>
                        <th>Số CT</th>
                        <th>Mã KD</th>
                        <th>PO</th>
                        <th>Khách hàng</th>
                        <th>Mã HH</th>
                        <th>Hàng hóa</th>
                        <th>Màu</th>
                        <th>Size</th>
                        <th>Quy cách</th>
                        <th>ĐVT</th>
                        <th>Số lượng</th>
                        <th>Lệnh</th>
                        <th>Số CT LSX</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
            
            <!-- Custom Grid Container to show the cards -->
            <div id="cardGrid" class="card-grid"></div>
        </div>
    </div>

    <!-- Image Modal -->
    <div id="imageModal">
        <span class="close-modal" onclick="closeModal()">&times;</span>
        <img id="modalImage" src="" alt="Full size image">
    </div>

    <script>
        function openImage(src) {
            document.getElementById('modalImage').src = src;
            document.getElementById('imageModal').classList.add('show');
        }

        function closeModal() {
            document.getElementById('imageModal').classList.remove('show');
        }

        // Close modal on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === "Escape") {
                closeModal();
            }
        });

        // Close modal when clicking outside image
        document.getElementById('imageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        function formatNumber(num) {
            if (!num) return '0';
            return parseFloat(num).toLocaleString('vi-VN');
        }

        $(function() {
            const fields = [
                'hang_hoa.Pngpath',     // 0
                'Ngay_ct',              // 1
                'So_ct',                // 2
                'Soseri',               // 3
                'DgiaiV',               // 4
                'khach_hang.Ten_kh',    // 5
                'Ma_hh',                // 6
                'hang_hoa.Ten_hh',      // 7
                'Ma_ch',                // 8
                'Msize',                // 9
                'Ma_so',                // 10
                'hang_hoa.Dvt',         // 11
                'Soluong',              // 12
                'lenh_sanxuat.So_dh',   // 13
                'lenh_sanxuat.So_ct'    // 14
            ];

            $('#ordersTable').DataTable({
                serverSide: true,
                processing: true,
                ajax: {
                    url: '{{ route('orders.data') }}'
                },
                columns: fields.map(f => ({ data: f, defaultContent: '' })),
                language: {
                    url: '//cdn.datatables.net/plug-ins/2.0.8/i18n/vi.json',
                    search: "",
                    searchPlaceholder: "Nhập Mã KD (MLT...), Lệnh, Hàng hóa để tìm kiếm..."
                },
                pageLength: 12,
                lengthMenu: [12, 24, 48, 96],
                order: [[1, 'desc']], // Sort by Ngay_ct desc by default
                
                // Intercept the draw event to render our cards
                drawCallback: function(settings) {
                    var api = this.api();
                    var data = api.rows().data();
                    var $grid = $('#cardGrid');
                    
                    $grid.empty();
                    
                    if (data.length === 0) {
                        $grid.html('<div style="grid-column: 1/-1; text-align: center; padding: 60px 20px; color: var(--text-muted);"><svg style="width: 64px; height: 64px; margin: 0 auto 16px; opacity: 0.5;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg><h3>Không tìm thấy dữ liệu</h3><p>Vui lòng thử từ khóa tìm kiếm khác</p></div>');
                        return;
                    }

                    data.each(function(row) {
                        // Extract data with safe fallbacks
                        const pngPath = row['hang_hoa'] ? row['hang_hoa']['Pngpath'] : null;
                        const maHh = row['Ma_hh'] || '';
                        
                        let imgHtml = '<div class="no-image">No Image</div>';
                        if (pngPath) {
                            const imgSrc = `/hinh_hh/HH_${maHh}/${pngPath}`;
                            imgHtml = `<img src="${imgSrc}" class="product-image" onerror="this.style.display='none'; this.parentElement.innerHTML='<div class=\\'no-image\\'>Error</div>';" onclick="openImage('${imgSrc}')" style="cursor: pointer;">`;
                        }

                        const maKD = row['Soseri'] || 'N/A';
                        const soLenh = row['lenh_sanxuat'] ? row['lenh_sanxuat']['So_dh'] : (row['So_dh'] || 'Chưa có Lệnh');
                        
                        // Parse date for better display
                        let displayDate = row['Ngay_ct'];
                        if (displayDate) {
                            const d = new Date(displayDate);
                            if (!isNaN(d.getTime())) {
                                displayDate = d.toLocaleDateString('vi-VN');
                            }
                        }

                        // Build Card HTML
                        const cardHtml = `
                            <div class="order-card">
                                <div class="card-accent"></div>
                                <div class="card-header">
                                    <div class="kd-code">
                                        <span>Mã KD</span> ${maKD}
                                    </div>
                                    <div class="lenh-badge" title="Số Lệnh">
                                        ${soLenh}
                                    </div>
                                </div>
                                
                                <div class="card-body">
                                    <div class="product-image-container">
                                        ${imgHtml}
                                    </div>
                                    <div class="product-info">
                                        <div class="product-name" title="${row['hang_hoa'] ? row['hang_hoa']['Ten_hh'] : 'N/A'}">
                                            ${row['hang_hoa'] ? row['hang_hoa']['Ten_hh'] : 'N/A'}
                                        </div>
                                        <div class="product-code">${maHh}</div>
                                        
                                        <div class="info-row">
                                            <div class="info-label">Khách:</div>
                                            <div class="info-value">${row['khach_hang'] ? row['khach_hang']['Ten_kh'] : 'N/A'}</div>
                                        </div>
                                        <div class="info-row">
                                            <div class="info-label">PO:</div>
                                            <div class="info-value" style="color: var(--primary); font-weight: 600;">${row['DgiaiV'] || '-'}</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-details">
                                    <div class="detail-item">
                                        <span class="detail-label">Số lượng</span>
                                        <span class="detail-value" style="color: var(--secondary); font-size: 16px;">
                                            ${formatNumber(row['Soluong'])} <span style="font-size: 12px; color: var(--text-muted); font-weight: 500;">${row['hang_hoa'] ? row['hang_hoa']['Dvt'] : ''}</span>
                                        </span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Màu sắc</span>
                                        <span class="detail-value">${row['Ma_ch'] || '-'}</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Size</span>
                                        <span class="detail-value">${row['Msize'] || '-'}</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Quy cách</span>
                                        <span class="detail-value">${row['Ma_so'] || '-'}</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Số CT</span>
                                        <span class="detail-value">${row['So_ct'] || '-'}</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">SCT LSX</span>
                                        <span class="detail-value">${row['lenh_sanxuat'] ? row['lenh_sanxuat']['So_ct'] : '-'}</span>
                                    </div>
                                </div>
                                
                                <div class="card-footer">
                                    <span>Ngày CT: <strong style="color: var(--text-main);">${displayDate}</strong></span>
                                </div>
                            </div>
                        `;
                        
                        $grid.append(cardHtml);
                    });
                }
            });
            
            // Move Datatables controls around to match new layout
            setTimeout(() => {
                $('.dt-layout-row:first').css({'display': 'flex', 'justify-content': 'space-between', 'align-items': 'center', 'margin-bottom': '16px'});
            }, 100);
        });
    </script>
</body>
</html>
