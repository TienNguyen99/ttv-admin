<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalInventoryCount extends Model
{
    protected $connection = 'internal';

    protected $table = 'inventory_counts';

    protected $fillable = [
        'ma_sp',
        'ma_ko',
        'internal_item_code',
        'size',
        'color',
        'side',
        'counted_quantity',
        'checked_at',
        'note',
    ];

    protected $casts = [
        'counted_quantity' => 'float',
        'checked_at' => 'date:Y-m-d',
    ];
}
