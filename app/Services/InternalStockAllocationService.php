<?php

namespace App\Services;

use App\Models\InternalInventoryCount;
use App\Models\InternalMaterialReceiptLine;
use App\Models\InventoryPackage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class InternalStockAllocationService
{
    private InternalUnitConverter $unitConverter;

    public function __construct(InternalUnitConverter $unitConverter)
    {
        $this->unitConverter = $unitConverter;
    }

    public function shortages(array $lines, string $warehouseCode): array
    {
        $reservedByPackage = [];
        $warnings = [];

        foreach (array_values($lines) as $index => $line) {
            $requiredQuantity = $this->baseQuantity($line);
            if ($requiredQuantity <= 0) {
                continue;
            }

            $packages = $this->packageQuery($line, $warehouseCode)->get();
            $availableQuantity = (float) $packages->sum(function ($package) use ($reservedByPackage) {
                return max(0, (float) $package->quantity - (float) ($reservedByPackage[$package->id] ?? 0));
            });

            if ($availableQuantity + 0.0001 < $requiredQuantity) {
                $warnings[] = $this->shortageWarning($line, $index, $requiredQuantity, $availableQuantity, $warehouseCode);
            }

            // Reserve available stock even when this line is short. A later duplicate
            // line must not see the same package quantity a second time.
            $remaining = $requiredQuantity;
            foreach ($packages as $package) {
                if ($remaining <= 0.0001) {
                    break;
                }

                $available = max(0, (float) $package->quantity - (float) ($reservedByPackage[$package->id] ?? 0));
                if ($available <= 0) {
                    continue;
                }

                $taken = min($available, $remaining);
                $reservedByPackage[$package->id] = (float) ($reservedByPackage[$package->id] ?? 0) + $taken;
                $remaining -= $taken;
            }
        }

        return $warnings;
    }

    public function packageQuery(array $line, string $warehouseCode, bool $lockForUpdate = false): Builder
    {
        $maHh = mb_strtoupper(trim((string) ($line['ma_hh'] ?? '')));
        $locationCodes = $this->selectedLocationCodes($line);
        $internalCode = trim((string) ($line['internal_item_code'] ?? ''));

        $query = InventoryPackage::query()->where('quantity', '>', 0);
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }
        if ($maHh !== '' && ($internalCode === '' || $maHh !== mb_strtoupper($internalCode))) {
            $query->where('ma_sp', $maHh);
        }
        if ($warehouseCode !== '') {
            $query->where('ma_ko', mb_strtoupper(trim($warehouseCode)));
        }
        if (!empty($locationCodes)) {
            $query->whereHas('location', function ($locationQuery) use ($locationCodes) {
                $locationQuery->whereIn(DB::raw('UPPER(TRIM(location_code))'), $locationCodes);
            });
            $cases = collect($locationCodes)
                ->map(fn ($code, $index) => 'WHEN ? THEN ' . $index)
                ->implode(' ');
            $query->orderByRaw(
                '(SELECT CASE UPPER(TRIM(wl_order.location_code)) '
                    . $cases
                    . ' ELSE ' . count($locationCodes)
                    . ' END FROM warehouse_locations wl_order WHERE wl_order.id = inventory_packages.warehouse_location_id)',
                $locationCodes
            );
        }

        $query->orderBy('checked_at')->orderBy('id');
        if ($internalCode !== '') {
            $query->where('internal_item_code', $internalCode);
        }
        if (empty($line['match_by_code_only'])) {
            foreach (['size', 'color', 'side'] as $field) {
                $value = trim((string) ($line[$field] ?? ''));
                if ($value !== '') {
                    $query->where($field, $value);
                }
            }
        }

        return $query;
    }

    public function selectedLocationCodes(array $line): array
    {
        $values = $line['location_codes'] ?? [];
        if (!is_array($values)) {
            $values = [];
        }
        if (empty($values)) {
            $single = trim((string) ($line['location_code'] ?? ''));
            $values = $single === '' ? [] : preg_split('/\s*[,;|]\s*/', $single);
        }

        return collect($values)
            ->map(fn ($value) => mb_strtoupper(trim((string) $value)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function baseQuantity(array $line): float
    {
        $baseQuantity = (float) ($line['base_quantity'] ?? 0);
        if ($baseQuantity > 0) {
            return $baseQuantity;
        }

        return (float) $this->unitConverter->toBase(
            trim((string) ($line['internal_item_code'] ?? '')),
            (float) ($line['quantity'] ?? 0),
            trim((string) ($line['dvt'] ?? '')),
            trim((string) ($line['dvt'] ?? ''))
        )['quantity'];
    }

    public function formatQuantity(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }

    private function shortageWarning(array $line, int $index, float $required, float $available, string $warehouse): array
    {
        $code = trim((string) ($line['internal_item_code'] ?? ($line['ma_hh'] ?? '')));
        $reason = $this->negativeStockReason($line, $warehouse, $available);
        $variant = implode('', array_filter([
            trim((string) ($line['size'] ?? '')) !== '' ? ' / size ' . trim((string) $line['size']) : '',
            trim((string) ($line['color'] ?? '')) !== '' ? ' / màu ' . trim((string) $line['color']) : '',
            trim((string) ($line['side'] ?? '')) !== '' ? ' / mặt ' . trim((string) $line['side']) : '',
        ]));
        $shortage = $required - $available;

        return [
            'line_index' => $index,
            'internal_item_code' => $code,
            'size' => trim((string) ($line['size'] ?? '')),
            'color' => trim((string) ($line['color'] ?? '')),
            'side' => trim((string) ($line['side'] ?? '')),
            'location_code' => trim((string) ($line['location_code'] ?? '')),
            'required_quantity' => $required,
            'available_quantity' => $available,
            'shortage_quantity' => $shortage,
            'projected_quantity' => $available - $required,
            'reason' => $reason['code'],
            'reason_label' => $reason['label'],
            'message' => sprintf(
                'Dòng %d (%s%s): cần %s, tồn khả dụng %s, sẽ âm %s. %s',
                $index + 1,
                $code,
                $variant,
                $this->formatQuantity($required),
                $this->formatQuantity($available),
                $this->formatQuantity($shortage),
                $reason['label']
            ),
        ];
    }

    private function negativeStockReason(array $line, string $warehouseCode, float $available): array
    {
        $internalCode = trim((string) ($line['internal_item_code'] ?? ''));
        $maHh = mb_strtoupper(trim((string) ($line['ma_hh'] ?? '')));
        $variants = collect(['size', 'color', 'side'])
            ->mapWithKeys(fn ($field) => [$field => trim((string) ($line[$field] ?? ''))])
            ->all();

        $negativeQuery = InternalInventoryCount::query()->where('counted_quantity', '<', 0);
        $this->applyItemMatch($negativeQuery, $internalCode, $maHh, $warehouseCode, $variants);
        if ($negativeQuery->exists()) {
            return ['code' => 'already_negative', 'label' => 'Mã/biến thể này đã âm từ phiếu xuất trước; cần bổ sung hoặc sửa phiếu nhập.'];
        }
        if ($available > 0.0001) {
            return ['code' => 'partially_available', 'label' => 'Có một phần tồn đúng biến thể nhưng không đủ số lượng cần xuất.'];
        }

        $otherStockQuery = InventoryPackage::query()->where('quantity', '>', 0);
        if ($internalCode !== '') {
            $otherStockQuery->where('internal_item_code', $internalCode);
        } elseif ($maHh !== '') {
            $otherStockQuery->where('ma_sp', $maHh);
        }
        if ($warehouseCode !== '') {
            $otherStockQuery->where('ma_ko', mb_strtoupper(trim($warehouseCode)));
        }
        if ($otherStockQuery->exists()) {
            return ['code' => 'variant_or_location_mismatch', 'label' => 'Có tồn cùng mã ở size, màu, mặt hoặc vị trí khác; kiểm tra lại biến thể trước khi xuất âm.'];
        }

        $receiptQuery = InternalMaterialReceiptLine::query()
            ->join('internal_material_receipts as receipt', 'receipt.id', '=', 'internal_material_receipt_lines.receipt_id')
            ->where(fn ($query) => $query->whereNull('receipt.status')->orWhere('receipt.status', '<>', 'cancelled'));
        if ($internalCode !== '') {
            $receiptQuery->whereRaw('UPPER(TRIM(internal_material_receipt_lines.internal_item_code)) = ?', [mb_strtoupper($internalCode)]);
        } elseif ($maHh !== '') {
            $receiptQuery->where('internal_material_receipt_lines.ma_hh', $maHh);
        }
        foreach ($variants as $field => $value) {
            if ($value !== '') {
                $receiptQuery->where("internal_material_receipt_lines.{$field}", $value);
            }
        }
        if ($receiptQuery->exists()) {
            return ['code' => 'received_but_depleted', 'label' => 'Đã có phiếu nhập phù hợp nhưng lượng đó đã xuất hết hoặc đang lệch liên kết FIFO.'];
        }

        return ['code' => 'not_received', 'label' => 'Chưa thấy phiếu nhập phù hợp cho đúng mã/size/màu/mặt này.'];
    }

    private function applyItemMatch($query, string $internalCode, string $maHh, string $warehouse, array $variants): void
    {
        if ($internalCode !== '') {
            $query->where('internal_item_code', $internalCode);
        } elseif ($maHh !== '') {
            $query->where('ma_sp', $maHh);
        }
        foreach ($variants as $field => $value) {
            if ($value !== '') {
                $query->where($field, $value);
            }
        }
        if ($warehouse !== '') {
            $query->where('ma_ko', mb_strtoupper(trim($warehouse)));
        }
    }
}
