<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AllowCatalogRowsWithoutItemCode extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        DB::connection($this->connection)->statement(
            'ALTER TABLE internal_item_catalogs DROP INDEX internal_item_catalogs_item_code_unique'
        );
        DB::connection($this->connection)->statement(
            'ALTER TABLE internal_item_catalogs MODIFY item_code VARCHAR(200) NULL'
        );
        DB::connection($this->connection)->statement(
            'ALTER TABLE internal_item_catalogs ADD UNIQUE INDEX internal_item_catalogs_source_row_unique (source_row)'
        );
        DB::connection($this->connection)->statement(
            'ALTER TABLE internal_item_catalogs ADD INDEX internal_item_catalogs_item_code_index (item_code)'
        );
    }

    public function down()
    {
        DB::connection($this->connection)->statement(
            'DELETE FROM internal_item_catalogs WHERE item_code IS NULL OR item_code = ""'
        );
        DB::connection($this->connection)->statement(
            'ALTER TABLE internal_item_catalogs DROP INDEX internal_item_catalogs_source_row_unique'
        );
        DB::connection($this->connection)->statement(
            'ALTER TABLE internal_item_catalogs DROP INDEX internal_item_catalogs_item_code_index'
        );
        DB::connection($this->connection)->statement(
            'ALTER TABLE internal_item_catalogs MODIFY item_code VARCHAR(200) NOT NULL'
        );
        DB::connection($this->connection)->statement(
            'ALTER TABLE internal_item_catalogs ADD UNIQUE INDEX internal_item_catalogs_item_code_unique (item_code)'
        );
    }
}
