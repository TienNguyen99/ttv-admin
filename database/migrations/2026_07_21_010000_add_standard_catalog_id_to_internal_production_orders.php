<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStandardCatalogIdToInternalProductionOrders extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->table('internal_production_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('standard_catalog_id')->nullable()->after('standard_item_code');
            $table->index('standard_catalog_id', 'prod_orders_standard_catalog_idx');
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->table('internal_production_orders', function (Blueprint $table) {
            $table->dropIndex('prod_orders_standard_catalog_idx');
            $table->dropColumn('standard_catalog_id');
        });
    }
}
