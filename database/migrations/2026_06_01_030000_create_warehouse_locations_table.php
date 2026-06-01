<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWarehouseLocationsTable extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->create('warehouse_locations', function (Blueprint $table) {
            $table->id();
            $table->string('location_code', 100)->unique();
            $table->string('warehouse_code', 50)->default('');
            $table->string('location_name', 255)->nullable();
            $table->string('status', 30)->default('pending');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->dropIfExists('warehouse_locations');
    }
}
