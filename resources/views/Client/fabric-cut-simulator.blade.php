<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Giả lập cắt vải</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
    <style>
        :root {
            --cut-blue: #2563eb;
            --cut-blue-dark: #173f8f;
            --cut-blue-soft: #eaf3ff;
            --cut-border: #cbdcf2;
            --cut-text: #102a43;
            --cut-muted: #52677d;
            --cut-danger: #dc3545;
            --cut-cm: 4px;
        }
        * { box-sizing: border-box; }
        body { background: #f3f8ff; color: var(--cut-text); font-family: Inter, system-ui, sans-serif; }
        button, input, select { letter-spacing: 0; }
        .cut-page { min-height: 100vh; padding: 18px; }
        .cut-toolbar, .cut-panel, .cut-stage-shell {
            border: 1px solid var(--cut-border);
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 8px 22px rgba(44, 86, 138, .07);
        }
        .cut-toolbar { display: flex; align-items: center; gap: 12px; padding: 12px 14px; margin-bottom: 12px; }
        .cut-title { margin: 0; font-size: 20px; font-weight: 800; }
        .cut-subtitle { color: var(--cut-muted); font-size: 12px; }
        .cut-toolbar__actions { display: flex; align-items: center; gap: 8px; margin-left: auto; }
        .cut-btn { display: inline-flex; min-height: 38px; align-items: center; justify-content: center; gap: 7px; border-radius: 7px; font-weight: 700; }
        .cut-btn svg { width: 17px; height: 17px; }
        .cut-layout { display: grid; grid-template-columns: 290px minmax(0, 1fr); gap: 12px; min-height: calc(100vh - 102px); }
        .cut-panel { padding: 14px; overflow: auto; }
        .cut-section + .cut-section { margin-top: 18px; padding-top: 16px; border-top: 1px solid #e5edf7; }
        .cut-section-title { margin: 0 0 10px; font-size: 13px; font-weight: 800; }
        .cut-field-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 9px; }
        .cut-field--wide { grid-column: 1 / -1; }
        .cut-field label { display: block; margin-bottom: 4px; color: var(--cut-muted); font-size: 11px; font-weight: 700; }
        .cut-field .form-control, .cut-field .form-select { min-height: 37px; border-color: var(--cut-border); border-radius: 6px; font-size: 13px; }
        .cut-field .form-control:focus, .cut-field .form-select:focus { border-color: #78aaf8; box-shadow: 0 0 0 3px rgba(37, 99, 235, .12); }
        .cut-metrics { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .cut-metric { min-width: 0; padding: 9px; border: 1px solid #dbe7f5; border-radius: 7px; background: #f7fbff; }
        .cut-metric span { display: block; color: var(--cut-muted); font-size: 10px; font-weight: 700; }
        .cut-metric strong { display: block; margin-top: 2px; overflow: hidden; font-size: 17px; text-overflow: ellipsis; white-space: nowrap; }
        .cut-type-list { display: grid; gap: 7px; }
        .cut-type { display: grid; grid-template-columns: 14px 1fr auto; gap: 8px; align-items: center; padding: 8px; border: 1px solid #dbe7f5; border-radius: 7px; cursor: pointer; transition: border-color .18s, background-color .18s; }
        .cut-type:hover, .cut-type.is-active { border-color: #7daaf1; background: #eef6ff; }
        .cut-type__swatch { width: 14px; height: 14px; border-radius: 3px; }
        .cut-type__name { min-width: 0; font-size: 12px; font-weight: 800; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .cut-type__meta { color: var(--cut-muted); font-size: 10px; }
        .cut-stage-shell { min-width: 0; display: flex; flex-direction: column; overflow: hidden; }
        .cut-stage-head { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; min-height: 54px; padding: 9px 12px; border-bottom: 1px solid var(--cut-border); background: #fbfdff; }
        .cut-stage-tools { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
        .cut-stage-tools .btn { min-width: 38px; min-height: 36px; border-radius: 6px; }
        .cut-stage-tools svg { width: 16px; height: 16px; }
        .cut-stage-status { margin-left: auto; color: var(--cut-muted); font-size: 11px; font-weight: 700; }
        .cut-scroll { position: relative; flex: 1; min-height: 520px; overflow: auto; background: #eaf1f9; overscroll-behavior: contain; }
        .cut-workspace { position: relative; width: max-content; min-width: 100%; padding: 58px 32px 32px 58px; }
        .cut-ruler-x { position: absolute; top: 34px; left: 58px; height: 24px; color: #536b85; font-size: 10px; pointer-events: none; }
        .cut-ruler-y { position: absolute; top: 58px; left: 8px; width: 50px; color: #536b85; font-size: 10px; pointer-events: none; }
        .fabric-stage {
            position: relative;
            width: calc(var(--fabric-length) * var(--cut-cm));
            height: calc(var(--fabric-width) * var(--cut-cm));
            min-width: 720px;
            min-height: 240px;
            overflow: hidden;
            border: 2px solid #5f7894;
            background-color: #fff;
            background-image:
                linear-gradient(to right, rgba(37, 99, 235, .17) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(37, 99, 235, .17) 1px, transparent 1px),
                linear-gradient(to right, rgba(15, 66, 128, .34) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(15, 66, 128, .34) 1px, transparent 1px);
            background-size:
                calc(10 * var(--cut-cm)) calc(10 * var(--cut-cm)),
                calc(10 * var(--cut-cm)) calc(10 * var(--cut-cm)),
                calc(60 * var(--cut-cm)) calc(60 * var(--cut-cm)),
                calc(60 * var(--cut-cm)) calc(60 * var(--cut-cm));
            box-shadow: 0 14px 30px rgba(20, 54, 91, .13);
            transform-origin: top left;
            touch-action: none;
        }
        .fabric-stage::before {
            position: absolute; inset: 0; content: ""; pointer-events: none;
            background: repeating-linear-gradient(135deg, rgba(220, 231, 244, .18) 0, rgba(220, 231, 244, .18) 2px, transparent 2px, transparent 9px);
        }
        .cut-piece {
            position: absolute;
            display: grid;
            place-items: center;
            border: 1px solid color-mix(in srgb, var(--piece-color) 70%, #173f8f);
            border-radius: 3px;
            background: color-mix(in srgb, var(--piece-color) 38%, white);
            color: #0d2f57;
            cursor: grab;
            user-select: none;
            touch-action: none;
            transition: box-shadow .12s, opacity .12s;
        }
        .cut-piece:active { cursor: grabbing; }
        .cut-piece.is-selected { z-index: 3; box-shadow: 0 0 0 2px #fff, 0 0 0 4px var(--cut-blue); }
        .cut-piece.is-overlap { border-color: var(--cut-danger); background: #ffd9de; }
        .cut-piece__label { max-width: 95%; overflow: hidden; font-size: 10px; font-weight: 800; text-overflow: ellipsis; white-space: nowrap; pointer-events: none; }
        .cut-empty { position: absolute; inset: 0; display: grid; place-items: center; color: #698199; font-size: 13px; pointer-events: none; }
        .cut-key-help { color: var(--cut-muted); font-size: 10px; line-height: 1.5; }
        .cut-toast { position: fixed; right: 20px; bottom: 20px; z-index: 1080; max-width: 340px; padding: 11px 14px; border-radius: 7px; background: #173f8f; color: #fff; box-shadow: 0 16px 32px rgba(15, 45, 82, .22); opacity: 0; transform: translateY(12px); pointer-events: none; transition: opacity .2s, transform .2s; }
        .cut-toast.is-visible { opacity: 1; transform: translateY(0); }
        @media (max-width: 1000px) {
            .cut-layout { grid-template-columns: 1fr; }
            .cut-panel { max-height: none; }
            .cut-scroll { min-height: 580px; }
        }
        @media (max-width: 640px) {
            .cut-page { padding: 10px; }
            .cut-toolbar { align-items: flex-start; }
            .cut-toolbar__actions { width: 100%; margin-left: 0; }
            .cut-toolbar { flex-wrap: wrap; }
            .cut-field-grid { grid-template-columns: 1fr; }
            .cut-field--wide { grid-column: auto; }
            .cut-stage-status { width: 100%; margin-left: 0; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { scroll-behavior: auto !important; transition: none !important; }
        }
    </style>
</head>
<body>
@include('layouts.partials.sidebar')

<main class="cut-page">
    <header class="cut-toolbar">
        <div>
            <h1 class="cut-title">Giả lập sơ đồ cắt vải</h1>
            <div class="cut-subtitle">Kéo thả chi tiết theo đúng tỷ lệ khổ vải. Ô đậm: 60 × 60 cm.</div>
        </div>
        <div class="cut-toolbar__actions">
            <a href="{{ url('/client/material-calculator') }}" class="btn btn-outline-primary cut-btn">
                <i data-lucide="calculator"></i><span>Bảng tính</span>
            </a>
            <button id="saveBtn" type="button" class="btn btn-primary cut-btn">
                <i data-lucide="save"></i><span>Lưu bản nháp</span>
            </button>
        </div>
    </header>

    <div class="cut-layout">
        <aside class="cut-panel">
            <section class="cut-section">
                <h2 class="cut-section-title">Khổ vải</h2>
                <div class="cut-field-grid">
                    <div class="cut-field">
                        <label for="fabricWidth">Khổ rộng (cm)</label>
                        <input id="fabricWidth" type="number" min="1" step="0.1" value="150" class="form-control">
                    </div>
                    <div class="cut-field">
                        <label for="fabricLength">Chiều dài xem (cm)</label>
                        <input id="fabricLength" type="number" min="60" max="10000" step="10" value="300" class="form-control">
                    </div>
                    <div class="cut-field cut-field--wide">
                        <label for="calculatorSource">Nạp từ bảng tính cắt vải</label>
                        <select id="calculatorSource" class="form-select">
                            <option value="">Chọn mã vật tư đã tính...</option>
                        </select>
                    </div>
                </div>
            </section>

            <section class="cut-section">
                <h2 class="cut-section-title">Thêm chi tiết</h2>
                <div class="cut-field-grid">
                    <div class="cut-field cut-field--wide">
                        <label for="pieceName">Tên chi tiết</label>
                        <input id="pieceName" class="form-control" value="Chi tiết A" placeholder="Ví dụ: Thân trước">
                    </div>
                    <div class="cut-field">
                        <label for="pieceLength">Dài (cm)</label>
                        <input id="pieceLength" type="number" min="0.1" step="0.1" value="20" class="form-control">
                    </div>
                    <div class="cut-field">
                        <label for="pieceWidth">Rộng (cm)</label>
                        <input id="pieceWidth" type="number" min="0.1" step="0.1" value="10" class="form-control">
                    </div>
                    <div class="cut-field">
                        <label for="pieceQuantity">Số lượng</label>
                        <input id="pieceQuantity" type="number" min="1" step="1" value="1" class="form-control">
                    </div>
                    <div class="cut-field">
                        <label for="pieceColor">Màu nhận diện</label>
                        <input id="pieceColor" type="color" value="#79aefa" class="form-control form-control-color w-100">
                    </div>
                    <button id="addPiecesBtn" type="button" class="btn btn-primary cut-btn cut-field--wide">
                        <i data-lucide="plus"></i><span>Thêm vào mặt vải</span>
                    </button>
                </div>
            </section>

            <section class="cut-section">
                <h2 class="cut-section-title">Kết quả bố trí</h2>
                <div class="cut-metrics">
                    <div class="cut-metric"><span>Chi tiết</span><strong id="pieceCountMetric">0</strong></div>
                    <div class="cut-metric"><span>Dài đã dùng</span><strong id="usedLengthMetric">0 cm</strong></div>
                    <div class="cut-metric"><span>Hiệu suất</span><strong id="efficiencyMetric">0%</strong></div>
                    <div class="cut-metric"><span>Xung đột</span><strong id="overlapMetric">0</strong></div>
                </div>
            </section>

            <section class="cut-section">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                    <h2 class="cut-section-title mb-0">Danh sách chi tiết</h2>
                    <button id="clearBtn" type="button" class="btn btn-sm btn-outline-danger" title="Xóa toàn bộ">
                        <i data-lucide="trash-2"></i>
                    </button>
                </div>
                <div id="pieceTypeList" class="cut-type-list"></div>
                <div id="pieceTypeEmpty" class="cut-key-help">Chưa có chi tiết nào.</div>
            </section>
        </aside>

        <section class="cut-stage-shell">
            <div class="cut-stage-head">
                <div class="cut-stage-tools">
                    <button id="autoArrangeBtn" type="button" class="btn btn-outline-primary" title="Xếp tự động"><i data-lucide="layout-grid"></i></button>
                    <button id="rotateBtn" type="button" class="btn btn-outline-primary" title="Xoay chi tiết 90 độ"><i data-lucide="rotate-cw"></i></button>
                    <button id="duplicateBtn" type="button" class="btn btn-outline-primary" title="Nhân bản chi tiết"><i data-lucide="copy"></i></button>
                    <button id="deleteBtn" type="button" class="btn btn-outline-danger" title="Xóa chi tiết"><i data-lucide="trash-2"></i></button>
                    <span class="vr mx-1"></span>
                    <button id="zoomOutBtn" type="button" class="btn btn-outline-secondary" title="Thu nhỏ"><i data-lucide="zoom-out"></i></button>
                    <button id="zoomInBtn" type="button" class="btn btn-outline-secondary" title="Phóng to"><i data-lucide="zoom-in"></i></button>
                    <button id="fitBtn" type="button" class="btn btn-outline-secondary" title="Về tỷ lệ mặc định"><i data-lucide="scan"></i></button>
                </div>
                <div id="stageStatus" class="cut-stage-status">Zoom 100% · Chưa có chi tiết</div>
            </div>
            <div id="cutScroll" class="cut-scroll">
                <div id="workspace" class="cut-workspace">
                    <div id="rulerX" class="cut-ruler-x"></div>
                    <div id="rulerY" class="cut-ruler-y"></div>
                    <div id="fabricStage" class="fabric-stage" style="--fabric-width:150; --fabric-length:300;">
                        <div id="emptyState" class="cut-empty">Thêm chi tiết hoặc nạp dữ liệu từ bảng tính để bắt đầu.</div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</main>

<div id="cutToast" class="cut-toast" role="status" aria-live="polite"></div>

<script>
(() => {
    'use strict';

    const STORAGE_KEY = 'ttv.fabricCutSimulator.v1';
    const CALCULATOR_KEY = 'ttv.materialCalculator.fabricCut.v2';
    const PX_PER_CM = 4;
    const palette = ['#79aefa', '#71d6c4', '#f5b96b', '#c7a6f5', '#f28ca5', '#80c783'];
    const state = {
        fabricWidth: 150,
        fabricLength: 300,
        zoom: 1,
        pieces: [],
        selectedId: null,
    };
    const el = id => document.getElementById(id);
    const number = (value, fallback = 0) => Number.isFinite(Number(value)) ? Number(value) : fallback;
    const uid = () => window.crypto?.randomUUID?.() || `piece-${Date.now()}-${Math.random().toString(16).slice(2)}`;
    const format = value => number(value).toLocaleString('vi-VN', { maximumFractionDigits: 2 });
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    let toastTimer;

    function showToast(message) {
        const toast = el('cutToast');
        toast.textContent = message;
        toast.classList.add('is-visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('is-visible'), 2200);
    }

    function saveState(notify = true) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        if (notify) showToast('Đã lưu bản nháp trên trình duyệt.');
    }

    function loadState() {
        try {
            const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
            if (!saved || !Array.isArray(saved.pieces)) return;
            state.fabricWidth = Math.max(number(saved.fabricWidth, 150), 1);
            state.fabricLength = Math.min(Math.max(number(saved.fabricLength, 300), 60), 10000);
            state.zoom = Math.min(Math.max(number(saved.zoom, 1), .5), 1.75);
            state.pieces = saved.pieces.map(piece => ({
                id: piece.id || uid(),
                name: String(piece.name || 'Chi tiết'),
                width: Math.max(number(piece.width, 1), .1),
                height: Math.max(number(piece.height, 1), .1),
                x: Math.max(number(piece.x), 0),
                y: Math.max(number(piece.y), 0),
                color: piece.color || '#79aefa',
            }));
            state.selectedId = saved.selectedId || null;
        } catch (error) {
            console.warn('Không thể đọc bản nháp sơ đồ cắt.', error);
        }
    }

    function loadCalculatorSources() {
        const select = el('calculatorSource');
        try {
            const lines = JSON.parse(localStorage.getItem(CALCULATOR_KEY) || '[]');
            if (!Array.isArray(lines)) return;
            lines.forEach((line, index) => {
                const option = document.createElement('option');
                option.value = String(index);
                option.textContent = `${line.materialCode || `Dòng ${index + 1}`} · ${format(line.pieceLength)} × ${format(line.pieceWidth)} cm`;
                option.dataset.line = JSON.stringify(line);
                select.appendChild(option);
            });
        } catch (error) {
            console.warn('Không thể đọc dữ liệu bảng tính cắt vải.', error);
        }
    }

    function getBounds(piece) {
        return { left: piece.x, top: piece.y, right: piece.x + piece.width, bottom: piece.y + piece.height };
    }

    function intersects(a, b) {
        const x = getBounds(a);
        const y = getBounds(b);
        return x.left < y.right && x.right > y.left && x.top < y.bottom && x.bottom > y.top;
    }

    function getOverlapIds() {
        const ids = new Set();
        for (let i = 0; i < state.pieces.length; i++) {
            for (let j = i + 1; j < state.pieces.length; j++) {
                if (intersects(state.pieces[i], state.pieces[j])) {
                    ids.add(state.pieces[i].id);
                    ids.add(state.pieces[j].id);
                }
            }
        }
        return ids;
    }

    function clampPiece(piece) {
        piece.x = Math.min(Math.max(piece.x, 0), Math.max(state.fabricLength - piece.width, 0));
        piece.y = Math.min(Math.max(piece.y, 0), Math.max(state.fabricWidth - piece.height, 0));
    }

    function nextPosition(width, height) {
        const gap = 1;
        let x = 0;
        let y = 0;
        let rowHeight = 0;
        const ordered = [...state.pieces].sort((a, b) => a.y - b.y || a.x - b.x);
        ordered.forEach(piece => {
            if (x + width > state.fabricLength) {
                x = 0;
                y += rowHeight + gap;
                rowHeight = 0;
            }
            x = Math.max(x, piece.x + piece.width + gap);
            rowHeight = Math.max(rowHeight, piece.height);
        });
        if (x + width > state.fabricLength) {
            x = 0;
            y += rowHeight + gap;
        }
        if (y + height > state.fabricWidth) return { x: 0, y: 0 };
        return { x, y };
    }

    function addPieces(definition, quantity) {
        const width = Math.max(number(definition.width), .1);
        const height = Math.max(number(definition.height), .1);
        if (width > state.fabricLength || height > state.fabricWidth) {
            showToast('Chi tiết lớn hơn vùng vải đang hiển thị.');
            return;
        }
        const total = Math.min(Math.max(Math.floor(number(quantity, 1)), 1), Math.max(1000 - state.pieces.length, 0));
        if (total === 0) {
            showToast('Sơ đồ đã đạt giới hạn 1.000 chi tiết để bảo đảm hiệu năng.');
            return;
        }
        for (let index = 0; index < total; index++) {
            const position = nextPosition(width, height);
            const piece = {
                id: uid(),
                name: definition.name || 'Chi tiết',
                width,
                height,
                x: position.x,
                y: position.y,
                color: definition.color || palette[state.pieces.length % palette.length],
            };
            state.pieces.push(piece);
            state.selectedId = piece.id;
        }
        autoArrange(false);
        render();
        saveState(false);
    }

    function autoArrange(notify = true) {
        const gap = 1;
        let x = 0;
        let y = 0;
        let rowHeight = 0;
        state.pieces.forEach(piece => {
            if (x + piece.width > state.fabricLength) {
                x = 0;
                y += rowHeight + gap;
                rowHeight = 0;
            }
            if (y + piece.height > state.fabricWidth) {
                piece.x = 0;
                piece.y = 0;
            } else {
                piece.x = x;
                piece.y = y;
                x += piece.width + gap;
                rowHeight = Math.max(rowHeight, piece.height);
            }
        });
        if (notify) showToast('Đã xếp lại các chi tiết theo hàng.');
        render();
        saveState(false);
    }

    function renderRulers() {
        const xTicks = [];
        for (let value = 0; value <= state.fabricLength; value += 60) {
            xTicks.push(`<span style="position:absolute;left:${value * PX_PER_CM * state.zoom}px">${value} cm</span>`);
        }
        el('rulerX').style.width = `${state.fabricLength * PX_PER_CM * state.zoom}px`;
        el('rulerX').innerHTML = xTicks.join('');

        const yTicks = [];
        for (let value = 0; value <= state.fabricWidth; value += 60) {
            yTicks.push(`<span style="position:absolute;top:${value * PX_PER_CM * state.zoom}px">${value} cm</span>`);
        }
        el('rulerY').style.height = `${state.fabricWidth * PX_PER_CM * state.zoom}px`;
        el('rulerY').innerHTML = yTicks.join('');
    }

    function renderPieces() {
        const stage = el('fabricStage');
        const overlaps = getOverlapIds();
        stage.querySelectorAll('.cut-piece').forEach(node => node.remove());
        state.pieces.forEach(piece => {
            const node = document.createElement('div');
            node.className = `cut-piece${piece.id === state.selectedId ? ' is-selected' : ''}${overlaps.has(piece.id) ? ' is-overlap' : ''}`;
            node.dataset.id = piece.id;
            node.style.left = `${piece.x * PX_PER_CM}px`;
            node.style.top = `${piece.y * PX_PER_CM}px`;
            node.style.width = `${piece.width * PX_PER_CM}px`;
            node.style.height = `${piece.height * PX_PER_CM}px`;
            node.style.setProperty('--piece-color', piece.color);
            node.title = `${piece.name}\n${format(piece.width)} × ${format(piece.height)} cm\nX: ${format(piece.x)} · Y: ${format(piece.y)}`;
            node.innerHTML = `<span class="cut-piece__label">${escapeHtml(piece.name)}</span>`;
            stage.appendChild(node);
        });
        el('emptyState').hidden = state.pieces.length > 0;
    }

    function renderTypeList() {
        const groups = new Map();
        state.pieces.forEach(piece => {
            const key = `${piece.name}|${piece.width}|${piece.height}|${piece.color}`;
            const current = groups.get(key) || { ...piece, count: 0 };
            current.count++;
            groups.set(key, current);
        });
        el('pieceTypeList').innerHTML = [...groups.values()].map(group => `
            <div class="cut-type${group.id === state.selectedId ? ' is-active' : ''}" data-select-id="${group.id}">
                <span class="cut-type__swatch" style="background:${escapeHtml(group.color)}"></span>
                <div>
                    <div class="cut-type__name">${escapeHtml(group.name)}</div>
                    <div class="cut-type__meta">${format(group.width)} × ${format(group.height)} cm</div>
                </div>
                <strong>${group.count}</strong>
            </div>
        `).join('');
        el('pieceTypeEmpty').hidden = groups.size > 0;
    }

    function renderMetrics() {
        const overlaps = getOverlapIds();
        const usedLength = state.pieces.reduce((max, piece) => Math.max(max, piece.x + piece.width), 0);
        const usedArea = Math.max(usedLength * state.fabricWidth, 0);
        const piecesArea = state.pieces.reduce((sum, piece) => sum + piece.width * piece.height, 0);
        const efficiency = usedArea > 0 ? Math.min((piecesArea / usedArea) * 100, 100) : 0;
        el('pieceCountMetric').textContent = format(state.pieces.length);
        el('usedLengthMetric').textContent = `${format(usedLength)} cm`;
        el('efficiencyMetric').textContent = `${format(efficiency)}%`;
        el('overlapMetric').textContent = format(overlaps.size);
        el('overlapMetric').classList.toggle('text-danger', overlaps.size > 0);
        el('stageStatus').textContent = `Zoom ${Math.round(state.zoom * 100)}% · ${state.pieces.length} chi tiết · dùng ${format(usedLength)} cm`;
    }

    function render() {
        el('fabricWidth').value = state.fabricWidth;
        el('fabricLength').value = state.fabricLength;
        const stage = el('fabricStage');
        stage.style.setProperty('--fabric-width', state.fabricWidth);
        stage.style.setProperty('--fabric-length', state.fabricLength);
        stage.style.transform = `scale(${state.zoom})`;
        el('workspace').style.width = `${Math.max(state.fabricLength * PX_PER_CM * state.zoom + 100, 900)}px`;
        el('workspace').style.height = `${state.fabricWidth * PX_PER_CM * state.zoom + 120}px`;
        renderRulers();
        renderPieces();
        renderTypeList();
        renderMetrics();
        window.lucide?.createIcons();
    }

    function selectedPiece() {
        return state.pieces.find(piece => piece.id === state.selectedId);
    }

    function rotateSelected() {
        const piece = selectedPiece();
        if (!piece) return showToast('Hãy chọn một chi tiết trước.');
        [piece.width, piece.height] = [piece.height, piece.width];
        clampPiece(piece);
        render();
        saveState(false);
    }

    function deleteSelected() {
        if (!state.selectedId) return;
        state.pieces = state.pieces.filter(piece => piece.id !== state.selectedId);
        state.selectedId = state.pieces.at(-1)?.id || null;
        render();
        saveState(false);
    }

    function duplicateSelected() {
        const source = selectedPiece();
        if (!source) return showToast('Hãy chọn một chi tiết trước.');
        const copy = { ...source, id: uid(), name: source.name };
        copy.x = Math.min(source.x + 3, Math.max(state.fabricLength - source.width, 0));
        copy.y = Math.min(source.y + 3, Math.max(state.fabricWidth - source.height, 0));
        state.pieces.push(copy);
        state.selectedId = copy.id;
        render();
        saveState(false);
    }

    function setZoom(next) {
        state.zoom = Math.min(Math.max(next, .5), 1.75);
        render();
        saveState(false);
    }

    el('addPiecesBtn').addEventListener('click', () => {
        addPieces({
            name: el('pieceName').value.trim() || 'Chi tiết',
            width: el('pieceLength').value,
            height: el('pieceWidth').value,
            color: el('pieceColor').value,
        }, el('pieceQuantity').value);
    });

    ['fabricWidth', 'fabricLength'].forEach(id => {
        el(id).addEventListener('change', () => {
            state[id] = Math.max(number(el(id).value, id === 'fabricWidth' ? 150 : 300), id === 'fabricWidth' ? 1 : 60);
            if (id === 'fabricLength') state.fabricLength = Math.min(state.fabricLength, 10000);
            state.pieces.forEach(clampPiece);
            render();
            saveState(false);
        });
    });

    el('calculatorSource').addEventListener('change', event => {
        const option = event.target.selectedOptions[0];
        if (!option?.dataset.line) return;
        const line = JSON.parse(option.dataset.line);
        state.fabricWidth = Math.max(number(line.fabricWidth, 150), 1);
        const requiredPieces = Math.max(Math.ceil(number(line.orderQty) / Math.max(number(line.pcsPerSet, 1), 1)), 1);
        const across = Math.max(Math.floor(state.fabricWidth / Math.max(number(line.pieceWidth, 1), .1)), 1);
        const calculatedLength = Math.max(Math.ceil(requiredPieces / across) * number(line.pieceLength, 20), 60);
        state.fabricLength = Math.min(calculatedLength, 3000);
        el('pieceName').value = line.materialCode || 'Chi tiết';
        el('pieceLength').value = number(line.pieceLength, 20);
        el('pieceWidth').value = number(line.pieceWidth, 10);
        el('pieceQuantity').value = Math.min(requiredPieces, 500);
        render();
        const previewNote = calculatedLength > 3000 ? ' Đang giới hạn đoạn xem ở 3.000 cm để giữ hiệu năng.' : '';
        showToast(`Đã nạp ${line.materialCode || 'dòng vật tư'}.${previewNote}`);
    });

    el('fabricStage').addEventListener('pointerdown', event => {
        const node = event.target.closest('.cut-piece');
        if (!node) {
            state.selectedId = null;
            render();
            return;
        }
        const piece = state.pieces.find(item => item.id === node.dataset.id);
        if (!piece) return;
        state.selectedId = piece.id;
        el('fabricStage').querySelectorAll('.cut-piece').forEach(item => item.classList.toggle('is-selected', item === node));
        renderTypeList();
        const startX = event.clientX;
        const startY = event.clientY;
        const originX = piece.x;
        const originY = piece.y;
        node.setPointerCapture(event.pointerId);

        const move = moveEvent => {
            const scale = PX_PER_CM * state.zoom;
            piece.x = Math.round((originX + (moveEvent.clientX - startX) / scale) * 2) / 2;
            piece.y = Math.round((originY + (moveEvent.clientY - startY) / scale) * 2) / 2;
            clampPiece(piece);
            node.style.left = `${piece.x * PX_PER_CM}px`;
            node.style.top = `${piece.y * PX_PER_CM}px`;
            renderMetrics();
        };
        const up = () => {
            node.removeEventListener('pointermove', move);
            node.removeEventListener('pointerup', up);
            node.removeEventListener('pointercancel', up);
            render();
            saveState(false);
        };
        node.addEventListener('pointermove', move);
        node.addEventListener('pointerup', up);
        node.addEventListener('pointercancel', up);
    });

    el('pieceTypeList').addEventListener('click', event => {
        const item = event.target.closest('[data-select-id]');
        if (!item) return;
        state.selectedId = item.dataset.selectId;
        render();
        el('fabricStage').querySelector(`[data-id="${CSS.escape(state.selectedId)}"]`)?.scrollIntoView({ block: 'center', inline: 'center', behavior: 'smooth' });
    });

    el('autoArrangeBtn').addEventListener('click', () => autoArrange(true));
    el('rotateBtn').addEventListener('click', rotateSelected);
    el('duplicateBtn').addEventListener('click', duplicateSelected);
    el('deleteBtn').addEventListener('click', deleteSelected);
    el('zoomOutBtn').addEventListener('click', () => setZoom(state.zoom - .1));
    el('zoomInBtn').addEventListener('click', () => setZoom(state.zoom + .1));
    el('fitBtn').addEventListener('click', () => setZoom(1));
    el('saveBtn').addEventListener('click', () => saveState(true));
    el('clearBtn').addEventListener('click', () => {
        if (!state.pieces.length || !window.confirm('Xóa toàn bộ chi tiết trên sơ đồ?')) return;
        state.pieces = [];
        state.selectedId = null;
        render();
        saveState(false);
    });
    document.addEventListener('keydown', event => {
        if (event.target.matches('input, select, textarea')) return;
        if (event.key === 'Delete') deleteSelected();
        if (event.key.toLowerCase() === 'r') rotateSelected();
        if (event.ctrlKey && event.key.toLowerCase() === 'd') {
            event.preventDefault();
            duplicateSelected();
        }
    });

    loadState();
    loadCalculatorSources();
    render();
})();
</script>
</body>
</html>
