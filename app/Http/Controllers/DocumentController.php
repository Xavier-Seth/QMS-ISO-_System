<?php

namespace App\Http\Controllers;

use App\Models\DocumentSeries;
use App\Models\DocumentType;
use App\Models\DocumentUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Illuminate\Support\Facades\File;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $seriesCode = $request->get('series', 'All'); // <-- NEW (replaces fileType/hasUploads)
        $sort = $request->get('sort', 'code_asc');
        $view = $request->get('view', 'group');

        // ✅ Series dropdown options (exclude MANUAL)
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
            ->withMax('uploads', 'created_at'); // uploads_max_created_at

        // ✅ Filter by selected series (R-QMS / F-QMS / IPCR)
        if ($seriesCode !== 'All') {
            $selected = DocumentSeries::where('code_prefix', $seriesCode)->first();
            if ($selected) {
                $query->where('series_id', $selected->id);
            } else {
                // if someone passes an invalid series, show nothing
                $query->whereRaw('1=0');
            }
        } else {
            // If "All", still exclude MANUAL results
            $query->whereHas('series', fn($qq) => $qq->where('code_prefix', '!=', 'MANUAL'));
        }

        // ✅ Search
        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('code', 'like', "%{$q}%")
                    ->orWhere('title', 'like', "%{$q}%")
                    ->orWhere('storage', 'like', "%{$q}%");
            });
        }

        // ✅ Sorting
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

        $documentTypes = $types->map(function ($t) {
            return [
                'id' => $t->id,
                'code' => $t->code,
                'name' => $t->title,
                'file_type' => $t->storage, // keep if you still want to display it somewhere
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
            'seriesOptions' => $seriesOptions, // ✅ NEW
            'filters' => [
                'q' => $q,
                'series' => $seriesCode, // ✅ NEW
                'sort' => $sort,
                'view' => $view,
            ],
        ]);
    }
    // Show documents under a document type
    public function show(DocumentType $documentType)
    {
        $documents = DocumentUpload::with('uploader')
            ->where('document_type_id', $documentType->id)
            ->latest()
            ->get()
            ->map(fn($d) => [
                'id' => $d->id,
                'file_name' => $d->file_name,
                'revision' => $d->revision,
                'status' => $d->status,
                'uploaded_by_name' => $d->uploader?->name ?? '—',
                'created_at' => $d->created_at,

                // ✅ NEW: Use preview & download routes
                'preview_url' => route('documents.uploads.preview', $d->id),
                'download_url' => route('documents.uploads.download', $d->id),
            ]);

        return Inertia::render('Documents/Show', [
            'documentType' => [
                'id' => $documentType->id,
                'code' => $documentType->code,
                'name' => $documentType->title,
                'file_type' => $documentType->storage,
            ],
            'documents' => $documents,
        ]);
    }

    // Upload file
    public function upload(Request $request, DocumentType $documentType)
    {
        $isRevisionControlled = str_starts_with(strtoupper($documentType->code), 'F-QMS');

        $rules = [
            'file' => ['required', 'file', 'max:20480'], // 20MB
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];

        if ($isRevisionControlled) {
            $rules['revision'] = ['required', 'string', 'max:50'];
        }

        $data = $request->validate($rules);

        $file = $data['file'];

        // store in public disk
        $path = $file->store("qms/{$documentType->code}", 'public');

        // F-QMS rule: only one active
        if ($isRevisionControlled) {
            DocumentUpload::where('document_type_id', $documentType->id)
                ->where('status', 'Active')
                ->update(['status' => 'Obsolete']);
        }

        DocumentUpload::create([
            'document_type_id' => $documentType->id,
            'uploaded_by' => $request->user()->id,
            'revision' => $isRevisionControlled ? $data['revision'] : null,
            'status' => $isRevisionControlled ? 'Active' : null,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'remarks' => $data['remarks'] ?? null,
        ]);

        return back();
    }

    // =========================
    // NEW: Preview file in browser
    // =========================
    public function preview(DocumentUpload $upload)
    {
        $disk = Storage::disk('public');

        abort_unless($disk->exists($upload->file_path), 404);

        $absolutePath = $disk->path($upload->file_path);
        $mime = File::mimeType($absolutePath) ?? 'application/octet-stream';

        return response()->file($absolutePath, [
            'Content-Type' => $mime,
            'Content-Disposition' => ResponseHeaderBag::DISPOSITION_INLINE . '; filename="' . addslashes($upload->file_name) . '"',
        ]);
    }

    // =========================
    // NEW: Force file download
    // =========================
    public function download(DocumentUpload $upload)
    {
        $disk = Storage::disk('public');

        abort_unless($disk->exists($upload->file_path), 404);

        $absolutePath = $disk->path($upload->file_path);

        // Force ATTACHMENT + keep original filename
        return response()->download($absolutePath, $upload->file_name, [
            'Content-Disposition' => ResponseHeaderBag::DISPOSITION_ATTACHMENT . '; filename="' . addslashes($upload->file_name) . '"',
        ]);
    }
}