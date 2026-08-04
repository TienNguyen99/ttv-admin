<script src="{{ asset('js/vietnam-date-input.js') }}?v=20260801" defer></script>
<style>
    :root { 
        --summary-sidebar-width: 240px; 
        --wms-transition-smooth: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        --radius-sm: 10px;
        --radius-md: 14px;
        --radius-lg: 20px;
        --wms-blue: #4f8df7;
        --wms-blue-hover: #2f6fe8;
        --wms-blue-soft: #eef6ff;
        --wms-blue-soft-border: rgba(79, 141, 247, 0.24);
        --shadow-sm: 0 8px 18px rgba(64, 111, 170, 0.07);
        --shadow-md: 0 14px 34px rgba(64, 111, 170, 0.10);
        --shadow-lg: 0 22px 48px rgba(64, 111, 170, 0.13);
        --shadow-xl: 0 30px 70px rgba(64, 111, 170, 0.16);
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes fabPulse {
        0% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.5); }
        70% { box-shadow: 0 0 0 12px rgba(37, 99, 235, 0); }
        100% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0); }
    }

    body { 
        padding-left: var(--summary-sidebar-width); 
        transition: padding-left 0.3s ease;
    }
    
    .summary-sidebar {
        position: fixed;
        inset: 0 auto 0 0;
        z-index: 1040;
        display: flex;
        flex-direction: column;
        width: var(--summary-sidebar-width);
        overflow-y: auto;
        background:
            radial-gradient(circle at 18% 4%, rgba(137, 197, 255, .36), transparent 18rem),
            linear-gradient(180deg, rgba(246, 251, 255, .96) 0%, rgba(231, 242, 255, .96) 100%);
        color: #31506f;
        border-right: 1px solid rgba(191, 216, 246, .9);
        box-shadow: 12px 0 44px rgba(64, 111, 170, .12);
        backdrop-filter: blur(18px) saturate(1.16);
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        font-family: "Plus Jakarta Sans", "Inter", sans-serif;
    }
    
    .summary-sidebar__brand {
        display: flex;
        align-items: center;
        gap: 12px;
        min-height: 78px;
        padding: 16px 20px;
        border-bottom: 1px solid rgba(120,168,230,.18);
        color: #123c69;
        text-decoration: none;
    }
    
    .summary-sidebar__mark {
        display: grid;
        width: 40px;
        height: 40px;
        flex: 0 0 40px;
        place-items: center;
        border: 1px solid rgba(79,141,247,.24);
        border-radius: 16px;
        background: linear-gradient(135deg, #77b7ff, #4f8df7);
        box-shadow: 0 14px 28px rgba(79,141,247,.24);
    }
    
    .summary-sidebar__mark svg { 
        width: 22px; 
        height: 22px; 
        color: #ffffff;
    }
    
    .summary-sidebar__brand-title { 
        font-size: 15.5px; 
        font-weight: 800; 
        line-height: 1.2; 
        letter-spacing: 0;
    }
    
    .summary-sidebar__brand-subtitle { 
        margin-top: 3px; 
        color: #6b7f99; 
        font-size: 10px; 
        font-weight: 700; 
        text-transform: uppercase; 
        letter-spacing: 0.05em;
    }
    
    .summary-sidebar__label {
        padding: 14px 18px 6px;
        color: #7b91aa;
        font-size: 10.5px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }
    
    .summary-sidebar__link,
    .summary-sidebar__summary {
        position: relative;
        display: flex;
        align-items: center;
        gap: 10px;
        min-height: 42px;
        margin: 4px 12px;
        padding: 9px 12px;
        border-left: 0;
        border-radius: 16px;
        color: #5c7592;
        font-size: 13.5px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        list-style: none;
        overflow: hidden;
        transition: var(--wms-transition-smooth);
    }

    .summary-sidebar__link::before,
    .summary-sidebar__summary::before {
        content: "";
        position: absolute;
        inset: 0 auto 0 0;
        width: 0;
        background: linear-gradient(90deg, rgba(117, 181, 255, 0.18), transparent);
        transition: width 0.24s cubic-bezier(0.16, 1, 0.3, 1);
    }
    
    .summary-sidebar__link svg,
    .summary-sidebar__summary svg { 
        width: 18px; 
        height: 18px; 
        flex: 0 0 18px; 
        opacity: 0.85;
        transition: transform 0.2s ease;
    }
    
    .summary-sidebar__link:hover,
    .summary-sidebar__summary:hover {
        background: rgba(255, 255, 255, 0.72);
        color: #174679;
        transform: translateX(3px);
        box-shadow: 0 10px 22px rgba(64, 111, 170, 0.10);
    }

    .summary-sidebar__link:hover::before,
    .summary-sidebar__summary:hover::before {
        width: 100%;
    }
    
    .summary-sidebar__link:hover svg,
    .summary-sidebar__summary:hover svg {
        transform: scale(1.1);
        opacity: 1;
    }
    
    .summary-sidebar__link.is-active,
    .summary-sidebar__group[open] > .summary-sidebar__summary {
        background: linear-gradient(135deg, #77b7ff 0%, #4f8df7 55%, #6c7ff4 100%);
        color: #ffffff;
        box-shadow: 0 18px 34px rgba(79, 141, 247, 0.28);
    }

    .summary-sidebar__link.is-active::before,
    .summary-sidebar__group[open] > .summary-sidebar__summary::before {
        width: 100%;
    }
    
    .summary-sidebar__link.is-active svg,
    .summary-sidebar__group[open] > .summary-sidebar__summary svg {
        opacity: 1;
    }
    
    .summary-sidebar__summary::-webkit-details-marker { display: none; }
    
    .summary-sidebar__summary::after {
        content: "›";
        margin-left: auto;
        color: #7b91aa;
        font-size: 18px;
        font-family: sans-serif;
        line-height: 1;
        transition: transform 0.2s ease;
        transform: rotate(0);
    }
    
    .summary-sidebar__group[open] > .summary-sidebar__summary::after { 
        transform: rotate(90deg); 
        color: #ffffff;
    }
    
    .summary-sidebar__child {
        min-height: 38px;
        margin-left: 28px;
        padding-left: 14px;
        font-size: 12.5px;
    }

    .summary-sidebar__nav {
        padding: 4px 0 16px;
    }

    .summary-sidebar__group--section {
        margin: 0;
    }

    .summary-sidebar__group--section > .summary-sidebar__summary {
        font-weight: 750;
    }

    .summary-sidebar__subgroup > .summary-sidebar__summary {
        min-height: 38px;
        margin-left: 28px;
        padding-left: 14px;
        font-size: 12.5px;
    }

    .summary-sidebar__subgroup[open] > .summary-sidebar__summary {
        border: 1px solid rgba(79, 141, 247, 0.18);
        background: rgba(255, 255, 255, 0.72);
        color: #174679;
        box-shadow: none;
    }

    .summary-sidebar__subgroup[open] > .summary-sidebar__summary::after {
        color: #4f8df7;
    }

    .summary-sidebar__deep-child {
        margin-left: 44px;
    }
    
    .summary-sidebar-toggle {
        display: none;
        position: fixed;
        top: 14px;
        left: 14px;
        z-index: 1050;
        width: 44px;
        height: 44px;
        border: 1px solid #cbd5e1;
        border-radius: 16px;
        background: rgba(255,255,255,.9);
        color: #174679;
        font-size: 22px;
        box-shadow: var(--shadow-md);
        cursor: pointer;
        transition: var(--wms-transition-smooth);
    }
    
    .summary-sidebar-toggle:hover {
        background: #f8fafc;
        border-color: #94a3b8;
    }

    @media (max-width: 991.98px) {
        body { padding-left: 0; }
        .summary-sidebar { transform: translateX(-100%); }
        .summary-sidebar.is-open { transform: translateX(0); }
        .summary-sidebar-toggle { display: block; }
    }
    
    @media (prefers-reduced-motion: reduce) { 
        .summary-sidebar { transition: none; } 
    }
    
    /* Warehouse Assistant Floating Button & Chat Drawer */
    .warehouse-assistant-fab {
        position: fixed;
        right: 24px;
        bottom: 24px;
        z-index: 1060;
        display: grid;
        width: 52px;
        height: 52px;
        place-items: center;
        border: none;
        border-radius: 18px;
        background: linear-gradient(135deg, #77b7ff, #4f8df7);
        color: #ffffff;
        box-shadow: 0 18px 38px rgba(79, 141, 247, 0.30);
        cursor: pointer;
        animation: fabPulse 2.4s infinite;
        transition: var(--wms-transition-smooth);
    }
    
    .warehouse-assistant-fab:hover {
        background: linear-gradient(135deg, #64aaff, #367cf0);
        transform: translateY(-2px) scale(1.04);
        box-shadow: 0 24px 48px rgba(79, 141, 247, 0.38);
    }
    
    .warehouse-assistant-fab svg { 
        width: 24px; 
        height: 24px; 
    }
    
    .warehouse-assistant {
        position: fixed;
        right: 24px;
        bottom: 90px;
        z-index: 1060;
        display: flex !important;
        flex-direction: column;
        width: min(380px, calc(100vw - 48px));
        height: 480px;
        overflow: hidden;
        border: 1px solid rgba(198, 220, 247, 0.9);
        border-radius: 24px;
        background: rgba(255,255,255,.94);
        color: #0f172a;
        box-shadow: var(--shadow-xl);
        backdrop-filter: blur(18px) saturate(1.12);
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transform: translateY(24px) scale(0.95);
        transition: opacity 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), 
                    transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), 
                    visibility 0.3s;
    }
    
    .warehouse-assistant.is-open { 
        opacity: 1 !important;
        visibility: visible !important;
        pointer-events: auto !important;
        transform: translateY(0) scale(1) !important;
    }
    
    .warehouse-assistant__head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 16px 20px;
        border-bottom: 1px solid rgba(198, 220, 247, 0.86);
        background: linear-gradient(135deg, #f7fbff 0%, #e8f4ff 100%);
        color: #123c69;
    }
    
    .warehouse-assistant__title { 
        margin: 0; 
        font-size: 15px; 
        font-weight: 800; 
        color: #123c69; 
        letter-spacing: 0;
    }
    
    .warehouse-assistant__sub { 
        margin-top: 3px; 
        color: #6b7f99; 
        font-size: 11px; 
        font-weight: 500;
    }
    
    .warehouse-assistant__close {
        display: grid;
        width: 30px;
        height: 30px;
        place-items: center;
        border: 1px solid rgba(198, 220, 247, 0.9);
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.76);
        color: #174679;
        cursor: pointer;
        transition: var(--wms-transition-smooth);
    }
    
    .warehouse-assistant__close:hover {
        background: #ffffff;
        transform: scale(1.05);
    }
    
    .warehouse-assistant__body {
        display: flex;
        flex-direction: column;
        gap: 12px;
        flex: 1;
        height: auto;
        padding: 16px;
        overflow-y: auto;
        background: #f3f8ff;
        scroll-behavior: smooth;
    }
    
    .warehouse-assistant__msg {
        max-width: 85%;
        padding: 10px 14px;
        border: 1px solid #d8e8fb;
        border-radius: 18px 18px 18px 6px;
        background: #ffffff;
        color: #0f172a;
        font-size: 13px;
        line-height: 1.45;
        white-space: pre-wrap;
        word-break: break-word;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        animation: fadeInUp 0.2s ease-out;
    }
    
    .warehouse-assistant__msg--user {
        align-self: flex-end;
        border: none;
        border-radius: 18px 18px 6px 18px;
        background: linear-gradient(135deg, #77b7ff, #4f8df7);
        color: #ffffff;
        box-shadow: 0 4px 10px rgba(37, 99, 235, 0.15);
    }
    
    .warehouse-assistant__quick {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        padding: 0 16px 12px;
        background: #f3f8ff;
    }
    
    .warehouse-assistant__chip {
        border: 1px solid #cbd5e1;
        border-radius: var(--radius-full);
        background: #ffffff;
        color: var(--wms-blue);
        font-size: 11.5px;
        font-weight: 600;
        padding: 6px 12px;
        cursor: pointer;
        box-shadow: var(--shadow-sm);
        transition: var(--wms-transition-smooth);
    }
    
    .warehouse-assistant__chip:hover {
        background: var(--wms-blue-soft);
        border-color: var(--wms-blue-soft-border);
        color: var(--wms-blue-hover);
        transform: translateY(-1px);
    }
    
    .warehouse-assistant__form {
        display: flex;
        gap: 8px;
        padding: 12px 16px;
        border-top: 1px solid #e2e8f0;
        background: #ffffff;
    }
    
    .warehouse-assistant__input {
        min-width: 0;
        flex: 1;
        height: 38px;
        border: 1px solid #cbd5e1;
        border-radius: 14px;
        padding: 0 12px;
        color: #0f172a;
        font-size: 13px;
        font-weight: 500;
        transition: var(--wms-transition-smooth);
    }
    
    .warehouse-assistant__input:focus {
        outline: none;
        border-color: var(--wms-blue);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }
    
    .warehouse-assistant__send {
        display: grid;
        width: 38px;
        height: 38px;
        place-items: center;
        border: none;
        border-radius: 14px;
        background: linear-gradient(135deg, #77b7ff, #4f8df7);
        color: #ffffff;
        cursor: pointer;
        transition: var(--wms-transition-smooth);
    }
    
    .warehouse-assistant__send:hover:not(:disabled) {
        background: var(--wms-blue-hover);
        transform: scale(1.05);
    }
    
    .warehouse-assistant__send:disabled { 
        opacity: .6; 
        cursor: not-allowed; 
    }
    
    @media (max-width: 575.98px) {
        .warehouse-assistant {
            right: 12px;
            bottom: 80px;
            width: calc(100vw - 24px);
            height: calc(100vh - 120px);
        }
        .warehouse-assistant-fab { right: 12px; bottom: 12px; }
    }
    
    @media print {
        body { padding-left: 0 !important; }
        .summary-sidebar, .summary-sidebar-toggle, .warehouse-assistant, .warehouse-assistant-fab { display: none !important; }
    }

    /* Custom scrollbars for sidebar and chat assistant */
    .summary-sidebar::-webkit-scrollbar,
    .warehouse-assistant__body::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }
    
    .summary-sidebar::-webkit-scrollbar-track,
    .warehouse-assistant__body::-webkit-scrollbar-track {
        background: transparent;
    }
    
    .summary-sidebar::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.15);
        border-radius: 9999px;
    }
    
    .summary-sidebar::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.28);
    }

    .warehouse-assistant__body::-webkit-scrollbar-thumb {
        background: rgba(0, 0, 0, 0.15);
        border-radius: 9999px;
    }

    .warehouse-assistant__body::-webkit-scrollbar-thumb:hover {
        background: rgba(0, 0, 0, 0.28);
    }
