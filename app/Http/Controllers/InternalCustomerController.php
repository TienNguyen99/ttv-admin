<?php

namespace App\Http\Controllers;

use App\Models\InternalCustomer;
use App\Services\InternalCustomerCatalogSync;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InternalCustomerController extends Controller
{
    public function page()
    {
        return view('Client.internal-customers');
    }

    public function index(Request $request)
    {
        $keyword = trim((string) $request->query('keyword', ''));
        $group = trim((string) $request->query('customer_group', ''));
        $perPage = min(max((int) $request->query('per_page', 50), 10), 100);

        $query = InternalCustomer::query()
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->where(function ($query) use ($keyword) {
                    $query->where('name', 'like', '%' . $keyword . '%')
                        ->orWhere('customer_code', 'like', '%' . $keyword . '%');
                });
            })
            ->when($group !== '', fn ($query) => $query->where('customer_group', $group))
            ->orderByDesc('is_active')
            ->orderBy('name');

        $rows = $query->paginate($perPage);
        $groups = InternalCustomer::query()
            ->where('is_active', true)
            ->whereNotNull('customer_group')
            ->where('customer_group', '<>', '')
            ->distinct()
            ->orderBy('customer_group')
            ->pluck('customer_group')
            ->values();
        $stats = InternalCustomer::query()
            ->selectRaw("SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count")
            ->selectRaw("SUM(CASE WHEN is_active = 1 AND customer_group = 'Chưa phân loại' THEN 1 ELSE 0 END) as unclassified_count")
            ->first();

        return response()->json([
            'data' => $rows->items(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'total' => $rows->total(),
                'active' => (int) ($stats->active_count ?? 0),
                'unclassified' => (int) ($stats->unclassified_count ?? 0),
            ],
            'groups' => $groups,
        ]);
    }

    public function suggestions(Request $request)
    {
        $keyword = trim((string) $request->query('keyword', ''));
        $limit = min(max((int) $request->query('limit', 30), 1), 100);

        $rows = InternalCustomer::query()
            ->where('is_active', true)
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->where(function ($query) use ($keyword) {
                    $query->where('name', 'like', '%' . $keyword . '%')
                        ->orWhere('customer_code', 'like', '%' . $keyword . '%');
                });
            })
            ->orderByDesc('order_count')
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'customer_code', 'name', 'customer_group', 'order_count']);

        return response()->json([
            'data' => $rows,
            'groups' => InternalCustomer::query()
                ->where('is_active', true)
                ->whereNotNull('customer_group')
                ->where('customer_group', '<>', '')
                ->distinct()
                ->orderBy('customer_group')
                ->pluck('customer_group')
                ->values(),
        ]);
    }

    public function check(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:200']);
        $customer = InternalCustomer::query()
            ->where('customer_key', InternalCustomerCatalogSync::key($data['name']))
            ->where('is_active', true)
            ->first(['id', 'customer_code', 'name', 'customer_group']);

        return response()->json([
            'valid' => (bool) $customer,
            'data' => $customer,
            'message' => $customer
                ? 'Khách hàng hợp lệ.'
                : 'Khách hàng chưa có trong danh mục hoặc đã ngừng sử dụng.',
        ]);
    }

    public function sync(InternalCustomerCatalogSync $sync)
    {
        return response()->json([
            'message' => 'Đã đồng bộ khách hàng từ lệnh sản xuất.',
            'data' => $sync->syncFromProductionOrders(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $key = InternalCustomerCatalogSync::key($data['name']);
        if (InternalCustomer::query()->where('customer_key', $key)->exists()) {
            throw ValidationException::withMessages(['name' => 'Khách hàng này đã có trong danh mục.']);
        }

        $customer = InternalCustomer::query()->create([
            'customer_key' => $key,
            'customer_code' => trim((string) ($data['customer_code'] ?? '')) ?: null,
            'name' => preg_replace('/\s+/u', ' ', trim($data['name'])),
            'customer_group' => trim((string) ($data['customer_group'] ?? '')) ?: 'Chưa phân loại',
            'source' => 'manual',
            'is_active' => true,
        ]);

        return response()->json(['message' => 'Đã thêm khách hàng.', 'data' => $customer], 201);
    }

    public function update(Request $request, InternalCustomer $customer)
    {
        $data = $this->validated($request, true);
        $name = preg_replace('/\s+/u', ' ', trim($data['name']));
        $key = InternalCustomerCatalogSync::key($name);
        if (InternalCustomer::query()->where('customer_key', $key)->where('id', '<>', $customer->id)->exists()) {
            throw ValidationException::withMessages(['name' => 'Tên khách hàng trùng với một dòng khác.']);
        }

        $customer->update([
            'customer_key' => $key,
            'customer_code' => trim((string) ($data['customer_code'] ?? '')) ?: null,
            'name' => $name,
            'customer_group' => trim((string) ($data['customer_group'] ?? '')) ?: 'Chưa phân loại',
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $customer->is_active,
        ]);

        return response()->json(['message' => 'Đã cập nhật khách hàng.', 'data' => $customer->fresh()]);
    }

    public function destroy(InternalCustomer $customer)
    {
        $customer->update(['is_active' => false]);

        return response()->json(['message' => 'Đã ngừng sử dụng khách hàng.']);
    }

    private function validated(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'name' => 'required|string|max:200',
            'customer_code' => 'nullable|string|max:100',
            'customer_group' => 'nullable|string|max:100',
            'is_active' => ($updating ? 'nullable' : 'sometimes') . '|boolean',
        ]);
    }
}
