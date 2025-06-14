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

    @if(session('success'))
        <p style="color: green">{{ session('success') }}</p>
    @endif

    <form method="POST" action="{{ route('mahh.update') }}">
        @csrf
        <table id="mahh-table" border="1" cellpadding="5" cellspacing="0">
            <thead>
                <tr>
                    <th>STT</th>
                    <th>Số CT</th>
                    <th>Số hd</th>
                    <th>Số PO</th>
                    <th>Tên hàng hóa</th>
                    <th>Mã HH (Hiện tại)</th>
                    <th>Tên hàng hóa thật</th>
                    <th>Mã HH (Cập nhật)</th>
                    <th>Cập nhật</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data as $index => $row)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $row->So_ct }}</td>
                        <td>{{ $row->So_hd }}</td>
                        <td>{{ $row->So_dh}}</td>
                        <td>{{ $row->Ten_hh }}</td>
                        <td>{{ $row->Ma_hh }}</td>
                        <td>{{ $row->Soseri }}</td>
                        <td>
                            <input 
                                type="text" 
                                class="mahh-autocomplete" 
                                name="mahh[{{ $row->So_ct }}]" 
                                value="{{ $row->Ma_hh }}" 
                            />
                        </td>
                        <td><button type="submit">Cập nhật</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </form>

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
                                    label: item.Ma_hh + " - " + item.Ten_hh,
                                    value: item.Ma_hh
                                };
                            }));
                        }
                    });
                },
                minLength: 2
            });

            // DataTables
            $('#mahh-table').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/vi.json'
                },
                paging: true,
                searching: true,
                ordering: true,
                columnDefs: [
                    { orderable: false, targets: [4, 5] } // Không sắp xếp 2 cột cuối
                ]
            });
        });
    </script>
</body>
</html>
