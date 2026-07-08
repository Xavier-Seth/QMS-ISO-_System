<?php

namespace Tests\Feature;

use App\Models\DcrRecord;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DcrResolutionStatusTest extends TestCase
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

        Schema::create('dcr_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_type_id')->nullable();
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

    private function createApprovedDcrRecord(User $admin, string $resolutionStatus = 'open'): DcrRecord
    {
        return DcrRecord::query()->create([
            'dcr_no' => 'DCR-RES-001',
            'status' => 'submitted',
            'workflow_status' => 'approved',
            'resolution_status' => $resolutionStatus,
            'data' => [],
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
    }

    public function test_admin_transitions_approved_dcr_open_to_ongoing_to_closed(): void
    {
        $admin = User::factory()->create(['username' => 'admindcrres1', 'role' => 'admin']);
        $record = $this->createApprovedDcrRecord($admin);

        $this->actingAs($admin)
            ->patchJson(route('dcr.records.resolution-status', $record), [
                'resolution_status' => 'ongoing',
            ])
            ->assertOk()
            ->assertJsonPath('resolution_status', 'ongoing');

        $this->assertDatabaseHas('dcr_records', [
            'id' => $record->id,
            'resolution_status' => 'ongoing',
        ]);

        $this->actingAs($admin)
            ->patchJson(route('dcr.records.resolution-status', $record), [
                'resolution_status' => 'closed',
            ])
            ->assertOk()
            ->assertJsonPath('resolution_status', 'closed');

        $this->assertDatabaseHas('dcr_records', [
            'id' => $record->id,
            'resolution_status' => 'closed',
        ]);
    }

    public function test_closed_dcr_cannot_transition_back_to_open(): void
    {
        $admin = User::factory()->create(['username' => 'admindcrres2', 'role' => 'admin']);
        $record = $this->createApprovedDcrRecord($admin, 'closed');

        $this->actingAs($admin)
            ->patchJson(route('dcr.records.resolution-status', $record), [
                'resolution_status' => 'open',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('resolution_status');

        $this->assertDatabaseHas('dcr_records', [
            'id' => $record->id,
            'resolution_status' => 'closed',
        ]);
    }

    public function test_non_approved_dcr_rejects_resolution_update(): void
    {
        $admin = User::factory()->create(['username' => 'admindcrres3', 'role' => 'admin']);

        $record = DcrRecord::query()->create([
            'dcr_no' => 'DCR-RES-002',
            'status' => 'submitted',
            'workflow_status' => 'pending',
            'resolution_status' => 'open',
            'data' => [],
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patchJson(route('dcr.records.resolution-status', $record), [
                'resolution_status' => 'ongoing',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseHas('dcr_records', [
            'id' => $record->id,
            'resolution_status' => 'open',
        ]);
    }

    public function test_non_admin_cannot_update_resolution_status(): void
    {
        $admin = User::factory()->create(['username' => 'admindcrres4', 'role' => 'admin']);
        $user = User::factory()->create(['username' => 'userdcrres1', 'role' => 'user']);
        $record = $this->createApprovedDcrRecord($admin);

        $this->actingAs($user)
            ->patchJson(route('dcr.records.resolution-status', $record), [
                'resolution_status' => 'ongoing',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('dcr_records', [
            'id' => $record->id,
            'resolution_status' => 'open',
        ]);
    }
}
