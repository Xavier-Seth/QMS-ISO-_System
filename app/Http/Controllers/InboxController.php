<?php

namespace App\Http\Controllers;

use App\Models\CarRecord;
use App\Models\DcrRecord;
use App\Models\OfiRecord;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class InboxController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $workflowStatus = (string) $request->input('workflow_status', 'pending');
        $type = (string) $request->input('type', 'all');

        $allowedWorkflowStatuses = ['all', 'pending', 'approved', 'rejected'];
        $allowedTypes = ['all', 'ofi', 'car', 'dcr'];

        if (!in_array($workflowStatus, $allowedWorkflowStatuses, true)) {
            $workflowStatus = 'pending';
        }

        if (!in_array($type, $allowedTypes, true)) {
            $type = 'all';
        }

        $perPage = 10;
        $page = max((int) $request->input('page', 1), 1);

        $ofiRecords = $this->getAdminOfiRecords($workflowStatus);
        $carRecords = $this->getAdminCarRecords($workflowStatus);
        $dcrRecords = $this->getAdminDcrRecords($workflowStatus);

        $merged = collect();

        if ($type === 'all' || $type === 'ofi') {
            $merged = $merged->concat($ofiRecords);
        }

        if ($type === 'all' || $type === 'car') {
            $merged = $merged->concat($carRecords);
        }

        if ($type === 'all' || $type === 'dcr') {
            $merged = $merged->concat($dcrRecords);
        }

        $merged = $merged
            ->sortByDesc(fn(array $record) => $record['submitted_at_sort'] ?? '')
            ->values();

        $paginated = $this->paginateCollection(
            $merged,
            $perPage,
            $page,
            $request->url(),
            $request->query()
        );

        // 3 grouped queries instead of 9 individual count() calls
        $ofiCounts = $this->getAdminCountsGrouped(OfiRecord::class);
        $carCounts = $this->getAdminCountsGrouped(CarRecord::class);
        $dcrCounts = $this->getAdminCountsGrouped(DcrRecord::class);

        return Inertia::render('Inbox/Index', [
            'records' => $paginated,
            'filters' => [
                'workflow_status' => $workflowStatus,
                'type' => $type,
            ],
            'counts' => [
                'all' => [
                    'pending' => ($ofiCounts['pending'] ?? 0) + ($carCounts['pending'] ?? 0) + ($dcrCounts['pending'] ?? 0),
                    'approved' => ($ofiCounts['approved'] ?? 0) + ($carCounts['approved'] ?? 0) + ($dcrCounts['approved'] ?? 0),
                    'rejected' => ($ofiCounts['rejected'] ?? 0) + ($carCounts['rejected'] ?? 0) + ($dcrCounts['rejected'] ?? 0),
                ],
                'ofi' => [
                    'pending' => $ofiCounts['pending'] ?? 0,
                    'approved' => $ofiCounts['approved'] ?? 0,
                    'rejected' => $ofiCounts['rejected'] ?? 0,
                ],
                'car' => [
                    'pending' => $carCounts['pending'] ?? 0,
                    'approved' => $carCounts['approved'] ?? 0,
                    'rejected' => $carCounts['rejected'] ?? 0,
                ],
                'dcr' => [
                    'pending' => $dcrCounts['pending'] ?? 0,
                    'approved' => $dcrCounts['approved'] ?? 0,
                    'rejected' => $dcrCounts['rejected'] ?? 0,
                ],
            ],
        ]);
    }

    public function myRecords(Request $request)
    {
        abort_if(auth()->user()?->role === 'admin', 403);

        $workflowStatus = (string) $request->input('workflow_status', 'all');
        $type = (string) $request->input('type', 'all');

        $allowedWorkflowStatuses = ['all', 'pending', 'approved', 'rejected'];
        $allowedTypes = ['all', 'ofi', 'car', 'dcr'];

        if (!in_array($workflowStatus, $allowedWorkflowStatuses, true)) {
            $workflowStatus = 'all';
        }

        if (!in_array($type, $allowedTypes, true)) {
            $type = 'all';
        }

        $perPage = 10;
        $page = max((int) $request->input('page', 1), 1);

        $ofiRecords = $this->getUserOfiRecords($workflowStatus);
        $carRecords = $this->getUserCarRecords($workflowStatus);
        $dcrRecords = $this->getUserDcrRecords($workflowStatus);

        $merged = collect();

        if ($type === 'all' || $type === 'ofi') {
            $merged = $merged->concat($ofiRecords);
        }

        if ($type === 'all' || $type === 'car') {
            $merged = $merged->concat($carRecords);
        }

        if ($type === 'all' || $type === 'dcr') {
            $merged = $merged->concat($dcrRecords);
        }

        $merged = $merged
            ->sortByDesc(fn(array $record) => $record['submitted_at_sort'] ?? '')
            ->values();

        $paginated = $this->paginateCollection(
            $merged,
            $perPage,
            $page,
            $request->url(),
            $request->query()
        );

        // 3 grouped queries instead of 12 individual count() calls
        $ofiCounts = $this->getUserCountsGrouped(OfiRecord::class);
        $carCounts = $this->getUserCountsGrouped(CarRecord::class);
        $dcrCounts = $this->getUserCountsGrouped(DcrRecord::class);

        $ofiTotal = array_sum($ofiCounts);
        $carTotal = array_sum($carCounts);
        $dcrTotal = array_sum($dcrCounts);

        return Inertia::render('Inbox/MyRecords', [
            'records' => $paginated,
            'filters' => [
                'workflow_status' => $workflowStatus,
                'type' => $type,
            ],
            'counts' => [
                'all' => [
                    'all' => $ofiTotal + $carTotal + $dcrTotal,
                    'pending' => ($ofiCounts['pending'] ?? 0) + ($carCounts['pending'] ?? 0) + ($dcrCounts['pending'] ?? 0),
                    'approved' => ($ofiCounts['approved'] ?? 0) + ($carCounts['approved'] ?? 0) + ($dcrCounts['approved'] ?? 0),
                    'rejected' => ($ofiCounts['rejected'] ?? 0) + ($carCounts['rejected'] ?? 0) + ($dcrCounts['rejected'] ?? 0),
                ],
                'ofi' => [
                    'all' => $ofiTotal,
                    'pending' => $ofiCounts['pending'] ?? 0,
                    'approved' => $ofiCounts['approved'] ?? 0,
                    'rejected' => $ofiCounts['rejected'] ?? 0,
                ],
                'car' => [
                    'all' => $carTotal,
                    'pending' => $carCounts['pending'] ?? 0,
                    'approved' => $carCounts['approved'] ?? 0,
                    'rejected' => $carCounts['rejected'] ?? 0,
                ],
                'dcr' => [
                    'all' => $dcrTotal,
                    'pending' => $dcrCounts['pending'] ?? 0,
                    'approved' => $dcrCounts['approved'] ?? 0,
                    'rejected' => $dcrCounts['rejected'] ?? 0,
                ],
            ],
        ]);
    }

    /**
     * Returns workflow_status => count for admin inbox (submitted, non-admin creators).
     * 1 query per model replaces 3 separate count() calls.
     *
     * @param class-string $model
     * @return array<string, int>
     */
    private function getAdminCountsGrouped(string $model): array
    {
        return $model::query()
            ->selectRaw('workflow_status, COUNT(*) as total')
            ->where('status', 'submitted')
            ->whereHas('creator', fn($q) => $q->where('role', '!=', 'admin'))
            ->groupBy('workflow_status')
            ->pluck('total', 'workflow_status')
            ->map(fn($v) => (int) $v)
            ->toArray();
    }

    /**
     * Returns workflow_status => count for the current user's records.
     * 1 query per model replaces 4 separate count() calls.
     *
     * @param class-string $model
     * @return array<string, int>
     */
    private function getUserCountsGrouped(string $model): array
    {
        return $model::query()
            ->selectRaw('workflow_status, COUNT(*) as total')
            ->where('created_by', auth()->id())
            ->groupBy('workflow_status')
            ->pluck('total', 'workflow_status')
            ->map(fn($v) => (int) $v)
            ->toArray();
    }

    private function getAdminOfiRecords(string $workflowStatus): Collection
    {
        $query = OfiRecord::query()
            ->with([
                'creator:id,name,department,role',
                'rejectedBy:id,name',
            ])
            ->where('status', 'submitted')
            ->whereHas('creator', function ($query) {
                $query->where('role', '!=', 'admin');
            });

        if ($workflowStatus !== 'all') {
            $query->where('workflow_status', $workflowStatus);
        }

        return $query
            ->latest()
            ->get()
            ->map(fn(OfiRecord $record) => $this->normalizeAdminOfiRecord($record))
            ->values();
    }

    private function getAdminCarRecords(string $workflowStatus): Collection
    {
        $query = CarRecord::query()
            ->with([
                'creator:id,name,department,role',
                'rejectedBy:id,name',
            ])
            ->where('status', 'submitted')
            ->whereHas('creator', function ($query) {
                $query->where('role', '!=', 'admin');
            });

        if ($workflowStatus !== 'all') {
            $query->where('workflow_status', $workflowStatus);
        }

        return $query
            ->latest()
            ->get()
            ->map(fn(CarRecord $record) => $this->normalizeAdminCarRecord($record))
            ->values();
    }

    private function getAdminDcrRecords(string $workflowStatus): Collection
    {
        $query = DcrRecord::query()
            ->with([
                'creator:id,name,department,role',
                'rejectedBy:id,name',
            ])
            ->where('status', 'submitted')
            ->whereHas('creator', function ($query) {
                $query->where('role', '!=', 'admin');
            });

        if ($workflowStatus !== 'all') {
            $query->where('workflow_status', $workflowStatus);
        }

        return $query
            ->latest()
            ->get()
            ->map(fn(DcrRecord $record) => $this->normalizeAdminDcrRecord($record))
            ->values();
    }

    private function getUserOfiRecords(string $workflowStatus): Collection
    {
        $query = OfiRecord::query()
            ->with([
                'creator:id,name,department',
                'rejectedBy:id,name',
            ])
            ->where('created_by', auth()->id());

        if ($workflowStatus !== 'all') {
            $query->where('workflow_status', $workflowStatus);
        }

        return $query
            ->latest()
            ->get()
            ->map(fn(OfiRecord $record) => $this->normalizeUserOfiRecord($record))
            ->values();
    }

    private function getUserCarRecords(string $workflowStatus): Collection
    {
        $query = CarRecord::query()
            ->with([
                'creator:id,name,department',
                'rejectedBy:id,name',
            ])
            ->where('created_by', auth()->id());

        if ($workflowStatus !== 'all') {
            $query->where('workflow_status', $workflowStatus);
        }

        return $query
            ->latest()
            ->get()
            ->map(fn(CarRecord $record) => $this->normalizeUserCarRecord($record))
            ->values();
    }

    private function getUserDcrRecords(string $workflowStatus): Collection
    {
        $query = DcrRecord::query()
            ->with([
                'creator:id,name,department',
                'rejectedBy:id,name',
            ])
            ->where('created_by', auth()->id());

        if ($workflowStatus !== 'all') {
            $query->where('workflow_status', $workflowStatus);
        }

        return $query
            ->latest()
            ->get()
            ->map(fn(DcrRecord $record) => $this->normalizeUserDcrRecord($record))
            ->values();
    }

    private function normalizeAdminOfiRecord(OfiRecord $record): array
    {
        return [
            'id' => $record->id,
            'type' => 'ofi',
            'type_label' => 'OFI',
            'record_no' => $record->ofi_no ?: ('OFI #' . $record->id),
            'subject' => $record->to ?: ($record->ref_no ?: '—'),
            'submitted_by' => $record->creator?->name ?? '—',
            'department' => $record->creator?->department ?? '—',
            'workflow_status' => $record->workflow_status,
            'resolution_status' => $record->resolution_status ?? '—',
            'date_submitted' => optional($record->created_at)->format('M d, Y g:i A'),
            'submitted_at_sort' => optional($record->created_at)?->toDateTimeString(),
            'rejection_reason' => $record->rejection_reason,
            'rejected_at' => $record->rejected_at,
            'rejected_by_name' => $record->rejectedBy?->name ?? null,
            'view_url' => '/ofi-form?record=' . $record->id,
            'approve_url' => '/inbox/ofi/' . $record->id . '/approve',
            'reject_url' => '/inbox/ofi/' . $record->id . '/reject',
        ];
    }

    private function normalizeAdminCarRecord(CarRecord $record): array
    {
        return [
            'id' => $record->id,
            'type' => 'car',
            'type_label' => 'CAR',
            'record_no' => $record->car_no ?: ('CAR #' . $record->id),
            'subject' => $record->dept_section ?: ($record->ref_no ?: '—'),
            'submitted_by' => $record->creator?->name ?? '—',
            'department' => $record->creator?->department ?? '—',
            'workflow_status' => $record->workflow_status,
            'resolution_status' => $record->resolution_status ?? '—',
            'date_submitted' => optional($record->created_at)->format('M d, Y g:i A'),
            'submitted_at_sort' => optional($record->created_at)?->toDateTimeString(),
            'rejection_reason' => $record->rejection_reason,
            'rejected_at' => $record->rejected_at,
            'rejected_by_name' => $record->rejectedBy?->name ?? null,
            'view_url' => '/car?record=' . $record->id,
            'approve_url' => '/inbox/car/' . $record->id . '/approve',
            'reject_url' => '/inbox/car/' . $record->id . '/reject',
        ];
    }

    private function normalizeAdminDcrRecord(DcrRecord $record): array
    {
        return [
            'id' => $record->id,
            'type' => 'dcr',
            'type_label' => 'DCR',
            'record_no' => $record->dcr_no ?: ('DCR #' . $record->id),
            'subject' => $record->to_for ?: ($record->from ?: '—'),
            'submitted_by' => $record->creator?->name ?? '—',
            'department' => $record->creator?->department ?? '—',
            'workflow_status' => $record->workflow_status,
            'resolution_status' => $record->resolution_status ?? '—',
            'date_submitted' => optional($record->created_at)->format('M d, Y g:i A'),
            'submitted_at_sort' => optional($record->created_at)?->toDateTimeString(),
            'rejection_reason' => $record->rejection_reason,
            'rejected_at' => $record->rejected_at,
            'rejected_by_name' => $record->rejectedBy?->name ?? null,
            'view_url' => '/dcr?record=' . $record->id,
            'approve_url' => '/inbox/dcr/' . $record->id . '/approve',
            'reject_url' => '/inbox/dcr/' . $record->id . '/reject',
        ];
    }

    private function normalizeUserOfiRecord(OfiRecord $record): array
    {
        return [
            'id' => $record->id,
            'type' => 'ofi',
            'type_label' => 'OFI',
            'record_no' => $record->ofi_no ?: ('OFI #' . $record->id),
            'subject' => $record->to ?: ($record->ref_no ?: '—'),
            'workflow_status' => $record->workflow_status ?: 'draft',
            'resolution_status' => $record->resolution_status ?? '—',
            'remarks' => $record->workflow_status === 'rejected'
                ? ($record->rejection_reason ?: 'Returned for correction.')
                : '—',
            'created_at' => $record->created_at,
            'date_submitted' => optional($record->created_at)->format('M d, Y g:i A'),
            'submitted_at_sort' => optional($record->created_at)?->toDateTimeString(),
            'view_url' => '/ofi-form?record=' . $record->id,
        ];
    }

    private function normalizeUserCarRecord(CarRecord $record): array
    {
        return [
            'id' => $record->id,
            'type' => 'car',
            'type_label' => 'CAR',
            'record_no' => $record->car_no ?: ('CAR #' . $record->id),
            'subject' => $record->dept_section ?: ($record->ref_no ?: '—'),
            'workflow_status' => $record->workflow_status ?: 'draft',
            'resolution_status' => $record->resolution_status ?? '—',
            'remarks' => $record->workflow_status === 'rejected'
                ? ($record->rejection_reason ?: 'Returned for correction.')
                : '—',
            'created_at' => $record->created_at,
            'date_submitted' => optional($record->created_at)->format('M d, Y g:i A'),
            'submitted_at_sort' => optional($record->created_at)?->toDateTimeString(),
            'view_url' => '/car?record=' . $record->id,
        ];
    }

    private function normalizeUserDcrRecord(DcrRecord $record): array
    {
        return [
            'id' => $record->id,
            'type' => 'dcr',
            'type_label' => 'DCR',
            'record_no' => $record->dcr_no ?: ('DCR #' . $record->id),
            'subject' => $record->to_for ?: ($record->from ?: '—'),
            'workflow_status' => $record->workflow_status ?: 'draft',
            'resolution_status' => $record->resolution_status ?? '—',
            'remarks' => $record->workflow_status === 'rejected'
                ? ($record->rejection_reason ?: 'Returned for correction.')
                : '—',
            'created_at' => $record->created_at,
            'date_submitted' => optional($record->created_at)->format('M d, Y g:i A'),
            'submitted_at_sort' => optional($record->created_at)?->toDateTimeString(),
            'view_url' => '/dcr?record=' . $record->id,
        ];
    }

    private function paginateCollection(
        Collection $items,
        int $perPage,
        int $page,
        string $path,
        array $query = []
    ): LengthAwarePaginator {
        $total = $items->count();

        $results = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $results,
            $total,
            $perPage,
            $page,
            [
                'path' => $path,
                'query' => $query,
            ]
        );
    }
}