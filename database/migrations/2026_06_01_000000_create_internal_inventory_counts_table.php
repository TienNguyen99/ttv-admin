<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInternalInventoryCountsTable extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->create('inventory_counts', function (Blueprint $table) {
            $table->id();
            $table->string('ma_sp', 100);
            $table->string('ma_ko', 50)->default('');
            $table->decimal('counted_quantity', 18, 3)->default(0);
            $table->date('checked_at');
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->unique(['ma_sp', 'ma_ko', 'checked_at'], 'inventory_counts_item_warehouse_date_unique');
            $table->index(['checked_at', 'ma_sp']);
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->dropIfExists('inventory_counts');
    }
}
