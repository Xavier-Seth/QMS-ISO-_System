<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();

        $rows = DB::table('document_series')
            ->whereIn('code_prefix', ['DPCR', 'UPCR'])
            ->pluck('code_prefix')
            ->all();

        $toInsert = [];

        if (!in_array('DPCR', $rows)) {
            $toInsert[] = [
                'code_prefix' => 'DPCR',
                'name' => 'Department Performance Commitment and Review',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!in_array('UPCR', $rows)) {
            $toInsert[] = [
                'code_prefix' => 'UPCR',
                'name' => 'University Performance Commitment and Review',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($toInsert)) {
            DB::table('document_series')->insert($toInsert);
        }
    }

    public function down(): void
    {
        DB::table('document_series')
            ->whereIn('code_prefix', ['DPCR', 'UPCR'])
            ->delete();
    }
};