<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ofi_records', function (Blueprint $table) {

            // Drop old foreign keys
            try {
                $table->dropForeign(['created_by']);
            } catch (\Throwable $e) {
            }

            try {
                $table->dropForeign(['updated_by']);
            } catch (\Throwable $e) {
            }

            // Recreate with correct behavior
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

            try {
                $table->dropForeign(['created_by']);
            } catch (\Throwable $e) {
            }

            try {
                $table->dropForeign(['updated_by']);
            } catch (\Throwable $e) {
            }

            $table->foreign('created_by')
                ->references('id')
                ->on('users');

            $table->foreign('updated_by')
                ->references('id')
                ->on('users');
        });
    }
};