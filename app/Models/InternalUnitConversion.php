<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalUnitConversion extends Model
{
    protected $connection = 'internal';

    protected $table = 'internal_unit_conversions';

    protected $fillable = [
        'item_code',
        'from_unit',
        'to_unit',
        'factor',
        'note',
    ];

    protected $casts = [
        'factor' => 'float',
    ];
}
