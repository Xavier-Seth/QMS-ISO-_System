<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use App\Models\DocumentUpload;
use App\Models\OfiRecord;
use App\Services\ActivityLogService;
use App\Services\OFIFormGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
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
            ]);

            @unlink($tmpPath);
        }
    }

    public function store(Request $request)
    {
        $payload = $request->all();

        $record = OfiRecord::create([
            'document_type_id' => $this->rQms018TypeId(),
            'ofi_no' => $payload['ofiNo'] ?? null,
            'ref_no' => $payload['refNo'] ?? null,
            'to' => $payload['to'] ?? null,
            'status' => $payload['status'] ?? 'draft',
            'workflow_status' => 'pending',
            'resolution_status' => 'open',
            'data' => $payload,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'id' => $record->id,
            'workflow_status' => $record->workflow_status,
            'resolution_status' => $record->resolution_status,
        ]);
    }

    public function show(OfiRecord $ofiRecord)
    {
        return response()->json([
            'id' => $ofiRecord->id,
            'status' => $ofiRecord->status,
            'workflow_status' => $ofiRecord->workflow_status,
            'resolution_status' => $ofiRecord->resolution_status,
            'data' => $ofiRecord->data,
        ]);
    }

    public function update(Request $request, OfiRecord $ofiRecord)
    {
        $payload = $request->all();

        $ofiRecord->update([
            'ofi_no' => $payload['ofiNo'] ?? $ofiRecord->ofi_no,
            'ref_no' => $payload['refNo'] ?? $ofiRecord->ref_no,
            'to' => $payload['to'] ?? $ofiRecord->to,
            'status' => $payload['status'] ?? $ofiRecord->status,
            'data' => $payload,
            'updated_by' => auth()->id(),
        ]);

        $this->syncPublishedUploads($ofiRecord->fresh());

        return response()->json([
            'ok' => true,
            'workflow_status' => $ofiRecord->fresh()->workflow_status,
            'resolution_status' => $ofiRecord->fresh()->resolution_status,
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
            $ofiRecord->update([
                'ofi_no' => $payload['ofiNo'] ?? $ofiRecord->ofi_no,
                'ref_no' => $payload['refNo'] ?? $ofiRecord->ref_no,
                'to' => $payload['to'] ?? $ofiRecord->to,
                'status' => $request->input('status', $ofiRecord->status),
                'data' => $payload ?: $ofiRecord->data,
                'updated_by' => auth()->id(),
            ]);
        }

        $ofiRecord = $ofiRecord->fresh();

        $tmpDir = $this->ensureTmpDir();
        $disk = Storage::disk('public');

        $baseName = $this->sanitizeBaseFileName($data['file_name'] ?? null, $ofiRecord);
        $fileName = "{$baseName}.docx";

        $tmpPath = $tmpDir . '/' . uniqid('ofi_publish_', true) . '_' . $fileName;
        $this->generateDocxToPath($ofiRecord, $tmpPath);

        $publicPath = 'documents/ofi/' . $fileName;

        if ($disk->exists($publicPath)) {
            $fileName = "{$baseName}_" . now()->format('His') . ".docx";
            $publicPath = 'documents/ofi/' . $fileName;
        }

        $disk->put($publicPath, file_get_contents($tmpPath));

        $upload = DocumentUpload::create([
            'document_type_id' => $this->rQms018TypeId(),
            'uploaded_by' => auth()->id(),
            'ofi_record_id' => $ofiRecord->id,
            'revision' => null,
            'status' => null,
            'file_name' => $fileName,
            'file_path' => $publicPath,
            'remarks' => $data['remarks'] ?? null,
        ]);

        @unlink($tmpPath);

        $this->activityLogService->log([
            'module' => 'ofi',
            'action' => 'published',
            'entity_type' => OfiRecord::class,
            'entity_id' => $ofiRecord->id,
            'record_label' => $ofiRecord->ofi_no ?: 'OFI #' . $ofiRecord->id,
            'file_type' => 'docx',
            'description' => 'Published OFI record ' . ($ofiRecord->ofi_no ?: 'OFI #' . $ofiRecord->id) . ' as document ' . $fileName,
            'new_values' => [
                'upload_id' => $upload->id,
                'file_name' => $fileName,
                'file_path' => $publicPath,
                'remarks' => $data['remarks'] ?? null,
            ],
        ]);

        return response()->json([
            'ok' => true,
            'upload_id' => $upload->id,
            'ofi_record_id' => $ofiRecord->id,
            'file_name' => $fileName,
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
            ->with(['creator:id,name'])
            ->where('workflow_status', $status)
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn(OfiRecord $record) => [
                'id' => $record->id,
                'ofi_no' => $record->ofi_no,
                'ref_no' => $record->ref_no,
                'to' => $record->to,
                'workflow_status' => $record->workflow_status,
                'resolution_status' => $record->resolution_status,
                'created_at' => $record->created_at,
                'created_by_name' => $record->creator?->name ?? '—',
            ]);

        $counts = [
            'pending' => OfiRecord::where('workflow_status', 'pending')->count(),
            'approved' => OfiRecord::where('workflow_status', 'approved')->count(),
            'rejected' => OfiRecord::where('workflow_status', 'rejected')->count(),
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
        if ($ofiRecord->workflow_status !== 'approved') {
            $ofiRecord->update([
                'workflow_status' => 'approved',
                'resolution_status' => $ofiRecord->resolution_status ?: 'open',
                'updated_by' => auth()->id(),
            ]);
        }

        return back()->with('success', 'OFI approved successfully.');
    }

    public function reject(OfiRecord $ofiRecord)
    {
        $ofiRecord->update([
            'workflow_status' => 'rejected',
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'OFI rejected successfully.');
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

        $ofiRecord->update([
            'resolution_status' => $validated['resolution_status'],
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'OFI resolution status updated successfully.',
            'resolution_status' => $ofiRecord->resolution_status,
        ]);
    }
}