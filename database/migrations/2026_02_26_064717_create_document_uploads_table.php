<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('document_uploads', function (Blueprint $table) {
            $table->id();

            $table->foreignId('document_type_id')
                ->constrained('document_types')
                ->cascadeOnDelete();

            $table->foreignId('uploaded_by')
                ->constrained('users')
                ->cascadeOnDelete();

            // Only used for F-QMS (keep nullable for R-QMS)
            $table->string('revision')->nullable();
            $table->enum('status', ['Active', 'Obsolete'])->nullable();

            $table->string('file_name');
            $table->string('file_path'); // stored path in storage/app/public
            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->index(['document_type_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_uploads');
    }
};