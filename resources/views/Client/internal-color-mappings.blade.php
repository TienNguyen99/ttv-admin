<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Màu nội bộ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/warehouse-wms.css') }}?v={{ filemtime(public_path('css/warehouse-wms.css')) }}" rel="stylesheet">
    <style>
        .color-form { display:grid; grid-template-columns:150px minmax(180px,1fr) 54px 120px 150px minmax(180px,1fr) auto; gap:8px; align-items:end; padding:14px; }
        .color-form label { display:block; margin-bottom:4px; color:#475569; font-size:12px; font-weight:800; }
        .color-picker { width:54px; height:40px; padding:3px; border:1px solid #cbd5e1; border-radius:7px; background:#fff; cursor:pointer; }
        .color-table { min-width:900px; }
        .color-preview { display:inline-flex; align-items:center; gap:9px; }
        .color-swatch-large { width:34px; height:34px; flex:0 0 auto; border:1px solid #cbd5e1; border-radius:7px; background:var(--swatch, #f8fafc); box-shadow:inset 0 0 0 1px rgba(255,255,255,.45); }
        .color-code { color:#174679; font-family:Menlo,Consolas,monospace; font-weight:900; }
        .color-source-note { padding:10px 14px; border-top:1px solid #dbeafe; background:#f8fbff; color:#475569; font-size:12px; }
        @media (max-width:1000px) { .color-form { grid-template-columns:1fr 1fr 54px 120px; } .color-form .color-note, .color-form .color-actions { grid-column:span 2; } }
        @media (max-width:620px) { .color-form { grid-template-columns:1fr 54px; } .color-form > div:not(.color-picker-wrap), .color-form .color-note, .color-form .color-actions { grid-column:1 / -1; } }
    </style>
</head>
<body>
@include('layouts.partials.sidebar')

<header class="wms-topbar">
    <h1 class="wms-topbar__title">WMS May Mặc</h1>
    <div class="wms-global-search">
        <i data-lucide="search"></i>
        <input id="topKeyword" aria-label="Tìm màu nội bộ" placeholder="Tìm mã màu hoặc tên màu...">
    </div>
</header>

<main class="wms-page">
    <div class="wms-heading">
        <div><h1>Màu nội bộ</h1><p>Khai báo một lần để mặt kệ, QR và danh mục hiển thị đúng màu.</p></div>
        <span id="resultLabel" class="text-secondary small">Đang tải...</span>
    </div>

    <section class="wms-panel mb-3">
        <div class="wms-panel__header">
            <div><h2 id="formTitle">Thêm màu</h2><p class="mb-0 text-secondary small">Mã nội bộ không được tự suy đoán thành Pantone.</p></div>
        </div>
        <form id="colorForm" class="color-form" autocomplete="off">
            <input id="mappingId" type="hidden">
            <div><label for="colorCode">Mã màu nội bộ</label><input id="colorCode" class="form-control text-uppercase" required placeholder="807-C"></div>
            <div><label for="colorName">Tên màu</label><input id="colorName" class="form-control" required placeholder="Hồng sen"></div>
            <div class="color-picker-wrap"><label for="colorPicker">Màu</label><input id="colorPicker" class="color-picker" type="color" value="#d92b87" title="Chọn màu"></div>
            <div><label for="colorHex">HEX</label><input id="colorHex" class="form-control text-uppercase" required value="#D92B87" pattern="^#?[0-9a-fA-F]{6}$"></div>
            <div><label for="pantoneCode">Pantone nếu có</label><input id="pantoneCode" class="form-control text-uppercase" placeholder="Để trống"></div>
            <div class="color-note"><label for="colorNote">Ghi chú</label><input id="colorNote" class="form-control" placeholder="Lô nhuộm hoặc mô tả"></div>
            <div class="color-actions d-flex gap-2">
                <button id="cancelEditBtn" type="button" class="wms-btn d-none"><i data-lucide="x"></i>Hủy</button>
                <button id="saveBtn" type="submit" class="wms-btn wms-btn--primary"><i data-lucide="save"></i>Lưu</button>
            </div>
        </form>
        <div class="color-source-note">Ưu tiên hiển thị: Màu nội bộ đã khai báo → HEX/Pantone ghi rõ trong danh mục → tên màu → Chưa khai báo màu.</div>
    </section>

    <section class="wms-panel">
        <div class="wms-panel__header"><h2>Danh sách màu</h2></div>
        <div class="wms-table-wrap">
            <table class="wms-table color-table">
                <thead><tr><th>Màu</th><th>Mã nội bộ</th><th>Tên màu</th><th>HEX</th><th>Pantone</th><th>Ghi chú</th><th></th></tr></thead>
                <tbody id="rows"><tr><td colspan="7" class="wms-loading">Đang tải...</td></tr></tbody>
            </table>
        </div>
    </section>
</main>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const rowsEl = document.getElementById('rows');
const keywordEl = document.getElementById('topKeyword');
const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
let rows = [], timer = null;

function jsonOrError(response, fallback) {
    return response.json().catch(() => ({})).then(result => {
        if (response.ok) return result;
        const errors = result.errors ? Object.values(result.errors).flat().join('\n') : '';
        throw new Error([result.message || fallback, errors].filter(Boolean).join('\n'));
    });
}

function loadRows() {
    const params = new URLSearchParams();
    if (keywordEl.value.trim()) params.set('keyword', keywordEl.value.trim());
    rowsEl.innerHTML = '<tr><td colspan="7" class="wms-loading">Đang tải...</td></tr>';
    fetch('/api/mau-noi-bo?' + params.toString(), {headers:{Accept:'application/json'}})
        .then(response => jsonOrError(response, 'Không tải được màu nội bộ'))
        .then(result => {
            rows = result.data || [];
            document.getElementById('resultLabel').textContent = `${Number(result.summary?.count || 0).toLocaleString('vi-VN')} màu đã khai báo`;
            rowsEl.innerHTML = rows.map(row => `<tr>
                <td><span class="color-swatch-large" style="--swatch:${esc(row.hex)}"></span></td>
                <td class="color-code">${esc(row.color_code)}</td>
                <td><strong>${esc(row.color_name)}</strong></td>
                <td class="color-code">${esc(row.hex)}</td>
                <td>${esc(row.pantone_code || '-')}</td>
                <td>${esc(row.note || '-')}</td>
                <td class="text-end"><div class="d-inline-flex gap-1"><button type="button" class="btn btn-sm btn-outline-primary edit-btn" data-id="${row.id}"><i data-lucide="pencil"></i></button><button type="button" class="btn btn-sm btn-outline-danger delete-btn" data-id="${row.id}"><i data-lucide="trash-2"></i></button></div></td>
            </tr>`).join('') || '<tr><td colspan="7" class="wms-empty">Chưa khai báo màu nội bộ.</td></tr>';
            lucide.createIcons();
        })
        .catch(error => rowsEl.innerHTML = `<tr><td colspan="7" class="wms-empty text-danger">${esc(error.message)}</td></tr>`);
}

function resetForm() {
    document.getElementById('colorForm').reset();
    document.getElementById('mappingId').value = '';
    document.getElementById('colorPicker').value = '#d92b87';
    document.getElementById('colorHex').value = '#D92B87';
    document.getElementById('formTitle').textContent = 'Thêm màu';
    document.getElementById('cancelEditBtn').classList.add('d-none');
    document.getElementById('colorCode').focus();
}

function editRow(id) {
    const row = rows.find(item => String(item.id) === String(id));
    if (!row) return;
    document.getElementById('mappingId').value = row.id;
    document.getElementById('colorCode').value = row.color_code || '';
    document.getElementById('colorName').value = row.color_name || '';
    document.getElementById('colorPicker').value = row.hex || '#d92b87';
    document.getElementById('colorHex').value = String(row.hex || '').toUpperCase();
    document.getElementById('pantoneCode').value = row.pantone_code || '';
    document.getElementById('colorNote').value = row.note || '';
    document.getElementById('formTitle').textContent = `Sửa ${row.color_code}`;
    document.getElementById('cancelEditBtn').classList.remove('d-none');
    document.getElementById('colorName').focus();
}

document.getElementById('colorPicker').addEventListener('input', event => document.getElementById('colorHex').value = event.target.value.toUpperCase());
document.getElementById('colorHex').addEventListener('input', event => {
    const value = event.target.value.trim();
    if (/^#[0-9a-fA-F]{6}$/.test(value)) document.getElementById('colorPicker').value = value;
});
document.getElementById('colorForm').addEventListener('submit', event => {
    event.preventDefault();
    const button = document.getElementById('saveBtn');
    const payload = {
        id: document.getElementById('mappingId').value || null,
        color_code: document.getElementById('colorCode').value.trim(),
        color_name: document.getElementById('colorName').value.trim(),
        hex: document.getElementById('colorHex').value.trim(),
        pantone_code: document.getElementById('pantoneCode').value.trim(),
        note: document.getElementById('colorNote').value.trim(),
    };
    button.disabled = true;
    fetch('/api/mau-noi-bo', {method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken}, body:JSON.stringify(payload)})
        .then(response => jsonOrError(response, 'Không lưu được màu nội bộ'))
        .then(() => { resetForm(); loadRows(); })
        .catch(error => alert(error.message))
        .finally(() => button.disabled = false);
});
document.getElementById('cancelEditBtn').addEventListener('click', resetForm);
rowsEl.addEventListener('click', event => {
    const edit = event.target.closest('.edit-btn');
    if (edit) return editRow(edit.dataset.id);
    const remove = event.target.closest('.delete-btn');
    if (!remove || !confirm('Xóa ánh xạ màu này?')) return;
    fetch(`/api/mau-noi-bo/${remove.dataset.id}`, {method:'DELETE', headers:{Accept:'application/json','X-CSRF-TOKEN':csrfToken}})
        .then(response => jsonOrError(response, 'Không xóa được màu nội bộ'))
        .then(loadRows)
        .catch(error => alert(error.message));
});
keywordEl.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(loadRows, 220); });

loadRows();
lucide.createIcons();
</script>
</body>
</html>
