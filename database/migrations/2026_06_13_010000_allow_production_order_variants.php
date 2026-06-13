<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AllowProductionOrderVariants extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->table('internal_production_orders', function (Blueprint $table) {
            $table->string('row_key', 64)->nullable()->after('id');
        });

        DB::connection($this->connection)
            ->table('internal_production_orders')
            ->orderBy('id')
            ->get()
            ->each(function ($row) {
                DB::connection($this->connection)
                    ->table('internal_production_orders')
                    ->where('id', $row->id)
                    ->update([
                        'row_key' => $this->makeRowKey(
                            $row->production_order,
                            $row->item_code,
                            $row->size,
                            $row->color,
                            $row->description
                        ),
                    ]);
            });

        Schema::connection($this->connection)->table('internal_production_orders', function (Blueprint $table) {
            $table->dropUnique('internal_production_orders_production_order_unique');
            $table->unique('row_key', 'prod_orders_row_key_unique');
            $table->index('production_order', 'prod_orders_order_idx');
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->table('internal_production_orders', function (Blueprint $table) {
            $table->dropIndex('prod_orders_order_idx');
            $table->dropUnique('prod_orders_row_key_unique');
            $table->dropColumn('row_key');
            $table->unique('production_order');
        });
    }

    private function makeRowKey($productionOrder, $itemCode, $size, $color, $description): string
    {
        $parts = [$productionOrder, $itemCode, $size, $color, $description];
        $parts = array_map(function ($value) {
            return mb_strtoupper(trim((string) $value));
        }, $parts);

        return hash('sha256', implode('|', $parts));
    }
}
