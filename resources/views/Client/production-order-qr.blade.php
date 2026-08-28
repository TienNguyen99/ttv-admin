<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>QR lệnh {{ $order['production_order'] }}</title>
    <style>
        @page { size:58mm 40mm; margin:0; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:Arial,sans-serif; }
        .label { width:58mm; height:40mm; display:grid; grid-template-columns:25mm 1fr; align-items:center; gap:2mm; padding:3mm; overflow:hidden; }
        img { width:24mm; height:24mm; }
        strong { display:block; font-size:15px; overflow-wrap:anywhere; }
        span { display:block; margin-top:2mm; font-size:9px; line-height:1.25; }
        @media screen { body { background:#eef4fb; } .label { margin:20px auto; background:#fff; } }
    </style>
</head>
<body onload="window.print()">
<div class="label">
    <img src="{{ url('/qr-code') }}?text={{ urlencode($order['qr_url']) }}&size=420&margin=2" alt="QR {{ $order['production_order'] }}">
    <div><strong>{{ $order['production_order'] }}</strong><span>{{ $order['customer'] ?: 'Nội bộ' }}</span><span>{{ $order['purchase_order'] }}</span></div>
</div>
</body>
</html>
