<?php

namespace App\Http\Controllers;

use App\Models\InternalBtpProductionOrder;
use App\Models\InternalItemCatalog;
use App\Models\InternalProductBomProfile;
use App\Models\InternalProductionOrder;
use App\Services\InternalAudit;
use App\Services\InternalBomCalculator;
use App\Services\InternalOrderBomService;
use App\Services\InternalProductBomProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InternalProductBomController extends Controller
{
    private InternalBomCalculator $calculator;
    private InternalOrderBomService $orderBomService;
    private InternalProductBomProfileService $profileService;

    public function __construct(
        InternalBomCalculator $calculator,
        InternalOrderBomService $orderBomService,
        InternalProductBomProfileService $profileService
    ) {
        $this->calculator = $calculator;
        $this->orderBomService = $orderBomService;
        $this->profileService = $profileService;
    }

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
        $itemCode = $this->calculator->code($request->query('item_code', ''));
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

        try {
            $profile = $this->profileService->save($data);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        app(InternalAudit::class)->model('product_bom.saved', $profile, [
            'revision' => $profile->revision,
            'material_count' => $profile->lines->count(),
            'operation_count' => $profile->routings->count(),
        ], $request);

        return response()->json([
            'message' => 'Đã lưu BOM và công đoạn của mã ' . $profile->item_code . '.',
            'data' => $profile,
        ]);
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
            return response()->json($this->orderBomService->central($orders));
        }

        $btpOrder = $this->btpOrder($orderCode);
        if ($btpOrder) {
            return response()->json($this->orderBomService->btp($btpOrder));
        }

        return response()->json(['message' => 'Không tìm thấy lệnh sản xuất hoặc lệnh BTP.'], 404);
    }

    public function snapshotOrder(Request $request)
    {
        $data = $request->validate(['production_order' => 'required|string|max:100']);
        $orderCode = trim($data['production_order']);
        $orders = InternalProductionOrder::query()
            ->where('is_active', true)
            ->where('production_order', $orderCode)
            ->orderBy('id')
            ->get();
        if ($orders->isNotEmpty()) {
            $result = DB::connection('internal')->transaction(function () use ($orderCode) {
                $lockedOrders = InternalProductionOrder::query()
                    ->where('is_active', true)
                    ->where('production_order', $orderCode)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                return $this->orderBomService->central($lockedOrders, true);
            });

            return response()->json([
                'message' => 'Đã chốt bản BOM cho lệnh ' . $orderCode . '.',
                'data' => $result['data'],
                'missing_items' => $result['missing_items'],
            ]);
        }

        $btpOrder = $this->btpOrder($orderCode);
        if (!$btpOrder) {
            return response()->json(['message' => 'Không tìm thấy lệnh sản xuất hoặc lệnh BTP.'], 404);
        }

        $result = DB::connection('internal')->transaction(function () use ($btpOrder) {
            $lockedOrder = InternalBtpProductionOrder::query()
                ->whereKey($btpOrder->id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedOrder->load('lines');

            return $this->orderBomService->btp($lockedOrder, true);
        });

        return response()->json([
            'message' => 'Đã chốt bản BOM cho lệnh BTP ' . $btpOrder->btp_order_code . '.',
            'data' => $result['data'],
            'missing_items' => $result['missing_items'],
        ]);
    }

    public function aggregateOrderNeeds(Request $request)
    {
        $data = $request->validate([
            'production_orders' => 'required|array|min:1|max:100',
            'production_orders.*' => 'required|string|max:100',
        ]);

        return response()->json($this->orderBomService->aggregate(collect($data['production_orders'])));
    }

    private function btpOrder(string $orderCode): ?InternalBtpProductionOrder
    {
        return InternalBtpProductionOrder::query()
            ->with('lines')
            ->whereRaw('UPPER(TRIM(btp_order_code)) = ?', [$this->calculator->code($orderCode)])
            ->first();
    }

    private function catalog(string $itemCode): ?InternalItemCatalog
    {
        return InternalItemCatalog::query()
            ->where('is_active', true)
            ->whereRaw('UPPER(TRIM(item_code)) = ?', [$itemCode])
            ->orderByDesc('source_row')
            ->first();
    }
}
