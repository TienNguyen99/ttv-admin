<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalWeavingOrder extends Model
{
    protected $connection = 'internal';

    protected $table = 'internal_weaving_orders';

    protected $fillable = [
        'order_code',
        'weaving_item_id',
        'item_code',
        'customer',
        'order_quantity',
        'unit',
        'order_date',
        'due_date',
        'status',
        'note',
    ];

    protected $casts = [
        'order_quantity' => 'float',
        'order_date' => 'date:Y-m-d',
        'due_date' => 'date:Y-m-d',
    ];

    public function item()
    {
        return $this->belongsTo(InternalWeavingItem::class, 'weaving_item_id');
    }
}
