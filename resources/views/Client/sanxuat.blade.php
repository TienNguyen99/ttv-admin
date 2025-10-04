<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Danh sách Sản Xuất</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        h2 {
            margin-bottom: 20px;
        }

        table {
            width: 100%;
        }

        input[type="text"] {
            width: 100%;
            box-sizing: border-box;
        }

        button {
            margin-right: 4px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>Danh sách Sản Xuất</h2>
        <table id="sanxuat-table" class="display">
            <thead>
                <tr>
                    <th>Ngày chứng từ</th>
                    <th>Số chứng từ</th>
                    <th>Mã nhân viên</th>
                    <th>Mã kho</th>
                    <th>Mã hàng hóa</th>
                    <th>Số lượng</th>
                    <th>Tiền (VND)</th>
                    <th>Số đơn hàng</th>
                    <th>Khách hàng</th>
                    <th>Tên hàng hóa</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                {{-- dữ liệu load bằng JS --}}
            </tbody>
        </table>
    </div>

    {{-- JS libraries --}}
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <script>
        $(document).ready(function() {
            axios.get('http://192.168.1.13:8888/api/sanxuat')
                .then(response => {
                    let data = response.data;
                    let tableBody = $('#sanxuat-table tbody');

                    data.forEach(item => {
                        tableBody.append(`
                            <tr data-id="${item.SttRecN}">
                                <td class="editable">${item.Ngay_ct ?? ''}</td>
                                <td class="editable">${item.So_ct ?? ''}</td>
                                <td class="editable">${item.Ma_nv ?? ''}</td>
                                <td class="editable">${item.Ma_ko ?? ''}</td>
                                <td class="editable">${item.Ma_hh ?? ''}</td>
                                <td class="editable">${item.Soluong ?? ''}</td>
                                <td class="editable">${item.Tien_vnd ?? ''}</td>
                                <td class="editable">${item.So_dh ?? ''}</td>
                                <td>${item.khach_hang ? item.khach_hang.Ten_kh : ''}</td>
                                <td>${item.hang_hoa ? item.hang_hoa.Ten_hh : ''}</td>
                                <td>
                                    <button class="edit-btn">Sửa</button>
                                    <button class="save-btn" style="display:none">Lưu</button>
                                    <button class="delete-btn">Xóa</button>
                                </td>
                            </tr>
                        `);
                    });

                    let table = $('#sanxuat-table').DataTable({
                        pageLength: 10,
                        language: {
                            url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/vi.json"
                        }
                    });

                    // Click Sửa
                    $('#sanxuat-table').on('click', '.edit-btn', function() {
                        let row = $(this).closest('tr');
                        row.find('.editable').each(function() {
                            let val = $(this).text();
                            $(this).html(`<input type="text" value="${val}" />`);
                        });
                        row.find('.edit-btn').hide();
                        row.find('.save-btn').show();
                    });

                    // Click Lưu
                    $('#sanxuat-table').on('click', '.save-btn', function() {
                        let row = $(this).closest('tr');
                        let SttRecN = row.data('id');

                        let Ngay_ct = row.find('td:eq(0) input').val();
                        let So_ct = row.find('td:eq(1) input').val();
                        let Ma_nv = row.find('td:eq(2) input').val();
                        let Ma_ko = row.find('td:eq(3) input').val();
                        let Ma_hh = row.find('td:eq(4) input').val();
                        let Soluong = row.find('td:eq(5) input').val();
                        let Tien_vnd = row.find('td:eq(6) input').val();
                        let So_dh = row.find('td:eq(7) input').val();

                        axios.put(`http://192.168.1.13:8888/api/sanxuat/${SttRecN}`, {
                            Ngay_ct,
                            So_ct,
                            Ma_nv,
                            Ma_ko,
                            Ma_hh,
                            Soluong,
                            Tien_vnd,
                            So_dh
                        }).then(res => {
                            alert("Cập nhật thành công!");
                            row.find('td:eq(0)').text(Ngay_ct);
                            row.find('td:eq(1)').text(So_ct);
                            row.find('td:eq(2)').text(Ma_nv);
                            row.find('td:eq(3)').text(Ma_ko);
                            row.find('td:eq(4)').text(Ma_hh);
                            row.find('td:eq(5)').text(Soluong);
                            row.find('td:eq(6)').text(Tien_vnd);
                            row.find('td:eq(7)').text(So_dh);

                            row.find('.save-btn').hide();
                            row.find('.edit-btn').show();
                        }).catch(err => {
                            console.error(err);
                            alert("Lỗi khi cập nhật!");
                        });
                    });

                    // Click Xóa
                    $('#sanxuat-table').on('click', '.delete-btn', function() {
                        if (!confirm("Bạn có chắc muốn xóa dòng này?")) return;
                        let row = $(this).closest('tr');
                        let SttRecN = row.data('id');

                        axios.delete(`http://192.168.1.13:8888/api/sanxuat/${SttRecN}`)
                            .then(res => {
                                alert("Xóa thành công!");
                                table.row(row).remove().draw();
                            })
                            .catch(err => {
                                console.error(err);
                                alert("Lỗi khi xóa!");
                            });
                    });
                })
                .catch(error => {
                    console.error("Lỗi khi load dữ liệu:", error);
                });
        });
    </script>
</body>

</html>
