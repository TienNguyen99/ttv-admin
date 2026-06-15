<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalMaterialReceiptLine extends Model
{
    protected $connection = 'internal';

    protected $table = 'internal_material_receipt_lines';

    protected $fillable = [
        'receipt_id',
        'inventory_package_id',
        'production_order_id',
        'production_order',
        'purchase_order',
        'customer',
        'ma_hh',
        'ten_hh',
        'dvt',
        'quantity',
        'location_code',
        'internal_item_code',
        'size',
        'color',
        'side',
        'note',
    ];

    protected $casts = [
        'quantity' => 'float',
    ];

    public function receipt()
    {
        return $this->belongsTo(InternalMaterialReceipt::class, 'receipt_id');
    }
}
