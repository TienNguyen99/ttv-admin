<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalProductBomLine extends Model
{
    protected $connection = 'internal';
    protected $table = 'internal_product_bom_lines';
    protected $guarded = [];

    protected $casts = [
        'sequence' => 'integer',
        'consumption_per_unit' => 'float',
        'formula_output_per_unit' => 'float',
        'formula_part' => 'float',
        'yield_quantity' => 'float',
        'waste_percent' => 'float',
        'round_to_whole' => 'boolean',
    ];
}
