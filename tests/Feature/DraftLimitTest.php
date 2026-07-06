<?php

namespace Tests\Feature;

use App\Models\DcrRecord;
use App\Models\DocumentSeries;
use App\Models\DocumentType;
use App\Models\OfiRecord;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DraftLimitTest extends TestCase
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

        foreach (['dcr_records' => 'dcr_no', 'ofi_records' => 'ofi_no', 'car_records' => 'car_no'] as $tableName => $noColumn) {
            Schema::create($tableName, function (Blueprint $table) use ($tableName, $noColumn) {
                $table->id();
                $table->foreignId('document_type_id')->nullable()->constrained('document_types')->nullOnDelete();
                $table->string($noColumn)->nullable();

                if ($tableName === 'dcr_records') {
                    $table->string('to_for')->nullable();
                    $table->string('from')->nullable();
                }

                if ($tableName === 'ofi_records') {
                    $table->string('ref_no')->nullable();
                    $table->string('to')->nullable();
                }

                if ($tableName === 'car_records') {
                    $table->string('ref_no')->nullable();
                    $table->string('dept_section')->nullable();
                    $table->string('auditor')->nullable();
                }

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
        }

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

        $series = DocumentSeries::query()->create([
            'code_prefix' => 'R-QMS',
            'name' => 'Records',
        ]);

        foreach (['R-QMS-013' => 'DCR Records', 'R-QMS-017' => 'CAR Records', 'R-QMS-018' => 'OFI Records'] as $code => $title) {
            DocumentType::query()->create([
                'series_id' => $series->id,
                'code' => $code,
                'title' => $title,
                'storage' => 'Electronic',
                'status' => 'active',
            ]);
        }
    }

    private function makeUser(string $username, string $role = 'user'): User
    {
        return User::factory()->create([
            'username' => $username,
            'role' => $role,
        ]);
    }

    private function makeOpenDcrDrafts(User $user, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            DcrRecord::query()->create([
                'dcr_no' => "DCR-LIMIT-{$i}",
                'status' => 'draft',
                'workflow_status' => null,
                'data' => [],
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        }
    }

    public function test_fourth_open_dcr_draft_is_blocked_for_non_admin(): void
    {
        $user = $this->makeUser('draftlimituser');
        $this->makeOpenDcrDrafts($user, 3);

        $response = $this
            ->actingAs($user)
            ->postJson(route('dcr.records.store'), ['dcrNo' => 'DCR-LIMIT-4']);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'You already have 3 open DCR drafts. Please submit or delete one before creating a new one.');

        $this->assertSame(3, DcrRecord::query()->count());
    }

    public function test_cap_is_counted_per_module(): void
    {
        $user = $this->makeUser('draftpermodule');
        $this->makeOpenDcrDrafts($user, 3);

        $response = $this
            ->actingAs($user)
            ->postJson(route('ofi.records.store'), ['ofiNo' => 'OFI-LIMIT-1']);

        $response->assertOk();
        $this->assertSame(1, OfiRecord::query()->count());
    }

    public function test_admin_store_creates_open_draft_not_approved(): void
    {
        $admin = $this->makeUser('draftadminshape', 'admin');

        $response = $this
            ->actingAs($admin)
            ->postJson(route('dcr.records.store'), ['dcrNo' => 'DCR-ADMIN-SHAPE']);

        $response->assertOk();
        $response->assertJsonPath('status', 'draft');
        $response->assertJsonPath('workflow_status', null);

        $record = DcrRecord::query()->firstOrFail();
        $this->assertSame('draft', $record->status);
        $this->assertNull($record->workflow_status);
    }

    public function test_admin_is_also_capped_at_store(): void
    {
        $admin = $this->makeUser('draftlimitadmin', 'admin');
        $this->makeOpenDcrDrafts($admin, 3);

        $response = $this
            ->actingAs($admin)
            ->postJson(route('dcr.records.store'), ['dcrNo' => 'DCR-ADMIN-4']);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'You already have 3 open DCR drafts. Please submit or delete one before creating a new one.');
        $this->assertSame(3, DcrRecord::query()->count());
    }

    public function test_non_open_records_do_not_count_toward_the_cap(): void
    {
        $user = $this->makeUser('draftstatuses');
        $this->makeOpenDcrDrafts($user, 2);

        DcrRecord::query()->create([
            'dcr_no' => 'DCR-PENDING',
            'status' => 'submitted',
            'workflow_status' => 'pending',
            'data' => [],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        DcrRecord::query()->create([
            'dcr_no' => 'DCR-REJECTED',
            'status' => 'submitted',
            'workflow_status' => 'rejected',
            'data' => [],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        DcrRecord::query()->create([
            'dcr_no' => 'DCR-ADMIN-APPROVED-SHAPE',
            'status' => 'draft',
            'workflow_status' => 'approved',
            'data' => [],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(route('dcr.records.store'), ['dcrNo' => 'DCR-THIRD-OPEN']);

        $response->assertOk();
        $this->assertSame(3, DcrRecord::query()
            ->where('status', 'draft')
            ->whereNull('workflow_status')
            ->count());
    }

    public function test_form_entry_with_existing_record_is_allowed_at_cap(): void
    {
        $user = $this->makeUser('draftcontinue');
        $this->makeOpenDcrDrafts($user, 3);

        $record = DcrRecord::query()->where('created_by', $user->id)->firstOrFail();

        $this->actingAs($user)
            ->get(route('dcr', ['record' => $record->id]))
            ->assertOk();
    }

    public function test_updating_existing_draft_is_allowed_at_cap(): void
    {
        $user = $this->makeUser('draftupdateatcap');
        $this->makeOpenDcrDrafts($user, 3);

        $record = DcrRecord::query()->where('created_by', $user->id)->firstOrFail();

        $response = $this
            ->actingAs($user)
            ->putJson(route('dcr.records.update', $record), [
                'dcrNo' => 'DCR-LIMIT-1-EDITED',
                'status' => 'draft',
            ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);

        $record->refresh();
        $this->assertSame('DCR-LIMIT-1-EDITED', $record->dcr_no);
        $this->assertSame('draft', $record->status);
        $this->assertNull($record->workflow_status);
    }

    public function test_form_entry_redirects_to_dashboard_at_cap(): void
    {
        $user = $this->makeUser('draftformentry');
        $this->makeOpenDcrDrafts($user, 3);

        $response = $this
            ->actingAs($user)
            ->get(route('dcr'));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error', 'You already have 3 open DCR drafts. Please submit or delete one before creating a new one.');
    }

    public function test_form_entry_is_allowed_below_cap(): void
    {
        $user = $this->makeUser('draftformentryok');
        $this->makeOpenDcrDrafts($user, 2);

        $this->actingAs($user)
            ->get(route('dcr'))
            ->assertOk();
    }

    public function test_form_entry_cap_is_per_module(): void
    {
        $user = $this->makeUser('draftformpermodule');
        $this->makeOpenDcrDrafts($user, 3);

        $this->actingAs($user)
            ->get(route('ofi.form'))
            ->assertOk();
    }

    public function test_admin_form_entry_is_also_blocked_at_cap(): void
    {
        $admin = $this->makeUser('draftformadmin', 'admin');
        $this->makeOpenDcrDrafts($admin, 3);

        $response = $this
            ->actingAs($admin)
            ->get(route('dcr'));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error', 'You already have 3 open DCR drafts. Please submit or delete one before creating a new one.');
    }

    public function test_published_records_do_not_count_toward_admin_cap(): void
    {
        $admin = $this->makeUser('draftadminpublished', 'admin');

        for ($i = 1; $i <= 3; $i++) {
            DcrRecord::query()->create([
                'dcr_no' => "DCR-ADMIN-PUBLISHED-{$i}",
                'status' => 'submitted',
                'workflow_status' => 'approved',
                'data' => [],
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);
        }

        $response = $this
            ->actingAs($admin)
            ->postJson(route('dcr.records.store'), ['dcrNo' => 'DCR-ADMIN-NEW']);

        $response->assertOk();
        $this->assertSame(4, DcrRecord::query()->count());

        $this->actingAs($admin)
            ->get(route('dcr'))
            ->assertOk();
    }
}
