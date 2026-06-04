<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInternalMaterialReceiptsTable extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->create('internal_material_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_code', 50)->unique();
            $table->date('receipt_date');
            $table->string('warehouse_code', 50)->nullable();
            $table->string('location_code', 100)->nullable();
            $table->string('receiver_name', 150)->nullable();
            $table->string('source', 150)->nullable();
            $table->string('status', 30)->default('posted');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('internal_material_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receipt_id');
            $table->unsignedBigInteger('inventory_package_id')->nullable();
            $table->string('ma_hh', 100);
            $table->string('ten_hh', 255)->nullable();
            $table->string('dvt', 50)->nullable();
            $table->decimal('quantity', 18, 3);
            $table->string('location_code', 100)->nullable();
            $table->string('internal_item_code', 100)->nullable();
            $table->string('size', 100)->nullable();
            $table->string('color', 100)->nullable();
            $table->string('side', 100)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('receipt_id')->references('id')->on('internal_material_receipts')->onDelete('cascade');
            $table->foreign('inventory_package_id')->references('id')->on('inventory_packages')->nullOnDelete();
            $table->index(['ma_hh', 'location_code']);
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->dropIfExists('internal_material_receipt_lines');
        Schema::connection($this->connection)->dropIfExists('internal_material_receipts');
    }
}
