<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExpandInternalIssueProductionOrder extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        if (!Schema::connection($this->connection)->hasColumn('internal_material_issues', 'production_order')) {
            return;
        }

        $connection = DB::connection($this->connection);
        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            $connection->statement('ALTER TABLE internal_material_issues MODIFY production_order TEXT NULL');
        } elseif ($driver === 'sqlsrv') {
            $connection->statement('ALTER TABLE internal_material_issues ALTER COLUMN production_order NVARCHAR(MAX) NULL');
        } elseif ($driver === 'pgsql') {
            $connection->statement('ALTER TABLE internal_material_issues ALTER COLUMN production_order TYPE TEXT');
        }
    }

    public function down()
    {
        // Do not narrow this column because existing multi-order documents may exceed 100 characters.
    }
}
