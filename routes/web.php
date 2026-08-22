<?php

use App\Http\Controllers\ClientHomeController;
use App\Http\Controllers\DanhMucController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DonHangController;
use App\Http\Controllers\PhieuNhapXuatKhoController;
use App\Http\Controllers\PhieuXuatKhoController;
use App\Http\Controllers\SanXuatController;
use App\Http\Controllers\UnipaxController;
use App\Http\Controllers\TiviController;
use App\Http\Controllers\PhieuUnipax;
use App\Http\Controllers\QuyDoiMucController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\Tool;
use App\Http\Controllers\InventoryComparisonController;
use App\Http\Controllers\WarehouseCountController;
use App\Http\Controllers\InternalOrderTrackingController;
use App\Http\Controllers\InternalProductionOrderController;
use App\Http\Controllers\InternalBtpProductionOrderController;
use App\Http\Controllers\InternalFinishedGoodsReceiptController;
use App\Http\Controllers\InternalMaterialIssueController;
use App\Http\Controllers\InternalItemCatalogController;
use App\Http\Controllers\InternalStocktakeController;
use App\Http\Controllers\InternalColorMappingController;
use App\Http\Controllers\InternalUnitConversionController;
use App\Http\Controllers\InternalXntController;
use App\Http\Controllers\InternalWeavingController;
use App\Http\Controllers\WeavingManagementPageController;
use App\Http\Controllers\InternalInventoryReportController;
use App\Http\Controllers\InternalCustomerController;
use App\Http\Controllers\LocalQrCodeController;
use Google\Service\Dfareporting\Order;


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
Route::get('/tool', [Tool::class, 'index'])->name('tool');

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

//Tắt mở
// Route::post('/mahh/update', [DonHangController::class, 'updateMaHH'])->name('mahh.update');
// Route::get('/suggest-mahh', [DonHangController::class, 'suggestMaHH'])->name('mahh.suggest');
//Route for NhapXuatKho

Route::get('/kho', [PhieuNhapXuatKhoController::class, 'index'])->name('kho');



// KHU VỰC ROUTE HIỂN THỊ HOME CHO CLIENT //////    


// Client Route //
// Trang TV riêng cho sản xuất
Route::get('/client/tivisanxuat', [TiviController::class, 'tiviSanxuat']);
// View xem dữ liệu SX
// View xem toàn bộ dữ liệu SX
Route::get('/client/view-all-sx-data', [TiviController::class, 'viewAllSXData']);
// View xem dữ liệu phân tích (NX)
Route::get('/client/view-nx-data', [TiviController::class, 'viewNXData']);
// API hiển thị dữ liệu Tivi
Route::get('/api/tivi', [TiviController::class, 'getTiviData']);
Route::get('/api/tivi/sx-data', [TiviController::class, 'getSXData']);
Route::get('/api/tivi/nx-data', [TiviController::class, 'getNXData']);
Route::get('/api/tivi/all-sx-data', [TiviController::class, 'getAllSXData']);
Route::get('/api/tivi/get-data-by-dgiaiV', [TiviController::class, 'getDataByDgiaiV']);
Route::get('/api/tivi/export-ton-kho', [TiviController::class, 'exportTonKho']);
// Trong routes/api.php hoặc routes/web.php
Route::get('/api/tivi/sx-detail/{soCt}', [TiviController::class, 'getSXDetailBySoCt'])
    ->where('soCt', '.*'); // Chấp nhận mọi ký tự kể cả /

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
// Route for Don Hang
Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
Route::get('/orders/data', [OrderController::class, 'getData'])->name('orders.data');
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
// Route for QuyDoiMuc
Route::get('/quydoi-muc', [QuyDoiMucController::class, 'index'])->name('quydoimuc');
Route::get('/get-khomuc', [QuyDoiMucController::class, 'getKhomuc'])->name('getKhomuc');





