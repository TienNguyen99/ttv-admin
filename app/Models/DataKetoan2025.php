<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
  

class DataKetoan2025 extends Model
{
    protected $table = 'DataKetoan2025';
    public $timestamps = false;
    use HasFactory;
     public function hangHoa()
    {
        return $this->belongsTo(CodeHanghoa::class, 'Ma_hh', 'Ma_hh');
    }

    public function nhanVien()
    {
        return $this->belongsTo(CodeNhanVien::class, 'Ma_nv', 'Ma_nv');
    }

    public function khachHang()
    {
        return $this->belongsTo(CodeKhachHang::class, 'Ma_kh', 'Ma_kh');
    }
}
