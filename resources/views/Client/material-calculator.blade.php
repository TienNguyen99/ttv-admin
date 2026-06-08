<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Material Calculator - Cắt vải</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; color: #1e293b; }
        .page-title { font-size: 24px; font-weight: 800; letter-spacing: 0; }
        .hint { color: #64748b; font-size: 13px; }
        .panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; }
        .metric { font-size: 21px; font-weight: 800; }
        .control-grid { display: grid; grid-template-columns: 1.25fr repeat(6, minmax(108px, 1fr)) 1.3fr auto; gap: 8px; align-items: end; }
        .form-label { margin-bottom: 4px; color: #475569; font-size: 12px; font-weight: 700; }
        .form-control { min-height: 38px; border-color: #cbd5e1; border-radius: 6px; }
        .line-row { cursor: pointer; }
        .line-row.is-active td { background: #eff6ff; }
        .ratio-chip { display: inline-flex; gap: 4px; align-items: center; padding: 4px 8px; border-radius: 999px; background: #eef2ff; color: #3730a3; font-size: 12px; font-weight: 700; }
        .preview-header { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 10px; align-items: center; margin-bottom: 10px; }
        .fabric-preview { height: 540px; overflow: auto; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; }
        .svg-wrap { padding: 12px; width: max-content; min-width: 100%; }
        .tool-actions { display: flex; gap: 8px; align-items: center; }
        @media (max-width: 1280px) {
            .control-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
            .control-actions { grid-column: span 4; }
        }
        @media (max-width: 760px) {
            .control-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .control-actions { grid-column: span 2; }
        }
    </style>
</head>

<body>
    @include('layouts.partials.sidebar')

    <main class="container-fluid py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
            <div>
                <h1 class="page-title mb-1">Material Calculator</h1>
                <div class="hint">Chiều dài vải chạy ngang để cuộn, khổ vải nằm dọc. Nền gạch đậm là 60 x 60 cm.</div>
            </div>
            <div class="tool-actions">
                <span id="saveStatus" class="hint"></span>
                <button id="addLineBtn" type="button" class="btn btn-outline-primary">Thêm dòng</button>
                <button id="exportCsvBtn" type="button" class="btn btn-success">Xuất CSV</button>
            </div>
        </div>

        <section class="row g-3 mb-3">
            <div class="col-md-3"><div class="panel"><div class="hint">Mã vật tư</div><div id="materialCount" class="metric">0</div></div></div>
            <div class="col-md-3"><div class="panel"><div class="hint">Tổng pcs cần cắt</div><div id="totalPieces" class="metric">0</div></div></div>
            <div class="col-md-3"><div class="panel"><div class="hint">Tổng dài vải xuất</div><div id="totalLength" class="metric">0 m</div></div></div>
            <div class="col-md-3"><div class="panel"><div class="hint">Tỷ lệ dùng khổ</div><div id="avgUtilization" class="metric">0%</div></div></div>
        </section>

        <section class="panel mb-3">
            <div class="control-grid">
                <div>
                    <label class="form-label">Mã vật tư / vải</label>
                    <input id="materialCode" class="form-control" placeholder="VD: VAI-THUN-50MM">
                </div>
                <div>
                    <label class="form-label">Khổ vải (cm)</label>
                    <input id="fabricWidth" type="number" step="0.01" class="form-control" value="150">
                </div>
                <div>
                    <label class="form-label">Dài chi tiết (cm)</label>
                    <input id="pieceLength" type="number" step="0.01" class="form-control" value="20">
                </div>
                <div>
                    <label class="form-label">Rộng chi tiết (cm)</label>
                    <input id="pieceWidth" type="number" step="0.01" class="form-control" value="5">
                </div>
                <div>
                    <label class="form-label">Pcs / set</label>
                    <input id="pcsPerSet" type="number" step="1" class="form-control" value="1">
                </div>
                <div>
                    <label class="form-label">SL đơn hàng</label>
                    <input id="orderQty" type="number" step="1" class="form-control" value="1000">
                </div>
                <div>
                    <label class="form-label">Hao hụt (%)</label>
                    <input id="wastePercent" type="number" step="0.01" class="form-control" value="3">
                </div>
                <div>
                    <label class="form-label">Ghi chú</label>
                    <input id="lineNote" class="form-control" placeholder="Màu, size, lệnh...">
                </div>
                <div class="control-actions d-flex gap-2">
                    <button id="saveLineBtn" type="button" class="btn btn-primary">Lưu</button>
                    <button id="deleteLineBtn" type="button" class="btn btn-outline-danger">Xóa</button>
                </div>
            </div>
        </section>

        <section class="row g-3 mb-3">
            <div class="col-xl-5">
                <div class="panel h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h2 class="h6 mb-0">Bảng vật tư xuất</h2>
                        <span id="activeRatio" class="ratio-chip">0 pcs / cột</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Mã vải</th>
                                    <th class="text-end">Khổ</th>
                                    <th class="text-end">Pcs</th>
                                    <th class="text-end">SL xuất</th>
                                </tr>
                            </thead>
                            <tbody id="lineRows"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-xl-7">
                <div class="panel h-100">
                    <div class="preview-header">
                        <div>
                            <h2 class="h6 mb-1">Editor grid cuộn ngang</h2>
                            <div id="previewHint" class="hint">Chọn một dòng để xem sơ đồ cắt.</div>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <label class="hint">Zoom</label>
                            <input id="zoomRange" type="range" min="0.6" max="2.2" step="0.1" value="1">
                        </div>
                    </div>
                    <div class="fabric-preview">
                        <div class="svg-wrap">
                            <svg id="fabricSvg" role="img" aria-label="Sơ đồ cắt vải"></svg>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script>
        const storageKey = 'ttv.materialCalculator.fabricCut.v2';
        const legacyStorageKey = 'ttv.materialCalculator.fabricCut.v1';
        const fields = ['materialCode','fabricWidth','pieceLength','pieceWidth','pcsPerSet','orderQty','wastePercent','lineNote'];
        const newId = () => (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : `line-${Date.now()}-${Math.random().toString(16).slice(2)}`;
        const defaultLines = [
            { id: newId(), materialCode: 'VAI-MAU', fabricWidth: 150, pieceLength: 20, pieceWidth: 5, pcsPerSet: 1, orderQty: 1000, wastePercent: 3, lineNote: 'Dòng mẫu' }
        ];
        let lines = loadSavedLines();
        let activeId = lines[0].id;

        const value = id => document.getElementById(id).value;
        const num = value => Number(value || 0);
        const fmt = value => Number(value || 0).toLocaleString('vi-VN', { maximumFractionDigits: 3 });
        const escapeSvg = value => String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

        function setSaveStatus(message, isError = false) {
            const status = document.getElementById('saveStatus');
            status.textContent = message;
            status.className = `hint ${isError ? 'text-danger' : 'text-success'}`;
        }

        function loadSavedLines() {
            try {
                const saved = JSON.parse(localStorage.getItem(storageKey) || localStorage.getItem(legacyStorageKey) || '[]');
                if (Array.isArray(saved) && saved.length > 0) {
                    return saved.map(line => ({ ...line, id: line.id || newId() }));
                }
            } catch (error) {
                console.warn('Cannot load material calculator data', error);
            }
            return defaultLines;
        }

        function saveLines() {
            try {
                localStorage.setItem(storageKey, JSON.stringify(lines));
                setSaveStatus(`Đã lưu ${new Date().toLocaleTimeString('vi-VN')}`);
                return true;
            } catch (error) {
                setSaveStatus('Không lưu được trên trình duyệt', true);
                return false;
            }
        }

        function calcLine(line) {
            const fabricWidth = Math.max(num(line.fabricWidth), 0);
            const pieceWidth = Math.max(num(line.pieceWidth), 0);
            const pieceLength = Math.max(num(line.pieceLength), 0);
            const pcsPerSet = Math.max(Math.floor(num(line.pcsPerSet)), 1);
            const orderQty = Math.max(Math.ceil(num(line.orderQty)), 0);
            const wastePercent = Math.max(num(line.wastePercent), 0);
            const requiredPieces = Math.ceil(orderQty / pcsPerSet);
            const piecesPerColumn = pieceWidth > 0 ? Math.max(Math.floor(fabricWidth / pieceWidth), 0) : 0;
            const columnsNeeded = piecesPerColumn > 0 ? Math.ceil(requiredPieces / piecesPerColumn) : 0;
            const rawLengthCm = columnsNeeded * pieceLength;
            const outputLengthCm = rawLengthCm * (1 + wastePercent / 100);
            const utilization = fabricWidth > 0 && piecesPerColumn > 0 ? (piecesPerColumn * pieceWidth / fabricWidth) * 100 : 0;
            return { fabricWidth, pieceWidth, pieceLength, pcsPerSet, orderQty, wastePercent, requiredPieces, piecesPerColumn, columnsNeeded, rawLengthCm, outputLengthCm, utilization };
        }

        function fillForm(line) {
            document.getElementById('materialCode').value = line.materialCode || '';
            document.getElementById('fabricWidth').value = line.fabricWidth || '';
            document.getElementById('pieceLength').value = line.pieceLength || '';
            document.getElementById('pieceWidth').value = line.pieceWidth || '';
            document.getElementById('pcsPerSet').value = line.pcsPerSet || '';
            document.getElementById('orderQty').value = line.orderQty || '';
            document.getElementById('wastePercent').value = line.wastePercent || '';
            document.getElementById('lineNote').value = line.lineNote || '';
        }

        function readForm() {
            return {
                id: activeId || newId(),
                materialCode: value('materialCode').trim() || 'CHUA-CO-MA',
                fabricWidth: num(value('fabricWidth')),
                pieceLength: num(value('pieceLength')),
                pieceWidth: num(value('pieceWidth')),
                pcsPerSet: num(value('pcsPerSet')),
                orderQty: num(value('orderQty')),
                wastePercent: num(value('wastePercent')),
                lineNote: value('lineNote').trim(),
            };
        }

        function renderRows() {
            document.getElementById('lineRows').innerHTML = lines.map(line => {
                const calc = calcLine(line);
                return `<tr class="line-row ${line.id === activeId ? 'is-active' : ''}" data-id="${line.id}">
                    <td><div class="fw-semibold">${line.materialCode}</div><div class="hint">${line.lineNote || ''}</div></td>
                    <td class="text-end">${fmt(line.fabricWidth)} cm</td>
                    <td class="text-end">${fmt(calc.requiredPieces)}</td>
                    <td class="text-end fw-semibold">${fmt(calc.outputLengthCm / 100)} m</td>
                </tr>`;
            }).join('');

            const totals = lines.map(calcLine);
            const totalPieces = totals.reduce((sum, item) => sum + item.requiredPieces, 0);
            const totalLength = totals.reduce((sum, item) => sum + item.outputLengthCm, 0) / 100;
            const avgUtilization = totals.length ? totals.reduce((sum, item) => sum + item.utilization, 0) / totals.length : 0;
            document.getElementById('materialCount').textContent = fmt(lines.length);
            document.getElementById('totalPieces').textContent = fmt(totalPieces);
            document.getElementById('totalLength').textContent = `${fmt(totalLength)} m`;
            document.getElementById('avgUtilization').textContent = `${fmt(avgUtilization)}%`;
        }

        function drawGrid() {
            const line = lines.find(item => item.id === activeId) || lines[0];
            if (!line) return;
            const calc = calcLine(line);
            const zoom = num(value('zoomRange')) || 1;
            const svg = document.getElementById('fabricSvg');
            const displayColumns = Math.min(calc.columnsNeeded, 240);
            const rulerWidth = 28;
            const topRuler = 22;
            const fabricX = rulerWidth;
            const fabricY = topRuler;
            const fabricViewWidth = Math.max(calc.pieceLength * displayColumns, 60);
            const fabricViewHeight = Math.max(calc.fabricWidth, 60);
            const viewWidth = fabricViewWidth + rulerWidth + 16;
            const viewHeight = fabricViewHeight + topRuler + 18;
            const pixelScale = 4 * zoom;
            svg.setAttribute('viewBox', `0 0 ${viewWidth} ${viewHeight}`);
            svg.style.width = `${Math.max(viewWidth * pixelScale, 1100)}px`;
            svg.style.height = `${Math.max(viewHeight * pixelScale, 430)}px`;

            let markup = `
                <defs>
                    <pattern id="grid10" width="10" height="10" patternUnits="userSpaceOnUse">
                        <path d="M 10 0 L 0 0 0 10" fill="none" stroke="#e8eef7" stroke-width="0.18"></path>
                    </pattern>
                    <pattern id="grid60" width="60" height="60" patternUnits="userSpaceOnUse">
                        <path d="M 60 0 L 0 0 0 60" fill="none" stroke="#cbd5e1" stroke-width="0.45"></path>
                    </pattern>
                </defs>
                <rect x="${fabricX}" y="${fabricY}" width="${fabricViewWidth}" height="${fabricViewHeight}" fill="url(#grid10)" stroke="#94a3b8" stroke-width="0.5"></rect>
                <rect x="${fabricX}" y="${fabricY}" width="${fabricViewWidth}" height="${fabricViewHeight}" fill="url(#grid60)" opacity="0.9"></rect>
                <text x="${fabricX}" y="9" font-size="4" fill="#2563eb">Chiều dài vải: ${fmt(calc.outputLengthCm / 100)} m</text>
                <text x="${fabricX}" y="${fabricY + 7}" font-size="3.4" fill="#64748b">mỗi ô nền = 60 x 60 cm</text>
            `;

            for (let x = 0; x <= fabricViewWidth; x += 60) {
                markup += `<text x="${fabricX + x + 1}" y="${fabricY - 5}" font-size="3" fill="#64748b">${fmt(x)}cm</text>`;
            }

            for (let y = 0; y <= fabricViewHeight; y += 60) {
                markup += `<text x="2" y="${fabricY + y + 3}" font-size="3" fill="#64748b">${fmt(y)}cm</text>`;
            }

            let pieceIndex = 0;
            for (let column = 0; column < displayColumns; column++) {
                const x = fabricX + column * calc.pieceLength;
                markup += `<line x1="${x}" y1="${fabricY}" x2="${x}" y2="${fabricY + fabricViewHeight}" stroke="#bfdbfe" stroke-width="0.28"></line>`;
                for (let row = 0; row < calc.piecesPerColumn && pieceIndex < calc.requiredPieces; row++) {
                    const y = fabricY + row * calc.pieceWidth;
                    const seq = pieceIndex + 1;
                    const tooltip = [
                        `Mã vật tư: ${line.materialCode}`,
                        `Pcs #${seq}`,
                        `Cột dài: ${column + 1}`,
                        `Vị trí theo khổ: ${row + 1}`,
                        `Chi tiết: ${fmt(calc.pieceLength)} x ${fmt(calc.pieceWidth)} cm`,
                        `Khổ vải: ${fmt(calc.fabricWidth)} cm`,
                        `Pcs mỗi cột: ${fmt(calc.piecesPerColumn)}`,
                        `Hao hụt: ${fmt(calc.wastePercent)}%`,
                        `Chiều dài xuất: ${fmt(calc.outputLengthCm / 100)} m`
                    ].join('\n');
                    markup += `<g>
                        <title>${escapeSvg(tooltip)}</title>
                        <rect x="${x}" y="${y}" width="${calc.pieceLength}" height="${calc.pieceWidth}" fill="#dbeafe" stroke="#2563eb" stroke-width="0.35"></rect>
                    `;
                    if (calc.pieceLength >= 8 && calc.pieceWidth >= 8) {
                        markup += `<text x="${x + calc.pieceLength / 2}" y="${y + calc.pieceWidth / 2}" text-anchor="middle" dominant-baseline="middle" font-size="3" fill="#1e3a8a">${seq}</text>`;
                    }
                    markup += '</g>';
                    pieceIndex++;
                }
            }

            if (calc.columnsNeeded > displayColumns) {
                markup += `<text x="${fabricX}" y="${viewHeight - 5}" font-size="4" fill="#b45309">Đang hiển thị ${displayColumns}/${calc.columnsNeeded} cột cắt</text>`;
            }

            svg.innerHTML = markup;
            document.getElementById('previewHint').textContent = `${line.materialCode}: ${fmt(calc.requiredPieces)} pcs cần cắt, ${fmt(calc.piecesPerColumn)} pcs / cột, ${fmt(calc.columnsNeeded)} cột ngang, xuất ${fmt(calc.outputLengthCm / 100)} m`;
            document.getElementById('activeRatio').textContent = `${fmt(calc.piecesPerColumn)} pcs / cột`;
        }

        function render() {
            const active = lines.find(item => item.id === activeId);
            if (active) fillForm(active);
            renderRows();
            drawGrid();
        }

        document.getElementById('saveLineBtn').addEventListener('click', () => {
            const next = readForm();
            const index = lines.findIndex(item => item.id === activeId);
            if (index >= 0) lines[index] = next;
            else lines.push(next);
            activeId = next.id;
            saveLines();
            render();
        });

        document.getElementById('addLineBtn').addEventListener('click', () => {
            const next = { id: newId(), materialCode: 'VAI-MOI', fabricWidth: 150, pieceLength: 20, pieceWidth: 5, pcsPerSet: 1, orderQty: 1000, wastePercent: 3, lineNote: '' };
            lines.push(next);
            activeId = next.id;
            saveLines();
            render();
        });

        document.getElementById('deleteLineBtn').addEventListener('click', () => {
            if (lines.length <= 1) return;
            lines = lines.filter(item => item.id !== activeId);
            activeId = lines[0].id;
            saveLines();
            render();
        });

        document.getElementById('lineRows').addEventListener('click', event => {
            const row = event.target.closest('.line-row');
            if (!row) return;
            activeId = row.dataset.id;
            render();
        });

        document.getElementById('zoomRange').addEventListener('input', drawGrid);
        fields.forEach(id => document.getElementById(id).addEventListener('input', () => {
            const index = lines.findIndex(item => item.id === activeId);
            if (index >= 0) lines[index] = readForm();
            saveLines();
            renderRows();
            drawGrid();
        }));

        document.getElementById('exportCsvBtn').addEventListener('click', () => {
            const headers = ['ma_vat_tu','kho_vai_cm','dai_chi_tiet_cm','rong_chi_tiet_cm','pcs_set','so_luong_don_hang_pcs','hao_hut_percent','pcs_can_cat','pcs_moi_cot','so_cot_cat','chieu_dai_xuat_m','ghi_chu'];
            const rows = lines.map(line => {
                const calc = calcLine(line);
                return [line.materialCode, line.fabricWidth, line.pieceLength, line.pieceWidth, line.pcsPerSet, line.orderQty, line.wastePercent, calc.requiredPieces, calc.piecesPerColumn, calc.columnsNeeded, (calc.outputLengthCm / 100).toFixed(3), line.lineNote];
            });
            const csv = [headers, ...rows].map(row => row.map(cell => `"${String(cell ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'material-calculator.csv';
            link.click();
            URL.revokeObjectURL(url);
        });

        render();
    </script>
</body>
</html>
