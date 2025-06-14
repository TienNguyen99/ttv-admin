<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class DataKetoanOder extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $table = 'DataKetoanOder'; // Nếu tên bảng không theo chuẩn Laravel

    public function khachHang()
    {
        return $this->belongsTo(CodeKhachHang::class, 'Ma_kh', 'Ma_kh');
    }


    public function hangHoa()
    {
        return $this->belongsTo(CodeHangHoa::class, 'Ma_hh', 'Ma_hh');
    }
    public function lenhSanxuat()
    {
        return $this->belongsTo(DataKetoanData::class, 'So_ct', 'So_hd');
    }
}
