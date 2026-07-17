<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMetadataToInternalWeavingTables extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->table('internal_weaving_items', function (Blueprint $table) {
            if (!Schema::connection($this->connection)->hasColumn('internal_weaving_items', 'design_code')) {
                $table->string('design_code', 200)->nullable()->after('item_name');
            }
            if (!Schema::connection($this->connection)->hasColumn('internal_weaving_items', 'metadata_json')) {
                $table->longText('metadata_json')->nullable()->after('note');
            }
        });

        Schema::connection($this->connection)->table('internal_weaving_boms', function (Blueprint $table) {
            if (!Schema::connection($this->connection)->hasColumn('internal_weaving_boms', 'metadata_json')) {
                $table->longText('metadata_json')->nullable()->after('note');
            }
        });

        Schema::connection($this->connection)->table('internal_weaving_orders', function (Blueprint $table) {
            if (!Schema::connection($this->connection)->hasColumn('internal_weaving_orders', 'po_number')) {
                $table->string('po_number', 200)->nullable()->after('customer');
            }
            if (!Schema::connection($this->connection)->hasColumn('internal_weaving_orders', 'design_code')) {
                $table->string('design_code', 200)->nullable()->after('po_number');
            }
            if (!Schema::connection($this->connection)->hasColumn('internal_weaving_orders', 'metadata_json')) {
                $table->longText('metadata_json')->nullable()->after('note');
            }
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->table('internal_weaving_orders', function (Blueprint $table) {
            foreach (['metadata_json', 'design_code', 'po_number'] as $column) {
                if (Schema::connection($this->connection)->hasColumn('internal_weaving_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::connection($this->connection)->table('internal_weaving_boms', function (Blueprint $table) {
            if (Schema::connection($this->connection)->hasColumn('internal_weaving_boms', 'metadata_json')) {
                $table->dropColumn('metadata_json');
            }
        });

        Schema::connection($this->connection)->table('internal_weaving_items', function (Blueprint $table) {
            foreach (['metadata_json', 'design_code'] as $column) {
                if (Schema::connection($this->connection)->hasColumn('internal_weaving_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
