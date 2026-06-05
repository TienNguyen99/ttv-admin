<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryPackage extends Model
{
    protected $connection = 'internal';

    protected $table = 'inventory_packages';

    protected $fillable = [
        'package_code',
        'warehouse_location_id',
        'inventory_count_id',
        'ma_sp',
        'ma_ko',
        'internal_item_code',
        'size',
        'color',
        'side',
        'quantity',
        'checked_at',
        'note',
    ];

    protected $casts = [
        'quantity' => 'float',
        'checked_at' => 'date:Y-m-d',
    ];

    public function location()
    {
        return $this->belongsTo(WarehouseLocation::class, 'warehouse_location_id');
    }

    public function receiptLines()
    {
        return $this->hasMany(InternalMaterialReceiptLine::class, 'inventory_package_id');
    }
}
