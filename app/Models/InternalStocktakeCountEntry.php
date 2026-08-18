<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalStocktakeCountEntry extends Model
{
    protected $connection = 'internal';
    protected $table = 'internal_stocktake_count_entries';
    protected $guarded = [];

    protected $casts = [
        'input_quantity' => 'float',
        'converted_quantity' => 'float',
        'weight_kg' => 'float',
    ];
}
