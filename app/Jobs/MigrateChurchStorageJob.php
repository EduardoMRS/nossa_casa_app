<?php

namespace App\Jobs;

use App\Models\Church;
use App\Models\ChurchStorageBackup;
use App\Models\ClassroomMaterial;
use App\Models\EventMaterial;
use App\Models\Media;
use App\Support\ChurchStorageManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MigrateChurchStorageJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public function __construct(
        public string $churchId,
        public ?string $previousDriver = 'default',
    ) {}

    public function handle(ChurchStorageManager $storageManager): void
    {
        $church = Church::query()->find($this->churchId);
        if (! $church) {
            return;
        }

        $sourceDiskName = ($this->previousDriver && $this->previousDriver !== 'default')
            ? 'church_'.$church->id
            : (string) config('media.disk', 'media');

        $sourceDisk = Storage::disk($sourceDiskName);
        $targetDiskName = $storageManager->diskNameFor($church);
        $targetDisk = $storageManager->diskFor($church);

        // Same storage disk, no migration needed
        if ($sourceDiskName === $targetDiskName) {
            return;
        }

        $backup = ChurchStorageBackup::query()->create([
            'church_id' => $church->id,
            'source_driver' => $this->previousDriver ?? 'default',
            'target_driver' => $church->storageSetting?->driver ?? 'default',
            'backup_disk' => 'local',
            'backup_directory' => 'direct_migration',
            'status' => 'in_progress',
            'cleanup_at' => now()->addDays(3),
        ]);

        $filesCount = 0;
        $totalBytes = 0;

        try {
            // 1. Migrate Church Media (copy directly to target disk, keep source intact)
            $medias = Media::query()->where('church_id', $church->id)->get();
            foreach ($medias as $media) {
                if (filled($media->file_path) && $sourceDisk->exists($media->file_path)) {
                    $bytes = $this->copyFile($media->file_path, $sourceDisk, $targetDisk);
                    $totalBytes += $bytes;
                    $filesCount++;
                }

                if (filled($media->optimized_path) && $sourceDisk->exists($media->optimized_path)) {
                    $bytes = $this->copyFile($media->optimized_path, $sourceDisk, $targetDisk);
                    $totalBytes += $bytes;
                    $filesCount++;
                }

                $media->update(['disk' => $targetDiskName]);
            }

            // 2. Migrate Event Materials
            $eventMaterials = EventMaterial::query()
                ->whereHas('event', fn ($query) => $query->where('church_id', $church->id))
                ->get();

            foreach ($eventMaterials as $material) {
                if (filled($material->file_path) && $sourceDisk->exists($material->file_path)) {
                    $bytes = $this->copyFile($material->file_path, $sourceDisk, $targetDisk);
                    $totalBytes += $bytes;
                    $filesCount++;
                }
                $material->update(['disk' => $targetDiskName]);
            }

            // 3. Migrate Classroom Materials
            $classroomMaterials = ClassroomMaterial::query()
                ->whereHas('classroom', fn ($query) => $query->where('church_id', $church->id))
                ->get();

            foreach ($classroomMaterials as $material) {
                if (filled($material->file_path) && $sourceDisk->exists($material->file_path)) {
                    $bytes = $this->copyFile($material->file_path, $sourceDisk, $targetDisk);
                    $totalBytes += $bytes;
                    $filesCount++;
                }
                $material->update(['disk' => $targetDiskName]);
            }

            // 4. Migrate Branding Logo
            $branding = $church->settings?->options['branding'] ?? [];
            if (! empty($branding['logo_path']) && $sourceDisk->exists($branding['logo_path'])) {
                $bytes = $this->copyFile($branding['logo_path'], $sourceDisk, $targetDisk);
                $totalBytes += $bytes;
                $filesCount++;
            }

            $backup->update([
                'status' => 'completed',
                'files_count' => $filesCount,
                'total_bytes' => $totalBytes,
            ]);
        } catch (Throwable $e) {
            Log::error("Church {$church->id} storage migration failed: ".$e->getMessage());

            $backup->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'files_count' => $filesCount,
                'total_bytes' => $totalBytes,
            ]);

            throw $e;
        }
    }

    /**
     * Directly copies a file from source disk to target disk via stream, preserving source file.
     */
    protected function copyFile(
        string $filePath,
        mixed $sourceDisk,
        mixed $targetDisk,
    ): int {
        $size = 0;
        try {
            $size = (int) $sourceDisk->size($filePath);
        } catch (Throwable) {
            // size lookup might fail on some drivers
        }

        $stream = $sourceDisk->readStream($filePath);
        if (is_resource($stream)) {
            $targetDisk->writeStream($filePath, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $size;
    }
}
