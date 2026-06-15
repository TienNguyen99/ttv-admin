<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalMaterialIssueAllocation extends Model
{
    protected $connection = 'internal';

    protected $table = 'internal_material_issue_allocations';

    protected $fillable = [
        'issue_line_id',
        'inventory_package_id',
        'warehouse_location_id',
        'inventory_count_id',
        'source_package_code',
        'location_code',
        'ma_hh',
        'warehouse_code',
        'internal_item_code',
        'size',
        'color',
        'side',
        'checked_at',
        'quantity',
        'note',
    ];

    protected $casts = [
        'checked_at' => 'date:Y-m-d',
        'quantity' => 'float',
    ];
}
