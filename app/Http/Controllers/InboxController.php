<?php

namespace App\Http\Controllers;

use App\Models\CarRecord;
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
        $allowedTypes = ['all', 'ofi', 'car'];

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

        $merged = collect();

        if ($type === 'all' || $type === 'ofi') {
            $merged = $merged->concat($ofiRecords);
        }

        if ($type === 'all' || $type === 'car') {
            $merged = $merged->concat($carRecords);
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

        return Inertia::render('Inbox/Index', [
            'records' => $paginated,
            'filters' => [
                'workflow_status' => $workflowStatus,
                'type' => $type,
            ],
            'counts' => [
                'all' => [
                    'pending' => $this->getAdminOfiCount('pending') + $this->getAdminCarCount('pending'),
                    'approved' => $this->getAdminOfiCount('approved') + $this->getAdminCarCount('approved'),
                    'rejected' => $this->getAdminOfiCount('rejected') + $this->getAdminCarCount('rejected'),
                ],
                'ofi' => [
                    'pending' => $this->getAdminOfiCount('pending'),
                    'approved' => $this->getAdminOfiCount('approved'),
                    'rejected' => $this->getAdminOfiCount('rejected'),
                ],
                'car' => [
                    'pending' => $this->getAdminCarCount('pending'),
                    'approved' => $this->getAdminCarCount('approved'),
                    'rejected' => $this->getAdminCarCount('rejected'),
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
        $allowedTypes = ['all', 'ofi', 'car'];

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

        $merged = collect();

        if ($type === 'all' || $type === 'ofi') {
            $merged = $merged->concat($ofiRecords);
        }

        if ($type === 'all' || $type === 'car') {
            $merged = $merged->concat($carRecords);
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

        return Inertia::render('Inbox/MyRecords', [
            'records' => $paginated,
            'filters' => [
                'workflow_status' => $workflowStatus,
                'type' => $type,
            ],
            'counts' => [
                'all' => [
                    'all' => $this->getUserOfiCount() + $this->getUserCarCount(),
                    'pending' => $this->getUserOfiCount('pending') + $this->getUserCarCount('pending'),
                    'approved' => $this->getUserOfiCount('approved') + $this->getUserCarCount('approved'),
                    'rejected' => $this->getUserOfiCount('rejected') + $this->getUserCarCount('rejected'),
                ],
                'ofi' => [
                    'all' => $this->getUserOfiCount(),
                    'pending' => $this->getUserOfiCount('pending'),
                    'approved' => $this->getUserOfiCount('approved'),
                    'rejected' => $this->getUserOfiCount('rejected'),
                ],
                'car' => [
                    'all' => $this->getUserCarCount(),
                    'pending' => $this->getUserCarCount('pending'),
                    'approved' => $this->getUserCarCount('approved'),
                    'rejected' => $this->getUserCarCount('rejected'),
                ],
            ],
        ]);
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
            'date_submitted' => optional($record->created_at)->format('M d, Y g:i A'),
            'submitted_at_sort' => optional($record->created_at)?->toDateTimeString(),
            'view_url' => '/car?record=' . $record->id,
        ];
    }

    private function getAdminOfiCount(string $workflowStatus): int
    {
        return OfiRecord::query()
            ->where('status', 'submitted')
            ->where('workflow_status', $workflowStatus)
            ->whereHas('creator', fn($query) => $query->where('role', '!=', 'admin'))
            ->count();
    }

    private function getAdminCarCount(string $workflowStatus): int
    {
        return CarRecord::query()
            ->where('status', 'submitted')
            ->where('workflow_status', $workflowStatus)
            ->whereHas('creator', fn($query) => $query->where('role', '!=', 'admin'))
            ->count();
    }

    private function getUserOfiCount(?string $workflowStatus = null): int
    {
        return OfiRecord::query()
            ->where('created_by', auth()->id())
            ->when($workflowStatus !== null, fn($query) => $query->where('workflow_status', $workflowStatus))
            ->count();
    }

    private function getUserCarCount(?string $workflowStatus = null): int
    {
        return CarRecord::query()
            ->where('created_by', auth()->id())
            ->when($workflowStatus !== null, fn($query) => $query->where('workflow_status', $workflowStatus))
            ->count();
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