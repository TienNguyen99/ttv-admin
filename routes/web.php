<?php

use App\Http\Controllers\ClientHomeController;
use App\Http\Controllers\DanhMucController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DonHangController;
use App\Http\Controllers\HangHoaController;
use App\Http\Controllers\PhieuNhapXuatKhoController;
use App\Http\Controllers\PhieuXuatKhoController;
use App\Http\Controllers\SanXuatController;
use App\Http\Controllers\UnipaxController;


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

// Route for Hang Hoa
//Route::get('/mahh/edit', [HangHoaController::class, 'editMaHH'])->name('mahh.edit');
//Route::post('/mahh/update', [HangHoaController::class, 'updateMaHH'])->name('mahh.update');
//Route::get('/suggest-mahh', [HangHoaController::class, 'suggestMaHH'])->name('mahh.suggest');
// Route for Unipax
Route::get('/unipax', [UnipaxController::class, 'index'])->name('unipax');
//Route for DonHang
Route::get('/ordertolsx', [DonHangController::class, 'ordertolsx'])->name('ordertolsx');
Route::get('/donhang', [DonHangController::class, 'index'])->name('donhang');
Route::post('/gui-po', [DonHangController::class, 'guiPO'])->name('gui.po');
//Tắt mở
Route::post('/mahh/update', [DonHangController::class, 'updateMaHH'])->name('mahh.update');
Route::get('/suggest-mahh', [DonHangController::class, 'suggestMaHH'])->name('mahh.suggest');
//Route for NhapXuatKho

Route::get('/kho', [PhieuNhapXuatKhoController::class, 'index'])->name('kho');
//Tool đổi mã hh toàn bộ
// Route for DanhMuc
Route::get('/danhmuc', [DanhMucController::class, 'index'])->name('danhmuc');
// Kiểm tra trước khi cập nhật
Route::post('/check-update-mahh', [DanhMucController::class, 'checkUpdateMaHH'])->name('checkUpdateMaHH');

// Cập nhật thực sự
Route::post('/update-mahh', [DanhMucController::class, 'updateMaHH'])->name('updateMaHH');
Route::get('/suggest-mahh', [DanhMucController::class, 'suggestMaHH'])->name('mahh.suggest');





// Client Route //
// Route for Home
Route::get('/client/home', [ClientHomeController::class, 'index']);
Route::get('/api/production-orders', [ClientHomeController::class, 'getData']);
