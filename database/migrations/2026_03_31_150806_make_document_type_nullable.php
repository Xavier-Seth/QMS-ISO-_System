<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use RuntimeException;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('
            ALTER TABLE document_uploads
            DROP FOREIGN KEY document_uploads_document_type_id_foreign,
            MODIFY document_type_id BIGINT UNSIGNED NULL,
            ADD CONSTRAINT document_uploads_document_type_id_foreign
                FOREIGN KEY (document_type_id)
                REFERENCES document_types(id)
                ON DELETE SET NULL
        ');
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

        DB::statement('
            ALTER TABLE document_uploads
            DROP FOREIGN KEY document_uploads_document_type_id_foreign,
            MODIFY document_type_id BIGINT UNSIGNED NOT NULL,
            ADD CONSTRAINT document_uploads_document_type_id_foreign
                FOREIGN KEY (document_type_id)
                REFERENCES document_types(id)
                ON DELETE CASCADE
        ');
    }
};