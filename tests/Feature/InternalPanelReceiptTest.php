<?php

namespace Tests\Feature;

use App\Models\InternalOrderTrackingRow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class InternalPanelReceiptTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::connection('internal')->beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::connection('internal')->rollBack();
        parent::tearDown();
    }

    public function test_worker_can_search_every_panel_of_the_same_ps(): void
    {
        $suffix = strtoupper(substr(md5((string) microtime(true)), 0, 8));
        $ps = 'PS-TEST-' . $suffix;
        $this->order($suffix . '-1', $ps, 'FRONT', 100);
        $this->order($suffix . '-2', $ps, 'BACK', 80);

        $this->getJson('/api/hang-ve-panel/tim-don?keyword=' . urlencode($ps))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.ps_number', $ps)
            ->assertJsonFragment(['panel' => 'FRONT'])
            ->assertJsonFragment(['panel' => 'BACK']);
    }

    public function test_receipt_keeps_duplicate_lines_and_recalculates_progress_after_delete(): void
    {
        $suffix = strtoupper(substr(md5((string) microtime(true)), 0, 8));
        $order = $this->order($suffix, 'PS-DUP-' . $suffix, 'FRONT', 100);

        $response = $this->postJson('/api/hang-ve-panel', [
            'received_date' => '2026-09-08',
            'source_type' => 'MANUAL',
            'note' => 'Test duplicate lines',
            'lines' => [
                ['order_tracking_row_id' => $order->id, 'received_quantity' => 10],
                ['order_tracking_row_id' => $order->id, 'received_quantity' => 10],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.line_count', 2)
            ->assertJsonPath('data.total_quantity', 20);

        $receiptId = $response->json('data.id');
        $this->assertSame(2, DB::connection('internal')->table('internal_panel_receipt_lines')->where('receipt_id', $receiptId)->count());
        $this->assertSame(20.0, (float) $order->fresh()->received_quantity);
        $this->assertSame(80.0, (float) $order->fresh()->remaining_quantity);

        $this->get('/client/hang-ve-panel/' . $receiptId . '/in')
            ->assertOk()
            ->assertSee('PHIẾU HÀNG VỀ PANEL')
            ->assertSee('PS-DUP-' . $suffix);

        $this->deleteJson('/api/hang-ve-panel/' . $receiptId)->assertOk();
        $this->assertSame(0.0, (float) $order->fresh()->received_quantity);
        $this->assertSame(100.0, (float) $order->fresh()->remaining_quantity);
    }

    public function test_excel_preview_preserves_duplicate_received_rows(): void
    {
        $suffix = strtoupper(substr(md5((string) microtime(true)), 0, 8));
        $ps = 'PS-IMPORT-' . $suffix;
        $this->order($suffix, $ps, 'FRONT', 100);

        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->fromArray([
            ['PS#', 'PANEL', 'SỐ LƯỢNG'],
            [$ps, 'front', 10],
            [$ps, 'front', 10],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'panel-receipt-') . '.xlsx';
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        try {
            $this->post('/api/hang-ve-panel/import-preview', [
                'file' => new UploadedFile($path, 'hang-ve.xlsx', null, null, true),
            ], ['Accept' => 'application/json'])
                ->assertOk()
                ->assertJsonCount(2, 'data')
                ->assertJsonPath('summary.matched_rows', 2)
                ->assertJsonPath('summary.total_quantity', 20);
        } finally {
            @unlink($path);
        }
    }

    public function test_source_import_keeps_duplicate_rows_and_existing_receipt_progress(): void
    {
        $suffix = strtoupper(substr(md5((string) microtime(true)), 0, 8));
        $ps = 'PS-SOURCE-' . $suffix;
        $book = new Spreadsheet();
        $book->getActiveSheet()->setTitle('A')->fromArray([
            ['MÃ HÀNG', 'PS#', 'PANEL', 'SỐ LƯỢNG'],
            ['ITEM-' . $suffix, $ps, 'FRONT', 100],
            ['ITEM-' . $suffix, $ps, 'FRONT', 100],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'panel-source-') . '.xlsx';
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        try {
            $this->importSource($path)->assertOk()->assertJsonPath('data.created', 2);
            $orders = InternalOrderTrackingRow::query()->where('order_number', $ps)->orderBy('source_row')->get();
            $this->assertCount(2, $orders);
            $this->assertNotSame($orders[0]->row_key, $orders[1]->row_key);

            $this->postJson('/api/hang-ve-panel', [
                'received_date' => '2026-09-08',
                'lines' => [['order_tracking_row_id' => $orders[0]->id, 'received_quantity' => 25]],
            ])->assertCreated();

            $this->importSource($path)->assertOk()->assertJsonPath('data.updated', 2);
            $this->assertSame(25.0, (float) $orders[0]->fresh()->received_quantity);
            $this->assertSame(75.0, (float) $orders[0]->fresh()->remaining_quantity);
        } finally {
            @unlink($path);
        }
    }

    private function order(string $rowKey, string $ps, string $panel, float $quantity): InternalOrderTrackingRow
    {
        return InternalOrderTrackingRow::query()->create([
            'sheet_code' => 'A',
            'source_sheet' => 'A',
            'row_key' => hash('sha256', $rowKey),
            'source_row' => random_int(10000, 99999),
            'item_code' => 'ITEM-' . $rowKey,
            'order_number' => $ps,
            'panel' => $panel,
            'order_quantity' => $quantity,
            'received_quantity' => 0,
            'remaining_quantity' => $quantity,
            'status' => 'pending',
            'is_active' => true,
        ]);
    }

    private function importSource(string $path)
    {
        return $this->post('/api/don-hang-noi-bo/import', [
            'file' => new UploadedFile($path, 'don-hang-da-tach.xlsx', null, null, true),
        ], ['Accept' => 'application/json']);
    }
}
