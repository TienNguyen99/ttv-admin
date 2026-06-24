<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class InternalStockLedger
{
    public function query(string $monthStart, string $monthEnd): Builder
    {
        $openingAdjustments = DB::connection('internal')->table('internal_opening_stocks')
            ->select(
                'warehouse_code',
                'location_code',
                'ma_hh',
                'internal_item_code',
                'size',
                'color',
                'side',
                DB::raw('SUM(quantity) as opening_quantity'),
                DB::raw('0 as receipt_quantity'),
                DB::raw('0 as issue_quantity')
            )
            ->whereDate('period_month', '<=', $monthStart)
            ->groupBy('warehouse_code', 'location_code', 'ma_hh', 'internal_item_code', 'size', 'color', 'side');

        $receiptsBeforeMonth = $this->receiptQuery()
            ->addSelect(
                DB::raw('SUM(l.quantity) as opening_quantity'),
                DB::raw('0 as receipt_quantity'),
                DB::raw('0 as issue_quantity')
            )
            ->whereDate('r.receipt_date', '<', $monthStart)
            ->groupBy(
                'r.warehouse_code',
                DB::raw("COALESCE(l.location_code, r.location_code, '')"),
                'l.ma_hh',
                'l.internal_item_code',
                'l.size',
                'l.color',
                'l.side'
            );

        $issuesBeforeMonth = $this->issueQuery()
            ->addSelect(
                DB::raw('SUM(-l.quantity) as opening_quantity'),
                DB::raw('0 as receipt_quantity'),
                DB::raw('0 as issue_quantity')
            )
            ->whereDate('i.issue_date', '<', $monthStart)
            ->groupBy('i.warehouse_code', 'l.location_code', 'l.ma_hh', 'l.internal_item_code', 'l.size', 'l.color', 'l.side');

        $receiptsThisMonth = $this->receiptQuery()
            ->addSelect(
                DB::raw('0 as opening_quantity'),
                DB::raw('SUM(l.quantity) as receipt_quantity'),
                DB::raw('0 as issue_quantity')
            )
            ->whereBetween('r.receipt_date', [$monthStart, $monthEnd])
            ->groupBy(
                'r.warehouse_code',
                DB::raw("COALESCE(l.location_code, r.location_code, '')"),
                'l.ma_hh',
                'l.internal_item_code',
                'l.size',
                'l.color',
                'l.side'
            );

        $issuesThisMonth = $this->issueQuery()
            ->addSelect(
                DB::raw('0 as opening_quantity'),
                DB::raw('0 as receipt_quantity'),
                DB::raw('SUM(l.quantity) as issue_quantity')
            )
            ->whereBetween('i.issue_date', [$monthStart, $monthEnd])
            ->groupBy('i.warehouse_code', 'l.location_code', 'l.ma_hh', 'l.internal_item_code', 'l.size', 'l.color', 'l.side');

        return DB::connection('internal')->query()
            ->fromSub(
                $openingAdjustments
                    ->unionAll($receiptsBeforeMonth)
                    ->unionAll($issuesBeforeMonth)
                    ->unionAll($receiptsThisMonth)
                    ->unionAll($issuesThisMonth),
                'ledger'
            );
    }

    private function receiptQuery(): Builder
    {
        return DB::connection('internal')->table('internal_material_receipt_lines as l')
            ->join('internal_material_receipts as r', 'r.id', '=', 'l.receipt_id')
            ->select(
                DB::raw("COALESCE(r.warehouse_code, '') as warehouse_code"),
                DB::raw("COALESCE(l.location_code, r.location_code, '') as location_code"),
                'l.ma_hh',
                DB::raw("COALESCE(l.internal_item_code, '') as internal_item_code"),
                DB::raw("COALESCE(l.size, '') as size"),
                DB::raw("COALESCE(l.color, '') as color"),
                DB::raw("COALESCE(l.side, '') as side")
            )
            ->where('r.source', 'Phieu nhap thanh pham');
    }

    private function issueQuery(): Builder
    {
        return DB::connection('internal')->table('internal_material_issue_lines as l')
            ->join('internal_material_issues as i', 'i.id', '=', 'l.issue_id')
            ->select(
                DB::raw("COALESCE(i.warehouse_code, '') as warehouse_code"),
                DB::raw("COALESCE(l.location_code, '') as location_code"),
                'l.ma_hh',
                DB::raw("COALESCE(l.internal_item_code, '') as internal_item_code"),
                DB::raw("COALESCE(l.size, '') as size"),
                DB::raw("COALESCE(l.color, '') as color"),
                DB::raw("COALESCE(l.side, '') as side")
            );
    }
}
