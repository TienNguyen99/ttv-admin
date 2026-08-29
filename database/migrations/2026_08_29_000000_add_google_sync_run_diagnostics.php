<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGoogleSyncRunDiagnostics extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        if (Schema::connection($this->connection)->hasTable('internal_google_sync_runs')) {
            Schema::connection($this->connection)->table('internal_google_sync_runs', function (Blueprint $table) {
                if (!Schema::connection($this->connection)->hasColumn('internal_google_sync_runs', 'processed_count')) {
                    $table->unsignedInteger('processed_count')->default(0)->after('status');
                }
                if (!Schema::connection($this->connection)->hasColumn('internal_google_sync_runs', 'failed_count')) {
                    $table->unsignedInteger('failed_count')->default(0)->after('skipped_count');
                }
                if (!Schema::connection($this->connection)->hasColumn('internal_google_sync_runs', 'last_source_row')) {
                    $table->unsignedInteger('last_source_row')->nullable()->after('failed_count');
                }
            });
        }

        if (!Schema::connection($this->connection)->hasTable('internal_google_sync_errors')) {
            Schema::connection($this->connection)->create('internal_google_sync_errors', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sync_run_id');
                $table->unsignedInteger('source_row')->nullable();
                $table->string('source_key', 255)->nullable();
                $table->text('message');
                $table->json('raw_data')->nullable();
                $table->timestamps();

                $table->foreign('sync_run_id', 'google_sync_error_run_fk')
                    ->references('id')
                    ->on('internal_google_sync_runs')
                    ->onDelete('cascade');
                $table->index(['sync_run_id', 'source_row'], 'google_sync_error_run_row_idx');
            });
        }
    }

    public function down()
    {
        Schema::connection($this->connection)->dropIfExists('internal_google_sync_errors');

        if (Schema::connection($this->connection)->hasTable('internal_google_sync_runs')) {
            Schema::connection($this->connection)->table('internal_google_sync_runs', function (Blueprint $table) {
                foreach (['processed_count', 'failed_count', 'last_source_row'] as $column) {
                    if (Schema::connection($this->connection)->hasColumn('internal_google_sync_runs', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
}
