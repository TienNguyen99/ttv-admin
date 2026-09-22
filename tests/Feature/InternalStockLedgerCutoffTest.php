<?php

namespace Tests\Feature;

use App\Services\InternalStockLedger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InternalStockLedgerCutoffTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = ['internal'];

    public function test_variant_stocktake_only_replaces_the_same_location_and_uses_same_day_time(): void
    {
        $code = 'LEDGER-CUTOFF-' . strtoupper(uniqid());
        $this->insertStocktake($code, 'CUT-A', 'M', 'RED', 100, '2037-10-10 10:00:00');

        $this->insertReceipt($code, 'CUT-A', 'M', 'RED', 30, '2037-10-05', '2037-10-05 08:00:00');
        $this->insertReceipt($code, 'CUT-B', 'M', 'RED', 40, '2037-10-05', '2037-10-05 08:00:00');
        $this->insertReceipt($code, 'CUT-A', 'M', 'RED', 50, '2037-10-10', '2037-10-10 09:00:00');
        $this->insertReceipt($code, 'CUT-A', 'M', 'RED', 7, '2037-10-10', '2037-10-10 11:00:00');

        $this->assertSame(147.0, $this->stockFor($code));
    }

    public function test_item_total_stocktake_only_replaces_old_stock_at_the_same_location(): void
    {
        $code = 'LEDGER-TOTAL-' . strtoupper(uniqid());
        $this->insertStocktake($code, 'TOTAL-A', '', '', 100, '2037-10-10 10:00:00');
        $this->insertReceipt($code, 'TOTAL-A', 'M', 'RED', 40, '2037-10-05', '2037-10-05 08:00:00');
        $this->insertReceipt($code, 'TOTAL-B', 'M', 'RED', 60, '2037-10-05', '2037-10-05 08:00:00');

        $this->assertSame(160.0, $this->stockFor($code));
    }

    public function test_legacy_item_total_stocktake_keeps_the_old_global_cutoff(): void
    {
        $code = 'LEDGER-LEGACY-' . strtoupper(uniqid());
        $this->insertStocktake($code, 'LEGACY-A', '', '', 100, '2037-10-10 10:00:00', 'legacy_global');
        $this->insertReceipt($code, 'LEGACY-B', 'M', 'RED', 40, '2037-10-05', '2037-10-05 08:00:00');

        $this->assertSame(100.0, $this->stockFor($code));
    }

    public function test_later_location_snapshot_replaces_earlier_variants_at_the_same_location(): void
    {
        $code = 'LEDGER-RECOUNT-' . strtoupper(uniqid());
        $this->insertStocktake($code, 'RECOUNT-A', 'M', 'RED', 100, '2037-10-10 10:00:00');
        $this->insertStocktake($code, 'RECOUNT-A', '', '', 25, '2037-10-11 10:00:00');
        $this->insertReceipt($code, 'RECOUNT-B', 'M', 'RED', 40, '2037-10-05', '2037-10-05 08:00:00');

        $this->assertSame(65.0, $this->stockFor($code));
    }

    public function test_uncounted_legacy_line_starts_a_new_period_without_old_movements(): void
    {
        $code = 'LEDGER-UNCOUNTED-' . strtoupper(uniqid());
        $this->insertStocktake($code, 'UNC-A', '', '', null, '2037-10-10 10:00:00', 'legacy_global');
        $this->insertReceipt($code, 'UNC-A', '', '', 30, '2037-10-05', '2037-10-05 08:00:00');
        $this->insertIssue($code, 'UNC-A', 11, '2037-10-06');
        $this->insertReceipt($code, 'UNC-A', '', '', 7, '2037-10-11', '2037-10-11 08:00:00');

        $this->assertSame(7.0, $this->stockFor($code));
    }

    private function stockFor(string $code): float
    {
        $row = app(InternalStockLedger::class)
            ->query('2037-10-01', '2037-10-31')
            ->where('internal_item_code', $code)
            ->selectRaw('SUM(opening_quantity + receipt_quantity - issue_quantity) as total_quantity')
            ->first();

        return (float) ($row->total_quantity ?? 0);
    }

    private function insertStocktake(
        string $code,
        string $locationCode,
        string $size,
        string $color,
        ?float $quantity,
        string $countedAt,
        string $cutoffScope = 'location'
    ): void {
        $countDate = substr($countedAt, 0, 10);
        $now = $countDate . ' 12:00:00';
        $sessionId = DB::connection('internal')->table('internal_stocktake_sessions')->insertGetId([
            'stocktake_code' => 'KK-' . strtoupper(uniqid()),
            'name' => 'Ledger cutoff test',
            'count_date' => $countDate,
            'status' => 'posted',
            'cutoff_scope' => $cutoffScope,
            'started_at' => $countDate . ' 08:00:00',
            'completed_at' => $countDate . ' 10:00:00',
            'posted_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $warehouseLocationId = DB::connection('internal')->table('warehouse_locations')->insertGetId([
            'location_code' => $locationCode . '-' . strtoupper(substr(uniqid(), -5)),
            'location_name' => $locationCode,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $sessionLocationId = DB::connection('internal')->table('internal_stocktake_locations')->insertGetId([
            'session_id' => $sessionId,
            'warehouse_location_id' => $warehouseLocationId,
            'location_code' => $locationCode,
            'status' => 'completed',
            'started_at' => $countDate . ' 08:00:00',
            'completed_at' => $countDate . ' 10:00:00',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::connection('internal')->table('internal_stocktake_lines')->insert([
            'session_id' => $sessionId,
            'session_location_id' => $sessionLocationId,
            'line_key' => sha1(implode('|', [$locationCode, $code, $size, $color])),
            'location_code' => $locationCode,
            'ma_hh' => $code,
            'internal_item_code' => $code,
            'size' => $size,
            'color' => $color,
            'side' => '',
            'expected_quantity' => 0,
            'counted_quantity' => $quantity,
            'counted_at' => $quantity === null ? null : $countedAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertReceipt(
        string $code,
        string $locationCode,
        string $size,
        string $color,
        float $quantity,
        string $receiptDate,
        string $createdAt
    ): void {
        $receiptId = DB::connection('internal')->table('internal_material_receipts')->insertGetId([
            'receipt_code' => 'PN-' . strtoupper(uniqid()),
            'receipt_date' => $receiptDate,
            'location_code' => $locationCode,
            'source' => 'Phieu nhap thanh pham',
            'status' => 'posted',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
        DB::connection('internal')->table('internal_material_receipt_lines')->insert([
            'receipt_id' => $receiptId,
            'ma_hh' => $code,
            'internal_item_code' => $code,
            'size' => $size,
            'color' => $color,
            'side' => '',
            'location_code' => $locationCode,
            'quantity' => $quantity,
            'base_quantity' => $quantity,
            'base_dvt' => 'PCS',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function insertIssue(string $code, string $locationCode, float $quantity, string $issueDate): void
    {
        $createdAt = $issueDate . ' 08:00:00';
        $issueId = DB::connection('internal')->table('internal_material_issues')->insertGetId([
            'issue_code' => 'PX-' . strtoupper(uniqid()),
            'issue_date' => $issueDate,
            'issue_type' => 'material',
            'status' => 'posted',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
        DB::connection('internal')->table('internal_material_issue_lines')->insert([
            'issue_id' => $issueId,
            'ma_hh' => $code,
            'internal_item_code' => $code,
            'location_code' => $locationCode,
            'quantity' => $quantity,
            'base_quantity' => $quantity,
            'base_dvt' => 'PCS',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
