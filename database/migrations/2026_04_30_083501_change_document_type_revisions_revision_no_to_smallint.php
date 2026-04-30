<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_type_revisions', function (Blueprint $table) {
            $table->unsignedSmallInteger('revision_no')->change();
        });
    }

    public function down(): void
    {
        $maxRevisionNo = DB::table('document_type_revisions')->max('revision_no');

        if ($maxRevisionNo !== null && $maxRevisionNo > 255) {
            throw new \RuntimeException(
                "Cannot downgrade revision_no to TINYINT UNSIGNED: max value {$maxRevisionNo} exceeds 255."
            );
        }

        Schema::table('document_type_revisions', function (Blueprint $table) {
            $table->unsignedTinyInteger('revision_no')->change();
        });
    }
};
