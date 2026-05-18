<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('system_name')->default('Quality Management System');
            $table->string('institution_name')->default('Leyte Normal University');
            $table->string('office_name')->default('QMS (ISO) Office');
            $table->boolean('maintenance_mode')->default(false);
            $table->string('backup_frequency')->default('weekly');
            $table->string('storage_location')->default('local');
            $table->boolean('auto_backup')->default(false);
            $table->string('e_signature_path')->nullable();
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
