<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class NormalizeMaterialOrderAllocationUnits extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        DB::connection($this->connection)->statement(
            "UPDATE internal_material_order_allocations allocation
             INNER JOIN internal_material_issue_lines line ON line.id = allocation.issue_line_id
             SET allocation.allocated_quantity = CASE
                    WHEN COALESCE(line.base_quantity, 0) > 0 THEN line.base_quantity
                    ELSE line.quantity
                 END,
                 allocation.updated_at = NOW()
             WHERE allocation.note = 'Tu dong chuyen du lieu cu'"
        );
    }

    public function down()
    {
        DB::connection($this->connection)->statement(
            "UPDATE internal_material_order_allocations allocation
             INNER JOIN internal_material_issue_lines line ON line.id = allocation.issue_line_id
             SET allocation.allocated_quantity = line.quantity,
                 allocation.updated_at = NOW()
             WHERE allocation.note = 'Tu dong chuyen du lieu cu'"
        );
    }
}
