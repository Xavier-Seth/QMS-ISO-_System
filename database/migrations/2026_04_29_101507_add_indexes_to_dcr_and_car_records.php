<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dcr_records', function (Blueprint $table) {
            $table->index('dcr_no', 'dcr_records_dcr_no_idx');
        });

        Schema::table('car_records', function (Blueprint $table) {
            $table->index('ref_no', 'car_records_ref_no_idx');
            $table->index('dept_section', 'car_records_dept_section_idx');
            $table->index('auditor', 'car_records_auditor_idx');
        });
    }

    public function down(): void
    {
        Schema::table('dcr_records', function (Blueprint $table) {
            $table->dropIndex('dcr_records_dcr_no_idx');
        });

        Schema::table('car_records', function (Blueprint $table) {
            $table->dropIndex('car_records_ref_no_idx');
            $table->dropIndex('car_records_dept_section_idx');
            $table->dropIndex('car_records_auditor_idx');
        });
    }
};
