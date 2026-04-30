<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ofi_records', function (Blueprint $table) {
            $table->index(['status', 'workflow_status'], 'ofi_records_status_workflow_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ofi_records', function (Blueprint $table) {
            $table->dropIndex('ofi_records_status_workflow_idx');
        });
    }
};
