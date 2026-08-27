<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOrderSourceToProductBomSnapshots extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->table('internal_production_order_bom_snapshots', function (Blueprint $table) {
            $table->dropUnique('production_bom_snapshot_order_sequence_unique');
            $table->string('order_type', 20)->default('central')->after('id');
            $table->unsignedBigInteger('order_line_id')->default(0)->after('production_order_id');
            $table->unique(
                ['order_type', 'production_order_id', 'order_line_id', 'sequence'],
                'product_bom_snapshot_source_line_sequence_unique'
            );
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->table('internal_production_order_bom_snapshots', function (Blueprint $table) {
            $table->dropUnique('product_bom_snapshot_source_line_sequence_unique');
            $table->dropColumn(['order_type', 'order_line_id']);
            $table->unique(
                ['production_order_id', 'sequence'],
                'production_bom_snapshot_order_sequence_unique'
            );
        });
    }
}
