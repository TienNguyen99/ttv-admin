<?php

namespace App\Http\Controllers;

use App\Models\InternalOrderTrackingRow;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class InternalOrderTrackingController extends Controller
{
    public function index()
    {
        return view('client.internal-order-tracking');
    }

    public function data(Request $request)
    {
        $query = InternalOrderTrackingRow::query()->where('is_active', true);
        $sheet = strtoupper(trim((string) $request->query('sheet', 'A')));
        $keyword = trim((string) $request->query('keyword', ''));
        $status = trim((string) $request->query('status', ''));

        if (in_array($sheet, ['A', 'B'], true)) {
            $query->where('sheet_code', $sheet);
        }

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('item_code', 'like', '%' . $keyword . '%')
                    ->orWhere('order_number', 'like', '%' . $keyword . '%')
                    ->orWhere('voucher_number', 'like', '%' . $keyword . '%')
                    ->orWhere('fabric_color', 'like', '%' . $keyword . '%')
                    ->orWhere('logo_color', 'like', '%' . $keyword . '%')
                    ->orWhere('size', 'like', '%' . $keyword . '%');
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('delivery_date', '>=', $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('delivery_date', '<=', $request->query('to_date'));
        }

        $summaryQuery = clone $query;
        $limit = min(max((int) $request->query('limit', 500), 1), 2000);
        $rows = $query
            ->orderByRaw('delivery_date IS NULL')
            ->orderBy('delivery_date')
            ->orderBy('item_code')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $rows,
            'summary' => [
                'row_count' => (clone $summaryQuery)->count(),
                'order_quantity' => (float) (clone $summaryQuery)->sum('order_quantity'),
                'received_quantity' => (float) (clone $summaryQuery)->sum('received_quantity'),
                'remaining_quantity' => (float) (clone $summaryQuery)->sum('remaining_quantity'),
                'late_count' => (clone $summaryQuery)
                    ->whereNotNull('delivery_date')
                    ->whereDate('delivery_date', '<', now()->format('Y-m-d'))
                    ->where('remaining_quantity', '>', 0)
                    ->count(),
            ],
        ]);
    }

    public function import(Request $request)
    {
        $data = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:20480',
        ]);

        $spreadsheet = IOFactory::load($data['file']->getRealPath());
        $sheets = $this->resolveOrderSheets($spreadsheet->getAllSheets());

        if (count($sheets) < 2) {
            return response()->json([
                'message' => 'File cần có ít nhất 2 sheet để đồng bộ Sheet A và Sheet B.',
            ], 422);
        }

        $batch = (string) Str::uuid();
        $result = ['created' => 0, 'updated' => 0, 'archived' => 0, 'sheets' => []];

        DB::connection('internal')->transaction(function () use ($sheets, $batch, &$result) {
            foreach ($sheets as $index => $worksheet) {
                $sheetCode = $index === 0 ? 'A' : 'B';
                $parsed = $this->parseWorksheet($worksheet, $sheetCode, $batch);
                $activeKeys = [];

                foreach ($parsed as $row) {
                    $activeKeys[] = $row['row_key'];
                    $existing = InternalOrderTrackingRow::query()
                        ->where('sheet_code', $sheetCode)
                        ->where('row_key', $row['row_key'])
                        ->first();

                    InternalOrderTrackingRow::query()->updateOrCreate(
                        ['sheet_code' => $sheetCode, 'row_key' => $row['row_key']],
                        $row
                    );

                    $existing ? $result['updated']++ : $result['created']++;
                }

                $archiveQuery = InternalOrderTrackingRow::query()
                    ->where('sheet_code', $sheetCode)
                    ->where('is_active', true);

                if ($activeKeys) {
                    $archiveQuery->whereNotIn('row_key', $activeKeys);
                }

                $archived = $archiveQuery->update(['is_active' => false]);
                $result['archived'] += $archived;
                $result['sheets'][$sheetCode] = [
                    'name' => $worksheet->getTitle(),
                    'rows' => count($parsed),
                ];
            }
        });

        return response()->json([
            'message' => 'Đã đồng bộ file Excel vào database nội bộ.',
            'data' => $result,
        ]);
    }

    private function resolveOrderSheets(array $sheets): array
    {
        $resolved = [];
        foreach (['A', 'B'] as $code) {
            foreach ($sheets as $sheet) {
                $title = strtoupper(trim($sheet->getTitle()));
                if ($title === $code || $title === 'SHEET ' . $code || $title === 'SHEET_' . $code) {
                    $resolved[$code] = $sheet;
                    break;
                }
            }
        }

        if (isset($resolved['A'], $resolved['B'])) {
            return [$resolved['A'], $resolved['B']];
        }

        return array_slice($sheets, 0, 2);
    }

    private function parseWorksheet($worksheet, string $sheetCode, string $batch): array
    {
        $highestRow = $worksheet->getHighestDataRow();
        $highestColumn = $worksheet->getHighestDataColumn();
        $matrix = $worksheet->rangeToArray("A1:{$highestColumn}{$highestRow}", null, true, true, false);
        $this->expandMergedHeaders($worksheet, $matrix);
        $headerEnd = $this->detectHeaderEnd($matrix);
        $headers = $this->buildHeaders($matrix, $headerEnd);
        $rows = [];

        for ($rowIndex = $headerEnd + 1; $rowIndex < count($matrix); $rowIndex++) {
            $values = $matrix[$rowIndex];
            $normalized = $this->normalizeRow($headers, $values);

            if (!$this->hasOrderData($normalized)) {
                continue;
            }

            $orderQuantity = $this->number($this->pick($normalized, ['so luong dat hang', 'quantity order', 'quantityorder']));
            $quantity = $this->number($this->pick($normalized, ['quantity']));
            $quantityFront = $this->number($this->pick($normalized, ['quantity front', 'front']));
            $quantityBack = $this->number($this->pick($normalized, ['quantity back', 'back']));
            $received = $this->number($this->pick($normalized, ['so luong nhan set', 'received quantity', 'so luong nhan']));
            $frontPass = $this->number($this->pick($normalized, ['so luong mat truoc dat', 'front dat', 'front pass', 'kinh doanh dat']));
            $frontFail = $this->number($this->pick($normalized, ['so luong mat truoc loi', 'front loi', 'front fail', 'kinh doanh loi']));
            $backPass = $this->number($this->pick($normalized, ['so luong mat sau dat', 'back dat', 'back pass']));
            $backFail = $this->number($this->pick($normalized, ['so luong mat sau loi', 'back loi', 'back fail']));

            if ($received <= 0) {
                $received = max($frontPass + $frontFail, $backPass + $backFail, $quantity);
            }

            $remaining = max(0, $orderQuantity - $received);
            $deliveryDate = $this->date($this->pick($normalized, ['delivery date', 'ngay giao hang']));
            $status = $remaining <= 0 && $orderQuantity > 0
                ? 'completed'
                : ($deliveryDate && $deliveryDate->isBefore(now()->startOfDay()) ? 'late' : ($received > 0 ? 'partial' : 'pending'));

            $identity = [
                $this->text($this->pick($normalized, ['ma hang'])),
                $this->text($this->pick($normalized, ['ps sub', 'don hang'])),
                $this->text($this->pick($normalized, ['size'])),
                $this->text($this->pick($normalized, ['fabric color', 'mau vai'])),
                $this->text($this->pick($normalized, ['logo color', 'mau in'])),
                $this->text($this->pick($normalized, ['so phieu'])),
            ];

            $rows[] = [
                'source_sheet' => $worksheet->getTitle(),
                'row_key' => hash('sha256', implode('|', array_map('mb_strtoupper', $identity))),
                'source_row' => $rowIndex + 1,
                'sequence_no' => $this->text($this->pick($normalized, ['stt'])),
                'export_date' => $this->dateValue($this->pick($normalized, ['export date', 'ngay xuat'])),
                'item_code' => $identity[0],
                'order_number' => $identity[1],
                'size' => $identity[2],
                'fabric_color' => $identity[3],
                'logo_color' => $identity[4],
                'panel_out_date' => $this->dateValue($this->pick($normalized, ['date out panel', 'ngay gui panel'])),
                'voucher_number' => $identity[5],
                'order_quantity' => $orderQuantity,
                'quantity' => $quantity,
                'quantity_front' => $quantityFront,
                'quantity_back' => $quantityBack,
                'received_quantity' => $received,
                'delivery_date' => $deliveryDate ? $deliveryDate->format('Y-m-d') : null,
                'front_pass' => $frontPass,
                'front_fail' => $frontFail,
                'back_pass' => $backPass,
                'back_fail' => $backFail,
                'remaining_quantity' => $remaining,
                'status' => $status,
                'note' => $this->text($this->pick($normalized, ['ghi chu'])),
                'extra_data' => $normalized,
                'import_batch' => $batch,
                'is_active' => true,
            ];
        }

        return $rows;
    }

    private function detectHeaderEnd(array $matrix): int
    {
        $candidate = 0;
        $keywords = [
            'stt', 'export date', 'ma hang', 'ps sub', 'don hang', 'size',
            'fabric color', 'logo color', 'date out panel', 'so phieu',
            'quantity', 'delivery date', 'ghi chu', 'so luong',
        ];

        foreach (array_slice($matrix, 0, 12) as $index => $row) {
            $text = $this->normalize(implode(' ', array_filter(array_map([$this, 'text'], $row))));
            $score = 0;
            foreach ($keywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    $score++;
                }
            }
            if ($score >= 2) {
                $candidate = $index;
            }
        }

        return $candidate;
    }

    private function expandMergedHeaders($worksheet, array &$matrix): void
    {
        foreach ($worksheet->getMergeCells() as $range) {
            [$start, $end] = Coordinate::rangeBoundaries($range);
            $startColumn = $start[0] - 1;
            $startRow = $start[1] - 1;
            $endColumn = $end[0] - 1;
            $endRow = $end[1] - 1;

            if ($startRow > 11 || !isset($matrix[$startRow][$startColumn])) {
                continue;
            }

            $value = $matrix[$startRow][$startColumn];
            for ($row = $startRow; $row <= min($endRow, 11); $row++) {
                for ($column = $startColumn; $column <= $endColumn; $column++) {
                    if (($matrix[$row][$column] ?? null) === null || $matrix[$row][$column] === '') {
                        $matrix[$row][$column] = $value;
                    }
                }
            }
        }
    }

    private function buildHeaders(array $matrix, int $headerEnd): array
    {
        $headers = [];
        $columnCount = count($matrix[0] ?? []);

        for ($column = 0; $column < $columnCount; $column++) {
            $parts = [];
            for ($row = 0; $row <= $headerEnd; $row++) {
                $value = $this->normalize($this->text($matrix[$row][$column] ?? ''));
                if ($value !== '' && !in_array($value, $parts, true)) {
                    $parts[] = $value;
                }
            }
            $headers[$column] = implode(' ', $parts) ?: 'column_' . ($column + 1);
        }

        return $headers;
    }

    private function normalizeRow(array $headers, array $values): array
    {
        $row = [];
        foreach ($headers as $column => $header) {
            $row[$header] = $values[$column] ?? null;
        }
        return $row;
    }

    private function hasOrderData(array $row): bool
    {
        return $this->text($this->pick($row, ['ma hang'])) !== ''
            || $this->text($this->pick($row, ['ps sub', 'don hang'])) !== '';
    }

    private function pick(array $row, array $needles)
    {
        $normalizedNeedles = array_map(function ($needle) {
            return $this->normalize($needle);
        }, $needles);

        foreach ($normalizedNeedles as $needle) {
            if (array_key_exists($needle, $row)) {
                return $row[$needle];
            }
        }

        foreach ($normalizedNeedles as $needle) {
            foreach ($row as $header => $value) {
                if (strpos($header, $needle) !== false) {
                    return $value;
                }
            }
        }
        return null;
    }

    private function normalize(string $value): string
    {
        $value = preg_replace('/[^a-z0-9]+/', ' ', Str::ascii(mb_strtolower($value)));
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    private function text($value): string
    {
        return trim((string) $value);
    }

    private function number($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        $value = preg_replace('/[^\d,.-]/', '', $this->text($value));
        $value = str_replace(',', '', $value);
        return is_numeric($value) ? (float) $value : 0;
    }

    private function date($value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject($value));
        }
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'm/d/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, trim((string) $value))->startOfDay();
            } catch (\Throwable $e) {
            }
        }
        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function dateValue($value): ?string
    {
        $date = $this->date($value);
        return $date ? $date->format('Y-m-d') : null;
    }
}
