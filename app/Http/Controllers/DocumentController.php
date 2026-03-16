<?php

namespace App\Http\Controllers;

use App\Models\DocumentSeries;
use App\Models\DocumentType;
use App\Models\DocumentUpload;
use App\Services\ActivityLogService;
use App\Services\DCRFormGenerator;
use App\Services\DocumentPreview\DocumentDownloadService;
use App\Services\DocumentPreview\DocumentPreviewService;
use App\Services\DocumentPreview\OfficeToPdfConverter;
use App\Services\OFIFormGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use RuntimeException;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class DocumentController extends Controller
{
    public function __construct(
        protected DocumentPreviewService $documentPreviewService,
        protected DocumentDownloadService $documentDownloadService,
        protected OfficeToPdfConverter $officeToPdfConverter,
        protected ActivityLogService $activityLogService,
    ) {
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $seriesCode = $request->get('series', 'All');
        $sort = $request->get('sort', 'code_asc');
        $view = $request->get('view', 'group');

        $seriesOptions = DocumentSeries::query()
            ->where('code_prefix', '!=', 'MANUAL')
            ->orderBy('code_prefix')
            ->get(['id', 'code_prefix', 'name'])
            ->map(fn($s) => [
                'id' => $s->id,
                'code_prefix' => $s->code_prefix,
                'name' => $s->name,
            ])
            ->values();

        $query = DocumentType::query()
            ->with('series:id,code_prefix,name')
            ->withCount('uploads')
            ->withMax('uploads', 'created_at');

        if ($seriesCode !== 'All') {
            $selected = DocumentSeries::where('code_prefix', $seriesCode)->first();

            if ($selected) {
                $query->where('series_id', $selected->id);
            } else {
                $query->whereRaw('1=0');
            }
        } else {
            $query->whereHas('series', fn($qq) => $qq->where('code_prefix', '!=', 'MANUAL'));
        }

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('code', 'like', "%{$q}%")
                    ->orWhere('title', 'like', "%{$q}%")
                    ->orWhere('storage', 'like', "%{$q}%");
            });
        }

        if ($sort === 'name_asc') {
            $query->orderBy('title');
        } elseif ($sort === 'uploads_desc') {
            $query->orderByDesc('uploads_count');
        } elseif ($sort === 'latest_desc') {
            $query->orderByDesc('uploads_max_created_at');
        } else {
            $query->orderBy('code');
        }

        $types = $query->get();

        $documentTypes = $types->map(function (DocumentType $t) {
            return [
                'id' => $t->id,
                'code' => $t->code,
                'name' => $t->title,
                'file_type' => $t->storage,
                'requires_revision' => $this->isRevisionControlled($t),
                'documents_count' => (int) $t->uploads_count,
                'latest_upload_at' => $t->uploads_max_created_at,
                'series' => [
                    'code_prefix' => $t->series?->code_prefix,
                    'name' => $t->series?->name,
                ],
            ];
        });

        return Inertia::render('Documents/Index', [
            'documentTypes' => $documentTypes,
            'seriesOptions' => $seriesOptions,
            'filters' => [
                'q' => $q,
                'series' => $seriesCode,
                'sort' => $sort,
                'view' => $view,
            ],
        ]);
    }

    public function show(Request $request, DocumentType $documentType)
    {
        $q = trim((string) $request->get('q', ''));
        $status = (string) $request->get('status', 'All');
        $sort = (string) $request->get('sort', 'latest');
        $dateFrom = trim((string) $request->get('date_from', ''));
        $dateTo = trim((string) $request->get('date_to', ''));

        $allowedPerPage = [10, 25, 50, 100];
        $perPage = (int) $request->get('per_page', 10);
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $allowedSorts = [
            'latest',
            'oldest',
            'name_asc',
            'name_desc',
            'revision_asc',
            'revision_desc',
        ];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'latest';
        }

        $isRevisionControlled = $this->isRevisionControlled($documentType);

        $query = DocumentUpload::query()
            ->with(['uploader', 'ofiRecord', 'dcrRecord'])
            ->where('document_type_id', $documentType->id);

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('file_name', 'like', "%{$q}%")
                    ->orWhere('revision', 'like', "%{$q}%")
                    ->orWhere('remarks', 'like', "%{$q}%")
                    ->orWhereHas('uploader', function ($uploaderQuery) use ($q) {
                        $uploaderQuery->where('name', 'like', "%{$q}%")
                            ->orWhere('username', 'like', "%{$q}%")
                            ->orWhere('email', 'like', "%{$q}%");
                    });
            });
        }

        if ($isRevisionControlled && in_array($status, ['Active', 'Obsolete'], true)) {
            $query->where('status', $status);
        }

        if ($dateFrom !== '') {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo !== '') {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        switch ($sort) {
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;

            case 'name_asc':
                $query->orderBy('file_name', 'asc');
                break;

            case 'name_desc':
                $query->orderBy('file_name', 'desc');
                break;

            case 'revision_asc':
                $query->orderBy('revision', 'asc');
                break;

            case 'revision_desc':
                $query->orderBy('revision', 'desc');
                break;

            case 'latest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        $documents = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn($d) => [
                'id' => $d->id,
                'file_name' => $d->file_name,
                'revision' => $d->revision,
                'status' => $d->status,
                'uploaded_by_name' => $d->uploader?->name ?? '—',
                'created_at' => $d->created_at,
                'ofi_record_id' => $d->ofi_record_id,
                'dcr_record_id' => $d->dcr_record_id,
                'ofi_workflow_status' => $d->ofiRecord?->workflow_status,
                'ofi_resolution_status' => $d->ofiRecord?->resolution_status,
                'preview_url' => route('documents.uploads.preview', $d->id),
                'download_url' => route('documents.uploads.download', $d->id),
                'file_url' => $d->file_path ? Storage::url($d->file_path) : null,
            ]);

        $statsBase = DocumentUpload::query()
            ->where('document_type_id', $documentType->id);

        $stats = [
            'total' => (clone $statsBase)->count(),
            'active' => $isRevisionControlled
                ? (clone $statsBase)->where('status', 'Active')->count()
                : 0,
            'obsolete' => $isRevisionControlled
                ? (clone $statsBase)->where('status', 'Obsolete')->count()
                : 0,
        ];

        return Inertia::render('Documents/Show', [
            'documentType' => [
                'id' => $documentType->id,
                'code' => $documentType->code,
                'name' => $documentType->title,
                'file_type' => $documentType->storage,
                'requires_revision' => $isRevisionControlled,
            ],
            'documents' => $documents,
            'filters' => [
                'q' => $q,
                'status' => $isRevisionControlled ? $status : 'All',
                'sort' => $sort,
                'per_page' => $perPage,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'stats' => $stats,
        ]);
    }

    public function upload(Request $request, DocumentType $documentType)
    {
        $isRevisionControlled = $this->isRevisionControlled($documentType);

        $rules = [
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:20480'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];

        if ($isRevisionControlled) {
            $rules['revision'] = ['required', 'string', 'max:50'];
        }

        $data = $request->validate($rules);

        $files = $request->file('files', []);

        if ($isRevisionControlled && count($files) > 1) {
            return back()->withErrors([
                'files' => 'Multiple upload is not allowed for revision-controlled documents.',
            ]);
        }

        $created = 0;

        foreach ($files as $file) {
            $path = $file->store("qms/{$documentType->code}", 'public');

            if ($isRevisionControlled) {
                DocumentUpload::where('document_type_id', $documentType->id)
                    ->where('status', 'Active')
                    ->update(['status' => 'Obsolete']);
            }

            DocumentUpload::create([
                'document_type_id' => $documentType->id,
                'uploaded_by' => $request->user()->id,
                'revision' => $isRevisionControlled ? trim((string) $data['revision']) : null,
                'status' => $isRevisionControlled ? 'Active' : null,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'storage_disk' => 'public',
                'remarks' => $data['remarks'] ?? null,
            ]);

            $created++;
        }

        return back()->with('success', $created > 1
            ? 'Files uploaded successfully.'
            : 'File uploaded successfully.');
    }

    public function preview(DocumentUpload $upload)
    {
        $upload->loadMissing(['documentType.series', 'ofiRecord', 'dcrRecord']);

        if ($upload->ofi_record_id && $upload->ofiRecord) {
            $this->activityLogService->log([
                'module' => 'ofi',
                'action' => 'previewed',
                'entity_type' => DocumentUpload::class,
                'entity_id' => $upload->id,
                'record_label' => $upload->ofiRecord->ofi_no ?: 'OFI #' . $upload->ofiRecord->id,
                'file_type' => $this->activityLogService->extensionFromFileName($upload->file_name),
                'description' => 'Previewed published OFI document ' . ($upload->file_name ?: 'file'),
            ]);

            return $this->previewLatestOfiAsPdf($upload);
        }

        if ($upload->dcr_record_id && $upload->dcrRecord) {
            $this->activityLogService->log([
                'module' => 'dcr',
                'action' => 'previewed',
                'entity_type' => DocumentUpload::class,
                'entity_id' => $upload->id,
                'record_label' => $upload->dcrRecord->dcr_no ?: 'DCR #' . $upload->dcrRecord->id,
                'file_type' => $this->activityLogService->extensionFromFileName($upload->file_name),
                'description' => 'Previewed published DCR document ' . ($upload->file_name ?: 'file'),
            ]);

            return $this->previewLatestDcrAsPdf($upload);
        }

        abort_unless(
            $this->documentPreviewService->canPreview($upload),
            404,
            'This file type is not supported for preview.'
        );

        $this->activityLogService->log([
            'module' => $this->resolveModuleFromUpload($upload),
            'action' => 'previewed',
            'entity_type' => DocumentUpload::class,
            'entity_id' => $upload->id,
            'record_label' => $this->resolveDocumentRecordLabel($upload),
            'file_type' => $this->activityLogService->extensionFromFileName($upload->file_name),
            'description' => 'Previewed document ' . $this->resolveDocumentRecordLabel($upload),
        ]);

        return $this->documentPreviewService->preview($upload);
    }

    public function download(DocumentUpload $upload)
    {
        $upload->loadMissing(['documentType.series', 'ofiRecord', 'dcrRecord']);

        if ($upload->ofi_record_id && $upload->ofiRecord) {
            $this->activityLogService->log([
                'module' => 'ofi',
                'action' => 'downloaded',
                'entity_type' => DocumentUpload::class,
                'entity_id' => $upload->id,
                'record_label' => $upload->ofiRecord->ofi_no ?: 'OFI #' . $upload->ofiRecord->id,
                'file_type' => $this->activityLogService->extensionFromFileName($upload->file_name),
                'description' => 'Downloaded published OFI document ' . ($upload->file_name ?: 'file'),
            ]);

            return $this->downloadLatestOfiDocx($upload);
        }

        if ($upload->dcr_record_id && $upload->dcrRecord) {
            $this->activityLogService->log([
                'module' => 'dcr',
                'action' => 'downloaded',
                'entity_type' => DocumentUpload::class,
                'entity_id' => $upload->id,
                'record_label' => $upload->dcrRecord->dcr_no ?: 'DCR #' . $upload->dcrRecord->id,
                'file_type' => $this->activityLogService->extensionFromFileName($upload->file_name),
                'description' => 'Downloaded published DCR document ' . ($upload->file_name ?: 'file'),
            ]);

            return $this->downloadLatestDcrDocx($upload);
        }

        $this->activityLogService->log([
            'module' => $this->resolveModuleFromUpload($upload),
            'action' => 'downloaded',
            'entity_type' => DocumentUpload::class,
            'entity_id' => $upload->id,
            'record_label' => $this->resolveDocumentRecordLabel($upload),
            'file_type' => $this->activityLogService->extensionFromFileName($upload->file_name),
            'description' => 'Downloaded document ' . $this->resolveDocumentRecordLabel($upload),
        ]);

        return $this->documentDownloadService->download($upload);
    }

    private function isRevisionControlled(DocumentType $documentType): bool
    {
        if (isset($documentType->requires_revision)) {
            return (bool) $documentType->requires_revision;
        }

        return str_starts_with(strtoupper((string) $documentType->code), 'F-QMS');
    }

    private function resolveModuleFromUpload(DocumentUpload $upload): string
    {
        return strtoupper((string) $upload->documentType?->series?->code_prefix) === 'MANUAL'
            ? 'manuals'
            : 'documents';
    }

    private function resolveDocumentRecordLabel(DocumentUpload $upload): string
    {
        return $upload->documentType?->code
            ?: $upload->file_name
            ?: 'Upload #' . $upload->id;
    }

    private function downloadLatestOfiDocx(DocumentUpload $upload)
    {
        [$outputPath, $fileName] = $this->generateLatestOfiTempFile($upload);

        return response()->download($outputPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => ResponseHeaderBag::DISPOSITION_ATTACHMENT . '; filename="' . addslashes($fileName) . '"',
        ])->deleteFileAfterSend(true);
    }

    private function previewLatestOfiAsPdf(DocumentUpload $upload)
    {
        [$docxPath, $fileName] = $this->generateLatestOfiTempFile($upload);
        [$pdfPath, $pdfName] = $this->convertGeneratedDocxToPdfUsingExistingConverter($docxPath, $fileName);

        return response()->file($pdfPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ResponseHeaderBag::DISPOSITION_INLINE . '; filename="' . addslashes($pdfName) . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'Pragma' => 'public',
        ])->deleteFileAfterSend(true);
    }

    private function generateLatestOfiTempFile(DocumentUpload $upload): array
    {
        $upload->loadMissing('ofiRecord');

        abort_unless($upload->ofiRecord, 404, 'Linked OFI record not found.');

        $templatePath = base_path('templates/F-QMS-007_template_fixed_v6.docx');
        abort_unless(file_exists($templatePath), 500, 'OFI template file not found.');

        $tmpDir = storage_path('app/ofi_forms_tmp');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $baseName = pathinfo($upload->file_name ?: 'OFI_record.docx', PATHINFO_FILENAME);
        $safeBaseName = preg_replace('/[^A-Za-z0-9 _\-\(\)]/', '', $baseName);
        $safeBaseName = trim(preg_replace('/\s+/', ' ', $safeBaseName));

        if ($safeBaseName === '') {
            $safeBaseName = 'OFI_record';
        }

        $fileName = $safeBaseName . '.docx';
        $outputPath = $tmpDir . '/' . uniqid('ofi_', true) . '_' . $fileName;

        $generator = new OFIFormGenerator($templatePath);
        $generator->generate($upload->ofiRecord->data ?? [], $outputPath);

        return [$outputPath, $fileName];
    }

    private function downloadLatestDcrDocx(DocumentUpload $upload)
    {
        [$outputPath, $fileName] = $this->generateLatestDcrTempFile($upload);

        return response()->download($outputPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => ResponseHeaderBag::DISPOSITION_ATTACHMENT . '; filename="' . addslashes($fileName) . '"',
        ])->deleteFileAfterSend(true);
    }

    private function previewLatestDcrAsPdf(DocumentUpload $upload)
    {
        [$docxPath, $fileName] = $this->generateLatestDcrTempFile($upload);
        [$pdfPath, $pdfName] = $this->convertGeneratedDocxToPdfUsingExistingConverter($docxPath, $fileName);

        return response()->file($pdfPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ResponseHeaderBag::DISPOSITION_INLINE . '; filename="' . addslashes($pdfName) . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'Pragma' => 'public',
        ])->deleteFileAfterSend(true);
    }

    private function generateLatestDcrTempFile(DocumentUpload $upload): array
    {
        $upload->loadMissing('dcrRecord');

        abort_unless($upload->dcrRecord, 404, 'Linked DCR record not found.');

        $templatePath = base_path('templates/F-QMS-001 _template.docx');
        abort_unless(file_exists($templatePath), 500, 'DCR template file not found.');

        $tmpDir = storage_path('app/dcr_forms_tmp');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $baseName = pathinfo($upload->file_name ?: 'DCR_record.docx', PATHINFO_FILENAME);
        $safeBaseName = preg_replace('/[^A-Za-z0-9 _\-\(\)]/', '', $baseName);
        $safeBaseName = trim(preg_replace('/\s+/', ' ', $safeBaseName));

        if ($safeBaseName === '') {
            $safeBaseName = 'DCR_record';
        }

        $fileName = $safeBaseName . '.docx';
        $outputPath = $tmpDir . '/' . uniqid('dcr_', true) . '_' . $fileName;

        $generator = new DCRFormGenerator($templatePath);
        $generator->generate($upload->dcrRecord->data ?? [], $outputPath);

        return [$outputPath, $fileName];
    }

    private function convertGeneratedDocxToPdfUsingExistingConverter(string $docxPath, string $originalFileName): array
    {
        if (!is_file($docxPath)) {
            throw new RuntimeException("Generated DOCX file not found: {$docxPath}");
        }

        $tmpDir = storage_path('app/generated_preview_tmp');
        if (!is_dir($tmpDir) && !mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
            throw new RuntimeException("Unable to create generated preview temp directory: {$tmpDir}");
        }

        $safeBaseName = pathinfo($originalFileName, PATHINFO_FILENAME);
        $safeBaseName = preg_replace('/[^A-Za-z0-9 _\-\(\)]/', '', $safeBaseName);
        $safeBaseName = trim(preg_replace('/\s+/', ' ', $safeBaseName));

        if ($safeBaseName === '') {
            $safeBaseName = 'preview';
        }

        $pdfPath = $tmpDir . '/' . uniqid('generated_pdf_', true) . '_' . $safeBaseName . '.pdf';

        try {
            $this->officeToPdfConverter->convertToPdf($docxPath, $pdfPath);
        } finally {
            @unlink($docxPath);
        }

        if (!is_file($pdfPath)) {
            throw new RuntimeException("Converted PDF was not created: {$pdfPath}");
        }

        return [$pdfPath, $safeBaseName . '.pdf'];
    }
}