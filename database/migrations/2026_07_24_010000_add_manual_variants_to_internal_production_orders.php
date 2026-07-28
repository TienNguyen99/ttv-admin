<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'internal';

    public function up(): void
    {
        Schema::connection($this->connection)->table('internal_production_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('variant_parent_id')->nullable()->after('standard_catalog_id');
            $table->boolean('is_variant_parent')->default(false)->after('variant_parent_id');
            $table->boolean('is_manual_variant')->default(false)->after('is_variant_parent');
            $table->index(['variant_parent_id', 'is_active'], 'prod_orders_variant_parent_active_idx');
            $table->index(['is_manual_variant', 'is_active'], 'prod_orders_manual_variant_active_idx');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('internal_production_orders', function (Blueprint $table) {
            $table->dropIndex('prod_orders_variant_parent_active_idx');
            $table->dropIndex('prod_orders_manual_variant_active_idx');
            $table->dropColumn(['variant_parent_id', 'is_variant_parent', 'is_manual_variant']);
        });
    }
};
