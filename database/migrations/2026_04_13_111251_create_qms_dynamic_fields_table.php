<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('qms_dynamic_fields', function (Blueprint $table) {
            $table->id();

            // DCR for now, but reusable later for OFI/CAR
            $table->string('module', 50);

            // What admin sees
            $table->string('label');

            // Placeholder/data key, e.g. officeCode
            $table->string('field_key');

            // Keep simple for turnover-friendly setup
            // Suggested values: text, textarea, date
            $table->string('field_type', 30)->default('text');

            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);

            // Optional but very useful for ordering in UI
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index('module');
            $table->index(['module', 'is_active']);
            $table->unique(['module', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qms_dynamic_fields');
    }
};