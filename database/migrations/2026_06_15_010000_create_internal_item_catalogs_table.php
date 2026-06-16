<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInternalItemCatalogsTable extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->create('internal_item_catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('item_code', 200)->unique();
            $table->string('item_name', 500)->nullable();
            $table->string('unit', 50)->nullable();
            $table->string('shelf_code', 150)->nullable();
            $table->decimal('opening_quantity', 18, 3)->default(0);
            $table->string('image_url', 1000)->nullable();
            $table->unsignedInteger('source_row')->nullable();
            $table->json('raw_data')->nullable();
            $table->string('sync_batch', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'item_name'], 'item_catalog_active_name_idx');
            $table->index('shelf_code', 'item_catalog_shelf_idx');
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->dropIfExists('internal_item_catalogs');
    }
}
