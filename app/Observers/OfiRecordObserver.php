<?php

namespace App\Observers;

use App\Models\OfiRecord;
use App\Models\User;
use App\Services\ActivityLogService;

class OfiRecordObserver
{
    public function __construct(
        protected ActivityLogService $activityLogService
    ) {
    }

    public function created(OfiRecord $ofiRecord): void
    {
        $this->activityLogService->logModelEvent(
            module: 'ofi',
            action: 'created',
            model: $ofiRecord,
            recordLabel: $this->recordLabel($ofiRecord),
            description: 'Created OFI record ' . $this->recordLabel($ofiRecord),
            newValues: [
                'ofi_no' => $ofiRecord->ofi_no,
                'ref_no' => $ofiRecord->ref_no,
                'to' => $ofiRecord->to,
                'status' => $ofiRecord->status,
                'workflow_status' => $ofiRecord->workflow_status,
                'resolution_status' => $ofiRecord->resolution_status,
            ],
            user: $this->resolveActor($ofiRecord)
        );
    }

    public function updated(OfiRecord $ofiRecord): void
    {
        $keys = [
            'ofi_no',
            'ref_no',
            'to',
            'status',
            'workflow_status',
            'resolution_status',
            'data',
        ];

        $changes = $this->activityLogService->onlyChanged(
            $ofiRecord->getOriginal(),
            $ofiRecord->getAttributes(),
            $keys
        );

        if (empty($changes)) {
            return;
        }

        $description = 'Updated OFI record ' . $this->recordLabel($ofiRecord);
        $action = 'updated';

        if (isset($changes['workflow_status'])) {
            $old = $changes['workflow_status']['old'] ?? null;
            $new = $changes['workflow_status']['new'] ?? null;

            if ($new === 'approved') {
                $action = 'approved';
                $description = 'Approved OFI record ' . $this->recordLabel($ofiRecord);
            } elseif ($new === 'rejected') {
                $action = 'rejected';
                $description = 'Rejected OFI record ' . $this->recordLabel($ofiRecord);
            } else {
                $action = 'workflow_status_changed';
                $description = 'Changed OFI workflow status of ' . $this->recordLabel($ofiRecord)
                    . ' from ' . $this->stringify($old)
                    . ' to ' . $this->stringify($new);
            }
        } elseif (isset($changes['resolution_status'])) {
            $description = 'Changed OFI resolution status of ' . $this->recordLabel($ofiRecord)
                . ' from ' . $this->stringify($changes['resolution_status']['old'] ?? null)
                . ' to ' . $this->stringify($changes['resolution_status']['new'] ?? null);
            $action = 'resolution_status_changed';
        } elseif (isset($changes['status'])) {
            $description = 'Changed OFI status of ' . $this->recordLabel($ofiRecord)
                . ' from ' . $this->stringify($changes['status']['old'] ?? null)
                . ' to ' . $this->stringify($changes['status']['new'] ?? null);
            $action = 'status_changed';
        }

        $this->activityLogService->logModelEvent(
            module: 'ofi',
            action: $action,
            model: $ofiRecord,
            recordLabel: $this->recordLabel($ofiRecord),
            description: $description,
            oldValues: $this->compactChanges($changes, 'old'),
            newValues: $this->compactChanges($changes, 'new'),
            user: $this->resolveActor($ofiRecord)
        );
    }

    public function deleted(OfiRecord $ofiRecord): void
    {
        $this->activityLogService->logModelEvent(
            module: 'ofi',
            action: 'deleted',
            model: $ofiRecord,
            recordLabel: $this->recordLabel($ofiRecord),
            description: 'Deleted OFI record ' . $this->recordLabel($ofiRecord),
            oldValues: [
                'ofi_no' => $ofiRecord->ofi_no,
                'status' => $ofiRecord->status,
                'workflow_status' => $ofiRecord->workflow_status,
                'resolution_status' => $ofiRecord->resolution_status,
            ],
            user: $this->resolveActor($ofiRecord)
        );
    }

    private function recordLabel(OfiRecord $ofiRecord): string
    {
        return $ofiRecord->ofi_no ?: 'OFI #' . $ofiRecord->id;
    }

    private function resolveActor(OfiRecord $ofiRecord): ?User
    {
        return auth()->user()
            ?: ($ofiRecord->updated_by ? User::find($ofiRecord->updated_by) : null)
            ?: ($ofiRecord->created_by ? User::find($ofiRecord->created_by) : null);
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