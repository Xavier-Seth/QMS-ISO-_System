<?php

namespace Tests\Feature;

use App\Models\QmsDynamicField;
use App\Models\QmsTemplate;
use App\Models\User;
use App\Services\QmsTemplateResolver;
use App\Support\QmsTemplateModules;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class QmsTemplateInfrastructureTest extends TestCase
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
    }

    public function test_dcr_template_upload_stores_new_templates_on_private_disk(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $admin = User::factory()->create([
            'username' => 'adminqmstemplate',
            'role' => 'admin',
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson(route('settings.dcr-template.upload'), [
                'template_file' => UploadedFile::fake()->create(
                    'DCR Template.docx',
                    12,
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                ),
                'name' => 'DCR Template',
                'set_active' => true,
            ]);

        $response->assertOk();
        $response->assertJsonPath('template.module', QmsTemplateModules::DCR);
        $response->assertJsonPath('template.storage_disk', 'private');

        $template = QmsTemplate::query()->firstOrFail();

        $this->assertSame(QmsTemplateModules::DCR, $template->module);
        $this->assertSame('private', $template->storage_disk);
        $this->assertTrue((bool) $template->is_active);
        Storage::disk('private')->assertExists($template->file_path);
        Storage::disk('public')->assertMissing($template->file_path);
    }

    public function test_public_disk_templates_still_resolve_through_template_resolver(): void
    {
        Storage::fake('public');

        $path = 'qms/templates/dcr/legacy-public-template.docx';
        Storage::disk('public')->put($path, 'legacy-template');

        QmsTemplate::query()->create([
            'module' => QmsTemplateModules::DCR,
            'name' => 'Legacy Public DCR Template',
            'original_file_name' => 'legacy-public-template.docx',
            'file_name' => 'legacy-public-template.docx',
            'file_path' => $path,
            'storage_disk' => 'public',
            'is_active' => true,
            'uploaded_by' => null,
        ]);

        $resolvedPath = app(QmsTemplateResolver::class)->getActiveDcrTemplatePath();

        $this->assertSame(Storage::disk('public')->path($path), $resolvedPath);
        $this->assertFileExists($resolvedPath);
    }

    public function test_template_resolver_resolves_dcr_ofi_and_car_active_template_paths(): void
    {
        Storage::fake('private');

        $templates = [
            QmsTemplateModules::DCR => 'qms/templates/dcr/active-dcr-template.docx',
            QmsTemplateModules::OFI => 'qms/templates/ofi/active-ofi-template.docx',
            QmsTemplateModules::CAR => 'qms/templates/car/active-car-template.docx',
        ];

        foreach ($templates as $module => $path) {
            Storage::disk('private')->put($path, strtolower($module) . '-template');

            QmsTemplate::query()->create([
                'module' => $module,
                'name' => "{$module} Active Template",
                'original_file_name' => basename($path),
                'file_name' => basename($path),
                'file_path' => $path,
                'storage_disk' => 'private',
                'is_active' => true,
                'uploaded_by' => null,
            ]);
        }

        $resolver = app(QmsTemplateResolver::class);

        $this->assertSame(
            Storage::disk('private')->path($templates[QmsTemplateModules::DCR]),
            $resolver->getActiveDcrTemplatePath()
        );

        $this->assertSame(
            Storage::disk('private')->path($templates[QmsTemplateModules::OFI]),
            $resolver->getActiveOfiTemplatePath()
        );

        $this->assertSame(
            Storage::disk('private')->path($templates[QmsTemplateModules::CAR]),
            $resolver->getActiveCarTemplatePath()
        );
    }

    public function test_invalid_template_module_usage_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(QmsTemplateResolver::class)->getActiveTemplatePath('BAD');
    }

    public function test_admin_can_access_generic_dcr_settings_route(): void
    {
        $admin = User::factory()->create([
            'username' => 'admingenericdcr',
            'role' => 'admin',
        ]);

        $response = $this
            ->actingAs($admin)
            ->getJson(route('settings.qms-templates.index', [
                'module' => QmsTemplateModules::DCR,
            ]));

        $response->assertOk();
        $response->assertJsonPath('module', QmsTemplateModules::DCR);
        $response->assertJsonCount(0, 'templates');
        $response->assertJsonCount(0, 'fields');
    }

    public function test_admin_can_access_generic_ofi_and_car_settings_routes(): void
    {
        $admin = User::factory()->create([
            'username' => 'admingenericoficar',
            'role' => 'admin',
        ]);

        foreach ([QmsTemplateModules::OFI, QmsTemplateModules::CAR] as $module) {
            $response = $this
                ->actingAs($admin)
                ->getJson(route('settings.qms-templates.index', [
                    'module' => $module,
                ]));

            $response->assertOk();
            $response->assertJsonPath('module', $module);
            $response->assertJsonCount(0, 'templates');
            $response->assertJsonCount(0, 'fields');
        }
    }

    public function test_invalid_generic_qms_template_settings_module_returns_not_found(): void
    {
        $admin = User::factory()->create([
            'username' => 'adminbadmodule',
            'role' => 'admin',
        ]);

        $response = $this
            ->actingAs($admin)
            ->getJson(route('settings.qms-templates.index', [
                'module' => 'BAD',
            ]));

        $response->assertNotFound();
    }

    public function test_existing_dcr_settings_routes_still_work_through_shim(): void
    {
        $admin = User::factory()->create([
            'username' => 'admindcrshim',
            'role' => 'admin',
        ]);

        QmsTemplate::query()->create([
            'module' => QmsTemplateModules::DCR,
            'name' => 'Active DCR Template',
            'original_file_name' => 'active-dcr-template.docx',
            'file_name' => 'active-dcr-template.docx',
            'file_path' => 'qms/templates/dcr/active-dcr-template.docx',
            'storage_disk' => 'private',
            'is_active' => true,
            'uploaded_by' => $admin->id,
        ]);

        QmsDynamicField::query()->create([
            'module' => QmsTemplateModules::DCR,
            'label' => 'Office Code',
            'field_key' => 'officeCode',
            'field_type' => 'text',
            'is_required' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this
            ->actingAs($admin)
            ->getJson(route('settings.dcr-template.index'));

        $response->assertOk();
        $response->assertJsonPath('module', QmsTemplateModules::DCR);
        $response->assertJsonPath('active_template.name', 'Active DCR Template');
        $response->assertJsonPath('fields.0.field_key', 'officeCode');
    }
}
