<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use RuntimeException;

class WeavingExcelTemplateFactory
{
    public function create(): Spreadsheet
    {
        $path = resource_path('templates/LENH_DET.xlsx');
        if (!is_file($path)) {
            throw new RuntimeException('Thiếu file mẫu Excel LENH_DET.');
        }

        $book = IOFactory::load($path);
        if (!$book->getSheetByName(GoogleSheetWeavingTemplateWriter::SHEET_NAME)) {
            $book->disconnectWorksheets();
            throw new RuntimeException('File mẫu Excel không có tab LENH_DET.');
        }
        $sheet = $book->getSheetByName(GoogleSheetWeavingTemplateWriter::SHEET_NAME);
        // IMAGE() is not supported by older desktop Excel versions. The Excel
        // exporter embeds QR/product images as drawings instead.
        $sheet->setCellValue('J2', '');
        $sheet->setCellValue('G19', '');
        $sheet->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(1)
            ->setPrintArea('A1:K42');

        return $book;
    }
}
