<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseLocation extends Model
{
    protected $connection = 'internal';

    protected $table = 'warehouse_locations';

    protected $fillable = [
        'location_code',
        'warehouse_code',
        'location_name',
        'status',
        'note',
    ];
}
