<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCompletionLinksToInternalMaterialIssueLines extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->table('internal_material_issue_lines', function (Blueprint $table) {
            if (!Schema::connection($this->connection)->hasColumn('internal_material_issue_lines', 'completion_receipt_id')) {
                $table->unsignedBigInteger('completion_receipt_id')->nullable()->after('issue_id');
                $table->index('completion_receipt_id', 'issue_lines_completion_receipt_idx');
            }
            if (!Schema::connection($this->connection)->hasColumn('internal_material_issue_lines', 'customer_issue_id')) {
                $table->unsignedBigInteger('customer_issue_id')->nullable()->after('completion_receipt_id');
                $table->index('customer_issue_id', 'issue_lines_customer_issue_idx');
            }
            if (!Schema::connection($this->connection)->hasColumn('internal_material_issue_lines', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('customer_issue_id');
                $table->index('completed_at', 'issue_lines_completed_at_idx');
            }
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->table('internal_material_issue_lines', function (Blueprint $table) {
            foreach ([
                'issue_lines_completion_receipt_idx',
                'issue_lines_customer_issue_idx',
                'issue_lines_completed_at_idx',
            ] as $index) {
                try {
                    $table->dropIndex($index);
                } catch (\Throwable $error) {
                    // Support databases where one of the optional indexes is already absent.
                }
            }
            foreach (['completion_receipt_id', 'customer_issue_id', 'completed_at'] as $column) {
                if (Schema::connection($this->connection)->hasColumn('internal_material_issue_lines', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
