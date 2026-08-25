<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMatchModeToInternalMaterialIssueLines extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->table('internal_material_issue_lines', function (Blueprint $table) {
            if (!Schema::connection($this->connection)->hasColumn('internal_material_issue_lines', 'match_by_code_only')) {
                $table->boolean('match_by_code_only')->nullable()->after('location_code');
            }
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->table('internal_material_issue_lines', function (Blueprint $table) {
            if (Schema::connection($this->connection)->hasColumn('internal_material_issue_lines', 'match_by_code_only')) {
                $table->dropColumn('match_by_code_only');
            }
        });
    }
}
