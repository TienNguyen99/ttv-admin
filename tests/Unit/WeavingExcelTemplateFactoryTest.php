<?php

namespace Tests\Unit;

use App\Services\WeavingExcelTemplateFactory;
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
        $this->assertSame('=K6/SUM($B$33:$C$42)', $sheet->getCell('J6')->getValue());
        $this->assertSame('=0.532*G15*1.1', $sheet->getCell('K13')->getValue());
        $this->assertSame('=SUM(B33:C42)*1.1', $sheet->getCell('G15')->getValue());
        $this->assertSame('=+I15*A15/(420*500)', $sheet->getCell('K15')->getValue());
        $this->assertCount(92, $sheet->getMergeCells());
        $this->assertSame('A1:K42', $sheet->getPageSetup()->getPrintArea());

        $reopened->disconnectWorksheets();
        unlink($path);
    }
}
