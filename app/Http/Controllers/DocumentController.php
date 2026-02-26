<?php

namespace App\Http\Controllers;

use App\Models\DocumentSeries;
use App\Models\DocumentType;
use App\Models\DocumentUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $fileType = $request->get('fileType', 'All');
        $sort = $request->get('sort', 'code_asc');
        $view = $request->get('view', 'group');

        // ✅ Get R-QMS series
        $series = DocumentSeries::where('code_prefix', 'R-QMS')->firstOrFail();

        $query = DocumentType::query()
            ->where('series_id', $series->id);

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('code', 'like', "%{$q}%")
                    ->orWhere('title', 'like', "%{$q}%")
                    ->orWhere('storage', 'like', "%{$q}%");
            });
        }

        if ($fileType !== 'All') {
            if ($fileType === '-') {
                $query->where(function ($qq) {
                    $qq->whereNull('storage')->orWhere('storage', '=', '-');
                });
            } else {
                $query->where('storage', 'like', "%{$fileType}%");
            }
        }

        if ($sort === 'name_asc')
            $query->orderBy('title');
        else
            $query->orderBy('code');

        $types = $query->get();

        $documentTypes = $types->map(function ($t) {
            return [
                'id' => $t->id,
                'code' => $t->code,
                'name' => $t->title,
                'file_type' => $t->storage,
                'documents_count' => 0,
                'latest_upload_at' => null,
            ];
        });

        return Inertia::render('Documents/Index', [
            'documentTypes' => $documentTypes,
            'filters' => [
                'q' => $q,
                'fileType' => $fileType,
                'hasUploads' => $request->get('hasUploads', 'All'),
                'sort' => $sort,
                'view' => $view,
            ],
        ]);
    }

    // ✅ FIXED: Use model binding so Vue receives full document type object
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
                'file_url' => Storage::url($d->file_path),
            ]);

        return Inertia::render('Documents/Show', [
            'documentType' => [
                'id' => $documentType->id,
                'code' => $documentType->code,
                'name' => $documentType->title,      // Vue expects name

                // optional later:
                // 'requires_revision' => $documentType->requires_revision,
            ],
            'documents' => $documents,
        ]);
    }

    // ✅ NEW: Upload endpoint used by your modal
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
}