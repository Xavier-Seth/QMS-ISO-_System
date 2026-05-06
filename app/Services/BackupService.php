<?php

namespace App\Services;

use App\Models\DocumentUpload;
use Illuminate\Support\Facades\Log;
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

        $fileCount = 0;
        $filesManifest = [];

        DocumentUpload::chunk(100, function ($uploads) use ($zip, &$fileCount, &$filesManifest) {
            foreach ($uploads as $upload) {
                $diskName = $upload->getStorageDiskName();
                $storageDisk = Storage::disk($diskName);

                if (! $storageDisk->exists($upload->file_path)) {
                    continue;
                }

                $absolutePath = $storageDisk->path($upload->file_path);
                $zip->addFile($absolutePath, $upload->file_path);
                $filesManifest[] = [
                    'file_path' => $upload->file_path,
                    'storage_disk' => $diskName,
                ];
                $fileCount++;
            }
        });

        $manifest = json_encode([
            'created_at' => now()->toIso8601String(),
            'file_count' => $fileCount,
            'app_name' => config('app.name'),
            'files' => $filesManifest,
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

        $diskMap = [];
        $manifestIndex = $zip->locateName('manifest.json');

        if ($manifestIndex !== false) {
            $manifestData = json_decode($zip->getFromIndex($manifestIndex), true);

            foreach ($manifestData['files'] ?? [] as $entry) {
                $diskMap[$entry['file_path']] = $entry['storage_disk'];
            }
        }

        $count = 0;
        $written = [];

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entryName = $zip->getNameIndex($i);

                if ($entryName === false) {
                    continue;
                }

                // Skip manifest and directories
                if ($entryName === 'manifest.json' || str_ends_with($entryName, '/')) {
                    continue;
                }

                // Zipslip guard: normalize backslashes, reject traversal and absolute paths
                $normalized = str_replace('\\', '/', $entryName);

                if (str_contains($normalized, '..') ||
                    str_starts_with($normalized, '/') ||
                    preg_match('/^[a-zA-Z]:/', $normalized)) {
                    throw new RuntimeException("Unsafe path in archive: {$entryName}");
                }

                $bytes = $zip->getFromIndex($i);

                if ($bytes === false) {
                    continue;
                }

                $diskName = $diskMap[$entryName] ?? 'private';
                Storage::disk($diskName)->put($entryName, $bytes);
                $written[] = ['disk' => $diskName, 'path' => $entryName];
                $count++;
            }
        } catch (\Throwable $e) {
            foreach ($written as $file) {
                try {
                    Storage::disk($file['disk'])->delete($file['path']);
                } catch (\Throwable $deleteError) {
                    Log::warning("Restore rollback: failed to delete {$file['path']} on disk {$file['disk']}: {$deleteError->getMessage()}");
                }
            }

            $zip->close();

            throw new RuntimeException($e->getMessage(), 0, $e);
        }

        $zip->close();

        return $count;
    }
}
