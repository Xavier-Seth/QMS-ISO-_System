<?php

namespace App\Observers;

use App\Models\DocumentUpload;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\Auth;

class DocumentUploadObserver
{
    public function __construct(
        protected ActivityLogService $activityLogService
    ) {
    }

    public function created(DocumentUpload $upload): void
    {
        $upload->loadMissing(['uploader', 'documentType.series', 'ofiRecord', 'dcrRecord']);

        [$module, $recordLabel, $description] = $this->buildCreatedMessage($upload);

        $this->activityLogService->logModelEvent(
            module: $module,
            action: 'uploaded',
            model: $upload,
            recordLabel: $recordLabel,
            description: $description,
            fileType: $this->resolveFileType($upload),
            user: $upload->uploader
        );
    }

    public function updated(DocumentUpload $upload): void
    {
        $upload->loadMissing(['uploader', 'documentType.series', 'ofiRecord', 'dcrRecord']);

        $important = [
            'document_type_id',
            'revision',
            'status',
            'file_name',
            'file_path',
            'remarks',
        ];

        $changes = $this->activityLogService->onlyChanged(
            $upload->getOriginal(),
            $upload->getAttributes(),
            $important
        );

        if (empty($changes)) {
            return;
        }

        $recordLabel = $this->resolveRecordLabel($upload);
        $module = $this->resolveModule($upload);
        $description = $this->buildUpdatedDescription($upload, $changes);

        $action = 'updated';

        if (isset($changes['revision'])) {
            $action = 'revision_changed';
        } elseif (isset($changes['status'])) {
            $action = 'status_changed';
        } elseif (isset($changes['file_name']) || isset($changes['file_path'])) {
            $action = 'replaced';
        }

        $this->activityLogService->logModelEvent(
            module: $module,
            action: $action,
            model: $upload,
            recordLabel: $recordLabel,
            description: $description,
            fileType: $this->resolveFileType($upload),
            oldValues: $this->compactChanges($changes, 'old'),
            newValues: $this->compactChanges($changes, 'new'),
            user: Auth::user() ?? $upload->uploader
        );
    }

    public function deleted(DocumentUpload $upload): void
    {
        $upload->loadMissing(['uploader', 'documentType.series', 'ofiRecord', 'dcrRecord']);

        $this->activityLogService->logModelEvent(
            module: $this->resolveModule($upload),
            action: 'deleted',
            model: $upload,
            recordLabel: $this->resolveRecordLabel($upload),
            description: 'Deleted upload ' . $this->resolveRecordLabel($upload),
            fileType: $this->resolveFileType($upload),
            oldValues: [
                'file_name' => $upload->file_name,
                'revision' => $upload->revision,
                'status' => $upload->status,
            ],
            user: Auth::user() ?? $upload->uploader
        );
    }

    private function buildCreatedMessage(DocumentUpload $upload): array
    {
        if ($upload->ofi_record_id && $upload->ofiRecord) {
            $record = $upload->ofiRecord->ofi_no ?: 'OFI #' . $upload->ofiRecord->id;

            return [
                'ofi',
                $record,
                "Published OFI record {$record} as document {$upload->file_name}",
            ];
        }

        if ($upload->dcr_record_id && $upload->dcrRecord) {
            $record = $upload->dcrRecord->dcr_no ?: 'DCR #' . $upload->dcrRecord->id;

            return [
                'dcr',
                $record,
                "Published DCR record {$record} as document {$upload->file_name}",
            ];
        }

        if ($this->isManual($upload)) {
            $label = $this->resolveRecordLabel($upload);
            $revision = $upload->revision ? " revision {$upload->revision}" : '';

            return [
                'manuals',
                $label,
                "Uploaded manual {$label}{$revision}",
            ];
        }

        $label = $this->resolveRecordLabel($upload);
        $revision = $upload->revision ? " revision {$upload->revision}" : '';

        return [
            'documents',
            $label,
            "Uploaded document {$label}{$revision}",
        ];
    }

    private function buildUpdatedDescription(DocumentUpload $upload, array $changes): string
    {
        $label = $this->resolveRecordLabel($upload);

        if (isset($changes['revision'])) {
            return "Changed revision of {$label} from {$this->stringify($changes['revision']['old'])} to {$this->stringify($changes['revision']['new'])}";
        }

        if (isset($changes['status'])) {
            return "Changed status of {$label} from {$this->stringify($changes['status']['old'])} to {$this->stringify($changes['status']['new'])}";
        }

        if (isset($changes['file_name']) || isset($changes['file_path'])) {
            return "Replaced file for {$label}";
        }

        return "Updated upload metadata for {$label}";
    }

    private function resolveModule(DocumentUpload $upload): string
    {
        if ($upload->ofi_record_id) {
            return 'ofi';
        }

        if ($upload->dcr_record_id) {
            return 'dcr';
        }

        return $this->isManual($upload) ? 'manuals' : 'documents';
    }

    private function resolveRecordLabel(DocumentUpload $upload): string
    {
        if ($upload->ofi_record_id && $upload->ofiRecord) {
            return $upload->ofiRecord->ofi_no ?: 'OFI #' . $upload->ofiRecord->id;
        }

        if ($upload->dcr_record_id && $upload->dcrRecord) {
            return $upload->dcrRecord->dcr_no ?: 'DCR #' . $upload->dcrRecord->id;
        }

        return $upload->documentType?->code
            ?: $upload->file_name
            ?: 'Upload #' . $upload->id;
    }

    private function resolveFileType(DocumentUpload $upload): ?string
    {
        return $this->activityLogService->extensionFromFileName($upload->file_name)
            ?: $upload->documentType?->storage;
    }

    private function isManual(DocumentUpload $upload): bool
    {
        $type = $upload->documentType;

        if (!$type) {
            return false;
        }

        if (method_exists($type, 'isManual')) {
            return (bool) $type->isManual();
        }

        return strtoupper((string) $type->series?->code_prefix) === 'MANUAL';
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