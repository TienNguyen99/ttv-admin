<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIncrementalSyncTracking extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        foreach (['internal_production_orders', 'internal_xnt_rows', 'internal_item_catalogs'] as $tableName) {
            if (Schema::connection($this->connection)->hasTable($tableName)
                && !Schema::connection($this->connection)->hasColumn($tableName, 'source_hash')) {
                Schema::connection($this->connection)->table($tableName, function (Blueprint $table) {
                    $table->string('source_hash', 64)->nullable()->index();
                });
            }
        }

        if (!Schema::connection($this->connection)->hasTable('internal_google_sync_runs')) {
            Schema::connection($this->connection)->create('internal_google_sync_runs', function (Blueprint $table) {
                $table->id();
                $table->string('scope', 30);
                $table->string('source', 50);
                $table->string('status', 20);
                $table->unsignedInteger('created_count')->default(0);
                $table->unsignedInteger('updated_count')->default(0);
                $table->unsignedInteger('unchanged_count')->default(0);
                $table->unsignedInteger('skipped_count')->default(0);
                $table->text('message')->nullable();
                $table->json('details')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->unsignedInteger('duration_ms')->default(0);
                $table->timestamps();

                $table->index(['source', 'started_at'], 'google_sync_source_started_idx');
                $table->index(['status', 'started_at'], 'google_sync_status_started_idx');
            });
        }
    }

    public function down()
    {
        Schema::connection($this->connection)->dropIfExists('internal_google_sync_runs');

        foreach (['internal_production_orders', 'internal_xnt_rows', 'internal_item_catalogs'] as $tableName) {
            if (Schema::connection($this->connection)->hasTable($tableName)
                && Schema::connection($this->connection)->hasColumn($tableName, 'source_hash')) {
                Schema::connection($this->connection)->table($tableName, function (Blueprint $table) {
                    $table->dropColumn('source_hash');
                });
            }
        }
    }
}
