<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ofi_records', function (Blueprint $table) {
            $table->dropIndex('ofi_records_ofi_no_index');
            $table->dropIndex('ofi_records_ref_no_index');
            $table->index('ofi_no', 'ofi_records_ofi_no_idx');
            $table->index('ref_no', 'ofi_records_ref_no_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ofi_records', function (Blueprint $table) {
            $table->dropIndex('ofi_records_ofi_no_idx');
            $table->dropIndex('ofi_records_ref_no_idx');
            $table->index('ofi_no', 'ofi_records_ofi_no_index');
            $table->index('ref_no', 'ofi_records_ref_no_index');
        });
    }
};
