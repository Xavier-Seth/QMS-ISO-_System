<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1. Drop existing foreign key (name may vary, so use try/catch)
        try {
            DB::statement('ALTER TABLE document_uploads DROP FOREIGN KEY document_uploads_document_type_id_foreign');
        } catch (\Throwable $e) {
            // ignore if FK name is different or already removed
        }

        // 2. Make column nullable
        DB::statement('ALTER TABLE document_uploads MODIFY document_type_id BIGINT UNSIGNED NULL');

        // 3. Re-add foreign key with SET NULL on delete
        try {
            DB::statement('
                ALTER TABLE document_uploads
                ADD CONSTRAINT document_uploads_document_type_id_foreign
                FOREIGN KEY (document_type_id)
                REFERENCES document_types(id)
                ON DELETE SET NULL
            ');
        } catch (\Throwable $e) {
            // ignore if already exists
        }
    }

    public function down(): void
    {
        // 1. Drop FK again
        try {
            DB::statement('ALTER TABLE document_uploads DROP FOREIGN KEY document_uploads_document_type_id_foreign');
        } catch (\Throwable $e) {
        }

        // 2. Make column NOT NULL again
        DB::statement('ALTER TABLE document_uploads MODIFY document_type_id BIGINT UNSIGNED NOT NULL');

        // 3. Restore original FK (cascade)
        try {
            DB::statement('
                ALTER TABLE document_uploads
                ADD CONSTRAINT document_uploads_document_type_id_foreign
                FOREIGN KEY (document_type_id)
                REFERENCES document_types(id)
                ON DELETE CASCADE
            ');
        } catch (\Throwable $e) {
        }
    }
};