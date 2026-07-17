<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AllowDuplicateMaterialsInWeavingBoms extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->table('internal_weaving_boms', function (Blueprint $table) {
            if (!Schema::connection($this->connection)->hasColumn('internal_weaving_boms', 'line_role')) {
                $table->string('line_role', 120)->default('')->after('material_code');
            }
        });

        $this->tryStatement('CREATE INDEX weaving_boms_item_fk_idx ON internal_weaving_boms (weaving_item_id)');
        $this->tryStatement('ALTER TABLE internal_weaving_boms DROP INDEX weaving_boms_item_material_unique');
        $this->tryStatement('CREATE UNIQUE INDEX weaving_boms_item_material_role_unique ON internal_weaving_boms (weaving_item_id, material_code, line_role)');
    }

    public function down()
    {
        $this->tryStatement('ALTER TABLE internal_weaving_boms DROP INDEX weaving_boms_item_material_role_unique');
        $this->tryStatement('CREATE UNIQUE INDEX weaving_boms_item_material_unique ON internal_weaving_boms (weaving_item_id, material_code)');

        Schema::connection($this->connection)->table('internal_weaving_boms', function (Blueprint $table) {
            if (Schema::connection($this->connection)->hasColumn('internal_weaving_boms', 'line_role')) {
                $table->dropColumn('line_role');
            }
        });
    }

    private function tryStatement(string $sql): void
    {
        try {
            DB::connection($this->connection)->statement($sql);
        } catch (\Throwable $e) {
            // Index migrations are made idempotent because the first run may fail midway on MySQL.
        }
    }
}
