<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PanelNormalizationAlias extends Model
{
    protected $connection = 'internal';

    protected $guarded = [];

    public function normalization()
    {
        return $this->belongsTo(PanelNormalization::class, 'panel_normalization_id');
    }
}
