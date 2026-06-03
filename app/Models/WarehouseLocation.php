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
        'shelf_code',
        'tier',
        'bay_code',
        'grid_x',
        'grid_y',
        'grid_w',
        'grid_h',
        'location_name',
        'status',
        'note',
    ];

    protected $casts = [
        'tier' => 'integer',
        'grid_x' => 'integer',
        'grid_y' => 'integer',
        'grid_w' => 'integer',
        'grid_h' => 'integer',
    ];
}
