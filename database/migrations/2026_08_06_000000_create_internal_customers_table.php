<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInternalCustomersTable extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        if (Schema::connection($this->connection)->hasTable('internal_customers')) {
            return;
        }

        Schema::connection($this->connection)->create('internal_customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_key', 64)->unique();
            $table->string('customer_code', 100)->nullable()->index();
            $table->string('name', 200);
            $table->string('customer_group', 100)->default('Chưa phân loại')->index();
            $table->string('source', 30)->default('production_order');
            $table->unsignedInteger('order_count')->default(0);
            $table->date('last_order_date')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['is_active', 'name'], 'internal_customers_active_name_idx');
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->dropIfExists('internal_customers');
    }
}
