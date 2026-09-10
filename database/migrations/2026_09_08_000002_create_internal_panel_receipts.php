<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInternalPanelReceipts extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        if (Schema::connection($this->connection)->hasTable('internal_order_tracking_rows')
            && !Schema::connection($this->connection)->hasColumn('internal_order_tracking_rows', 'panel')) {
            Schema::connection($this->connection)->table('internal_order_tracking_rows', function (Blueprint $table) {
                $table->string('panel', 150)->nullable()->after('order_number')->index();
            });
        }

        if (!Schema::connection($this->connection)->hasTable('internal_panel_receipts')) {
            Schema::connection($this->connection)->create('internal_panel_receipts', function (Blueprint $table) {
                $table->id();
                $table->string('receipt_number', 40)->unique();
                $table->date('received_date')->index();
                $table->string('source_type', 20)->default('MANUAL')->index();
                $table->string('source_file_name')->nullable();
                $table->text('note')->nullable();
                $table->decimal('total_quantity', 20, 3)->default(0);
                $table->unsignedInteger('line_count')->default(0);
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
            });
        }

        if (!Schema::connection($this->connection)->hasTable('internal_panel_receipt_lines')) {
            Schema::connection($this->connection)->create('internal_panel_receipt_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('receipt_id');
                $table->unsignedBigInteger('order_tracking_row_id')->nullable();
                $table->string('ps_number', 150)->index();
                $table->string('item_code', 150)->nullable()->index();
                $table->string('panel', 150)->nullable()->index();
                $table->decimal('received_quantity', 20, 3);
                $table->text('note')->nullable();
                $table->unsignedInteger('source_row')->nullable();
                $table->timestamps();

                $table->foreign('receipt_id', 'panel_receipt_lines_receipt_fk')
                    ->references('id')->on('internal_panel_receipts')->onDelete('cascade');
                $table->foreign('order_tracking_row_id', 'panel_receipt_lines_order_fk')
                    ->references('id')->on('internal_order_tracking_rows')->onDelete('set null');
                $table->index(['order_tracking_row_id', 'receipt_id'], 'panel_receipt_lines_order_receipt_idx');
            });
        }
    }

    public function down()
    {
        Schema::connection($this->connection)->dropIfExists('internal_panel_receipt_lines');
        Schema::connection($this->connection)->dropIfExists('internal_panel_receipts');
        if (Schema::connection($this->connection)->hasTable('internal_order_tracking_rows')
            && Schema::connection($this->connection)->hasColumn('internal_order_tracking_rows', 'panel')) {
            Schema::connection($this->connection)->table('internal_order_tracking_rows', function (Blueprint $table) {
                $table->dropColumn('panel');
            });
        }
    }
}
