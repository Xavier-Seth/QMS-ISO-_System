<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $carFks = array_column(Schema::getForeignKeys('car_records'), 'name');

        Schema::table('car_records', function (Blueprint $table) use ($carFks) {
            if (in_array('car_records_created_by_foreign', $carFks)) {
                $table->dropForeign('car_records_created_by_foreign');
            }
            if (in_array('car_records_updated_by_foreign', $carFks)) {
                $table->dropForeign('car_records_updated_by_foreign');
            }
        });

        Schema::table('car_records', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->change();
            $table->unsignedBigInteger('updated_by')->nullable()->change();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        $uploadFks = array_column(Schema::getForeignKeys('document_uploads'), 'name');

        Schema::table('document_uploads', function (Blueprint $table) use ($uploadFks) {
            if (in_array('document_uploads_uploaded_by_foreign', $uploadFks)) {
                $table->dropForeign('document_uploads_uploaded_by_foreign');
            }
        });

        Schema::table('document_uploads', function (Blueprint $table) {
            $table->unsignedBigInteger('uploaded_by')->nullable()->change();
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        $carFks = array_column(Schema::getForeignKeys('car_records'), 'name');

        Schema::table('car_records', function (Blueprint $table) use ($carFks) {
            if (in_array('car_records_created_by_foreign', $carFks)) {
                $table->dropForeign('car_records_created_by_foreign');
            }
            if (in_array('car_records_updated_by_foreign', $carFks)) {
                $table->dropForeign('car_records_updated_by_foreign');
            }
        });

        Schema::table('car_records', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable(false)->change();
            $table->unsignedBigInteger('updated_by')->nullable(false)->change();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->restrictOnDelete();
        });

        $uploadFks = array_column(Schema::getForeignKeys('document_uploads'), 'name');

        Schema::table('document_uploads', function (Blueprint $table) use ($uploadFks) {
            if (in_array('document_uploads_uploaded_by_foreign', $uploadFks)) {
                $table->dropForeign('document_uploads_uploaded_by_foreign');
            }
        });

        Schema::table('document_uploads', function (Blueprint $table) {
            $table->unsignedBigInteger('uploaded_by')->nullable(false)->change();
            $table->foreign('uploaded_by')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
