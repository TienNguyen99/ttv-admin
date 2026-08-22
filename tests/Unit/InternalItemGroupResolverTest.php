<?php

namespace Tests\Unit;

use App\Services\InternalItemGroupResolver;
use PHPUnit\Framework\TestCase;

class InternalItemGroupResolverTest extends TestCase
{
    public function test_generic_finished_goods_type_is_not_overridden_from_item_name(): void
    {
        $resolver = new InternalItemGroupResolver();
        $catalog = (object) [
            'item_name' => 'ELASTIC TAPE 55MM RICH BLUE',
            'raw_data' => ['loai' => 'TP'],
        ];

        $this->assertSame('Thành phẩm', $resolver->resolve($catalog));
        $this->assertSame('TP-KHAC', $resolver->code($catalog));
        $this->assertSame('Thành phẩm', $resolver->displayName($catalog));
        $this->assertSame('Thành phẩm', $resolver->family($catalog));
    }

    public function test_blank_sheet_type_stays_ungrouped_even_when_name_contains_elastic_tape(): void
    {
        $resolver = new InternalItemGroupResolver();
        $catalog = (object) [
            'item_name' => 'ELASTIC TAPE 55MM RICH BLUE',
            'raw_data' => ['loai' => ''],
        ];

        $this->assertSame('Chưa phân nhóm', $resolver->resolve($catalog));
        $this->assertSame('CHUA-PHAN-NHOM', $resolver->code($catalog));
    }

    public function test_explicit_elastic_tape_sheet_type_is_grouped_as_elastic_tape(): void
    {
        $resolver = new InternalItemGroupResolver();
        $catalog = (object) [
            'item_name' => 'ELASTIC TAPE 55MM RICH BLUE',
            'raw_data' => ['loai' => 'TPTHUNBAN'],
        ];

        $this->assertSame('Thun bản', $resolver->resolve($catalog));
        $this->assertSame('TP-THUNBAN', $resolver->code($catalog));
    }

    public function test_name_without_sheet_type_is_not_used_for_report_grouping(): void
    {
        $resolver = new InternalItemGroupResolver();

        $this->assertSame('Chưa phân nhóm', $resolver->resolveName('Nhãn dệt woven label'));
    }

    public function test_source_type_is_used_when_name_has_no_specific_group(): void
    {
        $resolver = new InternalItemGroupResolver();
        $catalog = (object) [
            'item_name' => '75D 807-C',
            'raw_data' => ['loai' => 'SỢI'],
        ];

        $this->assertSame('Sợi', $resolver->resolve($catalog));
        $this->assertSame('NVL-SOI', $resolver->code($catalog));
        $this->assertSame('Nguyên vật liệu', $resolver->family($catalog));
    }

    public function test_specific_sheet_types_remain_separate_goods_types(): void
    {
        $resolver = new InternalItemGroupResolver();
        $catalog = (object) [
            'item_name' => 'Hàng in theo đơn',
            'raw_data' => ['loai' => 'TPDAYIN'],
        ];

        $this->assertSame('Thành phẩm dây in', $resolver->resolve($catalog));
        $this->assertSame('TP-DAYIN', $resolver->code($catalog));
    }
}
