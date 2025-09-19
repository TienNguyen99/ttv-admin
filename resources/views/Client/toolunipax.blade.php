<!doctype html>
<html>

<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Nhập phiếu kho</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="container py-4">
    <h3>Nhập phiếu kho (thủ kho)</h3>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    {{-- P/S nhập hoặc chọn --}}
    <div class="mb-3">
        <label class="form-label">Chọn hoặc nhập P/S</label>
        <input list="psOptions" id="psSelect" class="form-control" placeholder="Nhập hoặc chọn P/S...">
        <datalist id="psOptions">
            @foreach ($psList as $ps)
                <option value="{{ $ps }}">
            @endforeach
        </datalist>
    </div>

    {{-- Khu vực hiển thị danh sách row --}}
    <div id="rowsArea" style="display:none;">
        <h5>Dòng chưa có Delivery/Đạt/Lỗi</h5>
        <table class="table table-sm table-bordered" id="rowsTable">
            <thead>
                <tr>
                    <th>Chọn</th>
                    <th>Row</th>
                    <th>Mã hàng</th>
                    <th>SL Thực nhận</th>
                    <th>Delivery</th>
                    <th>Đạt</th>
                    <th>Lỗi</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <hr>

    {{-- Form nhập phiếu --}}
    <form id="phieuForm" method="POST" action="/phieu-nhap">
        @csrf
        <input type="hidden" name="ps" id="psInput">
        <input type="hidden" name="row_kd" id="rowKdInput">

        <div class="mb-3">
            <label>Số đạt</label>
            <input type="number" name="dat" id="datInput" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Số lỗi</label>
            <input type="number" name="loi" id="loiInput" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-primary">Lưu phiếu</button>
    </form>

    <script>
        const psSelect = document.getElementById('psSelect');
        const rowsArea = document.getElementById('rowsArea');
        const rowsTableBody = document.querySelector('#rowsTable tbody');
        const psInput = document.getElementById('psInput');
        const rowKdInput = document.getElementById('rowKdInput');
        const datInput = document.getElementById('datInput');
        const loiInput = document.getElementById('loiInput');

        // khi người dùng chọn hoặc nhập P/S
        psSelect.addEventListener('change', () => {
            const ps = psSelect.value.trim();
            psInput.value = ps;
            rowsTableBody.innerHTML = '';
            rowKdInput.value = '';
            datInput.value = '';
            loiInput.value = '';

            if (!ps) {
                rowsArea.style.display = 'none';
                return;
            }

            // gọi route lấy danh sách row còn thiếu dữ liệu
            fetch(`/phieu-nhap/rows?ps=${encodeURIComponent(ps)}`)
                .then(r => r.json())
                .then(data => {
                    if (!data || data.length === 0) {
                        rowsTableBody.innerHTML =
                            '<tr><td colspan="7">Không có dòng cần nhập cho P/S này.</td></tr>';
                        rowsArea.style.display = 'block';
                        return;
                    }
                    rowsArea.style.display = 'block';
                    data.forEach(item => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
              <td><input type="radio" name="selectRow" value="${item.row}"></td>
              <td>${item.row}</td>
              <td>${item.mahang}</td>
              <td>${item.sl_thuc}</td>
              <td>${item.delivery}</td>
              <td>${item.dat}</td>
              <td>${item.loi}</td>
            `;
                        rowsTableBody.appendChild(tr);
                    });

                    // click radio -> fill inputs
                    document.querySelectorAll('input[name="selectRow"]').forEach(radio => {
                        radio.addEventListener('change', (e) => {
                            const row = e.target.value;
                            const rowEl = e.target.closest('tr');
                            const slThuc = rowEl.children[3].textContent.trim() || '0';
                            psInput.value = ps;
                            rowKdInput.value = row;
                            datInput.value = slThuc;
                            loiInput.value = 0;
                        });
                    });
                })
                .catch(err => {
                    console.error(err);
                    rowsTableBody.innerHTML = '<tr><td colspan="7">Lỗi khi lấy dữ liệu.</td></tr>';
                    rowsArea.style.display = 'block';
                });
        });
    </script>
</body>

</html>
