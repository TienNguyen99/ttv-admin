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
    .warehouse-assistant-fab {
        position: fixed;
        right: 18px;
        bottom: 18px;
        z-index: 1060;
        display: grid;
        width: 44px;
        height: 44px;
        place-items: center;
        border: 1px solid #0b5cad;
        border-radius: 8px;
        background: #0b5cad;
        color: #fff;
        box-shadow: 0 12px 30px rgba(6, 43, 85, .22);
        cursor: pointer;
    }
    .warehouse-assistant-fab svg { width: 20px; height: 20px; }
    .warehouse-assistant {
        position: fixed;
        right: 18px;
        bottom: 72px;
        z-index: 1060;
        display: none;
        width: min(380px, calc(100vw - 32px));
        overflow: hidden;
        border: 1px solid #c8d4e3;
        border-radius: 8px;
        background: #fff;
        color: #102033;
        box-shadow: 0 18px 45px rgba(6, 43, 85, .24);
    }
    .warehouse-assistant.is-open { display: block; }
    .warehouse-assistant__head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 12px;
        border-bottom: 1px solid #dbe3ee;
        background: #f5f8fc;
    }
    .warehouse-assistant__title { margin: 0; font-size: 13px; font-weight: 800; color: #062b55; }
    .warehouse-assistant__sub { margin-top: 2px; color: #64748b; font-size: 11px; }
    .warehouse-assistant__close {
        display: grid;
        width: 30px;
        height: 30px;
        place-items: center;
        border: 1px solid #c8d4e3;
        border-radius: 6px;
        background: #fff;
        color: #334155;
        cursor: pointer;
    }
    .warehouse-assistant__body {
        display: flex;
        flex-direction: column;
        gap: 8px;
        height: 290px;
        padding: 12px;
        overflow-y: auto;
        background: #f8fafc;
    }
    .warehouse-assistant__msg {
        max-width: 88%;
        padding: 8px 10px;
        border: 1px solid #dbe3ee;
        border-radius: 8px;
        background: #fff;
        color: #172033;
        font-size: 13px;
        line-height: 1.35;
        white-space: pre-wrap;
        word-break: break-word;
    }
    .warehouse-assistant__msg--user {
        align-self: flex-end;
        border-color: #0b5cad;
        background: #0b5cad;
        color: #fff;
    }
    .warehouse-assistant__quick {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        padding: 0 12px 10px;
        background: #f8fafc;
    }
    .warehouse-assistant__chip {
        border: 1px solid #c8d4e3;
        border-radius: 999px;
        background: #fff;
        color: #0b5cad;
        font-size: 12px;
        padding: 5px 9px;
        cursor: pointer;
    }
    .warehouse-assistant__form {
        display: flex;
        gap: 8px;
        padding: 10px;
        border-top: 1px solid #dbe3ee;
        background: #fff;
    }
    .warehouse-assistant__input {
        min-width: 0;
        flex: 1;
        height: 36px;
        border: 1px solid #c8d4e3;
        border-radius: 6px;
        padding: 0 10px;
        color: #102033;
        font-size: 13px;
    }
    .warehouse-assistant__send {
        display: grid;
        width: 38px;
        height: 36px;
        place-items: center;
        border: 1px solid #0b5cad;
        border-radius: 6px;
        background: #0b5cad;
        color: #fff;
        cursor: pointer;
    }
    .warehouse-assistant__send:disabled { opacity: .65; cursor: wait; }
    @media (max-width: 575.98px) {
        .warehouse-assistant {
            right: 10px;
            bottom: 64px;
            width: calc(100vw - 20px);
        }
        .warehouse-assistant-fab { right: 10px; bottom: 10px; }
    }
    @media print {
        body { padding-left: 0 !important; }
        .summary-sidebar, .summary-sidebar-toggle, .warehouse-assistant, .warehouse-assistant-fab { display: none !important; }
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
    <a class="summary-sidebar__link {{ request()->is('client/don-hang-noi-bo*') ? 'is-active' : '' }}" href="{{ url('/client/don-hang-noi-bo') }}"><i data-lucide="clipboard-list"></i>Đơn hàng A/B</a>
    <a class="summary-sidebar__link {{ request()->is('client/lenh-san-xuat-sheet*') ? 'is-active' : '' }}" href="{{ url('/client/lenh-san-xuat-sheet') }}"><i data-lucide="factory"></i>Lệnh sản xuất</a>
    <a class="summary-sidebar__link {{ request()->is('client/danh-muc-noi-bo*') ? 'is-active' : '' }}" href="{{ url('/client/danh-muc-noi-bo') }}"><i data-lucide="book-open"></i>Danh mục nội bộ</a>
    <a class="summary-sidebar__link {{ request()->is('client/theo-doi-san-xuat*') ? 'is-active' : '' }}" href="{{ url('/client/theo-doi-san-xuat') }}"><i data-lucide="workflow"></i>Đang sản xuất</a>
    <a class="summary-sidebar__link {{ request()->is('client/ton-kho-noi-bo*') ? 'is-active' : '' }}" href="{{ url('/client/ton-kho-noi-bo') }}"><i data-lucide="archive"></i>Tồn kho</a>
    <a class="summary-sidebar__link {{ request()->is('client/canh-bao-kho*') ? 'is-active' : '' }}" href="{{ url('/client/canh-bao-kho') }}"><i data-lucide="triangle-alert"></i>Cảnh báo kho</a>
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
