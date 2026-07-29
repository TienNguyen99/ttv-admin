<?php

namespace Tests\Unit;

use App\Http\Controllers\InternalWeavingController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class InternalWeavingDesignSheetParserTest extends TestCase
{
    public function test_operations_and_machine_metrics_are_parsed_separately(): void
    {
        $rows = [
            ['Khách Hàng', 'UNIPAX', '', '', '', 'LỆNH IN', 'M-02062/26'],
            ['PO', 'MẪU UNIPAX', '', '', '', 'MÃ HÀNG', 'C006527CANS'],
            ['Ten Label', '', '', '', '', 'Loai', 'Ma So Chi', 'Ke', 'Ten Mau Chi', 'TL/1PCS', 'T.L(g)'],
            ['Ui Keo', 'Ép keo', '', '', '', '1', '75', 'DEN03DECA', '71/6', 'ĐEN 75', '0,16', '4,8'],
            ['Loop', 'Cắt gấp 2 đầu', '', '', '', '2', '75', '200C', '', 'ĐỎ 200C', '0,11', '3,3'],
            ['Phần Trên', 'Gấp vào 5 mm', '', '', '', '3'],
            ['So Pick', 'Mat Do', 'May', 'So Cuon', '', 'So Luong +10%', 'So Dong+10%', '', 'CA'],
            ['885', '26.2', 'TRANG', 'Muller', '40', '83', 'Muller', '2', '0'],
            ['', '', '', 'Hi-Tex', '64', '', 'Hi-Tex', '1', '0'],
        ];

        $parsed = $this->invokePrivate('parseDesignSheetRows', [$rows, '3825']);

        $this->assertSame('M-02062/26', $parsed['order_code']);
        $this->assertSame('C006527CANS', $parsed['item_code']);
        $this->assertSame('', $parsed['metadata']['label_name']);
        $this->assertSame('Ép keo', $parsed['metadata']['operations']['UI KEO']);
        $this->assertSame('Cắt gấp 2 đầu', $parsed['metadata']['operations']['LOOP']);
        $this->assertSame('Gấp vào 5 mm', $parsed['metadata']['operations']['PHAN TREN']);
        $this->assertSame('DEN03DECA', $parsed['lines'][0]['material_code']);
        $this->assertSame('200C', $parsed['lines'][1]['material_code']);
        $this->assertSame('885', $parsed['metadata']['pick']);
        $this->assertSame('26.2', $parsed['metadata']['density']);
        $this->assertSame('TRANG', $parsed['metadata']['machine']);
        $this->assertSame('Muller', $parsed['metadata']['roll_machine_small']);
        $this->assertSame('40', $parsed['metadata']['roll_count_small']);
        $this->assertSame('Hi-Tex', $parsed['metadata']['roll_machine_large']);
        $this->assertSame('64', $parsed['metadata']['roll_count_large']);
        $this->assertSame('Muller', $parsed['metadata']['row_machine_small']);
        $this->assertSame('2', $parsed['metadata']['row_count_plus_10']);
        $this->assertSame('Hi-Tex', $parsed['metadata']['row_machine_large']);
        $this->assertSame('1', $parsed['metadata']['row_count_plus_10_large']);
    }

    public function test_central_order_overrides_business_data_from_design_sheet(): void
    {
        $parsed = [
            'order_code' => 'M-02062/26',
            'item_code' => 'C006537CANS',
            'item_name' => '',
            'customer' => 'SAI',
            'po' => 'SAI',
            'order_quantity' => 1035,
            'unit' => 'PCS',
            'job_date' => '2026-07-01',
            'delivery_date' => null,
            'warnings' => [],
        ];

        $resolved = $this->invokePrivate('applyCentralProductionOrder', [
            $parsed,
            ['M-02062/26' => [
                'order_code' => 'M-02062/26',
                'item_code' => 'C006527CANS',
                'item_name' => 'BACK LOOP LABEL',
                'customer' => 'UNIPAX',
                'po' => 'PO-2062',
                'order_quantity' => 30,
                'unit' => 'PCS',
                'received_date' => '2026-07-20',
                'promised_date' => '2026-07-30',
            ]],
        ]);

        $this->assertNull($resolved['error']);
        $this->assertSame('C006527CANS', $resolved['parsed']['item_code']);
        $this->assertSame('BACK LOOP LABEL', $resolved['parsed']['item_name']);
        $this->assertSame('UNIPAX', $resolved['parsed']['customer']);
        $this->assertSame('PO-2062', $resolved['parsed']['po']);
        $this->assertSame(30, $resolved['parsed']['order_quantity']);
        $this->assertSame('2026-07-20', $resolved['parsed']['job_date']);
        $this->assertSame('2026-07-30', $resolved['parsed']['delivery_date']);
        $this->assertNotEmpty($resolved['parsed']['warnings']);
    }

    public function test_total_weight_can_be_converted_back_to_consumption(): void
    {
        $lines = $this->invokePrivate('deriveBomConsumptionFromTotals', [[[
                'material_code' => '75D',
                'consumption_per_unit' => 0,
                'total_grams' => '11,4',
                'waste_percent' => 0,
            ], [
                'material_code' => '65N',
                'consumption_per_unit' => 0,
                'total_grams' => 33,
                'waste_percent' => 10,
            ]],
            30,
        ]);

        $this->assertSame(0.38, $lines[0]['consumption_per_unit']);
        $this->assertSame(1.0, $lines[1]['consumption_per_unit']);
    }

    private function invokePrivate(string $method, array $arguments)
    {
        $reflection = new ReflectionMethod(InternalWeavingController::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(new InternalWeavingController(), $arguments);
    }
}
