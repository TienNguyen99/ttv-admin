<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInternalBtpProductionOrdersTable extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->create('internal_btp_production_orders', function (Blueprint $table) {
            $table->id();
            $table->string('btp_order_code', 50)->unique();
            $table->date('order_date');
            $table->string('status', 30)->default('draft');
            $table->unsignedBigInteger('issue_id')->nullable();
            $table->string('issue_code', 50)->nullable();
            $table->string('receiver_name', 150)->nullable();
            $table->string('department', 150)->nullable();
            $table->string('purpose', 255)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'order_date'], 'btp_orders_status_date_idx');
            $table->index('issue_id', 'btp_orders_issue_idx');
        });

        Schema::connection($this->connection)->create('internal_btp_production_order_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('btp_order_id');
            $table->unsignedBigInteger('source_issue_line_id')->nullable();
            $table->string('ma_hh', 100);
            $table->string('ten_hh', 255)->nullable();
            $table->string('dvt', 50)->nullable();
            $table->decimal('ordered_quantity', 18, 3)->nullable();
            $table->decimal('quantity', 18, 3);
            $table->string('location_code', 100)->nullable();
            $table->string('internal_item_code', 100)->nullable();
            $table->string('size', 100)->nullable();
            $table->string('color', 100)->nullable();
            $table->string('side', 100)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('btp_order_id')->references('id')->on('internal_btp_production_orders')->onDelete('cascade');
            $table->index(['ma_hh', 'internal_item_code'], 'btp_order_lines_item_idx');
            $table->index('source_issue_line_id', 'btp_order_lines_issue_line_idx');
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->dropIfExists('internal_btp_production_order_lines');
        Schema::connection($this->connection)->dropIfExists('internal_btp_production_orders');
    }
}
