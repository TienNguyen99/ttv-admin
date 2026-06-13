<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalOrderTrackingRow extends Model
{
    protected $connection = 'internal';

    protected $table = 'internal_order_tracking_rows';

    protected $guarded = [];

    protected $casts = [
        'export_date' => 'date:Y-m-d',
        'panel_out_date' => 'date:Y-m-d',
        'delivery_date' => 'date:Y-m-d',
        'order_quantity' => 'float',
        'quantity' => 'float',
        'quantity_front' => 'float',
        'quantity_back' => 'float',
        'received_quantity' => 'float',
        'front_pass' => 'float',
        'front_fail' => 'float',
        'back_pass' => 'float',
        'back_fail' => 'float',
        'remaining_quantity' => 'float',
        'extra_data' => 'array',
        'is_active' => 'boolean',
    ];
}
