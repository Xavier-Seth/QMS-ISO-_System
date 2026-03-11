<?php

namespace App\Services\DocumentPreview;

use App\Models\DocumentUpload;
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
            $upload->preview_mime ?: 'application/pdf',
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