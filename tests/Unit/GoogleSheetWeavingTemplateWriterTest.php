<?php

namespace Tests\Unit;

use App\Services\GoogleSheetWeavingTemplateWriter;
use PHPUnit\Framework\TestCase;

class GoogleSheetWeavingTemplateWriterTest extends TestCase
{
    public function test_it_maps_weaving_plan_to_fixed_template_ranges(): void
    {
        $writer = new GoogleSheetWeavingTemplateWriter();
        $ranges = collect($writer->buildRanges([
            'order' => [
                'production_order' => 'M-03816/26',
                'customer' => 'UNIPAX',
                'item_code' => 'C006463-NPOR',
                'po_number' => 'MAU 30-06-2026',
                'design_code' => '3816.UNI.C006463-NPOR',
                'order_date' => '2026-07-28',
                'due_date' => '2026-08-01',
                'planned_quantity' => 30,
                'metadata' => [
                    'job_number' => 'JOB-3816',
                    'label_name' => 'Nhãn dệt',
                    'warp_grams' => 19.31,
                    'pick' => '1376',
                    'density' => '26',
                    'machine' => 'TRẮNG',
                    'file_name' => 'USB-3816',
                    'operations' => [
                        'UI KEO' => 'Ép dựng',
                        'PHAN TREN' => 'THEO ĐỊNH HÌNH',
                    ],
                ],
            ],
            'source_items' => [[
                'item_code' => 'C006463-NPOR',
                'item_name' => 'Nhãn dệt',
                'materials' => [[
                    'type' => '75D',
                    'material_code' => '15A',
                    'catalog_shelf_code' => 'A1',
                    'material_name' => '15A KEM',
                    'consumption_per_unit' => 0.38,
                    'required_quantity_raw' => 11.4,
                    'total_grams' => 11.39,
                ]],
            ]],
            'data' => [[
                'type' => '75D',
                'material_code' => '15A',
                'first_location' => 'A1',
                'catalog_name' => '15A KEM',
                'consumption_per_unit' => 0.38,
                'required_quantity_raw' => 11.4,
            ]],
        ]))->keyBy('range');

        $this->assertSame('UNIPAX', $ranges['B2']['values'][0][0]);
        $this->assertSame('M-03816/26', $ranges['H2']['values'][0][0]);
        $this->assertStringContainsString('quickchart.io/qr', $ranges['J2']['values'][0][0]);
        $this->assertSame('JOB-3816', $ranges['B4']['values'][0][0]);
        $this->assertSame('28/07/2026', $ranges['D4']['values'][0][0]);
        $this->assertSame(['75D', '15A', 'A1', '15A KEM'], $ranges['F6:I12']['values'][0]);
        $this->assertCount(7, $ranges['F6:I12']['values']);
        $this->assertSame(['1376', '26', 'TRẮNG'], $ranges['A15:C15']['values'][0]);
        $this->assertSame('Muller', $ranges['D15']['values'][0][0]);
        $this->assertSame('Hi-Tex', $ranges['D16']['values'][0][0]);
        $this->assertArrayNotHasKey('K13', $ranges);
        $this->assertArrayNotHasKey('A15:K16', $ranges);
        $this->assertSame('USB-3816', $ranges['A20']['values'][0][0]);
        $this->assertSame(['C006463-NPOR', 30.0, '', ''], $ranges['A33:D33']['values'][0]);
    }
}
