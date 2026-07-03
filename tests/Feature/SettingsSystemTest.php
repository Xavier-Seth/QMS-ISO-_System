<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsSystemTest extends TestCase
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
            $table->string('backup_frequency')->default('weekly');
            $table->string('storage_location')->default('local');
            $table->boolean('auto_backup')->default(false);
            $table->string('e_signature_path')->nullable();
            $table->string('logo_path')->nullable();
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
    // updateSystem
    // -----------------------------------------------------------------------

    public function test_admin_can_update_general_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/settings/system', [
            'system_name' => 'New QMS Name',
            'institution_name' => 'New University',
            'office_name' => 'New Office',
            'maintenance_mode' => true,
        ])->assertRedirect();

        $settings = SystemSetting::first();
        $this->assertSame('New QMS Name', $settings->system_name);
        $this->assertSame('New University', $settings->institution_name);
        $this->assertSame('New Office', $settings->office_name);
        $this->assertTrue($settings->maintenance_mode);
    }

    public function test_non_admin_cannot_update_general_settings(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->post('/settings/system', [
            'system_name' => 'Hacked',
            'institution_name' => 'Hacked',
            'office_name' => 'Hacked',
        ])->assertForbidden();
    }

    public function test_update_system_validates_required_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/settings/system', [
            'system_name' => '',
            'institution_name' => '',
            'office_name' => '',
        ])->assertSessionHasErrors(['system_name', 'institution_name', 'office_name']);
    }

    public function test_update_system_logs_activity(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/settings/system', [
            'system_name' => 'Logged Name',
            'institution_name' => 'Logged Uni',
            'office_name' => 'Logged Office',
            'maintenance_mode' => false,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'settings',
            'action' => 'updated',
            'record_label' => 'General Settings',
        ]);
    }

    // -----------------------------------------------------------------------
    // uploadSignature
    // -----------------------------------------------------------------------

    public function test_admin_can_upload_e_signature(): void
    {
        Storage::fake('private');

        $admin = User::factory()->create(['role' => 'admin']);
        $file = UploadedFile::fake()->image('signature.png');

        $this->actingAs($admin)->post('/settings/signature', [
            'e_signature' => $file,
        ])->assertRedirect();

        $settings = SystemSetting::first();
        $this->assertNotNull($settings->e_signature_path);
        Storage::disk('private')->assertExists($settings->e_signature_path);
    }

    public function test_non_admin_cannot_upload_e_signature(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'user']);
        $file = UploadedFile::fake()->image('signature.png');

        $this->actingAs($user)->post('/settings/signature', [
            'e_signature' => $file,
        ])->assertForbidden();
    }

    public function test_upload_signature_rejects_non_image(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/settings/signature', [
            'e_signature' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('e_signature');
    }

    public function test_uploading_new_signature_deletes_old_one(): void
    {
        Storage::fake('private');

        $admin = User::factory()->create(['role' => 'admin']);
        $settings = SystemSetting::first();

        $oldPath = 'signatures/old-sig.png';
        Storage::disk('private')->put($oldPath, 'old');
        $settings->update(['e_signature_path' => $oldPath]);

        $this->actingAs($admin)->post('/settings/signature', [
            'e_signature' => UploadedFile::fake()->image('new-sig.png'),
        ]);

        Storage::disk('private')->assertMissing($oldPath);
    }

    public function test_upload_signature_logs_activity(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/settings/signature', [
            'e_signature' => UploadedFile::fake()->image('signature.png'),
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'settings',
            'action' => 'uploaded',
            'record_label' => 'E-Signature',
        ]);
    }

    // -----------------------------------------------------------------------
    // removeSignature
    // -----------------------------------------------------------------------

    public function test_admin_can_remove_e_signature(): void
    {
        Storage::fake('private');

        $admin = User::factory()->create(['role' => 'admin']);
        $settings = SystemSetting::first();

        $path = 'signatures/sig.png';
        Storage::disk('private')->put($path, 'data');
        $settings->update(['e_signature_path' => $path]);

        $this->actingAs($admin)->delete('/settings/signature')->assertRedirect();

        $settings->refresh();
        $this->assertNull($settings->e_signature_path);
        Storage::disk('private')->assertMissing($path);
    }

    public function test_non_admin_cannot_remove_e_signature(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->delete('/settings/signature')->assertForbidden();
    }

    public function test_remove_signature_logs_activity(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        $settings = SystemSetting::first();
        $settings->update(['e_signature_path' => 'signatures/sig.png']);

        $this->actingAs($admin)->delete('/settings/signature');

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'settings',
            'action' => 'removed',
            'record_label' => 'E-Signature',
        ]);
    }

    // -----------------------------------------------------------------------
    // uploadLogo
    // -----------------------------------------------------------------------

    public function test_admin_can_upload_logo(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/settings/logo', [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertRedirect();

        $settings = SystemSetting::first();
        $this->assertNotNull($settings->logo_path);
        Storage::disk('public')->assertExists($settings->logo_path);
    }

    public function test_non_admin_cannot_upload_logo(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->post('/settings/logo', [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertForbidden();
    }

    public function test_uploading_new_logo_deletes_old_one(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        $settings = SystemSetting::first();

        $oldPath = 'settings/old-logo.png';
        Storage::disk('public')->put($oldPath, 'old');
        $settings->update(['logo_path' => $oldPath]);

        $this->actingAs($admin)->post('/settings/logo', [
            'logo' => UploadedFile::fake()->image('new-logo.png'),
        ]);

        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_upload_logo_logs_activity(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/settings/logo', [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'settings',
            'action' => 'uploaded',
            'record_label' => 'System Logo',
        ]);
    }

    // -----------------------------------------------------------------------
    // removeLogo
    // -----------------------------------------------------------------------

    public function test_admin_can_remove_logo(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        $settings = SystemSetting::first();

        $path = 'settings/logo.png';
        Storage::disk('public')->put($path, 'data');
        $settings->update(['logo_path' => $path]);

        $this->actingAs($admin)->delete('/settings/logo')->assertRedirect();

        $settings->refresh();
        $this->assertNull($settings->logo_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_non_admin_cannot_remove_logo(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->delete('/settings/logo')->assertForbidden();
    }

    public function test_remove_logo_logs_activity(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        $settings = SystemSetting::first();
        $settings->update(['logo_path' => 'settings/logo.png']);

        $this->actingAs($admin)->delete('/settings/logo');

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'settings',
            'action' => 'removed',
            'record_label' => 'System Logo',
        ]);
    }
}
