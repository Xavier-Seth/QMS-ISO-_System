<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('document_series', function (Blueprint $table) {
            $table->id();
            $table->string('code_prefix')->unique(); // R-QMS, F-QMS, IPCR, MANUAL
            $table->string('name');                  // Records, Forms, etc.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_series');
    }
};