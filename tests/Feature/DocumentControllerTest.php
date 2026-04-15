<?php

namespace Tests\Feature;

use App\Models\CarRecord;
use App\Models\DocumentSeries;
use App\Models\DocumentType;
use App\Models\DocumentUpload;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentControllerTest extends TestCase
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

        Schema::create('document_type_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->constrained('document_types')->cascadeOnDelete();
            $table->unsignedTinyInteger('revision_no');
            $table->date('revision_date')->nullable();
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

    public function test_destroy_type_returns_validation_error_when_car_records_still_reference_it(): void
    {
        $user = User::factory()->create([
            'username' => 'adminuser',
            'role' => 'admin',
        ]);

        $series = DocumentSeries::query()->create([
            'code_prefix' => 'F-QMS',
            'name' => 'Forms',
        ]);

        $documentType = DocumentType::query()->create([
            'series_id' => $series->id,
            'code' => 'F-QMS-006',
            'title' => 'Corrective Action Request',
            'storage' => 'Electronic',
            'status' => 'Active',
        ]);

        CarRecord::query()->create([
            'document_type_id' => $documentType->id,
            'car_no' => 'CAR-001',
            'status' => 'draft',
            'resolution_status' => 'open',
            'data' => [],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->from('/documents')
            ->delete(route('documents.types.destroy', $documentType));

        $response->assertRedirect('/documents');
        $response->assertSessionHasErrors([
            'delete' => 'Cannot delete F-QMS-006 because it is still referenced by CAR records.',
        ]);

        $this->assertDatabaseHas('document_types', [
            'id' => $documentType->id,
            'code' => 'F-QMS-006',
        ]);
    }

    public function test_upload_marks_previous_revision_obsolete_and_keeps_one_active_upload(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $user = User::factory()->create([
            'username' => 'adminupload',
            'role' => 'admin',
        ]);

        $series = DocumentSeries::query()->create([
            'code_prefix' => 'F-QMS',
            'name' => 'Forms',
        ]);

        $documentType = DocumentType::query()->create([
            'series_id' => $series->id,
            'code' => 'F-QMS-001',
            'title' => 'Controlled Form',
            'storage' => 'Electronic',
            'status' => 'Active',
            'requires_revision' => true,
        ]);

        DocumentUpload::query()->create([
            'document_type_id' => $documentType->id,
            'uploaded_by' => $user->id,
            'revision' => 'Rev 0',
            'status' => 'Active',
            'file_name' => 'controlled-form-rev-0.pdf',
            'file_path' => 'qms/F-QMS-001/controlled-form-rev-0.pdf',
            'storage_disk' => 'public',
            'remarks' => 'Initial revision',
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('documents.show', $documentType))
            ->post(route('documents.upload', $documentType), [
                'files' => [
                    UploadedFile::fake()->create('controlled-form-rev-1.pdf', 128, 'application/pdf'),
                ],
                'revision' => 'Rev 1',
                'remarks' => 'Updated revision',
            ]);

        $response->assertRedirect(route('documents.show', $documentType));
        $response->assertSessionHas('success', 'File uploaded successfully.');

        $this->assertDatabaseHas('document_uploads', [
            'document_type_id' => $documentType->id,
            'revision' => 'Rev 0',
            'status' => 'Obsolete',
            'file_name' => 'controlled-form-rev-0.pdf',
        ]);

        $this->assertDatabaseHas('document_uploads', [
            'document_type_id' => $documentType->id,
            'revision' => 'Rev 1',
            'status' => 'Active',
            'file_name' => 'controlled-form-rev-1.pdf',
        ]);

        $this->assertSame(
            1,
            DocumentUpload::query()
                ->where('document_type_id', $documentType->id)
                ->where('status', 'Active')
                ->count()
        );

        $latestUpload = DocumentUpload::query()
            ->where('document_type_id', $documentType->id)
            ->where('revision', 'Rev 1')
            ->firstOrFail();

        $this->assertSame('private', $latestUpload->storage_disk);
        Storage::disk('private')->assertExists($latestUpload->file_path);
        Storage::disk('public')->assertMissing($latestUpload->file_path);
    }

    public function test_show_does_not_expose_raw_file_urls_for_private_uploads(): void
    {
        Storage::fake('private');

        $user = User::factory()->create([
            'username' => 'adminshow',
            'role' => 'admin',
        ]);

        $series = DocumentSeries::query()->create([
            'code_prefix' => 'R-QMS',
            'name' => 'Records',
        ]);

        $documentType = DocumentType::query()->create([
            'series_id' => $series->id,
            'code' => 'R-QMS-001',
            'title' => 'Record Register',
            'storage' => 'Electronic',
            'status' => 'Active',
            'requires_revision' => false,
        ]);

        $storedPath = UploadedFile::fake()
            ->image('evidence.jpg')
            ->store("qms/{$documentType->code}", 'private');

        $upload = DocumentUpload::query()->create([
            'document_type_id' => $documentType->id,
            'uploaded_by' => $user->id,
            'status' => null,
            'file_name' => 'evidence.jpg',
            'file_path' => $storedPath,
            'storage_disk' => 'private',
            'remarks' => 'Image attachment',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('documents.show', $documentType));

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString(
            'documents\\/uploads\\/' . $upload->id . '\\/download',
            $content
        );
        $this->assertStringContainsString('can_preview&quot;:false', $content);
        $this->assertStringNotContainsString('file_url', $content);
        $this->assertStringNotContainsString('/storage/qms/', $content);
    }

    public function test_store_type_returns_validation_error_for_duplicate_generated_code(): void
    {
        $user = User::factory()->create([
            'username' => 'adminduplicate',
            'role' => 'admin',
        ]);

        $series = DocumentSeries::query()->create([
            'code_prefix' => 'F-QMS',
            'name' => 'Forms',
        ]);

        DocumentType::query()->create([
            'series_id' => $series->id,
            'code' => 'F-QMS-001',
            'title' => 'Existing Form',
            'storage' => 'Electronic',
            'status' => 'Active',
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('documents.index'))
            ->post(route('documents.types.store'), [
                'series_id' => $series->id,
                'document_no' => 1,
                'title' => 'Duplicate Form Attempt',
                'storage' => 'Electronic',
                'requires_revision' => true,
            ]);

        $response->assertRedirect(route('documents.index'));
        $response->assertSessionHasErrors([
            'document_no' => 'The generated code F-QMS-001 already exists.',
        ]);

        $this->assertDatabaseCount('document_types', 1);
        $this->assertDatabaseHas('document_types', [
            'series_id' => $series->id,
            'code' => 'F-QMS-001',
            'title' => 'Existing Form',
        ]);
    }

    public function test_upload_rejects_disallowed_file_types(): void
    {
        Storage::fake('private');

        $user = User::factory()->create([
            'username' => 'adminreject',
            'role' => 'admin',
        ]);

        $series = DocumentSeries::query()->create([
            'code_prefix' => 'R-QMS',
            'name' => 'Records',
        ]);

        $documentType = DocumentType::query()->create([
            'series_id' => $series->id,
            'code' => 'R-QMS-002',
            'title' => 'Unsafe Upload Test',
            'storage' => 'Electronic',
            'status' => 'Active',
            'requires_revision' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('documents.show', $documentType))
            ->post(route('documents.upload', $documentType), [
                'files' => [
                    UploadedFile::fake()->create('malicious.php', 10, 'application/x-php'),
                ],
            ]);

        $response->assertRedirect(route('documents.show', $documentType));
        $response->assertSessionHasErrors(['files.0']);
        $this->assertDatabaseCount('document_uploads', 0);
    }
}
