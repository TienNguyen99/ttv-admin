<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalMaterialIssue extends Model
{
    protected $connection = 'internal';

    protected $table = 'internal_material_issues';

    protected $fillable = [
        'source_receipt_id',
        'issue_code',
        'issue_type',
        'issue_date',
        'warehouse_code',
        'receiver_name',
        'department',
        'production_order',
        'purpose',
        'status',
        'note',
    ];

    protected $casts = [
        'issue_date' => 'date:Y-m-d',
    ];

    public function lines()
    {
        return $this->hasMany(InternalMaterialIssueLine::class, 'issue_id');
    }
}
