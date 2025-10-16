<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Danh sách Order</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- ✅ DataTables CDN -->
    <link rel="stylesheet" href="https://cdn.datatables.net/2.0.8/css/dataTables.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f9f9f9;
        }

        h2 {
            color: #2c3e50;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }

        th {
            background: #2c3e50;
            color: #fff;
        }
    </style>
</head>

<body>
    <h2>Danh sách Order</h2>

    <table id="ordersTable" class="display">
        <thead>
            <tr>
                <th>Ngày chứng từ</th>
                <th>Số CT</th>
                <th>Mã KD</th>
                <th>PO</th>
                <th>Khách hàng</th>
                <th>Hàng hóa</th>
                <th>Màu</th>
                <th>Size</th>
                <th>Quy cách</th>
                <th>ĐVT</th>
                <th>Số lượng</th>
                <th>Lệnh</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>

    <script>
        $(function() {
            const fields = [
                'Ngay_ct', 'So_ct', 'Soseri', 'DgiaiV',
                'khach_hang.Ten_kh', 'hang_hoa.Ten_hh',
                'Ma_ch',
                'Msize', 'Ma_so', 'hang_hoa.Dvt',
                'Soluong', 'lenh_sanxuat.So_dh'
            ];

            $('#ordersTable').DataTable({
                ajax: {
                    url: '{{ route('orders.data') }}',
                    dataSrc: ''
                },
                columns: fields.map(f => ({
                    data: f
                })),
                columnDefs: [{
                    defaultContent: '',
                    targets: '_all'
                }], // 👈 Áp dụng default cho toàn bộ
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
