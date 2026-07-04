<?php

namespace App\Observers;

use App\Models\CarRecord;
use App\Models\User;
use App\Services\ActivityLogService;

class CarRecordObserver
{
    public function __construct(
        protected ActivityLogService $activityLogService
    ) {}

    /**
     * Draft activity (record creation and edits, including the raw status
     * field flips) is intentionally not audit-logged. Formal actions —
     * submitted/approved/rejected/published/resolution changes — are logged
     * once each by CarRecordController.
     */
    public function deleted(CarRecord $carRecord): void
    {
        $this->activityLogService->logModelEvent(
            module: 'car',
            action: 'deleted',
            model: $carRecord,
            recordLabel: $this->recordLabel($carRecord),
            description: 'Deleted CAR record '.$this->recordLabel($carRecord),
            oldValues: [
                'car_no' => $carRecord->car_no,
                'status' => $carRecord->status,
            ],
            user: $this->resolveActor($carRecord)
        );
    }

    private function recordLabel(CarRecord $carRecord): string
    {
        return $carRecord->car_no ?: 'CAR #'.$carRecord->id;
    }

    private function resolveActor(CarRecord $carRecord): ?User
    {
        return auth()->user()
            ?: ($carRecord->updated_by ? User::find($carRecord->updated_by) : null)
            ?: ($carRecord->created_by ? User::find($carRecord->created_by) : null);
    }
}
