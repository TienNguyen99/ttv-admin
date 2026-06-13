<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalProductionOrder extends Model
{
    protected $connection = 'internal';

    protected $table = 'internal_production_orders';

    protected $guarded = [];

    protected $casts = [
        'received_date' => 'date:Y-m-d',
        'promised_date' => 'date:Y-m-d',
        'customer_requested_date' => 'date:Y-m-d',
        'order_quantity' => 'float',
        'raw_data' => 'array',
        'is_active' => 'boolean',
    ];
}
