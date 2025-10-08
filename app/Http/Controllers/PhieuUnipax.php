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
        $this->filePath = storage_path('app/public/theodoi.xlsx');
    }
    protected function excelDate($value)
{
    if (is_numeric($value)) {
        // Excel date serial -> PHP date
        return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('d/m/Y');
    }
    return $value; // nếu không phải số thì giữ nguyên (đề phòng đã là chuỗi)
}

    public function index()
    {
        $psList = $this->getDistinctPs();
        return view('client.toolunipax', compact('psList'));
    }

    // ✅ AJAX: Lấy danh sách dòng trong sheet KINHDOANH thiếu Delivery/Đạt/Lỗi
    public function getRows(Request $request)
    {
        $ps = trim($request->query('ps'));
        if (!$ps) return response()->json([]);

        $cacheFile = storage_path('app/cache/kinhdoanh.json');
        if (!file_exists($cacheFile)) {
            return response()->json(['error' => 'Cache chưa được tạo.'], 400);
        }

        $rows = json_decode(file_get_contents($cacheFile), true);
        if (!$rows) return response()->json([]);

        // ✅ Lọc theo P/S và các dòng chưa có Delivery/Đạt/Lỗi
        $result = array_filter($rows, function ($row) use ($ps) {
            return $row['ps'] === $ps &&
                // ($row['delivery'] === '' || $row['dat'] === '' || $row['loi'] === '');
                ($row['delivery'] === '' || ($row['dat'] === '' && $row['loi'] === ''));
        });

        // ✅ Trả lại toàn bộ dữ liệu, bao gồm cột mới
        return response()->json(array_values($result));
    }

    // ✅ Lưu phiếu nhập sang sheet PHIEU_NHAP
    public function store(Request $request)
{
    $data = $request->validate([
        'ps' => 'required|string',
        'row_kd' => 'required|integer',
        'dat' => 'required|integer|min:0',
        'loi' => 'required|integer|min:0',
        'ghichu' => 'nullable|string|max:255',
    ]);

    $ps = $data['ps'];
    $rowKd = (int) $data['row_kd'];
    $user = Auth::user()->name ?? 'unknown';

    // ✅ Đọc cache KINHDOANH
    $cacheFile = storage_path('app/cache/kinhdoanh.json');
    if (!file_exists($cacheFile)) {
        return back()->with('error', '❌ Cache chưa được tạo. Hãy bấm "Làm mới dữ liệu" trước.');
    }

    $rows = json_decode(file_get_contents($cacheFile), true);
    $found = collect($rows)->first(fn($r) => $r['ps'] === $ps && (int)$r['row'] === $rowKd);

    if (!$found) {
        return back()->with('error', "Không tìm thấy dòng $rowKd của P/S $ps trong cache.");
    }

    // ✅ Mở file fixed
    $fixedFile = storage_path('app/public/theodoi_fixed.xlsx');
    if (!file_exists($fixedFile)) {
        return back()->with('error', 'Không tìm thấy file theodoi_fixed.xlsx.');
    }

    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
    $reader->setLoadSheetsOnly(['PHIEU_NHAP']);
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($fixedFile);
    $sheet = $spreadsheet->getSheetByName('PHIEU_NHAP');

    if (!$sheet) {
        return back()->with('error', 'Không tìm thấy sheet PHIEU_NHAP trong file fixed.');
    }

    // ✅ Ghi dữ liệu vào dòng kế tiếp
    $nextRow = $sheet->getHighestRow() + 1;

    // --- Ghi dữ liệu người nhập ---
    $sheet->setCellValue("N{$nextRow}", now()->format('d/m/Y')); // Ngày nhập
    $sheet->setCellValue("D{$nextRow}", $ps);
    $sheet->setCellValue("A{$nextRow}", $rowKd);
    $sheet->setCellValue("J{$nextRow}", $data['dat']);
    $sheet->setCellValue("K{$nextRow}", $data['loi']);
    $sheet->setCellValue("O{$nextRow}", 'CHUA_DUYET');
    // $sheet->setCellValue("{$nextRow}", $user);

    // --- Ghi dữ liệu từ cache KINHDOANH ---
    $sheet->setCellValue("B{$nextRow}", $found['ngayxuat'] ?? '');
    $sheet->setCellValue("C{$nextRow}", $found['mahang'] ?? '');
    $sheet->setCellValue("F{$nextRow}", $found['mau'] ?? '');
    $sheet->setCellValue("E{$nextRow}", $found['size'] ?? '');
    $sheet->setCellValue("M{$nextRow}", $found['mat'] ?? '');
    $sheet->setCellValue("G{$nextRow}", $found['logo'] ?? '');
    $sheet->setCellValue("I{$nextRow}", $found['soluongdonhang'] ?? '');
    $sheet->setCellValue("P{$nextRow}", $found['sl_thuc'] ?? '');

    // --- Ghi ghi chú từ người nhập form ---
    $sheet->setCellValue("L{$nextRow}", $data['ghichu'] ?? '');

    // ✅ Lưu lại file
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save($fixedFile);

    return back()->with('success', "✅ Đã lưu phiếu cho P/S {$ps}, dòng {$rowKd} (đầy đủ thông tin).");
}
    



    // ✅ Làm mới cache từ sheet KINHDOANH
    public function refreshCache()
    {
        $reader = new Xlsx();
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly(['KINHDOANH']);
        $spreadsheet = $reader->load($this->filePath);

        $sheet = $spreadsheet->getSheetByName('KINHDOANH');
        if (!$sheet) return back()->with('error', 'Không tìm thấy sheet KINHDOANH');

        $startRow = 5;
        
        $ngayxuatCol = 2;
        $mahangCol = 3;
        $psCol = 4;
        $sizeCol = 5;
        $mauCol = 6;
        $logoCol = 7;
        $soluongdonhangCol = 10;
        $slThucCol = 11;
        $deliveryCol = 12;
        $datCol = 13;
        $loiCol = 14;
        $matCol = 16;
        $ghichuCol = 17;
        $highestRow = $sheet->getHighestRow();

        $rows = [];
        for ($r = $startRow; $r <= $highestRow; $r++) {
            $ps = trim((string)$sheet->getCellByColumnAndRow($psCol, $r)->getValue());
            if ($ps === '') continue;

            $rows[] = [
                'row' => $r,
                'ps' => $ps,
                // 'ngayxuat' => (string)$sheet->getCellByColumnAndRow($ngayxuatCol, $r)->getValue(),
                'ngayxuat'=> $this->excelDate($sheet->getCellByColumnAndRow($ngayxuatCol, $r)->getValue()),

                'mahang' => (string)$sheet->getCellByColumnAndRow($mahangCol, $r)->getValue(),
                'logo' => (string)$sheet->getCellByColumnAndRow($logoCol, $r)->getValue(),
                'mau' => (string)$sheet->getCellByColumnAndRow($mauCol, $r)->getValue(),
                'size' => (string)$sheet->getCellByColumnAndRow($sizeCol, $r)->getValue(),
                'soluongdonhang' => (string)$sheet->getCellByColumnAndRow($soluongdonhangCol, $r)->getValue(),
                'sl_thuc' => (string)$sheet->getCellByColumnAndRow($slThucCol, $r)->getValue(),
                'delivery' => (string)$sheet->getCellByColumnAndRow($deliveryCol, $r)->getValue(),
                'dat' => (string)$sheet->getCellByColumnAndRow($datCol, $r)->getValue(),
                'loi' => (string)$sheet->getCellByColumnAndRow($loiCol, $r)->getValue(),
                'mat' => (string)$sheet->getCellByColumnAndRow($matCol, $r)->getValue(),
                'ghichu' => (string)$sheet->getCellByColumnAndRow($ghichuCol, $r)->getValue(),
            ];
        }

        $cacheDir = storage_path('app/cache');
        if (!file_exists($cacheDir)) mkdir($cacheDir, 0777, true);
        file_put_contents("$cacheDir/kinhdoanh.json", json_encode($rows, JSON_UNESCAPED_UNICODE));

        return back()->with('success', '✅ Đã làm mới cache dữ liệu từ KINHDOANH!');
    }

    // ✅ Xem toàn bộ phiếu nhập trong file fixed
   public function viewAllFixed()
{
    $fixedFile = storage_path('app/public/theodoi_fixed.xlsx');
    if (!file_exists($fixedFile)) {
        return response()->json(['error' => 'Không tìm thấy file fixed.'], 404);
    }

    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
    $reader->setLoadSheetsOnly(['PHIEU_NHAP']);
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($fixedFile);
    $sheet = $spreadsheet->getSheetByName('PHIEU_NHAP');
    if (!$sheet) {
        return response()->json([]);
    }

    $highestRow = $sheet->getHighestRow();
    $rows = [];

    for ($r = 2; $r <= $highestRow; $r++) {
        $rows[] = [
            'row_kd'           => (string)$sheet->getCell("A$r")->getValue(),
            'ngayxuat' => $this->excelDate($sheet->getCell("B$r")->getValue()),
            'mahang'           => (string)$sheet->getCell("C$r")->getValue(),
            'size'             => (string)$sheet->getCell("E$r")->getValue(),
            'mau'              => (string)$sheet->getCell("F$r")->getValue(),
            'logo'             => (string)$sheet->getCell("G$r")->getValue(),
            'soluongdonhang'   => (string)$sheet->getCell("I$r")->getValue(),
            'ps'               => (string)$sheet->getCell("D$r")->getValue(),
            'dat'              => (string)$sheet->getCell("J$r")->getValue(),
            'loi'              => (string)$sheet->getCell("K$r")->getValue(),
            'ghichu'           => (string)$sheet->getCell("L$r")->getValue(),
            'mat'              => (string)$sheet->getCell("M$r")->getValue(),
            'ngaynhap'         => (string)$sheet->getCell("N$r")->getValue(),
            'trangthai'        => (string)$sheet->getCell("O$r")->getValue(),
            'sl_thuc'          => (string)$sheet->getCell("P$r")->getValue(),
        ];
    }

    return response()->json($rows);
}


    // ✅ Helper: lấy danh sách mã P/S duy nhất
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
