<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();

            $table->foreignId('series_id')
                ->constrained('document_series')
                ->cascadeOnDelete();

            $table->string('code')->unique();
            $table->string('title');
            $table->string('manual_category', 20)->nullable();
            $table->string('manual_access', 20)->nullable();
            $table->string('storage')->nullable();
            $table->date('initial_issue_date')->nullable();
            $table->string('status')->default('active');
            $table->text('status_note')->nullable();
            $table->boolean('requires_revision')->default(false);

            $table->timestamps();

            $table->index(['series_id', 'title']);
            $table->index(['manual_category', 'manual_access'], 'dt_manual_category_access_idx');
            $table->index(['series_id', 'manual_category'], 'dt_series_manual_category_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_types');
    }
};
