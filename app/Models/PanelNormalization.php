<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PanelNormalization extends Model
{
    protected $connection = 'internal';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function aliases()
    {
        return $this->hasMany(PanelNormalizationAlias::class)->orderBy('alias');
    }
}
