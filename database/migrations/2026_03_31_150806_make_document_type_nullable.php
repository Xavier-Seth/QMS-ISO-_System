<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use RuntimeException;

return new class extends Migration {
    public function up(): void
    {
        DB::beginTransaction();

        try {
            DB::statement('ALTER TABLE document_uploads DROP FOREIGN KEY document_uploads_document_type_id_foreign');
            DB::statement('ALTER TABLE document_uploads MODIFY document_type_id BIGINT UNSIGNED NULL');
            DB::statement('
                ALTER TABLE document_uploads
                ADD CONSTRAINT document_uploads_document_type_id_foreign
                FOREIGN KEY (document_type_id)
                REFERENCES document_types(id)
                ON DELETE SET NULL
            ');

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function down(): void
    {
        $nullCount = DB::table('document_uploads')
            ->whereNull('document_type_id')
            ->count();

        if ($nullCount > 0) {
            throw new RuntimeException(
                'Cannot rollback document_type_id to NOT NULL because document_uploads contains NULL document_type_id rows.'
            );
        }

        DB::beginTransaction();

        try {
            DB::statement('ALTER TABLE document_uploads DROP FOREIGN KEY document_uploads_document_type_id_foreign');
            DB::statement('ALTER TABLE document_uploads MODIFY document_type_id BIGINT UNSIGNED NOT NULL');
            DB::statement('
                ALTER TABLE document_uploads
                ADD CONSTRAINT document_uploads_document_type_id_foreign
                FOREIGN KEY (document_type_id)
                REFERENCES document_types(id)
                ON DELETE CASCADE
            ');

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
};