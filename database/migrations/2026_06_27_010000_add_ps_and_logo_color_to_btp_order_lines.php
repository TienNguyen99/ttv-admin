<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPsAndLogoColorToBtpOrderLines extends Migration
{
    protected $connection = 'internal';

    public function up()
    {
        Schema::connection($this->connection)->table('internal_btp_production_order_lines', function (Blueprint $table) {
            if (!Schema::connection($this->connection)->hasColumn('internal_btp_production_order_lines', 'ps_number')) {
                $table->string('ps_number', 100)->nullable()->after('source_issue_line_id');
            }

            if (!Schema::connection($this->connection)->hasColumn('internal_btp_production_order_lines', 'logo_color')) {
                $table->string('logo_color', 100)->nullable()->after('color');
            }

            $table->index(['ps_number', 'internal_item_code'], 'btp_order_lines_ps_item_idx');
        });
    }

    public function down()
    {
        Schema::connection($this->connection)->table('internal_btp_production_order_lines', function (Blueprint $table) {
            $table->dropIndex('btp_order_lines_ps_item_idx');

            if (Schema::connection($this->connection)->hasColumn('internal_btp_production_order_lines', 'ps_number')) {
                $table->dropColumn('ps_number');
            }

            if (Schema::connection($this->connection)->hasColumn('internal_btp_production_order_lines', 'logo_color')) {
                $table->dropColumn('logo_color');
            }
        });
    }
}
