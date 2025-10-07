<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as Writer;
use Illuminate\Support\Facades\Auth;

class PhieuUnipax extends Controller
{
    protected $filePath;

    public function __construct()
    {
        // File Excel chính (đặt đúng đường dẫn thực tế của bạn)
        $this->filePath = storage_path('app/public/theodoi.xlsx');
    }

    // Hiển thị form nhập
    public function index()
    {
        $psList = $this->getDistinctPs();
        return view('client.toolunipax', compact('psList'));
    }

    // AJAX: Lấy danh sách dòng trong sheet KINHDOANH thiếu Delivery/Đạt/Lỗi
public function getRows(Request $request)
{
    $ps = trim($request->query('ps'));
    if (!$ps) return response()->json([]);

    $reader = new Xlsx();
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($this->filePath);

    $sheet = $spreadsheet->getSheetByName('KINHDOANH');
    if (!$sheet) {
        return response()->json(['error' => 'Không tìm thấy sheet KINHDOANH'], 400);
    }

    $rows = [];
    $startRow = 5;
    $psCol = 4;       // Cột P/S
    $mahangCol = 3;   // Cột Mã hàng
    $sizeCol = 5;     // Cột Size
    $mauCol = 6;      // Cột Màu
    $slThucCol = 11;  // SL thực nhận
    $deliveryCol = 12;
    $datCol = 13;
    $loiCol = 14;
    $matCol = 16;     // Mặt
    $ghichuCol = 17;  // Ghi chú (nếu có)
    $highestRow = $sheet->getHighestRow();

    for ($r = $startRow; $r <= $highestRow; $r++) {
        $psVal = trim((string)$sheet->getCellByColumnAndRow($psCol, $r)->getValue());
        if ($psVal === $ps) {
            $delivery = trim((string)$sheet->getCellByColumnAndRow($deliveryCol, $r)->getValue());
            $dat = trim((string)$sheet->getCellByColumnAndRow($datCol, $r)->getValue());
            $loi = trim((string)$sheet->getCellByColumnAndRow($loiCol, $r)->getValue());

            if ($delivery === '' || $dat === '' || $loi === '') {
                $rows[] = [
                    'row' => $r,
                    'mahang' => (string)$sheet->getCellByColumnAndRow($mahangCol, $r)->getValue(),
                    'mau' => (string)$sheet->getCellByColumnAndRow($mauCol, $r)->getValue(),
                    // 'mau' => (string)$sheet->getCellByColumnAndRow($mauCol, $r)->getCalculatedValue(),
                    'size' => (string)$sheet->getCellByColumnAndRow($sizeCol, $r)->getValue(),
                    'mat' => (string)$sheet->getCellByColumnAndRow($matCol, $r)->getValue(),
                    'ghichu' => (string)$sheet->getCellByColumnAndRow($ghichuCol, $r)->getValue(),
                    'sl_thuc' => (string)$sheet->getCellByColumnAndRow($slThucCol, $r)->getValue(),
                    'delivery' => $delivery,
                    'dat' => $dat,
                    'loi' => $loi,
                ];
            }
        }
    }

    return response()->json($rows);
}

    // Lưu phiếu nhập sang sheet PHIEU_NHAP
public function store(Request $request)
{
    $data = $request->validate([
        'ps' => 'required|string',
        'row_kd' => 'required|integer',
        'dat' => 'required|integer|min:0',
        'loi' => 'required|integer|min:0',
    ]);

    // ✅ Đường dẫn file gốc
    $sourceFile = $this->filePath;

    // ✅ Tạo file đích (bản fixed)
    $fixedFile = storage_path('app/public/theodoi_fixed.xlsx');

    // ✅ Chỉ load sheet "PHIEU_NHAP"
    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
    $reader->setLoadSheetsOnly(['PHIEU_NHAP']);
    $reader->setReadDataOnly(false);
    $spreadsheet = $reader->load($fixedFile);

    $sheet = $spreadsheet->getSheetByName('PHIEU_NHAP');
    if (!$sheet) {
        return back()->with('error', 'Không tìm thấy sheet PHIEU_NHAP trong file.');
    }

    // ✅ Ghi dữ liệu vào dòng tiếp theo (append)
    $nextRow = $sheet->getHighestRow() + 1;
    $sheet->setCellValue("A{$nextRow}", now()->format('d/m/Y'));
    $sheet->setCellValue("B{$nextRow}", $data['ps']);
    $sheet->setCellValue("C{$nextRow}", $data['row_kd']);
    $sheet->setCellValue("D{$nextRow}", $data['dat']);
    $sheet->setCellValue("E{$nextRow}", $data['loi']);
    $sheet->setCellValue("F{$nextRow}", 'CHUA_DUYET');
    $sheet->setCellValue("G{$nextRow}", Auth::user()->name ?? 'unknown');

    // ✅ Lưu sang file FIXED (không ghi đè file gốc)
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save($fixedFile);
    

    return back()->with('success', "Đã thêm 1 dòng mới vào sheet PHIEU_NHAP trong file fixed!");
}
public function viewFixedData(Request $request)
{
    $ps = trim($request->query('ps'));
    if (!$ps) return response()->json([]);

    $fixedFile = storage_path('app/public/theodoi_fixed.xlsx');

    if (!file_exists($fixedFile)) {
        return response()->json(['error' => 'Không tìm thấy file fixed.'], 404);
    }

    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
    $reader->setLoadSheetsOnly(['PHIEU_NHAP']);
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($fixedFile);

    $sheet = $spreadsheet->getSheetByName('PHIEU_NHAP');
    if (!$sheet) return response()->json([]);

    $highestRow = $sheet->getHighestRow();
    $rows = [];

    // Giả sử cấu trúc cột: A=Ngày, B=PS, C=Row_KD, D=Đạt, E=Lỗi, F=Trạng thái, G=Người nhập
    for ($r = 2; $r <= $highestRow; $r++) {
        $psVal = trim((string)$sheet->getCell("B$r")->getValue());
        if ($psVal === $ps) {
            $rows[] = [
                'ngay' => (string)$sheet->getCell("A$r")->getValue(),
                'ps' => $psVal,
                'row_kd' => (string)$sheet->getCell("C$r")->getValue(),
                'dat' => (string)$sheet->getCell("D$r")->getValue(),
                'loi' => (string)$sheet->getCell("E$r")->getValue(),
                'trangthai' => (string)$sheet->getCell("F$r")->getValue(),
                'nguoitao' => (string)$sheet->getCell("G$r")->getValue(),
            ];
        }
    }

    return response()->json($rows);
}





    // Helper: lấy danh sách mã P/S duy nhất
    protected function getDistinctPs()
    {
        $reader = new Xlsx();
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($this->filePath);
        $sheet = $spreadsheet->getSheetByName('KINHDOANH');
        if (!$sheet) return [];

        $startRow = 5;
        $psCol = 4;
        $highestRow = $sheet->getHighestRow();

        $arr = [];
        for ($r = $startRow; $r <= $highestRow; $r++) {
            $val = trim((string)$sheet->getCellByColumnAndRow($psCol, $r)->getValue());
            if ($val !== '') $arr[] = $val;
        }

        $arr = array_values(array_unique($arr));
        sort($arr);
        return $arr;
    }
}
