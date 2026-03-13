<?php

namespace App\Listeners;

use App\Services\ActivityLogService;
use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
{
    public function __construct(
        protected ActivityLogService $activityLogService
    ) {
    }

    public function handle(Login $event): void
    {
        $this->activityLogService->log([
            'user' => $event->user,
            'module' => 'auth',
            'action' => 'login',
            'entity_type' => get_class($event->user),
            'entity_id' => $event->user->id,
            'record_label' => $event->user->name,
            'description' => 'User logged in',
        ]);
    }
}