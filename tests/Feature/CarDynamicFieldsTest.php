<?php

namespace Tests\Feature;

use App\Models\CarRecord;
use App\Models\DocumentSeries;
use App\Models\DocumentType;
use App\Models\DocumentUpload;
use App\Models\QmsDynamicField;
use App\Models\QmsTemplate;
use App\Models\User;
use App\Support\QmsTemplateModules;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;
use ZipArchive;

class CarDynamicFieldsTest extends TestCase
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

    public function test_normal_user_can_load_active_car_dynamic_fields(): void
    {
        $user = User::factory()->create([
            'username' => 'normalcaruser',
            'role' => 'user',
        ]);

        QmsDynamicField::query()->create([
            'module' => QmsTemplateModules::CAR,
            'label' => 'Office Code',
            'field_key' => 'officeCode',
            'field_type' => 'text',
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        QmsDynamicField::query()->create([
            'module' => QmsTemplateModules::CAR,
            'label' => 'Target Completion Date',
            'field_key' => 'targetCompletionDate',
            'field_type' => 'date',
            'is_required' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        QmsDynamicField::query()->create([
            'module' => QmsTemplateModules::CAR,
            'label' => 'Inactive Field',
            'field_key' => 'inactiveField',
            'field_type' => 'text',
            'is_required' => false,
            'is_active' => false,
            'sort_order' => 0,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(route('car.dynamic-fields'));

        $response->assertOk();
        $response->assertJsonCount(2, 'fields');
        $response->assertJsonPath('fields.0.label', 'Target Completion Date');
        $response->assertJsonPath('fields.0.field_key', 'targetCompletionDate');
        $response->assertJsonPath('fields.1.label', 'Office Code');
        $response->assertJsonMissing([
            'label' => 'Inactive Field',
        ]);
    }

    public function test_required_car_dynamic_fields_are_enforced_before_user_submission(): void
    {
        $user = User::factory()->create([
            'username' => 'carsubmitter',
            'role' => 'user',
        ]);

        QmsDynamicField::query()->create([
            'module' => QmsTemplateModules::CAR,
            'label' => 'Office Code',
            'field_key' => 'officeCode',
            'field_type' => 'text',
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $record = CarRecord::query()->create([
            'document_type_id' => null,
            'car_no' => 'CAR-REQ-001',
            'status' => 'draft',
            'workflow_status' => null,
            'resolution_status' => 'open',
            'data' => [
                'carNo' => 'CAR-REQ-001',
                'dynamic' => [
                    'officeCode' => '',
                ],
            ],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(route('car.records.submit', $record));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['dynamic.officeCode']);

        $this->assertDatabaseHas('car_records', [
            'id' => $record->id,
            'status' => 'draft',
            'workflow_status' => null,
        ]);
    }

    public function test_car_update_validates_existing_dynamic_data_when_payload_is_omitted(): void
    {
        $user = User::factory()->create([
            'username' => 'carstatusonly',
            'role' => 'user',
        ]);

        $documentType = $this->createCarDocumentType();

        QmsDynamicField::query()->create([
            'module' => QmsTemplateModules::CAR,
            'label' => 'Office Code',
            'field_key' => 'officeCode',
            'field_type' => 'text',
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $record = CarRecord::query()->create([
            'document_type_id' => $documentType->id,
            'car_no' => 'CAR-STATUS-001',
            'status' => 'draft',
            'workflow_status' => null,
            'resolution_status' => 'open',
            'data' => [
                'carNo' => 'CAR-STATUS-001',
                'dynamic' => [
                    'officeCode' => 'QMS-CAR',
                ],
            ],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->putJson(route('car.records.update', $record), [
                'status' => 'submitted',
            ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'submitted');

        $record->refresh();

        $this->assertSame('QMS-CAR', $record->data['dynamic']['officeCode'] ?? null);
    }

    public function test_admin_cannot_create_car_dynamic_field_with_reserved_placeholder_key(): void
    {
        $admin = User::factory()->create([
            'username' => 'admincarfields',
            'role' => 'admin',
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson(route('settings.qms-templates.fields.store', QmsTemplateModules::CAR), [
                'label' => 'Override CAR Number',
                'field_key' => 'carNo',
                'field_type' => 'text',
                'is_required' => false,
                'is_active' => true,
                'sort_order' => 0,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['field_key']);

        $this->assertDatabaseMissing('qms_dynamic_fields', [
            'module' => QmsTemplateModules::CAR,
            'field_key' => 'carNo',
        ]);
    }

    public function test_saved_car_record_preserves_dynamic_field_values(): void
    {
        $user = User::factory()->create([
            'username' => 'carsavedynamic',
            'role' => 'user',
        ]);

        $documentType = $this->createCarDocumentType();

        $response = $this
            ->actingAs($user)
            ->postJson(route('car.records.store'), [
                'document_type_id' => $documentType->id,
                'data' => [
                    'carNo' => 'CAR-SAVED-001',
                    'refNo' => 'REF-001',
                    'deptSection' => 'QMS Office',
                    'dynamic' => [
                        'officeCode' => 'QMS-CAR',
                    ],
                ],
            ]);

        $response->assertOk();

        $record = CarRecord::query()->firstOrFail();

        $this->assertSame('QMS-CAR', $record->data['dynamic']['officeCode'] ?? null);

        $showResponse = $this
            ->actingAs($user)
            ->getJson(route('car.records.show', $record));

        $showResponse->assertOk();
        $showResponse->assertJsonPath('data.dynamic.officeCode', 'QMS-CAR');
    }

    public function test_car_publish_uses_active_template_and_dynamic_placeholders(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $admin = User::factory()->create([
            'username' => 'admincarpublish',
            'role' => 'admin',
        ]);

        $this->createCarDocumentType();
        $this->storeMinimalCarTemplate('private', 'qms/templates/car/car-active-template.docx', $admin);

        QmsDynamicField::query()->create([
            'module' => QmsTemplateModules::CAR,
            'label' => 'Office Code',
            'field_key' => 'officeCode',
            'field_type' => 'text',
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $record = CarRecord::query()->create([
            'document_type_id' => null,
            'car_no' => 'CAR-PUBLISH-001',
            'ref_no' => 'REF-PUBLISH',
            'dept_section' => 'QMS Office',
            'auditor' => 'Internal Auditor',
            'status' => 'draft',
            'workflow_status' => 'approved',
            'resolution_status' => 'open',
            'data' => [
                'carNo' => 'CAR-PUBLISH-001',
                'refNo' => 'REF-PUBLISH',
                'dynamic' => [
                    'officeCode' => 'QMS-CAR',
                ],
            ],
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson(route('car.records.publish', $record), [
                'file_name' => 'CAR_PUBLISH_001.docx',
                'remarks' => 'Published from test',
            ]);

        $response->assertOk();

        $upload = DocumentUpload::query()->firstOrFail();

        $this->assertSame('private', $upload->storage_disk);
        Storage::disk('private')->assertExists($upload->file_path);
        Storage::disk('public')->assertMissing($upload->file_path);

        $documentXml = $this->readDocxDocumentXml(Storage::disk('private')->path($upload->file_path));

        $this->assertStringContainsString('CAR-PUBLISH-001', $documentXml);
        $this->assertStringContainsString('QMS-CAR', $documentXml);
        $this->assertStringNotContainsString('${officeCode}', $documentXml);
    }

    public function test_existing_public_car_upload_can_still_be_downloaded(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $admin = User::factory()->create([
            'username' => 'admincarpubliccompat',
            'role' => 'admin',
        ]);

        $documentType = $this->createCarDocumentType();
        $this->storeMinimalCarTemplate('private', 'qms/templates/car/car-public-compatible-template.docx', $admin);

        $record = CarRecord::query()->create([
            'document_type_id' => $documentType->id,
            'car_no' => 'CAR-PUBLIC-001',
            'ref_no' => 'REF-PUBLIC',
            'dept_section' => 'QMS Office',
            'auditor' => 'Internal Auditor',
            'status' => 'draft',
            'workflow_status' => 'approved',
            'resolution_status' => 'open',
            'data' => [
                'carNo' => 'CAR-PUBLIC-001',
                'dynamic' => [
                    'officeCode' => 'PUBLIC-CAR',
                ],
            ],
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $path = 'documents/car/CAR_PUBLIC_001.docx';
        Storage::disk('public')->put($path, 'legacy-public-file');

        $upload = DocumentUpload::query()->create([
            'document_type_id' => $documentType->id,
            'uploaded_by' => $admin->id,
            'car_record_id' => $record->id,
            'file_name' => 'CAR_PUBLIC_001.docx',
            'file_path' => $path,
            'storage_disk' => 'public',
            'remarks' => 'Existing public upload',
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('documents.uploads.download', $upload));

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );

        Storage::disk('public')->assertExists($path);
    }

    public function test_republishing_existing_public_car_upload_moves_it_to_private_disk(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $admin = User::factory()->create([
            'username' => 'admincarrepublishprivate',
            'role' => 'admin',
        ]);

        $documentType = $this->createCarDocumentType();
        $this->storeMinimalCarTemplate('private', 'qms/templates/car/car-republish-template.docx', $admin);

        $record = CarRecord::query()->create([
            'document_type_id' => $documentType->id,
            'car_no' => 'CAR-REPUBLISH-001',
            'ref_no' => 'REF-REPUBLISH',
            'dept_section' => 'QMS Office',
            'auditor' => 'Internal Auditor',
            'status' => 'draft',
            'workflow_status' => 'approved',
            'resolution_status' => 'open',
            'data' => [
                'carNo' => 'CAR-REPUBLISH-001',
                'dynamic' => [
                    'officeCode' => 'REPUBLISH-CAR',
                ],
            ],
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $path = 'documents/car/CAR_REPUBLISH_001.docx';
        Storage::disk('public')->put($path, 'legacy-public-file');

        $upload = DocumentUpload::query()->create([
            'document_type_id' => $documentType->id,
            'uploaded_by' => $admin->id,
            'car_record_id' => $record->id,
            'file_name' => 'CAR_REPUBLISH_001.docx',
            'file_path' => $path,
            'storage_disk' => 'public',
            'preview_disk' => 'private',
            'preview_path' => 'previews/old-car-preview.pdf',
            'preview_mime' => 'application/pdf',
            'preview_generated_at' => now(),
            'preview_last_accessed_at' => now(),
            'preview_source_hash' => 'old-hash',
            'preview_size' => 123,
            'remarks' => 'Existing public upload',
        ]);
        Storage::disk('private')->put('previews/old-car-preview.pdf', 'old-preview');

        $response = $this
            ->actingAs($admin)
            ->postJson(route('car.records.publish', $record), [
                'remarks' => 'Republished privately',
            ]);

        $response->assertOk();
        $response->assertJsonPath('upload_id', $upload->id);

        $upload->refresh();

        $this->assertSame('private', $upload->storage_disk);
        $this->assertSame($path, $upload->file_path);
        $this->assertNull($upload->preview_disk);
        $this->assertNull($upload->preview_path);
        Storage::disk('private')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
        Storage::disk('private')->assertMissing('previews/old-car-preview.pdf');
    }

    public function test_published_car_download_regenerates_from_active_template(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $admin = User::factory()->create([
            'username' => 'admincarregenerate',
            'role' => 'admin',
        ]);

        $documentType = $this->createCarDocumentType();
        $this->storeMinimalCarTemplate('private', 'qms/templates/car/car-regenerate-template.docx', $admin);

        $record = CarRecord::query()->create([
            'document_type_id' => $documentType->id,
            'car_no' => 'CAR-REGEN-001',
            'ref_no' => 'REF-REGEN',
            'dept_section' => 'QMS Office',
            'auditor' => 'Internal Auditor',
            'status' => 'draft',
            'workflow_status' => 'approved',
            'resolution_status' => 'open',
            'data' => [
                'carNo' => 'CAR-REGEN-001',
                'dynamic' => [
                    'officeCode' => 'REGEN-CAR',
                ],
            ],
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $upload = DocumentUpload::query()->create([
            'document_type_id' => $documentType->id,
            'uploaded_by' => $admin->id,
            'car_record_id' => $record->id,
            'file_name' => 'CAR_REGEN_001.docx',
            'file_path' => 'documents/car/CAR_REGEN_001.docx',
            'remarks' => 'Existing published upload',
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('documents.uploads.download', $upload));

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );

        $contentDisposition = (string) $response->headers->get('content-disposition');

        $this->assertStringContainsString('CAR_REGEN_001.docx', $contentDisposition);
    }

    private function createCarDocumentType(): DocumentType
    {
        $series = DocumentSeries::query()->create([
            'code_prefix' => 'R-QMS',
            'name' => 'Records',
        ]);

        return DocumentType::query()->create([
            'series_id' => $series->id,
            'code' => 'R-QMS-017',
            'title' => 'Corrective Action Request Records',
            'storage' => 'Electronic',
            'status' => 'Active',
        ]);
    }

    private function storeMinimalCarTemplate(string $disk, string $path, User $uploadedBy): string
    {
        $phpWord = new PhpWord();
        $phpWord->addSection()->addText('${carNo} ${officeCode}');

        $tmpBasePath = tempnam(sys_get_temp_dir(), 'car_template_');
        $tmpPath = $tmpBasePath . '.docx';
        @unlink($tmpBasePath);

        IOFactory::createWriter($phpWord, 'Word2007')->save($tmpPath);
        Storage::disk($disk)->put($path, file_get_contents($tmpPath));

        @unlink($tmpPath);

        QmsTemplate::query()->create([
            'module' => QmsTemplateModules::CAR,
            'name' => 'Test CAR Template',
            'original_file_name' => basename($path),
            'file_name' => basename($path),
            'file_path' => $path,
            'storage_disk' => $disk,
            'is_active' => true,
            'uploaded_by' => $uploadedBy->id,
        ]);

        return $path;
    }

    private function readDocxDocumentXml(string $path): string
    {
        $zip = new ZipArchive();

        $this->assertTrue($zip->open($path));

        $documentXml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        return $documentXml;
    }
}
