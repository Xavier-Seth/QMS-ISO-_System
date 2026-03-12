<?php

namespace App\Services\DocumentPreview;

use App\Models\DocumentUpload;
use Illuminate\Support\Facades\Storage;
use RuntimeException;


class DocumentDownloadService
{
    public function download(DocumentUpload $upload)
    {
        $disk = $upload->getStorageDiskName();
        $path = $upload->file_path;

        if (!$path) {
            throw new RuntimeException('Document file path is missing.');
        }

        if (!Storage::disk($disk)->exists($path)) {
            throw new RuntimeException("Original file not found on disk [{$disk}] at path [{$path}].");
        }

        $absolutePath = Storage::disk($disk)->path($path);

        $downloadName = $this->resolveDownloadFilename($upload);
        $mime = $this->detectMimeType($disk, $path);

        return response()->download(
            $absolutePath,
            $downloadName,
            [
                'Content-Type' => $mime ?: 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, max-age=0, must-revalidate',
                'Pragma' => 'public',
            ]
        );
    }

    protected function resolveDownloadFilename(DocumentUpload $upload): string
    {
        if (filled($upload->file_name)) {
            return $upload->file_name;
        }

        return basename((string) $upload->file_path) ?: 'document';
    }

    protected function detectMimeType(string $disk, string $path): ?string
    {
        $absolutePath = Storage::disk($disk)->path($path);

        if (!is_file($absolutePath)) {
            return null;
        }

        return mime_content_type($absolutePath) ?: null;
    }
}