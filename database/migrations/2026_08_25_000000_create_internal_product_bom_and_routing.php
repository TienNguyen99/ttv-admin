<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInternalProductBomAndRouting extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->create('internal_product_bom_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('item_code', 200)->unique();
            $table->string('item_name', 500)->nullable();
            $table->string('unit', 50)->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->string('status', 30)->default('active');
            $table->string('note', 1000)->nullable();
            $table->timestamps();

            $table->index(['status', 'updated_at'], 'product_bom_profile_status_updated_idx');
        });

        Schema::connection($this->connection)->create('internal_product_routings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profile_id');
            $table->unsignedInteger('sequence');
            $table->string('operation_code', 50);
            $table->string('operation_name', 200);
            $table->string('work_center', 150)->nullable();
            $table->boolean('is_outsourced')->default(false);
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->foreign('profile_id')->references('id')->on('internal_product_bom_profiles')->onDelete('cascade');
            $table->unique(['profile_id', 'sequence'], 'product_routing_profile_sequence_unique');
            $table->unique(['profile_id', 'operation_code'], 'product_routing_profile_code_unique');
        });

        Schema::connection($this->connection)->create('internal_product_bom_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profile_id');
            $table->unsignedInteger('sequence');
            $table->string('material_code', 120);
            $table->string('material_name', 500)->nullable();
            $table->string('component_role', 120)->default('CHUNG');
            $table->string('unit', 50)->nullable();
            $table->decimal('consumption_per_unit', 20, 9);
            $table->decimal('waste_percent', 8, 3)->default(0);
            $table->string('operation_code', 50)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->foreign('profile_id')->references('id')->on('internal_product_bom_profiles')->onDelete('cascade');
            $table->unique(['profile_id', 'sequence'], 'product_bom_line_profile_sequence_unique');
            $table->index(['material_code', 'profile_id'], 'product_bom_line_material_profile_idx');
            $table->index(['profile_id', 'operation_code'], 'product_bom_line_profile_operation_idx');
        });

        Schema::connection($this->connection)->create('internal_production_order_bom_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('production_order_id');
            $table->string('production_order_code', 100);
            $table->string('finished_item_code', 200);
            $table->unsignedInteger('bom_revision');
            $table->unsignedInteger('sequence');
            $table->string('material_code', 120);
            $table->string('material_name', 500)->nullable();
            $table->string('component_role', 120)->default('CHUNG');
            $table->string('unit', 50)->nullable();
            $table->decimal('consumption_per_unit', 20, 9);
            $table->decimal('waste_percent', 8, 3)->default(0);
            $table->decimal('order_quantity', 18, 3)->default(0);
            $table->decimal('required_quantity', 20, 6)->default(0);
            $table->string('operation_code', 50)->nullable();
            $table->timestamp('snapshotted_at')->nullable();
            $table->timestamps();

            $table->unique(['production_order_id', 'sequence'], 'production_bom_snapshot_order_sequence_unique');
            $table->index(['production_order_code', 'material_code'], 'production_bom_snapshot_order_material_idx');
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->dropIfExists('internal_production_order_bom_snapshots');
        Schema::connection($this->connection)->dropIfExists('internal_product_bom_lines');
        Schema::connection($this->connection)->dropIfExists('internal_product_routings');
        Schema::connection($this->connection)->dropIfExists('internal_product_bom_profiles');
    }
}
