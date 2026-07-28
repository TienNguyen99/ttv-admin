<!doctype html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Tạo lệnh dệt | WMS May Mặc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/warehouse-wms.css') }}?v={{ filemtime(public_path('css/warehouse-wms.css')) }}" rel="stylesheet">
    <style>
        :root { --designer-blue:#2563eb; --designer-soft:#eff6ff; --designer-border:#cbdcf4; --designer-text:#102a4c; }
        html,body { max-width:100%; overflow-x:hidden; }
        .designer-create { max-width:1600px; min-width:0; margin:0 auto; }
        .designer-find { position:relative; padding:16px; border:1px solid var(--designer-border); background:#fff; border-radius:8px; }
        .designer-find-row { display:grid; grid-template-columns:minmax(280px,1fr) auto; gap:10px; }
        .designer-find-input { position:relative; min-width:0; }
        .designer-find-input input,.designer-form-panel,.designer-preview,.designer-field { width:100%; min-width:0; }
        .designer-find-input > svg { position:absolute; left:13px; top:50%; width:18px; transform:translateY(-50%); color:#4f77a8; pointer-events:none; }
        .designer-find-input input { min-height:46px; padding-left:42px; font-weight:750; border-color:#9fbae0; }
        .designer-suggestions { position:absolute; z-index:30; top:calc(100% + 6px); left:0; right:0; max-height:340px; overflow:auto; border:1px solid #b7cbea; border-radius:8px; background:#fff; box-shadow:0 16px 40px rgba(30,64,175,.15); }
        .designer-suggestion { width:100%; display:grid; grid-template-columns:minmax(150px,.7fr) minmax(220px,1.2fr) auto; gap:12px; align-items:center; padding:11px 13px; border:0; border-bottom:1px solid #e4edf9; background:#fff; text-align:left; color:var(--designer-text); }
        .designer-suggestion:hover,.designer-suggestion:focus { background:#eff6ff; outline:0; }
        .designer-suggestion:last-child { border-bottom:0; }
        .designer-suggestion strong { color:#155fc0; }
        .designer-steps { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; margin:12px 0; }
        .designer-step { display:flex; align-items:center; gap:8px; padding:9px 11px; border:1px solid #dce7f5; border-radius:8px; background:#fff; color:#6b7f99; font-size:12px; font-weight:800; }
        .designer-step span { display:grid; place-items:center; width:23px; height:23px; border-radius:50%; background:#e7eef8; }
        .designer-step.is-active { border-color:#8ab5f4; background:#edf5ff; color:#175ca8; }
        .designer-step.is-done { border-color:#9bd9c0; background:#effbf6; color:#087a55; }
        .designer-workspace { display:grid; grid-template-columns:minmax(0,1.15fr) minmax(390px,.85fr); gap:14px; align-items:start; }
        .designer-form-panel,.designer-preview { border:1px solid var(--designer-border); border-radius:8px; background:#fff; overflow:hidden; }
        .designer-section { border-bottom:1px solid #e1eaf6; }
        .designer-section:last-child { border-bottom:0; }
        .designer-section-title { display:flex; align-items:center; justify-content:space-between; gap:10px; width:100%; padding:13px 16px; border:0; background:#f8fbff; color:#17375e; font-size:13px; font-weight:850; text-align:left; }
        .designer-section-title svg { width:17px; }
        .designer-section-body { padding:15px 16px; }
        .designer-field-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:11px; }
        .designer-field-grid .span-2 { grid-column:span 2; }
        .designer-field label { display:block; margin-bottom:5px; color:#486681; font-size:11px; font-weight:800; }
        .designer-field input { min-height:39px; }
        .designer-field input[readonly] { background:#f3f7fc; color:#49657f; }
        .designer-bom-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px; }
        .designer-bom-list { display:grid; gap:8px; }
        .designer-bom-row { display:grid; grid-template-columns:36px 90px minmax(115px,.75fr) minmax(170px,1.35fr) 80px 88px 88px 34px; gap:7px; align-items:end; padding:9px; border:1px solid #dbe7f6; border-radius:8px; background:#fbfdff; }
        .designer-bom-row .designer-field label { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .designer-bom-index { align-self:center; display:grid; place-items:center; width:28px; height:28px; border-radius:6px; background:#e7f1ff; color:#1764c0; font-size:12px; font-weight:900; }
        .designer-icon-btn { display:grid; place-items:center; width:34px; height:39px; border:1px solid #f1b6bd; border-radius:6px; background:#fff; color:#c93646; }
        .designer-icon-btn svg { width:16px; }
        .designer-preview { position:sticky; top:70px; }
        .designer-preview-head { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:13px 15px; border-bottom:1px solid #dbe7f5; background:#f6faff; }
        .designer-preview-head h2 { margin:0; color:#17375e; font-size:14px; font-weight:900; }
        .designer-completeness { padding:4px 8px; border-radius:999px; background:#fff1d6; color:#915600; font-size:10px; font-weight:900; }
        .designer-completeness.is-ready { background:#ddf8ed; color:#087a55; }
        .designer-sheet { margin:12px; border:1px solid #8194ad; color:#111827; font-family:Arial,sans-serif; font-size:10px; }
        .designer-sheet-title { padding:7px; border-bottom:1px solid #8194ad; text-align:center; font-size:15px; font-weight:900; }
        .designer-sheet-meta { display:grid; grid-template-columns:1fr 1fr; }
        .designer-sheet-cell { display:flex; justify-content:space-between; gap:8px; min-height:30px; padding:6px 8px; border-right:1px solid #8194ad; border-bottom:1px solid #8194ad; }
        .designer-sheet-cell:nth-child(2n) { border-right:0; }
        .designer-sheet-cell b { color:#17375e; }
        .designer-sheet-main { display:grid; grid-template-columns:.78fr 1.22fr; }
        .designer-sheet-ops { padding:7px; border-right:1px solid #8194ad; }
        .designer-sheet-op { display:flex; justify-content:space-between; gap:8px; padding:3px 0; border-bottom:1px dotted #c1ccd9; }
        .designer-sheet table { width:100%; border-collapse:collapse; }
        .designer-sheet th,.designer-sheet td { padding:4px; border:1px solid #9aa9bb; vertical-align:top; }
        .designer-sheet th { background:#edf4fc; font-size:9px; }
        .designer-sheet-image { display:grid; place-items:center; min-height:150px; padding:10px; border-top:1px solid #8194ad; }
        .designer-sheet-image img { max-width:100%; max-height:145px; object-fit:contain; }
        .designer-sheet-empty { color:#718096; text-align:center; }
        .designer-actions { position:sticky; bottom:0; z-index:20; display:flex; justify-content:flex-end; gap:8px; margin-top:14px; padding:11px; border:1px solid #c9daef; border-radius:8px; background:rgba(255,255,255,.96); box-shadow:0 -6px 22px rgba(31,73,125,.08); backdrop-filter:blur(8px); }
        .designer-toast { position:fixed; right:18px; bottom:18px; z-index:1080; max-width:420px; padding:12px 15px; border-radius:8px; background:#17375e; color:#fff; box-shadow:0 16px 40px rgba(15,42,76,.25); opacity:0; transform:translateY(12px); pointer-events:none; transition:opacity .2s ease,transform .2s ease; }
        .designer-toast.is-visible { opacity:1; transform:translateY(0); }
        .designer-toast.is-error { background:#b42335; }
        @media (max-width:1250px) { .designer-workspace { grid-template-columns:1fr; } .designer-preview { position:static; } }
        @media (max-width:900px) { .designer-field-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } .designer-bom-row { grid-template-columns:32px 80px 1fr 1fr; } .designer-bom-row .bom-consumption,.designer-bom-row .bom-waste,.designer-bom-row .bom-total { grid-column:auto; } }
        @media (max-width:620px) {
            .designer-create { width:100% !important; padding:80px 12px 120px !important; }
            .designer-create > *,.designer-create .wms-heading > div { min-width:0; max-width:100%; }
            .designer-create .wms-heading { padding-left:46px; }
            .designer-create .wms-heading h1 { font-size:25px; }
            .designer-create .wms-heading p { overflow-wrap:anywhere; }
            .designer-find,.designer-form-panel,.designer-preview { max-width:100%; overflow:hidden; }
            .designer-find-row,.designer-steps,.designer-field-grid { grid-template-columns:minmax(0,1fr); }
            .designer-field-grid .span-2 { grid-column:auto; }
            .designer-suggestion { grid-template-columns:minmax(0,1fr); gap:3px; }
            .designer-bom-row { grid-template-columns:32px minmax(0,1fr); }
            .designer-bom-row > :not(.designer-bom-index) { grid-column:2; }
            .designer-actions { position:sticky; right:auto; bottom:8px; left:auto; display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); width:100%; margin:12px 0 0; }
            .designer-actions .wms-btn { width:100%; min-width:0; padding-inline:8px; justify-content:center; white-space:normal; }
            .designer-actions .wms-btn:last-child { grid-column:1 / -1; }
        }
        @media (prefers-reduced-motion:reduce) { * { scroll-behavior:auto !important; transition-duration:.01ms !important; } }
    </style>
</head>
<body>
@include('layouts.partials.sidebar')

<header class="wms-topbar">
    <h1 class="wms-topbar__title">WMS May Mặc</h1>
    <div class="flex-grow-1"></div>
    <a class="wms-btn" href="{{ route('weaving.dashboard') }}"><i data-lucide="layout-dashboard"></i>Quản lý dệt</a>
    <a class="wms-btn" href="{{ route('weaving.bom') }}"><i data-lucide="settings-2"></i>Định mức</a>
</header>

<main class="wms-page designer-create">
    <div class="wms-heading">
        <div><h1>Tạo lệnh dệt</h1><p>Chọn lệnh sản xuất trung tâm, hoàn thiện thông số và gửi xuống sản xuất.</p></div>
        <span class="wms-chip">Lệnh /{{ now('Asia/Ho_Chi_Minh')->format('y') }}</span>
    </div>

    <section class="designer-find">
        <div class="designer-find-row">
            <div class="designer-find-input">
                <i data-lucide="search"></i>
                <input id="orderSearch" class="form-control" autocomplete="off" placeholder="Gõ số lệnh, mã hàng, PO hoặc khách hàng...">
                <div id="orderSuggestions" class="designer-suggestions d-none"></div>
            </div>
            <button id="clearOrderBtn" class="wms-btn" type="button"><i data-lucide="rotate-ccw"></i>Chọn lệnh khác</button>
        </div>
    </section>

    <div class="designer-steps" aria-label="Tiến trình tạo lệnh">
        <div class="designer-step is-active" data-step="1"><span>1</span>Chọn lệnh</div>
        <div class="designer-step" data-step="2"><span>2</span>Thông tin kỹ thuật</div>
        <div class="designer-step" data-step="3"><span>3</span>Định mức sợi</div>
        <div class="designer-step" data-step="4"><span>4</span>Kiểm tra và gửi</div>
    </div>

    <div class="designer-workspace">
        <section class="designer-form-panel">
            <div class="designer-section">
                <button class="designer-section-title" type="button" data-bs-toggle="collapse" data-bs-target="#generalSection">
                    <span><i data-lucide="clipboard-list"></i> Thông tin lệnh</span><i data-lucide="chevron-down"></i>
                </button>
                <div id="generalSection" class="collapse show">
                    <div class="designer-section-body">
                        <div class="designer-field-grid">
                            <div class="designer-field"><label>Số lệnh</label><input id="production_order" class="form-control" readonly></div>
                            <div class="designer-field"><label>Mã hàng</label><input id="item_code" class="form-control" readonly></div>
                            <div class="designer-field span-2"><label>Tên hàng</label><input id="item_name" class="form-control" readonly></div>
                            <div class="designer-field"><label>Khách hàng</label><input id="customer" class="form-control" data-preview></div>
                            <div class="designer-field"><label>PO</label><input id="po_number" class="form-control" data-preview></div>
                            <div class="designer-field"><label>Mã design</label><input id="design_code" class="form-control" data-preview></div>
                            <div class="designer-field"><label>Job #</label><input id="job_number" class="form-control" data-metadata data-preview></div>
                            <div class="designer-field"><label>Ngày ra lệnh</label><input id="order_date" inputmode="numeric" placeholder="dd/mm/yyyy" class="form-control" data-preview></div>
                            <div class="designer-field"><label>Ngày giao</label><input id="due_date" inputmode="numeric" placeholder="dd/mm/yyyy" class="form-control" data-preview></div>
                            <div class="designer-field"><label>Số lượng</label><input id="order_quantity" type="number" min="0" step="0.001" class="form-control" data-preview></div>
                            <div class="designer-field"><label>ĐVT</label><input id="unit" class="form-control" data-preview></div>
                            <div class="designer-field span-2"><label>Tên label</label><input id="label_name" class="form-control" data-metadata data-preview></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="designer-section">
                <button class="designer-section-title" type="button" data-bs-toggle="collapse" data-bs-target="#technicalSection">
                    <span><i data-lucide="ruler"></i> Công đoạn và quy cách</span><i data-lucide="chevron-down"></i>
                </button>
                <div id="technicalSection" class="collapse show">
                    <div class="designer-section-body">
                        <div class="designer-field-grid">
                            <div class="designer-field span-2"><label>Ui keo</label><input id="op_ui_keo" class="form-control" data-operation data-preview></div>
                            <div class="designer-field span-2"><label>Loop</label><input id="op_loop" class="form-control" data-operation data-preview></div>
                            <div class="designer-field span-2"><label>Phần trên</label><input id="op_phan_tren" class="form-control" data-operation data-preview></div>
                            <div class="designer-field span-2"><label>Phần dưới</label><input id="op_phan_duoi" class="form-control" data-operation data-preview></div>
                            <div class="designer-field"><label>Chiều dài</label><input id="length" class="form-control" data-metadata data-preview></div>
                            <div class="designer-field"><label>Hoàn chỉnh</label><input id="finished_size" class="form-control" data-metadata data-preview></div>
                            <div class="designer-field"><label>Mã số hộp</label><input id="box_code" class="form-control" data-metadata data-preview></div>
                            <div class="designer-field"><label>SL/hộp</label><input id="quantity_per_box" class="form-control" data-metadata data-preview></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="designer-section">
                <button class="designer-section-title collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#machineSection">
                    <span><i data-lucide="settings"></i> Máy, cuộn và file</span><i data-lucide="chevron-down"></i>
                </button>
                <div id="machineSection" class="collapse">
                    <div class="designer-section-body">
                        <div class="designer-field-grid">
                            <div class="designer-field"><label>Số pick</label><input id="pick" class="form-control" data-metadata></div>
                            <div class="designer-field"><label>Mật độ</label><input id="density" class="form-control" data-metadata></div>
                            <div class="designer-field"><label>Máy</label><input id="machine" class="form-control" data-metadata></div>
                            <div class="designer-field"><label>Sợi dọc (g)</label><input id="warp_grams" type="number" min="0" step="0.001" class="form-control" data-metadata></div>
                            <div class="designer-field"><label>Máy cuộn nhỏ</label><input id="roll_machine_small" class="form-control" data-metadata></div>
                            <div class="designer-field"><label>Số cuộn nhỏ</label><input id="roll_count_small" type="number" min="0" step="0.001" class="form-control" data-metadata></div>
                            <div class="designer-field"><label>Máy cuộn lớn</label><input id="roll_machine_large" class="form-control" data-metadata></div>
                            <div class="designer-field"><label>Số cuộn lớn</label><input id="roll_count_large" type="number" min="0" step="0.001" class="form-control" data-metadata></div>
                            <div class="designer-field"><label>Số lượng +10%</label><input id="quantity_plus_10" type="number" min="0" step="0.001" class="form-control" data-metadata></div>
                            <div class="designer-field"><label>Số dòng</label><input id="row_count" type="number" min="0" step="1" class="form-control" data-metadata></div>
                            <div class="designer-field"><label>Tên file</label><input id="file_name" class="form-control" data-metadata></div>
                            <div class="designer-field"><label>USB máy nhỏ</label><input id="usb_small" class="form-control" data-metadata></div>
                            <div class="designer-field"><label>USB máy lớn</label><input id="usb_large" class="form-control" data-metadata></div>
                            <div class="designer-field"><label>Ca sản xuất</label><input id="shift" class="form-control" data-metadata></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="designer-section">
                <div class="designer-section-body">
                    <div class="designer-bom-head">
                        <div><strong>Định mức sợi</strong><div class="text-secondary small">Tối đa 7 dòng để khớp mẫu LENH_DET.</div></div>
                        <button id="addBomRowBtn" class="wms-btn" type="button"><i data-lucide="plus"></i>Thêm sợi</button>
                    </div>
                    <div id="bomRows" class="designer-bom-list">
                        <div class="wms-empty">Chọn lệnh để nạp định mức.</div>
                    </div>
                    <datalist id="materialOptions"></datalist>
                </div>
            </div>
        </section>

        <aside class="designer-preview">
            <div class="designer-preview-head">
                <h2>Xem trước lệnh dệt</h2>
                <span id="completeness" class="designer-completeness">Chưa chọn lệnh</span>
            </div>
            <div id="sheetPreview" class="designer-sheet">
                <div class="designer-sheet-title">LỆNH DỆT</div>
                <div class="designer-sheet-empty p-5">Chọn một lệnh sản xuất để bắt đầu.</div>
            </div>
        </aside>
    </div>

    <div class="designer-actions">
        <button id="saveDraftBtn" class="wms-btn" type="button" disabled><i data-lucide="save"></i>Lưu nháp</button>
        <button id="exportBtn" class="wms-btn" type="button" disabled><i data-lucide="file-spreadsheet"></i>Xuất Sheet</button>
        <button id="issueBtn" class="wms-btn wms-btn--primary" type="button" disabled><i data-lucide="send"></i>Lưu + gửi sản xuất</button>
    </div>
</main>

<div id="toast" class="designer-toast" role="status" aria-live="polite"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const currentYear = {{ (int) now('Asia/Ho_Chi_Minh')->format('Y') }};
const currentDateCompact = '{{ now('Asia/Ho_Chi_Minh')->format('Ymd') }}';
const metadataFields = ['job_number','label_name','length','finished_size','box_code','quantity_per_box','pick','density','machine','warp_grams','roll_machine_small','roll_count_small','roll_machine_large','roll_count_large','quantity_plus_10','row_count','file_name','usb_small','usb_large','shift'];
const operationFields = {op_ui_keo:'UI_KEO',op_loop:'LOOP',op_phan_tren:'PHAN_TREN',op_phan_duoi:'PHAN_DUOI'};
let selectedPlan = null;
let selectedOrderCode = '';
let searchTimer = null;
let materialTimer = null;
let catalogRows = new Map();

function esc(value) {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
}
function num(value) {
    return new Intl.NumberFormat('vi-VN', {maximumFractionDigits:3}).format(Number(value || 0));
}
async function api(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrfToken,...(options.headers || {})}
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(result.message || 'Không xử lý được yêu cầu.');
    return result;
}
function toast(message, error = false) {
    const box = document.getElementById('toast');
    box.textContent = message;
    box.classList.toggle('is-error', error);
    box.classList.add('is-visible');
    clearTimeout(box.timer);
    box.timer = setTimeout(() => box.classList.remove('is-visible'), 3500);
}
function operationValue(operations, key) {
    const wanted = key.replaceAll('_',' ');
    const entry = Object.entries(operations || {}).find(([name]) => name.replaceAll('_',' ').toUpperCase() === wanted.toUpperCase());
    return entry ? entry[1] : '';
}
function setValue(id, value) {
    const input = document.getElementById(id);
    if (input) input.value = value ?? '';
}
function dateForInput(value) {
    const text = String(value || '').trim();
    const match = text.match(/^(\d{4})-(\d{2})-(\d{2})/);
    return match ? `${match[3]}/${match[2]}/${match[1]}` : text;
}
function professionalFileName(orderCode, itemCode, orderDate) {
    const safePart = value => String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toUpperCase()
        .replace(/[^A-Z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
    const dateParts = String(orderDate || '').match(/^(\d{2})[/-](\d{2})[/-](\d{4})$/);
    const dateSuffix = dateParts ? `${dateParts[3]}${dateParts[2]}${dateParts[1]}` : currentDateCompact;
    return ['LENH-DET', safePart(orderCode), safePart(itemCode), dateSuffix].filter(Boolean).join('_');
}
function getValue(id) {
    return String(document.getElementById(id)?.value || '').trim();
}
function setSteps(active) {
    document.querySelectorAll('.designer-step').forEach(step => {
        const index = Number(step.dataset.step);
        step.classList.toggle('is-active', index === active);
        step.classList.toggle('is-done', index < active);
    });
}

async function searchOrders() {
    const keyword = getValue('orderSearch');
    const box = document.getElementById('orderSuggestions');
    if (keyword.length < 2) {
        box.classList.add('d-none');
        return;
    }
    box.classList.remove('d-none');
    box.innerHTML = '<div class="wms-loading p-3">Đang tìm lệnh /' + String(currentYear).slice(-2) + '...</div>';
    try {
        const result = await api(`/api/lenh-det/production-orders?year=${currentYear}&per_page=25&keyword=${encodeURIComponent(keyword)}`);
        const rows = result.data || [];
        box.innerHTML = rows.map(row => `
            <button class="designer-suggestion" type="button" data-order="${esc(row.production_order)}">
                <span><strong>${esc(row.production_order)}</strong><small class="d-block text-secondary">${esc(row.customer || '-')}</small></span>
                <span>${esc(row.item_code || '-')}<small class="d-block text-secondary">${esc(row.description || '')}</small></span>
                <span class="text-end fw-bold">${num(row.planned_quantity)} ${esc(row.unit || '')}</span>
            </button>
        `).join('') || '<div class="wms-empty p-3">Không tìm thấy lệnh /' + String(currentYear).slice(-2) + '.</div>';
    } catch (error) {
        box.innerHTML = `<div class="wms-empty text-danger p-3">${esc(error.message)}</div>`;
    }
}

async function selectOrder(code) {
    selectedOrderCode = code;
    setValue('orderSearch', code);
    document.getElementById('orderSuggestions').classList.add('d-none');
    document.getElementById('sheetPreview').innerHTML = '<div class="designer-sheet-title">LỆNH DỆT</div><div class="wms-loading p-5">Đang nạp dữ liệu...</div>';
    try {
        const result = await api(`/api/lenh-det/production-order-plan?production_order=${encodeURIComponent(code)}`);
        selectedPlan = result;
        populateForm(result);
        document.querySelectorAll('#saveDraftBtn,#exportBtn,#issueBtn').forEach(button => button.disabled = false);
        setSteps((result.source_items?.[0]?.materials || []).length ? 4 : 3);
        renderPreview();
    } catch (error) {
        selectedPlan = null;
        toast(error.message, true);
        clearForm(false);
    }
}

function populateForm(result) {
    const order = result.order || {};
    const item = (result.source_items || [])[0] || {};
    const metadata = {...(item.metadata || {}), ...(order.metadata || {})};
    const operations = metadata.operations || {};
    setValue('production_order', order.production_order || order.order_code || selectedOrderCode);
    setValue('item_code', order.item_code || item.item_code);
    setValue('item_name', item.item_name);
    ['customer','po_number','design_code','unit'].forEach(key => setValue(key, order[key] ?? item[key] ?? ''));
    setValue('order_date', dateForInput(order.order_date));
    setValue('due_date', dateForInput(order.due_date));
    setValue('order_quantity', order.planned_quantity ?? item.order_quantity ?? '');
    metadataFields.forEach(key => setValue(key, metadata[key] ?? ''));
    if (!getValue('file_name')) {
        setValue('file_name', professionalFileName(
            getValue('production_order'),
            getValue('item_code'),
            getValue('order_date')
        ));
    }
    Object.entries(operationFields).forEach(([id,key]) => setValue(id, operationValue(operations, key)));
    const lines = item.materials?.length ? item.materials : [];
    renderBomRows(lines.length ? lines : [{}]);
}

function renderBomRows(lines) {
    const box = document.getElementById('bomRows');
    box.innerHTML = lines.slice(0, 7).map((line, index) => bomRow(line, index)).join('');
    document.getElementById('addBomRowBtn').disabled = lines.length >= 7;
    if (window.lucide) lucide.createIcons();
}
function bomRow(line = {}, index) {
    const total = Number(line.total_grams) > 0 ? line.total_grams : (line.required_quantity_raw || line.required_quantity || '');
    return `
        <div class="designer-bom-row" data-bom-row>
            <div class="designer-bom-index">${index + 1}</div>
            <div class="designer-field"><label>Loại</label><input class="form-control" data-bom="type" value="${esc(line.type || '')}"></div>
            <div class="designer-field"><label>Mã sợi *</label><input class="form-control" list="materialOptions" data-bom="material_code" data-material-code value="${esc(line.material_code || '')}"></div>
            <div class="designer-field"><label>Tên màu sợi</label><input class="form-control" data-bom="material_name" value="${esc(line.catalog_name || line.material_name || '')}"></div>
            <div class="designer-field"><label>Kệ</label><input class="form-control" data-bom="shelf_hint" value="${esc(line.first_location || line.catalog_shelf_code || line.shelf_hint || '')}"></div>
            <div class="designer-field bom-consumption"><label>TL/1PCS *</label><input type="number" min="0" step="0.000001" class="form-control" data-bom="consumption_per_unit" value="${esc(line.consumption_per_unit || '')}"></div>
            <div class="designer-field bom-total"><label>T.L(g)</label><input type="number" min="0" step="0.001" class="form-control" data-bom="total_grams" value="${esc(total)}" readonly></div>
            <button class="designer-icon-btn" type="button" data-remove-row title="Xóa dòng"><i data-lucide="x"></i></button>
            <input type="hidden" data-bom="line_role" value="${esc(line.line_role || `DONG-${index + 1}`)}">
            <input type="hidden" data-bom="unit" value="${esc(line.bom_unit || line.unit || 'gam')}">
            <input type="hidden" data-bom="waste_percent" value="${esc(line.waste_percent || 0)}">
        </div>
    `;
}

function collectBomLines() {
    return Array.from(document.querySelectorAll('[data-bom-row]')).map(row => {
        const line = {};
        row.querySelectorAll('[data-bom]').forEach(input => line[input.dataset.bom] = input.value.trim());
        line.consumption_per_unit = Number(line.consumption_per_unit || 0);
        line.waste_percent = Number(line.waste_percent || 0);
        line.total_grams = Number(line.total_grams || 0);
        return line;
    }).filter(line => line.material_code || line.consumption_per_unit);
}
function recalculateBom() {
    const quantity = Number(getValue('order_quantity') || 0);
    document.querySelectorAll('[data-bom-row]').forEach(row => {
        const consumption = Number(row.querySelector('[data-bom="consumption_per_unit"]').value || 0);
        const waste = Number(row.querySelector('[data-bom="waste_percent"]').value || 0);
        row.querySelector('[data-bom="total_grams"]').value = consumption > 0 ? (quantity * consumption * (1 + waste / 100)).toFixed(3).replace(/\.?0+$/, '') : '';
    });
}

async function loadMaterialSuggestions(keyword) {
    if (keyword.length < 1) return;
    try {
        const result = await api(`/api/lenh-det/material-suggestions?keyword=${encodeURIComponent(keyword)}`);
        const options = result.data || [];
        options.forEach(row => catalogRows.set(row.item_code.toUpperCase(), row));
        document.getElementById('materialOptions').innerHTML = options.map(row =>
            `<option value="${esc(row.item_code)}">${esc(row.item_name)} · Kệ ${esc(row.shelf_code || '-')}</option>`
        ).join('');
    } catch (_) {}
}
function applyMaterial(input) {
    const row = catalogRows.get(input.value.trim().toUpperCase());
    if (!row) return;
    const wrapper = input.closest('[data-bom-row]');
    wrapper.querySelector('[data-bom="material_name"]').value = row.item_name || row.color || '';
    wrapper.querySelector('[data-bom="unit"]').value = 'gam';
    if (row.shelf_code) wrapper.querySelector('[data-bom="shelf_hint"]').value = row.shelf_code;
    renderPreview();
}

function metadataPayload() {
    const metadata = {operations:{}};
    metadataFields.forEach(key => metadata[key] = getValue(key));
    Object.entries(operationFields).forEach(([id,key]) => metadata.operations[key] = getValue(id));
    return metadata;
}
function payload(action) {
    return {
        action,
        production_order:getValue('production_order'),
        item_code:getValue('item_code'),
        item_name:getValue('item_name'),
        customer:getValue('customer'),
        po_number:getValue('po_number'),
        design_code:getValue('design_code'),
        order_quantity:Number(getValue('order_quantity') || 0),
        unit:getValue('unit'),
        order_date:getValue('order_date') || null,
        due_date:getValue('due_date') || null,
        metadata:metadataPayload(),
        lines:collectBomLines(),
    };
}
function validatePayload(data) {
    const missing = [];
    if (!data.production_order) missing.push('số lệnh');
    if (!data.item_code) missing.push('mã hàng');
    if (!(data.order_quantity > 0)) missing.push('số lượng');
    if (!data.lines.length) missing.push('định mức sợi');
    data.lines.forEach((line,index) => {
        if (!line.material_code) missing.push(`mã sợi dòng ${index + 1}`);
        if (!(line.consumption_per_unit > 0)) missing.push(`TL/1PCS dòng ${index + 1}`);
    });
    if (data.lines.length > 7) missing.push('BOM vượt 7 dòng');
    return [...new Set(missing)];
}

async function save(action, silent = false) {
    const data = payload(action);
    const missing = validatePayload(data);
    if (missing.length) throw new Error('Cần bổ sung: ' + missing.join(', ') + '.');
    const result = await api('/api/lenh-det/designer-save', {method:'POST',body:JSON.stringify(data)});
    if (!silent) toast(result.message);
    setSteps(4);
    return result;
}
async function saveWithButton(button, action) {
    button.disabled = true;
    try {
        if (action === 'issued' && !confirm('Lưu lệnh và gửi xuống sản xuất? BOM sẽ được chụp phiên bản tại thời điểm gửi.')) return;
        await save(action);
    } catch (error) {
        toast(error.message, true);
    } finally {
        button.disabled = false;
    }
}

function renderPreview() {
    if (!selectedPlan) return;
    recalculateBom();
    const data = payload('draft');
    const missing = validatePayload(data);
    const badge = document.getElementById('completeness');
    badge.textContent = missing.length ? `Thiếu ${missing.length} mục` : 'Sẵn sàng';
    badge.classList.toggle('is-ready', !missing.length);
    const operations = data.metadata.operations;
    const image = selectedPlan.order?.image_url || selectedPlan.source_items?.[0]?.image_url || '';
    document.getElementById('sheetPreview').innerHTML = `
        <div class="designer-sheet-title">LỆNH DỆT</div>
        <div class="designer-sheet-meta">
            ${previewCell('Khách hàng',data.customer)}
            ${previewCell('Lệnh in',data.production_order)}
            ${previewCell('PO',data.po_number)}
            ${previewCell('Mã hàng',data.item_code)}
            ${previewCell('Ngày ra lệnh',data.order_date)}
            ${previewCell('Mã design',data.design_code)}
            ${previewCell('Job #',data.metadata.job_number)}
            ${previewCell('Ngày giao',data.due_date)}
        </div>
        <div class="designer-sheet-main">
            <div class="designer-sheet-ops">
                ${previewOp('Tên label',data.metadata.label_name || data.item_name)}
                ${previewOp('Ui keo',operations.UI_KEO)}
                ${previewOp('Loop',operations.LOOP)}
                ${previewOp('Phần trên',operations.PHAN_TREN)}
                ${previewOp('Phần dưới',operations.PHAN_DUOI)}
                ${previewOp('Chiều dài',data.metadata.length)}
                ${previewOp('Hoàn chỉnh',data.metadata.finished_size)}
                ${previewOp('Số pick',data.metadata.pick)}
                ${previewOp('Mật độ',data.metadata.density)}
                ${previewOp('Máy',data.metadata.machine)}
            </div>
            <div><table><thead><tr><th>Loại</th><th>Mã sợi</th><th>Kệ</th><th>Tên màu</th><th>TL/PCS</th><th>T.L(g)</th></tr></thead><tbody>
                ${data.lines.map(line => `<tr><td>${esc(line.type)}</td><td><b>${esc(line.material_code)}</b></td><td>${esc(line.shelf_hint)}</td><td>${esc(line.material_name)}</td><td class="text-end">${num(line.consumption_per_unit)}</td><td class="text-end">${num(line.total_grams)}</td></tr>`).join('') || '<tr><td colspan="6" class="text-center">Chưa có định mức</td></tr>'}
            </tbody></table></div>
        </div>
        <div class="designer-sheet-image">${image ? `<img src="${esc(image)}" alt="Ảnh ${esc(data.item_code)}">` : '<div class="designer-sheet-empty">Chưa có ảnh trong Danh mục nội bộ</div>'}</div>
        <div class="p-2 d-flex justify-content-between"><b>SL: ${num(data.order_quantity)} ${esc(data.unit)}</b><span>${missing.length ? 'Còn thiếu: ' + esc(missing.slice(0,3).join(', ')) : 'Đủ thông tin bắt buộc'}</span></div>
    `;
}
function previewCell(label,value) {
    return `<div class="designer-sheet-cell"><span>${esc(label)}</span><b>${esc(value || '-')}</b></div>`;
}
function previewOp(label,value) {
    return `<div class="designer-sheet-op"><span>${esc(label)}</span><b>${esc(value || '-')}</b></div>`;
}
function clearForm(clearSearch = true) {
    selectedPlan = null;
    selectedOrderCode = '';
    if (clearSearch) setValue('orderSearch','');
    document.querySelectorAll('.designer-form-panel input').forEach(input => input.value = '');
    document.getElementById('bomRows').innerHTML = '<div class="wms-empty">Chọn lệnh để nạp định mức.</div>';
    document.getElementById('sheetPreview').innerHTML = '<div class="designer-sheet-title">LỆNH DỆT</div><div class="designer-sheet-empty p-5">Chọn một lệnh sản xuất để bắt đầu.</div>';
    document.getElementById('completeness').textContent = 'Chưa chọn lệnh';
    document.getElementById('completeness').classList.remove('is-ready');
    document.querySelectorAll('#saveDraftBtn,#exportBtn,#issueBtn').forEach(button => button.disabled = true);
    setSteps(1);
}

document.getElementById('orderSearch').addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(searchOrders, 280);
});
document.getElementById('orderSearch').addEventListener('keydown', event => {
    if (event.key !== 'Enter') return;
    event.preventDefault();
    const first = document.querySelector('[data-order]');
    if (first) selectOrder(first.dataset.order);
});
document.getElementById('orderSuggestions').addEventListener('click', event => {
    const button = event.target.closest('[data-order]');
    if (button) selectOrder(button.dataset.order);
});
document.addEventListener('click', event => {
    if (!event.target.closest('.designer-find-input')) document.getElementById('orderSuggestions').classList.add('d-none');
});
document.getElementById('clearOrderBtn').addEventListener('click', () => clearForm(true));
document.getElementById('addBomRowBtn').addEventListener('click', () => {
    const rows = collectBomLines();
    if (document.querySelectorAll('[data-bom-row]').length >= 7) return toast('Mẫu chỉ có 7 dòng sợi đầy đủ.', true);
    renderBomRows([...rows, {}]);
    renderPreview();
});
document.getElementById('bomRows').addEventListener('click', event => {
    const button = event.target.closest('[data-remove-row]');
    if (!button) return;
    button.closest('[data-bom-row]').remove();
    Array.from(document.querySelectorAll('[data-bom-row]')).forEach((row,index) => row.querySelector('.designer-bom-index').textContent = index + 1);
    document.getElementById('addBomRowBtn').disabled = document.querySelectorAll('[data-bom-row]').length >= 7;
    renderPreview();
});
document.getElementById('bomRows').addEventListener('input', event => {
    if (event.target.matches('[data-material-code]')) {
        clearTimeout(materialTimer);
        materialTimer = setTimeout(() => loadMaterialSuggestions(event.target.value.trim()), 250);
    }
    renderPreview();
});
document.getElementById('bomRows').addEventListener('change', event => {
    if (event.target.matches('[data-material-code]')) applyMaterial(event.target);
    renderPreview();
});
document.querySelector('.designer-form-panel').addEventListener('input', event => {
    if (event.target.matches('input') && !event.target.closest('#bomRows')) renderPreview();
});
document.getElementById('saveDraftBtn').addEventListener('click', event => saveWithButton(event.currentTarget,'draft'));
document.getElementById('issueBtn').addEventListener('click', event => saveWithButton(event.currentTarget,'issued'));
document.getElementById('exportBtn').addEventListener('click', async event => {
    const button = event.currentTarget;
    const sheetWindow = window.open('about:blank','_blank');
    button.disabled = true;
    try {
        await save('draft', true);
        const result = await api('/api/lenh-det/export-sheet',{method:'POST',body:JSON.stringify({production_order:selectedOrderCode})});
        if (sheetWindow) sheetWindow.location.href = result.sheet_url;
        else window.location.href = result.sheet_url;
        toast(result.message);
    } catch (error) {
        if (sheetWindow) sheetWindow.close();
        toast(error.message,true);
    } finally {
        button.disabled = false;
    }
});

const requestedOrder = new URLSearchParams(window.location.search).get('order');
if (requestedOrder) selectOrder(requestedOrder);
if (window.lucide) lucide.createIcons();
</script>
</body>
</html>
