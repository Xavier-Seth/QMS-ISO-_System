<?php

namespace App\Http\Controllers;

use App\Models\CarRecord;
use App\Models\DcrRecord;
use App\Models\OfiRecord;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;

class InboxController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $activeTab = (string) $request->input('tab', 'ofi');
        $workflowStatus = (string) $request->input('workflow_status', 'pending');
        $q = trim((string) $request->input('q', ''));

        $allowedTabs = ['ofi', 'car', 'dcr'];
        $allowedWorkflowStatuses = ['pending', 'approved', 'rejected'];

        if (! in_array($activeTab, $allowedTabs, true)) {
            $activeTab = 'ofi';
        }

        if (! in_array($workflowStatus, $allowedWorkflowStatuses, true)) {
            $workflowStatus = 'pending';
        }

        $perPage = 10;

        $records = match ($activeTab) {
            'car' => $this->paginateAdminCarRecords($workflowStatus, $q, $perPage),
            'dcr' => $this->paginateAdminDcrRecords($workflowStatus, $q, $perPage),
            default => $this->paginateAdminOfiRecords($workflowStatus, $q, $perPage),
        };

        return Inertia::render('Inbox/Index', [
            'records' => $records,
            'filters' => [
                'tab' => $activeTab,
                'workflow_status' => $workflowStatus,
                'q' => $q,
            ],
            'pendingCounts' => [
                'ofi' => $this->adminPendingCount(OfiRecord::class),
                'car' => $this->adminPendingCount(CarRecord::class),
                'dcr' => $this->adminPendingCount(DcrRecord::class),
            ],
        ]);
    }

    public function myRecords(Request $request)
    {
        abort_if(auth()->user()?->role === 'admin', 403);

        $activeTab = (string) $request->input('tab', 'ofi');
        $workflowStatus = (string) $request->input('workflow_status', 'all');
        $q = trim((string) $request->input('q', ''));

        $allowedTabs = ['ofi', 'car', 'dcr'];
        $allowedWorkflowStatuses = ['all', 'pending', 'approved', 'rejected'];

        if (! in_array($activeTab, $allowedTabs, true)) {
            $activeTab = 'ofi';
        }

        if (! in_array($workflowStatus, $allowedWorkflowStatuses, true)) {
            $workflowStatus = 'all';
        }

        $perPage = 10;

        $records = match ($activeTab) {
            'car' => $this->paginateUserCarRecords($workflowStatus, $q, $perPage),
            'dcr' => $this->paginateUserDcrRecords($workflowStatus, $q, $perPage),
            default => $this->paginateUserOfiRecords($workflowStatus, $q, $perPage),
        };

        return Inertia::render('Inbox/MyRecords', [
            'records' => $records,
            'filters' => [
                'tab' => $activeTab,
                'workflow_status' => $workflowStatus,
                'q' => $q,
            ],
            'returnedCounts' => [
                'ofi' => $this->userReturnedCount(OfiRecord::class),
                'car' => $this->userReturnedCount(CarRecord::class),
                'dcr' => $this->userReturnedCount(DcrRecord::class),
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // Count helpers
    // -------------------------------------------------------------------------

    private function adminPendingCount(string $model): int
    {
        return $model::query()
            ->where('status', 'submitted')
            ->where('workflow_status', 'pending')
            ->whereHas('creator', fn ($q) => $q->where('role', '!=', 'admin'))
            ->count();
    }

    private function userReturnedCount(string $model): int
    {
        return $model::query()
            ->where('created_by', auth()->id())
            ->where('workflow_status', 'rejected')
            ->count();
    }

    // -------------------------------------------------------------------------
    // Admin paginated queries
    // -------------------------------------------------------------------------

    private function paginateAdminOfiRecords(string $workflowStatus, string $q, int $perPage): LengthAwarePaginator
    {
        $query = OfiRecord::query()
            ->with([
                'creator:id,name,department,role',
                'rejectedBy:id,name',
            ])
            ->where('status', 'submitted')
            ->whereHas('creator', fn ($query) => $query->where('role', '!=', 'admin'));

        $query->where('workflow_status', $workflowStatus);

        if ($q !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
            $query->where(fn ($sub) => $sub
                ->where('ofi_no', 'LIKE', $escaped.'%')
                ->orWhereHas('creator', fn ($u) => $u
                    ->where('name', 'LIKE', '%'.$escaped.'%')
                    ->orWhere('department', 'LIKE', '%'.$escaped.'%')
                )
            );
        }

        return $query
            ->latest()
            ->paginate($perPage)
            ->through(fn (OfiRecord $record) => $this->normalizeAdminOfiRecord($record))
            ->withQueryString();
    }

    private function paginateAdminCarRecords(string $workflowStatus, string $q, int $perPage): LengthAwarePaginator
    {
        $query = CarRecord::query()
            ->with([
                'creator:id,name,department,role',
                'rejectedBy:id,name',
            ])
            ->where('status', 'submitted')
            ->whereHas('creator', fn ($query) => $query->where('role', '!=', 'admin'));

        $query->where('workflow_status', $workflowStatus);

        if ($q !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
            $query->where(fn ($sub) => $sub
                ->where('car_no', 'LIKE', $escaped.'%')
                ->orWhereHas('creator', fn ($u) => $u
                    ->where('name', 'LIKE', '%'.$escaped.'%')
                    ->orWhere('department', 'LIKE', '%'.$escaped.'%')
                )
            );
        }

        return $query
            ->latest()
            ->paginate($perPage)
            ->through(fn (CarRecord $record) => $this->normalizeAdminCarRecord($record))
            ->withQueryString();
    }

    private function paginateAdminDcrRecords(string $workflowStatus, string $q, int $perPage): LengthAwarePaginator
    {
        $query = DcrRecord::query()
            ->with([
                'creator:id,name,department,role',
                'rejectedBy:id,name',
            ])
            ->where('status', 'submitted')
            ->whereHas('creator', fn ($query) => $query->where('role', '!=', 'admin'));

        $query->where('workflow_status', $workflowStatus);

        if ($q !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
            $query->where(fn ($sub) => $sub
                ->where('dcr_no', 'LIKE', $escaped.'%')
                ->orWhereHas('creator', fn ($u) => $u
                    ->where('name', 'LIKE', '%'.$escaped.'%')
                    ->orWhere('department', 'LIKE', '%'.$escaped.'%')
                )
            );
        }

        return $query
            ->latest()
            ->paginate($perPage)
            ->through(fn (DcrRecord $record) => $this->normalizeAdminDcrRecord($record))
            ->withQueryString();
    }

    // -------------------------------------------------------------------------
    // User paginated queries
    // -------------------------------------------------------------------------

    private function paginateUserOfiRecords(string $workflowStatus, string $q, int $perPage): LengthAwarePaginator
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

        if ($q !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
            $query->where(fn ($sub) => $sub
                ->where('ofi_no', 'LIKE', $escaped.'%')
                ->orWhereHas('creator', fn ($u) => $u
                    ->where('name', 'LIKE', '%'.$escaped.'%')
                    ->orWhere('department', 'LIKE', '%'.$escaped.'%')
                )
            );
        }

        return $query
            ->latest()
            ->paginate($perPage)
            ->through(fn (OfiRecord $record) => $this->normalizeUserOfiRecord($record))
            ->withQueryString();
    }

    private function paginateUserCarRecords(string $workflowStatus, string $q, int $perPage): LengthAwarePaginator
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

        if ($q !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
            $query->where(fn ($sub) => $sub
                ->where('car_no', 'LIKE', $escaped.'%')
                ->orWhereHas('creator', fn ($u) => $u
                    ->where('name', 'LIKE', '%'.$escaped.'%')
                    ->orWhere('department', 'LIKE', '%'.$escaped.'%')
                )
            );
        }

        return $query
            ->latest()
            ->paginate($perPage)
            ->through(fn (CarRecord $record) => $this->normalizeUserCarRecord($record))
            ->withQueryString();
    }

    private function paginateUserDcrRecords(string $workflowStatus, string $q, int $perPage): LengthAwarePaginator
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

        if ($q !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
            $query->where(fn ($sub) => $sub
                ->where('dcr_no', 'LIKE', $escaped.'%')
                ->orWhereHas('creator', fn ($u) => $u
                    ->where('name', 'LIKE', '%'.$escaped.'%')
                    ->orWhere('department', 'LIKE', '%'.$escaped.'%')
                )
            );
        }

        return $query
            ->latest()
            ->paginate($perPage)
            ->through(fn (DcrRecord $record) => $this->normalizeUserDcrRecord($record))
            ->withQueryString();
    }

    // -------------------------------------------------------------------------
    // Normalizers — admin
    // -------------------------------------------------------------------------

    private function normalizeAdminOfiRecord(OfiRecord $record): array
    {
        return [
            'id' => $record->id,
            'type' => 'ofi',
            'type_label' => 'OFI',
            'record_no' => $record->ofi_no ?: ('OFI #'.$record->id),
            'subject' => $record->to ?: ($record->ref_no ?: '—'),
            'submitted_by' => $record->creator?->name ?? '—',
            'department' => $record->creator?->department ?? '—',
            'workflow_status' => $record->workflow_status,
            'resolution_status' => $record->resolution_status ?? '—',
            'date_submitted' => optional($record->created_at)->format('M d, Y g:i A'),
            'rejection_reason' => $record->rejection_reason,
            'rejected_at' => $record->rejected_at,
            'rejected_by_name' => $record->rejectedBy?->name ?? null,
            'view_url' => '/ofi-form?record='.$record->id.'&from=inbox',
            'approve_url' => '/inbox/ofi/'.$record->id.'/approve',
            'reject_url' => '/inbox/ofi/'.$record->id.'/reject',
        ];
    }

    private function normalizeAdminCarRecord(CarRecord $record): array
    {
        return [
            'id' => $record->id,
            'type' => 'car',
            'type_label' => 'CAR',
            'record_no' => $record->car_no ?: ('CAR #'.$record->id),
            'subject' => $record->dept_section ?: ($record->ref_no ?: '—'),
            'submitted_by' => $record->creator?->name ?? '—',
            'department' => $record->creator?->department ?? '—',
            'workflow_status' => $record->workflow_status,
            'resolution_status' => $record->resolution_status ?? '—',
            'date_submitted' => optional($record->created_at)->format('M d, Y g:i A'),
            'rejection_reason' => $record->rejection_reason,
            'rejected_at' => $record->rejected_at,
            'rejected_by_name' => $record->rejectedBy?->name ?? null,
            'view_url' => '/car?record='.$record->id.'&from=inbox',
            'approve_url' => '/inbox/car/'.$record->id.'/approve',
            'reject_url' => '/inbox/car/'.$record->id.'/reject',
        ];
    }

    private function normalizeAdminDcrRecord(DcrRecord $record): array
    {
        return [
            'id' => $record->id,
            'type' => 'dcr',
            'type_label' => 'DCR',
            'record_no' => $record->dcr_no ?: ('DCR #'.$record->id),
            'subject' => $record->to_for ?: ($record->from ?: '—'),
            'submitted_by' => $record->creator?->name ?? '—',
            'department' => $record->creator?->department ?? '—',
            'workflow_status' => $record->workflow_status,
            'resolution_status' => $record->resolution_status ?? '—',
            'date_submitted' => optional($record->created_at)->format('M d, Y g:i A'),
            'rejection_reason' => $record->rejection_reason,
            'rejected_at' => $record->rejected_at,
            'rejected_by_name' => $record->rejectedBy?->name ?? null,
            'view_url' => '/dcr?record='.$record->id.'&from=inbox',
            'approve_url' => '/inbox/dcr/'.$record->id.'/approve',
            'reject_url' => '/inbox/dcr/'.$record->id.'/reject',
        ];
    }

    // -------------------------------------------------------------------------
    // Normalizers — user
    // -------------------------------------------------------------------------

    private function normalizeUserOfiRecord(OfiRecord $record): array
    {
        return [
            'id' => $record->id,
            'type' => 'ofi',
            'type_label' => 'OFI',
            'record_no' => $record->ofi_no ?: ('OFI #'.$record->id),
            'subject' => $record->to ?: ($record->ref_no ?: '—'),
            'workflow_status' => $record->workflow_status ?: 'draft',
            'resolution_status' => $record->resolution_status ?? '—',
            'remarks' => $record->workflow_status === 'rejected'
                ? ($record->rejection_reason ?: 'Returned for correction.')
                : '—',
            'created_at' => $record->created_at,
            'date_submitted' => optional($record->created_at)->format('M d, Y g:i A'),
            'view_url' => '/ofi-form?record='.$record->id.'&from=my-records',
        ];
    }

    private function normalizeUserCarRecord(CarRecord $record): array
    {
        return [
            'id' => $record->id,
            'type' => 'car',
            'type_label' => 'CAR',
            'record_no' => $record->car_no ?: ('CAR #'.$record->id),
            'subject' => $record->dept_section ?: ($record->ref_no ?: '—'),
            'workflow_status' => $record->workflow_status ?: 'draft',
            'resolution_status' => $record->resolution_status ?? '—',
            'remarks' => $record->workflow_status === 'rejected'
                ? ($record->rejection_reason ?: 'Returned for correction.')
                : '—',
            'created_at' => $record->created_at,
            'date_submitted' => optional($record->created_at)->format('M d, Y g:i A'),
            'view_url' => '/car?record='.$record->id.'&from=my-records',
        ];
    }

    private function normalizeUserDcrRecord(DcrRecord $record): array
    {
        return [
            'id' => $record->id,
            'type' => 'dcr',
            'type_label' => 'DCR',
            'record_no' => $record->dcr_no ?: ('DCR #'.$record->id),
            'subject' => $record->to_for ?: ($record->from ?: '—'),
            'workflow_status' => $record->workflow_status ?: 'draft',
            'resolution_status' => $record->resolution_status ?? '—',
            'remarks' => $record->workflow_status === 'rejected'
                ? ($record->rejection_reason ?: 'Returned for correction.')
                : '—',
            'created_at' => $record->created_at,
            'date_submitted' => optional($record->created_at)->format('M d, Y g:i A'),
            'view_url' => '/dcr?record='.$record->id.'&from=my-records',
        ];
    }
}
