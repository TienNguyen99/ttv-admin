<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;




class PhieuXuatKhoController extends Controller
{
    public function index()
    {
        $data = DB::table('DataKetoan2025')
            ->join('codehanghoa', 'DataKetoan2025.Ma_hh', '=', 'codehanghoa.Ma_hh')

            // ->where('Ma_ct', '=', 'XV')
            ->where('Ma_ko', '=', 'KHODUY')
            ->get();
        return view('pxkunipax', ['data' => $data]);
    }

    public function export($so_ct)
    {
        $so_ct = str_replace('-', '/', $so_ct);

        // Lấy tất cả dòng có cùng So_ct
        $rows = DB::table('DataKetoan2025')
            ->join('codehanghoa', 'DataKetoan2025.Ma_hh', '=', 'codehanghoa.Ma_hh')
            ->where('So_ct', $so_ct)
            ->where('Ten_hh', 'like', '%1028%')
            ->where('Ma_ct', '=', 'XV')
            ->where('Ma_ko', '=', 'KHODUY')
            ->get();

        if ($rows->isEmpty()) {
            abort(404, 'Không tìm thấy dữ liệu');
        }

        // Load file template Excel
        $templatePath = storage_path('app/templates/vithanh.xlsx');
        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        // Ghi thông tin chung (dùng dòng đầu tiên)
        $first = $rows[0];
        $sheet->setCellValue('C7', \Carbon\Carbon::parse($first->Ngay_ct)->format('d/m/Y'));

        // Vị trí dòng bắt đầu ghi dữ liệu
        $startRow = 13;
        $rowCount = count($rows);

        // Nếu có nhiều dòng, chèn thêm dòng để đủ chỗ ghi dữ liệu
        if ($rowCount > 1) {
            $sheet->insertNewRowBefore($startRow + 1, $rowCount - 1);

            // Sao chép style từ dòng mẫu
            for ($i = 0; $i < $rowCount - 1; $i++) {
                $targetRow = $startRow + 1 + $i;
                $sheet->duplicateStyle($sheet->getStyle("A{$startRow}:N{$startRow}"), "A{$targetRow}:N{$targetRow}");
                // Bật wrap text cho từng ô
                foreach (range('A', 'N') as $col) {
                    $sheet->getStyle("{$col}{$targetRow}")->getAlignment()->setWrapText(true);
                }
            }
        }

        // Ghi dữ liệu vào từng dòng
        foreach ($rows as $index => $row) {
            $currentRow = $startRow + $index;
            $sheet->setCellValue("A{$currentRow}", $index + 1);
            $sheet->setCellValue("B{$currentRow}", \Carbon\Carbon::parse($first->Ngay_ct)->format('d/m/Y') ?? '');
            $sheet->setCellValue("C{$currentRow}", $row->Ten_hh ?? '');
            $sheet->setCellValue("D{$currentRow}", $row->Soseri ?? '');
            $sheet->setCellValue("E{$currentRow}", $row->Msize ?? '');
            $sheet->setCellValue("F{$currentRow}", $row->Ma_ch ?? '');
            $sheet->setCellValue("H{$currentRow}", $row->Dvt ?? '');
            $sheet->setCellValue("I{$currentRow}", $row->Soluong ?? '');
            $sheet->setCellValue("J{$currentRow}", $row->TienCvnd ?? 0);
            $sheet->setCellValue("K{$currentRow}", $row->TienCnte ?? 0);
            $sheet->setCellValue("L{$currentRow}", $row->TienHvnd ?? 0);
            $sheet->setCellValue("M{$currentRow}", $row->TienHnte ?? 0);
            $sheet->setCellValue("N{$currentRow}", $row->DgiaiV ?? '');
        }

        // Xuất file Excel
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = "Phieu_" . str_replace('/', '-', $so_ct) . ".xlsx";

        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content)
            ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->header('Content-Disposition', "attachment; filename={$filename}");
    }
    /*public function unipaxexport($so_ct)
    {
        $so_ct = str_replace('-', '/', $so_ct);

        // Lấy tất cả dòng có cùng So_ct
        $rows = DB::table('DataKetoan2025')
            ->join('codehanghoa', 'DataKetoan2025.Ma_hh', '=', 'codehanghoa.Ma_hh')
            ->where('So_ct', $so_ct)
            ->where('Ten_hh', 'not like', '%1028%')
            ->where('Ma_ct', '=', 'XV')
            ->where('Ma_ko', '=', 'KHODUY')
            ->get();

        if ($rows->isEmpty()) {
            abort(404, 'Không tìm thấy dữ liệu');
        }

        // Load file template Excel
        $templatePath = storage_path('app/templates/dongnai.xlsx');
        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();
        Calculation::getInstance()->disableCalculationCache();
        // Ghi thông tin chung (dùng dòng đầu tiên)
        $first = $rows[0];
        $sheet->setCellValue('C7', \Carbon\Carbon::parse($first->Ngay_ct)->format('d/m/Y'));

        // Vị trí dòng bắt đầu ghi dữ liệu
        $startRow = 13;
        $rowCount = count($rows);

        // Nếu có nhiều dòng, chèn thêm dòng để đủ chỗ ghi dữ liệu
        if ($rowCount > 1) {
            $sheet->insertNewRowBefore($startRow + 1, $rowCount - 1);

            // Sao chép style từ dòng mẫu
            for ($i = 0; $i < $rowCount - 1; $i++) {
                $targetRow = $startRow + 1 + $i;
                $sheet->duplicateStyle($sheet->getStyle("A{$startRow}:N{$startRow}"), "A{$targetRow}:N{$targetRow}");
                // Bật wrap text cho từng ô
                foreach (range('A', 'N') as $col) {
                    $sheet->getStyle("{$col}{$targetRow}")->getAlignment()->setWrapText(true);
                }
            }
        }

        // Ghi dữ liệu vào từng dòng
        foreach ($rows as $index => $row) {
            $currentRow = $startRow + $index;
            $sheet->setCellValue("A{$currentRow}", $index + 1);

            $sheet->setCellValue("B{$currentRow}", \Carbon\Carbon::parse($first->Ngay_ct)->format('d/m/Y') ?? '');
            $sheet->setCellValue("C{$currentRow}", $row->Ten_hh ?? '');
            $sheet->setCellValue("D{$currentRow}", $row->Soseri ?? '');
            $sheet->setCellValue("E{$currentRow}", $row->Msize ?? '');
            $formulaF = "=VLOOKUP(D{$currentRow};'Y:\\1. DUY 2024\\17. UNIPAX 2024\\1. THEO D�I N H�NG + MU\\[1. THEO D�I N H�NG UNIPAX - GIA C�NG 18-05-2024.xlsx]TNG HP M� KH�C'!\$D\$7:\$O\$50000;3;FALSE)";
            $formulaG = "=VLOOKUP(D{$currentRow};'Y:\\1. DUY 2024\\17. UNIPAX 2024\\1. THEO D�I N H�NG + MU\\[1. THEO D�I N H�NG UNIPAX - GIA C�NG 18-05-2024.xlsx]TNG HP M� KH�C'!\$D\$7:\$O\$50000;4;FALSE)";
            $sheet->setCellValueExplicit("F{$currentRow}", $formulaF, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("G{$currentRow}", $formulaG, DataType::TYPE_STRING);
            $sheet->setCellValue("H{$currentRow}", $row->Dvt ?? '');
            $sheet->setCellValue("I{$currentRow}", $row->Soluong ?? '');
            $sheet->setCellValue("J{$currentRow}", $row->TienCvnd ?? 0);
            $sheet->setCellValue("K{$currentRow}", $row->TienCnte ?? 0);
            $sheet->setCellValue("M{$currentRow}", $row->DgiaiV ?? '');
        }

        // Xuất file Excel
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = "PhieuUnipax_" . str_replace('/', '-', $so_ct) . ".xlsx";

        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content)
            ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->header('Content-Disposition', "attachment; filename={$filename}");
    }*/
}
