<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class IncludeSideInInventoryCountUniqueKey extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->table('inventory_counts', function (Blueprint $table) {
            $table->dropUnique('inventory_counts_item_warehouse_variant_date_unique');
            $table->unique(
                ['ma_sp', 'ma_ko', 'size', 'color', 'side', 'checked_at'],
                'inventory_counts_item_warehouse_variant_side_date_unique'
            );
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->table('inventory_counts', function (Blueprint $table) {
            $table->dropUnique('inventory_counts_item_warehouse_variant_side_date_unique');
            $table->unique(
                ['ma_sp', 'ma_ko', 'size', 'color', 'checked_at'],
                'inventory_counts_item_warehouse_variant_date_unique'
            );
        });
    }
}
