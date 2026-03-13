<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use App\Models\DocumentUpload;
use App\Services\ActivityLogService;
use App\Services\DocumentPreview\DocumentDownloadService;
use App\Services\DocumentPreview\DocumentPreviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
    ];

    public function __construct(
        protected DocumentPreviewService $documentPreviewService,
        protected DocumentDownloadService $documentDownloadService,
        protected ActivityLogService $activityLogService,
    ) {
    }

    public function show(Request $request, string $category): Response
    {
        $category = strtoupper($category);

        abort_unless(in_array($category, self::ALLOWED_CATEGORIES, true), 404);

        $manualTypes = DocumentType::query()
            ->with([
                'series:id,code_prefix,name',
                'activeUpload.uploader:id,name,email',
                'uploads' => function ($query) {
                    $query->with('uploader:id,name,email')
                        ->latest('id');
                },
            ])
            ->active()
            ->manuals()
            ->manualCategory($category)
            ->orderByRaw("
                CASE manual_access
                    WHEN 'controlled' THEN 1
                    WHEN 'uncontrolled' THEN 2
                    ELSE 3
                END
            ")
            ->get();

        $controlled = $manualTypes->firstWhere('manual_access', 'controlled');
        $uncontrolled = $manualTypes->firstWhere('manual_access', 'uncontrolled');

        $canViewControlled = $controlled
            ? $request->user()?->can('viewManual', $controlled) ?? false
            : false;

        $canViewUncontrolled = $uncontrolled
            ? $request->user()?->can('viewManual', $uncontrolled) ?? false
            : false;

        return Inertia::render('Manual/Show', [
            'category' => $category,
            'pageTitle' => $this->buildPageTitle($category),

            'manuals' => [
                'controlled' => $canViewControlled
                    ? $this->transformManualType($controlled)
                    : null,

                'uncontrolled' => $canViewUncontrolled
                    ? $this->transformManualType($uncontrolled)
                    : null,
            ],

            'can' => [
                'upload_controlled' => $controlled
                    ? ($request->user()?->can('manageManual', $controlled) ?? false)
                    : false,

                'upload_uncontrolled' => $uncontrolled
                    ? ($request->user()?->can('manageManual', $uncontrolled) ?? false)
                    : false,

                'view_controlled' => $canViewControlled,
                'view_uncontrolled' => $canViewUncontrolled,
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
            'file' => [
                'required',
                'file',
                'mimes:pdf,doc,docx',
                'max:20480',
            ],
            'revision' => [
                'nullable',
                'string',
                'max:50',
            ],
            'remarks' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        DB::transaction(function () use ($request, $documentType, $category, $access, $validated) {
            DocumentUpload::query()
                ->where('document_type_id', $documentType->id)
                ->where('status', 'Active')
                ->lockForUpdate()
                ->update([
                    'status' => 'Obsolete',
                ]);

            $file = $request->file('file');

            $directory = sprintf(
                'manuals/%s/%s',
                strtolower($category),
                $access
            );

            $storedPath = $file->store($directory, 'public');

            DocumentUpload::create([
                'document_type_id' => $documentType->id,
                'uploaded_by' => $request->user()->id,
                'revision' => $validated['revision'] ?? null,
                'ofi_record_id' => null,
                'dcr_record_id' => null,
                'status' => 'Active',
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $storedPath,
                'storage_disk' => 'public',
                'remarks' => $validated['remarks'] ?? null,
            ]);
        });

        return redirect()
            ->route('manual.show', ['category' => strtolower($category)])
            ->with('success', ucfirst(strtolower($category)) . ' ' . ucfirst($access) . ' manual uploaded successfully.');
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
            'description' => 'Previewed manual ' . $this->resolveManualRecordLabel($upload),
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
            'description' => 'Downloaded manual ' . $this->resolveManualRecordLabel($upload),
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
            default => $category . ' Manuals',
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

        return $upload->file_name ?: 'Manual #' . $upload->id;
    }

    private function transformManualType(?DocumentType $documentType): ?array
    {
        if (!$documentType) {
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

            'active_upload' => $documentType->activeUpload
                ? $this->transformUpload($documentType->activeUpload)
                : null,

            'history' => $documentType->uploads
                ->map(fn($upload) => $this->transformUpload($upload))
                ->values()
                ->all(),
        ];
    }

    private function transformUpload($upload): array
    {
        return [
            'id' => $upload->id,
            'revision' => $upload->revision,
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