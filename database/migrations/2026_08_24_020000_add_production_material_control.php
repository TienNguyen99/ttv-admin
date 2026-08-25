<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddProductionMaterialControl extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->table('internal_material_issue_lines', function (Blueprint $table) {
            if (!Schema::connection($this->connection)->hasColumn('internal_material_issue_lines', 'component_role')) {
                $table->string('component_role', 100)->default('CHUNG')->after('side');
            }
        });

        Schema::connection($this->connection)->create('internal_material_order_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('issue_line_id');
            $table->unsignedBigInteger('production_order_id')->nullable();
            $table->string('production_order_code', 100);
            $table->string('finished_item_code', 200)->nullable();
            $table->decimal('allocated_quantity', 18, 6);
            $table->decimal('returned_quantity', 18, 6)->default(0);
            $table->decimal('scrap_quantity', 18, 6)->default(0);
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->foreign('issue_line_id')->references('id')->on('internal_material_issue_lines')->onDelete('cascade');
            $table->index(['production_order_code', 'issue_line_id'], 'material_order_alloc_order_line_idx');
            $table->index('production_order_id', 'material_order_alloc_order_id_idx');
        });

        Schema::connection($this->connection)->create('internal_material_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_code', 50)->unique();
            $table->date('return_date');
            $table->unsignedBigInteger('issue_id')->nullable();
            $table->string('production_order_code', 100)->nullable();
            $table->string('returned_by', 150)->nullable();
            $table->string('received_by', 150)->nullable();
            $table->string('status', 30)->default('posted');
            $table->string('note', 1000)->nullable();
            $table->timestamps();

            $table->index(['production_order_code', 'return_date'], 'material_returns_order_date_idx');
        });

        Schema::connection($this->connection)->create('internal_material_return_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('return_id');
            $table->unsignedBigInteger('order_allocation_id');
            $table->unsignedBigInteger('issue_line_id');
            $table->string('material_item_code', 100);
            $table->string('unit', 50)->nullable();
            $table->decimal('reusable_quantity', 18, 6)->default(0);
            $table->decimal('scrap_quantity', 18, 6)->default(0);
            $table->string('location_code', 100)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->foreign('return_id')->references('id')->on('internal_material_returns')->onDelete('cascade');
            $table->foreign('order_allocation_id')->references('id')->on('internal_material_order_allocations');
            $table->foreign('issue_line_id')->references('id')->on('internal_material_issue_lines');
            $table->index(['issue_line_id', 'return_id'], 'material_return_line_issue_idx');
        });

        Schema::connection($this->connection)->create('internal_production_bom_drafts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('production_order_id')->nullable();
            $table->string('production_order_code', 100);
            $table->string('finished_item_code', 200)->nullable();
            $table->string('material_item_code', 100);
            $table->string('component_role', 100)->default('CHUNG');
            $table->string('unit', 50)->nullable();
            $table->decimal('issued_quantity', 18, 6)->default(0);
            $table->decimal('returned_quantity', 18, 6)->default(0);
            $table->decimal('scrap_quantity', 18, 6)->default(0);
            $table->decimal('actual_consumed_quantity', 18, 6)->default(0);
            $table->decimal('good_output_quantity', 18, 6)->default(0);
            $table->decimal('consumption_per_unit', 18, 9)->default(0);
            $table->string('status', 30)->default('draft');
            $table->json('source_issue_ids')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('note', 1000)->nullable();
            $table->timestamps();

            $table->unique(
                ['production_order_code', 'material_item_code', 'component_role'],
                'production_bom_draft_order_material_role_unique'
            );
            $table->index(['finished_item_code', 'status'], 'production_bom_draft_item_status_idx');
        });

        DB::connection($this->connection)->statement(
            "INSERT INTO internal_material_order_allocations
                (issue_line_id, production_order_id, production_order_code, finished_item_code, allocated_quantity, returned_quantity, scrap_quantity, note, created_at, updated_at)
             SELECT line.id, line.production_order_id, line.production_order,
                    COALESCE(NULLIF(prod.standard_item_code, ''), prod.item_code),
                    line.quantity, 0, 0, 'Tu dong chuyen du lieu cu', NOW(), NOW()
             FROM internal_material_issue_lines line
             LEFT JOIN internal_production_orders prod ON prod.id = line.production_order_id
             WHERE COALESCE(line.production_order, '') <> ''"
        );
    }

    public function down()
    {
        Schema::connection($this->connection)->dropIfExists('internal_production_bom_drafts');
        Schema::connection($this->connection)->dropIfExists('internal_material_return_lines');
        Schema::connection($this->connection)->dropIfExists('internal_material_returns');
        Schema::connection($this->connection)->dropIfExists('internal_material_order_allocations');

        Schema::connection($this->connection)->table('internal_material_issue_lines', function (Blueprint $table) {
            if (Schema::connection($this->connection)->hasColumn('internal_material_issue_lines', 'component_role')) {
                $table->dropColumn('component_role');
            }
        });
    }
}
