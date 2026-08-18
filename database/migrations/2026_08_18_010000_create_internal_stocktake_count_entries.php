<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateInternalStocktakeCountEntries extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->create('internal_stocktake_count_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id');
            $table->unsignedBigInteger('stocktake_line_id');
            $table->string('input_type', 20)->default('base');
            $table->decimal('input_quantity', 18, 6);
            $table->string('input_unit', 50)->nullable();
            $table->decimal('converted_quantity', 18, 6);
            $table->decimal('weight_kg', 18, 6)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->foreign('session_id')->references('id')->on('internal_stocktake_sessions')->cascadeOnDelete();
            $table->foreign('stocktake_line_id')->references('id')->on('internal_stocktake_lines')->cascadeOnDelete();
            $table->index(['stocktake_line_id', 'id'], 'stocktake_entry_line_idx');
        });

        $now = now();
        DB::connection($this->connection)->table('internal_stocktake_lines')
            ->whereNotNull('counted_quantity')
            ->orderBy('id')
            ->chunkById(500, function ($lines) use ($now) {
                $rows = [];
                foreach ($lines as $line) {
                    $rows[] = [
                        'session_id' => $line->session_id,
                        'stocktake_line_id' => $line->id,
                        'input_type' => $line->counted_weight_kg === null ? 'base' : 'kg',
                        'input_quantity' => $line->counted_weight_kg === null ? $line->counted_quantity : $line->counted_weight_kg,
                        'input_unit' => $line->counted_weight_kg === null ? $line->unit : 'KG',
                        'converted_quantity' => $line->counted_quantity,
                        'weight_kg' => $line->counted_weight_kg,
                        'note' => 'So dem truoc khi nang cap chi tiet kien',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if ($rows) {
                    DB::connection($this->connection)->table('internal_stocktake_count_entries')->insert($rows);
                }
            });
    }

    public function down()
    {
        Schema::connection($this->connection)->dropIfExists('internal_stocktake_count_entries');
    }
}
