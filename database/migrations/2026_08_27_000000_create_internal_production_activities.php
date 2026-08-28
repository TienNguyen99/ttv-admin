<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInternalProductionActivities extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->create('internal_production_activities', function (Blueprint $table) {
            $table->id();
            $table->string('activity_code', 50)->unique();
            $table->date('activity_date');
            $table->string('production_order_code', 100);
            $table->string('operation_code', 50);
            $table->string('operation_name', 200);
            $table->string('operator_name', 150)->nullable();
            $table->string('status', 20)->default('posted');
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['production_order_code', 'activity_date'], 'production_activity_order_date_idx');
            $table->index(['production_order_code', 'operation_code'], 'production_activity_order_operation_idx');
        });

        Schema::connection($this->connection)->create('internal_production_activity_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('activity_id');
            $table->unsignedBigInteger('production_order_id')->nullable();
            $table->string('source_item_code', 100)->nullable();
            $table->string('internal_item_code', 100);
            $table->string('item_name', 500)->nullable();
            $table->string('size', 255)->nullable();
            $table->string('color', 1000)->nullable();
            $table->string('unit', 50)->nullable();
            $table->decimal('planned_quantity', 20, 6)->default(0);
            $table->decimal('good_quantity', 20, 6)->default(0);
            $table->decimal('defect_quantity', 20, 6)->default(0);
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->foreign('activity_id')->references('id')->on('internal_production_activities')->onDelete('cascade');
            $table->foreign('production_order_id')->references('id')->on('internal_production_orders')->nullOnDelete();
            $table->index(['internal_item_code', 'activity_id'], 'production_activity_line_item_idx');
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->dropIfExists('internal_production_activity_lines');
        Schema::connection($this->connection)->dropIfExists('internal_production_activities');
    }
}
