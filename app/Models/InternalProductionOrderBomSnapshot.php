<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalProductionOrderBomSnapshot extends Model
{
    protected $connection = 'internal';
    protected $table = 'internal_production_order_bom_snapshots';
    protected $guarded = [];

    protected $casts = [
        'bom_revision' => 'integer',
        'sequence' => 'integer',
        'consumption_per_unit' => 'float',
        'formula_output_per_unit' => 'float',
        'formula_part' => 'float',
        'yield_quantity' => 'float',
        'waste_percent' => 'float',
        'round_to_whole' => 'boolean',
        'order_quantity' => 'float',
        'required_quantity' => 'float',
        'exact_required_quantity' => 'float',
        'snapshotted_at' => 'datetime:Y-m-d H:i:s',
    ];
}
