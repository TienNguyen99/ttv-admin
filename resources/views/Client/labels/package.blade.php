<!DOCTYPE html>
<html lang="vi"><head><meta charset="UTF-8"><title>{{ $package->package_code }}</title>
<style>
@page { size: 70mm 50mm; margin: 2mm; } body { margin:0; font-family:Arial,sans-serif; font-size:10px; }
.label { width:66mm; height:46mm; display:grid; grid-template-columns:22mm 1fr; gap:2mm; }
.qr img { width:22mm; height:22mm; } .code { font-size:12px; font-weight:700; } .line { margin-bottom:1mm; }
@media screen { body { padding:10px; } }
</style></head><body onload="window.print()"><div class="label">
<div class="qr"><img src="https://quickchart.io/qr?size=180&text={{ urlencode($package->package_code) }}" alt="QR"><div class="code">{{ $package->package_code }}</div></div>
<div>
<div class="line"><strong>Vị trí:</strong> {{ $package->location->location_code }}</div>
<div class="line"><strong>Mã TP:</strong> {{ $package->ma_sp }}</div>
<div class="line"><strong>Mã nội bộ:</strong> {{ $package->internal_item_code }}</div>
<div class="line"><strong>Size:</strong> {{ $package->size }} | <strong>Màu:</strong> {{ $package->color }}</div>
<div class="line"><strong>Side:</strong> {{ $package->side }} | <strong>SL:</strong> {{ $package->quantity }}</div>
</div></div></body></html>
