<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('dcr_records', function (Blueprint $table) {
            if (!Schema::hasColumn('dcr_records', 'workflow_status')) {
                $table->string('workflow_status')->nullable()->after('status');
            }

            if (!Schema::hasColumn('dcr_records', 'resolution_status')) {
                $table->string('resolution_status')->nullable()->after('workflow_status');
            }

            if (!Schema::hasColumn('dcr_records', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('resolution_status');
            }

            if (!Schema::hasColumn('dcr_records', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejection_reason');
            }

            if (!Schema::hasColumn('dcr_records', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->after('rejected_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            $table->index(['status', 'workflow_status'], 'dcr_records_status_workflow_idx');
            $table->index(['created_by', 'workflow_status'], 'dcr_records_created_by_workflow_idx');
        });
    }

    public function down(): void
    {
        Schema::table('dcr_records', function (Blueprint $table) {
            $table->dropIndex('dcr_records_status_workflow_idx');
            $table->dropIndex('dcr_records_created_by_workflow_idx');

            if (Schema::hasColumn('dcr_records', 'rejected_by')) {
                $table->dropConstrainedForeignId('rejected_by');
            }

            if (Schema::hasColumn('dcr_records', 'rejected_at')) {
                $table->dropColumn('rejected_at');
            }

            if (Schema::hasColumn('dcr_records', 'rejection_reason')) {
                $table->dropColumn('rejection_reason');
            }

            if (Schema::hasColumn('dcr_records', 'resolution_status')) {
                $table->dropColumn('resolution_status');
            }

            if (Schema::hasColumn('dcr_records', 'workflow_status')) {
                $table->dropColumn('workflow_status');
            }
        });
    }
};