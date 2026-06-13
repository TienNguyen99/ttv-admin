<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProductionOrderReferenceToIssueLines extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->table('internal_material_issue_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('production_order_id')->nullable()->after('issue_id');
            $table->string('production_order', 100)->nullable()->after('production_order_id');
            $table->text('purchase_order')->nullable()->after('production_order');
            $table->string('customer', 200)->nullable()->after('purchase_order');
            $table->decimal('ordered_quantity', 18, 3)->nullable()->after('dvt');

            $table->index('production_order', 'issue_lines_production_order_idx');
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->table('internal_material_issue_lines', function (Blueprint $table) {
            $table->dropIndex('issue_lines_production_order_idx');
            $table->dropColumn([
                'production_order_id',
                'production_order',
                'purchase_order',
                'customer',
                'ordered_quantity',
            ]);
        });
    }
}
