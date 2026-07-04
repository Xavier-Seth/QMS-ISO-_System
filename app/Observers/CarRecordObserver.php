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

    public function created(CarRecord $carRecord): void
    {
        $this->activityLogService->logModelEvent(
            module: 'car',
            action: 'created',
            model: $carRecord,
            recordLabel: $this->recordLabel($carRecord),
            description: 'Created CAR record '.$this->recordLabel($carRecord),
            newValues: [
                'car_no' => $carRecord->car_no,
                'ref_no' => $carRecord->ref_no,
                'dept_section' => $carRecord->dept_section,
                'auditor' => $carRecord->auditor,
                'status' => $carRecord->status,
            ],
            user: $this->resolveActor($carRecord)
        );
    }

    public function updated(CarRecord $carRecord): void
    {
        $keys = ['car_no', 'ref_no', 'dept_section', 'auditor', 'status', 'data'];
        $changes = $this->activityLogService->onlyChanged(
            $carRecord->getOriginal(),
            $carRecord->getAttributes(),
            $keys
        );

        if (empty($changes)) {
            return;
        }

        $description = 'Updated CAR record '.$this->recordLabel($carRecord);

        if (isset($changes['status'])) {
            $description = 'Changed CAR status of '.$this->recordLabel($carRecord)
                .' from '.$this->stringify($changes['status']['old'])
                .' to '.$this->stringify($changes['status']['new']);
        }

        $this->activityLogService->logModelEvent(
            module: 'car',
            action: isset($changes['status']) ? 'status_changed' : 'updated',
            model: $carRecord,
            recordLabel: $this->recordLabel($carRecord),
            description: $description,
            oldValues: $this->compactChanges($changes, 'old'),
            newValues: $this->compactChanges($changes, 'new'),
            user: $this->resolveActor($carRecord)
        );
    }

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
