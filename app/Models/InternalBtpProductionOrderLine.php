<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalBtpProductionOrderLine extends Model
{
    protected $connection = 'internal';

    protected $table = 'internal_btp_production_order_lines';

    protected $guarded = [];

    protected $casts = [
        'ordered_quantity' => 'float',
        'quantity' => 'float',
    ];

    public function order()
    {
        return $this->belongsTo(InternalBtpProductionOrder::class, 'btp_order_id');
    }
}
