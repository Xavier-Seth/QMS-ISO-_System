<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ofi_records', function (Blueprint $table) {
            $table->enum('workflow_status', ['pending', 'approved', 'rejected'])
                ->nullable()
                ->after('status');

            $table->enum('resolution_status', ['open', 'ongoing', 'closed'])
                ->default('open')
                ->after('workflow_status');

            $table->index('workflow_status');
            $table->index('resolution_status');
        });
    }

    public function down(): void
    {
        Schema::table('ofi_records', function (Blueprint $table) {
            $table->dropIndex(['workflow_status']);
            $table->dropIndex(['resolution_status']);
            $table->dropColumn(['workflow_status', 'resolution_status']);
        });
    }
};