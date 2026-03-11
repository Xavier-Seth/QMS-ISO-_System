<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            // Used only for MANUAL series records
            // Null for existing R-QMS / F-QMS / other non-manual document types
            $table->string('manual_category', 20)
                ->nullable()
                ->after('title');

            // controlled | uncontrolled
            // Null for non-manual records
            $table->string('manual_access', 20)
                ->nullable()
                ->after('manual_category');

            // Lets the UI / backend know whether this type expects revision handling
            // Default false so existing rows remain safe unless explicitly updated later
            $table->boolean('requires_revision')
                ->default(false)
                ->after('status_note');

            $table->index(['manual_category', 'manual_access'], 'dt_manual_category_access_idx');
            $table->index(['series_id', 'manual_category'], 'dt_series_manual_category_idx');
        });

        /*
         |------------------------------------------------------------------
         | Backfill existing records safely
         |------------------------------------------------------------------
         | We only mark F-QMS as revision-based because your existing system
         | already treats F-QMS as requiring revision.
         | R-QMS and other series stay false unless changed intentionally.
         */
        DB::table('document_types')
            ->join('document_series', 'document_series.id', '=', 'document_types.series_id')
            ->where('document_series.code_prefix', 'F-QMS')
            ->update([
                'document_types.requires_revision' => true,
            ]);
    }

    public function down(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->dropIndex('dt_manual_category_access_idx');
            $table->dropIndex('dt_series_manual_category_idx');

            $table->dropColumn([
                'manual_category',
                'manual_access',
                'requires_revision',
            ]);
        });
    }
};