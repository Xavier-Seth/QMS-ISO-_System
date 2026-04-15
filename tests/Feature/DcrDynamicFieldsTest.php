<?php

namespace Tests\Feature;

use App\Models\DcrRecord;
use App\Models\DocumentSeries;
use App\Models\DocumentType;
use App\Models\DocumentUpload;
use App\Models\QmsDynamicField;
use App\Models\QmsTemplate;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class DcrDynamicFieldsTest extends TestCase
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
            $table->unsignedBigInteger('dcr_record_id')->nullable();
            $table->string('revision')->nullable();
            $table->string('status')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->string('storage_disk')->nullable();
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
            $table->string('storage_disk', 50)->default('public');
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
    }

    public function test_normal_user_can_load_active_dcr_dynamic_fields(): void
    {
        $user = User::factory()->create([
            'username' => 'normaldcruser',
            'role' => 'user',
        ]);

        QmsDynamicField::query()->create([
            'module' => 'DCR',
            'label' => 'Office Code',
            'field_key' => 'officeCode',
            'field_type' => 'text',
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        QmsDynamicField::query()->create([
            'module' => 'DCR',
            'label' => 'Priority Date',
            'field_key' => 'priorityDate',
            'field_type' => 'date',
            'is_required' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        QmsDynamicField::query()->create([
            'module' => 'DCR',
            'label' => 'Inactive Field',
            'field_key' => 'inactiveField',
            'field_type' => 'text',
            'is_required' => false,
            'is_active' => false,
            'sort_order' => 0,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(route('dcr.dynamic-fields'));

        $response->assertOk();
        $response->assertJsonCount(2, 'fields');
        $response->assertJsonPath('fields.0.label', 'Priority Date');
        $response->assertJsonPath('fields.0.field_key', 'priorityDate');
        $response->assertJsonPath('fields.1.label', 'Office Code');
        $response->assertJsonMissing([
            'label' => 'Inactive Field',
        ]);
    }

    public function test_admin_cannot_create_dcr_dynamic_field_with_reserved_placeholder_key(): void
    {
        $admin = User::factory()->create([
            'username' => 'admindcrfields',
            'role' => 'admin',
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson(route('settings.dcr-template.fields.store'), [
                'label' => 'Override Date',
                'field_key' => 'date',
                'field_type' => 'text',
                'is_required' => false,
                'is_active' => true,
                'sort_order' => 0,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['field_key']);

        $this->assertDatabaseMissing('qms_dynamic_fields', [
            'module' => 'DCR',
            'field_key' => 'date',
        ]);
    }

    public function test_required_dcr_dynamic_fields_are_enforced_before_user_submission(): void
    {
        $user = User::factory()->create([
            'username' => 'dcrsubmitter',
            'role' => 'user',
        ]);

        QmsDynamicField::query()->create([
            'module' => 'DCR',
            'label' => 'Office Code',
            'field_key' => 'officeCode',
            'field_type' => 'text',
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $record = DcrRecord::query()->create([
            'document_type_id' => null,
            'dcr_no' => 'DCR-REQ-001',
            'status' => 'draft',
            'workflow_status' => null,
            'resolution_status' => 'open',
            'data' => [
                'dcrNo' => 'DCR-REQ-001',
                'dynamic' => [
                    'officeCode' => '',
                ],
            ],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(route('dcr.records.submit', $record));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['dynamic.officeCode']);

        $this->assertDatabaseHas('dcr_records', [
            'id' => $record->id,
            'status' => 'draft',
            'workflow_status' => null,
        ]);
    }

    public function test_published_dcr_document_upload_is_stored_on_private_disk(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $admin = User::factory()->create([
            'username' => 'admindcrpublish',
            'role' => 'admin',
        ]);

        $series = DocumentSeries::query()->create([
            'code_prefix' => 'R-QMS',
            'name' => 'Records',
        ]);

        DocumentType::query()->create([
            'series_id' => $series->id,
            'code' => 'R-QMS-013',
            'title' => 'Document Change Request Records',
            'storage' => 'Electronic',
            'status' => 'Active',
        ]);

        $templatePath = $this->storeMinimalDcrTemplate('private', 'qms/templates/dcr/test-template.docx');

        QmsTemplate::query()->create([
            'module' => 'DCR',
            'name' => 'Test DCR Template',
            'original_file_name' => 'test-template.docx',
            'file_name' => 'test-template.docx',
            'file_path' => $templatePath,
            'storage_disk' => 'private',
            'is_active' => true,
            'uploaded_by' => $admin->id,
        ]);

        $record = DcrRecord::query()->create([
            'document_type_id' => null,
            'dcr_no' => 'DCR-PRIVATE-001',
            'status' => 'draft',
            'workflow_status' => 'approved',
            'resolution_status' => 'open',
            'data' => [
                'dcrNo' => 'DCR-PRIVATE-001',
                'dynamic' => [],
            ],
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson(route('dcr.records.publish', $record), [
                'file_name' => 'DCR_PRIVATE_001.docx',
                'remarks' => 'Published from test',
            ]);

        $response->assertOk();

        $upload = DocumentUpload::query()->firstOrFail();

        $this->assertSame('private', $upload->storage_disk);
        Storage::disk('private')->assertExists($upload->file_path);
        Storage::disk('public')->assertMissing($upload->file_path);
    }

    private function storeMinimalDcrTemplate(string $disk, string $path): string
    {
        $phpWord = new PhpWord();
        $phpWord->addSection()->addText('${dcrNo}');

        $tmpBasePath = tempnam(sys_get_temp_dir(), 'dcr_template_');
        $tmpPath = $tmpBasePath . '.docx';
        @unlink($tmpBasePath);

        IOFactory::createWriter($phpWord, 'Word2007')->save($tmpPath);
        Storage::disk($disk)->put($path, file_get_contents($tmpPath));

        @unlink($tmpPath);

        return $path;
    }
}
