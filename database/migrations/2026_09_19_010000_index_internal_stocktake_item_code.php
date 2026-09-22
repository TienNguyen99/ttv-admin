<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class IndexInternalStocktakeItemCode extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->table('internal_stocktake_lines', function (Blueprint $table) {
            $table->index(['internal_item_code', 'session_id'], 'stocktake_item_session_idx');
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->table('internal_stocktake_lines', function (Blueprint $table) {
            $table->dropIndex('stocktake_item_session_idx');
        });
    }
}
