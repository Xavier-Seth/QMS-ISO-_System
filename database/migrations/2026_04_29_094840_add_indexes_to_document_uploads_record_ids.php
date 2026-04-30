<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_uploads', function (Blueprint $table) {
            $table->index('dcr_record_id', 'document_uploads_dcr_record_id_idx');
            $table->index('car_record_id', 'document_uploads_car_record_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('document_uploads', function (Blueprint $table) {
            $table->dropIndex('document_uploads_dcr_record_id_idx');
            $table->dropIndex('document_uploads_car_record_id_idx');
        });
    }
};
