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
        'ordered_quantity',
        'quantity',
        'base_quantity',
        'base_dvt',
        'unit_factor',
        'location_code',
        'internal_item_code',
        'size',
        'color',
        'logo_color',
        'side',
        'note',
    ];

    protected $casts = [
        'ordered_quantity' => 'float',
        'quantity' => 'float',
        'base_quantity' => 'float',
        'unit_factor' => 'float',
    ];

    public function receipt()
    {
        return $this->belongsTo(InternalMaterialReceipt::class, 'receipt_id');
    }
}
