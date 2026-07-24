<?php

namespace App\Console\Commands;

use App\Http\Controllers\InternalItemCatalogController;
use App\Http\Controllers\InternalProductionOrderController;
use App\Http\Controllers\InternalXntController;
use App\Models\InternalGoogleSyncRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SyncInternalGoogleSheets extends Command
{
    protected $signature = 'internal:sync-google-sheets
        {scope=operational : operational, reference hoặc all}';

    protected $description = 'Đồng bộ Google Sheet vào database kho nội bộ';

    public function handle(): int
    {
        $scope = strtolower(trim((string) $this->argument('scope')));
        if (!in_array($scope, ['operational', 'reference', 'all'], true)) {
            $this->error('Scope phải là operational, reference hoặc all.');
            return 2;
        }

        $sources = $this->sourcesFor($scope);
        $failed = 0;

        foreach ($sources as $source => $callback) {
            if (!$this->syncSource($scope, $source, $callback)) {
                $failed++;
            }
        }

        return $failed === 0 ? 0 : 1;
    }

    private function sourcesFor(string $scope): array
    {
        $sources = [];

        if (in_array($scope, ['operational', 'all'], true)) {
            $sources['production_orders'] = fn () => app(InternalProductionOrderController::class)->sync();
            $sources['xnt'] = fn () => app(InternalXntController::class)->sync();
        }

        if (in_array($scope, ['reference', 'all'], true)) {
            $sources['catalog'] = fn () => app(InternalItemCatalogController::class)->sync();
            $sources['catalog_locations'] = fn () => app(InternalItemCatalogController::class)->syncShelvesToLocations();
        }

        return $sources;
    }

    private function syncSource(string $scope, string $source, callable $callback): bool
    {
        $lockSeconds = $scope === 'reference'
            ? config('internal_sync.reference_lock_seconds', 900)
            : config('internal_sync.operational_lock_seconds', 110);
        $lock = Cache::lock('internal-google-sync:' . $source, $lockSeconds);

        if (!$lock->get()) {
            $this->warn("Bỏ qua {$source}: lượt đồng bộ trước chưa kết thúc.");
            return true;
        }

        $started = now(config('internal_sync.timezone'));
        $timer = microtime(true);

        try {
            $response = $callback();
            $statusCode = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 500;
            $payload = method_exists($response, 'getData') ? $response->getData(true) : [];
            $data = (array) ($payload['data'] ?? []);
            $success = $statusCode >= 200 && $statusCode < 300;

            InternalGoogleSyncRun::query()->create([
                'scope' => $scope,
                'source' => $source,
                'status' => $success ? 'success' : 'failed',
                'created_count' => (int) ($data['created'] ?? 0),
                'updated_count' => (int) ($data['updated'] ?? 0),
                'unchanged_count' => (int) ($data['unchanged'] ?? 0),
                'skipped_count' => (int) ($data['skipped'] ?? 0),
                'message' => (string) ($payload['message'] ?? ''),
                'details' => $data,
                'started_at' => $started,
                'finished_at' => now(config('internal_sync.timezone')),
                'duration_ms' => (int) round((microtime(true) - $timer) * 1000),
            ]);

            $line = sprintf(
                '%s: mới %d, sửa %d, không đổi %d, bỏ qua %d',
                $source,
                (int) ($data['created'] ?? 0),
                (int) ($data['updated'] ?? 0),
                (int) ($data['unchanged'] ?? 0),
                (int) ($data['skipped'] ?? 0)
            );
            $success ? $this->info($line) : $this->error($line . ' - ' . ($payload['message'] ?? 'Lỗi đồng bộ'));

            return $success;
        } catch (Throwable $error) {
            InternalGoogleSyncRun::query()->create([
                'scope' => $scope,
                'source' => $source,
                'status' => 'failed',
                'message' => mb_substr($error->getMessage(), 0, 4000),
                'started_at' => $started,
                'finished_at' => now(config('internal_sync.timezone')),
                'duration_ms' => (int) round((microtime(true) - $timer) * 1000),
            ]);
            report($error);
            $this->error("{$source}: {$error->getMessage()}");

            return false;
        } finally {
            optional($lock)->release();
        }
    }
}
