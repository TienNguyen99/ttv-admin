<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalProductionBomDraft extends Model
{
    protected $connection = 'internal';
    protected $table = 'internal_production_bom_drafts';
    protected $guarded = [];

    protected $casts = [
        'issued_quantity' => 'float',
        'returned_quantity' => 'float',
        'scrap_quantity' => 'float',
        'actual_consumed_quantity' => 'float',
        'good_output_quantity' => 'float',
        'consumption_per_unit' => 'float',
        'source_issue_ids' => 'array',
        'generated_at' => 'datetime:Y-m-d H:i:s',
        'approved_at' => 'datetime:Y-m-d H:i:s',
    ];
}
