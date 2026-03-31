<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('document_uploads', function (Blueprint $table) {
            if (!Schema::hasColumn('document_uploads', 'year')) {
                $table->unsignedSmallInteger('year')
                    ->nullable()
                    ->after('revision');
            }

            if (!Schema::hasColumn('document_uploads', 'month')) {
                $table->unsignedTinyInteger('month')
                    ->nullable()
                    ->after('year');
            }

            $table->index(['document_type_id', 'year'], 'document_uploads_type_year_idx');
            $table->index(['document_type_id', 'year', 'month'], 'document_uploads_type_year_month_idx');
        });
    }

    public function down(): void
    {
        Schema::table('document_uploads', function (Blueprint $table) {
            $table->dropIndex('document_uploads_type_year_idx');
            $table->dropIndex('document_uploads_type_year_month_idx');

            if (Schema::hasColumn('document_uploads', 'month')) {
                $table->dropColumn('month');
            }

            if (Schema::hasColumn('document_uploads', 'year')) {
                $table->dropColumn('year');
            }
        });
    }
};