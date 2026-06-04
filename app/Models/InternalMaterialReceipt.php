<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalMaterialReceipt extends Model
{
    protected $connection = 'internal';

    protected $table = 'internal_material_receipts';

    protected $fillable = [
        'receipt_code',
        'receipt_date',
        'warehouse_code',
        'location_code',
        'receiver_name',
        'source',
        'status',
        'note',
    ];

    protected $casts = [
        'receipt_date' => 'date:Y-m-d',
    ];

    public function lines()
    {
        return $this->hasMany(InternalMaterialReceiptLine::class, 'receipt_id');
    }
}
