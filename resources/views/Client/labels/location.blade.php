<!DOCTYPE html>
<html lang="vi"><head><meta charset="UTF-8"><title>{{ $location->location_code }}</title>
<style>
@page { size: 70mm 40mm; margin: 2mm; } body { margin:0; font-family:Arial,sans-serif; text-align:center; }
.label { width:66mm; height:36mm; } img { width:23mm; height:23mm; } .code { font-size:16px; font-weight:700; } .name { font-size:10px; }
@media screen { body { padding:10px; } }
</style></head><body onload="window.print()"><div class="label">
<img src="https://quickchart.io/qr?size=180&text={{ urlencode(request()->root() . '/client/kiem-ton-kho/vi-tri/' . $location->id) }}" alt="QR">
<div class="code">{{ $location->location_code }}</div><div class="name">{{ $location->location_name }}</div>
</div></body></html>
