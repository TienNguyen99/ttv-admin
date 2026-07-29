<style>
    .catalog-image-trigger {
        display:inline-flex;
        align-items:center;
        gap:5px;
        min-height:30px;
        padding:4px 8px;
        border:1px dashed #8eb7e8;
        border-radius:7px;
        background:#f5f9ff;
        color:#1d5ea8;
        font-size:11px;
        font-weight:800;
        cursor:pointer;
    }
    .catalog-image-trigger:hover,
    .catalog-image-trigger:focus-visible { border-style:solid; border-color:#2563eb; background:#eaf3ff; outline:0; }
    .catalog-image-trigger img { width:30px; height:30px; border-radius:5px; object-fit:cover; }
    .catalog-image-paste-zone {
        min-height:220px;
        display:grid;
        place-items:center;
        padding:20px;
        border:2px dashed #93b9e8;
        border-radius:8px;
        background:#f7fbff;
        color:#45698f;
        text-align:center;
        cursor:pointer;
        transition:border-color .2s ease,background-color .2s ease;
    }
    .catalog-image-paste-zone:hover,
    .catalog-image-paste-zone.is-active { border-color:#2563eb; background:#edf5ff; }
    .catalog-image-paste-zone.is-uploading { pointer-events:none; opacity:.7; }
    .catalog-image-paste-zone img { max-width:100%; max-height:300px; object-fit:contain; }
    .catalog-image-paste-zone svg { width:34px; height:34px; margin-bottom:8px; color:#2563eb; }
    .catalog-image-paste-status { min-height:22px; margin-top:10px; font-size:12px; font-weight:700; }
</style>

<div class="modal fade" id="catalogImagePasteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title fs-5 mb-0">Ảnh danh mục</h2>
                    <div id="catalogImagePasteMeta" class="small text-secondary"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <input id="catalogImagePasteInput" type="file" accept="image/*" hidden>
                <button id="catalogImagePasteZone" class="catalog-image-paste-zone w-100" type="button">
                    <span>
                        <i data-lucide="image-plus"></i>
                        <strong class="d-block">Paste ảnh, kéo thả hoặc bấm để chọn</strong>
                        <small>JPG, PNG, WebP hoặc GIF, tối đa 8 MB</small>
                    </span>
                </button>
                <div id="catalogImagePasteStatus" class="catalog-image-paste-status"></div>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const modalElement = document.getElementById('catalogImagePasteModal');
    if (!modalElement || !window.bootstrap) return;

    const modal = new bootstrap.Modal(modalElement);
    const zone = document.getElementById('catalogImagePasteZone');
    const input = document.getElementById('catalogImagePasteInput');
    const meta = document.getElementById('catalogImagePasteMeta');
    const status = document.getElementById('catalogImagePasteStatus');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let current = null;

    const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({
        '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;'
    }[char]));
    const safeImageUrl = value => {
        const url = String(value || '').trim();
        return /^(https?:)?\/\//i.test(url) || url.startsWith('/') ? url : '';
    };
    const emptyZone = () => {
        zone.innerHTML = '<span><i data-lucide="image-plus"></i><strong class="d-block">Paste ảnh, kéo thả hoặc bấm để chọn</strong><small>JPG, PNG, WebP hoặc GIF, tối đa 8 MB</small></span>';
        if (window.lucide) lucide.createIcons();
    };
    const renderZone = imageUrl => {
        const url = safeImageUrl(imageUrl);
        if (!url) {
            emptyZone();
            return;
        }
        zone.innerHTML = `<img src="${esc(url)}" alt="${esc(current?.itemCode || 'Ảnh danh mục')}">`;
    };
    const setStatus = (message, type = '') => {
        status.textContent = message;
        status.className = `catalog-image-paste-status ${type ? `text-${type}` : ''}`;
    };

    async function upload(file) {
        if (!current?.catalogId || !file) return;
        if (!String(file.type || '').startsWith('image/')) {
            setStatus('File đã chọn không phải là ảnh.', 'danger');
            return;
        }

        const body = new FormData();
        body.append('image', file);
        zone.classList.add('is-uploading');
        setStatus('Đang tải ảnh lên Cloudinary...', 'primary');

        try {
            const response = await fetch(`/api/danh-muc-noi-bo/${encodeURIComponent(current.catalogId)}/anh`, {
                method: 'POST',
                headers: {'Accept':'application/json', 'X-CSRF-TOKEN':csrf},
                body,
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.message || 'Không lưu được ảnh danh mục.');

            const data = result.data || {};
            renderZone(data.image_url || '');
            setStatus('Đã lưu ảnh vào Danh mục nội bộ.', 'success');
            document.dispatchEvent(new CustomEvent('catalog-image-uploaded', {detail:data}));
        } catch (error) {
            setStatus(error.message, 'danger');
        } finally {
            zone.classList.remove('is-uploading');
            input.value = '';
        }
    }

    async function ensureCatalog() {
        if (current?.catalogId || !current?.itemCode) return;
        zone.classList.add('is-uploading');
        setStatus('Đang đồng bộ mã với Google Sheet DANH MỤC...', 'primary');
        try {
            const response = await fetch('/api/danh-muc-noi-bo/tao-tu-lenh', {
                method: 'POST',
                headers: {
                    'Accept':'application/json',
                    'Content-Type':'application/json',
                    'X-CSRF-TOKEN':csrf,
                },
                body: JSON.stringify({
                    item_code: current.itemCode,
                    item_name: current.itemName,
                    unit: current.unit,
                    size: current.size,
                    color: current.color,
                }),
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.message || 'Không đồng bộ được mã với Google Sheet DANH MỤC.');

            current.catalogId = String(result.data?.id || '');
            renderZone(result.data?.image_url || '');
            setStatus('Đã sẵn sàng. Paste hoặc chọn ảnh để lưu.', 'success');
            document.dispatchEvent(new CustomEvent('catalog-image-ready', {detail:result.data || {}}));
        } catch (error) {
            setStatus(error.message, 'danger');
        } finally {
            zone.classList.remove('is-uploading');
        }
    }

    zone.addEventListener('click', () => input.click());
    input.addEventListener('change', event => upload(event.target.files?.[0]));
    zone.addEventListener('dragover', event => {
        event.preventDefault();
        zone.classList.add('is-active');
    });
    zone.addEventListener('dragleave', () => zone.classList.remove('is-active'));
    zone.addEventListener('drop', event => {
        event.preventDefault();
        zone.classList.remove('is-active');
        const file = Array.from(event.dataTransfer?.files || [])
            .find(item => String(item.type || '').startsWith('image/'));
        upload(file);
    });
    document.addEventListener('paste', event => {
        if (!modalElement.classList.contains('show')) return;
        const file = Array.from(event.clipboardData?.items || [])
            .find(item => String(item.type || '').startsWith('image/'))
            ?.getAsFile();
        if (file) {
            event.preventDefault();
            upload(file);
        }
    });

    window.CatalogImagePaste = {
        open(options = {}) {
            if (!options.catalogId && !options.itemCode) return false;
            current = {
                catalogId: String(options.catalogId || ''),
                itemCode: String(options.itemCode || ''),
                itemName: String(options.itemName || ''),
                unit: String(options.unit || ''),
                size: String(options.size || ''),
                color: String(options.color || ''),
            };
            meta.textContent = current.itemCode;
            setStatus('');
            renderZone(options.imageUrl || '');
            modal.show();
            ensureCatalog();
            return true;
        },
    };
})();
</script>
