<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('document_uploads', function (Blueprint $table) {
            if (!Schema::hasColumn('document_uploads', 'car_record_id')) {
                $table->foreignId('car_record_id')
                    ->nullable()
                    ->after('dcr_record_id')
                    ->constrained('car_records')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('document_uploads', function (Blueprint $table) {
            if (Schema::hasColumn('document_uploads', 'car_record_id')) {
                $table->dropConstrainedForeignId('car_record_id');
            }
        });
    }
};