<?php

namespace Tests\Feature;

use App\Models\InternalOrderTrackingRow;
use App\Services\GoogleSheetReader;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class InternalOrderTrackingGoogleSyncTest extends TestCase
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

    public function test_it_syncs_duplicate_panel_rows_from_google_sheet(): void
    {
        $suffix = strtoupper(substr(md5((string) microtime(true)), 0, 8));
        $ps = 'PS-GOOGLE-' . $suffix;
        $reader = Mockery::mock(GoogleSheetReader::class);
        $reader->shouldReceive('isConfigured')->once()->andReturn(true);
        $reader->shouldReceive('values')->once()->andReturn([
            ['STT', 'MÃ HÀNG', 'PS#', 'PANEL', 'SIZE', 'MÀU', 'SỐ LƯỢNG ĐẶT HÀNG'],
            [1, 'ITEM-' . $suffix, $ps, 'FRONT', '42', 'BLACK', 100],
            [2, 'ITEM-' . $suffix, $ps, 'FRONT', '42', 'BLACK', 100],
        ]);
        $this->app->instance(GoogleSheetReader::class, $reader);

        $this->postJson('/api/don-hang-noi-bo/dong-bo')
            ->assertOk()
            ->assertJsonPath('data.created', 2)
            ->assertJsonPath('data.processed', 2);

        $rows = InternalOrderTrackingRow::query()
            ->where('order_number', $ps)
            ->orderBy('source_row')
            ->get();

        $this->assertCount(2, $rows);
        $this->assertNotSame($rows[0]->row_key, $rows[1]->row_key);
        $this->assertSame('DON_HANG_TACH', $rows[0]->source_sheet);
        $this->assertSame('BLACK', $rows[0]->fabric_color);
    }

    public function test_empty_google_source_does_not_archive_existing_orders(): void
    {
        $row = InternalOrderTrackingRow::query()->create([
            'sheet_code' => 'A',
            'source_sheet' => 'DON_HANG_TACH',
            'row_key' => hash('sha256', 'existing-google-order-' . microtime(true)),
            'source_row' => 2,
            'item_code' => 'ITEM-EXISTING',
            'order_number' => 'PS-EXISTING',
            'panel' => 'FRONT',
            'order_quantity' => 100,
            'remaining_quantity' => 100,
            'status' => 'pending',
            'is_active' => true,
        ]);

        $reader = Mockery::mock(GoogleSheetReader::class);
        $reader->shouldReceive('isConfigured')->once()->andReturn(true);
        $reader->shouldReceive('values')->once()->andReturn([
            ['STT', 'MÃ HÀNG', 'PS#', 'PANEL'],
        ]);
        $this->app->instance(GoogleSheetReader::class, $reader);

        $this->postJson('/api/don-hang-noi-bo/dong-bo')->assertStatus(422);
        $this->assertTrue((bool) $row->fresh()->is_active);
    }
}
