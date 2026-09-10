<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalPanelReceiptLine extends Model
{
    protected $connection = 'internal';

    protected $table = 'internal_panel_receipt_lines';

    protected $guarded = [];

    protected $casts = [
        'received_quantity' => 'float',
        'source_row' => 'integer',
    ];

    public function receipt()
    {
        return $this->belongsTo(InternalPanelReceipt::class, 'receipt_id');
    }

    public function orderRow()
    {
        return $this->belongsTo(InternalOrderTrackingRow::class, 'order_tracking_row_id');
    }
}
