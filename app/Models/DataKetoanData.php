<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataKetoanData extends Model
{
    protected $table = 'DataKetoanData';
    public $timestamps = false;
    use HasFactory;
    function dataother()
    {
        return $this->belongsTo(DataKetoanOder::class, 'So_hd', 'So_ct');
    }
}
