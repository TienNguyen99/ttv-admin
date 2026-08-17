<?php

namespace Tests\Unit;

use App\Services\WeavingExcelTemplateFactory;
use App\Services\WeavingExcelBatchExporter;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class WeavingExcelTemplateFactoryTest extends TestCase
{
    public function test_it_creates_a_readable_single_sheet_template_with_required_formulas(): void
    {
        $book = (new WeavingExcelTemplateFactory())->create();
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'weaving-template-' . uniqid('', true) . '.xlsx';
        $writer = IOFactory::createWriter($book, 'Xlsx');
        $writer->setPreCalculateFormulas(false);
        $writer->save($path);
        $book->disconnectWorksheets();

        $reopened = IOFactory::load($path);
        $sheet = $reopened->getSheetByName('LENH_DET');

        $this->assertNotNull($sheet);
        $this->assertSame(1, $reopened->getSheetCount());
        $this->assertSame('LỆNH DỆT', $sheet->getCell('A1')->getValue());
        $this->assertNull($sheet->getCell('J2')->getValue());
        $this->assertSame('=K6/SUM($B$33:$C$42)', $sheet->getCell('J6')->getValue());
        $this->assertSame('=0.532*G15*1.1', $sheet->getCell('K13')->getValue());
        $this->assertSame('=SUM(B33:C42)*1.1', $sheet->getCell('G15')->getValue());
        $this->assertSame('=+I15*A15/(420*500)', $sheet->getCell('K15')->getValue());
        $this->assertCount(92, $sheet->getMergeCells());
        $this->assertSame('A1:K42', $sheet->getPageSetup()->getPrintArea());

        $reopened->disconnectWorksheets();
        unlink($path);
    }

    public function test_excel_export_embeds_qr_without_unsupported_image_formula(): void
    {
        $result = app(WeavingExcelBatchExporter::class)->single('T-02055/26', [
            'order' => [
                'production_order' => 'T-02055/26',
                'customer' => 'UNIPAX',
                'item_code' => 'C006416-SDWF41S',
                'planned_quantity' => 250,
                'unit' => 'PCS',
                'metadata' => [
                    'calculation_waste_percent' => 7.5,
                    'color_weight_factor' => 0.2,
                    'color_weight_multiplier' => 2,
                    'warp_weight_factor' => 0.6,
                    'warp_extra_waste_percent' => 2,
                    'roll_count_small' => 24,
                    'roll_count_large' => 36,
                    'muller_capacity' => 200000,
                    'hitex_capacity' => 150000,
                ],
            ],
            'source_items' => [[
                'item_code' => 'C006416-SDWF41S',
                'order_quantity' => 250,
                'materials' => [[
                    'material_code' => '282C',
                    'consumption_per_unit' => 0.112,
                    'total_grams' => 28,
                ]],
            ]],
            'data' => [],
        ]);

        $book = IOFactory::load($result['path']);
        $sheet = $book->getSheetByName('LENH_DET');
        $formulas = collect($sheet->getCellCollection()->getCoordinates())
            ->mapWithKeys(fn (string $coordinate) => [$coordinate => (string) $sheet->getCell($coordinate)->getValue()])
            ->filter(fn (string $value) => str_starts_with($value, '='));

        $this->assertNotNull($sheet);
        $this->assertNull($sheet->getCell('J2')->getValue());
        $this->assertCount(1, $sheet->getDrawingCollection());
        $this->assertFalse($formulas->contains(fn (string $formula) => stripos($formula, 'IMAGE(') !== false));
        $this->assertEmpty($formulas->keys()->filter(fn (string $coordinate) => preg_match('/^[JK](?:6|7|8|9|10|11|12)$/', $coordinate))->all());
        $this->assertSame(0.112, $sheet->getCell('J6')->getValue());
        $this->assertSame(28.0, $sheet->getCell('K6')->getValue());
        $this->assertSame(24.0, $sheet->getCell('E15')->getValue());
        $this->assertSame(36.0, $sheet->getCell('E16')->getValue());
        $this->assertTrue($formulas->contains('=SUM(B33:C42)*(1+7.5/100)'));
        $this->assertSame('=0.6*G15*(1+2/100)', $sheet->getCell('K13')->getValue());
        $this->assertSame('=IFERROR(I15*A15/200000,0)', $sheet->getCell('K15')->getValue());
        $this->assertSame('=IFERROR(I16*A15/150000,0)', $sheet->getCell('K16')->getValue());

        $book->disconnectWorksheets();
        unlink($result['path']);
    }

    public function test_excel_export_uses_color_factors_when_bom_consumption_is_blank(): void
    {
        $result = app(WeavingExcelBatchExporter::class)->single('T-FACTOR/26', [
            'order' => [
                'production_order' => 'T-FACTOR/26',
                'item_code' => 'ITEM-FACTOR',
                'planned_quantity' => 100,
                'metadata' => [
                    'calculation_waste_percent' => 5,
                    'color_weight_factor' => 0.2,
                    'color_weight_multiplier' => 2,
                ],
            ],
            'source_items' => [[
                'item_code' => 'ITEM-FACTOR',
                'order_quantity' => 100,
                'materials' => [[
                    'material_code' => 'YARN-01',
                    'consumption_per_unit' => 0,
                    'total_grams' => 0,
                ]],
            ]],
            'data' => [],
        ]);

        $book = IOFactory::load($result['path']);
        $sheet = $book->getSheetByName('LENH_DET');

        $this->assertEqualsWithDelta(0.42, (float) $sheet->getCell('J6')->getValue(), 0.000001);
        $this->assertEqualsWithDelta(42.0, (float) $sheet->getCell('K6')->getValue(), 0.000001);

        $book->disconnectWorksheets();
        unlink($result['path']);
    }

    public function test_printable_html_uses_only_the_excel_print_area(): void
    {
        $html = app(WeavingExcelBatchExporter::class)->printableHtml([
            'order' => [
                'production_order' => 'T-PRINT/26',
                'customer' => 'UNIPAX',
                'item_code' => 'ITEM-PRINT',
                'planned_quantity' => 30,
                'unit' => 'PCS',
            ],
            'source_items' => [[
                'item_code' => 'ITEM-PRINT',
                'order_quantity' => 30,
                'materials' => [[
                    'material_code' => 'YARN-01',
                    'consumption_per_unit' => 0.3,
                    'total_grams' => 9,
                ]],
            ]],
            'data' => [],
        ]);

        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('T-PRINT/26', $html);
        $this->assertStringContainsString('class="row41"', $html);
        $this->assertStringNotContainsString('class="row42"', $html);
        $this->assertLessThan(200000, strlen($html));
        $this->assertLessThan(400, substr_count($html, '<td'));
    }
}