</style>

<button type="button" class="summary-sidebar-toggle" id="summarySidebarToggle" aria-label="Mở menu">&#9776;</button>

<aside class="summary-sidebar" id="summarySidebar">
    <a class="summary-sidebar__brand" href="{{ url('/client/kho-noi-bo') }}">
        <span class="summary-sidebar__mark"><i data-lucide="warehouse"></i></span>
        <span>
            <span class="summary-sidebar__brand-title">Quản lý Kho</span>
            <span class="summary-sidebar__brand-subtitle d-block">TTV May Mặc</span>
        </span>
    </a>

    <nav class="summary-sidebar__nav" aria-label="Điều hướng chính">
        <div class="summary-sidebar__label">Nghiệp vụ</div>

        <details class="summary-sidebar__group summary-sidebar__group--section" data-sidebar-section {{ request()->is('client/lenh-san-xuat-trung-tam*', 'client/lenh-san-xuat-sheet*', 'client/lenh-btp*', 'client/theo-doi-san-xuat*', 'client/quan-ly-det*', 'client/home', 'client/view-all-sx-data') ? 'open' : '' }}>
            <summary class="summary-sidebar__summary"><i data-lucide="factory"></i>Quản lý sản xuất</summary>
            <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('client/lenh-san-xuat-trung-tam*') ? 'is-active' : '' }}" href="{{ url('/client/lenh-san-xuat-trung-tam') }}"><i data-lucide="route"></i>Lệnh SX trung tâm</a>
            <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('client/lenh-san-xuat-sheet*') ? 'is-active' : '' }}" href="{{ url('/client/lenh-san-xuat-sheet') }}"><i data-lucide="refresh-cw"></i>Lệnh SX đồng bộ</a>
            <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('client/lenh-btp*') ? 'is-active' : '' }}" href="{{ url('/client/lenh-btp') }}"><i data-lucide="git-branch-plus"></i>Lệnh BTP</a>
            <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('client/theo-doi-san-xuat*') ? 'is-active' : '' }}" href="{{ url('/client/theo-doi-san-xuat') }}"><i data-lucide="workflow"></i>Đang sản xuất</a>
            <details class="summary-sidebar__group summary-sidebar__subgroup" {{ request()->is('client/quan-ly-det*') ? 'open' : '' }}>
                <summary class="summary-sidebar__summary"><i data-lucide="swatch-book"></i>Quản lý dệt</summary>
                <a class="summary-sidebar__link summary-sidebar__child summary-sidebar__deep-child {{ request()->routeIs('weaving.dashboard') ? 'is-active' : '' }}" href="{{ route('weaving.dashboard') }}">Quản lý dệt nhanh</a>
                <a class="summary-sidebar__link summary-sidebar__child summary-sidebar__deep-child {{ request()->routeIs('weaving.tracking') ? 'is-active' : '' }}" href="{{ route('weaving.tracking') }}">Theo dõi chi tiết</a>
            </details>
            <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('client/home') ? 'is-active' : '' }}" href="{{ url('/client/home') }}"><i data-lucide="list-tree"></i>Tổng quan lệnh</a>
            <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('client/view-all-sx-data') ? 'is-active' : '' }}" href="{{ url('/client/view-all-sx-data') }}"><i data-lucide="chart-no-axes-combined"></i>Tổng hợp sản xuất</a>
        </details>

        <details class="summary-sidebar__group summary-sidebar__group--section" data-sidebar-section {{ request()->is('client/don-hang-noi-bo*', 'orders') ? 'open' : '' }}>
            <summary class="summary-sidebar__summary"><i data-lucide="clipboard-list"></i>Đơn hàng</summary>
            <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('client/don-hang-noi-bo*') ? 'is-active' : '' }}" href="{{ url('/client/don-hang-noi-bo') }}"><i data-lucide="table-2"></i>Đơn hàng A/B</a>
            <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('orders') ? 'is-active' : '' }}" href="{{ url('/orders') }}"><i data-lucide="building-2"></i>Đơn hàng công ty</a>
        </details>

        <details class="summary-sidebar__group summary-sidebar__group--section" data-sidebar-section {{ request()->is('client/danh-muc-noi-bo*', 'client/mau-noi-bo*', 'client/quy-doi-don-vi*') ? 'open' : '' }}>
            <summary class="summary-sidebar__summary"><i data-lucide="book-open"></i>Danh mục</summary>
            <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('client/danh-muc-noi-bo*') ? 'is-active' : '' }}" href="{{ url('/client/danh-muc-noi-bo') }}"><i data-lucide="barcode"></i>Danh mục nội bộ</a>
            <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('client/mau-noi-bo*') ? 'is-active' : '' }}" href="{{ url('/client/mau-noi-bo') }}"><i data-lucide="palette"></i>Màu nội bộ</a>
            <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('client/quy-doi-don-vi*') ? 'is-active' : '' }}" href="{{ url('/client/quy-doi-don-vi') }}"><i data-lucide="repeat-2"></i>Quy đổi ĐVT</a>
        </details>

        <details class="summary-sidebar__group summary-sidebar__group--section" data-sidebar-section {{ request()->is('client/kho-noi-bo', 'client/ton-kho-noi-bo*', 'client/canh-bao-kho*', 'client/nhap-thanh-pham-nhanh*', 'client/xuat-thanh-pham-nhanh*', 'client/kiem-ton-kho*', 'client/mat-ke-kho*', 'client/xuat-vat-tu-noi-bo*', 'client/xuat-chi-lenh-sx*', 'client/view-nx-data') ? 'open' : '' }}>
            <summary class="summary-sidebar__summary"><i data-lucide="warehouse"></i>Kho</summary>
            <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('client/kho-noi-bo') ? 'is-active' : '' }}" href="{{ url('/client/kho-noi-bo') }}"><i data-lucide="layout-dashboard"></i>Tổng quan kho</a>
            <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('client/nhap-thanh-pham-nhanh*') ? 'is-active' : '' }}" href="{{ url('/client/nhap-thanh-pham-nhanh') }}"><i data-lucide="package-plus"></i>Nhập thành phẩm</a>
            <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('client/xuat-thanh-pham-nhanh*') ? 'is-active' : '' }}" href="{{ url('/client/xuat-thanh-pham-nhanh') }}"><i data-lucide="truck"></i>Xuất thành phẩm</a>
            <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('client/kiem-ton-kho*') ? 'is-active' : '' }}" href="{{ url('/client/kiem-ton-kho') }}"><i data-lucide="map-pinned"></i>Phiếu kho & vị trí</a>
            <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('client/ton-kho-noi-bo*') ? 'is-active' : '' }}" href="{{ url('/client/ton-kho-noi-bo') }}"><i data-lucide="archive"></i>Tồn kho</a>
            <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('client/mat-ke-kho*') ? 'is-active' : '' }}" href="{{ url('/client/mat-ke-kho') }}"><i data-lucide="panel-top"></i>Mặt kệ kho</a>
            <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('client/xuat-vat-tu-noi-bo*') ? 'is-active' : '' }}" href="{{ url('/client/xuat-vat-tu-noi-bo') }}"><i data-lucide="package-minus"></i>Phiếu xuất kho</a>
            <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('client/xuat-chi-lenh-sx*') ? 'is-active' : '' }}" href="{{ url('/client/xuat-chi-lenh-sx') }}"><i data-lucide="file-spreadsheet"></i>Xuất chỉ theo XNT</a>
            <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('client/canh-bao-kho*') ? 'is-active' : '' }}" href="{{ url('/client/canh-bao-kho') }}"><i data-lucide="triangle-alert"></i>Cảnh báo kho</a>
            <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('client/view-nx-data') ? 'is-active' : '' }}" href="{{ url('/client/view-nx-data') }}"><i data-lucide="chart-column"></i>Phân tích nhập xuất</a>
        </details>

        <details class="summary-sidebar__group summary-sidebar__group--section" data-sidebar-section {{ request()->is('client/ketoan*', 'client/phieu-nhap-thanh-pham', 'client/doi-chieu-ton') ? 'open' : '' }}>
            <summary class="summary-sidebar__summary"><i data-lucide="database"></i>TSoft kế toán</summary>
            <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('client/ketoan') ? 'is-active' : '' }}" href="{{ url('/client/ketoan') }}"><i data-lucide="package-minus"></i>Xuất kho TSoft</a>
            <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('client/ketoan-ton') ? 'is-active' : '' }}" href="{{ url('/client/ketoan-ton') }}"><i data-lucide="database-zap"></i>Tồn mã hàng TSoft</a>
            <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('client/phieu-nhap-thanh-pham') ? 'is-active' : '' }}" href="{{ url('/client/phieu-nhap-thanh-pham') }}"><i data-lucide="file-input"></i>Phiếu nhập TSoft</a>
            <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('client/doi-chieu-ton') ? 'is-active' : '' }}" href="{{ url('/client/doi-chieu-ton') }}"><i data-lucide="scale"></i>Đối chiếu tồn</a>
        </details>

        <details class="summary-sidebar__group summary-sidebar__group--section" data-sidebar-section {{ request()->is('client/material-calculator', 'client/fabric-cut-simulator') ? 'open' : '' }}>
            <summary class="summary-sidebar__summary"><i data-lucide="wrench"></i>Công cụ</summary>
            <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('client/material-calculator') ? 'is-active' : '' }}" href="{{ url('/client/material-calculator') }}"><i data-lucide="calculator"></i>Tính cắt vải</a>
            <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('client/fabric-cut-simulator') ? 'is-active' : '' }}" href="{{ url('/client/fabric-cut-simulator') }}"><i data-lucide="scissors"></i>Giả lập sơ đồ cắt</a>
        </details>
    </nav>
