<?php

namespace App\Console\Commands;

use App\Models\DocumentUpload;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class CleanDocumentPreviewCache extends Command
{
    protected $signature = 'document-preview:clean {--days= : Override preview cache TTL in days}';

    protected $description = 'Delete expired cached document preview PDFs and clear their metadata.';

    public function handle(): int
    {
        $ttlDays = $this->resolveTtlDays();
        $cutoff = Carbon::now()->subDays($ttlDays);

        $this->info("Cleaning cached document previews older than {$ttlDays} day(s)...");
        $this->line("Cutoff: {$cutoff->toDateTimeString()}");

        $deletedFiles = 0;
        $clearedRecords = 0;
        $missingFiles = 0;

        DocumentUpload::query()
            ->whereNotNull('preview_disk')
            ->whereNotNull('preview_path')
            ->where(function ($query) use ($cutoff) {
                $query->whereNull('preview_last_accessed_at')
                    ->orWhere('preview_last_accessed_at', '<', $cutoff);
            })
            ->orderBy('id')
            ->chunkById(100, function (Collection $uploads) use (&$deletedFiles, &$clearedRecords, &$missingFiles) {
                /** @var DocumentUpload $upload */
                foreach ($uploads as $upload) {
                    $previewDisk = $upload->getPreviewDiskName();
                    $previewPath = $upload->preview_path;

                    if ($previewDisk && $previewPath && Storage::disk($previewDisk)->exists($previewPath)) {
                        Storage::disk($previewDisk)->delete($previewPath);
                        $deletedFiles++;
                    } else {
                        $missingFiles++;
                    }

                    $upload->clearPreviewCacheMeta();
                    $clearedRecords++;
                }
            });

        $this->newLine();
        $this->info('Preview cache cleanup completed.');
        $this->line("Deleted preview files: {$deletedFiles}");
        $this->line("Records cleared: {$clearedRecords}");
        $this->line("Missing preview files already absent: {$missingFiles}");

        return self::SUCCESS;
    }

    protected function resolveTtlDays(): int
    {
        $option = $this->option('days');

        if ($option !== null && $option !== '') {
            $days = (int) $option;

            return $days > 0 ? $days : 30;
        }

        $days = (int) config('document_preview.cache_ttl_days', 30);

        return $days > 0 ? $days : 30;
    }
}