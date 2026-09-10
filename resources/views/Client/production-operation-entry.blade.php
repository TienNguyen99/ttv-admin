<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ghi nhận sản xuất</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/warehouse-wms.css') }}?v={{ filemtime(public_path('css/warehouse-wms.css')) }}" rel="stylesheet">
    <style>
        .activity-page { max-width: none; margin: 0; }
        .activity-search { display:grid; grid-template-columns:minmax(260px,1fr) auto; gap:10px; }
        .activity-meta { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; margin-top:12px; }
        .activity-stat { padding:12px 14px; border:1px solid #d8e5f4; border-radius:8px; background:#f8fbff; }
        .activity-stat small { display:block; color:#64748b; font-size:11px; font-weight:800; text-transform:uppercase; }
        .activity-stat strong { display:block; margin-top:3px; color:#12345c; font-size:18px; }
        .activity-route { display:flex; gap:8px; padding-top:12px; overflow-x:auto; }
        .activity-stage { min-width:150px; padding:10px 12px; border:1px solid #cbdcf2; border-radius:8px; background:#fff; cursor:pointer; transition:border-color .18s ease,background .18s ease; }
        .activity-stage:hover,.activity-stage.is-selected { border-color:#3b82f6; background:#eff6ff; }
        .activity-stage strong,.activity-stage span { display:block; }
        .activity-stage span { margin-top:4px; color:#64748b; font-size:12px; }
        .activity-progress { height:5px; margin-top:8px; overflow:hidden; border-radius:3px; background:#dbeafe; }
        .activity-progress i { display:block; height:100%; background:#2563eb; transition:width .25s ease; }
        .activity-form { display:grid; grid-template-columns:150px minmax(150px,1fr) minmax(180px,1fr) minmax(180px,1fr); gap:10px; }
        .activity-variant { margin-bottom:12px; padding:12px; border:1px solid #bfdbfe; border-radius:8px; background:#eff6ff; }
        .activity-variant__head { display:flex; justify-content:space-between; gap:12px; align-items:center; margin-bottom:10px; }
        .activity-variant__form { display:grid; grid-template-columns:minmax(180px,1.4fr) minmax(130px,.8fr) minmax(100px,.6fr) minmax(150px,1fr) minmax(90px,.55fr) auto; gap:8px; align-items:end; }
        .activity-table { min-width:960px; }
        .activity-table td { vertical-align:middle; }
        .activity-number { text-align:right; font-weight:800; }
        .activity-input { min-width:110px; text-align:right; }
        .activity-history { display:grid; gap:0; }
        .activity-log { display:grid; grid-template-columns:150px 140px minmax(0,1fr) auto; gap:12px; align-items:center; padding:11px 16px; border-top:1px solid #e2e8f0; }
        .activity-log:first-child { border-top:0; }
        .activity-empty { padding:30px 16px; color:#64748b; text-align:center; }
        .activity-loading { display:none; position:fixed; inset:0; z-index:2100; place-items:center; background:rgba(239,246,255,.76); backdrop-filter:blur(2px); }
        .activity-loading.is-visible { display:grid; }
        .activity-loading>div { display:flex; gap:10px; align-items:center; padding:12px 16px; border:1px solid #bfdbfe; border-radius:8px; background:#fff; color:#12345c; font-weight:800; }
        @media(max-width:900px) { .activity-variant__form { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media(max-width:760px) { .activity-meta,.activity-form,.activity-variant__form { grid-template-columns:1fr; } .activity-search { grid-template-columns:1fr; } .activity-log { grid-template-columns:1fr auto; } .activity-log__detail { grid-column:1/-1; } }
        @media(prefers-reduced-motion:reduce) { * { transition:none!important; scroll-behavior:auto!important; } }
    </style>
</head>
<body>
@include('layouts.partials.sidebar')

<header class="wms-topbar">
    <h1 class="wms-topbar__title">WMS May Mặc</h1>
    <div class="wms-global-search"><i data-lucide="search"></i><input id="topSearch" class="form-control" placeholder="Tìm lệnh hoặc mã hàng..."></div>
    <a class="wms-btn" href="{{ url('/client/lenh-san-xuat-trung-tam') }}"><i data-lucide="route"></i>Lệnh trung tâm</a>
</header>

<main class="wms-page activity-page">
    <div class="wms-heading"><div><h1>Ghi nhận sản xuất</h1><p>Quét QR hoặc mở lệnh, chọn công đoạn, nhập sản lượng.</p></div></div>

    <section class="wms-panel">
        <div class="wms-panel__body">
            <div class="activity-search">
                <label class="wms-field"><span>Lệnh sản xuất</span><input id="orderInput" class="form-control" list="orderOptions" autocomplete="off" placeholder="Gõ lệnh hoặc mã hàng rồi Enter"><datalist id="orderOptions"></datalist></label>
                <button id="loadBtn" class="wms-btn wms-btn--primary" type="button"><i data-lucide="search"></i>Mở lệnh</button>
            </div>
            <div id="orderMeta" class="activity-meta d-none"></div>
            <div id="routeBar" class="activity-route"></div>
        </div>
    </section>

    <section id="entryPanel" class="wms-panel mt-3 d-none">
        <div class="wms-panel__head"><strong id="entryTitle">Sản lượng công đoạn</strong><a id="qrButton" class="wms-btn wms-btn--sm" target="_blank"><i data-lucide="qr-code"></i>In QR lệnh</a></div>
        <div class="wms-panel__body">
            <div id="variantPanel" class="activity-variant d-none">
                <div class="activity-variant__head">
                    <div><strong>Thêm mã biến thể</strong><div id="variantHint" class="small text-secondary"></div></div>
                    <span id="variantRemaining" class="badge text-bg-primary"></span>
                </div>
                <div class="activity-variant__form">
                    <label class="wms-field"><span>Mã biến thể</span><input id="variantCode" class="form-control" list="variantOptions" autocomplete="off" placeholder="Ví dụ 108333-2AB"><datalist id="variantOptions"></datalist></label>
                    <label class="wms-field"><span>SL kế hoạch</span><input id="variantQuantity" class="form-control" type="number" min="0.001" step="0.001"></label>
                    <label class="wms-field"><span>Size</span><input id="variantSize" class="form-control"></label>
                    <label class="wms-field"><span>Màu</span><input id="variantColor" class="form-control"></label>
                    <label class="wms-field"><span>ĐVT</span><input id="variantUnit" class="form-control" value="PCS"></label>
                    <button id="addVariantBtn" class="wms-btn wms-btn--primary" type="button"><i data-lucide="plus"></i>Thêm</button>
                </div>
            </div>
            <div class="activity-form">
                <label class="wms-field"><span>Ngày ghi nhận</span><input id="activityDate" class="form-control" placeholder="dd/mm/yyyy"></label>
                <label class="wms-field"><span>Công đoạn</span><select id="operationSelect" class="form-select"></select></label>
                <label class="wms-field"><span>Chuyển sang công đoạn</span><select id="nextOperationSelect" class="form-select"></select></label>
                <label class="wms-field"><span>Người thực hiện</span><input id="operatorName" class="form-control" placeholder="Tên người / tổ sản xuất"></label>
            </div>
        </div>
        <div class="wms-table-wrap">
            <table class="wms-table activity-table">
                <thead><tr><th>Mã sản xuất</th><th>Tên hàng</th><th>Size / màu</th><th class="text-end">SL lệnh</th><th class="text-end">Đã ghi nhận</th><th class="text-end">Còn lại</th><th class="text-end">SL đạt</th><th class="text-end">SL lỗi</th><th>ĐVT</th><th>Ghi chú</th></tr></thead>
                <tbody id="lineBody"></tbody>
            </table>
        </div>
        <div class="wms-panel__body d-flex flex-wrap justify-content-between gap-2 align-items-end">
            <label class="wms-field flex-grow-1"><span>Ghi chú chung</span><input id="activityNote" class="form-control"></label>
            <button id="saveBtn" class="wms-btn wms-btn--primary" type="button"><i data-lucide="save"></i>Lưu sản lượng</button>
        </div>
        <div id="message" class="alert d-none mx-3 mb-3" role="alert"></div>
    </section>

    <section id="historyPanel" class="wms-panel mt-3 d-none">
        <div class="wms-panel__head"><strong>Nhật ký gần đây</strong><span class="small text-secondary">Chỉ là nhật ký sản xuất, không làm tăng giảm tồn kho</span></div>
        <div id="historyBody" class="activity-history"></div>
    </section>
</main>

<div id="loading" class="activity-loading"><div><span class="spinner-border spinner-border-sm"></span><span id="loadingText">Đang tải...</span></div></div>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
(() => {
    const csrf=document.querySelector('meta[name="csrf-token"]').content;
    const state={order:null,searchTimer:null,variantTimer:null,variantSuggestions:[]};
    const esc=value=>String(value??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
    const num=value=>new Intl.NumberFormat('vi-VN',{maximumFractionDigits:3}).format(Number(value||0));
    const code=value=>String(value||'').trim().toUpperCase();
    const today=()=>new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Ho_Chi_Minh'}).format(new Date()).split('-').reverse().join('/');
    const vnToIso=value=>{const m=String(value||'').match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);return m?`${m[3]}-${m[2].padStart(2,'0')}-${m[1].padStart(2,'0')}`:value;};
    const loading=(show,text='Đang tải...')=>{document.getElementById('loadingText').textContent=text;document.getElementById('loading').classList.toggle('is-visible',show);};
    const parse=async response=>{const payload=await response.json().catch(()=>({}));if(!response.ok){const errors=payload.errors?Object.values(payload.errors).flat().join('\n'):'';throw new Error(errors||payload.message||'Không xử lý được yêu cầu.');}return payload;};
    const notice=(message,type='success')=>{const node=document.getElementById('message');node.textContent=message;node.className=`alert alert-${type} mx-3 mb-3`;};

    function itemProgress(item,operationCode){return (item.operation_progress||[]).find(row=>code(row.operation_code)===code(operationCode))||{good_quantity:0,defect_quantity:0,remaining_quantity:Number(item.planned_quantity||0),percent:0};}
    function selectedOperation(){return code(document.getElementById('operationSelect').value);}
    function updateNextOperation(){
        const current=selectedOperation(),select=document.getElementById('nextOperationSelect'),previous=code(select.value);
        const operations=(state.order?.operations||[]).filter(op=>code(op.code)!==current);
        select.innerHTML='<option value="">Không tạo phiếu chuyển</option>'+operations.map(op=>`<option value="${esc(op.code)}">${esc(op.name)}</option>`).join('');
        if(previous&&operations.some(op=>code(op.code)===previous)){select.value=previous;return;}
        const configured=(state.order?.operations||[]).filter(op=>op.is_configured);
        const configuredIndex=configured.findIndex(op=>code(op.code)===current);
        const suggested=configuredIndex>=0?configured[configuredIndex+1]?.code:({IN:'CAT',DET:'CAT'}[current]||'');
        if(suggested&&operations.some(op=>code(op.code)===code(suggested))){select.value=suggested;}
    }
    function suggestedNextOperation(sourceCode){
        const source=code(sourceCode),configured=(state.order?.operations||[]).filter(op=>op.is_configured);
        const configuredIndex=configured.findIndex(op=>code(op.code)===source);
        if(configuredIndex>=0&&configured[configuredIndex+1])return configured[configuredIndex+1];
        const suggestedCode=({IN:'CAT',DET:'CAT'}[source]||'');
        return (state.order?.operations||[]).find(op=>code(op.code)===suggestedCode)||null;
    }
    function renderLines(){
        const operation=selectedOperation();
        document.getElementById('lineBody').innerHTML=(state.order?.items||[]).map((item,index)=>{
            const progress=itemProgress(item,operation),remaining=Number(progress.remaining_quantity||0);
            return `<tr data-line="${index}"><td><strong>${esc(item.internal_item_code)}</strong>${item.source_item_code&&code(item.source_item_code)!==code(item.internal_item_code)?`<div class="small text-secondary">Mã gốc ${esc(item.source_item_code)}</div>`:''}</td><td>${esc(item.item_name||'-')}</td><td>${esc([item.size,item.color].filter(Boolean).join(' · ')||'-')}</td><td class="activity-number">${num(item.planned_quantity)}</td><td class="activity-number text-primary">${num(progress.good_quantity)}</td><td class="activity-number">${num(remaining)}</td><td><input class="form-control form-control-sm activity-input js-good" type="number" min="0" max="${remaining}" step="0.001" value="0"></td><td><input class="form-control form-control-sm activity-input js-defect" type="number" min="0" step="0.001" value="0"></td><td>${esc(item.unit||'PCS')}</td><td><input class="form-control form-control-sm js-note"></td></tr>`;
        }).join('')||'<tr><td colspan="10" class="activity-empty">Lệnh không có mã hàng.</td></tr>';
    }
    function renderRoute(){
        const operations=state.order?.operations||[],selected=selectedOperation()||operations[0]?.code;
        const routeOperations=operations.filter(op=>op.is_configured||Number(op.good_quantity||0)>0);
        document.getElementById('routeBar').innerHTML=routeOperations.map((op,index)=>`<button type="button" class="activity-stage ${code(op.code)===code(selected)?'is-selected':''}" data-operation="${esc(op.code)}"><strong>${index+1}. ${esc(op.name)}</strong><span>${num(op.good_quantity)} / ${num(op.planned_quantity)} · ${num(op.percent)}%</span><div class="activity-progress"><i style="width:${Number(op.percent||0)}%"></i></div></button>`).join('');
        document.getElementById('routeBar').classList.toggle('d-none',routeOperations.length===0);
    }
    function renderHistory(){
        const rows=state.order?.activities||[];
        document.getElementById('historyBody').innerHTML=rows.length?rows.map(row=>{
            const next=suggestedNextOperation(row.operation_code);
            const transferAction=row.transfer_print_url
                ? `<a class="wms-btn wms-btn--sm" href="${esc(row.transfer_print_url)}" target="_blank" title="In phiếu chuyển"><i data-lucide="printer"></i></a>`
                : (next?`<button class="wms-btn wms-btn--sm" type="button" data-create-transfer="${row.id}" data-next-operation="${esc(next.code)}"><i data-lucide="arrow-right"></i>Chuyển ${esc(next.name)}</button>`:'');
            return `<div class="activity-log"><div><strong>${esc(row.activity_code)}</strong><div class="small text-secondary">${esc(String(row.activity_date||'').slice(0,10).split('-').reverse().join('/'))}</div></div><div><strong>${esc(row.operation_name)}${row.next_operation_name?` → ${esc(row.next_operation_name)}`:''}</strong><div class="small text-secondary">${esc(row.transfer_code||row.operator_name||'-')}</div></div><div class="activity-log__detail">${(row.lines||[]).map(line=>`${esc(line.internal_item_code)}: đạt ${num(line.good_quantity)}, lỗi ${num(line.defect_quantity)} ${esc(line.unit||'')}`).join(' · ')}</div><div class="d-flex gap-1">${transferAction}<button class="wms-btn wms-btn--sm text-danger" type="button" data-delete="${row.id}" title="Xóa lần ghi nhận"><i data-lucide="trash-2"></i></button></div></div>`;
        }).join(''):'<div class="activity-empty">Chưa ghi nhận sản lượng công đoạn.</div>';
        lucide.createIcons();
    }
    function renderOrder(payload){
        const previousOperation=selectedOperation();
        state.order=payload;
        document.getElementById('orderMeta').className='activity-meta';
        const orderLabel=payload.order_type==='supplemental'?'Lệnh sản xuất phụ':'Lệnh sản xuất';
        document.getElementById('orderMeta').innerHTML=`<div class="activity-stat"><small>${orderLabel}</small><strong>${esc(payload.production_order)}</strong><span class="small text-secondary">Tổng đơn hàng ${num(payload.order_quantity||0)}</span></div><div class="activity-stat"><small>Khách hàng</small><strong>${esc(payload.customer||'-')}</strong></div><div class="activity-stat"><small>PO</small><strong>${esc(payload.purchase_order||'-')}</strong></div>`;
        document.getElementById('operationSelect').innerHTML=(payload.operations||[]).map(op=>`<option value="${esc(op.code)}">${esc(op.name)}</option>`).join('');
        if(previousOperation&&(payload.operations||[]).some(op=>code(op.code)===previousOperation)){document.getElementById('operationSelect').value=previousOperation;}
        updateNextOperation();
        const isSupplemental=payload.order_type==='supplemental';
        const baseCode=(payload.root_item_codes||[])[0]||'';
        document.getElementById('variantPanel').classList.toggle('d-none',!isSupplemental);
        document.getElementById('variantHint').textContent=isSupplemental?`Mã phải thuộc mã gốc ${baseCode}. Mỗi biến thể có số lượng kế hoạch riêng.`:'';
        document.getElementById('variantRemaining').textContent=`Còn phân bổ ${num(payload.variant_remaining_quantity||0)}`;
        if(isSupplemental&&!document.getElementById('variantCode').value){document.getElementById('variantCode').placeholder=`Ví dụ ${baseCode}-2AB`;document.getElementById('variantUnit').value=(payload.items||[])[0]?.unit||'PCS';}
        document.getElementById('entryPanel').classList.toggle('d-none',!(payload.operations||[]).length);
        document.getElementById('historyPanel').classList.remove('d-none');
        document.getElementById('qrButton').href='/client/ghi-nhan-san-xuat/qr?production_order='+encodeURIComponent(payload.production_order);
        renderRoute();renderLines();renderHistory();lucide.createIcons();
    }
    async function addVariant(){
        if(!state.order||state.order.order_type!=='supplemental')return;
        const internalItemCode=document.getElementById('variantCode').value.trim();
        const plannedQuantity=Number(document.getElementById('variantQuantity').value||0);
        if(!internalItemCode||plannedQuantity<=0){notice('Nhập mã biến thể và số lượng kế hoạch.','warning');return;}
        loading(true,'Đang thêm biến thể...');
        try{
            const payload=await fetch('/api/ghi-nhan-san-xuat/bien-the',{method:'POST',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({production_order:state.order.production_order,internal_item_code:internalItemCode,planned_quantity:plannedQuantity,size:document.getElementById('variantSize').value.trim(),color:document.getElementById('variantColor').value.trim(),unit:document.getElementById('variantUnit').value.trim()})}).then(parse);
            ['variantCode','variantQuantity','variantSize','variantColor'].forEach(id=>document.getElementById(id).value='');
            renderOrder(payload.order);notice(payload.message,'success');
        }catch(error){notice(error.message,'danger');}finally{loading(false);}
    }
    async function loadOrder(){
        const order=document.getElementById('orderInput').value.trim();if(!order)return;
        loading(true,'Đang mở lệnh sản xuất...');
        try{const payload=await fetch('/api/ghi-nhan-san-xuat?production_order='+encodeURIComponent(order)).then(parse);renderOrder(payload.data);document.getElementById('message').classList.add('d-none');}
        catch(error){notice(error.message,'danger');}
        finally{loading(false);}
    }
    async function save(){
        if(!state.order)return;
        const lines=[...document.querySelectorAll('#lineBody tr[data-line]')].map(tr=>{const item=state.order.items[Number(tr.dataset.line)];return{internal_item_code:item.internal_item_code,good_quantity:Number(tr.querySelector('.js-good').value||0),defect_quantity:Number(tr.querySelector('.js-defect').value||0),note:tr.querySelector('.js-note').value.trim()};}).filter(line=>line.good_quantity>0||line.defect_quantity>0);
        loading(true,'Đang lưu sản lượng...');
        try{const payload=await fetch('/api/ghi-nhan-san-xuat',{method:'POST',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({activity_date:vnToIso(document.getElementById('activityDate').value),production_order:state.order.production_order,operation_code:selectedOperation(),next_operation_code:document.getElementById('nextOperationSelect').value,operator_name:document.getElementById('operatorName').value.trim(),note:document.getElementById('activityNote').value.trim(),lines})}).then(parse);renderOrder(payload.order);notice(payload.message,'success');}
        catch(error){notice(error.message,'danger');}
        finally{loading(false);}
    }
    async function remove(id){
        if(!confirm('Xóa lần ghi nhận này? Tiến độ công đoạn sẽ được tính lại.'))return;
        loading(true,'Đang xóa...');
        try{const payload=await fetch('/api/ghi-nhan-san-xuat/'+id,{method:'DELETE',headers:{'Accept':'application/json','X-CSRF-TOKEN':csrf}}).then(parse);renderOrder(payload.order);notice(payload.message,'success');}
        catch(error){notice(error.message,'danger');}finally{loading(false);}
    }
    async function createTransfer(id,nextOperationCode){
        loading(true,'Đang tạo phiếu chuyển công đoạn...');
        try{const payload=await fetch(`/api/ghi-nhan-san-xuat/${id}/phieu-chuyen`,{method:'POST',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({next_operation_code:nextOperationCode})}).then(parse);renderOrder(payload.order);notice(payload.message,'success');}
        catch(error){notice(error.message,'danger');}finally{loading(false);}
    }

    document.getElementById('activityDate').value=today();
    document.getElementById('loadBtn').onclick=loadOrder;
    document.getElementById('saveBtn').onclick=save;
    document.getElementById('addVariantBtn').onclick=addVariant;
    document.getElementById('orderInput').addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();loadOrder();}});
    document.getElementById('operationSelect').addEventListener('change',()=>{updateNextOperation();renderRoute();renderLines();});
    document.getElementById('routeBar').addEventListener('click',event=>{const button=event.target.closest('[data-operation]');if(!button)return;document.getElementById('operationSelect').value=button.dataset.operation;updateNextOperation();renderRoute();renderLines();});
    document.getElementById('historyBody').addEventListener('click',event=>{const deleteButton=event.target.closest('[data-delete]');if(deleteButton){remove(deleteButton.dataset.delete);return;}const transferButton=event.target.closest('[data-create-transfer]');if(transferButton)createTransfer(transferButton.dataset.createTransfer,transferButton.dataset.nextOperation);});
    document.getElementById('topSearch').addEventListener('input',event=>{document.getElementById('orderInput').value=event.target.value;});
    document.getElementById('orderInput').addEventListener('input',event=>{clearTimeout(state.searchTimer);const keyword=event.target.value.trim();if(keyword.length<2)return;state.searchTimer=setTimeout(()=>fetch('/api/lenh-san-xuat-trung-tam/tim-kiem?keyword='+encodeURIComponent(keyword)+'&limit=20').then(parse).then(payload=>{document.getElementById('orderOptions').innerHTML=(payload.data||[]).map(row=>`<option value="${esc(row.production_order)}" label="${esc([row.order_type==='supplemental'?'Lệnh phụ':'Lệnh chính',`ĐH ${num(row.order_quantity||0)}`,row.customer,row.purchase_order,(row.items||[]).map(item=>item.item_code).join(', ')].filter(Boolean).join(' · '))}"></option>`).join('');}).catch(()=>{}),180);});
    document.getElementById('variantCode').addEventListener('input',event=>{clearTimeout(state.variantTimer);const keyword=event.target.value.trim();if(keyword.length<2)return;state.variantTimer=setTimeout(()=>fetch('/api/ma-noi-bo-danh-muc?with_color=0&limit=20&keyword='+encodeURIComponent(keyword)).then(parse).then(payload=>{state.variantSuggestions=payload.data||[];document.getElementById('variantOptions').innerHTML=state.variantSuggestions.map(row=>`<option value="${esc(row.code)}" label="${esc(row.name||'')}"></option>`).join('');}).catch(()=>{}),180);});
    document.getElementById('variantCode').addEventListener('change',event=>{const selected=state.variantSuggestions.find(row=>code(row.code)===code(event.target.value));if(!selected)return;document.getElementById('variantSize').value=selected.size||'';document.getElementById('variantColor').value=selected.color||'';document.getElementById('variantUnit').value=selected.unit||'PCS';});
    const requested=new URLSearchParams(location.search).get('production_order');if(requested){document.getElementById('orderInput').value=requested;loadOrder();}
    lucide.createIcons();
})();
</script>
</body>
</html>
