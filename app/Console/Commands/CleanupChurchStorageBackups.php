<?php

namespace App\Console\Commands;

use App\Models\ChurchStorageBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CleanupChurchStorageBackups extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'church:cleanup-storage-backups';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up local storage backups that exceeded the retention period (default 3 days)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $expiredBackups = ChurchStorageBackup::query()
            ->where('cleanup_at', '<=', now())
            ->get();

        $deletedCount = 0;

        foreach ($expiredBackups as $backup) {
            try {
                if (filled($backup->backup_directory)) {
                    $disk = Storage::disk($backup->backup_disk ?: 'local');
                    if ($disk->exists($backup->backup_directory)) {
                        $disk->deleteDirectory($backup->backup_directory);
                    }
                }

                $backup->delete();
                $deletedCount++;
            } catch (Throwable $e) {
                $this->error("Failed to delete backup {$backup->id}: ".$e->getMessage());
            }
        }

        $this->info("Cleaned up {$deletedCount} expired church storage backup(s).");

        return self::SUCCESS;
    }
}
