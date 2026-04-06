<?php

namespace App\Http\Controllers;

use App\Models\DocumentUpload;
use App\Services\ActivityLogService;
use App\Services\DocumentPreview\DocumentDownloadService;
use App\Services\DocumentPreview\DocumentPreviewService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PerformanceController extends Controller
{
    public function __construct(
        protected DocumentPreviewService $documentPreviewService,
        protected DocumentDownloadService $documentDownloadService,
        protected ActivityLogService $activityLogService,
    ) {
    }

    public function index(Request $request)
    {
        $allowedCategories = $this->allowedCategories();
        $allowedRecordTypes = $this->allowedRecordTypes();
        $allowedPeriods = $this->allowedPeriods();

        $selectedCategory = strtoupper(trim((string) $request->input('category', 'IPCR')));
        if (!in_array($selectedCategory, $allowedCategories, true)) {
            $selectedCategory = 'IPCR';
        }

        $selectedRecordType = strtoupper(trim((string) $request->input('record_type', '')));
        if ($selectedRecordType !== '' && !in_array($selectedRecordType, $allowedRecordTypes, true)) {
            $selectedRecordType = '';
        }

        $selectedYear = $request->filled('year') ? (int) $request->input('year') : null;

        $selectedPeriod = strtoupper(trim((string) $request->input('period', '')));
        if ($selectedPeriod !== '' && !in_array($selectedPeriod, $allowedPeriods, true)) {
            $selectedPeriod = '';
        }

        $search = trim((string) $request->input('q', ''));
        $sort = (string) $request->input('sort', 'latest');

        $allowedSorts = [
            'latest',
            'oldest',
            'name_asc',
            'name_desc',
        ];

        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'latest';
        }

        $baseQuery = DocumentUpload::query()
            ->whereNull('document_type_id')
            ->whereNotNull('performance_category')
            ->whereNotNull('performance_record_type')
            ->whereNotNull('year')
            ->whereNotNull('period');

        $categories = collect($allowedCategories)
            ->map(function (string $category) use ($baseQuery) {
                return [
                    'value' => $category,
                    'label' => $category,
                    'files_count' => (clone $baseQuery)
                        ->where('performance_category', $category)
                        ->count(),
                ];
            })
            ->values();

        $recordTypes = collect();
        $years = collect();
        $periods = collect();

        $files = [
            'data' => [],
            'links' => [],
            'total' => 0,
            'from' => null,
            'to' => null,
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => 10,
        ];

        if ($selectedCategory !== '') {
            $recordTypes = collect($allowedRecordTypes)
                ->map(function (string $recordType) use ($baseQuery, $selectedCategory) {
                    return [
                        'value' => $recordType,
                        'label' => $this->recordTypeLabel($recordType),
                        'files_count' => (clone $baseQuery)
                            ->where('performance_category', $selectedCategory)
                            ->where('performance_record_type', $recordType)
                            ->count(),
                    ];
                })
                ->values();
        }

        if ($selectedCategory !== '' && $selectedRecordType !== '') {
            $years = (clone $baseQuery)
                ->where('performance_category', $selectedCategory)
                ->where('performance_record_type', $selectedRecordType)
                ->select('year')
                ->distinct()
                ->orderByDesc('year')
                ->pluck('year')
                ->map(fn($year) => [
                    'value' => (int) $year,
                    'label' => (string) $year,
                ])
                ->values();
        }

        if ($selectedCategory !== '' && $selectedRecordType !== '' && $selectedYear !== null) {
            $existingPeriods = (clone $baseQuery)
                ->where('performance_category', $selectedCategory)
                ->where('performance_record_type', $selectedRecordType)
                ->where('year', $selectedYear)
                ->select('period')
                ->distinct()
                ->pluck('period')
                ->map(fn($period) => strtoupper((string) $period))
                ->values()
                ->all();

            $periods = collect($allowedPeriods)
                ->filter(fn($period) => in_array($period, $existingPeriods, true))
                ->map(fn($period) => [
                    'value' => $period,
                    'label' => $this->periodLabel($period),
                ])
                ->values();
        }

        if (
            $selectedCategory !== ''
            && $selectedRecordType !== ''
            && $selectedYear !== null
            && $selectedPeriod !== ''
        ) {
            $filesQuery = DocumentUpload::query()
                ->with('uploader:id,name,email')
                ->whereNull('document_type_id')
                ->where('performance_category', $selectedCategory)
                ->where('performance_record_type', $selectedRecordType)
                ->where('year', $selectedYear)
                ->where('period', $selectedPeriod);

            if ($search !== '') {
                $filesQuery->where(function ($query) use ($search) {
                    $query->where('file_name', 'like', "%{$search}%")
                        ->orWhere('remarks', 'like', "%{$search}%")
                        ->orWhereHas('uploader', function ($uploaderQuery) use ($search) {
                            $uploaderQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            }

            switch ($sort) {
                case 'oldest':
                    $filesQuery->orderBy('created_at', 'asc');
                    break;

                case 'name_asc':
                    $filesQuery->orderBy('file_name', 'asc');
                    break;

                case 'name_desc':
                    $filesQuery->orderBy('file_name', 'desc');
                    break;

                case 'latest':
                default:
                    $filesQuery->orderBy('created_at', 'desc');
                    break;
            }

            $files = $filesQuery
                ->paginate(10)
                ->withQueryString()
                ->through(function (DocumentUpload $upload) {
                    return [
                        'id' => $upload->id,
                        'file_name' => $upload->file_name,
                        'remarks' => $upload->remarks,
                        'uploaded_by_name' => $upload->uploader?->name ?? '—',
                        'created_at' => $upload->created_at,
                        'performance_category' => $upload->performance_category,
                        'performance_record_type' => $upload->performance_record_type,
                        'year' => $upload->year,
                        'period' => $upload->period,
                        'preview_url' => route('performance.uploads.preview', $upload->id),
                        'download_url' => route('performance.uploads.download', $upload->id),
                    ];
                })
                ->toArray();
        }

        return Inertia::render('Performance/Index', [
            'categories' => $categories,
            'recordTypes' => $recordTypes,
            'years' => $years,
            'periods' => $periods,
            'files' => $files,
            'filters' => [
                'category' => $selectedCategory,
                'record_type' => $selectedRecordType,
                'year' => $selectedYear,
                'period' => $selectedPeriod,
                'q' => $search,
                'sort' => $sort,
            ],
            'meta' => [
                'category_label' => $selectedCategory,
                'record_type_label' => $selectedRecordType !== ''
                    ? $this->recordTypeLabel($selectedRecordType)
                    : null,
                'period_label' => $selectedPeriod !== ''
                    ? $this->periodLabel($selectedPeriod)
                    : null,
                'can_upload' => $selectedCategory !== ''
                    && $selectedRecordType !== ''
                    && $selectedYear !== null
                    && $selectedPeriod !== '',
            ],
        ]);
    }

    public function upload(Request $request)
    {
        $data = $request->validate([
            'performance_category' => ['required', 'string', 'in:IPCR,DPCR,UPCR'],
            'performance_record_type' => ['required', 'string', 'in:TARGET,ACCOMPLISHMENT'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period' => ['required', 'string', 'in:JAN_JUN,JUL_DEC'],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['required', 'file', 'max:20480'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ], [
            'performance_category.required' => 'Performance category is required.',
            'performance_category.in' => 'Performance category must be IPCR, DPCR, or UPCR.',
            'performance_record_type.required' => 'Record type is required.',
            'performance_record_type.in' => 'Record type must be Target or Accomplishment.',
            'year.required' => 'Year is required.',
            'year.integer' => 'Year must be a valid year.',
            'period.required' => 'Period is required.',
            'period.in' => 'Period must be either January–June or July–December.',
            'files.required' => 'Please choose at least one file.',
            'files.array' => 'Files must be uploaded as a list.',
            'files.min' => 'Please choose at least one file.',
        ]);

        $category = strtoupper((string) $data['performance_category']);
        $recordType = strtoupper((string) $data['performance_record_type']);
        $year = (int) $data['year'];
        $period = strtoupper((string) $data['period']);

        $created = 0;

        foreach ($request->file('files', []) as $file) {
            $path = $file->store(
                "performance/{$category}/{$recordType}/{$year}/{$period}",
                'public'
            );

            $upload = DocumentUpload::create([
                'document_type_id' => null,
                'uploaded_by' => $request->user()->id,
                'revision' => null,
                'year' => $year,
                'performance_category' => $category,
                'performance_record_type' => $recordType,
                'period' => $period,
                'status' => null,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'storage_disk' => 'public',
                'remarks' => $data['remarks'] ?? null,
            ]);

            $this->activityLogService->log([
                'module' => 'performance',
                'action' => 'uploaded',
                'entity_type' => DocumentUpload::class,
                'entity_id' => $upload->id,
                'record_label' => $this->performanceRecordLabelFromUpload($upload),
                'file_type' => $this->activityLogService->extensionFromFileName($upload->file_name),
                'description' => 'Uploaded performance file ' . ($upload->file_name ?: ('Upload #' . $upload->id)),
            ]);

            $created++;
        }

        return redirect()->route('performance.index', [
            'category' => $category,
            'record_type' => $recordType,
            'year' => $year,
            'period' => $period,
        ])->with('success', $created > 1
                ? 'Files uploaded successfully.'
                : 'File uploaded successfully.');
    }

    public function preview(DocumentUpload $upload)
    {
        abort_unless($this->isPerformanceUpload($upload), 404);

        abort_unless(
            $this->documentPreviewService->canPreview($upload),
            404,
            'This file type is not supported for preview.'
        );

        $this->activityLogService->log([
            'module' => 'performance',
            'action' => 'previewed',
            'entity_type' => DocumentUpload::class,
            'entity_id' => $upload->id,
            'record_label' => $this->performanceRecordLabelFromUpload($upload),
            'file_type' => $this->activityLogService->extensionFromFileName($upload->file_name),
            'description' => 'Previewed performance file ' . ($upload->file_name ?: ('Upload #' . $upload->id)),
        ]);

        return $this->documentPreviewService->preview($upload);
    }

    public function download(DocumentUpload $upload)
    {
        abort_unless($this->isPerformanceUpload($upload), 404);

        $this->activityLogService->log([
            'module' => 'performance',
            'action' => 'downloaded',
            'entity_type' => DocumentUpload::class,
            'entity_id' => $upload->id,
            'record_label' => $this->performanceRecordLabelFromUpload($upload),
            'file_type' => $this->activityLogService->extensionFromFileName($upload->file_name),
            'description' => 'Downloaded performance file ' . ($upload->file_name ?: ('Upload #' . $upload->id)),
        ]);

        return $this->documentDownloadService->download($upload);
    }

    private function allowedCategories(): array
    {
        return ['IPCR', 'DPCR', 'UPCR'];
    }

    private function allowedRecordTypes(): array
    {
        return ['TARGET', 'ACCOMPLISHMENT'];
    }

    private function allowedPeriods(): array
    {
        return ['JAN_JUN', 'JUL_DEC'];
    }

    private function recordTypeLabel(string $value): string
    {
        return match (strtoupper($value)) {
            'TARGET' => 'Target',
            'ACCOMPLISHMENT' => 'Accomplishment',
            default => $value,
        };
    }

    private function periodLabel(string $value): string
    {
        return match (strtoupper($value)) {
            'JAN_JUN' => 'January – June',
            'JUL_DEC' => 'July – December',
            default => $value,
        };
    }

    private function isPerformanceUpload(DocumentUpload $upload): bool
    {
        return $upload->document_type_id === null
            && filled($upload->performance_category)
            && filled($upload->performance_record_type)
            && filled($upload->year)
            && filled($upload->period);
    }

    private function performanceRecordLabelFromUpload(DocumentUpload $upload): string
    {
        $category = strtoupper((string) $upload->performance_category);
        $recordType = $this->recordTypeLabel((string) $upload->performance_record_type);
        $year = (string) $upload->year;
        $period = $this->periodLabel((string) $upload->period);

        return trim("{$category} - {$recordType} - {$year} - {$period}");
    }
}