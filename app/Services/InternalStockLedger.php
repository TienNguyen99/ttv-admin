<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class InternalStockLedger
{
    public function query(string $monthStart, string $monthEnd): Builder
    {
        $catalogOpening = DB::connection('internal')->table('internal_item_catalogs as c')
            ->select(
                DB::raw("'' as warehouse_code"),
                DB::raw("COALESCE(NULLIF(c.shelf_code, ''), 'CHUA-XEP') as location_code"),
                DB::raw("'' as ma_hh"),
                DB::raw("COALESCE(c.item_code, '') as internal_item_code"),
                DB::raw("COALESCE(c.size, '') as size"),
                DB::raw("COALESCE(c.color, '') as color"),
                DB::raw("COALESCE(c.side, '') as side"),
                DB::raw('SUM(c.opening_quantity) as opening_quantity'),
                DB::raw('0 as receipt_quantity'),
                DB::raw('0 as issue_quantity')
            )
            ->where('c.is_active', true)
            ->whereRaw('COALESCE(c.opening_quantity, 0) <> 0')
            ->whereRaw("TRIM(COALESCE(c.item_code, '')) <> ''")
            ->whereNotExists(function ($query) use ($monthEnd) {
                $query->select(DB::raw(1))
                    ->from('internal_stocktake_lines as stl')
                    ->join('internal_stocktake_sessions as sts', 'sts.id', '=', 'stl.session_id')
                    ->where('sts.status', 'posted')
                    ->whereNotNull('stl.counted_quantity')
                    ->whereDate('sts.count_date', '<=', $monthEnd)
                    ->whereRaw("UPPER(TRIM(COALESCE(stl.internal_item_code, ''))) = UPPER(TRIM(COALESCE(c.item_code, '')))")
                    ->whereRaw("(TRIM(COALESCE(stl.size, '')) = '' OR UPPER(TRIM(stl.size)) = UPPER(TRIM(COALESCE(c.size, ''))))")
                    ->whereRaw("(TRIM(COALESCE(stl.color, '')) = '' OR UPPER(TRIM(stl.color)) = UPPER(TRIM(COALESCE(c.color, ''))))")
                    ->whereRaw("(TRIM(COALESCE(stl.side, '')) = '' OR UPPER(TRIM(stl.side)) = UPPER(TRIM(COALESCE(c.side, ''))))")
                    ->whereRaw("(
                        UPPER(TRIM(COALESCE(stl.location_code, ''))) = UPPER(TRIM(COALESCE(NULLIF(c.shelf_code, ''), 'CHUA-XEP')))
                        OR (
                            COALESCE(sts.cutoff_scope, 'legacy_global') = 'legacy_global'
                            AND
                            TRIM(COALESCE(stl.size, '')) = ''
                            AND TRIM(COALESCE(stl.color, '')) = ''
                            AND TRIM(COALESCE(stl.side, '')) = ''
                        )
                    )");
            })
            ->whereNotExists(function ($query) use ($monthStart) {
                $query->select(DB::raw(1))
                    ->from('internal_opening_stocks as os')
                    ->whereDate('os.period_month', '<=', $monthStart)
                    ->whereRaw("UPPER(TRIM(COALESCE(os.internal_item_code, ''))) = UPPER(TRIM(COALESCE(c.item_code, '')))");
            })
            ->groupBy('c.shelf_code', 'c.item_code', 'c.size', 'c.color', 'c.side');

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
            ->whereNotExists(function ($query) use ($monthEnd) {
                $query->select(DB::raw(1))
                    ->from('internal_stocktake_lines as stl')
                    ->join('internal_stocktake_sessions as sts', 'sts.id', '=', 'stl.session_id')
                    ->where('sts.status', 'posted')
                    ->whereNotNull('stl.counted_quantity')
                    ->whereDate('sts.count_date', '<=', $monthEnd)
                    ->whereRaw("UPPER(TRIM(COALESCE(stl.internal_item_code, ''))) = UPPER(TRIM(COALESCE(internal_opening_stocks.internal_item_code, '')))")
                    ->whereRaw("(TRIM(COALESCE(stl.size, '')) = '' OR UPPER(TRIM(stl.size)) = UPPER(TRIM(COALESCE(internal_opening_stocks.size, ''))))")
                    ->whereRaw("(TRIM(COALESCE(stl.color, '')) = '' OR UPPER(TRIM(stl.color)) = UPPER(TRIM(COALESCE(internal_opening_stocks.color, ''))))")
                    ->whereRaw("(TRIM(COALESCE(stl.side, '')) = '' OR UPPER(TRIM(stl.side)) = UPPER(TRIM(COALESCE(internal_opening_stocks.side, ''))))")
                    ->whereRaw("(
                        UPPER(TRIM(COALESCE(stl.location_code, ''))) = UPPER(TRIM(COALESCE(internal_opening_stocks.location_code, '')))
                        OR (
                            COALESCE(sts.cutoff_scope, 'legacy_global') = 'legacy_global'
                            AND
                            TRIM(COALESCE(stl.size, '')) = ''
                            AND TRIM(COALESCE(stl.color, '')) = ''
                            AND TRIM(COALESCE(stl.side, '')) = ''
                        )
                    )");
            })
            ->groupBy('warehouse_code', 'location_code', 'ma_hh', 'internal_item_code', 'size', 'color', 'side');

        $stocktakeOpening = $this->stocktakeOpeningQuery($monthEnd);

        $receiptsBeforeMonth = $this->receiptQuery()
            ->addSelect(
                DB::raw('SUM(COALESCE(l.base_quantity, l.quantity)) as opening_quantity'),
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
        $this->afterLatestStocktake(
            $receiptsBeforeMonth,
            'r.receipt_date',
            'r.created_at',
            "COALESCE(l.location_code, r.location_code, '')",
            'l.internal_item_code',
            'l.size',
            'l.color',
            'l.side',
            $monthEnd
        );

        $issuesBeforeMonth = $this->issueQuery()
            ->addSelect(
                DB::raw('SUM(-COALESCE(l.base_quantity, l.quantity)) as opening_quantity'),
                DB::raw('0 as receipt_quantity'),
                DB::raw('0 as issue_quantity')
            )
            ->whereDate('i.issue_date', '<', $monthStart)
            ->groupBy('i.warehouse_code', 'l.location_code', 'l.ma_hh', 'l.internal_item_code', 'l.size', 'l.color', 'l.side');
        $this->afterLatestStocktake(
            $issuesBeforeMonth,
            'i.issue_date',
            'i.created_at',
            "COALESCE(l.location_code, '')",
            'l.internal_item_code',
            'l.size',
            'l.color',
            'l.side',
            $monthEnd
        );

        $receiptsThisMonth = $this->receiptQuery()
            ->addSelect(
                DB::raw('0 as opening_quantity'),
                DB::raw('SUM(COALESCE(l.base_quantity, l.quantity)) as receipt_quantity'),
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
        $this->afterLatestStocktake(
            $receiptsThisMonth,
            'r.receipt_date',
            'r.created_at',
            "COALESCE(l.location_code, r.location_code, '')",
            'l.internal_item_code',
            'l.size',
            'l.color',
            'l.side',
            $monthEnd
        );

        $issuesThisMonth = $this->issueQuery()
            ->addSelect(
                DB::raw('0 as opening_quantity'),
                DB::raw('0 as receipt_quantity'),
                DB::raw('SUM(COALESCE(l.base_quantity, l.quantity)) as issue_quantity')
            )
            ->whereBetween('i.issue_date', [$monthStart, $monthEnd])
            ->groupBy('i.warehouse_code', 'l.location_code', 'l.ma_hh', 'l.internal_item_code', 'l.size', 'l.color', 'l.side');
        $this->afterLatestStocktake(
            $issuesThisMonth,
            'i.issue_date',
            'i.created_at',
            "COALESCE(l.location_code, '')",
            'l.internal_item_code',
            'l.size',
            'l.color',
            'l.side',
            $monthEnd
        );

        return DB::connection('internal')->query()
            ->fromSub(
                $catalogOpening
                    ->unionAll($openingAdjustments)
                    ->unionAll($stocktakeOpening)
                    ->unionAll($receiptsBeforeMonth)
                    ->unionAll($issuesBeforeMonth)
                    ->unionAll($receiptsThisMonth)
                    ->unionAll($issuesThisMonth),
                'ledger'
            );
    }

    private function stocktakeOpeningQuery(string $monthEnd): Builder
    {
        return DB::connection('internal')->table('internal_stocktake_lines as stl')
            ->join('internal_stocktake_sessions as sts', 'sts.id', '=', 'stl.session_id')
            ->select(
                DB::raw("'' as warehouse_code"),
                DB::raw("COALESCE(stl.location_code, 'CHUA-XEP') as location_code"),
                DB::raw("COALESCE(stl.ma_hh, '') as ma_hh"),
                DB::raw("COALESCE(stl.internal_item_code, '') as internal_item_code"),
                DB::raw("COALESCE(stl.size, '') as size"),
                DB::raw("COALESCE(stl.color, '') as color"),
                DB::raw("COALESCE(stl.side, '') as side"),
                DB::raw('stl.counted_quantity as opening_quantity'),
                DB::raw('0 as receipt_quantity'),
                DB::raw('0 as issue_quantity')
            )
            ->where('sts.status', 'posted')
            ->whereNotNull('stl.counted_quantity')
            ->whereDate('sts.count_date', '<=', $monthEnd)
            ->whereRaw("sts.id = (
                SELECT sts2.id
                FROM internal_stocktake_lines stl2
                INNER JOIN internal_stocktake_sessions sts2 ON sts2.id = stl2.session_id
                WHERE sts2.status = 'posted'
                  AND stl2.counted_quantity IS NOT NULL
                  AND sts2.count_date <= ?
                  AND UPPER(TRIM(COALESCE(stl2.location_code, ''))) = UPPER(TRIM(COALESCE(stl.location_code, '')))
                  AND UPPER(TRIM(COALESCE(stl2.internal_item_code, ''))) = UPPER(TRIM(COALESCE(stl.internal_item_code, '')))
                  AND (
                      COALESCE(sts.cutoff_scope, 'legacy_global') = 'location'
                      OR (
                          UPPER(TRIM(COALESCE(stl2.size, ''))) = UPPER(TRIM(COALESCE(stl.size, '')))
                          AND UPPER(TRIM(COALESCE(stl2.color, ''))) = UPPER(TRIM(COALESCE(stl.color, '')))
                          AND UPPER(TRIM(COALESCE(stl2.side, ''))) = UPPER(TRIM(COALESCE(stl.side, '')))
                      )
                  )
                ORDER BY sts2.count_date DESC, sts2.id DESC
                LIMIT 1
            )", [$monthEnd]);
    }

    private function afterLatestStocktake(
        Builder $query,
        string $dateColumn,
        string $createdAtColumn,
        string $locationExpression,
        string $itemCodeExpression,
        string $sizeExpression,
        string $colorExpression,
        string $sideExpression,
        string $monthEnd
    ): void {
        $matching = function (string $lineAlias) use (
            $itemCodeExpression,
            $sizeExpression,
            $colorExpression,
            $sideExpression
        ): string {
            return "UPPER(TRIM(COALESCE({$lineAlias}.internal_item_code, ''))) = UPPER(TRIM(COALESCE({$itemCodeExpression}, '')))
                AND (TRIM(COALESCE({$lineAlias}.size, '')) = '' OR UPPER(TRIM({$lineAlias}.size)) = UPPER(TRIM(COALESCE({$sizeExpression}, ''))))
                AND (TRIM(COALESCE({$lineAlias}.color, '')) = '' OR UPPER(TRIM({$lineAlias}.color)) = UPPER(TRIM(COALESCE({$colorExpression}, ''))))
                AND (TRIM(COALESCE({$lineAlias}.side, '')) = '' OR UPPER(TRIM({$lineAlias}.side)) = UPPER(TRIM(COALESCE({$sideExpression}, ''))))";
        };

        $documentTimestamp = "CASE
            WHEN DATE({$createdAtColumn}) = {$dateColumn} THEN {$createdAtColumn}
            ELSE TIMESTAMP({$dateColumn}, '23:59:59')
        END";
        $stocktakeTimestamp = function (string $sessionAlias, string $lineAlias): string {
            return "TIMESTAMP(
                {$sessionAlias}.count_date,
                CASE
                    WHEN DATE(COALESCE({$lineAlias}.counted_at, {$sessionAlias}.completed_at)) = {$sessionAlias}.count_date
                        THEN TIME(COALESCE({$lineAlias}.counted_at, {$sessionAlias}.completed_at))
                    ELSE '23:59:59'
                END
            )";
        };

        $query->whereRaw("{$documentTimestamp} > COALESCE(
            (
                SELECT MAX({$stocktakeTimestamp('sts_exact', 'stl_exact')})
                FROM internal_stocktake_lines stl_exact
                INNER JOIN internal_stocktake_sessions sts_exact ON sts_exact.id = stl_exact.session_id
                WHERE sts_exact.status = 'posted'
                  AND stl_exact.counted_quantity IS NOT NULL
                  AND sts_exact.count_date <= ?
                  AND {$matching('stl_exact')}
                  AND UPPER(TRIM(COALESCE(stl_exact.location_code, ''))) = UPPER(TRIM(COALESCE({$locationExpression}, '')))
            ),
            (
                SELECT MAX({$stocktakeTimestamp('sts_item', 'stl_item')})
                FROM internal_stocktake_lines stl_item
                INNER JOIN internal_stocktake_sessions sts_item ON sts_item.id = stl_item.session_id
                WHERE sts_item.status = 'posted'
                  AND COALESCE(sts_item.cutoff_scope, 'legacy_global') = 'legacy_global'
                  AND stl_item.counted_quantity IS NOT NULL
                  AND sts_item.count_date <= ?
                  AND {$matching('stl_item')}
                  AND TRIM(COALESCE(stl_item.size, '')) = ''
                  AND TRIM(COALESCE(stl_item.color, '')) = ''
                  AND TRIM(COALESCE(stl_item.side, '')) = ''
            ),
            (
                SELECT MIN(TIMESTAMP(sts_initial.count_date, '00:00:00'))
                FROM internal_stocktake_lines stl_initial
                INNER JOIN internal_stocktake_sessions sts_initial ON sts_initial.id = stl_initial.session_id
                WHERE sts_initial.status = 'posted'
                  AND COALESCE(sts_initial.cutoff_scope, 'legacy_global') = 'legacy_global'
                  AND sts_initial.count_date <= ?
                  AND stl_initial.internal_item_code = TRIM(COALESCE({$itemCodeExpression}, ''))
            ),
            '1900-01-01 00:00:00'
        )", [$monthEnd, $monthEnd, $monthEnd]);
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
            ->whereIn('r.source', ['Phieu nhap thanh pham', 'Dieu chinh kiem ke'])
            ->where('r.status', 'posted');
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
            )
            ->whereRaw("COALESCE(i.issue_type, 'material') <> 'production'")
            ->whereIn('i.status', ['posted', 'completed']);
    }
}
