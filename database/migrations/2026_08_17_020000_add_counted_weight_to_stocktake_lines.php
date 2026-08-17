<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCountedWeightToStocktakeLines extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->table('internal_stocktake_lines', function (Blueprint $table) {
            if (!Schema::connection($this->connection)->hasColumn('internal_stocktake_lines', 'counted_weight_kg')) {
                $table->decimal('counted_weight_kg', 18, 6)->nullable()->after('counted_quantity');
            }
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->table('internal_stocktake_lines', function (Blueprint $table) {
            if (Schema::connection($this->connection)->hasColumn('internal_stocktake_lines', 'counted_weight_kg')) {
                $table->dropColumn('counted_weight_kg');
            }
        });
    }
}
