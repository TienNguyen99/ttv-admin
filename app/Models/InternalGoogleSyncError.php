<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalGoogleSyncError extends Model
{
    protected $connection = 'internal';

    protected $table = 'internal_google_sync_errors';

    protected $guarded = [];

    protected $casts = [
        'raw_data' => 'array',
    ];
}
