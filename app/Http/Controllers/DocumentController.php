<?php

namespace App\Http\Controllers;

use App\Models\DocumentSeries;
use App\Models\DocumentType;
use Illuminate\Http\Request;
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

        // ✅ Search (code or title)
        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('code', 'like', "%{$q}%")
                    ->orWhere('title', 'like', "%{$q}%")
                    ->orWhere('storage', 'like', "%{$q}%");
            });
        }

        // ✅ File type filter
        if ($fileType !== 'All') {
            if ($fileType === '-') {
                $query->where(function ($qq) {
                    $qq->whereNull('storage')->orWhere('storage', '=', '-');
                });
            } else {
                $query->where('storage', 'like', "%{$fileType}%");
            }
        }

        // ✅ Sort
        if ($sort === 'name_asc')
            $query->orderBy('title');
        else
            $query->orderBy('code'); // code_asc default

        $types = $query->get();

        // ✅ Map DB columns → what your Vue expects
        $documentTypes = $types->map(function ($t) {
            return [
                'id' => $t->id,
                'code' => $t->code,
                'name' => $t->title,
                'file_type' => $t->storage,
                'documents_count' => 0,      // later connect to uploads table
                'latest_upload_at' => null,  // later connect to uploads table
            ];
        });

        return Inertia::render('Documents/Index', [
            'documentTypes' => $documentTypes,
            'filters' => [
                'q' => $q,
                'fileType' => $fileType,
                'hasUploads' => $request->get('hasUploads', 'All'), // not used yet
                'sort' => $sort,
                'view' => $view,
            ],
        ]);
    }

    public function show($documentType)
    {
        // later: show uploads for this code
        return Inertia::render('Documents/Show', [
            'documentType' => $documentType,
            'documents' => [],
        ]);
    }
}