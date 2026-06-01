<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInternalItemCodeAndSideToInventoryCountsTable extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->table('inventory_counts', function (Blueprint $table) {
            $table->string('internal_item_code', 100)->default('')->after('ma_ko');
            $table->string('side', 100)->default('')->after('color');
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->table('inventory_counts', function (Blueprint $table) {
            $table->dropColumn(['internal_item_code', 'side']);
        });
    }
}
