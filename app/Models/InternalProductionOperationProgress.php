<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalProductionOperationProgress extends Model
{
    protected $connection = 'internal';

    protected $table = 'internal_production_operation_progress';

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime:Y-m-d H:i:s',
        'completed_at' => 'datetime:Y-m-d H:i:s',
    ];
}
