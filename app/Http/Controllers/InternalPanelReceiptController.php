<?php

namespace App\Http\Controllers;

use App\Models\InternalOrderTrackingRow;
use App\Models\InternalPanelReceipt;
use App\Models\InternalPanelReceiptLine;
use App\Services\InternalDocumentNumber;
use App\Services\Panel\PanelRuleRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class InternalPanelReceiptController extends Controller
{
    private $numbers;
    private $panelRules;

    public function __construct(InternalDocumentNumber $numbers, PanelRuleRepository $panelRules)
    {
        $this->numbers = $numbers;
        $this->panelRules = $panelRules;
    }

    public function page()
    {
        return view('client.internal-panel-receipt');
    }

    public function searchOrders(Request $request)
    {
        $data = $request->validate([
            'keyword' => 'required|string|min:2|max:150',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);
        $keyword = trim($data['keyword']);
        $rows = InternalOrderTrackingRow::query()
            ->where('is_active', true)
            ->where(function ($query) use ($keyword) {
                $query->where('order_number', 'like', '%' . $keyword . '%')
                    ->orWhere('item_code', 'like', '%' . $keyword . '%')
                    ->orWhere('panel', 'like', '%' . $keyword . '%');
            })
            ->orderBy('order_number')
            ->orderBy('source_sheet')
            ->orderBy('source_row')
            ->limit((int) ($data['limit'] ?? 60))
            ->get();

        return response()->json(['data' => $rows->map(function (InternalOrderTrackingRow $row) {
            return $this->orderPayload($row);
        })->values()]);
    }

    public function previewImport(Request $request)
    {
        $data = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:20480',
        ], [
            'file.required' => 'Hãy chọn file phiếu hàng về.',
            'file.mimes' => 'Chỉ hỗ trợ file .xlsx, .xls hoặc .csv.',
        ]);

        $reader = IOFactory::createReaderForFile($data['file']->getRealPath());
        $reader->setReadDataOnly(true);
        $book = $reader->load($data['file']->getRealPath());
        $parsed = [];
        $errors = [];
        try {
            foreach ($book->getAllSheets() as $sheet) {
                $this->parseReceiptSheet($sheet, $parsed, $errors);
                if (count($parsed) + count($errors) > 5000) {
                    throw ValidationException::withMessages(['file' => 'File hàng về không được vượt quá 5.000 dòng.']);
                }
            }
        } finally {
            $book->disconnectWorksheets();
        }

        $matched = $this->matchImportedRows($parsed, $errors);

        return response()->json([
            'file_name' => $data['file']->getClientOriginalName(),
            'data' => $matched,
            'errors' => $errors,
            'summary' => [
                'source_rows' => count($parsed) + count($errors),
                'matched_rows' => count($matched),
                'error_rows' => count($errors),
                'total_quantity' => array_sum(array_column($matched, 'received_quantity_input')),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'received_date' => 'required|date_format:Y-m-d',
            'source_type' => 'nullable|in:MANUAL,IMPORT',
            'source_file_name' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:5000',
            'lines' => 'required|array|min:1|max:500',
            'lines.*.order_tracking_row_id' => 'required|integer|min:1',
            'lines.*.received_quantity' => 'required|numeric|gt:0|max:999999999999',
            'lines.*.note' => 'nullable|string|max:1000',
            'lines.*.source_row' => 'nullable|integer|min:1',
        ], [
            'lines.required' => 'Phiếu chưa có dòng hàng về.',
            'lines.min' => 'Phiếu chưa có dòng hàng về.',
            'lines.*.received_quantity.gt' => 'Số lượng hàng về phải lớn hơn 0.',
        ]);

        $ids = collect($data['lines'])->pluck('order_tracking_row_id')->map(function ($id) {
            return (int) $id;
        })->unique()->values();
        $orders = InternalOrderTrackingRow::query()->where('is_active', true)->whereIn('id', $ids)->get()->keyBy('id');
        if ($orders->count() !== $ids->count()) {
            throw ValidationException::withMessages(['lines' => 'Có dòng đơn hàng không còn tồn tại. Hãy tìm PS lại.']);
        }

        $receipt = DB::connection('internal')->transaction(function () use ($data, $orders, $ids) {
            $receipt = InternalPanelReceipt::create([
                'receipt_number' => $this->numbers->next('PHV'),
                'received_date' => $data['received_date'],
                'source_type' => $data['source_type'] ?? 'MANUAL',
                'source_file_name' => $data['source_file_name'] ?? null,
                'note' => trim((string) ($data['note'] ?? '')) ?: null,
                'created_by' => auth()->id(),
            ]);

            $total = 0.0;
            $now = now();
            $receiptLines = [];
            foreach ($data['lines'] as $line) {
                $order = $orders->get((int) $line['order_tracking_row_id']);
                $quantity = (float) $line['received_quantity'];
                $receiptLines[] = [
                    'receipt_id' => $receipt->id,
                    'order_tracking_row_id' => $order->id,
                    'ps_number' => $order->order_number ?: '-',
                    'item_code' => $order->item_code,
                    'panel' => $order->panel,
                    'received_quantity' => $quantity,
                    'note' => trim((string) ($line['note'] ?? '')) ?: null,
                    'source_row' => $line['source_row'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $total += $quantity;
            }

            InternalPanelReceiptLine::query()->insert($receiptLines);
            $receipt->update(['line_count' => count($data['lines']), 'total_quantity' => $total]);
            $this->recalculateOrders($ids->all());

            return $receipt;
        });

        return response()->json([
            'message' => 'Đã lưu phiếu hàng về ' . $receipt->receipt_number . '.',
            'data' => $this->receiptPayload($receipt),
            'print_url' => route('panel-receipts.print', ['receipt' => $receipt->id]),
        ], 201);
    }

    public function history(Request $request)
    {
        $data = $request->validate([
            'keyword' => 'nullable|string|max:150',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);
        $query = InternalPanelReceipt::query();
        $keyword = trim((string) ($data['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(function ($subQuery) use ($keyword) {
                $subQuery->where('receipt_number', 'like', '%' . $keyword . '%')
                    ->orWhere('source_file_name', 'like', '%' . $keyword . '%')
                    ->orWhereHas('lines', function ($lineQuery) use ($keyword) {
                        $lineQuery->where('ps_number', 'like', '%' . $keyword . '%')
                            ->orWhere('item_code', 'like', '%' . $keyword . '%')
                            ->orWhere('panel', 'like', '%' . $keyword . '%');
                    });
            });
        }
        if (!empty($data['date_from'])) {
            $query->whereDate('received_date', '>=', $data['date_from']);
        }
        if (!empty($data['date_to'])) {
            $query->whereDate('received_date', '<=', $data['date_to']);
        }
        $page = $query->orderByDesc('received_date')->orderByDesc('id')->paginate((int) ($data['per_page'] ?? 15));

        return response()->json([
            'data' => collect($page->items())->map(function (InternalPanelReceipt $receipt) {
                return $this->receiptPayload($receipt);
            })->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function show(InternalPanelReceipt $receipt)
    {
        $receipt->load(['lines' => function ($query) {
            $query->orderBy('id');
        }, 'lines.orderRow']);

        return response()->json([
            'data' => $this->receiptPayload($receipt),
            'lines' => $receipt->lines->map(function ($line) {
                return [
                    'id' => $line->id,
                    'ps_number' => $line->ps_number,
                    'item_code' => $line->item_code,
                    'panel' => $line->panel,
                    'size' => optional($line->orderRow)->size,
                    'fabric_color' => optional($line->orderRow)->fabric_color,
                    'logo_color' => optional($line->orderRow)->logo_color,
                    'received_quantity' => $line->received_quantity,
                    'note' => $line->note,
                ];
            })->values(),
        ]);
    }

    public function destroy(InternalPanelReceipt $receipt)
    {
        $orderIds = $receipt->lines()->whereNotNull('order_tracking_row_id')->pluck('order_tracking_row_id')->unique()->values()->all();
        $number = $receipt->receipt_number;
        DB::connection('internal')->transaction(function () use ($receipt, $orderIds) {
            $receipt->delete();
            $this->recalculateOrders($orderIds);
        });

        return response()->json(['message' => 'Đã xóa phiếu ' . $number . ' và tính lại số đã nhận.']);
    }

    public function printReceipt(InternalPanelReceipt $receipt)
    {
        $receipt->load(['lines.orderRow']);
        return view('client.internal-panel-receipt-print', compact('receipt'));
    }

    private function recalculateOrders(array $orderIds): void
    {
        if (!$orderIds) {
            return;
        }
        $received = DB::connection('internal')->table('internal_panel_receipt_lines')
            ->select('order_tracking_row_id', DB::raw('SUM(received_quantity) as total_received'))
            ->whereIn('order_tracking_row_id', $orderIds)
            ->groupBy('order_tracking_row_id')
            ->pluck('total_received', 'order_tracking_row_id');

        foreach (InternalOrderTrackingRow::query()->whereIn('id', $orderIds)->get() as $order) {
            $total = (float) ($received[$order->id] ?? 0);
            $remaining = max(0, (float) $order->order_quantity - $total);
            $late = $order->delivery_date && Carbon::parse($order->delivery_date)->startOfDay()->lt(now()->startOfDay());
            $status = $remaining <= 0 && (float) $order->order_quantity > 0
                ? 'completed'
                : ($late ? 'late' : ($total > 0 ? 'partial' : 'pending'));
            $order->update(['received_quantity' => $total, 'remaining_quantity' => $remaining, 'status' => $status]);
        }
    }

    private function parseReceiptSheet($sheet, array &$rows, array &$errors): void
    {
        $highestRow = min(5002, $sheet->getHighestDataRow());
        $highestColumn = $sheet->getHighestDataColumn();
        $matrix = $sheet->rangeToArray('A1:' . $highestColumn . $highestRow, null, true, true, false);
        $headerIndex = $this->detectHeader($matrix);
        $headers = array_map([$this, 'normalizeHeader'], $matrix[$headerIndex] ?? []);
        $psColumn = $this->findColumn($headers, ['ps', 'ps no', 'ps sub', 'don hang', 'lenh']);
        $panelColumn = $this->findColumn($headers, ['panel', 'mat', 'vi tri']);
        $quantityColumn = $this->findColumn($headers, ['so luong ve', 'sl ve', 'so luong', 'quantity', 'qty', 'sl dat']);
        $itemColumn = $this->findColumn($headers, ['ma hang', 'item', 'item code']);
        if ($psColumn === null || $panelColumn === null || $quantityColumn === null) {
            $errors[] = ['sheet' => $sheet->getTitle(), 'row' => $headerIndex + 1, 'message' => 'Thiếu cột PS, PANEL hoặc SỐ LƯỢNG.'];
            return;
        }

        for ($index = $headerIndex + 1; $index < count($matrix); $index++) {
            $values = $matrix[$index];
            $ps = trim((string) ($values[$psColumn] ?? ''));
            $panel = $this->normalizePanel($values[$panelColumn] ?? '');
            $quantity = $this->number($values[$quantityColumn] ?? 0);
            if ($ps === '' && $panel === '' && $quantity == 0.0) {
                continue;
            }
            if ($ps === '' || $panel === '' || $quantity <= 0) {
                $errors[] = ['sheet' => $sheet->getTitle(), 'row' => $index + 1, 'message' => 'PS, PANEL và số lượng phải có dữ liệu hợp lệ.'];
                continue;
            }
            $rows[] = [
                'sheet' => $sheet->getTitle(),
                'source_row' => $index + 1,
                'ps_number' => $ps,
                'panel' => $panel,
                'item_code' => $itemColumn === null ? '' : trim((string) ($values[$itemColumn] ?? '')),
                'received_quantity' => $quantity,
            ];
        }
    }

    private function matchImportedRows(array $rows, array &$errors): array
    {
        $psValues = collect($rows)->pluck('ps_number')->filter()->unique()->values();
        $orders = collect();
        foreach ($psValues->chunk(500) as $chunk) {
            $orders = $orders->concat(InternalOrderTrackingRow::query()
                ->where('is_active', true)
                ->whereIn('order_number', $chunk->all())
                ->orderBy('source_row')
                ->get());
        }
        $groups = $orders->groupBy(function ($order) {
            return mb_strtoupper(trim((string) $order->order_number));
        });
        $planned = [];
        $matched = [];

        foreach ($rows as $row) {
            $candidates = $groups->get(mb_strtoupper($row['ps_number']), collect())->filter(function ($order) use ($row) {
                if ($this->normalizePanel($order->panel) !== $this->normalizePanel($row['panel'])) {
                    return false;
                }
                return $row['item_code'] === '' || mb_strtoupper(trim((string) $order->item_code)) === mb_strtoupper($row['item_code']);
            })->values();
            if ($candidates->isEmpty()) {
                $errors[] = ['sheet' => $row['sheet'], 'row' => $row['source_row'], 'message' => 'Không tìm thấy PS + PANEL trong Đơn hàng A/B.'];
                continue;
            }
            $order = $candidates->first(function ($candidate) use ($planned) {
                return ((float) $candidate->remaining_quantity - (float) ($planned[$candidate->id] ?? 0)) > 0;
            }) ?: $candidates->first();
            $planned[$order->id] = (float) ($planned[$order->id] ?? 0) + $row['received_quantity'];
            $payload = $this->orderPayload($order);
            $payload['received_quantity_input'] = $row['received_quantity'];
            $payload['source_row'] = $row['source_row'];
            $matched[] = $payload;
        }

        return $matched;
    }

    private function detectHeader(array $matrix): int
    {
        $best = 0;
        $bestScore = -1;
        foreach (array_slice($matrix, 0, 20) as $index => $row) {
            $headers = array_map([$this, 'normalizeHeader'], $row);
            $score = 0;
            foreach (['ps', 'panel', 'quantity', 'so luong', 'qty'] as $needle) {
                if ($this->findColumn($headers, [$needle]) !== null) {
                    $score++;
                }
            }
            if ($score > $bestScore) {
                $best = $index;
                $bestScore = $score;
            }
        }
        return $best;
    }

    private function findColumn(array $headers, array $names)
    {
        foreach ($names as $name) {
            $needle = $this->normalizeHeader($name);
            foreach ($headers as $index => $header) {
                if ($header === $needle || strpos($header, $needle) !== false) {
                    return $index;
                }
            }
        }
        return null;
    }

    private function normalizeHeader($value): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/', ' ', Str::ascii(mb_strtolower(trim((string) $value))))));
    }

    private function normalizePanel($value): string
    {
        $value = trim((string) $value);
        $canonical = $this->panelRules->canonical($value);
        return $this->panelRules->rules()['automatic'][$canonical] ?? $value;
    }

    private function number($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        $value = str_replace(' ', '', trim((string) $value));
        if (strpos($value, ',') !== false && strpos($value, '.') !== false) {
            $value = strrpos($value, ',') > strrpos($value, '.')
                ? str_replace(',', '.', str_replace('.', '', $value))
                : str_replace(',', '', $value);
        } elseif (strpos($value, ',') !== false) {
            $value = str_replace(',', '.', $value);
        }
        $value = preg_replace('/[^0-9.\-]/', '', $value);
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function orderPayload(InternalOrderTrackingRow $row): array
    {
        return [
            'id' => $row->id,
            'sheet_code' => $row->sheet_code,
            'source_sheet' => $row->source_sheet,
            'source_row' => $row->source_row,
            'ps_number' => $row->order_number,
            'item_code' => $row->item_code,
            'panel' => $row->panel,
            'size' => $row->size,
            'fabric_color' => $row->fabric_color,
            'logo_color' => $row->logo_color,
            'voucher_number' => $row->voucher_number,
            'order_quantity' => (float) $row->order_quantity,
            'received_quantity' => (float) $row->received_quantity,
            'remaining_quantity' => (float) $row->remaining_quantity,
            'delivery_date' => optional($row->delivery_date)->format('Y-m-d'),
        ];
    }

    private function receiptPayload(InternalPanelReceipt $receipt): array
    {
        return [
            'id' => $receipt->id,
            'receipt_number' => $receipt->receipt_number,
            'received_date' => optional($receipt->received_date)->format('Y-m-d'),
            'received_date_label' => optional($receipt->received_date)->format('d/m/Y'),
            'source_type' => $receipt->source_type,
            'source_file_name' => $receipt->source_file_name,
            'note' => $receipt->note,
            'line_count' => $receipt->line_count,
            'total_quantity' => $receipt->total_quantity,
            'print_url' => route('panel-receipts.print', ['receipt' => $receipt->id]),
        ];
    }
}
