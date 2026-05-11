<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ofi_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('document_type_id')->constrained('document_types');

            $table->string('ofi_no')->nullable();
            $table->string('ref_no')->nullable();
            $table->string('to')->nullable();

            $table->enum('status', ['draft', 'submitted'])->default('draft');
            $table->enum('workflow_status', ['pending', 'approved', 'rejected'])->nullable();
            $table->enum('resolution_status', ['open', 'ongoing', 'closed'])->default('open');

            $table->text('rejection_reason')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->json('data');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('ofi_no', 'ofi_records_ofi_no_idx');
            $table->index('ref_no', 'ofi_records_ref_no_idx');
            $table->index('to');
            $table->index('workflow_status');
            $table->index('resolution_status');
            $table->index(['status', 'workflow_status'], 'ofi_records_status_workflow_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ofi_records');
    }
};
