<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInternalWeavingTables extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->create('internal_weaving_items', function (Blueprint $table) {
            $table->id();
            $table->string('item_code', 120)->unique();
            $table->string('item_name', 500)->nullable();
            $table->string('customer', 200)->nullable();
            $table->string('unit', 50)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['customer', 'item_name'], 'weaving_items_customer_name_idx');
        });

        Schema::connection($this->connection)->create('internal_weaving_boms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('weaving_item_id');
            $table->string('material_code', 120);
            $table->string('material_name', 500)->nullable();
            $table->string('unit', 50)->nullable();
            $table->decimal('consumption_per_unit', 18, 6)->default(0);
            $table->decimal('waste_percent', 8, 3)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('weaving_item_id')->references('id')->on('internal_weaving_items')->onDelete('cascade');
            $table->unique(['weaving_item_id', 'material_code'], 'weaving_boms_item_material_unique');
            $table->index('material_code', 'weaving_boms_material_idx');
        });

        Schema::connection($this->connection)->create('internal_weaving_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_code', 120)->unique();
            $table->unsignedBigInteger('weaving_item_id')->nullable();
            $table->string('item_code', 120);
            $table->string('customer', 200)->nullable();
            $table->decimal('order_quantity', 18, 3)->default(0);
            $table->string('unit', 50)->nullable();
            $table->date('order_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 40)->default('draft');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('weaving_item_id')->references('id')->on('internal_weaving_items')->nullOnDelete();
            $table->index(['item_code', 'status'], 'weaving_orders_item_status_idx');
            $table->index(['order_date', 'due_date'], 'weaving_orders_dates_idx');
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->dropIfExists('internal_weaving_orders');
        Schema::connection($this->connection)->dropIfExists('internal_weaving_boms');
        Schema::connection($this->connection)->dropIfExists('internal_weaving_items');
    }
}
