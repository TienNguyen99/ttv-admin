<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInternalProductionOperationProgress extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->create('internal_production_operation_progress', function (Blueprint $table) {
            $table->id();
            $table->string('production_order_code', 100);
            $table->string('operation_code', 50);
            $table->string('operation_name', 200);
            $table->unsignedInteger('sequence')->default(1);
            $table->string('status', 20)->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('updated_by', 150)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->unique(
                ['production_order_code', 'operation_code'],
                'production_operation_order_code_unique'
            );
            $table->index(
                ['production_order_code', 'sequence'],
                'production_operation_order_sequence_idx'
            );
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->dropIfExists('internal_production_operation_progress');
    }
}
