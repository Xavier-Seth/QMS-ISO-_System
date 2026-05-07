<?php

namespace Tests\Feature;

use App\Models\DocumentSeries;
use App\Models\DocumentType;
use App\Models\DocumentUpload;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentUploadDestroyTest extends TestCase
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

        Schema::create('ofi_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->constrained('document_types')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('dcr_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->nullable()->constrained('document_types')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('car_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->constrained('document_types')->restrictOnDelete();
            $table->string('car_no')->nullable();
            $table->string('status')->default('draft');
            $table->string('resolution_status')->default('open');
            $table->json('data')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
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
    }

    private function makeAdmin(): User
    {
        return User::factory()->create(['username' => 'admin', 'role' => 'admin']);
    }

    private function makeUser(): User
    {
        return User::factory()->create(['username' => 'staff', 'role' => 'user']);
    }

    private function makeSeries(string $prefix = 'R-QMS'): DocumentSeries
    {
        return DocumentSeries::query()->create(['code_prefix' => $prefix, 'name' => 'Records']);
    }

    private function makeType(DocumentSeries $series, array $attrs = []): DocumentType
    {
        return DocumentType::query()->create(array_merge([
            'series_id' => $series->id,
            'code' => $series->code_prefix.'-001',
            'title' => 'Test Document',
            'storage' => 'Electronic',
            'status' => 'Active',
            'requires_revision' => false,
        ], $attrs));
    }

    private function makeUpload(DocumentType $type, User $uploader, array $attrs = []): DocumentUpload
    {
        return DocumentUpload::query()->create(array_merge([
            'document_type_id' => $type->id,
            'uploaded_by' => $uploader->id,
            'file_name' => 'test.pdf',
            'file_path' => 'qms/R-QMS-001/test.pdf',
            'storage_disk' => 'private',
            'status' => null,
        ], $attrs));
    }

    public function test_admin_can_delete_standard_upload(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('qms/R-QMS-001/test.pdf', 'content');

        $admin = $this->makeAdmin();
        $series = $this->makeSeries();
        $type = $this->makeType($series);
        $upload = $this->makeUpload($type, $admin);

        $response = $this->actingAs($admin)->delete(route('documents.uploads.destroy', $upload));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('document_uploads', ['id' => $upload->id]);
        Storage::disk('private')->assertMissing('qms/R-QMS-001/test.pdf');
    }

    public function test_deleting_active_revision_promotes_previous_obsolete(): void
    {
        Storage::fake('private');

        $admin = $this->makeAdmin();
        $series = $this->makeSeries('F-QMS');
        $type = $this->makeType($series, ['code' => 'F-QMS-001', 'requires_revision' => true]);

        $older = $this->makeUpload($type, $admin, [
            'file_path' => 'qms/F-QMS-001/older.pdf',
            'status' => 'Obsolete',
            'revision' => 'Rev. 1',
            'created_at' => now()->subDay(),
        ]);

        $active = $this->makeUpload($type, $admin, [
            'file_path' => 'qms/F-QMS-001/active.pdf',
            'status' => 'Active',
            'revision' => 'Rev. 2',
        ]);

        $response = $this->actingAs($admin)->delete(route('documents.uploads.destroy', $active));

        $response->assertRedirect();
        $this->assertDatabaseMissing('document_uploads', ['id' => $active->id]);
        $this->assertDatabaseHas('document_uploads', ['id' => $older->id, 'status' => 'Active']);
    }

    public function test_deleting_obsolete_revision_does_not_affect_active(): void
    {
        Storage::fake('private');

        $admin = $this->makeAdmin();
        $series = $this->makeSeries('F-QMS');
        $type = $this->makeType($series, ['code' => 'F-QMS-001', 'requires_revision' => true]);

        $obsolete = $this->makeUpload($type, $admin, [
            'file_path' => 'qms/F-QMS-001/old.pdf',
            'status' => 'Obsolete',
            'revision' => 'Rev. 1',
            'created_at' => now()->subDay(),
        ]);

        $active = $this->makeUpload($type, $admin, [
            'file_path' => 'qms/F-QMS-001/active.pdf',
            'status' => 'Active',
            'revision' => 'Rev. 2',
        ]);

        $response = $this->actingAs($admin)->delete(route('documents.uploads.destroy', $obsolete));

        $response->assertRedirect();
        $this->assertDatabaseMissing('document_uploads', ['id' => $obsolete->id]);
        $this->assertDatabaseHas('document_uploads', ['id' => $active->id, 'status' => 'Active']);
    }

    public function test_deleting_upload_with_ofi_record_is_blocked(): void
    {
        $admin = $this->makeAdmin();
        $series = $this->makeSeries();
        $type = $this->makeType($series);
        $upload = $this->makeUpload($type, $admin, ['ofi_record_id' => 999]);

        $response = $this->actingAs($admin)->delete(route('documents.uploads.destroy', $upload));

        $response->assertRedirect();
        $response->assertSessionHasErrors('delete');
        $this->assertDatabaseHas('document_uploads', ['id' => $upload->id]);
    }

    public function test_deleting_upload_with_dcr_record_is_blocked(): void
    {
        $admin = $this->makeAdmin();
        $series = $this->makeSeries();
        $type = $this->makeType($series);
        $upload = $this->makeUpload($type, $admin, ['dcr_record_id' => 999]);

        $response = $this->actingAs($admin)->delete(route('documents.uploads.destroy', $upload));

        $response->assertRedirect();
        $response->assertSessionHasErrors('delete');
        $this->assertDatabaseHas('document_uploads', ['id' => $upload->id]);
    }

    public function test_deleting_upload_with_car_record_is_blocked(): void
    {
        $admin = $this->makeAdmin();
        $series = $this->makeSeries();
        $type = $this->makeType($series);
        $upload = $this->makeUpload($type, $admin, ['car_record_id' => 999]);

        $response = $this->actingAs($admin)->delete(route('documents.uploads.destroy', $upload));

        $response->assertRedirect();
        $response->assertSessionHasErrors('delete');
        $this->assertDatabaseHas('document_uploads', ['id' => $upload->id]);
    }

    public function test_non_admin_cannot_delete_upload(): void
    {
        $user = $this->makeUser();
        $admin = $this->makeAdmin();
        $series = $this->makeSeries();
        $type = $this->makeType($series);
        $upload = $this->makeUpload($type, $admin);

        $response = $this->actingAs($user)->delete(route('documents.uploads.destroy', $upload));

        $response->assertForbidden();
        $this->assertDatabaseHas('document_uploads', ['id' => $upload->id]);
    }

    public function test_unauthenticated_cannot_delete_upload(): void
    {
        $admin = $this->makeAdmin();
        $series = $this->makeSeries();
        $type = $this->makeType($series);
        $upload = $this->makeUpload($type, $admin);

        $response = $this->delete(route('documents.uploads.destroy', $upload));

        $response->assertRedirect('/login');
        $this->assertDatabaseHas('document_uploads', ['id' => $upload->id]);
    }

    public function test_admin_can_delete_performance_upload_from_public_disk(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('performance/IPCR/TARGET/2024/JAN_JUN/report.pdf', 'content');

        $admin = $this->makeAdmin();
        $series = $this->makeSeries('IPCR');
        $type = $this->makeType($series, ['code' => 'PERF-IPCR']);
        $upload = $this->makeUpload($type, $admin, [
            'file_name' => 'report.pdf',
            'file_path' => 'performance/IPCR/TARGET/2024/JAN_JUN/report.pdf',
            'storage_disk' => 'public',
            'performance_category' => 'IPCR',
            'performance_record_type' => 'TARGET',
            'year' => 2024,
            'period' => 'JAN_JUN',
        ]);

        $response = $this->actingAs($admin)->delete(route('documents.uploads.destroy', $upload));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('document_uploads', ['id' => $upload->id]);
        Storage::disk('public')->assertMissing('performance/IPCR/TARGET/2024/JAN_JUN/report.pdf');
    }

    public function test_preview_cache_deleted_alongside_upload(): void
    {
        Storage::fake('private');
        Storage::fake('local');

        Storage::disk('private')->put('qms/R-QMS-001/main.pdf', 'content');
        Storage::disk('local')->put('previews/cache.pdf', 'preview');

        $admin = $this->makeAdmin();
        $series = $this->makeSeries();
        $type = $this->makeType($series);
        $upload = $this->makeUpload($type, $admin, [
            'file_path' => 'qms/R-QMS-001/main.pdf',
            'preview_disk' => 'local',
            'preview_path' => 'previews/cache.pdf',
        ]);

        $this->actingAs($admin)->delete(route('documents.uploads.destroy', $upload));

        Storage::disk('private')->assertMissing('qms/R-QMS-001/main.pdf');
        Storage::disk('local')->assertMissing('previews/cache.pdf');
    }
}
