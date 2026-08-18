<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class NormalizeLegacyStocktakeWeightEntries extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        DB::connection($this->connection)
            ->table('internal_stocktake_count_entries')
            ->where('input_type', 'base')
            ->whereNotNull('weight_kg')
            ->update([
                'input_type' => 'kg',
                'input_quantity' => DB::raw('weight_kg'),
                'input_unit' => 'KG',
                'updated_at' => now(),
            ]);
    }

    public function down()
    {
        // The original base quantity cannot be reconstructed after normalization.
    }
}
