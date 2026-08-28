<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalProductionActivity extends Model
{
    protected $connection = 'internal';
    protected $table = 'internal_production_activities';
    protected $guarded = [];
    protected $casts = ['activity_date' => 'date:Y-m-d'];

    public function lines()
    {
        return $this->hasMany(InternalProductionActivityLine::class, 'activity_id');
    }
}
