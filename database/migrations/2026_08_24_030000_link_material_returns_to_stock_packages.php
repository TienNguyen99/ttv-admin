<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class LinkMaterialReturnsToStockPackages extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->table('internal_material_return_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('inventory_package_id')->nullable()->after('issue_line_id');
            $table->index('inventory_package_id', 'material_return_line_package_idx');
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->table('internal_material_return_lines', function (Blueprint $table) {
            $table->dropIndex('material_return_line_package_idx');
            $table->dropColumn('inventory_package_id');
        });
    }
}
