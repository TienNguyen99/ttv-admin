<!doctype html>
<html lang="vi">

<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Phiếu kho Unipax</title>
    <meta name="viewport" content="width=device-width, initial-scale=1"> <!-- responsive -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #f8f9fa;
            font-size: 16px;
        }

        .card {
            border-radius: 12px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            font-size: 18px;
            padding: 12px;
            border-radius: 10px;
            width: 100%;
        }

        input,
        select {
            font-size: 18px !important;
            padding: 10px !important;
        }

        table {
            font-size: 14px;
        }

        th,
        td {
            text-align: center;
            vertical-align: middle !important;
        }

        .fixed-bottom-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid #ccc;
            padding: 10px;
            z-index: 100;
        }
    </style>
</head>

<body class="container py-3">

    <h4 class="text-center mb-3 fw-bold text-primary">📦 Nhập phiếu kho (Unipax)</h4>
    <a href="{{ route('phieuunipax.refreshCache') }}" class="btn btn-outline-secondary btn-sm">
        🔄 Làm mới dữ liệu
    </a>
    {{-- Thông báo --}}
    @if (session('success'))
        <div class="alert alert-success p-2 text-center">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger p-2 text-center">{{ session('error') }}</div>
    @endif

    {{-- Chọn P/S --}}
    <div class="card mb-3 p-3">
        <label class="form-label fw-semibold">🔍 Chọn hoặc nhập mã P/S</label>
        <input list="psOptions" id="psSelect" class="form-control" placeholder="Nhập hoặc chọn P/S...">

        <datalist id="psOptions">
            @foreach ($psList as $ps)
                <option value="{{ $ps }}">
            @endforeach
        </datalist>
    </div>
    {{-- Xem toàn bộ phiếu đã nhập --}}
    <div class="text-end mb-2">
        <button type="button" id="btnViewAll" class="btn btn-outline-primary btn-sm">
            📄 Xem toàn bộ phiếu đã nhập
        </button>
    </div>
    {{-- Modal xem toàn bộ --}}
    <div class="modal fade" id="viewAllModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">📋 Danh sách phiếu đã nhập</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewAllContent">
                    <p class="text-center text-muted">Chưa có dữ liệu.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Danh sách dòng --}}
    <div id="rowsArea" class="card p-2" style="display:none;">
        <h6 class="fw-semibold text-secondary mb-2">📋 Dòng chưa có Delivery/Đạt/Lỗi</h6>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle" id="rowsTable">
                <thead class="table-light">
                    <tr>
                        <th>Chọn</th>
                        <th>Dòng</th>
                        <th>Mã hàng</th>
                        <th>Màu</th>
                        <th>Size</th>
                        <th>SL</th>

                        <th>Mặt</th>
                        <th>Ghi chú</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    {{-- Form nhập --}}
    <form id="phieuForm" method="POST" action="{{ route('phieuunipax.store') }}" style="display:none;">
        @csrf
        <input type="hidden" name="ps" id="psInput">
        <input type="hidden" name="row_kd" id="rowKdInput">

        <div class="card mt-3 p-3">
            <div class="mb-3">
                <label class="form-label fw-semibold">✅ Số đạt</label>
                <input type="number" name="dat" id="datInput" class="form-control text-center fw-bold"
                    style="font-size: 22px;" required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold text-danger">❌ Số lỗi</label>
                <input type="number" name="loi" id="loiInput" class="form-control text-center fw-bold text-danger"
                    style="font-size: 22px;" required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">📝 Ghi chú (nếu có)</label>
                <input type="text" name="ghichu" id="ghichuInput" class="form-control"
                    placeholder="Nhập ghi chú...">
            </div>

            <div class="fixed-bottom-bar">
                <button type="submit" class="btn btn-primary">💾 Lưu phiếu</button>
            </div>
    </form>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const psSelect = document.getElementById('psSelect');
        const rowsArea = document.getElementById('rowsArea');
        const rowsTableBody = document.querySelector('#rowsTable tbody');
        const phieuForm = document.getElementById('phieuForm');
        const psInput = document.getElementById('psInput');
        const rowKdInput = document.getElementById('rowKdInput');
        const datInput = document.getElementById('datInput');
        const loiInput = document.getElementById('loiInput');
        const ghichuInput = document.getElementById('ghichuInput');

        psSelect.addEventListener('change', () => {
            const ps = psSelect.value.trim();
            psInput.value = ps;
            rowsTableBody.innerHTML = '';
            rowKdInput.value = '';
            datInput.value = '';
            loiInput.value = '';
            ghichuInput.value = '';

            if (!ps) {
                rowsArea.style.display = 'none';
                phieuForm.style.display = 'none';
                return;
            }

            fetch(`/phieu-nhap/rows?ps=${encodeURIComponent(ps)}`)
                .then(r => r.json())
                .then(data => {
                    if (!data || data.length === 0) {
                        rowsTableBody.innerHTML =
                            '<tr><td colspan="4" class="text-center text-muted py-2">Không có dòng cần nhập.</td></tr>';
                        rowsArea.style.display = 'block';
                        phieuForm.style.display = 'none';
                        return;
                    }

                    rowsArea.style.display = 'block';
                    phieuForm.style.display = 'block';
                    rowsTableBody.innerHTML = '';

                    data.forEach(item => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td><input type="radio" name="selectRow" value="${item.row}" style="transform: scale(1.5);"></td>
                            <td>${item.row}</td>
                            <td>${item.mahang}</td>
                            <td>${item.mau}</td>
                            <td>${item.size}</td>
                            <td>${item.sl_thuc}</td>
                            
                            <td>${item.mat}</td>
                            <td>${item.ghichu || ''}</td>
                        `;
                        rowsTableBody.appendChild(tr);
                    });

                    // khi chọn radio -> fill form
                    document.querySelectorAll('input[name="selectRow"]').forEach(radio => {
                        radio.addEventListener('change', e => {
                            const row = e.target.value;
                            const tr = e.target.closest('tr');
                            const slThuc = tr.children[5].textContent.trim() || '0';
                            rowKdInput.value = row;
                            datInput.value = slThuc;
                            loiInput.value = 0;
                            datInput.focus();
                        });
                    });
                })
                .catch(err => {
                    console.error(err);
                    rowsTableBody.innerHTML = '<tr><td colspan="4">Lỗi khi lấy dữ liệu.</td></tr>';
                    rowsArea.style.display = 'block';
                    phieuForm.style.display = 'none';
                });
        });
        // Xem toàn bộ phiếu đã nhập
        const btnViewAll = document.getElementById('btnViewAll');
        const viewAllContent = document.getElementById('viewAllContent');

        btnViewAll.addEventListener('click', () => {
            viewAllContent.innerHTML = '<p class="text-center text-muted py-2">⏳ Đang tải dữ liệu...</p>';

            fetch('/phieu-nhap/view-all')
                .then(r => r.json())
                .then(data => {
                    if (!data || data.length === 0) {
                        viewAllContent.innerHTML =
                            '<p class="text-center text-muted">Chưa có phiếu nào được nhập.</p>';
                        return;
                    }

                    let html = `
                <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle text-center">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Ngày</th>
                            <th>P/S</th>
                            <th>Dòng KD</th>
                            <th>Đạt</th>
                            <th>Lỗi</th>
                            <th>Trạng thái</th>
                            <th>Người nhập</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${data.map((item, i) => `
                                        <tr>
                                            <td>${i + 1}</td>
                                            <td>${item.ngay}</td>
                                            <td>${item.ps}</td>
                                            <td>${item.row_kd}</td>
                                            <td>${item.dat}</td>
                                            <td class="text-danger">${item.loi}</td>
                                            <td>${item.trangthai}</td>
                                            <td>${item.nguoitao}</td>
                                        </tr>
                                    `).join('')}
                    </tbody>
                </table>
                </div>
            `;
                    viewAllContent.innerHTML = html;
                })
                .catch(err => {
                    console.error(err);
                    viewAllContent.innerHTML = '<p class="text-center text-danger">Lỗi khi tải dữ liệu.</p>';
                });

            const modal = new bootstrap.Modal(document.getElementById('viewAllModal'));
            modal.show();
        });
    </script>

</body>

</html>
