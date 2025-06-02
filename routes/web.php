<?php

use App\Http\Controllers\DanhMucController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PhieuXuatKhoController;
use App\Http\Controllers\SanXuatController;
use App\Http\Controllers\Unipax;
use App\Models\PhieuXuatKho;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

/*Route::get('/', function () {
    return view('welcome');
});*/

Route::get('/', [DashboardController::class, 'index']);
Route::get('/lenh/{so_ct}', [DashboardController::class, 'showDetail']);
Route::get('/pxkunipax', [PhieuXuatKhoController::class, 'index']);
Route::get('/phieuxuat/export/{so_ct}', [PhieuXuatKhoController::class, 'export'])->name('phieuxuat.export');
Route::get('/pxkunipax/export/{so_ct}', [PhieuXuatKhoController::class, 'unipaxexport'])->name('pxkunipax.export');
// Route for nhập liệu công nhân
route::get('/nhaplieu', [SanXuatController::class, 'index'])->name('nhaplieu');
// Route for DanhMuc
Route::get('/danhmuc', [DanhMucController::class, 'index'])->name('danhmuc');
