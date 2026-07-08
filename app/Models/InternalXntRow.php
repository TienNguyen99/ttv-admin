<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalXntRow extends Model
{
    protected $connection = 'internal';

    protected $table = 'internal_xnt_rows';

    protected $guarded = [];

    protected $casts = [
        'issue_date' => 'date:Y-m-d',
        'quantity' => 'float',
        'raw_data' => 'array',
        'is_active' => 'boolean',
        'issued_at' => 'datetime',
    ];

    public function issue()
    {
        return $this->belongsTo(InternalMaterialIssue::class, 'issue_id');
    }
}
