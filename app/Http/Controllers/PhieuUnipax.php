<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PhieuUnipax extends Controller
{
    protected $filePath;

    public function __construct()
    {
        $this->filePath = storage_path('app/public/theodoi.xlsx'); // đường dẫn file excel
    }

    // trang form (load danh sách PS để select)
    public function index()
    {
        $psList = $this->getDistinctPs();
        return view('client.toolunipax', compact('psList'));
    }

    // API AJAX: lấy các row trong KINHDOANH với PS = ? và thiếu Delivery/Dat/Loi
    public function getRows(Request $request)
    {
        $ps = $request->query('ps');
        if (!$ps) return response()->json([]);

        // load file excel
        $spreadsheet = IOFactory::load($this->filePath);
        $sheet = $spreadsheet->getSheetByName('KINHDOANH');
        $highestRow = $sheet->getHighestRow();

        $startRow = 5; // chỉnh nếu header bắt đầu ở dòng khác
        $psCol = 4; $mahangCol = 3; $slThucCol = 11; $deliveryCol = 12; $datCol = 13; $loiCol = 14;

        $rows = [];
        for ($r = $startRow; $r <= $highestRow; $r++) {
            $psVal = trim((string)$sheet->getCellByColumnAndRow($psCol, $r)->getValue());
            if ($psVal === trim($ps)) {
                $delivery = trim((string)$sheet->getCellByColumnAndRow($deliveryCol, $r)->getValue());
                $dat = trim((string)$sheet->getCellByColumnAndRow($datCol, $r)->getValue());
                $loi = trim((string)$sheet->getCellByColumnAndRow($loiCol, $r)->getValue());

                // nếu thiếu 1 trong 3 trường thì đưa vô danh sách cần nhập
                if ($delivery === '' || $dat === '' || $loi === '') {
                    $rows[] = [
                        'row' => $r,
                        'mahang' => (string)$sheet->getCellByColumnAndRow($mahangCol, $r)->getValue(),
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

    // lưu phiếu vào sheet PHIEU_NHAP (không cập nhật KINHDOANH — chờ kinh doanh duyệt)
    public function store(Request $request)
    {
        $data = $request->validate([
            'ps' => 'required|string',
            'row_kd' => 'required|integer',
            'dat' => 'required|integer',
            'loi' => 'required|integer',
        ]);

        $spreadsheet = IOFactory::load($this->filePath);
        $sheet = $spreadsheet->getSheetByName('PHIEU_NHAP');

        $nextRow = $sheet->getHighestRow() + 1;
        $sheet->setCellValue("A{$nextRow}", now()->format('d/m/Y'));
        $sheet->setCellValue("B{$nextRow}", $data['ps']);
        $sheet->setCellValue("C{$nextRow}", $data['row_kd']);
        $sheet->setCellValue("D{$nextRow}", $data['dat']);
        $sheet->setCellValue("E{$nextRow}", $data['loi']);
        $sheet->setCellValue("F{$nextRow}", "CHUA_DUYET");
        $sheet->setCellValue("G{$nextRow}", "");

        $writer = new Xlsx($spreadsheet);
        $writer->save($this->filePath);

        return back()->with('success', 'Đã lưu phiếu nhập vào Excel!');
    }

    // helper: lấy danh sách PS hiện có (unique)
    protected function getDistinctPs()
    {
        $spreadsheet = IOFactory::load($this->filePath);
        $sheet = $spreadsheet->getSheetByName('KINHDOANH');
        $highestRow = $sheet->getHighestRow();

        $startRow = 5;
        $psCol = 4;
        $arr = [];
        for ($r = $startRow; $r <= $highestRow; $r++) {
            $v = trim((string)$sheet->getCellByColumnAndRow($psCol, $r)->getValue());
            if ($v !== '') $arr[] = $v;
        }
        $arr = array_values(array_unique($arr));
        sort($arr);
        return $arr;
    }
}

