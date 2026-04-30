<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_uploads', function (Blueprint $table) {
            $table->foreignId('ofi_record_id')
                ->nullable()
                ->after('uploaded_by')
                ->constrained('ofi_records')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('document_uploads', function (Blueprint $table) {
            $table->dropForeign(['ofi_record_id']);
            $table->dropColumn('ofi_record_id');
        });
    }
};
