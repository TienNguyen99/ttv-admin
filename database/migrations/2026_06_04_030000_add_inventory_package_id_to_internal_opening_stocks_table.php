<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInventoryPackageIdToInternalOpeningStocksTable extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->table('internal_opening_stocks', function (Blueprint $table) {
            $table->unsignedBigInteger('inventory_package_id')->nullable()->after('id');
            $table->foreign('inventory_package_id')->references('id')->on('inventory_packages')->nullOnDelete();
            $table->index('inventory_package_id');
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->table('internal_opening_stocks', function (Blueprint $table) {
            $table->dropForeign(['inventory_package_id']);
            $table->dropIndex(['inventory_package_id']);
            $table->dropColumn('inventory_package_id');
        });
    }
}
