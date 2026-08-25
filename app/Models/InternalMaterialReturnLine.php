<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalMaterialReturnLine extends Model
{
    protected $connection = 'internal';
    protected $table = 'internal_material_return_lines';
    protected $guarded = [];

    protected $casts = [
        'reusable_quantity' => 'float',
        'scrap_quantity' => 'float',
    ];
}
