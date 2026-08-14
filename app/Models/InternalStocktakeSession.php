<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalStocktakeSession extends Model
{
    protected $connection = 'internal';
    protected $table = 'internal_stocktake_sessions';
    protected $guarded = [];

    protected $casts = [
        'count_date' => 'date:Y-m-d',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'posted_at' => 'datetime',
    ];

    public function locations()
    {
        return $this->hasMany(InternalStocktakeLocation::class, 'session_id');
    }

    public function lines()
    {
        return $this->hasMany(InternalStocktakeLine::class, 'session_id');
    }
}
