<?php

namespace App\Http\Controllers;

use App\Models\DocumentSeries;
use App\Models\DocumentType;
use App\Models\DocumentUpload;
use App\Services\DCRFormGenerator;
use App\Services\OFIFormGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class DocumentController extends Controller
{
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
        $isRevisionControlled = $this->isRevisionControlled($documentType);

        $query = DocumentUpload::query()
            ->with(['uploader', 'ofiRecord', 'dcrRecord'])
            ->where('document_type_id', $documentType->id);

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('file_name', 'like', "%{$q}%")
                    ->orWhere('revision', 'like', "%{$q}%")
                    ->orWhere('remarks', 'like', "%{$q}%");
            });
        }

        if ($isRevisionControlled && in_array($status, ['Active', 'Obsolete'], true)) {
            $query->where('status', $status);
        }

        $documents = $query
            ->latest()
            ->paginate(10)
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
        if ($upload->ofi_record_id && $upload->ofiRecord) {
            return $this->serveLatestOfiDocxInline($upload);
        }

        if ($upload->dcr_record_id && $upload->dcrRecord) {
            return $this->serveLatestDcrDocxInline($upload);
        }

        $disk = Storage::disk('public');

        abort_unless($upload->file_path && $disk->exists($upload->file_path), 404);

        $absolutePath = $disk->path($upload->file_path);
        $mime = File::mimeType($absolutePath) ?? 'application/octet-stream';

        return response()->file($absolutePath, [
            'Content-Type' => $mime,
            'Content-Disposition' => ResponseHeaderBag::DISPOSITION_INLINE . '; filename="' . addslashes($upload->file_name) . '"',
        ]);
    }

    public function download(DocumentUpload $upload)
    {
        if ($upload->ofi_record_id && $upload->ofiRecord) {
            return $this->downloadLatestOfiDocx($upload);
        }

        if ($upload->dcr_record_id && $upload->dcrRecord) {
            return $this->downloadLatestDcrDocx($upload);
        }

        $disk = Storage::disk('public');

        abort_unless($upload->file_path && $disk->exists($upload->file_path), 404);

        $absolutePath = $disk->path($upload->file_path);

        return response()->download($absolutePath, $upload->file_name, [
            'Content-Disposition' => ResponseHeaderBag::DISPOSITION_ATTACHMENT . '; filename="' . addslashes($upload->file_name) . '"',
        ]);
    }

    private function isRevisionControlled(DocumentType $documentType): bool
    {
        if (isset($documentType->requires_revision)) {
            return (bool) $documentType->requires_revision;
        }

        return str_starts_with(strtoupper((string) $documentType->code), 'F-QMS');
    }

    private function downloadLatestOfiDocx(DocumentUpload $upload)
    {
        [$outputPath, $fileName] = $this->generateLatestOfiTempFile($upload);

        return response()->download($outputPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => ResponseHeaderBag::DISPOSITION_ATTACHMENT . '; filename="' . addslashes($fileName) . '"',
        ])->deleteFileAfterSend(true);
    }

    private function serveLatestOfiDocxInline(DocumentUpload $upload)
    {
        [$outputPath, $fileName] = $this->generateLatestOfiTempFile($upload);

        return response()->file($outputPath, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => ResponseHeaderBag::DISPOSITION_INLINE . '; filename="' . addslashes($fileName) . '"',
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

    private function serveLatestDcrDocxInline(DocumentUpload $upload)
    {
        [$outputPath, $fileName] = $this->generateLatestDcrTempFile($upload);

        return response()->file($outputPath, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => ResponseHeaderBag::DISPOSITION_INLINE . '; filename="' . addslashes($fileName) . '"',
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
}