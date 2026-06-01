<style>
    :root {
        --summary-sidebar-width: 224px;
    }

    body {
        padding-left: var(--summary-sidebar-width);
    }

    .summary-sidebar {
        position: fixed;
        inset: 0 auto 0 0;
        z-index: 1040;
        width: var(--summary-sidebar-width);
        overflow-y: auto;
        background: #1f2937;
        color: #e5e7eb;
    }

    .summary-sidebar__brand {
        display: block;
        padding: 18px 16px 12px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        color: #fff;
        font-size: 15px;
        font-weight: 700;
        text-decoration: none;
    }

    .summary-sidebar__label {
        padding: 14px 16px 6px;
        color: #9ca3af;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .summary-sidebar__link {
        display: block;
        padding: 9px 16px;
        border-left: 3px solid transparent;
        color: #d1d5db;
        font-size: 14px;
        text-decoration: none;
    }

    .summary-sidebar__link:hover,
    .summary-sidebar__link.is-active {
        border-left-color: #60a5fa;
        background: rgba(255, 255, 255, 0.08);
        color: #fff;
    }

    .summary-sidebar-toggle {
        display: none;
        position: fixed;
        top: 10px;
        left: 10px;
        z-index: 1050;
        width: 40px;
        height: 40px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        background: #fff;
        color: #111827;
        font-size: 20px;
        line-height: 1;
    }

    @media (max-width: 991.98px) {
        body {
            padding-left: 0;
        }

        .summary-sidebar {
            transform: translateX(-100%);
            transition: transform 0.2s ease;
        }

        .summary-sidebar.is-open {
            transform: translateX(0);
        }

        .summary-sidebar-toggle {
            display: block;
        }
    }

    @media print {
        body {
            padding-left: 0 !important;
        }

        .summary-sidebar,
        .summary-sidebar-toggle {
            display: none !important;
        }
    }
</style>

<button type="button" class="summary-sidebar-toggle" id="summarySidebarToggle" aria-label="Mở menu">&#9776;</button>

<aside class="summary-sidebar" id="summarySidebar">
    <a class="summary-sidebar__brand" href="{{ url('/client/home') }}">TTV Tổng hợp</a>

    <div class="summary-sidebar__label">Sản xuất</div>
    <a class="summary-sidebar__link {{ request()->is('client/home') ? 'is-active' : '' }}" href="{{ url('/client/home') }}">Tổng quan lệnh</a>
    <a class="summary-sidebar__link {{ request()->is('orders') ? 'is-active' : '' }}" href="{{ url('/orders') }}">Đơn hàng</a>
    <a class="summary-sidebar__link {{ request()->is('client/view-all-sx-data') ? 'is-active' : '' }}" href="{{ url('/client/view-all-sx-data') }}">Tổng hợp sản xuất</a>
    <a class="summary-sidebar__link {{ request()->is('client/view-nx-data') ? 'is-active' : '' }}" href="{{ url('/client/view-nx-data') }}">Phân tích NX</a>

    <div class="summary-sidebar__label">Kế toán</div>
    <a class="summary-sidebar__link {{ request()->is('client/ketoan') ? 'is-active' : '' }}" href="{{ url('/client/ketoan') }}">Xuất kho</a>
    <a class="summary-sidebar__link {{ request()->is('client/ketoan-ton') ? 'is-active' : '' }}" href="{{ url('/client/ketoan-ton') }}">Tồn mã hàng</a>
    <a class="summary-sidebar__link {{ request()->is('client/phieu-nhap-thanh-pham') ? 'is-active' : '' }}" href="{{ url('/client/phieu-nhap-thanh-pham') }}">Phiếu nhập TP</a>
    <a class="summary-sidebar__link {{ request()->is('client/doi-chieu-ton') ? 'is-active' : '' }}" href="{{ url('/client/doi-chieu-ton') }}">Đối chiếu tồn</a>
</aside>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('summarySidebar');
        const toggle = document.getElementById('summarySidebarToggle');
        if (!sidebar || !toggle) return;

        toggle.addEventListener('click', function() {
            sidebar.classList.toggle('is-open');
        });
    });
</script>
