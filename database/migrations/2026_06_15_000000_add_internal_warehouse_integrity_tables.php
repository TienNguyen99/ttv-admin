<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddInternalWarehouseIntegrityTables extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->create('internal_document_sequences', function (Blueprint $table) {
            $table->string('sequence_key', 100)->primary();
            $table->unsignedBigInteger('current_value')->default(0);
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('internal_material_issue_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('issue_line_id');
            $table->unsignedBigInteger('inventory_package_id')->nullable();
            $table->unsignedBigInteger('warehouse_location_id')->nullable();
            $table->unsignedBigInteger('inventory_count_id')->nullable();
            $table->string('source_package_code', 100);
            $table->string('location_code', 100)->nullable();
            $table->string('ma_hh', 100);
            $table->string('warehouse_code', 50)->nullable();
            $table->string('internal_item_code', 100)->nullable();
            $table->string('size', 100)->nullable();
            $table->string('color', 100)->nullable();
            $table->string('side', 100)->nullable();
            $table->date('checked_at');
            $table->decimal('quantity', 18, 3);
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->foreign('issue_line_id')
                ->references('id')
                ->on('internal_material_issue_lines')
                ->onDelete('cascade');
            $table->index('source_package_code');
            $table->index('inventory_package_id');
        });

        Schema::connection($this->connection)->table('internal_material_receipt_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('production_order_id')->nullable()->after('inventory_package_id');
            $table->string('production_order', 100)->nullable()->after('production_order_id');
            $table->string('purchase_order', 1000)->nullable()->after('production_order');
            $table->string('customer', 200)->nullable()->after('purchase_order');
            $table->index('production_order');
        });

        DB::connection($this->connection)->statement(
            "UPDATE internal_material_receipt_lines AS receipt_line
             INNER JOIN internal_production_orders AS production_order
                ON production_order.production_order = receipt_line.note
             SET receipt_line.production_order_id = production_order.id,
                 receipt_line.production_order = production_order.production_order,
                 receipt_line.purchase_order = production_order.purchase_order,
                 receipt_line.customer = production_order.customer
             WHERE COALESCE(receipt_line.production_order, '') = ''"
        );

        Schema::connection($this->connection)->create('internal_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action', 100);
            $table->string('entity_type', 100);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('entity_code', 100)->nullable();
            $table->json('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->dropIfExists('internal_audit_logs');

        Schema::connection($this->connection)->table('internal_material_receipt_lines', function (Blueprint $table) {
            $table->dropIndex(['production_order']);
            $table->dropColumn([
                'production_order_id',
                'production_order',
                'purchase_order',
                'customer',
            ]);
        });

        Schema::connection($this->connection)->dropIfExists('internal_material_issue_allocations');
        Schema::connection($this->connection)->dropIfExists('internal_document_sequences');
    }
}
