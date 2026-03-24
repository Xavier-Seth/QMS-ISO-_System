<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use App\Models\DocumentUpload;
use App\Models\OfiRecord;
use App\Services\ActivityLogService;
use App\Services\OFIFormGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class OfiRecordController extends Controller
{
    public function __construct(
        protected ActivityLogService $activityLogService
    ) {
    }

    private function rQms018TypeId(): int
    {
        return DocumentType::where('code', 'R-QMS-018')->value('id')
            ?? abort(404, 'DocumentType R-QMS-018 not found.');
    }

    private function templatePath(): string
    {
        $path = base_path('templates/F-QMS-007_template_fixed_v6.docx');

        if (!file_exists($path)) {
            abort(500, 'OFI template file not found.');
        }

        return $path;
    }

    private function ensureTmpDir(): string
    {
        $tmpDir = storage_path('app/ofi_forms_tmp');

        if (!is_dir($tmpDir)) {
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
            $raw = trim(preg_replace('/\s+/', ' ', $raw));
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
        $generator = new OFIFormGenerator($this->templatePath());
        $generator->generate($ofiRecord->data ?? [], $outputPath);
    }

    private function syncPublishedUploads(OfiRecord $ofiRecord): void
    {
        /** @var Collection<int, DocumentUpload> $uploads */
        $uploads = DocumentUpload::query()
            ->where('ofi_record_id', $ofiRecord->id)
            ->get();

        if ($uploads->isEmpty()) {
            return;
        }

        $disk = Storage::disk('public');
        $tmpDir = $this->ensureTmpDir();

        /** @var DocumentUpload $upload */
        foreach ($uploads as $upload) {
            $fileName = $upload->file_name ?: ('OFI_' . ($ofiRecord->ofi_no ?: $ofiRecord->id) . '.docx');
            $tmpPath = $tmpDir . '/' . uniqid('ofi_sync_', true) . '_' . $fileName;

            $this->generateDocxToPath($ofiRecord, $tmpPath);

            $publicPath = $upload->file_path ?: ('documents/ofi/' . $fileName);
            $disk->put($publicPath, file_get_contents($tmpPath));

            $upload->update([
                'file_name' => $fileName,
                'file_path' => $publicPath,
                'storage_disk' => 'public',
            ]);

            @unlink($tmpPath);
        }
    }

    private function isAdminUser(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    private function resolveSafeStatusForSave(OfiRecord $ofiRecord, ?string $requestedStatus): string
    {
        $currentStatus = $ofiRecord->status ?: 'draft';
        $requestedStatus = trim((string) $requestedStatus);

        if ($requestedStatus === '' || $requestedStatus === 'draft') {
            if (
                in_array($ofiRecord->workflow_status, ['pending', 'approved', 'rejected'], true)
                || $currentStatus === 'submitted'
            ) {
                return 'submitted';
            }

            return 'draft';
        }

        return $requestedStatus;
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
        $disk = Storage::disk('public');

        if ($existingUpload) {
            $fileName = $existingUpload->file_name
                ?: ($this->sanitizeBaseFileName($requestedFileName, $ofiRecord) . '.docx');

            $tmpPath = $tmpDir . '/' . uniqid('ofi_republish_', true) . '_' . $fileName;
            $this->generateDocxToPath($ofiRecord, $tmpPath);

            $publicPath = $existingUpload->file_path ?: ('documents/ofi/' . $fileName);
            $disk->put($publicPath, file_get_contents($tmpPath));

            $existingUpload->update([
                'document_type_id' => $this->rQms018TypeId(),
                'uploaded_by' => auth()->id(),
                'file_name' => $fileName,
                'file_path' => $publicPath,
                'storage_disk' => 'public',
                'remarks' => $remarks ?? $existingUpload->remarks,
            ]);

            @unlink($tmpPath);

            return $existingUpload->fresh();
        }

        $baseName = $this->sanitizeBaseFileName($requestedFileName, $ofiRecord);
        $fileName = "{$baseName}.docx";
        $publicPath = 'documents/ofi/' . $fileName;

        if ($disk->exists($publicPath)) {
            $fileName = "{$baseName}_" . now()->format('His') . '.docx';
            $publicPath = 'documents/ofi/' . $fileName;
        }

        $tmpPath = $tmpDir . '/' . uniqid('ofi_publish_', true) . '_' . $fileName;
        $this->generateDocxToPath($ofiRecord, $tmpPath);

        $disk->put($publicPath, file_get_contents($tmpPath));

        $upload = DocumentUpload::create([
            'document_type_id' => $this->rQms018TypeId(),
            'uploaded_by' => auth()->id(),
            'ofi_record_id' => $ofiRecord->id,
            'revision' => null,
            'status' => null,
            'file_name' => $fileName,
            'file_path' => $publicPath,
            'storage_disk' => 'public',
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
        $payload = $request->all();
        $safeStatus = $this->resolveSafeStatusForSave($ofiRecord, $payload['status'] ?? null);

        $ofiRecord->update([
            'ofi_no' => $payload['ofiNo'] ?? $ofiRecord->ofi_no,
            'ref_no' => $payload['refNo'] ?? $ofiRecord->ref_no,
            'to' => $payload['to'] ?? $ofiRecord->to,
            'status' => $safeStatus,
            'data' => $payload,
            'updated_by' => auth()->id(),
        ]);

        $this->syncPublishedUploads($ofiRecord->fresh());

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
        if ($ofiRecord->created_by !== auth()->id() && !$this->isAdminUser()) {
            return response()->json([
                'message' => 'You are not allowed to submit this OFI record.',
            ], 403);
        }

        if ($this->isAdminUser()) {
            return response()->json([
                'message' => 'Admin-created OFI records do not need inbox submission.',
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
        $tmpDir = $this->ensureTmpDir();

        $fileName = 'OFI_' . ($ofiRecord->ofi_no ?: now()->format('Ymd_His')) . '.docx';
        $outputPath = $tmpDir . '/' . uniqid('ofi_download_', true) . '_' . $fileName;

        $this->generateDocxToPath($ofiRecord, $outputPath);

        $this->activityLogService->log([
            'module' => 'ofi',
            'action' => 'downloaded',
            'entity_type' => OfiRecord::class,
            'entity_id' => $ofiRecord->id,
            'record_label' => $ofiRecord->ofi_no ?: 'OFI #' . $ofiRecord->id,
            'file_type' => 'docx',
            'description' => 'Downloaded generated OFI form ' . ($ofiRecord->ofi_no ?: 'OFI #' . $ofiRecord->id),
        ]);

        return response()->download($outputPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    public function publish(Request $request, OfiRecord $ofiRecord)
    {
        $data = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
            'file_name' => ['nullable', 'string', 'max:200'],
        ]);

        if ($request->has('data')) {
            $payload = (array) $request->input('data', []);
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

        $ofiRecord = $ofiRecord->fresh();

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
            'record_label' => $ofiRecord->ofi_no ?: 'OFI #' . $ofiRecord->id,
            'file_type' => 'docx',
            'description' => 'Published OFI record ' . ($ofiRecord->ofi_no ?: 'OFI #' . $ofiRecord->id) . ' as document ' . $upload->file_name,
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

    public function inbox(Request $request)
    {
        $status = $request->input('workflow_status', 'pending');
        $allowed = ['pending', 'approved', 'rejected'];

        if (!in_array($status, $allowed, true)) {
            $status = 'pending';
        }

        $records = OfiRecord::query()
            ->with([
                'creator:id,name,department,role',
                'rejectedBy:id,name',
            ])
            ->where('status', 'submitted')
            ->where('workflow_status', $status)
            ->whereHas('creator', function ($query) {
                $query->where('role', '!=', 'admin');
            })
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn(OfiRecord $record) => [
                'id' => $record->id,
                'ofi_no' => $record->ofi_no,
                'ref_no' => $record->ref_no,
                'to' => $record->to,
                'status' => $record->status,
                'workflow_status' => $record->workflow_status,
                'resolution_status' => $record->resolution_status,
                'rejection_reason' => $record->rejection_reason,
                'rejected_at' => $record->rejected_at,
                'rejected_by_name' => $record->rejectedBy?->name ?? null,
                'created_at' => $record->created_at,
                'created_by_name' => $record->creator?->name ?? '—',
                'created_by_department' => $record->creator?->department ?? '—',
            ]);

        $counts = [
            'pending' => OfiRecord::query()
                ->where('status', 'submitted')
                ->where('workflow_status', 'pending')
                ->whereHas('creator', fn($q) => $q->where('role', '!=', 'admin'))
                ->count(),
            'approved' => OfiRecord::query()
                ->where('status', 'submitted')
                ->where('workflow_status', 'approved')
                ->whereHas('creator', fn($q) => $q->where('role', '!=', 'admin'))
                ->count(),
            'rejected' => OfiRecord::query()
                ->where('status', 'submitted')
                ->where('workflow_status', 'rejected')
                ->whereHas('creator', fn($q) => $q->where('role', '!=', 'admin'))
                ->count(),
        ];

        return Inertia::render('Inbox/OFIInbox', [
            'records' => $records,
            'filters' => [
                'workflow_status' => $status,
            ],
            'counts' => $counts,
        ]);
    }

    public function approve(OfiRecord $ofiRecord)
    {
        if ($ofiRecord->status !== 'submitted' || $ofiRecord->workflow_status !== 'pending') {
            return back()->with('error', 'Only submitted pending OFI records can be approved.');
        }

        DB::transaction(function () use ($ofiRecord) {
            $ofiRecord->update([
                'workflow_status' => 'approved',
                'resolution_status' => $ofiRecord->resolution_status ?: 'open',
                'updated_by' => auth()->id(),
            ]);

            $upload = $this->publishRecordDocument(
                $ofiRecord->fresh(),
                null,
                'Auto-published after admin approval'
            );

            $fresh = $ofiRecord->fresh();

            $this->activityLogService->log([
                'module' => 'ofi',
                'action' => 'approved',
                'entity_type' => OfiRecord::class,
                'entity_id' => $fresh->id,
                'record_label' => $fresh->ofi_no ?: 'OFI #' . $fresh->id,
                'file_type' => 'docx',
                'description' => 'Approved OFI and published document ' . $upload->file_name,
                'new_values' => [
                    'workflow_status' => $fresh->workflow_status,
                    'resolution_status' => $fresh->resolution_status,
                    'upload_id' => $upload->id,
                ],
            ]);
        });

        return back()->with('success', 'OFI approved and published successfully.');
    }

    public function reject(Request $request, OfiRecord $ofiRecord)
    {
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
        $validated = $request->validate([
            'resolution_status' => ['required', 'in:open,ongoing,closed'],
        ]);

        if ($ofiRecord->workflow_status !== 'approved') {
            return response()->json([
                'message' => 'Only approved OFI records can update resolution status.',
            ], 422);
        }

        $newStatus = $validated['resolution_status'];
        $currentStatus = $ofiRecord->resolution_status;

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

        if (!in_array($status, $allowed, true)) {
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
            ->through(fn(OfiRecord $record) => [
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

        $counts = [
            'all' => OfiRecord::query()
                ->where('created_by', auth()->id())
                ->count(),
            'pending' => OfiRecord::query()
                ->where('created_by', auth()->id())
                ->where('workflow_status', 'pending')
                ->count(),
            'approved' => OfiRecord::query()
                ->where('created_by', auth()->id())
                ->where('workflow_status', 'approved')
                ->count(),
            'rejected' => OfiRecord::query()
                ->where('created_by', auth()->id())
                ->where('workflow_status', 'rejected')
                ->count(),
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