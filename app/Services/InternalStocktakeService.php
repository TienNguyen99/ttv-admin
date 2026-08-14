<?php

namespace App\Services;

use App\Models\InternalItemCatalog;
use App\Models\InternalMaterialIssue;
use App\Models\InternalMaterialReceipt;
use App\Models\InternalStocktakeLocation;
use App\Models\InternalStocktakeSession;
use App\Models\WarehouseLocation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InternalStocktakeService
{
    public function create(string $name, string $countDate, ?string $note = null): InternalStocktakeSession
    {
        $active = InternalStocktakeSession::query()
            ->whereIn('status', ['counting', 'completed'])
            ->first();
        if ($active) {
            throw new \DomainException("Đợt {$active->stocktake_code} chưa được chốt hoặc hủy.");
        }

        $date = Carbon::parse($countDate)->format('Y-m-d');
        $monthStart = Carbon::parse($date)->startOfMonth()->format('Y-m-d');
        $ledgerRows = app(InternalStockLedger::class)
            ->query($monthStart, $date)
            ->select(
                'warehouse_code',
                'location_code',
                'ma_hh',
                'internal_item_code',
                'size',
                'color',
                'side',
                DB::raw('SUM(opening_quantity + receipt_quantity - issue_quantity) as expected_quantity')
            )
            ->groupBy('warehouse_code', 'location_code', 'ma_hh', 'internal_item_code', 'size', 'color', 'side')
            ->havingRaw('SUM(opening_quantity + receipt_quantity - issue_quantity) <> 0')
            ->get();

        $catalogRows = InternalItemCatalog::query()
            ->where('is_active', true)
            ->orderByDesc('source_row')
            ->get();
        $catalogs = $catalogRows
            ->unique(fn ($row) => mb_strtoupper(trim((string) $row->item_code)))
            ->keyBy(fn ($row) => mb_strtoupper(trim((string) $row->item_code)));

        return DB::connection('internal')->transaction(function () use ($name, $date, $note, $ledgerRows, $catalogRows, $catalogs) {
            $session = InternalStocktakeSession::query()->create([
                'stocktake_code' => app(InternalDocumentNumber::class)->next('KK', 4),
                'name' => $name,
                'count_date' => $date,
                'status' => 'counting',
                'note' => $note,
                'started_at' => now(),
            ]);

            $physicalLocations = WarehouseLocation::query()
                ->whereRaw("UPPER(TRIM(location_code)) <> 'DI-HANG'")
                ->get(['id', 'location_code'])
                ->mapWithKeys(fn ($location) => [mb_strtoupper(trim((string) $location->location_code)) => $location]);

            $locationCodes = $physicalLocations->keys()
                ->merge($ledgerRows->pluck('location_code')->map(fn ($code) => mb_strtoupper(trim((string) $code)) ?: 'CHUA-XEP'))
                ->merge($catalogRows->pluck('shelf_code')->map(fn ($code) => mb_strtoupper(trim((string) $code))))
                ->filter(fn ($code) => $code !== '' && $code !== 'DI-HANG')
                ->unique()
                ->sort(fn ($a, $b) => strnatcasecmp($a, $b))
                ->values();

            foreach ($locationCodes as $locationCode) {
                $location = $physicalLocations->get($locationCode);
                InternalStocktakeLocation::query()->create([
                    'session_id' => $session->id,
                    'warehouse_location_id' => $location->id ?? null,
                    'location_code' => $locationCode,
                    'status' => 'pending',
                ]);
            }

            $sessionLocations = $session->locations()->get()->keyBy('location_code');
            $now = now();
            $lineInserts = [];
            foreach ($ledgerRows as $row) {
                $locationCode = mb_strtoupper(trim((string) $row->location_code)) ?: 'CHUA-XEP';
                if ($locationCode === 'DI-HANG') {
                    continue;
                }
                $itemCode = trim((string) $row->internal_item_code);
                if ($itemCode === '') {
                    continue;
                }
                $catalog = $catalogs->get(mb_strtoupper($itemCode));
                $key = $this->lineKey($locationCode, $row->ma_hh, $itemCode, $row->size, $row->color, $row->side);
                if (isset($lineInserts[$key])) {
                    $lineInserts[$key]['expected_quantity'] += (float) $row->expected_quantity;
                    if ($lineInserts[$key]['ma_hh'] === '') {
                        $lineInserts[$key]['ma_hh'] = trim((string) $row->ma_hh);
                    }
                    continue;
                }
                $lineInserts[$key] = [
                    'session_id' => $session->id,
                    'session_location_id' => $sessionLocations[$locationCode]->id,
                    'line_key' => $key,
                    'location_code' => $locationCode,
                    'ma_hh' => trim((string) $row->ma_hh),
                    'internal_item_code' => $itemCode,
                    'item_name' => $catalog->item_name ?? '',
                    'unit' => $catalog->unit ?? '',
                    'size' => trim((string) $row->size),
                    'color' => trim((string) $row->color),
                    'side' => trim((string) $row->side),
                    'expected_quantity' => (float) $row->expected_quantity,
                    'counted_quantity' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach ($catalogRows as $catalog) {
                $locationCode = mb_strtoupper(trim((string) $catalog->shelf_code));
                $itemCode = trim((string) $catalog->item_code);
                if ($locationCode === '' || $locationCode === 'DI-HANG' || $itemCode === '' || !$sessionLocations->has($locationCode)) {
                    continue;
                }
                $key = $this->lineKey($locationCode, '', $itemCode, $catalog->size, $catalog->color, $catalog->side);
                if (isset($lineInserts[$key])) {
                    continue;
                }
                $lineInserts[$key] = [
                    'session_id' => $session->id,
                    'session_location_id' => $sessionLocations[$locationCode]->id,
                    'line_key' => $key,
                    'location_code' => $locationCode,
                    'ma_hh' => '',
                    'internal_item_code' => $itemCode,
                    'item_name' => $catalog->item_name ?? '',
                    'unit' => $catalog->unit ?? '',
                    'size' => trim((string) $catalog->size),
                    'color' => trim((string) $catalog->color),
                    'side' => trim((string) $catalog->side),
                    'expected_quantity' => 0,
                    'counted_quantity' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk(array_values($lineInserts), 500) as $chunk) {
                DB::connection('internal')->table('internal_stocktake_lines')->insert($chunk);
            }

            return $session->fresh();
        });
    }

    public function post(InternalStocktakeSession $session): InternalStocktakeSession
    {
        return DB::connection('internal')->transaction(function () use ($session) {
            $session = InternalStocktakeSession::query()->lockForUpdate()->findOrFail($session->id);
            if ($session->status === 'posted') {
                return $session;
            }
            if ($session->status !== 'completed') {
                throw new \DomainException('Phải hoàn tất toàn bộ vị trí trước khi áp dụng chênh lệch.');
            }
            if ($session->lines()->whereNull('counted_quantity')->exists()) {
                throw new \DomainException('Vẫn còn dòng chưa nhập số đếm.');
            }

            $lines = $session->lines()->get()->map(function ($line) {
                $line->variance = round((float) $line->counted_quantity - (float) $line->expected_quantity, 3);
                return $line;
            })->filter(fn ($line) => abs($line->variance) >= 0.0005);

            $receipt = null;
            $positiveLines = $lines->filter(fn ($line) => $line->variance > 0)->values();
            if ($positiveLines->isNotEmpty()) {
                $receipt = InternalMaterialReceipt::query()->create([
                    'receipt_code' => app(InternalDocumentNumber::class)->next('PNKK', 4),
                    'receipt_date' => $session->count_date,
                    'warehouse_code' => '',
                    'location_code' => '',
                    'receiver_name' => '',
                    'source' => 'Dieu chinh kiem ke',
                    'status' => 'posted',
                    'note' => "Điều chỉnh tăng từ {$session->stocktake_code}",
                ]);
                $this->insertReceiptLines($receipt->id, $positiveLines, $session->stocktake_code);
            }

            $issue = null;
            $negativeLines = $lines->filter(fn ($line) => $line->variance < 0)->values();
            if ($negativeLines->isNotEmpty()) {
                $issue = InternalMaterialIssue::query()->create([
                    'issue_code' => app(InternalDocumentNumber::class)->next('PXKK', 4),
                    'issue_type' => 'inventory_adjustment',
                    'issue_date' => $session->count_date,
                    'warehouse_code' => '',
                    'receiver_name' => '',
                    'department' => 'Kho',
                    'purpose' => 'Điều chỉnh giảm kiểm kê',
                    'status' => 'posted',
                    'note' => "Điều chỉnh giảm từ {$session->stocktake_code}",
                ]);
                $this->insertIssueLines($issue->id, $negativeLines, $session->stocktake_code);
            }

            $session->update([
                'status' => 'posted',
                'adjustment_receipt_id' => $receipt->id ?? null,
                'adjustment_issue_id' => $issue->id ?? null,
                'posted_at' => now(),
            ]);

            return $session->fresh();
        });
    }

    public function lineKey($locationCode, $maHh, $internalCode, $size, $color, $side): string
    {
        return sha1(implode('|', array_map(function ($value) {
            return mb_strtoupper(trim((string) $value));
        }, [$locationCode, $internalCode, $size, $color, $side])));
    }

    private function insertReceiptLines(int $receiptId, $lines, string $stocktakeCode): void
    {
        $now = now();
        foreach ($lines->chunk(500) as $chunk) {
            DB::connection('internal')->table('internal_material_receipt_lines')->insert($chunk->map(fn ($line) => [
                'receipt_id' => $receiptId,
                'ma_hh' => $line->ma_hh ?: '',
                'ten_hh' => mb_substr((string) $line->item_name, 0, 255),
                'dvt' => $line->unit ?: '',
                'quantity' => $line->variance,
                'base_quantity' => $line->variance,
                'base_dvt' => $line->unit ?: '',
                'unit_factor' => 1,
                'location_code' => $line->location_code,
                'internal_item_code' => $line->internal_item_code,
                'size' => $line->size,
                'color' => $line->color,
                'side' => $line->side,
                'note' => "Chênh tăng {$stocktakeCode}",
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        }
    }

    private function insertIssueLines(int $issueId, $lines, string $stocktakeCode): void
    {
        $now = now();
        foreach ($lines->chunk(500) as $chunk) {
            DB::connection('internal')->table('internal_material_issue_lines')->insert($chunk->map(fn ($line) => [
                'issue_id' => $issueId,
                'ma_hh' => $line->ma_hh ?: '',
                'ten_hh' => mb_substr((string) $line->item_name, 0, 255),
                'dvt' => $line->unit ?: '',
                'quantity' => abs($line->variance),
                'base_quantity' => abs($line->variance),
                'base_dvt' => $line->unit ?: '',
                'unit_factor' => 1,
                'location_code' => $line->location_code,
                'internal_item_code' => $line->internal_item_code,
                'size' => $line->size,
                'color' => $line->color,
                'side' => $line->side,
                'note' => "Chênh giảm {$stocktakeCode}",
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        }
    }
}
