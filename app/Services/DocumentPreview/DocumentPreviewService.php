<?php

namespace App\Services\DocumentPreview;

use App\Models\DocumentUpload;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentPreviewService
{
    public function __construct(
        protected OfficeToPdfConverter $converter
    ) {
    }

    public function preview(DocumentUpload $upload): BinaryFileResponse
    {
        if ($this->isDirectPdf($upload)) {
            return $this->buildInlineResponse(
                $this->getOriginalAbsolutePath($upload),
                'application/pdf',
                $this->buildPreviewFilename($upload)
            );
        }

        if (!$this->isOfficeConvertible($upload)) {
            throw new RuntimeException('This file type is not supported for inline preview.');
        }

        $previewAbsolutePath = $this->ensureOfficePreviewExists($upload);

        $upload->markPreviewAccessed();

        return $this->buildInlineResponse(
            $previewAbsolutePath,
            $upload->fresh()->preview_mime ?: 'application/pdf',
            $this->buildPreviewFilename($upload)
        );
    }

    public function canPreview(DocumentUpload $upload): bool
    {
        return $this->isDirectPdf($upload) || $this->isOfficeConvertible($upload);
    }

    protected function ensureOfficePreviewExists(DocumentUpload $upload): string
    {
        $originalAbsolutePath = $this->getOriginalAbsolutePath($upload);
        $sourceHash = $this->generateSourceHash($originalAbsolutePath);

        if ($this->hasReusablePreview($upload, $sourceHash)) {
            return Storage::disk($upload->getPreviewDiskName())->path($upload->preview_path);
        }

        $lockKey = $this->buildPreviewLockKey($upload, $sourceHash);
        $lockTimeout = (int) config('document_preview.lock_timeout', 180);
        $lockWaitSeconds = (int) config('document_preview.lock_wait_seconds', 15);

        $previewLock = Cache::lock($lockKey, $lockTimeout);

        try {
            $previewLock->block($lockWaitSeconds);

            $upload->refresh();

            if ($this->hasReusablePreview($upload, $sourceHash)) {
                return Storage::disk($upload->getPreviewDiskName())->path($upload->preview_path);
            }

            return $this->generateAndStorePreviewWithGlobalLimit(
                $upload,
                $originalAbsolutePath,
                $sourceHash
            );
        } catch (LockTimeoutException $e) {
            return $this->waitForPreviewOrFail($upload->fresh(), $sourceHash);
        } finally {
            optional($previewLock)->release();
        }
    }

    protected function generateAndStorePreviewWithGlobalLimit(
        DocumentUpload $upload,
        string $originalAbsolutePath,
        string $sourceHash
    ): string {
        $globalLimitEnabled = (bool) config('document_preview.global_conversion_limit_enabled', true);

        if (!$globalLimitEnabled) {
            return $this->generateAndStorePreview($upload, $originalAbsolutePath, $sourceHash);
        }

        $globalLockKey = (string) config('document_preview.global_conversion_lock_key', 'office-preview-global-conversion');
        $globalLockTimeout = (int) config('document_preview.global_conversion_lock_timeout', 180);
        $globalWaitSeconds = (int) config('document_preview.global_conversion_wait_seconds', 30);

        $globalLock = Cache::lock($globalLockKey, $globalLockTimeout);

        try {
            $globalLock->block($globalWaitSeconds);

            $upload->refresh();

            if ($this->hasReusablePreview($upload, $sourceHash)) {
                return Storage::disk($upload->getPreviewDiskName())->path($upload->preview_path);
            }

            return $this->generateAndStorePreview($upload, $originalAbsolutePath, $sourceHash);
        } catch (LockTimeoutException $e) {
            return $this->waitForPreviewOrFail($upload->fresh(), $sourceHash, $globalWaitSeconds);
        } finally {
            optional($globalLock)->release();
        }
    }

    protected function generateAndStorePreview(
        DocumentUpload $upload,
        string $originalAbsolutePath,
        string $sourceHash
    ): string {
        $previewDisk = config('document_preview.preview_disk', 'private');
        $previewPath = $this->generatePreviewPath($upload, $sourceHash);
        $previewAbsolutePath = Storage::disk($previewDisk)->path($previewPath);

        $this->deleteExistingPreviewFileIfAny($upload);

        $this->ensureParentDirectoryExists($previewAbsolutePath);

        $this->converter->convertToPdf($originalAbsolutePath, $previewAbsolutePath);

        clearstatcache(true, $previewAbsolutePath);

        if (!is_file($previewAbsolutePath)) {
            throw new RuntimeException('Preview PDF was not generated successfully.');
        }

        $upload->forceFill([
            'preview_disk' => $previewDisk,
            'preview_path' => $previewPath,
            'preview_mime' => 'application/pdf',
            'preview_generated_at' => now(),
            'preview_last_accessed_at' => now(),
            'preview_source_hash' => $sourceHash,
            'preview_size' => filesize($previewAbsolutePath) ?: 0,
        ])->save();

        return $previewAbsolutePath;
    }

    protected function waitForPreviewOrFail(
        DocumentUpload $upload,
        string $sourceHash,
        ?int $waitSeconds = null
    ): string {
        $maxWaitSeconds = $waitSeconds ?? (int) config('document_preview.lock_wait_seconds', 15);
        $pollMs = (int) config('document_preview.lock_poll_interval_ms', 250);
        $maxIterations = max(1, (int) ceil(($maxWaitSeconds * 1000) / $pollMs));

        for ($i = 0; $i < $maxIterations; $i++) {
            usleep($pollMs * 1000);

            $upload->refresh();

            if ($this->hasReusablePreview($upload, $sourceHash)) {
                return Storage::disk($upload->getPreviewDiskName())->path($upload->preview_path);
            }
        }

        throw new RuntimeException('Preview generation is already in progress. Please try again in a moment.');
    }

    protected function buildPreviewLockKey(DocumentUpload $upload, string $sourceHash): string
    {
        return "document-preview:upload:{$upload->id}:hash:{$sourceHash}";
    }

    protected function hasReusablePreview(DocumentUpload $upload, string $currentSourceHash): bool
    {
        if (!$upload->hasPreviewCache()) {
            return false;
        }

        if ($upload->preview_source_hash !== $currentSourceHash) {
            return false;
        }

        $previewDisk = $upload->getPreviewDiskName();
        if (!$previewDisk) {
            return false;
        }

        if (!Storage::disk($previewDisk)->exists($upload->preview_path)) {
            return false;
        }

        return true;
    }

    protected function deleteExistingPreviewFileIfAny(DocumentUpload $upload): void
    {
        if (!$upload->hasPreviewCache()) {
            return;
        }

        $previewDisk = $upload->getPreviewDiskName();

        if ($previewDisk && Storage::disk($previewDisk)->exists($upload->preview_path)) {
            Storage::disk($previewDisk)->delete($upload->preview_path);
        }

        $upload->clearPreviewCacheMeta();
    }

    protected function isDirectPdf(DocumentUpload $upload): bool
    {
        $extension = $this->getExtension($upload->file_name ?: $upload->file_path);

        if ($extension === 'pdf') {
            return true;
        }

        $mime = $this->detectOriginalMime($upload);

        return in_array(
            $mime,
            config('document_preview.direct_preview_mimes', ['application/pdf']),
            true
        );
    }

    protected function isOfficeConvertible(DocumentUpload $upload): bool
    {
        return in_array(
            $this->getExtension($upload->file_name ?: $upload->file_path),
            config('document_preview.office_extensions', []),
            true
        );
    }

    protected function getOriginalAbsolutePath(DocumentUpload $upload): string
    {
        $disk = $upload->getStorageDiskName();

        if (!Storage::disk($disk)->exists($upload->file_path)) {
            throw new RuntimeException("Original file not found on disk [{$disk}] at path [{$upload->file_path}].");
        }

        return Storage::disk($disk)->path($upload->file_path);
    }

    protected function detectOriginalMime(DocumentUpload $upload): ?string
    {
        $disk = $upload->getStorageDiskName();

        if (!Storage::disk($disk)->exists($upload->file_path)) {
            return null;
        }

        $absolutePath = Storage::disk($disk)->path($upload->file_path);

        if (!is_file($absolutePath)) {
            return null;
        }

        return mime_content_type($absolutePath) ?: null;
    }

    protected function generateSourceHash(string $absolutePath): string
    {
        $hash = hash_file('sha256', $absolutePath);

        if ($hash === false) {
            throw new RuntimeException('Unable to generate source hash for preview caching.');
        }

        return $hash;
    }

    protected function generatePreviewPath(DocumentUpload $upload, string $sourceHash): string
    {
        $baseDirectory = trim(config('document_preview.preview_directory', 'previews'), '/');
        $folderA = substr($sourceHash, 0, 2);
        $folderB = substr($sourceHash, 2, 2);
        $safeFilename = Str::slug(pathinfo($upload->file_name ?: 'document', PATHINFO_FILENAME));
        $safeFilename = $safeFilename !== '' ? $safeFilename : 'document';

        return "{$baseDirectory}/{$folderA}/{$folderB}/upload-{$upload->id}-{$safeFilename}-{$sourceHash}.pdf";
    }

    protected function ensureParentDirectoryExists(string $absolutePath): void
    {
        $directory = dirname($absolutePath);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create preview directory: {$directory}");
        }
    }

    protected function buildInlineResponse(
        string $absolutePath,
        string $mime,
        string $downloadName
    ): BinaryFileResponse {
        return response()->file($absolutePath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . addslashes($downloadName) . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'Pragma' => 'public',
        ]);
    }

    protected function buildPreviewFilename(DocumentUpload $upload): string
    {
        $baseName = pathinfo($upload->file_name ?: 'document', PATHINFO_FILENAME);

        return ($baseName !== '' ? $baseName : 'document') . '.pdf';
    }

    protected function getExtension(?string $value): string
    {
        return strtolower(pathinfo((string) $value, PATHINFO_EXTENSION));
    }
}