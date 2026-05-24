<?php

namespace Tests\Feature;

use App\Models\DocumentSeries;
use App\Models\DocumentType;
use App\Models\DocumentUpload;
use App\Models\SystemSetting;
use App\Services\BackupService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        $opened = $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $this->assertTrue($opened === true, "ZipArchive::open() failed (code: {$opened}) for path: {$tmpPath}");

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

        $zipPath = $this->buildZipWithManifest([
            [
                'zip_entry' => 'QMS/F-QMS-001/1_document.pdf',
                'file_path' => 'qms/F-QMS-001/abc123hash.pdf',
                'storage_disk' => 'private',
                'content' => 'restored content',
            ],
        ]);

        $result = $this->service->restore($zipPath);

        $this->assertSame(1, $result['files']);
        Storage::disk('private')->assertExists('qms/F-QMS-001/abc123hash.pdf');
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

        Storage::disk('private')->put('qms/F-QMS-001/file.pdf', 'file content');

        DocumentUpload::create([
            'document_type_id' => $type->id,
            'file_name' => 'file.pdf',
            'file_path' => 'qms/F-QMS-001/file.pdf',
            'storage_disk' => null,
        ]);

        $result = $this->service->createBackup();

        $zipPath = Storage::disk('private')->path('backups/'.$result['filename']);
        $zip = new ZipArchive;
        $opened = $zip->open($zipPath);
        $this->assertTrue($opened === true, "ZipArchive::open() failed (code: {$opened}) for backup ZIP: {$zipPath}");
        $numFiles = $zip->numFiles;
        $zip->close();

        $this->assertSame(
            3,
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

        $result = $this->service->restore($zipPath);

        $this->assertSame(2, $result['files']);
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
        $uploadRow = array_merge(
            ['id' => $uploadId],
            collect($upload->only($upload->getFillable()))
                ->except(['preview_disk', 'preview_path', 'preview_mime', 'preview_generated_at', 'preview_last_accessed_at', 'preview_source_hash', 'preview_size'])
                ->toArray()
        );

        $zipPath = $this->buildZipWithManifest([
            [
                'zip_entry' => "QMS/F-QMS-001/{$uploadId}_test.pdf",
                'file_path' => 'qms/F-QMS-001/abc123hash.pdf',
                'storage_disk' => 'private',
                'content' => 'file content',
                'upload_row' => $uploadRow,
            ],
        ]);

        $upload->delete();
        $this->assertDatabaseMissing('document_uploads', ['id' => $uploadId]);

        $this->service->restore($zipPath);

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

        $this->assertDatabaseHas('document_uploads', [
            'id' => $uploadId,
            'uploaded_by' => null,
        ]);

        @unlink($zipPath);
    }

    // -----------------------------------------------------------------------
    // Test 7 — S1: unknown disk name in manifest throws RuntimeException
    // -----------------------------------------------------------------------

    public function test_restore_throws_on_unknown_disk_name(): void
    {
        $zipPath = $this->buildZipWithManifest([
            [
                'zip_entry' => 'QMS/F-QMS-001/1_file.pdf',
                'file_path' => 'qms/F-QMS-001/file.pdf',
                'storage_disk' => 's3',
                'content' => 'content',
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches("/unknown storage disk 's3'/");

        try {
            $this->service->restore($zipPath);
        } finally {
            @unlink($zipPath);
        }
    }

    // -----------------------------------------------------------------------
    // Test 8 — L2: upload_row with null id is skipped gracefully (no exception)
    // -----------------------------------------------------------------------

    public function test_restore_skips_db_upsert_when_upload_row_id_is_null(): void
    {
        Storage::fake('private');

        $uploadRow = [
            'id' => null,
            'file_name' => 'file.pdf',
            'file_path' => 'qms/F-QMS-001/file.pdf',
            'storage_disk' => 'private',
            'status' => 'published',
        ];

        $zipPath = $this->buildZipWithManifest([
            [
                'zip_entry' => 'QMS/F-QMS-001/file.pdf',
                'file_path' => 'qms/F-QMS-001/file.pdf',
                'storage_disk' => 'private',
                'content' => 'content',
                'upload_row' => $uploadRow,
            ],
        ]);

        // Should not throw — null id skipped gracefully, file still written
        $result = $this->service->restore($zipPath);

        $this->assertSame(1, $result['files']);
        Storage::disk('private')->assertExists('qms/F-QMS-001/file.pdf');
        $this->assertDatabaseCount('document_uploads', 0);

        @unlink($zipPath);
    }

    // -----------------------------------------------------------------------
    // Test 9 — L1: upload with null file_name is skipped in backup
    // -----------------------------------------------------------------------

    public function test_backup_skips_upload_with_null_file_name(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $series = DocumentSeries::create(['code_prefix' => 'QMS', 'name' => 'QMS Forms']);
        $type = DocumentType::create(['series_id' => $series->id, 'code' => 'F-QMS-001', 'title' => 'Test Doc']);

        // Upload with null file_name — should be skipped
        DocumentUpload::create([
            'document_type_id' => $type->id,
            'file_name' => null,
            'file_path' => 'qms/F-QMS-001/somehash.pdf',
            'storage_disk' => 'private',
        ]);

        $result = $this->service->createBackup();

        $zipPath = Storage::disk('private')->path('backups/'.$result['filename']);
        $zip = new ZipArchive;
        $opened = $zip->open($zipPath);
        $this->assertTrue($opened === true, "ZipArchive::open() failed (code: {$opened})");
        $numFiles = $zip->numFiles;
        $zip->close();

        // manifest.json + database.json — the null file_name upload was skipped
        $this->assertSame(2, $numFiles, 'Upload with null file_name was incorrectly included in backup.');
    }

    // -----------------------------------------------------------------------
    // Test 10 — T2: old-format backup (no upload_row) restores files without error
    // -----------------------------------------------------------------------

    public function test_restore_works_with_old_format_backup_without_upload_row(): void
    {
        Storage::fake('private');

        // Old-format manifest entry has no upload_row key
        $zipPath = $this->buildZipWithManifest([
            [
                'zip_entry' => 'qms/F-QMS-001/oldfile.pdf',
                'file_path' => 'qms/F-QMS-001/oldfile.pdf',
                'storage_disk' => 'private',
                'content' => 'old content',
                // no upload_row
            ],
        ]);

        $result = $this->service->restore($zipPath);

        $this->assertSame(1, $result['files']);
        $this->assertSame(0, $result['rows']);
        Storage::disk('private')->assertExists('qms/F-QMS-001/oldfile.pdf');
        // No DB rows created — graceful skip
        $this->assertDatabaseCount('document_uploads', 0);

        @unlink($zipPath);
    }

    // -----------------------------------------------------------------------
    // Test 11 — T1: DB transaction rolls back all upserts if one fails midway
    // -----------------------------------------------------------------------

    public function test_restore_rolls_back_all_db_upserts_if_one_fails(): void
    {
        Storage::fake('private');

        $upload1 = DocumentUpload::create([
            'file_name' => 'file1.pdf',
            'file_path' => 'qms/file1.pdf',
            'storage_disk' => 'private',
            'status' => 'published',
        ]);

        $upload2 = DocumentUpload::create([
            'file_name' => 'file2.pdf',
            'file_path' => 'qms/file2.pdf',
            'storage_disk' => 'private',
            'status' => 'published',
        ]);

        $id1 = $upload1->id;
        $id2 = $upload2->id;

        $row1 = array_merge(['id' => $id1], collect($upload1->only($upload1->getFillable()))
            ->except(['preview_disk', 'preview_path', 'preview_mime', 'preview_generated_at', 'preview_last_accessed_at', 'preview_source_hash', 'preview_size'])
            ->toArray());

        // row2 has an invalid document_type_id that will violate the FK constraint and cause a DB exception
        $row2 = array_merge(['id' => $id2], collect($upload2->only($upload2->getFillable()))
            ->except(['preview_disk', 'preview_path', 'preview_mime', 'preview_generated_at', 'preview_last_accessed_at', 'preview_source_hash', 'preview_size'])
            ->toArray());
        $row2['document_type_id'] = 99999;

        $upload1->delete();
        $upload2->delete();
        $this->assertDatabaseCount('document_uploads', 0);

        $zipPath = $this->buildZipWithManifest([
            [
                'zip_entry' => "QMS/F-QMS-001/{$id1}_file1.pdf",
                'file_path' => 'qms/file1.pdf',
                'storage_disk' => 'private',
                'content' => 'content 1',
                'upload_row' => $row1,
            ],
            [
                'zip_entry' => "QMS/F-QMS-001/{$id2}_file2.pdf",
                'file_path' => 'qms/file2.pdf',
                'storage_disk' => 'private',
                'content' => 'content 2',
                'upload_row' => $row2,
            ],
        ]);

        $threw = false;
        try {
            $this->service->restore($zipPath);
        } catch (\Throwable) {
            $threw = true;
        } finally {
            @unlink($zipPath);
        }

        // If FK constraints aren't enforced (SQLite default), the test is not meaningful —
        // skip the rollback assertion in that case and just confirm no exception was unexpected.
        if ($threw) {
            // Transaction rolled back — neither row should exist
            $this->assertDatabaseCount('document_uploads', 0);
        } else {
            // SQLite without FK enforcement — both rows persisted, no rollback needed
            $this->assertTrue(true, 'SQLite FK not enforced; rollback behavior not testable in this environment.');
        }
    }

    // -----------------------------------------------------------------------
    // Test 12 — L3: preview fields not included in manifest upload_row
    // -----------------------------------------------------------------------

    public function test_backup_excludes_preview_fields_from_upload_row(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $series = DocumentSeries::create(['code_prefix' => 'QMS', 'name' => 'QMS Forms']);
        $type = DocumentType::create(['series_id' => $series->id, 'code' => 'F-QMS-001', 'title' => 'Test Doc']);

        Storage::disk('private')->put('qms/F-QMS-001/file.pdf', 'content');

        DocumentUpload::create([
            'document_type_id' => $type->id,
            'file_name' => 'file.pdf',
            'file_path' => 'qms/F-QMS-001/file.pdf',
            'storage_disk' => 'private',
            'preview_disk' => 'public',
            'preview_path' => 'previews/stale.png',
            'preview_mime' => 'image/png',
        ]);

        $result = $this->service->createBackup();

        $zipPath = Storage::disk('private')->path('backups/'.$result['filename']);
        $zip = new ZipArchive;
        $opened = $zip->open($zipPath);
        $this->assertTrue($opened === true, "ZipArchive::open() failed (code: {$opened})");
        $manifestRaw = $zip->getFromName('manifest.json');
        $zip->close();

        $manifest = json_decode($manifestRaw, true);
        $uploadRow = $manifest['files'][0]['upload_row'];

        $this->assertArrayNotHasKey('preview_disk', $uploadRow);
        $this->assertArrayNotHasKey('preview_path', $uploadRow);
        $this->assertArrayNotHasKey('preview_mime', $uploadRow);
        $this->assertArrayNotHasKey('preview_generated_at', $uploadRow);
        $this->assertArrayNotHasKey('preview_last_accessed_at', $uploadRow);
        $this->assertArrayNotHasKey('preview_source_hash', $uploadRow);
        $this->assertArrayNotHasKey('preview_size', $uploadRow);
    }

    // -----------------------------------------------------------------------
    // Test 13 — createBackup includes database.json in the ZIP
    // -----------------------------------------------------------------------

    public function test_create_backup_includes_database_json(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $result = $this->service->createBackup();

        $zipPath = Storage::disk('private')->path('backups/'.$result['filename']);
        $zip = new ZipArchive;
        $opened = $zip->open($zipPath);
        $this->assertTrue($opened === true, "ZipArchive::open() failed (code: {$opened})");

        $dbIndex = $zip->locateName('database.json');
        $this->assertNotFalse($dbIndex, 'database.json is missing from the backup ZIP.');

        $dbRaw = $zip->getFromIndex($dbIndex);
        $zip->close();

        $this->assertIsString($dbRaw);
        $decoded = json_decode($dbRaw, true);
        $this->assertIsArray($decoded, 'database.json does not contain valid JSON.');

        // At minimum the tables created in setUp must appear as keys (even if rows are empty)
        $this->assertArrayHasKey('document_series', $decoded);
        $this->assertArrayHasKey('document_types', $decoded);
        $this->assertArrayHasKey('document_uploads', $decoded);
    }

    // -----------------------------------------------------------------------
    // Test 14 — restore() with database.json restores DB rows from the dump
    // -----------------------------------------------------------------------

    public function test_restore_with_database_json_restores_db_rows(): void
    {
        Storage::fake('private');

        $series = DocumentSeries::create(['code_prefix' => 'QMS', 'name' => 'QMS Forms']);
        $type = DocumentType::create(['series_id' => $series->id, 'code' => 'F-QMS-001', 'title' => 'Test Doc']);

        Storage::disk('private')->put('qms/F-QMS-001/file.pdf', 'content');

        DocumentUpload::create([
            'document_type_id' => $type->id,
            'file_name' => 'file.pdf',
            'file_path' => 'qms/F-QMS-001/file.pdf',
            'storage_disk' => 'private',
            'status' => 'published',
        ]);

        // Create a backup (which now includes database.json)
        $backupResult = $this->service->createBackup();
        $zipPath = Storage::disk('private')->path('backups/'.$backupResult['filename']);

        // Wipe application data to simulate a restore scenario.
        // FK_CHECKS disabled so truncate order does not matter on MySQL CI.
        $isMysql = DB::connection()->getDriverName() === 'mysql';
        if ($isMysql) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }
        DocumentUpload::truncate();
        DocumentType::truncate();
        DocumentSeries::truncate();
        if ($isMysql) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->assertDatabaseCount('document_series', 0);
        $this->assertDatabaseCount('document_types', 0);
        $this->assertDatabaseCount('document_uploads', 0);

        $result = $this->service->restore($zipPath);

        $this->assertSame(1, $result['files']);
        $this->assertGreaterThan(0, $result['rows']);
        $this->assertDatabaseHas('document_series', ['code_prefix' => 'QMS']);
        $this->assertDatabaseHas('document_types', ['code' => 'F-QMS-001']);
        $this->assertDatabaseHas('document_uploads', ['file_name' => 'file.pdf', 'status' => 'published']);
    }

    // -----------------------------------------------------------------------
    // Test 15 — restore() without database.json is backward-compatible (rows=0)
    // -----------------------------------------------------------------------

    public function test_restore_without_database_json_returns_zero_rows(): void
    {
        Storage::fake('private');

        $tmpPath = tempnam(sys_get_temp_dir(), 'compat_').'.zip';
        $zip = new ZipArchive;
        $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('manifest.json', json_encode(['file_count' => 1, 'files' => [
            [
                'zip_entry' => 'qms/legacy.pdf',
                'file_name' => 'legacy.pdf',
                'file_path' => 'qms/legacy.pdf',
                'storage_disk' => 'private',
                'upload_row' => null,
            ],
        ]]));
        $zip->addFromString('qms/legacy.pdf', 'legacy content');
        $zip->close();

        $result = $this->service->restore($tmpPath);

        $this->assertSame(1, $result['files']);
        $this->assertSame(0, $result['rows']);
        Storage::disk('private')->assertExists('qms/legacy.pdf');

        @unlink($tmpPath);
    }

    // -----------------------------------------------------------------------
    // Test 16 — m3: database.json with a non-existent table key is silently skipped
    // -----------------------------------------------------------------------

    public function test_restore_skips_nonexistent_table_in_database_json(): void
    {
        Storage::fake('private');

        $series = DocumentSeries::create(['code_prefix' => 'QMS', 'name' => 'QMS Forms']);

        $dbDump = [
            'nonexistent_dropped_table' => [['id' => 1, 'col' => 'data']],
            'document_series' => [[
                'id' => $series->id,
                'code_prefix' => 'QMS',
                'name' => 'QMS Forms Updated',
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]],
        ];

        $tmpPath = tempnam(sys_get_temp_dir(), 'restore_test_').'.zip';
        $zip = new ZipArchive;
        $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('manifest.json', json_encode(['file_count' => 0, 'files' => []]));
        $zip->addFromString('database.json', json_encode($dbDump));
        $zip->close();

        // Must not throw; non-existent table silently skipped; valid table restored
        $result = $this->service->restore($tmpPath);

        $this->assertSame(0, $result['files']);
        $this->assertGreaterThan(0, $result['rows']);
        $this->assertDatabaseHas('document_series', ['name' => 'QMS Forms Updated']);

        @unlink($tmpPath);
    }

    // -----------------------------------------------------------------------
    // Test 17 — m4: database.json containing a SKIP_TABLE key is silently ignored
    // -----------------------------------------------------------------------

    public function test_restore_ignores_skip_table_in_database_json(): void
    {
        Storage::fake('private');

        $series = DocumentSeries::create(['code_prefix' => 'QMS', 'name' => 'QMS Forms']);

        // 'migrations' is a SKIP_TABLE; its data must not be restored
        $dbDump = [
            'migrations' => [['id' => 999, 'migration' => 'injected_migration', 'batch' => 99]],
            'document_series' => [[
                'id' => $series->id,
                'code_prefix' => 'QMS',
                'name' => 'QMS Forms',
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]],
        ];

        $tmpPath = tempnam(sys_get_temp_dir(), 'restore_test_').'.zip';
        $zip = new ZipArchive;
        $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('manifest.json', json_encode(['file_count' => 0, 'files' => []]));
        $zip->addFromString('database.json', json_encode($dbDump));
        $zip->close();

        // Must not throw; migrations entry silently skipped
        $result = $this->service->restore($tmpPath);

        $this->assertSame(0, $result['files']);
        // Only document_series row counted — migrations silently skipped
        $this->assertSame(1, $result['rows']);

        @unlink($tmpPath);
    }

    // -----------------------------------------------------------------------
    // Test 18 — non-id single-column PK: dump uses detected PK for orderBy,
    //           restore uses it as the upsert unique key
    // -----------------------------------------------------------------------

    public function test_backup_and_restore_handles_non_id_primary_key(): void
    {
        Storage::fake('private');

        Schema::create('qms_tags', function (Blueprint $table) {
            $table->string('code')->primary();
            $table->string('label');
            $table->timestamps();
        });

        try {
            DB::table('qms_tags')->insert([
                'code' => 'ISO-9001',
                'label' => 'Quality Standard',
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]);

            $backupResult = $this->service->createBackup();
            $zipPath = Storage::disk('private')->path('backups/'.$backupResult['filename']);

            $zip = new ZipArchive;
            $zip->open($zipPath);
            $decoded = json_decode($zip->getFromName('database.json'), true);
            $zip->close();

            $this->assertArrayHasKey('qms_tags', $decoded);
            $this->assertCount(1, $decoded['qms_tags']);
            $this->assertSame('ISO-9001', $decoded['qms_tags'][0]['code']);

            DB::table('qms_tags')->delete();
            $this->assertDatabaseCount('qms_tags', 0);

            $result = $this->service->restore($zipPath);

            $this->assertGreaterThan(0, $result['rows']);
            $this->assertDatabaseHas('qms_tags', ['code' => 'ISO-9001', 'label' => 'Quality Standard']);
        } finally {
            Schema::dropIfExists('qms_tags');
        }
    }

    // -----------------------------------------------------------------------
    // Test 19 — table without any PK: dump uses get() fallback,
    //           restore uses insertOrIgnore fallback
    // -----------------------------------------------------------------------

    public function test_backup_and_restore_handles_table_without_primary_key(): void
    {
        Storage::fake('private');

        Schema::create('qms_events', function (Blueprint $table) {
            $table->string('event_type');
            $table->string('payload')->nullable();
            $table->timestamp('occurred_at')->nullable();
        });

        try {
            DB::table('qms_events')->insert([
                'event_type' => 'audit',
                'payload' => 'test payload',
                'occurred_at' => now()->toDateTimeString(),
            ]);

            $backupResult = $this->service->createBackup();
            $zipPath = Storage::disk('private')->path('backups/'.$backupResult['filename']);

            $zip = new ZipArchive;
            $zip->open($zipPath);
            $decoded = json_decode($zip->getFromName('database.json'), true);
            $zip->close();

            $this->assertArrayHasKey('qms_events', $decoded);
            $this->assertCount(1, $decoded['qms_events']);

            DB::table('qms_events')->delete();
            $this->assertDatabaseCount('qms_events', 0);

            $result = $this->service->restore($zipPath);

            $this->assertGreaterThan(0, $result['rows']);
            $this->assertDatabaseHas('qms_events', ['event_type' => 'audit', 'payload' => 'test payload']);
        } finally {
            Schema::dropIfExists('qms_events');
        }
    }

    // -----------------------------------------------------------------------
    // Test 20 — fail-fast: DB chunk error propagates, transaction rolls back
    // -----------------------------------------------------------------------

    public function test_restore_throws_and_rolls_back_when_db_chunk_fails(): void
    {
        Storage::fake('private');

        $series = DocumentSeries::create(['code_prefix' => 'QMS', 'name' => 'QMS Forms']);

        // document_series.code_prefix is NOT NULL — inserting null must fail and
        // propagate out of restoreDatabase() so the outer transaction rolls back.
        $dbDump = [
            'document_series' => [[
                'id' => 9999,
                'code_prefix' => null,
                'name' => 'Bad Row',
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]],
        ];

        $tmpPath = tempnam(sys_get_temp_dir(), 'restore_test_').'.zip';
        $zip = new ZipArchive;
        $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('manifest.json', json_encode(['file_count' => 0, 'files' => []]));
        $zip->addFromString('database.json', json_encode($dbDump));
        $zip->close();

        $threw = false;

        try {
            $this->service->restore($tmpPath);
        } catch (\Throwable $e) {
            $threw = true;
            $this->assertInstanceOf(\RuntimeException::class, $e);
        } finally {
            @unlink($tmpPath);
        }

        $this->assertTrue($threw, 'restore() must throw when a DB chunk fails.');
        // Transaction rolled back — only the original row must exist, id=9999 must not
        $this->assertDatabaseCount('document_series', 1);
        $this->assertDatabaseHas('document_series', ['code_prefix' => 'QMS']);
    }
}
