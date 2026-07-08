<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInternalXntRowsTable extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->create('internal_xnt_rows', function (Blueprint $table) {
            $table->id();
            $table->string('row_key', 120)->unique();
            $table->unsignedInteger('source_row')->nullable();
            $table->string('voucher_code', 100)->nullable();
            $table->date('issue_date')->nullable();
            $table->string('item_code', 200)->nullable();
            $table->text('item_name')->nullable();
            $table->decimal('quantity', 18, 3)->default(0);
            $table->string('unit', 50)->nullable();
            $table->string('receiver_name', 200)->nullable();
            $table->string('production_order', 100)->nullable();
            $table->json('raw_data')->nullable();
            $table->string('sync_batch', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'production_order'], 'xnt_active_order_idx');
            $table->index(['is_active', 'item_code'], 'xnt_active_item_idx');
            $table->index('issue_date', 'xnt_issue_date_idx');
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->dropIfExists('internal_xnt_rows');
    }
}
