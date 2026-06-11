<style>
    :root { --summary-sidebar-width: 204px; }
    body { padding-left: var(--summary-sidebar-width); }
    .summary-sidebar {
        position: fixed;
        inset: 0 auto 0 0;
        z-index: 1040;
        display: flex;
        flex-direction: column;
        width: var(--summary-sidebar-width);
        overflow-y: auto;
        background: #062b55;
        color: #d7e4f3;
    }
    .summary-sidebar__brand {
        display: flex;
        align-items: center;
        gap: 11px;
        min-height: 74px;
        padding: 14px 16px;
        border-bottom: 1px solid rgba(255,255,255,.12);
        color: #fff;
        text-decoration: none;
    }
    .summary-sidebar__mark {
        display: grid;
        width: 38px;
        height: 38px;
        flex: 0 0 38px;
        place-items: center;
        border: 1px solid #77b7f7;
        background: #0c5fa8;
    }
    .summary-sidebar__mark svg { width: 21px; height: 21px; }
    .summary-sidebar__brand-title { font-size: 16px; font-weight: 800; line-height: 1.15; }
    .summary-sidebar__brand-subtitle { margin-top: 3px; color: #9eb5cf; font-size: 10px; font-weight: 700; text-transform: uppercase; }
    .summary-sidebar__label {
        padding: 17px 14px 7px;
        color: #7994b2;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
    }
    .summary-sidebar__link,
    .summary-sidebar__summary {
        display: flex;
        align-items: center;
        gap: 10px;
        min-height: 42px;
        margin: 2px 10px;
        padding: 8px 11px;
        border-left: 3px solid transparent;
        border-radius: 4px;
        color: #b9cbe0;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        list-style: none;
    }
    .summary-sidebar__link svg,
    .summary-sidebar__summary svg { width: 17px; height: 17px; flex: 0 0 17px; }
    .summary-sidebar__link:hover,
    .summary-sidebar__link.is-active,
    .summary-sidebar__summary:hover,
    .summary-sidebar__group[open] > .summary-sidebar__summary {
        border-left-color: #93c5fd;
        background: #75adeda8;
        color: #fff;
    }
    .summary-sidebar__summary::-webkit-details-marker { display: none; }
    .summary-sidebar__summary::after {
        content: "›";
        margin-left: auto;
        color: #9eb5cf;
        font-size: 18px;
        transform: rotate(0);
    }
    .summary-sidebar__group[open] > .summary-sidebar__summary::after { transform: rotate(90deg); }
    .summary-sidebar__child {
        min-height: 36px;
        margin-left: 26px;
        padding-left: 12px;
        font-size: 12px;
    }
    .summary-sidebar__footer { margin-top: auto; padding: 10px 0 14px; border-top: 1px solid rgba(255,255,255,.1); }
    .summary-sidebar-toggle {
        display: none;
        position: fixed;
        top: 10px;
        left: 10px;
        z-index: 1050;
        width: 40px;
        height: 40px;
        border: 1px solid #bdc8d8;
        border-radius: 5px;
        background: #fff;
        color: #062b55;
        font-size: 20px;
    }
    @media (max-width: 991.98px) {
        body { padding-left: 0; }
        .summary-sidebar { transform: translateX(-100%); transition: transform .18s ease; }
        .summary-sidebar.is-open { transform: translateX(0); }
        .summary-sidebar-toggle { display: block; }
    }
    @media (prefers-reduced-motion: reduce) { .summary-sidebar { transition: none; } }
    @media print {
        body { padding-left: 0 !important; }
        .summary-sidebar, .summary-sidebar-toggle { display: none !important; }
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

    <div class="summary-sidebar__label">Kho nội bộ</div>
    <a class="summary-sidebar__link {{ request()->is('client/kho-noi-bo') ? 'is-active' : '' }}" href="{{ url('/client/kho-noi-bo') }}"><i data-lucide="layout-dashboard"></i>Tổng quan kho</a>
    <a class="summary-sidebar__link {{ request()->is('client/ton-kho-noi-bo*') ? 'is-active' : '' }}" href="{{ url('/client/ton-kho-noi-bo') }}"><i data-lucide="archive"></i>Tồn kho</a>
    <a class="summary-sidebar__link {{ request()->is('client/kiem-ton-kho*') ? 'is-active' : '' }}" href="{{ url('/client/kiem-ton-kho') }}"><i data-lucide="map-pinned"></i>Nhập kho & vị trí</a>
    <a class="summary-sidebar__link {{ request()->is('client/xuat-vat-tu-noi-bo*') ? 'is-active' : '' }}" href="{{ url('/client/xuat-vat-tu-noi-bo') }}"><i data-lucide="package-minus"></i>Xuất kho</a>
    <a class="summary-sidebar__link {{ request()->is('client/doi-chieu-ton') ? 'is-active' : '' }}" href="{{ url('/client/doi-chieu-ton') }}"><i data-lucide="scale"></i>Đối chiếu TSoft</a>
    <a class="summary-sidebar__link {{ request()->is('client/material-calculator') ? 'is-active' : '' }}" href="{{ url('/client/material-calculator') }}"><i data-lucide="ruler"></i>Tính cắt vải</a>

    <div class="summary-sidebar__label">Dữ liệu công ty</div>
    <details class="summary-sidebar__group" {{ request()->is('client/home') || request()->is('orders') || request()->is('client/view-*') ? 'open' : '' }}>
        <summary class="summary-sidebar__summary"><i data-lucide="factory"></i>Sản xuất</summary>
        <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('client/home') ? 'is-active' : '' }}" href="{{ url('/client/home') }}">Tổng quan lệnh</a>
        <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('orders') ? 'is-active' : '' }}" href="{{ url('/orders') }}">Đơn hàng</a>
        <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('client/view-all-sx-data') ? 'is-active' : '' }}" href="{{ url('/client/view-all-sx-data') }}">Tổng hợp sản xuất</a>
        <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('client/view-nx-data') ? 'is-active' : '' }}" href="{{ url('/client/view-nx-data') }}">Phân tích nhập xuất</a>
    </details>
    <details class="summary-sidebar__group" {{ request()->is('client/ketoan*') || request()->is('client/phieu-nhap-thanh-pham') ? 'open' : '' }}>
        <summary class="summary-sidebar__summary"><i data-lucide="database"></i>TSoft kế toán</summary>
        <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('client/ketoan') ? 'is-active' : '' }}" href="{{ url('/client/ketoan') }}">Xuất kho TSoft</a>
        <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('client/ketoan-ton') ? 'is-active' : '' }}" href="{{ url('/client/ketoan-ton') }}">Tồn mã hàng TSoft</a>
        <a class="summary-sidebar__link summary-sidebar__child {{ request()->is('client/phieu-nhap-thanh-pham') ? 'is-active' : '' }}" href="{{ url('/client/phieu-nhap-thanh-pham') }}">Phiếu nhập TSoft</a>
    </details>

    <div class="summary-sidebar__footer">
        <a class="summary-sidebar__link" href="{{ url('/client/kiem-ton-kho?view=editor') }}"><i data-lucide="grid-3x3"></i>Thiết lập sơ đồ</a>
    </div>
</aside>

<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const sidebar = document.getElementById('summarySidebar');
        const toggle = document.getElementById('summarySidebarToggle');
        if (toggle && sidebar) {
            toggle.addEventListener('click', function () {
                sidebar.classList.toggle('is-open');
            });
        }
        if (window.lucide) window.lucide.createIcons();
    });
</script>
