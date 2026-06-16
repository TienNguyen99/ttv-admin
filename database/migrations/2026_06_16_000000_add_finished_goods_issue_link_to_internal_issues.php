<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddFinishedGoodsIssueLinkToInternalIssues extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->table('internal_material_issues', function (Blueprint $table) {
            if (!Schema::connection($this->connection)->hasColumn('internal_material_issues', 'source_receipt_id')) {
                $table->unsignedBigInteger('source_receipt_id')->nullable()->after('id');
            }
            if (!Schema::connection($this->connection)->hasColumn('internal_material_issues', 'issue_type')) {
                $table->string('issue_type', 30)->default('material')->after('issue_code');
            }
        });

        DB::connection($this->connection)->statement(
            "UPDATE internal_material_issues
             SET issue_type = CASE
                WHEN issue_code LIKE 'PXBTP-%' THEN 'production'
                WHEN issue_code LIKE 'PXTP-%' THEN 'customer'
                ELSE 'material'
             END"
        );

        Schema::connection($this->connection)->table('internal_material_issues', function (Blueprint $table) {
            $table->index('source_receipt_id', 'issues_source_receipt_idx');
            $table->index('issue_type', 'issues_type_idx');
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->table('internal_material_issues', function (Blueprint $table) {
            $table->dropIndex('issues_source_receipt_idx');
            $table->dropIndex('issues_type_idx');
            $table->dropColumn(['source_receipt_id', 'issue_type']);
        });
    }
}
