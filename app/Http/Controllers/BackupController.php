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

        return Storage::disk('private')->download('backups/'.$latest['filename']);
    }

    public function restore(Request $request): RedirectResponse
    {
        $request->validate([
            'backup_file' => ['required', 'file', 'mimes:zip', 'max:204800'],
        ]);

        $tmpPath = $request->file('backup_file')->getPathname();
        $count = 0;

        try {
            $count = $this->backupService->restore($tmpPath);
        } catch (RuntimeException $e) {
            return back()->with('error', 'Restore failed: '.$e->getMessage());
        }

        $this->activityLogService->log([
            'module' => 'system',
            'action' => 'backup_restored',
            'entity_type' => SystemSetting::class,
            'entity_id' => null,
            'record_label' => $request->file('backup_file')->getClientOriginalName(),
            'description' => "Backup restored: {$count} files",
        ]);

        return back()->with('success', "Backup restored successfully ({$count} files).");
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
