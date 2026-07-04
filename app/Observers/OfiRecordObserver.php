<?php

namespace App\Observers;

use App\Models\OfiRecord;
use App\Models\User;
use App\Services\ActivityLogService;

class OfiRecordObserver
{
    public function __construct(
        protected ActivityLogService $activityLogService
    ) {}

    /**
     * Draft activity (record creation and edits, including the raw status
     * field flips) is intentionally not audit-logged. Formal actions —
     * submitted/approved/rejected/published/resolution changes — are logged
     * once each by OfiRecordController.
     */
    public function deleted(OfiRecord $ofiRecord): void
    {
        $this->activityLogService->logModelEvent(
            module: 'ofi',
            action: 'deleted',
            model: $ofiRecord,
            recordLabel: $this->recordLabel($ofiRecord),
            description: 'Deleted OFI record '.$this->recordLabel($ofiRecord),
            oldValues: [
                'ofi_no' => $ofiRecord->ofi_no,
                'status' => $ofiRecord->status,
            ],
            user: $this->resolveActor($ofiRecord)
        );
    }

    private function recordLabel(OfiRecord $ofiRecord): string
    {
        return $ofiRecord->ofi_no ?: 'OFI #'.$ofiRecord->id;
    }

    private function resolveActor(OfiRecord $ofiRecord): ?User
    {
        return auth()->user()
            ?: ($ofiRecord->updated_by ? User::find($ofiRecord->updated_by) : null)
            ?: ($ofiRecord->created_by ? User::find($ofiRecord->created_by) : null);
    }
}
