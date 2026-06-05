<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateInternalOpeningStocksTable extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->create('internal_opening_stocks', function (Blueprint $table) {
            $table->id();
            $table->date('period_month');
            $table->string('warehouse_code', 50)->default('');
            $table->string('location_code', 100)->default('');
            $table->string('ma_hh', 100);
            $table->string('internal_item_code', 100)->default('');
            $table->string('size', 100)->default('');
            $table->string('color', 100)->default('');
            $table->string('side', 100)->default('');
            $table->decimal('quantity', 18, 3)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['period_month', 'warehouse_code', 'ma_hh']);
        });

        DB::connection($this->connection)->statement("
            INSERT INTO internal_opening_stocks
                (period_month, warehouse_code, location_code, ma_hh, internal_item_code, size, color, side, quantity, note, created_at, updated_at)
            SELECT
                DATE_FORMAT(ip.checked_at, '%Y-%m-01') as period_month,
                COALESCE(NULLIF(ip.ma_ko, ''), NULLIF(wl.warehouse_code, ''), '') as warehouse_code,
                COALESCE(wl.location_code, '') as location_code,
                ip.ma_sp as ma_hh,
                COALESCE(ip.internal_item_code, '') as internal_item_code,
                COALESCE(ip.size, '') as size,
                COALESCE(ip.color, '') as color,
                COALESCE(ip.side, '') as side,
                SUM(ip.quantity) as quantity,
                'Backfill tu ton hien tai' as note,
                NOW(),
                NOW()
            FROM inventory_packages ip
            INNER JOIN warehouse_locations wl ON wl.id = ip.warehouse_location_id
            WHERE ip.quantity > 0
            GROUP BY
                DATE_FORMAT(ip.checked_at, '%Y-%m-01'),
                ip.ma_ko,
                wl.warehouse_code,
                wl.location_code,
                ip.ma_sp,
                ip.internal_item_code,
                ip.size,
                ip.color,
                ip.side
        ");
    }

    public function down()
    {
        Schema::connection($this->connection)->dropIfExists('internal_opening_stocks');
    }
}
