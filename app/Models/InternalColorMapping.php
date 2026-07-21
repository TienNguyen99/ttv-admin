<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalColorMapping extends Model
{
    protected $connection = 'internal';

    protected $table = 'internal_color_mappings';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
