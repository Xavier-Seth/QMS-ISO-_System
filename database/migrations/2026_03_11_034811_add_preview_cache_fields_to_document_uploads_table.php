<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('document_uploads', function (Blueprint $table) {
            // Original file storage disk
            $table->string('storage_disk', 50)
                ->default('public')
                ->after('file_path');

            // Cached preview PDF metadata
            $table->string('preview_disk', 50)
                ->nullable()
                ->after('storage_disk');

            $table->string('preview_path')
                ->nullable()
                ->after('preview_disk');

            $table->string('preview_mime', 100)
                ->nullable()
                ->after('preview_path');

            $table->timestamp('preview_generated_at')
                ->nullable()
                ->after('preview_mime');

            $table->timestamp('preview_last_accessed_at')
                ->nullable()
                ->after('preview_generated_at');

            $table->string('preview_source_hash', 64)
                ->nullable()
                ->after('preview_last_accessed_at');

            $table->unsignedBigInteger('preview_size')
                ->nullable()
                ->after('preview_source_hash');

            $table->index('preview_last_accessed_at', 'du_preview_last_accessed_idx');
            $table->index('preview_generated_at', 'du_preview_generated_idx');
            $table->index('preview_source_hash', 'du_preview_source_hash_idx');
        });
    }

    public function down(): void
    {
        Schema::table('document_uploads', function (Blueprint $table) {
            $table->dropIndex('du_preview_last_accessed_idx');
            $table->dropIndex('du_preview_generated_idx');
            $table->dropIndex('du_preview_source_hash_idx');

            $table->dropColumn([
                'storage_disk',
                'preview_disk',
                'preview_path',
                'preview_mime',
                'preview_generated_at',
                'preview_last_accessed_at',
                'preview_source_hash',
                'preview_size',
            ]);
        });
    }
};