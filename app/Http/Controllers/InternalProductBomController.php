<?php

namespace App\Http\Controllers;

use App\Models\InternalItemCatalog;
use App\Models\InternalBtpProductionOrder;
use App\Models\InternalProductBomProfile;
use App\Models\InternalProductionOrder;
use App\Models\InternalProductionOrderBomSnapshot;
use App\Services\InternalAudit;
use App\Services\InternalUnitConverter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InternalProductBomController extends Controller
{
    public function index()
    {
        return view('client.product-bom-editor');
    }

    public function profiles(Request $request)
    {
        $keyword = trim((string) $request->query('keyword', ''));
        $rows = InternalProductBomProfile::query()
            ->withCount(['lines', 'routings'])
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->where(function ($nested) use ($keyword) {
                    $nested->where('item_code', 'like', '%' . $keyword . '%')
                        ->orWhere('item_name', 'like', '%' . $keyword . '%');
                });
            })
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function show(Request $request)
    {
        $itemCode = $this->code($request->query('item_code', ''));
        if ($itemCode === '') {
            return response()->json(['message' => 'Chọn mã hàng để xem BOM.'], 422);
        }

        $profile = InternalProductBomProfile::query()
            ->with(['lines', 'routings'])
            ->where('item_code', $itemCode)
            ->first();
        $catalog = $this->catalog($itemCode);

        return response()->json([
            'data' => $profile,
            'catalog' => $catalog ? [
                'item_code' => $catalog->item_code,
                'item_name' => $catalog->item_name,
                'unit' => $catalog->unit,
                'image_url' => $catalog->image_url ?? null,
            ] : null,
        ]);
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'item_code' => 'required|string|max:200',
            'item_name' => 'nullable|string|max:500',
            'unit' => 'nullable|string|max:50',
            'note' => 'nullable|string|max:1000',
            'operations' => 'nullable|array|max:30',
            'operations.*.operation_code' => 'required_with:operations|string|max:50',
            'operations.*.operation_name' => 'required_with:operations|string|max:200',
            'operations.*.work_center' => 'nullable|string|max:150',
            'operations.*.is_outsourced' => 'nullable|boolean',
            'operations.*.note' => 'nullable|string|max:500',
            'materials' => 'required|array|min:1|max:200',
            'materials.*.material_code' => 'required|string|max:120',
            'materials.*.material_name' => 'nullable|string|max:500',
            'materials.*.component_role' => 'nullable|string|max:120',
            'materials.*.unit' => 'required|string|max:50',
            'materials.*.calculation_mode' => 'nullable|in:consumption,yield,formula',
            'materials.*.consumption_per_unit' => 'nullable|numeric|min:0',
            'materials.*.yield_quantity' => 'nullable|numeric|min:0',
            'materials.*.formula_code' => 'nullable|string|max:80',
            'materials.*.formula_output_per_unit' => 'nullable|numeric|min:0',
            'materials.*.formula_output_unit' => 'nullable|string|max:50',
            'materials.*.formula_part' => 'nullable|numeric|min:0',
            'materials.*.waste_percent' => 'nullable|numeric|min:0|max:100',
            'materials.*.round_to_whole' => 'nullable|boolean',
            'materials.*.operation_code' => 'nullable|string|max:50',
            'materials.*.note' => 'nullable|string|max:500',
        ]);

        $itemCode = $this->code($data['item_code']);
        $catalog = $this->catalog($itemCode);
        $operations = collect($data['operations'] ?? [])->map(function ($operation) {
            return [
                'operation_code' => $this->code($operation['operation_code'] ?? ''),
                'operation_name' => trim((string) ($operation['operation_name'] ?? '')),
                'work_center' => trim((string) ($operation['work_center'] ?? '')),
                'is_outsourced' => !empty($operation['is_outsourced']),
                'note' => trim((string) ($operation['note'] ?? '')),
            ];
        })->filter(fn ($operation) => $operation['operation_code'] !== '')->values();

        if ($operations->pluck('operation_code')->duplicates()->isNotEmpty()) {
            return response()->json(['message' => 'Mã công đoạn không được trùng trong cùng một mã hàng.'], 422);
        }

        $operationCodes = $operations->pluck('operation_code');
        $materials = collect($data['materials'])->map(function ($material) {
            $calculationMode = in_array(($material['calculation_mode'] ?? 'consumption'), ['yield', 'formula'], true)
                ? $material['calculation_mode']
                : 'consumption';
            $yieldQuantity = (float) ($material['yield_quantity'] ?? 0);
            $consumptionPerUnit = $calculationMode === 'yield'
                ? ($yieldQuantity > 0 ? 1 / $yieldQuantity : 0)
                : ($calculationMode === 'formula' ? 0 : (float) ($material['consumption_per_unit'] ?? 0));

            return [
                'material_code' => $this->code($material['material_code']),
                'material_name' => trim((string) ($material['material_name'] ?? '')),
                'component_role' => $this->code($material['component_role'] ?? '') ?: 'CHUNG',
                'calculation_mode' => $calculationMode,
                'formula_code' => $calculationMode === 'formula' ? $this->code($material['formula_code'] ?? '') : null,
                'formula_output_per_unit' => $calculationMode === 'formula' ? (float) ($material['formula_output_per_unit'] ?? 0) : null,
                'formula_output_unit' => $calculationMode === 'formula' ? app(InternalUnitConverter::class)->normalizeUnit($material['formula_output_unit'] ?? '') : null,
                'formula_part' => $calculationMode === 'formula' ? (float) ($material['formula_part'] ?? 0) : null,
                'unit' => mb_strtoupper(trim((string) $material['unit'])),
                'consumption_per_unit' => $consumptionPerUnit,
                'yield_quantity' => $calculationMode === 'yield' ? $yieldQuantity : null,
                'waste_percent' => (float) ($material['waste_percent'] ?? 0),
                'round_to_whole' => !empty($material['round_to_whole']),
                'operation_code' => $this->code($material['operation_code'] ?? ''),
                'note' => trim((string) ($material['note'] ?? '')),
            ];
        })->values();

        foreach ($materials->where('calculation_mode', 'formula')->groupBy('formula_code') as $formulaCode => $formulaLines) {
            if ($formulaCode === '' || $formulaLines->count() < 2) {
                return response()->json(['message' => 'Công thức pha cần mã nhóm và ít nhất 2 nguyên liệu.'], 422);
            }

            $outputQuantities = $formulaLines->pluck('formula_output_per_unit')->unique()->values();
            $outputUnits = $formulaLines->pluck('formula_output_unit')->unique()->values();
            $totalParts = (float) $formulaLines->sum('formula_part');
            if ($outputQuantities->count() !== 1 || (float) $outputQuantities->first() <= 0 || $outputUnits->count() !== 1 || !$outputUnits->first() || $totalParts <= 0) {
                return response()->json(['message' => 'Các dòng công thức ' . $formulaCode . ' phải cùng tổng hỗn hợp/PCS, cùng đơn vị và có tỷ lệ lớn hơn 0.'], 422);
            }

            foreach ($formulaLines as $index => $formulaLine) {
                $factor = app(InternalUnitConverter::class)->factor(
                    $formulaLine['material_code'],
                    $formulaLine['formula_output_unit'],
                    $formulaLine['unit']
                );
                if ($factor === null) {
                    return response()->json([
                        'message' => 'Chưa có quy đổi ' . $formulaLine['formula_output_unit'] . ' sang ' . $formulaLine['unit'] . ' cho ' . $formulaLine['material_code'] . '.',
                    ], 422);
                }

                $materials[$index] = array_merge($formulaLine, [
                    'consumption_per_unit' => (float) $formulaLine['formula_output_per_unit']
                        * ((float) $formulaLine['formula_part'] / $totalParts)
                        * $factor,
                ]);
            }
        }

        $invalidRate = $materials->search(fn ($material) => $material['consumption_per_unit'] <= 0);
        if ($invalidRate !== false) {
            return response()->json([
                'message' => 'Dòng vật tư ' . ((int) $invalidRate + 1) . ' chưa có định mức hoặc năng suất hợp lệ.',
            ], 422);
        }

        $invalidOperation = $materials->pluck('operation_code')->filter()
            ->first(fn ($code) => !$operationCodes->contains($code));
        if ($invalidOperation) {
            return response()->json([
                'message' => 'Công đoạn ' . $invalidOperation . ' của dòng vật tư chưa có trong tuyến công đoạn.',
            ], 422);
        }

        $catalogCodes = $materials->pluck('material_code')->unique()->all();
        $catalogByCode = InternalItemCatalog::query()
            ->where('is_active', true)
            ->whereIn(DB::raw('UPPER(TRIM(item_code))'), $catalogCodes)
            ->orderByDesc('source_row')
            ->get()
            ->keyBy(fn ($row) => $this->code($row->item_code));

        $profile = DB::connection('internal')->transaction(function () use ($data, $itemCode, $catalog, $operations, $materials, $catalogByCode) {
            $profile = InternalProductBomProfile::query()->where('item_code', $itemCode)->lockForUpdate()->first();
            if (!$profile) {
                $profile = new InternalProductBomProfile(['item_code' => $itemCode, 'revision' => 0]);
            }
            $profile->item_name = trim((string) ($data['item_name'] ?? '')) ?: trim((string) ($catalog->item_name ?? ''));
            $profile->unit = mb_strtoupper(trim((string) ($data['unit'] ?? ''))) ?: mb_strtoupper(trim((string) ($catalog->unit ?? '')));
            $profile->revision = (int) $profile->revision + 1;
            $profile->status = 'active';
            $profile->note = trim((string) ($data['note'] ?? ''));
            $profile->save();

            $profile->routings()->delete();
            foreach ($operations as $index => $operation) {
                $profile->routings()->create($operation + ['sequence' => $index + 1]);
            }

            $profile->lines()->delete();
            foreach ($materials as $index => $material) {
                $catalogMaterial = $catalogByCode->get($material['material_code']);
                $profile->lines()->create($material + [
                    'sequence' => $index + 1,
                    'material_name' => $material['material_name'] ?: trim((string) ($catalogMaterial->item_name ?? '')),
                    'unit' => $material['unit'] ?: mb_strtoupper(trim((string) ($catalogMaterial->unit ?? ''))),
                ]);
            }

            return $profile->fresh()->load(['lines', 'routings']);
        });

        app(InternalAudit::class)->model('product_bom.saved', $profile, [
            'revision' => $profile->revision,
            'material_count' => $profile->lines->count(),
            'operation_count' => $profile->routings->count(),
        ], $request);

        return response()->json(['message' => 'Đã lưu BOM và công đoạn của mã ' . $itemCode . '.', 'data' => $profile]);
    }

    public function destroy(InternalProductBomProfile $profile)
    {
        $code = $profile->item_code;
        $profile->delete();
        return response()->json(['message' => 'Đã xóa BOM và tuyến công đoạn của ' . $code . '.']);
    }

    public function orderNeeds(Request $request)
    {
        $orderCode = trim((string) $request->query('production_order', ''));
        if ($orderCode === '') {
            return response()->json(['message' => 'Nhập lệnh sản xuất.'], 422);
        }

        $orders = InternalProductionOrder::query()
            ->where('is_active', true)
            ->where('production_order', $orderCode)
            ->orderBy('id')
            ->get();
        if ($orders->isNotEmpty()) {
            return response()->json($this->buildOrderNeeds($orders, false));
        }

        $btpOrder = InternalBtpProductionOrder::query()
            ->with('lines')
            ->whereRaw('UPPER(TRIM(btp_order_code)) = ?', [$this->code($orderCode)])
            ->first();
        if ($btpOrder) {
            return response()->json($this->buildBtpOrderNeeds($btpOrder, false));
        }

        return response()->json(['message' => 'Không tìm thấy lệnh sản xuất hoặc lệnh BTP.'], 404);
    }

    public function snapshotOrder(Request $request)
    {
        $data = $request->validate(['production_order' => 'required|string|max:100']);
        $orders = InternalProductionOrder::query()
            ->where('is_active', true)
            ->where('production_order', trim($data['production_order']))
            ->orderBy('id')
            ->get();
        if ($orders->isNotEmpty()) {
            $result = DB::connection('internal')->transaction(fn () => $this->buildOrderNeeds($orders, true));
            return response()->json(['message' => 'Đã chốt bản BOM cho lệnh ' . trim($data['production_order']) . '.', 'data' => $result['data'], 'missing_items' => $result['missing_items']]);
        }

        $btpOrder = InternalBtpProductionOrder::query()
            ->with('lines')
            ->whereRaw('UPPER(TRIM(btp_order_code)) = ?', [$this->code($data['production_order'])])
            ->first();
        if (!$btpOrder) {
            return response()->json(['message' => 'Không tìm thấy lệnh sản xuất hoặc lệnh BTP.'], 404);
        }

        $result = DB::connection('internal')->transaction(fn () => $this->buildBtpOrderNeeds($btpOrder, true));
        return response()->json(['message' => 'Đã chốt bản BOM cho lệnh BTP ' . $btpOrder->btp_order_code . '.', 'data' => $result['data'], 'missing_items' => $result['missing_items']]);
    }

    public function aggregateOrderNeeds(Request $request)
    {
        $data = $request->validate([
            'production_orders' => 'required|array|min:1|max:100',
            'production_orders.*' => 'required|string|max:100',
        ]);
        $orderCodes = collect($data['production_orders'])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        $orders = InternalProductionOrder::query()
            ->whereIn('production_order', $orderCodes->all())
            ->where('is_active', true)
            ->orderBy('production_order')
            ->orderBy('id')
            ->get();
        $central = $this->buildOrderNeeds($orders, false);
        $foundOrders = $orders->pluck('production_order')->map(fn ($value) => trim((string) $value))->unique();
        $rows = collect($central['data']);
        $missingItems = collect($central['missing_items']);

        $btpOrders = InternalBtpProductionOrder::query()
            ->with('lines')
            ->whereIn('btp_order_code', $orderCodes->diff($foundOrders)->all())
            ->get();
        foreach ($btpOrders as $btpOrder) {
            $result = $this->buildBtpOrderNeeds($btpOrder, false);
            $rows = $rows->concat($result['data']);
            $missingItems = $missingItems->concat($result['missing_items']);
            $foundOrders->push($btpOrder->btp_order_code);
        }

        $orderMeta = $orders->groupBy('production_order')->map(function ($items) {
            $first = $items->first();
            return [
                'customer' => trim((string) $first->customer),
                'purchase_order' => trim((string) $first->purchase_order),
            ];
        });
        $summaryRows = $rows->flatMap(function ($row) use ($orderMeta) {
            $meta = $orderMeta->get($row['production_order'], ['customer' => '', 'purchase_order' => '']);
            return collect($row['materials'])->map(function ($material) use ($row, $meta) {
                return [
                    'production_order' => $row['production_order'],
                    'customer' => $meta['customer'],
                    'purchase_order' => $meta['purchase_order'],
                    'finished_item_code' => $row['item_code'],
                    'material_code' => $material['material_code'],
                    'material_name' => $material['material_name'],
                    'component_role' => $material['component_role'],
                    'unit' => $material['unit'],
                    'calculation_mode' => $material['calculation_mode'],
                    'formula_code' => $material['formula_code'] ?? null,
                    'formula_output_per_unit' => $material['formula_output_per_unit'] ?? null,
                    'formula_output_unit' => $material['formula_output_unit'] ?? null,
                    'formula_part' => $material['formula_part'] ?? null,
                    'yield_quantity' => $material['yield_quantity'],
                    'consumption_per_unit' => $material['consumption_per_unit'],
                    'waste_percent' => $material['waste_percent'],
                    'round_to_whole' => $material['round_to_whole'],
                    'exact_required_quantity' => (float) $material['exact_required_quantity'],
                ];
            });
        })->groupBy(function ($row) {
            return implode('|', [
                $row['production_order'], $row['material_code'], $row['component_role'],
                $row['unit'], $row['calculation_mode'], (string) $row['yield_quantity'],
                (string) $row['consumption_per_unit'], (string) $row['waste_percent'],
            ]);
        })->map(function ($items) {
            $first = $items->first();
            $exact = (float) $items->sum('exact_required_quantity');
            $first['exact_required_quantity'] = round($exact, 6);
            $first['required_quantity'] = $this->shouldRoundRequirement($first)
                ? (float) ceil($exact - 0.000000001)
                : round($exact, 6);
            return $first;
        })->sortBy(fn ($row) => $row['production_order'] . '|' . $row['material_code'])->values();

        $issuedByMaterial = DB::connection('internal')
            ->table('internal_material_order_allocations as allocation')
            ->join('internal_material_issue_lines as line', 'line.id', '=', 'allocation.issue_line_id')
            ->join('internal_material_issues as issue', 'issue.id', '=', 'line.issue_id')
            ->where('issue.status', 'posted')
            ->where('issue.issue_type', 'material')
            ->whereIn('allocation.production_order_code', $orderCodes->all())
            ->selectRaw("allocation.production_order_code, COALESCE(NULLIF(TRIM(line.internal_item_code), ''), line.ma_hh) as material_code, COALESCE(NULLIF(TRIM(line.component_role), ''), 'CHUNG') as component_role, COALESCE(NULLIF(TRIM(line.base_dvt), ''), line.dvt) as unit, SUM(allocation.allocated_quantity) as issued_quantity")
            ->groupBy(
                'allocation.production_order_code',
                'line.internal_item_code',
                'line.ma_hh',
                'line.component_role',
                'line.base_dvt',
                'line.dvt'
            )
            ->get()
            ->groupBy(fn ($row) => implode('|', [
                $this->code($row->production_order_code),
                $this->code($row->material_code),
                $this->code($row->component_role ?: 'CHUNG'),
                $this->code($row->unit),
            ]))
            ->map(fn ($items) => (float) $items->sum('issued_quantity'));

        $summaryRows = $summaryRows->map(function ($row) use ($issuedByMaterial) {
            $key = implode('|', [
                $this->code($row['production_order']),
                $this->code($row['material_code']),
                $this->code($row['component_role'] ?: 'CHUNG'),
                $this->code($row['unit']),
            ]);
            $issued = (float) ($issuedByMaterial->get($key, 0));
            $row['issued_quantity'] = round($issued, 6);
            $row['suggested_quantity'] = round(max(0, (float) $row['required_quantity'] - $issued), 6);
            $row['over_issued_quantity'] = round(max(0, $issued - (float) $row['required_quantity']), 6);
            return $row;
        });

        return response()->json([
            'data' => $summaryRows,
            'missing_orders' => $orderCodes->diff($foundOrders->unique())->values(),
            'missing_items' => $missingItems->unique()->values(),
        ]);
    }

    private function buildOrderNeeds($orders, bool $persist): array
    {
        $itemCodes = $orders->map(fn ($order) => $this->code($order->standard_item_code ?: $order->item_code))->filter()->unique();
        $profiles = InternalProductBomProfile::query()
            ->with(['lines', 'routings'])
            ->whereIn('item_code', $itemCodes->all())
            ->get()
            ->keyBy('item_code');
        $missing = [];
        $data = [];

        foreach ($orders as $order) {
            $itemCode = $this->code($order->standard_item_code ?: $order->item_code);
            $snapshots = InternalProductionOrderBomSnapshot::query()
                ->where('order_type', 'central')
                ->where('production_order_id', $order->id)
                ->where('order_line_id', 0)
                ->orderBy('sequence')
                ->get();
            $profile = $profiles->get($itemCode);
            if ($snapshots->isEmpty() && !$profile) {
                $missing[$itemCode] = true;
                continue;
            }

            if ($snapshots->isEmpty() && $persist) {
                foreach ($profile->lines as $line) {
                    $requirement = $this->calculateRequirement($line, (float) $order->order_quantity);
                    InternalProductionOrderBomSnapshot::query()->create([
                        'order_type' => 'central',
                        'production_order_id' => $order->id,
                        'order_line_id' => 0,
                        'production_order_code' => $order->production_order,
                        'finished_item_code' => $itemCode,
                        'bom_revision' => $profile->revision,
                        'sequence' => $line->sequence,
                        'material_code' => $line->material_code,
                        'material_name' => $line->material_name,
                        'component_role' => $line->component_role,
                        'calculation_mode' => $line->calculation_mode,
                        'formula_code' => $line->formula_code,
                        'formula_output_per_unit' => $line->formula_output_per_unit,
                        'formula_output_unit' => $line->formula_output_unit,
                        'formula_part' => $line->formula_part,
                        'unit' => $line->unit,
                        'consumption_per_unit' => $line->consumption_per_unit,
                        'yield_quantity' => $line->yield_quantity,
                        'waste_percent' => $line->waste_percent,
                        'round_to_whole' => $line->round_to_whole,
                        'order_quantity' => $order->order_quantity,
                        'required_quantity' => $requirement['required'],
                        'exact_required_quantity' => $requirement['exact'],
                        'operation_code' => $line->operation_code,
                        'snapshotted_at' => now(),
                    ]);
                }
                $snapshots = InternalProductionOrderBomSnapshot::query()
                    ->where('order_type', 'central')
                    ->where('production_order_id', $order->id)
                    ->where('order_line_id', 0)
                    ->orderBy('sequence')
                    ->get();
            }

            $sourceLines = $snapshots->isNotEmpty() ? $snapshots : $profile->lines;
            $data[] = [
                'order_type' => 'central',
                'production_order_id' => (int) $order->id,
                'production_order' => $order->production_order,
                'item_code' => $itemCode,
                'item_name' => $order->description,
                'size' => $order->size,
                'color' => $order->color,
                'order_quantity' => (float) $order->order_quantity,
                'is_snapshot' => $snapshots->isNotEmpty(),
                'bom_revision' => $snapshots->isNotEmpty() ? (int) $snapshots->first()->bom_revision : (int) $profile->revision,
                'operations' => $profile ? $profile->routings->values() : [],
                'materials' => $sourceLines->map(function ($line) use ($order, $snapshots) {
                    $requirement = $snapshots->isNotEmpty()
                        ? [
                            'exact' => (float) ($line->exact_required_quantity ?? $line->required_quantity),
                            'required' => (float) $line->required_quantity,
                        ]
                        : $this->calculateRequirement($line, (float) $order->order_quantity);
                    return [
                        'material_code' => $line->material_code,
                        'material_name' => $line->material_name,
                        'component_role' => $line->component_role,
                        'calculation_mode' => $line->calculation_mode ?: 'consumption',
                        'formula_code' => $line->formula_code,
                        'formula_output_per_unit' => $line->formula_output_per_unit !== null ? (float) $line->formula_output_per_unit : null,
                        'formula_output_unit' => $line->formula_output_unit,
                        'formula_part' => $line->formula_part !== null ? (float) $line->formula_part : null,
                        'unit' => $line->unit,
                        'consumption_per_unit' => (float) $line->consumption_per_unit,
                        'yield_quantity' => $line->yield_quantity !== null ? (float) $line->yield_quantity : null,
                        'waste_percent' => (float) $line->waste_percent,
                        'round_to_whole' => (bool) $line->round_to_whole,
                        'exact_required_quantity' => round($requirement['exact'], 6),
                        'required_quantity' => round($requirement['required'], 6),
                        'operation_code' => $line->operation_code,
                    ];
                })->values(),
            ];
        }

        return ['data' => $data, 'missing_items' => array_keys($missing)];
    }

    private function buildBtpOrderNeeds(InternalBtpProductionOrder $order, bool $persist): array
    {
        $lines = $order->lines->filter(function ($line) {
            return $this->code($line->internal_item_code ?: $line->ma_hh) !== '';
        })->values();
        $itemCodes = $lines
            ->map(fn ($line) => $this->code($line->internal_item_code ?: $line->ma_hh))
            ->unique()
            ->values();
        $profiles = InternalProductBomProfile::query()
            ->with(['lines', 'routings'])
            ->whereIn('item_code', $itemCodes->all())
            ->get()
            ->keyBy('item_code');
        $missing = [];
        $result = [];

        foreach ($lines as $line) {
            $itemCode = $this->code($line->internal_item_code ?: $line->ma_hh);
            $quantity = (float) ($line->ordered_quantity ?: $line->quantity);
            $snapshots = InternalProductionOrderBomSnapshot::query()
                ->where('order_type', 'btp')
                ->where('production_order_id', $order->id)
                ->where('order_line_id', $line->id)
                ->orderBy('sequence')
                ->get();
            $profile = $profiles->get($itemCode);

            if ($snapshots->isEmpty() && !$profile) {
                $missing[$itemCode] = true;
                continue;
            }

            if ($snapshots->isEmpty() && $persist) {
                foreach ($profile->lines as $bomLine) {
                    $requirement = $this->calculateRequirement($bomLine, $quantity);
                    InternalProductionOrderBomSnapshot::query()->create([
                        'order_type' => 'btp',
                        'production_order_id' => $order->id,
                        'order_line_id' => $line->id,
                        'production_order_code' => $order->btp_order_code,
                        'finished_item_code' => $itemCode,
                        'bom_revision' => $profile->revision,
                        'sequence' => $bomLine->sequence,
                        'material_code' => $bomLine->material_code,
                        'material_name' => $bomLine->material_name,
                        'component_role' => $bomLine->component_role,
                        'calculation_mode' => $bomLine->calculation_mode,
                        'formula_code' => $bomLine->formula_code,
                        'formula_output_per_unit' => $bomLine->formula_output_per_unit,
                        'formula_output_unit' => $bomLine->formula_output_unit,
                        'formula_part' => $bomLine->formula_part,
                        'unit' => $bomLine->unit,
                        'consumption_per_unit' => $bomLine->consumption_per_unit,
                        'yield_quantity' => $bomLine->yield_quantity,
                        'waste_percent' => $bomLine->waste_percent,
                        'round_to_whole' => $bomLine->round_to_whole,
                        'order_quantity' => $quantity,
                        'required_quantity' => $requirement['required'],
                        'exact_required_quantity' => $requirement['exact'],
                        'operation_code' => $bomLine->operation_code,
                        'snapshotted_at' => now(),
                    ]);
                }
                $snapshots = InternalProductionOrderBomSnapshot::query()
                    ->where('order_type', 'btp')
                    ->where('production_order_id', $order->id)
                    ->where('order_line_id', $line->id)
                    ->orderBy('sequence')
                    ->get();
            }

            $sourceLines = $snapshots->isNotEmpty() ? $snapshots : $profile->lines;
            $result[] = [
                'order_type' => 'btp',
                'production_order_id' => (int) $order->id,
                'production_order_line_id' => (int) $line->id,
                'production_order' => $order->btp_order_code,
                'item_code' => $itemCode,
                'item_name' => $line->ten_hh,
                'size' => $line->size,
                'color' => $line->color,
                'order_quantity' => $quantity,
                'is_snapshot' => $snapshots->isNotEmpty(),
                'bom_revision' => $snapshots->isNotEmpty() ? (int) $snapshots->first()->bom_revision : (int) $profile->revision,
                'operations' => $profile ? $profile->routings->values() : [],
                'materials' => $sourceLines->map(function ($bomLine) use ($quantity, $snapshots) {
                    $requirement = $snapshots->isNotEmpty()
                        ? [
                            'exact' => (float) ($bomLine->exact_required_quantity ?? $bomLine->required_quantity),
                            'required' => (float) $bomLine->required_quantity,
                        ]
                        : $this->calculateRequirement($bomLine, $quantity);
                    return [
                        'material_code' => $bomLine->material_code,
                        'material_name' => $bomLine->material_name,
                        'component_role' => $bomLine->component_role,
                        'calculation_mode' => $bomLine->calculation_mode ?: 'consumption',
                        'formula_code' => $bomLine->formula_code,
                        'formula_output_per_unit' => $bomLine->formula_output_per_unit !== null ? (float) $bomLine->formula_output_per_unit : null,
                        'formula_output_unit' => $bomLine->formula_output_unit,
                        'formula_part' => $bomLine->formula_part !== null ? (float) $bomLine->formula_part : null,
                        'unit' => $bomLine->unit,
                        'consumption_per_unit' => (float) $bomLine->consumption_per_unit,
                        'yield_quantity' => $bomLine->yield_quantity !== null ? (float) $bomLine->yield_quantity : null,
                        'waste_percent' => (float) $bomLine->waste_percent,
                        'round_to_whole' => (bool) $bomLine->round_to_whole,
                        'exact_required_quantity' => round($requirement['exact'], 6),
                        'required_quantity' => round($requirement['required'], 6),
                        'operation_code' => $bomLine->operation_code,
                    ];
                })->values(),
            ];
        }

        return [
            'data' => $result,
            'missing_items' => array_keys($missing),
            'order_type' => 'btp',
        ];
    }

    private function calculateRequirement($line, float $orderQuantity): array
    {
        $yieldQuantity = (float) ($line->yield_quantity ?? 0);
        $baseQuantity = ($line->calculation_mode ?? 'consumption') === 'yield' && $yieldQuantity > 0
            ? $orderQuantity / $yieldQuantity
            : $orderQuantity * (float) $line->consumption_per_unit;
        $exact = $baseQuantity * (1 + ((float) $line->waste_percent / 100));

        return [
            'exact' => $exact,
            'required' => $this->shouldRoundRequirement($line)
                ? (float) ceil($exact - 0.000000001)
                : $exact,
        ];
    }

    private function shouldRoundRequirement($line): bool
    {
        if (!empty(data_get($line, 'round_to_whole'))) {
            return true;
        }

        if (data_get($line, 'calculation_mode', 'consumption') !== 'yield') {
            return false;
        }

        return in_array($this->code(data_get($line, 'unit')), [
            'TẤM', 'TAM', 'CUỘN', 'CUON', 'THÙNG', 'THUNG', 'CÁI', 'CAI', 'PCS', 'BỘ', 'BO',
        ], true);
    }

    private function catalog(string $itemCode)
    {
        return InternalItemCatalog::query()
            ->where('is_active', true)
            ->whereRaw('UPPER(TRIM(item_code)) = ?', [$itemCode])
            ->orderByDesc('source_row')
            ->first();
    }

    private function code($value): string
    {
        return mb_strtoupper(trim((string) $value));
    }
}
