<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSideToInternalMaterialIssueLines extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->table('internal_material_issue_lines', function (Blueprint $table) {
            if (!Schema::connection($this->connection)->hasColumn('internal_material_issue_lines', 'side')) {
                $table->string('side', 100)->nullable()->after('color');
            }
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->table('internal_material_issue_lines', function (Blueprint $table) {
            $table->dropColumn('side');
        });
    }
}
