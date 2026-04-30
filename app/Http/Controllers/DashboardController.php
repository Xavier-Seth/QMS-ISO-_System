<?php

namespace App\Http\Controllers;

use App\Models\CarRecord;
use App\Models\DcrRecord;
use App\Models\DocumentSeries;
use App\Models\DocumentType;
use App\Models\DocumentUpload;
use App\Models\OfiRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // ── Summary Counts ───────────────────────────────────────────────────
        $totalDocumentTypes = DocumentType::query()
            ->whereHas('series', fn($q) => $q->where('code_prefix', '!=', 'MANUAL'))
            ->count();

        $activeDocumentTypes = DocumentType::query()
            ->whereHas('series', fn($q) => $q->where('code_prefix', '!=', 'MANUAL'))
            ->whereRaw("LOWER(COALESCE(status,'active')) = 'active'")
            ->count();

        $obsoleteDocumentTypes = DocumentType::query()
            ->whereHas('series', fn($q) => $q->where('code_prefix', '!=', 'MANUAL'))
            ->whereRaw("LOWER(COALESCE(status,'active')) = 'obsolete'")
            ->count();

        $totalUploads = DocumentUpload::query()
            ->whereHas('documentType.series', fn($q) => $q->where('code_prefix', '!=', 'MANUAL'))
            ->count();

        $totalOfi = OfiRecord::count();
        $totalDcr = DcrRecord::count();
        $totalCar = CarRecord::count();

        $pendingOfi = OfiRecord::where('workflow_status', 'pending')->count();
        $pendingDcr = DcrRecord::where('workflow_status', 'pending')->count();
        $pendingCar = CarRecord::where('workflow_status', 'pending')->count();

        // ── Documents needing revision ────────────────────────────────────────
        // These are revision-controlled types that have NO active upload (i.e. no current active version)
        $needsRevision = DocumentType::query()
            ->with('series:id,code_prefix,name')
            ->whereHas('series', fn($q) => $q->where('code_prefix', '!=', 'MANUAL'))
            ->where('requires_revision', true)
            ->whereRaw("LOWER(COALESCE(status,'active')) = 'active'")
            ->whereDoesntHave('uploads', fn($q) => $q->where('status', 'Active'))
            ->select(['id', 'code', 'title', 'series_id', 'updated_at'])
            ->orderBy('code')
            ->limit(10)
            ->get()
            ->map(fn(DocumentType $t) => [
                'id' => $t->id,
                'code' => $t->code,
                'title' => $t->title,
                'series_code' => $t->series?->code_prefix,
            ])
            ->values();

        // ── Recently uploaded documents ───────────────────────────────────────
        $recentUploads = DocumentUpload::query()
            ->with(['documentType:id,code,title', 'uploader:id,name'])
            ->whereHas('documentType.series', fn($q) => $q->where('code_prefix', '!=', 'MANUAL'))
            ->whereNull('ofi_record_id')
            ->whereNull('dcr_record_id')
            ->whereNull('car_record_id')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn(DocumentUpload $u) => [
                'id' => $u->id,
                'file_name' => $u->file_name,
                'doc_code' => $u->documentType?->code,
                'doc_title' => $u->documentType?->title,
                'uploader' => $u->uploader?->name ?? '—',
                'revision' => $u->revision,
                'uploaded_at' => $u->created_at?->diffForHumans(),
            ])
            ->values();

        // ── Distribution by series ────────────────────────────────────────────
        $seriesDistribution = DocumentSeries::query()
            ->where('code_prefix', '!=', 'MANUAL')
            ->whereNotIn('code_prefix', ['IPCR', 'DPCR', 'UPCR'])
            ->withCount([
                'types as total_types',
                'types as active_types' => fn($q) => $q->whereRaw("LOWER(COALESCE(status,'active')) = 'active'"),
                'types as obsolete_types' => fn($q) => $q->whereRaw("LOWER(COALESCE(status,'active')) = 'obsolete'"),
            ])
            ->orderBy('code_prefix')
            ->get()
            ->map(fn(DocumentSeries $s) => [
                'series' => $s->code_prefix,
                'name' => $s->name,
                'total_types' => (int) $s->total_types,
                'active_types' => (int) $s->active_types,
                'obsolete_types' => (int) $s->obsolete_types,
            ])
            ->filter(fn($s) => $s['total_types'] > 0)
            ->values();

        // ── Recent QMS form activity (last 5 records across OFI / DCR / CAR) ─
        $activityRows = DB::table('ofi_records')
            ->selectRaw("'OFI' as type, id, ofi_no as record_no_raw, created_by, workflow_status, updated_at")
            ->whereNotNull('workflow_status')
            ->unionAll(
                DB::table('dcr_records')
                    ->selectRaw("'DCR' as type, id, dcr_no as record_no_raw, created_by, workflow_status, updated_at")
                    ->whereNotNull('workflow_status')
            )
            ->unionAll(
                DB::table('car_records')
                    ->selectRaw("'CAR' as type, id, car_no as record_no_raw, created_by, workflow_status, updated_at")
                    ->whereNotNull('workflow_status')
            )
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        $creators = User::whereIn('id', $activityRows->pluck('created_by')->filter()->unique()->values()->all())
            ->pluck('name', 'id');

        $recentActivity = $activityRows->map(fn($row) => [
            'type' => $row->type,
            'record_no' => $row->record_no_raw ?: ($row->type . ' #' . $row->id),
            'actor' => $creators->get($row->created_by) ?? '—',
            'workflow_status' => $row->workflow_status,
            'updated_at' => Carbon::parse($row->updated_at)->diffForHumans(),
        ])->values();

        // ── Yearly Statistics (OFI / DCR / CAR) ──────────────────────────────
        // "Closed" = workflow_status = 'approved'
        $yearlyStats = DB::table('ofi_records')
            ->selectRaw("'OFI' as type, YEAR(created_at) as year, COUNT(*) as total, SUM(workflow_status = 'approved') as closed")
            ->groupByRaw('YEAR(created_at)')
            ->unionAll(
                DB::table('dcr_records')
                    ->selectRaw("'DCR' as type, YEAR(created_at) as year, COUNT(*) as total, SUM(workflow_status = 'approved') as closed")
                    ->groupByRaw('YEAR(created_at)')
            )
            ->unionAll(
                DB::table('car_records')
                    ->selectRaw("'CAR' as type, YEAR(created_at) as year, COUNT(*) as total, SUM(workflow_status = 'approved') as closed")
                    ->groupByRaw('YEAR(created_at)')
            )
            ->get()
            ->groupBy('year')
            ->map(function ($rows, $year) {
                $byType = collect($rows)->keyBy('type');
                $ofi = $byType->get('OFI');
                $dcr = $byType->get('DCR');
                $car = $byType->get('CAR');

                $ofiTotal = (int) ($ofi->total ?? 0);
                $ofiClosed = (int) ($ofi->closed ?? 0);
                $dcrTotal = (int) ($dcr->total ?? 0);
                $dcrClosed = (int) ($dcr->closed ?? 0);
                $carTotal = (int) ($car->total ?? 0);
                $carClosed = (int) ($car->closed ?? 0);

                $grandTotal = $ofiTotal + $dcrTotal + $carTotal;
                $grandClosed = $ofiClosed + $dcrClosed + $carClosed;

                return [
                    'year' => (int) $year,
                    'ofi_total' => $ofiTotal,
                    'ofi_closed' => $ofiClosed,
                    'dcr_total' => $dcrTotal,
                    'dcr_closed' => $dcrClosed,
                    'car_total' => $carTotal,
                    'car_closed' => $carClosed,
                    'grand_total' => $grandTotal,
                    'grand_closed' => $grandClosed,
                    // close rate as integer percent (0–100) for the bar fill
                    'close_rate' => $grandTotal > 0
                        ? (int) round($grandClosed / $grandTotal * 100)
                        : 0,
                ];
            })
            ->sortKeys()
            ->values();

        return Inertia::render('Dashboard', [
            'summary' => [
                'total_document_types' => $totalDocumentTypes,
                'active_document_types' => $activeDocumentTypes,
                'obsolete_document_types' => $obsoleteDocumentTypes,
                'total_uploads' => $totalUploads,
                'total_ofi' => $totalOfi,
                'total_dcr' => $totalDcr,
                'total_car' => $totalCar,
                'pending_ofi' => $pendingOfi,
                'pending_dcr' => $pendingDcr,
                'pending_car' => $pendingCar,
            ],
            'needs_revision' => $needsRevision,
            'recent_uploads' => $recentUploads,
            'series_distribution' => $seriesDistribution,
            'recent_activity' => $recentActivity,
            'yearly_stats' => $yearlyStats,
        ]);
    }
}
