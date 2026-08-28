<?php

namespace Tests\Feature;

use App\Models\InternalMaterialIssue;
use App\Models\InternalMaterialIssueLine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InternalMaterialIssueListTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = ['internal'];

    public function test_list_honors_the_requested_limit(): void
    {
        foreach ([1, 2] as $number) {
            InternalMaterialIssue::query()->create([
                'issue_code' => 'TEST-LIMIT-' . uniqid() . '-' . $number,
                'issue_type' => 'material',
                'issue_date' => '2099-08-28',
                'warehouse_code' => 'KTPHAM',
                'status' => 'draft',
            ]);
        }

        $this->getJson('/api/xuat-vat-tu-noi-bo?limit=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('summary.total_issues', 1);
    }

    public function test_paginated_list_returns_full_summary_without_n_plus_one_queries(): void
    {
        $prefix = 'TEST-PAGE-' . uniqid();
        foreach (range(1, 11) as $number) {
            $issue = InternalMaterialIssue::query()->create([
                'issue_code' => $prefix . '-' . $number,
                'issue_type' => 'material',
                'issue_date' => '2099-08-28',
                'warehouse_code' => 'KTPHAM',
                'status' => 'draft',
            ]);
            InternalMaterialIssueLine::query()->create([
                'issue_id' => $issue->id,
                'ma_hh' => 'ITEM-' . $number,
                'internal_item_code' => 'ITEM-' . $number,
                'quantity' => $number,
                'dvt' => 'PCS',
            ]);
        }

        $queryCount = 0;
        DB::connection('internal')->listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $this->getJson('/api/xuat-vat-tu-noi-bo?keyword=' . urlencode($prefix) . '&page=2&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('summary.total_issues', 11)
            ->assertJsonPath('summary.total_lines', 11)
            ->assertJsonPath('summary.total_quantity', 66)
            ->assertJsonPath('pagination.current_page', 2)
            ->assertJsonPath('pagination.last_page', 2)
            ->assertJsonPath('pagination.from', 11)
            ->assertJsonPath('pagination.to', 11);

        $this->assertLessThanOrEqual(6, $queryCount, 'Danh sách phiếu phát sinh quá nhiều query.');
    }

    public function test_list_accepts_vietnamese_date_filters(): void
    {
        $code = 'TEST-DATE-' . uniqid();
        InternalMaterialIssue::query()->create([
            'issue_code' => $code,
            'issue_type' => 'material',
            'issue_date' => '2099-08-28',
            'warehouse_code' => 'KTPHAM',
            'status' => 'draft',
        ]);

        $this->getJson('/api/xuat-vat-tu-noi-bo?keyword=' . urlencode($code) . '&from_date=28%2F08%2F2099&to_date=28%2F08%2F2099&limit=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.issue_code', $code);
    }
}
