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
            if (!Schema::hasColumn('document_uploads', 'performance_record_type')) {
                $table->string('performance_record_type', 30)
                    ->nullable()
                    ->after('performance_category');
            }
        });

        Schema::table($this->table, function (Blueprint $table) {
            if (!$this->hasIndex('document_uploads_perf_record_year_period_idx')) {
                $table->index(
                    ['document_type_id', 'performance_record_type', 'year', 'period'],
                    'document_uploads_perf_record_year_period_idx'
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table($this->table, function (Blueprint $table) {
            if ($this->hasIndex('document_uploads_perf_record_year_period_idx')) {
                $table->dropIndex('document_uploads_perf_record_year_period_idx');
            }

            if (Schema::hasColumn('document_uploads', 'performance_record_type')) {
                $table->dropColumn('performance_record_type');
            }
        });
    }
};