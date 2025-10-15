<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tính định mức mực theo đơn hàng</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

    <div class="container py-4">
        <h4 class="mb-4 text-center">Tính định mức mực theo đơn hàng</h4>

        <div class="card p-4 shadow-sm border-0">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Chọn Mã SP:</label>
                    <select id="ma_sp" class="form-select">
                        <option value="">-- Chọn Mã SP --</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold">Tổng khối lượng mực gốc (g):</label>
                    <input type="number" step="0.1" id="tong_khoi_luong_goc" class="form-control" value="200">
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-bold">Định mức tiêu hao (g/pcs):</label>
                    <input type="number" step="0.01" id="dinh_muc_tieu_hao" class="form-control" value="1.2">
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-bold">Số lượng đơn hàng (pcs):</label>
                    <input type="number" id="so_luong_don_hang" class="form-control" value="550">
                </div>

                <div class="col-md-2">
                    <button id="btn_tinh" class="btn btn-primary w-100">Tính định mức</button>
                </div>
            </div>

            <div id="result" class="mt-5" style="display:none;">
                <h6 class="fw-bold mb-3">📊 Kết quả quy đổi định mức:</h6>
                <table class="table table-bordered table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Mã HH</th>
                            <th>Tên HH</th>
                            <th>ĐVT</th>
                            <th>Số lượng gốc (g)</th>
                            <th>Định mức mới (g)</th>
                        </tr>
                    </thead>
                    <tbody id="tbody_data"></tbody>
                    <tfoot class="table-secondary">
                        <tr>
                            <th colspan="3" class="text-end">Tổng định mức:</th>
                            <th id="tong_goc"></th>
                            <th id="tong_moi"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            fetch("{{ route('getKhomuc') }}")
                .then(res => res.json())
                .then(data => {
                    window.allData = data;
                    const uniqueMaSP = [...new Set(data.map(item => item.Ma_sp))];
                    const select = document.getElementById('ma_sp');

                    uniqueMaSP.forEach(ma_sp => {
                        const item = data.find(i => i.Ma_sp === ma_sp);
                        const tenHienThi = item?.ten_sp || item?.hang_hoa?.Ten_hh || ma_sp;
                        const opt = document.createElement('option');
                        opt.value = ma_sp;
                        opt.textContent = `${tenHienThi} (${ma_sp})`;
                        select.appendChild(opt);
                    });
                });

            document.getElementById('btn_tinh').addEventListener('click', function() {
                const ma_sp = document.getElementById('ma_sp').value;
                const dinhMuc = parseFloat(document.getElementById('dinh_muc_tieu_hao').value);
                const soLuong = parseInt(document.getElementById('so_luong_don_hang').value);
                const tongKhoiLuongGoc = parseFloat(document.getElementById('tong_khoi_luong_goc').value);

                if (!ma_sp) return alert("Vui lòng chọn Mã SP!");

                const filtered = window.allData.filter(item => item.Ma_sp === ma_sp);
                if (filtered.length === 0) return alert("Không tìm thấy dữ liệu cho Mã SP này!");

                const tbody = document.getElementById('tbody_data');
                tbody.innerHTML = '';
                const resultDiv = document.getElementById('result');

                const tongMucMoi = dinhMuc * soLuong;
                let tongGoc = 0,
                    tongMoi = 0;

                filtered.forEach(item => {
                    const soLuongGoc = parseFloat(item.Soluong) || 0;
                    tongGoc += soLuongGoc;

                    const mucMoi = soLuongGoc * (tongMucMoi / tongKhoiLuongGoc);
                    tongMoi += mucMoi;

                    const row = `
                <tr>
                    <td>${item.hang_hoa?.Ma_hh ?? item.Ma_hh}</td>
                    <td>${item.hang_hoa?.Ten_hh ?? ''}</td>
                    <td>${item.hang_hoa?.Dvt ?? ''}</td>
                    <td class="text-end">${soLuongGoc.toFixed(4)}</td>
                    <td class="text-end fw-bold">${mucMoi.toFixed(4)}</td>
                </tr>`;
                    tbody.insertAdjacentHTML('beforeend', row);
                });

                document.getElementById('tong_goc').textContent = tongGoc.toFixed(4);
                document.getElementById('tong_moi').textContent = tongMoi.toFixed(4);
                resultDiv.style.display = 'block';
            });
        });
    </script>

</body>

</html>
