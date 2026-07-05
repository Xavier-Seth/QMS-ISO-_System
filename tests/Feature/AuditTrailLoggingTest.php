<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\CarRecord;
use App\Models\DcrRecord;
use App\Models\DocumentSeries;
use App\Models\DocumentType;
use App\Models\DocumentUpload;
use App\Models\OfiRecord;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuditTrailLoggingTest extends TestCase
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
            $table->string('manual_category')->nullable();
            $table->string('manual_access')->nullable();
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
            $table->text('remarks')->nullable();
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
    }

    public function test_draft_record_creation_logs_nothing(): void
    {
        $user = User::factory()->create([
            'username' => 'carauditcreator',
            'role' => 'user',
        ]);

        $this->actingAs($user);

        ActivityLog::query()->delete();

        CarRecord::query()->create([
            'car_no' => 'CAR-AUDIT-001',
            'status' => 'draft',
            'data' => [],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        OfiRecord::query()->create([
            'ofi_no' => 'OFI-AUDIT-DRAFT',
            'status' => 'draft',
            'data' => [],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        DcrRecord::query()->create([
            'dcr_no' => 'DCR-AUDIT-DRAFT',
            'status' => 'draft',
            'data' => [],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->assertSame(0, ActivityLog::query()->count());
    }

    public function test_draft_edit_and_status_change_log_nothing(): void
    {
        $user = User::factory()->create([
            'username' => 'carauditstatus',
            'role' => 'user',
        ]);

        $this->actingAs($user);

        $record = CarRecord::query()->create([
            'car_no' => 'CAR-AUDIT-002',
            'status' => 'draft',
            'data' => [],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        ActivityLog::query()->delete();

        $record->update(['car_no' => 'CAR-AUDIT-002-EDITED', 'data' => ['carNo' => 'CAR-AUDIT-002-EDITED']]);
        $record->update(['status' => 'submitted']);

        $this->assertSame(0, ActivityLog::query()->count());
    }

    public function test_car_workflow_status_change_alone_is_not_logged_by_observer(): void
    {
        $user = User::factory()->create([
            'username' => 'carauditworkflow',
            'role' => 'user',
        ]);

        $this->actingAs($user);

        $record = CarRecord::query()->create([
            'car_no' => 'CAR-AUDIT-003',
            'status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        ActivityLog::query()->delete();

        $record->update(['workflow_status' => 'pending']);

        $this->assertSame(0, ActivityLog::query()->count());
    }

    public function test_car_record_deletion_is_not_audit_logged(): void
    {
        $user = User::factory()->create([
            'username' => 'carauditdeleter',
            'role' => 'user',
        ]);

        $this->actingAs($user);

        $record = CarRecord::query()->create([
            'car_no' => 'CAR-AUDIT-004',
            'status' => 'draft',
            'data' => [],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        ActivityLog::query()->delete();

        $record->delete();

        $this->assertSame(0, ActivityLog::query()->count());
    }

    public function test_car_resolution_status_update_is_logged_exactly_once(): void
    {
        $admin = User::factory()->create([
            'username' => 'caradminresolution',
            'role' => 'admin',
        ]);

        $record = CarRecord::query()->create([
            'car_no' => 'CAR-AUDIT-005',
            'status' => 'submitted',
            'workflow_status' => 'approved',
            'resolution_status' => 'open',
            'data' => [],
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        ActivityLog::query()->delete();

        $response = $this
            ->actingAs($admin)
            ->patchJson(route('car.records.resolution-status', $record), [
                'resolution_status' => 'ongoing',
            ]);

        $response->assertOk();

        $this->assertSame(1, ActivityLog::query()
            ->where('module', 'car')
            ->where('action', 'resolution_status_changed')
            ->where('entity_id', $record->id)
            ->count());
    }

    public function test_ofi_submit_is_logged_exactly_once(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'username' => 'ofiauditsubmitter',
            'role' => 'user',
        ]);

        $record = OfiRecord::query()->create([
            'ofi_no' => 'OFI-AUDIT-001',
            'status' => 'draft',
            'data' => [],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        ActivityLog::query()->delete();

        $response = $this
            ->actingAs($user)
            ->postJson(route('ofi.records.submit', $record));

        $response->assertOk();

        $this->assertSame(1, ActivityLog::query()
            ->where('module', 'ofi')
            ->where('action', 'submitted')
            ->where('entity_id', $record->id)
            ->count());
        $this->assertSame(0, ActivityLog::query()->where('action', 'status_changed')->count());
        $this->assertSame(1, ActivityLog::query()->count());
    }

    public function test_car_submit_is_logged_exactly_once(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'username' => 'carauditsubmitter',
            'role' => 'user',
        ]);

        $record = CarRecord::query()->create([
            'car_no' => 'CAR-AUDIT-SUBMIT',
            'status' => 'draft',
            'data' => [],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        ActivityLog::query()->delete();

        $response = $this
            ->actingAs($user)
            ->postJson(route('car.records.submit', $record));

        $response->assertOk();

        $this->assertSame(1, ActivityLog::query()
            ->where('module', 'car')
            ->where('action', 'submitted')
            ->where('entity_id', $record->id)
            ->count());
        $this->assertSame(0, ActivityLog::query()->where('action', 'status_changed')->count());
        $this->assertSame(1, ActivityLog::query()->count());
    }

    public function test_dcr_submit_is_logged_exactly_once(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'username' => 'dcrauditsubmitter',
            'role' => 'user',
        ]);

        $record = DcrRecord::query()->create([
            'dcr_no' => 'DCR-AUDIT-SUBMIT',
            'status' => 'draft',
            'data' => [],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        ActivityLog::query()->delete();

        $response = $this
            ->actingAs($user)
            ->postJson(route('dcr.records.submit', $record));

        $response->assertOk();

        $this->assertSame(1, ActivityLog::query()
            ->where('module', 'dcr')
            ->where('action', 'submitted')
            ->where('entity_id', $record->id)
            ->count());
        $this->assertSame(0, ActivityLog::query()->where('action', 'status_changed')->count());
        $this->assertSame(1, ActivityLog::query()->count());
    }

    public function test_ofi_reject_is_logged_exactly_once(): void
    {
        Notification::fake();

        $admin = User::factory()->create([
            'username' => 'ofiadminrejector',
            'role' => 'admin',
        ]);

        $user = User::factory()->create([
            'username' => 'ofiauditowner',
            'role' => 'user',
        ]);

        $record = OfiRecord::query()->create([
            'ofi_no' => 'OFI-AUDIT-002',
            'status' => 'submitted',
            'workflow_status' => 'pending',
            'data' => [],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        ActivityLog::query()->delete();

        $response = $this
            ->actingAs($admin)
            ->post(route('ofi.inbox.reject', $record), [
                'rejection_reason' => 'Incomplete details.',
            ]);

        $response->assertRedirect();

        $this->assertSame(1, ActivityLog::query()
            ->where('module', 'ofi')
            ->where('action', 'rejected')
            ->where('entity_id', $record->id)
            ->count());
    }

    public function test_record_linked_upload_lifecycle_is_not_logged_by_observer(): void
    {
        $user = User::factory()->create([
            'username' => 'uploadauditlinked',
            'role' => 'admin',
        ]);

        $this->actingAs($user);

        $ofiRecord = OfiRecord::query()->create([
            'ofi_no' => 'OFI-UPLOAD-001',
            'status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $carRecord = CarRecord::query()->create([
            'car_no' => 'CAR-UPLOAD-001',
            'status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        ActivityLog::query()->delete();

        $ofiUpload = DocumentUpload::query()->create([
            'uploaded_by' => $user->id,
            'ofi_record_id' => $ofiRecord->id,
            'file_name' => 'OFI_UPLOAD_001.docx',
            'file_path' => 'documents/ofi/OFI_UPLOAD_001.docx',
            'storage_disk' => 'private',
        ]);

        $carUpload = DocumentUpload::query()->create([
            'uploaded_by' => $user->id,
            'car_record_id' => $carRecord->id,
            'file_name' => 'CAR_UPLOAD_001.docx',
            'file_path' => 'documents/car/CAR_UPLOAD_001.docx',
            'storage_disk' => 'private',
        ]);

        $dcrUpload = DocumentUpload::query()->create([
            'uploaded_by' => $user->id,
            'dcr_record_id' => 999,
            'file_name' => 'DCR_UPLOAD_001.docx',
            'file_path' => 'documents/dcr/DCR_UPLOAD_001.docx',
            'storage_disk' => 'private',
        ]);

        $ofiUpload->update(['file_name' => 'OFI_UPLOAD_001_v2.docx']);
        $carUpload->delete();
        $dcrUpload->delete();

        $this->assertSame(0, ActivityLog::query()->count());
    }

    public function test_standalone_document_upload_is_not_logged_by_observer(): void
    {
        $user = User::factory()->create([
            'username' => 'uploadauditdocs',
            'role' => 'admin',
        ]);

        $this->actingAs($user);

        $series = DocumentSeries::query()->create([
            'code_prefix' => 'R-QMS',
            'name' => 'Records',
        ]);

        $documentType = DocumentType::query()->create([
            'series_id' => $series->id,
            'code' => 'R-QMS-020',
            'title' => 'Standalone Records',
            'storage' => 'Electronic',
            'status' => 'active',
        ]);

        ActivityLog::query()->delete();

        DocumentUpload::query()->create([
            'document_type_id' => $documentType->id,
            'uploaded_by' => $user->id,
            'file_name' => 'standalone.pdf',
            'file_path' => 'documents/standalone.pdf',
            'storage_disk' => 'private',
        ]);

        $this->assertSame(0, ActivityLog::query()
            ->where('action', 'uploaded')
            ->count());
    }

    public function test_manual_upload_is_logged_once_by_observer(): void
    {
        $user = User::factory()->create([
            'username' => 'uploadauditmanual',
            'role' => 'admin',
        ]);

        $this->actingAs($user);

        $series = DocumentSeries::query()->create([
            'code_prefix' => 'MANUAL',
            'name' => 'Manuals',
        ]);

        $documentType = DocumentType::query()->create([
            'series_id' => $series->id,
            'code' => 'MANUAL-QSM-01',
            'title' => 'Quality System Manual',
            'storage' => 'Electronic',
            'status' => 'active',
            'manual_category' => 'QSM',
            'manual_access' => 'controlled',
        ]);

        ActivityLog::query()->delete();

        $upload = DocumentUpload::query()->create([
            'document_type_id' => $documentType->id,
            'uploaded_by' => $user->id,
            'status' => 'Active',
            'file_name' => 'qsm-manual.pdf',
            'file_path' => 'manuals/qsm-manual.pdf',
            'storage_disk' => 'private',
        ]);

        $this->assertSame(1, ActivityLog::query()
            ->where('module', 'manuals')
            ->where('action', 'uploaded')
            ->where('entity_id', $upload->id)
            ->count());
    }

    public function test_ofi_resolution_status_update_is_logged_exactly_once(): void
    {
        $admin = User::factory()->create([
            'username' => 'ofiadminresolution',
            'role' => 'admin',
        ]);

        $record = OfiRecord::query()->create([
            'ofi_no' => 'OFI-AUDIT-003',
            'status' => 'submitted',
            'workflow_status' => 'approved',
            'resolution_status' => 'open',
            'data' => [],
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        ActivityLog::query()->delete();

        $response = $this
            ->actingAs($admin)
            ->patchJson(route('ofi.records.resolution-status', $record), [
                'resolution_status' => 'ongoing',
            ]);

        $response->assertOk();

        $this->assertSame(1, ActivityLog::query()
            ->where('module', 'ofi')
            ->where('action', 'resolution_status_changed')
            ->where('entity_id', $record->id)
            ->count());
    }
}
