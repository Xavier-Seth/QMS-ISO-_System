<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ActivityLogService
{
    public function log(array $data): ?ActivityLog
    {
        try {
            /** @var User|null $user */
            $user = $data['user'] ?? Auth::user();

            /** @var Request|null $request */
            $request = $data['request'] ?? request();

            return ActivityLog::create([
                'user_id' => $user?->id,
                'user_name' => $data['user_name'] ?? $user?->name,
                'department' => $data['department'] ?? $user?->department,
                'office_location' => $data['office_location'] ?? $user?->office_location,
                'module' => $data['module'],
                'action' => $data['action'],
                'entity_type' => $data['entity_type'] ?? null,
                'entity_id' => $data['entity_id'] ?? null,
                'record_label' => $data['record_label'] ?? null,
                'file_type' => $data['file_type'] ?? null,
                'description' => $data['description'],
                'old_values' => $this->normalizeJsonData($data['old_values'] ?? null),
                'new_values' => $this->normalizeJsonData($data['new_values'] ?? null),
                'ip_address' => $data['ip_address'] ?? $request?->ip(),
                'user_agent' => $data['user_agent'] ?? $request?->userAgent(),
                'created_at' => $data['created_at'] ?? now(),
            ]);
        } catch (Throwable $e) {
            report($e);
            return null;
        }
    }

    public function logModelEvent(
        string $module,
        string $action,
        Model $model,
        string $recordLabel,
        string $description,
        ?string $fileType = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?User $user = null
    ): ?ActivityLog {
        return $this->log([
            'user' => $user,
            'module' => $module,
            'action' => $action,
            'entity_type' => $model::class,
            'entity_id' => $model->getKey(),
            'record_label' => $recordLabel,
            'file_type' => $fileType,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    public function onlyChanged(array $old, array $new, array $keys): array
    {
        $changes = [];

        foreach ($keys as $key) {
            $oldValue = Arr::get($old, $key);
            $newValue = Arr::get($new, $key);

            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }

    public function extensionFromFileName(?string $fileName): ?string
    {
        if (!$fileName) {
            return null;
        }

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        return $ext !== '' ? $ext : null;
    }

    private function normalizeJsonData(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        return ['value' => $value];
    }
}