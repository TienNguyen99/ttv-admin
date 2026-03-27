<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách Order</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/2.0.8/css/dataTables.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background: #f0f2f5;
            color: #1a1a2e;
            min-height: 100vh;
        }

        .page-wrapper {
            max-width: 1600px;
            margin: 0 auto;
            padding: 32px 24px;
        }

        /* Header */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .page-header h1 {
            font-size: 24px;
            font-weight: 700;
            color: #1a1a2e;
            letter-spacing: -0.3px;
        }

        .page-header .badge {
            background: #e8f4fd;
            color: #1a73e8;
            font-size: 13px;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 20px;
        }

        /* Card container */
        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 4px 16px rgba(0,0,0,0.04);
            overflow: hidden;
        }

        .card-body {
            padding: 20px 24px 24px;
        }

        /* DataTables overrides */
        table.dataTable {
            border-collapse: collapse !important;
            width: 100% !important;
            font-size: 13px;
        }

        table.dataTable thead th {
            background: #f7f8fa !important;
            color: #5f6368;
            font-weight: 600;
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 14px 12px !important;
            border-bottom: 2px solid #e8eaed !important;
            white-space: nowrap;
        }

        table.dataTable thead th:first-child { border-radius: 8px 0 0 0; }
        table.dataTable thead th:last-child { border-radius: 0 8px 0 0; }

        table.dataTable tbody td {
            padding: 12px 12px !important;
            border-bottom: 1px solid #f1f3f4 !important;
            color: #3c4043;
            vertical-align: middle;
        }

        table.dataTable tbody tr:hover td {
            background: #f0f6ff !important;
        }

        table.dataTable tbody tr:last-child td {
            border-bottom: none !important;
        }

        /* Sorting icons */
        table.dataTable thead .dt-orderable-asc,
        table.dataTable thead .dt-orderable-desc {
            cursor: pointer;
        }

        /* Search & length controls */
        .dt-search {
            margin-bottom: 16px !important;
        }

        .dt-search input {
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            padding: 10px 16px !important;
            border: 1.5px solid #dadce0 !important;
            border-radius: 10px !important;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            min-width: 280px;
        }

        .dt-search input:focus {
            border-color: #1a73e8 !important;
            box-shadow: 0 0 0 3px rgba(26,115,232,0.12) !important;
        }

        .dt-search label {
            font-size: 13px;
            color: #5f6368;
            font-weight: 500;
        }

        .dt-length select {
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            padding: 8px 12px;
            border: 1.5px solid #dadce0;
            border-radius: 8px;
            outline: none;
            background: #fff;
            cursor: pointer;
        }

        .dt-length label {
            font-size: 13px;
            color: #5f6368;
            font-weight: 500;
        }

        /* Pagination */
        .dt-paging {
            margin-top: 16px !important;
            display: flex;
            justify-content: flex-end;
        }

        .dt-paging-button {
            font-family: 'Inter', sans-serif !important;
            font-size: 13px !important;
            font-weight: 500 !important;
            padding: 8px 14px !important;
            margin: 0 2px !important;
            border: 1.5px solid #dadce0 !important;
            border-radius: 8px !important;
            background: #fff !important;
            color: #3c4043 !important;
            cursor: pointer;
            transition: all 0.15s;
        }

        .dt-paging-button:hover:not(.disabled) {
            background: #f0f6ff !important;
            border-color: #1a73e8 !important;
            color: #1a73e8 !important;
        }

        .dt-paging-button.current {
            background: #1a73e8 !important;
            border-color: #1a73e8 !important;
            color: #fff !important;
        }

        .dt-paging-button.disabled {
            opacity: 0.4;
            cursor: default;
        }

        /* Info text */
        .dt-info {
            font-size: 13px;
            color: #80868b;
            padding-top: 20px !important;
        }

        /* Processing indicator */
        .dt-processing {
            background: rgba(255,255,255,0.95) !important;
            border: none !important;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            border-radius: 12px !important;
            padding: 20px 32px !important;
            font-size: 14px;
            color: #5f6368;
            font-weight: 500;
        }

        /* Empty state */
        .dataTables_empty {
            padding: 40px !important;
            color: #80868b;
            font-size: 14px;
        }

        /* Product image */
        .order-img {
            width: 48px;
            height: 48px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #e8eaed;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .order-img:hover {
            transform: scale(2.5);
            z-index: 100;
            position: relative;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
    </style>
</head>

<body>
    <div class="page-wrapper">
        <div class="page-header">
            <h1>Danh sách Order</h1>
            <span class="badge">Server-side</span>
        </div>

        <div class="card">
            <div class="card-body">
                <table id="ordersTable" class="display" style="width:100%">
                    <thead>
                        <tr>
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
            </div>
        </div>
    </div>

    <script>
        $(function() {
            const fields = [
                'hang_hoa.Pngpath',
                'Ngay_ct', 'So_ct', 'Soseri', 'DgiaiV',
                'khach_hang.Ten_kh', 'Ma_hh', 'hang_hoa.Ten_hh',
                'Ma_ch',
                'Msize', 'Ma_so', 'hang_hoa.Dvt',
                'Soluong', 'lenh_sanxuat.So_dh', 'lenh_sanxuat.So_ct'
            ];

            const nonSortable = [0, 5, 7, 11, 13, 14];

            $('#ordersTable').DataTable({
                serverSide: true,
                processing: true,
                ajax: {
                    url: '{{ route('orders.data') }}'
                },
                columns: fields.map(f => ({
                    data: f
                })),
                columnDefs: [
                    { defaultContent: '', targets: '_all' },
                    { orderable: false, targets: nonSortable },
                    {
                        targets: 0,
                        render: function(data, type, row) {
                            if (!data) return '';
                            const maHh = row.Ma_hh || '';
                            return `<img src="/hinh_hh/HH_${maHh}/${data}" class="order-img" onerror="this.style.display='none'">`;
                        }
                    }
                ],
                language: {
                    url: '//cdn.datatables.net/plug-ins/2.0.8/i18n/vi.json'
                },
                pageLength: 10,
                order: [
                    [0, 'desc']
                ],
                responsive: true
            });
        });
    </script>
</body>

</html>
