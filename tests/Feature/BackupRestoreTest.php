<?php

namespace Tests\Feature;

use App\Models\DocumentSeries;
use App\Models\DocumentType;
use App\Models\DocumentUpload;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class BackupRestoreTest extends TestCase
{
    protected BackupService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropAllTables();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role')->default('user');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('system_name')->default('Quality Management System');
            $table->string('institution_name')->default('Leyte Normal University');
            $table->string('office_name')->default('QMS (ISO) Office');
            $table->boolean('maintenance_mode')->default(false);
            $table->string('e_signature_path')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('backup_frequency')->default('weekly');
            $table->string('storage_location')->default('local');
            $table->boolean('auto_backup')->default(false);
            $table->timestamps();
        });

        Schema::create('document_series', function (Blueprint $table) {
            $table->id();
            $table->string('code_prefix')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('series_id')->constrained('document_series')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('title');
            $table->string('storage')->nullable();
            $table->string('status')->default('active');
            $table->text('status_note')->nullable();
            $table->boolean('requires_revision')->default(false);
            $table->timestamps();
        });

        Schema::create('document_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->nullable()->constrained('document_types')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revision')->nullable();
            $table->integer('year')->nullable();
            $table->string('performance_category')->nullable();
            $table->string('performance_record_type')->nullable();
            $table->string('period')->nullable();
            $table->unsignedBigInteger('ofi_record_id')->nullable();
            $table->unsignedBigInteger('dcr_record_id')->nullable();
            $table->unsignedBigInteger('car_record_id')->nullable();
            $table->string('status')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->text('remarks')->nullable();
            $table->string('storage_disk')->nullable();
            $table->string('preview_disk')->nullable();
            $table->string('preview_path')->nullable();
            $table->string('preview_mime')->nullable();
            $table->timestamp('preview_generated_at')->nullable();
            $table->timestamp('preview_last_accessed_at')->nullable();
            $table->string('preview_source_hash')->nullable();
            $table->unsignedBigInteger('preview_size')->nullable();
            $table->timestamps();
        });

        SystemSetting::instance();

        $this->service = new BackupService;
    }

    // -----------------------------------------------------------------------
    // Helper
    // -----------------------------------------------------------------------

    /**
     * Build a real ZIP at a temp path with a properly structured manifest.
     *
     * Each $files entry: ['zip_entry' => ..., 'file_path' => ..., 'storage_disk' => ..., 'content' => ..., 'upload_row' => ...]
     */
    private function buildZipWithManifest(array $files): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'restore_test_').'.zip';

        $zip = new ZipArchive;
        $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $manifestFiles = [];

        foreach ($files as $file) {
            $zip->addFromString($file['zip_entry'], $file['content']);
            $manifestFiles[] = [
                'zip_entry' => $file['zip_entry'],
                'file_name' => basename($file['file_path']),
                'file_path' => $file['file_path'],
                'storage_disk' => $file['storage_disk'],
                'upload_row' => $file['upload_row'] ?? null,
            ];
        }

        $zip->addFromString('manifest.json', json_encode([
            'created_at' => now()->toIso8601String(),
            'file_count' => count($files),
            'files' => $manifestFiles,
        ]));

        $zip->close();

        return $tmpPath;
    }

    // -----------------------------------------------------------------------
    // Test 1 — Happy path: file restored to manifest file_path, not zip_entry
    // -----------------------------------------------------------------------

    public function test_restore_writes_file_to_manifest_file_path_not_zip_entry(): void
    {
        Storage::fake('private');

        // zip_entry (organisational path in ZIP) differs from file_path (original DB path)
        $zipPath = $this->buildZipWithManifest([
            [
                'zip_entry' => 'QMS/F-QMS-001/1_document.pdf',
                'file_path' => 'qms/F-QMS-001/abc123hash.pdf',
                'storage_disk' => 'private',
                'content' => 'restored content',
            ],
        ]);

        $count = $this->service->restore($zipPath);

        $this->assertSame(1, $count);

        // Must exist at the original DB path (from manifest file_path)
        Storage::disk('private')->assertExists('qms/F-QMS-001/abc123hash.pdf');

        // Must NOT exist at the ZIP entry path
        Storage::disk('private')->assertMissing('QMS/F-QMS-001/1_document.pdf');

        @unlink($zipPath);
    }

    // -----------------------------------------------------------------------
    // Test 2 — Null storage_disk falls back to private (backup side)
    // -----------------------------------------------------------------------

    public function test_null_storage_disk_falls_back_to_private_for_backup(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $series = DocumentSeries::create(['code_prefix' => 'QMS', 'name' => 'QMS Forms']);
        $type = DocumentType::create(['series_id' => $series->id, 'code' => 'F-QMS-001', 'title' => 'Test Doc']);

        // File lives on private disk
        Storage::disk('private')->put('qms/F-QMS-001/file.pdf', 'file content');

        // Upload record has no storage_disk — simulates old-style records
        DocumentUpload::create([
            'document_type_id' => $type->id,
            'file_name' => 'file.pdf',
            'file_path' => 'qms/F-QMS-001/file.pdf',
            'storage_disk' => null,
        ]);

        $result = $this->service->createBackup();

        $zipPath = Storage::disk('private')->path('backups/'.$result['filename']);
        $zip = new ZipArchive;
        $zip->open($zipPath);
        $numFiles = $zip->numFiles; // manifest.json + any backed-up files
        $zip->close();

        // Expect 2 entries: manifest.json + the file.
        // If fallback was still 'public', the file would not be found and numFiles = 1.
        $this->assertSame(
            2,
            $numFiles,
            'File with null storage_disk was NOT included in backup — private fallback is not working.'
        );
    }

    // -----------------------------------------------------------------------
    // Test 3 — Write failure: FilesystemException (extends RuntimeException) bubbles up
    // -----------------------------------------------------------------------

    public function test_restore_throws_when_disk_put_throws(): void
    {
        $failingDisk = \Mockery::mock();
        $failingDisk->shouldReceive('put')->andThrow(
            new \League\Flysystem\UnableToWriteFile('disk write error')
        );

        Storage::shouldReceive('disk')
            ->with('private')
            ->andReturn($failingDisk);

        $zipPath = $this->buildZipWithManifest([
            [
                'zip_entry' => 'QMS/F-QMS-001/1_document.pdf',
                'file_path' => 'qms/F-QMS-001/file.pdf',
                'storage_disk' => 'private',
                'content' => 'content',
            ],
        ]);

        $this->expectException(RuntimeException::class);

        try {
            $this->service->restore($zipPath);
        } finally {
            @unlink($zipPath);
        }
    }

    // -----------------------------------------------------------------------
    // Test 4 — Count accuracy: returned count = files written, not ZIP entries
    // -----------------------------------------------------------------------

    public function test_restore_count_equals_number_of_files_actually_written(): void
    {
        Storage::fake('private');

        $zipPath = $this->buildZipWithManifest([
            [
                'zip_entry' => 'QMS/F-QMS-001/1_file1.pdf',
                'file_path' => 'qms/F-QMS-001/file1.pdf',
                'storage_disk' => 'private',
                'content' => 'content one',
            ],
            [
                'zip_entry' => 'QMS/F-QMS-002/2_file2.pdf',
                'file_path' => 'qms/F-QMS-002/file2.pdf',
                'storage_disk' => 'private',
                'content' => 'content two',
            ],
        ]);

        $count = $this->service->restore($zipPath);

        $this->assertSame(2, $count);
        Storage::disk('private')->assertExists('qms/F-QMS-001/file1.pdf');
        Storage::disk('private')->assertExists('qms/F-QMS-002/file2.pdf');

        @unlink($zipPath);
    }

    // -----------------------------------------------------------------------
    // Test 5 — DB row upserted: deleted document_uploads row restored from manifest
    // -----------------------------------------------------------------------

    public function test_restore_upserts_document_upload_row(): void
    {
        Storage::fake('private');

        $upload = DocumentUpload::create([
            'file_name' => 'test.pdf',
            'file_path' => 'qms/F-QMS-001/abc123hash.pdf',
            'storage_disk' => 'private',
            'status' => 'published',
        ]);

        $uploadId = $upload->id;
        $uploadRow = array_merge(['id' => $uploadId], $upload->only($upload->getFillable()));

        $zipPath = $this->buildZipWithManifest([
            [
                'zip_entry' => "QMS/F-QMS-001/{$uploadId}_test.pdf",
                'file_path' => 'qms/F-QMS-001/abc123hash.pdf',
                'storage_disk' => 'private',
                'content' => 'file content',
                'upload_row' => $uploadRow,
            ],
        ]);

        // Delete the DB row — simulates a deleted record that needs restoring
        $upload->delete();
        $this->assertDatabaseMissing('document_uploads', ['id' => $uploadId]);

        $this->service->restore($zipPath);

        // Row must be back with correct data
        $this->assertDatabaseHas('document_uploads', [
            'id' => $uploadId,
            'file_name' => 'test.pdf',
            'file_path' => 'qms/F-QMS-001/abc123hash.pdf',
            'status' => 'published',
        ]);

        Storage::disk('private')->assertExists('qms/F-QMS-001/abc123hash.pdf');

        @unlink($zipPath);
    }

    // -----------------------------------------------------------------------
    // Test 6 — uploaded_by set to null when the original uploader no longer exists
    // -----------------------------------------------------------------------

    public function test_restore_sets_uploaded_by_null_when_user_missing(): void
    {
        Storage::fake('private');

        $upload = DocumentUpload::create([
            'file_name' => 'doc.pdf',
            'file_path' => 'qms/F-QMS-001/dochash.pdf',
            'storage_disk' => 'private',
            'uploaded_by' => null,
        ]);

        $uploadId = $upload->id;
        $uploadRow = array_merge(['id' => $uploadId], $upload->only($upload->getFillable()));
        // Simulate a manifest where the original uploader (ID 9999) no longer exists in users
        $uploadRow['uploaded_by'] = 9999;

        $zipPath = $this->buildZipWithManifest([
            [
                'zip_entry' => "QMS/F-QMS-001/{$uploadId}_doc.pdf",
                'file_path' => 'qms/F-QMS-001/dochash.pdf',
                'storage_disk' => 'private',
                'content' => 'doc content',
                'upload_row' => $uploadRow,
            ],
        ]);

        $upload->delete();

        $this->service->restore($zipPath);

        // uploaded_by must be null — not the deleted user's ID — to avoid FK violations
        $this->assertDatabaseHas('document_uploads', [
            'id' => $uploadId,
            'uploaded_by' => null,
        ]);

        @unlink($zipPath);
    }
}
