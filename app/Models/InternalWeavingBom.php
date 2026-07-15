<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalWeavingBom extends Model
{
    protected $connection = 'internal';

    protected $table = 'internal_weaving_boms';

    protected $fillable = [
        'weaving_item_id',
        'material_code',
        'material_name',
        'unit',
        'consumption_per_unit',
        'waste_percent',
        'note',
    ];

    protected $casts = [
        'consumption_per_unit' => 'float',
        'waste_percent' => 'float',
    ];

    public function item()
    {
        return $this->belongsTo(InternalWeavingItem::class, 'weaving_item_id');
    }
}
