<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLayoutFieldsToWarehouseLocationsTable extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->table('warehouse_locations', function (Blueprint $table) {
            $table->string('shelf_code', 20)->nullable()->after('warehouse_code');
            $table->unsignedTinyInteger('tier')->default(1)->after('shelf_code');
            $table->string('bay_code', 50)->nullable()->after('tier');
            $table->index(['shelf_code', 'tier']);
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->table('warehouse_locations', function (Blueprint $table) {
            $table->dropIndex(['shelf_code', 'tier']);
            $table->dropColumn(['shelf_code', 'tier', 'bay_code']);
        });
    }
}
