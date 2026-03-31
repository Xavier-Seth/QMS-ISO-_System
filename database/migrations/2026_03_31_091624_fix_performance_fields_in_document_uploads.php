<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('document_uploads', function (Blueprint $table) {

            // Drop indexes using month FIRST (important!)
            try {
                $table->dropIndex('document_uploads_type_year_month_idx');
            } catch (\Exception $e) {
            }

            // Remove month column
            if (Schema::hasColumn('document_uploads', 'month')) {
                $table->dropColumn('month');
            }

            // Add new fields
            if (!Schema::hasColumn('document_uploads', 'performance_category')) {
                $table->string('performance_category')
                    ->nullable()
                    ->after('year');
            }

            if (!Schema::hasColumn('document_uploads', 'period')) {
                $table->string('period')
                    ->nullable()
                    ->after('performance_category');
            }

            // New index
            $table->index(
                ['document_type_id', 'year', 'performance_category', 'period'],
                'document_uploads_perf_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('document_uploads', function (Blueprint $table) {

            $table->dropIndex('document_uploads_perf_idx');

            if (Schema::hasColumn('document_uploads', 'performance_category')) {
                $table->dropColumn('performance_category');
            }

            if (Schema::hasColumn('document_uploads', 'period')) {
                $table->dropColumn('period');
            }

            // Restore month if rollback
            if (!Schema::hasColumn('document_uploads', 'month')) {
                $table->unsignedTinyInteger('month')
                    ->nullable()
                    ->after('year');
            }

            // Restore old index
            $table->index(
                ['document_type_id', 'year', 'month'],
                'document_uploads_type_year_month_idx'
            );
        });
    }
};