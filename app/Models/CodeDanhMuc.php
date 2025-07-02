<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CodeDanhMuc extends Model
{
    use HasFactory;
    protected $table = 'CodeDmChung';
    public $timestamps = false;
}
