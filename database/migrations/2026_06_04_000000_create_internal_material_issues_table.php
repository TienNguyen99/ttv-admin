<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInternalMaterialIssuesTable extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->create('internal_material_issues', function (Blueprint $table) {
            $table->id();
            $table->string('issue_code', 50)->unique();
            $table->date('issue_date');
            $table->string('warehouse_code', 50)->nullable();
            $table->string('receiver_name', 150)->nullable();
            $table->string('department', 150)->nullable();
            $table->string('production_order', 100)->nullable();
            $table->string('purpose', 255)->nullable();
            $table->string('status', 30)->default('draft');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('internal_material_issue_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('issue_id');
            $table->string('ma_hh', 100);
            $table->string('ten_hh', 255)->nullable();
            $table->string('dvt', 50)->nullable();
            $table->decimal('quantity', 18, 3);
            $table->string('location_code', 100)->nullable();
            $table->string('internal_item_code', 100)->nullable();
            $table->string('size', 100)->nullable();
            $table->string('color', 100)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('issue_id')->references('id')->on('internal_material_issues')->onDelete('cascade');
            $table->index(['ma_hh', 'location_code']);
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->dropIfExists('internal_material_issue_lines');
        Schema::connection($this->connection)->dropIfExists('internal_material_issues');
    }
}
