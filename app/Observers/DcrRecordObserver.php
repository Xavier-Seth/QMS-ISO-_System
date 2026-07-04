<?php

namespace App\Observers;

use App\Models\DcrRecord;
use App\Models\User;
use App\Services\ActivityLogService;

class DcrRecordObserver
{
    public function __construct(
        protected ActivityLogService $activityLogService
    ) {}

    /**
     * Draft activity (record creation and edits, including the raw status
     * field flips) is intentionally not audit-logged. Formal actions —
     * submitted/approved/rejected/published/resolution changes — are logged
     * once each by DcrRecordController.
     */
    public function deleted(DcrRecord $dcrRecord): void
    {
        $this->activityLogService->logModelEvent(
            module: 'dcr',
            action: 'deleted',
            model: $dcrRecord,
            recordLabel: $this->recordLabel($dcrRecord),
            description: 'Deleted DCR record '.$this->recordLabel($dcrRecord),
            oldValues: [
                'dcr_no' => $dcrRecord->dcr_no,
                'status' => $dcrRecord->status,
            ],
            user: $this->resolveActor($dcrRecord)
        );
    }

    private function recordLabel(DcrRecord $dcrRecord): string
    {
        return $dcrRecord->dcr_no ?: 'DCR #'.$dcrRecord->id;
    }

    private function resolveActor(DcrRecord $dcrRecord): ?User
    {
        return auth()->user()
            ?: ($dcrRecord->updated_by ? User::find($dcrRecord->updated_by) : null)
            ?: ($dcrRecord->created_by ? User::find($dcrRecord->created_by) : null);
    }
}
