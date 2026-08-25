<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\NormalizesDateInput;
use App\Models\InternalMaterialIssueLine;
use App\Models\InternalMaterialOrderAllocation;
use App\Models\InternalMaterialReturn;
use App\Models\InternalProductionBomDraft;
use App\Models\InternalProductionOrder;
use App\Models\InternalInventoryCount;
use App\Models\InventoryPackage;
use App\Services\InternalAudit;
use App\Services\InternalDocumentNumber;
use App\Services\InternalStockMovementService;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class InternalProductionMaterialController extends Controller
{
    use NormalizesDateInput;

    public function index()
    {
        return view('client.production-material-control');
    }

    public function data(Request $request)
    {
        $keyword = mb_strtoupper(trim((string) $request->query('keyword', '')));
        $status = trim((string) $request->query('status', 'open'));
        $limit = min(max((int) $request->query('limit', 150), 25), 500);

        $rows = InternalMaterialOrderAllocation::query()
            ->join('internal_material_issue_lines as line', 'line.id', '=', 'internal_material_order_allocations.issue_line_id')
            ->join('internal_material_issues as issue', 'issue.id', '=', 'line.issue_id')
            ->leftJoin('internal_production_orders as production', 'production.id', '=', 'internal_material_order_allocations.production_order_id')
            ->where('issue.status', 'posted')
            ->select(
                'internal_material_order_allocations.*',
                'issue.id as issue_id',
                'issue.issue_code',
                'issue.issue_date',
                'line.internal_item_code as material_item_code',
                'line.ma_hh',
                'line.ten_hh as material_name',
                'line.base_dvt',
                'line.dvt',
                'line.component_role',
                'production.customer',
                'production.purchase_order',
                'production.description as finished_item_name',
                'production.order_quantity'
            )
            ->orderByDesc('issue.issue_date')
            ->orderByDesc('internal_material_order_allocations.id')
            ->limit(3000)
            ->get();

        $grouped = $rows->groupBy('production_order_code')->map(function ($items, $orderCode) {
            $issued = (float) $items->sum('allocated_quantity');
            $returned = (float) $items->sum('returned_quantity');
            $scrap = (float) $items->sum('scrap_quantity');
            $consumed = max(0, $issued - $returned);
            $first = $items->first();

            return [
                'production_order' => $orderCode,
                'customer' => trim((string) $first->customer),
                'purchase_order' => trim((string) $first->purchase_order),
                'finished_item_code' => trim((string) ($first->finished_item_code ?: '')),
                'finished_item_name' => trim((string) $first->finished_item_name),
                'order_quantity' => (float) ($first->order_quantity ?? 0),
                'issued_quantity' => $issued,
                'returned_quantity' => $returned,
                'scrap_quantity' => $scrap,
                'consumed_quantity' => $consumed,
                'open_quantity' => max(0, $issued - $returned - $scrap),
                'issue_codes' => $items->pluck('issue_code')->filter()->unique()->values(),
                'materials' => $items->map(function ($row) {
                    return [
                        'allocation_id' => (int) $row->id,
                        'issue_line_id' => (int) $row->issue_line_id,
                        'issue_code' => $row->issue_code,
                        'issue_date' => $row->issue_date ? substr((string) $row->issue_date, 0, 10) : null,
                        'material_item_code' => trim((string) ($row->material_item_code ?: $row->ma_hh)),
                        'material_name' => trim((string) $row->material_name),
                        'component_role' => trim((string) ($row->component_role ?: 'CHUNG')),
                        'unit' => trim((string) ($row->base_dvt ?: $row->dvt)),
                        'allocated_quantity' => (float) $row->allocated_quantity,
                        'returned_quantity' => (float) $row->returned_quantity,
                        'scrap_quantity' => (float) $row->scrap_quantity,
                        'remaining_quantity' => max(0, (float) $row->allocated_quantity - (float) $row->returned_quantity - (float) $row->scrap_quantity),
                    ];
                })->values(),
            ];
        })->values();

        if ($keyword !== '') {
            $grouped = $grouped->filter(function ($row) use ($keyword) {
                $haystack = mb_strtoupper(implode(' ', [
                    $row['production_order'], $row['customer'], $row['purchase_order'],
                    $row['finished_item_code'], $row['finished_item_name'],
                    $row['materials']->pluck('material_item_code')->implode(' '),
                    $row['materials']->pluck('material_name')->implode(' '),
                ]));
                return mb_strpos($haystack, $keyword) !== false;
            })->values();
        }

        if ($status === 'open') {
            $grouped = $grouped->where('open_quantity', '>', 0)->values();
        } elseif ($status === 'closed') {
            $grouped = $grouped->where('open_quantity', '<=', 0)->values();
        }

        $filteredOrderCount = $grouped->count();
        $grouped = $grouped->take($limit)->values();

        $bomStatuses = InternalProductionBomDraft::query()
            ->whereIn('production_order_code', $grouped->pluck('production_order')->all())
            ->get()
            ->groupBy('production_order_code');
        $grouped = $grouped->map(function ($row) use ($bomStatuses) {
            $bom = $bomStatuses->get($row['production_order'], collect());
            $row['bom_count'] = $bom->count();
            $row['bom_status'] = $bom->contains('status', 'approved') ? 'approved' : ($bom->isNotEmpty() ? 'draft' : 'none');
            return $row;
        });

        $visibleOrderCodes = $grouped->pluck('production_order')->filter()->values();
        return response()->json([
            'data' => $grouped,
            'summary' => [
                'order_count' => $grouped->count(),
                'filtered_order_count' => $filteredOrderCount,
                'material_line_count' => (int) $grouped->sum(fn ($row) => $row['materials']->count()),
                'return_count' => $visibleOrderCodes->isEmpty() ? 0 : InternalMaterialReturn::query()
                    ->whereIn('production_order_code', $visibleOrderCodes->all())
                    ->count(),
                'open_material_count' => (int) $grouped->sum(fn ($row) => $row['materials']->where('remaining_quantity', '>', 0)->count()),
                'draft_bom_count' => (int) $grouped->where('bom_status', 'draft')->count(),
            ],
        ]);
    }

    public function storeReturn(Request $request)
    {
        $data = $request->validate([
            'return_date' => 'required|date',
            'production_order' => 'required|string|max:100',
            'returned_by' => 'nullable|string|max:150',
            'received_by' => 'nullable|string|max:150',
            'note' => 'nullable|string|max:1000',
            'lines' => 'required|array|min:1|max:200',
            'lines.*.allocation_id' => 'required|integer',
            'lines.*.reusable_quantity' => 'nullable|numeric|min:0',
            'lines.*.scrap_quantity' => 'nullable|numeric|min:0',
            'lines.*.location_code' => 'nullable|string|max:100',
            'lines.*.note' => 'nullable|string|max:500',
        ]);
        $data = $this->normalizeDateFields($data, ['return_date']);

        $return = DB::connection('internal')->transaction(function () use ($data) {
            $return = InternalMaterialReturn::query()->create([
                'return_code' => app(InternalDocumentNumber::class)->next('PTVT', 5),
                'return_date' => $data['return_date'],
                'production_order_code' => trim($data['production_order']),
                'returned_by' => trim((string) ($data['returned_by'] ?? '')),
                'received_by' => trim((string) ($data['received_by'] ?? '')),
                'status' => 'posted',
                'note' => trim((string) ($data['note'] ?? '')),
            ]);

            $issueIds = [];
            foreach ($data['lines'] as $index => $input) {
                $allocation = InternalMaterialOrderAllocation::query()
                    ->where('production_order_code', trim($data['production_order']))
                    ->lockForUpdate()
                    ->findOrFail((int) $input['allocation_id']);
                $line = InternalMaterialIssueLine::query()->with('issue')->findOrFail($allocation->issue_line_id);
                $reusable = (float) ($input['reusable_quantity'] ?? 0);
                $scrap = (float) ($input['scrap_quantity'] ?? 0);
                $remaining = (float) $allocation->allocated_quantity
                    - (float) $allocation->returned_quantity
                    - (float) $allocation->scrap_quantity;

                if (($reusable + $scrap) <= 0) {
                    continue;
                }
                if (($reusable + $scrap) - $remaining > 0.000001) {
                    throw new HttpResponseException(response()->json([
                        'message' => 'Dong ' . ($index + 1) . ' tra vuot so luong con tai san xuat.',
                    ], 422));
                }

                $returnLine = $return->lines()->create([
                    'order_allocation_id' => $allocation->id,
                    'issue_line_id' => $line->id,
                    'material_item_code' => trim((string) ($line->internal_item_code ?: $line->ma_hh)),
                    'unit' => trim((string) ($line->base_dvt ?: $line->dvt)),
                    'reusable_quantity' => $reusable,
                    'scrap_quantity' => $scrap,
                    'location_code' => mb_strtoupper(trim((string) ($input['location_code'] ?? ''))),
                    'note' => mb_substr(trim((string) ($input['note'] ?? '')), 0, 500),
                ]);

                if ($reusable > 0) {
                    $package = app(InternalStockMovementService::class)->receive([
                        'ma_hh' => $line->ma_hh,
                        'internal_item_code' => $line->internal_item_code,
                        'size' => $line->size,
                        'color' => $line->color,
                        'side' => $line->side,
                        'quantity' => $reusable,
                        'location_code' => $returnLine->location_code,
                    ], trim((string) ($line->issue->warehouse_code ?? '')), $data['return_date'], 'Tra vat tu ' . $return->return_code . ' - lenh ' . $data['production_order']);
                    $returnLine->inventory_package_id = $package->id;
                    $returnLine->save();
                }

                $allocation->returned_quantity = (float) $allocation->returned_quantity + $reusable;
                $allocation->scrap_quantity = (float) $allocation->scrap_quantity + $scrap;
                $allocation->save();
                $issueIds[$line->issue_id] = true;
            }

            if ($return->lines()->count() === 0) {
                throw new HttpResponseException(response()->json(['message' => 'Nhap it nhat mot so luong tra hoac phe.'], 422));
            }

            $return->issue_id = count($issueIds) === 1 ? (int) array_key_first($issueIds) : null;
            $return->save();

            return $return->load('lines');
        });

        app(InternalAudit::class)->model('material.returned', $return, [
            'line_count' => $return->lines->count(),
            'reusable_quantity' => (float) $return->lines->sum('reusable_quantity'),
            'scrap_quantity' => (float) $return->lines->sum('scrap_quantity'),
        ], $request);

        return response()->json(['message' => 'Da ghi nhan phieu tra vat tu.', 'data' => $return]);
    }

    public function returns(Request $request)
    {
        $orderCode = trim((string) $request->query('production_order', ''));
        return response()->json([
            'data' => InternalMaterialReturn::query()
                ->with('lines')
                ->when($orderCode !== '', fn ($query) => $query->where('production_order_code', $orderCode))
                ->orderByDesc('return_date')
                ->orderByDesc('id')
                ->limit(300)
                ->get(),
        ]);
    }

    public function destroyReturn(InternalMaterialReturn $materialReturn)
    {
        DB::connection('internal')->transaction(function () use ($materialReturn) {
            $materialReturn->load('lines');
            foreach ($materialReturn->lines as $line) {
                if ((float) $line->reusable_quantity > 0) {
                    $package = InventoryPackage::query()->lockForUpdate()->find($line->inventory_package_id);
                    if (!$package || (float) $package->quantity + 0.000001 < (float) $line->reusable_quantity) {
                        throw new HttpResponseException(response()->json([
                            'message' => 'Vật tư trả của phiếu này đã được xuất tiếp. Không thể hủy phiếu trả.',
                        ], 409));
                    }
                    $package->quantity = (float) $package->quantity - (float) $line->reusable_quantity;
                    $package->save();

                    if ($package->inventory_count_id) {
                        $count = InternalInventoryCount::query()->lockForUpdate()->find($package->inventory_count_id);
                        if ($count) {
                            $count->counted_quantity = (float) $count->counted_quantity - (float) $line->reusable_quantity;
                            $count->save();
                        }
                    }
                }

                $allocation = InternalMaterialOrderAllocation::query()->lockForUpdate()->find($line->order_allocation_id);
                if ($allocation) {
                    $allocation->returned_quantity = max(0, (float) $allocation->returned_quantity - (float) $line->reusable_quantity);
                    $allocation->scrap_quantity = max(0, (float) $allocation->scrap_quantity - (float) $line->scrap_quantity);
                    $allocation->save();
                }
            }
            $materialReturn->delete();
        });

        return response()->json(['message' => 'Đã hủy phiếu trả và hoàn nguyên tồn kho.']);
    }

    public function inferBom(Request $request)
    {
        $data = $request->validate(['production_order' => 'required|string|max:100']);
        $orderCode = trim($data['production_order']);
        $allocations = InternalMaterialOrderAllocation::query()
            ->with('issueLine')
            ->where('production_order_code', $orderCode)
            ->get();

        if ($allocations->isEmpty()) {
            return response()->json(['message' => 'Lenh chua co phieu xuat vat tu trung tam.'], 422);
        }

        $output = (float) DB::connection('internal')->table('internal_material_receipt_lines as line')
            ->join('internal_material_receipts as receipt', 'receipt.id', '=', 'line.receipt_id')
            ->where('line.production_order', $orderCode)
            ->where(function ($query) {
                $query->whereNull('receipt.status')->orWhere('receipt.status', '<>', 'cancelled');
            })
            ->sum('line.quantity');

        if ($output <= 0) {
            return response()->json([
                'message' => 'Lenh chua co san luong nhap kho. Chua the suy ra dinh muc thuc te.',
            ], 422);
        }

        $order = InternalProductionOrder::query()
            ->where('production_order', $orderCode)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();
        $created = [];
        $groups = $allocations->groupBy(function ($allocation) {
            $line = $allocation->issueLine;
            return mb_strtoupper(implode('|', [
                trim((string) ($line->internal_item_code ?: $line->ma_hh)),
                trim((string) ($line->component_role ?: 'CHUNG')),
                trim((string) ($line->base_dvt ?: $line->dvt)),
            ]));
        });

        foreach ($groups as $items) {
            $first = $items->first();
            $line = $first->issueLine;
            $issued = (float) $items->sum('allocated_quantity');
            $returned = (float) $items->sum('returned_quantity');
            $scrap = (float) $items->sum('scrap_quantity');
            $consumed = max(0, $issued - $returned);
            $attributes = [
                'production_order_code' => $orderCode,
                'material_item_code' => trim((string) ($line->internal_item_code ?: $line->ma_hh)),
                'component_role' => trim((string) ($line->component_role ?: 'CHUNG')),
            ];
            $existing = InternalProductionBomDraft::query()->where($attributes)->first();
            if ($existing && $existing->status === 'approved') {
                $created[] = $existing;
                continue;
            }

            $created[] = InternalProductionBomDraft::query()->updateOrCreate($attributes, [
                'production_order_id' => $order->id ?? null,
                'finished_item_code' => trim((string) ($first->finished_item_code ?: ($order->standard_item_code ?? $order->item_code ?? ''))),
                'unit' => trim((string) ($line->base_dvt ?: $line->dvt)),
                'issued_quantity' => $issued,
                'returned_quantity' => $returned,
                'scrap_quantity' => $scrap,
                'actual_consumed_quantity' => $consumed,
                'good_output_quantity' => $output,
                'consumption_per_unit' => $consumed / $output,
                'status' => 'draft',
                'source_issue_ids' => $items->pluck('issueLine.issue_id')->filter()->unique()->values()->all(),
                'generated_at' => now(),
                'note' => 'Suy ra tu phieu xuat, phieu tra va san luong nhap kho.',
            ]);
        }

        return response()->json(['message' => 'Da tao BOM nhap tu du lieu thuc te.', 'data' => $created]);
    }

    public function approveBom(Request $request)
    {
        $data = $request->validate([
            'production_order' => 'required|string|max:100',
            'ids' => 'nullable|array',
            'ids.*' => 'integer',
        ]);
        $query = InternalProductionBomDraft::query()
            ->where('production_order_code', trim($data['production_order']))
            ->where('status', 'draft');
        if (!empty($data['ids'])) {
            $query->whereIn('id', $data['ids']);
        }
        $count = $query->update(['status' => 'approved', 'approved_at' => now()]);

        return response()->json(['message' => 'Da duyet ' . $count . ' dong BOM.', 'approved_count' => $count]);
    }

    public function bom(Request $request)
    {
        $orderCode = trim((string) $request->query('production_order', ''));
        return response()->json([
            'data' => InternalProductionBomDraft::query()
                ->when($orderCode !== '', fn ($query) => $query->where('production_order_code', $orderCode))
                ->orderByDesc('generated_at')
                ->limit(500)
                ->get(),
        ]);
    }
}
