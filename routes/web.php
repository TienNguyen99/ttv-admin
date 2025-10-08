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
use App\Http\Controllers\KeToanController;
use App\Http\Controllers\TiviController;
use App\Http\Controllers\PhieuUnipax;



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



// KHU VỰC ROUTE HIỂN THỊ HOME CHO CLIENT //////    


// Client Route //
// Routeu for Hiển thị Tivi trên Client
Route::get('/client/tivi', [TiviController::class, 'tiviIndex']);
Route::get('/api/tivi', [TiviController::class, 'getTiviData']);
// Route for Unipax only
Route::get('/client/unipax', [ClientHomeController::class, 'indexUnipax']);
// Route for GRS only
Route::get('/client/grs', [ClientHomeController::class, 'indexGRS']);
// Route for Home
Route::get('/client/home', [ClientHomeController::class, 'index']); 
// Route for San Xuat
Route::get('/client/sanxuat', [SanXuatController::class, 'index']);
Route::get('/api/sanxuat', [SanXuatController::class, 'getData']);
Route::put('/api/sanxuat/{SttRecN}', [SanXuatController::class, 'update']);
Route::delete('/api/sanxuat/{SttRecN}', [SanXuatController::class, 'destroy']);
////////////////////////////////////////////////////////////////////////







Route::get('/api/production-orders', [ClientHomeController::class, 'getData']);
Route::get('/api/nhapkho-chi-tiet', [ClientHomeController::class, 'getNhapKhoDetail']);
//API lấy danh sách xuất kho ke toán theo mã hàng hóa
Route::get('/api/xuatkhoketoan-chi-tiet', [ClientHomeController::class, 'getXuatKhoKeToanDetail']);
//API lấy danh sách xuất vật tư của lệnh
Route::get('/api/xuat-vat-tu', [ClientHomeController::class, 'getXuatVatTu']);
//API lấy danh sách phân tích theo số đơn hàng
Route::get('/api/phan-tich', [ClientHomeController::class, 'getPhanTich']);
//API lấy danh sách vật tư thành phẩm của kế toán để tìm nguyên liệu phân tích
Route::get('/api/vat-tu-thanh-pham-ketoan', [ClientHomeController::class, 'getVatTuThanhPhamKeToan']);
//Tool đổi mã hh toàn bộ//
// Route for DanhMuc
Route::get('/danhmuc', [DanhMucController::class, 'index'])->name('danhmuc');
Route::get('/doinl', [DanhMucController::class, 'doinl'])->name('doinl');
// Kiểm tra trước khi cập nhật
Route::post('/check-update-mahh', [DanhMucController::class, 'checkUpdateMaHH'])->name('checkUpdateMaHH');

// Cập nhật thực sự
Route::post('/update-mahh', [DanhMucController::class, 'updateMaHH'])->name('updateMaHH');
// update mã nguyên liệu
Route::post('/update-manl', [DanhMucController::class, 'updateMaNL'])->name('updateMaNL');
// 
Route::get('/suggest-mahh', [DanhMucController::class, 'suggestMaHH'])->name('mahh.suggest');






// DATABASE KE TOAN
Route::get('/client/ketoan', [App\Http\Controllers\KeToanController::class, 'index']);
// API DATABASE KETOAN  
Route::get('/api/ketoan-today', [App\Http\Controllers\KeToanController::class, 'getDataToday']);


// Route riêng dành cho tool Unipax


Route::prefix('phieu-nhap')->group(function () {
    Route::get('/', [PhieuUnipax::class, 'index'])->name('phieuunipax.index');
    Route::get('/rows', [PhieuUnipax::class, 'getRows'])->name('phieuunipax.rows'); // AJAX
    Route::post('/', [PhieuUnipax::class, 'store'])->name('phieuunipax.store');
});
Route::get('/phieu-nhap/view-all', [PhieuUnipax::class, 'viewAllFixed']);
Route::get('/phieu-nhap/refresh-cache', [PhieuUnipax::class, 'refreshCache'])
    ->name('phieuunipax.refreshCache');