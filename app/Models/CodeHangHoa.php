<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CodeHangHoa extends Model
{

    protected $table = 'codehanghoa';
    public $timestamps = false;

    public function ketoanoder()
    {
        return $this->hasMany(DataKetoanOder::class, 'Ma_hh', 'Ma_hh');
    }
}
