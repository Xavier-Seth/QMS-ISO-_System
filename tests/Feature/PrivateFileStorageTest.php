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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PrivateFileStorageTest extends TestCase
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
            $table->string('position')->nullable();
            $table->string('department')->nullable();
            $table->string('office_location')->nullable();
            $table->string('profile_photo')->nullable();
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
            $table->string('manual_category', 20)->nullable();
            $table->string('manual_access', 20)->nullable();
            $table->string('storage')->nullable();
            $table->date('initial_issue_date')->nullable();
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

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makeUser(): User
    {
        return User::factory()->create(['role' => 'user']);
    }

    // =========================================================================
    // Profile photo
    // =========================================================================

    public function test_profile_photo_upload_stores_on_private_disk(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $user = $this->makeUser();

        $this->actingAs($user)->post(route('settings.profile.update'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $user->email,
            'profile_photo' => UploadedFile::fake()->image('me.png'),
        ])->assertRedirect();

        $user->refresh();

        $this->assertNotNull($user->profile_photo);
        Storage::disk('private')->assertExists($user->profile_photo);
        Storage::disk('public')->assertMissing($user->profile_photo);
    }

    public function test_profile_photo_route_requires_authentication(): void
    {
        $this->get(route('profile.photo'))->assertRedirect(route('login'));
    }

    public function test_profile_photo_route_serves_own_photo(): void
    {
        Storage::fake('private');

        $user = $this->makeUser();
        $path = UploadedFile::fake()->image('me.png')->store('profile-photos', 'private');
        $user->update(['profile_photo' => $path]);

        $this->actingAs($user)
            ->get(route('profile.photo'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_profile_photo_route_returns_404_without_photo(): void
    {
        Storage::fake('private');

        $this->actingAs($this->makeUser())
            ->get(route('profile.photo'))
            ->assertNotFound();
    }

    // =========================================================================
    // E-signature
    // =========================================================================

    public function test_signature_upload_stores_on_private_disk(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $this->actingAs($this->makeAdmin())->post(route('settings.signature.upload'), [
            'e_signature' => UploadedFile::fake()->image('signature.png'),
        ])->assertRedirect();

        $path = SystemSetting::first()->e_signature_path;

        $this->assertNotNull($path);
        Storage::disk('private')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_signature_image_route_is_forbidden_for_non_admin(): void
    {
        Storage::fake('private');

        $path = UploadedFile::fake()->image('signature.png')->store('signatures', 'private');
        SystemSetting::first()->update(['e_signature_path' => $path]);

        $this->actingAs($this->makeUser())
            ->get(route('settings.signature.image'))
            ->assertForbidden();
    }

    public function test_signature_image_route_serves_for_admin(): void
    {
        Storage::fake('private');

        $path = UploadedFile::fake()->image('signature.png')->store('signatures', 'private');
        SystemSetting::first()->update(['e_signature_path' => $path]);

        $this->actingAs($this->makeAdmin())
            ->get(route('settings.signature.image'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_guest_page_props_do_not_expose_signature_url(): void
    {
        SystemSetting::first()->update(['e_signature_path' => 'signatures/some-signature.png']);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('system_settings.e_signature_url', null)
        );
    }

    public function test_admin_page_props_expose_guarded_signature_route(): void
    {
        SystemSetting::first()->update(['e_signature_path' => 'signatures/some-signature.png']);

        $this->actingAs($this->makeAdmin())
            ->get('/')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home')
                ->where(
                    'system_settings.e_signature_url',
                    fn ($url) => str_contains((string) $url, '/settings/signature/image')
                )
            );
    }

    // =========================================================================
    // storage:migrate-private command
    // =========================================================================

    public function test_migrate_command_moves_public_files_to_private(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $admin = $this->makeAdmin();

        // Manual upload stranded on the public disk
        $series = DocumentSeries::query()->create(['code_prefix' => 'MANUAL', 'name' => 'Manuals']);
        $type = DocumentType::query()->create([
            'series_id' => $series->id,
            'code' => 'MANUAL-ASM-CONTROLLED',
            'title' => 'ASM Controlled Manual',
            'manual_category' => 'ASM',
            'manual_access' => 'controlled',
            'status' => 'active',
        ]);

        Storage::disk('public')->put('manuals/asm/controlled/manual.pdf', 'pdf-bytes');
        $upload = DocumentUpload::query()->create([
            'document_type_id' => $type->id,
            'uploaded_by' => $admin->id,
            'status' => 'Active',
            'file_name' => 'manual.pdf',
            'file_path' => 'manuals/asm/controlled/manual.pdf',
            'storage_disk' => 'public',
        ]);

        // Signature and profile photo stranded on the public disk
        Storage::disk('public')->put('signatures/sig.png', 'sig-bytes');
        SystemSetting::first()->update(['e_signature_path' => 'signatures/sig.png']);

        Storage::disk('public')->put('profile-photos/photo.png', 'photo-bytes');
        $admin->update(['profile_photo' => 'profile-photos/photo.png']);

        $this->artisan('storage:migrate-private')->assertSuccessful();

        Storage::disk('private')->assertExists('manuals/asm/controlled/manual.pdf');
        Storage::disk('public')->assertMissing('manuals/asm/controlled/manual.pdf');
        $this->assertDatabaseHas('document_uploads', [
            'id' => $upload->id,
            'storage_disk' => 'private',
        ]);

        Storage::disk('private')->assertExists('signatures/sig.png');
        Storage::disk('public')->assertMissing('signatures/sig.png');

        Storage::disk('private')->assertExists('profile-photos/photo.png');
        Storage::disk('public')->assertMissing('profile-photos/photo.png');
    }

    public function test_migrate_command_is_idempotent(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        Storage::disk('public')->put('signatures/sig.png', 'sig-bytes');
        SystemSetting::first()->update(['e_signature_path' => 'signatures/sig.png']);

        $this->artisan('storage:migrate-private')->assertSuccessful();
        $this->artisan('storage:migrate-private')->assertSuccessful();

        Storage::disk('private')->assertExists('signatures/sig.png');
    }

    public function test_migrate_command_dry_run_changes_nothing(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        Storage::disk('public')->put('signatures/sig.png', 'sig-bytes');
        SystemSetting::first()->update(['e_signature_path' => 'signatures/sig.png']);

        $this->artisan('storage:migrate-private', ['--dry-run' => true])->assertSuccessful();

        Storage::disk('public')->assertExists('signatures/sig.png');
        Storage::disk('private')->assertMissing('signatures/sig.png');
    }
}
