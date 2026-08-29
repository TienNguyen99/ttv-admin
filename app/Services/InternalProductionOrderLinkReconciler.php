<?php

namespace App\Services;

use App\Models\InternalProductionOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InternalProductionOrderLinkReconciler
{
    public function preview(int $limit = 500): array
    {
        $limit = min(max($limit, 1), 2000);
        $lines = $this->problemLines($limit);
        $ordersByCode = $this->ordersByCode($lines->pluck('production_order'));
        $rows = $lines->map(fn ($line) => $this->proposal($line, $ordersByCode))->values();

        return [
            'rows' => $rows->all(),
            'summary' => [
                'total' => $rows->count(),
                'matchable' => $rows->where('status', 'matchable')->count(),
                'ambiguous' => $rows->where('status', 'ambiguous')->count(),
                'missing_order' => $rows->where('status', 'missing_order')->count(),
                'missing_item' => $rows->where('status', 'missing_item')->count(),
                'missing_variant' => $rows->where('status', 'missing_variant')->count(),
                'truncated' => $lines->count() >= $limit,
                'limit' => $limit,
            ],
        ];
    }

    public function apply(int $limit = 500): array
    {
        $preview = $this->preview($limit);
        $matchable = collect($preview['rows'])->where('status', 'matchable')->values();
        $updated = DB::connection('internal')->transaction(function () use ($matchable) {
            $updated = 0;
            foreach ($matchable as $row) {
                $table = $row['line_type'] === 'receipt'
                    ? 'internal_material_receipt_lines'
                    : 'internal_material_issue_lines';
                $query = DB::connection('internal')->table($table)
                    ->where('id', (int) $row['line_id']);
                $currentId = (int) ($row['current_order_id'] ?? 0);
                $currentId > 0
                    ? $query->where('production_order_id', $currentId)
                    : $query->whereNull('production_order_id');

                $updated += $query->update([
                    'production_order_id' => (int) $row['suggested_order_id'],
                    'updated_at' => now(),
                ]);
            }

            return $updated;
        });

        app(InternalAudit::class)->record(
            'production_order_links.reconciled',
            'InternalProductionOrder',
            null,
            null,
            [
                'updated' => $updated,
                'matchable' => $matchable->count(),
                'ambiguous' => (int) $preview['summary']['ambiguous'],
                'missing_order' => (int) $preview['summary']['missing_order'],
                'missing_item' => (int) $preview['summary']['missing_item'],
                'missing_variant' => (int) $preview['summary']['missing_variant'],
            ]
        );

        $preview['summary']['updated'] = $updated;

        return $preview;
    }

    private function problemLines(int $limit): Collection
    {
        $receipts = DB::connection('internal')->table('internal_material_receipt_lines as line')
            ->join('internal_material_receipts as document', 'document.id', '=', 'line.receipt_id')
            ->leftJoin('internal_production_orders as linked_order', 'linked_order.id', '=', 'line.production_order_id')
            ->whereNotNull('line.production_order')
            ->where('line.production_order', '<>', '')
            ->whereNotNull('line.internal_item_code')
            ->where('line.internal_item_code', '<>', '')
            ->where(function ($query) {
                $query->whereNull('line.production_order_id')
                    ->orWhereRaw('UPPER(TRIM(line.production_order)) <> UPPER(TRIM(linked_order.production_order))');
            })
            ->selectRaw("'receipt' as line_type")
            ->addSelect([
                'line.id as line_id', 'line.production_order', 'line.production_order_id as current_order_id',
                'linked_order.production_order as current_order_code', 'line.internal_item_code',
                'line.size', 'line.color', 'line.quantity', 'line.dvt as unit',
                'document.receipt_code as document_code', 'document.receipt_date as document_date',
            ])
            ->orderBy('document.receipt_date')
            ->orderBy('line.id')
            ->limit($limit)
            ->get();

        $remaining = max(0, $limit - $receipts->count());
        $issues = $remaining === 0 ? collect() : DB::connection('internal')->table('internal_material_issue_lines as line')
            ->join('internal_material_issues as document', 'document.id', '=', 'line.issue_id')
            ->leftJoin('internal_production_orders as linked_order', 'linked_order.id', '=', 'line.production_order_id')
            ->whereNotNull('line.production_order')
            ->where('line.production_order', '<>', '')
            ->whereNotNull('line.internal_item_code')
            ->where('line.internal_item_code', '<>', '')
            ->where(function ($query) {
                $query->whereNull('line.production_order_id')
                    ->orWhereRaw('UPPER(TRIM(line.production_order)) <> UPPER(TRIM(linked_order.production_order))');
            })
            ->selectRaw("'issue' as line_type")
            ->addSelect([
                'line.id as line_id', 'line.production_order', 'line.production_order_id as current_order_id',
                'linked_order.production_order as current_order_code', 'line.internal_item_code',
                'line.size', 'line.color', 'line.quantity', 'line.dvt as unit',
                'document.issue_code as document_code', 'document.issue_date as document_date',
            ])
            ->orderBy('document.issue_date')
            ->orderBy('line.id')
            ->limit($remaining)
            ->get();

        return $receipts->concat($issues)
            ->sortBy(fn ($line) => (string) $line->document_date . '|' . $line->line_type . '|' . str_pad((string) $line->line_id, 12, '0', STR_PAD_LEFT))
            ->values();
    }

    private function ordersByCode(Collection $codes): Collection
    {
        $codes = $codes->map(fn ($code) => $this->key($code))->filter()->unique()->values();
        if ($codes->isEmpty()) {
            return collect();
        }

        return InternalProductionOrder::query()
            ->whereIn(DB::raw('UPPER(TRIM(production_order))'), $codes->all())
            ->select([
                'id', 'production_order', 'item_code', 'standard_item_code', 'size', 'color',
                'variant_parent_id', 'is_variant_parent', 'is_manual_variant', 'is_active',
            ])
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($order) => $this->key($order->production_order));
    }

    private function proposal($line, Collection $ordersByCode): array
    {
        $orders = $ordersByCode->get($this->key($line->production_order), collect());
        $itemCode = $this->key($line->internal_item_code);
        $base = [
            'line_type' => $line->line_type,
            'line_id' => (int) $line->line_id,
            'document_code' => trim((string) $line->document_code),
            'document_date' => (string) $line->document_date,
            'production_order' => trim((string) $line->production_order),
            'internal_item_code' => trim((string) $line->internal_item_code),
            'size' => trim((string) $line->size),
            'color' => trim((string) $line->color),
            'quantity' => (float) $line->quantity,
            'unit' => trim((string) $line->unit),
            'current_order_id' => $line->current_order_id ? (int) $line->current_order_id : null,
            'current_order_code' => trim((string) $line->current_order_code),
            'suggested_order_id' => null,
            'suggested_item_code' => null,
            'source_item_code' => null,
            'variant_item_code' => null,
        ];
        if ($orders->isEmpty()) {
            return array_merge($base, ['status' => 'missing_order', 'reason' => 'Không tìm thấy lệnh trung tâm.']);
        }

        // standard_item_code identifies a central variant. item_code remains the
        // source code imported from the read-only production order.
        $matches = $orders->filter(function ($order) use ($itemCode) {
            $standard = $this->key($order->standard_item_code);
            $source = $this->key($order->item_code);
            return $standard === $itemCode
                && ((int) $order->variant_parent_id > 0 || $standard !== $source);
        })->values();
        if ($matches->isEmpty()) {
            $sourceMatches = $orders->filter(fn ($order) => $this->key($order->item_code) === $itemCode)->values();
            $variantChildren = $sourceMatches->filter(fn ($order) => (int) $order->variant_parent_id > 0)->values();
            $matches = $variantChildren->isNotEmpty() ? $variantChildren : $sourceMatches;
        }

        if ($matches->isEmpty()) {
            $sourceCandidates = $orders->filter(function ($order) use ($itemCode) {
                $source = $this->key($order->item_code);
                return $source !== ''
                    && (strpos($itemCode, $source . '-') === 0 || strpos($itemCode, $source . '_') === 0);
            })->unique(fn ($order) => $this->key($order->item_code))->values();

            if ($sourceCandidates->count() === 1) {
                $source = trim((string) $sourceCandidates->first()->item_code);
                return array_merge($base, [
                    'status' => 'missing_variant',
                    'reason' => 'Mã thuộc mã gốc ' . $source . ' nhưng biến thể chưa được tạo trong lệnh trung tâm.',
                    'source_item_code' => $source,
                    'variant_item_code' => trim((string) $line->internal_item_code),
                ]);
            }

            return array_merge($base, ['status' => 'missing_item', 'reason' => 'Lệnh có tồn tại nhưng mã không thuộc mã gốc hoặc biến thể nào.']);
        }

        if ($matches->count() > 1) {
            $variantMatches = $this->matchDimensions($matches, $line->size, $line->color);
            if ($variantMatches->count() === 1) {
                $matches = $variantMatches;
            }
        }

        if ($matches->count() !== 1) {
            $sourceCodes = $matches->pluck('item_code')->map(fn ($code) => trim((string) $code))->filter()->unique()->values();
            return array_merge($base, [
                'status' => 'ambiguous',
                'reason' => 'Có nhiều biến thể phù hợp; cần bổ sung size/màu hoặc chọn thủ công.',
                'source_item_code' => $sourceCodes->count() === 1 ? $sourceCodes->first() : null,
            ]);
        }

        $suggested = $matches->first();
        $sourceItemCode = trim((string) $suggested->item_code);
        $variantItemCode = trim((string) $suggested->standard_item_code);
        return array_merge($base, [
            'status' => 'matchable',
            'reason' => 'Khớp duy nhất theo lệnh, mã gốc và biến thể.',
            'suggested_order_id' => (int) $suggested->id,
            'suggested_item_code' => $variantItemCode ?: $sourceItemCode,
            'source_item_code' => $sourceItemCode,
            'variant_item_code' => $variantItemCode !== '' && $this->key($variantItemCode) !== $this->key($sourceItemCode)
                ? $variantItemCode
                : null,
        ]);
    }

    private function matchDimensions(Collection $orders, $sizeValue, $colorValue): Collection
    {
        $size = $this->key($sizeValue);
        $color = $this->key($colorValue);

        return $orders->filter(function ($order) use ($size, $color) {
            return ($size === '' || $this->key($order->size) === $size)
                && ($color === '' || $this->key($order->color) === $color);
        })->values();
    }

    private function key($value): string
    {
        return mb_strtoupper(trim((string) $value));
    }
}
