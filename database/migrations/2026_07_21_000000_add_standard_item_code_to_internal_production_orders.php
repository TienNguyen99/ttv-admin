<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStandardItemCodeToInternalProductionOrders extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->table('internal_production_orders', function (Blueprint $table) {
            $table->string('standard_item_code', 200)->nullable()->after('item_code');
            $table->index('standard_item_code', 'prod_orders_standard_item_idx');
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->table('internal_production_orders', function (Blueprint $table) {
            $table->dropIndex('prod_orders_standard_item_idx');
            $table->dropColumn('standard_item_code');
        });
    }
}
