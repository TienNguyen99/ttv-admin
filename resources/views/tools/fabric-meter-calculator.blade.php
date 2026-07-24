<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#eef8ff">
    <title>Tính mét vải cần xuất</title>
    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
    <script src="{{ asset('vendor/three/three.min.js') }}"></script>
    <style>
        :root {
            --ink: #123653;
            --muted: #557187;
            --line: #c9ddec;
            --surface: #ffffff;
            --canvas: #eaf7ff;
            --primary: #157da6;
            --primary-hover: #0f678a;
            --cyan: #59c9d8;
            --green: #179768;
            --orange: #e78a32;
            --danger: #c9485b;
        }
        * { box-sizing: border-box; }
        html, body { min-height: 100%; }
        body {
            margin: 0;
            background: #f2f9fd;
            color: var(--ink);
            font-family: "Segoe UI", Arial, sans-serif;
            letter-spacing: 0;
        }
        button, input, select { font: inherit; letter-spacing: 0; }
        button { cursor: pointer; }
        .meter-app { width: min(1500px, 100%); min-height: 100vh; margin: 0 auto; padding: 16px; }
        .meter-topbar {
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 56px;
            margin-bottom: 12px;
            padding: 8px 12px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--surface);
        }
        .meter-mark {
            display: grid;
            width: 38px;
            height: 38px;
            flex: 0 0 38px;
            place-items: center;
            border-radius: 7px;
            background: #d9effc;
            color: var(--primary);
        }
        .meter-mark svg { width: 21px; height: 21px; }
        .meter-brand { min-width: 0; }
        .meter-brand strong { display: block; font-size: 15px; }
        .meter-brand span { display: block; color: var(--muted); font-size: 11px; }
        .meter-topbar__badge {
            margin-left: auto;
            padding: 6px 9px;
            border: 1px solid #b9dfd0;
            border-radius: 999px;
            background: #ecfbf5;
            color: #117250;
            font-size: 11px;
            font-weight: 700;
        }
        .meter-layout {
            display: grid;
            grid-template-columns: minmax(320px, 410px) minmax(0, 1fr);
            gap: 12px;
            min-height: calc(100vh - 96px);
        }
        .meter-panel, .meter-visual {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--surface);
            overflow: hidden;
        }
        .meter-panel { min-width: 0; padding: 20px; }
        .meter-title { margin: 0; font-size: 24px; line-height: 1.2; }
        .meter-lead { margin: 6px 0 18px; color: var(--muted); font-size: 13px; line-height: 1.5; }
        .meter-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .meter-field--wide { grid-column: 1 / -1; }
        .meter-field { min-width: 0; }
        .meter-field label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 5px;
            color: #35546c;
            font-size: 12px;
            font-weight: 700;
        }
        .meter-field label small { color: #7890a3; font-weight: 500; }
        .meter-input-wrap { position: relative; }
        .meter-input, .meter-select {
            width: 100%;
            min-height: 44px;
            padding: 9px 44px 9px 11px;
            border: 1px solid #bcd2e3;
            border-radius: 7px;
            outline: 0;
            background: #fbfdff;
            color: var(--ink);
            font-weight: 700;
            transition: border-color .18s, box-shadow .18s;
        }
        .meter-select { padding-right: 10px; }
        .meter-input:focus, .meter-select:focus {
            border-color: #42a6cb;
            box-shadow: 0 0 0 3px rgba(21, 125, 166, .12);
        }
        .meter-unit {
            position: absolute;
            top: 50%;
            right: 11px;
            color: #668096;
            font-size: 11px;
            font-weight: 700;
            transform: translateY(-50%);
            pointer-events: none;
        }
        .meter-result {
            margin-top: 18px;
            padding: 16px;
            border: 1px solid #9ed6c1;
            border-radius: 8px;
            background: #effbf6;
        }
        .meter-result__label { color: #25634e; font-size: 11px; font-weight: 800; text-transform: uppercase; }
        .meter-result__value {
            display: flex;
            align-items: baseline;
            gap: 7px;
            margin-top: 3px;
            color: #0c7951;
        }
        .meter-result__value strong { font-size: 42px; line-height: 1; }
        .meter-result__value span { font-size: 18px; font-weight: 800; }
        .meter-result__meta { margin-top: 8px; color: #39715f; font-size: 12px; line-height: 1.5; }
        .meter-actions { display: grid; grid-template-columns: 1fr auto; gap: 8px; margin-top: 12px; }
        .meter-button {
            display: inline-flex;
            min-height: 43px;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 1px solid transparent;
            border-radius: 7px;
            font-weight: 800;
            transition: background-color .18s, border-color .18s;
        }
        .meter-button svg { width: 17px; height: 17px; }
        .meter-button--primary { background: var(--primary); color: #fff; }
        .meter-button--primary:hover { background: var(--primary-hover); }
        .meter-button--secondary { border-color: #bcd2e3; background: #fff; color: var(--primary); }
        .meter-button--secondary:hover { background: #edf7fc; }
        .meter-formula {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid #dce8f1;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.6;
        }
        .meter-visual { display: flex; min-width: 0; flex-direction: column; }
        .meter-visual__head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            min-height: 58px;
            padding: 10px 14px;
            border-bottom: 1px solid var(--line);
        }
        .meter-visual__head strong { font-size: 14px; }
        .meter-visual__head span { color: var(--muted); font-size: 11px; }
        .meter-orientation {
            display: inline-flex;
            margin-left: auto;
            padding: 6px 9px;
            border-radius: 6px;
            background: #e8f5ff;
            color: #146d93 !important;
            font-weight: 800;
        }
        .meter-scene {
            position: relative;
            flex: 1;
            min-height: 430px;
            overflow: hidden;
            background: var(--canvas);
        }
        #fabricCanvas { display: block; width: 100%; height: 100%; }
        .meter-scene__mobile-art { display: none; }
        .meter-scene__fallback {
            position: absolute;
            inset: 0;
            display: none;
            place-items: center;
            padding: 20px;
            color: var(--muted);
            text-align: center;
        }
        .meter-scene__legend {
            position: absolute;
            right: 12px;
            bottom: 12px;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            padding: 8px;
            border: 1px solid rgba(166, 199, 221, .92);
            border-radius: 7px;
            background: rgba(255,255,255,.9);
            backdrop-filter: blur(8px);
        }
        .meter-scene__scale {
            position: absolute;
            left: 12px;
            top: 12px;
            z-index: 2;
            max-width: 310px;
            padding: 8px 10px;
            border: 1px solid #efc58e;
            border-radius: 7px;
            background: rgba(255, 249, 238, .94);
            color: #8b5219;
            backdrop-filter: blur(8px);
        }
        .meter-scene__scale strong { display: block; font-size: 12px; }
        .meter-scene__scale span { display: block; margin-top: 2px; font-size: 10px; line-height: 1.4; }
        .meter-legend-item { min-width: 78px; }
        .meter-legend-item span { display: block; color: var(--muted); font-size: 9px; font-weight: 700; }
        .meter-legend-item strong { display: block; margin-top: 2px; font-size: 12px; }
        .meter-comparison {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            padding: 12px;
            border-top: 1px solid var(--line);
            background: #f8fbfe;
        }
        .meter-exact {
            padding: 12px;
            border-top: 1px solid var(--line);
            background: #fff;
        }
        .meter-exact__head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            margin-bottom: 8px;
        }
        .meter-exact__head strong { font-size: 13px; }
        .meter-exact__head span { color: var(--muted); font-size: 11px; font-weight: 700; }
        .meter-exact__scroll {
            max-width: 100%;
            max-height: 360px;
            overflow: auto;
            border: 1px solid #bcd2e3;
            border-radius: 7px;
            background: #edf8fd;
        }
        #exactLayoutCanvas { display: block; }
        .meter-plan {
            padding: 11px;
            border: 1px solid #d4e3ef;
            border-radius: 7px;
            background: #fff;
        }
        .meter-plan.is-best { border-color: #61b99a; background: #f0fbf7; }
        .meter-plan__name { display: flex; align-items: center; justify-content: space-between; gap: 7px; font-size: 11px; font-weight: 800; }
        .meter-plan__best { color: var(--green); font-size: 9px; text-transform: uppercase; }
        .meter-plan__value { margin-top: 5px; font-size: 18px; font-weight: 800; }
        .meter-plan__meta { margin-top: 2px; color: var(--muted); font-size: 10px; }
        .meter-toast {
            position: fixed;
            right: 18px;
            bottom: 18px;
            z-index: 10;
            padding: 10px 13px;
            border-radius: 7px;
            background: #123653;
            color: #fff;
            font-size: 12px;
            opacity: 0;
            transform: translateY(10px);
            pointer-events: none;
            transition: opacity .18s, transform .18s;
        }
        .meter-toast.is-visible { opacity: 1; transform: translateY(0); }
        @media (max-width: 900px) {
            .meter-layout { grid-template-columns: 1fr; }
            .meter-scene { min-height: 420px; }
        }
        @media (max-width: 540px) {
            .meter-app { padding: 8px; overflow-x: hidden; }
            .meter-topbar__badge { display: none; }
            .meter-panel { padding: 15px; }
            .meter-fields, .meter-comparison { grid-template-columns: 1fr; }
            .meter-field--wide { grid-column: auto; }
            .meter-title { font-size: 21px; }
            .meter-result__value strong { font-size: 36px; }
            .meter-scene { min-height: 340px; }
            #fabricCanvas { display: none; }
            .meter-scene__mobile-art {
                position: absolute;
                inset: 0 0 70px;
                display: flex;
                align-items: center;
                justify-content: center;
                overflow: hidden;
                perspective: 700px;
            }
            .mobile-fabric {
                position: relative;
                width: 250px;
                height: 160px;
                border: 5px solid #63b7d1;
                background: #88d7e9;
                transform: rotateX(58deg) rotateZ(-6deg) translate(18px, 18px);
                transform-style: preserve-3d;
            }
            .mobile-roll {
                position: absolute;
                z-index: 2;
                width: 178px;
                height: 58px;
                border: 5px solid #4497b5;
                border-radius: 30px;
                background: #62bdd7;
                transform: translate(-70px, -72px) rotate(-18deg);
                animation: mobileRoll 3.2s ease-in-out infinite;
            }
            .mobile-roll::before {
                position: absolute;
                left: -5px;
                top: -5px;
                width: 58px;
                height: 58px;
                content: "";
                border: 5px solid #4497b5;
                border-radius: 50%;
                background: #71c9df;
            }
            .mobile-roll::after {
                position: absolute;
                left: 16px;
                top: 16px;
                width: 16px;
                height: 16px;
                content: "";
                border-radius: 50%;
                background: #edb66f;
            }
            .mobile-panels {
                display: grid;
                grid-template-columns: repeat(5, 1fr);
                gap: 3px;
                padding: 12px;
            }
            .mobile-panels span { height: 24px; border: 1px solid rgba(26, 102, 133, .35); background: #f3b76f; }
            .mobile-panels span:nth-child(4n+2) { background: #f296aa; }
            .mobile-panels span:nth-child(4n+3) { background: #8ed9b5; }
            .mobile-panels span:nth-child(4n+4) { background: #aaa4e8; }
            .meter-scene__legend { left: 8px; right: 8px; bottom: 8px; }
            .meter-scene__scale { left: 8px; right: 8px; top: 8px; max-width: none; }
            .meter-legend-item { min-width: 0; }
            .meter-orientation { width: 100%; margin-left: 0; }
        }
        @media print {
            .meter-topbar__badge, .meter-actions { display: none !important; }
            .meter-app { padding: 0; }
            .meter-layout { min-height: 0; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { transition: none !important; }
            .mobile-roll { animation: none !important; }
        }
        @keyframes mobileRoll {
            0%, 100% { transform: translate(-70px, -72px) rotate(-18deg); }
            50% { transform: translate(-64px, -76px) rotate(-15deg); }
        }
    </style>
</head>
<body>
<main class="meter-app">
    <header class="meter-topbar">
        <span class="meter-mark"><i data-lucide="scissors"></i></span>
        <div class="meter-brand">
            <strong>TTV · Tính mét vải</strong>
            <span>Kết quả nhanh cho kinh doanh</span>
        </div>
        <span class="meter-topbar__badge">Không ghi dữ liệu kho</span>
    </header>

    <div class="meter-layout">
        <section class="meter-panel">
            <h1 class="meter-title">Cần xuất bao nhiêu mét vải?</h1>
            <p class="meter-lead">Nhập khổ cây vải, kích thước một tấm và sản lượng đơn hàng. Hệ thống tự chọn hướng cắt tiết kiệm hơn.</p>

            <div class="meter-fields">
                <div class="meter-field meter-field--wide">
                    <label for="materialName">Tên vải / ghi chú <small>không bắt buộc</small></label>
                    <input id="materialName" class="meter-input" value="Vải mẫu" autocomplete="off">
                </div>
                <div class="meter-field">
                    <label for="fabricWidth">Khổ cây vải</label>
                    <div class="meter-input-wrap">
                        <input id="fabricWidth" class="meter-input" type="number" min="1" step="0.1" value="137" inputmode="decimal">
                        <span class="meter-unit">cm</span>
                    </div>
                </div>
                <div class="meter-field">
                    <label for="wastePercent">Hao hụt</label>
                    <div class="meter-input-wrap">
                        <input id="wastePercent" class="meter-input" type="number" min="0" max="100" step="0.1" value="3" inputmode="decimal">
                        <span class="meter-unit">%</span>
                    </div>
                </div>
                <div class="meter-field">
                    <label for="pieceLength">Dài một tấm</label>
                    <div class="meter-input-wrap">
                        <input id="pieceLength" class="meter-input" type="number" min="0.1" step="0.1" value="25" inputmode="decimal">
                        <span class="meter-unit">cm</span>
                    </div>
                </div>
                <div class="meter-field">
                    <label for="pieceWidth">Rộng một tấm</label>
                    <div class="meter-input-wrap">
                        <input id="pieceWidth" class="meter-input" type="number" min="0.1" step="0.1" value="20" inputmode="decimal">
                        <span class="meter-unit">cm</span>
                    </div>
                </div>
                <div class="meter-field">
                    <label for="pcsPerPanel">Sản lượng mỗi tấm</label>
                    <div class="meter-input-wrap">
                        <input id="pcsPerPanel" class="meter-input" type="number" min="1" step="1" value="30" inputmode="numeric">
                        <span class="meter-unit">pcs</span>
                    </div>
                </div>
                <div class="meter-field">
                    <label for="orderQuantity">Số lượng đơn hàng</label>
                    <div class="meter-input-wrap">
                        <input id="orderQuantity" class="meter-input" type="number" min="0" step="1" value="1000" inputmode="numeric">
                        <span class="meter-unit">pcs</span>
                    </div>
                </div>
                <div class="meter-field meter-field--wide">
                    <label for="roundStep">Làm tròn khi xuất</label>
                    <select id="roundStep" class="meter-select">
                        <option value="0.01">Lên 0,01 mét</option>
                        <option value="0.1" selected>Lên 0,1 mét</option>
                        <option value="1">Lên 1 mét</option>
                    </select>
                </div>
            </div>

            <div class="meter-result" aria-live="polite">
                <div class="meter-result__label">Đề nghị xuất</div>
                <div class="meter-result__value"><strong id="recommendedMeters">0</strong><span>mét</span></div>
                <div id="resultMeta" class="meter-result__meta"></div>
            </div>

            <div class="meter-actions">
                <button id="copyResultBtn" type="button" class="meter-button meter-button--primary">
                    <i data-lucide="copy"></i><span>Copy kết quả</span>
                </button>
                <button id="printBtn" type="button" class="meter-button meter-button--secondary" title="In kết quả">
                    <i data-lucide="printer"></i>
                </button>
            </div>

            <div id="formulaText" class="meter-formula"></div>
        </section>

        <section class="meter-visual">
            <header class="meter-visual__head">
                <div>
                    <strong>Mô phỏng cây vải</strong>
                    <span id="sceneSubtitle">Tự cập nhật theo số liệu bên trái</span>
                </div>
                <span id="orientationBadge" class="meter-orientation"></span>
            </header>

            <div id="sceneHost" class="meter-scene">
                <canvas id="fabricCanvas" aria-label="Mô phỏng 3D cây vải và cách xếp tấm"></canvas>
                <div class="meter-scene__mobile-art" aria-hidden="true">
                    <div class="mobile-fabric">
                        <div class="mobile-roll"></div>
                        <div class="mobile-panels">
                            <span></span><span></span><span></span><span></span><span></span>
                            <span></span><span></span><span></span><span></span><span></span>
                            <span></span><span></span><span></span><span></span><span></span>
                            <span></span><span></span><span></span><span></span><span></span>
                        </div>
                    </div>
                </div>
                <div id="threeFallback" class="meter-scene__fallback">Trình duyệt không tải được mô phỏng 3D. Kết quả tính mét vẫn hoạt động bình thường.</div>
                <div class="meter-scene__scale">
                    <strong id="sceneScaleTitle">Đang chuẩn bị mô phỏng</strong>
                    <span id="sceneScaleMeta"></span>
                </div>
                <div class="meter-scene__legend">
                    <div class="meter-legend-item"><span>Khổ vải</span><strong id="legendFabricWidth">0 cm</strong></div>
                    <div class="meter-legend-item"><span>Tấm cắt</span><strong id="legendPieceSize">0 × 0 cm</strong></div>
                    <div class="meter-legend-item"><span>Số tấm</span><strong id="legendPanelCount">0</strong></div>
                </div>
            </div>

            <div class="meter-exact">
                <div class="meter-exact__head">
                    <strong>Sơ đồ đúng toàn bộ số tấm</strong>
                    <span id="exactLayoutMeta"></span>
                </div>
                <div class="meter-exact__scroll">
                    <canvas id="exactLayoutCanvas" aria-label="Sơ đồ chính xác toàn bộ tấm cắt"></canvas>
                </div>
            </div>

            <div class="meter-comparison">
                <article id="normalPlan" class="meter-plan"></article>
                <article id="rotatedPlan" class="meter-plan"></article>
            </div>
        </section>
    </div>
</main>

<div id="meterToast" class="meter-toast" role="status" aria-live="polite"></div>

<script>
(() => {
    'use strict';

    const ids = ['fabricWidth', 'wastePercent', 'pieceLength', 'pieceWidth', 'pcsPerPanel', 'orderQuantity', 'roundStep', 'materialName'];
    const get = id => document.getElementById(id);
    const num = (id, fallback = 0) => Number.isFinite(Number(get(id).value)) ? Number(get(id).value) : fallback;
    const fmt = (value, digits = 3) => Number(value || 0).toLocaleString('vi-VN', { maximumFractionDigits: digits });
    const ceilStep = (value, step) => Math.ceil((value - 1e-9) / step) * step;
    let latest = null;
    let toastTimer;

    function createPlan(fabricWidth, panelLength, panelWidth, panels, waste, rotated) {
        const across = panelWidth > 0 ? Math.floor(fabricWidth / panelWidth) : 0;
        const rows = across > 0 ? Math.ceil(panels / across) : 0;
        const rawCm = rows * panelLength;
        const meters = rawCm * (1 + waste / 100) / 100;
        const area = panels * panelLength * panelWidth;
        const efficiency = rawCm > 0 && fabricWidth > 0 ? Math.min(area / (rawCm * fabricWidth) * 100, 100) : 0;
        return { fabricWidth, panelLength, panelWidth, panels, waste, rotated, across, rows, rawCm, meters, efficiency, valid: across > 0 };
    }

    function calculate() {
        const fabricWidth = Math.max(num('fabricWidth'), 0);
        const length = Math.max(num('pieceLength'), 0);
        const width = Math.max(num('pieceWidth'), 0);
        const perPanel = Math.max(Math.floor(num('pcsPerPanel', 1)), 1);
        const order = Math.max(Math.ceil(num('orderQuantity')), 0);
        const waste = Math.max(num('wastePercent'), 0);
        const panels = Math.ceil(order / perPanel);
        const normal = createPlan(fabricWidth, length, width, panels, waste, false);
        const rotated = createPlan(fabricWidth, width, length, panels, waste, true);
        let best = normal;
        if (rotated.valid && (!normal.valid || rotated.meters < normal.meters)) best = rotated;
        const step = Math.max(num('roundStep', .1), .01);
        const recommended = best.valid ? ceilStep(best.meters, step) : 0;
        return { fabricWidth, length, width, perPanel, order, waste, panels, normal, rotated, best, step, recommended };
    }

    function planMarkup(plan, isBest, name) {
        const result = plan.valid ? `${fmt(plan.meters)} m` : 'Không vừa khổ';
        const meta = plan.valid ? `${fmt(plan.across, 0)} tấm/hàng · ${fmt(plan.rows, 0)} hàng` : `Cạnh ngang ${fmt(plan.panelWidth)} cm lớn hơn khổ`;
        return `
            <div class="meter-plan__name"><span>${name}</span>${isBest ? '<span class="meter-plan__best">Tối ưu</span>' : ''}</div>
            <div class="meter-plan__value">${result}</div>
            <div class="meter-plan__meta">${meta}</div>
        `;
    }

    function renderResult() {
        latest = calculate();
        const { best, normal, rotated } = latest;
        get('recommendedMeters').textContent = fmt(latest.recommended);
        get('resultMeta').textContent = best.valid
            ? `${fmt(latest.panels, 0)} tấm ÷ ${fmt(best.across, 0)} tấm/hàng = ${fmt(best.rows, 0)} hàng. ${fmt(best.rows, 0)} × ${fmt(best.panelLength)} cm = ${fmt(best.rawCm / 100)} m; cộng ${fmt(latest.waste)}% = ${fmt(best.meters)} m; làm tròn lên ${fmt(latest.recommended)} m.`
            : 'Kích thước tấm không vừa với khổ cây vải.';
        get('orientationBadge').textContent = best.valid
            ? `${best.rotated ? 'Xoay 90°' : 'Giữ nguyên'} · ${fmt(best.panelLength)} dọc × ${fmt(best.panelWidth)} ngang`
            : 'Không có hướng phù hợp';
        get('normalPlan').classList.toggle('is-best', best === normal && normal.valid);
        get('rotatedPlan').classList.toggle('is-best', best === rotated && rotated.valid);
        get('normalPlan').innerHTML = planMarkup(normal, best === normal && normal.valid, `${fmt(latest.length)} dọc × ${fmt(latest.width)} ngang`);
        get('rotatedPlan').innerHTML = planMarkup(rotated, best === rotated && rotated.valid, `${fmt(latest.width)} dọc × ${fmt(latest.length)} ngang`);
        get('legendFabricWidth').textContent = `${fmt(latest.fabricWidth)} cm`;
        get('legendPieceSize').textContent = `${fmt(best.panelLength)} × ${fmt(best.panelWidth)} cm`;
        get('legendPanelCount').textContent = fmt(latest.panels, 0);
        get('formulaText').textContent = `${fmt(latest.order, 0)} pcs ÷ ${fmt(latest.perPanel, 0)} pcs/tấm = ${fmt(latest.panels, 0)} tấm. Xếp ${fmt(best.across, 0)} tấm mỗi hàng, cần ${fmt(best.rows, 0)} hàng dọc cây vải.`;
        get('sceneScaleTitle').textContent = `3D hiển thị đủ ${fmt(latest.panels, 0)} tấm`;
        get('sceneScaleMeta').textContent = `${fmt(best.rows, 0)} hàng × ${fmt(best.panelLength)} cm = ${fmt(best.rawCm / 100)} m trước hao hụt.`;
        renderExactLayout(latest);
        updateThreeScene(latest);
    }

    function renderExactLayout(data) {
        const canvas = get('exactLayoutCanvas');
        const best = data.best;
        if (!best.valid || !best.rows || !best.across) {
            canvas.width = 600;
            canvas.height = 110;
            const emptyContext = canvas.getContext('2d');
            emptyContext.clearRect(0, 0, canvas.width, canvas.height);
            emptyContext.fillStyle = '#557187';
            emptyContext.font = '13px Segoe UI';
            emptyContext.fillText('Kích thước tấm không vừa khổ vải.', 20, 55);
            get('exactLayoutMeta').textContent = 'Không có sơ đồ';
            return;
        }

        const rulerTop = 30;
        const labelLeft = 46;
        const maxCanvasWidth = 24000;
        const cellLength = Math.max(Math.min(54, (maxCanvasWidth - labelLeft) / best.rows), 5);
        const cellWidth = Math.max(Math.min(38, 260 / best.across), 5);
        const fabricLengthPx = best.rows * cellLength;
        const fabricWidthPx = best.across * cellWidth;
        canvas.width = Math.ceil(labelLeft + fabricLengthPx + 10);
        canvas.height = Math.ceil(rulerTop + fabricWidthPx + 20);
        canvas.style.width = `${canvas.width}px`;
        canvas.style.height = `${canvas.height}px`;

        const context = canvas.getContext('2d');
        context.clearRect(0, 0, canvas.width, canvas.height);
        context.fillStyle = '#e7f6fb';
        context.fillRect(labelLeft, rulerTop, fabricLengthPx, fabricWidthPx);
        context.strokeStyle = '#4e9fba';
        context.lineWidth = 1;
        context.strokeRect(labelLeft + .5, rulerTop + .5, fabricLengthPx, fabricWidthPx);

        const colors = ['#f5b56b', '#ef91a5', '#87d5ad', '#aaa4e8'];
        for (let index = 0; index < data.panels; index++) {
            const row = Math.floor(index / best.across);
            const across = index % best.across;
            const x = labelLeft + row * cellLength;
            const y = rulerTop + across * cellWidth;
            context.fillStyle = colors[index % colors.length];
            context.fillRect(x + 1, y + 1, Math.max(cellLength - 2, 1), Math.max(cellWidth - 2, 1));
            context.strokeStyle = 'rgba(30, 92, 122, .38)';
            context.strokeRect(x + .5, y + .5, cellLength, cellWidth);
            if (cellLength >= 34 && cellWidth >= 22) {
                context.fillStyle = '#173f59';
                context.font = '10px Segoe UI';
                context.textAlign = 'center';
                context.textBaseline = 'middle';
                context.fillText(String(index + 1), x + cellLength / 2, y + cellWidth / 2);
            }
        }

        context.fillStyle = '#35546c';
        context.font = '10px Segoe UI';
        context.textAlign = 'left';
        context.textBaseline = 'alphabetic';
        const centimetersPerColumn = best.panelLength;
        const markerEveryRows = Math.max(Math.ceil(100 / centimetersPerColumn), 1);
        for (let row = 0; row <= best.rows; row += markerEveryRows) {
            const x = labelLeft + row * cellLength;
            const meters = row * centimetersPerColumn / 100;
            context.strokeStyle = '#6d9ab0';
            context.beginPath();
            context.moveTo(x + .5, rulerTop - 5);
            context.lineTo(x + .5, rulerTop);
            context.stroke();
            context.fillText(`${fmt(meters)} m`, x + 2, 17);
        }
        context.fillText(`${fmt(data.fabricWidth)} cm`, 3, rulerTop + 14);
        context.fillStyle = '#0c7951';
        context.font = 'bold 11px Segoe UI';
        context.fillText(`Thực tính sau hao hụt: ${fmt(best.meters)} m`, labelLeft, canvas.height - 5);

        get('exactLayoutMeta').textContent = `${fmt(data.panels, 0)} tấm · ${fmt(best.rows, 0)} hàng · ${fmt(best.rawCm / 100)} m trước hao hụt`;
    }

    function showToast(message) {
        const toast = get('meterToast');
        toast.textContent = message;
        toast.classList.add('is-visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('is-visible'), 2000);
    }

    let scene;
    let camera;
    let renderer;
    let fabricGroup;
    let roll;
    let animationFrame;
    let resizeObserver;
    let reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function initThree() {
        if (!window.THREE) {
            get('fabricCanvas').style.display = 'none';
            get('threeFallback').style.display = 'grid';
            return;
        }
        const host = get('sceneHost');
        const canvas = get('fabricCanvas');
        scene = new THREE.Scene();
        scene.background = new THREE.Color(0xeaf7ff);
        camera = new THREE.PerspectiveCamera(38, 1, .1, 100);
        camera.position.set(7.4, 6.8, 8.6);
        camera.lookAt(2.2, 0, 0);

        renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: false });
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
        renderer.outputColorSpace = THREE.SRGBColorSpace;
        renderer.shadowMap.enabled = true;
        renderer.shadowMap.type = THREE.PCFSoftShadowMap;

        scene.add(new THREE.HemisphereLight(0xffffff, 0x8db3c9, 2.4));
        const key = new THREE.DirectionalLight(0xffffff, 2.5);
        key.position.set(2, 8, 5);
        key.castShadow = true;
        scene.add(key);

        const floor = new THREE.Mesh(
            new THREE.PlaneGeometry(30, 20),
            new THREE.MeshStandardMaterial({ color: 0xddeff8, roughness: 1 })
        );
        floor.rotation.x = -Math.PI / 2;
        floor.position.y = -.12;
        floor.receiveShadow = true;
        scene.add(floor);

        fabricGroup = new THREE.Group();
        scene.add(fabricGroup);

        const resize = () => {
            const width = Math.max(host.clientWidth, 1);
            const height = Math.max(host.clientHeight, 1);
            renderer.setSize(width, height, false);
            camera.aspect = width / height;
            camera.updateProjectionMatrix();
        };
        resizeObserver = new ResizeObserver(resize);
        resizeObserver.observe(host);
        resize();

        const animate = () => {
            animationFrame = requestAnimationFrame(animate);
            if (roll && !reducedMotion) roll.rotation.z -= .004;
            renderer.render(scene, camera);
            canvas.dataset.rendered = 'true';
        };
        animate();
    }

    function clearGroup(group) {
        while (group.children.length) {
            const child = group.children.pop();
            child.geometry?.dispose();
            if (Array.isArray(child.material)) child.material.forEach(material => material.dispose());
            else child.material?.dispose();
        }
    }

    function updateThreeScene(data) {
        if (!fabricGroup || !data.best.valid) return;
        clearGroup(fabricGroup);

        const best = data.best;
        const shownRows = best.rows;
        const shownPanels = data.panels;
        const visualWidth = 4.4;
        const widthScale = visualWidth / Math.max(data.fabricWidth, 1);
        const panelX = Math.max(best.panelLength * widthScale, .22);
        const panelZ = Math.max(best.panelWidth * widthScale, .16);
        const stripLength = Math.max(shownRows * panelX, 2.4);

        const fabricMaterial = new THREE.MeshStandardMaterial({ color: 0x79c7df, roughness: .78, metalness: 0, side: THREE.DoubleSide });
        const strip = new THREE.Mesh(new THREE.BoxGeometry(stripLength, .06, visualWidth), fabricMaterial);
        strip.position.set(stripLength / 2, 0, 0);
        strip.receiveShadow = true;
        fabricGroup.add(strip);

        const rollRadius = .62;
        roll = new THREE.Mesh(
            new THREE.CylinderGeometry(rollRadius, rollRadius, visualWidth, 44),
            new THREE.MeshStandardMaterial({ color: 0x5aaecb, roughness: .68 })
        );
        roll.rotation.x = Math.PI / 2;
        roll.position.set(-.48, rollRadius - .02, 0);
        roll.castShadow = true;
        fabricGroup.add(roll);

        const core = new THREE.Mesh(
            new THREE.CylinderGeometry(.19, .19, visualWidth + .12, 32),
            new THREE.MeshStandardMaterial({ color: 0xe7b26d, roughness: .9 })
        );
        core.rotation.x = Math.PI / 2;
        core.position.copy(roll.position);
        fabricGroup.add(core);

        const colors = [0xf7b56d, 0xf08ba0, 0x83d0aa, 0xa9a1e8];
        const matrixHelper = new THREE.Object3D();
        colors.forEach((color, colorIndex) => {
            const instanceCount = Math.ceil(Math.max(shownPanels - colorIndex, 0) / colors.length);
            if (!instanceCount) return;
            const panelInstances = new THREE.InstancedMesh(
                new THREE.BoxGeometry(Math.max(panelX - .035, .08), .045, Math.max(panelZ - .035, .08)),
                new THREE.MeshStandardMaterial({ color, roughness: .72 }),
                instanceCount
            );
            let instanceIndex = 0;
            for (let index = colorIndex; index < shownPanels; index += colors.length) {
                const row = Math.floor(index / best.across);
                const across = index % best.across;
                const x = row * panelX + panelX / 2 + .08;
                const zStart = -visualWidth / 2;
                const z = zStart + across * panelZ + panelZ / 2;
                matrixHelper.position.set(x, .065, z);
                matrixHelper.updateMatrix();
                panelInstances.setMatrixAt(instanceIndex, matrixHelper.matrix);
                instanceIndex++;
            }
            panelInstances.instanceMatrix.needsUpdate = true;
            fabricGroup.add(panelInstances);
        });

        const isNarrow = get('sceneHost').clientWidth < 600;
        const targetX = stripLength / 2;
        const framingDistance = Math.max(stripLength * (isNarrow ? 1.25 : .72), isNarrow ? 12 : 8);
        camera.position.set(targetX, framingDistance * .72, framingDistance);
        camera.lookAt(targetX, 0, 0);
        get('sceneSubtitle').textContent = `${fmt(data.panels, 0)} tấm · ${fmt(best.across, 0)} tấm/hàng · tổng ${fmt(best.rows, 0)} hàng`;
    }

    ids.forEach(id => get(id).addEventListener(id === 'roundStep' ? 'change' : 'input', renderResult));
    get('copyResultBtn').addEventListener('click', async () => {
        const name = get('materialName').value.trim() || 'Vải';
        const text = `${name}: đề nghị xuất ${fmt(latest.recommended)} mét (${latest.panels} tấm, khổ ${fmt(latest.fabricWidth)} cm, đã gồm ${fmt(latest.waste)}% hao hụt).`;
        try {
            await navigator.clipboard.writeText(text);
            showToast('Đã copy kết quả.');
        } catch (error) {
            showToast(text);
        }
    });
    get('printBtn').addEventListener('click', () => window.print());

    window.lucide?.createIcons();
    initThree();
    renderResult();

    window.addEventListener('beforeunload', () => {
        if (animationFrame) cancelAnimationFrame(animationFrame);
        resizeObserver?.disconnect();
        renderer?.dispose();
    });
})();
</script>
</body>
</html>
