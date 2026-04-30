<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $nullCount = DB::table('document_uploads')->whereNull('document_type_id')->count();

        if ($nullCount > 0) {
            throw new \RuntimeException(
                "Cannot make document_uploads.document_type_id NOT NULL: {$nullCount} row(s) have NULL values. Resolve them first."
            );
        }

        // ON DELETE SET NULL is incompatible with NOT NULL — drop FK, change column, re-add with RESTRICT
        Schema::table('document_uploads', function (Blueprint $table) {
            $table->dropForeign('document_uploads_document_type_id_foreign');
        });

        Schema::table('document_uploads', function (Blueprint $table) {
            $table->unsignedBigInteger('document_type_id')->nullable(false)->change();
            $table->foreign('document_type_id')->references('id')->on('document_types')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('document_uploads', function (Blueprint $table) {
            $table->dropForeign('document_uploads_document_type_id_foreign');
        });

        Schema::table('document_uploads', function (Blueprint $table) {
            $table->unsignedBigInteger('document_type_id')->nullable()->change();
            $table->foreign('document_type_id')->references('id')->on('document_types')->nullOnDelete();
        });
    }
};
