<?php

namespace App\Services;

use App\Models\InternalMaterialIssue;
use App\Models\InternalMaterialIssueLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InternalMaterialIssueListService
{
    public function search(array $filters): array
    {
        $query = $this->filteredQuery($filters);
        $usesPagination = array_key_exists('page', $filters) || array_key_exists('per_page', $filters);

        if ($usesPagination) {
            return $this->paginatedResult($query, $filters);
        }

        $limit = min(max((int) ($filters['limit'] ?? 200), 1), 200);
        $data = $this->loadPage($query, 1, $limit);

        return [
            'data' => $data,
            'summary' => $this->summaryFromLoadedData($data),
            'pagination' => null,
        ];
    }

    private function paginatedResult(Builder $query, array $filters): array
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 50), 10), 100);
        $summary = $this->summaryForAllMatches($query);
        $lastPage = max(1, (int) ceil($summary['total_issues'] / $perPage));
        $page = min(max((int) ($filters['page'] ?? 1), 1), $lastPage);
        $data = $this->loadPage($query, $page, $perPage);

        return [
            'data' => $data,
            'summary' => $summary,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => $lastPage,
                'total' => $summary['total_issues'],
                'from' => $summary['total_issues'] > 0 ? (($page - 1) * $perPage) + 1 : 0,
                'to' => $summary['total_issues'] > 0 ? (($page - 1) * $perPage) + $data->count() : 0,
            ],
        ];
    }

    private function filteredQuery(array $filters): Builder
    {
        $query = InternalMaterialIssue::query();

        if (!empty($filters['from_date'])) {
            $query->whereDate('issue_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->whereDate('issue_date', '<=', $filters['to_date']);
        }
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['issue_type'])) {
            $query->where('issue_type', $filters['issue_type']);
        }

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(function ($issueQuery) use ($keyword) {
                $issueQuery->where('issue_code', 'like', '%' . $keyword . '%')
                    ->orWhere('warehouse_code', 'like', '%' . $keyword . '%')
                    ->orWhere('receiver_name', 'like', '%' . $keyword . '%')
                    ->orWhere('department', 'like', '%' . $keyword . '%')
                    ->orWhere('production_order', 'like', '%' . $keyword . '%')
                    ->orWhereHas('lines', function ($lineQuery) use ($keyword) {
                        $lineQuery->where('production_order', 'like', '%' . $keyword . '%')
                            ->orWhere('ma_hh', 'like', '%' . $keyword . '%')
                            ->orWhere('internal_item_code', 'like', '%' . $keyword . '%')
                            ->orWhere('ten_hh', 'like', '%' . $keyword . '%')
                            ->orWhere('size', 'like', '%' . $keyword . '%')
                            ->orWhere('color', 'like', '%' . $keyword . '%')
                            ->orWhere('side', 'like', '%' . $keyword . '%')
                            ->orWhere('location_code', 'like', '%' . $keyword . '%');
                    });
            });
        }

        return $query;
    }

    private function loadPage(Builder $query, int $page, int $perPage): Collection
    {
        return (clone $query)
            ->with('lines:id,issue_id,production_order,purchase_order,customer,internal_item_code,ma_hh,ten_hh,quantity,dvt,location_code')
            ->withCount('lines')
            ->withSum('lines', 'quantity')
            ->orderByDesc('issue_date')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (InternalMaterialIssue $issue) => $this->decorate($issue));
    }

    private function decorate(InternalMaterialIssue $issue): InternalMaterialIssue
    {
        $btpCodes = $issue->lines
            ->pluck('production_order')
            ->map(fn ($code) => trim((string) $code))
            ->filter(fn ($code) => strpos($code, 'BTP') === 0)
            ->unique()
            ->values();

        $issue->setAttribute('btp_label_count', $btpCodes->count());
        $issue->setAttribute('btp_label_print_url', $btpCodes->isNotEmpty()
            ? url('/client/lenh-btp/tem-qr?codes=' . urlencode($btpCodes->implode(',')))
            : null);
        $issue->setAttribute('customer_label', $issue->lines
            ->pluck('customer')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->first() ?: trim((string) $issue->receiver_name));
        $issue->setAttribute('item_preview', $issue->lines
            ->map(fn ($line) => trim((string) ($line->internal_item_code ?: $line->ma_hh)))
            ->filter()
            ->unique()
            ->take(4)
            ->values());
        $issue->setAttribute('missing_location_count', $issue->lines
            ->filter(fn ($line) => trim((string) $line->location_code) === '')
            ->count());

        return $issue;
    }

    private function summaryForAllMatches(Builder $query): array
    {
        $totalIssues = (clone $query)->count();
        $lineSummary = InternalMaterialIssueLine::query()
            ->whereIn('issue_id', (clone $query)->select('id'))
            ->selectRaw('COUNT(*) as total_lines, COALESCE(SUM(quantity), 0) as total_quantity')
            ->first();

        return [
            'total_issues' => $totalIssues,
            'total_lines' => (int) ($lineSummary->total_lines ?? 0),
            'total_quantity' => (float) ($lineSummary->total_quantity ?? 0),
        ];
    }

    private function summaryFromLoadedData(Collection $data): array
    {
        return [
            'total_issues' => $data->count(),
            'total_lines' => $data->sum('lines_count'),
            'total_quantity' => (float) $data->sum('lines_sum_quantity'),
        ];
    }
}
