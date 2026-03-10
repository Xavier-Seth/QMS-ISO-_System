<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('document_uploads', function (Blueprint $table) {
            $table->foreignId('dcr_record_id')
                ->nullable()
                ->after('ofi_record_id')
                ->constrained('dcr_records')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('document_uploads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dcr_record_id');
        });
    }
};