<?php

namespace App\Http\Controllers;

use App\Models\DcrRecord;
use App\Models\DocumentType;
use App\Models\DocumentUpload;
use App\Services\ActivityLogService;
use App\Services\DcrDynamicFieldValidator;
use App\Services\DCRFormGenerator;
use App\Services\QmsTemplateResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DcrRecordController extends Controller
{
    public function __construct(
        protected ActivityLogService $activityLogService,
        protected QmsTemplateResolver $templateResolver,
        protected DcrDynamicFieldValidator $dynamicFieldValidator
    ) {}

    private function rQms013TypeId(): int
    {
        $id = DocumentType::where('code', 'R-QMS-013')->value('id');

        if (! $id) {
            abort(404, 'DocumentType R-QMS-013 not found.');
        }

        return (int) $id;
    }

    private function ensureTmpDir(): string
    {
        $tmpDir = storage_path('app/dcr_forms_tmp');

        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        return $tmpDir;
    }

    private function sanitizeBaseFileName(?string $raw, DcrRecord $dcrRecord): string
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

        $fallbackBase = $dcrRecord->dcr_no
            ? preg_replace('/[^A-Za-z0-9_\-]/', '_', $dcrRecord->dcr_no)
            : now()->format('Ymd_His');

        return "DCR_{$fallbackBase}";
    }

    private function generateDocxToPath(DcrRecord $dcrRecord, string $outputPath): void
    {
        $data = is_array($dcrRecord->data) ? $dcrRecord->data : [];

        $this->dynamicFieldValidator->validateRequiredFields($data);

        $templatePath = $this->templateResolver->getActiveDcrTemplatePath();

        $generator = new DCRFormGenerator($templatePath);
        $generator->generate($data, $outputPath);
    }

    private function publishRecordDocument(
        DcrRecord $dcrRecord,
        ?string $requestedFileName = null,
        ?string $remarks = null
    ): DocumentUpload {
        $existingUpload = DocumentUpload::query()
            ->where('dcr_record_id', $dcrRecord->id)
            ->orderByDesc('id')
            ->first();

        $tmpDir = $this->ensureTmpDir();
        $storageDisk = 'private';
        $disk = Storage::disk($storageDisk);

        if ($existingUpload) {
            $fileName = $existingUpload->file_name
                ?: ($this->sanitizeBaseFileName($requestedFileName, $dcrRecord).'.docx');

            $tmpPath = $tmpDir.'/'.uniqid('dcr_republish_', true).'_'.$fileName;
            $this->generateDocxToPath($dcrRecord, $tmpPath);

            $oldDisk = $existingUpload->getStorageDiskName();
            $oldPath = $existingUpload->file_path;
            $storedPath = $existingUpload->file_path ?: ('documents/dcr/'.$fileName);

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
                'document_type_id' => $this->rQms013TypeId(),
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

        $baseName = $this->sanitizeBaseFileName($requestedFileName, $dcrRecord);
        $fileName = "{$baseName}.docx";
        $storedPath = 'documents/dcr/'.$fileName;

        if ($disk->exists($storedPath)) {
            $fileName = "{$baseName}_".now()->format('His').'.docx';
            $storedPath = 'documents/dcr/'.$fileName;
        }

        $tmpPath = $tmpDir.'/'.uniqid('dcr_publish_', true).'_'.$fileName;
        $this->generateDocxToPath($dcrRecord, $tmpPath);

        $written = $disk->put($storedPath, file_get_contents($tmpPath));

        if ($written === false) {
            @unlink($tmpPath);
            throw new \RuntimeException("Failed to write file to storage: {$storedPath}");
        }

        $upload = DocumentUpload::create([
            'document_type_id' => $this->rQms013TypeId(),
            'uploaded_by' => auth()->id(),
            'dcr_record_id' => $dcrRecord->id,
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

    private function syncPublishedUploads(DcrRecord $dcrRecord): void
    {
        /** @var Collection<int, DocumentUpload> $uploads */
        $uploads = DocumentUpload::query()
            ->where('dcr_record_id', $dcrRecord->id)
            ->get();

        if ($uploads->isEmpty()) {
            return;
        }

        $storageDisk = 'private';
        $disk = Storage::disk($storageDisk);
        $tmpDir = $this->ensureTmpDir();

        /** @var DocumentUpload $upload */
        foreach ($uploads as $upload) {
            $fileName = $upload->file_name ?: ('DCR_'.($dcrRecord->dcr_no ?: $dcrRecord->id).'.docx');
            $tmpPath = $tmpDir.'/'.uniqid('dcr_sync_', true).'_'.$fileName;

            $this->generateDocxToPath($dcrRecord, $tmpPath);

            $storedPath = $upload->file_path ?: ('documents/dcr/'.$fileName);
            $disk->put($storedPath, file_get_contents($tmpPath));

            $upload->update([
                'file_name' => $fileName,
                'file_path' => $storedPath,
                'storage_disk' => $storageDisk,
            ]);

            @unlink($tmpPath);
        }
    }

    private function isAdminUser(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    private function canManageRecord(DcrRecord $dcrRecord): bool
    {
        if ($this->isAdminUser()) {
            return true;
        }

        return (int) $dcrRecord->created_by === (int) auth()->id();
    }

    private function canEditRecordContent(DcrRecord $dcrRecord): bool
    {
        if ($this->isAdminUser()) {
            return true;
        }

        if ((int) $dcrRecord->created_by !== (int) auth()->id()) {
            return false;
        }

        if (in_array($dcrRecord->workflow_status, ['pending', 'approved'], true)) {
            return false;
        }

        return $dcrRecord->status === 'draft'
            || $dcrRecord->workflow_status === 'rejected'
            || $dcrRecord->workflow_status === null;
    }

    private function ensureCanManageRecord(DcrRecord $dcrRecord): void
    {
        abort_unless(
            $this->canManageRecord($dcrRecord),
            403,
            'You are not allowed to access this DCR record.'
        );
    }

    private function ensureCanEditRecordContent(DcrRecord $dcrRecord): void
    {
        abort_unless(
            $this->canEditRecordContent($dcrRecord),
            403,
            'This DCR record can no longer be edited. Pending and approved records are locked.'
        );
    }

    private function resolveSafeStatusForSave(DcrRecord $dcrRecord, ?string $requestedStatus): string
    {
        $currentStatus = $dcrRecord->status ?: 'draft';
        $requestedStatus = trim((string) $requestedStatus);

        $allowedStatuses = ['draft', 'submitted'];

        if ($requestedStatus === '') {
            $requestedStatus = 'draft';
        }

        if (! in_array($requestedStatus, $allowedStatuses, true)) {
            return $currentStatus;
        }

        if ($requestedStatus === 'draft') {
            if (
                in_array($dcrRecord->workflow_status, ['pending', 'approved'], true)
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

    public function store(Request $request)
    {
        try {
            $payload = $request->all();
            $isAdmin = $this->isAdminUser();

            $record = DcrRecord::create([
                'document_type_id' => $this->rQms013TypeId(),
                'dcr_no' => $payload['dcrNo'] ?? null,
                'to_for' => $payload['toFor'] ?? null,
                'from' => $payload['from'] ?? null,
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
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to save DCR draft.',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }

    public function show(DcrRecord $dcrRecord)
    {
        $this->ensureCanManageRecord($dcrRecord);

        $dcrRecord->load([
            'creator:id,name,department',
            'rejectedBy:id,name',
        ]);

        return response()->json([
            'id' => $dcrRecord->id,
            'status' => $dcrRecord->status,
            'workflow_status' => $dcrRecord->workflow_status,
            'resolution_status' => $dcrRecord->resolution_status,
            'rejection_reason' => $dcrRecord->rejection_reason,
            'rejected_at' => $dcrRecord->rejected_at,
            'rejected_by_name' => $dcrRecord->rejectedBy?->name ?? null,
            'created_by_name' => $dcrRecord->creator?->name ?? '—',
            'created_by_department' => $dcrRecord->creator?->department ?? '—',
            'data' => $dcrRecord->data,
        ]);
    }

    public function update(Request $request, DcrRecord $dcrRecord)
    {
        $this->ensureCanManageRecord($dcrRecord);
        $this->ensureCanEditRecordContent($dcrRecord);

        try {
            $incomingPayload = $request->all();
            $existingPayload = is_array($dcrRecord->data) ? $dcrRecord->data : [];
            $formPayload = $incomingPayload;
            unset($formPayload['status']);

            $payload = $existingPayload;

            if ($formPayload !== []) {
                $payload = array_replace($existingPayload, $formPayload);

                if (array_key_exists('dynamic', $formPayload)) {
                    $existingDynamic = $existingPayload['dynamic'] ?? [];
                    $incomingDynamic = is_array($formPayload['dynamic'])
                        ? $formPayload['dynamic']
                        : [];

                    $payload['dynamic'] = array_replace(
                        is_array($existingDynamic) ? $existingDynamic : [],
                        $incomingDynamic
                    );
                }
            }

            $safeStatus = $this->resolveSafeStatusForSave($dcrRecord, $incomingPayload['status'] ?? null);

            if ($safeStatus === 'submitted') {
                $this->dynamicFieldValidator->validateRequiredFields($payload);
            }

            $dcrRecord->update([
                'dcr_no' => $payload['dcrNo'] ?? $dcrRecord->dcr_no,
                'to_for' => $payload['toFor'] ?? $dcrRecord->to_for,
                'from' => $payload['from'] ?? $dcrRecord->from,
                'status' => $safeStatus,
                'data' => $payload ?: $dcrRecord->data,
                'updated_by' => auth()->id(),
            ]);

            $this->syncPublishedUploads($dcrRecord->fresh());

            $fresh = $dcrRecord->fresh();

            return response()->json([
                'ok' => true,
                'status' => $fresh->status,
                'workflow_status' => $fresh->workflow_status,
                'resolution_status' => $fresh->resolution_status,
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Log::error('DcrRecordController@update failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'error' => 'Failed to update DCR draft. Please try again or contact support.',
            ], 500);
        }
    }

    public function submitForApproval(DcrRecord $dcrRecord)
    {
        $this->ensureCanManageRecord($dcrRecord);

        if ($this->isAdminUser()) {
            return response()->json([
                'message' => 'Admin-created DCR records do not need inbox submission.',
            ], 422);
        }

        if (! $this->canEditRecordContent($dcrRecord)) {
            return response()->json([
                'message' => 'This DCR record can no longer be submitted from its current state.',
            ], 422);
        }

        if ($dcrRecord->workflow_status === 'approved') {
            return response()->json([
                'message' => 'This DCR record is already approved.',
            ], 422);
        }

        if ($dcrRecord->workflow_status === 'pending') {
            return response()->json([
                'message' => 'This DCR record is already submitted for approval.',
            ], 422);
        }

        $this->dynamicFieldValidator->validateRequiredFields(is_array($dcrRecord->data) ? $dcrRecord->data : []);

        $isResubmission = $dcrRecord->workflow_status === 'rejected';

        $dcrRecord->update([
            'status' => 'submitted',
            'workflow_status' => 'pending',
            'rejection_reason' => null,
            'rejected_at' => null,
            'rejected_by' => null,
            'updated_by' => auth()->id(),
        ]);

        $this->activityLogService->log([
            'module' => 'dcr',
            'action' => 'submitted',
            'entity_type' => DcrRecord::class,
            'entity_id' => $dcrRecord->id,
            'record_label' => $dcrRecord->dcr_no ?: 'DCR #'.$dcrRecord->id,
            'file_type' => null,
            'description' => $isResubmission
                ? 'DCR corrected and resubmitted to admin.'
                : 'DCR submitted to admin for approval.',
        ]);

        return response()->json([
            'message' => $isResubmission
                ? 'DCR corrected and resubmitted to admin successfully.'
                : 'DCR submitted to admin for approval successfully.',
            'status' => $dcrRecord->status,
            'workflow_status' => $dcrRecord->workflow_status,
        ]);
    }

    public function download(DcrRecord $dcrRecord)
    {
        $this->ensureCanManageRecord($dcrRecord);

        $tmpDir = $this->ensureTmpDir();

        $fileName = 'DCR_'.($dcrRecord->dcr_no ?: now()->format('Ymd_His')).'.docx';
        $outputPath = $tmpDir.'/'.uniqid('dcr_download_', true).'_'.$fileName;

        $this->generateDocxToPath($dcrRecord, $outputPath);

        $this->activityLogService->log([
            'module' => 'dcr',
            'action' => 'downloaded',
            'entity_type' => DcrRecord::class,
            'entity_id' => $dcrRecord->id,
            'record_label' => $dcrRecord->dcr_no ?: 'DCR #'.$dcrRecord->id,
            'file_type' => 'docx',
            'description' => 'Downloaded generated DCR form '.($dcrRecord->dcr_no ?: 'DCR #'.$dcrRecord->id),
        ]);

        return response()->download($outputPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    public function publish(Request $request, DcrRecord $dcrRecord)
    {
        abort_unless($this->isAdminUser(), 403, 'Only admins can publish DCR records.');

        try {
            $data = $request->validate([
                'remarks' => ['nullable', 'string', 'max:1000'],
                'file_name' => ['nullable', 'string', 'max:200'],
            ]);

            if ($request->has('data')) {
                $payload = (array) $request->input('data', []);

                if ($this->canEditRecordContent($dcrRecord)) {
                    $this->dynamicFieldValidator->validateRequiredFields($payload);

                    $safeStatus = $this->resolveSafeStatusForSave(
                        $dcrRecord,
                        $request->input('status', $dcrRecord->status)
                    );

                    $dcrRecord->update([
                        'dcr_no' => $payload['dcrNo'] ?? $dcrRecord->dcr_no,
                        'to_for' => $payload['toFor'] ?? $dcrRecord->to_for,
                        'from' => $payload['from'] ?? $dcrRecord->from,
                        'status' => $safeStatus,
                        'data' => $payload ?: $dcrRecord->data,
                        'updated_by' => auth()->id(),
                    ]);
                }
            }

            $dcrRecord = $dcrRecord->fresh();
            $this->dynamicFieldValidator->validateRequiredFields(is_array($dcrRecord->data) ? $dcrRecord->data : []);

            $upload = $this->publishRecordDocument(
                $dcrRecord,
                $data['file_name'] ?? null,
                $data['remarks'] ?? null
            );

            $this->activityLogService->log([
                'module' => 'dcr',
                'action' => 'published',
                'entity_type' => DcrRecord::class,
                'entity_id' => $dcrRecord->id,
                'record_label' => $dcrRecord->dcr_no ?: 'DCR #'.$dcrRecord->id,
                'file_type' => 'docx',
                'description' => 'Published DCR record '.($dcrRecord->dcr_no ?: 'DCR #'.$dcrRecord->id).' as document '.$upload->file_name,
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
                'dcr_record_id' => $dcrRecord->id,
                'file_name' => $upload->file_name,
                'workflow_status' => $dcrRecord->workflow_status,
                'resolution_status' => $dcrRecord->resolution_status,
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to publish DCR.',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }

    public function approve(DcrRecord $dcrRecord)
    {
        abort_unless($this->isAdminUser(), 403, 'Only admins can approve DCR records.');

        if ($dcrRecord->status !== 'submitted' || $dcrRecord->workflow_status !== 'pending') {
            return back()->with('error', 'Only submitted pending DCR records can be approved.');
        }

        $this->dynamicFieldValidator->validateRequiredFields(is_array($dcrRecord->data) ? $dcrRecord->data : []);

        DB::transaction(function () use ($dcrRecord) {
            $dcrRecord->update([
                'workflow_status' => 'approved',
                'resolution_status' => $dcrRecord->resolution_status ?: 'open',
                'rejection_reason' => null,
                'rejected_at' => null,
                'rejected_by' => null,
                'updated_by' => auth()->id(),
            ]);

            DB::afterCommit(function () use ($dcrRecord) {
                $fresh = $dcrRecord->fresh();

                $upload = $this->publishRecordDocument(
                    $fresh,
                    null,
                    'Auto-published after admin approval'
                );

                $fresh = $fresh->fresh();

                $this->activityLogService->log([
                    'module' => 'dcr',
                    'action' => 'approved',
                    'entity_type' => DcrRecord::class,
                    'entity_id' => $fresh->id,
                    'record_label' => $fresh->dcr_no ?: 'DCR #'.$fresh->id,
                    'file_type' => 'docx',
                    'description' => 'Approved DCR and published document '.$upload->file_name,
                    'new_values' => [
                        'workflow_status' => $fresh->workflow_status,
                        'resolution_status' => $fresh->resolution_status,
                        'upload_id' => $upload->id,
                    ],
                ]);
            });
        });

        return back()->with('success', 'DCR approved and published successfully.');
    }

    public function reject(Request $request, DcrRecord $dcrRecord)
    {
        abort_unless($this->isAdminUser(), 403, 'Only admins can reject DCR records.');

        if ($dcrRecord->status !== 'submitted' || $dcrRecord->workflow_status !== 'pending') {
            return back()->with('error', 'Only submitted pending DCR records can be rejected.');
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ]);

        $dcrRecord->update([
            'workflow_status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'rejected_at' => now(),
            'rejected_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        $this->activityLogService->log([
            'module' => 'dcr',
            'action' => 'rejected',
            'entity_type' => DcrRecord::class,
            'entity_id' => $dcrRecord->id,
            'record_label' => $dcrRecord->dcr_no ?: 'DCR #'.$dcrRecord->id,
            'file_type' => null,
            'description' => 'Rejected DCR and returned for correction.',
            'new_values' => [
                'workflow_status' => 'rejected',
                'rejection_reason' => $validated['rejection_reason'],
            ],
        ]);

        return back()->with('success', 'DCR rejected and returned for correction.');
    }

    public function updateResolutionStatus(Request $request, DcrRecord $dcrRecord)
    {
        abort_unless($this->isAdminUser(), 403, 'Only admins can update DCR resolution status.');

        $validated = $request->validate([
            'resolution_status' => ['required', 'in:open,ongoing,closed'],
        ]);

        if ($dcrRecord->workflow_status !== 'approved') {
            return response()->json([
                'message' => 'Only approved DCR records can update resolution status.',
            ], 422);
        }

        $newStatus = $validated['resolution_status'];
        $currentStatus = $dcrRecord->resolution_status ?: 'open';

        $allowedTransitions = [
            'open' => ['open', 'ongoing', 'closed'],
            'ongoing' => ['ongoing', 'closed'],
            'closed' => ['closed'],
        ];

        if (! in_array($newStatus, $allowedTransitions[$currentStatus] ?? [], true)) {
            return response()->json([
                'message' => "Invalid resolution status transition from {$currentStatus} to {$newStatus}.",
            ], 422);
        }

        $dcrRecord->update([
            'resolution_status' => $newStatus,
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'DCR resolution status updated successfully.',
            'resolution_status' => $dcrRecord->resolution_status,
        ]);
    }

    public function myRecords(Request $request)
    {
        $status = $request->input('workflow_status', 'all');
        $allowed = ['all', 'pending', 'approved', 'rejected'];

        if (! in_array($status, $allowed, true)) {
            $status = 'all';
        }

        $query = DcrRecord::query()
            ->where('created_by', auth()->id());

        if ($status !== 'all') {
            $query->where('workflow_status', $status);
        }

        $records = $query
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (DcrRecord $record) => [
                'id' => $record->id,
                'dcr_no' => $record->dcr_no,
                'to_for' => $record->to_for,
                'from' => $record->from,
                'status' => $record->status,
                'workflow_status' => $record->workflow_status,
                'resolution_status' => $record->resolution_status,
                'rejection_reason' => $record->rejection_reason,
                'created_at' => $record->created_at,
            ]);

        $statusCounts = DcrRecord::query()
            ->where('created_by', auth()->id())
            ->selectRaw('workflow_status, COUNT(*) as total')
            ->groupBy('workflow_status')
            ->pluck('total', 'workflow_status');

        $counts = [
            'all' => $statusCounts->sum(),
            'pending' => $statusCounts->get('pending') ?? 0,
            'approved' => $statusCounts->get('approved') ?? 0,
            'rejected' => $statusCounts->get('rejected') ?? 0,
        ];

        return \Inertia\Inertia::render('Inbox/MyDCRRecords', [
            'records' => $records,
            'filters' => [
                'workflow_status' => $status,
            ],
            'counts' => $counts,
        ]);
    }
}
