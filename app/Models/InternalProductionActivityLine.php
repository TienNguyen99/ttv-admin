<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalProductionActivityLine extends Model
{
    protected $connection = 'internal';
    protected $table = 'internal_production_activity_lines';
    protected $guarded = [];
    protected $casts = [
        'planned_quantity' => 'float',
        'good_quantity' => 'float',
        'defect_quantity' => 'float',
    ];

    public function activity()
    {
        return $this->belongsTo(InternalProductionActivity::class, 'activity_id');
    }
}
