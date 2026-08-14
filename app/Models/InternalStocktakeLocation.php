<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalStocktakeLocation extends Model
{
    protected $connection = 'internal';
    protected $table = 'internal_stocktake_locations';
    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(InternalStocktakeSession::class, 'session_id');
    }

    public function lines()
    {
        return $this->hasMany(InternalStocktakeLine::class, 'session_location_id');
    }
}
