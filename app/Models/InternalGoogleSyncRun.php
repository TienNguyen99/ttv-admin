<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalGoogleSyncRun extends Model
{
    protected $connection = 'internal';

    protected $table = 'internal_google_sync_runs';

    protected $guarded = [];

    protected $casts = [
        'details' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function errors()
    {
        return $this->hasMany(InternalGoogleSyncError::class, 'sync_run_id');
    }
}
