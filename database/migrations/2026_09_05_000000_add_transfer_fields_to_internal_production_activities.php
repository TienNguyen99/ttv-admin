<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTransferFieldsToInternalProductionActivities extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->table('internal_production_activities', function (Blueprint $table) {
            $table->string('transfer_code', 50)->nullable()->unique()->after('status');
            $table->string('next_operation_code', 50)->nullable()->after('transfer_code');
            $table->string('next_operation_name', 200)->nullable()->after('next_operation_code');
            $table->string('transfer_status', 20)->nullable()->after('next_operation_name');
            $table->index(['production_order_code', 'next_operation_code', 'transfer_status'], 'production_activity_transfer_idx');
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->table('internal_production_activities', function (Blueprint $table) {
            $table->dropIndex('production_activity_transfer_idx');
            $table->dropUnique(['transfer_code']);
            $table->dropColumn(['transfer_code', 'next_operation_code', 'next_operation_name', 'transfer_status']);
        });
    }
}
