<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('dcr_records', function (Blueprint $table) {

            $table->foreignId('document_type_id')
                ->nullable()
                ->after('id')
                ->constrained('document_types')
                ->cascadeOnDelete();

            $table->string('dcr_no')->nullable()->after('document_type_id');

            $table->string('to_for')->nullable()->after('dcr_no');

            $table->string('from')->nullable()->after('to_for');

            $table->string('status')
                ->default('draft')
                ->after('from');

            $table->json('data')
                ->nullable()
                ->after('status');

            $table->foreignId('created_by')
                ->nullable()
                ->after('data')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->after('created_by')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dcr_records', function (Blueprint $table) {

            $table->dropForeign(['document_type_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);

            $table->dropColumn([
                'document_type_id',
                'dcr_no',
                'to_for',
                'from',
                'status',
                'data',
                'created_by',
                'updated_by'
            ]);
        });
    }
};