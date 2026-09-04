<?php

namespace Tests\Feature;

use App\Services\InternalStockLedger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InternalStockLedgerStatusTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = ['internal'];

    public function test_draft_documents_do_not_change_stock(): void
    {
        $code = 'LEDGER-STATUS-' . strtoupper(uniqid());
        $now = now();

        $postedReceipt = DB::connection('internal')->table('internal_material_receipts')->insertGetId([
            'receipt_code' => 'PN-' . uniqid(),
            'receipt_date' => '2099-01-10',
            'source' => 'Phieu nhap thanh pham',
            'status' => 'posted',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $draftReceipt = DB::connection('internal')->table('internal_material_receipts')->insertGetId([
            'receipt_code' => 'PN-' . uniqid(),
            'receipt_date' => '2099-01-10',
            'source' => 'Phieu nhap thanh pham',
            'status' => 'draft',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        foreach ([[$postedReceipt, 20], [$draftReceipt, 900]] as $receipt) {
            DB::connection('internal')->table('internal_material_receipt_lines')->insert([
                'receipt_id' => $receipt[0],
                'ma_hh' => $code,
                'internal_item_code' => $code,
                'quantity' => $receipt[1],
                'base_quantity' => $receipt[1],
                'base_dvt' => 'PCS',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $postedIssue = DB::connection('internal')->table('internal_material_issues')->insertGetId([
            'issue_code' => 'PX-' . uniqid(),
            'issue_date' => '2099-01-11',
            'issue_type' => 'material',
            'status' => 'posted',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $draftIssue = DB::connection('internal')->table('internal_material_issues')->insertGetId([
            'issue_code' => 'PX-' . uniqid(),
            'issue_date' => '2099-01-11',
            'issue_type' => 'material',
            'status' => 'draft',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        foreach ([[$postedIssue, 5], [$draftIssue, 800]] as $issue) {
            DB::connection('internal')->table('internal_material_issue_lines')->insert([
                'issue_id' => $issue[0],
                'ma_hh' => $code,
                'internal_item_code' => $code,
                'quantity' => $issue[1],
                'base_quantity' => $issue[1],
                'base_dvt' => 'PCS',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $row = app(InternalStockLedger::class)
            ->query('2099-01-01', '2099-01-31')
            ->where('internal_item_code', $code)
            ->selectRaw('SUM(receipt_quantity) as receipt_quantity')
            ->selectRaw('SUM(issue_quantity) as issue_quantity')
            ->selectRaw('SUM(opening_quantity + receipt_quantity - issue_quantity) as total_quantity')
            ->first();

        $this->assertSame(20.0, (float) $row->receipt_quantity);
        $this->assertSame(5.0, (float) $row->issue_quantity);
        $this->assertSame(15.0, (float) $row->total_quantity);
    }
}
