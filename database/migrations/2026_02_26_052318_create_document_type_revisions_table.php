<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('document_type_revisions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('document_type_id')
                ->constrained('document_types')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('revision_no'); // 1..5
            $table->date('revision_date')->nullable();

            $table->timestamps();

            $table->unique(['document_type_id', 'revision_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_type_revisions');
    }
};