<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWeightNormToCatalogAndStocktake extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->table('internal_item_catalogs', function (Blueprint $table) {
            if (!Schema::connection($this->connection)->hasColumn('internal_item_catalogs', 'weight_per_unit_grams')) {
                $table->decimal('weight_per_unit_grams', 18, 6)->nullable()->after('opening_quantity');
            }
        });
        Schema::connection($this->connection)->table('internal_stocktake_lines', function (Blueprint $table) {
            if (!Schema::connection($this->connection)->hasColumn('internal_stocktake_lines', 'weight_per_unit_grams')) {
                $table->decimal('weight_per_unit_grams', 18, 6)->nullable()->after('unit');
            }
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->table('internal_stocktake_lines', function (Blueprint $table) {
            if (Schema::connection($this->connection)->hasColumn('internal_stocktake_lines', 'weight_per_unit_grams')) {
                $table->dropColumn('weight_per_unit_grams');
            }
        });
        Schema::connection($this->connection)->table('internal_item_catalogs', function (Blueprint $table) {
            if (Schema::connection($this->connection)->hasColumn('internal_item_catalogs', 'weight_per_unit_grams')) {
                $table->dropColumn('weight_per_unit_grams');
            }
        });
    }
}
