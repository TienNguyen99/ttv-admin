<?php

namespace Tests\Unit;

use App\Services\InternalItemGroupResolver;
use PHPUnit\Framework\TestCase;

class InternalItemGroupResolverTest extends TestCase
{
    public function test_elastic_tape_is_grouped_as_finished_goods_elastic_tape(): void
    {
        $resolver = new InternalItemGroupResolver();
        $catalog = (object) [
            'item_name' => 'ELASTIC TAPE 55MM RICH BLUE',
            'raw_data' => ['loai' => 'TP'],
        ];

        $this->assertSame('Thun bản', $resolver->resolve($catalog));
        $this->assertSame('TP-THUNBAN', $resolver->code($catalog));
        $this->assertSame('Thành phẩm thun bản', $resolver->displayName($catalog));
        $this->assertSame('Thành phẩm', $resolver->family($catalog));
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
