<?php

namespace App\Http\Controllers;

use App\Models\InternalProductionOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class InternalProductionOrderController extends Controller
{
    private const SPREADSHEET_ID = '1nd9sOnKCq-hDf44Uo7_002qT7zoznrx7mcQoRw0oEcs';
    private const SHEET_NAME = 'LENH_SAN_XUAT';

    public function index()
    {
        return view('client.internal-production-orders');
    }

    public function data(Request $request)
    {
        $query = InternalProductionOrder::query()->where('is_active', true);
        $keyword = trim((string) $request->query('keyword', ''));
        $status = trim((string) $request->query('status', ''));
        $productionOrder = trim((string) $request->query('production_order', ''));

        if ($productionOrder !== '') {
            $query->where('production_order', $productionOrder);
        }

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('production_order', 'like', '%' . $keyword . '%')
                    ->orWhere('purchase_order', 'like', '%' . $keyword . '%')
                    ->orWhere('customer', 'like', '%' . $keyword . '%')
                    ->orWhere('item_code', 'like', '%' . $keyword . '%')
                    ->orWhere('specification', 'like', '%' . $keyword . '%')
                    ->orWhere('description', 'like', '%' . $keyword . '%')
                    ->orWhere('tracking_staff', 'like', '%' . $keyword . '%')
                    ->orWhere('size', 'like', '%' . $keyword . '%')
                    ->orWhere('color', 'like', '%' . $keyword . '%')
                    ->orWhere('unit', 'like', '%' . $keyword . '%')
                    ->orWhere('location', 'like', '%' . $keyword . '%')
                    ->orWhere('delivery_place', 'like', '%' . $keyword . '%')
                    ->orWhere('status', 'like', '%' . $keyword . '%')
                    ->orWhere('order_quantity', 'like', '%' . $keyword . '%')
                    ->orWhere('received_date', 'like', '%' . $keyword . '%')
                    ->orWhere('promised_date', 'like', '%' . $keyword . '%')
                    ->orWhere('customer_requested_date', 'like', '%' . $keyword . '%')
                    ->orWhere('source_row', 'like', '%' . $keyword . '%')
                    ->orWhereRaw('CAST(raw_data AS CHAR) LIKE ?', ['%' . $keyword . '%']);
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('promised_date', '>=', $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('promised_date', '<=', $request->query('to_date'));
        }

        $summaryQuery = clone $query;
        $limit = min(max((int) $request->query('limit', 500), 1), 2000);
        if ($productionOrder !== '') {
            $query->orderBy('source_row');
        } else {
            $query->orderByRaw('promised_date IS NULL')
                ->orderBy('promised_date')
                ->orderByDesc('production_order');
        }

        $rows = $query->limit($limit)->get();

        return response()->json([
            'data' => $rows,
            'summary' => [
                'order_count' => (clone $summaryQuery)->distinct()->count('production_order'),
                'variant_count' => (clone $summaryQuery)->count(),
                'total_quantity' => (float) (clone $summaryQuery)->sum('order_quantity'),
                'late_count' => (clone $summaryQuery)->where('status', 'late')->count(),
                'due_count' => (clone $summaryQuery)->where('status', 'due')->count(),
                'customer_count' => (clone $summaryQuery)->whereNotNull('customer')->distinct('customer')->count('customer'),
                'last_synced_at' => InternalProductionOrder::query()->max('updated_at'),
            ],
            'source' => [
                'spreadsheet_id' => self::SPREADSHEET_ID,
                'sheet' => self::SHEET_NAME,
                'mode' => 'read_only',
            ],
        ]);
    }

    public function sync()
    {
        $url = sprintf(
            'https://docs.google.com/spreadsheets/d/%s/gviz/tq?tqx=out:csv&sheet=%s',
            self::SPREADSHEET_ID,
            rawurlencode(self::SHEET_NAME)
        );

        $response = Http::timeout(60)
            ->withOptions(['verify' => false])
            ->get($url);

        if (!$response->successful()) {
            return response()->json([
                'message' => 'Không đọc được Google Sheet. Kiểm tra quyền chia sẻ của file.',
            ], 502);
        }

        $rows = $this->parseCsv($response->body());
        if (count($rows) < 2) {
            return response()->json([
                'message' => 'Tab LENH_SAN_XUAT không có dữ liệu hợp lệ.',
            ], 422);
        }

        $headers = array_map([$this, 'normalizeHeader'], array_shift($rows));
        $batch = (string) Str::uuid();
        $activeKeys = [];
        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::connection('internal')->transaction(function () use ($rows, $headers, $batch, &$activeKeys, &$created, &$updated, &$skipped) {
            foreach ($rows as $index => $values) {
                $row = [];
                foreach ($headers as $column => $header) {
                    $row[$header] = trim((string) ($values[$column] ?? ''));
                }

                $productionOrder = $this->pick($row, ['lenh sx']);
                if ($productionOrder === '') {
                    $skipped++;
                    continue;
                }

                $itemCode = $this->pick($row, ['ma hang']);
                $size = $this->pick($row, ['size']);
                $color = $this->pick($row, ['color']);
                $description = $this->pick($row, ['mo ta ten nhan']);
                $rowKey = $this->rowKey($productionOrder, $itemCode, $size, $color, $description);
                $activeKeys[] = $rowKey;
                $promisedDate = $this->date($this->pick($row, ['ngay hen giao']));
                $customerDate = $this->date($this->pick($row, ['ngay khach hang yeu cau giao']));
                $targetDate = $customerDate ?: $promisedDate;
                $status = $this->status($targetDate);
                $existing = InternalProductionOrder::query()
                    ->where('row_key', $rowKey)
                    ->first();

                InternalProductionOrder::query()->updateOrCreate(
                    ['row_key' => $rowKey],
                    [
                        'production_order' => $productionOrder,
                        'purchase_order' => $this->pick($row, ['purchase order po']),
                        'tracking_staff' => $this->pick($row, ['nhan vien theo doi']),
                        'customer' => $this->pick($row, ['khach hang']),
                        'item_code' => $itemCode,
                        'specification' => $this->pick($row, ['quy cach']),
                        'description' => $description,
                        'size' => $size,
                        'color' => $color,
                        'unit' => $this->pick($row, ['dvt']),
                        'order_quantity' => $this->number($this->pick($row, ['so luong dat'])),
                        'location' => $this->pick($row, ['vi tri']),
                        'received_date' => $this->dateValue($this->pick($row, ['ngay nhan'])),
                        'promised_date' => $this->dateValue($this->pick($row, ['ngay hen giao'])),
                        'customer_requested_date' => $this->dateValue($this->pick($row, ['ngay khach hang yeu cau giao'])),
                        'delivery_place' => $this->pick($row, ['noi giao']),
                        'status' => $status,
                        'source_row' => $index + 2,
                        'raw_data' => $row,
                        'sync_batch' => $batch,
                        'is_active' => true,
                    ]
                );

                $existing ? $updated++ : $created++;
            }

            $archiveQuery = InternalProductionOrder::query()->where('is_active', true);
            if ($activeKeys) {
                $archiveQuery->whereNotIn('row_key', array_unique($activeKeys));
            }
            $archiveQuery->update(['is_active' => false]);
        });

        return response()->json([
            'message' => 'Đã đồng bộ lệnh sản xuất từ Google Sheet.',
            'data' => [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'active_variants' => count(array_unique($activeKeys)),
                'sheet' => self::SHEET_NAME,
            ],
        ]);
    }

    private function parseCsv(string $contents): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream)) !== false) {
            $rows[] = $row;
        }
        fclose($stream);
        return $rows;
    }

    private function normalizeHeader($value): string
    {
        $value = preg_replace('/[^a-z0-9]+/', ' ', Str::ascii(mb_strtolower(trim((string) $value))));
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    private function pick(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            $key = $this->normalizeHeader($key);
            if (array_key_exists($key, $row)) {
                return trim((string) $row[$key]);
            }
        }
        return '';
    }

    private function number($value): float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }
        $value = str_replace(['.', ' '], '', $value);
        $value = str_replace(',', '.', $value);
        return is_numeric($value) ? (float) $value : 0;
    }

    private function date($value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->startOfDay();
            } catch (\Throwable $e) {
            }
        }
        return null;
    }

    private function dateValue($value): ?string
    {
        $date = $this->date($value);
        return $date ? $date->format('Y-m-d') : null;
    }

    private function status(?Carbon $targetDate): string
    {
        if (!$targetDate) {
            return 'pending';
        }
        if ($targetDate->isBefore(now()->startOfDay())) {
            return 'late';
        }
        if ($targetDate->lte(now()->addDays(3)->startOfDay())) {
            return 'due';
        }
        return 'scheduled';
    }

    private function rowKey($productionOrder, $itemCode, $size, $color, $description): string
    {
        $parts = [$productionOrder, $itemCode, $size, $color, $description];
        $parts = array_map(function ($value) {
            return mb_strtoupper(trim((string) $value));
        }, $parts);

        return hash('sha256', implode('|', $parts));
    }
}
