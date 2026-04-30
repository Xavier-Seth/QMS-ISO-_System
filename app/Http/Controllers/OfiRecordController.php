<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use App\Models\DocumentUpload;
use App\Models\OfiRecord;
use App\Services\ActivityLogService;
use App\Services\OFIFormGenerator;
use App\Services\QmsDynamicFieldValidator;
use App\Services\QmsTemplateResolver;
use App\Support\QmsTemplateModules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class OfiRecordController extends Controller
{
    public function __construct(
        protected ActivityLogService $activityLogService,
        protected QmsTemplateResolver $templateResolver,
        protected QmsDynamicFieldValidator $dynamicFieldValidator
    ) {}

    private function rQms018TypeId(): int
    {
        return DocumentType::where('code', 'R-QMS-018')->value('id')
            ?? abort(404, 'DocumentType R-QMS-018 not found.');
    }

    private function templatePath(): string
    {
        return $this->templateResolver->getActiveOfiTemplatePath();
    }

    private function ensureTmpDir(): string
    {
        $tmpDir = storage_path('app/ofi_forms_tmp');

        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        return $tmpDir;
    }

    private function sanitizeBaseFileName(?string $raw, OfiRecord $ofiRecord): string
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

        $fallbackBase = $ofiRecord->ofi_no
            ? preg_replace('/[^A-Za-z0-9_\-]/', '_', $ofiRecord->ofi_no)
            : now()->format('Ymd_His');

        return "OFI_{$fallbackBase}";
    }

    private function generateDocxToPath(OfiRecord $ofiRecord, string $outputPath): void
    {
        $data = is_array($ofiRecord->data) ? $ofiRecord->data : [];

        $this->dynamicFieldValidator->validateRequiredFields(
            QmsTemplateModules::OFI,
            $data
        );

        $generator = new OFIFormGenerator($this->templatePath());
        $generator->generate($data, $outputPath);
    }

    private function isAdminUser(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    private function canManageRecord(OfiRecord $ofiRecord): bool
    {
        if ($this->isAdminUser()) {
            return true;
        }

        return (int) $ofiRecord->created_by === (int) auth()->id();
    }

    private function canEditRecordContent(OfiRecord $ofiRecord): bool
    {
        if ($this->isAdminUser()) {
            return true;
        }

        if ((int) $ofiRecord->created_by !== (int) auth()->id()) {
            return false;
        }

        if (in_array($ofiRecord->workflow_status, ['pending', 'approved'], true)) {
            return false;
        }

        return $ofiRecord->status === 'draft'
            || $ofiRecord->workflow_status === 'rejected'
            || $ofiRecord->workflow_status === null;
    }

    private function ensureCanManageRecord(OfiRecord $ofiRecord): void
    {
        abort_unless(
            $this->canManageRecord($ofiRecord),
            403,
            'You are not allowed to access this OFI record.'
        );
    }

    private function ensureCanEditRecordContent(OfiRecord $ofiRecord): void
    {
        abort_unless(
            $this->canEditRecordContent($ofiRecord),
            403,
            'This OFI record can no longer be edited. Pending and approved records are locked.'
        );
    }

    private function resolveSafeStatusForSave(OfiRecord $ofiRecord, ?string $requestedStatus): string
    {
        $currentStatus = $ofiRecord->status ?: 'draft';
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
                in_array($ofiRecord->workflow_status, ['pending', 'approved'], true)
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
        OfiRecord $ofiRecord,
        ?string $requestedFileName = null,
        ?string $remarks = null
    ): DocumentUpload {
        $existingUpload = DocumentUpload::query()
            ->where('ofi_record_id', $ofiRecord->id)
            ->orderByDesc('id')
            ->first();

        $tmpDir = $this->ensureTmpDir();
        $storageDisk = 'private';
        $disk = Storage::disk($storageDisk);

        if ($existingUpload) {
            $fileName = $existingUpload->file_name
                ?: ($this->sanitizeBaseFileName($requestedFileName, $ofiRecord).'.docx');

            $tmpPath = $tmpDir.'/'.uniqid('ofi_republish_', true).'_'.$fileName;
            $this->generateDocxToPath($ofiRecord, $tmpPath);

            $oldDisk = $existingUpload->getStorageDiskName();
            $oldPath = $existingUpload->file_path;
            $storedPath = $existingUpload->file_path ?: ('documents/ofi/'.$fileName);

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
                'document_type_id' => $this->rQms018TypeId(),
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

        $baseName = $this->sanitizeBaseFileName($requestedFileName, $ofiRecord);
        $fileName = "{$baseName}.docx";
        $storedPath = 'documents/ofi/'.$fileName;

        if ($disk->exists($storedPath)) {
            $fileName = "{$baseName}_".now()->format('His').'.docx';
            $storedPath = 'documents/ofi/'.$fileName;
        }

        $tmpPath = $tmpDir.'/'.uniqid('ofi_publish_', true).'_'.$fileName;
        $this->generateDocxToPath($ofiRecord, $tmpPath);

        $written = $disk->put($storedPath, file_get_contents($tmpPath));

        if ($written === false) {
            @unlink($tmpPath);
            throw new \RuntimeException("Failed to write file to storage: {$storedPath}");
        }

        $upload = DocumentUpload::create([
            'document_type_id' => $this->rQms018TypeId(),
            'uploaded_by' => auth()->id(),
            'ofi_record_id' => $ofiRecord->id,
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
        $payload = $request->all();
        $isAdmin = $this->isAdminUser();

        $record = OfiRecord::create([
            'document_type_id' => $this->rQms018TypeId(),
            'ofi_no' => $payload['ofiNo'] ?? null,
            'ref_no' => $payload['refNo'] ?? null,
            'to' => $payload['to'] ?? null,
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

    public function show(OfiRecord $ofiRecord)
    {
        $this->ensureCanManageRecord($ofiRecord);

        $ofiRecord->load([
            'creator:id,name,department',
            'rejectedBy:id,name',
        ]);

        return response()->json([
            'id' => $ofiRecord->id,
            'status' => $ofiRecord->status,
            'workflow_status' => $ofiRecord->workflow_status,
            'resolution_status' => $ofiRecord->resolution_status,
            'rejection_reason' => $ofiRecord->rejection_reason,
            'rejected_at' => $ofiRecord->rejected_at,
            'rejected_by_name' => $ofiRecord->rejectedBy?->name ?? null,
            'created_by_name' => $ofiRecord->creator?->name ?? '—',
            'created_by_department' => $ofiRecord->creator?->department ?? '—',
            'data' => $ofiRecord->data,
        ]);
    }

    public function update(Request $request, OfiRecord $ofiRecord)
    {
        $this->ensureCanManageRecord($ofiRecord);
        $this->ensureCanEditRecordContent($ofiRecord);

        $incomingPayload = $request->all();
        $existingPayload = is_array($ofiRecord->data) ? $ofiRecord->data : [];
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

        $safeStatus = $this->resolveSafeStatusForSave($ofiRecord, $incomingPayload['status'] ?? null);

        if ($safeStatus === 'submitted') {
            $this->dynamicFieldValidator->validateRequiredFields(
                QmsTemplateModules::OFI,
                $payload
            );
        }

        $ofiRecord->update([
            'ofi_no' => $payload['ofiNo'] ?? $ofiRecord->ofi_no,
            'ref_no' => $payload['refNo'] ?? $ofiRecord->ref_no,
            'to' => $payload['to'] ?? $ofiRecord->to,
            'status' => $safeStatus,
            'data' => $payload,
            'updated_by' => auth()->id(),
        ]);

        $fresh = $ofiRecord->fresh();

        return response()->json([
            'ok' => true,
            'status' => $fresh->status,
            'workflow_status' => $fresh->workflow_status,
            'resolution_status' => $fresh->resolution_status,
        ]);
    }

    public function submitForApproval(OfiRecord $ofiRecord)
    {
        $this->ensureCanManageRecord($ofiRecord);

        if ($this->isAdminUser()) {
            return response()->json([
                'message' => 'Admin-created OFI records do not need inbox submission.',
            ], 422);
        }

        if (! $this->canEditRecordContent($ofiRecord)) {
            return response()->json([
                'message' => 'This OFI record can no longer be submitted from its current state.',
            ], 422);
        }

        if ($ofiRecord->workflow_status === 'approved') {
            return response()->json([
                'message' => 'This OFI record is already approved.',
            ], 422);
        }

        if ($ofiRecord->workflow_status === 'pending') {
            return response()->json([
                'message' => 'This OFI record is already submitted for approval.',
            ], 422);
        }

        $this->dynamicFieldValidator->validateRequiredFields(
            QmsTemplateModules::OFI,
            is_array($ofiRecord->data) ? $ofiRecord->data : []
        );

        $isResubmission = $ofiRecord->workflow_status === 'rejected';

        $ofiRecord->update([
            'status' => 'submitted',
            'workflow_status' => 'pending',
            'rejection_reason' => null,
            'rejected_at' => null,
            'rejected_by' => null,
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => $isResubmission
                ? 'OFI corrected and resubmitted to admin successfully.'
                : 'OFI submitted to admin for approval successfully.',
            'status' => $ofiRecord->status,
            'workflow_status' => $ofiRecord->workflow_status,
        ]);
    }

    public function download(OfiRecord $ofiRecord)
    {
        $this->ensureCanManageRecord($ofiRecord);

        $tmpDir = $this->ensureTmpDir();

        $fileName = 'OFI_'.($ofiRecord->ofi_no ?: now()->format('Ymd_His')).'.docx';
        $outputPath = $tmpDir.'/'.uniqid('ofi_download_', true).'_'.$fileName;

        $this->generateDocxToPath($ofiRecord, $outputPath);

        $this->activityLogService->log([
            'module' => 'ofi',
            'action' => 'downloaded',
            'entity_type' => OfiRecord::class,
            'entity_id' => $ofiRecord->id,
            'record_label' => $ofiRecord->ofi_no ?: 'OFI #'.$ofiRecord->id,
            'file_type' => 'docx',
            'description' => 'Downloaded generated OFI form '.($ofiRecord->ofi_no ?: 'OFI #'.$ofiRecord->id),
        ]);

        return response()->download($outputPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    public function publish(Request $request, OfiRecord $ofiRecord)
    {
        abort_unless($this->isAdminUser(), 403, 'Only admins can publish OFI records.');

        $data = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
            'file_name' => ['nullable', 'string', 'max:200'],
        ]);

        if ($request->has('data')) {
            $payload = (array) $request->input('data', []);

            if ($this->canEditRecordContent($ofiRecord)) {
                $this->dynamicFieldValidator->validateRequiredFields(
                    QmsTemplateModules::OFI,
                    $payload
                );

                $safeStatus = $this->resolveSafeStatusForSave(
                    $ofiRecord,
                    $request->input('status', $ofiRecord->status)
                );

                $ofiRecord->update([
                    'ofi_no' => $payload['ofiNo'] ?? $ofiRecord->ofi_no,
                    'ref_no' => $payload['refNo'] ?? $ofiRecord->ref_no,
                    'to' => $payload['to'] ?? $ofiRecord->to,
                    'status' => $safeStatus,
                    'data' => $payload ?: $ofiRecord->data,
                    'updated_by' => auth()->id(),
                ]);
            }
        }

        $ofiRecord = $ofiRecord->fresh();

        $this->dynamicFieldValidator->validateRequiredFields(
            QmsTemplateModules::OFI,
            is_array($ofiRecord->data) ? $ofiRecord->data : []
        );

        $upload = $this->publishRecordDocument(
            $ofiRecord,
            $data['file_name'] ?? null,
            $data['remarks'] ?? null
        );

        $this->activityLogService->log([
            'module' => 'ofi',
            'action' => 'published',
            'entity_type' => OfiRecord::class,
            'entity_id' => $ofiRecord->id,
            'record_label' => $ofiRecord->ofi_no ?: 'OFI #'.$ofiRecord->id,
            'file_type' => 'docx',
            'description' => 'Published OFI record '.($ofiRecord->ofi_no ?: 'OFI #'.$ofiRecord->id).' as document '.$upload->file_name,
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
            'ofi_record_id' => $ofiRecord->id,
            'file_name' => $upload->file_name,
            'workflow_status' => $ofiRecord->workflow_status,
            'resolution_status' => $ofiRecord->resolution_status,
        ]);
    }

    public function approve(OfiRecord $ofiRecord)
    {
        abort_unless($this->isAdminUser(), 403, 'Only admins can approve OFI records.');

        if ($ofiRecord->status !== 'submitted' || $ofiRecord->workflow_status !== 'pending') {
            return back()->with('error', 'Only submitted pending OFI records can be approved.');
        }

        $this->dynamicFieldValidator->validateRequiredFields(
            QmsTemplateModules::OFI,
            is_array($ofiRecord->data) ? $ofiRecord->data : []
        );

        DB::transaction(function () use ($ofiRecord) {
            $ofiRecord->update([
                'workflow_status' => 'approved',
                'resolution_status' => $ofiRecord->resolution_status ?: 'open',
                'rejection_reason' => null,
                'rejected_at' => null,
                'rejected_by' => null,
                'updated_by' => auth()->id(),
            ]);

            DB::afterCommit(function () use ($ofiRecord) {
                $fresh = $ofiRecord->fresh();

                $upload = $this->publishRecordDocument(
                    $fresh,
                    null,
                    'Auto-published after admin approval'
                );

                $fresh = $fresh->fresh();

                $this->activityLogService->log([
                    'module' => 'ofi',
                    'action' => 'approved',
                    'entity_type' => OfiRecord::class,
                    'entity_id' => $fresh->id,
                    'record_label' => $fresh->ofi_no ?: 'OFI #'.$fresh->id,
                    'file_type' => 'docx',
                    'description' => 'Approved OFI and published document '.$upload->file_name,
                    'new_values' => [
                        'workflow_status' => $fresh->workflow_status,
                        'resolution_status' => $fresh->resolution_status,
                        'upload_id' => $upload->id,
                    ],
                ]);
            });
        });

        return back()->with('success', 'OFI approved and published successfully.');
    }

    public function reject(Request $request, OfiRecord $ofiRecord)
    {
        abort_unless($this->isAdminUser(), 403, 'Only admins can reject OFI records.');

        if ($ofiRecord->status !== 'submitted' || $ofiRecord->workflow_status !== 'pending') {
            return back()->with('error', 'Only submitted pending OFI records can be rejected.');
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ]);

        $ofiRecord->update([
            'workflow_status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'rejected_at' => now(),
            'rejected_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'OFI rejected and returned for correction.');
    }

    public function updateResolutionStatus(Request $request, OfiRecord $ofiRecord)
    {
        abort_unless($this->isAdminUser(), 403, 'Only admins can update OFI resolution status.');

        $validated = $request->validate([
            'resolution_status' => ['required', 'in:open,ongoing,closed'],
        ]);

        if ($ofiRecord->workflow_status !== 'approved') {
            return response()->json([
                'message' => 'Only approved OFI records can update resolution status.',
            ], 422);
        }

        $newStatus = $validated['resolution_status'];
        $currentStatus = $ofiRecord->resolution_status ?: 'open';

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

        $ofiRecord->update([
            'resolution_status' => $newStatus,
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'OFI resolution status updated successfully.',
            'resolution_status' => $ofiRecord->resolution_status,
        ]);
    }

    public function myRecords(Request $request)
    {
        $status = $request->input('workflow_status', 'all');
        $allowed = ['all', 'pending', 'approved', 'rejected'];

        if (! in_array($status, $allowed, true)) {
            $status = 'all';
        }

        $query = OfiRecord::query()
            ->where('created_by', auth()->id());

        if ($status !== 'all') {
            $query->where('workflow_status', $status);
        }

        $records = $query
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (OfiRecord $record) => [
                'id' => $record->id,
                'ofi_no' => $record->ofi_no,
                'ref_no' => $record->ref_no,
                'to' => $record->to,
                'status' => $record->status,
                'workflow_status' => $record->workflow_status,
                'resolution_status' => $record->resolution_status,
                'rejection_reason' => $record->rejection_reason,
                'created_at' => $record->created_at,
            ]);

        $statusCounts = OfiRecord::query()
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

        return Inertia::render('Inbox/MyRecords', [
            'records' => $records,
            'filters' => [
                'workflow_status' => $status,
            ],
            'counts' => $counts,
        ]);
    }
}
