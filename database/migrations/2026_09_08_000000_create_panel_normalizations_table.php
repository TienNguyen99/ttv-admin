<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePanelNormalizationsTable extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        if (Schema::connection($this->connection)->hasTable('panel_normalizations')) {
            return;
        }

        Schema::connection($this->connection)->create('panel_normalizations', function (Blueprint $table) {
            $table->id();
            $table->string('standard_name')->nullable();
            $table->string('normalized_standard_name')->nullable()->unique('panel_normalizations_standard_unique');
            $table->string('status', 20)->default('AUTO')->index();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();

            $table->index(['is_active', 'status', 'sort_order'], 'panel_normalizations_lookup_idx');
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->dropIfExists('panel_normalizations');
    }
}
