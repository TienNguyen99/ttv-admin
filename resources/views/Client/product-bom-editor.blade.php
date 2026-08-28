<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>BOM và công đoạn</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/warehouse-wms.css') }}?v={{ filemtime(public_path('css/warehouse-wms.css')) }}" rel="stylesheet">
    <style>
        .bom-toolbar { display:grid; grid-template-columns:minmax(220px,1fr) minmax(260px,1.5fr) 120px auto; gap:10px; align-items:end; }
        .wms-field { display:flex; min-width:0; flex-direction:column; gap:6px; }
        .wms-field > span { color:#475569; font-size:12px; font-weight:800; }
        .wms-field > input, .wms-field > select { width:100%; min-height:40px; padding:8px 11px; border:1px solid #c8d8ec; border-radius:6px; background:#fff; }
        .wms-field > input:focus, .wms-field > select:focus { border-color:#60a5fa; outline:3px solid rgba(96,165,250,.16); }
        .bom-meta { color:#64748b; font-size:12px; }
        .bom-section-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:12px 16px; border-bottom:1px solid #dbe7f5; }
        .bom-section-head strong { color:#102f56; }
        .bom-table { min-width:900px; }
        .bom-table td { padding:8px; }
        .bom-table input, .bom-table select { min-width:0; height:36px; padding:6px 8px; border:1px solid #c8d8ec; border-radius:6px; background:#fff; }
        .bom-table input:focus, .bom-table select:focus { border-color:#60a5fa; outline:3px solid rgba(96,165,250,.16); }
        .bom-table .bom-code { width:145px; font-weight:750; text-transform:uppercase; }
        .bom-table .bom-name { width:240px; }
        .bom-table .bom-number { width:105px; text-align:right; }
        .bom-table .bom-mode { width:130px; }
        .bom-table .bom-unit { width:90px; text-transform:uppercase; }
        .bom-table .bom-operation { width:145px; }
        .bom-detail-row td { padding:0 8px 10px 50px; background:#f7fbff !important; }
        .bom-detail-strip { display:grid; grid-template-columns:150px 120px 120px minmax(220px,1fr); gap:8px; align-items:end; padding:8px 12px; border:1px solid #d7e5f5; border-radius:8px; background:#fff; }
        .bom-detail-strip label { display:flex; min-width:0; flex-direction:column; gap:4px; color:#536a84; font-size:11px; font-weight:800; }
        .bom-detail-strip input { width:100%; height:34px; }
        .bom-detail-check { min-height:34px; flex-direction:row !important; align-items:center; gap:7px !important; }
        .bom-detail-check input { width:16px; height:16px; min-height:0; flex:0 0 16px; }
        .bom-formula-strip { display:grid; grid-template-columns:minmax(150px,1fr) minmax(150px,1fr) 90px minmax(120px,.8fr) minmax(180px,1.4fr); gap:8px; align-items:end; padding:10px 12px; border:1px solid #b9d5f5; border-left:4px solid #4f8df7; border-radius:8px; background:#eef6ff; }
        .bom-formula-strip label { display:flex; min-width:0; flex-direction:column; gap:4px; color:#38516f; font-size:11px; font-weight:800; }
        .bom-formula-strip input { width:100%; height:34px; }
        .bom-formula-help { align-self:center; color:#365b83; font-size:12px; line-height:1.35; }
        .bom-round { display:flex; align-items:center; justify-content:center; gap:6px; min-width:92px; color:#334155; font-size:12px; font-weight:700; white-space:nowrap; }
        .bom-result { display:flex; flex-direction:column; align-items:flex-end; gap:2px; }
        .bom-result small { color:#64748b; font-weight:500; }
        .bom-drag { width:34px; color:#94a3b8; text-align:center; }
        .bom-empty { padding:34px; color:#64748b; text-align:center; }
        .bom-suggestions { position:absolute; z-index:1060; top:calc(100% + 5px); left:0; right:0; max-height:310px; overflow:auto; border:1px solid #bfd3ec; border-radius:7px; background:#fff; box-shadow:0 14px 32px rgba(15,47,86,.16); }
        .bom-suggestions--floating { position:fixed; z-index:2100; top:auto; right:auto; min-width:420px; max-width:min(720px,calc(100vw - 24px)); }
        .bom-suggestion { display:grid; grid-template-columns:150px 1fr auto; gap:10px; width:100%; padding:9px 11px; border:0; border-bottom:1px solid #e7eef8; background:#fff; text-align:left; }
        .bom-suggestion:hover, .bom-suggestion:focus, .bom-suggestion.is-active { background:#e5f1ff; outline:0; box-shadow:inset 3px 0 0 #4f8df7; }
        .bom-suggestion strong { color:#075aa5; }
        .bom-suggestion small { color:#64748b; }
        .bom-order-grid { display:grid; grid-template-columns:minmax(240px,420px) auto 1fr; gap:10px; align-items:end; }
        .bom-need-group { padding:12px 16px; border-top:1px solid #e2e8f0; }
        .bom-need-title { display:flex; flex-wrap:wrap; justify-content:space-between; gap:8px; margin-bottom:8px; }
        .bom-pill { display:inline-flex; align-items:center; padding:3px 8px; border-radius:999px; background:#e7f1ff; color:#155eaa; font-size:11px; font-weight:800; }
        .bom-message { display:none; margin:12px 16px 0; }
        .bom-message.is-visible { display:block; }
        .bom-loading { display:none; position:fixed; inset:0; z-index:2000; place-items:center; background:rgba(239,246,255,.7); backdrop-filter:blur(2px); }
        .bom-loading.is-visible { display:grid; }
        .bom-loading > div { display:flex; align-items:center; gap:10px; padding:12px 16px; border:1px solid #bfdbfe; border-radius:8px; background:#fff; color:#12345c; font-weight:800; }
        @media (max-width:900px) {
            .wms-page { overflow-x:hidden; }
            .wms-heading { align-items:flex-start; margin-top:42px; }
            .wms-panel { min-width:0; }
            .wms-panel__body { padding:16px; }
            .bom-toolbar, .bom-order-grid, .bom-formula-strip, .bom-detail-strip { grid-template-columns:minmax(0,1fr); }
            .bom-section-head { align-items:flex-start; padding:12px; }
            .bom-section-head > div { min-width:0; }
            .bom-section-head .wms-btn { flex:0 0 auto; white-space:nowrap; }
        }
    </style>
</head>
<body>
@include('layouts.partials.sidebar')

<header class="wms-topbar">
    <h1 class="wms-topbar__title">WMS May Mặc</h1>
    <div class="wms-global-search"><i data-lucide="search"></i><input id="topSearch" placeholder="Tìm BOM theo mã hàng..."></div>
    <a class="wms-btn" href="{{ url('/client/vat-tu-san-xuat') }}"><i data-lucide="boxes"></i>Vật tư theo lệnh</a>
</header>

<main class="wms-page">
    <div class="wms-heading">
        <div><h1>BOM và công đoạn</h1><p>Mỗi mã hàng có một định mức chuẩn và một tuyến sản xuất.</p></div>
        <button id="saveBtn" class="wms-btn wms-btn--primary" type="button"><i data-lucide="save"></i>Lưu BOM</button>
    </div>

    <section class="wms-panel">
        <div class="wms-panel__body">
            <div class="bom-toolbar">
                <label class="wms-field position-relative"><span>Mã hàng thành phẩm *</span><input id="itemCode" autocomplete="off" placeholder="Gõ mã hàng"><div id="productSuggestions" class="bom-suggestions d-none"></div></label>
                <label class="wms-field"><span>Tên hàng</span><input id="itemName" placeholder="Tự lấy từ danh mục"></label>
                <label class="wms-field"><span>ĐVT thành phẩm</span><input id="itemUnit" placeholder="PCS"></label>
                <div class="d-flex gap-2"><span id="revision" class="bom-pill">BOM mới</span><button id="newBtn" class="wms-btn" type="button"><i data-lucide="file-plus-2"></i>Mới</button></div>
            </div>
            <div id="message" class="alert bom-message" role="alert"></div>
        </div>
    </section>

    <section class="wms-panel mt-3">
        <div class="bom-section-head"><div><strong>Tuyến công đoạn</strong><div class="bom-meta">Thứ tự thực hiện từ trên xuống.</div></div><button id="addOperationBtn" class="wms-btn wms-btn--sm" type="button"><i data-lucide="plus"></i>Thêm công đoạn</button></div>
        <div class="wms-table-wrap"><table class="wms-table bom-table"><thead><tr><th style="width:42px">STT</th><th>Mã công đoạn</th><th>Tên công đoạn</th><th>Bộ phận / máy</th><th>Gia công ngoài</th><th>Ghi chú</th><th style="width:96px">Thao tác</th></tr></thead><tbody id="operationBody"></tbody></table></div>
    </section>

    <section class="wms-panel mt-3">
        <div class="bom-section-head"><div><strong>Định mức vật tư</strong><div class="bom-meta">Vật tư trực tiếp, vật tư theo năng suất hoặc nhóm nguyên liệu pha theo tỷ lệ.</div></div><div class="d-flex flex-wrap gap-2"><button id="addFormulaBtn" class="wms-btn wms-btn--sm" type="button"><i data-lucide="flask-conical"></i>Thêm công thức pha</button><button id="addMaterialBtn" class="wms-btn wms-btn--sm" type="button"><i data-lucide="plus"></i>Thêm vật tư</button></div></div>
        <div class="wms-table-wrap"><table class="wms-table bom-table"><thead><tr><th style="width:42px">STT</th><th>Mã vật tư *</th><th>Tên vật tư</th><th>Cách tính</th><th>Giá trị *</th><th>ĐVT xuất *</th><th>Công đoạn</th><th style="width:48px"></th></tr></thead><tbody id="materialBody"></tbody></table></div>
    </section>

    <section class="wms-panel mt-3">
        <div class="bom-section-head"><div><strong>Kiểm tra theo lệnh</strong><div class="bom-meta">Nhận lệnh sản xuất trung tâm hoặc lệnh BTP.</div></div></div>
        <div class="wms-panel__body">
            <div class="bom-order-grid">
                <label class="wms-field"><span>Lệnh SX / lệnh BTP</span><input id="orderCode" placeholder="T-02088/26 hoặc BTP2026-0001"></label>
                <button id="calculateBtn" class="wms-btn" type="button"><i data-lucide="calculator"></i>Tính nhu cầu</button>
                <div class="d-flex justify-content-end"><button id="snapshotBtn" class="wms-btn wms-btn--primary" type="button" disabled><i data-lucide="lock-keyhole"></i>Chốt BOM cho lệnh</button></div>
            </div>
        </div>
        <div id="needs"></div>
    </section>
</main>

<div id="loading" class="bom-loading"><div><span class="spinner-border spinner-border-sm"></span><span id="loadingText">Đang xử lý...</span></div></div>
<div id="materialSuggestions" class="bom-suggestions bom-suggestions--floating d-none"></div>

<datalist id="operationPresets">
    <option value="DET">Dệt</option><option value="EP">Ép</option><option value="DUC">Đúc</option><option value="IN">In</option><option value="CAT">Cắt</option><option value="MAY">May</option><option value="KCS">KCS</option><option value="NHAP_KHO">Nhập kho</option><option value="XUAT_KHO">Xuất kho</option>
</datalist>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const state = { profileId:null, operations:[], materials:[], productTimer:null, materialTimer:null, materialRequest:0, activeMaterialIndex:null, activeMaterialInput:null, activeSuggestion:-1 };
    const presets = { DET:'Dệt', EP:'Ép', DUC:'Đúc', IN:'In', CAT:'Cắt', MAY:'May', KCS:'KCS', NHAP_KHO:'Nhập kho', XUAT_KHO:'Xuất kho' };
    const esc = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
    const fmt = value => new Intl.NumberFormat('vi-VN', { maximumFractionDigits:6 }).format(Number(value || 0));
    const code = value => String(value || '').trim().toUpperCase();
    const loading = (show, text='Đang xử lý...') => { document.getElementById('loadingText').textContent=text; document.getElementById('loading').classList.toggle('is-visible',show); };
    const parse = async response => { const payload=await response.json().catch(()=>({})); if(!response.ok) { const errors=payload.errors ? Object.values(payload.errors).flat().join('\n') : ''; throw new Error(errors || payload.message || 'Không xử lý được yêu cầu.'); } return payload; };
    const request = (url, options={}) => fetch(url, { headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf,...(options.headers||{})}, ...options }).then(parse);
    const notify = (message, type='success') => { const node=document.getElementById('message'); node.className=`alert alert-${type} bom-message is-visible`; node.textContent=message; window.scrollTo({top:0,behavior:'smooth'}); };

    function blankOperation() { return { operation_code:'', operation_name:'', work_center:'', is_outsourced:false, note:'' }; }
    function blankMaterial(overrides={}) { return { material_code:'', material_name:'', component_role:'CHUNG', calculation_mode:'consumption', calculation_value:'', consumption_per_unit:'', yield_quantity:null, formula_code:'', formula_output_per_unit:'', formula_output_unit:'G', formula_part:'', unit:'KG', waste_percent:0, round_to_whole:false, operation_code:'', note:'', ...overrides }; }
    function normalizeMaterial(row={}) {
        const mode=['yield','formula'].includes(row.calculation_mode)?row.calculation_mode:'consumption';
        return { ...blankMaterial(), ...row, calculation_mode:mode, calculation_value:mode==='yield'?(row.yield_quantity??''):(mode==='formula'?'':(row.consumption_per_unit??'')), round_to_whole:Boolean(row.round_to_whole) };
    }
    function reset() {
        state.profileId=null; state.operations=[blankOperation()]; state.materials=[blankMaterial()];
        ['itemCode','itemName','itemUnit','orderCode'].forEach(id => document.getElementById(id).value='');
        document.getElementById('revision').textContent='BOM mới'; document.getElementById('needs').innerHTML=''; document.getElementById('snapshotBtn').disabled=true;
        renderOperations(); renderMaterials();
    }
    function operationOptions(selected='') { return '<option value="">Chưa gắn</option>' + state.operations.filter(row=>code(row.operation_code)).map(row=>`<option value="${esc(code(row.operation_code))}" ${code(row.operation_code)===code(selected)?'selected':''}>${esc(code(row.operation_code))} · ${esc(row.operation_name)}</option>`).join(''); }
    function renderOperations() {
        const body=document.getElementById('operationBody');
        body.innerHTML=state.operations.map((row,index)=>`<tr data-op="${index}"><td class="text-center fw-bold">${index+1}</td><td><input class="bom-code" data-field="operation_code" list="operationPresets" value="${esc(row.operation_code)}" placeholder="DET"></td><td><input class="bom-name" data-field="operation_name" value="${esc(row.operation_name)}" placeholder="Dệt"></td><td><input class="bom-name" data-field="work_center" value="${esc(row.work_center)}" placeholder="Bộ phận / máy"></td><td class="text-center"><input class="form-check-input" data-field="is_outsourced" type="checkbox" ${row.is_outsourced?'checked':''}></td><td><input class="bom-name" data-field="note" value="${esc(row.note)}"></td><td><button class="wms-btn wms-btn--sm" data-up title="Lên" ${index===0?'disabled':''}><i data-lucide="arrow-up"></i></button><button class="wms-btn wms-btn--sm" data-down title="Xuống" ${index===state.operations.length-1?'disabled':''}><i data-lucide="arrow-down"></i></button><button class="wms-btn wms-btn--sm text-danger" data-remove-op title="Xóa"><i data-lucide="trash-2"></i></button></td></tr>`).join('');
        renderMaterials(); lucide.createIcons();
    }
    function renderMaterials() {
        const body=document.getElementById('materialBody');
        body.innerHTML=state.materials.map((row,index)=>{
            const formula=row.calculation_mode==='formula';
            const valuePlaceholder=row.calculation_mode==='yield'?'VD: 1840':'VD: 0,5';
            return `<tr data-material="${index}"><td class="text-center fw-bold">${index+1}</td><td><input class="bom-code" data-field="material_code" value="${esc(row.material_code)}" autocomplete="off" placeholder="Gõ mã hoặc tên vật tư"></td><td><input class="bom-name" data-field="material_name" value="${esc(row.material_name)}" placeholder="Tự lấy danh mục"></td><td><select class="bom-mode" data-field="calculation_mode"><option value="consumption" ${row.calculation_mode==='consumption'?'selected':''}>Dùng / 1 PCS</option><option value="yield" ${row.calculation_mode==='yield'?'selected':''}>PCS / 1 ĐVT</option><option value="formula" ${formula?'selected':''}>Công thức pha</option></select></td><td>${formula?'<span class="bom-pill">Theo tỷ lệ</span>':`<input class="bom-number" data-field="calculation_value" type="number" min="0" step="0.000001" value="${esc(row.calculation_value)}" placeholder="${valuePlaceholder}">`}</td><td><input class="bom-unit" data-field="unit" value="${esc(row.unit)}" placeholder="KG"></td><td><select class="bom-operation" data-field="operation_code">${operationOptions(row.operation_code)}</select></td><td><button class="wms-btn wms-btn--sm text-danger" data-remove-material title="Xóa"><i data-lucide="trash-2"></i></button></td></tr><tr class="bom-detail-row" data-detail-for="${index}"><td colspan="8"><div class="bom-detail-strip"><label>Vai trò<input data-detail-field="component_role" value="${esc(row.component_role)}" placeholder="Chung / pha / in"></label><label>Hao hụt %<input type="number" min="0" max="100" step="0.1" data-detail-field="waste_percent" value="${esc(row.waste_percent)}"></label><label class="bom-detail-check"><input class="form-check-input" data-detail-field="round_to_whole" type="checkbox" ${row.round_to_whole?'checked':''}> Cấp nguyên</label><label>Ghi chú<input data-detail-field="note" value="${esc(row.note)}" placeholder="Thông tin kỹ thuật nếu có"></label></div>${formula?`<div class="bom-formula-strip mt-2"><label>Nhóm công thức<input data-formula-field="formula_code" value="${esc(row.formula_code)}" placeholder="PHA-SILICONE"></label><label>Tổng hỗn hợp / PCS<input type="number" min="0" step="0.000001" data-formula-field="formula_output_per_unit" value="${esc(row.formula_output_per_unit)}" placeholder="2"></label><label>ĐVT hỗn hợp<input data-formula-field="formula_output_unit" value="${esc(row.formula_output_unit)}" placeholder="G"></label><label>Tỷ lệ nguyên liệu<input type="number" min="0" step="0.000001" data-formula-field="formula_part" value="${esc(row.formula_part)}" placeholder="100"></label><div class="bom-formula-help">Các dòng cùng nhóm dùng chung tổng hỗn hợp và đơn vị. Hệ thống tự chia theo tổng tỷ lệ.</div></div>`:''}</td></tr>`;
        }).join('');
        lucide.createIcons();
    }
    async function catalogSuggestions(keyword) { const value=String(keyword||'').trim(); if(value.length<2) return []; return (await request('/api/ma-noi-bo-danh-muc?with_color=0&limit=20&keyword='+encodeURIComponent(value))).data || []; }
    function suggestionHtml(rows, type, index='') { return rows.map(row=>{ const missing=!code(row.code); return `<button type="button" class="bom-suggestion" data-pick-${type}="${esc(index)}" data-code="${esc(row.code)}" data-name="${esc(row.name)}" data-unit="${esc(row.unit)}" ${missing?'disabled title="Dòng danh mục chưa có mã"':''}><strong>${missing?'Thiếu mã':esc(row.code)}</strong><span>${esc(row.name||'')}</span><small>${missing?'Bổ sung DANH MỤC':esc(row.unit||'')}</small></button>`; }).join(''); }
    async function searchProducts(keyword) { const box=document.getElementById('productSuggestions'); const rows=await catalogSuggestions(keyword); box.innerHTML=suggestionHtml(rows,'product'); box.classList.toggle('d-none',!rows.length); }
    function showMaterialSuggestions(input,index,rows) {
        if(!input?.isConnected || document.activeElement!==input) return;
        const box=document.getElementById('materialSuggestions'), rect=input.getBoundingClientRect();
        box.innerHTML=suggestionHtml(rows,'material',index);
        box.style.left=Math.max(12,Math.min(rect.left,window.innerWidth-Math.max(420,rect.width)-12))+'px';
        const dropdownHeight=Math.min(310,Math.max(52,rows.length*46));
        box.style.top=(window.innerHeight-rect.bottom>=dropdownHeight+8?rect.bottom+5:Math.max(8,rect.top-dropdownHeight-5))+'px';
        box.style.width=Math.max(420,rect.width)+'px';
        box.classList.toggle('d-none',!rows.length);
        state.activeSuggestion=-1;
    }
    function repositionMaterialSuggestions() {
        const box=document.getElementById('materialSuggestions'), input=state.activeMaterialInput;
        if(!input?.isConnected || box.classList.contains('d-none')) return;
        const rect=input.getBoundingClientRect();
        if(rect.bottom<0 || rect.top>window.innerHeight) return box.classList.add('d-none');
        box.style.left=Math.max(12,Math.min(rect.left,window.innerWidth-Math.max(420,rect.width)-12))+'px';
        box.style.top=Math.min(rect.bottom+5,window.innerHeight-box.offsetHeight-8)+'px';
    }
    async function loadProfile(itemCode) {
        if(!code(itemCode)) return;
        loading(true,'Đang tải BOM...');
        try {
            const payload=await request('/api/dinh-muc-san-xuat?item_code='+encodeURIComponent(code(itemCode)));
            const profile=payload.data, catalog=payload.catalog;
            state.profileId=profile?.id || null;
            document.getElementById('itemCode').value=code(itemCode);
            document.getElementById('itemName').value=profile?.item_name || catalog?.item_name || '';
            document.getElementById('itemUnit').value=profile?.unit || catalog?.unit || '';
            state.operations=profile?.routings?.length ? profile.routings : [blankOperation()];
            state.materials=profile?.lines?.length ? profile.lines.map(normalizeMaterial) : [blankMaterial()];
            document.getElementById('revision').textContent=profile ? `Bản ${profile.revision}` : 'BOM mới';
            renderOperations(); renderMaterials();
        } catch(error) { notify(error.message,'danger'); } finally { loading(false); }
    }
    async function save() {
        syncRows();
        const materials=state.materials.filter(row=>code(row.material_code)).map(row=>{
            const mode=['yield','formula'].includes(row.calculation_mode)?row.calculation_mode:'consumption', value=Number(row.calculation_value||0);
            return { ...row, calculation_mode:mode, consumption_per_unit:mode==='yield'?(value>0?1/value:0):(mode==='formula'?0:value), yield_quantity:mode==='yield'?value:null, formula_output_per_unit:mode==='formula'?Number(row.formula_output_per_unit||0):null, formula_part:mode==='formula'?Number(row.formula_part||0):null, round_to_whole:Boolean(row.round_to_whole) };
        });
        const payload={ item_code:code(document.getElementById('itemCode').value), item_name:document.getElementById('itemName').value.trim(), unit:code(document.getElementById('itemUnit').value), operations:state.operations.filter(row=>code(row.operation_code)), materials };
        if(!payload.item_code) return notify('Chưa chọn mã hàng thành phẩm.','warning');
        if(!payload.materials.length) return notify('Cần ít nhất một dòng vật tư.','warning');
        loading(true,'Đang lưu BOM và công đoạn...');
        try { const result=await request('/api/dinh-muc-san-xuat/luu',{method:'POST',body:JSON.stringify(payload)}); notify(result.message); await loadProfile(payload.item_code); }
        catch(error) { notify(error.message,'danger'); } finally { loading(false); }
    }
    function syncRows() {
        document.querySelectorAll('[data-op]').forEach(tr=>{ const row=state.operations[Number(tr.dataset.op)]; tr.querySelectorAll('[data-field]').forEach(input=>row[input.dataset.field]=input.type==='checkbox'?input.checked:input.value); });
        document.querySelectorAll('[data-material]').forEach(tr=>{ const row=state.materials[Number(tr.dataset.material)]; tr.querySelectorAll('[data-field]').forEach(input=>row[input.dataset.field]=input.type==='checkbox'?input.checked:input.value); });
        document.querySelectorAll('[data-detail-for]').forEach(tr=>{ const row=state.materials[Number(tr.dataset.detailFor)]; tr.querySelectorAll('[data-detail-field]').forEach(input=>row[input.dataset.detailField]=input.type==='checkbox'?input.checked:input.value); tr.querySelectorAll('[data-formula-field]').forEach(input=>row[input.dataset.formulaField]=input.value); });
    }
    async function calculate() {
        const order=code(document.getElementById('orderCode').value); if(!order) return notify('Nhập lệnh sản xuất cần tính.','warning');
        loading(true,'Đang tính nhu cầu vật tư...');
        try { const payload=await request('/api/dinh-muc-san-xuat/nhu-cau?production_order='+encodeURIComponent(order)); renderNeeds(payload); document.getElementById('snapshotBtn').disabled=!(payload.data||[]).length; }
        catch(error) { document.getElementById('needs').innerHTML=''; document.getElementById('snapshotBtn').disabled=true; notify(error.message,'danger'); } finally { loading(false); }
    }
    function requirementLabel(line) {
        if(line.calculation_mode==='formula') return `${esc(line.formula_code||'Công thức')} · ${fmt(line.formula_output_per_unit)} ${esc(line.formula_output_unit||'')} / PCS · tỷ lệ ${fmt(line.formula_part)}`;
        if(line.calculation_mode==='yield') return `${fmt(line.yield_quantity)} PCS / 1 ${esc(line.unit)}`;
        return `${fmt(line.consumption_per_unit)} ${esc(line.unit)} / PCS`;
    }
    function renderNeeds(payload) {
        const rows=payload.data||[], missing=payload.missing_items||[];
        document.getElementById('needs').innerHTML=(missing.length?`<div class="alert alert-warning mx-3">Chưa có BOM: ${esc(missing.join(', '))}</div>`:'')+rows.map(row=>`<div class="bom-need-group"><div class="bom-need-title"><div><strong>${esc(row.production_order)} · ${esc(row.item_code)}</strong><div class="bom-meta">${row.order_type==='btp'?'Lệnh BTP':'Lệnh SX trung tâm'} · ${esc(row.size||'')} ${esc(row.color||'')} · SL lệnh ${fmt(row.order_quantity)}</div></div><div class="d-flex align-items-center gap-2"><span class="bom-pill">${row.is_snapshot?'Đã chốt':'BOM hiện hành'} · Bản ${row.bom_revision}</span><button class="wms-btn wms-btn--sm wms-btn--primary" type="button" data-issue-order="${esc(row.production_order)}"><i data-lucide="package-minus"></i>Lập phiếu xuất vật tư</button></div></div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Vật tư</th><th>Vai trò</th><th>Công đoạn</th><th>Định mức</th><th>Hao hụt</th><th class="text-end">Đề xuất cấp</th></tr></thead><tbody>${row.materials.map(line=>{ const exact=Number(line.exact_required_quantity??line.required_quantity); const rounded=Math.abs(Number(line.required_quantity)-exact)>0.000001; return `<tr><td><strong>${esc(line.material_code)}</strong><div class="bom-meta">${esc(line.material_name)}</div></td><td>${esc(line.component_role)}</td><td>${esc(line.operation_code||'-')}</td><td>${requirementLabel(line)}</td><td>${fmt(line.waste_percent)}%</td><td class="text-end"><div class="bom-result"><strong>${fmt(line.required_quantity)} ${esc(line.unit)}</strong>${rounded?`<small>Tính ra ${fmt(exact)} ${esc(line.unit)}</small>`:''}</div></td></tr>`; }).join('')}</tbody></table></div></div>`).join('');
        lucide.createIcons();
    }

    document.addEventListener('input', event=>{
        const input=event.target, tr=input.closest('[data-op], [data-material]');
        const detail=input.closest('[data-detail-for]');
        if(detail) { const row=state.materials[Number(detail.dataset.detailFor)]; if(input.dataset.detailField) row[input.dataset.detailField]=input.type==='checkbox'?input.checked:input.value; if(input.dataset.formulaField) row[input.dataset.formulaField]=input.value; }
        if(tr && input.dataset.field) {
            const isOp=tr.hasAttribute('data-op'), index=Number(isOp?tr.dataset.op:tr.dataset.material), row=isOp?state.operations[index]:state.materials[index]; row[input.dataset.field]=input.type==='checkbox'?input.checked:input.value;
            if(isOp && input.dataset.field==='operation_code') { const preset=presets[code(input.value)]; if(preset && !row.operation_name) { row.operation_name=preset; tr.querySelector('[data-field="operation_name"]').value=preset; } renderMaterials(); }
            if(!isOp && input.dataset.field==='calculation_mode') { row.calculation_value=''; renderMaterials(); }
            if(!isOp && input.dataset.field==='material_code') { clearTimeout(state.materialTimer); const requestId=++state.materialRequest; state.activeMaterialIndex=index; state.activeMaterialInput=input; state.materialTimer=setTimeout(async()=>{ try { const typed=input.value; const rows=await catalogSuggestions(typed); if(requestId===state.materialRequest && input.value===typed) showMaterialSuggestions(input,index,rows); } catch(error) { if(requestId===state.materialRequest) document.getElementById('materialSuggestions').classList.add('d-none'); } },220); }
        }
        if(input.id==='itemCode') { clearTimeout(state.productTimer); state.productTimer=setTimeout(()=>searchProducts(input.value),220); }
    });
    document.addEventListener('click', event=>{
        const product=event.target.closest('[data-pick-product]'); if(product) { document.getElementById('productSuggestions').classList.add('d-none'); loadProfile(product.dataset.code); return; }
        const issueOrder=event.target.closest('[data-issue-order]'); if(issueOrder) { window.location.href='/client/vat-tu-san-xuat?production_order='+encodeURIComponent(issueOrder.dataset.issueOrder); return; }
        const material=event.target.closest('[data-pick-material]'); if(material) { const index=Number(material.dataset.pickMaterial), row=state.materials[index]; row.material_code=material.dataset.code; row.material_name=material.dataset.name; row.unit=code(material.dataset.unit)||row.unit; document.getElementById('materialSuggestions').classList.add('d-none'); renderMaterials(); document.querySelector(`[data-material="${index}"] [data-field="${row.calculation_mode==='formula'?'material_name':'calculation_value'}"]`)?.focus(); return; }
        if(!event.target.closest('#materialSuggestions') && !event.target.matches('[data-field="material_code"]')) document.getElementById('materialSuggestions').classList.add('d-none');
        const tr=event.target.closest('[data-op], [data-material]'); if(!tr) return;
        syncRows();
        if(event.target.closest('[data-remove-op]')) { state.operations.splice(Number(tr.dataset.op),1); if(!state.operations.length) state.operations.push(blankOperation()); renderOperations(); }
        if(event.target.closest('[data-remove-material]')) { state.materials.splice(Number(tr.dataset.material),1); if(!state.materials.length) state.materials.push(blankMaterial()); renderMaterials(); }
        if(event.target.closest('[data-up]')) { const i=Number(tr.dataset.op); [state.operations[i-1],state.operations[i]]=[state.operations[i],state.operations[i-1]]; renderOperations(); }
        if(event.target.closest('[data-down]')) { const i=Number(tr.dataset.op); [state.operations[i+1],state.operations[i]]=[state.operations[i],state.operations[i+1]]; renderOperations(); }
    });
    document.getElementById('itemCode').addEventListener('keydown',event=>{ if(event.key==='Enter'){ event.preventDefault(); document.getElementById('productSuggestions').classList.add('d-none'); loadProfile(event.target.value); } });
    document.getElementById('topSearch').addEventListener('keydown',event=>{ if(event.key==='Enter'){ document.getElementById('itemCode').value=event.target.value; loadProfile(event.target.value); } });
    document.getElementById('addOperationBtn').onclick=()=>{ syncRows(); state.operations.push(blankOperation()); renderOperations(); };
    document.getElementById('addMaterialBtn').onclick=()=>{ syncRows(); state.materials.push(blankMaterial()); renderMaterials(); };
    document.getElementById('addFormulaBtn').onclick=()=>{ syncRows(); const number=state.materials.filter(row=>row.calculation_mode==='formula').length+1, formula=`PHA-${number}`; state.materials.push(blankMaterial({calculation_mode:'formula',formula_code:formula,formula_output_unit:'G',component_role:'PHA'}),blankMaterial({calculation_mode:'formula',formula_code:formula,formula_output_unit:'G',component_role:'PHA'})); renderMaterials(); document.querySelector(`[data-material="${state.materials.length-2}"] [data-field="material_code"]`)?.focus(); };
    document.getElementById('newBtn').onclick=reset;
    document.getElementById('saveBtn').onclick=save;
    document.getElementById('calculateBtn').onclick=calculate;
    document.getElementById('orderCode').addEventListener('keydown',event=>{ if(event.key==='Enter'){ event.preventDefault(); calculate(); } });
    document.getElementById('snapshotBtn').onclick=async()=>{ const order=code(document.getElementById('orderCode').value); loading(true,'Đang chốt BOM cho lệnh...'); try { const payload=await request('/api/dinh-muc-san-xuat/chot-lenh',{method:'POST',body:JSON.stringify({production_order:order})}); notify(payload.message); await calculate(); } catch(error){ notify(error.message,'danger'); } finally{ loading(false); } };
    document.addEventListener('keydown',event=>{ if(!event.target.matches('[data-field="material_code"]')) return; const options=Array.from(document.querySelectorAll('#materialSuggestions .bom-suggestion:not([disabled])')); if(!options.length) return; if(event.key==='ArrowDown'||event.key==='ArrowUp'){ event.preventDefault(); state.activeSuggestion=(state.activeSuggestion+(event.key==='ArrowDown'?1:-1)+options.length)%options.length; options.forEach((node,i)=>node.classList.toggle('is-active',i===state.activeSuggestion)); options[state.activeSuggestion].scrollIntoView({block:'nearest'}); } else if(event.key==='Enter'&&state.activeSuggestion>=0){ event.preventDefault(); options[state.activeSuggestion].click(); } else if(event.key==='Escape'){ document.getElementById('materialSuggestions').classList.add('d-none'); } });
    window.addEventListener('resize',repositionMaterialSuggestions);
    window.addEventListener('scroll',repositionMaterialSuggestions,true);
    reset();
    const requestedItemCode = new URLSearchParams(window.location.search).get('item_code');
    if (requestedItemCode) {
        document.getElementById('itemCode').value = requestedItemCode;
        loadProfile(requestedItemCode);
    }
    lucide.createIcons();
})();
</script>
</body>
</html>
