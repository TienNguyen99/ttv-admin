<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFormulaFieldsToProductBom extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->table('internal_product_bom_lines', function (Blueprint $table) {
            $table->string('formula_code', 80)->nullable()->after('calculation_mode');
            $table->decimal('formula_output_per_unit', 20, 9)->nullable()->after('formula_code');
            $table->string('formula_output_unit', 50)->nullable()->after('formula_output_per_unit');
            $table->decimal('formula_part', 20, 6)->nullable()->after('formula_output_unit');
        });

        Schema::connection($this->connection)->table('internal_production_order_bom_snapshots', function (Blueprint $table) {
            $table->string('formula_code', 80)->nullable()->after('calculation_mode');
            $table->decimal('formula_output_per_unit', 20, 9)->nullable()->after('formula_code');
            $table->string('formula_output_unit', 50)->nullable()->after('formula_output_per_unit');
            $table->decimal('formula_part', 20, 6)->nullable()->after('formula_output_unit');
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->table('internal_production_order_bom_snapshots', function (Blueprint $table) {
            $table->dropColumn(['formula_code', 'formula_output_per_unit', 'formula_output_unit', 'formula_part']);
        });

        Schema::connection($this->connection)->table('internal_product_bom_lines', function (Blueprint $table) {
            $table->dropColumn(['formula_code', 'formula_output_per_unit', 'formula_output_unit', 'formula_part']);
        });
    }
}