// DATABASE KE TOAN
Route::get('/client/ketoan', [App\Http\Controllers\KeToanController::class, 'index']);
Route::get('/client/ketoan-ton', [App\Http\Controllers\KeToanController::class, 'tonKho']);
Route::get('/client/phieu-nhap-thanh-pham', [App\Http\Controllers\KeToanController::class, 'nhapThanhPham']);
// API DATABASE KETOAN  
Route::get('/api/ketoan-today', [App\Http\Controllers\KeToanController::class, 'getDataToday']);
Route::get('/api/ketoan-ton', [App\Http\Controllers\KeToanController::class, 'getTonKho']);
Route::get('/api/phieu-nhap-thanh-pham', [App\Http\Controllers\KeToanController::class, 'getNhapThanhPham']);
Route::get('/api/thanh-pham-ke-toan/goi-y', [App\Http\Controllers\KeToanController::class, 'getThanhPhamSuggestions']);
Route::get('/client/doi-chieu-ton', [InventoryComparisonController::class, 'index']);
Route::get('/api/doi-chieu-ton', [InventoryComparisonController::class, 'data']);
Route::post('/api/doi-chieu-ton', [InventoryComparisonController::class, 'store']);
Route::delete('/api/doi-chieu-ton/{inventoryCount}', [InventoryComparisonController::class, 'destroy']);
Route::get('/qr-code', [LocalQrCodeController::class, 'png']);
Route::post('/api/phieu-nhap-thanh-pham-noi-bo', [InternalFinishedGoodsReceiptController::class, 'store']);
Route::get('/client/phieu-nhap-thanh-pham-noi-bo/{receipt}/in', [InternalFinishedGoodsReceiptController::class, 'print']);
Route::get('/client/xuat-vat-tu-noi-bo', [InternalMaterialIssueController::class, 'index']);
Route::get('/api/xuat-vat-tu-noi-bo', [InternalMaterialIssueController::class, 'list']);
Route::get('/api/xuat-vat-tu-noi-bo/lenh-san-xuat', [InternalMaterialIssueController::class, 'productionOrderLines']);
Route::get('/api/xuat-vat-tu-noi-bo/vi-tri-ton', [InternalMaterialIssueController::class, 'stockLocations']);
Route::post('/api/xuat-vat-tu-noi-bo/phan-tich-paste', [InternalMaterialIssueController::class, 'resolvePastedLines']);
Route::get('/api/ma-noi-bo-danh-muc', [InternalItemCatalogController::class, 'suggestions']);
Route::get('/client/theo-doi-san-xuat', [InternalMaterialIssueController::class, 'productionTrackingIndex']);
Route::get('/api/theo-doi-san-xuat', [InternalMaterialIssueController::class, 'productionTracking']);
Route::post('/api/xuat-vat-tu-noi-bo', [InternalMaterialIssueController::class, 'store']);
Route::post('/api/xuat-vat-tu-noi-bo/tu-phieu-nhap/{receipt}', [InternalMaterialIssueController::class, 'createFromReceipt']);
Route::post('/api/xuat-vat-tu-noi-bo/gui-san-xuat/{receipt}', [InternalMaterialIssueController::class, 'sendReceiptToProduction']);
Route::post('/api/xuat-vat-tu-noi-bo/nhap-xuat-thanh-pham-theo-dong', [InternalMaterialIssueController::class, 'receiveProductionLines']);
Route::post('/api/xuat-vat-tu-noi-bo/{issue}/nhap-lai-thanh-pham', [InternalMaterialIssueController::class, 'receiveProductionIssue']);
Route::get('/api/xuat-vat-tu-noi-bo/{issue}', [InternalMaterialIssueController::class, 'show']);
Route::put('/api/xuat-vat-tu-noi-bo/{issue}', [InternalMaterialIssueController::class, 'update']);
Route::delete('/api/xuat-vat-tu-noi-bo/{issue}', [InternalMaterialIssueController::class, 'destroy']);
Route::get('/client/xuat-vat-tu-noi-bo/{issue}/in', [InternalMaterialIssueController::class, 'print']);
Route::get('/api/vat-tu-ke-toan/goi-y', [InternalMaterialIssueController::class, 'materialSuggestions']);
Route::get('/client/xuat-chi-lenh-sx', [InternalXntController::class, 'index']);
Route::get('/api/xnt', [InternalXntController::class, 'data']);
Route::post('/api/xnt/dong-bo', [InternalXntController::class, 'sync']);
Route::post('/api/xnt/tao-phieu-xuat', [InternalXntController::class, 'createIssue']);
Route::prefix('client/quan-ly-det')->name('weaving.')->controller(WeavingManagementPageController::class)->group(function () {
    Route::get('/', 'dashboard')->name('dashboard');
    Route::get('/theo-doi', 'tracking')->name('tracking');
    Route::get('/tao-lenh', 'createOrder')->name('orders.create');
    Route::get('/dinh-muc', 'bom')->name('bom');
    Route::get('/xuat-excel', 'exports')->name('exports.index');
});
Route::get('/client/quan-ly-det/lenh/{order}/in', [InternalWeavingController::class, 'printOrder'])->name('weaving.orders.print');
Route::get('/client/designer-lenh-det', fn () => redirect()->route('weaving.dashboard', [], 301));
Route::get('/client/designer-tao-lenh-det', function (\Illuminate\Http\Request $request) {
    $target = route('weaving.orders.create');
    return redirect()->to($request->getQueryString() ? $target . '?' . $request->getQueryString() : $target, 301);
});
Route::get('/client/lenh-det', fn () => redirect()->route('weaving.bom', [], 301));

