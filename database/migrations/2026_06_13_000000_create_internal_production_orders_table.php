<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInternalProductionOrdersTable extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->create('internal_production_orders', function (Blueprint $table) {
            $table->id();
            $table->string('production_order', 100)->unique();
            $table->text('purchase_order')->nullable();
            $table->string('tracking_staff', 150)->nullable();
            $table->string('customer', 200)->nullable();
            $table->string('item_code', 200)->nullable();
            $table->text('specification')->nullable();
            $table->text('description')->nullable();
            $table->string('size', 255)->nullable();
            $table->text('color')->nullable();
            $table->string('unit', 50)->nullable();
            $table->decimal('order_quantity', 18, 3)->default(0);
            $table->string('location', 150)->nullable();
            $table->date('received_date')->nullable();
            $table->date('promised_date')->nullable();
            $table->date('customer_requested_date')->nullable();
            $table->string('delivery_place', 255)->nullable();
            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('source_row')->nullable();
            $table->json('raw_data')->nullable();
            $table->string('sync_batch', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'promised_date'], 'prod_orders_active_date_idx');
            $table->index(['customer', 'item_code'], 'prod_orders_customer_item_idx');
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->dropIfExists('internal_production_orders');
    }
}
