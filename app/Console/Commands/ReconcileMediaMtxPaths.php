<?php

namespace App\Console\Commands;

use App\Models\LiveStream;
use App\Services\Media\MediaMtxClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('media:reconcile-paths')]
#[Description('Restore active live stream paths in MediaMTX')]
class ReconcileMediaMtxPaths extends Command
{
    public function __construct(private MediaMtxClient $mediaMtx)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $synchronized = 0;
        $failed = 0;

        try {
            $configuredPaths = array_fill_keys($this->mediaMtx->configuredPathNames(), true);
        } catch (Throwable $exception) {
            report($exception);
            $this->error(__('media.reconcile_list_failed'));

            return self::FAILURE;
        }

        LiveStream::query()
            ->where('active_slot', 1)
            ->orderBy('id')
            ->lazyById()
            ->each(function (LiveStream $liveStream) use (&$configuredPaths, &$synchronized, &$failed): void {
                if (isset($configuredPaths[$liveStream->path])) {
                    $synchronized++;

                    return;
                }

                try {
                    $this->mediaMtx->addPath($liveStream);
                    $configuredPaths[$liveStream->path] = true;
                    $synchronized++;
                } catch (Throwable $exception) {
                    report($exception);
                    $failed++;

                    $this->error(__('media.reconcile_path_failed', [
                        'path' => $liveStream->path,
                    ]));
                }
            });

        $this->info(__('media.reconcile_summary', [
            'synchronized' => $synchronized,
            'failed' => $failed,
        ]));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
