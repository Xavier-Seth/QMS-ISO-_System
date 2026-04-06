<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private string $table = 'document_uploads';

    private function hasIndex(string $indexName): bool
    {
        return in_array($indexName, Schema::getIndexListing($this->table), true);
    }

    public function up(): void
    {
        Schema::table($this->table, function (Blueprint $table) {
            if ($this->hasIndex('document_uploads_type_year_month_idx')) {
                $table->dropIndex('document_uploads_type_year_month_idx');
            }

            if (Schema::hasColumn('document_uploads', 'month')) {
                $table->dropColumn('month');
            }

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
        });

        Schema::table($this->table, function (Blueprint $table) {
            if (!$this->hasIndex('document_uploads_perf_idx')) {
                $table->index(
                    ['document_type_id', 'year', 'performance_category', 'period'],
                    'document_uploads_perf_idx'
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table($this->table, function (Blueprint $table) {
            if ($this->hasIndex('document_uploads_perf_idx')) {
                $table->dropIndex('document_uploads_perf_idx');
            }

            if (Schema::hasColumn('document_uploads', 'performance_category')) {
                $table->dropColumn('performance_category');
            }

            if (Schema::hasColumn('document_uploads', 'period')) {
                $table->dropColumn('period');
            }

            if (!Schema::hasColumn('document_uploads', 'month')) {
                $table->unsignedTinyInteger('month')
                    ->nullable()
                    ->after('year');
            }
        });

        Schema::table($this->table, function (Blueprint $table) {
            if (!$this->hasIndex('document_uploads_type_year_month_idx')) {
                $table->index(
                    ['document_type_id', 'year', 'month'],
                    'document_uploads_type_year_month_idx'
                );
            }
        });
    }
};