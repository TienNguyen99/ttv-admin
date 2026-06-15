<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalAuditLog extends Model
{
    protected $connection = 'internal';

    protected $table = 'internal_audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'action',
        'entity_type',
        'entity_id',
        'entity_code',
        'payload',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];
}
