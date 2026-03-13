<?php

namespace App\Observers;

use App\Models\User;
use App\Services\ActivityLogService;

class UserObserver
{
    public function __construct(
        protected ActivityLogService $activityLogService
    ) {
    }

    public function created(User $user): void
    {
        $this->activityLogService->logModelEvent(
            module: 'users',
            action: 'created',
            model: $user,
            recordLabel: $user->name,
            description: 'Created user ' . $user->name,
            newValues: [
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'department' => $user->department,
                'position' => $user->position,
            ]
        );
    }

    public function updated(User $user): void
    {
        $keys = [
            'name',
            'email',
            'role',
            'position',
            'department',
            'office_location',
            'profile_photo',
        ];

        $changes = $this->activityLogService->onlyChanged(
            $user->getOriginal(),
            $user->getAttributes(),
            $keys
        );

        if (empty($changes)) {
            return;
        }

        $description = 'Updated user ' . $user->name;

        if (isset($changes['role'])) {
            $description = 'Changed user role of ' . $user->name
                . ' from ' . $this->stringify($changes['role']['old'])
                . ' to ' . $this->stringify($changes['role']['new']);
        }

        $this->activityLogService->logModelEvent(
            module: 'users',
            action: isset($changes['role']) ? 'role_changed' : 'updated',
            model: $user,
            recordLabel: $user->name,
            description: $description,
            oldValues: $this->compactChanges($changes, 'old'),
            newValues: $this->compactChanges($changes, 'new')
        );
    }

    public function deleted(User $user): void
    {
        $this->activityLogService->logModelEvent(
            module: 'users',
            action: 'deleted',
            model: $user,
            recordLabel: $user->name,
            description: 'Deleted user ' . $user->name,
            oldValues: [
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'department' => $user->department,
            ]
        );
    }

    private function compactChanges(array $changes, string $side): array
    {
        $data = [];

        foreach ($changes as $field => $change) {
            $data[$field] = $change[$side] ?? null;
        }

        return $data;
    }

    private function stringify(mixed $value): string
    {
        return $value === null || $value === '' ? 'blank' : (string) $value;
    }
}