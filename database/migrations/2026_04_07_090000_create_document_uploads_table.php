<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_uploads', function (Blueprint $table) {
            $table->id();

            $table->foreignId('document_type_id')
                ->constrained('document_types')
                ->restrictOnDelete();

            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('ofi_record_id')
                ->nullable()
                ->constrained('ofi_records')
                ->nullOnDelete();

            $table->foreignId('dcr_record_id')
                ->nullable()
                ->constrained('dcr_records')
                ->nullOnDelete();

            $table->foreignId('car_record_id')
                ->nullable()
                ->constrained('car_records')
                ->nullOnDelete();

            $table->string('revision')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('performance_category')->nullable();
            $table->string('performance_record_type', 30)->nullable();
            $table->string('period')->nullable();
            $table->enum('status', ['Active', 'Obsolete'])->nullable();

            $table->string('file_name');
            $table->string('file_path');
            $table->string('storage_disk', 50)->default('public');
            $table->string('preview_disk', 50)->nullable();
            $table->string('preview_path')->nullable();
            $table->string('preview_mime', 100)->nullable();
            $table->timestamp('preview_generated_at')->nullable();
            $table->timestamp('preview_last_accessed_at')->nullable();
            $table->string('preview_source_hash', 64)->nullable();
            $table->unsignedBigInteger('preview_size')->nullable();

            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->index(['document_type_id', 'status']);
            $table->index(
                ['document_type_id', 'year', 'performance_category', 'period'],
                'document_uploads_perf_idx'
            );
            $table->index(
                ['document_type_id', 'performance_record_type', 'year', 'period'],
                'document_uploads_perf_record_year_period_idx'
            );
            $table->index('preview_last_accessed_at', 'du_preview_last_accessed_idx');
            $table->index('preview_generated_at', 'du_preview_generated_idx');
            $table->index('preview_source_hash', 'du_preview_source_hash_idx');
            $table->index('dcr_record_id', 'document_uploads_dcr_record_id_idx');
            $table->index('car_record_id', 'document_uploads_car_record_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_uploads');
    }
};