</aside>

<button type="button" class="warehouse-assistant-fab" id="warehouseAssistantFab" aria-label="Mo chat kho" title="Hoi kho">
    <i data-lucide="message-circle"></i>
</button>

<section class="warehouse-assistant" id="warehouseAssistant" aria-hidden="true">
    <div class="warehouse-assistant__head">
        <div>
            <p class="warehouse-assistant__title">Assistant kho</p>
            <div class="warehouse-assistant__sub">Hoi ton ma, vi tri, phieu nhap moi</div>
        </div>
        <button type="button" class="warehouse-assistant__close" id="warehouseAssistantClose" aria-label="Dong chat">
            <i data-lucide="x"></i>
        </button>
    </div>
    <div class="warehouse-assistant__body" id="warehouseAssistantBody">
        <div class="warehouse-assistant__msg">Nhap cau hoi ngan, vi du: "ma TT01 nam dau" hoac "ke A1 co gi".</div>
    </div>
    <div class="warehouse-assistant__quick">
        <button type="button" class="warehouse-assistant__chip" data-question="ma TT01 nam dau">ma TT01 nam dau</button>
        <button type="button" class="warehouse-assistant__chip" data-question="ke A1 co gi">ke A1 co gi</button>
        <button type="button" class="warehouse-assistant__chip" data-question="phieu nhap moi nhat">phieu nhap moi</button>
    </div>
    <form class="warehouse-assistant__form" id="warehouseAssistantForm">
        <input class="warehouse-assistant__input" id="warehouseAssistantInput" autocomplete="off" placeholder="Hoi ton kho..." />
        <button type="submit" class="warehouse-assistant__send" id="warehouseAssistantSend" aria-label="Gui cau hoi">
            <i data-lucide="send"></i>
        </button>
    </form>
