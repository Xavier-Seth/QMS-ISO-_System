<?php

namespace App\Console\Commands;

use App\Models\DocumentUpload;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigratePublicFilesToPrivate extends Command
{
    protected $signature = 'storage:migrate-private {--dry-run : Report what would be moved without changing anything}';

    protected $description = 'One-off security migration: move manual files, the e-signature, and profile photos from the public disk to the private disk (H-1 fix). Safe to re-run.';

    private bool $dryRun = false;

    private int $moved = 0;

    private int $skipped = 0;

    private int $missing = 0;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        if ($this->dryRun) {
            $this->warn('Dry run — no files or database rows will be changed.');
        }

        $this->migrateManualUploads();
        $this->migrateSignature();
        $this->migrateProfilePhotos();

        $this->newLine();
        $this->info("Done. Moved: {$this->moved}, already private: {$this->skipped}, missing on both disks: {$this->missing}.");

        return $this->missing > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function migrateManualUploads(): void
    {
        $uploads = DocumentUpload::query()
            ->where('storage_disk', 'public')
            ->where('file_path', 'like', 'manuals/%')
            ->orderBy('id')
            ->get();

        $this->line("Manual uploads on public disk: {$uploads->count()}");

        foreach ($uploads as $upload) {
            if (! $this->moveToPrivate($upload->file_path, "manual upload #{$upload->id}")) {
                continue;
            }

            if (! $this->dryRun) {
                $upload->update(['storage_disk' => 'private']);
            }
        }
    }

    private function migrateSignature(): void
    {
        $settings = SystemSetting::first();
        $path = $settings?->e_signature_path;

        if (! $path) {
            $this->line('E-signature: none configured.');

            return;
        }

        $this->moveToPrivate($path, 'e-signature');
    }

    private function migrateProfilePhotos(): void
    {
        $users = User::query()->whereNotNull('profile_photo')->orderBy('id')->get();

        $this->line("Users with profile photos: {$users->count()}");

        foreach ($users as $user) {
            $this->moveToPrivate($user->profile_photo, "profile photo of user #{$user->id}");
        }
    }

    /**
     * Copy a file from the public disk to the same relative path on the private
     * disk, then delete the public copy. Returns true when the file ends up on
     * the private disk (moved now or already there).
     */
    private function moveToPrivate(string $path, string $label): bool
    {
        if (Storage::disk('private')->exists($path)) {
            if (Storage::disk('public')->exists($path) && ! $this->dryRun) {
                Storage::disk('public')->delete($path);
            }

            $this->line("  [skip] {$label} — already on private disk ({$path})");
            $this->skipped++;

            return true;
        }

        if (! Storage::disk('public')->exists($path)) {
            $this->error("  [missing] {$label} — not found on public or private disk ({$path})");
            $this->missing++;

            return false;
        }

        if (! $this->dryRun) {
            $stream = Storage::disk('public')->readStream($path);
            Storage::disk('private')->writeStream($path, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

            if (! Storage::disk('private')->exists($path)) {
                $this->error("  [failed] {$label} — copy to private disk failed ({$path})");
                $this->missing++;

                return false;
            }

            Storage::disk('public')->delete($path);
        }

        $this->line('  '.($this->dryRun ? '[would move] ' : '[moved] ')."{$label} ({$path})");
        $this->moved++;

        return true;
    }
}
