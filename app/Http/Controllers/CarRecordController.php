<?php

namespace App\Http\Controllers;

use App\Models\CarRecord;
use App\Models\DocumentType;
use App\Models\DocumentUpload;
use App\Services\ActivityLogService;
use App\Services\CARFormGenerator;
use App\Services\QmsDynamicFieldValidator;
use App\Services\QmsTemplateResolver;
use App\Support\QmsTemplateModules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CarRecordController extends Controller
{
    public function __construct(
        protected ActivityLogService $activityLogService,
        protected QmsTemplateResolver $templateResolver,
        protected QmsDynamicFieldValidator $dynamicFieldValidator
    ) {
    }

    private function rQms017TypeId(): int
    {
        return DocumentType::where('code', 'R-QMS-017')->value('id')
            ?? abort(404, 'DocumentType R-QMS-017 not found.');
    }

    private function templatePath(): string
    {
        return $this->templateResolver->getActiveCarTemplatePath();
    }

    private function ensureTmpDir(): string
    {
        $tmpDir = storage_path('app/car_forms_tmp');

        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        return $tmpDir;
    }

    private function sanitizeBaseFileName(?string $raw, CarRecord $carRecord): string
    {
        $raw = trim((string) $raw);

        if ($raw !== '') {
            $raw = preg_replace('/\.docx$/i', '', $raw);
            $raw = preg_replace('/[^A-Za-z0-9 _\-\(\)]/', '', $raw);
            $raw = trim((string) preg_replace('/\s+/', ' ', $raw));
        }

        if ($raw !== '') {
            return $raw;
        }

        $fallbackBase = $carRecord->car_no
            ? preg_replace('/[^A-Za-z0-9_\-]/', '_', $carRecord->car_no)
            : now()->format('Ymd_His');

        return "CAR_{$fallbackBase}";
    }

    private function generateDocxToPath(CarRecord $carRecord, string $outputPath): void
    {
        $data = is_array($carRecord->data) ? $carRecord->data : [];

        $this->dynamicFieldValidator->validateRequiredFields(
            QmsTemplateModules::CAR,
            $data
        );

        $generator = new CARFormGenerator($this->templatePath());
        $generator->generate($data, $outputPath);
    }

    private function isAdminUser(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    private function canManageRecord(CarRecord $carRecord): bool
    {
        if ($this->isAdminUser()) {
            return true;
        }

        return (int) $carRecord->created_by === (int) auth()->id();
    }

    private function canEditRecordContent(CarRecord $carRecord): bool
    {
        if ($this->isAdminUser()) {
            return true;
        }

        if ((int) $carRecord->created_by !== (int) auth()->id()) {
            return false;
        }

        if (in_array($carRecord->workflow_status, ['pending', 'approved'], true)) {
            return false;
        }

        return $carRecord->status === 'draft'
            || $carRecord->workflow_status === 'rejected'
            || $carRecord->workflow_status === null;
    }

    private function ensureCanManageRecord(CarRecord $carRecord): void
    {
        abort_unless(
            $this->canManageRecord($carRecord),
            403,
            'You are not allowed to access this CAR record.'
        );
    }

    private function ensureCanEditRecordContent(CarRecord $carRecord): void
    {
        abort_unless(
            $this->canEditRecordContent($carRecord),
            403,
            'This CAR record can no longer be edited. Pending and approved records are locked.'
        );
    }

    private function resolveSafeStatusForSave(CarRecord $carRecord, ?string $requestedStatus): string
    {
        $currentStatus = $carRecord->status ?: 'draft';
        $requestedStatus = trim((string) $requestedStatus);

        $allowedStatuses = ['draft', 'submitted'];

        if ($requestedStatus === '') {
            $requestedStatus = 'draft';
        }

        if (!in_array($requestedStatus, $allowedStatuses, true)) {
            return $currentStatus;
        }

        if ($requestedStatus === 'draft') {
            if (
                in_array($carRecord->workflow_status, ['pending', 'approved'], true)
                || $currentStatus === 'submitted'
            ) {
                return 'submitted';
            }

            return 'draft';
        }

        if ($requestedStatus === 'submitted') {
            return 'submitted';
        }

        return $currentStatus;
    }

    private function publishRecordDocument(
        CarRecord $carRecord,
        ?string $requestedFileName = null,
        ?string $remarks = null
    ): DocumentUpload {
        $existingUpload = DocumentUpload::query()
            ->where('car_record_id', $carRecord->id)
            ->orderByDesc('id')
            ->first();

        $tmpDir = $this->ensureTmpDir();
        $storageDisk = 'private';
        $disk = Storage::disk($storageDisk);

        if ($existingUpload) {
            $fileName = $existingUpload->file_name
                ?: ($this->sanitizeBaseFileName($requestedFileName, $carRecord) . '.docx');

            $tmpPath = $tmpDir . '/' . uniqid('car_republish_', true) . '_' . $fileName;
            $this->generateDocxToPath($carRecord, $tmpPath);

            $oldDisk = $existingUpload->getStorageDiskName();
            $oldPath = $existingUpload->file_path;
            $storedPath = $existingUpload->file_path ?: ('documents/car/' . $fileName);

            $written = $disk->put($storedPath, file_get_contents($tmpPath));

            if ($written === false) {
                @unlink($tmpPath);
                throw new \RuntimeException("Failed to write file to storage: {$storedPath}");
            }

            if ($existingUpload->hasPreviewCache()) {
                $previewDisk = $existingUpload->getPreviewDiskName();

                if (
                    $previewDisk &&
                    $existingUpload->preview_path &&
                    Storage::disk($previewDisk)->exists($existingUpload->preview_path)
                ) {
                    Storage::disk($previewDisk)->delete($existingUpload->preview_path);
                }

                $existingUpload->clearPreviewCacheMeta();
            }

            $existingUpload->update([
                'document_type_id' => $this->rQms017TypeId(),
                'uploaded_by' => auth()->id(),
                'file_name' => $fileName,
                'file_path' => $storedPath,
                'storage_disk' => $storageDisk,
                'remarks' => $remarks ?? $existingUpload->remarks,
            ]);

            @unlink($tmpPath);

            if (
                $oldPath &&
                ($oldDisk !== $storageDisk || $oldPath !== $storedPath) &&
                Storage::disk($oldDisk)->exists($oldPath)
            ) {
                Storage::disk($oldDisk)->delete($oldPath);
            }

            return $existingUpload->fresh();
        }

        $baseName = $this->sanitizeBaseFileName($requestedFileName, $carRecord);
        $fileName = "{$baseName}.docx";
        $storedPath = 'documents/car/' . $fileName;

        if ($disk->exists($storedPath)) {
            $fileName = "{$baseName}_" . now()->format('His') . '.docx';
            $storedPath = 'documents/car/' . $fileName;
        }

        $tmpPath = $tmpDir . '/' . uniqid('car_publish_', true) . '_' . $fileName;
        $this->generateDocxToPath($carRecord, $tmpPath);

        $written = $disk->put($storedPath, file_get_contents($tmpPath));

        if ($written === false) {
            @unlink($tmpPath);
            throw new \RuntimeException("Failed to write file to storage: {$storedPath}");
        }

        $upload = DocumentUpload::create([
            'document_type_id' => $this->rQms017TypeId(),
            'uploaded_by' => auth()->id(),
            'car_record_id' => $carRecord->id,
            'revision' => null,
            'status' => null,
            'file_name' => $fileName,
            'file_path' => $storedPath,
            'storage_disk' => $storageDisk,
            'remarks' => $remarks,
        ]);

        @unlink($tmpPath);

        return $upload;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'document_type_id' => ['required', 'exists:document_types,id'],
            'data' => ['nullable', 'array'],
            'status' => ['nullable', 'in:draft,submitted'],
        ]);

        $payload = (array) ($validated['data'] ?? []);
        $isAdmin = $this->isAdminUser();

        $record = CarRecord::create([
            'document_type_id' => $validated['document_type_id'],
            'car_no' => $payload['carNo'] ?? null,
            'ref_no' => $payload['refNo'] ?? null,
            'dept_section' => $payload['deptSection'] ?? null,
            'auditor' => $payload['auditor'] ?? null,
            'status' => 'draft',
            'workflow_status' => $isAdmin ? 'approved' : null,
            'resolution_status' => 'open',
            'data' => $payload,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'id' => $record->id,
            'status' => $record->status,
            'workflow_status' => $record->workflow_status,
            'resolution_status' => $record->resolution_status,
        ]);
    }

    public function updateResolutionStatus(Request $request, CarRecord $carRecord)
    {
        abort_unless($this->isAdminUser(), 403, 'Only admins can update CAR resolution status.');

        $validated = $request->validate([
            'resolution_status' => ['required', 'in:open,ongoing,closed'],
        ]);

        if ($carRecord->workflow_status !== 'approved') {
            return response()->json([
                'message' => 'Only approved CAR records can update resolution status.',
            ], 422);
        }

        $newStatus = $validated['resolution_status'];
        $currentStatus = $carRecord->resolution_status ?: 'open';

        $allowedTransitions = [
            'open' => ['open', 'ongoing', 'closed'],
            'ongoing' => ['ongoing', 'closed'],
            'closed' => ['closed'],
        ];

        if (!in_array($newStatus, $allowedTransitions[$currentStatus] ?? [], true)) {
            return response()->json([
                'message' => "Invalid resolution status transition from {$currentStatus} to {$newStatus}.",
            ], 422);
        }

        $carRecord->update([
            'resolution_status' => $newStatus,
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'CAR resolution status updated successfully.',
            'resolution_status' => $carRecord->resolution_status,
        ]);
    }

    public function show(CarRecord $carRecord)
    {
        $this->ensureCanManageRecord($carRecord);

        $carRecord->load([
            'creator:id,name,department',
            'rejectedBy:id,name',
        ]);

        return response()->json([
            'id' => $carRecord->id,
            'status' => $carRecord->status,
            'workflow_status' => $carRecord->workflow_status,
            'resolution_status' => $carRecord->resolution_status,
            'rejection_reason' => $carRecord->rejection_reason,
            'rejected_at' => $carRecord->rejected_at,
            'rejected_by_name' => $carRecord->rejectedBy?->name ?? null,
            'created_by_name' => $carRecord->creator?->name ?? '—',
            'created_by_department' => $carRecord->creator?->department ?? '—',
            'data' => $carRecord->data,
        ]);
    }

    public function update(Request $request, CarRecord $carRecord)
    {
        $this->ensureCanManageRecord($carRecord);
        $this->ensureCanEditRecordContent($carRecord);

        $validated = $request->validate([
            'data' => ['nullable', 'array'],
            'status' => ['nullable', 'in:draft,submitted'],
        ]);

        $payload = (array) ($validated['data'] ?? []);
        if ($payload === [] && is_array($carRecord->data)) {
            $payload = $carRecord->data;
        }

        $safeStatus = $this->resolveSafeStatusForSave($carRecord, $validated['status'] ?? null);

        if ($safeStatus === 'submitted') {
            $this->dynamicFieldValidator->validateRequiredFields(
                QmsTemplateModules::CAR,
                $payload
            );
        }

        $carRecord->update([
            'car_no' => $payload['carNo'] ?? $carRecord->car_no,
            'ref_no' => $payload['refNo'] ?? $carRecord->ref_no,
            'dept_section' => $payload['deptSection'] ?? $carRecord->dept_section,
            'auditor' => $payload['auditor'] ?? $carRecord->auditor,
            'status' => $safeStatus,
            'data' => $payload ?: $carRecord->data,
            'updated_by' => auth()->id(),
        ]);

        $fresh = $carRecord->fresh();

        return response()->json([
            'ok' => true,
            'status' => $fresh->status,
            'workflow_status' => $fresh->workflow_status,
            'resolution_status' => $fresh->resolution_status,
        ]);
    }

    public function submitForApproval(CarRecord $carRecord)
    {
        $this->ensureCanManageRecord($carRecord);

        if ($this->isAdminUser()) {
            return response()->json([
                'message' => 'Admin-created CAR records do not need inbox submission.',
            ], 422);
        }

        if (!$this->canEditRecordContent($carRecord)) {
            return response()->json([
                'message' => 'This CAR record can no longer be submitted from its current state.',
            ], 422);
        }

        if ($carRecord->workflow_status === 'approved') {
            return response()->json([
                'message' => 'This CAR record is already approved.',
            ], 422);
        }

        if ($carRecord->workflow_status === 'pending') {
            return response()->json([
                'message' => 'This CAR record is already submitted for approval.',
            ], 422);
        }

        $this->dynamicFieldValidator->validateRequiredFields(
            QmsTemplateModules::CAR,
            is_array($carRecord->data) ? $carRecord->data : []
        );

        $isResubmission = $carRecord->workflow_status === 'rejected';

        $carRecord->update([
            'status' => 'submitted',
            'workflow_status' => 'pending',
            'rejection_reason' => null,
            'rejected_at' => null,
            'rejected_by' => null,
            'updated_by' => auth()->id(),
        ]);

        $this->activityLogService->log([
            'module' => 'car',
            'action' => 'submitted',
            'entity_type' => CarRecord::class,
            'entity_id' => $carRecord->id,
            'record_label' => $carRecord->car_no ?: 'CAR #' . $carRecord->id,
            'file_type' => null,
            'description' => $isResubmission
                ? 'CAR corrected and resubmitted to admin.'
                : 'CAR submitted to admin for approval.',
        ]);

        return response()->json([
            'message' => $isResubmission
                ? 'CAR corrected and resubmitted to admin successfully.'
                : 'CAR submitted to admin for approval successfully.',
            'status' => $carRecord->status,
            'workflow_status' => $carRecord->workflow_status,
        ]);
    }

    public function download(CarRecord $carRecord)
    {
        $this->ensureCanManageRecord($carRecord);

        $tmpDir = $this->ensureTmpDir();

        $fileName = 'CAR_' . ($carRecord->car_no ?: now()->format('Ymd_His')) . '.docx';
        $outputPath = $tmpDir . '/' . uniqid('car_download_', true) . '_' . $fileName;

        $this->generateDocxToPath($carRecord, $outputPath);

        $this->activityLogService->log([
            'module' => 'car',
            'action' => 'downloaded',
            'entity_type' => CarRecord::class,
            'entity_id' => $carRecord->id,
            'record_label' => $carRecord->car_no ?: 'CAR #' . $carRecord->id,
            'file_type' => 'docx',
            'description' => 'Downloaded generated CAR form ' . ($carRecord->car_no ?: 'CAR #' . $carRecord->id),
        ]);

        return response()->download($outputPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    public function publish(Request $request, CarRecord $carRecord)
    {
        abort_unless($this->isAdminUser(), 403, 'Only admins can publish CAR records.');

        $data = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
            'file_name' => ['nullable', 'string', 'max:200'],
        ]);

        if ($request->has('data')) {
            $payload = (array) $request->input('data', []);

            if ($this->canEditRecordContent($carRecord)) {
                $this->dynamicFieldValidator->validateRequiredFields(
                    QmsTemplateModules::CAR,
                    $payload
                );

                $safeStatus = $this->resolveSafeStatusForSave(
                    $carRecord,
                    $request->input('status', $carRecord->status)
                );

                $carRecord->update([
                    'car_no' => $payload['carNo'] ?? $carRecord->car_no,
                    'ref_no' => $payload['refNo'] ?? $carRecord->ref_no,
                    'dept_section' => $payload['deptSection'] ?? $carRecord->dept_section,
                    'auditor' => $payload['auditor'] ?? $carRecord->auditor,
                    'status' => $safeStatus,
                    'data' => $payload ?: $carRecord->data,
                    'updated_by' => auth()->id(),
                ]);
            }
        }

        $carRecord = $carRecord->fresh();

        $this->dynamicFieldValidator->validateRequiredFields(
            QmsTemplateModules::CAR,
            is_array($carRecord->data) ? $carRecord->data : []
        );

        $upload = $this->publishRecordDocument(
            $carRecord,
            $data['file_name'] ?? null,
            $data['remarks'] ?? null
        );

        $this->activityLogService->log([
            'module' => 'car',
            'action' => 'published',
            'entity_type' => CarRecord::class,
            'entity_id' => $carRecord->id,
            'record_label' => $carRecord->car_no ?: 'CAR #' . $carRecord->id,
            'file_type' => 'docx',
            'description' => 'Published CAR record ' . ($carRecord->car_no ?: 'CAR #' . $carRecord->id) . ' as document ' . $upload->file_name,
            'new_values' => [
                'upload_id' => $upload->id,
                'file_name' => $upload->file_name,
                'file_path' => $upload->file_path,
                'remarks' => $upload->remarks,
            ],
        ]);

        return response()->json([
            'ok' => true,
            'upload_id' => $upload->id,
            'car_record_id' => $carRecord->id,
            'file_name' => $upload->file_name,
            'workflow_status' => $carRecord->workflow_status,
            'resolution_status' => $carRecord->resolution_status,
        ]);
    }

    public function approve(CarRecord $carRecord)
    {
        abort_unless($this->isAdminUser(), 403, 'Only admins can approve CAR records.');

        if ($carRecord->status !== 'submitted' || $carRecord->workflow_status !== 'pending') {
            return back()->with('error', 'Only submitted pending CAR records can be approved.');
        }

        $this->dynamicFieldValidator->validateRequiredFields(
            QmsTemplateModules::CAR,
            is_array($carRecord->data) ? $carRecord->data : []
        );

        DB::transaction(function () use ($carRecord) {
            $carRecord->update([
                'workflow_status' => 'approved',
                'resolution_status' => $carRecord->resolution_status ?: 'open',
                'rejection_reason' => null,
                'rejected_at' => null,
                'rejected_by' => null,
                'updated_by' => auth()->id(),
            ]);

            DB::afterCommit(function () use ($carRecord) {
                $fresh = $carRecord->fresh();

                $upload = $this->publishRecordDocument(
                    $fresh,
                    null,
                    'Auto-published after admin approval'
                );

                $fresh = $fresh->fresh();

                $this->activityLogService->log([
                    'module' => 'car',
                    'action' => 'approved',
                    'entity_type' => CarRecord::class,
                    'entity_id' => $fresh->id,
                    'record_label' => $fresh->car_no ?: 'CAR #' . $fresh->id,
                    'file_type' => 'docx',
                    'description' => 'Approved CAR and published document ' . $upload->file_name,
                    'new_values' => [
                        'workflow_status' => $fresh->workflow_status,
                        'resolution_status' => $fresh->resolution_status,
                        'upload_id' => $upload->id,
                    ],
                ]);
            });
        });

        return back()->with('success', 'CAR approved and published successfully.');
    }

    public function reject(Request $request, CarRecord $carRecord)
    {
        abort_unless($this->isAdminUser(), 403, 'Only admins can reject CAR records.');

        if ($carRecord->status !== 'submitted' || $carRecord->workflow_status !== 'pending') {
            return back()->with('error', 'Only submitted pending CAR records can be rejected.');
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ]);

        $carRecord->update([
            'workflow_status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'rejected_at' => now(),
            'rejected_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        $this->activityLogService->log([
            'module' => 'car',
            'action' => 'rejected',
            'entity_type' => CarRecord::class,
            'entity_id' => $carRecord->id,
            'record_label' => $carRecord->car_no ?: 'CAR #' . $carRecord->id,
            'file_type' => null,
            'description' => 'Rejected CAR and returned it for correction.',
            'new_values' => [
                'workflow_status' => 'rejected',
                'rejection_reason' => $validated['rejection_reason'],
            ],
        ]);

        return back()->with('success', 'CAR rejected and returned for correction.');
    }

    public function myRecords(Request $request)
    {
        $status = $request->input('workflow_status', 'all');
        $allowed = ['all', 'pending', 'approved', 'rejected'];

        if (!in_array($status, $allowed, true)) {
            $status = 'all';
        }

        $query = CarRecord::query()
            ->where('created_by', auth()->id());

        if ($status !== 'all') {
            $query->where('workflow_status', $status);
        }

        $records = $query
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn(CarRecord $record) => [
                'id' => $record->id,
                'car_no' => $record->car_no,
                'ref_no' => $record->ref_no,
                'dept_section' => $record->dept_section,
                'status' => $record->status,
                'workflow_status' => $record->workflow_status,
                'resolution_status' => $record->resolution_status,
                'rejection_reason' => $record->rejection_reason,
                'created_at' => $record->created_at,
            ]);

        $counts = [
            'all' => CarRecord::query()
                ->where('created_by', auth()->id())
                ->count(),
            'pending' => CarRecord::query()
                ->where('created_by', auth()->id())
                ->where('workflow_status', 'pending')
                ->count(),
            'approved' => CarRecord::query()
                ->where('created_by', auth()->id())
                ->where('workflow_status', 'approved')
                ->count(),
            'rejected' => CarRecord::query()
                ->where('created_by', auth()->id())
                ->where('workflow_status', 'rejected')
                ->count(),
        ];

        return \Inertia\Inertia::render('Inbox/MyCarRecords', [
            'records' => $records,
            'filters' => [
                'workflow_status' => $status,
            ],
            'counts' => $counts,
        ]);
    }
}
