<!DOCTYPE html>
<html>

<head>
    <title>Cập nhật Mã HH</title>
    <meta charset="UTF-8">

    <!-- jQuery UI -->
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
</head>

<body>
    <h2>Cập nhật Mã HH trong DataketoanData</h2>

    @if (session('success'))
        <p style="color: green">{{ session('success') }}</p>
    @endif
    <form method="POST" action="{{ route('gui.po') }}" id="form-po">
        @csrf
        <table border="1" cellpadding="5" cellspacing="0">
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all"></th>
                    <th>STT</th>
                    <th>PO</th>

                    {{-- ... --}}
                </tr>
            </thead>
            <tbody>
                @foreach ($data as $index => $row)
                    <tr>
                        <td>
                            <input type="checkbox" name="selected_po[]" value="{{ $row->So_dh }}">
                        </td>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $row->So_dh }}</td>


                    </tr>
                @endforeach
            </tbody>
        </table>
        <button type="submit" class="btn btn-warning">🛠 Gửi PO đã chọn</button>
    </form>
    {{-- ... 
    <form method="POST" action="{{ route('mahh.update') }}">
        @csrf
        <table id="mahh-table" border="1" cellpadding="5" cellspacing="0">
            <thead>
                <tr>
                    <th>STT</th>
                    <th>Lệnh sản xuất</th>
                    <th>Tên PO</th>
                    <th>Khách hàng</th>
                    <th>Tên hàng hóa ( Mã nhãn Kinh doanh đặt )</th>
                    <th>Quy cách</th>
                    <th>Mô tả</th>
                    <th>Kích thước</th>
                    <th>Màu sắc</th>
                    <th>Đơn vị tính</th>
                    <th>Số lượng đặt</th>
                    <th>Ngày nhận</th>
                    <th>Ngày giao</th>
                    <th>Nơi giao</th>
                    <th>Cập nhật</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data as $index => $row)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ optional($row->lenhSanxuat)->So_ct }}</td>
                        <td>{{ $row->So_dh }}</td>
                        <td>{{ $row->khachHang->Ten_kh }}</td>
                        <td>{{ $row->Soseri }}</td>
                        <td>{{ $row->Ma_so }}</td>

                        <td>
                            <input type="text" class="mahh-autocomplete" name="mahh[{{ $row->So_ct }}]"
                                value="{{ $row->Ma_hh }}" />
                        </td>
                        <td>{{ $row->Msize }}</td>
                        <td>{{ $row->Ma_ch }}</td>
                        <td>{{ optional($row->hangHoa)->Dvt }}</td>
                        <td class="text-end">{{ number_format($row->Soluong, 0, ',', '.') }}</td>
                        <td>{{ \Carbon\Carbon::parse($row->Ngay_ct)->format('d/m/Y') }}</td>
                        <td>{{ \Carbon\Carbon::parse($row->Ngay_dh)->format('d/m/Y') }}</td>
                        <td>{{ $row->Ghichu }}</td>
                        <td><button type="submit">Cập nhật</button></td>
                    </tr>
                @endforeach

            </tbody>

        </table>

    </form>
    {{-- {{ $data->links() }} --}}

    <script>
        $(function() {
            // Autocomplete
            $(".mahh-autocomplete").autocomplete({
                source: function(request, response) {
                    $.ajax({
                        url: "{{ route('mahh.suggest') }}",
                        dataType: "json",
                        data: {
                            term: request.term
                        },
                        success: function(data) {
                            response($.map(data, function(item) {
                                return {
                                    label: item.Ma_hh + " - " + item.Ten_hh +
                                        " - " + item.Dvt,
                                    value: item.Ma_hh
                                };
                            }));
                        }
                    });
                },
                minLength: 2
            });


        });
    </script>
    <script>
        $('#select-all').click(function() {
            $('input[name="selected_po[]"]').prop('checked', this.checked);
        });
    </script>
</body>

</html>
