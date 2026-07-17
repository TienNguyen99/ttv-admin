<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalWeavingItem extends Model
{
    protected $connection = 'internal';

    protected $table = 'internal_weaving_items';

    protected $fillable = [
        'item_code',
        'item_name',
        'design_code',
        'customer',
        'unit',
        'note',
        'metadata_json',
    ];

    public function boms()
    {
        return $this->hasMany(InternalWeavingBom::class, 'weaving_item_id');
    }

    public function orders()
    {
        return $this->hasMany(InternalWeavingOrder::class, 'weaving_item_id');
    }
}
