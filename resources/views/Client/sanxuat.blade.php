<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Danh sách Sản Xuất</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
            padding: 5px;
            border: 1px solid #ccc;
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
            axios.defaults.headers.common['X-CSRF-TOKEN'] = $('meta[name="csrf-token"]').attr('content');
            const API_BASE_URL = 'http://192.168.1.13:8888/api/sanxuat';

            axios.get(API_BASE_URL)
                .then(response => {
                    let data = response.data;
                    let tableBody = $('#sanxuat-table tbody');

                    // --- Tạo hàng dữ liệu ---
                    data.forEach(item => {
                        tableBody.append(`
                            <tr data-id="${item.SttRecN}">
                                <td class="editable" data-field="Ngay_ct">${item.Ngay_ct ?? ''}</td>
                                <td class="editable" data-field="So_ct">${item.So_ct ?? ''}</td>
                                <td class="editable" data-field="Ma_nv">${item.Ma_nv ?? ''}</td>
                                <td class="editable" data-field="Ma_ko">${item.Ma_ko ?? ''}</td>
                                <td class="editable" data-field="Ma_hh">${item.Ma_hh ?? ''}</td>
                                <td class="editable" data-field="Soluong">${item.Soluong ?? ''}</td>
                                <td class="editable" data-field="Tien_vnd">${item.Tien_vnd ?? ''}</td>
                                <td class="editable" data-field="So_dh">${item.So_dh ?? ''}</td>
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

                    // --- Sửa ---
                    $('#sanxuat-table').on('click', '.edit-btn', function() {
                        let row = $(this).closest('tr');
                        row.find('.editable').each(function() {
                            let val = $(this).text().trim();
                            $(this).html(`<input type="text" value="${val}" />`);
                        });
                        row.find('.edit-btn').hide();
                        row.find('.save-btn').show();
                    });

                    // --- Lưu (Cách 1: chỉ gửi các ô được sửa) ---
                    $('#sanxuat-table').on('click', '.save-btn', function() {
                        let button = $(this);
                        let row = button.closest('tr');
                        let SttRecN = row.data('id');
                        let updateData = {};

                        // chỉ lấy các field có input (nghĩa là user đang sửa)
                        row.find('td.editable').each(function() {
                            let field = $(this).data('field');
                            let input = $(this).find('input');
                            if (input.length) {
                                updateData[field] = input.val();
                            }
                        });

                        if (Object.keys(updateData).length === 0) {
                            alert("Không có dữ liệu nào thay đổi!");
                            return;
                        }

                        axios.put(`${API_BASE_URL}/${SttRecN}`, updateData)
                            .then(res => {
                                alert("Cập nhật thành công!");
                                // render lại giá trị
                                row.find('.editable').each(function() {
                                    let field = $(this).data('field');
                                    if (updateData[field] !== undefined) {
                                        $(this).text(updateData[field]);
                                    } else {
                                        $(this).text($(this).find('input').val() || $(this)
                                            .text());
                                    }
                                });

                                row.find('.save-btn').hide();
                                row.find('.edit-btn').show();
                            })
                            .catch(err => {
                                console.error(err);
                                alert("Lỗi khi cập nhật!");
                            });
                    });

                    // --- Xóa ---
                    $('#sanxuat-table').on('click', '.delete-btn', function() {
                        if (!confirm("Bạn có chắc muốn xóa dòng này?")) return;

                        let row = $(this).closest('tr');
                        let SttRecN = row.data('id');

                        axios.delete(`${API_BASE_URL}/${SttRecN}`)
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
