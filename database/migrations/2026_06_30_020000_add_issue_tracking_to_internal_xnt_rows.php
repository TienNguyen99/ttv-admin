<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIssueTrackingToInternalXntRows extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->table('internal_xnt_rows', function (Blueprint $table) {
            if (!Schema::connection($this->connection)->hasColumn('internal_xnt_rows', 'issue_id')) {
                $table->unsignedBigInteger('issue_id')->nullable()->after('is_active');
                $table->timestamp('issued_at')->nullable()->after('issue_id');
                $table->index(['issue_id'], 'xnt_issue_id_idx');
                $table->index(['production_order', 'issue_id'], 'xnt_order_issue_idx');
            }
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->table('internal_xnt_rows', function (Blueprint $table) {
            if (Schema::connection($this->connection)->hasColumn('internal_xnt_rows', 'issue_id')) {
                $table->dropIndex('xnt_issue_id_idx');
                $table->dropIndex('xnt_order_issue_idx');
                $table->dropColumn(['issue_id', 'issued_at']);
            }
        });
    }
}
