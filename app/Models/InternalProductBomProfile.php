<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalProductBomProfile extends Model
{
    protected $connection = 'internal';
    protected $table = 'internal_product_bom_profiles';
    protected $guarded = [];

    protected $casts = ['revision' => 'integer'];

    public function lines()
    {
        return $this->hasMany(InternalProductBomLine::class, 'profile_id')->orderBy('sequence');
    }

    public function routings()
    {
        return $this->hasMany(InternalProductRouting::class, 'profile_id')->orderBy('sequence');
    }
}
