<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qms_templates', function (Blueprint $table) {
            $table->id();
            $table->string('module', 50);
            $table->string('name');
            $table->string('original_file_name');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('storage_disk', 50)->default('private');
            $table->boolean('is_active')->default(false);
            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('module');
            $table->index(['module', 'is_active']);
            $table->unique(['module', 'name'], 'qms_templates_module_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qms_templates');
    }
};
