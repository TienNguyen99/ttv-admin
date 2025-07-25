<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>QUẢN LÝ LỆNH SẢN XUẤT</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/searchpanes/2.2.0/css/searchPanes.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/select/1.7.0/css/select.bootstrap5.min.css" rel="stylesheet">

    <style>
        body {
            padding: 20px;
            background-color: #f8f9fa;
        }

        h1 {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 20px;
            color: #343a40;
        }

        table.dataTable th,
        table.dataTable td {
            white-space: nowrap;
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <h1>BẢNG QUẢN LÝ LỆNH SẢN XUẤT TAGTIME VIỆT TIẾN</h1>

        <table id="myTable" class="table table-striped table-bordered display nowrap" style="width:100%">
            <thead>
                <tr>
                    <th>STT</th>
                    <th>SỐ ĐƠN HÀNG</th>
                    <th>MÃ LỆNH</th>
                    <th>TÊN PO</th>
                    <th>KHÁCH HÀNG</th>
                    <th>Mã HH</th>
                    <th>TÊN SP</th>
                    <th>SIZE</th>
                    <th>MÀU</th>
                    <th>SL ĐƠN HÀNG</th>
                    <th>SỐ LƯỢNG CẦN</th>
                    <th>ĐÃ SẢN XUẤT</th>
                    <th>ĐƠN VỊ TÍNH</th>
                    <th>NGÀY NHẬN ĐƠN</th>
                    <th>NGÀY GIAO</th>
                    <th>PHÂN TÍCH</th>
                    <th>CHUẨN BỊ</th>
                    <th>SẢN XUẤT</th>
                    <th>NHẬP KHO</th>
                    <th>XUẤT KHO</th>
                    <th>TÌNH TRẠNG</th>
                </tr>
            </thead>
            <tbody>
                <!-- Dữ liệu từ Blade/PHP -->
                @foreach ($data as $row)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $row->So_hd }}</td>
                        {{-- <a href="{{ url('/lenh/' . str_replace('/', '-', $row->So_ct)) }}"> {{ $row->So_ct }}   </a> --}}
                        <td>
                            <span class="copy-text" data-text="{{ $row->So_ct }}" style="cursor:pointer; color:blue;">
                                {{ $row->So_ct }}
                            </span>
                        </td>
                        <td>{{ $row->So_dh }}</td>
                        <td>{{ optional($row->khachHang)->Ten_kh ?? '' }}</td>
                        <td>{{ $row->Ma_hh }}</td>
                        <td>{{ optional($row->hangHoa)->Ten_hh ?? '' }}</td>
                        <td>{{ $row->Msize }}</td>
                        <td>{{ $row->Ma_ch }}</td>
                        <td>{{ round($row->Dgbannte, 0) }}</td>
                        <td>{{ round($sumSoLuong[$row->So_ct] ?? 0, 0) }}</td>

                        @php
                            $key = $row->So_ct . '|' . $row->Ma_hh;
                            $cd1 = round($sumCongDoan1[$key]->total_sx1 ?? 0);
                            $cd2 = round($sumCongDoan2[$key]->total_sx2 ?? 0);
                            $cd3 = round($sumCongDoan3[$key]->total_sx3 ?? 0);
                            $cd4 = round($sumCongDoan4[$key]->total_sx4 ?? 0);
                            $lastStep = 0;
                            $lastLabel = '';
                            if ($cd4 > 0) {
                                $lastStep = $cd4;
                                $lastLabel = 'CĐ4';
                            } elseif ($cd3 > 0) {
                                $lastStep = $cd3;
                                $lastLabel = 'CĐ3';
                            } elseif ($cd2 > 0) {
                                $lastStep = $cd2;
                                $lastLabel = 'CĐ2';
                            } elseif ($cd1 > 0) {
                                $lastStep = $cd1;
                                $lastLabel = 'CĐ1';
                            } else {
                                $lastLabel = 'Chưa bắt đầu';
                            }
                        @endphp

                        <td>
                            @if ($lastStep > 0)
                                <span class="text-primary">{{ $lastLabel }} - {{ $lastStep }}</span>
                            @else
                                <span class="text-muted">{{ $lastLabel }}</span>
                            @endif
                        </td>
                        <td>{{ optional($row->hangHoa)->Dvt ?? '' }}</td>
                        <td>{{ \Carbon\Carbon::parse($row->Ngay_ct)->format('d/m/Y') }}</td>
                        <td>{{ \Carbon\Carbon::parse($row->Date)->format('d/m/Y') }}</td>
                        <td>{{ in_array($row->So_ct, $nxSoDhs) ? '✅' : '❌' }}</td>
                        <td>{{ in_array($row->So_ct, $xvSoDhs) ? '✅' : '❌' }}</td>
                        <td>
                            @php $so_luong_sx = $lastStep; @endphp
                            @if ($so_luong_sx >= $row->Soluong)
                                <span class="text-success">Đã hoàn thành ✅</span>
                            @elseif ($so_luong_sx > 0)
                                <span class="text-warning">Sản xuất dở dang 🛠️</span>
                            @else
                                <span class="text-danger">Chưa sản xuất ❌</span>
                            @endif
                        </td>

                        @php
                            $key = $row->So_ct . '|' . $row->Ma_hh;
                            $tong_xuat = isset($checkXuatKho[$key]) ? round($checkXuatKho[$key]->total_xuat, 0) : 0;
                            $tong_nhap = isset($checkNhapKho[$key]) ? round($checkNhapKho[$key]->total_nhap, 0) : 0;
                        @endphp
                        <td>{{ $tong_nhap }}</td>
                        <td>{{ $tong_xuat }}</td>
                        <td>
                            @if ($tong_nhap >= $row->Dgbannte && $tong_xuat == 0)
                                <span class="text-warning">📦 Chưa xuất kho</span>
                            @elseif ($tong_xuat >= $tong_nhap && $tong_nhap > 0)
                                <span class="text-success">✔️ Hoàn thành</span>
                            @elseif ($tong_nhap == 0)
                                <span class="text-danger">⛔ Chưa nhập kho</span>
                            @elseif ($tong_nhap > 0 && $tong_nhap < $row->Dgbannte)
                                <span class="text-warning">📦 Chưa đủ số lượng</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- JS Libraries -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

    <!-- SearchPanes & Select -->
    <script src="https://cdn.datatables.net/select/1.7.0/js/dataTables.select.min.js"></script>
    <script src="https://cdn.datatables.net/searchpanes/2.2.0/js/dataTables.searchPanes.min.js"></script>
    <script src="https://cdn.datatables.net/searchpanes/2.2.0/js/searchPanes.bootstrap5.min.js"></script>

    <!-- DataTable Init -->
    <script>
        $(document).ready(function() {
            $('#myTable').DataTable({
                dom: 'PlBfrtip',
                searchPanes: {
                    cascadePanes: true,
                    layout: 'columns-4'
                },
                columnDefs: [{
                        targets: [3, 13, 14, 20], // KHÁCH HÀNG, NGÀY NHẬN ĐƠN, TÌNH TRẠNG
                        searchPanes: {
                            show: true
                        }
                    },
                    {
                        targets: '_all',
                        searchPanes: {
                            show: false
                        }
                    }
                ],
                buttons: [{
                    extend: 'excelHtml5',
                    text: '📥 Xuất Excel',
                    className: 'btn btn-success',
                    title: 'Bang_Quan_Ly_Lenh_SX'
                }],
                scrollX: true,
                responsive: true,
                pageLength: 10,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/vi.json'
                }
            });
        });
    </script>
    {{-- Script copy --}}
    <script>
        $(document).on('click', '.copy-text', function() {
            const text = $(this).data('text');
            const tempInput = document.createElement("input");
            document.body.appendChild(tempInput);
            tempInput.value = text;
            tempInput.select();
            tempInput.setSelectionRange(0, 99999); // For mobile
            document.execCommand("copy");
            document.body.removeChild(tempInput);

        });
    </script>
</body>

</html>
