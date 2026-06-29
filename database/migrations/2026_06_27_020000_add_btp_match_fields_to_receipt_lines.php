<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBtpMatchFieldsToReceiptLines extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->table('internal_material_receipt_lines', function (Blueprint $table) {
            if (!Schema::connection($this->connection)->hasColumn('internal_material_receipt_lines', 'ordered_quantity')) {
                $table->decimal('ordered_quantity', 18, 3)->nullable()->after('dvt');
            }
            if (!Schema::connection($this->connection)->hasColumn('internal_material_receipt_lines', 'logo_color')) {
                $table->string('logo_color', 100)->nullable()->after('color');
            }
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->table('internal_material_receipt_lines', function (Blueprint $table) {
            if (Schema::connection($this->connection)->hasColumn('internal_material_receipt_lines', 'ordered_quantity')) {
                $table->dropColumn('ordered_quantity');
            }
            if (Schema::connection($this->connection)->hasColumn('internal_material_receipt_lines', 'logo_color')) {
                $table->dropColumn('logo_color');
            }
        });
    }
}
