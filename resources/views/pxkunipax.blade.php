<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Danh sách chứng từ</title>

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

    <!-- DataTables CSS + Responsive -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <style>
        body {
            padding: 2rem;
        }
        div.dataTables_wrapper {
            width: 100%;
            overflow-x: auto;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.2em 0.8em;
            margin-left: 2px;
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <h3 class="mb-4">📄 IN PHIẾU XUẤT KHO UNIPAX</h3>

    <div class="table-responsive">
        <table id="myTable" class="table table-striped table-bordered nowrap w-100" style="width:100%">
            <thead class="table-dark text-center">
                <tr>
                    <th>STT</th>
                    <th>Số chứng từ</th>
                    <th>Ngày Xuất hàng</th>
                    <th>Mã hàng</th>
                    <th>P/S</th>
                    <th>Size</th>
                    <th>Màu vải</th>
                    <th>Đơn vị tính</th>
                    <th>Số lượng đơn hàng</th>
                    <th>Front đạt</th>
                    <th>Front lỗi</th>
                    <th>Back đạt</th>
                    <th>Back lỗi</th>
                    <th>Ghi chú</th>
                    <th>S2315CA1028+GFU</th>
                    <th>TỔNG HỢP MÃ KHÁC</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data as $row)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td>{{ $row->So_ct }}</td>
                    <td>{{ \Carbon\Carbon::parse($row->Ngay_ct)->format('d/m/Y') }}</td>
                    <td>{{ $row->Ten_hh }}</td>
                    <td>{{ $row->Soseri }}</td>
                    <td>{{ $row->Msize }}</td>
                    <td>{{ $row->Ma_ch }}</td>
                    <td>{{ $row->Dvt }}</td>
                    <td class="text-end">{{ number_format($row->Soluong, 0, ',', '.') }}</td>
                    <td class="text-center">{{ $row->TienCvnd }}</td>
                    <td class="text-center">{{ $row->TienCnte }}</td>
                    <td class="text-center">{{ $row->TienHvnd }}</td>
                    <td class="text-center">{{ $row->TienHnte }}</td>
                    <td>{{ $row->DgiaiV }}</td>
                    <td class="text-center">
                        <a href="{{ route('phieuxuat.export', ['so_ct' => str_replace('/', '-', $row->So_ct)]) }}"
                           class="btn btn-sm btn-success" target="_blank" title="Xuất Excel">
                            <i class="bi bi-file-earmark-excel-fill"></i> Xuất
                        </a>
                    </td>
                                        <td class="text-center">
                        <a href="{{ route('pxkunipax.export', ['so_ct' => str_replace('/', '-', $row->So_ct)]) }}"
                           class="btn btn-sm btn-success" target="_blank" title="Xuất Excel">
                            <i class="bi bi-file-earmark-excel-fill"></i> Xuất
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<!-- JQuery -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>

<!-- DataTables + Bootstrap 5 JS + Responsive -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

<!-- DataTables Language - Vietnamese -->
<script>
    $(document).ready(function () {
        $('#myTable').DataTable({
            responsive: false,     // tắt tự ẩn cột
            scrollX: true,         // cho phép cuộn ngang
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/vi.json'
            },
            pageLength: 10,
            lengthMenu: [ [10, 25, 50, -1], [10, 25, 50, "Tất cả"] ]
        });
    });
</script>

</body>
</html>
