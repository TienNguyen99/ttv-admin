<?php

namespace App\Http\Controllers;

use App\Models\InternalItemCatalog;
use App\Models\InternalStocktakeLine;
use App\Models\InternalStocktakeLocation;
use App\Models\InternalStocktakeSession;
use App\Services\InternalStocktakeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InternalStocktakeController extends Controller
{
    public function page()
    {
        return view('client.internal-stocktake');
    }

    public function index()
    {
        $sessions = InternalStocktakeSession::query()
            ->withCount([
                'locations',
                'locations as completed_location_count' => fn ($query) => $query->where('status', 'completed'),
                'lines',
                'lines as counted_line_count' => fn ($query) => $query->whereNotNull('counted_quantity'),
            ])
            ->orderByDesc('count_date')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json(['data' => $sessions]);
    }

    public function store(Request $request, InternalStocktakeService $service)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'count_date' => 'required|date|before_or_equal:today',
            'note' => 'nullable|string|max:2000',
        ]);

        try {
            $session = $service->create($data['name'], $data['count_date'], $data['note'] ?? null);
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json([
            'message' => "Đã tạo {$session->stocktake_code} và chụp tồn hệ thống.",
            'data' => $session,
        ], 201);
    }

    public function show(InternalStocktakeSession $stocktake)
    {
        $locations = DB::connection('internal')->table('internal_stocktake_locations as sl')
            ->leftJoin('internal_stocktake_lines as line', 'line.session_location_id', '=', 'sl.id')
            ->where('sl.session_id', $stocktake->id)
            ->select(
                'sl.id',
                'sl.location_code',
                'sl.status',
                'sl.note',
                'sl.started_at',
                'sl.completed_at',
                DB::raw('COUNT(line.id) as line_count'),
                DB::raw('SUM(CASE WHEN line.counted_quantity IS NOT NULL THEN 1 ELSE 0 END) as counted_line_count'),
                DB::raw('COALESCE(SUM(line.expected_quantity), 0) as expected_quantity'),
                DB::raw('COALESCE(SUM(line.counted_quantity), 0) as counted_quantity'),
                DB::raw('COALESCE(SUM(CASE WHEN line.counted_quantity IS NOT NULL THEN line.counted_quantity - line.expected_quantity ELSE 0 END), 0) as variance_quantity')
            )
            ->groupBy('sl.id', 'sl.location_code', 'sl.status', 'sl.note', 'sl.started_at', 'sl.completed_at')
            ->get()
            ->sortBy(fn ($row) => $this->naturalLocationKey($row->location_code))
            ->values();

        return response()->json([
            'data' => [
                'session' => $stocktake,
                'summary' => $this->summary($stocktake),
                'locations' => $locations,
            ],
        ]);
    }

    public function location(InternalStocktakeSession $stocktake, InternalStocktakeLocation $stocktakeLocation)
    {
        $this->assertLocationBelongsToSession($stocktake, $stocktakeLocation);
        $catalogCodes = InternalItemCatalog::query()
            ->where('is_active', true)
            ->whereIn('item_code', $stocktakeLocation->lines()->pluck('internal_item_code')->filter()->unique()->values())
            ->pluck('item_code')
            ->map(fn ($code) => mb_strtoupper(trim((string) $code)))
            ->flip();

        $lines = $stocktakeLocation->lines()
            ->orderBy('internal_item_code')
            ->orderBy('size')
            ->orderBy('color')
            ->get()
            ->map(function ($line) use ($catalogCodes) {
                $line->setAttribute('variance_quantity', $line->counted_quantity === null
                    ? null
                    : round((float) $line->counted_quantity - (float) $line->expected_quantity, 3));
                $line->setAttribute('catalog_exists', $catalogCodes->has(mb_strtoupper(trim((string) $line->internal_item_code))));
                return $line;
            });

        if ($stocktakeLocation->status === 'pending') {
            $stocktakeLocation->update(['status' => 'counting', 'started_at' => now()]);
        }

        return response()->json([
            'data' => [
                'location' => $stocktakeLocation->fresh(),
                'lines' => $lines,
            ],
        ]);
    }

    public function saveLocation(
        Request $request,
        InternalStocktakeSession $stocktake,
        InternalStocktakeLocation $stocktakeLocation,
        InternalStocktakeService $service
    ) {
        $this->assertEditable($stocktake);
        $this->assertLocationBelongsToSession($stocktake, $stocktakeLocation);
        $data = $request->validate([
            'note' => 'nullable|string|max:2000',
            'lines' => 'required|array|max:2000',
            'lines.*.id' => 'nullable|integer',
            'lines.*.ma_hh' => 'nullable|string|max:100',
            'lines.*.internal_item_code' => 'required|string|max:100',
            'lines.*.item_name' => 'nullable|string|max:500',
            'lines.*.unit' => 'nullable|string|max:50',
            'lines.*.size' => 'nullable|string|max:100',
            'lines.*.color' => 'nullable|string|max:100',
            'lines.*.side' => 'nullable|string|max:100',
            'lines.*.counted_quantity' => 'nullable|numeric|min:0|max:999999999999999',
            'lines.*.note' => 'nullable|string|max:2000',
        ]);

        DB::connection('internal')->transaction(function () use ($data, $stocktake, $stocktakeLocation, $service) {
            foreach ($data['lines'] as $input) {
                $itemCode = mb_strtoupper(trim((string) $input['internal_item_code']));
                $attributes = [
                    'location_code' => $stocktakeLocation->location_code,
                    'ma_hh' => mb_strtoupper(trim((string) ($input['ma_hh'] ?? ''))),
                    'internal_item_code' => $itemCode,
                    'size' => trim((string) ($input['size'] ?? '')),
                    'color' => trim((string) ($input['color'] ?? '')),
                    'side' => trim((string) ($input['side'] ?? '')),
                ];
                $lineKey = $service->lineKey(
                    $attributes['location_code'],
                    $attributes['ma_hh'],
                    $attributes['internal_item_code'],
                    $attributes['size'],
                    $attributes['color'],
                    $attributes['side']
                );
                $line = !empty($input['id'])
                    ? $stocktakeLocation->lines()->whereKey($input['id'])->first()
                    : $stocktake->lines()->where('line_key', $lineKey)->first();
                $catalog = InternalItemCatalog::query()
                    ->where('is_active', true)
                    ->whereRaw('UPPER(TRIM(item_code)) = ?', [$itemCode])
                    ->orderByDesc('source_row')
                    ->first();
                $counted = array_key_exists('counted_quantity', $input) && $input['counted_quantity'] !== null
                    ? (float) $input['counted_quantity']
                    : null;
                $values = array_merge($attributes, [
                    'session_id' => $stocktake->id,
                    'session_location_id' => $stocktakeLocation->id,
                    'line_key' => $lineKey,
                    'item_name' => trim((string) ($input['item_name'] ?? '')) ?: ($catalog->item_name ?? ''),
                    'unit' => trim((string) ($input['unit'] ?? '')) ?: ($catalog->unit ?? ''),
                    'counted_quantity' => $counted,
                    'counted_at' => $counted === null ? null : now(),
                    'note' => $input['note'] ?? null,
                ]);
                if ($line) {
                    unset($values['session_id'], $values['session_location_id']);
                    $line->update($values);
                } else {
                    $values['expected_quantity'] = 0;
                    InternalStocktakeLine::query()->create($values);
                }
            }
            $stocktakeLocation->update([
                'status' => 'counting',
                'started_at' => $stocktakeLocation->started_at ?: now(),
                'completed_at' => null,
                'note' => $data['note'] ?? $stocktakeLocation->note,
            ]);
            if ($stocktake->status === 'completed') {
                $stocktake->update(['status' => 'counting', 'completed_at' => null]);
            }
        });

        return response()->json(['message' => "Đã lưu số đếm tại {$stocktakeLocation->location_code}."]);
    }

    public function completeLocation(Request $request, InternalStocktakeSession $stocktake, InternalStocktakeLocation $stocktakeLocation)
    {
        $this->assertEditable($stocktake);
        $this->assertLocationBelongsToSession($stocktake, $stocktakeLocation);
        $data = $request->validate(['zero_unentered' => 'nullable|boolean']);

        try {
            DB::connection('internal')->transaction(function () use ($stocktakeLocation, $data) {
                if (!empty($data['zero_unentered'])) {
                    $stocktakeLocation->lines()->whereNull('counted_quantity')->update([
                        'counted_quantity' => 0,
                        'counted_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $missing = $stocktakeLocation->lines()->whereNull('counted_quantity')->count();
                if ($missing > 0) {
                    throw new \DomainException("Còn {$missing} dòng chưa nhập số đếm.");
                }
                $stocktakeLocation->update(['status' => 'completed', 'completed_at' => now()]);
            });
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['message' => "Đã hoàn tất vị trí {$stocktakeLocation->location_code}."]);
    }

    public function reopenLocation(InternalStocktakeSession $stocktake, InternalStocktakeLocation $stocktakeLocation)
    {
        $this->assertEditable($stocktake);
        $this->assertLocationBelongsToSession($stocktake, $stocktakeLocation);
        $stocktakeLocation->update(['status' => 'counting', 'completed_at' => null]);
        $stocktake->update(['status' => 'counting', 'completed_at' => null]);

        return response()->json(['message' => "Đã mở lại vị trí {$stocktakeLocation->location_code}."]);
    }

    public function complete(InternalStocktakeSession $stocktake)
    {
        $this->assertEditable($stocktake);
        $pending = $stocktake->locations()->where('status', '<>', 'completed')->count();
        if ($pending > 0) {
            return response()->json(['message' => "Còn {$pending} vị trí chưa hoàn tất."], 422);
        }
        if ($stocktake->lines()->whereNull('counted_quantity')->exists()) {
            return response()->json(['message' => 'Vẫn còn dòng chưa nhập số đếm.'], 422);
        }
        $stocktake->update(['status' => 'completed', 'completed_at' => now()]);

        return response()->json(['message' => 'Đã hoàn tất kiểm đếm. Hãy xem chênh lệch trước khi áp dụng.']);
    }

    public function post(InternalStocktakeSession $stocktake, InternalStocktakeService $service)
    {
        try {
            $session = $service->post($stocktake);
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Đã áp dụng chênh lệch vào tồn nội bộ.',
            'data' => $session,
        ]);
    }

    public function destroy(InternalStocktakeSession $stocktake)
    {
        if ($stocktake->status === 'posted') {
            return response()->json(['message' => 'Đợt đã áp dụng không thể xóa.'], 409);
        }
        $stocktake->delete();
        return response()->json(['message' => 'Đã xóa đợt kiểm kê chưa áp dụng.']);
    }

    private function summary(InternalStocktakeSession $session): array
    {
        $locations = DB::connection('internal')->table('internal_stocktake_locations')
            ->where('session_id', $session->id)
            ->selectRaw('COUNT(*) total, SUM(status = ?) completed', ['completed'])
            ->first();
        $lines = DB::connection('internal')->table('internal_stocktake_lines')
            ->where('session_id', $session->id)
            ->selectRaw('COUNT(*) total, SUM(counted_quantity IS NOT NULL) counted, COALESCE(SUM(expected_quantity), 0) expected, COALESCE(SUM(counted_quantity), 0) actual, COALESCE(SUM(CASE WHEN counted_quantity IS NOT NULL THEN counted_quantity - expected_quantity ELSE 0 END), 0) variance')
            ->first();

        return [
            'location_count' => (int) ($locations->total ?? 0),
            'completed_location_count' => (int) ($locations->completed ?? 0),
            'line_count' => (int) ($lines->total ?? 0),
            'counted_line_count' => (int) ($lines->counted ?? 0),
            'expected_quantity' => (float) ($lines->expected ?? 0),
            'counted_quantity' => (float) ($lines->actual ?? 0),
            'variance_quantity' => (float) ($lines->variance ?? 0),
        ];
    }

    private function assertEditable(InternalStocktakeSession $session): void
    {
        abort_if(in_array($session->status, ['posted', 'cancelled'], true), 409, 'Đợt kiểm kê đã khóa.');
    }

    private function assertLocationBelongsToSession(InternalStocktakeSession $session, InternalStocktakeLocation $location): void
    {
        abort_unless((int) $location->session_id === (int) $session->id, 404);
    }

    private function naturalLocationKey(string $code): string
    {
        return preg_replace_callback('/\d+/', fn ($match) => str_pad($match[0], 10, '0', STR_PAD_LEFT), mb_strtoupper($code));
    }
}
