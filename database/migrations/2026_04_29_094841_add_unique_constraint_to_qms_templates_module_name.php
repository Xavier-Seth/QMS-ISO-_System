<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qms_templates', function (Blueprint $table) {
            $table->unique(['module', 'name'], 'qms_templates_module_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('qms_templates', function (Blueprint $table) {
            $table->dropUnique('qms_templates_module_name_unique');
        });
    }
};
