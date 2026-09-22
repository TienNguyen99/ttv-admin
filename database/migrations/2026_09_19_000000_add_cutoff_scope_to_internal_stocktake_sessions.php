<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddCutoffScopeToInternalStocktakeSessions extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        if (!Schema::connection($this->connection)->hasColumn('internal_stocktake_sessions', 'cutoff_scope')) {
            Schema::connection($this->connection)->table('internal_stocktake_sessions', function (Blueprint $table) {
                $table->string('cutoff_scope', 30)->nullable()->after('status');
            });
        }

        DB::connection($this->connection)
            ->table('internal_stocktake_sessions')
            ->whereNull('cutoff_scope')
            ->update(['cutoff_scope' => 'legacy_global']);
    }

    public function down()
    {
        if (Schema::connection($this->connection)->hasColumn('internal_stocktake_sessions', 'cutoff_scope')) {
            Schema::connection($this->connection)->table('internal_stocktake_sessions', function (Blueprint $table) {
                $table->dropColumn('cutoff_scope');
            });
        }
    }
}
