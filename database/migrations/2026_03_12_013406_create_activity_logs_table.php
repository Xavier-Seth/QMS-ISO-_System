<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('user_name')->nullable();
            $table->string('department')->nullable();
            $table->string('office_location')->nullable();

            $table->string('module', 50);          // documents, manuals, ofi, dcr, users, settings, auth
            $table->string('action', 50);          // created, updated, deleted, uploaded, downloaded, previewed, published, login, logout
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();

            $table->string('record_label')->nullable();   // F-QMS-007, OFI-0004, Angela Ornameta
            $table->string('file_type', 50)->nullable(); // pdf, docx, xlsx, manual, etc.

            $table->text('description');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index('user_id');
            $table->index('department');
            $table->index('module');
            $table->index('action');
            $table->index('file_type');
            $table->index(['entity_type', 'entity_id']);
            $table->index(['module', 'action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};