<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalOpeningStock extends Model
{
    protected $connection = 'internal';

    protected $table = 'internal_opening_stocks';

    protected $fillable = [
        'inventory_package_id',
        'period_month',
        'warehouse_code',
        'location_code',
        'ma_hh',
        'internal_item_code',
        'size',
        'color',
        'side',
        'quantity',
        'note',
    ];

    protected $casts = [
        'period_month' => 'date:Y-m-d',
        'quantity' => 'float',
    ];
}
