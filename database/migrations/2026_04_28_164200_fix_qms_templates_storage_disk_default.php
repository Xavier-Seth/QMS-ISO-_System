<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('qms_templates', function (Blueprint $table) {
            $table->string('storage_disk', 50)->default('private')->change();
        });
    }

    public function down(): void
    {
        Schema::table('qms_templates', function (Blueprint $table) {
            $table->string('storage_disk', 50)->default('public')->change();
        });
    }
};