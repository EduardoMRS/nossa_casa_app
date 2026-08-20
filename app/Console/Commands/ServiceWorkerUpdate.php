<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ServiceWorkerUpdate extends Command
{
    protected $signature = 'service-worker:update';

    protected $description = 'Validate the dynamic PWA manifest and service worker source';

    public function handle(): int
    {
        if (! is_file(resource_path('pwa/sw.js'))) {
            $this->error(__('pwa.command_source_missing'));

            return self::FAILURE;
        }

        $this->info(__('pwa.command_ready'));

        return self::SUCCESS;
    }
}
