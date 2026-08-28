<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFifoLookupIndexesToInventoryPackages extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->table('inventory_packages', function (Blueprint $table) {
            $table->index(
                ['internal_item_code', 'ma_ko', 'checked_at'],
                'inventory_packages_internal_warehouse_fifo_index'
            );
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->table('inventory_packages', function (Blueprint $table) {
            $table->dropIndex('inventory_packages_internal_warehouse_fifo_index');
        });
    }
}
