<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rename conflicting rows before adding the unique constraint.
        // For each (module, name) group with duplicates, keep the lowest-id
        // row unchanged and append " (id: X)" to each extra row.
        $duplicates = DB::table('qms_templates')
            ->select('module', 'name')
            ->groupBy('module', 'name')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $extras = DB::table('qms_templates')
                ->where('module', $duplicate->module)
                ->where('name', $duplicate->name)
                ->orderBy('id')
                ->skip(1)
                ->get(['id', 'name']);

            foreach ($extras as $row) {
                DB::table('qms_templates')
                    ->where('id', $row->id)
                    ->update(['name' => $row->name.' (id: '.$row->id.')']);
            }
        }

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
