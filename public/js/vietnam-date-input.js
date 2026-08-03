(function () {
    'use strict';

    const TIME_ZONE = 'Asia/Ho_Chi_Minh';
    const nativeValue = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');

    function dateParts(date) {
        const parts = new Intl.DateTimeFormat('en-GB', {
            timeZone: TIME_ZONE,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        }).formatToParts(date || new Date());
        return Object.fromEntries(parts.map(part => [part.type, part.value]));
    }

    function todayIso() {
        const parts = dateParts(new Date());
        return `${parts.year}-${parts.month}-${parts.day}`;
    }

    function isValidDate(year, month, day) {
        const date = new Date(Date.UTC(year, month - 1, day));
        return date.getUTCFullYear() === year
            && date.getUTCMonth() === month - 1
            && date.getUTCDate() === day;
    }

    function displayToIso(value) {
        const match = String(value || '').trim().match(/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})$/);
        if (!match) return '';

        const day = Number(match[1]);
        const month = Number(match[2]);
        const year = Number(match[3]);
        if (!isValidDate(year, month, day)) return '';

        return `${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    }

    function isoToDisplay(value) {
        const match = String(value || '').trim().match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!match || !isValidDate(Number(match[1]), Number(match[2]), Number(match[3]))) return '';
        return `${match[3]}/${match[2]}/${match[1]}`;
    }

    function displayToMonthIso(value) {
        const match = String(value || '').trim().match(/^(\d{1,2})[\/.\-](\d{4})$/);
        if (!match) return '';
        const month = Number(match[1]);
        const year = Number(match[2]);
        if (month < 1 || month > 12 || year < 1000) return '';
        return `${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}`;
    }

    function monthIsoToDisplay(value) {
        const match = String(value || '').trim().match(/^(\d{4})-(\d{2})$/);
        if (!match || Number(match[2]) < 1 || Number(match[2]) > 12) return '';
        return `${match[2]}/${match[1]}`;
    }

    function format(value, options) {
        if (!value) return '';
        const settings = options || {};
        if (!settings.time && /^\d{4}-\d{2}-\d{2}$/.test(String(value))) return isoToDisplay(value);

        const date = value instanceof Date ? value : new Date(value);
        if (Number.isNaN(date.getTime())) return '';
        return new Intl.DateTimeFormat('vi-VN', {
            timeZone: TIME_ZONE,
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            ...(settings.time ? { hour: '2-digit', minute: '2-digit', hour12: false } : {})
        }).format(date);
    }

    function installStyles() {
        if (document.getElementById('vietnam-date-input-styles')) return;
        const style = document.createElement('style');
        style.id = 'vietnam-date-input-styles';
        style.textContent = `
            .vi-date-field { position: relative; display: flex; width: 100%; min-width: 0; }
            .vi-date-field .vi-date-display { width: 100%; min-width: 0; padding-right: 2.75rem !important; }
            .vi-date-field .vi-date-display.is-invalid {
                border-color: #dc3545 !important;
                box-shadow: 0 0 0 .2rem rgba(220, 53, 69, .12) !important;
            }
            .vi-date-field .vi-date-native {
                position: absolute !important; z-index: 3 !important;
                top: 0 !important; right: 0 !important; bottom: 0 !important; left: auto !important;
                display: block !important; width: 2.75rem !important; min-width: 2.75rem !important;
                height: 100% !important; margin: 0 !important; padding: 0 !important;
                border: 0 !important; opacity: 0 !important; cursor: pointer !important;
            }
            .vi-date-field .vi-date-native:disabled { cursor: not-allowed !important; pointer-events: none !important; }
            .vi-date-field .vi-date-icon {
                position: absolute; z-index: 2; top: 50%; right: .82rem;
                width: 1rem; height: 1rem; color: #4f6f9f;
                pointer-events: none; transform: translateY(-50%);
            }
            .vi-date-field:focus-within .vi-date-icon { color: #2563eb; }
        `;
        document.head.appendChild(style);
    }

    function enhance(input) {
        if (!(input instanceof HTMLInputElement)
            || !['date', 'month'].includes(input.type)
            || input.dataset.viDateReady === '1') return;
        input.dataset.viDateReady = '1';
        const isMonth = input.type === 'month';
        const parseDisplay = isMonth ? displayToMonthIso : displayToIso;
        const formatDisplay = isMonth ? monthIsoToDisplay : isoToDisplay;

        const wrapper = document.createElement('span');
        wrapper.className = 'vi-date-field';
        if (input.style.width) wrapper.style.width = input.style.width;
        if (input.style.minWidth) wrapper.style.minWidth = input.style.minWidth;
        if (input.style.maxWidth) wrapper.style.maxWidth = input.style.maxWidth;
        const display = document.createElement('input');
        display.type = 'text';
        display.inputMode = 'numeric';
        display.autocomplete = 'off';
        display.placeholder = isMonth ? 'mm/yyyy' : 'dd/mm/yyyy';
        display.className = `${input.className || ''} vi-date-display`.trim();
        display.disabled = input.disabled;
        display.readOnly = input.readOnly;
        display.required = input.required;
        display.setAttribute('aria-label', input.getAttribute('aria-label') || (isMonth
            ? 'Tháng, định dạng tháng/năm'
            : 'Ngày, định dạng ngày/tháng/năm'));

        const icon = document.createElement('span');
        icon.className = 'vi-date-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>';

        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(display);
        wrapper.appendChild(input);
        wrapper.appendChild(icon);
        input.classList.add('vi-date-native');

        const syncDisplay = () => {
            display.value = formatDisplay(nativeValue.get.call(input));
            display.classList.remove('is-invalid');
            display.setCustomValidity('');
            display.disabled = input.disabled;
            display.readOnly = input.readOnly;
        };

        const commitDisplay = dispatchEvents => {
            const text = display.value.trim();
            const iso = parseDisplay(text);
            if (text && !iso) {
                display.classList.add('is-invalid');
                display.setCustomValidity(isMonth
                    ? 'Nhập tháng theo định dạng mm/yyyy.'
                    : 'Nhập ngày theo định dạng dd/mm/yyyy.');
                return false;
            }

            display.classList.remove('is-invalid');
            display.setCustomValidity('');
            const changed = nativeValue.get.call(input) !== iso;
            nativeValue.set.call(input, iso);
            if (iso) display.value = formatDisplay(iso);
            if (dispatchEvents && changed) {
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
            return true;
        };

        try {
            Object.defineProperty(input, 'value', {
                configurable: true,
                get: () => nativeValue.get.call(input),
                set: value => {
                    nativeValue.set.call(input, value || '');
                    syncDisplay();
                }
            });
        } catch (error) {
            // Event listeners still synchronize the fields in older browsers.
        }

        input._viDateCommit = commitDisplay;
        input.addEventListener('input', syncDisplay);
        input.addEventListener('change', syncDisplay);
        display.addEventListener('input', () => {
            const text = display.value.trim();
            if (!text || parseDisplay(text)) commitDisplay(true);
        });
        display.addEventListener('change', () => commitDisplay(true));
        display.addEventListener('blur', () => commitDisplay(true));
        display.addEventListener('keydown', event => {
            if (event.key === 'Enter' && !commitDisplay(true)) {
                event.preventDefault();
                display.reportValidity();
            }
        });
        syncDisplay();
    }

    function enhanceAll(root) {
        const scope = root && root.querySelectorAll ? root : document;
        scope.querySelectorAll('input[type="date"], input[type="month"]').forEach(enhance);
        if (scope instanceof HTMLInputElement && ['date', 'month'].includes(scope.type)) enhance(scope);
    }

    function syncAll() {
        let valid = true;
        document.querySelectorAll('input[data-vi-date-ready="1"]').forEach(input => {
            if (typeof input._viDateCommit === 'function' && !input._viDateCommit(false)) valid = false;
        });
        return valid;
    }

    window.VietnamDate = Object.freeze({
        timeZone: TIME_ZONE,
        todayIso,
        toIso: displayToIso,
        toDisplay: isoToDisplay,
        toMonthIso: displayToMonthIso,
        toMonthDisplay: monthIsoToDisplay,
        format,
        enhanceAll,
        syncAll
    });

    function initialize() {
        installStyles();
        enhanceAll(document);
        document.addEventListener('submit', event => {
            if (!syncAll()) {
                event.preventDefault();
                document.querySelector('.vi-date-display.is-invalid')?.reportValidity();
            }
        }, true);

        new MutationObserver(mutations => {
            mutations.forEach(mutation => mutation.addedNodes.forEach(node => {
                if (node.nodeType === Node.ELEMENT_NODE) enhanceAll(node);
            }));
        }).observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
})();
