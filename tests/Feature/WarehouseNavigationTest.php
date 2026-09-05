<?php

namespace Tests\Feature;

use Tests\TestCase;

class WarehouseNavigationTest extends TestCase
{
    public function test_warehouse_receipt_list_has_its_own_page(): void
    {
        $this->get('/client/kho/phieu-nhap')
            ->assertOk()
            ->assertSee('Danh sách phiếu nhập')
            ->assertSee('const workspaceView = "receipts";', false);
    }

    public function test_warehouse_location_list_has_its_own_page(): void
    {
        $this->get('/client/kho/vi-tri')
            ->assertOk()
            ->assertSee('Vị trí kho')
            ->assertSee('const workspaceView = "overview";', false);
    }

    public function test_warehouse_issue_list_hides_the_entry_form_by_default(): void
    {
        $this->get('/client/kho/phieu-xuat')
            ->assertOk()
            ->assertSee('Danh sách phiếu xuất')
            ->assertSee('id="issueEntryPanel" class="panel mb-3 d-none"', false);
    }

    public function test_sidebar_separates_warehouse_management_from_data_entry(): void
    {
        $response = $this->get('/client/kho/phieu-nhap');

        $response->assertSee('Quản lý kho')
            ->assertSee('Tổng quan kho')
            ->assertSee('Tồn kho hiện tại')
            ->assertSee('Danh sách phiếu nhập')
            ->assertSee('Danh sách phiếu xuất')
            ->assertSee('Mặt kệ kho')
            ->assertSee('Vị trí kho')
            ->assertSee('Đợt kiểm kê')
            ->assertSee('Cảnh báo kho')
            ->assertSee('Quản lý nhập liệu')
            ->assertSee('Nhập thành phẩm nhanh')
            ->assertSee('Xuất thành phẩm nhanh');
    }

    public function test_shelf_map_supports_large_queue_and_bulk_delete_controls(): void
    {
        $this->get('/client/mat-ke-kho')
            ->assertOk()
            ->assertSee('1.000 dòng')
            ->assertSee('Xóa đã chọn')
            ->assertSee('Xóa toàn bộ');
    }
}
