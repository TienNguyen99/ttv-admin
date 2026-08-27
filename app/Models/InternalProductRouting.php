<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalProductRouting extends Model
{
    protected $connection = 'internal';
    protected $table = 'internal_product_routings';
    protected $guarded = [];

    protected $casts = [
        'sequence' => 'integer',
        'is_outsourced' => 'boolean',
    ];
}
