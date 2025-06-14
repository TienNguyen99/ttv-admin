<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CodeKhachHang extends Model
{
    use HasFactory;
    protected $table = 'codekhachang';
    public $timestamps = false;
    public function ketoanoder()
    {
        return $this->hasMany(DataKetoanOder::class, 'Ma_kh', 'Ma_kh');
    }
}
