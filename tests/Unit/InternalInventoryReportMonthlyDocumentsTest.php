<?php

namespace Tests\Unit;

use App\Http\Controllers\InternalInventoryReportController;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\TestCase;

class InternalInventoryReportMonthlyDocumentsTest extends TestCase
{
    public function test_monthly_document_sheet_groups_and_sorts_receipts_and_issues(): void
    {
        $rows = collect([
            $this->row('2026-08-02', 'PN-0002', 'ITEM-B', 7, 'YARD', 'Nhập kho'),
            $this->row('2026-08-01', 'PX-0001', 'ITEM-A', 4, 'YARD', 'Xuất kho'),
            $this->row('2026-08-01', 'PN-0001', 'ITEM-A', 2, 'YARD', 'Nhập kho'),
            $this->row('2026-08-01', 'PN-0001', 'ITEM-A', 3, 'YARD', 'Nhập kho'),
            $this->row('2026-08-01', 'PN-0001', 'ITEM-C', 1, 'PCS', 'Nhập kho'),
        ]);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $method = new \ReflectionMethod(InternalInventoryReportController::class, 'writeMonthlyDocumentsSheet');
        $method->setAccessible(true);
        $method->invoke(
            new InternalInventoryReportController(),
            $sheet,
            Carbon::create(2026, 8, 1),
            $rows
        );

        $this->assertSame('PHIẾU NHẬP', $sheet->getCell('A4')->getValue());
        $this->assertSame('PHIẾU XUẤT', $sheet->getCell('G4')->getValue());
        $this->assertSame('01/08/2026', $this->excelDate($sheet->getCell('A6')->getValue()));
        $this->assertSame('PN-0001', $sheet->getCell('B6')->getValue());
        $this->assertSame('ITEM-A', $sheet->getCell('C6')->getValue());
        $this->assertSame(5.0, $sheet->getCell('D6')->getValue());
        $this->assertSame('ITEM-C', $sheet->getCell('C7')->getValue());
        $this->assertSame('01/08/2026', $this->excelDate($sheet->getCell('G6')->getValue()));
        $this->assertSame('PX-0001', $sheet->getCell('H6')->getValue());
        $this->assertSame(4.0, $sheet->getCell('J6')->getValue());
        $this->assertSame('02/08/2026', $this->excelDate($sheet->getCell('A8')->getValue()));
        $this->assertSame('PN-0002', $sheet->getCell('B8')->getValue());
        $this->assertContains('A6:A7', $sheet->getMergeCells());
        $this->assertContains('B6:B7', $sheet->getMergeCells());

        $spreadsheet->disconnectWorksheets();
    }

    private function row(string $date, string $document, string $code, float $quantity, string $unit, string $operation): array
    {
        return [
            'date' => $date,
            'document_code' => $document,
            'code' => $code,
            'quantity' => $quantity,
            'unit' => $unit,
            'operation' => $operation,
        ];
    }

    private function excelDate($value): string
    {
        return ExcelDate::excelToDateTimeObject((float) $value)->format('d/m/Y');
    }
}
