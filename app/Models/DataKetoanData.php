<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataKetoanData extends Model
{
    protected $table = 'DataKetoanData';
    
    public $timestamps = false;
        protected $primaryKey = null;
    public $incrementing = false;
    use HasFactory;
    function dataother()
    {
        return $this->belongsTo(DataKetoanOder::class, 'So_hd', 'So_ct');
    }
    public function khachHang()
    {
        return $this->belongsTo(CodeKhachHang::class, 'Ma_kh', 'Ma_kh');
    }


    public function hangHoa()
    {
        return $this->belongsTo(CodeHangHoa::class, 'Ma_hh', 'Ma_hh');
    }
    public function nhanVien()
    {
        return $this->belongsTo(CodeNhanVien::class, 'Ma_nv', 'Ma_nv');
    }

}
