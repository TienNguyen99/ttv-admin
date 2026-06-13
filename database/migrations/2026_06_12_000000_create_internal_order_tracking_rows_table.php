<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInternalOrderTrackingRowsTable extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->create('internal_order_tracking_rows', function (Blueprint $table) {
            $table->id();
            $table->string('sheet_code', 20);
            $table->string('source_sheet', 150);
            $table->string('row_key', 64);
            $table->unsignedInteger('source_row')->nullable();
            $table->string('sequence_no', 50)->nullable();
            $table->date('export_date')->nullable();
            $table->string('item_code', 150)->nullable();
            $table->string('order_number', 150)->nullable();
            $table->string('size', 100)->nullable();
            $table->string('fabric_color', 255)->nullable();
            $table->string('logo_color', 255)->nullable();
            $table->date('panel_out_date')->nullable();
            $table->string('voucher_number', 150)->nullable();
            $table->decimal('order_quantity', 18, 3)->default(0);
            $table->decimal('quantity', 18, 3)->default(0);
            $table->decimal('quantity_front', 18, 3)->default(0);
            $table->decimal('quantity_back', 18, 3)->default(0);
            $table->decimal('received_quantity', 18, 3)->default(0);
            $table->date('delivery_date')->nullable();
            $table->decimal('front_pass', 18, 3)->default(0);
            $table->decimal('front_fail', 18, 3)->default(0);
            $table->decimal('back_pass', 18, 3)->default(0);
            $table->decimal('back_fail', 18, 3)->default(0);
            $table->decimal('remaining_quantity', 18, 3)->default(0);
            $table->string('status', 50)->default('pending');
            $table->text('note')->nullable();
            $table->json('extra_data')->nullable();
            $table->string('import_batch', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['sheet_code', 'row_key'], 'order_rows_sheet_key_unique');
            $table->index(['sheet_code', 'is_active', 'delivery_date'], 'order_rows_sheet_active_date_idx');
            $table->index(['item_code', 'order_number'], 'order_rows_item_order_idx');
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->dropIfExists('internal_order_tracking_rows');
    }
}
