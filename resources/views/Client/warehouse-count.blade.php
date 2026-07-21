<!DOCTYPE html>
@php($shelfMapOnly = (bool) ($shelfMapOnly ?? false))
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $shelfMapOnly ? 'Mặt kệ kho' : 'Kiểm tồn kho' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --line: #e2e8f0; --muted: #64748b; --ink: #0f172a; --accent: #2563eb; }
        body { background: #f8fafc; color: var(--ink); }
        body.shelf-map-only .shelf-map-hide { display: none !important; }
        body.shelf-map-only [data-workspace-panel]:not(#map3dPanel) { display: none !important; }
        body.shelf-map-only #map3dPanel { display: block !important; }
        body.shelf-map-only .page-shell { max-width: 1800px; }
        body.shelf-map-only .rack-front-wrap { min-height: calc(100vh - 260px); }
        .page-shell { max-width: 1680px; margin: 0 auto; padding: 22px; }
        .page-title { font-size: 24px; font-weight: 700; }
        .page-subtitle { color: var(--muted); font-size: 14px; }
        .panel { background: #fff; border: 1px solid var(--line); border-radius: 8px; }
        .panel-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 13px 14px; border-bottom: 1px solid var(--line); }
        .panel-title { margin: 0; font-size: 15px; font-weight: 700; }
        .panel-body { padding: 14px; }
        .context-bar { padding: 12px 14px; }
        .form-label { margin-bottom: 4px; color: #475569; font-size: 12px; font-weight: 700; }
        .form-control, .form-select { min-height: 40px; border-color: #cbd5e1; border-radius: 6px; font-size: 14px; }
        .btn { border-radius: 6px; }
        .btn-icon { display: inline-flex; align-items: center; gap: 6px; }
        .btn-icon svg { width: 16px; height: 16px; }
        .workspace-grid { display: grid; grid-template-columns: minmax(300px, 0.85fr) minmax(0, 2fr); gap: 14px; }
        .location-list { max-height: 460px; overflow: auto; }
        .location-item { display: flex; align-items: center; gap: 10px; width: 100%; padding: 11px 12px; border: 0; border-bottom: 1px solid #edf2f7; background: #fff; text-align: left; }
        .location-item:hover { background: #f8fafc; }
        .location-item.is-active { background: #eff6ff; box-shadow: inset 3px 0 0 var(--accent); }
        .location-code { font-size: 14px; font-weight: 700; }
        .location-meta { color: var(--muted); font-size: 12px; }
        .location-actions { margin-left: auto; white-space: nowrap; }
        .location-actions .btn { min-height: 32px; padding: 4px 7px; }
        .summary-strip { display: flex; flex-wrap: wrap; gap: 8px; }
        .summary-chip { padding: 4px 8px; border: 1px solid #bfdbfe; border-radius: 6px; background: #eff6ff; color: #1d4ed8; font-size: 12px; font-weight: 700; }
        .color-chip { display: inline-flex; align-items: center; gap: 6px; min-width: 0; }
        .color-swatch { flex: 0 0 auto; width: 14px; height: 14px; border: 1px solid #cbd5e1; border-radius: 3px; background: var(--swatch, transparent); box-shadow: inset 0 0 0 1px rgba(255,255,255,.35); }
        .table { margin-bottom: 0; font-size: 13px; }
        .table > :not(caption) > * > * { padding: 9px 10px; border-color: var(--line); }
        .table thead th { background: #f8fafc; color: #475569; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .empty-state { padding: 34px 12px !important; color: var(--muted) !important; }
        .product-search { position: relative; }
        .product-results { position: absolute; inset: calc(100% + 4px) 0 auto; z-index: 1030; max-height: 260px; overflow-y: auto; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; box-shadow: 0 10px 24px rgba(15, 23, 42, 0.14); }
        .product-option { display: block; width: 100%; padding: 9px 10px; border: 0; border-bottom: 1px solid #edf2f7; background: #fff; text-align: left; }
        .product-option:hover, .product-option:focus { background: #eff6ff; outline: 0; }
        .product-option-code { color: #1d4ed8; font-size: 13px; font-weight: 700; }
        .product-option-name { color: var(--muted); font-size: 12px; }
        .kpi-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-bottom: 14px; }
        .kpi-item { display: flex; align-items: center; gap: 10px; padding: 12px; border: 1px solid var(--line); border-radius: 8px; background: #fff; }
        .kpi-icon { display: grid; width: 36px; height: 36px; place-items: center; border-radius: 7px; background: #eff6ff; color: #1d4ed8; }
        .kpi-icon svg { width: 18px; height: 18px; }
        .kpi-value { font-size: 19px; font-weight: 800; line-height: 1; }
        .kpi-label { margin-top: 4px; color: var(--muted); font-size: 12px; }
        .view-tabs { display: flex; gap: 4px; margin-bottom: 14px; padding: 4px; border: 1px solid var(--line); border-radius: 8px; background: #fff; }
        .view-tab { display: inline-flex; align-items: center; gap: 6px; min-height: 38px; padding: 7px 11px; border: 0; border-radius: 5px; background: transparent; color: #475569; font-size: 13px; font-weight: 700; }
        .view-tab svg { width: 16px; height: 16px; }
        .view-tab:hover { background: #f8fafc; }
        .view-tab.is-active { background: #eff6ff; color: #1d4ed8; }
        .section-hint { color: var(--muted); font-size: 12px; }
        .voice-assistant { display: grid; grid-template-columns: auto minmax(220px, 420px) auto minmax(0, 1fr); gap: 8px; align-items: center; padding: 10px 12px; }
        .voice-button { width: 40px; height: 40px; padding: 0; justify-content: center; }
        .voice-button.is-listening { border-color: #dc2626; background: #fee2e2; color: #b91c1c; animation: voice-pulse 1.2s infinite; }
        .voice-result { min-width: 0; color: #334155; font-size: 13px; }
        .voice-result strong { color: #0f172a; }
        .voice-location { display: inline-flex; gap: 5px; margin: 2px 4px 2px 0; padding: 3px 7px; border: 1px solid #bfdbfe; border-radius: 5px; background: #eff6ff; color: #1d4ed8; font-size: 12px; font-weight: 700; }
        @keyframes voice-pulse { 50% { box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.12); } }
        .warehouse-toast-stack { position: fixed; right: 18px; bottom: 18px; z-index: 2000; display: grid; gap: 8px; width: min(420px, calc(100vw - 32px)); pointer-events: none; }
        .warehouse-toast { pointer-events: auto; padding: 12px 14px; border: 1px solid #fed7aa; border-left: 4px solid #f97316; border-radius: 8px; background: #fff7ed; box-shadow: 0 14px 34px rgba(15, 23, 42, .16); color: #7c2d12; animation: toast-in .18s ease-out both; }
        .warehouse-toast strong { display: block; margin-bottom: 3px; color: #9a3412; }
        .warehouse-toast div { font-size: 13px; line-height: 1.35; }
        @keyframes toast-in { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        .warehouse-map { padding: 14px; overflow-x: auto; }
        .warehouse-blueprint { position: relative; min-width: 980px; padding: 12px 14px 14px; border: 2px solid #cbd5e1; border-radius: 8px; background: #fff; }
        .blueprint-title { margin: 0 0 10px; text-align: center; color: #334155; font-size: 22px; font-weight: 900; letter-spacing: 0.06em; }
        .blueprint-top { display: grid; grid-template-columns: 118px 1fr 360px; gap: 10px; margin-bottom: 8px; }
        .blueprint-main { display: grid; grid-template-columns: 96px minmax(0, 1fr) 96px; gap: 10px; }
        .blueprint-bottom { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; margin-top: 10px; }
        .zone-box { display: grid; min-height: 46px; place-items: center; padding: 6px; border: 1px solid #94a3b8; background: rgba(248, 250, 252, 0.85); color: #334155; font-size: 11px; font-weight: 800; text-align: center; text-transform: uppercase; }
        .zone-stack { display: grid; align-content: space-between; gap: 10px; }
        .aisle-column { position: relative; min-height: 100%; border-left: 1px dashed #cbd5e1; border-right: 1px dashed #cbd5e1; }
        .aisle-column::before, .aisle-column::after { position: absolute; left: 50%; transform: translateX(-50%); color: #475569; font-size: 38px; font-weight: 900; line-height: 1; }
        .aisle-column::before { content: "â†“"; top: 12px; }
        .aisle-column::after { content: "â†“"; bottom: 12px; }
        .shelf-area { display: grid; gap: 8px; }
        .shelf-row { display: grid; grid-template-columns: 130px minmax(0, 1fr); gap: 8px; align-items: stretch; }
        .shelf-label { display: flex; flex-direction: column; justify-content: center; padding: 10px; border: 1px solid #94a3b8; border-radius: 0; background: rgba(248, 250, 252, 0.9); }
        .shelf-code { font-size: 18px; font-weight: 900; }
        .shelf-name { color: var(--muted); font-size: 12px; }
        .shelf-lanes { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
        .shelf-tier { min-height: 132px; border: 1px solid #94a3b8; border-radius: 0; background: rgba(255, 255, 255, 0.95); }
        .shelf-tier-title { position: relative; display: flex; justify-content: space-between; gap: 8px; padding: 7px 34px; border-bottom: 1px solid #edf2f7; color: #475569; font-size: 12px; font-weight: 800; }
        .shelf-tier-title::before { content: "â†"; position: absolute; left: 10px; top: 4px; color: #475569; font-size: 18px; }
        .shelf-tier-title::after { content: "â†’"; position: absolute; right: 10px; top: 4px; color: #475569; font-size: 18px; }
        .shelf-tier-body { display: grid; gap: 8px; padding: 8px; }
        .map-card { position: relative; border: 1px solid var(--line); border-radius: 8px; background: #fff; overflow: visible; }
        .map-card.has-stock { border-color: #bfdbfe; }
        .map-card.is-filter-match { border-color: #22c55e; background: #f0fdf4; box-shadow: 0 0 0 3px #bbf7d0; }
        .map-card.is-selected { border-color: #93c5fd; box-shadow: 0 0 0 3px #dbeafe; }
        .map-card.is-drop-target { border-color: #22c55e; box-shadow: 0 0 0 3px #dcfce7; }
        .map-card-header { display: flex; justify-content: space-between; gap: 10px; padding: 9px; border-bottom: 1px solid #edf2f7; background: #f8fafc; }
        .map-card.is-filter-match .map-card-header { background: #dcfce7; }
        .map-card-code { font-size: 14px; font-weight: 800; }
        .map-card-name { margin-top: 2px; color: var(--muted); font-size: 11px; }
        .map-card-summary { color: #1d4ed8; font-size: 11px; font-weight: 700; white-space: nowrap; }
        .map-package-list { display: grid; gap: 7px; min-height: 54px; padding: 8px; }
        .map-package { cursor: grab; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; background: #f8fafc; }
        .map-package:active { cursor: grabbing; }
        .map-package-code { color: #0f172a; font-size: 12px; font-weight: 800; overflow-wrap: anywhere; }
        .map-package-meta { margin-top: 2px; color: var(--muted); font-size: 11px; overflow-wrap: anywhere; }
        .map-swatch { display: inline-block; width: 12px; height: 12px; margin-right: 5px; border: 1px solid #cbd5e1; border-radius: 3px; vertical-align: -2px; background: var(--swatch, transparent); }
        .map-hover { position: absolute; z-index: 40; left: 8px; right: 8px; top: calc(100% + 6px); display: none; padding: 9px; border: 1px solid #cbd5e1; border-radius: 7px; background: #fff; box-shadow: 0 16px 34px rgba(15, 23, 42, .18); color: #0f172a; font-size: 12px; }
        .map-card:hover .map-hover { display: grid; gap: 5px; }
        .map-hover-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .map-hover-code { min-width: 0; overflow-wrap: anywhere; font-weight: 800; }
        .map-hover-qty { flex: 0 0 auto; font-weight: 800; color: #166534; }
        .map-empty { display: grid; min-height: 80px; place-items: center; color: #94a3b8; font-size: 12px; }
        .shelf-empty { padding: 16px 8px; color: #94a3b8; font-size: 12px; text-align: center; }
        .layout-editor-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 12px 14px; border-top: 1px solid #e2e8f0; background: #f8fafc; }
        .layout-editor-toolbar .form-range { width: 130px; }
        .layout-editor-wrap { padding: 14px; overflow: auto; background: #f1f5f9; }
        .layout-editor {
            position: relative;
            display: grid;
            grid-template-columns: repeat(24, 40px);
            grid-template-rows: repeat(40, 32px);
            width: 960px;
            min-height: 1280px;
            border: 1px solid #94a3b8;
            background-image:
                linear-gradient(to right, #e2e8f0 1px, transparent 1px),
                linear-gradient(to bottom, #e2e8f0 1px, transparent 1px);
            background-size: 40px 32px;
            background-color: #fff;
            transform-origin: top left;
            transition: transform 180ms ease;
        }
        .layout-editor::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: 0;
            background-image: var(--warehouse-layout-bg, none);
            background-repeat: no-repeat;
            background-position: center top;
            background-size: contain;
            opacity: var(--warehouse-layout-bg-opacity, .36);
            pointer-events: none;
        }
        .layout-editor::after {
            content: "Kéo kệ để sắp xếp · Double click để chn vị trí";
            position: sticky;
            left: 12px;
            top: 12px;
            align-self: start;
            grid-column: 1 / span 7;
            grid-row: 1 / span 1;
            z-index: 0;
            width: max-content;
            padding: 4px 8px;
            border: 1px solid #bfdbfe;
            border-radius: 999px;
            background: rgba(239, 246, 255, .92);
            color: #1d4ed8;
            font-size: 11px;
            font-weight: 800;
        }
        .layout-block {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-width: 0;
            padding: 7px 8px;
            border: 1px solid #2563eb;
            border-radius: 6px;
            background: #eff6ff;
            color: #1e3a8a;
            cursor: move;
            user-select: none;
            box-shadow: 0 3px 8px rgba(37, 99, 235, 0.12);
            transition: border-color 160ms ease, box-shadow 160ms ease, background 160ms ease;
        }
        .layout-block:hover { border-color: #f97316; box-shadow: 0 0 0 3px rgba(249, 115, 22, .16), 0 8px 18px rgba(15, 23, 42, .14); }
        .layout-block.is-dragging { opacity: 0.72; z-index: 5; }
        .layout-block.is-selected { border-color: #16a34a; background: #ecfdf5; color: #14532d; box-shadow: 0 0 0 3px #bbf7d0; }
        .layout-block.has-stock { border-color: #60a5fa; background: #eff6ff; }
        .layout-block.is-filter-match { border-color: #22c55e; background: #dcfce7; color: #14532d; box-shadow: 0 0 0 3px #bbf7d0, 0 8px 18px rgba(22, 163, 74, .16); }
        .layout-block.is-tier-stack { transform: translate(calc(var(--tier-offset, 0) * 7px), calc(var(--tier-offset, 0) * -7px)); box-shadow: calc(var(--tier-offset, 0) * -1px) calc(var(--tier-offset, 0) * 1px) 0 rgba(15,23,42,.08), 0 3px 8px rgba(37, 99, 235, 0.12); }
        .layout-block.is-tier-stack::before { content: ""; position: absolute; left: 5px; right: 5px; bottom: -5px; height: 5px; border: 1px solid #cbd5e1; border-top: 0; border-radius: 0 0 6px 6px; background: rgba(226,232,240,.75); }
        .layout-block-code { font-size: 13px; font-weight: 900; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .layout-block-meta { margin-top: 2px; font-size: 11px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .layout-block-count { position: absolute; right: 6px; top: 5px; min-width: 20px; padding: 1px 5px; border-radius: 999px; background: #1d4ed8; color: #fff; font-size: 10px; font-weight: 900; text-align: center; }
        .layout-block-stock { margin-top: 4px; color: #166534; font-size: 11px; font-weight: 800; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .layout-stock-hover { position: absolute; z-index: 40; left: 0; top: calc(100% + 8px); display: none; width: min(420px, 80vw); max-height: 360px; overflow: auto; padding: 12px; border: 1px solid #bfdbfe; border-radius: 8px; background: #fff; box-shadow: 0 20px 44px rgba(15, 23, 42, .22); color: #0f172a; font-size: 12px; }
        .layout-block:hover .layout-stock-hover { display: grid; gap: 9px; }
        .layout-stock-title { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; padding-bottom: 8px; border-bottom: 1px solid #e2e8f0; font-weight: 900; }
        .layout-stock-row { display: grid; grid-template-columns: 44px minmax(0, 1fr) auto; align-items: center; gap: 10px; padding: 8px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; }
        .layout-stock-code { min-width: 0; overflow-wrap: anywhere; color: #0f172a; font-size: 13px; font-weight: 900; }
        .layout-stock-color { margin-top: 2px; color: #64748b; font-size: 11px; font-weight: 600; }
        .layout-stock-qty { flex: 0 0 auto; color: #166534; font-size: 15px; font-weight: 950; white-space: nowrap; }
        .layout-swatch { display: inline-block; width: 34px; height: 34px; border: 1px solid #cbd5e1; border-radius: 8px; background: var(--swatch, transparent); box-shadow: inset 0 0 0 1px rgba(255,255,255,.45); }
        .layout-empty-note { grid-column: 2 / span 10; grid-row: 3 / span 2; align-self: start; padding: 14px; border: 1px dashed #cbd5e1; border-radius: 8px; background: rgba(255,255,255,.92); color: #64748b; font-size: 13px; font-weight: 700; }
        .layout-drag-hint { position: absolute; z-index: 10; display: none; padding: 4px 7px; border-radius: 6px; background: #0f172a; color: #fff; font-size: 11px; pointer-events: none; }
        .layout-help { color: var(--muted); font-size: 12px; }
        .warehouse-3d-wrap { position: relative; height: min(68vh, 720px); min-height: 460px; overflow: hidden; border-top: 1px solid #e2e8f0; background: linear-gradient(180deg, #eef6ff 0%, #f8fafc 55%, #e2e8f0 100%); }
        .warehouse-3d-canvas { width: 100%; height: 100%; display: block; cursor: grab; }
        .warehouse-3d-canvas:active { cursor: grabbing; }
        .warehouse-3d-help { position: absolute; left: 14px; top: 14px; z-index: 2; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; max-width: min(720px, calc(100% - 28px)); padding: 8px 10px; border: 1px solid rgba(148, 163, 184, .45); border-radius: 8px; background: rgba(255,255,255,.88); color: #334155; font-size: 12px; font-weight: 700; backdrop-filter: blur(6px); }
        .warehouse-3d-badge { display: inline-flex; align-items: center; gap: 5px; }
        .warehouse-3d-dot { width: 10px; height: 10px; border-radius: 3px; background: #60a5fa; box-shadow: inset 0 0 0 1px rgba(15,23,42,.12); }
        .warehouse-3d-dot.is-match { background: #22c55e; }
        .warehouse-3d-dot.is-empty { background: #cbd5e1; }
        .warehouse-3d-tooltip { position: absolute; right: 14px; top: 14px; z-index: 3; display: none; width: min(360px, calc(100% - 28px)); max-height: calc(100% - 28px); overflow: auto; padding: 12px; border: 1px solid #bfdbfe; border-radius: 10px; background: rgba(255,255,255,.95); box-shadow: 0 18px 42px rgba(15,23,42,.18); color: #0f172a; }
        .warehouse-3d-tooltip.is-visible { display: block; }
        .warehouse-3d-title { display: flex; justify-content: space-between; gap: 10px; align-items: baseline; padding-bottom: 8px; border-bottom: 1px solid #e2e8f0; font-weight: 900; }
        .warehouse-3d-items { display: grid; gap: 7px; margin-top: 9px; }
        .warehouse-3d-item { display: grid; grid-template-columns: 28px minmax(0, 1fr) auto; gap: 8px; align-items: center; padding: 8px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; font-size: 12px; }
        .warehouse-3d-swatch { width: 28px; height: 28px; border: 1px solid #cbd5e1; border-radius: 6px; background: var(--swatch, #f8fafc); }
        .warehouse-3d-code { min-width: 0; overflow-wrap: anywhere; font-weight: 900; }
        .warehouse-3d-color { color: #64748b; font-size: 11px; }
        .warehouse-3d-qty { color: #166534; font-weight: 900; white-space: nowrap; }
        .warehouse-3d-empty { position: absolute; inset: 0; display: grid; place-items: center; padding: 24px; color: #64748b; font-size: 14px; font-weight: 700; text-align: center; pointer-events: none; }
        .rack-front-wrap { position: relative; min-height: 520px; padding: 16px 16px 22px; overflow: auto; border-top: 1px solid #e2e8f0; background: linear-gradient(180deg, #f8fbff 0%, #eef6ff 100%); scrollbar-gutter: stable both-edges; }
        .rack-front-wrap::-webkit-scrollbar { width: 12px; height: 14px; }
        .rack-front-wrap::-webkit-scrollbar-track { background: #dbeafe; border-radius: 999px; }
        .rack-front-wrap::-webkit-scrollbar-thumb { background: #2563eb; border: 3px solid #dbeafe; border-radius: 999px; }
        .rack-front-view { display: grid; gap: 22px; width: max-content; min-width: 100%; padding-bottom: 4px; }
        .rack-line { display: grid; gap: 12px; width: max-content; min-width: 100%; }
        .rack-line-header { position: sticky; left: 0; z-index: 2; display: flex; align-items: center; gap: 10px; width: min(100%, calc(100vw - 260px)); color: #0f2747; font-weight: 950; }
        .rack-line-header::after { content: ""; flex: 1; height: 1px; background: #bfdbfe; }
        .rack-line-title { display: inline-flex; align-items: center; gap: 8px; padding: 6px 10px; border: 1px solid #bfdbfe; border-radius: 999px; background: #eff6ff; }
        .rack-line-grid { display: grid; grid-auto-flow: column; grid-auto-columns: 220px; grid-template-columns: none; gap: 14px; align-items: start; width: max-content; min-width: max-content; }
        .rack-card { width: 220px; border: 2px solid #334155; border-radius: 8px; background: #f8fafc; box-shadow: 0 12px 28px rgba(15, 23, 42, .12); overflow: visible; }
        .rack-card-header { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 12px; background: #0f2747; color: #fff; font-weight: 900; }
        .rack-card-title { font-size: 16px; letter-spacing: .02em; }
        .rack-card-total { font-size: 12px; color: #bfdbfe; white-space: nowrap; }
        .rack-shelves { display: grid; gap: 0; padding: 8px; background: #e2e8f0; }
        .rack-tier-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px; }
        .rack-tier-row + .rack-tier-row { margin-top: 6px; }
        .rack-tier { position: relative; display: grid; grid-template-rows: auto 1fr auto; gap: 4px; align-items: start; min-height: 74px; padding: 6px; border: 1px solid #94a3b8; border-bottom-width: 3px; background: #fff; transition: border-color 160ms ease, background 160ms ease, box-shadow 160ms ease; cursor: pointer; }
        .rack-tier + .rack-tier { margin-top: 6px; }
        .rack-tier.has-stock { background: #eff6ff; border-color: #60a5fa; }
        .rack-tier.is-selected { background: #ecfdf5; border-color: #16a34a; box-shadow: inset 0 0 0 2px #86efac; }
        .rack-tier.is-filter-match { background: #dcfce7; border-color: #22c55e; animation: rackMatchGlow 1.15s ease-in-out infinite; }
        .rack-tier.is-order-match { background: #dbeafe; border-color: #2563eb; animation: rackOrderGlow 1.2s ease-in-out infinite; }
        .rack-tier.is-order-match .rack-tier-code { color: #1d4ed8; }
        .rack-tier-label { display: none; }
        .rack-tier-body { min-width: 0; display: grid; gap: 3px; }
        .rack-tier-code { font-size: 13px; line-height: 1; font-weight: 950; color: #0f172a; }
        .rack-tier-items { display: block; min-width: 0; }
        .rack-color-board { display: grid; grid-template-columns: repeat(2, 30px); grid-auto-rows: 21px; gap: 3px; align-items: start; }
        .thread-spool { width: 30px; height: 21px; display: block; filter: drop-shadow(0 1px 1px rgba(15, 23, 42, .18)); }
        .thread-spool-body { fill: var(--swatch, #f8fafc); stroke: #334155; stroke-width: 1.2; }
        .thread-spool-ring { fill: rgba(255,255,255,.72); stroke: rgba(15,23,42,.22); stroke-width: .8; }
        .thread-spool-line { stroke: rgba(15,23,42,.22); stroke-width: .8; }
        .rack-color-more { display: grid; place-items: center; width: 30px; height: 21px; border: 1px solid #cbd5e1; border-radius: 5px; background: #e2e8f0; color: #0f172a; font-size: 10px; font-weight: 950; }
        .rack-chip { display: inline-flex; align-items: center; gap: 5px; max-width: 100%; padding: 2px 6px; border: 1px solid #dbeafe; border-radius: 999px; background: #fff; color: #1e3a8a; font-size: 11px; font-weight: 800; }
        .rack-chip-text { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .rack-swatch { width: 12px; height: 12px; border: 1px solid #cbd5e1; border-radius: 3px; background: var(--swatch, #f8fafc); flex: 0 0 auto; }
        .rack-tier-qty { color: #166534; font-size: 12px; font-weight: 950; white-space: nowrap; line-height: 1; }
        .rack-tier-empty { color: #94a3b8; font-size: 12px; font-weight: 700; }
        .rack-tier-detail { display: none; }
        .rack-hover-preview { position: fixed; z-index: 2500; display: none; width: min(460px, calc(100vw - 24px)); padding: 12px; border: 1px solid #bfdbfe; border-radius: 10px; background: rgba(255,255,255,.98); box-shadow: 0 22px 60px rgba(15, 23, 42, .28); pointer-events: none; }
        .rack-hover-preview.is-visible { display: block; }
        .rack-hover-preview-title { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 10px; color: #0f2747; font-size: 14px; font-weight: 950; }
        .rack-hover-preview-meta { color: #166534; font-size: 12px; font-weight: 900; white-space: nowrap; }
        .rack-hover-preview-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 8px; }
        .rack-crud-table { max-height: 280px; overflow: auto; border: 1px solid #e2e8f0; border-radius: 8px; }
        .rack-crud-table table { margin: 0; }
        .rack-crud-location { display: inline-flex; align-items: center; gap: 8px; padding: 6px 10px; border: 1px solid #bfdbfe; border-radius: 999px; background: #eff6ff; color: #1d4ed8; font-weight: 900; }
        .rack-crud-swatch { width: 18px; height: 18px; border: 1px solid #cbd5e1; border-radius: 5px; background: var(--swatch, #f8fafc); display: inline-block; vertical-align: middle; }
        @keyframes rackMatchGlow {
            0%, 100% { box-shadow: inset 0 0 0 2px rgba(34, 197, 94, .36), 0 0 0 0 rgba(34, 197, 94, .30); }
            50% { box-shadow: inset 0 0 0 2px rgba(34, 197, 94, .72), 0 0 0 6px rgba(34, 197, 94, .16); }
        }
        @keyframes rackOrderGlow {
            0%, 100% { box-shadow: inset 0 0 0 2px rgba(37, 99, 235, .34), 0 0 0 0 rgba(37, 99, 235, .28); }
            50% { box-shadow: inset 0 0 0 2px rgba(37, 99, 235, .72), 0 0 0 7px rgba(37, 99, 235, .14); }
        }
        .rack-order-tools { display: grid; grid-template-columns: minmax(250px, 460px) auto minmax(0, 1fr); gap: 10px; align-items: center; width: 100%; padding: 12px 14px; border-bottom: 1px solid #bfdbfe; background: #f0f7ff; }
        .rack-order-search { position: relative; }
        .rack-order-search svg { position: absolute; left: 12px; top: 50%; width: 17px; height: 17px; color: #2563eb; transform: translateY(-50%); pointer-events: none; }
        .rack-order-search .form-control { padding-left: 38px; border-color: #93c5fd; background: #fff; font-weight: 800; text-transform: uppercase; }
        .rack-order-status { min-width: 0; color: #475569; font-size: 12px; }
        .rack-order-result { border-bottom: 1px solid #bfdbfe; background: #fff; }
        .rack-order-summary { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 10px 14px; border-bottom: 1px solid #e2e8f0; }
        .rack-order-summary strong { color: #0f2747; }
        .rack-order-chip { display: inline-flex; align-items: center; gap: 5px; min-height: 28px; padding: 4px 8px; border: 1px solid #bfdbfe; border-radius: 6px; background: #eff6ff; color: #1d4ed8; font-size: 12px; font-weight: 800; }
        .rack-order-chip.is-warn { border-color: #fed7aa; background: #fff7ed; color: #c2410c; }
        .rack-order-lines { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 8px; padding: 10px 14px 12px; }
        .rack-order-line { display: grid; grid-template-columns: 34px minmax(0, 1fr) auto; gap: 9px; align-items: center; min-width: 0; padding: 9px 10px; border: 1px solid #dbeafe; border-radius: 7px; background: #f8fbff; }
        .rack-order-line-swatch { width: 34px; height: 34px; border: 1px solid #cbd5e1; border-radius: 7px; background: var(--swatch, #f8fafc); }
        .rack-order-line-code { color: #0f2747; font-size: 13px; font-weight: 900; overflow-wrap: anywhere; }
        .rack-order-line-name { color: #64748b; font-size: 11px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .rack-order-line-qty { color: #166534; font-size: 12px; font-weight: 900; white-space: nowrap; }
        .rack-order-locations { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 5px; }
        .rack-order-location { padding: 2px 7px; border: 1px solid #93c5fd; border-radius: 5px; background: #fff; color: #1d4ed8; font-size: 11px; font-weight: 900; cursor: pointer; }
        .rack-order-location:hover, .rack-order-location:focus { background: #2563eb; color: #fff; outline: 0; }
        .rack-order-missing { color: #c2410c; font-size: 11px; font-weight: 800; }
        @media (prefers-reduced-motion: reduce) {
            .rack-tier.is-filter-match { animation: none; box-shadow: inset 0 0 0 2px rgba(34, 197, 94, .65); }
            .rack-tier.is-order-match { animation: none; box-shadow: inset 0 0 0 2px rgba(37, 99, 235, .65); }
        }
        @media (max-width: 1100px) { .workspace-grid { grid-template-columns: 1fr; } }
        @media (max-width: 900px) { .shelf-row { grid-template-columns: 1fr; } .shelf-lanes { grid-template-columns: 1fr; } }
        @media (max-width: 700px) { .kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .view-tabs { overflow-x: auto; } .view-tab { white-space: nowrap; } .voice-assistant { grid-template-columns: auto minmax(0, 1fr) auto; } .voice-result { grid-column: 1 / -1; } .rack-order-tools { grid-template-columns: 1fr auto; } .rack-order-status { grid-column: 1 / -1; } .rack-order-lines { grid-template-columns: 1fr; } }
        @media (max-width: 991.98px) { .page-shell { padding: 62px 12px 16px; } }
    </style>
    <link href="{{ asset('css/warehouse-wms.css') }}?v={{ filemtime(public_path('css/warehouse-wms.css')) }}" rel="stylesheet">
    <style>
        .page-shell { max-width: 1600px; padding: 24px 28px 40px; }
        .page-title { color: var(--wms-ink); font-size: 28px; font-weight: 800; }
        .panel { border-color: var(--wms-line); border-radius: 7px; }
        .kpi-item { min-height: 92px; border-color: var(--wms-line); border-radius: 7px; }
        .kpi-value { font-size: 25px; }
        .view-tabs { border-radius: 7px; }
        .view-tab { border-radius: 4px; }
        .view-tab.is-active { background: var(--wms-blue); color: #fff; }
        .table thead th { background: var(--wms-navy); color: #fff; }
    </style>
</head>
<body class="{{ $shelfMapOnly ? 'shelf-map-only' : '' }}">
    @include('layouts.partials.sidebar')
    <div id="warehouseToastStack" class="warehouse-toast-stack" aria-live="polite"></div>

    <header class="wms-topbar">
        <h1 class="wms-topbar__title">WMS May Mặc</h1>
        <div class="wms-global-search">
            <i data-lucide="search"></i>
            <input id="warehouseTopSearch" aria-label="Tìm mã hoặc vị trí kho" placeholder="Tìm mã hàng, vị trí hoặc quét mã...">
        </div>
        <div class="wms-topbar__actions">
            <button id="warehouseTopMic" type="button" class="wms-btn" title="Tìm bằng ging nói"><i data-lucide="mic"></i><span class="visually-hidden">Tìm bằng ging nói</span></button>
            <a class="wms-btn" href="{{ url('/client/ton-kho-noi-bo') }}"><i data-lucide="boxes"></i> Xem tồn</a>
        </div>
    </header>

    <main class="page-shell">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <div>
                <h1 class="page-title mb-1">{{ $shelfMapOnly ? 'Mặt kệ kho' : 'Quản lý nhập kho & vị trí' }}</h1>
                <div class="page-subtitle">{{ $shelfMapOnly ? 'Xem kệ theo line, tầng và ô vị trí. Click ô kệ để quản lý hàng trong vị trí.' : 'Nhập thành phẩm, bố trí vị trí, in tem QR và theo dõi kiện nội bộ.' }}</div>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <button type="button" class="btn btn-outline-primary btn-icon" onclick="openBulkLocationModal()"><i data-lucide="grid-2x2-plus"></i>Tạo nhanh vị trí</button>
                <button type="button" class="btn btn-outline-primary btn-icon" onclick="openBulkPrintLocationModal()"><i data-lucide="printer"></i>In tem vị trí</button>
                <button type="button" class="btn btn-outline-primary btn-icon" onclick="openLocationModal()"><i data-lucide="map-pin-plus"></i>Thêm vị trí</button>
                <select id="warehouseFlowTop" class="form-select shelf-map-hide" style="width:180px" onchange="handleWarehouseFlow(this.value)">
                    <option value="receipt">Nhập thành phẩm</option>
                    <option value="production">Xuất BTP sản xuất</option>
                </select>
                <button type="button" class="btn btn-primary btn-icon shelf-map-hide" onclick="handleWarehouseFlow(document.getElementById('warehouseFlowTop').value)"><i data-lucide="file-plus-2"></i>Mở phiếu</button>
            </div>
        </div>

        <section class="panel voice-assistant mb-3 shelf-map-hide" aria-label="Trợ lý ging nói kho">
            <button id="voiceLookupBtn" type="button" class="btn btn-outline-primary btn-icon voice-button" title="Nói mã hàng cần tìm"><i data-lucide="mic"></i></button>
            <input id="voiceLookupInput" class="form-control" placeholder="Nói hoặc nhập mã hàng, mã nội bộ">
            <button id="voiceSearchBtn" type="button" class="btn btn-outline-primary btn-icon"><i data-lucide="search"></i>Tìm</button>
            <div id="voiceLookupResult" class="voice-result">Bấm micro và nói: “Tìm mã BTPDAYHAIRB1-1.</div>
        </section>

        <section class="kpi-grid shelf-map-hide">
            <div class="kpi-item"><div class="kpi-icon"><i data-lucide="map-pinned"></i></div><div><div id="kpiLocations" class="kpi-value">0</div><div class="kpi-label">Vị trí kho</div></div></div>
            <div class="kpi-item"><div class="kpi-icon"><i data-lucide="scan-line"></i></div><div><div id="kpiCountingLocations" class="kpi-value">0</div><div class="kpi-label">Vị trí đang kiểm</div></div></div>
            <div class="kpi-item"><div class="kpi-icon"><i data-lucide="package-check"></i></div><div><div id="kpiPackages" class="kpi-value">0</div><div class="kpi-label">Kiện trong ngày</div></div></div>
            <div class="kpi-item"><div class="kpi-icon"><i data-lucide="boxes"></i></div><div><div id="kpiQuantity" class="kpi-value">0</div><div class="kpi-label">Số lượng trong ngày</div></div></div>
        </section>

        <section class="panel context-bar mb-3">
            <div class="row g-2 align-items-end">
                <input id="checkedAt" type="hidden" value="{{ now()->format('d/m/Y') }}">
                <div class="col-lg-7"><label class="form-label">V&#7883; tr&#237; &#273;ang ki&#7875;m</label><input id="locationCode" list="locationOptions" class="form-control" placeholder="&#272;&#7875; tr&#7889;ng n&#7871;u ch&#432;a x&#7871;p v&#7883; tr&#237;"></div>
                <div class="col-lg-2"><button type="button" class="btn btn-outline-secondary btn-icon w-100 justify-content-center" onclick="openLocationModal(value('locationCode').toUpperCase())"><i data-lucide="settings-2"></i>Quản lý vị trí</button></div>
            </div>
            <datalist id="locationOptions"></datalist>
        </section>

        <nav class="view-tabs shelf-map-hide" aria-label="Khu vực quản lý kho">
            <button type="button" class="view-tab is-active" data-workspace-view="entry" onclick="switchWorkspace('entry')"><i data-lucide="package-plus"></i>Nhập kho</button>
            <button type="button" class="view-tab" data-workspace-view="receipts" onclick="switchWorkspace('receipts')"><i data-lucide="files"></i>Danh sách phiếu</button>
            <button type="button" class="view-tab" data-workspace-view="overview" onclick="switchWorkspace('overview')"><i data-lucide="layout-dashboard"></i>Vị trí & hàng hóa</button>
            <button type="button" class="view-tab" data-workspace-view="history" onclick="switchWorkspace('history')"><i data-lucide="package-search"></i>Kiện hàng</button>
            <button type="button" class="view-tab" data-workspace-view="editor" onclick="switchWorkspace('editor')"><i data-lucide="grid-3x3"></i>Sơ đồ kho</button>
            <button type="button" class="view-tab" data-workspace-view="map3d" onclick="switchWorkspace('map3d')"><i data-lucide="panel-top"></i>Mặt kệ</button>
        </nav>

        <section id="editorPanel" data-workspace-panel="editor" class="panel mb-3 d-none">
            <div class="panel-header">
                <div><h2 class="panel-title">Sơ đồ kho tương tác</h2><div class="layout-help mt-1">Kéo thả kệ để sắp xếp đúng mặt bằng. Hover kệ để xem mã hàng, màu và số lượng.</div></div>
                <button type="button" class="btn btn-outline-primary btn-icon" onclick="renderLayoutEditor()"><i data-lucide="refresh-cw"></i>Tải lại</button>
            </div>
            <div class="layout-editor-toolbar">
                <input id="mapSearch" class="form-control" style="max-width:320px" placeholder="Lc mã hàng, màu hoặc vị trí">
                <input id="layoutBackgroundInput" type="file" accept="image/*" class="d-none">
                <button type="button" class="btn btn-outline-primary btn-icon" id="uploadLayoutBackgroundBtn"><i data-lucide="image-plus"></i>Thêm background sơ đồ</button>
                <button type="button" class="btn btn-outline-secondary btn-icon" id="clearLayoutBackgroundBtn"><i data-lucide="image-off"></i>Xóa nn</button>
                <label class="d-flex align-items-center gap-2 small text-secondary mb-0"><input id="showEmptyLocations" type="checkbox" class="form-check-input mt-0">Hiện vị trí trống</label>
                <label class="d-flex align-items-center gap-2 small text-secondary mb-0">ộ m nn <input id="layoutBackgroundOpacity" type="range" class="form-range" min="10" max="90" value="36"></label>
                <label class="d-flex align-items-center gap-2 small text-secondary mb-0">Zoom
                    <select id="layoutZoom" class="form-select form-select-sm" style="width:92px">
                        <option value="0.65">65%</option>
                        <option value="0.8">80%</option>
                        <option value="1" selected>100%</option>
                        <option value="1.2">120%</option>
                    </select>
                </label>
                <button type="button" class="btn btn-outline-primary btn-icon" onclick="openLocationModal()"><i data-lucide="map-pin-plus"></i>Thêm kệ</button>
                <span id="layoutSaveStatus" class="small text-secondary ms-auto">Kéo kệ, thả chuột là tự lưu.</span>
            </div>
            <div class="layout-editor-wrap"><div id="layoutEditor" class="layout-editor"><div id="layoutDragHint" class="layout-drag-hint"></div></div></div>
        </section>

        <section id="map3dPanel" data-workspace-panel="map3d" class="panel mb-3 d-none">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Mặt kệ kho</h2>
                    <div class="layout-help mt-1">Nhìn thẳng vào kệ: line 1 A-E, line 2 F-J; kéo ngang trong khung để xem các kệ phía sau.</div>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <button type="button" class="btn btn-outline-primary btn-icon" onclick="renderWarehouse3D()"><i data-lucide="refresh-cw"></i>Tải lại</button>
                </div>
            </div>
            <form id="rackProductionOrderForm" class="rack-order-tools" autocomplete="off">
                <div class="rack-order-search">
                    <i data-lucide="factory"></i>
                    <input id="rackProductionOrderInput" class="form-control" list="rackProductionOrderOptions" placeholder="Nhập lệnh SX rồi nhấn Enter" aria-label="Lệnh sản xuất cần tìm vị trí sợi">
                    <datalist id="rackProductionOrderOptions"></datalist>
                </div>
                <button id="rackProductionOrderClear" type="button" class="btn btn-outline-secondary btn-icon d-none" title="Bỏ lệnh đang xem"><i data-lucide="x"></i>Xóa</button>
                <div id="rackProductionOrderStatus" class="rack-order-status">Nhập lệnh để hiện toàn bộ kệ sợi cần lấy.</div>
            </form>
            <div id="rackProductionOrderResult" class="rack-order-result d-none" aria-live="polite"></div>
            <div class="rack-front-wrap">
                <div id="rackFrontView" class="rack-front-view"></div>
                <div id="warehouse3dEmpty" class="warehouse-3d-empty d-none">Không có kệ nào có hàng theo bộ lọc hiện tại.</div>
            </div>
        </section>
        <div id="rackHoverPreview" class="rack-hover-preview" aria-hidden="true"></div>

        <div id="overviewPanel" data-workspace-panel="overview" class="workspace-grid mb-3 d-none">
            <section class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">Danh sách vị trí</h2>
                    <span id="locationCount" class="location-meta"></span>
                </div>
                <div class="panel-body pb-2">
                    <input id="locationSearch" class="form-control" placeholder="Tìm vị trí hoặc mã kho">
                </div>
                <div id="locationRows" class="location-list"></div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Hàng tại <span id="selectedLocationTitle">chưa chn vị trí</span></h2>
                        <div id="selectedLocationName" class="location-meta mt-1"></div>
                    </div>
                    <div id="locationSummary" class="summary-strip"></div>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Mã nội bộ</th><th>Mã TP kế toán</th><th>Size</th><th>Màu</th><th>Side</th><th class="text-end">Số kiện</th><th class="text-end">Tổng SL</th></tr></thead>
                        <tbody id="locationContentRows"></tbody>
                    </table>
                </div>
            </section>
        </div>

        <section id="entryPanel" data-workspace-panel="entry" class="panel mb-3 d-none">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Phiếu kho</h2>
                    <div id="entryLocationContext" class="section-hint mt-1">Nếu chưa chn vị trí, phiếu sẽ lưu vào CHUA-XEP để xếp kệ sau.</div>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <select id="warehouseFlowEntry" class="form-select" style="width:180px" onchange="handleWarehouseFlow(this.value)">
                        <option value="receipt">Nhập thành phẩm</option>
                        <option value="production">Xuất BTP sản xuất</option>
                    </select>
                    <button id="cancelReceiptEditBtn" type="button" class="btn btn-outline-secondary btn-icon d-none"><i data-lucide="x"></i>Hủy sửa</button>
                    <button id="saveReceiptBatchBtn" class="btn btn-primary btn-icon"><i data-lucide="printer"></i>Lưu + in</button>
                </div>
            </div>
            <div class="panel-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-2">
                        <label class="form-label">Ngày nhập</label>
                        <input id="receiptDate" type="text" class="form-control date-vn" inputmode="numeric" placeholder="dd/mm/yyyy" value="{{ now()->format('d/m/Y') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Vị trí nhập</label>
                        <input id="receiptLocationCode" list="locationOptions" class="form-control" placeholder="ể trống: CHUA-XEP">
                    </div>
                    <div class="col-md-4"><label class="form-label">Ghi chú phiếu</label><input id="receiptHeaderNote" class="form-control" placeholder="Ví dụ: KCS giao kho, ca sáng"></div>
                    <div class="col-md-3 d-flex align-items-end">
                        <label class="form-check mb-2">
                            <input id="receiptSendToProduction" class="form-check-input" type="checkbox">
                            <span class="form-check-label">BTP đi sản xuất sau khi lưu</span>
                        </label>
                    </div>
                </div>
                <datalist id="receiptProductOptions"></datalist>
                <datalist id="productionOrderOptions"></datalist>
                <datalist id="internalCatalogOptions"></datalist>
                <div class="table-responsive">
                    <table class="table align-middle receipt-entry-table">
                        <thead>
                            <tr>
                                <th style="width:48px">Stt</th>
                                <th style="min-width:160px">Danh mục</th>
                                <th style="min-width:180px">Mã nội bộ *</th>
                                <th style="min-width:180px">Mã kế toán</th>
                                <th style="min-width:130px">Màu sắc</th>
                                <th style="min-width:110px">Size</th>
                                <th style="min-width:120px" class="text-end">Số lượng</th>
                                <th style="min-width:90px">vt</th>
                                <th style="min-width:160px">Lệnh sản xuất</th>
                                <th style="min-width:180px">Ghi chú dòng</th>
                            </tr>
                        </thead>
                        <tbody id="receiptEntryRows">
                            @for ($i = 1; $i <= 10; $i++)
                                <tr>
                                    <td class="text-muted fw-bold">{{ $i }}</td>
                                    <td><input class="form-control receipt-note" placeholder="TP / KCS"></td>
                                    <td><input class="form-control receipt-internal-code" list="internalCatalogOptions" autocomplete="off" placeholder="Mã DANH MỤC"></td>
                                    <td><input class="form-control receipt-ma-sp" list="receiptProductOptions" autocomplete="off" placeholder="Có thể thêm sau"></td>
                                    <td><input class="form-control receipt-color"></td>
                                    <td><input class="form-control receipt-size"></td>
                                    <td><input class="form-control receipt-quantity text-end" type="number" step="0.001" min="0"></td>
                                    <td><input class="form-control receipt-dvt" placeholder="Cái"></td>
                                    <td><input class="form-control receipt-order" list="productionOrderOptions" autocomplete="off" placeholder="Gõ lệnh SX"></td>
                                    <td><input class="form-control receipt-line-note" placeholder="Ghi chú riêng dòng"></td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section id="receiptsPanel" data-workspace-panel="receipts" class="panel mb-3 d-none">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Danh sách phiếu nhập thành phẩm</h2>
                    <div id="receiptListSummary" class="section-hint mt-1">Mỗi dòng là một phiếu cha, bên trong có nhiu dòng hàng.</div>
                </div>
                <button type="button" class="btn btn-outline-primary btn-icon" onclick="loadReceipts()"><i data-lucide="refresh-cw"></i>Tải lại</button>
            </div>
            <div class="panel-body pb-2">
                <div class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label">Tìm số phiếu, mã nội bộ, mã kế toán hoặc ghi chú</label>
                        <input id="receiptKeyword" class="form-control" placeholder="Ví dụ: PNTP-20260612-0001">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Ngày phiếu</label>
                        <input id="receiptFilterDate" type="text" class="form-control date-vn" inputmode="numeric" placeholder="dd/mm/yyyy">
                    </div>
                    <div class="col-md-2">
                        <button id="clearReceiptFilter" type="button" class="btn btn-outline-secondary w-100">Xóa lc</button>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Số phiếu</th>
                            <th>Ngày</th>
                            <th>Vị trí</th>
                            <th class="text-end">Số dòng</th>
                            <th class="text-end">Tổng SL</th>
                            <th>Xuất TP</th>
                            <th>Ghi chú</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="receiptRows"></tbody>
                </table>
            </div>
        </section>

        <section id="historyPanel" data-workspace-panel="history" class="panel d-none">
            <div class="panel-header"><h2 class="panel-title">Kiện vừa nhập</h2></div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Mã kiện</th><th>Vị trí</th><th>Mã TP</th><th>Mã nội bộ</th><th>Size</th><th>Màu</th><th>Side</th><th>SL</th><th></th></tr></thead>
                    <tbody id="packageRows"></tbody>
                </table>
            </div>
        </section>
    </main>
    <div class="modal fade" id="locationModal" tabindex="-1" aria-labelledby="locationModalTitle" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="locationModalTitle">Lưu vị trí kho</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="óng"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Vị trí kho</label><input id="editLocationCode" class="form-control" placeholder="A1"></div>
                    <div class="row g-2 mb-3">
                        <div class="col-4"><label class="form-label">Kệ</label><input id="editShelfCode" class="form-control text-uppercase" placeholder="Tự nhận"></div>
                        <div class="col-4"><label class="form-label">Tầng</label><select id="editTier" class="form-select"><option value="1">Tầng 1</option><option value="2">Tầng 2</option><option value="3">Tầng 3</option><option value="4">Tầng 4</option><option value="5">Tầng 5</option></select></div>
                        <div class="col-4"><label class="form-label">Ô</label><input id="editBayCode" class="form-control" placeholder="01"></div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-4"><label class="form-label">Ngang</label><input id="editGridW" type="number" min="1" max="24" class="form-control" value="4"></div>
                        <div class="col-4"><label class="form-label">Cao</label><input id="editGridH" type="number" min="1" max="10" class="form-control" value="2"></div>
                        <div class="col-4"><label class="form-label">Khổ nhanh</label><select id="editGridPreset" class="form-select"><option value="">Tùy chỉnh</option><option value="1x1">1 x 1</option><option value="1x2">1 x 2</option><option value="2x1">2 x 1</option><option value="2x2">2 x 2</option><option value="4x2">4 x 2</option></select></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Tên vị trí</label><input id="editLocationName" class="form-control" placeholder="Kệ A1"></div>
                    <div id="locationSaveStatus" class="small text-muted"></div>
                </div>
                <div class="modal-footer">
                    <button id="deleteLocationBtn" type="button" class="btn btn-outline-danger btn-icon me-auto d-none"><i data-lucide="trash-2"></i>Xóa vị trí</button>
                    <button id="useLocationBtn" type="button" class="btn btn-outline-primary btn-icon"><i data-lucide="package-plus"></i>Nhập hàng tại vị trí này</button>
                    <button id="printLocationBtn" type="button" class="btn btn-outline-secondary btn-icon"><i data-lucide="printer"></i>In tem</button>
                    <button id="saveLocationBtn" type="button" class="btn btn-primary btn-icon"><i data-lucide="save"></i>Lưu vị trí</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="movePackageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <div><h5 class="modal-title">Chuyển vị trí kiện</h5><div id="movePackageTitle" class="text-muted small"></div></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="óng"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Vị trí đích</label>
                    <select id="moveTargetLocationId" class="form-select"></select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">óng</button>
                    <button id="confirmMovePackageBtn" type="button" class="btn btn-primary">Chuyển kiện</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="bulkLocationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <div><h5 class="modal-title">Tạo nhanh vị trí kệ</h5><div class="text-muted small">Ví dụ: A đến D, số 1 đến 100 sẽ tạo A1...D100.</div></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="óng"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-3">
                        <div class="col-6"><label class="form-label">Kệ từ</label><input id="bulkShelfFrom" class="form-control text-uppercase" maxlength="1" value="A"></div>
                        <div class="col-6"><label class="form-label">Kệ đến</label><input id="bulkShelfTo" class="form-control text-uppercase" maxlength="1" value="D"></div>
                        <div class="col-6"><label class="form-label">Số từ</label><input id="bulkNumberFrom" type="number" min="1" max="999" class="form-control" value="1"></div>
                        <div class="col-6"><label class="form-label">Số đến</label><input id="bulkNumberTo" type="number" min="1" max="999" class="form-control" value="100"></div>
                        <div class="col-12"><label class="form-label">Tầng mặc định</label><select id="bulkTier" class="form-select"><option value="1">Tầng 1</option><option value="2">Tầng 2</option><option value="3">Tầng 3</option><option value="4">Tầng 4</option><option value="5">Tầng 5</option></select></div>
                        <div class="col-12"><label class="form-label">Tên tin tố</label><input id="bulkNamePrefix" class="form-control" value="Kệ"></div>
                    </div>
                    <div id="bulkLocationPreview" class="section-hint"></div>
                    <div id="bulkLocationStatus" class="small mt-2"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">óng</button>
                    <button id="createBulkLocationsBtn" type="button" class="btn btn-primary btn-icon"><i data-lucide="grid-2x2-plus"></i>Tạo vị trí</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="bulkPrintLocationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <div><h5 class="modal-title">In QR vị trí hàng loạt</h5><div class="text-muted small">Chn dãy vị trí đã tạo, ví dụ A đến D và 1 đến 100.</div></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="óng"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-3">
                        <div class="col-6"><label class="form-label">Kệ từ</label><input id="printShelfFrom" class="form-control text-uppercase" maxlength="1" value="A"></div>
                        <div class="col-6"><label class="form-label">Kệ đến</label><input id="printShelfTo" class="form-control text-uppercase" maxlength="1" value="D"></div>
                        <div class="col-6"><label class="form-label">Số từ</label><input id="printNumberFrom" type="number" min="1" max="999" class="form-control" value="1"></div>
                        <div class="col-6"><label class="form-label">Số đến</label><input id="printNumberTo" type="number" min="1" max="999" class="form-control" value="100"></div>
                    </div>
                    <div id="bulkPrintLocationPreview" class="section-hint"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">óng</button>
                    <button id="printBulkLocationsBtn" type="button" class="btn btn-primary btn-icon"><i data-lucide="printer"></i>Mở trang in</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="rackInventoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Hàng trong kệ</h5>
                        <div class="text-muted small">Số kệ lấy tự động từ ô vừa chọn.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-3">
                        <span class="rack-crud-location"><i data-lucide="map-pin"></i><span id="rackCrudLocationLabel">-</span></span>
                        <span id="rackCrudStatus" class="small text-muted"></span>
                    </div>
                    <div class="rack-crud-table mb-3">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Mã hàng hóa</th>
                                    <th>Tên hàng</th>
                                    <th class="text-end">Số lượng</th>
                                    <th>ĐVT</th>
                                    <th>Size</th>
                                    <th>Màu</th>
                                    <th class="text-end">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody id="rackCrudRows"><tr><td colspan="7" class="text-center text-muted py-3">Đang tải...</td></tr></tbody>
                        </table>
                    </div>
                    <form id="rackCrudForm" class="row g-2">
                        <input type="hidden" id="rackCrudPackageId">
                        <div class="col-lg-2 col-md-4">
                            <label class="form-label">Số kệ</label>
                            <input id="rackCrudLocationCode" class="form-control fw-bold text-uppercase" readonly>
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <label class="form-label">Mã hàng hóa</label>
                            <input id="rackCrudItemCode" class="form-control text-uppercase" list="rackCrudCatalogOptions" placeholder="Gõ mã/tên" autocomplete="off">
                            <datalist id="rackCrudCatalogOptions"></datalist>
                        </div>
                        <div class="col-lg-3 col-md-4">
                            <label class="form-label">Tên hàng</label>
                            <input id="rackCrudItemName" class="form-control" readonly>
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <label class="form-label">Số lượng</label>
                            <input id="rackCrudQuantity" type="number" step="0.001" min="0" class="form-control" placeholder="0">
                        </div>
                        <div class="col-lg-1 col-md-4">
                            <label class="form-label">ĐVT</label>
                            <input id="rackCrudUnit" class="form-control" readonly>
                        </div>
                        <div class="col-lg-1 col-md-4">
                            <label class="form-label">Size</label>
                            <input id="rackCrudSize" class="form-control">
                        </div>
                        <div class="col-lg-1 col-md-4">
                            <label class="form-label">Màu</label>
                            <input id="rackCrudColor" class="form-control">
                        </div>
                        <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                            <button type="button" class="btn btn-outline-secondary" onclick="resetRackCrudForm()">Hủy sửa</button>
                            <button type="submit" class="btn btn-primary btn-icon"><i data-lucide="save"></i>Lưu hàng</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="receiptLocationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <div><h5 class="modal-title">Gán vị trí phiếu nhập</h5><div id="receiptLocationTitle" class="text-muted small"></div></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="óng"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Vị trí đích</label>
                    <select id="receiptTargetLocationId" class="form-select"></select>
                    <div class="section-hint mt-2">Toàn bộ kiện còn tồn thuộc phiếu sẽ được chuyển sang vị trí này.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">óng</button>
                    <button id="confirmReceiptLocationBtn" type="button" class="btn btn-primary btn-icon"><i data-lucide="map-pin-check"></i>Lưu vị trí</button>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.min.js"></script>
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const shelfMapOnly = @json($shelfMapOnly);
        let locationContentsCache = [];
        function isoToDateVn(value) {
            const raw = String(value || '').slice(0, 10);
            const match = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
            return match ? `${match[3]}/${match[2]}/${match[1]}` : raw;
        }

        function dateVnToIso(value) {
            const raw = String(value || '').trim();
            const vn = raw.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
            if (vn) {
                return `${vn[3]}-${vn[2].padStart(2, '0')}-${vn[1].padStart(2, '0')}`;
            }
            return raw;
        }

        function setDateValue(id, value) {
            const input = document.getElementById(id);
            if (input) input.value = isoToDateVn(value);
        }

        const value = id => {
            const input = document.getElementById(id);
            if (!input) return '';
            const raw = input.value.trim();
            return input.classList.contains('date-vn') ? dateVnToIso(raw) : raw;
        };

        function showWarehouseToast(title, message, timeout = 6500) {
            const stack = document.getElementById('warehouseToastStack');
            if (!stack) return;
            const toast = document.createElement('div');
            toast.className = 'warehouse-toast';
            toast.innerHTML = `<strong>${escapeHtml(title)}</strong><div>${escapeHtml(message)}</div>`;
            stack.appendChild(toast);
            setTimeout(() => toast.remove(), timeout);
            toast.addEventListener('click', () => toast.remove());
        }

        function localReceiptDuplicates(lines) {
            const seen = new Map();
            const duplicates = [];
            lines.forEach((line, index) => {
                const code = String(line.internal_item_code || '').trim().toUpperCase();
                const quantity = Number(line.quantity || 0);
                if (!code || quantity <= 0) return;
                const key = `${code}|${quantity.toFixed(3)}`;
                if (seen.has(key)) {
                    duplicates.push({
                        line_index: index,
                        internal_item_code: code,
                        quantity,
                        first_line: seen.get(key),
                    });
                    return;
                }
                seen.set(key, index);
            });
            return duplicates;
        }

        function warnReceiptDuplicates(lines) {
            const localDuplicates = localReceiptDuplicates(lines);
            if (localDuplicates.length) {
                const first = localDuplicates[0];
                showWarehouseToast(
                    'Cảnh báo trùng trong phiếu',
                    `Dòng ${first.line_index + 1} giống dòng ${first.first_line + 1}: ${first.internal_item_code}, SL ${Number(first.quantity).toLocaleString('vi-VN')}.`
                );
            }

            return fetch('/api/kiem-ton-kho/phieu-nhap-tp/kiem-tra-trung', {
                method: 'POST',
                headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify({
                    checked_at: value('receiptDate'),
                    exclude_receipt_id: editingReceiptFormId,
                    lines
                })
            }).then(r => jsonOrError(r, 'Không kiểm tra được phiếu nhập trùng'))
              .then(result => {
                  const duplicates = result.duplicates || [];
                  if (!duplicates.length) return result;
                  const first = duplicates[0];
                  const matchCodes = (first.matches || []).map(item => item.receipt_code).filter(Boolean).join(', ');
                  showWarehouseToast(
                      `Có ${duplicates.length} dòng nghi trùng`,
                      `Dòng ${Number(first.line_index) + 1}: ${first.internal_item_code}, SL ${Number(first.quantity).toLocaleString('vi-VN')} đã có trong ${matchCodes || 'phiếu cũ'}.`
                  );
                  return result;
              })
              .catch(error => {
                  showWarehouseToast('Không kiểm tra được trùng', error.message);
                  return { duplicates: [] };
              });
        }

        function scheduleReceiptDuplicateCheck() {
            clearTimeout(receiptDuplicateTimer);
            receiptDuplicateTimer = setTimeout(() => {
                if (document.getElementById('entryPanel')?.classList.contains('d-none')) return;
                if (!value('receiptDate')) return;
                const lines = collectReceiptLines().filter(line => line.internal_item_code && Number(line.quantity || 0) > 0);
                if (!lines.length) return;
                warnReceiptDuplicates(lines);
            }, 650);
        }
        const locationModal = new bootstrap.Modal(document.getElementById('locationModal'));
        const movePackageModal = new bootstrap.Modal(document.getElementById('movePackageModal'));
        const bulkLocationModal = new bootstrap.Modal(document.getElementById('bulkLocationModal'));
        const bulkPrintLocationModal = new bootstrap.Modal(document.getElementById('bulkPrintLocationModal'));
        const rackInventoryModal = new bootstrap.Modal(document.getElementById('rackInventoryModal'));
        const receiptLocationModal = new bootstrap.Modal(document.getElementById('receiptLocationModal'));
        let editingReceiptId = null;
        let editingReceiptFormId = null;
        let locations = [];
        let mapPackages = [];
        let movingPackageId = null;
        let draggingLayout = null;
        let editingLocationId = null;
        let selectedAccountingProduct = '';
        let rackCrudLocationCode = '';
        let rackCrudItems = [];
        let rackCrudSearchTimer = null;
        let rackHoverTarget = null;
        let rackProductionOrderTimer = null;
        let rackProductionOrderRequest = 0;
        let activeRackProductionPlan = null;
        let activeRackLocationCodes = new Set();
        let receiptDuplicateTimer = null;
        let layoutBackgroundImage = localStorage.getItem('warehouseLayoutBackground') || '';
        let layoutBackgroundOpacity = Number(localStorage.getItem('warehouseLayoutBackgroundOpacity') || 36);
        let layoutZoom = Number(localStorage.getItem('warehouseLayoutZoom') || 1);
        let productSearchTimer;
        let internalCatalogSearchTimer = null;
        let internalCatalogItems = [];
        let voiceRecognition = null;
        const warehouse3d = {
            initialized: false,
            renderer: null,
            scene: null,
            camera: null,
            root: null,
            raycaster: null,
            pointer: null,
            meshes: [],
            hovered: null,
            dragging: false,
            lastX: 0,
            lastY: 0,
            rotationX: -0.72,
            rotationY: 0.72,
            distance: 46,
            animationFrame: null,
        };

        function refreshIcons() {
            if (window.lucide) lucide.createIcons();
        }

        function formatNumber(value) {
            return Number(value || 0).toLocaleString('vi-VN', { maximumFractionDigits: 3 });
        }

        function localIsoDate() {
            const date = new Date();
            date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
            return date.toISOString().slice(0, 10);
        }

        function updateSavePackageButton() {
            refreshIcons();
        }

        function setWarehouseFlow(value) {
            ['warehouseFlowTop', 'warehouseFlowEntry'].forEach(id => {
                const select = document.getElementById(id);
                if (select) select.value = value;
            });
        }

        function handleWarehouseFlow(value) {
            if (value === 'production') {
                window.location.href = '/client/xuat-vat-tu-noi-bo?type=production';
                return;
            }
            setWarehouseFlow('receipt');
            switchWorkspace('entry');
        }

        function normalizeVoiceKeyword(text) {
            let normalized = String(text || '').toUpperCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/Đ/g, 'D')
                .replace(/[.,?!:;]/g, ' ')
                .replace(/\s+/g, ' ');
            [
                'CHO TOI BIET', 'CAN BAO NHIEU', 'VI TRI NAO', 'MA NOI BO',
                'KIEM TRA', 'TRA CUU', 'MA HANG', 'TON KHO', 'KE NAO',
                'NAM O', 'O DAU', 'BAO NHIEU', 'VI TRI', 'TIM', 'MA', 'TEN', 'NAM', 'KE'
            ].forEach(phrase => {
                normalized = normalized.split(phrase).join(' ');
            });
            normalized = normalized.replace(/\s+/g, ' ').trim();

            const tokens = normalized.split(' ').filter(Boolean);
            return tokens.join('');
        }

        function speakWarehouseAnswer(text) {
            if (!('speechSynthesis' in window)) return;
            window.speechSynthesis.cancel();
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'vi-VN';
            utterance.rate = 0.95;
            window.speechSynthesis.speak(utterance);
        }

        function lookupWarehouseByVoice(rawText = '') {
            const input = document.getElementById('voiceLookupInput');
            const resultBox = document.getElementById('voiceLookupResult');
            const keyword = normalizeVoiceKeyword(rawText || input.value);
            input.value = keyword;

            if (!keyword) {
                resultBox.textContent = 'Không nhận được mã hàng. Hãy nói lại hoặc nhập mã.';
                speakWarehouseAnswer('Không nhận được mã hàng. Hãy nói lại.');
                return;
            }

            resultBox.textContent = `ang tìm ${keyword}...`;
            fetch(`/api/kiem-ton-kho/tra-cuu-giong-noi?keyword=${encodeURIComponent(keyword)}`)
                .then(r => jsonOrError(r, 'Không tra cứu được tồn kho'))
                .then(result => {
                    const rows = result.data || [];
                    if (!rows.length) {
                        resultBox.innerHTML = `<strong>${escapeHtml(keyword)}</strong>: không có tồn trong kho nội bộ.`;
                        speakWarehouseAnswer(`Mã ${keyword} hiện không có tồn trong kho nội bộ.`);
                        return;
                    }

                    const byLocation = {};
                    rows.forEach(row => {
                        const location = row.location_code || 'CHUA-XEP';
                        byLocation[location] = (byLocation[location] || 0) + Number(row.total_quantity || 0);
                    });
                    const locationsText = Object.entries(byLocation)
                        .map(([location, quantity]) => `${location}: ${formatNumber(quantity)}`)
                        .join(', ');
                    const locationsHtml = Object.entries(byLocation)
                        .map(([location, quantity]) => `<span class="voice-location">${escapeHtml(location)} · ${formatNumber(quantity)}</span>`)
                        .join('');
                    const total = formatNumber(result.summary?.total_quantity || 0);

                    resultBox.innerHTML = `<strong>${escapeHtml(keyword)}</strong> · Tổng ${total} ${locationsHtml}`;
                    speakWarehouseAnswer(`Mã ${keyword} còn tổng ${total}. ${locationsText}.`);
                })
                .catch(error => {
                    resultBox.textContent = error.message;
                    speakWarehouseAnswer('Không tra cứu được tồn kho.');
                });
        }

        function startVoiceLookup() {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            const button = document.getElementById('voiceLookupBtn');
            const resultBox = document.getElementById('voiceLookupResult');

            if (!SpeechRecognition) {
                resultBox.textContent = 'Trình duyệt không hỗ trợ nhận diện ging nói. Hãy dùng Chrome hoặc Edge.';
                return;
            }

            if (voiceRecognition) {
                voiceRecognition.stop();
                return;
            }

            voiceRecognition = new SpeechRecognition();
            voiceRecognition.lang = 'vi-VN';
            voiceRecognition.interimResults = false;
            voiceRecognition.maxAlternatives = 3;
            button.classList.add('is-listening');
            resultBox.textContent = 'ang nghe...';

            voiceRecognition.onresult = event => {
                const transcript = event.results[0][0].transcript;
                document.getElementById('voiceLookupInput').value = transcript;
                lookupWarehouseByVoice(transcript);
            };
            voiceRecognition.onerror = event => {
                resultBox.textContent = event.error === 'not-allowed'
                    ? 'Chưa được cấp quyn micro cho trình duyệt.'
                    : 'Không nghe rõ. Hãy thử nói lại.';
            };
            voiceRecognition.onend = () => {
                button.classList.remove('is-listening');
                voiceRecognition = null;
            };
            voiceRecognition.start();
        }

        function switchWorkspace(view) {
            document.querySelectorAll('[data-workspace-panel]').forEach(panel => {
                panel.classList.toggle('d-none', panel.dataset.workspacePanel !== view);
            });
            document.querySelectorAll('[data-workspace-view]').forEach(tab => {
                tab.classList.toggle('is-active', tab.dataset.workspaceView === view);
            });
            if (view === 'editor') {
                loadWarehouseMap();
                applyLayoutEditorSettings();
            }
            if (view === 'map3d') {
                loadWarehouseMap().then(renderWarehouse3D);
            }
            if (view === 'entry') loadReceipts();
            if (view === 'entry') {
                setWarehouseFlow('receipt');
                const firstReceiptInput = document.querySelector('.receipt-ma-sp');
                if (firstReceiptInput) firstReceiptInput.focus();
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }

        function escapeJs(text) {
            return String(text || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
        }

        function cssColorValue(valueText, fallback = '#f8fafc') {
            const raw = String(valueText || '').trim();
            if (!raw) return fallback;
            return raw.startsWith('#') ? raw : `#${raw}`;
        }

        function threadSpoolSvg(color, title) {
            const swatch = escapeHtml(cssColorValue(color));
            return `
                <svg class="thread-spool" viewBox="0 0 68 48" role="img" aria-label="${escapeHtml(title)}" style="--swatch:${swatch}">
                    <title>${escapeHtml(title)}</title>
                    <path class="thread-spool-body" d="M16 8h36c5 0 9 4 9 9v14c0 5-4 9-9 9H16c-5 0-9-4-9-9V17c0-5 4-9 9-9Z"/>
                    <path class="thread-spool-ring" d="M15 12h7c2 0 4 2 4 4v16c0 2-2 4-4 4h-7c-2 0-4-2-4-4V16c0-2 2-4 4-4Z"/>
                    <path class="thread-spool-ring" d="M46 12h7c2 0 4 2 4 4v16c0 2-2 4-4 4h-7c-2 0-4-2-4-4V16c0-2 2-4 4-4Z"/>
                    <path class="thread-spool-line" d="M29 14h10M29 20h10M29 26h10M29 32h10"/>
                </svg>
            `;
        }

        function hideProductResults() {
            const results = document.getElementById('maSpResults');
            if (results) results.classList.add('d-none');
        }

        function selectAccountingProduct(code) {
            const input = document.getElementById('maSp');
            if (!input) return;
            input.value = code;
            selectedAccountingProduct = code;
            hideProductResults();
        }

        function searchAccountingProducts() {
            if (!document.getElementById('maSp') || !document.getElementById('maSpResults')) return;
            const keyword = value('maSp');
            const results = document.getElementById('maSpResults');
            selectedAccountingProduct = '';
            clearTimeout(productSearchTimer);
            if (!keyword) {
                hideProductResults();
                return;
            }

            productSearchTimer = setTimeout(() => {
                fetch(`/api/thanh-pham-ke-toan/goi-y?keyword=${encodeURIComponent(keyword)}`)
                    .then(r => jsonOrError(r, 'Không tải được mã TP kế toán'))
                    .then(result => {
                        results.innerHTML = (result.data || []).map(item => `<button type="button" class="product-option" onclick="selectAccountingProduct('${String(item.Ma_sp || '').replace(/'/g, "\\'")}')">
                            <div class="product-option-code">${escapeHtml(item.Ma_sp)}</div>
                            <div class="product-option-name">${escapeHtml(item.Ten_hh || 'Chưa có tên hàng')}${item.Dvt ? ` · ${escapeHtml(item.Dvt)}` : ''}</div>
                        </button>`).join('') || '<div class="p-3 text-muted small">Không tìm thấy mã thành phẩm</div>';
                        results.classList.remove('d-none');
                    })
                    .catch(error => {
                        results.innerHTML = `<div class="p-3 text-danger small">${escapeHtml(error.message)}</div>`;
                        results.classList.remove('d-none');
                    });
            }, 250);
        }

        function searchReceiptProducts(input) {
            const keyword = input.value.trim();
            const options = document.getElementById('receiptProductOptions');
            clearTimeout(productSearchTimer);
            if (!keyword || !options) return;

            productSearchTimer = setTimeout(() => {
                fetch(`/api/thanh-pham-ke-toan/goi-y?keyword=${encodeURIComponent(keyword)}`)
                    .then(r => jsonOrError(r, 'Không tải được mã TP kế toán'))
                    .then(result => {
                        options.innerHTML = (result.data || []).map(item => {
                            const code = escapeHtml(item.Ma_sp || '');
                            const name = escapeHtml(item.Ten_hh || '');
                            const dvt = escapeHtml(item.Dvt || '');
                            return `<option value="${code}" label="${name}${dvt ? ' - ' + dvt : ''}"></option>`;
                        }).join('');
                    })
                    .catch(() => {});
            }, 250);
        }

        function renderInternalCatalogOptions() {
            const options = document.getElementById('internalCatalogOptions');
            if (!options) return;
            options.innerHTML = internalCatalogItems.map(item => {
                const label = [
                    item.name,
                    item.size ? `Size ${item.size}` : '',
                    item.color ? `Màu ${item.color}` : '',
                    item.logo_color ? `Màu in ${item.logo_color}` : '',
                    item.side ? `Mặt ${item.side}` : '',
                    item.unit,
                    item.shelf ? `Kệ ${item.shelf}` : '',
                    item.has_code ? '' : 'Chưa có mã'
                ].filter(Boolean).join(' · ');
                return `<option value="${escapeHtml(item.value || item.code || item.name || '')}" label="${escapeHtml(label)}"></option>`;
            }).join('');
        }

        function searchInternalCatalog(input) {
            const keyword = input.value.trim();
            clearTimeout(internalCatalogSearchTimer);
            if (!keyword) return;

            internalCatalogSearchTimer = setTimeout(() => {
                fetch(`/api/ma-noi-bo-danh-muc?keyword=${encodeURIComponent(keyword)}&limit=30`)
                    .then(r => jsonOrError(r, 'Không tải được DANH MỤC'))
                    .then(result => {
                        internalCatalogItems = result.data || [];
                        renderInternalCatalogOptions();
                    })
                    .catch(() => {});
            }, 180);
        }

        function findInternalCatalogItem(code) {
            const normalized = String(code || '').trim().toUpperCase();
            return internalCatalogItems.find(row => {
                return [row.code, row.value, row.name].some(value => String(value || '').trim().toUpperCase() === normalized);
            });
        }

        function fetchInternalCatalogExact(code) {
            const normalized = String(code || '').trim();
            if (!normalized) return Promise.resolve(null);
            const found = findInternalCatalogItem(normalized);
            if (found) return Promise.resolve(found);

            return fetch(`/api/ma-noi-bo-danh-muc?keyword=${encodeURIComponent(normalized)}&limit=10`)
                .then(r => jsonOrError(r, 'Không tải được DANH MỤC'))
                .then(result => {
                    const rows = result.data || [];
                    internalCatalogItems = [...rows, ...internalCatalogItems];
                    renderInternalCatalogOptions();
                    return findInternalCatalogItem(normalized);
                })
                .catch(() => null);
        }

        function applyInternalCatalog(input) {
            const item = findInternalCatalogItem(input.value);
            if (!item) return;

            const row = input.closest('tr');
            if (!row.querySelector('.receipt-note').value.trim()) row.querySelector('.receipt-note').value = item.name || '';
            if (!row.querySelector('.receipt-dvt').value.trim()) row.querySelector('.receipt-dvt').value = item.unit || '';
            if (!row.querySelector('.receipt-size').value.trim()) row.querySelector('.receipt-size').value = item.size || '';
            if (!row.querySelector('.receipt-color').value.trim()) row.querySelector('.receipt-color').value = item.color || '';
            if (item.shelf && !row.querySelector('.receipt-line-note').value.trim()) {
                row.querySelector('.receipt-line-note').value = `Kệ danh mục: ${item.shelf}`;
            }
            if (item.code) input.value = item.code;
        }

        function applyInternalCatalogAsync(input) {
            const code = input.value.trim();
            if (!code) return Promise.resolve();
            const found = findInternalCatalogItem(code);
            if (found) {
                applyInternalCatalog(input);
                return Promise.resolve();
            }
            return fetchInternalCatalogExact(code).then(item => {
                if (item) applyInternalCatalog(input);
            });
        }
        let productionOrderSearchTimer = null;
        let productionOrderOptions = [];

        function searchProductionOrders(input) {
            const keyword = input.value.trim();
            if (input.dataset.appliedOrder !== keyword.toUpperCase()) {
                delete input.dataset.appliedOrder;
            }
            clearTimeout(productionOrderSearchTimer);
            if (keyword.length < 2) return;

            productionOrderSearchTimer = setTimeout(() => {
                fetch(`/api/lenh-san-xuat-sheet?keyword=${encodeURIComponent(keyword)}&limit=20`)
                    .then(r => jsonOrError(r, 'Không tải được lệnh sản xuất'))
                    .then(result => {
                        productionOrderOptions = result.data || [];
                        document.getElementById('productionOrderOptions').innerHTML = productionOrderOptions.map(order => {
                            const label = [
                                order.customer,
                                order.item_code,
                                order.size ? `Size ${order.size}` : '',
                                order.color ? `Màu ${order.color}` : '',
                                order.description
                            ].filter(Boolean).join(' · ');
                            return `<option value="${escapeHtml(order.production_order)}" label="${escapeHtml(label)}"></option>`;
                        }).join('');
                        applyProductionOrder(input);
                    })
                    .catch(() => {});
            }, 220);
        }

        function applyProductionOrder(input) {
            const code = input.value.trim().toUpperCase();
            if (!code || input.dataset.appliedOrder === code || input.dataset.loadingOrder === code) return false;
            const hasExactMatch = productionOrderOptions.some(
                item => String(item.production_order || '').trim().toUpperCase() === code
            );
            if (!hasExactMatch) return false;

            input.dataset.loadingOrder = code;
            fetch(`/api/lenh-san-xuat-sheet?production_order=${encodeURIComponent(input.value.trim())}&limit=500`)
                .then(r => jsonOrError(r, 'Khong tai duoc chi tiet lenh san xuat'))
                .then(result => {
                    const variants = result.data || [];
                    if (variants.length) expandProductionOrder(input, variants);
                })
                .catch(error => alert(error.message))
                .finally(() => delete input.dataset.loadingOrder);
            return true;
        }

        function fillReceiptRow(row, order) {
            row.querySelector('.receipt-internal-code').value = order.item_code || '';
            row.querySelector('.receipt-note').value = order.description || order.specification || '';
            row.querySelector('.receipt-size').value = order.size || '';
            row.querySelector('.receipt-color').value = order.color || '';
            row.querySelector('.receipt-dvt').value = order.unit || '';
            row.querySelector('.receipt-quantity').value = Number(order.order_quantity || 0) || '';
            const orderInput = row.querySelector('.receipt-order');
            orderInput.value = order.production_order || '';
            orderInput.dataset.appliedOrder = String(order.production_order || '').trim().toUpperCase();
            orderInput.dataset.productionOrderId = order.id || '';
            orderInput.dataset.purchaseOrder = order.purchase_order || '';
            orderInput.dataset.customer = order.customer || '';
        }

        function receiptRowIsEmpty(row) {
            return !Array.from(row.querySelectorAll('input')).some(input => input.value.trim() !== '');
        }

        function appendReceiptRow() {
            const body = document.getElementById('receiptEntryRows');
            const row = body.lastElementChild.cloneNode(true);
            row.querySelectorAll('input').forEach(input => {
                input.value = '';
                delete input.dataset.appliedOrder;
                delete input.dataset.loadingOrder;
                delete input.dataset.productionOrderId;
                delete input.dataset.purchaseOrder;
                delete input.dataset.customer;
            });
            row.firstElementChild.textContent = body.children.length + 1;
            body.appendChild(row);
            return row;
        }

        function focusReceiptInput(input) {
            if (!input) return;
            input.focus();
            if (typeof input.select === 'function') input.select();
        }

        function moveReceiptEntryByEnter(input) {
            const row = input.closest('tr');
            if (!row) return;

            if (input.classList.contains('receipt-internal-code')) {
                applyInternalCatalog(input);
                focusReceiptInput(row.querySelector('.receipt-quantity'));
                return;
            }

            if (input.classList.contains('receipt-ma-sp')) {
                focusReceiptInput(row.querySelector('.receipt-quantity'));
                return;
            }

            if (input.classList.contains('receipt-color') || input.classList.contains('receipt-size')) {
                focusReceiptInput(row.querySelector('.receipt-quantity'));
                return;
            }

            if (input.classList.contains('receipt-quantity')) {
                let nextRow = row.nextElementSibling;
                if (!nextRow) nextRow = appendReceiptRow();
                focusReceiptInput(nextRow.querySelector('.receipt-internal-code'));
                return;
            }

            const inputs = Array.from(row.querySelectorAll('input'));
            const nextInput = inputs[inputs.indexOf(input) + 1];
            focusReceiptInput(nextInput || row.querySelector('.receipt-quantity'));
        }

        function expandProductionOrder(input, variants) {
            const currentRow = input.closest('tr');
            let rows = Array.from(document.querySelectorAll('#receiptEntryRows tr'));
            const currentIndex = rows.indexOf(currentRow);
            const targets = [currentRow];

            for (let index = currentIndex + 1; index < rows.length && targets.length < variants.length; index++) {
                if (receiptRowIsEmpty(rows[index])) targets.push(rows[index]);
            }

            while (targets.length < variants.length && rows.length < 50) {
                const row = appendReceiptRow();
                rows.push(row);
                targets.push(row);
            }

            variants.slice(0, targets.length).forEach((variant, index) => {
                fillReceiptRow(targets[index], variant);
            });

            if (targets.length < variants.length) {
                alert(`Lenh ${input.value} co ${variants.length} dong size/mau, form chi nhan toi da 50 dong.`);
            }
        }

        function receiptProductionOrder(row) {
            return row.querySelector('.receipt-order')?.value.trim() || '';
        }

        function receiptLineNote(row) {
            return row.querySelector('.receipt-line-note')?.value.trim() || '';
        }

        function collectReceiptLines() {
            return Array.from(document.querySelectorAll('#receiptEntryRows tr'))
                .map(row => ({
                    category: row.querySelector('.receipt-note')?.value.trim() || '',
                    ma_sp: row.querySelector('.receipt-ma-sp')?.value.trim() || '',
                    internal_item_code: row.querySelector('.receipt-internal-code')?.value.trim() || '',
                    size: row.querySelector('.receipt-size')?.value.trim() || '',
                    color: row.querySelector('.receipt-color')?.value.trim() || '',
                    side: '',
                    dvt: row.querySelector('.receipt-dvt')?.value.trim() || '',
                    quantity: row.querySelector('.receipt-quantity')?.value || '',
                    note: receiptLineNote(row),
                    production_order_id: row.querySelector('.receipt-order')?.dataset.productionOrderId || null,
                    production_order: receiptProductionOrder(row),
                    purchase_order: row.querySelector('.receipt-order')?.dataset.purchaseOrder || '',
                    customer: row.querySelector('.receipt-order')?.dataset.customer || '',
                }))
                .filter(line => line.ma_sp || line.internal_item_code || line.quantity);
        }

        function clearReceiptLines() {
            document.querySelectorAll('#receiptEntryRows input').forEach(input => {
                input.value = '';
                delete input.dataset.appliedOrder;
                delete input.dataset.loadingOrder;
                delete input.dataset.productionOrderId;
                delete input.dataset.purchaseOrder;
                delete input.dataset.customer;
            });
            document.getElementById('receiptHeaderNote').value = '';
            document.getElementById('receiptSendToProduction').checked = false;
        }

        function setReceiptEditMode(receipt = null) {
            editingReceiptFormId = receipt?.id || null;
            document.getElementById('cancelReceiptEditBtn').classList.toggle('d-none', !editingReceiptFormId);
            document.getElementById('receiptSendToProduction').disabled = !!editingReceiptFormId;
            if (editingReceiptFormId) document.getElementById('receiptSendToProduction').checked = false;
            document.getElementById('saveReceiptBatchBtn').innerHTML = editingReceiptFormId
                ? '<i data-lucide="save"></i>Cập nhật + in'
                : '<i data-lucide="printer"></i>Lưu + in';
            refreshIcons();
        }

        function fillReceiptEditForm(receipt) {
            setDateValue('receiptDate', receipt.receipt_date);
            document.getElementById('receiptLocationCode').value = receipt.location_code || '';
            document.getElementById('receiptHeaderNote').value = receipt.note || '';
            clearReceiptLines();

            const rows = Array.from(document.querySelectorAll('#receiptEntryRows tr'));
            (receipt.lines || []).forEach((line, index) => {
                let row = rows[index];
                if (!row) row = appendReceiptRow();
                row.querySelector('.receipt-note').value = line.ten_hh || '';
                row.querySelector('.receipt-internal-code').value = line.internal_item_code || '';
                row.querySelector('.receipt-ma-sp').value = line.ma_hh || '';
                row.querySelector('.receipt-color').value = line.color || '';
                row.querySelector('.receipt-size').value = line.size || '';
                row.querySelector('.receipt-quantity').value = line.quantity || '';
                row.querySelector('.receipt-dvt').value = line.dvt || '';
                const orderInput = row.querySelector('.receipt-order');
                orderInput.value = line.production_order || '';
                orderInput.dataset.productionOrderId = line.production_order_id || '';
                orderInput.dataset.purchaseOrder = line.purchase_order || '';
                orderInput.dataset.customer = line.customer || '';
                row.querySelector('.receipt-line-note').value = line.note || '';
            });

            setReceiptEditMode(receipt);
            switchWorkspace('entry');
            window.scrollTo({top: 0, behavior: 'smooth'});
        }

        function receiptQuantityInput(value) {
            const number = Number(value || 0);
            if (!Number.isFinite(number) || number <= 0) return '';
            return String(Math.round(number * 1000) / 1000);
        }

        function fillReceiptFromIssue(issue) {
            if (!issue || !Array.isArray(issue.lines)) return;
            setReceiptEditMode(null);
            clearReceiptLines();
            setWarehouseFlow('receipt');
            switchWorkspace('entry');

            const issueCode = issue.issue_code || '';
            document.getElementById('receiptHeaderNote').value = `Nhập thành phẩm từ phiếu xuất BTP ${issueCode}`;

            const rows = Array.from(document.querySelectorAll('#receiptEntryRows tr'));
            issue.lines.forEach((line, index) => {
                let row = rows[index];
                if (!row) row = appendReceiptRow();

                row.querySelector('.receipt-note').value = line.ten_hh || 'TP / KCS';
                row.querySelector('.receipt-internal-code').value = line.internal_item_code || '';
                row.querySelector('.receipt-ma-sp').value = line.ma_hh || '';
                row.querySelector('.receipt-color').value = line.color || '';
                row.querySelector('.receipt-size').value = line.size || '';
                row.querySelector('.receipt-quantity').value = receiptQuantityInput(line.quantity);
                row.querySelector('.receipt-dvt').value = line.dvt || 'Cái';

                const orderInput = row.querySelector('.receipt-order');
                orderInput.value = line.production_order || issue.production_order || '';
                orderInput.dataset.productionOrderId = line.production_order_id || '';
                orderInput.dataset.purchaseOrder = line.purchase_order || '';
                orderInput.dataset.customer = line.customer || '';

                const notes = [
                    line.note || '',
                    issueCode ? `Từ ${issueCode}` : '',
                    line.location_code ? `Vị trí xuất ${line.location_code}` : '',
                    line.side ? `Mặt ${line.side}` : '',
                ].filter(Boolean);
                row.querySelector('.receipt-line-note').value = notes.join(' - ');
            });

            document.querySelector('#receiptEntryRows .receipt-quantity')?.focus();
            scheduleReceiptDuplicateCheck();
            showWarehouseToast('ã nạp phiếu xuất BTP', 'Kiểm lại số lượng, vị trí nhập rồi bấm Lưu + in.');
        }

        function loadReceiptFromIssue(issueId) {
            if (!issueId) return;
            fetch(`/api/xuat-vat-tu-noi-bo/${encodeURIComponent(issueId)}`)
                .then(response => jsonOrError(response, 'Không tải được phiếu xuất BTP'))
                .then(result => fillReceiptFromIssue(result.data))
                .catch(error => alert(error.message));
        }

        function cancelReceiptEdit() {
            editingReceiptFormId = null;
            clearReceiptLines();
            setReceiptEditMode(null);
        }

        function syncReceiptLocationFromContext() {
            const receiptLocation = document.getElementById('receiptLocationCode');
            if (!receiptLocation.value.trim()) {
                receiptLocation.value = value('locationCode').toUpperCase();
            }
        }

        function selectedLocation() {
            const code = value('locationCode').toUpperCase();
            return locations.find(location => location.location_code === code);
        }

        function fillSelectedLocation() {
            const location = selectedLocation();
            if (!location) {
                if (!value('locationCode')) {
                    document.getElementById('entryLocationContext').textContent = 'Chưa chn vị trí: kiện sẽ lưu vào CHUA-XEP để xếp kệ sau.';
                }
                return;
            }
            document.getElementById('locationCode').value = location.location_code;
            document.getElementById('entryLocationContext').textContent = `ang nhập tại ${location.location_code} · Kho ${location.warehouse_code || '-'}`;
        }

        function setLocationStatus(message, isError = false) {
            const status = document.getElementById('locationSaveStatus');
            status.textContent = message;
            status.className = `small ${isError ? 'text-danger' : 'text-success'}`;
        }

        function jsonOrError(response, fallback) {
            return response.json().catch(() => ({})).then(result => {
                if (response.ok) return result;
                const validation = result.errors ? Object.values(result.errors).flat().join('\n') : '';
                throw new Error(validation || result.message || fallback);
            });
        }

        function loadLocations() {
            return fetch('/api/kiem-ton-kho/vi-tri').then(r => r.json()).then(result => {
                locations = result.data || [];
                document.getElementById('locationOptions').innerHTML = locations.map(x => `<option value="${x.location_code}">${x.location_name || ''}</option>`).join('');
                document.getElementById('locationCount').textContent = `${locations.length} vị trí`;
                document.getElementById('kpiLocations').textContent = formatNumber(locations.length);
                document.getElementById('kpiCountingLocations').textContent = formatNumber(locations.filter(x => x.status === 'counting').length);
                renderLocations();
                renderLayoutEditor();
                applyLayoutEditorSettings();
                fillSelectedLocation();
            });
        }

        function normalizeLayout(location, index) {
            if (isTierStackLocation(location)) {
                const base = tierStackBaseLocation(location);
                const rackNumber = physicalRackNumberForLocation(location) || index + 1;
                return {
                    x: Number(base?.grid_x || (((rackNumber - 1) % 6) * 4 + 1)),
                    y: Number(base?.grid_y || (Math.floor((rackNumber - 1) / 6) * 3 + 1)),
                    w: Number(base?.grid_w || location.grid_w || 4),
                    h: Number(base?.grid_h || location.grid_h || 2),
                };
            }

            return {
                x: Number(location.grid_x || ((index % 6) * 4 + 1)),
                y: Number(location.grid_y || (Math.floor(index / 6) * 3 + 1)),
                w: Number(location.grid_w || 4),
                h: Number(location.grid_h || 2),
            };
        }

        function isTierStackLocation(location) {
            return /^([A-Z])0*\d{1,4}$/.test(String(location?.location_code || '').toUpperCase().trim());
        }

        function physicalRackNumberForLocation(location) {
            const bay = Number(bayCodeForLocation(location) || 0);
            return bay > 0 ? Math.ceil(bay / 2) : 0;
        }

        function baySlotForLocation(location) {
            const bay = Number(bayCodeForLocation(location) || 0);
            return bay > 0 ? ((bay - 1) % 2) + 1 : 1;
        }

        function tierStackKey(location) {
            return `${lineForLocation(location)}|${physicalRackNumberForLocation(location)}`;
        }

        function tierStackBaseLocation(location) {
            if (!isTierStackLocation(location)) return location;
            const key = tierStackKey(location);
            return locations
                .filter(item => isTierStackLocation(item) && tierStackKey(item) === key)
                .sort((a, b) => Number(tierForLocationModel(a)) - Number(tierForLocationModel(b)))[0] || location;
        }

        function tierStackLocations(location) {
            if (!isTierStackLocation(location)) return [location];
            const key = tierStackKey(location);
            return locations.filter(item => isTierStackLocation(item) && tierStackKey(item) === key);
        }

        function applyLayoutEditorSettings() {
            const editor = document.getElementById('layoutEditor');
            if (!editor) return;
            if (!document.getElementById('layoutDragHint')) {
                editor.insertAdjacentHTML('afterbegin', '<div id="layoutDragHint" class="layout-drag-hint"></div>');
            }
            editor.style.setProperty('--warehouse-layout-bg', layoutBackgroundImage ? `url("${layoutBackgroundImage}")` : 'none');
            editor.style.setProperty('--warehouse-layout-bg-opacity', String(Math.max(0.1, Math.min(0.9, layoutBackgroundOpacity / 100))));
            editor.style.transform = `scale(${layoutZoom})`;
            const opacityInput = document.getElementById('layoutBackgroundOpacity');
            const zoomInput = document.getElementById('layoutZoom');
            if (opacityInput) opacityInput.value = String(layoutBackgroundOpacity);
            if (zoomInput) zoomInput.value = String(layoutZoom);
        }

        function setLayoutSaveStatus(message, type = 'secondary') {
            const el = document.getElementById('layoutSaveStatus');
            if (!el) return;
            el.textContent = message;
            el.className = `small text-${type} ms-auto`;
        }

        function packagesByLocationMap() {
            return mapPackages.reduce((map, item) => {
                const code = item.location?.location_code || '';
                if (!map[code]) map[code] = [];
                map[code].push(item);
                return map;
            }, {});
        }

        function stockSummaryForPackages(packages) {
            const byItem = packages.reduce((map, item) => {
                const code = item.internal_item_code || item.ma_sp || item.package_code || '';
                if (!map[code]) {
                    map[code] = {
                        code,
                        color: item.color_name || item.color || '',
                        pantone_hex: item.pantone_hex || '',
                        unit: item.catalog_unit || '',
                        quantity: 0,
                        package_count: 0,
                    };
                }
                map[code].quantity += Number(item.quantity || 0);
                map[code].package_count += 1;
                if (!map[code].color && (item.color_name || item.color)) map[code].color = item.color_name || item.color;
                if (!map[code].pantone_hex && item.pantone_hex) map[code].pantone_hex = item.pantone_hex;
                if (!map[code].unit && item.catalog_unit) map[code].unit = item.catalog_unit;
                return map;
            }, {});
            return Object.values(byItem).sort((a, b) => String(a.code).localeCompare(String(b.code)));
        }

        function renderLayoutEditor() {
            const editor = document.getElementById('layoutEditor');
            if (!editor) return;
            const keyword = value('mapSearch').toUpperCase();
            const selectedCode = value('locationCode').toUpperCase();
            const showEmpty = Boolean(document.getElementById('showEmptyLocations')?.checked);
            const packagesByLocation = packagesByLocationMap();
            const visibleLocations = locations.filter(location => {
                const packages = packagesByLocation[location.location_code] || [];
                const text = `${location.location_code} ${location.location_name || ''} ${packages.map(item => `${item.package_code} ${item.ma_sp} ${item.internal_item_code} ${item.size} ${item.color} ${item.side}`).join(' ')}`.toUpperCase();
                return packages.length > 0 || showEmpty || (keyword && text.includes(keyword));
            });
            editor.innerHTML = visibleLocations.map((location, index) => {
                const layout = normalizeLayout(location, index);
                const packages = packagesByLocation[location.location_code] || [];
                const itemSummaries = stockSummaryForPackages(packages);
                const totalQuantity = packages.reduce((sum, item) => sum + Number(item.quantity || 0), 0);
                const text = `${location.location_code} ${location.location_name || ''} ${packages.map(item => `${item.package_code} ${item.ma_sp} ${item.internal_item_code} ${item.size} ${item.color} ${item.side}`).join(' ')}`.toUpperCase();
                const isMatch = keyword && text.includes(keyword);
                const tierOffset = isTierStackLocation(location) ? Number(tierForLocationModel(location)) - 1 : 0;
                const hoverRows = itemSummaries.map(item => `<div class="layout-stock-row">
                    <span class="layout-swatch" style="--swatch:${escapeHtml(item.pantone_hex || '#f8fafc')}"></span>
                    <div><div class="layout-stock-code">${escapeHtml(item.code)}</div><div class="layout-stock-color">${escapeHtml(item.color || 'Chưa có màu')}</div></div>
                    <div class="layout-stock-qty">${formatNumber(item.quantity)}${item.unit ? ` ${escapeHtml(item.unit)}` : ''}</div>
                </div>`).join('');
                return `<div class="layout-block ${isTierStackLocation(location) ? 'is-tier-stack' : ''} ${packages.length ? 'has-stock' : ''} ${isMatch ? 'is-filter-match' : ''} ${location.location_code === selectedCode ? 'is-selected' : ''}" data-location-id="${location.id}" style="grid-column:${layout.x} / span ${layout.w}; grid-row:${layout.y} / span ${layout.h}; --tier-offset:${tierOffset}; z-index:${10 + tierOffset};">
                    <div class="layout-block-code">${escapeHtml(location.location_code)}</div>
                    <div class="layout-block-meta">Line ${escapeHtml(lineForLocation(location))} - Kệ ${escapeHtml(shelfForLocation(location))} - Tầng ${escapeHtml(tierForLocationModel(location))}${bayCodeForLocation(location) ? ` - Ô ${escapeHtml(bayCodeForLocation(location))}` : ''}</div>
                    ${packages.length ? `<div class="layout-block-count">${packages.length}</div><div class="layout-block-stock">SL ${formatNumber(totalQuantity)}</div><div class="layout-stock-hover"><div class="layout-stock-title"><span>${escapeHtml(location.location_code)}</span><span>${formatNumber(totalQuantity)}</span></div>${hoverRows}</div>` : ''}
                </div>`;
            }).join('') || '<div class="layout-empty-note">Không có vị trí nào có hàng theo bộ lc hiện tại.</div>';
            applyLayoutEditorSettings();
        }

        function warehouse3DVisibleLocations() {
            const keyword = value('mapSearch').toUpperCase();
            const showEmpty = Boolean(document.getElementById('showEmptyLocations')?.checked);
            const packagesByLocation = packagesByLocationMap();
            return locations.filter(location => {
                const packages = packagesByLocation[location.location_code] || [];
                const text = `${location.location_code} ${location.location_name || ''} ${packages.map(item => `${item.package_code} ${item.ma_sp} ${item.internal_item_code} ${item.size} ${item.color} ${item.side}`).join(' ')}`.toUpperCase();
                return packages.length > 0 || showEmpty || (keyword && text.includes(keyword));
            });
        }

        function colorFromHex(hex, fallback = 0x60a5fa) {
            const raw = String(hex || '').trim();
            if (!/^#[0-9a-f]{6}$/i.test(raw)) return fallback;
            return Number.parseInt(raw.slice(1), 16);
        }

        function makeTextSprite(text, options = {}) {
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');
            const fontSize = options.fontSize || 34;
            const label = String(text || '');
            context.font = `900 ${fontSize}px Arial`;
            const width = Math.ceil(context.measureText(label).width) + 28;
            canvas.width = Math.max(128, width);
            canvas.height = 64;
            context.font = `900 ${fontSize}px Arial`;
            context.textAlign = 'center';
            context.textBaseline = 'middle';
            context.fillStyle = 'rgba(255,255,255,.92)';
            context.strokeStyle = 'rgba(15,23,42,.22)';
            context.lineWidth = 3;
            roundCanvasRect(context, 3, 8, canvas.width - 6, canvas.height - 16, 10);
            context.fill();
            context.stroke();
            context.fillStyle = options.color || '#0f172a';
            context.fillText(label, canvas.width / 2, canvas.height / 2);
            const texture = new THREE.CanvasTexture(canvas);
            if (THREE.SRGBColorSpace) texture.colorSpace = THREE.SRGBColorSpace;
            const material = new THREE.SpriteMaterial({ map: texture, transparent: true });
            const sprite = new THREE.Sprite(material);
            sprite.scale.set(canvas.width / 44, canvas.height / 44, 1);
            return sprite;
        }

        function roundCanvasRect(context, x, y, width, height, radius) {
            context.beginPath();
            context.moveTo(x + radius, y);
            context.arcTo(x + width, y, x + width, y + height, radius);
            context.arcTo(x + width, y + height, x, y + height, radius);
            context.arcTo(x, y + height, x, y, radius);
            context.arcTo(x, y, x + width, y, radius);
            context.closePath();
        }

        function initWarehouse3D() {
            if (warehouse3d.initialized || !window.THREE) return Boolean(window.THREE);
            const canvas = document.getElementById('warehouse3dCanvas');
            if (!canvas) return false;

            warehouse3d.scene = new THREE.Scene();
            warehouse3d.scene.background = new THREE.Color(0xeef6ff);
            warehouse3d.camera = new THREE.PerspectiveCamera(45, 1, 0.1, 1000);
            warehouse3d.renderer = new THREE.WebGLRenderer({ canvas, antialias: true });
            warehouse3d.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
            warehouse3d.raycaster = new THREE.Raycaster();
            warehouse3d.pointer = new THREE.Vector2();
            warehouse3d.root = new THREE.Group();
            warehouse3d.scene.add(warehouse3d.root);

            const ambient = new THREE.HemisphereLight(0xffffff, 0x94a3b8, 1.65);
            warehouse3d.scene.add(ambient);
            const light = new THREE.DirectionalLight(0xffffff, 2.2);
            light.position.set(18, 32, 24);
            warehouse3d.scene.add(light);

            const grid = new THREE.GridHelper(48, 24, 0x94a3b8, 0xcbd5e1);
            grid.position.y = -0.02;
            warehouse3d.scene.add(grid);

            canvas.addEventListener('pointerdown', event => {
                warehouse3d.dragging = true;
                warehouse3d.lastX = event.clientX;
                warehouse3d.lastY = event.clientY;
                canvas.setPointerCapture(event.pointerId);
            });
            canvas.addEventListener('pointermove', event => {
                if (warehouse3d.dragging) {
                    const dx = event.clientX - warehouse3d.lastX;
                    const dy = event.clientY - warehouse3d.lastY;
                    warehouse3d.lastX = event.clientX;
                    warehouse3d.lastY = event.clientY;
                    warehouse3d.rotationY += dx * 0.008;
                    warehouse3d.rotationX = Math.max(-1.25, Math.min(-0.25, warehouse3d.rotationX + dy * 0.006));
                    updateWarehouse3DCamera();
                    return;
                }
                updateWarehouse3DHover(event);
            });
            canvas.addEventListener('pointerup', event => {
                warehouse3d.dragging = false;
                try { canvas.releasePointerCapture(event.pointerId); } catch (e) {}
            });
            canvas.addEventListener('pointerleave', () => {
                warehouse3d.dragging = false;
                setWarehouse3DHover(null);
            });
            canvas.addEventListener('wheel', event => {
                event.preventDefault();
                warehouse3d.distance = Math.max(16, Math.min(92, warehouse3d.distance + event.deltaY * 0.035));
                updateWarehouse3DCamera();
            }, { passive: false });
            canvas.addEventListener('click', () => {
                const location = warehouse3d.hovered?.userData?.location;
                if (location?.location_code) selectLocation(location.location_code);
            });
            window.addEventListener('resize', resizeWarehouse3D);

            warehouse3d.initialized = true;
            resizeWarehouse3D();
            updateWarehouse3DCamera();
            animateWarehouse3D();
            return true;
        }

        function resetWarehouse3DView() {
            warehouse3d.rotationX = -0.72;
            warehouse3d.rotationY = 0.72;
            warehouse3d.distance = 46;
            updateWarehouse3DCamera();
        }

        function updateWarehouse3DCamera() {
            if (!warehouse3d.camera) return;
            const radius = warehouse3d.distance;
            const cosX = Math.cos(warehouse3d.rotationX);
            warehouse3d.camera.position.set(
                Math.sin(warehouse3d.rotationY) * radius * cosX,
                Math.max(10, Math.abs(Math.sin(warehouse3d.rotationX)) * radius),
                Math.cos(warehouse3d.rotationY) * radius * cosX
            );
            warehouse3d.camera.lookAt(0, 0, 0);
        }

        function resizeWarehouse3D() {
            const wrap = document.getElementById('warehouse3dWrap');
            if (!wrap || !warehouse3d.renderer || !warehouse3d.camera) return;
            const width = Math.max(320, wrap.clientWidth);
            const height = Math.max(320, wrap.clientHeight);
            warehouse3d.renderer.setSize(width, height, false);
            warehouse3d.camera.aspect = width / height;
            warehouse3d.camera.updateProjectionMatrix();
        }

        function clearWarehouse3DScene() {
            if (!warehouse3d.root) return;
            warehouse3d.meshes = [];
            while (warehouse3d.root.children.length) {
                const child = warehouse3d.root.children[0];
                warehouse3d.root.remove(child);
                child.traverse?.(node => {
                    node.geometry?.dispose?.();
                    if (Array.isArray(node.material)) {
                        node.material.forEach(material => {
                            material.map?.dispose?.();
                            material.dispose?.();
                        });
                    } else {
                        node.material?.map?.dispose?.();
                        node.material?.dispose?.();
                    }
                });
            }
            setWarehouse3DHover(null);
        }

        function renderWarehouse3D() {
            renderRackFrontView();
            return;
        }

        function rackFrontGroups() {
            const keyword = value('mapSearch').toUpperCase();
            const showEmpty = Boolean(document.getElementById('showEmptyLocations')?.checked);
            const selectedCode = value('locationCode').toUpperCase();
            const packagesByLocation = packagesByLocationMap();
            const groupMap = new Map();

            locations.forEach((location, index) => {
                if (!isTierStackLocation(location)) return;
                const packages = packagesByLocation[location.location_code] || [];
                const itemText = packages.map(item => `${item.package_code} ${item.ma_sp} ${item.internal_item_code} ${item.size} ${item.color} ${item.side}`).join(' ');
                const text = `${location.location_code} ${location.location_name || ''} ${itemText}`.toUpperCase();
                const isOrderMatch = activeRackLocationCodes.has(normalizeRackLocationCode(location.location_code));
                const isSelectedLocation = normalizeRackLocationCode(location.location_code) === normalizeRackLocationCode(selectedCode);
                if (!(packages.length > 0 || showEmpty || isOrderMatch || isSelectedLocation || (keyword && text.includes(keyword)))) return;

                const line = lineForLocation(location);
                const rackNumber = physicalRackNumberForLocation(location);
                const slot = baySlotForLocation(location);
                const rackKey = `${line}|${rackNumber}`;
                if (!groupMap.has(rackKey)) {
                    const layout = normalizeLayout(tierStackBaseLocation(location), index);
                    groupMap.set(rackKey, {
                        rackKey,
                        line,
                        shelf: rackNumber,
                        layout,
                        tiers: new Map(),
                        totalQuantity: 0,
                        rowIndex: index,
                    });
                }

                const group = groupMap.get(rackKey);
                const tier = Number(tierForLocationModel(location) || 1);
                const itemSummaries = stockSummaryForPackages(packages);
                const totalQuantity = packages.reduce((sum, item) => sum + Number(item.quantity || 0), 0);
                group.totalQuantity += totalQuantity;
                if (!group.tiers.has(tier)) group.tiers.set(tier, new Map());
                group.tiers.get(tier).set(slot, {
                    location,
                    slot,
                    packages,
                    itemSummaries,
                    totalQuantity,
                    isMatch: Boolean(keyword && text.includes(keyword)),
                    isOrderMatch,
                    isSelected: isSelectedLocation,
                });
            });

            return Array.from(groupMap.values()).sort((a, b) => {
                const lineDiff = Number(a.line) - Number(b.line);
                if (lineDiff !== 0) return lineDiff;
                const shelfDiff = Number(a.shelf || 0) - Number(b.shelf || 0);
                if (shelfDiff !== 0) return shelfDiff;
                return String(a.rackKey).localeCompare(String(b.rackKey), undefined, { numeric: true });
            });
        }

        function renderRackFrontView() {
            const view = document.getElementById('rackFrontView');
            const empty = document.getElementById('warehouse3dEmpty');
            if (!view) return;
            const groups = rackFrontGroups();
            if (empty) empty.classList.toggle('d-none', groups.length > 0);

            const lines = groups.reduce((map, group) => {
                if (!map[group.line]) map[group.line] = [];
                map[group.line].push(group);
                return map;
            }, {});

            view.innerHTML = Object.keys(lines).sort((a, b) => Number(a) - Number(b)).map(line => {
                const cards = lines[line].map(group => {
                const tiers = [5, 4, 3, 2, 1].map(tier => {
                    const tierLetter = tierLetterForLine(group.line, tier);
                    const slots = [1, 2].map(slot => {
                        const tierData = group.tiers.get(tier)?.get(slot);
                        const bayNumber = ((Number(group.shelf) - 1) * 2) + slot;
                        const locationCode = tierData?.location?.location_code || `${tierLetter}${bayNumber}`;
                        const summaries = tierData?.itemSummaries || [];
                        const visibleColors = summaries.slice(0, 3).map(item => {
                            const title = `${item.code} - ${item.color || 'Chưa có màu'} - ${formatNumber(item.quantity)}${item.unit ? ` ${item.unit}` : ''}`;
                            return threadSpoolSvg(item.pantone_hex || item.color_hex || '#f8fafc', title);
                        }).join('');
                        const moreColors = summaries.length > 3
                            ? `<span class="rack-color-more" title="Còn ${summaries.length - 3} màu">+${summaries.length - 3}</span>`
                            : '';
                        const colorBoard = summaries.length
                            ? `<span class="rack-color-board">${visibleColors}${moreColors}</span>`
                            : '<span class="rack-tier-empty">Trống</span>';
                        const detailRows = (tierData?.itemSummaries || []).map(item => `
                            <div class="layout-stock-row">
                                <span class="layout-swatch" style="--swatch:${escapeHtml(cssColorValue(item.pantone_hex || item.color_hex || '#f8fafc'))}"></span>
                                <div><div class="layout-stock-code">${escapeHtml(item.code)}</div><div class="layout-stock-color">${escapeHtml(item.color || 'Chưa có màu')}</div></div>
                                <div class="layout-stock-qty">${formatNumber(item.quantity)}${item.unit ? ` ${escapeHtml(item.unit)}` : ''}</div>
                            </div>
                        `).join('');
                        const hoverTitle = `${locationCode} - ${summaries.length} mã hàng`;
                        const hoverMeta = tierData ? `Tổng ${formatNumber(tierData.totalQuantity)}` : '';
                        return `
                            <button type="button" class="rack-tier ${tierData?.packages?.length ? 'has-stock' : ''} ${tierData?.isSelected ? 'is-selected' : ''} ${tierData?.isMatch ? 'is-filter-match' : ''} ${tierData?.isOrderMatch ? 'is-order-match' : ''}"
                                data-rack-location="${escapeHtml(locationCode)}"
                                data-rack-hover-title="${escapeHtml(hoverTitle)}"
                                data-rack-hover-meta="${escapeHtml(hoverMeta)}"
                                data-rack-hover-detail="${escapeHtml(encodeURIComponent(detailRows))}"
                                onclick="openRackInventoryModal('${escapeJs(locationCode)}')">
                                <span class="rack-tier-body">
                                    <span class="rack-tier-code">${escapeHtml(locationCode)}</span>
                                    <span class="rack-tier-items">${colorBoard}</span>
                                </span>
                                <span class="rack-tier-qty">${tierData ? formatNumber(tierData.totalQuantity) : '-'}</span>
                                ${detailRows ? `<span class="rack-tier-detail">${detailRows}</span>` : ''}
                            </button>
                        `;
                    }).join('');
                    return `<div class="rack-tier-row">${slots}</div>`;
                }).join('');
                return `
                    <section class="rack-card">
                        <div class="rack-card-header">
                            <span class="rack-card-title">Line ${escapeHtml(group.line)} - Kệ ${escapeHtml(group.shelf)}</span>
                            <span class="rack-card-total">Tổng ${formatNumber(group.totalQuantity)}</span>
                        </div>
                        <div class="rack-shelves">${tiers}</div>
                    </section>
                `;
                }).join('');
                return `
                    <section class="rack-line">
                        <div class="rack-line-header"><span class="rack-line-title">Line ${escapeHtml(line)}</span></div>
                        <div class="rack-line-grid">${cards}</div>
                    </section>
                `;
            }).join('');
        }

        function scrollRackSearchIntoView() {
            const keyword = value('mapSearch').trim();
            if (!keyword) return;
            const wrap = document.querySelector('.rack-front-wrap');
            const target = document.querySelector('#rackFrontView .rack-tier.is-filter-match');
            if (!wrap || !target) return;

            requestAnimationFrame(() => {
                const wrapRect = wrap.getBoundingClientRect();
                const targetRect = target.getBoundingClientRect();
                const nextLeft = wrap.scrollLeft + (targetRect.left - wrapRect.left) - (wrap.clientWidth / 2) + (targetRect.width / 2);
                const nextTop = wrap.scrollTop + (targetRect.top - wrapRect.top) - 80;
                wrap.scrollTo({
                    left: Math.max(0, nextLeft),
                    top: Math.max(0, nextTop),
                    behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'
                });
                target.focus({ preventScroll: true });
            });
        }

        function moveRackHoverPreview(event) {
            const preview = document.getElementById('rackHoverPreview');
            if (!preview || !preview.classList.contains('is-visible')) return;
            const margin = 14;
            const rect = preview.getBoundingClientRect();
            let left = event.clientX + 18;
            let top = event.clientY + 18;
            if (left + rect.width + margin > window.innerWidth) left = event.clientX - rect.width - 18;
            if (top + rect.height + margin > window.innerHeight) top = window.innerHeight - rect.height - margin;
            preview.style.left = `${Math.max(margin, left)}px`;
            preview.style.top = `${Math.max(margin, top)}px`;
        }

        function showRackHoverPreview(button, event) {
            const preview = document.getElementById('rackHoverPreview');
            if (!preview) return;
            const detail = button.dataset.rackHoverDetail ? decodeURIComponent(button.dataset.rackHoverDetail) : '';
            if (!detail.trim()) return hideRackHoverPreview();
            preview.innerHTML = `
                <div class="rack-hover-preview-title">
                    <span>${escapeHtml(button.dataset.rackHoverTitle || 'Chi tiết kệ')}</span>
                    <span class="rack-hover-preview-meta">${escapeHtml(button.dataset.rackHoverMeta || '')}</span>
                </div>
                <div class="rack-hover-preview-grid">${detail}</div>
            `;
            preview.classList.add('is-visible');
            preview.setAttribute('aria-hidden', 'false');
            moveRackHoverPreview(event);
        }

        function hideRackHoverPreview() {
            const preview = document.getElementById('rackHoverPreview');
            if (!preview) return;
            preview.classList.remove('is-visible');
            preview.setAttribute('aria-hidden', 'true');
            rackHoverTarget = null;
        }

        function applyMapSearch(keyword) {
            const mapSearchInput = document.getElementById('mapSearch');
            if (!mapSearchInput) return;
            mapSearchInput.value = keyword || '';
            renderLayoutEditor();
            if (!document.getElementById('map3dPanel')?.classList.contains('d-none')) {
                renderWarehouse3D();
                scrollRackSearchIntoView();
            }
        }

        function normalizeRackLocationCode(code) {
            const value = String(code || '').trim().toUpperCase();
            const simple = value.match(/^([A-Z])0*(\d+)$/);
            return simple ? `${simple[1]}${Number(simple[2])}` : value;
        }

        function planLineLocations(line) {
            const raw = [];
            (Array.isArray(line?.locations) ? line.locations : []).forEach(location => {
                const code = typeof location === 'string' ? location : location?.location_code;
                if (code) raw.push({
                    location_code: String(code).trim().toUpperCase(),
                    quantity: Number(location?.quantity || 0),
                    color: String(location?.color || ''),
                    pantone_hex: String(location?.pantone_hex || '')
                });
            });
            if (!raw.length && line?.first_location) raw.push({ location_code: String(line.first_location).trim().toUpperCase(), quantity: 0, color: '', pantone_hex: '' });
            if (!raw.length && line?.catalog_shelf_code) raw.push({ location_code: String(line.catalog_shelf_code).trim().toUpperCase(), quantity: Number(line.stock_quantity || 0), color: '', pantone_hex: '' });
            return Array.from(new Map(raw.filter(row => row.location_code).map(row => [normalizeRackLocationCode(row.location_code), row])).values());
        }

        function renderRackProductionPlan(result) {
            const box = document.getElementById('rackProductionOrderResult');
            const status = document.getElementById('rackProductionOrderStatus');
            const clearButton = document.getElementById('rackProductionOrderClear');
            const order = result?.order || {};
            const lines = Array.isArray(result?.data) ? result.data : [];
            const summary = result?.summary || {};
            const missingBom = Array.isArray(summary.missing_bom_items) ? summary.missing_bom_items : [];

            activeRackProductionPlan = result;
            activeRackLocationCodes = new Set(lines.flatMap(line => planLineLocations(line).map(location => normalizeRackLocationCode(location.location_code))));
            clearButton?.classList.remove('d-none');
            if (status) status.textContent = activeRackLocationCodes.size
                ? `${activeRackLocationCodes.size} vị trí đang được làm sáng.`
                : 'Lệnh chưa có sợi được gán vị trí.';

            const lineHtml = lines.map(line => {
                const lineLocations = planLineLocations(line);
                const color = lineLocations.find(location => location.pantone_hex)?.pantone_hex || '#e2e8f0';
                const locationHtml = lineLocations.length
                    ? lineLocations.map(location => `<button type="button" class="rack-order-location" data-order-location="${escapeHtml(location.location_code)}">${escapeHtml(location.location_code)}${location.quantity > 0 ? ` · ${formatNumber(location.quantity)}` : ''}</button>`).join('')
                    : '<span class="rack-order-missing">Chưa có kệ</span>';
                const shortage = Number(line.shortage_quantity || 0);
                return `<article class="rack-order-line">
                    <span class="rack-order-line-swatch" style="--swatch:${escapeHtml(cssColorValue(color))}"></span>
                    <div class="min-width-0">
                        <div class="rack-order-line-code">${escapeHtml(line.material_code || '-')}</div>
                        <div class="rack-order-line-name" title="${escapeHtml(line.material_name || '')}">${escapeHtml(line.material_name || 'Chưa có tên danh mục')}</div>
                        <div class="rack-order-locations">${locationHtml}</div>
                    </div>
                    <div class="text-end">
                        <div class="rack-order-line-qty">Cần ${formatNumber(line.required_quantity || 0)} ${escapeHtml(line.unit || '')}</div>
                        <div class="${shortage > 0 ? 'rack-order-missing' : 'text-success small fw-bold'}">${shortage > 0 ? `Thiếu ${formatNumber(shortage)}` : 'Đủ tồn'}</div>
                    </div>
                </article>`;
            }).join('');

            const missingHtml = missingBom.length
                ? `<span class="rack-order-chip is-warn">Thiếu BOM: ${escapeHtml(missingBom.join(', '))}</span>`
                : '';
            box.innerHTML = `<div class="rack-order-summary">
                    <strong>${escapeHtml(order.production_order || order.order_code || '')}</strong>
                    ${order.customer ? `<span class="rack-order-chip">${escapeHtml(order.customer)}</span>` : ''}
                    <span class="rack-order-chip">${Number(summary.line_count || lines.length)} mã sợi</span>
                    <span class="rack-order-chip">${activeRackLocationCodes.size} vị trí</span>
                    ${missingHtml}
                </div>
                <div class="rack-order-lines">${lineHtml || `<div class="rack-order-missing">${missingBom.length ? 'Lệnh chưa có định mức sợi. Hãy khai báo BOM cho mã hàng trước.' : 'Không có dữ liệu sợi cho lệnh này.'}</div>`}</div>`;
            box.classList.remove('d-none');
            renderWarehouse3D();
            scrollFirstRackOrderLocationIntoView();
            refreshIcons();
        }

        function clearRackProductionPlan() {
            activeRackProductionPlan = null;
            activeRackLocationCodes = new Set();
            document.getElementById('rackProductionOrderInput').value = '';
            document.getElementById('rackProductionOrderResult').classList.add('d-none');
            document.getElementById('rackProductionOrderResult').innerHTML = '';
            document.getElementById('rackProductionOrderClear').classList.add('d-none');
            document.getElementById('rackProductionOrderStatus').textContent = 'Nhập lệnh để hiện toàn bộ kệ sợi cần lấy.';
            renderWarehouse3D();
            document.getElementById('rackProductionOrderInput').focus();
        }

        function scrollRackLocationIntoView(locationCode) {
            const normalized = normalizeRackLocationCode(locationCode);
            const target = Array.from(document.querySelectorAll('#rackFrontView [data-rack-location]'))
                .find(element => normalizeRackLocationCode(element.dataset.rackLocation) === normalized);
            const wrap = document.querySelector('.rack-front-wrap');
            if (!target || !wrap) {
                showWarehouseToast('Chưa thấy vị trí trên sơ đồ', `${locationCode} chưa có trong danh sách vị trí kho.`);
                return;
            }
            const wrapRect = wrap.getBoundingClientRect();
            const targetRect = target.getBoundingClientRect();
            wrap.scrollTo({
                left: Math.max(0, wrap.scrollLeft + targetRect.left - wrapRect.left - wrap.clientWidth / 2 + targetRect.width / 2),
                top: Math.max(0, wrap.scrollTop + targetRect.top - wrapRect.top - 90),
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'
            });
            target.focus({ preventScroll: true });
        }

        function scrollFirstRackOrderLocationIntoView() {
            const first = activeRackLocationCodes.values().next().value;
            if (first) requestAnimationFrame(() => scrollRackLocationIntoView(first));
        }

        async function loadRackProductionPlan() {
            const input = document.getElementById('rackProductionOrderInput');
            const status = document.getElementById('rackProductionOrderStatus');
            const orderCode = String(input?.value || '').trim().toUpperCase();
            if (!orderCode) return clearRackProductionPlan();
            const requestId = ++rackProductionOrderRequest;
            if (status) status.textContent = 'Đang tìm BOM và vị trí sợi...';
            try {
                const response = await fetch(`/api/lenh-det/production-order-plan?production_order=${encodeURIComponent(orderCode)}`, { headers: { Accept: 'application/json' } });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'Không tìm thấy lệnh sản xuất.');
                if (requestId !== rackProductionOrderRequest) return;
                input.value = result?.order?.production_order || result?.order?.order_code || orderCode;
                renderRackProductionPlan(result);
            } catch (error) {
                if (requestId !== rackProductionOrderRequest) return;
                activeRackProductionPlan = null;
                activeRackLocationCodes = new Set();
                document.getElementById('rackProductionOrderResult').classList.add('d-none');
                document.getElementById('rackProductionOrderClear').classList.add('d-none');
                if (status) status.textContent = error.message;
                renderWarehouse3D();
                showWarehouseToast('Không tải được lệnh', error.message);
            }
        }

        function suggestRackProductionOrders(keyword) {
            clearTimeout(rackProductionOrderTimer);
            const query = String(keyword || '').trim();
            if (query.length < 2) return;
            rackProductionOrderTimer = setTimeout(async () => {
                try {
                    const [mainResponse, weavingResponse] = await Promise.all([
                        fetch(`/api/lenh-det/production-orders?keyword=${encodeURIComponent(query)}&per_page=25`, { headers: { Accept: 'application/json' } }),
                        fetch(`/api/lenh-det/orders?keyword=${encodeURIComponent(query)}&per_page=25`, { headers: { Accept: 'application/json' } })
                    ]);
                    const [mainResult, weavingResult] = await Promise.all([mainResponse.json(), weavingResponse.json()]);
                    const suggestions = [...(mainResult.data || []), ...(weavingResult.data || [])]
                        .map(row => ({
                            code: row.production_order || row.order_code || '',
                            item: row.item_code || '',
                            customer: row.customer || ''
                        }))
                        .filter(row => row.code);
                    const unique = Array.from(new Map(suggestions.map(row => [String(row.code).toUpperCase(), row])).values()).slice(0, 40);
                    document.getElementById('rackProductionOrderOptions').innerHTML = unique.map(row =>
                        `<option value="${escapeHtml(row.code)}" label="${escapeHtml([row.item, row.customer].filter(Boolean).join(' · '))}"></option>`
                    ).join('');
                } catch (error) {
                    // Autocomplete is optional; Enter still performs an exact lookup.
                }
            }, 140);
        }

        function renderWarehouse3DScene() {
            const empty = document.getElementById('warehouse3dEmpty');
            if (!initWarehouse3D()) {
                if (empty) {
                    empty.classList.remove('d-none');
                    empty.textContent = 'Không tải được Three.js. Kiểm tra kết nối CDN hoặc dùng tab Sơ đồ kho 2D.';
                }
                return;
            }
            resizeWarehouse3D();
            clearWarehouse3DScene();
            const keyword = value('mapSearch').toUpperCase();
            const selectedCode = value('locationCode').toUpperCase();
            const packagesByLocation = packagesByLocationMap();
            const visibleLocations = warehouse3DVisibleLocations();
            if (empty) {
                empty.classList.toggle('d-none', visibleLocations.length > 0);
                empty.textContent = 'Không có kệ nào có hàng theo bộ lọc hiện tại.';
            }

            visibleLocations.forEach((location, index) => {
                const layout = normalizeLayout(location, index);
                const packages = packagesByLocation[location.location_code] || [];
                const itemSummaries = stockSummaryForPackages(packages);
                const totalQuantity = packages.reduce((sum, item) => sum + Number(item.quantity || 0), 0);
                const text = `${location.location_code} ${location.location_name || ''} ${packages.map(item => `${item.package_code} ${item.ma_sp} ${item.internal_item_code} ${item.size} ${item.color} ${item.side}`).join(' ')}`.toUpperCase();
                const isMatch = keyword && text.includes(keyword);
                const isSelected = location.location_code === selectedCode;
                const width = Math.max(1.2, layout.w * 1.05);
                const depth = Math.max(1.0, layout.h * 0.9);
                const isTierStack = isTierStackLocation(location);
                const tierIndex = isTierStack ? Number(tierForLocationModel(location)) - 1 : 0;
                const height = isTierStack
                    ? (packages.length ? 0.82 : 0.28)
                    : (packages.length ? Math.max(0.8, Math.min(5.6, 0.75 + Math.log10(totalQuantity + 1) * 1.35 + packages.length * 0.08)) : 0.35);
                const x = (layout.x - 12) * 1.42 + width / 2;
                const z = (layout.y - 20) * 1.1 + depth / 2;
                const color = isMatch ? 0x22c55e : (packages.length ? 0x60a5fa : 0xcbd5e1);
                const material = new THREE.MeshStandardMaterial({
                    color,
                    roughness: 0.62,
                    metalness: 0.08,
                    transparent: true,
                    opacity: packages.length ? 0.92 : 0.55,
                });
                const mesh = new THREE.Mesh(new THREE.BoxGeometry(width, height, depth), material);
                mesh.position.set(x, (isTierStack ? tierIndex * 0.9 : 0) + height / 2, z);
                mesh.userData = { location, packages, itemSummaries, totalQuantity, isMatch, isSelected };

                const edge = new THREE.LineSegments(
                    new THREE.EdgesGeometry(mesh.geometry),
                    new THREE.LineBasicMaterial({ color: isSelected ? 0x0f172a : 0xffffff, transparent: true, opacity: isSelected ? 0.95 : 0.55 })
                );
                mesh.add(edge);

                const label = makeTextSprite(location.location_code, { fontSize: 30, color: isSelected ? '#14532d' : '#0f172a' });
                label.position.set(0, height / 2 + 0.85, 0);
                mesh.add(label);

                warehouse3d.root.add(mesh);
                warehouse3d.meshes.push(mesh);
            });
            updateWarehouse3DCamera();
        }

        function updateWarehouse3DHover(event) {
            if (!warehouse3d.raycaster || !warehouse3d.camera || !warehouse3d.renderer) return;
            const rect = warehouse3d.renderer.domElement.getBoundingClientRect();
            warehouse3d.pointer.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
            warehouse3d.pointer.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;
            warehouse3d.raycaster.setFromCamera(warehouse3d.pointer, warehouse3d.camera);
            const hit = warehouse3d.raycaster.intersectObjects(warehouse3d.meshes, false)[0]?.object || null;
            setWarehouse3DHover(hit);
        }

        function setWarehouse3DHover(mesh) {
            if (warehouse3d.hovered === mesh) return;
            if (warehouse3d.hovered) warehouse3d.hovered.scale.set(1, 1, 1);
            warehouse3d.hovered = mesh;
            if (mesh) mesh.scale.set(1.04, 1.04, 1.04);
            renderWarehouse3DTooltip(mesh);
        }

        function renderWarehouse3DTooltip(mesh) {
            const tooltip = document.getElementById('warehouse3dTooltip');
            if (!tooltip) return;
            if (!mesh) {
                tooltip.classList.remove('is-visible');
                tooltip.innerHTML = '';
                return;
            }
            const { location, itemSummaries, totalQuantity, packages } = mesh.userData;
            const rows = itemSummaries.map(item => `
                <div class="warehouse-3d-item">
                    <span class="warehouse-3d-swatch" style="--swatch:${escapeHtml(item.pantone_hex || '#f8fafc')}"></span>
                    <div><div class="warehouse-3d-code">${escapeHtml(item.code)}</div><div class="warehouse-3d-color">${escapeHtml(item.color || 'Chưa có màu')}</div></div>
                    <div class="warehouse-3d-qty">${formatNumber(item.quantity)}${item.unit ? ` ${escapeHtml(item.unit)}` : ''}</div>
                </div>`).join('');
            tooltip.innerHTML = `
                <div class="warehouse-3d-title"><span>${escapeHtml(location.location_code)}</span><span>${formatNumber(totalQuantity)}</span></div>
                <div class="small text-secondary mt-2">Kệ ${escapeHtml(shelfForLocation(location))} · Tầng ${escapeHtml(tierForLocationModel(location))} · ${packages.length} dòng tồn</div>
                <div class="warehouse-3d-items">${rows || '<div class="text-secondary small">Kệ trống.</div>'}</div>
            `;
            tooltip.classList.add('is-visible');
        }

        function animateWarehouse3D() {
            if (!warehouse3d.renderer || !warehouse3d.scene || !warehouse3d.camera) return;
            warehouse3d.animationFrame = requestAnimationFrame(animateWarehouse3D);
            warehouse3d.renderer.render(warehouse3d.scene, warehouse3d.camera);
        }

        function saveLocationLayout(locationId, gridX, gridY, gridW, gridH) {
            return fetch(`/api/kiem-ton-kho/vi-tri/${locationId}/layout`, {
                method: 'PATCH',
                headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify({ grid_x: gridX, grid_y: gridY, grid_w: gridW, grid_h: gridH })
            }).then(r => jsonOrError(r, 'Không lưu được layout vị trí'));
        }

        function loadWarehouseStats() {
            const params = new URLSearchParams({ checked_at: value('checkedAt') });
            return fetch(`/api/kiem-ton-kho/kien?${params}`).then(r => r.json()).then(result => {
                document.getElementById('kpiPackages').textContent = formatNumber(result.summary?.package_count);
                document.getElementById('kpiQuantity').textContent = formatNumber(result.summary?.total_quantity);
            });
        }

        const warehouseShelves = [
            { code: 'G', name: 'GRS' },
            { code: 'F', name: 'NPL khác' },
            { code: 'D', name: 'NPL khác' },
            { code: 'C', name: 'Thành phẩm' },
            { code: 'B', name: 'Thành phẩm' },
            { code: 'A', name: 'NPL sợi + su' },
        ];

        function shelfCodeForLocation(locationCode) {
            const code = String(locationCode || '').toUpperCase().trim();
            const tierRack = code.match(/^([A-Z])0*(\d{1,4})$/);
            if (tierRack) return String(Number(tierRack[2]));
            const match = code.match(/[A-Z]/);
            return match ? match[0] : 'KHAC';
        }

        function shelfForLocation(location) {
            if (isTierStackLocation(location)) return String(physicalRackNumberForLocation(location) || '');
            return shelfCodeForLocation(location?.location_code) || location?.shelf_code || 'KHAC';
        }

        function tierForLocationModel(location) {
            return String(tierForLocation(location?.location_code) || location?.tier || 1);
        }

        function tierForLocation(locationCode) {
            const code = String(locationCode || '').toUpperCase();
            const tierRack = code.match(/^([A-Z])0*\d{1,4}$/);
            if (tierRack) {
                const letterIndex = tierRack[1].charCodeAt(0) - 65;
                return String(5 - (letterIndex % 5));
            }
            return /(^|[-_\s])T?2($|[-_\s])|TANG\s*2|TẦNG\s*2/.test(code) ? '2' : '1';
        }

        function lineForLocation(location) {
            const code = String(location?.location_code || '').toUpperCase().trim();
            const match = code.match(/^([A-Z])0*\d{1,4}$/);
            if (!match) return '1';
            return String(Math.floor((match[1].charCodeAt(0) - 65) / 5) + 1);
        }

        function tierLetterForLine(line, tier) {
            const index = (Number(line || 1) - 1) * 5 + (5 - Number(tier || 1));
            return index >= 0 && index < 26 ? String.fromCharCode(65 + index) : '';
        }

        function bayCodeForLocation(location) {
            const code = String(location?.location_code || '').toUpperCase().trim();
            const match = code.match(/(\d+)$/);
            return match ? String(Number(match[1])) : (location?.bay_code || '');
        }

        function loadWarehouseMap() {
            const params = new URLSearchParams({ checked_at: value('checkedAt') });
            return fetch(`/api/kiem-ton-kho/so-do-ton?${params}`).then(r => r.json()).then(result => {
                mapPackages = result.data || [];
                renderLayoutEditor();
                if (!document.getElementById('map3dPanel')?.classList.contains('d-none')) {
                    renderWarehouse3D();
                    scrollRackSearchIntoView();
                }
            });
        }

        function renderLocations() {
            const keyword = value('locationSearch').toUpperCase();
            const selectedCode = value('locationCode').toUpperCase();
            const filtered = locations.filter(x => `${x.location_code} ${x.warehouse_code || ''} ${x.location_name || ''}`.toUpperCase().includes(keyword));
            document.getElementById('locationRows').innerHTML = filtered.map(x => `
                <div class="location-item ${x.location_code === selectedCode ? 'is-active' : ''}">
                    <button type="button" class="btn p-0 border-0 text-start flex-grow-1" onclick="selectLocation('${x.location_code}')">
                        <div class="location-code">${x.location_code}</div>
                        <div class="location-meta">Line ${escapeHtml(lineForLocation(x))} · Kệ ${escapeHtml(shelfForLocation(x))} · Tầng ${escapeHtml(tierForLocationModel(x))}${x.location_name ? ` · ${escapeHtml(x.location_name)}` : ''}</div>
                    </button>
                    <div class="location-actions">
                        <a class="btn btn-outline-primary" title="Xem chi tiết vị trí" href="/client/kiem-ton-kho/vi-tri/${x.id}" target="_blank"><i data-lucide="eye"></i></a>
                        <button type="button" class="btn btn-outline-secondary" title="Quản lý vị trí" onclick="openLocationModal('${x.location_code}')"><i data-lucide="settings-2"></i></button>
                    </div>
                </div>`).join('') || '<div class="empty-state text-center">Không tìm thấy vị trí</div>';
            refreshIcons();
        }

        function selectLocation(locationCode) {
            document.getElementById('locationCode').value = locationCode;
            document.getElementById('receiptLocationCode').value = locationCode;
            fillSelectedLocation();
            renderLocations();
            renderLayoutEditor();
            if (!document.getElementById('map3dPanel')?.classList.contains('d-none')) {
                renderWarehouse3D();
                scrollRackSearchIntoView();
            }
            loadPackages();
            loadLocationContents();
        }

        function openLocationModal(locationCode = '') {
            const location = locations.find(item => item.location_code === locationCode) || selectedLocation();
            editingLocationId = location?.id || null;
            document.getElementById('locationModalTitle').textContent = location ? 'Chỉnh sửa vị trí kho' : 'Thêm vị trí kho';
            document.getElementById('editLocationCode').value = location?.location_code || '';
            document.getElementById('editShelfCode').value = location ? shelfForLocation(location) : '';
            document.getElementById('editTier').value = location ? tierForLocationModel(location) : 1;
            document.getElementById('editBayCode').value = location ? bayCodeForLocation(location) : '';
            document.getElementById('editGridW').value = Number(location?.grid_w || 4);
            document.getElementById('editGridH').value = Number(location?.grid_h || 2);
            document.getElementById('editGridPreset').value = `${Number(location?.grid_w || 4)}x${Number(location?.grid_h || 2)}`;
            document.getElementById('editLocationName').value = location?.location_name || '';
            document.getElementById('deleteLocationBtn').classList.toggle('d-none', !location);
            document.getElementById('useLocationBtn').classList.toggle('d-none', !location);
            document.getElementById('printLocationBtn').classList.toggle('d-none', !location);
            setLocationStatus('');
            locationModal.show();
        }

        function bulkLocationPayload() {
            return {
                shelf_from: value('bulkShelfFrom').toUpperCase(),
                shelf_to: value('bulkShelfTo').toUpperCase(),
                number_from: Number(value('bulkNumberFrom') || 0),
                number_to: Number(value('bulkNumberTo') || 0),
                warehouse_code: '',
                tier: Number(value('bulkTier') || 1),
                name_prefix: value('bulkNamePrefix') || 'Kệ',
            };
        }

        function updateBulkLocationPreview() {
            const data = bulkLocationPayload();
            const fromShelf = data.shelf_from.charCodeAt(0);
            const toShelf = data.shelf_to.charCodeAt(0);
            const shelfCount = fromShelf >= 65 && toShelf >= fromShelf ? (toShelf - fromShelf + 1) : 0;
            const numberCount = data.number_to >= data.number_from ? (data.number_to - data.number_from + 1) : 0;
            const total = shelfCount * numberCount;
            const first = data.shelf_from && data.number_from ? `${data.shelf_from}${data.number_from}` : '';
            const last = data.shelf_to && data.number_to ? `${data.shelf_to}${data.number_to}` : '';
            document.getElementById('bulkLocationPreview').textContent = total
                ? `Sẽ tạo ${formatNumber(total)} vị trí: ${first} ... ${last}.`
                : 'Nhập dãy kệ và số hợp lệ để xem trước.';
        }

        function openBulkLocationModal() {
            document.getElementById('bulkLocationStatus').textContent = '';
            updateBulkLocationPreview();
            bulkLocationModal.show();
        }

        function bulkPrintLocationPayload() {
            return {
                shelf_from: value('printShelfFrom').toUpperCase(),
                shelf_to: value('printShelfTo').toUpperCase(),
                number_from: Number(value('printNumberFrom') || 0),
                number_to: Number(value('printNumberTo') || 0),
            };
        }

        function updateBulkPrintLocationPreview() {
            const data = bulkPrintLocationPayload();
            const fromShelf = data.shelf_from.charCodeAt(0);
            const toShelf = data.shelf_to.charCodeAt(0);
            const shelfCount = fromShelf >= 65 && toShelf >= fromShelf ? (toShelf - fromShelf + 1) : 0;
            const numberCount = data.number_to >= data.number_from ? (data.number_to - data.number_from + 1) : 0;
            const total = shelfCount * numberCount;
            const first = data.shelf_from && data.number_from ? `${data.shelf_from}${data.number_from}` : '';
            const last = data.shelf_to && data.number_to ? `${data.shelf_to}${data.number_to}` : '';
            document.getElementById('bulkPrintLocationPreview').textContent = total
                ? `Sẽ mở trang in ${formatNumber(total)} tem QR: ${first} ... ${last}.`
                : 'Nhập dãy vị trí hợp lệ để xem trước.';
        }

        function openBulkPrintLocationModal() {
            const source = bulkLocationPayload();
            document.getElementById('printShelfFrom').value = source.shelf_from || 'A';
            document.getElementById('printShelfTo').value = source.shelf_to || 'D';
            document.getElementById('printNumberFrom').value = source.number_from || 1;
            document.getElementById('printNumberTo').value = source.number_to || 100;
            updateBulkPrintLocationPreview();
            bulkPrintLocationModal.show();
        }

        function openMovePackageModal(packageId) {
            const item = mapPackages.find(packageItem => String(packageItem.id) === String(packageId));
            movingPackageId = packageId;
            document.getElementById('movePackageTitle').textContent = item ? `${item.package_code} · ${item.internal_item_code || item.ma_sp}` : '';
            document.getElementById('moveTargetLocationId').innerHTML = locations.map(location => `<option value="${location.id}" ${item?.warehouse_location_id === location.id ? 'selected' : ''}>${escapeHtml(location.location_code)} · Line ${escapeHtml(lineForLocation(location))} · Kệ ${escapeHtml(shelfForLocation(location))} · Tầng ${escapeHtml(tierForLocationModel(location))}</option>`).join('');
            movePackageModal.show();
        }

        function movePackageToLocation(packageId, locationId) {
            return fetch(`/api/kiem-ton-kho/kien/${packageId}/chuyen-vi-tri`, {
                method: 'PATCH',
                headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify({ warehouse_location_id: locationId })
            }).then(r => jsonOrError(r, 'Không chuyển được kiện'));
        }

        function openRackInventoryModal(locationCode) {
            rackCrudLocationCode = String(locationCode || '').toUpperCase();
            if (!rackCrudLocationCode) return;
            selectLocation(rackCrudLocationCode);
            resetRackCrudForm();
            document.getElementById('rackCrudLocationLabel').textContent = `Kệ ${rackCrudLocationCode}`;
            document.getElementById('rackCrudLocationCode').value = rackCrudLocationCode;
            document.getElementById('rackCrudStatus').textContent = 'Đang tải hàng trong kệ...';
            document.getElementById('rackCrudRows').innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Đang tải...</td></tr>';
            rackInventoryModal.show();
            refreshIcons();
            loadRackInventoryRows();
        }

        function rackCatalogOptionLabel(item) {
            return [
                item.name,
                item.unit,
                item.size ? `Size ${item.size}` : '',
                item.color ? `Màu ${item.color}` : '',
                item.shelf ? `Kệ ${item.shelf}` : ''
            ].filter(Boolean).join(' · ');
        }

        function renderRackCatalogOptions(rows = internalCatalogItems) {
            const options = document.getElementById('rackCrudCatalogOptions');
            if (!options) return;
            options.innerHTML = (rows || []).map(item => {
                const valueText = item.value || item.code || item.name || '';
                return `<option value="${escapeHtml(valueText)}" label="${escapeHtml(rackCatalogOptionLabel(item))}"></option>`;
            }).join('');
        }

        function findRackCatalogItem(code) {
            const normalized = String(code || '').trim().toUpperCase();
            return internalCatalogItems.find(item => {
                return [item.code, item.value, item.name].some(valueText => String(valueText || '').trim().toUpperCase() === normalized);
            });
        }

        function searchRackCatalog() {
            const keyword = value('rackCrudItemCode').trim();
            clearTimeout(rackCrudSearchTimer);
            if (!keyword) return;
            rackCrudSearchTimer = setTimeout(() => {
                fetch(`/api/ma-noi-bo-danh-muc?keyword=${encodeURIComponent(keyword)}&limit=50`)
                    .then(r => jsonOrError(r, 'Không tải được DANH MỤC'))
                    .then(result => {
                        internalCatalogItems = [...(result.data || []), ...internalCatalogItems];
                        renderRackCatalogOptions(result.data || []);
                        applyRackCatalogSelection();
                    })
                    .catch(() => {});
            }, 180);
        }

        function applyRackCatalogSelection() {
            const item = findRackCatalogItem(value('rackCrudItemCode'));
            if (!item) {
                document.getElementById('rackCrudItemName').value = '';
                document.getElementById('rackCrudUnit').value = '';
                return;
            }
            document.getElementById('rackCrudItemCode').value = item.code || item.value || '';
            document.getElementById('rackCrudItemName').value = item.name || '';
            document.getElementById('rackCrudUnit').value = item.unit || '';
            if (!value('rackCrudSize')) document.getElementById('rackCrudSize').value = item.size || '';
            if (!value('rackCrudColor')) document.getElementById('rackCrudColor').value = item.color || '';
        }

        function resetRackCrudForm() {
            const keepLocation = rackCrudLocationCode || value('rackCrudLocationCode');
            ['rackCrudPackageId', 'rackCrudItemCode', 'rackCrudItemName', 'rackCrudQuantity', 'rackCrudUnit', 'rackCrudSize', 'rackCrudColor'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
            document.getElementById('rackCrudLocationCode').value = keepLocation || '';
            document.getElementById('rackCrudStatus').textContent = '';
        }

        function loadRackInventoryRows() {
            if (!rackCrudLocationCode) return;
            const packageParams = new URLSearchParams({ location_code: rackCrudLocationCode, limit: 1000 });
            const stockParams = new URLSearchParams({ checked_at: value('checkedAt') || localIsoDate() });
            Promise.all([
                fetch(`/api/kiem-ton-kho/kien?${packageParams}`).then(r => jsonOrError(r, 'Không tải được kiện trong kệ')),
                fetch(`/api/kiem-ton-kho/so-do-ton?${stockParams}`).then(r => jsonOrError(r, 'Không tải được tồn tổng hợp trong kệ'))
            ])
                .then(([packageResult, stockResult]) => {
                    const packages = packageResult.data || [];
                    const stockRows = (stockResult.data || []).filter(item => String(item.location?.location_code || item.location_code || '').toUpperCase() === rackCrudLocationCode);
                    const packageByKey = packages.reduce((map, item) => {
                        const key = rackInventoryKey(item);
                        if (!map[key]) map[key] = item;
                        return map;
                    }, {});
                    const seenPackageIds = new Set();

                    rackCrudItems = stockRows.map(item => {
                        const key = rackInventoryKey(item);
                        const packageItem = packageByKey[key];
                        if (packageItem?.id) seenPackageIds.add(String(packageItem.id));
                        return {
                            ...item,
                            id: packageItem?.id || item.id,
                            package_id: packageItem?.id || null,
                            package_code: packageItem?.package_code || 'Tồn tổng hợp',
                            note: packageItem?.note || item.catalog_name || '',
                            source_type: packageItem?.id ? 'package' : 'ledger',
                        };
                    });

                    packages.forEach(item => {
                        if (!seenPackageIds.has(String(item.id))) {
                            rackCrudItems.push({ ...item, package_id: item.id, source_type: 'package' });
                        }
                    });

                    const totalQuantity = rackCrudItems.reduce((sum, item) => sum + Number(item.quantity || 0), 0);
                    document.getElementById('rackCrudStatus').textContent = `${formatNumber(rackCrudItems.length)} dòng · Tổng ${formatNumber(totalQuantity)}`;
                    document.getElementById('rackCrudRows').innerHTML = rackCrudItems.map((item, index) => {
                        const swatch = item.pantone_hex ? `<span class="rack-crud-swatch" style="--swatch:${escapeHtml(cssColorValue(item.pantone_hex))}"></span>` : '';
                        const actions = item.package_id
                            ? `<button type="button" class="btn btn-sm btn-outline-secondary" onclick="editRackInventoryItem(${item.package_id})">Sửa</button>
                               <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteRackInventoryItem(${item.package_id})">Xóa</button>`
                            : `<button type="button" class="btn btn-sm btn-outline-primary" onclick="copyRackInventoryLedgerItem(${index})">Tạo điều chỉnh</button>`;
                        return `<tr>
                            <td><strong>${escapeHtml(item.internal_item_code || item.ma_sp || '')}</strong></td>
                            <td>${escapeHtml(item.catalog_name || item.note || '')}</td>
                            <td class="text-end fw-bold">${formatNumber(item.quantity || 0)}</td>
                            <td>${escapeHtml(item.catalog_unit || '')}</td>
                            <td>${escapeHtml(item.size || '')}</td>
                            <td>${swatch} ${escapeHtml(item.color || '')}</td>
                            <td class="text-end text-nowrap">${actions}</td>
                        </tr>`;
                    }).join('') || '<tr><td colspan="7" class="text-center text-muted py-3">Kệ này chưa có hàng.</td></tr>';
                    refreshIcons();
                })
                .catch(error => {
                    document.getElementById('rackCrudStatus').textContent = error.message;
                    document.getElementById('rackCrudRows').innerHTML = `<tr><td colspan="7" class="text-center text-danger py-3">${escapeHtml(error.message)}</td></tr>`;
                });
        }

        function rackInventoryKey(item) {
            return [
                item.internal_item_code || item.ma_sp || '',
                item.size || '',
                item.color || '',
                item.side || ''
            ].map(valueText => String(valueText || '').trim().toUpperCase()).join('|');
        }

        function editRackInventoryItem(packageId) {
            const item = rackCrudItems.find(row => Number(row.package_id || row.id) === Number(packageId));
            if (!item) return;
            document.getElementById('rackCrudPackageId').value = item.package_id || item.id;
            document.getElementById('rackCrudLocationCode').value = rackCrudLocationCode;
            document.getElementById('rackCrudItemCode').value = item.internal_item_code || item.ma_sp || '';
            document.getElementById('rackCrudItemName').value = item.catalog_name || item.note || '';
            document.getElementById('rackCrudQuantity').value = item.quantity || 0;
            document.getElementById('rackCrudUnit').value = item.catalog_unit || '';
            document.getElementById('rackCrudSize').value = item.size || '';
            document.getElementById('rackCrudColor').value = item.color || '';
            document.getElementById('rackCrudStatus').textContent = `Đang sửa ${item.package_code || ''}`;
        }

        function copyRackInventoryLedgerItem(index) {
            const item = rackCrudItems[index];
            if (!item) return;
            document.getElementById('rackCrudPackageId').value = '';
            document.getElementById('rackCrudLocationCode').value = rackCrudLocationCode;
            document.getElementById('rackCrudItemCode').value = item.internal_item_code || item.ma_sp || '';
            document.getElementById('rackCrudItemName').value = item.catalog_name || item.note || '';
            document.getElementById('rackCrudQuantity').value = item.quantity || 0;
            document.getElementById('rackCrudUnit').value = item.catalog_unit || '';
            document.getElementById('rackCrudSize').value = item.size || '';
            document.getElementById('rackCrudColor').value = item.color || '';
            document.getElementById('rackCrudStatus').textContent = 'Đang tạo dòng điều chỉnh từ tồn tổng hợp.';
        }

        function rackCrudPayload() {
            const item = findRackCatalogItem(value('rackCrudItemCode'));
            const code = (item?.code || value('rackCrudItemCode')).trim();
            return {
                location_code: rackCrudLocationCode,
                ma_sp: '',
                internal_item_code: code,
                size: value('rackCrudSize'),
                color: value('rackCrudColor'),
                side: '',
                quantity: Number(value('rackCrudQuantity') || 0),
                checked_at: value('checkedAt') || new Date().toISOString().slice(0, 10),
                entry_type: 'opening',
                note: item?.name || value('rackCrudItemName') || ''
            };
        }

        function saveRackInventoryItem(event) {
            event.preventDefault();
            applyRackCatalogSelection();
            const payload = rackCrudPayload();
            if (!payload.internal_item_code) return alert('Nhập mã hàng hóa từ DANH MỤC.');
            if (!(payload.quantity > 0)) return alert('Nhập số lượng lớn hơn 0.');
            const packageId = value('rackCrudPackageId');
            fetch(packageId ? `/api/kiem-ton-kho/kien/${packageId}` : '/api/kiem-ton-kho/kien', {
                method: packageId ? 'PATCH' : 'POST',
                headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify(payload)
            }).then(r => jsonOrError(r, packageId ? 'Không cập nhật được hàng trong kệ' : 'Không thêm được hàng vào kệ'))
              .then(() => {
                  resetRackCrudForm();
                  loadRackInventoryRows();
                  loadPackages();
                  loadLocations();
                  loadWarehouseStats();
                  loadWarehouseMap();
                  loadLocationContents();
              })
              .catch(error => alert(error.message));
        }

        function deleteRackInventoryItem(packageId) {
            const item = rackCrudItems.find(row => Number(row.id) === Number(packageId));
            if (!confirm(`Xóa ${item?.internal_item_code || item?.package_code || 'dòng này'} khỏi kệ ${rackCrudLocationCode}?`)) return;
            fetch(`/api/kiem-ton-kho/kien/${packageId}`, {
                method: 'DELETE',
                headers: {'Accept':'application/json','X-CSRF-TOKEN':csrfToken}
            }).then(r => jsonOrError(r, 'Không xóa được hàng trong kệ'))
              .then(() => {
                  resetRackCrudForm();
                  loadRackInventoryRows();
                  loadPackages();
                  loadLocations();
                  loadWarehouseStats();
                  loadWarehouseMap();
                  loadLocationContents();
              })
              .catch(error => alert(error.message));
        }

        function loadPackages() {
            const params = new URLSearchParams({ checked_at: value('checkedAt') });
            if (value('locationCode')) params.set('location_code', value('locationCode').toUpperCase());
            fetch(`/api/kiem-ton-kho/kien?${params}`).then(r => r.json()).then(result => {
                document.getElementById('packageRows').innerHTML = (result.data || []).map(x => `<tr>
                    <td>${x.package_code}</td><td>${x.location?.location_code || ''}</td><td>${x.ma_sp}</td><td>${x.internal_item_code}</td>
                    <td>${x.size}</td><td>${x.color}</td><td>${x.side}</td><td>${x.quantity}</td>
                    <td class="text-nowrap">
                        <a class="btn btn-sm btn-outline-secondary btn-icon" target="_blank" href="/client/kiem-ton-kho/tem-kien/${x.id}"><i data-lucide="printer"></i>In</a>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-package-btn" data-id="${x.id}" data-code="${x.package_code}" title="Xóa kiện"><i data-lucide="trash-2"></i></button>
                    </td>
                </tr>`).join('');
                refreshIcons();
            });
        }

        function renderReceiptRow(receipt) {
            const receiptStatus = receipt.issue_status === 'exported'
                ? `<span class="badge text-bg-success">Đã xuất hết · ${formatNumber(receipt.fifo_issued_quantity || 0)}</span>`
                : receipt.issue_status === 'partial_exported'
                    ? `<span class="badge text-bg-warning">Xuất một phần · còn ${formatNumber(receipt.fifo_remaining_quantity || 0)}</span>`
                    : '<span class="badge text-bg-secondary">Chưa xuất</span>';
            const issueButtonLabel = receipt.issue_print_url
                ? 'In PXTP'
                : (receipt.issue_status === 'exported' ? 'Đã FIFO' : 'Xuất TP');
            const issueButtonIcon = receipt.issue_print_url
                ? 'file-check-2'
                : (receipt.issue_status === 'exported' ? 'check-circle-2' : 'send');
            const issueButtonClass = receipt.issue_status === 'not_exported' || receipt.issue_status === 'partial_exported'
                ? 'btn-outline-success'
                : 'btn-outline-secondary';
            const disabled = receipt.issue_status === 'exported' && !receipt.issue_print_url ? 'disabled' : '';

            return `<tr>
                <td><strong>${escapeHtml(receipt.receipt_code)}</strong></td>
                <td>${escapeHtml(isoToDateVn(receipt.receipt_date || ''))}</td>
                <td>${escapeHtml(receipt.location_code || '')}</td>
                <td class="text-end">${formatNumber(receipt.lines_count || 0)}</td>
                <td class="text-end">${formatNumber(receipt.total_quantity || 0)}</td>
                <td>${receiptStatus}</td>
                <td>${escapeHtml(receipt.note || '')}</td>
                <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-primary btn-icon" target="_blank" href="${receipt.print_url}"><i data-lucide="printer"></i>In lại</a>
                    <button type="button" class="btn btn-sm btn-outline-secondary btn-icon edit-receipt-btn" data-id="${receipt.id}"><i data-lucide="pencil"></i>Sửa</button>
                    <button type="button" class="btn btn-sm ${issueButtonClass} btn-icon issue-from-receipt-btn"
                        data-id="${receipt.id}"
                        data-code="${escapeHtml(receipt.receipt_code)}"
                        data-print-url="${escapeHtml(receipt.issue_print_url || '')}"
                        ${disabled}>
                        <i data-lucide="${issueButtonIcon}"></i>${issueButtonLabel}
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary btn-icon send-receipt-production-btn" data-id="${receipt.id}" data-code="${escapeHtml(receipt.receipt_code)}"><i data-lucide="factory"></i>Gửi SX</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary btn-icon assign-receipt-location-btn" data-id="${receipt.id}" data-code="${escapeHtml(receipt.receipt_code)}" data-location="${escapeHtml(receipt.location_code || '')}"><i data-lucide="map-pin"></i>Vị trí</button>
                    <button type="button" class="btn btn-sm btn-outline-danger delete-receipt-btn" data-id="${receipt.id}" data-code="${escapeHtml(receipt.receipt_code)}"><i data-lucide="trash-2"></i>Xóa</button>
                </td>
            </tr>`;
        }

        function loadReceipts() {
            const params = new URLSearchParams();
            if (value('receiptFilterDate')) params.set('receipt_date', value('receiptFilterDate'));
            if (value('receiptKeyword')) params.set('keyword', value('receiptKeyword'));
            fetch(`/api/kiem-ton-kho/phieu-nhap-tp?${params}`).then(r => r.json()).then(result => {
                const rows = result.data || [];
                document.getElementById('receiptRows').innerHTML = rows.map(renderReceiptRow).join('') || '<tr><td colspan="8" class="empty-state text-center">Chưa có phiếu nhập trong ngày/kho đang chn</td></tr>';
                document.getElementById('receiptListSummary').textContent = `${formatNumber(result.summary?.receipt_count || 0)} phiếu · ${formatNumber(result.summary?.line_count || 0)} dòng · SL ${formatNumber(result.summary?.total_quantity || 0)} · ã xuất hết ${formatNumber(result.summary?.exported_count || 0)}`;
                refreshIcons();
            });
        }
        function loadLocationContents() {
            const locationCode = value('locationCode').toUpperCase();
            const rows = document.getElementById('locationContentRows');
            const summary = document.getElementById('locationSummary');
            const location = selectedLocation();
            document.getElementById('selectedLocationTitle').textContent = locationCode || 'chưa chn vị trí';
            document.getElementById('selectedLocationName').textContent = location?.location_name || '';
            if (!locationCode) {
                rows.innerHTML = '<tr><td colspan="7" class="empty-state text-center">Chn vị trí để xem hàng đang chứa</td></tr>';
                summary.innerHTML = '';
                return;
            }

            const params = new URLSearchParams({ location_code: locationCode, checked_at: value('checkedAt') });
            fetch(`/api/kiem-ton-kho/noi-dung-vi-tri?${params}`).then(r => r.json()).then(result => {
                locationContentsCache = result.data || [];
                rows.innerHTML = locationContentsCache.map(x => {
                    const colorLabel = x.color_name || x.color || x.pantone_code || x.pantone_hex || '';
                    const quickStockButton = x.catalog_only ? `<button type="button" class="btn btn-sm btn-outline-primary quick-catalog-stock-btn" data-code="${escapeHtml(x.internal_item_code || '')}">Nhập tồn</button>` : formatNumber(x.total_quantity || 0);
                    return `<tr>
                    <td>${x.internal_item_code || ''}</td><td>${x.ma_sp || ''}</td><td>${x.size || ''}</td>
                    <td>${colorLabel ? `<span class="color-chip">${x.pantone_hex ? `<span class="color-swatch" style="--swatch:${escapeHtml(x.pantone_hex)}"></span>` : ''}<span>${escapeHtml(colorLabel)}${x.pantone_code ? ` · ${escapeHtml(x.pantone_code)}` : ''}</span></span>` : ''}</td><td>${x.side || ''}</td><td class="text-end">${x.catalog_only ? 'Danh mục' : (x.package_count || 0)}</td>
                    <td class="text-end">${quickStockButton}</td>
                </tr>`;
                }).join('') || '<tr><td colspan="7" class="empty-state text-center">Vị trí chưa có kiện trong ngày kiểm kê</td></tr>';
                summary.innerHTML = `<span class="summary-chip">${result.summary?.item_count || 0} mã</span>
                    <span class="summary-chip">${result.summary?.package_count || 0} kiện</span>
                    <span class="summary-chip">SL ${result.summary?.total_quantity || 0}</span>`;
            });
        }

        function saveCatalogStockFromLocation(code) {
            const item = (locationContentsCache || []).find(row => String(row.internal_item_code || '') === String(code));
            if (!item) return alert('Không tìm thấy mã danh mục trong kệ đang chn.');
            const quantity = Number(prompt(`Nhập số lượng tồn cho ${item.internal_item_code}${item.catalog_unit ? ` (${item.catalog_unit})` : ''}`, ''));
            if (!quantity || quantity <= 0) return;
            fetch('/api/kiem-ton-kho/kien', {
                method: 'POST',
                headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify({
                    location_code: value('locationCode').toUpperCase(),
                    internal_item_code: item.internal_item_code || '',
                    ma_sp: item.ma_sp || '',
                    size: item.size || '',
                    color: item.color || '',
                    side: item.side || '',
                    quantity,
                    checked_at: value('checkedAt'),
                    entry_type: 'opening',
                    note: `Nhap ton nhanh tu danh muc ke ${value('locationCode').toUpperCase()}${item.catalog_unit ? ` - DVT ${item.catalog_unit}` : ''}`,
                }),
            })
                .then(r => jsonOrError(r, 'Không lưu được tồn'))
                .then(() => { loadPackages(); loadWarehouseStats(); loadWarehouseMap(); loadLocationContents(); })
                .catch(error => alert(error.message));
        }

        document.getElementById('saveLocationBtn').addEventListener('click', () => {
            fetch('/api/kiem-ton-kho/vi-tri', {
                method: 'POST', headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify({
                    location_code:value('editLocationCode'), warehouse_code:'',
                    shelf_code:value('editShelfCode'), tier:value('editTier'), bay_code:value('editBayCode'),
                    grid_w:Number(value('editGridW') || 4), grid_h:Number(value('editGridH') || 2),
                    location_name:value('editLocationName')
                })
            }).then(r => jsonOrError(r, 'Không lưu được vị trí'))
              .then(result => {
                  setLocationStatus(`ã lưu ${result.data.location_code}`);
                  document.getElementById('locationCode').value = result.data.location_code;
                  document.getElementById('receiptLocationCode').value = result.data.location_code;
                  loadLocations();
                  loadPackages();
                  loadWarehouseStats();
                  loadWarehouseMap();
                  loadLocationContents();
              }).catch(e => { setLocationStatus(e.message, true); alert(e.message); });
        });

        ['bulkShelfFrom','bulkShelfTo','bulkNumberFrom','bulkNumberTo','bulkTier','bulkNamePrefix'].forEach(id => {
            document.getElementById(id).addEventListener('input', updateBulkLocationPreview);
            document.getElementById(id).addEventListener('change', updateBulkLocationPreview);
        });

        ['printShelfFrom','printShelfTo','printNumberFrom','printNumberTo'].forEach(id => {
            document.getElementById(id).addEventListener('input', updateBulkPrintLocationPreview);
            document.getElementById(id).addEventListener('change', updateBulkPrintLocationPreview);
        });

        document.getElementById('printBulkLocationsBtn').addEventListener('click', () => {
            const data = bulkPrintLocationPayload();
            const params = new URLSearchParams({
                shelf_from: data.shelf_from,
                shelf_to: data.shelf_to,
                number_from: data.number_from,
                number_to: data.number_to,
            });
            window.open(`/client/kiem-ton-kho/tem-vi-tri-hang-loat?${params.toString()}`, '_blank');
        });

        document.getElementById('createBulkLocationsBtn').addEventListener('click', () => {
            const button = document.getElementById('createBulkLocationsBtn');
            const status = document.getElementById('bulkLocationStatus');
            button.disabled = true;
            status.className = 'small mt-2 text-muted';
            status.textContent = 'ang tạo vị trí...';
            fetch('/api/kiem-ton-kho/vi-tri/tao-nhanh', {
                method: 'POST',
                headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify(bulkLocationPayload())
            }).then(r => jsonOrError(r, 'Không tạo nhanh được vị trí'))
              .then(result => {
                  const data = result.data || {};
                  status.className = 'small mt-2 text-success';
                  status.textContent = `Tạo mới ${formatNumber(data.created || 0)}, cập nhật ${formatNumber(data.updated || 0)}, b qua ${formatNumber(data.skipped || 0)} vị trí đã có.`;
                  return loadLocations().then(() => {
                      renderLocations();
                      loadWarehouseMap();
                      renderLayoutEditor();
                      applyLayoutEditorSettings();
                      refreshIcons();
                  });
              })
              .catch(error => {
                  status.className = 'small mt-2 text-danger';
                  status.textContent = error.message;
                  alert(error.message);
              })
              .finally(() => { button.disabled = false; });
        });

        document.getElementById('deleteLocationBtn').addEventListener('click', () => {
            const code = value('editLocationCode').toUpperCase();
            if (!editingLocationId || !confirm(`Xóa vị trí ${code}?`)) return;
            fetch(`/api/kiem-ton-kho/vi-tri/${editingLocationId}`, {
                method: 'DELETE', headers: {'Accept':'application/json','X-CSRF-TOKEN':csrfToken}
            }).then(r => jsonOrError(r, 'Không xóa được vị trí'))
              .then(() => {
                  if (value('locationCode').toUpperCase() === code) {
                      document.getElementById('locationCode').value = '';
                  }
                  editingLocationId = null;
                  locationModal.hide();
                  loadLocations();
                  loadPackages();
                  loadWarehouseStats();
                  loadWarehouseMap();
                  loadLocationContents();
              }).catch(e => { setLocationStatus(e.message, true); alert(e.message); });
        });

        document.getElementById('saveReceiptBatchBtn').addEventListener('click', async () => {
            const lines = collectReceiptLines();
            const validLines = lines.filter(line => line.internal_item_code && Number(line.quantity || 0) > 0);
            if (!validLines.length) return alert('Nhập ít nhất 1 dòng có Mã nội bộ và Số lượng lớn hơn 0. Mã kế toán có thể thêm sau.');
            if (!value('receiptDate')) return alert('Chn ngày nhập kho.');
            const printWindow = window.open('', '_blank');
            await warnReceiptDuplicates(validLines);

            submitReceiptBatch(validLines, printWindow, false);
        });

        function submitReceiptBatch(validLines, printWindow, force = false) {
            return fetch(editingReceiptFormId ? `/api/kiem-ton-kho/phieu-nhap-tp/${editingReceiptFormId}` : '/api/kiem-ton-kho/phieu-nhap-tp', {
                method: editingReceiptFormId ? 'PUT' : 'POST', headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify({
                    force,
                    location_code: value('receiptLocationCode'),
                    ma_ko: '',
                    checked_at: value('receiptDate'),
                    note: value('receiptHeaderNote'),
                    send_to_production: !editingReceiptFormId && document.getElementById('receiptSendToProduction').checked,
                    lines: validLines
                })
            }).then(async response => {
                const result = await response.json().catch(() => ({}));
                if (response.status === 409 && result.force_required) {
                    const issues = (result.linked_issues || []).map(item => item.issue_code).join(', ');
                    const ok = confirm(`${result.message}\n\nPhiếu xuất liên quan: ${issues || 'không có / không rõ'}\n\nChắc chắn cập nhật lại phiếu nhập này?`);
                    if (ok) return submitReceiptBatch(validLines, printWindow, true).then(() => null);
                    return null;
                }
                if (!response.ok) throw new Error(result.message || 'Không lưu được phiếu nhập');
                return result;
            })
              .then(result => {
                  if (result === null) {
                      if (printWindow) printWindow.close();
                      return;
                  }
                  if (result.receipt_print_url && printWindow) {
                      printWindow.location.href = result.receipt_print_url;
                  } else if (result.receipt_print_url) {
                      window.location.href = result.receipt_print_url;
                  }
                  if (result.production_issue_print_url) {
                      window.open(result.production_issue_print_url, '_blank');
                  }
                  if (result.production_message) {
                      showWarehouseToast(result.production_message, 'Đã tạo phiếu xuất BTP sang sản xuất.');
                  }
                  if (result.production_failed) {
                      showWarehouseToast(result.message || 'Đã lưu phiếu nhập nhưng chưa gửi sản xuất được.', result.production_error?.message || '', 9000);
                  }
                  cancelReceiptEdit();
                  loadPackages();
                  loadLocations();
                  loadWarehouseStats();
                  loadWarehouseMap();
                  loadReceipts();
                  loadLocationContents();
              }).catch(e => {
                  if (printWindow) printWindow.close();
                  alert(e.message);
              });
        }

        document.getElementById('locationContentRows').addEventListener('click', event => {
            const button = event.target.closest('.quick-catalog-stock-btn');
            if (button) saveCatalogStockFromLocation(button.dataset.code);
        });

        document.getElementById('packageRows').addEventListener('click', event => {
            const button = event.target.closest('.delete-package-btn');
            if (!button || !confirm(`Xóa kiện ${button.dataset.code}? Số lượng sẽ được trừ khi đối chiếu tồn.`)) return;
            fetch(`/api/kiem-ton-kho/kien/${button.dataset.id}`, {
                method: 'DELETE', headers: {'Accept':'application/json','X-CSRF-TOKEN':csrfToken}
            }).then(r => jsonOrError(r, 'Không xóa được kiện'))
              .then(() => { loadPackages(); loadLocations(); loadWarehouseStats(); loadWarehouseMap(); loadLocationContents(); })
              .catch(e => alert(e.message));
        });

        document.getElementById('receiptRows').addEventListener('click', event => {
            const editReceiptButton = event.target.closest('.edit-receipt-btn');
            if (editReceiptButton) {
                fetch(`/api/kiem-ton-kho/phieu-nhap-tp/${editReceiptButton.dataset.id}`)
                    .then(r => jsonOrError(r, 'Không tải được phiếu nhập cần sửa'))
                    .then(result => fillReceiptEditForm(result.data))
                    .catch(e => alert(e.message));
                return;
            }

            const issueButton = event.target.closest('.issue-from-receipt-btn');
            if (issueButton) {
                if (issueButton.dataset.printUrl) {
                    window.open(issueButton.dataset.printUrl, '_blank');
                    return;
                }

                const receiver = prompt(`Khách hàng/ngưi nhận cho phiếu xuất TP từ ${issueButton.dataset.code}:`, 'Khách hàng');
                if (receiver === null) return;
                if (!confirm(`Tạo phiếu xuất thành phẩm cho khách từ toàn bộ dòng của ${issueButton.dataset.code}? Phiếu này sẽ trừ tồn nội bộ.`)) return;

                issueButton.disabled = true;
                fetch(`/api/xuat-vat-tu-noi-bo/tu-phieu-nhap/${issueButton.dataset.id}`, {
                    method: 'POST',
                    headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                    body: JSON.stringify({
                        issue_date: value('checkedAt') || localIsoDate(),
                        receiver_name: receiver.trim() || 'Khách hàng',
                        department: 'Kinh doanh',
                        purpose: 'Xuất thành phẩm cho khách hàng'
                    })
                }).then(r => jsonOrError(r, 'Không tạo được phiếu xuất từ phiếu nhập'))
                  .then(result => {
                      if (result.print_url) window.open(result.print_url, '_blank');
                      loadReceipts();
                      loadPackages();
                      loadLocations();
                      loadWarehouseStats();
                      loadWarehouseMap();
                      loadLocationContents();
                  })
                  .catch(e => {
                      alert(e.message);
                      if (e.message.includes('đã được tạo phiếu xuất') || e.message.includes('đã được tạo')) {
                          loadReceipts();
                      }
                  })
                  .finally(() => { issueButton.disabled = false; });
                return;
            }

            const sendProductionButton = event.target.closest('.send-receipt-production-btn');
            if (sendProductionButton) {
                if (!confirm(`Gửi toàn bộ dòng của ${sendProductionButton.dataset.code} sang sản xuất? Phiếu này sẽ tạo PXBTP và trừ tồn nội bộ.`)) return;
                sendProductionButton.disabled = true;
                fetch(`/api/xuat-vat-tu-noi-bo/gui-san-xuat/${sendProductionButton.dataset.id}`, {
                    method: 'POST',
                    headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                    body: JSON.stringify({
                        issue_date: value('checkedAt') || localIsoDate(),
                        receiver_name: 'Sản xuất',
                        department: 'Sản xuất',
                        purpose: 'Gửi phiếu nhập sang sản xuất'
                    })
                }).then(r => jsonOrError(r, 'Không gửi được phiếu sang sản xuất'))
                  .then(result => {
                      if (result.print_url) window.open(result.print_url, '_blank');
                      loadReceipts();
                      loadPackages();
                      loadLocations();
                      loadWarehouseStats();
                      loadWarehouseMap();
                      loadLocationContents();
                  })
                  .catch(e => alert(e.message))
                  .finally(() => { sendProductionButton.disabled = false; });
                return;
            }

            const assignButton = event.target.closest('.assign-receipt-location-btn');
            if (assignButton) {
                editingReceiptId = assignButton.dataset.id;
                document.getElementById('receiptLocationTitle').textContent = `${assignButton.dataset.code} · Hiện tại: ${assignButton.dataset.location || 'CHUA-XEP'}`;
                document.getElementById('receiptTargetLocationId').innerHTML = locations.map(location =>
                    `<option value="${location.id}" ${location.location_code === assignButton.dataset.location ? 'selected' : ''}>${escapeHtml(location.location_code)}${location.location_name ? ' · ' + escapeHtml(location.location_name) : ''}</option>`
                ).join('');
                receiptLocationModal.show();
                return;
            }

            const button = event.target.closest('.delete-receipt-btn');
            if (!button) return;
            confirmAndDeleteReceipt(button.dataset.id, button.dataset.code);
        });

        function receiptLinkText(links) {
            const issues = (links?.issues || []).map(item => `${item.issue_code} (${item.issue_type || '-'})`).join(', ');
            const btp = (links?.btp_orders || []).map(item => `${item.btp_order_code} (${item.status || '-'})`).join(', ');
            return [
                issues ? `Phiếu xuất liên quan: ${issues}` : '',
                btp ? `Lệnh BTP liên quan: ${btp}` : '',
                links?.block_reason ? `Khóa xóa: ${links.block_reason}` : '',
            ].filter(Boolean).join('\n');
        }

        function confirmAndDeleteReceipt(id, code) {
            fetch(`/api/kiem-ton-kho/phieu-nhap-tp/${id}/lien-ket`, {
                headers: {'Accept':'application/json'}
            }).then(r => jsonOrError(r, 'Không kiểm tra được liên kết phiếu'))
              .then(result => {
                  const links = result.data || {};
                  const detail = receiptLinkText(links);
                  if (links.has_links) {
                      if (!links.can_cascade_delete) {
                          alert(`Không thể xóa phiếu nhập ${code}.\n\n${detail}`);
                          return;
                      }
                      const ok = confirm(`Phiếu nhập ${code} đang có liên kết.\n\n${detail}\n\nXóa cả liên kết sẽ hoàn tồn/xóa phiếu xuất liên quan, đưa lệnh BTP về nháp, rồi xóa phiếu nhập.\n\nChắc chắn xóa cả liên kết?`);
                      if (ok) deleteReceipt(id, code, true);
                      return;
                  }

                  if (confirm(`Xóa phiếu nhập ${code}? Toàn bộ kiện và số tồn nội bộ tạo từ phiếu này sẽ bị trừ lại.`)) {
                      deleteReceipt(id, code, false);
                  }
              })
              .catch(e => alert(e.message));
        }

        function deleteReceipt(id, code, cascade = false) {
            fetch(`/api/kiem-ton-kho/phieu-nhap-tp/${id}${cascade ? '?cascade=1' : ''}`, {
                method: 'DELETE', headers: {'Accept':'application/json','X-CSRF-TOKEN':csrfToken}
            }).then(async response => {
                const result = await response.json().catch(() => ({}));
                if (response.status === 409 && result.force_required) {
                    const detail = receiptLinkText(result.links || {});
                    const ok = confirm(`${result.message}\n\n${detail || 'Có liên kết nhưng không đọc được chi tiết.'}\n\nChắc chắn xóa cả liên kết và xóa phiếu nhập ${code}?`);
                    if (ok) return deleteReceipt(id, code, true);
                    return null;
                }
                if (!response.ok) throw new Error(result.message || 'Không xóa được phiếu nhập');
                return result;
            }).then(result => {
                if (result === null) return;
                loadReceipts();
                loadPackages();
                loadLocations();
                loadWarehouseStats();
                loadWarehouseMap();
                loadLocationContents();
            }).catch(e => alert(e.message));
        }
        document.getElementById('confirmReceiptLocationBtn').addEventListener('click', () => {
            const locationId = document.getElementById('receiptTargetLocationId').value;
            const location = locations.find(item => String(item.id) === String(locationId));
            if (!editingReceiptId || !location) return;

            fetch(`/api/kiem-ton-kho/phieu-nhap-tp/${editingReceiptId}/vi-tri`, {
                method: 'PATCH',
                headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify({ location_code: location.location_code })
            }).then(r => jsonOrError(r, 'Không cập nhật được vị trí phiếu'))
              .then(() => {
                  receiptLocationModal.hide();
                  editingReceiptId = null;
                  loadReceipts();
                  loadPackages();
                  loadWarehouseStats();
                  loadWarehouseMap();
                  loadLocationContents();
              })
              .catch(e => alert(e.message));
        });

        document.getElementById('printLocationBtn').addEventListener('click', () => {
            const code = value('editLocationCode').toUpperCase();
            const location = locations.find(item => item.location_code === code);
            if (!location) return alert('Lưu vị trí trước khi in tem.');
            window.open(`/client/kiem-ton-kho/tem-vi-tri/${location.id}`, '_blank');
        });
        document.getElementById('useLocationBtn').addEventListener('click', () => {
            const code = value('editLocationCode').toUpperCase();
            const location = locations.find(item => item.location_code === code);
            if (!location) return alert('Lưu vị trí trước khi nhập hàng.');
            selectLocation(location.location_code);
            locationModal.hide();
            switchWorkspace('entry');
        });
        document.getElementById('rackCrudItemCode').addEventListener('input', searchRackCatalog);
        document.getElementById('rackCrudItemCode').addEventListener('change', applyRackCatalogSelection);
        document.getElementById('rackCrudForm').addEventListener('submit', saveRackInventoryItem);
        document.getElementById('locationCode').addEventListener('change', () => {
            fillSelectedLocation();
            document.getElementById('receiptLocationCode').value = value('locationCode').toUpperCase();
            renderLocations();
            loadPackages();
            loadReceipts();
            loadLocationContents();
        });
        document.getElementById('receiptLocationCode').addEventListener('change', event => {
            event.target.value = event.target.value.trim().toUpperCase();
            const location = locations.find(item => item.location_code === event.target.value);
        });
        document.getElementById('locationSearch').addEventListener('input', renderLocations);
        document.getElementById('rackProductionOrderForm')?.addEventListener('submit', event => {
            event.preventDefault();
            loadRackProductionPlan();
        });
        document.getElementById('rackProductionOrderInput')?.addEventListener('input', event => {
            suggestRackProductionOrders(event.target.value);
        });
        document.getElementById('rackProductionOrderInput')?.addEventListener('change', event => {
            if (event.target.value.trim()) loadRackProductionPlan();
        });
        document.getElementById('rackProductionOrderClear')?.addEventListener('click', clearRackProductionPlan);
        document.getElementById('rackProductionOrderResult')?.addEventListener('click', event => {
            const button = event.target.closest('[data-order-location]');
            if (button) scrollRackLocationIntoView(button.dataset.orderLocation);
        });
        document.getElementById('mapSearch').addEventListener('input', () => {
            renderLayoutEditor();
            if (!document.getElementById('map3dPanel')?.classList.contains('d-none')) {
                renderWarehouse3D();
                scrollRackSearchIntoView();
            }
        });
        document.getElementById('showEmptyLocations')?.addEventListener('change', () => {
            renderLayoutEditor();
            if (!document.getElementById('map3dPanel')?.classList.contains('d-none')) renderWarehouse3D();
        });
        document.getElementById('uploadLayoutBackgroundBtn')?.addEventListener('click', () => {
            document.getElementById('layoutBackgroundInput')?.click();
        });
        document.getElementById('layoutBackgroundInput')?.addEventListener('change', event => {
            const file = event.target.files?.[0];
            if (!file) return;
            if (!file.type.startsWith('image/')) return alert('Chn file ảnh sơ đồ kho.');
            const reader = new FileReader();
            reader.onload = () => {
                layoutBackgroundImage = String(reader.result || '');
                localStorage.setItem('warehouseLayoutBackground', layoutBackgroundImage);
                applyLayoutEditorSettings();
                setLayoutSaveStatus('ã thêm background sơ đồ.', 'success');
            };
            reader.readAsDataURL(file);
        });
        document.getElementById('clearLayoutBackgroundBtn')?.addEventListener('click', () => {
            layoutBackgroundImage = '';
            localStorage.removeItem('warehouseLayoutBackground');
            applyLayoutEditorSettings();
            setLayoutSaveStatus('ã xóa background.', 'secondary');
        });
        document.getElementById('layoutBackgroundOpacity')?.addEventListener('input', event => {
            layoutBackgroundOpacity = Number(event.target.value || 36);
            localStorage.setItem('warehouseLayoutBackgroundOpacity', String(layoutBackgroundOpacity));
            applyLayoutEditorSettings();
        });
        document.getElementById('layoutZoom')?.addEventListener('change', event => {
            layoutZoom = Number(event.target.value || 1);
            localStorage.setItem('warehouseLayoutZoom', String(layoutZoom));
            applyLayoutEditorSettings();
        });
        document.getElementById('layoutEditor').addEventListener('dblclick', event => {
            const block = event.target.closest('.layout-block');
            if (!block) return;
            const location = locations.find(item => String(item.id) === String(block.dataset.locationId));
            if (location) selectLocation(location.location_code);
        });
        document.getElementById('layoutEditor').addEventListener('pointerdown', event => {
            const block = event.target.closest('.layout-block');
            if (!block) return;
            const location = locations.find(item => String(item.id) === String(block.dataset.locationId));
            if (!location) return;
            const layout = normalizeLayout(location, 0);
            draggingLayout = {
                block,
                location,
                startX: event.clientX,
                startY: event.clientY,
                gridX: layout.x,
                gridY: layout.y,
                gridW: layout.w,
                gridH: layout.h,
            };
            block.classList.add('is-dragging');
            setLayoutSaveStatus(`ang kéo ${location.location_code}...`, 'primary');
            block.setPointerCapture(event.pointerId);
        });
        document.getElementById('layoutEditor').addEventListener('pointermove', event => {
            if (!draggingLayout) return;
            const deltaX = Math.round((event.clientX - draggingLayout.startX) / (40 * layoutZoom));
            const deltaY = Math.round((event.clientY - draggingLayout.startY) / (32 * layoutZoom));
            const nextX = Math.min(24 - draggingLayout.gridW + 1, Math.max(1, draggingLayout.gridX + deltaX));
            const nextY = Math.min(40 - draggingLayout.gridH + 1, Math.max(1, draggingLayout.gridY + deltaY));
            draggingLayout.block.style.gridColumn = `${nextX} / span ${draggingLayout.gridW}`;
            draggingLayout.block.style.gridRow = `${nextY} / span ${draggingLayout.gridH}`;
            const hint = document.getElementById('layoutDragHint');
            if (hint) {
                hint.style.display = 'block';
                hint.style.left = `${Math.max(8, event.offsetX + 12)}px`;
                hint.style.top = `${Math.max(8, event.offsetY + 12)}px`;
                hint.textContent = `${draggingLayout.location.location_code}: X${nextX} Y${nextY}`;
            }
        });
        document.getElementById('layoutEditor').addEventListener('pointerup', event => {
            if (!draggingLayout) return;
            const deltaX = Math.round((event.clientX - draggingLayout.startX) / (40 * layoutZoom));
            const deltaY = Math.round((event.clientY - draggingLayout.startY) / (32 * layoutZoom));
            const nextX = Math.min(24 - draggingLayout.gridW + 1, Math.max(1, draggingLayout.gridX + deltaX));
            const nextY = Math.min(40 - draggingLayout.gridH + 1, Math.max(1, draggingLayout.gridY + deltaY));
            const currentDrag = draggingLayout;
            currentDrag.block.classList.remove('is-dragging');
            draggingLayout = null;
            const hint = document.getElementById('layoutDragHint');
            if (hint) hint.style.display = 'none';
            const stack = tierStackLocations(currentDrag.location);
            setLayoutSaveStatus(`ang lưu ${isTierStackLocation(currentDrag.location) ? `cụm kệ ${shelfForLocation(currentDrag.location)}` : currentDrag.location.location_code}...`, 'primary');
            Promise.all(stack.map(location => saveLocationLayout(location.id, nextX, nextY, currentDrag.gridW, currentDrag.gridH)))
                .then(results => {
                    results.forEach(result => {
                        const index = locations.findIndex(item => item.id === result.data.id);
                        if (index >= 0) locations[index] = result.data;
                    });
                    renderLayoutEditor();
                    applyLayoutEditorSettings();
                    setLayoutSaveStatus(`Đã lưu ${stack.length > 1 ? `${stack.length} tầng cùng kệ` : results[0].data.location_code}.`, 'success');
                })
                .catch(error => {
                    alert(error.message);
                    renderLayoutEditor();
                    applyLayoutEditorSettings();
                    setLayoutSaveStatus('Không lưu được layout.', 'danger');
                });
        });
        document.getElementById('confirmMovePackageBtn').addEventListener('click', () => {
            const locationId = document.getElementById('moveTargetLocationId').value;
            const location = locations.find(item => String(item.id) === String(locationId));
            if (!movingPackageId || !locationId) return;
            movePackageToLocation(movingPackageId, locationId)
                .then(() => {
                    movePackageModal.hide();
                    selectLocation(location.location_code);
                    loadWarehouseStats();
                    loadWarehouseMap();
                }).catch(e => alert(e.message));
        });
        document.getElementById('receiptEntryRows').addEventListener('input', event => {
            if (event.target.classList.contains('receipt-ma-sp')) searchReceiptProducts(event.target);
            if (event.target.classList.contains('receipt-order')) searchProductionOrders(event.target);
            if (event.target.classList.contains('receipt-internal-code')) searchInternalCatalog(event.target);
        });
        document.getElementById('receiptEntryRows').addEventListener('keydown', async event => {
            if (event.key !== 'Enter' || !event.target.matches('input')) return;
            event.preventDefault();
            if (event.target.classList.contains('receipt-internal-code')) {
                await applyInternalCatalogAsync(event.target);
            }
            moveReceiptEntryByEnter(event.target);
        });
        document.getElementById('receiptEntryRows').addEventListener('change', event => {
            if (event.target.classList.contains('receipt-order')) applyProductionOrder(event.target);
            if (event.target.classList.contains('receipt-internal-code')) {
                applyInternalCatalogAsync(event.target).then(scheduleReceiptDuplicateCheck);
                return;
            }
            if (event.target.classList.contains('receipt-internal-code') || event.target.classList.contains('receipt-quantity')) {
                scheduleReceiptDuplicateCheck();
            }
        });
        document.getElementById('voiceLookupBtn').addEventListener('click', startVoiceLookup);
        document.getElementById('voiceSearchBtn').addEventListener('click', () => lookupWarehouseByVoice());
        document.getElementById('voiceLookupInput').addEventListener('keydown', event => {
            if (event.key === 'Enter') lookupWarehouseByVoice();
        });
        document.getElementById('warehouseTopSearch').addEventListener('input', event => {
            document.getElementById('voiceLookupInput').value = event.target.value;
            const activeView = document.querySelector('[data-workspace-panel]:not(.d-none)')?.dataset.workspacePanel;
            if (['editor', 'map3d'].includes(activeView)) applyMapSearch(event.target.value);
        });
        document.getElementById('warehouseTopSearch').addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                document.getElementById('voiceLookupInput').value = event.target.value;
                lookupWarehouseByVoice();
            }
        });
        document.getElementById('warehouseTopMic').addEventListener('click', () => {
            document.getElementById('voiceLookupBtn').click();
        });
        document.getElementById('editGridPreset').addEventListener('change', event => {
            const match = String(event.target.value || '').match(/^(\d+)x(\d+)$/);
            if (!match) return;
            document.getElementById('editGridW').value = match[1];
            document.getElementById('editGridH').value = match[2];
        });
        document.addEventListener('click', event => {
            if (!event.target.closest('.product-search')) hideProductResults();
        });
        document.getElementById('rackFrontView')?.addEventListener('mouseover', event => {
            const button = event.target.closest('.rack-tier');
            if (!button || button === rackHoverTarget) return;
            rackHoverTarget = button;
            showRackHoverPreview(button, event);
        });
        document.getElementById('rackFrontView')?.addEventListener('mousemove', event => {
            if (rackHoverTarget) moveRackHoverPreview(event);
        });
        document.getElementById('rackFrontView')?.addEventListener('mouseleave', hideRackHoverPreview);
        document.getElementById('rackFrontView')?.addEventListener('click', hideRackHoverPreview);
        document.getElementById('checkedAt').addEventListener('change', () => { loadPackages(); loadReceipts(); loadWarehouseStats(); loadWarehouseMap(); loadLocationContents(); });
        document.getElementById('checkedAt').addEventListener('change', event => {
            setDateValue('receiptDate', value('checkedAt'));
        });
        document.querySelectorAll('.date-vn').forEach(input => {
            input.addEventListener('blur', () => {
                if (input.value.trim()) input.value = isoToDateVn(dateVnToIso(input.value));
                if (input.id === 'receiptDate') scheduleReceiptDuplicateCheck();
            });
        });
        document.getElementById('cancelReceiptEditBtn').addEventListener('click', cancelReceiptEdit);
        let receiptSearchTimer = null;
        document.getElementById('receiptKeyword').addEventListener('input', () => {
            clearTimeout(receiptSearchTimer);
            receiptSearchTimer = setTimeout(loadReceipts, 250);
        });
        document.getElementById('receiptFilterDate').addEventListener('change', loadReceipts);
        document.getElementById('clearReceiptFilter').addEventListener('click', () => {
            document.getElementById('receiptKeyword').value = '';
            document.getElementById('receiptFilterDate').value = '';
            loadReceipts();
        });
        const pageParams = new URLSearchParams(window.location.search);
        const requestedView = pageParams.get('view');
        const requestedIssue = pageParams.get('from_issue');
        const requestedKeyword = pageParams.get('keyword');
        const requestedProductionOrder = pageParams.get('production_order');
        if (requestedKeyword) document.getElementById('receiptKeyword').value = requestedKeyword;
        switchWorkspace(shelfMapOnly ? 'map3d' : (requestedView === 'map' ? 'editor' : (['entry', 'receipts', 'history', 'overview', 'editor', 'map3d'].includes(requestedView) ? requestedView : 'entry')));
        const requestedLocation = pageParams.get('location_code');
        if (requestedLocation) document.getElementById('locationCode').value = requestedLocation.toUpperCase();
        loadLocations().then(() => {
            syncReceiptLocationFromContext();
            loadPackages();
            loadReceipts();
            loadWarehouseStats();
            const mapPromise = loadWarehouseMap();
            loadLocationContents();
            if (requestedIssue) loadReceiptFromIssue(requestedIssue);
            if (requestedProductionOrder) {
                document.getElementById('rackProductionOrderInput').value = requestedProductionOrder;
                mapPromise
                    .then(() => loadRackProductionPlan())
                    .then(() => {
                        if (requestedLocation) scrollRackLocationIntoView(requestedLocation);
                    });
            } else if (requestedLocation) {
                mapPromise.then(() => scrollRackLocationIntoView(requestedLocation));
            }
        });
        updateSavePackageButton();
        refreshIcons();
    </script>
</body>
</html>






