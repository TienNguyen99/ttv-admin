<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCustomerToInternalBtpProductionOrdersTable extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        $schema = Schema::connection($this->connection);

        if (!$schema->hasColumn('internal_btp_production_orders', 'customer')) {
            $schema->table('internal_btp_production_orders', function (Blueprint $table) {
                $table->string('customer', 200)->nullable()->after('receiver_name')->index('btp_orders_customer_idx');
            });
        }
    }

    public function down()
    {
        $schema = Schema::connection($this->connection);

        if ($schema->hasColumn('internal_btp_production_orders', 'customer')) {
            $schema->table('internal_btp_production_orders', function (Blueprint $table) {
                $table->dropIndex('btp_orders_customer_idx');
                $table->dropColumn('customer');
            });
        }
    }
}
