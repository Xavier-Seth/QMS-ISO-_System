<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Convert any legacy values written before the enum was corrected.
        // 'final' and 'closed' were in the original enum but are never set
        // by application code; only 'draft' and 'submitted' are used.
        DB::table('ofi_records')
            ->whereIn('status', ['final', 'closed'])
            ->update(['status' => 'submitted']);

        Schema::table('ofi_records', function (Blueprint $table) {
            $table->enum('status', ['draft', 'submitted'])->default('draft')->change();
        });
    }

    public function down(): void
    {
        Schema::table('ofi_records', function (Blueprint $table) {
            $table->enum('status', ['draft', 'final', 'closed'])->default('draft')->change();
        });
        // Data cannot be reverted: we no longer know which rows were
        // originally 'final' vs 'closed' vs 'submitted'.
    }
};
