<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalFinishedGoodsReceipt extends Model
{
    protected $connection = 'internal';

    protected $table = 'internal_finished_goods_receipts';

    protected $fillable = [
        'receipt_code',
        'receipt_date',
        'ma_sp',
        'ma_ko',
        'ten_hh',
        'dvt',
        'quantity',
        'status',
        'note',
    ];

    protected $casts = [
        'receipt_date' => 'date:Y-m-d',
        'quantity' => 'float',
    ];
}
