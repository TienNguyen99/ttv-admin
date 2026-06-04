<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalMaterialIssueLine extends Model
{
    protected $connection = 'internal';

    protected $table = 'internal_material_issue_lines';

    protected $fillable = [
        'issue_id',
        'ma_hh',
        'ten_hh',
        'dvt',
        'quantity',
        'location_code',
        'internal_item_code',
        'size',
        'color',
        'note',
    ];

    protected $casts = [
        'quantity' => 'float',
    ];
}
