<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();

            $table->foreignId('series_id')
                ->constrained('document_series')
                ->cascadeOnDelete();

            $table->string('code')->unique();  // R-QMS-001, F-QMS-001
            $table->string('title');           // Filing Chart, Document Change Request
            $table->string('storage')->nullable(); // Physical, Electronic | Physical/Electronic | - | Electronic

            // ✅ for F-QMS structure
            $table->date('initial_issue_date')->nullable();
            $table->string('status')->default('active'); // active | deleted
            $table->text('status_note')->nullable();     // "Deleted as of August 16, 2024"

            $table->timestamps();

            $table->index(['series_id', 'title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_types');
    }
};