<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index('department', 'users_department_idx');
            $table->index('position', 'users_position_idx');
            $table->index('office_location', 'users_office_location_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_department_idx');
            $table->dropIndex('users_position_idx');
            $table->dropIndex('users_office_location_idx');
        });
    }
};
