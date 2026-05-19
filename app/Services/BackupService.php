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

        $filename = 'backup_'.now()->format('Y-m-d_His').'_'.substr(uniqid(), -6).'.zip';
        $zipPath = $disk->path($this->backupDir.'/'.$filename);

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create backup archive.');
        }

        $fileCount = 0;
        $filesManifest = [];

        DocumentUpload::with('documentType.series')->chunk(100, function ($uploads) use ($zip, &$fileCount, &$filesManifest) {
            foreach ($uploads as $upload) {
                $diskName = $upload->getStorageDiskName();
                $storageDisk = Storage::disk($diskName);

                if (! $storageDisk->exists($upload->file_path)) {
                    continue;
                }

                $type = $upload->documentType;
                $seriesCode = $type?->series?->code_prefix ?? 'unknown';
                $typeCode = $type?->code ?? 'unknown';
                $entryName = "{$seriesCode}/{$typeCode}/{$upload->file_name}";
                $absolutePath = $storageDisk->path($upload->file_path);
                $zip->addFile($absolutePath, $entryName);
                $filesManifest[] = [
                    'zip_entry' => $entryName,
                    'file_name' => $upload->file_name,
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

        if ($zip->numFiles === 0) {
            $zip->close();

            return 0;
        }

        // Issue B: require a readable, valid manifest in any non-empty archive
        $manifestIndex = $zip->locateName('manifest.json');

        if ($manifestIndex === false) {
            $zip->close();
            throw new RuntimeException('Backup archive is missing manifest.json.');
        }

        $manifestRaw = $zip->getFromIndex($manifestIndex);

        if ($manifestRaw === false) {
            $zip->close();
            throw new RuntimeException('Could not read manifest.json from archive.');
        }

        $manifestData = json_decode($manifestRaw, true);

        if (! is_array($manifestData)) {
            $zip->close();
            throw new RuntimeException('manifest.json is corrupt or contains invalid JSON.');
        }

        // Key by zip_entry (new format) with file_path fallback for old-format backups
        $diskMap = [];

        foreach ($manifestData['files'] ?? [] as $entry) {
            $key = $entry['zip_entry'] ?? $entry['file_path'];
            $diskMap[$key] = [
                'disk' => $entry['storage_disk'],
                'path' => $entry['file_path'],
            ];
        }

        // Pass 1 — validate all entries and buffer bytes; no files written yet
        $validated = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);

            if ($entryName === false) {
                continue;
            }

            if ($entryName === 'manifest.json' || str_ends_with($entryName, '/')) {
                continue;
            }

            // Zipslip guard: normalize backslashes, reject traversal and absolute paths
            $normalized = str_replace('\\', '/', $entryName);

            if (str_contains($normalized, '..') ||
                str_starts_with($normalized, '/') ||
                preg_match('/^[a-zA-Z]:/', $normalized)) {
                $zip->close();
                throw new RuntimeException("Unsafe path in archive: {$entryName}");
            }

            $bytes = $zip->getFromIndex($i);

            if ($bytes === false) {
                $zip->close();
                throw new RuntimeException("Could not read entry from archive: {$entryName}");
            }

            $target = $diskMap[$entryName] ?? null;
            $validated[] = [
                'disk' => $target['disk'] ?? 'private',
                'path' => $target['path'] ?? $entryName,
                'bytes' => $bytes,
            ];
        }

        $zip->close();

        // Pass 2 — all entries validated; write atomically
        $successCount = 0;
        $failures = [];

        foreach ($validated as $entry) {
            $written = Storage::disk($entry['disk'])->put($entry['path'], $entry['bytes']);
            if ($written) {
                $successCount++;
            } else {
                $failures[] = $entry['path'];
            }
        }

        if (! empty($failures)) {
            throw new RuntimeException(
                'Restore partially failed. Could not write '.count($failures).' file(s): '
                .implode(', ', array_slice($failures, 0, 5))
            );
        }

        return $successCount;
    }
}