</section>

<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const sidebar = document.getElementById('summarySidebar');
        const toggle = document.getElementById('summarySidebarToggle');
        if (toggle && sidebar) {
            toggle.addEventListener('click', function () {
                sidebar.classList.toggle('is-open');
            });

            const sections = Array.from(sidebar.querySelectorAll('[data-sidebar-section]'));
            sections.forEach(function (section) {
                section.addEventListener('toggle', function () {
                    if (!section.open) return;
                    sections.forEach(function (otherSection) {
                        if (otherSection !== section) otherSection.open = false;
                    });
                });
            });

            sidebar.querySelectorAll('a.summary-sidebar__link').forEach(function (link) {
                link.addEventListener('click', function () {
                    if (window.innerWidth < 992) sidebar.classList.remove('is-open');
                });
            });
        }
        const assistant = document.getElementById('warehouseAssistant');
        const assistantFab = document.getElementById('warehouseAssistantFab');
        const assistantClose = document.getElementById('warehouseAssistantClose');
        const assistantBody = document.getElementById('warehouseAssistantBody');
        const assistantForm = document.getElementById('warehouseAssistantForm');
        const assistantInput = document.getElementById('warehouseAssistantInput');
        const assistantSend = document.getElementById('warehouseAssistantSend');

        const setAssistantOpen = function (isOpen) {
            if (!assistant) return;
            assistant.classList.toggle('is-open', isOpen);
            assistant.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
            if (isOpen && assistantInput) setTimeout(function () { assistantInput.focus(); }, 30);
        };

        const addAssistantMessage = function (text, isUser) {
            if (!assistantBody) return;
            const bubble = document.createElement('div');
            bubble.className = 'warehouse-assistant__msg' + (isUser ? ' warehouse-assistant__msg--user' : '');
            bubble.textContent = text;
            assistantBody.appendChild(bubble);
            assistantBody.scrollTop = assistantBody.scrollHeight;
            return bubble;
        };

        const askWarehouseAssistant = async function (question) {
            const text = (question || '').trim();
            if (!text || !assistantInput || !assistantSend) return;
            setAssistantOpen(true);
            assistantInput.value = '';
            assistantSend.disabled = true;
            addAssistantMessage(text, true);
            const pending = addAssistantMessage('Dang kiem tra...', false);

            try {
                const response = await fetch('/api/assistant/chat?limit=3', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ message: text })
                });
                const payload = await response.json();
                pending.textContent = payload.answer || payload.message || 'Khong co ket qua phu hop.';
            } catch (error) {
                pending.textContent = 'Khong goi duoc API localhost. Kiem tra server port 8889.';
            } finally {
                assistantSend.disabled = false;
                assistantInput.focus();
            }
        };

        if (assistantFab) assistantFab.addEventListener('click', function () {
            setAssistantOpen(!assistant.classList.contains('is-open'));
        });
        if (assistantClose) assistantClose.addEventListener('click', function () { setAssistantOpen(false); });
        if (assistantForm) assistantForm.addEventListener('submit', function (event) {
            event.preventDefault();
            askWarehouseAssistant(assistantInput ? assistantInput.value : '');
        });
        document.querySelectorAll('[data-question]').forEach(function (button) {
            button.addEventListener('click', function () {
                askWarehouseAssistant(button.getAttribute('data-question') || '');
            });
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') setAssistantOpen(false);
        });
        if (window.lucide) window.lucide.createIcons();
    });
</script>
