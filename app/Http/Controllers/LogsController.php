<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class LogsController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        $department = trim((string) $request->input('department', ''));
        $fileType = trim((string) $request->input('file_type', ''));
        $action = trim((string) $request->input('action', ''));
        $module = trim((string) $request->input('module', ''));
        $user = trim((string) $request->input('user', ''));
        $dateFrom = trim((string) $request->input('date_from', ''));
        $dateTo = trim((string) $request->input('date_to', ''));

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
            ->when($department !== '', fn ($query) => $query->where('department', $department))
            ->when($fileType !== '', fn ($query) => $query->where('file_type', $fileType))
            ->when($action !== '', fn ($query) => $query->where('action', $action))
            ->when($module !== '', fn ($query) => $query->where('module', $module))
            ->when($user !== '', fn ($query) => $query->where('user_name', $user))
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('created_at', '<=', $dateTo))
            ->latest('created_at');

        $logs = $query
            ->paginate(15)
            ->through(function (ActivityLog $log) {
                return [
                    'id' => $log->id,
                    'created_at' => optional($log->created_at)?->format('Y-m-d H:i'),
                    'user_name' => $log->user_name,
                    'department' => $log->department,
                    'module' => ucfirst((string) $log->module),
                    'record_label' => $log->record_label,
                    'action' => str((string) $log->action)->replace('_', ' ')->title(),
                    'description' => $log->description,
                    'file_type' => $log->file_type,
                ];
            })
            ->withQueryString();

        $dropdownOptions = DB::table('activity_logs')
            ->selectRaw("'department' as opt_type, department as opt_value")
            ->whereNotNull('department')->where('department', '!=', '')
            ->distinct()
            ->union(
                DB::table('activity_logs')
                    ->selectRaw("'file_type' as opt_type, file_type as opt_value")
                    ->whereNotNull('file_type')->where('file_type', '!=', '')
                    ->distinct()
            )
            ->union(
                DB::table('activity_logs')
                    ->selectRaw("'action' as opt_type, action as opt_value")
                    ->whereNotNull('action')->where('action', '!=', '')
                    ->distinct()
            )
            ->union(
                DB::table('activity_logs')
                    ->selectRaw("'module' as opt_type, module as opt_value")
                    ->whereNotNull('module')->where('module', '!=', '')
                    ->distinct()
            )
            ->orderBy('opt_value')
            ->get()
            ->pipe(function ($rows) {
                $byType = $rows->groupBy('opt_type');

                return [
                    'departments' => $byType->get('department', collect())->pluck('opt_value')->values(),
                    'file_types' => $byType->get('file_type', collect())->pluck('opt_value')->values(),
                    'actions' => $byType->get('action', collect())->pluck('opt_value')->values(),
                    'modules' => $byType->get('module', collect())->pluck('opt_value')->values(),
                ];
            });

        $userOptions = User::query()
            ->whereIn('id', ActivityLog::query()->whereNotNull('user_id')->distinct()->pluck('user_id'))
            ->orderBy('name')
            ->pluck('name')
            ->values();

        return Inertia::render('Logs/Index', [
            'logs' => $logs,
            'filters' => [
                'q' => $q,
                'department' => $department,
                'file_type' => $fileType,
                'action' => $action,
                'module' => $module,
                'user' => $user,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'options' => [
                'departments' => $dropdownOptions['departments'],
                'file_types' => $dropdownOptions['file_types'],
                'actions' => $dropdownOptions['actions'],
                'modules' => $dropdownOptions['modules'],
                'users' => $userOptions,
            ],
        ]);
    }
}
