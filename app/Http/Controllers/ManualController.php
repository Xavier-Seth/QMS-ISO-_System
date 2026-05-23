<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use App\Models\DocumentUpload;
use App\Services\ActivityLogService;
use App\Services\DocumentPreview\DocumentDownloadService;
use App\Services\DocumentPreview\DocumentPreviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ManualController extends Controller
{
    private const ALLOWED_CATEGORIES = [
        'ASM',
        'QSM',
        'HRM',
        'RIEM',
        'REM',
    ];

    private const ALLOWED_ACCESS = [
        'controlled',
        'uncontrolled',
        'master_copy',
    ];

    public function __construct(
        protected DocumentPreviewService $documentPreviewService,
        protected DocumentDownloadService $documentDownloadService,
        protected ActivityLogService $activityLogService,
    ) {}

    public function show(Request $request, string $category): Response
    {
        $category = strtoupper($category);

        abort_unless(in_array($category, self::ALLOWED_CATEGORIES, true), 404);

        $manualTypes = DocumentType::query()
            ->with([
                'series:id,code_prefix,name',
                'uploads' => function ($query) {
                    $query->with('uploader:id,name,email')
                        ->latest('id');
                },
            ])
            ->active()
            ->manuals()
            ->manualCategory($category)
            ->get()
            ->keyBy('manual_access');

        $masterCopy = $manualTypes->get('master_copy');
        $controlled = $manualTypes->get('controlled');
        $uncontrolled = $manualTypes->get('uncontrolled');

        $user = $request->user();

        $canViewMasterCopy = $masterCopy && $user->can('viewManual', $masterCopy);
        $canViewControlled = $controlled && $user->can('viewManual', $controlled);

        return Inertia::render('Manual/Show', [
            'category' => $category,
            'pageTitle' => $this->buildPageTitle($category),
            'manuals' => [
                'master_copy' => $canViewMasterCopy ? $this->transformManualType($masterCopy) : null,
                'controlled' => $canViewControlled ? $this->transformManualType($controlled) : null,
                'uncontrolled' => $this->transformManualType($uncontrolled),
            ],
            'can' => [
                'view_master_copy' => $canViewMasterCopy,
                'upload_master_copy' => $masterCopy ? $user->can('manageManual', $masterCopy) : false,
                'view_controlled' => $canViewControlled,
                'upload_controlled' => $controlled ? $user->can('manageManual', $controlled) : false,
                'view_uncontrolled' => $uncontrolled ? $user->can('viewManual', $uncontrolled) : false,
                'upload_uncontrolled' => $uncontrolled ? $user->can('manageManual', $uncontrolled) : false,
            ],
        ]);
    }

    public function upload(Request $request, string $category, string $access): RedirectResponse
    {
        $category = strtoupper($category);
        $access = strtolower($access);

        abort_unless(in_array($category, self::ALLOWED_CATEGORIES, true), 404);
        abort_unless(in_array($access, self::ALLOWED_ACCESS, true), 404);

        $documentType = DocumentType::query()
            ->active()
            ->manuals()
            ->manualCategory($category)
            ->manualAccess($access)
            ->firstOrFail();

        $this->authorize('manageManual', $documentType);

        $validated = $request->validate([
            'files' => [
                'required',
                'array',
                'min:1',
                'max:20',
            ],
            'files.*' => [
                'file',
                'mimes:pdf,doc,docx',
                'max:20480',
            ],
            'remarks' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $directory = sprintf(
            'manuals/%s/%s',
            strtolower($category),
            $access
        );

        $count = 0;

        foreach ($request->file('files') as $file) {
            $storedPath = $file->store($directory, 'public');

            DocumentUpload::create([
                'document_type_id' => $documentType->id,
                'uploaded_by' => $request->user()->id,
                'status' => 'Active',
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $storedPath,
                'storage_disk' => 'public',
                'remarks' => $validated['remarks'] ?? null,
            ]);

            $count++;
        }

        $label = $count === 1 ? '1 file uploaded successfully.' : "{$count} files uploaded successfully.";

        return redirect()
            ->route('manual.show', ['category' => strtolower($category)])
            ->with('success', $label);
    }

    public function destroy(Request $request, DocumentUpload $upload): RedirectResponse
    {
        $upload->loadMissing('documentType');
        $documentType = $upload->documentType;

        abort_unless($documentType && $documentType->isManual(), 404);

        $this->authorize('manageManual', $documentType);

        $label = $this->resolveManualRecordLabel($upload);
        $fileType = $this->activityLogService->extensionFromFileName($upload->file_name);

        Storage::disk($upload->storage_disk)->delete($upload->file_path);

        $this->activityLogService->log([
            'module' => 'manuals',
            'action' => 'deleted',
            'entity_type' => DocumentUpload::class,
            'entity_id' => $upload->id,
            'record_label' => $label,
            'file_type' => $fileType,
            'description' => 'Deleted manual file '.$label,
        ]);

        $upload->delete();

        return redirect()->back()->with('success', 'File deleted successfully.');
    }

    public function toggleStatus(Request $request, DocumentUpload $upload): RedirectResponse
    {
        $upload->loadMissing('documentType');
        $documentType = $upload->documentType;

        abort_unless($documentType && $documentType->isManual(), 403);

        $this->authorize('manageManual', $documentType);

        $newStatus = $upload->status === 'Active' ? 'Obsolete' : 'Active';
        $upload->update(['status' => $newStatus]);

        return redirect()->back()->with('success', "File marked as {$newStatus}.");
    }

    public function preview(Request $request, DocumentUpload $upload)
    {
        $upload->loadMissing('documentType.series');

        $documentType = $upload->documentType;

        abort_unless($documentType && $documentType->isManual(), 404);

        $this->authorize('accessManualFile', $documentType);

        abort_unless(
            $this->documentPreviewService->canPreview($upload),
            404,
            'This file type is not supported for preview.'
        );

        $this->activityLogService->log([
            'module' => 'manuals',
            'action' => 'previewed',
            'entity_type' => DocumentUpload::class,
            'entity_id' => $upload->id,
            'record_label' => $this->resolveManualRecordLabel($upload),
            'file_type' => $this->activityLogService->extensionFromFileName($upload->file_name),
            'description' => 'Previewed manual '.$this->resolveManualRecordLabel($upload),
        ]);

        return $this->documentPreviewService->preview($upload);
    }

    public function download(Request $request, DocumentUpload $upload)
    {
        $upload->loadMissing('documentType.series');

        $documentType = $upload->documentType;

        abort_unless($documentType && $documentType->isManual(), 404);

        $this->authorize('accessManualFile', $documentType);

        $this->activityLogService->log([
            'module' => 'manuals',
            'action' => 'downloaded',
            'entity_type' => DocumentUpload::class,
            'entity_id' => $upload->id,
            'record_label' => $this->resolveManualRecordLabel($upload),
            'file_type' => $this->activityLogService->extensionFromFileName($upload->file_name),
            'description' => 'Downloaded manual '.$this->resolveManualRecordLabel($upload),
        ]);

        return $this->documentDownloadService->download($upload);
    }

    private function buildPageTitle(string $category): string
    {
        return match ($category) {
            'ASM' => 'ASM Manuals',
            'QSM' => 'QSM Manuals',
            'HRM' => 'HRM Manuals',
            'RIEM' => 'RIEM Manuals',
            'REM' => 'REM Manuals',
            default => $category.' Manuals',
        };
    }

    private function resolveManualRecordLabel(DocumentUpload $upload): string
    {
        $documentType = $upload->documentType;

        if ($documentType?->code) {
            return $documentType->code;
        }

        if ($documentType?->title) {
            return $documentType->title;
        }

        return $upload->file_name ?: 'Manual #'.$upload->id;
    }

    private function transformManualType(?DocumentType $documentType): ?array
    {
        if (! $documentType) {
            return null;
        }

        return [
            'id' => $documentType->id,
            'code' => $documentType->code,
            'title' => $documentType->title,
            'manual_category' => $documentType->manual_category,
            'manual_access' => $documentType->manual_access,
            'storage' => $documentType->storage,
            'requires_revision' => (bool) $documentType->requires_revision,
            'status' => $documentType->status,
            'files' => $documentType->uploads
                ->map(fn ($upload) => $this->transformUpload($upload))
                ->values()
                ->all(),
        ];
    }

    private function transformUpload($upload): array
    {
        return [
            'id' => $upload->id,
            'status' => $upload->status,
            'file_name' => $upload->file_name,
            'file_path' => $upload->file_path,
            'remarks' => $upload->remarks,
            'uploaded_at' => optional($upload->created_at)?->toDateTimeString(),
            'updated_at' => optional($upload->updated_at)?->toDateTimeString(),
            'uploader' => $upload->uploader
                ? [
                    'id' => $upload->uploader->id,
                    'name' => $upload->uploader->name,
                    'email' => $upload->uploader->email,
                ]
                : null,
        ];
    }
}
