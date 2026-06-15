<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalMaterialIssueLine extends Model
{
    protected $connection = 'internal';

    protected $table = 'internal_material_issue_lines';

    protected $fillable = [
        'issue_id',
        'production_order_id',
        'production_order',
        'purchase_order',
        'customer',
        'ma_hh',
        'ten_hh',
        'dvt',
        'ordered_quantity',
        'quantity',
        'location_code',
        'internal_item_code',
        'size',
        'color',
        'note',
    ];

    protected $casts = [
        'ordered_quantity' => 'float',
        'quantity' => 'float',
    ];

    public function allocations()
    {
        return $this->hasMany(InternalMaterialIssueAllocation::class, 'issue_line_id');
    }
}
