<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdempotencyToInternalMaterialIssues extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->table('internal_material_issues', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->after('source_receipt_id');
            $table->char('request_fingerprint', 64)->nullable()->after('idempotency_key');
            $table->unique('idempotency_key', 'internal_issues_idempotency_key_unique');
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->table('internal_material_issues', function (Blueprint $table) {
            $table->dropUnique('internal_issues_idempotency_key_unique');
            $table->dropColumn(['idempotency_key', 'request_fingerprint']);
        });
    }
}
