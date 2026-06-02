<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInternalFinishedGoodsReceiptsTable extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->create('internal_finished_goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_code', 100)->unique();
            $table->date('receipt_date');
            $table->string('ma_sp', 100);
            $table->string('ma_ko', 50)->default('');
            $table->string('ten_hh', 255)->nullable();
            $table->string('dvt', 50)->nullable();
            $table->decimal('quantity', 18, 3);
            $table->string('status', 30)->default('draft');
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['receipt_date', 'ma_sp']);
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->dropIfExists('internal_finished_goods_receipts');
    }
}
