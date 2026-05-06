<?php

namespace App\Services;

use App\Models\DocumentUpload;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class BackupService
{
    private string $backupDir = 'backups';

    public function createBackup(): array
    {
        $disk = Storage::disk('private');

        if (! $disk->exists($this->backupDir)) {
            $disk->makeDirectory($this->backupDir);
        }

        $filename = 'backup_'.now()->format('Y-m-d_His').'.zip';
        $zipPath = $disk->path($this->backupDir.'/'.$filename);

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create backup archive.');
        }

        $uploads = DocumentUpload::all();
        $fileCount = 0;

        foreach ($uploads as $upload) {
            $storageDisk = Storage::disk($upload->getStorageDiskName());

            if (! $storageDisk->exists($upload->file_path)) {
                continue;
            }

            $bytes = $storageDisk->get($upload->file_path);
            $zip->addFromString($upload->file_path, $bytes);
            $fileCount++;
        }

        $manifest = json_encode([
            'created_at' => now()->toIso8601String(),
            'file_count' => $fileCount,
            'app_name' => config('app.name'),
        ]);

        $zip->addFromString('manifest.json', $manifest);
        $zip->close();

        return [
            'filename' => $filename,
            'size' => filesize($zipPath),
            'created_at' => now()->toIso8601String(),
        ];
    }

    public function getLatestBackup(): ?array
    {
        $disk = Storage::disk('private');

        if (! $disk->exists($this->backupDir)) {
            return null;
        }

        $files = collect($disk->files($this->backupDir))
            ->filter(fn (string $f) => str_ends_with($f, '.zip'))
            ->sortByDesc(fn (string $f) => $disk->lastModified($f))
            ->values();

        if ($files->isEmpty()) {
            return null;
        }

        $path = $files->first();

        return [
            'filename' => basename($path),
            'size' => $disk->size($path),
            'created_at' => date('c', $disk->lastModified($path)),
        ];
    }

    public function restore(string $tmpZipPath): int
    {
        $zip = new ZipArchive;

        if ($zip->open($tmpZipPath) !== true) {
            throw new RuntimeException('Could not open backup archive.');
        }

        $disk = Storage::disk('private');
        $count = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);

            if ($entryName === false) {
                continue;
            }

            // Skip manifest and directories
            if ($entryName === 'manifest.json' || str_ends_with($entryName, '/')) {
                continue;
            }

            // Zipslip guard: reject paths with traversal segments
            if (str_contains($entryName, '..') || str_starts_with($entryName, '/')) {
                throw new RuntimeException("Unsafe path in archive: {$entryName}");
            }

            $bytes = $zip->getFromIndex($i);

            if ($bytes === false) {
                continue;
            }

            $disk->put($entryName, $bytes);
            $count++;
        }

        $zip->close();

        return $count;
    }
}
