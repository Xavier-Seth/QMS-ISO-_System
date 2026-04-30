<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $nullCount = DB::table('dcr_records')->whereNull('document_type_id')->count();

        if ($nullCount > 0) {
            throw new \RuntimeException(
                "Cannot make dcr_records.document_type_id NOT NULL: {$nullCount} row(s) have NULL values. Resolve them first."
            );
        }

        Schema::table('dcr_records', function (Blueprint $table) {
            $table->unsignedBigInteger('document_type_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('dcr_records', function (Blueprint $table) {
            $table->unsignedBigInteger('document_type_id')->nullable()->change();
        });
    }
};
