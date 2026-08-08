<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalCustomer extends Model
{
    protected $connection = 'internal';

    protected $table = 'internal_customers';

    protected $guarded = [];

    protected $casts = [
        'last_order_date' => 'date:Y-m-d',
        'order_count' => 'integer',
        'is_active' => 'boolean',
    ];
}
