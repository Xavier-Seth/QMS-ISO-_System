<?php

namespace Tests\Feature;

use App\Models\CarRecord;
use App\Models\DcrRecord;
use App\Models\DocumentSeries;
use App\Models\DocumentType;
use App\Models\DocumentUpload;
use App\Models\OfiRecord;
use App\Models\QmsTemplate;
use App\Models\User;
use App\Support\QmsTemplateModules;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class RecordApprovalTest extends TestCase
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

        Schema::create('ofi_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->nullable()->constrained('document_types')->nullOnDelete();
            $table->string('ofi_no')->nullable();
            $table->string('ref_no')->nullable();
            $table->string('to')->nullable();
            $table->string('status')->default('draft');
            $table->string('workflow_status')->nullable();
            $table->string('resolution_status')->default('open');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('data')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('car_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->nullable()->constrained('document_types')->nullOnDelete();
            $table->string('car_no')->nullable();
            $table->string('ref_no')->nullable();
            $table->string('dept_section')->nullable();
            $table->string('auditor')->nullable();
            $table->string('status')->default('draft');
            $table->string('workflow_status')->nullable();
            $table->string('resolution_status')->default('open');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('data')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('dcr_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->nullable()->constrained('document_types')->nullOnDelete();
            $table->string('dcr_no')->nullable();
            $table->string('to_for')->nullable();
            $table->string('from')->nullable();
            $table->string('status')->default('draft');
            $table->string('workflow_status')->nullable();
            $table->string('resolution_status')->default('open');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('data')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('document_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->nullable()->constrained('document_types')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('ofi_record_id')->nullable();
            $table->unsignedBigInteger('dcr_record_id')->nullable();
            $table->unsignedBigInteger('car_record_id')->nullable();
            $table->string('revision')->nullable();
            $table->string('status')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->string('storage_disk')->nullable();
            $table->string('preview_disk')->nullable();
            $table->string('preview_path')->nullable();
            $table->string('preview_mime')->nullable();
            $table->timestamp('preview_generated_at')->nullable();
            $table->timestamp('preview_last_accessed_at')->nullable();
            $table->string('preview_source_hash')->nullable();
            $table->unsignedBigInteger('preview_size')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('qms_templates', function (Blueprint $table) {
            $table->id();
            $table->string('module', 50);
            $table->string('name');
            $table->string('original_file_name');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('storage_disk', 50)->default('private');
            $table->boolean('is_active')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('qms_dynamic_fields', function (Blueprint $table) {
            $table->id();
            $table->string('module', 50);
            $table->string('label');
            $table->string('field_key');
            $table->string('field_type', 30)->default('text');
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['module', 'field_key']);
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

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    // --- Finding #4 ---

    public function test_document_type_has_car_records_relationship(): void
    {
        $series = DocumentSeries::query()->create([
            'code_prefix' => 'R-QMS',
            'name' => 'Records',
        ]);

        $documentType = DocumentType::query()->create([
            'series_id' => $series->id,
            'code' => 'R-QMS-017',
            'title' => 'Corrective Action Request Records',
            'storage' => 'Electronic',
            'status' => 'active',
        ]);

        $admin = User::factory()->create(['username' => 'admincar4', 'role' => 'admin']);

        CarRecord::query()->create([
            'document_type_id' => $documentType->id,
            'car_no' => 'CAR-REL-001',
            'status' => 'draft',
            'workflow_status' => null,
            'resolution_status' => 'open',
            'data' => [],
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        CarRecord::query()->create([
            'document_type_id' => $documentType->id,
            'car_no' => 'CAR-REL-002',
            'status' => 'draft',
            'workflow_status' => null,
            'resolution_status' => 'open',
            'data' => [],
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $related = $documentType->carRecords;

        $this->assertCount(2, $related);
        $this->assertSame($documentType->id, $related->first()->document_type_id);
    }

    // --- Finding #6: OFI ---

    public function test_ofi_approval_clears_rejection_fields(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $admin = User::factory()->create(['username' => 'adminofi6', 'role' => 'admin']);

        $series = DocumentSeries::query()->create([
            'code_prefix' => 'R-QMS',
            'name' => 'Records',
        ]);

        DocumentType::query()->create([
            'series_id' => $series->id,
            'code' => 'R-QMS-018',
            'title' => 'Opportunity for Improvement Records',
            'storage' => 'Electronic',
            'status' => 'active',
        ]);

        $this->storeMinimalTemplate(QmsTemplateModules::OFI, 'private', 'qms/templates/ofi/test-ofi.docx', $admin);

        $record = OfiRecord::query()->create([
            'document_type_id' => null,
            'ofi_no' => 'OFI-REJ-001',
            'status' => 'submitted',
            'workflow_status' => 'pending',
            'resolution_status' => 'open',
            'rejection_reason' => 'Incomplete form.',
            'rejected_at' => now()->subDay(),
            'rejected_by' => $admin->id,
            'data' => ['ofiNo' => 'OFI-REJ-001', 'dynamic' => []],
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('ofi.inbox.approve', $record));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $record->refresh();

        $this->assertNull($record->rejection_reason);
        $this->assertNull($record->rejected_at);
        $this->assertNull($record->rejected_by);
        $this->assertSame('approved', $record->workflow_status);

        $upload = DocumentUpload::query()->where('ofi_record_id', $record->id)->first();
        $this->assertNotNull($upload, 'Expected a DocumentUpload to be created on OFI approval.');
        $this->assertSame('private', $upload->storage_disk);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    // --- Finding #6: CAR ---

    public function test_car_approval_clears_rejection_fields(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $admin = User::factory()->create(['username' => 'admincar6', 'role' => 'admin']);

        $series = DocumentSeries::query()->create([
            'code_prefix' => 'R-QMS',
            'name' => 'Records',
        ]);

        DocumentType::query()->create([
            'series_id' => $series->id,
            'code' => 'R-QMS-017',
            'title' => 'Corrective Action Request Records',
            'storage' => 'Electronic',
            'status' => 'active',
        ]);

        $this->storeMinimalTemplate(QmsTemplateModules::CAR, 'private', 'qms/templates/car/test-car.docx', $admin);

        $record = CarRecord::query()->create([
            'document_type_id' => null,
            'car_no' => 'CAR-REJ-001',
            'status' => 'submitted',
            'workflow_status' => 'pending',
            'resolution_status' => 'open',
            'rejection_reason' => 'Missing evidence.',
            'rejected_at' => now()->subDay(),
            'rejected_by' => $admin->id,
            'data' => ['carNo' => 'CAR-REJ-001', 'dynamic' => []],
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('car.inbox.approve', $record));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $record->refresh();

        $this->assertNull($record->rejection_reason);
        $this->assertNull($record->rejected_at);
        $this->assertNull($record->rejected_by);
        $this->assertSame('approved', $record->workflow_status);

        $upload = DocumentUpload::query()->where('car_record_id', $record->id)->first();
        $this->assertNotNull($upload, 'Expected a DocumentUpload to be created on CAR approval.');
        $this->assertSame('private', $upload->storage_disk);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    // --- Finding #8: DCR ---

    public function test_dcr_republish_clears_preview_cache(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $admin = User::factory()->create(['username' => 'admindcr8', 'role' => 'admin']);

        $series = DocumentSeries::query()->create([
            'code_prefix' => 'R-QMS',
            'name' => 'Records',
        ]);

        DocumentType::query()->create([
            'series_id' => $series->id,
            'code' => 'R-QMS-013',
            'title' => 'Document Change Request Records',
            'storage' => 'Electronic',
            'status' => 'active',
        ]);

        $this->storeMinimalTemplate('DCR', 'private', 'qms/templates/dcr/test-dcr.docx', $admin);

        $record = DcrRecord::query()->create([
            'document_type_id' => null,
            'dcr_no' => 'DCR-PREV-001',
            'status' => 'draft',
            'workflow_status' => 'approved',
            'resolution_status' => 'open',
            'data' => ['dcrNo' => 'DCR-PREV-001', 'dynamic' => []],
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $existingPath = 'documents/dcr/DCR_PREV_001.docx';
        $previewPath = 'previews/old-dcr-preview.pdf';
        Storage::disk('private')->put($existingPath, 'old-docx-content');
        Storage::disk('private')->put($previewPath, 'old-preview-content');

        $upload = DocumentUpload::query()->create([
            'document_type_id' => null,
            'uploaded_by' => $admin->id,
            'dcr_record_id' => $record->id,
            'file_name' => 'DCR_PREV_001.docx',
            'file_path' => $existingPath,
            'storage_disk' => 'private',
            'preview_disk' => 'private',
            'preview_path' => $previewPath,
            'preview_mime' => 'application/pdf',
            'preview_generated_at' => now(),
            'preview_last_accessed_at' => now(),
            'preview_source_hash' => 'abc123',
            'preview_size' => 500,
            'remarks' => 'First publish',
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson(route('dcr.records.publish', $record), [
                'remarks' => 'Republished to verify preview cleanup',
            ]);

        $response->assertOk();

        $upload->refresh();

        $this->assertNull($upload->preview_disk);
        $this->assertNull($upload->preview_path);
        $this->assertNull($upload->preview_mime);
        $this->assertNull($upload->preview_generated_at);
        $this->assertNull($upload->preview_source_hash);
        Storage::disk('private')->assertMissing($previewPath);

        $this->assertSame('private', $upload->storage_disk);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    private function storeMinimalTemplate(string $module, string $disk, string $path, User $uploadedBy): void
    {
        $phpWord = new PhpWord;
        $phpWord->addSection()->addText('${dcrNo}${ofiNo}${carNo}');

        $tmpBasePath = tempnam(sys_get_temp_dir(), 'template_');
        $tmpPath = $tmpBasePath.'.docx';
        @unlink($tmpBasePath);

        IOFactory::createWriter($phpWord, 'Word2007')->save($tmpPath);
        Storage::disk($disk)->put($path, file_get_contents($tmpPath));

        @unlink($tmpPath);

        QmsTemplate::query()->create([
            'module' => $module,
            'name' => 'Test Template',
            'original_file_name' => basename($path),
            'file_name' => basename($path),
            'file_path' => $path,
            'storage_disk' => $disk,
            'is_active' => true,
            'uploaded_by' => $uploadedBy->id,
        ]);
    }
}
