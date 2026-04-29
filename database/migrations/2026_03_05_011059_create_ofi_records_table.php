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

            // connect to DocumentType: R-QMS-018
            $table->foreignId('document_type_id')->constrained('document_types');

            // searchable fields (handy)
            $table->string('ofi_no')->nullable()->index();
            $table->string('ref_no')->nullable()->index();
            $table->string('to')->nullable()->index();

            $table->enum('status', ['draft', 'submitted'])->default('draft');

            // store EVERYTHING here (same shape as your Vue form)
            $table->json('data');

            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ofi_records');
    }
};
