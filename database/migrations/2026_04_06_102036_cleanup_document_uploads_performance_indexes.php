<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private string $table = 'document_uploads';

    private function hasColumn(string $column): bool
    {
        return Schema::hasColumn($this->table, $column);
    }

    private function hasIndex(string $indexName): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $this->table)
            ->where('index_name', $indexName)
            ->exists();
    }

    public function up(): void
    {
        // 1. Drop old/obsolete indexes if they still exist
        if ($this->hasIndex('document_uploads_type_year_month_idx')) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->dropIndex('document_uploads_type_year_month_idx');
            });
        }

        // Optional:
        // Keep this only if you no longer use the simple year-only index anywhere.
        // If you want to keep it, remove this block.
        if ($this->hasIndex('document_uploads_type_year_idx')) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->dropIndex('document_uploads_type_year_idx');
            });
        }

        // 2. Ensure the final performance indexes exist
        if (
            $this->hasColumn('document_type_id') &&
            $this->hasColumn('year') &&
            $this->hasColumn('performance_category') &&
            $this->hasColumn('period') &&
            !$this->hasIndex('document_uploads_perf_idx')
        ) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->index(
                    ['document_type_id', 'year', 'performance_category', 'period'],
                    'document_uploads_perf_idx'
                );
            });
        }

        if (
            $this->hasColumn('document_type_id') &&
            $this->hasColumn('performance_record_type') &&
            $this->hasColumn('year') &&
            $this->hasColumn('period') &&
            !$this->hasIndex('document_uploads_perf_record_year_period_idx')
        ) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->index(
                    ['document_type_id', 'performance_record_type', 'year', 'period'],
                    'document_uploads_perf_record_year_period_idx'
                );
            });
        }
    }

    public function down(): void
    {
        // 1. Drop the newer performance indexes if they exist
        if ($this->hasIndex('document_uploads_perf_record_year_period_idx')) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->dropIndex('document_uploads_perf_record_year_period_idx');
            });
        }

        if ($this->hasIndex('document_uploads_perf_idx')) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->dropIndex('document_uploads_perf_idx');
            });
        }

        // 2. Restore the old indexes only if the needed columns still exist
        if (
            $this->hasColumn('document_type_id') &&
            $this->hasColumn('year') &&
            !$this->hasIndex('document_uploads_type_year_idx')
        ) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->index(
                    ['document_type_id', 'year'],
                    'document_uploads_type_year_idx'
                );
            });
        }

        if (
            $this->hasColumn('document_type_id') &&
            $this->hasColumn('year') &&
            $this->hasColumn('month') &&
            !$this->hasIndex('document_uploads_type_year_month_idx')
        ) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->index(
                    ['document_type_id', 'year', 'month'],
                    'document_uploads_type_year_month_idx'
                );
            });
        }
    }
};