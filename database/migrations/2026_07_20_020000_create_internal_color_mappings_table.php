<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInternalColorMappingsTable extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->create('internal_color_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('color_code', 120)->unique();
            $table->string('color_name', 255);
            $table->string('hex', 7);
            $table->string('pantone_code', 80)->nullable();
            $table->string('note', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'color_name'], 'internal_color_active_name_idx');
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->dropIfExists('internal_color_mappings');
    }
}
