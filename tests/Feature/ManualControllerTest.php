<?php

namespace Tests\Feature;

use App\Models\DocumentSeries;
use App\Models\DocumentType;
use App\Models\DocumentUpload;
use App\Models\User;
use Database\Seeders\ManualDocumentTypesSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManualControllerTest extends TestCase
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

    private function makeSeries(): DocumentSeries
    {
        return DocumentSeries::query()->create([
            'code_prefix' => 'MANUAL',
            'name' => 'Manuals',
        ]);
    }

    private function makeType(DocumentSeries $series, string $category, string $access): DocumentType
    {
        return DocumentType::query()->create([
            'series_id' => $series->id,
            'code' => 'MANUAL-'.strtoupper($category).'-'.strtoupper(str_replace('_', '-', $access)),
            'title' => strtoupper($category).' '.ucwords(str_replace('_', ' ', $access)).' Manual',
            'manual_category' => strtoupper($category),
            'manual_access' => strtolower($access),
            'storage' => 'Electronic',
            'requires_revision' => true,
            'status' => 'active',
        ]);
    }

    private function makeUpload(DocumentType $type, User $uploader, string $status = 'Active'): DocumentUpload
    {
        return DocumentUpload::query()->create([
            'document_type_id' => $type->id,
            'uploaded_by' => $uploader->id,
            'status' => $status,
            'file_name' => 'manual.pdf',
            'file_path' => 'manuals/asm/controlled/manual.pdf',
            'storage_disk' => 'public',
        ]);
    }

    // =========================================================================
    // show()
    // =========================================================================

    public function test_non_admin_cannot_see_controlled_section_in_page_props(): void
    {
        $series = $this->makeSeries();
        $this->makeType($series, 'ASM', 'controlled');
        $this->makeType($series, 'ASM', 'uncontrolled');

        $user = $this->makeUser();

        $this->actingAs($user)
            ->get(route('manual.show', ['category' => 'asm']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Manual/Show')
                ->where('manuals.controlled', null)
            );
    }

    public function test_non_admin_cannot_see_master_copy_section_in_page_props(): void
    {
        $series = $this->makeSeries();
        $this->makeType($series, 'ASM', 'master_copy');
        $this->makeType($series, 'ASM', 'uncontrolled');

        $user = $this->makeUser();

        $this->actingAs($user)
            ->get(route('manual.show', ['category' => 'asm']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Manual/Show')
                ->where('manuals.master_copy', null)
            );
    }

    public function test_admin_receives_all_three_sections_in_page_props(): void
    {
        $series = $this->makeSeries();
        $admin = $this->makeAdmin();

        $this->makeType($series, 'ASM', 'master_copy');
        $this->makeType($series, 'ASM', 'controlled');
        $this->makeType($series, 'ASM', 'uncontrolled');

        $this->actingAs($admin)
            ->get(route('manual.show', ['category' => 'asm']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Manual/Show')
                ->whereNot('manuals.master_copy', null)
                ->whereNot('manuals.controlled', null)
                ->whereNot('manuals.uncontrolled', null)
            );
    }

    public function test_non_admin_can_see_uncontrolled_section(): void
    {
        $series = $this->makeSeries();
        $this->makeType($series, 'ASM', 'uncontrolled');

        $user = $this->makeUser();

        $this->actingAs($user)
            ->get(route('manual.show', ['category' => 'asm']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Manual/Show')
                ->whereNot('manuals.uncontrolled', null)
            );
    }

    // =========================================================================
    // upload()
    // =========================================================================

    public function test_admin_can_upload_file_and_new_row_is_active(): void
    {
        Storage::fake('public');

        $series = $this->makeSeries();
        $admin = $this->makeAdmin();
        $type = $this->makeType($series, 'ASM', 'controlled');

        $file = UploadedFile::fake()->create('manual.pdf', 100, 'application/pdf');

        $response = $this->actingAs($admin)
            ->post(route('manual.upload', ['category' => 'asm', 'access' => 'controlled']), [
                'files' => [$file],
            ]);

        $response->assertRedirect(route('manual.show', ['category' => 'asm']));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('document_uploads', [
            'document_type_id' => $type->id,
            'status' => 'Active',
            'file_name' => 'manual.pdf',
        ]);
    }

    public function test_admin_can_bulk_upload_multiple_files(): void
    {
        Storage::fake('public');

        $series = $this->makeSeries();
        $admin = $this->makeAdmin();
        $type = $this->makeType($series, 'ASM', 'controlled');

        $files = [
            UploadedFile::fake()->create('first.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('second.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('third.pdf', 100, 'application/pdf'),
        ];

        $response = $this->actingAs($admin)
            ->post(route('manual.upload', ['category' => 'asm', 'access' => 'controlled']), [
                'files' => $files,
            ]);

        $response->assertRedirect(route('manual.show', ['category' => 'asm']));
        $response->assertSessionHas('success', '3 files uploaded successfully.');

        $this->assertDatabaseCount('document_uploads', 3);
    }

    public function test_upload_rejects_more_than_20_files(): void
    {
        Storage::fake('public');

        $series = $this->makeSeries();
        $admin = $this->makeAdmin();
        $this->makeType($series, 'ASM', 'controlled');

        $files = array_fill(0, 21, UploadedFile::fake()->create('manual.pdf', 100, 'application/pdf'));

        $this->actingAs($admin)
            ->post(route('manual.upload', ['category' => 'asm', 'access' => 'controlled']), [
                'files' => $files,
            ])
            ->assertSessionHasErrors('files');
    }

    public function test_upload_does_not_obsolete_existing_files(): void
    {
        Storage::fake('public');

        $series = $this->makeSeries();
        $admin = $this->makeAdmin();
        $type = $this->makeType($series, 'ASM', 'controlled');

        $existing = $this->makeUpload($type, $admin, 'Active');

        $file = UploadedFile::fake()->create('new.pdf', 100, 'application/pdf');

        $this->actingAs($admin)
            ->post(route('manual.upload', ['category' => 'asm', 'access' => 'controlled']), [
                'files' => [$file],
            ]);

        $this->assertDatabaseHas('document_uploads', [
            'id' => $existing->id,
            'status' => 'Active',
        ]);
    }

    public function test_non_admin_cannot_upload(): void
    {
        Storage::fake('public');

        $series = $this->makeSeries();
        $this->makeType($series, 'ASM', 'controlled');
        $user = $this->makeUser();

        $file = UploadedFile::fake()->create('manual.pdf', 100, 'application/pdf');

        $this->actingAs($user)
            ->post(route('manual.upload', ['category' => 'asm', 'access' => 'controlled']), [
                'files' => [$file],
            ])
            ->assertForbidden();
    }

    // =========================================================================
    // toggleStatus()
    // =========================================================================

    public function test_admin_can_toggle_file_status_from_active_to_obsolete(): void
    {
        $series = $this->makeSeries();
        $admin = $this->makeAdmin();
        $type = $this->makeType($series, 'ASM', 'controlled');
        $upload = $this->makeUpload($type, $admin, 'Active');

        $this->actingAs($admin)
            ->post(route('manual.uploads.toggle-status', $upload))
            ->assertRedirect();

        $this->assertDatabaseHas('document_uploads', [
            'id' => $upload->id,
            'status' => 'Obsolete',
        ]);
    }

    public function test_admin_can_toggle_file_status_from_obsolete_to_active(): void
    {
        $series = $this->makeSeries();
        $admin = $this->makeAdmin();
        $type = $this->makeType($series, 'ASM', 'controlled');
        $upload = $this->makeUpload($type, $admin, 'Obsolete');

        $this->actingAs($admin)
            ->post(route('manual.uploads.toggle-status', $upload))
            ->assertRedirect();

        $this->assertDatabaseHas('document_uploads', [
            'id' => $upload->id,
            'status' => 'Active',
        ]);
    }

    public function test_non_admin_cannot_toggle_status(): void
    {
        $series = $this->makeSeries();
        $admin = $this->makeAdmin();
        $type = $this->makeType($series, 'ASM', 'controlled');
        $upload = $this->makeUpload($type, $admin, 'Active');

        $user = $this->makeUser();

        $this->actingAs($user)
            ->post(route('manual.uploads.toggle-status', $upload))
            ->assertForbidden();
    }

    // =========================================================================
    // destroy()
    // =========================================================================

    public function test_admin_can_delete_file_and_it_is_removed_from_db(): void
    {
        Storage::fake('public');

        $series = $this->makeSeries();
        $admin = $this->makeAdmin();
        $type = $this->makeType($series, 'ASM', 'controlled');
        $upload = $this->makeUpload($type, $admin, 'Active');

        $this->actingAs($admin)
            ->delete(route('manual.uploads.destroy', $upload))
            ->assertRedirect();

        $this->assertDatabaseMissing('document_uploads', ['id' => $upload->id]);
    }

    public function test_non_admin_cannot_delete_file(): void
    {
        Storage::fake('public');

        $series = $this->makeSeries();
        $admin = $this->makeAdmin();
        $type = $this->makeType($series, 'ASM', 'controlled');
        $upload = $this->makeUpload($type, $admin, 'Active');

        $user = $this->makeUser();

        $this->actingAs($user)
            ->delete(route('manual.uploads.destroy', $upload))
            ->assertForbidden();

        $this->assertDatabaseHas('document_uploads', ['id' => $upload->id]);
    }

    // =========================================================================
    // Seeder
    // =========================================================================

    public function test_seeder_produces_exactly_15_manual_document_type_rows(): void
    {
        $this->makeSeries();

        $this->seed(ManualDocumentTypesSeeder::class);

        $this->assertDatabaseCount('document_types', 15);
    }
}
