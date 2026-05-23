<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
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
    ) {}

    public function index(Request $request)
    {
        $allowedCategories = $this->allowedCategories();
        $allowedRecordTypes = $this->allowedRecordTypes();

        $selectedCategory = strtoupper(trim((string) $request->input('category', 'IPCR')));
        if (! in_array($selectedCategory, $allowedCategories, true)) {
            $selectedCategory = 'IPCR';
        }

        $selectedRecordType = strtoupper(trim((string) $request->input('record_type', '')));
        if ($selectedRecordType !== '' && ! in_array($selectedRecordType, $allowedRecordTypes, true)) {
            $selectedRecordType = '';
        }

        $selectedYear = $request->filled('year') ? (int) $request->input('year') : null;

        $usesPeriod = $this->categoryUsesPeriod($selectedCategory);

        $selectedPeriod = '';
        if ($usesPeriod) {
            $selectedPeriod = strtoupper(trim((string) $request->input('period', '')));
            if ($selectedPeriod !== '' && ! in_array($selectedPeriod, $this->allowedPeriods(), true)) {
                $selectedPeriod = '';
            }
        }

        $search = trim((string) $request->input('q', ''));
        $sort = (string) $request->input('sort', 'latest');

        $allowedSorts = [
            'latest',
            'oldest',
            'name_asc',
            'name_desc',
        ];

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'latest';
        }

        $categoryTypeIds = $this->performanceTypeIds();

        // Per-category missing check — a missing PERF-OPCR type does not block
        // IPCR, DPCR, and UPCR navigation. Only the affected category is degraded.
        $missingByCategory = [];
        foreach ($allowedCategories as $cat) {
            $missingByCategory[$cat] = ! isset($categoryTypeIds[$cat]);
        }
        $currentCategoryMissing = $missingByCategory[$selectedCategory] ?? true;

        $categories = collect($allowedCategories)
            ->map(function (string $category) use ($categoryTypeIds, $missingByCategory) {
                $typeId = $categoryTypeIds[$category] ?? null;
                $catUsesPeriod = $this->categoryUsesPeriod($category);

                return [
                    'value' => $category,
                    'label' => $category,
                    'missing' => $missingByCategory[$category] ?? true,
                    'files_count' => $typeId
                        ? DocumentUpload::query()
                            ->where('document_type_id', $typeId)
                            ->where('performance_category', $category)
                            ->whereNotNull('performance_record_type')
                            ->whereNotNull('year')
                            ->when($catUsesPeriod, fn ($q) => $q->whereNotNull('period'))
                            ->count()
                        : 0,
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

        if (! $currentCategoryMissing && $selectedCategory !== '' && isset($categoryTypeIds[$selectedCategory])) {
            $selectedTypeId = $categoryTypeIds[$selectedCategory];

            $recordTypes = collect($allowedRecordTypes)
                ->map(function (string $recordType) use ($selectedCategory, $selectedTypeId, $usesPeriod) {
                    return [
                        'value' => $recordType,
                        'label' => $this->recordTypeLabel($recordType),
                        'files_count' => DocumentUpload::query()
                            ->where('document_type_id', $selectedTypeId)
                            ->where('performance_category', $selectedCategory)
                            ->where('performance_record_type', $recordType)
                            ->whereNotNull('year')
                            ->when($usesPeriod, fn ($q) => $q->whereNotNull('period'))
                            ->count(),
                    ];
                })
                ->values();
        }

        if (
            ! $currentCategoryMissing
            && $selectedCategory !== ''
            && $selectedRecordType !== ''
            && isset($categoryTypeIds[$selectedCategory])
        ) {
            $selectedTypeId = $categoryTypeIds[$selectedCategory];

            $years = DocumentUpload::query()
                ->where('document_type_id', $selectedTypeId)
                ->where('performance_category', $selectedCategory)
                ->where('performance_record_type', $selectedRecordType)
                ->whereNotNull('year')
                ->select('year')
                ->distinct()
                ->orderByDesc('year')
                ->pluck('year')
                ->map(fn ($year) => [
                    'value' => (int) $year,
                    'label' => (string) $year,
                ])
                ->values();
        }

        if (
            ! $currentCategoryMissing
            && $selectedCategory !== ''
            && $selectedRecordType !== ''
            && $selectedYear !== null
            && $usesPeriod
            && isset($categoryTypeIds[$selectedCategory])
        ) {
            $selectedTypeId = $categoryTypeIds[$selectedCategory];

            $existingPeriods = DocumentUpload::query()
                ->where('document_type_id', $selectedTypeId)
                ->where('performance_category', $selectedCategory)
                ->where('performance_record_type', $selectedRecordType)
                ->where('year', $selectedYear)
                ->whereNotNull('period')
                ->select('period')
                ->distinct()
                ->pluck('period')
                ->map(fn ($period) => strtoupper((string) $period))
                ->values()
                ->all();

            $periods = collect($this->allowedPeriods())
                ->filter(fn ($period) => in_array($period, $existingPeriods, true))
                ->map(fn ($period) => [
                    'value' => $period,
                    'label' => $this->periodLabel($period),
                ])
                ->values();
        }

        $filesReady = ! $currentCategoryMissing
            && $selectedCategory !== ''
            && $selectedRecordType !== ''
            && $selectedYear !== null
            && (! $usesPeriod || $selectedPeriod !== '')
            && isset($categoryTypeIds[$selectedCategory]);

        if ($filesReady) {
            $selectedTypeId = $categoryTypeIds[$selectedCategory];

            $filesQuery = DocumentUpload::query()
                ->with('uploader:id,name,email')
                ->where('document_type_id', $selectedTypeId)
                ->where('performance_category', $selectedCategory)
                ->where('performance_record_type', $selectedRecordType)
                ->where('year', $selectedYear);

            if ($usesPeriod) {
                $filesQuery->where('period', $selectedPeriod);
            } else {
                $filesQuery->whereNull('period');
            }

            if ($search !== '') {
                $escaped = $this->escapeLike($search);

                $filesQuery->where(function ($query) use ($escaped) {
                    $query->where('file_name', 'like', "%{$escaped}%")
                        ->orWhere('remarks', 'like', "%{$escaped}%")
                        ->orWhereHas('uploader', function ($uploaderQuery) use ($escaped) {
                            $uploaderQuery->where('name', 'like', "%{$escaped}%")
                                ->orWhere('email', 'like', "%{$escaped}%");
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
                        'can_preview' => $this->documentPreviewService->canPreview($upload),
                        'preview_url' => $this->documentPreviewService->canPreview($upload)
                            ? route('performance.uploads.preview', $upload->id)
                            : null,
                        'download_url' => route('performance.uploads.download', $upload->id),
                        'delete_url' => route('documents.uploads.destroy', $upload->id),
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
                'uses_period' => $usesPeriod,
                'can_upload' => ! $currentCategoryMissing
                    && $selectedCategory !== ''
                    && $selectedRecordType !== '',
                'missing_types' => $currentCategoryMissing,
                'missing_by_category' => $missingByCategory,
                'missing_types_message' => $currentCategoryMissing
                    ? 'Performance document type for '.$selectedCategory.' is missing or its code does not match the controller mapping.'
                    : null,
            ],
        ]);
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }

    public function upload(Request $request)
    {
        // Derive uses_period before validation so the period rule can be conditional.
        $rawCategory = strtoupper(trim((string) $request->input('performance_category', '')));
        $usesPeriod = $this->categoryUsesPeriod($rawCategory);

        $data = $request->validate([
            'performance_category' => ['required', 'string', 'in:'.implode(',', $this->allowedCategories())],
            'performance_record_type' => ['required', 'string', 'in:TARGET,ACCOMPLISHMENT'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period' => $usesPeriod ? ['required', 'string', 'in:JAN_JUN,JUL_DEC'] : ['nullable'],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => [
                'required',
                'file',
                'max:20480',
                'mimes:pdf,doc,docx,xls,xlsx,csv,ppt,pptx,jpg,jpeg,png,gif,webp',
            ],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ], [
            'performance_category.required' => 'Performance category is required.',
            'performance_category.in' => 'Performance category must be '.implode(', ', $this->allowedCategories()).'.',
            'performance_record_type.required' => 'Record type is required.',
            'performance_record_type.in' => 'Record type must be Target or Accomplishment.',
            'year.required' => 'Year is required.',
            'year.integer' => 'Year must be a valid year.',
            'period.required' => 'Period is required.',
            'period.in' => 'Period must be either January–June or July–December.',
            'files.required' => 'Please choose at least one file.',
            'files.array' => 'Files must be uploaded as a list.',
            'files.min' => 'Please choose at least one file.',
            'files.*.mimes' => 'Only PDF, Word, Excel, PowerPoint, JPG, JPEG, PNG, GIF, and WEBP files are allowed.',
            'files.*.max' => 'Each file must not be greater than 20 MB.',
        ]);

        $category = strtoupper((string) $data['performance_category']);
        $recordType = strtoupper((string) $data['performance_record_type']);
        $year = (int) $data['year'];
        $period = $usesPeriod ? strtoupper((string) $data['period']) : null;

        $typeId = $this->performanceTypeId($category);

        if (! $typeId) {
            return back()->withErrors([
                'performance_category' => 'The performance document type for this category is missing.',
            ]);
        }

        $created = 0;

        foreach ($request->file('files', []) as $file) {
            $storagePath = $usesPeriod
                ? "performance/{$category}/{$recordType}/{$year}/{$period}"
                : "performance/{$category}/{$recordType}/{$year}";

            $path = $file->store($storagePath, 'public');

            $upload = DocumentUpload::create([
                'document_type_id' => $typeId,
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
                'description' => 'Uploaded performance file '.($upload->file_name ?: ('Upload #'.$upload->id)),
            ]);

            $created++;
        }

        $redirectParams = array_filter([
            'category' => $category,
            'record_type' => $recordType,
            'year' => $year,
            'period' => $usesPeriod ? $period : null,
        ], fn ($v) => $v !== null);

        return redirect()->route('performance.index', $redirectParams)
            ->with('success', $created > 1
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
            'description' => 'Previewed performance file '.($upload->file_name ?: ('Upload #'.$upload->id)),
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
            'description' => 'Downloaded performance file '.($upload->file_name ?: ('Upload #'.$upload->id)),
        ]);

        return $this->documentDownloadService->download($upload);
    }

    /**
     * Single source of truth for category configuration.
     * Add new categories here — all helpers and queries derive from this map.
     */
    private function categoryConfig(): array
    {
        return [
            'IPCR' => ['uses_period' => true,  'type_code' => 'PERF-IPCR'],
            'DPCR' => ['uses_period' => true,  'type_code' => 'PERF-DPCR'],
            'UPCR' => ['uses_period' => true,  'type_code' => 'PERF-UPCR'],
            'OPCR' => ['uses_period' => false, 'type_code' => 'PERF-OPCR'],
        ];
    }

    private function allowedCategories(): array
    {
        return array_keys($this->categoryConfig());
    }

    private function allowedRecordTypes(): array
    {
        return ['TARGET', 'ACCOMPLISHMENT'];
    }

    private function allowedPeriods(): array
    {
        return ['JAN_JUN', 'JUL_DEC'];
    }

    private function categoryUsesPeriod(string $category): bool
    {
        return $this->categoryConfig()[strtoupper($category)]['uses_period'] ?? true;
    }

    private function performanceTypeIds(): array
    {
        $config = $this->categoryConfig();
        $codes = array_map(fn ($cfg) => $cfg['type_code'], $config);

        $types = DocumentType::query()
            ->whereIn('code', array_values($codes))
            ->pluck('id', 'code');

        $resolved = [];

        foreach ($config as $category => $cfg) {
            $id = $types->get($cfg['type_code']);

            if ($id) {
                $resolved[$category] = (int) $id;
            }
        }

        return $resolved;
    }

    private function performanceTypeId(string $category): ?int
    {
        $ids = $this->performanceTypeIds();

        return $ids[$category] ?? null;
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
        $validTypeIds = array_values($this->performanceTypeIds());
        $category = strtoupper((string) $upload->performance_category);
        $usesPeriod = $this->categoryUsesPeriod($category);

        return in_array((int) $upload->document_type_id, $validTypeIds, true)
            && in_array($category, $this->allowedCategories(), true)
            && in_array(strtoupper((string) $upload->performance_record_type), $this->allowedRecordTypes(), true)
            && filled($upload->year)
            && (! $usesPeriod || in_array(strtoupper((string) $upload->period), $this->allowedPeriods(), true));
    }

    private function performanceRecordLabelFromUpload(DocumentUpload $upload): string
    {
        $category = strtoupper((string) $upload->performance_category);
        $usesPeriod = $this->categoryUsesPeriod($category);
        $recordType = $this->recordTypeLabel((string) $upload->performance_record_type);
        $year = (string) $upload->year;

        $parts = [$category, $recordType, $year];

        if ($usesPeriod) {
            $parts[] = $this->periodLabel((string) $upload->period);
        }

        return trim(implode(' - ', $parts));
    }
}
