<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('qms_templates', function (Blueprint $table) {
            $table->id();

            // DCR for now, but reusable later for OFI/CAR
            $table->string('module', 50);

            // Friendly/template display name
            $table->string('name');

            // Original uploaded filename from user
            $table->string('original_file_name');

            // Stored file name/path info
            $table->string('file_name');
            $table->string('file_path');
            $table->string('storage_disk', 50)->default('public');

            // Active template switch per module
            $table->boolean('is_active')->default(false);

            // Admin who uploaded it
            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('module');
            $table->index(['module', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qms_templates');
    }
};