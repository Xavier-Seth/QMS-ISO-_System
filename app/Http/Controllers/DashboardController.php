<?php

namespace App\Http\Controllers;

use App\Models\DocumentUpload;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $startThisMonth = Carbon::now()->startOfMonth();
        $startLastMonth = Carbon::now()->subMonth()->startOfMonth();
        $endLastMonth = Carbon::now()->subMonth()->endOfMonth();

        // ----- TOTAL DOCUMENTS -----
        $totalDocs = DocumentUpload::count();
        $totalDocsThisMonth = DocumentUpload::where('created_at', '>=', $startThisMonth)->count();
        $totalDocsLastMonth = DocumentUpload::whereBetween('created_at', [$startLastMonth, $endLastMonth])->count();

        // ----- DCR (F-QMS-001) -----
        $dcrTotal = DocumentUpload::whereHas('documentType', fn($q) => $q->where('code', 'F-QMS-001'))->count();
        $dcrThisMonth = DocumentUpload::where('created_at', '>=', $startThisMonth)
            ->whereHas('documentType', fn($q) => $q->where('code', 'F-QMS-001'))
            ->count();
        $dcrLastMonth = DocumentUpload::whereBetween('created_at', [$startLastMonth, $endLastMonth])
            ->whereHas('documentType', fn($q) => $q->where('code', 'F-QMS-001'))
            ->count();

        // ----- OFI (F-QMS-007) -----
        $ofiTotal = DocumentUpload::whereHas('documentType', fn($q) => $q->where('code', 'F-QMS-007'))->count();
        $ofiThisMonth = DocumentUpload::where('created_at', '>=', $startThisMonth)
            ->whereHas('documentType', fn($q) => $q->where('code', 'F-QMS-007'))
            ->count();
        $ofiLastMonth = DocumentUpload::whereBetween('created_at', [$startLastMonth, $endLastMonth])
            ->whereHas('documentType', fn($q) => $q->where('code', 'F-QMS-007'))
            ->count();

        // ----- USERS -----
        $usersTotal = User::count();
        $usersThisWeek = User::where('created_at', '>=', Carbon::now()->startOfWeek())->count();
        $usersLastWeek = User::whereBetween('created_at', [
            Carbon::now()->subWeek()->startOfWeek(),
            Carbon::now()->subWeek()->endOfWeek(),
        ])->count();

        $stats = [
            [
                'label' => 'Total Documents',
                'value' => (string) $totalDocs,
                'change' => $this->formatChangeDiff($totalDocsThisMonth - $totalDocsLastMonth, 'this month'),
                'trend' => $this->trendFromDiff($totalDocsThisMonth - $totalDocsLastMonth),
                'icon' => 'docs',
                'color' => '#6366f1',
                'bg' => 'rgba(99,102,241,0.12)',
            ],
            [
                'label' => 'Pending DCR Forms',
                'value' => (string) $dcrTotal,
                'change' => $this->formatChangeDiff($dcrThisMonth - $dcrLastMonth, 'this month'),
                'trend' => $this->trendFromDiff($dcrThisMonth - $dcrLastMonth),
                'icon' => 'dcr',
                'color' => '#f59e0b',
                'bg' => 'rgba(245,158,11,0.12)',
            ],
            [
                'label' => 'Open OFI Items',
                'value' => (string) $ofiTotal,
                'change' => $this->formatChangeDiff($ofiThisMonth - $ofiLastMonth, 'this month'),
                'trend' => $this->trendFromDiff($ofiThisMonth - $ofiLastMonth),
                'icon' => 'ofi',
                'color' => '#10b981',
                'bg' => 'rgba(16,185,129,0.12)',
            ],
            [
                'label' => 'Active Users',
                'value' => (string) $usersTotal,
                'change' => $this->formatChangeDiff($usersThisWeek - $usersLastWeek, 'this week'),
                'trend' => $this->trendFromDiff($usersThisWeek - $usersLastWeek),
                'icon' => 'users',
                'color' => '#3b82f6',
                'bg' => 'rgba(59,130,246,0.12)',
            ],
        ];

        // Recent Activity: latest uploads
        $recentUploads = DocumentUpload::with(['documentType', 'uploader'])
            ->latest()
            ->take(5)
            ->get();

        $recentActivity = $recentUploads->map(function ($u) {
            $code = strtoupper($u->documentType->code ?? '');
            $type = match ($code) {
                'F-QMS-001' => 'dcr',
                'F-QMS-007' => 'ofi',
                default => 'approve',
            };

            return [
                'user' => $u->uploader->name ?? $u->uploader->username ?? 'Unknown',
                'action' => 'uploaded a document',
                'doc' => $u->documentType->code ?? $u->file_name,
                'time' => optional($u->created_at)->diffForHumans() ?? '',
                'type' => $type,
            ];
        })->values();

        // Pending Docs table: show latest DCR/OFI uploads only (matches your card titles)
        $pending = DocumentUpload::with(['documentType', 'uploader'])
            ->whereHas('documentType', fn($q) => $q->whereIn('code', ['F-QMS-001', 'F-QMS-007']))
            ->latest()
            ->take(6)
            ->get();

        $pendingDocs = $pending->map(function ($u) {
            $code = strtoupper($u->documentType->code ?? '');
            $shortType = match ($code) {
                'F-QMS-001' => 'DCR',
                'F-QMS-007' => 'OFI',
                default => 'DOC',
            };

            return [
                'id' => ($u->documentType->code ?? 'DOC') . '-' . $u->id,
                'type' => $shortType,
                'dept' => $u->uploader->role ?? '—',
                'submitted' => optional($u->created_at)->format('M d, Y') ?? '',
                // you only have Active/Obsolete (nullable) on uploads; if null => Uploaded
                'status' => $u->status ?? 'Uploaded',
            ];
        })->values();

        return Inertia::render('Dashboard/Index', [
            'stats' => $stats,
            'recentActivity' => $recentActivity,
            'pendingDocs' => $pendingDocs,
            'authUser' => [
                'name' => auth()->user()->name ?? auth()->user()->username ?? 'User',
                'role' => auth()->user()->role ?? 'User',
            ],
        ]);
    }

    private function trendFromDiff(int $diff): string
    {
        if ($diff > 0)
            return 'up';
        if ($diff < 0)
            return 'down';
        return 'neutral';
    }

    private function formatChangeDiff(int $diff, string $suffix): string
    {
        if ($diff > 0)
            return "+{$diff} {$suffix}";
        if ($diff < 0)
            return "{$diff} {$suffix}";
        return "0 {$suffix}";
    }
}