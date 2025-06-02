<!DOCTYPE html>
<html>
<head>
    <title>Nhập liệu sản xuất</title>
</head>
<body>
    @if(session('success'))
        <p style="color: green">{{ session('success') }}</p>
    @endif

    <form action="/nhap-lieu" method="POST">
        @csrf
        <label>Lệnh SX:</label>
        <input type="text" name="lenh_sx"><br>

        <label>Tên sản phẩm:</label>
        <input type="text" name="ten_san_pham"><br>

        <label>Công đoạn:</label>
        <input type="text" name="cong_doan"><br>

        <label>Số lượng SX:</label>
        <input type="number" name="so_luong_sx"><br>

        <label>Số lượng đạt:</label>
        <input type="number" name="so_luong_dat"><br>

        <label>Số lượng hư:</label>
        <input type="number" name="so_luong_hu"><br>

        <button type="submit">Gửi</button>
    </form>
</body>
</html>
