<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('car_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('document_type_id')
                ->constrained('document_types')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('car_no')->nullable();
            $table->string('ref_no')->nullable();
            $table->string('dept_section')->nullable();
            $table->string('auditor')->nullable();

            $table->string('status')->default('draft');
            $table->string('workflow_status')->nullable();
            $table->string('resolution_status')->default('open');

            $table->text('rejection_reason')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->foreignId('rejected_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->json('data')->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('updated_by')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->timestamps();

            $table->index('car_no');
            $table->index('status');
            $table->index('workflow_status');
            $table->index('resolution_status');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_records');
    }
};