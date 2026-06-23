<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalBtpProductionOrder extends Model
{
    protected $connection = 'internal';

    protected $table = 'internal_btp_production_orders';

    protected $guarded = [];

    protected $casts = [
        'order_date' => 'date:Y-m-d',
        'issued_at' => 'datetime:Y-m-d H:i:s',
        'completed_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function lines()
    {
        return $this->hasMany(InternalBtpProductionOrderLine::class, 'btp_order_id');
    }
}
