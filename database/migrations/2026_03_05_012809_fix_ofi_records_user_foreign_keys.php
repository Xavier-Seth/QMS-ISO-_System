<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ofi_records', function (Blueprint $table) {
            // Drop existing foreign keys if they exist
            // Laravel default names: {table}_{column}_foreign
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);

            // Re-add correctly (explicit)
            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('updated_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ofi_records', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);

            // Restore "simple" ones (optional)
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
        });
    }
};