<?php

namespace App\Http\Controllers;

use App\Models\DocumentSeries;
use App\Models\DocumentType;
use App\Models\DocumentTypeRevision;
use App\Models\DocumentUpload;
use App\Services\ActivityLogService;
use App\Services\DCRFormGenerator;
use App\Services\DocumentPreview\DocumentDownloadService;
use App\Services\DocumentPreview\DocumentPreviewService;
use App\Services\DocumentPreview\OfficeToPdfConverter;
use App\Services\OFIFormGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use App\Services\CARFormGenerator;
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
        $q = trim((string) $request->input('q', ''));
        $seriesCode = (string) $request->input('series', 'All');
        $sort = (string) $request->input('sort', 'code_asc');
        $view = (string) $request->input('view', 'group');
        $status = (string) $request->input('status', 'All');
        $mode = (string) $request->input('mode', '');

        $allowedTypeStatuses = ['All', 'Active', 'Obsolete'];
        if (!in_array($status, $allowedTypeStatuses, true)) {
            $status = 'All';
        }

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

        if ($mode === 'performance') {
            $query->whereHas('series', function ($qq) {
                $qq->whereIn('code_prefix', ['IPCR', 'DPCR', 'UPCR']);
            });

            if ($seriesCode !== 'All' && $seriesCode !== '') {
                $query->whereHas('series', function ($qq) use ($seriesCode) {
                    $qq->where('code_prefix', $seriesCode);
                });
            }
        } elseif ($seriesCode !== 'All' && $seriesCode !== '') {
            $selected = DocumentSeries::query()
                ->where('code_prefix', $seriesCode)
                ->first();

            if ($selected) {
                $query->where('series_id', $selected->id);
            } else {
                $query->whereRaw('1=0');
            }
        } else {
            $query->whereHas('series', fn($qq) => $qq->where('code_prefix', '!=', 'MANUAL'));
        }

        if ($status !== 'All') {
            $normalizedStatus = strtolower($status);

            $query->whereRaw(
                "LOWER(COALESCE(status, 'active')) = ?",
                [$normalizedStatus]
            );
        }

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('code', 'like', "%{$q}%")
                    ->orWhere('title', 'like', "%{$q}%")
                    ->orWhere('storage', 'like', "%{$q}%")
                    ->orWhere('status', 'like', "%{$q}%")
                    ->orWhere('status_note', 'like', "%{$q}%");
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
            $normalizedStatus = strtolower((string) ($t->status ?: 'active'));

            return [
                'id' => $t->id,
                'code' => $t->code,
                'name' => $t->title,
                'file_type' => $t->storage,
                'requires_revision' => $this->isRevisionControlled($t),
                'is_performance_form' => $this->isPerformanceFormType($t),
                'documents_count' => (int) $t->uploads_count,
                'latest_upload_at' => $t->uploads_max_created_at,
                'status' => $normalizedStatus === 'obsolete' ? 'Obsolete' : 'Active',
                'status_note' => $t->status_note,
                'can_upload' => !$t->isObsolete(),
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
                'status' => $status,
                'mode' => $mode,
            ],
        ]);
    }

    public function storeType(Request $request)
    {
        $data = $request->validate([
            'series_id' => ['required', 'integer', 'exists:document_series,id'],
            'document_no' => ['required', 'integer', 'min:1', 'max:999'],
            'title' => ['required', 'string', 'max:255'],
            'storage' => ['required', 'string', 'max:255'],
            'status_note' => ['nullable', 'string', 'max:1000'],
            'requires_revision' => ['nullable', 'boolean'],
        ], [
            'series_id.required' => 'Please select a document series.',
            'series_id.exists' => 'The selected document series is invalid.',
            'document_no.required' => 'Document number is required.',
            'document_no.integer' => 'Document number must be a whole number.',
            'document_no.min' => 'Document number must be at least 1.',
            'document_no.max' => 'Document number must not be greater than 999.',
            'title.required' => 'Document title is required.',
            'storage.required' => 'Storage / file type is required.',
        ]);

        $series = DocumentSeries::query()->findOrFail($data['series_id']);

        if (strtoupper((string) $series->code_prefix) === 'MANUAL') {
            throw ValidationException::withMessages([
                'series_id' => 'Manual series cannot be created from the Documents module.',
            ]);
        }

        $documentNo = (int) $data['document_no'];
        $generatedCode = strtoupper($series->code_prefix) . '-' . str_pad((string) $documentNo, 3, '0', STR_PAD_LEFT);

        $existing = DocumentType::query()
            ->whereRaw('LOWER(code) = ?', [strtolower($generatedCode)])
            ->exists();

        if ($existing) {
            throw ValidationException::withMessages([
                'document_no' => "The generated code {$generatedCode} already exists.",
            ]);
        }

        $documentType = DocumentType::create([
            'series_id' => $series->id,
            'code' => $generatedCode,
            'title' => trim($data['title']),
            'storage' => trim($data['storage']),
            'status' => 'Active',
            'status_note' => filled($data['status_note'] ?? null)
                ? trim($data['status_note'])
                : null,
            'requires_revision' => (bool) ($data['requires_revision'] ?? false),
        ]);

        $this->activityLogService->log([
            'module' => 'documents',
            'action' => 'created',
            'entity_type' => DocumentType::class,
            'entity_id' => $documentType->id,
            'record_label' => $documentType->code,
            'file_type' => null,
            'description' => 'Created document type ' . $documentType->code . ' - ' . $documentType->title,
        ]);

        return back()->with('success', "Document type {$documentType->code} created.");
    }

    public function markObsolete(Request $request, DocumentType $documentType)
    {
        $data = $request->validate([
            'status_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $documentType->update([
            'status' => 'Obsolete',
            'status_note' => filled($data['status_note'] ?? null)
                ? trim($data['status_note'])
                : 'Marked as obsolete by admin.',
        ]);

        $this->activityLogService->log([
            'module' => 'documents',
            'action' => 'updated',
            'entity_type' => DocumentType::class,
            'entity_id' => $documentType->id,
            'record_label' => $documentType->code,
            'file_type' => null,
            'description' => 'Marked document type ' . $documentType->code . ' as obsolete.',
        ]);

        return back()->with('success', "{$documentType->code} marked as obsolete.");
    }

    public function destroyType(DocumentType $documentType)
    {
        $documentType->load(['series']);
        $documentType->loadCount('uploads');

        $code = $documentType->code;
        $title = $documentType->title;
        $uploadCount = (int) $documentType->uploads_count;

        $isManualSeries = strtoupper((string) $documentType->series?->code_prefix) === 'MANUAL';

        if ($isManualSeries) {
            return back()->withErrors([
                'delete' => 'Manual document types cannot be deleted from the Documents module.',
            ]);
        }

        $hasOfiReferences = DB::table('ofi_records')
            ->where('document_type_id', $documentType->id)
            ->exists();

        if ($hasOfiReferences) {
            return back()->withErrors([
                'delete' => "Cannot delete {$code} because it is still referenced by OFI records.",
            ]);
        }

        $hasDcrReferences = DB::table('dcr_records')
            ->where('document_type_id', $documentType->id)
            ->exists();

        if ($hasDcrReferences) {
            return back()->withErrors([
                'delete' => "Cannot delete {$code} because it is still referenced by DCR records.",
            ]);
        }

        $filesToDelete = [];
        $previewFilesToDelete = [];

        DB::transaction(function () use ($documentType, &$filesToDelete, &$previewFilesToDelete) {
            $documentType->load(['uploads', 'series']);

            foreach ($documentType->uploads as $upload) {
                $storageDisk = $upload->getStorageDiskName();

                if ($upload->file_path) {
                    $filesToDelete[] = [
                        'disk' => $storageDisk,
                        'path' => $upload->file_path,
                    ];
                }

                if ($upload->hasPreviewCache()) {
                    $previewDisk = $upload->getPreviewDiskName();

                    if ($previewDisk && $upload->preview_path) {
                        $previewFilesToDelete[] = [
                            'disk' => $previewDisk,
                            'path' => $upload->preview_path,
                        ];
                    }
                }

                $upload->delete();
            }

            DocumentTypeRevision::where('document_type_id', $documentType->id)->delete();

            $documentType->delete();
        });

        DB::afterCommit(function () use ($filesToDelete, $previewFilesToDelete) {
            foreach ($filesToDelete as $file) {
                if (
                    !empty($file['disk']) &&
                    !empty($file['path']) &&
                    Storage::disk($file['disk'])->exists($file['path'])
                ) {
                    Storage::disk($file['disk'])->delete($file['path']);
                }
            }

            foreach ($previewFilesToDelete as $file) {
                if (
                    !empty($file['disk']) &&
                    !empty($file['path']) &&
                    Storage::disk($file['disk'])->exists($file['path'])
                ) {
                    Storage::disk($file['disk'])->delete($file['path']);
                }
            }
        });

        $this->activityLogService->log([
            'module' => 'documents',
            'action' => 'deleted',
            'entity_type' => DocumentType::class,
            'entity_id' => null,
            'record_label' => $code,
            'file_type' => null,
            'description' => 'Deleted document type ' . $code . ' - ' . $title . ' and removed ' . $uploadCount . ' related upload(s).',
        ]);

        return back()->with('success', 'Document type permanently deleted.');
    }

    public function show(Request $request, DocumentType $documentType)
    {
        $q = trim((string) $request->input('q', ''));
        $status = (string) $request->input('status', 'All');
        $sort = (string) $request->input('sort', 'latest');
        $dateFrom = trim((string) $request->input('date_from', ''));
        $dateTo = trim((string) $request->input('date_to', ''));
        $selectedRecordType = $request->filled('record_type')
            ? strtoupper((string) $request->input('record_type'))
            : null;
        $selectedYear = $request->filled('year') ? (int) $request->input('year') : null;
        $selectedPeriod = $request->filled('period') ? (string) $request->input('period') : null;

        $allowedPerPage = [10, 25, 50, 100];
        $perPage = (int) $request->input('per_page', 10);
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
        $isPerformanceForm = $this->isPerformanceFormType($documentType);

        $normalizedTypeStatus = strtolower((string) ($documentType->status ?: 'active'));

        $statsBase = DocumentUpload::query()
            ->where('document_type_id', $documentType->id);

        $stats = [
            'total' => (clone $statsBase)->count(),
            'active' => $isRevisionControlled && !$isPerformanceForm
                ? (clone $statsBase)->where('status', 'Active')->count()
                : 0,
            'obsolete' => $isRevisionControlled && !$isPerformanceForm
                ? (clone $statsBase)->where('status', 'Obsolete')->count()
                : 0,
        ];

        $documentTypePayload = [
            'id' => $documentType->id,
            'code' => $documentType->code,
            'name' => $documentType->title,
            'file_type' => $documentType->storage,
            'requires_revision' => $isRevisionControlled,
            'is_performance_form' => $isPerformanceForm,
            'status' => $normalizedTypeStatus === 'obsolete' ? 'Obsolete' : 'Active',
            'status_note' => $documentType->status_note,
            'is_obsolete' => $documentType->isObsolete(),
            'can_upload' => !$documentType->isObsolete(),
            'series' => [
                'code_prefix' => $documentType->series?->code_prefix,
                'name' => $documentType->series?->name,
            ],
        ];

        if ($isPerformanceForm) {
            $groupedBaseQuery = DocumentUpload::query()
                ->where('document_type_id', $documentType->id)
                ->whereNotNull('performance_record_type')
                ->whereNotNull('year')
                ->whereNotNull('period');

            if ($q !== '') {
                $groupedBaseQuery->where(function ($qq) use ($q) {
                    $qq->where('file_name', 'like', "%{$q}%")
                        ->orWhere('remarks', 'like', "%{$q}%")
                        ->orWhereHas('uploader', function ($uploaderQuery) use ($q) {
                            $uploaderQuery->where('name', 'like', "%{$q}%")
                                ->orWhere('username', 'like', "%{$q}%")
                                ->orWhere('email', 'like', "%{$q}%");
                        });
                });
            }

            if ($dateFrom !== '') {
                $groupedBaseQuery->whereDate('created_at', '>=', $dateFrom);
            }

            if ($dateTo !== '') {
                $groupedBaseQuery->whereDate('created_at', '<=', $dateTo);
            }

            $groupRows = (clone $groupedBaseQuery)
                ->selectRaw('performance_record_type, year, period, COUNT(*) as files_count, MAX(created_at) as latest_uploaded_at')
                ->groupBy('performance_record_type', 'year', 'period')
                ->orderByRaw("CASE performance_record_type WHEN 'TARGET' THEN 1 WHEN 'ACCOMPLISHMENT' THEN 2 ELSE 3 END")
                ->orderByDesc('year')
                ->orderByRaw("CASE period WHEN 'JAN_JUN' THEN 1 WHEN 'JUL_DEC' THEN 2 ELSE 3 END")
                ->get();

            $recordTypeGroups = $groupRows
                ->groupBy('performance_record_type')
                ->map(function ($recordTypeRows, $recordType) {
                    $yearGroups = $recordTypeRows
                        ->groupBy('year')
                        ->map(function ($yearRows, $year) {
                            $periods = $yearRows->map(function ($row) {
                                return [
                                    'period' => (string) $row->period,
                                    'period_name' => $this->resolvePerformancePeriodName((string) $row->period),
                                    'files_count' => (int) $row->files_count,
                                    'latest_uploaded_at' => $row->latest_uploaded_at,
                                ];
                            })->values();

                            return [
                                'year' => (int) $year,
                                'total_files' => (int) $periods->sum('files_count'),
                                'periods_count' => $periods->count(),
                                'periods' => $periods,
                            ];
                        })
                        ->sortByDesc('year')
                        ->values();

                    return [
                        'record_type' => (string) $recordType,
                        'record_type_name' => match ((string) $recordType) {
                            'TARGET' => 'Target',
                            'ACCOMPLISHMENT' => 'Accomplishment',
                            default => 'Record Type',
                        },
                        'total_files' => (int) $yearGroups->sum('total_files'),
                        'years_count' => $yearGroups->count(),
                        'years' => $yearGroups,
                    ];
                })
                ->values();

            $hasValidSelectedRecordType = $selectedRecordType !== null
                && $recordTypeGroups->contains(fn($group) => (string) $group['record_type'] === $selectedRecordType);

            if (!$hasValidSelectedRecordType && $recordTypeGroups->isNotEmpty()) {
                $selectedRecordType = (string) $recordTypeGroups->first()['record_type'];
            }

            $selectedRecordTypeGroup = $recordTypeGroups->firstWhere('record_type', $selectedRecordType);
            $availableYearsForSelectedRecordType = collect($selectedRecordTypeGroup['years'] ?? []);

            $hasValidSelectedYear = $selectedYear !== null
                && $availableYearsForSelectedRecordType->contains(fn($group) => (int) $group['year'] === $selectedYear);

            if (!$hasValidSelectedYear && $availableYearsForSelectedRecordType->isNotEmpty()) {
                $selectedYear = (int) $availableYearsForSelectedRecordType->first()['year'];
            }

            $selectedYearGroup = $availableYearsForSelectedRecordType->firstWhere('year', $selectedYear);
            $availablePeriodsForSelectedYear = collect($selectedYearGroup['periods'] ?? []);

            $hasValidSelectedPeriod = $selectedPeriod !== null
                && $availablePeriodsForSelectedYear->contains(fn($period) => (string) $period['period'] === $selectedPeriod);

            if (!$hasValidSelectedPeriod && $availablePeriodsForSelectedYear->isNotEmpty()) {
                $selectedPeriod = (string) $availablePeriodsForSelectedYear->first()['period'];
            }

            $selectedPeriodFiles = collect();

            if ($selectedRecordType !== null && $selectedYear !== null && $selectedPeriod !== null) {
                $periodFilesQuery = DocumentUpload::query()
                    ->with(['uploader', 'ofiRecord', 'dcrRecord', 'carRecord'])
                    ->where('document_type_id', $documentType->id)
                    ->where('performance_record_type', $selectedRecordType)
                    ->where('year', $selectedYear)
                    ->where('period', $selectedPeriod);

                if ($q !== '') {
                    $periodFilesQuery->where(function ($qq) use ($q) {
                        $qq->where('file_name', 'like', "%{$q}%")
                            ->orWhere('remarks', 'like', "%{$q}%")
                            ->orWhereHas('uploader', function ($uploaderQuery) use ($q) {
                                $uploaderQuery->where('name', 'like', "%{$q}%")
                                    ->orWhere('username', 'like', "%{$q}%")
                                    ->orWhere('email', 'like', "%{$q}%");
                            });
                    });
                }

                if ($dateFrom !== '') {
                    $periodFilesQuery->whereDate('created_at', '>=', $dateFrom);
                }

                if ($dateTo !== '') {
                    $periodFilesQuery->whereDate('created_at', '<=', $dateTo);
                }

                switch ($sort) {
                    case 'oldest':
                        $periodFilesQuery->orderBy('created_at', 'asc');
                        break;
                    case 'name_asc':
                        $periodFilesQuery->orderBy('file_name', 'asc');
                        break;
                    case 'name_desc':
                        $periodFilesQuery->orderBy('file_name', 'desc');
                        break;
                    case 'latest':
                    default:
                        $periodFilesQuery->orderBy('created_at', 'desc');
                        break;
                }

                $selectedPeriodFiles = $periodFilesQuery->get()->map(fn($d) => [
                    'id' => $d->id,
                    'file_name' => $d->file_name,
                    'revision' => $d->revision,
                    'status' => $d->status,
                    'year' => $d->year,
                    'period' => $d->period,
                    'period_name' => $this->resolvePerformancePeriodName((string) $d->period),
                    'performance_category' => $d->performance_category,
                    'performance_record_type' => $d->performance_record_type,
                    'uploaded_by_name' => $d->uploader?->name ?? '—',
                    'created_at' => $d->created_at,
                    'ofi_record_id' => $d->ofi_record_id,
                    'dcr_record_id' => $d->dcr_record_id,
                    'car_record_id' => $d->car_record_id,
                    'ofi_workflow_status' => $d->ofiRecord?->workflow_status,
                    'ofi_resolution_status' => $d->ofiRecord?->resolution_status,
                    'preview_url' => route('documents.uploads.preview', $d->id),
                    'download_url' => route('documents.uploads.download', $d->id),
                    'file_url' => $d->file_path ? Storage::url($d->file_path) : null,
                ])->values();
            }

            return Inertia::render('Documents/Show', [
                'documentType' => $documentTypePayload,
                'documents' => null,
                'filters' => [
                    'q' => $q,
                    'status' => 'All',
                    'sort' => $sort,
                    'per_page' => $perPage,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'record_type' => $selectedRecordType,
                    'year' => $selectedYear,
                    'period' => $selectedPeriod,
                ],
                'stats' => $stats,
                'performanceView' => [
                    'enabled' => true,
                    'record_type_groups' => $recordTypeGroups,
                    'selected_record_type' => $selectedRecordType,
                    'selected_year' => $selectedYear,
                    'selected_period' => $selectedPeriod,
                    'selected_period_name' => $selectedPeriod
                        ? $this->resolvePerformancePeriodName($selectedPeriod)
                        : null,
                    'selected_files' => $selectedPeriodFiles,
                ],
            ]);
        }

        $query = DocumentUpload::query()
            ->with(['uploader', 'ofiRecord', 'dcrRecord', 'carRecord'])
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
                'year' => $d->year,
                'period' => $d->period,
                'performance_category' => $d->performance_category,
                'uploaded_by_name' => $d->uploader?->name ?? '—',
                'created_at' => $d->created_at,
                'ofi_record_id' => $d->ofi_record_id,
                'dcr_record_id' => $d->dcr_record_id,
                'car_record_id' => $d->car_record_id,
                'ofi_workflow_status' => $d->ofiRecord?->workflow_status,
                'ofi_resolution_status' => $d->ofiRecord?->resolution_status,
                'preview_url' => route('documents.uploads.preview', $d->id),
                'download_url' => route('documents.uploads.download', $d->id),
                'file_url' => $d->file_path ? Storage::url($d->file_path) : null,
            ]);

        return Inertia::render('Documents/Show', [
            'documentType' => $documentTypePayload,
            'documents' => $documents,
            'filters' => [
                'q' => $q,
                'status' => $isRevisionControlled ? $status : 'All',
                'sort' => $sort,
                'per_page' => $perPage,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'record_type' => null,
                'year' => null,
                'period' => null,
            ],
            'stats' => $stats,
            'performanceView' => [
                'enabled' => false,
                'record_type_groups' => [],
                'selected_record_type' => null,
                'selected_year' => null,
                'selected_period' => null,
                'selected_period_name' => null,
                'selected_files' => [],
            ],
        ]);
    }

    public function upload(Request $request, DocumentType $documentType)
    {
        if ($documentType->isObsolete()) {
            return back()->withErrors([
                'upload' => "This document type ({$documentType->code}) is obsolete and cannot accept new uploads.",
            ]);
        }

        $isRevisionControlled = $this->isRevisionControlled($documentType);
        $isPerformanceForm = $this->isPerformanceFormType($documentType);

        $rules = [
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:20480'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];

        if ($isRevisionControlled && !$isPerformanceForm) {
            $rules['revision'] = ['required', 'string', 'max:50'];
        }

        if ($isPerformanceForm) {
            $rules['performance_record_type'] = ['required', 'string', 'in:TARGET,ACCOMPLISHMENT'];
            $rules['year'] = ['required', 'integer', 'min:2000', 'max:2100'];
            $rules['period'] = ['required', 'string', 'in:JAN_JUN,JUL_DEC'];
        }

        $data = $request->validate($rules, [
            'performance_record_type.required' => 'Record type is required for performance forms.',
            'performance_record_type.in' => 'Record type must be either Target or Accomplishment.',
            'year.required' => 'Year is required for performance forms.',
            'year.integer' => 'Year must be a valid year.',
            'year.min' => 'Year must be at least 2000.',
            'year.max' => 'Year must not be greater than 2100.',
            'period.required' => 'Period is required for performance forms.',
            'period.in' => 'Period must be either January–June or July–December.',
        ]);

        $files = $request->file('files', []);

        if ($isRevisionControlled && !$isPerformanceForm && count($files) > 1) {
            return back()->withErrors([
                'files' => 'Multiple upload is not allowed for revision-controlled documents.',
            ]);
        }

        $created = 0;

        $year = $isPerformanceForm ? (int) $data['year'] : null;
        $period = $isPerformanceForm ? (string) $data['period'] : null;
        $performanceCategory = $isPerformanceForm
            ? strtoupper((string) $documentType->series?->code_prefix)
            : null;
        $performanceRecordType = $isPerformanceForm
            ? strtoupper((string) $data['performance_record_type'])
            : null;

        foreach ($files as $file) {
            $storePath = $isPerformanceForm
                ? "qms/{$performanceCategory}/{$performanceRecordType}/{$year}/{$period}"
                : "qms/{$documentType->code}";

            $path = $file->store($storePath, 'public');

            if ($isRevisionControlled && !$isPerformanceForm) {
                DocumentUpload::where('document_type_id', $documentType->id)
                    ->where('status', 'Active')
                    ->update(['status' => 'Obsolete']);
            }

            DocumentUpload::create([
                'document_type_id' => $documentType->id,
                'uploaded_by' => $request->user()->id,
                'revision' => ($isRevisionControlled && !$isPerformanceForm)
                    ? trim((string) $data['revision'])
                    : null,
                'year' => $year,
                'performance_category' => $performanceCategory,
                'performance_record_type' => $performanceRecordType,
                'period' => $period,
                'status' => ($isRevisionControlled && !$isPerformanceForm) ? 'Active' : null,
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
        $upload->loadMissing(['documentType.series', 'ofiRecord', 'dcrRecord', 'carRecord']);

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

        if ($upload->car_record_id && $upload->carRecord) {
            $this->activityLogService->log([
                'module' => 'car',
                'action' => 'previewed',
                'entity_type' => DocumentUpload::class,
                'entity_id' => $upload->id,
                'record_label' => $upload->carRecord->car_no ?: 'CAR #' . $upload->carRecord->id,
                'file_type' => $this->activityLogService->extensionFromFileName($upload->file_name),
                'description' => 'Previewed published CAR document ' . ($upload->file_name ?: 'file'),
            ]);

            return $this->previewLatestCarAsPdf($upload);
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
        $upload->loadMissing(['documentType.series', 'ofiRecord', 'dcrRecord', 'carRecord']);

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

        if ($upload->car_record_id && $upload->carRecord) {
            $this->activityLogService->log([
                'module' => 'car',
                'action' => 'downloaded',
                'entity_type' => DocumentUpload::class,
                'entity_id' => $upload->id,
                'record_label' => $upload->carRecord->car_no ?: 'CAR #' . $upload->carRecord->id,
                'file_type' => $this->activityLogService->extensionFromFileName($upload->file_name),
                'description' => 'Downloaded published CAR document ' . ($upload->file_name ?: 'file'),
            ]);

            return $this->downloadLatestCarDocx($upload);
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

    private function isPerformanceFormType(DocumentType $documentType): bool
    {
        $documentType->loadMissing('series');

        $seriesCode = strtoupper(trim((string) $documentType->series?->code_prefix));

        return in_array($seriesCode, ['IPCR', 'DPCR', 'UPCR'], true);
    }

    private function resolvePerformancePeriodName(string $period): string
    {
        return match ($period) {
            'JAN_JUN' => 'January – June',
            'JUL_DEC' => 'July – December',
            default => 'Unknown Period',
        };
    }

    private function resolveModuleFromUpload(DocumentUpload $upload): string
    {
        return strtoupper((string) $upload->documentType?->series?->code_prefix) === 'MANUAL'
            ? 'manuals'
            : 'documents';
    }

    private function resolveDocumentRecordLabel(DocumentUpload $upload): string
    {
        if (
            $upload->documentType &&
            $this->isPerformanceFormType($upload->documentType) &&
            $upload->year &&
            $upload->period
        ) {
            return $upload->documentType->title . ' - ' . $upload->year . ' ' . $this->resolvePerformancePeriodName((string) $upload->period);
        }

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

        $templatePath = config('qms_templates.ofi.path');
        abort_unless(is_string($templatePath) && file_exists($templatePath), 500, 'OFI template file not found.');

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

        $templatePath = config('qms_templates.dcr.path');
        abort_unless(is_string($templatePath) && file_exists($templatePath), 500, 'DCR template file not found.');

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

    private function generateLatestCarTempFile(DocumentUpload $upload): array
    {
        $upload->loadMissing('carRecord');

        abort_unless($upload->carRecord, 404, 'Linked CAR record not found.');

        $templatePath = config('qms_templates.car.path');
        abort_unless(is_string($templatePath) && file_exists($templatePath), 500, 'CAR template file not found.');

        $tmpDir = storage_path('app/car_forms_tmp');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $baseName = pathinfo($upload->file_name ?: 'CAR_record.docx', PATHINFO_FILENAME);
        $safeBaseName = preg_replace('/[^A-Za-z0-9 _\-\(\)]/', '', $baseName);
        $safeBaseName = trim(preg_replace('/\s+/', ' ', $safeBaseName));

        if ($safeBaseName === '') {
            $safeBaseName = 'CAR_record';
        }

        $fileName = $safeBaseName . '.docx';
        $outputPath = $tmpDir . '/' . uniqid('car_', true) . '_' . $fileName;

        $generator = new CARFormGenerator($templatePath);
        $generator->generate($upload->carRecord->data ?? [], $outputPath);

        return [$outputPath, $fileName];
    }

    private function downloadLatestCarDocx(DocumentUpload $upload)
    {
        [$outputPath, $fileName] = $this->generateLatestCarTempFile($upload);

        return response()->download($outputPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => ResponseHeaderBag::DISPOSITION_ATTACHMENT . '; filename="' . addslashes($fileName) . '"',
        ])->deleteFileAfterSend(true);
    }

    private function previewLatestCarAsPdf(DocumentUpload $upload)
    {
        [$docxPath, $fileName] = $this->generateLatestCarTempFile($upload);
        [$pdfPath, $pdfName] = $this->convertGeneratedDocxToPdfUsingExistingConverter($docxPath, $fileName);

        return response()->file($pdfPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ResponseHeaderBag::DISPOSITION_INLINE . '; filename="' . addslashes($pdfName) . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'Pragma' => 'public',
        ])->deleteFileAfterSend(true);
    }
}