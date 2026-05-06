<?php

namespace Tests\Feature;

use App\Models\DocumentSeries;
use App\Models\DocumentType;
use App\Models\DocumentUpload;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class BackupTest extends TestCase
{
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

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('department')->nullable();
            $table->string('office_location')->nullable();
            $table->string('module');
            $table->string('action');
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('record_label')->nullable();
            $table->string('file_type')->nullable();
            $table->text('description');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        SystemSetting::instance();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function nonAdmin(): User
    {
        return User::factory()->create(['role' => 'admin_officer']);
    }

    private function makeValidZipUpload(array $entries = []): UploadedFile
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'backup_').'.zip';

        $zip = new ZipArchive;
        $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('manifest.json', json_encode(['file_count' => count($entries)]));

        foreach ($entries as $path => $content) {
            $zip->addFromString($path, $content);
        }

        $zip->close();

        return new UploadedFile($tmpPath, 'backup.zip', 'application/zip', null, true);
    }

    private function seedDocumentUpload(DocumentType $type, string $filePath, string $disk = 'private'): DocumentUpload
    {
        return DocumentUpload::create([
            'document_type_id' => $type->id,
            'file_name' => basename($filePath),
            'file_path' => $filePath,
            'storage_disk' => $disk,
            'status' => 'Active',
        ]);
    }

    // -----------------------------------------------------------------------
    // create
    // -----------------------------------------------------------------------

    public function test_admin_can_create_backup(): void
    {
        Storage::fake('private');

        $series = DocumentSeries::create(['code_prefix' => 'F-QMS', 'name' => 'QMS Forms']);
        $type = DocumentType::create(['series_id' => $series->id, 'code' => 'F-QMS-001', 'title' => 'Test Doc']);

        Storage::disk('private')->put('qms/F-QMS-001/test.pdf', 'fake pdf content');
        $this->seedDocumentUpload($type, 'qms/F-QMS-001/test.pdf');

        $this->actingAs($this->admin())
            ->post('/settings/backup/create')
            ->assertRedirect();

        $files = Storage::disk('private')->files('backups');
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.zip', $files[0]);
    }

    public function test_non_admin_cannot_create_backup(): void
    {
        $this->actingAs($this->nonAdmin())
            ->post('/settings/backup/create')
            ->assertForbidden();
    }

    public function test_create_backup_logs_activity(): void
    {
        Storage::fake('private');

        $this->actingAs($this->admin())->post('/settings/backup/create');

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'system',
            'action' => 'backup_created',
        ]);
    }

    // -----------------------------------------------------------------------
    // download
    // -----------------------------------------------------------------------

    public function test_admin_can_download_latest_backup(): void
    {
        Storage::fake('private');

        Storage::disk('private')->makeDirectory('backups');
        $zipPath = Storage::disk('private')->path('backups/backup_2026-01-01_120000.zip');

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('manifest.json', '{}');
        $zip->close();

        $this->actingAs($this->admin())
            ->get('/settings/backup/download')
            ->assertOk()
            ->assertDownload();
    }

    public function test_download_returns_404_when_no_backup_exists(): void
    {
        Storage::fake('private');

        $this->actingAs($this->admin())
            ->get('/settings/backup/download')
            ->assertNotFound();
    }

    public function test_non_admin_cannot_download_backup(): void
    {
        $this->actingAs($this->nonAdmin())
            ->get('/settings/backup/download')
            ->assertForbidden();
    }

    // -----------------------------------------------------------------------
    // restore
    // -----------------------------------------------------------------------

    public function test_admin_can_restore_from_uploaded_zip(): void
    {
        Storage::fake('private');

        $zipFile = $this->makeValidZipUpload([
            'qms/F-QMS-001/document.pdf' => 'restored content',
        ]);

        $this->actingAs($this->admin())
            ->post('/settings/backup/restore', ['backup_file' => $zipFile])
            ->assertRedirect();

        Storage::disk('private')->assertExists('qms/F-QMS-001/document.pdf');
    }

    public function test_restore_rejects_non_zip_file(): void
    {
        $txtFile = UploadedFile::fake()->create('backup.txt', 100, 'text/plain');

        $this->actingAs($this->admin())
            ->post('/settings/backup/restore', ['backup_file' => $txtFile])
            ->assertSessionHasErrors('backup_file');
    }

    public function test_restore_is_logged_in_activity_log(): void
    {
        Storage::fake('private');

        $zipFile = $this->makeValidZipUpload(['qms/test.pdf' => 'content']);

        $this->actingAs($this->admin())
            ->post('/settings/backup/restore', ['backup_file' => $zipFile]);

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'system',
            'action' => 'backup_restored',
        ]);
    }

    public function test_non_admin_cannot_restore_backup(): void
    {
        $zipFile = $this->makeValidZipUpload();

        $this->actingAs($this->nonAdmin())
            ->post('/settings/backup/restore', ['backup_file' => $zipFile])
            ->assertForbidden();
    }

    // -----------------------------------------------------------------------
    // saveSettings
    // -----------------------------------------------------------------------

    public function test_admin_can_save_backup_settings(): void
    {
        $this->actingAs($this->admin())->post('/settings/backup/settings', [
            'backup_frequency' => 'daily',
            'storage_location' => 'local',
            'auto_backup' => true,
        ])->assertRedirect();

        $settings = SystemSetting::first();
        $this->assertSame('daily', $settings->backup_frequency);
        $this->assertSame('local', $settings->storage_location);
        $this->assertTrue($settings->auto_backup);
    }

    public function test_non_admin_cannot_save_backup_settings(): void
    {
        $this->actingAs($this->nonAdmin())->post('/settings/backup/settings', [
            'backup_frequency' => 'daily',
            'storage_location' => 'local',
            'auto_backup' => false,
        ])->assertForbidden();
    }

    public function test_save_settings_validates_invalid_frequency(): void
    {
        $this->actingAs($this->admin())->post('/settings/backup/settings', [
            'backup_frequency' => 'hourly',
            'storage_location' => 'local',
            'auto_backup' => false,
        ])->assertSessionHasErrors('backup_frequency');
    }

    public function test_save_settings_logs_activity(): void
    {
        $this->actingAs($this->admin())->post('/settings/backup/settings', [
            'backup_frequency' => 'monthly',
            'storage_location' => 'local',
            'auto_backup' => false,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'system',
            'action' => 'settings_updated',
            'record_label' => 'Backup Settings',
        ]);
    }
}
