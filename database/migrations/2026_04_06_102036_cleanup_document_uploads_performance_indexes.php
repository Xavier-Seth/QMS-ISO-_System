<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private string $table = 'document_uploads';

    private function hasColumn(string $column): bool
    {
        return Schema::hasColumn($this->table, $column);
    }

    private function hasIndex(string $indexName): bool
    {
        return in_array($indexName, Schema::getIndexListing($this->table), true);
    }

    public function up(): void
    {
        if ($this->hasIndex('document_uploads_type_year_month_idx')) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->dropIndex('document_uploads_type_year_month_idx');
            });
        }

        if ($this->hasIndex('document_uploads_type_year_idx')) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->dropIndex('document_uploads_type_year_idx');
            });
        }

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