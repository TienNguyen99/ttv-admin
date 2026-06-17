<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVariantFieldsToInternalItemCatalogs extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->table('internal_item_catalogs', function (Blueprint $table) {
            if (!Schema::connection($this->connection)->hasColumn('internal_item_catalogs', 'size')) {
                $table->string('size', 100)->nullable()->after('unit');
            }
            if (!Schema::connection($this->connection)->hasColumn('internal_item_catalogs', 'color')) {
                $table->string('color', 200)->nullable()->after('size');
            }
            if (!Schema::connection($this->connection)->hasColumn('internal_item_catalogs', 'logo_color')) {
                $table->string('logo_color', 200)->nullable()->after('color');
            }
            if (!Schema::connection($this->connection)->hasColumn('internal_item_catalogs', 'side')) {
                $table->string('side', 100)->nullable()->after('logo_color');
            }
        });

        Schema::connection($this->connection)->table('internal_item_catalogs', function (Blueprint $table) {
            $table->index(['item_code', 'size', 'color', 'side'], 'item_catalog_variant_idx');
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->table('internal_item_catalogs', function (Blueprint $table) {
            $table->dropIndex('item_catalog_variant_idx');
            $table->dropColumn(['size', 'color', 'logo_color', 'side']);
        });
    }
}
