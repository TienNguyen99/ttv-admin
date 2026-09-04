<?php

namespace App\Http\Controllers;

use App\Services\InternalMaterialUsageService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class InternalMaterialDemandController extends Controller
{
    public function page()
    {
        return view('Client.internal-material-demand');
    }

    public function index(Request $request, InternalMaterialUsageService $service)
    {
        $data = $request->validate([
            'as_of' => 'nullable|date_format:Y-m-d',
            'window' => 'nullable|integer|in:1,3,6,9,12',
            'lead_time_days' => 'nullable|integer|min:0|max:365',
            'safety_percent' => 'nullable|numeric|min:0|max:500',
            'target_months' => 'nullable|numeric|min:0.1|max:24',
            'keyword' => 'nullable|string|max:200',
            'status' => 'nullable|in:all,needs_purchase,in_stock',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:10|max:100',
            'fresh' => 'nullable|boolean',
        ]);

        $asOf = Carbon::createFromFormat(
            'Y-m-d',
            $data['as_of'] ?? now('Asia/Ho_Chi_Minh')->format('Y-m-d'),
            'Asia/Ho_Chi_Minh'
        );
        $report = $service->report(
            $asOf,
            (int) ($data['window'] ?? 3),
            (int) ($data['lead_time_days'] ?? 14),
            (float) ($data['safety_percent'] ?? 20),
            (float) ($data['target_months'] ?? 2),
            trim((string) ($data['keyword'] ?? '')),
            (string) ($data['status'] ?? 'all'),
            (bool) ($data['fresh'] ?? false)
        );

        $page = (int) ($data['page'] ?? 1);
        $perPage = (int) ($data['per_page'] ?? 50);
        $total = count($report['data']);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $report['data'] = array_slice($report['data'], ($page - 1) * $perPage, $perPage);
        $report['meta'] = [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $lastPage,
        ];

        return response()->json($report);
    }
}
