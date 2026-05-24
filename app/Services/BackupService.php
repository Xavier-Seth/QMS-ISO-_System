<?php

namespace App\Services;

use App\Models\DocumentUpload;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class BackupService
{
    private string $backupDir = 'backups';

    private const ALLOWED_RESTORE_DISKS = ['private', 'public'];

    /** Preview cache fields are excluded from the manifest — they are never backed up. */
    private const PREVIEW_FIELDS = [
        'preview_disk',
        'preview_path',
        'preview_mime',
        'preview_generated_at',
        'preview_last_accessed_at',
        'preview_source_hash',
        'preview_size',
    ];

    /** Framework/transient tables excluded from the database dump and restore. */
    private const SKIP_TABLES = [
        'migrations',
        'password_reset_tokens',
        'sessions',
        'jobs',
        'job_batches',
        'failed_jobs',
        'cache',
        'cache_locks',
    ];

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
                // L1 — skip uploads with no file_name; a null file_name produces a broken zip_entry
                if (blank($upload->file_name)) {
                    Log::warning('[BACKUP] Skipping upload with null file_name', ['upload_id' => $upload->id]);

                    continue;
                }

                $diskName = $upload->getStorageDiskName();
                $storageDisk = Storage::disk($diskName);

                if (! $storageDisk->exists($upload->file_path)) {
                    continue;
                }

                $type = $upload->documentType;
                $seriesCode = $type?->series?->code_prefix ?? 'unknown';
                $typeCode = $type?->code ?? 'unknown';
                $entryName = "{$seriesCode}/{$typeCode}/{$upload->id}_{$upload->file_name}";
                $absolutePath = $storageDisk->path($upload->file_path);
                $zip->addFile($absolutePath, $entryName);
                $filesManifest[] = [
                    'zip_entry' => $entryName,
                    'file_name' => $upload->file_name,
                    'file_path' => $upload->file_path,
                    'storage_disk' => $diskName,
                    // L3 — preview_* fields excluded; they reference cache files not in the backup
                    'upload_row' => array_merge(
                        ['id' => $upload->id],
                        collect($upload->only($upload->getFillable()))
                            ->except(self::PREVIEW_FIELDS)
                            ->toArray()
                    ),
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

        // Note: dumpDatabaseToArray() loads all application table rows into PHP memory before
        // JSON-encoding. For large datasets (e.g. high-volume activity_logs) this can exhaust
        // the PHP memory limit. Monitor backup memory usage and consider archiving old rows
        // before taking a backup if this becomes a constraint.
        $zip->addFromString('database.json', json_encode($this->dumpDatabaseToArray()));
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

    /** @return array{files: int, rows: int} */
    public function restore(string $tmpZipPath): array
    {
        $zip = new ZipArchive;

        if ($zip->open($tmpZipPath) !== true) {
            throw new RuntimeException('Could not open backup archive.');
        }

        if ($zip->numFiles === 0) {
            $zip->close();

            return ['files' => 0, 'rows' => 0];
        }

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

        // Attempt to read database.json — absence is not an error (backward compat with old ZIPs)
        $dbDump = null;
        $dbIndex = $zip->locateName('database.json');

        if ($dbIndex !== false) {
            $stat = $zip->statIndex($dbIndex);

            if ($stat !== false && $stat['size'] > 256 * 1024 * 1024) {
                Log::warning('[RESTORE] database.json exceeds 256 MB — skipping DB restore to prevent memory exhaustion.');
            } else {
                $dbRaw = $zip->getFromIndex($dbIndex);

                if ($dbRaw !== false) {
                    $decoded = json_decode($dbRaw, true);

                    if (is_array($decoded)) {
                        $dbDump = $decoded;
                    } else {
                        Log::warning('[RESTORE] database.json contains invalid JSON — skipping DB restore.');
                    }
                }
            }
        }

        // Key by zip_entry (new format) with file_path fallback for old-format backups
        $diskMap = [];

        foreach ($manifestData['files'] ?? [] as $entry) {
            $key = $entry['zip_entry'] ?? $entry['file_path'];
            $diskMap[$key] = [
                'disk' => $entry['storage_disk'],
                'path' => $entry['file_path'],
                'upload_row' => $entry['upload_row'] ?? null,
            ];
        }

        // Pass 1 — validate all entries and buffer bytes; no files written yet
        $validated = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);

            if ($entryName === false) {
                continue;
            }

            if ($entryName === 'manifest.json' || $entryName === 'database.json' || str_ends_with($entryName, '/')) {
                continue;
            }

            // Zipslip guard: normalize backslashes, reject traversal, absolute paths, and null bytes
            $normalized = str_replace('\\', '/', $entryName);

            if (str_contains($normalized, '..') ||
                str_contains($normalized, "\0") ||
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
            $disk = $target['disk'] ?? 'private';

            // S1 — whitelist allowed disk names to prevent writes to unexpected disks
            if (! in_array($disk, self::ALLOWED_RESTORE_DISKS, true)) {
                $zip->close();
                throw new RuntimeException("Restore refused: unknown storage disk '{$disk}'.");
            }

            $validated[] = [
                'disk' => $disk,
                'path' => $target['path'] ?? $entryName,
                'bytes' => $bytes,
                'upload_row' => $target['upload_row'] ?? null,
            ];
        }

        $zip->close();

        // Pass 2 — all entries validated; write files and restore DB inside a single transaction.
        // FK checks are disabled at the session level (MySQL only) via try/finally so they are
        // always re-enabled even when the transaction throws. File writes already on disk are NOT
        // rolled back on transaction failure. DB atomicity is best-effort: individual chunk
        // failures in restoreDatabase() are caught and logged without aborting the transaction,
        // so a partial table restore is possible when upserts fail on individual chunks.
        $successCount = 0;
        $dbRowCount = 0;
        $isMysql = DB::connection()->getDriverName() === 'mysql';

        if ($isMysql) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        try {
            DB::transaction(function () use ($validated, $dbDump, &$successCount, &$dbRowCount) {
                foreach ($validated as $entry) {
                    Storage::disk($entry['disk'])->put($entry['path'], $entry['bytes']);
                    $successCount++;

                    // When database.json is present all tables are restored from the dump;
                    // per-file upload_row upserts are only used for old ZIPs without database.json.
                    if ($dbDump !== null || empty($entry['upload_row'])) {
                        continue;
                    }

                    $row = $entry['upload_row'];

                    // L2 — skip DB upsert if id is missing; avoids silent auto-increment ID mismatch
                    if (empty($row['id'])) {
                        Log::warning('[RESTORE] Skipping DB upsert — upload_row.id is null or missing', [
                            'file_path' => $entry['path'],
                        ]);

                        continue;
                    }

                    $row['uploaded_by'] = User::find($row['uploaded_by'] ?? null)
                        ? $row['uploaded_by']
                        : null;
                    $uploadId = $row['id'];
                    unset($row['id']);

                    $existing = DocumentUpload::find($uploadId);
                    if ($existing) {
                        $existing->fill($row)->save();
                    } else {
                        $instance = new DocumentUpload;
                        $instance->id = $uploadId;
                        $instance->fill($row)->save();
                    }
                }

                if ($dbDump !== null) {
                    $dbRowCount = $this->restoreDatabase($dbDump);
                }
            });
        } finally {
            if ($isMysql) {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }

        return ['files' => $successCount, 'rows' => $dbRowCount];
    }

    private function applicationTableListing(): array
    {
        // getTableListing() may return schema-qualified names (e.g. "archive_system.users"
        // on MySQL, "main.users" on SQLite). Strip the prefix so DB queries use bare table names.
        return collect(Schema::getTableListing())
            ->map(fn (string $t) => str_contains($t, '.') ? substr($t, strrpos($t, '.') + 1) : $t)
            ->reject(fn (string $t) => in_array($t, self::SKIP_TABLES, true))
            ->unique()
            ->values()
            ->all();
    }

    private function dumpDatabaseToArray(): array
    {
        $tables = $this->applicationTableListing();

        $dump = [];

        foreach ($tables as $table) {
            $rows = [];

            try {
                DB::table($table)->orderBy('id')->chunk(500, function ($chunk) use (&$rows) {
                    foreach ($chunk as $row) {
                        $rows[] = (array) $row;
                    }
                });
            } catch (\Throwable $e) {
                Log::warning('[BACKUP] Could not dump table', ['table' => $table, 'error' => $e->getMessage()]);
            }

            $dump[$table] = $rows;
        }

        return $dump;
    }

    private function restoreDatabase(array $dbDump): int
    {
        $allowed = array_flip($this->applicationTableListing());

        $rowCount = 0;

        foreach ($dbDump as $table => $rows) {
            if (! isset($allowed[$table]) || empty($rows)) {
                continue;
            }

            $updateCols = array_values(array_diff(Schema::getColumnListing($table), ['id']));

            if (empty($updateCols)) {
                continue;
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                try {
                    DB::table($table)->upsert($chunk, ['id'], $updateCols);
                    $rowCount += count($chunk);
                } catch (\Throwable $e) {
                    Log::error('[RESTORE] DB chunk upsert failed', [
                        'table' => $table,
                        'chunk_size' => count($chunk),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $rowCount;
    }
}
