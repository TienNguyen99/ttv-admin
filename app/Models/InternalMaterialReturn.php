<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalMaterialReturn extends Model
{
    protected $connection = 'internal';
    protected $table = 'internal_material_returns';
    protected $guarded = [];

    protected $casts = ['return_date' => 'date:Y-m-d'];

    public function lines()
    {
        return $this->hasMany(InternalMaterialReturnLine::class, 'return_id');
    }
}
