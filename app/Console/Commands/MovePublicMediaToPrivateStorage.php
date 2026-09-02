<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

#[Signature('media:storage:migrate {--dry-run : List files without moving them}')]
#[Description('Move legacy public uploads into the private media disk')]
class MovePublicMediaToPrivateStorage extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $publicDisk = Storage::disk('public');
        $privateDisk = Storage::disk((string) config('media.disk'));
        $moved = 0;

        foreach ($publicDisk->allFiles() as $path) {
            if ($privateDisk->exists($path)) {
                $this->warn("Removed duplicate public file: {$path}");

                if (! $this->option('dry-run')) {
                    $publicDisk->delete($path);
                    $moved++;
                }

                continue;
            }

            $this->line($path);

            if ($this->option('dry-run')) {
                continue;
            }

            $stream = $publicDisk->readStream($path);

            if (! is_resource($stream)) {
                throw new RuntimeException(__('media.public_file_read_failed', ['path' => $path]));
            }

            try {
                $stored = $privateDisk->writeStream($path, $stream);
            } finally {
                fclose($stream);
            }

            if (! $stored) {
                throw new RuntimeException(__('media.private_file_store_failed', ['path' => $path]));
            }

            $publicDisk->delete($path);
            $moved++;
        }

        $this->info("Moved {$moved} file(s) to private storage.");

        return self::SUCCESS;
    }
}
