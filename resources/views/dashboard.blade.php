<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>QUẢN LÝ LỆNH SẢN XUẤT</title>
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
</head>
<body>
    <h1>BẢNG QUẢN LÝ LỆNH SẢN XUẤT TAGTIME VIỆT TIẾN</h1>
    
    <table id="myTable" class="display">
        <thead>
            <tr>
                <th>STT</th>
                <th>MÃ LỆNH</th>
                <th>TÊN PO</th>
                <th>KHÁCH HÀNG</th>
                <th>MÃ SP</th>
                <th>TÊN SP</th>
                <th>SIZE</th>
                <th>MÀU</th>
                <th>SỐ LƯỢNG ĐƠN HÀNG</th>
                <th>SLSX</th>
                <th>ĐƠN VỊ TÍNH</th>
                <th>NGÀY NHẬN ĐƠN</th>
                <th>NGÀY GIAO</th>
                <th>PHÂN TÍCH</th>
                <th>CHUẨN BỊ</th>
                
                <th>SẢN XUẤT</th>
                <th>NHẬP KHO</th>
                <th>XUẤT KHO</th>
                <th>TÌNH TRẠNG</th>
                
                <!-- Thêm các cột bạn cần -->
            </tr>
        </thead>
        <tbody>
            @foreach ($data as $row)
            
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $row->So_ct }}</td>
                <td>{{ $row->So_dh }}</td>
                <td>{{ $row->Ten_kh }}</td>
                <td>{{ $row->Ma_hh }}</td>
                <td>{{ $row->Ten_hh }}</td>
                
                <td>{{ $row->Msize }}</td>
                <td>{{ $row->Ma_ch }}</td>
                <td>{{ round($row->Soluong, 0) }}</td>
                

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
                </td> <!--Duyệt từ công đoạn 1 đến 4 lấy sum công đoạn cuối cùng -->
                
                <td>{{ $row->Dvt }}</td>
                <td>{{ \Carbon\Carbon::parse($row->Ngay_ct)->format('d/m/Y') }}</td>
                <td>{{ \Carbon\Carbon::parse($row->Date)->format('d/m/Y') }}</td>
                <td>{{ in_array($row->So_ct, $nxSoDhs) ? '✅' : '❌' }}</td>
                
                <td>{{ in_array($row->So_ct, $xvSoDhs) ? '✅' : '❌' }}</td>
                
                @php
                $so_luong_sx = $lastStep;
                
                @endphp
                
                @if ($so_luong_sx >= $row->Soluong)
                <td style="color: green;">Đã hoàn thành ✅</td>
                @elseif ($so_luong_sx > 0)
                <td style="color: orange;">Sản xuất dở dang 🛠️</td>
                @else
                <td style="color: red;">Chưa sản xuất ❌</td>
                @endif
                
                <td>{{ in_array($row->So_ct, $checkNhapKho) ? '✅' : '❌' }}</td>
                <td>{{ in_array($row->So_ct, $checkXuatKho) ? '✅' : '❌' }}</td>
                @if ($row->Date < now())
                <td>Quá hạn ❌</td>
                @else
                <td>Chưa đến hạn</td>
                @endif
                
            </tr>
            @endforeach
        </tbody>
    </table>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#myTable').DataTable();
        });
    </script>
</body>
</html>
