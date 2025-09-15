<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách đơn hàng</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>

<body>
    <div class="container-fluid text-center mt-4">
        <h2 class="mb-4">📺 Dashboard Lệnh Sản Xuất</h2>
        <table class="table table-bordered table-striped" id="dashboardTable">
            <thead class="table-dark">
                <tr>
                    <th>Mã Lệnh</th>
                    <th>Khách hàng</th>
                    <th>Mã hàng hóa</th>
                    <th>Sản phẩm</th>
                    <th>Số lượng</th>
                    <th>Ngày giao</th>
                    <th>Trạng thái</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
    <script>
        function loadDashboard() {
            fetch("http://192.168.1.13:8888/api/tivi")
                .then(res => res.json())
                .then(data => {
                    let tbody = document.querySelector("#dashboardTable tbody");
                    tbody.innerHTML = "";
                    data.forEach(d => {
                        let ngayGiao = new Date(d.Date);
                        let today = new Date();
                        let status = "";
                        if (ngayGiao < today) status = "<span class='text-danger'>❌ Quá hạn</span>";
                        else status = "<span class='text-warning'>⚠️ Sắp đến hạn</span>";

                        tbody.innerHTML += `
                        <tr>
                            <td>${d.So_dh}</td>
                            <td>${d.khach_hang?.Ten_kh ?? ''}</td>
                            <td>${d.Soseri}</td>
                            <td>${d.hang_hoa?.Ten_hh ?? ''}</td>
                            <td>${Math.round(d.Soluong)}</td>
                            <td>${ngayGiao.toLocaleDateString("vi-VN")}</td>
                            <td>${status}</td>
                        </tr>
                    `;
                    });
                });
        }

        loadDashboard();
        setInterval(loadDashboard, 30000); // reload mỗi 30s
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
