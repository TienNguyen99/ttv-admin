<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGridLayoutToWarehouseLocationsTable extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->table('warehouse_locations', function (Blueprint $table) {
            $table->unsignedSmallInteger('grid_x')->default(1)->after('bay_code');
            $table->unsignedSmallInteger('grid_y')->default(1)->after('grid_x');
            $table->unsignedSmallInteger('grid_w')->default(4)->after('grid_y');
            $table->unsignedSmallInteger('grid_h')->default(2)->after('grid_w');
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->table('warehouse_locations', function (Blueprint $table) {
            $table->dropColumn(['grid_x', 'grid_y', 'grid_w', 'grid_h']);
        });
    }
}
