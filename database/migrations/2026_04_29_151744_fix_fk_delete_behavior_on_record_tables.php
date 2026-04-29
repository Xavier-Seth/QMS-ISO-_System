<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // car_records: created_by and updated_by were restrictOnDelete — standardize to nullOnDelete
        Schema::table('car_records', function (Blueprint $table) {
            try {
                $table->dropForeign(['created_by']);
            } catch (\Throwable $e) {
            }

            try {
                $table->dropForeign(['updated_by']);
            } catch (\Throwable $e) {
            }

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        // document_uploads: uploaded_by was cascadeOnDelete — standardize to nullOnDelete
        Schema::table('document_uploads', function (Blueprint $table) {
            try {
                $table->dropForeign(['uploaded_by']);
            } catch (\Throwable $e) {
            }

            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Restore car_records to original restrictOnDelete behavior
        Schema::table('car_records', function (Blueprint $table) {
            try {
                $table->dropForeign(['created_by']);
            } catch (\Throwable $e) {
            }

            try {
                $table->dropForeign(['updated_by']);
            } catch (\Throwable $e) {
            }

            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->restrictOnDelete();
        });

        // Restore document_uploads to original cascadeOnDelete behavior
        Schema::table('document_uploads', function (Blueprint $table) {
            try {
                $table->dropForeign(['uploaded_by']);
            } catch (\Throwable $e) {
            }

            $table->foreign('uploaded_by')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
