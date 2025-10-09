<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhieuNhap extends Model
{
    protected $connection = 'unipax';
    protected $table = 'phieu_nhap';
    protected $fillable = [
        'ps','row_kd','mahang','mau','size','logo','mat',
        'ngayxuat','soluongdonhang','sl_thuc',
        'dat','loi','ghichu','trangthai','user'
    ];
}

