<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInternalStocktakeTables extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->create('internal_stocktake_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('stocktake_code', 50)->unique();
            $table->string('name', 255);
            $table->date('count_date');
            $table->string('status', 30)->default('counting');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('adjustment_receipt_id')->nullable();
            $table->unsignedBigInteger('adjustment_issue_id')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('posted_at')->nullable();
            $table->timestamps();

            $table->foreign('adjustment_receipt_id')->references('id')->on('internal_material_receipts')->nullOnDelete();
            $table->foreign('adjustment_issue_id')->references('id')->on('internal_material_issues')->nullOnDelete();
            $table->index(['status', 'count_date'], 'stocktake_session_status_date_idx');
        });

        Schema::connection($this->connection)->create('internal_stocktake_locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id');
            $table->unsignedBigInteger('warehouse_location_id')->nullable();
            $table->string('location_code', 100);
            $table->string('status', 30)->default('pending');
            $table->text('note')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('session_id')->references('id')->on('internal_stocktake_sessions')->cascadeOnDelete();
            $table->foreign('warehouse_location_id')->references('id')->on('warehouse_locations')->nullOnDelete();
            $table->unique(['session_id', 'location_code'], 'stocktake_session_location_unique');
            $table->index(['session_id', 'status'], 'stocktake_location_status_idx');
        });

        Schema::connection($this->connection)->create('internal_stocktake_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id');
            $table->unsignedBigInteger('session_location_id');
            $table->string('line_key', 40);
            $table->string('location_code', 100);
            $table->string('ma_hh', 100)->default('');
            $table->string('internal_item_code', 100);
            $table->string('item_name', 500)->nullable();
            $table->string('unit', 50)->nullable();
            $table->string('size', 100)->default('');
            $table->string('color', 100)->default('');
            $table->string('side', 100)->default('');
            $table->decimal('expected_quantity', 18, 3)->default(0);
            $table->decimal('counted_quantity', 18, 3)->nullable();
            $table->text('note')->nullable();
            $table->dateTime('counted_at')->nullable();
            $table->timestamps();

            $table->foreign('session_id')->references('id')->on('internal_stocktake_sessions')->cascadeOnDelete();
            $table->foreign('session_location_id')->references('id')->on('internal_stocktake_locations')->cascadeOnDelete();
            $table->unique(['session_id', 'line_key'], 'stocktake_session_line_unique');
            $table->index(['session_location_id', 'internal_item_code'], 'stocktake_location_item_idx');
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->dropIfExists('internal_stocktake_lines');
        Schema::connection($this->connection)->dropIfExists('internal_stocktake_locations');
        Schema::connection($this->connection)->dropIfExists('internal_stocktake_sessions');
    }
}
