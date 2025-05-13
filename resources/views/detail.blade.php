<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CHI TIẾT LỆNH SẢN XUẤT</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        h1, h2 {
            text-align: center;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        th {
            background-color: #4CAF50;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .status-green {
            color: green;
        }
        .status-red {
            color: red;
        }
    </style>
</head>
<body>
    <h1>CHI TIẾT LỆNH SỐ: {{ $lenh->first()->So_ct }} - MADE IN VIETNAM</h1>
    <h2>SỐ LƯỢNG ĐƠN HÀNG</h2>
    @foreach ($lenh as $item)
    <table>
        <thead>
            <tr>
                <th>CÔNG ĐOẠN</th>
                <th>MÃ HÀNG</th>
                <th>TÊN NGUYÊN LIỆU</th>
                <th>ĐỊNH MỨC NGUYÊN LIỆU CHÍNH</th>
                <th>CẤP PHỐI (% Nguyên liệu tái chế)</th>
                <th>ĐƠN VỊ TÍNH</th>
                <th>SỐ LƯỢNG ĐỊNH MỨC (ĐVT tính bằng gram)</th>
                <th>SỐ LƯỢNG SẢN XUẤT THỰC TẾ (ĐVT tính bằng gram)</th>
                <th>CHÊNH LỆCH</th>
                <th>% HẠT HƯ</th>
                <th>NGÀY SẢN XUẤT</th>
                <th>NGÀY NHẬP KHO</th>
                <th>TÌNH TRẠNG</th>
            </tr>
        </thead>
        <tbody>
            
            <tr>
                <td>{{ $item->Ma_ko }}</td>
                <td>{{ $item->DgiaiV }}</td>
                <td>{{ $item->Ten_hh }}</td>
                <td>{{ $item->Tshh }}</td>
                <td>{{ $item->Tsgt ?? 'N/A' }}</td>
                <td>{{ $item->Dvt }}</td>
                <td>{{ round($item->Soluong, 0) }}</td>
                <td>{{ round($item->Noluong, 0) }}</td>
                <td>{{ round($item->Noluong - $item->Soluong, 0) }}</td>
                <td>{{ $item->Tsgt ? round(($item->Noluong - $item->Soluong) / $item->Soluong * 100, 2) : 'N/A' }}%</td>
                <td>{{ \Carbon\Carbon::parse($item->Ngay_ct)->format('d/m/Y') }}</td>
                <td>{{ $item->Ngay2ct ? \Carbon\Carbon::parse($item->Ngay2ct)->format('d/m/Y') : 'Chưa nhập kho' }}</td>
                <td>
                    @if ($item->Status == 'HOAN THANH')
                        <span class="status-green">ĐÃ HOÀN THÀNH</span>
                    @else
                        <span class="status-red">ĐANG SẢN XUẤT</span>
                    @endif
                </td>
            </tr>
            
        </tbody>
    </table>
@endforeach
    <h2>TIẾN ĐỘ SẢN XUẤT - Kết thúc ca SX</h2>
    <table>
        <thead>
            <tr>
                <th>CÔNG ĐOẠN</th>
                <th>MÃ NHÂN VIÊN</th>
                <th>NGÀY SẢN XUẤT</th>
                <th>SỐ LƯỢNG SẢN XUẤT</th>
                <th>SỐ LƯỢNG ĐẠT</th>
                <th>SỐ LƯỢNG HỎNG</th>
                <th>SỐ LƯỢNG HỦY</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lenh as $item)
            <tr>
                <td>{{ $item->Ma_ko }}</td>
                <td>{{ $item->Ma_nv }}</td>
                <td>{{ \Carbon\Carbon::parse($item->Ngay_ct)->format('d/m/Y') }}</td>
                <td>{{ round($item->Soluong, 0) }}</td>
                <td>{{ round($item->Noluong, 0) }}</td>
                <td>{{ round($item->Soluong - $item->Noluong, 0) }}</td>
                <td>{{ $item->TienHnte ?? 0 }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>