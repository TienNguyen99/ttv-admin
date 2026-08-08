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
                'metadata' => [],
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
        $this->assertTrue($formulas->contains('=SUM(B33:C42)*1.1'));

        $book->disconnectWorksheets();
        unlink($result['path']);
    }
}
