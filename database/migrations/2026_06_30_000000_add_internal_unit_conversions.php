<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddInternalUnitConversions extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->create('internal_unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->string('item_code', 100)->nullable();
            $table->string('from_unit', 50);
            $table->string('to_unit', 50);
            $table->decimal('factor', 20, 10);
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->unique(['item_code', 'from_unit', 'to_unit'], 'unit_conversion_item_from_to_unique');
            $table->index(['from_unit', 'to_unit'], 'unit_conversion_from_to_idx');
        });

        Schema::connection($this->connection)->table('internal_material_receipt_lines', function (Blueprint $table) {
            if (!Schema::connection($this->connection)->hasColumn('internal_material_receipt_lines', 'base_quantity')) {
                $table->decimal('base_quantity', 18, 3)->nullable()->after('quantity');
            }
            if (!Schema::connection($this->connection)->hasColumn('internal_material_receipt_lines', 'base_dvt')) {
                $table->string('base_dvt', 50)->nullable()->after('base_quantity');
            }
            if (!Schema::connection($this->connection)->hasColumn('internal_material_receipt_lines', 'unit_factor')) {
                $table->decimal('unit_factor', 20, 10)->nullable()->after('base_dvt');
            }
        });

        Schema::connection($this->connection)->table('internal_material_issue_lines', function (Blueprint $table) {
            if (!Schema::connection($this->connection)->hasColumn('internal_material_issue_lines', 'base_quantity')) {
                $table->decimal('base_quantity', 18, 3)->nullable()->after('quantity');
            }
            if (!Schema::connection($this->connection)->hasColumn('internal_material_issue_lines', 'base_dvt')) {
                $table->string('base_dvt', 50)->nullable()->after('base_quantity');
            }
            if (!Schema::connection($this->connection)->hasColumn('internal_material_issue_lines', 'unit_factor')) {
                $table->decimal('unit_factor', 20, 10)->nullable()->after('base_dvt');
            }
        });

        DB::connection($this->connection)->table('internal_unit_conversions')->upsert([
            [
                'item_code' => null,
                'from_unit' => 'YARD',
                'to_unit' => 'M',
                'factor' => 0.9144,
                'note' => 'Quy doi chieu dai mac dinh: yard sang met.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'item_code' => null,
                'from_unit' => 'M',
                'to_unit' => 'YARD',
                'factor' => 1.0936132983,
                'note' => 'Quy doi chieu dai mac dinh: met sang yard.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['item_code', 'from_unit', 'to_unit'], ['factor', 'note', 'updated_at']);

        DB::connection($this->connection)->table('internal_material_receipt_lines')
            ->whereNull('base_quantity')
            ->update([
                'base_quantity' => DB::raw('quantity'),
                'base_dvt' => DB::raw('dvt'),
                'unit_factor' => 1,
            ]);

        DB::connection($this->connection)->table('internal_material_issue_lines')
            ->whereNull('base_quantity')
            ->update([
                'base_quantity' => DB::raw('quantity'),
                'base_dvt' => DB::raw('dvt'),
                'unit_factor' => 1,
            ]);
    }

    public function down()
    {
        Schema::connection($this->connection)->table('internal_material_issue_lines', function (Blueprint $table) {
            foreach (['base_quantity', 'base_dvt', 'unit_factor'] as $column) {
                if (Schema::connection($this->connection)->hasColumn('internal_material_issue_lines', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::connection($this->connection)->table('internal_material_receipt_lines', function (Blueprint $table) {
            foreach (['base_quantity', 'base_dvt', 'unit_factor'] as $column) {
                if (Schema::connection($this->connection)->hasColumn('internal_material_receipt_lines', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::connection($this->connection)->dropIfExists('internal_unit_conversions');
    }
}
