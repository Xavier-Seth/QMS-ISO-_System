<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->string('backup_frequency')->default('weekly')->after('maintenance_mode');
            $table->string('storage_location')->default('local')->after('backup_frequency');
            $table->boolean('auto_backup')->default(false)->after('storage_location');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn(['backup_frequency', 'storage_location', 'auto_backup']);
        });
    }
};
