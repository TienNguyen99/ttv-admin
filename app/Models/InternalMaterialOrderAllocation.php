<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalMaterialOrderAllocation extends Model
{
    protected $connection = 'internal';
    protected $table = 'internal_material_order_allocations';
    protected $guarded = [];

    protected $casts = [
        'allocated_quantity' => 'float',
        'returned_quantity' => 'float',
        'scrap_quantity' => 'float',
    ];

    public function issueLine()
    {
        return $this->belongsTo(InternalMaterialIssueLine::class, 'issue_line_id');
    }
}
