<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
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
use Illuminate\Support\Facades\Route;
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

        $this->assertSame(1, ActivityLog::query()
            ->where('module', 'ofi')
            ->where('action', 'approved')
            ->count());
        $this->assertSame(0, ActivityLog::query()->where('action', 'uploaded')->count());
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

        $this->assertSame(1, ActivityLog::query()
            ->where('module', 'car')
            ->where('action', 'approved')
            ->count());
        $this->assertSame(0, ActivityLog::query()->where('action', 'uploaded')->count());
    }

    public function test_car_approve_returns_error_when_record_deleted_before_lock(): void
    {
        $admin = User::factory()->create(['username' => 'admincarrace', 'role' => 'admin']);

        $record = CarRecord::query()->create([
            'car_no' => 'CAR-RACE-001',
            'status' => 'submitted',
            'workflow_status' => 'pending',
            'resolution_status' => 'open',
            'data' => [],
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        // Override route binding to return the model object even after it is deleted,
        // simulating a race condition where the record is removed between binding and lock.
        Route::bind('carRecord', fn () => $record);

        CarRecord::query()->where('id', $record->id)->delete();

        $response = $this
            ->actingAs($admin)
            ->from('/inbox')
            ->post(route('car.inbox.approve', ['carRecord' => $record->id]));

        $response->assertRedirect('/inbox');
        $response->assertSessionHas('error', 'CAR record not found.');
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

        $this->assertSame(1, ActivityLog::query()
            ->where('module', 'dcr')
            ->where('action', 'published')
            ->count());
        $this->assertSame(0, ActivityLog::query()
            ->whereIn('action', ['uploaded', 'replaced'])
            ->count());
    }

    public function test_dcr_approval_creates_exactly_one_audit_entry(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $admin = User::factory()->create(['username' => 'admindcraudit', 'role' => 'admin']);

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

        $this->storeMinimalTemplate('DCR', 'private', 'qms/templates/dcr/test-dcr-audit.docx', $admin);

        $record = DcrRecord::query()->create([
            'document_type_id' => null,
            'dcr_no' => 'DCR-AUDIT-001',
            'status' => 'submitted',
            'workflow_status' => 'pending',
            'resolution_status' => 'open',
            'data' => ['dcrNo' => 'DCR-AUDIT-001', 'dynamic' => []],
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        ActivityLog::query()->delete();

        $response = $this
            ->actingAs($admin)
            ->post(route('dcr.inbox.approve', $record));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSame(1, ActivityLog::query()
            ->where('module', 'dcr')
            ->where('action', 'approved')
            ->count());
        $this->assertSame(0, ActivityLog::query()->where('action', 'uploaded')->count());
    }

    public function test_admin_publish_marks_dcr_record_submitted_and_approved(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $admin = User::factory()->create(['username' => 'admindcrpublish', 'role' => 'admin']);

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

        $this->storeMinimalTemplate('DCR', 'private', 'qms/templates/dcr/test-dcr-publish.docx', $admin);

        $record = DcrRecord::query()->create([
            'document_type_id' => null,
            'dcr_no' => 'DCR-PUB-001',
            'status' => 'draft',
            'workflow_status' => null,
            'resolution_status' => 'open',
            'data' => ['dcrNo' => 'DCR-PUB-001', 'dynamic' => []],
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson(route('dcr.records.publish', $record), [
                'remarks' => 'First publish',
            ]);

        $response->assertOk();
        $response->assertJsonPath('workflow_status', 'approved');

        $record->refresh();
        $this->assertSame('submitted', $record->status);
        $this->assertSame('approved', $record->workflow_status);
        $this->assertSame(1, DocumentUpload::query()->where('dcr_record_id', $record->id)->count());

        // Republishing an already-approved record is idempotent.
        $this
            ->actingAs($admin)
            ->postJson(route('dcr.records.publish', $record), [
                'remarks' => 'Republish',
            ])
            ->assertOk();

        $record->refresh();
        $this->assertSame('submitted', $record->status);
        $this->assertSame('approved', $record->workflow_status);
        $this->assertSame(1, DocumentUpload::query()->where('dcr_record_id', $record->id)->count());
    }

    public function test_admin_publish_marks_ofi_record_submitted_and_approved(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $admin = User::factory()->create(['username' => 'adminofipublish', 'role' => 'admin']);

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

        $this->storeMinimalTemplate(QmsTemplateModules::OFI, 'private', 'qms/templates/ofi/test-ofi-publish.docx', $admin);

        $record = OfiRecord::query()->create([
            'document_type_id' => null,
            'ofi_no' => 'OFI-PUB-001',
            'status' => 'draft',
            'workflow_status' => null,
            'resolution_status' => 'open',
            'data' => ['ofiNo' => 'OFI-PUB-001', 'dynamic' => []],
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson(route('ofi.records.publish', $record), [
                'remarks' => 'First publish',
            ]);

        $response->assertOk();
        $response->assertJsonPath('workflow_status', 'approved');

        $record->refresh();
        $this->assertSame('submitted', $record->status);
        $this->assertSame('approved', $record->workflow_status);
        $this->assertSame(1, DocumentUpload::query()->where('ofi_record_id', $record->id)->count());
    }

    public function test_admin_publish_marks_car_record_submitted_and_approved(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $admin = User::factory()->create(['username' => 'admincarpublish', 'role' => 'admin']);

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

        $this->storeMinimalTemplate(QmsTemplateModules::CAR, 'private', 'qms/templates/car/test-car-publish.docx', $admin);

        $record = CarRecord::query()->create([
            'document_type_id' => null,
            'car_no' => 'CAR-PUB-001',
            'status' => 'draft',
            'workflow_status' => null,
            'resolution_status' => 'open',
            'data' => ['carNo' => 'CAR-PUB-001', 'dynamic' => []],
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson(route('car.records.publish', $record), [
                'remarks' => 'First publish',
            ]);

        $response->assertOk();
        $response->assertJsonPath('workflow_status', 'approved');

        $record->refresh();
        $this->assertSame('submitted', $record->status);
        $this->assertSame('approved', $record->workflow_status);
        $this->assertSame(1, DocumentUpload::query()->where('car_record_id', $record->id)->count());
    }

    public function test_publish_rejects_pending_and_rejected_records(): void
    {
        $admin = User::factory()->create(['username' => 'adminpublishguard', 'role' => 'admin']);
        $staff = User::factory()->create(['username' => 'staffpublishguard']);

        $modules = [
            [OfiRecord::class, 'ofi_no', 'ofi.records.publish'],
            [DcrRecord::class, 'dcr_no', 'dcr.records.publish'],
            [CarRecord::class, 'car_no', 'car.records.publish'],
        ];

        foreach ($modules as [$model, $noColumn, $routeName]) {
            $pending = $model::query()->create([
                $noColumn => 'GUARD-PENDING',
                'status' => 'submitted',
                'workflow_status' => 'pending',
                'data' => [],
                'created_by' => $staff->id,
                'updated_by' => $staff->id,
            ]);

            $this->actingAs($admin)
                ->postJson(route($routeName, $pending))
                ->assertStatus(422);

            $pending->refresh();
            $this->assertSame('pending', $pending->workflow_status);
            $this->assertSame('submitted', $pending->status);

            $rejected = $model::query()->create([
                $noColumn => 'GUARD-REJECTED',
                'status' => 'submitted',
                'workflow_status' => 'rejected',
                'rejection_reason' => 'Needs correction.',
                'rejected_at' => now()->subDay(),
                'rejected_by' => $admin->id,
                'data' => [],
                'created_by' => $staff->id,
                'updated_by' => $staff->id,
            ]);

            $this->actingAs($admin)
                ->postJson(route($routeName, $rejected))
                ->assertStatus(422);

            $rejected->refresh();
            $this->assertSame('rejected', $rejected->workflow_status);
            $this->assertSame('Needs correction.', $rejected->rejection_reason);
            $this->assertNotNull($rejected->rejected_at);
            $this->assertSame($admin->id, (int) $rejected->rejected_by);

            $this->assertSame(0, DocumentUpload::query()->count());
        }
    }

    // --- M-2: record write validation (guard-only) ---

    private function seedDocumentType(string $code): DocumentType
    {
        $series = DocumentSeries::query()->firstOrCreate(
            ['code_prefix' => 'R-QMS'],
            ['name' => 'Records']
        );

        return DocumentType::query()->create([
            'series_id' => $series->id,
            'code' => $code,
            'title' => $code.' Records',
            'storage' => 'Electronic',
            'status' => 'active',
        ]);
    }

    public function test_dcr_store_rejects_oversize_scalar_and_creates_nothing(): void
    {
        $this->seedDocumentType('R-QMS-013');
        $user = User::factory()->create(['username' => 'dcrstorebad']);

        $response = $this
            ->actingAs($user)
            ->postJson(route('dcr.records.store'), [
                'dcrNo' => str_repeat('a', 300),
            ]);

        $response->assertStatus(422);
        $this->assertSame(0, DcrRecord::query()->count());
    }

    public function test_dcr_store_allows_short_draft(): void
    {
        $this->seedDocumentType('R-QMS-013');
        $user = User::factory()->create(['username' => 'dcrstoreok']);

        $response = $this
            ->actingAs($user)
            ->postJson(route('dcr.records.store'), [
                'dcrNo' => 'DCR-OK-1',
                'toFor' => 'Someone',
            ]);

        $response->assertOk();
        $this->assertSame(1, DcrRecord::query()->count());
        $this->assertSame('DCR-OK-1', DcrRecord::query()->first()->dcr_no);
    }

    public function test_dcr_update_rejects_oversize_scalar_and_leaves_record_intact(): void
    {
        $user = User::factory()->create(['username' => 'dcrupdatebad']);

        $record = DcrRecord::query()->create([
            'document_type_id' => null,
            'dcr_no' => 'DCR-ORIG',
            'to_for' => 'Original To',
            'from' => 'Original From',
            'status' => 'draft',
            'workflow_status' => null,
            'resolution_status' => 'open',
            'data' => ['dcrNo' => 'DCR-ORIG', 'dynamic' => []],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->putJson(route('dcr.records.update', $record), [
                'dcrNo' => str_repeat('a', 300),
            ]);

        $response->assertStatus(422);

        $record->refresh();
        $this->assertSame('DCR-ORIG', $record->dcr_no);
        $this->assertSame('Original To', $record->to_for);
        $this->assertSame('Original From', $record->from);
    }

    public function test_ofi_store_rejects_oversize_scalar_and_creates_nothing(): void
    {
        $this->seedDocumentType('R-QMS-018');
        $user = User::factory()->create(['username' => 'ofistorebad']);

        $response = $this
            ->actingAs($user)
            ->postJson(route('ofi.records.store'), [
                'ofiNo' => str_repeat('a', 300),
            ]);

        $response->assertStatus(422);
        $this->assertSame(0, OfiRecord::query()->count());
    }

    public function test_ofi_store_allows_short_draft(): void
    {
        $this->seedDocumentType('R-QMS-018');
        $user = User::factory()->create(['username' => 'ofistoreok']);

        $response = $this
            ->actingAs($user)
            ->postJson(route('ofi.records.store'), [
                'ofiNo' => 'OFI-OK-1',
                'to' => 'Someone',
            ]);

        $response->assertOk();
        $this->assertSame(1, OfiRecord::query()->count());
        $this->assertSame('OFI-OK-1', OfiRecord::query()->first()->ofi_no);
    }

    public function test_ofi_update_rejects_oversize_scalar_and_leaves_record_intact(): void
    {
        $user = User::factory()->create(['username' => 'ofiupdatebad']);

        $record = OfiRecord::query()->create([
            'document_type_id' => null,
            'ofi_no' => 'OFI-ORIG',
            'ref_no' => 'REF-ORIG',
            'to' => 'Original To',
            'status' => 'draft',
            'workflow_status' => null,
            'resolution_status' => 'open',
            'data' => ['ofiNo' => 'OFI-ORIG', 'dynamic' => []],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->putJson(route('ofi.records.update', $record), [
                'ofiNo' => str_repeat('a', 300),
            ]);

        $response->assertStatus(422);

        $record->refresh();
        $this->assertSame('OFI-ORIG', $record->ofi_no);
        $this->assertSame('REF-ORIG', $record->ref_no);
        $this->assertSame('Original To', $record->to);
    }

    public function test_car_store_rejects_oversize_scalar_and_creates_nothing(): void
    {
        $type = $this->seedDocumentType('R-QMS-017');
        $user = User::factory()->create(['username' => 'carstorebad']);

        $response = $this
            ->actingAs($user)
            ->postJson(route('car.records.store'), [
                'document_type_id' => $type->id,
                'data' => ['carNo' => str_repeat('a', 300)],
            ]);

        $response->assertStatus(422);
        $this->assertSame(0, CarRecord::query()->count());

        // The message reads the clean field name, not the leaked "data." prefix.
        $message = $response->json('message');
        $this->assertStringContainsString('car no', $message);
        $this->assertStringNotContainsString('data.', $message);
    }

    public function test_car_store_allows_short_draft(): void
    {
        $type = $this->seedDocumentType('R-QMS-017');
        $user = User::factory()->create(['username' => 'carstoreok']);

        $response = $this
            ->actingAs($user)
            ->postJson(route('car.records.store'), [
                'document_type_id' => $type->id,
                'data' => [
                    'carNo' => 'CAR-OK-1',
                    'dynamic' => ['officeCode' => 'QMS-CAR'],
                    'followUp' => [
                        ['date' => '2026-07-01', 'status' => 'Done', 'effective' => 'Yes', 'auditor' => 'A. Auditor', 'rep' => 'R. Rep'],
                    ],
                    'notedBy' => 'QMR Head',
                ],
            ]);

        $response->assertOk();
        $this->assertSame(1, CarRecord::query()->count());

        $record = CarRecord::query()->first();
        $this->assertSame('CAR-OK-1', $record->car_no);
        $this->assertSame('QMS-CAR', $record->data['dynamic']['officeCode'] ?? null);
        $this->assertSame('2026-07-01', $record->data['followUp'][0]['date'] ?? null);
        $this->assertSame('QMR Head', $record->data['notedBy'] ?? null);
    }

    public function test_car_update_rejects_oversize_scalar_and_leaves_record_intact(): void
    {
        $user = User::factory()->create(['username' => 'carupdatebad']);

        $record = CarRecord::query()->create([
            'document_type_id' => null,
            'car_no' => 'CAR-ORIG',
            'ref_no' => 'REF-ORIG',
            'dept_section' => 'Original Dept',
            'auditor' => 'Original Auditor',
            'status' => 'draft',
            'workflow_status' => null,
            'resolution_status' => 'open',
            'data' => ['carNo' => 'CAR-ORIG', 'dynamic' => []],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->putJson(route('car.records.update', $record), [
                'data' => ['carNo' => str_repeat('a', 300)],
            ]);

        $response->assertStatus(422);

        $record->refresh();
        $this->assertSame('CAR-ORIG', $record->car_no);
        $this->assertSame('REF-ORIG', $record->ref_no);
        $this->assertSame('Original Dept', $record->dept_section);
        $this->assertSame('Original Auditor', $record->auditor);
    }

    public function test_car_update_preserves_nested_data_alongside_validated_scalars(): void
    {
        $user = User::factory()->create(['username' => 'carupdatenested']);

        $record = CarRecord::query()->create([
            'document_type_id' => null,
            'car_no' => 'CAR-NEST-ORIG',
            'status' => 'draft',
            'workflow_status' => null,
            'resolution_status' => 'open',
            'data' => ['carNo' => 'CAR-NEST-ORIG', 'dynamic' => []],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->putJson(route('car.records.update', $record), [
                'data' => [
                    'carNo' => 'CAR-NEST-NEW',
                    'dynamic' => ['officeCode' => 'QMS-CAR'],
                    'followUp' => [
                        ['date' => '2026-07-02', 'status' => 'Ongoing', 'effective' => 'No', 'auditor' => 'B. Auditor', 'rep' => 'S. Rep'],
                    ],
                    'notedBy' => 'QMR Head',
                ],
            ]);

        $response->assertOk();

        $record->refresh();
        $this->assertSame('CAR-NEST-NEW', $record->car_no);
        $this->assertSame('QMS-CAR', $record->data['dynamic']['officeCode'] ?? null);
        $this->assertSame('2026-07-02', $record->data['followUp'][0]['date'] ?? null);
        $this->assertSame('QMR Head', $record->data['notedBy'] ?? null);
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