Route::prefix('api/lenh-det')->controller(InternalWeavingController::class)->group(function () {
    Route::get('/designer-dashboard', 'designerDashboard');
    Route::get('/material-suggestions', 'materialSuggestions');
    Route::post('/check-stock', 'checkStock');
    Route::post('/designer-save', 'saveDesignerOrder');
    Route::post('/orders/{order}/gui-san-xuat', 'sendToProduction');
    Route::get('/items', 'items');
    Route::post('/items', 'storeItem');
    Route::post('/items/import', 'importItems');
    Route::get('/boms', 'boms');
    Route::post('/boms', 'replaceBoms');
    Route::post('/boms/import', 'importBoms');
    Route::post('/design-sheet/import', 'importDesignSheet');
    Route::post('/design-workbook/import', 'importDesignWorkbook');
    Route::post('/design-workbook/chunk', 'importDesignWorkbookChunk');
    Route::get('/orders', 'orders');
    Route::post('/orders', 'storeOrder');
    Route::post('/orders/import', 'importOrders');
    Route::get('/orders/{order}/plan', 'plan');
    Route::post('/orders/{order}/issue', 'createIssue');
    Route::get('/production-orders', 'productionOrders');
    Route::get('/production-order-plan', 'productionPlan');
    Route::post('/template-details', 'saveTemplateDetails');
    Route::get('/export-excel', 'exportExcel');
    Route::post('/batch-exports', 'startBatchExport');
    Route::post('/batch-exports/{token}/process', 'processBatchExport');
    Route::get('/batch-exports/{token}', 'batchExportStatus');
    Route::get('/batch-exports/{token}/download', 'downloadBatchExport');
    Route::post('/production-order-issue', 'createProductionIssue');
});
Route::view('/client/material-calculator', 'client.material-calculator');
Route::view('/client/fabric-cut-simulator', 'client.fabric-cut-simulator');
Route::view('/tools/tinh-met-vai', 'tools.fabric-meter-calculator');
Route::view('/client/kho-noi-bo', 'client.warehouse-dashboard');
Route::get('/client/don-hang-noi-bo', [InternalOrderTrackingController::class, 'index']);
Route::get('/api/don-hang-noi-bo', [InternalOrderTrackingController::class, 'data']);
Route::post('/api/don-hang-noi-bo/import', [InternalOrderTrackingController::class, 'import']);
Route::get('/client/lenh-san-xuat-sheet', [InternalProductionOrderController::class, 'index']);
Route::get('/api/lenh-san-xuat-sheet', [InternalProductionOrderController::class, 'data']);
Route::post('/api/lenh-san-xuat-sheet/dong-bo', [InternalProductionOrderController::class, 'sync']);
Route::get('/client/lenh-san-xuat-trung-tam', [InternalProductionOrderController::class, 'workflowIndex']);
Route::get('/api/lenh-san-xuat-trung-tam', [InternalProductionOrderController::class, 'workflow']);
Route::patch('/api/lenh-san-xuat-trung-tam/dong/{order}', [InternalProductionOrderController::class, 'updateStandardItemCode']);
Route::get('/client/lenh-btp', [InternalBtpProductionOrderController::class, 'index']);
Route::get('/client/lenh-btp/tem-qr', [InternalBtpProductionOrderController::class, 'printLabels']);
Route::get('/api/lenh-btp', [InternalBtpProductionOrderController::class, 'data']);
Route::post('/api/lenh-btp', [InternalBtpProductionOrderController::class, 'store']);
Route::post('/api/lenh-btp/hang-loat', [InternalBtpProductionOrderController::class, 'storeBatch']);
Route::post('/api/lenh-btp/tao-phieu-xuat', [InternalBtpProductionOrderController::class, 'createIssueFromOrders']);
Route::delete('/api/lenh-btp/xoa-hang-loat', [InternalBtpProductionOrderController::class, 'bulkDestroy']);
Route::get('/api/lenh-btp/{btpOrder}', [InternalBtpProductionOrderController::class, 'show']);
Route::put('/api/lenh-btp/{btpOrder}', [InternalBtpProductionOrderController::class, 'update']);
Route::delete('/api/lenh-btp/{btpOrder}', [InternalBtpProductionOrderController::class, 'destroy']);
Route::get('/client/danh-muc-noi-bo', [InternalItemCatalogController::class, 'index']);
Route::get('/client/khach-hang-noi-bo', [InternalCustomerController::class, 'page']);
Route::get('/api/khach-hang-noi-bo', [InternalCustomerController::class, 'index']);
Route::get('/api/khach-hang-noi-bo/goi-y', [InternalCustomerController::class, 'suggestions']);
Route::get('/api/khach-hang-noi-bo/kiem-tra', [InternalCustomerController::class, 'check']);
Route::post('/api/khach-hang-noi-bo/dong-bo', [InternalCustomerController::class, 'sync']);
Route::post('/api/khach-hang-noi-bo', [InternalCustomerController::class, 'store']);
Route::patch('/api/khach-hang-noi-bo/{customer}', [InternalCustomerController::class, 'update']);
Route::delete('/api/khach-hang-noi-bo/{customer}', [InternalCustomerController::class, 'destroy']);
Route::get('/client/mau-noi-bo', [InternalColorMappingController::class, 'page']);
Route::get('/api/mau-noi-bo', [InternalColorMappingController::class, 'index']);
Route::post('/api/mau-noi-bo', [InternalColorMappingController::class, 'store']);
Route::delete('/api/mau-noi-bo/{colorMapping}', [InternalColorMappingController::class, 'destroy']);
Route::view('/client/quy-doi-don-vi', 'client.internal-unit-conversions');
Route::get('/api/danh-muc-noi-bo', [InternalItemCatalogController::class, 'data']);
Route::post('/api/danh-muc-noi-bo/nhap-ke-hang-loat', [InternalItemCatalogController::class, 'bulkShelfIntake']);
Route::get('/api/danh-muc-noi-bo/loi-ma-phieu', [InternalItemCatalogController::class, 'invalidDocumentCodes']);
Route::post('/api/danh-muc-noi-bo/dong-bo', [InternalItemCatalogController::class, 'sync']);
Route::post('/api/danh-muc-noi-bo/tu-dong-dong-bo', [InternalItemCatalogController::class, 'autoSync']);
Route::post('/api/danh-muc-noi-bo/dong-bo-vi-tri', [InternalItemCatalogController::class, 'syncShelvesToLocations']);
Route::post('/api/danh-muc-noi-bo/tach-ma-trung', [InternalItemCatalogController::class, 'splitDuplicateCodes']);
Route::post('/api/danh-muc-noi-bo/bien-the-lenh-san-xuat', [InternalItemCatalogController::class, 'productionOrderVariants']);
Route::post('/api/danh-muc-noi-bo/tao-tu-lenh', [InternalItemCatalogController::class, 'ensureFromOrder']);
Route::patch('/api/danh-muc-noi-bo/{catalog}', [InternalItemCatalogController::class, 'updateCatalogRow']);
Route::post('/api/danh-muc-noi-bo/{catalog}/anh', [InternalItemCatalogController::class, 'uploadImage']);
Route::get('/api/quy-doi-don-vi', [InternalUnitConversionController::class, 'index']);
Route::post('/api/quy-doi-don-vi', [InternalUnitConversionController::class, 'store']);
Route::delete('/api/quy-doi-don-vi/{unitConversion}', [InternalUnitConversionController::class, 'destroy']);
Route::get('/client/kiem-ton-kho', [WarehouseCountController::class, 'index']);
Route::get('/client/dot-kiem-ke', [InternalStocktakeController::class, 'page']);
Route::get('/api/dot-kiem-ke', [InternalStocktakeController::class, 'index']);
Route::post('/api/dot-kiem-ke', [InternalStocktakeController::class, 'store']);
Route::get('/api/dot-kiem-ke/{stocktake}', [InternalStocktakeController::class, 'show']);
Route::delete('/api/dot-kiem-ke/{stocktake}', [InternalStocktakeController::class, 'destroy']);
Route::get('/api/dot-kiem-ke/{stocktake}/vi-tri/{stocktakeLocation}', [InternalStocktakeController::class, 'location']);
Route::put('/api/dot-kiem-ke/{stocktake}/vi-tri/{stocktakeLocation}', [InternalStocktakeController::class, 'saveLocation']);
Route::post('/api/dot-kiem-ke/{stocktake}/vi-tri/{stocktakeLocation}/hoan-tat', [InternalStocktakeController::class, 'completeLocation']);
Route::post('/api/dot-kiem-ke/{stocktake}/vi-tri/{stocktakeLocation}/mo-lai', [InternalStocktakeController::class, 'reopenLocation']);
Route::post('/api/dot-kiem-ke/{stocktake}/hoan-tat', [InternalStocktakeController::class, 'complete']);
Route::post('/api/dot-kiem-ke/{stocktake}/ap-dung', [InternalStocktakeController::class, 'post']);
Route::get('/client/mat-ke-kho', [WarehouseCountController::class, 'shelfMapIndex']);
Route::view('/client/nhap-thanh-pham-nhanh', 'client.quick-finished-goods-receipt');
Route::view('/client/xuat-thanh-pham-nhanh', 'client.quick-finished-goods-issue');
Route::get('/client/tivi-nhap-thanh-pham', [WarehouseCountController::class, 'finishedGoodsTvIndex']);
Route::get('/api/tivi-nhap-thanh-pham', [WarehouseCountController::class, 'finishedGoodsTvData']);
Route::get('/client/ton-kho-noi-bo', [WarehouseCountController::class, 'stockIndex']);
Route::get('/client/canh-bao-kho', [WarehouseCountController::class, 'qualityIndex']);
Route::get('/api/canh-bao-kho', [WarehouseCountController::class, 'qualityData']);
Route::get('/api/kho-noi-bo/nhap-xuat-ngay', [WarehouseCountController::class, 'dailyFlow']);
Route::get('/api/ton-kho-noi-bo/export', [WarehouseCountController::class, 'exportStock']);
Route::get('/api/bao-cao-nhap-xuat-ton/loai-hang', [InternalInventoryReportController::class, 'groups']);
Route::get('/api/bao-cao-nhap-xuat-ton/xuat', [InternalInventoryReportController::class, 'export']);
Route::get('/api/ton-kho-noi-bo/kho', [WarehouseCountController::class, 'stockWarehouses']);
Route::get('/api/ton-kho-noi-bo/chi-tiet-fifo', [WarehouseCountController::class, 'stockFifoDetail']);
Route::get('/api/ton-kho-noi-bo', [WarehouseCountController::class, 'stockData']);
Route::patch('/api/ton-kho-noi-bo/ma-ke-toan', [WarehouseCountController::class, 'assignAccountingCode']);
Route::patch('/api/ton-kho-noi-bo/vi-tri', [WarehouseCountController::class, 'assignStockLocation']);
Route::delete('/api/ton-kho-noi-bo', [WarehouseCountController::class, 'destroyOpeningStock']);
Route::get('/client/kiem-ton-kho/vi-tri/{warehouseLocation}', [WarehouseCountController::class, 'showLocation']);
Route::get('/api/kiem-ton-kho/vi-tri', [WarehouseCountController::class, 'locations']);
Route::post('/api/kiem-ton-kho/vi-tri', [WarehouseCountController::class, 'storeLocation']);
Route::post('/api/kiem-ton-kho/vi-tri/tao-nhanh', [WarehouseCountController::class, 'bulkStoreLocations']);
Route::patch('/api/kiem-ton-kho/vi-tri/{warehouseLocation}/layout', [WarehouseCountController::class, 'updateLocationLayout']);
Route::delete('/api/kiem-ton-kho/vi-tri/{warehouseLocation}', [WarehouseCountController::class, 'destroyLocation']);
Route::get('/api/kiem-ton-kho/so-do-ton', [WarehouseCountController::class, 'stockMapData']);
Route::get('/api/kiem-ton-kho/kien', [WarehouseCountController::class, 'packages']);
Route::post('/api/kiem-ton-kho/kien', [WarehouseCountController::class, 'storePackage']);
Route::patch('/api/kiem-ton-kho/kien/{inventoryPackage}', [WarehouseCountController::class, 'updatePackage']);
Route::get('/api/kiem-ton-kho/phieu-nhap-tp', [WarehouseCountController::class, 'receipts']);
Route::post('/api/kiem-ton-kho/phieu-nhap-tp', [WarehouseCountController::class, 'storeReceiptBatch']);
Route::post('/api/kiem-ton-kho/phieu-nhap-tp/kiem-tra-trung', [WarehouseCountController::class, 'checkReceiptDuplicates']);
Route::get('/api/kiem-ton-kho/phieu-nhap-tp/{receipt}', [WarehouseCountController::class, 'showReceipt']);
Route::get('/api/kiem-ton-kho/phieu-nhap-tp/{receipt}/lien-ket', [WarehouseCountController::class, 'receiptLinks']);
Route::put('/api/kiem-ton-kho/phieu-nhap-tp/{receipt}', [WarehouseCountController::class, 'updateReceiptBatch']);
Route::patch('/api/kiem-ton-kho/phieu-nhap-tp/{receipt}/vi-tri', [WarehouseCountController::class, 'updateReceiptLocation']);
Route::delete('/api/kiem-ton-kho/phieu-nhap-tp/{receipt}/dong/{line}', [WarehouseCountController::class, 'destroyReceiptLine']);
Route::delete('/api/kiem-ton-kho/phieu-nhap-tp/{receipt}', [WarehouseCountController::class, 'destroyReceipt']);
Route::patch('/api/kiem-ton-kho/kien/{inventoryPackage}/chuyen-vi-tri', [WarehouseCountController::class, 'movePackage']);
Route::delete('/api/kiem-ton-kho/kien/{inventoryPackage}', [WarehouseCountController::class, 'destroyPackage']);
Route::get('/api/kiem-ton-kho/noi-dung-vi-tri', [WarehouseCountController::class, 'locationContents']);
Route::get('/api/kiem-ton-kho/tra-cuu-giong-noi', [WarehouseCountController::class, 'voiceLookup']);
Route::get('/api/health', [WarehouseCountController::class, 'assistantHealth']);
Route::get('/api/ton', [WarehouseCountController::class, 'assistantStock']);
Route::get('/api/vi-tri/{locationCode}', [WarehouseCountController::class, 'assistantLocation']);
Route::get('/api/phieu-nhap-moi', [WarehouseCountController::class, 'assistantLatestReceipts']);
Route::get('/api/hoi', [WarehouseCountController::class, 'assistantAsk']);
Route::post('/api/assistant/chat', [WarehouseCountController::class, 'assistantChat']);
Route::get('/client/kiem-ton-kho/tem-kien/{inventoryPackage}', [WarehouseCountController::class, 'printPackage']);
Route::get('/client/kiem-ton-kho/tem-vi-tri-hang-loat', [WarehouseCountController::class, 'printLocations']);
Route::get('/client/kiem-ton-kho/tem-vi-tri/{warehouseLocation}', [WarehouseCountController::class, 'printLocation']);
Route::get('/client/nhap-thanh-pham-noi-bo/{receipt}/in', [WarehouseCountController::class, 'printMaterialReceipt']);
Route::get('/client/nhap-vat-tu-noi-bo/{receipt}/in', [WarehouseCountController::class, 'printMaterialReceipt']);


// Route riêng dành cho tool Unipax


Route::prefix('phieu-nhap')->group(function () {
    Route::get('/', [PhieuUnipax::class, 'index'])->name('phieuunipax.index');
    Route::get('/rows', [PhieuUnipax::class, 'getRows'])->name('phieuunipax.rows'); // AJAX
    Route::post('/', [PhieuUnipax::class, 'store'])->name('phieuunipax.store');
});
Route::get('/phieu-nhap/view-all', [PhieuUnipax::class, 'viewAllFixed']);
Route::get('/phieu-nhap/refresh-cache', [PhieuUnipax::class, 'refreshCache'])
    ->name('phieuunipax.refreshCache');
    Route::delete('/phieu-nhap/delete', [PhieuUnipax::class, 'deleteRow'])->name('phieuunipax.delete');
