<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalMaterialIssueLine extends Model
{
    protected $connection = 'internal';

    protected $table = 'internal_material_issue_lines';

    protected $fillable = [
        'issue_id',
        'completion_receipt_id',
        'customer_issue_id',
        'completed_at',
        'production_order_id',
        'production_order',
        'purchase_order',
        'customer',
        'ma_hh',
        'ten_hh',
        'dvt',
        'ordered_quantity',
        'quantity',
        'base_quantity',
        'base_dvt',
        'unit_factor',
        'location_code',
        'internal_item_code',
        'size',
        'color',
        'side',
        'note',
    ];

    protected $casts = [
        'completed_at' => 'datetime:Y-m-d H:i:s',
        'ordered_quantity' => 'float',
        'quantity' => 'float',
        'base_quantity' => 'float',
        'unit_factor' => 'float',
    ];

    public function allocations()
    {
        return $this->hasMany(InternalMaterialIssueAllocation::class, 'issue_line_id');
    }
}
