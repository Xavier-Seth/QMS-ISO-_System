<?php

namespace App\Listeners;

use App\Services\ActivityLogService;
use Illuminate\Auth\Events\Logout;

class LogSuccessfulLogout
{
    public function __construct(
        protected ActivityLogService $activityLogService
    ) {
    }

    public function handle(Logout $event): void
    {
        $user = $event->user;

        $this->activityLogService->log([
            'user' => $user,
            'module' => 'auth',
            'action' => 'logout',
            'entity_type' => $user ? get_class($user) : null,
            'entity_id' => $user?->id,
            'record_label' => $user?->name,
            'description' => 'User logged out',
        ]);
    }
}