<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCloudinaryFieldsToInternalItemCatalogs extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->table('internal_item_catalogs', function (Blueprint $table) {
            if (!Schema::connection($this->connection)->hasColumn('internal_item_catalogs', 'image_public_id')) {
                $table->string('image_public_id', 500)->nullable()->after('image_url');
            }
            if (!Schema::connection($this->connection)->hasColumn('internal_item_catalogs', 'image_source')) {
                $table->string('image_source', 50)->nullable()->after('image_public_id');
            }
            if (!Schema::connection($this->connection)->hasColumn('internal_item_catalogs', 'image_uploaded_at')) {
                $table->timestamp('image_uploaded_at')->nullable()->after('image_source');
            }
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->table('internal_item_catalogs', function (Blueprint $table) {
            foreach (['image_uploaded_at', 'image_source', 'image_public_id'] as $column) {
                if (Schema::connection($this->connection)->hasColumn('internal_item_catalogs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
