<?php

namespace App\Services;

use App\Models\InternalProductionOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InternalProductionLifecycleService
{
    private InternalOrderBomService $bomService;

    public function __construct(InternalOrderBomService $bomService)
    {
        $this->bomService = $bomService;
    }

    public function enrich(Collection $orders, Collection $rows): Collection
    {
        $ordersByCode = $orders->groupBy(fn ($order) => $this->code($order->production_order));
        $orderCodes = $ordersByCode->keys()->filter()->values();
        if ($orderCodes->isEmpty()) {
            return $rows;
        }

        $orderIds = $orders->pluck('id')->map(fn ($id) => (int) $id)->filter()->values();
        $receiptLinks = $this->receiptLinkStats($orderCodes, $orderIds);
        $issueLinks = $this->issueLinkStats($orderCodes, $orderIds);

        return $rows->map(function (array $row) use ($ordersByCode, $receiptLinks, $issueLinks) {
            $code = $this->code($row['production_order'] ?? '');
            $orderLines = $ordersByCode->get($code, collect());
            $receipt = $receiptLinks->get($code, $this->emptyLinkStats());
            $issue = $issueLinks->get($code, $this->emptyLinkStats());
            $warnings = $this->summaryWarnings($row, $receipt, $issue);
            $linked = (int) $receipt['linked'] + (int) $issue['linked'];
            $total = (int) $receipt['total'] + (int) $issue['total'];

            $row['canonical_order_id'] = $this->canonicalOrderId($orderLines);
            $row['order_ids'] = $orderLines->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
            $row['link_integrity'] = [
                'linked' => $linked,
                'total' => $total,
                'percent' => $total > 0 ? (int) round($linked / $total * 100) : 100,
                'receipt' => $receipt,
                'issue' => $issue,
            ];
            $row['warnings'] = $warnings;
            $row['warning_count'] = count($warnings);
            $row['has_error'] = collect($warnings)->contains(fn ($warning) => $warning['severity'] === 'error');

            return $row;
        });
    }

    public function detail(InternalProductionOrder $selected): array
    {
        $orderCode = trim((string) $selected->production_order);
        $orders = InternalProductionOrder::query()
            ->where('production_order', $orderCode)
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->get();
        $orderIds = $orders->pluck('id')->map(fn ($id) => (int) $id)->values();
        $orderCodes = collect([$this->code($orderCode)]);
        $receipts = $this->receiptDocuments($orderCodes, $orderIds);
        $issues = $this->issueDocuments($orderCodes, $orderIds);
        $bom = $this->bomService->aggregate(collect([$orderCode]));
        $operations = $this->operations($orderCode, $orders);
        $planned = $this->plannedQuantity($orders->where('is_active', true));
        $received = (float) collect($receipts)->sum('quantity');
        $customerIssued = (float) collect($issues)
            ->where('issue_type', 'customer')
            ->sum('quantity');
        $receiptLinks = $this->receiptLinkStats($orderCodes, $orderIds)->get($this->code($orderCode), $this->emptyLinkStats());
        $issueLinks = $this->issueLinkStats($orderCodes, $orderIds)->get($this->code($orderCode), $this->emptyLinkStats());
        $warnings = $this->detailWarnings($bom, $operations, $planned, $received, $customerIssued, $receiptLinks, $issueLinks);

        return [
            'canonical_order_id' => $this->canonicalOrderId($orders),
            'order_ids' => $orderIds->all(),
            'production_order' => $orderCode,
            'customer' => trim((string) $selected->customer),
            'purchase_order' => trim((string) $selected->purchase_order),
            'planned_quantity' => $planned,
            'received_quantity' => $received,
            'customer_issue_quantity' => $customerIssued,
            'items' => $orders->map(fn ($order) => [
                'id' => (int) $order->id,
                'item_code' => trim((string) ($order->standard_item_code ?: $order->item_code)),
                'source_item_code' => trim((string) $order->item_code),
                'variant_item_code' => $this->variantItemCode($order),
                'variant_parent_id' => $order->variant_parent_id ? (int) $order->variant_parent_id : null,
                'is_variant' => (bool) $order->variant_parent_id,
                'is_variant_parent' => (bool) $order->is_variant_parent,
                'description' => trim((string) $order->description),
                'size' => trim((string) $order->size),
                'color' => trim((string) $order->color),
                'quantity' => (float) $order->order_quantity,
                'unit' => trim((string) $order->unit),
                'is_active' => (bool) $order->is_active,
            ])->values(),
            'bom' => $bom,
            'operations' => $operations,
            'receipts' => $receipts,
            'issues' => $issues,
            'link_integrity' => [
                'receipt' => $receiptLinks,
                'issue' => $issueLinks,
            ],
            'warnings' => $warnings,
        ];
    }

    private function receiptLinkStats(Collection $orderCodes, Collection $orderIds): Collection
    {
        $orderExpression = "UPPER(TRIM(COALESCE(linked_order.production_order, NULLIF(line.production_order, ''))))";

        return DB::connection('internal')->table('internal_material_receipt_lines as line')
            ->join('internal_material_receipts as receipt', 'receipt.id', '=', 'line.receipt_id')
            ->leftJoin('internal_production_orders as linked_order', 'linked_order.id', '=', 'line.production_order_id')
            ->where(function ($query) use ($orderCodes, $orderIds) {
                $query->whereIn(DB::raw('UPPER(TRIM(line.production_order))'), $orderCodes->all());
                if ($orderIds->isNotEmpty()) {
                    $query->orWhereIn('line.production_order_id', $orderIds->all());
                }
            })
            ->where(function ($query) {
                $query->where('receipt.source', 'Phieu nhap thanh pham')
                    ->orWhere('receipt.receipt_code', 'like', 'PNTP-%');
            })
            ->selectRaw("{$orderExpression} as order_code")
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN linked_order.id IS NOT NULL THEN 1 ELSE 0 END) as linked')
            ->selectRaw("SUM(CASE WHEN linked_order.id IS NULL THEN 1 ELSE 0 END) as unlinked")
            ->selectRaw("SUM(CASE WHEN linked_order.id IS NOT NULL AND NULLIF(TRIM(line.production_order), '') IS NOT NULL AND UPPER(TRIM(line.production_order)) <> UPPER(TRIM(linked_order.production_order)) THEN 1 ELSE 0 END) as mismatched")
            ->groupBy('order_code')
            ->get()
            ->mapWithKeys(fn ($row) => [$this->code($row->order_code) => $this->linkStatsArray($row)]);
    }

    private function issueLinkStats(Collection $orderCodes, Collection $orderIds): Collection
    {
        $orderExpression = "UPPER(TRIM(COALESCE(linked_order.production_order, NULLIF(line.production_order, ''))))";

        return DB::connection('internal')->table('internal_material_issue_lines as line')
            ->join('internal_material_issues as issue', 'issue.id', '=', 'line.issue_id')
            ->leftJoin('internal_production_orders as linked_order', 'linked_order.id', '=', 'line.production_order_id')
            ->where('issue.status', 'posted')
            ->where(function ($query) use ($orderCodes, $orderIds) {
                $query->whereIn(DB::raw('UPPER(TRIM(line.production_order))'), $orderCodes->all());
                if ($orderIds->isNotEmpty()) {
                    $query->orWhereIn('line.production_order_id', $orderIds->all());
                }
            })
            ->selectRaw("{$orderExpression} as order_code")
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN linked_order.id IS NOT NULL THEN 1 ELSE 0 END) as linked')
            ->selectRaw("SUM(CASE WHEN linked_order.id IS NULL THEN 1 ELSE 0 END) as unlinked")
            ->selectRaw("SUM(CASE WHEN linked_order.id IS NOT NULL AND NULLIF(TRIM(line.production_order), '') IS NOT NULL AND UPPER(TRIM(line.production_order)) <> UPPER(TRIM(linked_order.production_order)) THEN 1 ELSE 0 END) as mismatched")
            ->groupBy('order_code')
            ->get()
            ->mapWithKeys(fn ($row) => [$this->code($row->order_code) => $this->linkStatsArray($row)]);
    }

    private function receiptDocuments(Collection $orderCodes, Collection $orderIds): array
    {
        return DB::connection('internal')->table('internal_material_receipt_lines as line')
            ->join('internal_material_receipts as receipt', 'receipt.id', '=', 'line.receipt_id')
            ->leftJoin('internal_production_orders as linked_order', 'linked_order.id', '=', 'line.production_order_id')
            ->where(function ($query) use ($orderCodes, $orderIds) {
                $query->whereIn(DB::raw('UPPER(TRIM(line.production_order))'), $orderCodes->all());
                if ($orderIds->isNotEmpty()) {
                    $query->orWhereIn('line.production_order_id', $orderIds->all());
                }
            })
            ->where(function ($query) {
                $query->where('receipt.source', 'Phieu nhap thanh pham')
                    ->orWhere('receipt.receipt_code', 'like', 'PNTP-%');
            })
            ->select('receipt.id', 'receipt.receipt_code', 'receipt.receipt_date', 'receipt.status', 'receipt.source')
            ->selectRaw('SUM(line.quantity) as quantity')
            ->selectRaw('COUNT(*) as line_count')
            ->selectRaw('SUM(CASE WHEN linked_order.id IS NULL THEN 1 ELSE 0 END) as unlinked_lines')
            ->groupBy('receipt.id', 'receipt.receipt_code', 'receipt.receipt_date', 'receipt.status', 'receipt.source')
            ->orderBy('receipt.receipt_date')
            ->orderBy('receipt.id')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'code' => $row->receipt_code,
                'date' => $row->receipt_date,
                'status' => $row->status,
                'source' => $row->source,
                'quantity' => (float) $row->quantity,
                'line_count' => (int) $row->line_count,
                'unlinked_lines' => (int) $row->unlinked_lines,
            ])->all();
    }

    private function issueDocuments(Collection $orderCodes, Collection $orderIds): array
    {
        return DB::connection('internal')->table('internal_material_issue_lines as line')
            ->join('internal_material_issues as issue', 'issue.id', '=', 'line.issue_id')
            ->leftJoin('internal_production_orders as linked_order', 'linked_order.id', '=', 'line.production_order_id')
            ->where('issue.status', 'posted')
            ->where(function ($query) use ($orderCodes, $orderIds) {
                $query->whereIn(DB::raw('UPPER(TRIM(line.production_order))'), $orderCodes->all());
                if ($orderIds->isNotEmpty()) {
                    $query->orWhereIn('line.production_order_id', $orderIds->all());
                }
            })
            ->select('issue.id', 'issue.issue_code', 'issue.issue_date', 'issue.status', 'issue.issue_type')
            ->selectRaw('SUM(line.quantity) as quantity')
            ->selectRaw('COUNT(*) as line_count')
            ->selectRaw('SUM(CASE WHEN linked_order.id IS NULL THEN 1 ELSE 0 END) as unlinked_lines')
            ->groupBy('issue.id', 'issue.issue_code', 'issue.issue_date', 'issue.status', 'issue.issue_type')
            ->orderBy('issue.issue_date')
            ->orderBy('issue.id')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'code' => $row->issue_code,
                'date' => $row->issue_date,
                'status' => $row->status,
                'issue_type' => $row->issue_type,
                'quantity' => (float) $row->quantity,
                'line_count' => (int) $row->line_count,
                'unlinked_lines' => (int) $row->unlinked_lines,
            ])->all();
    }

    private function operations(string $orderCode, Collection $orders): array
    {
        $itemCodes = $orders->map(fn ($order) => $this->code($order->standard_item_code ?: $order->item_code))->filter()->unique();
        $routing = DB::connection('internal')->table('internal_product_routings as routing')
            ->join('internal_product_bom_profiles as profile', 'profile.id', '=', 'routing.profile_id')
            ->where('profile.status', 'active')
            ->whereIn('profile.item_code', $itemCodes->all())
            ->select('routing.operation_code', 'routing.operation_name', 'routing.sequence')
            ->orderBy('routing.sequence')
            ->get()
            ->unique('operation_code');
        $progress = DB::connection('internal')->table('internal_production_operation_progress')
            ->where('production_order_code', $orderCode)
            ->get()
            ->keyBy(fn ($row) => $this->code($row->operation_code));
        $activity = DB::connection('internal')->table('internal_production_activities as activity')
            ->join('internal_production_activity_lines as line', 'line.activity_id', '=', 'activity.id')
            ->where('activity.production_order_code', $orderCode)
            ->where('activity.status', 'posted')
            ->select('activity.operation_code')
            ->selectRaw('COUNT(DISTINCT activity.id) as document_count')
            ->selectRaw('SUM(line.good_quantity) as good_quantity')
            ->selectRaw('SUM(line.defect_quantity) as defect_quantity')
            ->groupBy('activity.operation_code')
            ->get()
            ->keyBy(fn ($row) => $this->code($row->operation_code));

        return $routing->map(function ($route) use ($progress, $activity) {
            $code = $this->code($route->operation_code);
            $saved = $progress->get($code);
            $actual = $activity->get($code);

            return [
                'code' => $code,
                'name' => $route->operation_name,
                'sequence' => (int) $route->sequence,
                'status' => $saved->status ?? 'pending',
                'good_quantity' => (float) ($actual->good_quantity ?? 0),
                'defect_quantity' => (float) ($actual->defect_quantity ?? 0),
                'document_count' => (int) ($actual->document_count ?? 0),
                'updated_by' => trim((string) ($saved->updated_by ?? '')),
                'note' => trim((string) ($saved->note ?? '')),
            ];
        })->values()->all();
    }

    private function summaryWarnings(array $row, array $receipt, array $issue): array
    {
        $warnings = [];
        if (!($row['lifecycle']['bom_complete'] ?? false)) {
            $warnings[] = $this->warning('missing_bom', 'warning', 'Chưa đủ BOM cho các mã trong lệnh.');
        }
        if (empty($row['lifecycle']['operations'])) {
            $warnings[] = $this->warning('missing_routing', 'warning', 'Chưa có tuyến công đoạn.');
        }
        $this->appendLinkWarnings($warnings, $receipt, $issue);
        $planned = (float) ($row['planned_quantity'] ?? 0);
        $received = (float) ($row['received_quantity'] ?? 0);
        $shipped = (float) ($row['customer_issue_quantity'] ?? 0);
        if ($planned > 0 && $received > $planned + 0.000001) {
            $warnings[] = $this->warning('receipt_over_plan', 'error', 'Nhập thành phẩm vượt số lượng kế hoạch.');
        }
        if ($shipped > $received + 0.000001) {
            $warnings[] = $this->warning('shipment_over_receipt', 'error', 'Xuất khách vượt số lượng đã nhập kho.');
        }

        return $warnings;
    }

    private function detailWarnings(array $bom, array $operations, float $planned, float $received, float $shipped, array $receipt, array $issue): array
    {
        $warnings = [];
        if (collect($bom['missing_items'] ?? [])->isNotEmpty()) {
            $warnings[] = $this->warning('missing_bom', 'warning', 'Có mã thành phẩm chưa có BOM.');
        }
        $materials = collect($bom['data'] ?? []);
        $short = $materials->where('stock_status', 'short')->count();
        $over = $materials->filter(fn ($line) => (float) ($line['over_issued_quantity'] ?? 0) > 0.000001)->count();
        if ($short > 0) {
            $warnings[] = $this->warning('material_shortage', 'error', "Thiếu tồn {$short} dòng vật tư.");
        }
        if ($over > 0) {
            $warnings[] = $this->warning('material_over_issue', 'error', "Có {$over} dòng xuất vượt BOM.");
        }
        if (!$operations) {
            $warnings[] = $this->warning('missing_routing', 'warning', 'Chưa có tuyến công đoạn.');
        }
        $this->appendLinkWarnings($warnings, $receipt, $issue);
        if ($planned > 0 && $received > $planned + 0.000001) {
            $warnings[] = $this->warning('receipt_over_plan', 'error', 'Nhập thành phẩm vượt số lượng kế hoạch.');
        }
        if ($shipped > $received + 0.000001) {
            $warnings[] = $this->warning('shipment_over_receipt', 'error', 'Xuất khách vượt số lượng đã nhập kho.');
        }

        return $warnings;
    }

    private function appendLinkWarnings(array &$warnings, array $receipt, array $issue): void
    {
        $unlinked = (int) $receipt['unlinked'] + (int) $issue['unlinked'];
        $mismatched = (int) $receipt['mismatched'] + (int) $issue['mismatched'];
        if ($unlinked > 0) {
            $warnings[] = $this->warning('unlinked_documents', 'warning', "Có {$unlinked} dòng chứng từ chưa liên kết ID lệnh.");
        }
        if ($mismatched > 0) {
            $warnings[] = $this->warning('mismatched_documents', 'error', "Có {$mismatched} dòng chứng từ liên kết sai mã lệnh.");
        }
    }

    private function plannedQuantity(Collection $orders): float
    {
        $sourceQuantities = $orders->map(function ($order) {
            $raw = is_array($order->raw_data) ? $order->raw_data : [];
            return (float) ($raw['_internal_variant']['source_quantity'] ?? 0);
        })->filter(fn ($quantity) => $quantity > 0);

        return $sourceQuantities->isNotEmpty()
            ? (float) $sourceQuantities->max()
            : (float) $orders->sum('order_quantity');
    }

    private function canonicalOrderId(Collection $orders): ?int
    {
        $parentIds = $orders->pluck('variant_parent_id')->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($parentIds->count() === 1) {
            return (int) $parentIds->first();
        }

        $order = $orders->first(fn ($row) => (bool) $row->is_variant_parent)
            ?: $orders->first(fn ($row) => !$row->variant_parent_id)
            ?: $orders->first();

        return $order ? (int) $order->id : null;
    }

    private function variantItemCode(InternalProductionOrder $order): ?string
    {
        $source = trim((string) $order->item_code);
        $variant = trim((string) $order->standard_item_code);

        return $variant !== '' && $this->code($variant) !== $this->code($source) ? $variant : null;
    }

    private function linkStatsArray($row): array
    {
        return [
            'total' => (int) $row->total,
            'linked' => (int) $row->linked,
            'unlinked' => (int) $row->unlinked,
            'mismatched' => (int) $row->mismatched,
        ];
    }

    private function emptyLinkStats(): array
    {
        return ['total' => 0, 'linked' => 0, 'unlinked' => 0, 'mismatched' => 0];
    }

    private function warning(string $code, string $severity, string $message): array
    {
        return compact('code', 'severity', 'message');
    }

    private function code($value): string
    {
        return mb_strtoupper(trim((string) $value));
    }
}
