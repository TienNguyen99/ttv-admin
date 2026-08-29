<?php

namespace Tests\Feature;

use App\Exceptions\InternalGoogleSyncBusyException;
use App\Exceptions\InternalGoogleSyncSourceException;
use App\Models\InternalGoogleSyncError;
use App\Models\InternalGoogleSyncRun;
use App\Services\InternalGoogleSyncContext;
use App\Services\InternalGoogleSyncCoordinator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class InternalGoogleSyncCoordinatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::connection('internal')->beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::connection('internal')->rollBack();
        parent::tearDown();
    }

    public function test_it_records_a_successful_incremental_run(): void
    {
        $source = 'test-success-' . Str::uuid();
        $result = app(InternalGoogleSyncCoordinator::class)->run(
            'reference',
            $source,
            function (InternalGoogleSyncContext $context) {
                $context->checkpoint(12);

                return [
                    'processed' => 10,
                    'created' => 2,
                    'updated' => 1,
                    'unchanged' => 7,
                    'skipped' => 0,
                ];
            },
            30
        );

        $run = InternalGoogleSyncRun::query()->findOrFail($result['sync_run_id']);
        $this->assertSame('success', $run->status);
        $this->assertSame(10, (int) $run->processed_count);
        $this->assertSame(12, (int) $run->last_source_row);
        $this->assertSame(0, (int) $run->failed_count);
    }

    public function test_it_keeps_row_errors_and_marks_the_run_partial(): void
    {
        $source = 'test-partial-' . Str::uuid();
        $result = app(InternalGoogleSyncCoordinator::class)->run(
            'operational',
            $source,
            function (InternalGoogleSyncContext $context) {
                $context->checkpoint(2);
                $context->recordRowError(3, 'T-TEST/26', 'Invalid date', ['ngay nhan' => 'invalid']);

                return [
                    'processed' => 2,
                    'created' => 1,
                    'updated' => 0,
                    'unchanged' => 0,
                    'skipped' => 1,
                ];
            },
            30
        );

        $run = InternalGoogleSyncRun::query()->findOrFail($result['sync_run_id']);
        $error = InternalGoogleSyncError::query()->where('sync_run_id', $run->id)->firstOrFail();

        $this->assertSame('partial', $run->status);
        $this->assertSame(1, (int) $run->failed_count);
        $this->assertSame(3, (int) $run->last_source_row);
        $this->assertSame('T-TEST/26', $error->source_key);
        $this->assertSame('invalid', $error->raw_data['ngay nhan']);
    }

    public function test_it_rejects_a_concurrent_run_for_the_same_source(): void
    {
        $source = 'test-lock-' . Str::uuid();
        $lock = Cache::lock('internal-google-sync:' . $source, 30);
        $this->assertTrue($lock->get());

        try {
            $this->expectException(InternalGoogleSyncBusyException::class);
            app(InternalGoogleSyncCoordinator::class)->run(
                'reference',
                $source,
                fn () => [],
                30
            );
        } finally {
            $lock->release();
        }
    }

    public function test_it_marks_a_failed_source_run_and_releases_the_lock_for_retry(): void
    {
        $source = 'test-retry-' . Str::uuid();
        $coordinator = app(InternalGoogleSyncCoordinator::class);

        try {
            $coordinator->run('operational', $source, function (InternalGoogleSyncContext $context) {
                $context->recordRowError(8, 'T-FAILED/26', 'Row failed before source failure');
                throw new InternalGoogleSyncSourceException('Source unavailable', 502);
            }, 30);
            $this->fail('Expected source exception was not thrown.');
        } catch (InternalGoogleSyncSourceException $error) {
            $this->assertSame(502, $error->statusCode());
        }

        $failedRun = InternalGoogleSyncRun::query()->where('source', $source)->latest('id')->firstOrFail();
        $this->assertSame('failed', $failedRun->status);
        $this->assertSame(1, $failedRun->errors()->count());

        $retry = $coordinator->run('operational', $source, fn () => [
            'processed' => 1,
            'created' => 0,
            'updated' => 0,
            'unchanged' => 1,
            'skipped' => 0,
        ], 30);

        $this->assertNotSame((int) $failedRun->id, (int) $retry['sync_run_id']);
        $this->assertSame('success', InternalGoogleSyncRun::query()->findOrFail($retry['sync_run_id'])->status);
    }
}
