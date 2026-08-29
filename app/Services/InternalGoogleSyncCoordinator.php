<?php

namespace App\Services;

use App\Exceptions\InternalGoogleSyncBusyException;
use App\Models\InternalGoogleSyncError;
use App\Models\InternalGoogleSyncRun;
use Illuminate\Support\Facades\Cache;
use Throwable;

class InternalGoogleSyncCoordinator
{
    public function run(string $scope, string $source, callable $callback, int $lockSeconds): array
    {
        $lock = Cache::lock('internal-google-sync:' . $source, $lockSeconds);
        if (!$lock->get()) {
            throw new InternalGoogleSyncBusyException('Đồng bộ đang chạy. Vui lòng chờ lượt hiện tại hoàn tất.');
        }

        $startedAt = now(config('internal_sync.timezone', 'Asia/Ho_Chi_Minh'));
        $startedTimer = microtime(true);
        $context = new InternalGoogleSyncContext();
        $run = null;

        try {
            $run = InternalGoogleSyncRun::query()->create([
                'scope' => $scope,
                'source' => $source,
                'status' => 'running',
                'started_at' => $startedAt,
            ]);

            $result = (array) $callback($context);
            $errors = $context->errors();
            $this->persistErrors((int) $run->id, $errors);

            $failed = count($errors);
            $processed = (int) ($result['processed'] ?? (
                (int) ($result['created'] ?? 0)
                + (int) ($result['updated'] ?? 0)
                + (int) ($result['unchanged'] ?? 0)
                + (int) ($result['skipped'] ?? 0)
            ));
            $status = $failed > 0 ? 'partial' : 'success';
            $details = $result + [
                'failed' => $failed,
                'sync_run_id' => (int) $run->id,
            ];

            $run->update([
                'status' => $status,
                'processed_count' => $processed,
                'created_count' => (int) ($result['created'] ?? 0),
                'updated_count' => (int) ($result['updated'] ?? 0),
                'unchanged_count' => (int) ($result['unchanged'] ?? 0),
                'skipped_count' => (int) ($result['skipped'] ?? 0),
                'failed_count' => $failed,
                'last_source_row' => $context->lastSourceRow(),
                'message' => $failed > 0
                    ? "Đồng bộ một phần; {$failed} dòng cần kiểm tra."
                    : 'Đồng bộ hoàn tất.',
                'details' => $details,
                'finished_at' => now(config('internal_sync.timezone', 'Asia/Ho_Chi_Minh')),
                'duration_ms' => $this->durationMs($startedTimer),
            ]);

            return $details;
        } catch (Throwable $error) {
            if ($run) {
                try {
                    if ($context->failedCount() > 0 && !$run->errors()->exists()) {
                        $this->persistErrors((int) $run->id, $context->errors());
                    }
                } catch (Throwable $logError) {
                    report($logError);
                }

                $run->update([
                    'status' => 'failed',
                    'failed_count' => max(1, $context->failedCount()),
                    'last_source_row' => $context->lastSourceRow(),
                    'message' => mb_substr($error->getMessage(), 0, 4000),
                    'finished_at' => now(config('internal_sync.timezone', 'Asia/Ho_Chi_Minh')),
                    'duration_ms' => $this->durationMs($startedTimer),
                ]);
            }

            throw $error;
        } finally {
            $lock->release();
        }
    }

    private function durationMs(float $startedTimer): int
    {
        return (int) round((microtime(true) - $startedTimer) * 1000);
    }

    private function persistErrors(int $runId, array $errors): void
    {
        foreach ($errors as $error) {
            InternalGoogleSyncError::query()->create($error + ['sync_run_id' => $runId]);
        }
    }
}
