<?php

namespace App\Observers;

use App\Models\DcrRecord;
use App\Models\User;
use App\Services\ActivityLogService;

class DcrRecordObserver
{
    public function __construct(
        protected ActivityLogService $activityLogService
    ) {
    }

    public function created(DcrRecord $dcrRecord): void
    {
        $this->activityLogService->logModelEvent(
            module: 'dcr',
            action: 'created',
            model: $dcrRecord,
            recordLabel: $this->recordLabel($dcrRecord),
            description: 'Created DCR record ' . $this->recordLabel($dcrRecord),
            newValues: [
                'dcr_no' => $dcrRecord->dcr_no,
                'to_for' => $dcrRecord->to_for,
                'from' => $dcrRecord->from,
                'status' => $dcrRecord->status,
            ],
            user: $this->resolveActor($dcrRecord)
        );
    }

    public function updated(DcrRecord $dcrRecord): void
    {
        $keys = ['dcr_no', 'to_for', 'from', 'status', 'data'];
        $changes = $this->activityLogService->onlyChanged(
            $dcrRecord->getOriginal(),
            $dcrRecord->getAttributes(),
            $keys
        );

        if (empty($changes)) {
            return;
        }

        $description = 'Updated DCR record ' . $this->recordLabel($dcrRecord);

        if (isset($changes['status'])) {
            $description = 'Changed DCR status of ' . $this->recordLabel($dcrRecord)
                . ' from ' . $this->stringify($changes['status']['old'])
                . ' to ' . $this->stringify($changes['status']['new']);
        }

        $this->activityLogService->logModelEvent(
            module: 'dcr',
            action: isset($changes['status']) ? 'status_changed' : 'updated',
            model: $dcrRecord,
            recordLabel: $this->recordLabel($dcrRecord),
            description: $description,
            oldValues: $this->compactChanges($changes, 'old'),
            newValues: $this->compactChanges($changes, 'new'),
            user: $this->resolveActor($dcrRecord)
        );
    }

    public function deleted(DcrRecord $dcrRecord): void
    {
        $this->activityLogService->logModelEvent(
            module: 'dcr',
            action: 'deleted',
            model: $dcrRecord,
            recordLabel: $this->recordLabel($dcrRecord),
            description: 'Deleted DCR record ' . $this->recordLabel($dcrRecord),
            oldValues: [
                'dcr_no' => $dcrRecord->dcr_no,
                'status' => $dcrRecord->status,
            ],
            user: $this->resolveActor($dcrRecord)
        );
    }

    private function recordLabel(DcrRecord $dcrRecord): string
    {
        return $dcrRecord->dcr_no ?: 'DCR #' . $dcrRecord->id;
    }

    private function resolveActor(DcrRecord $dcrRecord): ?User
    {
        return auth()->user()
            ?: ($dcrRecord->updated_by ? User::find($dcrRecord->updated_by) : null)
            ?: ($dcrRecord->created_by ? User::find($dcrRecord->created_by) : null);
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