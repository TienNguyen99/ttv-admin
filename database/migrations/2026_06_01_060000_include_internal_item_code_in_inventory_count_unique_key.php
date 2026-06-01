<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class IncludeInternalItemCodeInInventoryCountUniqueKey extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->table('inventory_counts', function (Blueprint $table) {
            $table->dropUnique('inventory_counts_item_warehouse_variant_side_date_unique');
            $table->unique(
                ['ma_sp', 'ma_ko', 'internal_item_code', 'size', 'color', 'side', 'checked_at'],
                'inventory_counts_item_warehouse_internal_variant_date_unique'
            );
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->table('inventory_counts', function (Blueprint $table) {
            $table->dropUnique('inventory_counts_item_warehouse_internal_variant_date_unique');
            $table->unique(
                ['ma_sp', 'ma_ko', 'size', 'color', 'side', 'checked_at'],
                'inventory_counts_item_warehouse_variant_side_date_unique'
            );
        });
    }
}
