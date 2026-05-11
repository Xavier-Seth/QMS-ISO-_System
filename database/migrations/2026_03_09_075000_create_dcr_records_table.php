<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dcr_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('document_type_id')
                ->constrained('document_types')
                ->cascadeOnDelete();

            $table->string('dcr_no')->nullable();
            $table->string('to_for')->nullable();
            $table->string('from')->nullable();

            $table->string('status')->default('draft');
            $table->string('workflow_status')->nullable();
            $table->string('resolution_status')->nullable();

            $table->text('rejection_reason')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->json('data')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('dcr_no', 'dcr_records_dcr_no_idx');
            $table->index(['status', 'workflow_status'], 'dcr_records_status_workflow_idx');
            $table->index(['created_by', 'workflow_status'], 'dcr_records_created_by_workflow_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dcr_records');
    }
};
