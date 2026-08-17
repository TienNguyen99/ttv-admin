<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalStocktakeLine extends Model
{
    protected $connection = 'internal';
    protected $table = 'internal_stocktake_lines';
    protected $guarded = [];

    protected $casts = [
        'expected_quantity' => 'float',
        'counted_quantity' => 'float',
        'counted_weight_kg' => 'float',
        'weight_per_unit_grams' => 'float',
        'counted_at' => 'datetime',
    ];

    public function sessionLocation()
    {
        return $this->belongsTo(InternalStocktakeLocation::class, 'session_location_id');
    }
}
