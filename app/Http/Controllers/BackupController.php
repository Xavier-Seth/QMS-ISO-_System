<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Services\ActivityLogService;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;

class BackupController extends Controller
{
    public function __construct(
        protected BackupService $backupService,
        protected ActivityLogService $activityLogService,
    ) {}

    public function create(Request $request): RedirectResponse
    {
        try {
            $backup = $this->backupService->createBackup();
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->activityLogService->log([
            'module' => 'system',
            'action' => 'backup_created',
            'entity_type' => SystemSetting::class,
            'entity_id' => null,
            'record_label' => $backup['filename'],
            'description' => "Manual backup created: {$backup['filename']}",
        ]);

        return back()->with('success', 'Backup created successfully.');
    }

    public function download(): mixed
    {
        $latest = $this->backupService->getLatestBackup();

        abort_if($latest === null, 404, 'No backup found.');

        $this->activityLogService->log([
            'module' => 'system',
            'action' => 'backup_downloaded',
            'entity_type' => null,
            'entity_id' => null,
            'record_label' => $latest['filename'],
            'description' => "Backup downloaded: {$latest['filename']}",
        ]);

        return Storage::disk('private')->download('backups/'.$latest['filename']);
    }

    public function restore(Request $request): RedirectResponse
    {
        $request->validate([
            'backup_file' => ['required', 'file', 'mimes:zip', 'max:204800'],
        ]);

        $tmpPath = $request->file('backup_file')->getPathname();
        $result = ['files' => 0, 'rows' => 0];

        try {
            $result = $this->backupService->restore($tmpPath);
        } catch (RuntimeException $e) {
            return back()->with('error', 'Restore failed: '.$e->getMessage());
        }

        $this->activityLogService->log([
            'module' => 'system',
            'action' => 'backup_restored',
            'entity_type' => SystemSetting::class,
            'entity_id' => null,
            'record_label' => $request->file('backup_file')->getClientOriginalName(),
            'description' => "Backup restored: {$result['files']} files, {$result['rows']} DB rows",
        ]);

        return back()->with('success', "Backup restored successfully ({$result['files']} files, {$result['rows']} DB rows).");
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $request->validate([
            'backup_frequency' => ['required', 'string', Rule::in(['daily', 'weekly', 'monthly'])],
            'storage_location' => ['required', 'string', Rule::in(['local', 'external', 'cloud'])],
            'auto_backup' => ['required', 'boolean'],
        ]);

        SystemSetting::instance()->update([
            'backup_frequency' => $request->backup_frequency,
            'storage_location' => $request->storage_location,
            'auto_backup' => $request->auto_backup,
        ]);

        $this->activityLogService->log([
            'module' => 'system',
            'action' => 'settings_updated',
            'entity_type' => SystemSetting::class,
            'entity_id' => null,
            'record_label' => 'Backup Settings',
            'description' => 'Backup settings updated',
        ]);

        return back()->with('success', 'Backup settings saved.');
    }
}
