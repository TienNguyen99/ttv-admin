<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInventoryPackagesTable extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->create('inventory_packages', function (Blueprint $table) {
            $table->id();
            $table->string('package_code', 100)->unique();
            $table->unsignedBigInteger('warehouse_location_id');
            $table->unsignedBigInteger('inventory_count_id')->nullable();
            $table->string('ma_sp', 100);
            $table->string('ma_ko', 50)->default('');
            $table->string('internal_item_code', 100)->default('');
            $table->string('size', 100)->default('');
            $table->string('color', 100)->default('');
            $table->string('side', 100)->default('');
            $table->decimal('quantity', 18, 3)->default(0);
            $table->date('checked_at');
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->foreign('warehouse_location_id')->references('id')->on('warehouse_locations');
            $table->foreign('inventory_count_id')->references('id')->on('inventory_counts')->nullOnDelete();
            $table->index(['checked_at', 'warehouse_location_id']);
            $table->index(['ma_sp', 'ma_ko']);
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->dropIfExists('inventory_packages');
    }
}
