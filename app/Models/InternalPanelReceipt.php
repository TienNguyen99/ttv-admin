<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalPanelReceipt extends Model
{
    protected $connection = 'internal';

    protected $table = 'internal_panel_receipts';

    protected $guarded = [];

    protected $casts = [
        'received_date' => 'date:Y-m-d',
        'total_quantity' => 'float',
        'line_count' => 'integer',
    ];

    public function lines()
    {
        return $this->hasMany(InternalPanelReceiptLine::class, 'receipt_id');
    }
}
