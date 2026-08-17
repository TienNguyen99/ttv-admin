<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalItemCatalog extends Model
{
    protected $connection = 'internal';

    protected $table = 'internal_item_catalogs';

    protected $guarded = [];

    protected $casts = [
        'opening_quantity' => 'float',
        'weight_per_unit_grams' => 'float',
        'raw_data' => 'array',
        'is_active' => 'boolean',
    ];
}
