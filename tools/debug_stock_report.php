<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\InternalStockLedger;
use Illuminate\Support\Facades\DB;

$mode = $argv[1] ?? 'sessions';

if ($mode === 'sessions') {
    $rows = DB::connection('internal')
        ->table('internal_stocktake_sessions')
        ->orderByDesc('id')
        ->limit(10)
        ->get(['id', 'stocktake_code', 'count_date', 'status', 'posted_at']);
    echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit(0);
}

if ($mode === 'negative-ledger') {
    $monthStart = $argv[2] ?? now('Asia/Ho_Chi_Minh')->startOfMonth()->format('Y-m-d');
    $monthEnd = $argv[3] ?? now('Asia/Ho_Chi_Minh')->format('Y-m-d');
    $rows = app(InternalStockLedger::class)
        ->query($monthStart, $monthEnd)
        ->select(
            'location_code',
            'ma_hh',
            'internal_item_code',
            'size',
            'color',
            'side',
            DB::raw('SUM(opening_quantity) as opening_quantity'),
            DB::raw('SUM(receipt_quantity) as receipt_quantity'),
            DB::raw('SUM(issue_quantity) as issue_quantity'),
            DB::raw('SUM(opening_quantity + receipt_quantity - issue_quantity) as closing_quantity')
        )
        ->groupBy('location_code', 'ma_hh', 'internal_item_code', 'size', 'color', 'side')
        ->havingRaw('SUM(opening_quantity + receipt_quantity - issue_quantity) < 0')
        ->orderBy('internal_item_code')
        ->limit(50)
        ->get();
    echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit(0);
}

if ($mode === 'negative-counts') {
    $rows = DB::connection('internal')
        ->table('inventory_counts')
        ->where('counted_quantity', '<', 0)
        ->orderBy('internal_item_code')
        ->limit(50)
        ->get([
            'id',
            'checked_at',
            'ma_sp',
            'internal_item_code',
            'size',
            'color',
            'side',
            'counted_quantity',
            'note',
        ]);
    echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit(0);
}

if ($mode === 'item-counts') {
    $itemCode = mb_strtoupper(trim((string) ($argv[2] ?? '')));
    $rows = DB::connection('internal')
        ->table('inventory_counts')
        ->whereRaw('UPPER(TRIM(COALESCE(internal_item_code, ma_sp))) = ?', [$itemCode])
        ->orderBy('checked_at')
        ->get([
            'id',
            'checked_at',
            'ma_sp',
            'ma_ko',
            'internal_item_code',
            'size',
            'color',
            'side',
            'counted_quantity',
            'note',
        ]);
    echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit(0);
}

if ($mode === 'item-ledger') {
    $itemCode = mb_strtoupper(trim((string) ($argv[2] ?? '')));
    $monthStart = $argv[3] ?? now('Asia/Ho_Chi_Minh')->startOfMonth()->format('Y-m-d');
    $monthEnd = $argv[4] ?? now('Asia/Ho_Chi_Minh')->format('Y-m-d');
    $rows = app(InternalStockLedger::class)
        ->query($monthStart, $monthEnd)
        ->select(
            'location_code',
            'ma_hh',
            'internal_item_code',
            'size',
            'color',
            'side',
            DB::raw('SUM(opening_quantity) as opening_quantity'),
            DB::raw('SUM(receipt_quantity) as receipt_quantity'),
            DB::raw('SUM(issue_quantity) as issue_quantity'),
            DB::raw('SUM(opening_quantity + receipt_quantity - issue_quantity) as closing_quantity')
        )
        ->whereRaw('UPPER(TRIM(COALESCE(internal_item_code, ma_hh))) = ?', [$itemCode])
        ->groupBy('location_code', 'ma_hh', 'internal_item_code', 'size', 'color', 'side')
        ->orderBy('location_code')
        ->get();
    echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit(0);
}

if ($mode === 'opening-item') {
    $itemCode = mb_strtoupper(trim((string) ($argv[2] ?? '')));
    $rows = DB::connection('internal')
        ->table('internal_opening_stocks')
        ->whereRaw('UPPER(TRIM(COALESCE(internal_item_code, ma_hh))) = ?', [$itemCode])
        ->orderBy('period_month')
        ->get([
            'period_month',
            'warehouse_code',
            'location_code',
            'ma_hh',
            'internal_item_code',
            'size',
            'color',
            'side',
            'quantity',
        ]);
    echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit(0);
}

fwrite(STDERR, "Unknown mode: {$mode}\n");
exit(1);
