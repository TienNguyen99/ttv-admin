<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>QUẢN LÝ LỆNH SẢN XUẤT</title>

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/searchpanes/2.2.0/css/searchPanes.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/select/1.7.0/css/select.dataTables.min.css">
</head>
<body>
    <h1>BẢNG QUẢN LÝ LỆNH SẢN XUẤT TAGTIME VIỆT TIẾN</h1>

    <table id="myTable" class="display nowrap" style="width:100%">
        <thead>
            <tr>
                <th>STT</th>
                <th>MÃ LỆNH</th>
                <th>TÊN PO</th>
                <th>KHÁCH HÀNG</th>
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
            @foreach ($data as $row)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td><a href="{{ url('/lenh/' . str_replace('/', '-', $row->So_ct)) }}">{{ $row->So_ct }}</a></td>
                <td>{{ $row->So_dh }}</td>
                <td>{{ $row->Ten_kh }}</td>
                <td>{{ $row->Ten_hh }}</td>
                <td>{{ $row->Msize }}</td>
                <td>{{ $row->Ma_ch }}</td>
                <td>{{ round($row->Soluong, 0) }}</td>
                <td>{{ round($sumSoLuong[$row->So_ct] ?? 0, 0) }}</td>

                @php
                    $cd1 = round($sumCongDoan1[$row->So_ct] ?? 0, 0);
                    $cd2 = round($sumCongDoan2[$row->So_ct] ?? 0, 0);
                    $cd3 = round($sumCongDoan3[$row->So_ct] ?? 0, 0);
                    $cd4 = round($sumCongDoan4[$row->So_ct] ?? 0, 0);
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
                        <span style="color: blue;">{{ $lastLabel }} - {{ $lastStep }}</span>
                    @else
                        <span style="color: gray;">{{ $lastLabel }}</span>
                    @endif
                </td>
                <td>{{ $row->Dvt }}</td>
                <td>{{ \Carbon\Carbon::parse($row->Ngay_ct)->format('d/m/Y') }}</td>
                <td>{{ \Carbon\Carbon::parse($row->Date)->format('d/m/Y') }}</td>
                <td>{{ in_array($row->So_ct, $nxSoDhs) ? '✅' : '❌' }}</td>
                <td>{{ in_array($row->So_ct, $xvSoDhs) ? '✅' : '❌' }}</td>
                <td>
                    @php $so_luong_sx = $lastStep; @endphp
                    @if ($so_luong_sx >= $sumSoLuong[$row->So_ct])
                        <span style="color: green;">Đã hoàn thành ✅</span>
                    @elseif ($so_luong_sx > 0)
                        <span style="color: orange;">Sản xuất dở dang 🛠️</span>
                    @else
                        <span style="color: red;">Chưa sản xuất ❌</span>
                    @endif
                </td>
                <td>{{ in_array($row->So_ct, $checkNhapKho) ? '✅' : '❌' }}</td>
                <td>{{ in_array($row->So_ct, $checkXuatKho) ? '✅' : '❌' }}</td>
                <td>
                    @if ($row->Date < now())
                        Quá hạn ❌
                    @else
                        Chưa đến hạn
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- JS Libraries -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

    <!-- SearchPanes + Select -->
    <script src="https://cdn.datatables.net/select/1.7.0/js/dataTables.select.min.js"></script>
    <script src="https://cdn.datatables.net/searchpanes/2.2.0/js/dataTables.searchPanes.min.js"></script>

    <script>
        $(document).ready(function () {
            $('#myTable').DataTable({
                dom: 'PlBfrtip',
                searchPanes: {
                    cascadePanes: true,
                    layout: 'columns-4'
                },
                columnDefs: [
                    {
                        targets: [3, 11, 18], // Chỉ hiện SearchPane cho KHÁCH HÀNG, NGÀY NHẬN ĐƠN, TÌNH TRẠNG
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
                buttons: [
                    {
                        extend: 'excelHtml5',
                        text: '📥 Xuất Excel',
                        title: 'Bang_Quan_Ly_Lenh_SX'
                    }
                ],
                scrollX: true,
                pageLength: 25
            });
        });
    </script>
</body>
</html>
