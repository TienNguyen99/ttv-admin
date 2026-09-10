<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePanelNormalizationAliasesTable extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        if (Schema::connection($this->connection)->hasTable('panel_normalization_aliases')) {
            return;
        }

        Schema::connection($this->connection)->create('panel_normalization_aliases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('panel_normalization_id');
            $table->string('alias');
            $table->string('normalized_alias')->unique('panel_normalization_alias_unique');
            $table->timestamps();

            $table->index('panel_normalization_id', 'panel_normalization_alias_parent_idx');
            $table->foreign('panel_normalization_id', 'panel_normalization_alias_parent_fk')
                ->references('id')
                ->on('panel_normalizations')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->dropIfExists('panel_normalization_aliases');
    }
}
