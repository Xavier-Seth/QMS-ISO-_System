<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LogsController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $department = trim((string) $request->get('department', ''));
        $fileType = trim((string) $request->get('file_type', ''));
        $action = trim((string) $request->get('action', ''));
        $module = trim((string) $request->get('module', ''));
        $dateFrom = trim((string) $request->get('date_from', ''));
        $dateTo = trim((string) $request->get('date_to', ''));

        $query = ActivityLog::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('user_name', 'like', "%{$q}%")
                        ->orWhere('department', 'like', "%{$q}%")
                        ->orWhere('module', 'like', "%{$q}%")
                        ->orWhere('record_label', 'like', "%{$q}%")
                        ->orWhere('action', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%");
                });
            })
            ->when($department !== '', fn($query) => $query->where('department', $department))
            ->when($fileType !== '', fn($query) => $query->where('file_type', $fileType))
            ->when($action !== '', fn($query) => $query->where('action', $action))
            ->when($module !== '', fn($query) => $query->where('module', $module))
            ->when($dateFrom !== '', fn($query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn($query) => $query->whereDate('created_at', '<=', $dateTo))
            ->latest('created_at');

        $logs = $query
            ->paginate(15)
            ->through(function (ActivityLog $log) {
                return [
                    'id' => $log->id,
                    'created_at' => optional($log->created_at)?->format('Y-m-d H:i'),
                    'user_name' => $log->user_name,
                    'department' => $log->department,
                    'module' => ucfirst($log->module),
                    'record_label' => $log->record_label,
                    'action' => str($log->action)->replace('_', ' ')->title(),
                    'description' => $log->description,
                    'file_type' => $log->file_type,
                ];
            })
            ->withQueryString();

        return Inertia::render('Logs/Index', [
            'logs' => $logs,
            'filters' => [
                'q' => $q,
                'department' => $department,
                'file_type' => $fileType,
                'action' => $action,
                'module' => $module,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'options' => [
                'departments' => ActivityLog::query()
                    ->whereNotNull('department')
                    ->where('department', '!=', '')
                    ->distinct()
                    ->orderBy('department')
                    ->pluck('department')
                    ->values(),

                'file_types' => ActivityLog::query()
                    ->whereNotNull('file_type')
                    ->where('file_type', '!=', '')
                    ->distinct()
                    ->orderBy('file_type')
                    ->pluck('file_type')
                    ->values(),

                'actions' => ActivityLog::query()
                    ->whereNotNull('action')
                    ->distinct()
                    ->orderBy('action')
                    ->pluck('action')
                    ->values(),

                'modules' => ActivityLog::query()
                    ->whereNotNull('module')
                    ->distinct()
                    ->orderBy('module')
                    ->pluck('module')
                    ->values(),
            ],
        ]);
    }
}