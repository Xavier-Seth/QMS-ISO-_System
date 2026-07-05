<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\CarRecord;
use App\Models\DcrRecord;
use App\Models\OfiRecord;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DraftDeleteTest extends TestCase
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

    /**
     * @return array<string, array{class-string, string, string}>
     */
    private function modules(): array
    {
        return [
            'dcr' => [DcrRecord::class, 'dcr_no', 'dcr.records.destroy'],
            'ofi' => [OfiRecord::class, 'ofi_no', 'ofi.records.destroy'],
            'car' => [CarRecord::class, 'car_no', 'car.records.destroy'],
        ];
    }

    private function makeUser(string $username, string $role = 'user'): User
    {
        return User::factory()->create([
            'username' => $username,
            'role' => $role,
        ]);
    }

    private function makeRecord(
        string $model,
        string $noColumn,
        User $creator,
        string $status = 'draft',
        ?string $workflowStatus = null
    ) {
        return $model::query()->create([
            $noColumn => strtoupper(class_basename($model)).'-DEL-'.uniqid(),
            'status' => $status,
            'workflow_status' => $workflowStatus,
            'data' => [],
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
        ]);
    }

    public function test_owner_can_delete_own_open_draft_in_each_module(): void
    {
        $user = $this->makeUser('draftdeleteowner');

        foreach ($this->modules() as $module => [$model, $noColumn, $routeName]) {
            $record = $this->makeRecord($model, $noColumn, $user);

            $response = $this
                ->actingAs($user)
                ->from('/dashboard')
                ->delete(route($routeName, $record));

            $response->assertRedirect('/dashboard');
            $response->assertSessionHas('success');

            $this->assertNull($model::query()->find($record->id), "Expected {$module} draft to be deleted.");
            $this->assertSame(1, ActivityLog::query()
                ->where('module', $module)
                ->where('action', 'deleted')
                ->count(), "Expected exactly one deleted log entry for {$module}.");
        }
    }

    public function test_cannot_delete_another_users_open_draft(): void
    {
        $creator = $this->makeUser('draftdeletecreator');
        $intruder = $this->makeUser('draftdeleteintruder');

        foreach ($this->modules() as $module => [$model, $noColumn, $routeName]) {
            $record = $this->makeRecord($model, $noColumn, $creator);

            $this->actingAs($intruder)
                ->delete(route($routeName, $record))
                ->assertStatus(403);

            $this->assertNotNull($model::query()->find($record->id), "Expected {$module} draft to survive.");
        }

        $this->assertSame(0, ActivityLog::query()->where('action', 'deleted')->count());
    }

    public function test_admin_cannot_delete_another_users_open_draft(): void
    {
        $creator = $this->makeUser('draftdeletestaff');
        $admin = $this->makeUser('draftdeleteadmin', 'admin');

        foreach ($this->modules() as $module => [$model, $noColumn, $routeName]) {
            $record = $this->makeRecord($model, $noColumn, $creator);

            $this->actingAs($admin)
                ->delete(route($routeName, $record))
                ->assertStatus(403);

            $this->assertNotNull($model::query()->find($record->id), "Expected {$module} draft to survive admin delete.");
        }
    }

    public function test_cannot_delete_records_with_workflow_history(): void
    {
        $user = $this->makeUser('draftdeletestates');

        $blockedStates = [
            ['submitted', 'pending'],
            ['submitted', 'approved'],
            ['submitted', 'rejected'],
        ];

        foreach ($this->modules() as $module => [$model, $noColumn, $routeName]) {
            foreach ($blockedStates as [$status, $workflowStatus]) {
                $record = $this->makeRecord($model, $noColumn, $user, $status, $workflowStatus);

                $response = $this
                    ->actingAs($user)
                    ->from('/dashboard')
                    ->delete(route($routeName, $record));

                $response->assertRedirect('/dashboard');
                $response->assertSessionHas('error', 'Only open drafts can be deleted.');

                $this->assertNotNull(
                    $model::query()->find($record->id),
                    "Expected {$module} {$workflowStatus} record to survive."
                );
            }
        }

        $this->assertSame(0, ActivityLog::query()->where('action', 'deleted')->count());
    }

    public function test_deleting_missing_record_returns_404(): void
    {
        $user = $this->makeUser('draftdeletemissing');

        foreach ($this->modules() as [$model, $noColumn, $routeName]) {
            $this->actingAs($user)
                ->delete(route($routeName, 999999))
                ->assertNotFound();
        }
    }
}
