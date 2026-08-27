<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddYieldCalculationToProductBom extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->table('internal_product_bom_lines', function (Blueprint $table) {
            $table->string('calculation_mode', 20)->default('consumption')->after('component_role');
            $table->decimal('yield_quantity', 20, 6)->nullable()->after('consumption_per_unit');
            $table->boolean('round_to_whole')->default(false)->after('waste_percent');
        });

        Schema::connection($this->connection)->table('internal_production_order_bom_snapshots', function (Blueprint $table) {
            $table->string('calculation_mode', 20)->default('consumption')->after('component_role');
            $table->decimal('yield_quantity', 20, 6)->nullable()->after('consumption_per_unit');
            $table->boolean('round_to_whole')->default(false)->after('waste_percent');
            $table->decimal('exact_required_quantity', 20, 6)->nullable()->after('required_quantity');
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->table('internal_production_order_bom_snapshots', function (Blueprint $table) {
            $table->dropColumn(['calculation_mode', 'yield_quantity', 'round_to_whole', 'exact_required_quantity']);
        });

        Schema::connection($this->connection)->table('internal_product_bom_lines', function (Blueprint $table) {
            $table->dropColumn(['calculation_mode', 'yield_quantity', 'round_to_whole']);
        });
    }
}
