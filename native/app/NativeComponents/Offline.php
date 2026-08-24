<?php

namespace App\NativeComponents;

use App\Services\OfflineContentService;
use App\Services\ServerDiscoveryService;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Throwable;

final class Offline extends NativeComponent
{
    /** @var list<array<string, mixed>> */
    public array $caches = [];

    public string $bibleVersion = '';

    public string $status = '';

    public bool $syncing = false;

    public function mount(): void
    {
        $this->refreshSummary();
    }

    public function sync(): void
    {
        $server = app(ServerDiscoveryService::class)->selected();

        if (! $server) {
            $this->replace('/server');

            return;
        }

        $this->syncing = true;
        $results = app(OfflineContentService::class)->syncPublic($server);
        $this->status = collect($results)->every() ? __('native.offline.synced') : __('native.offline.partial');
        $this->syncing = false;
        $this->refreshSummary();
    }

    public function downloadBible(): void
    {
        $server = app(ServerDiscoveryService::class)->selected();

        if (! $server) {
            $this->replace('/server');

            return;
        }

        try {
            app(OfflineContentService::class)->downloadBible($server, $this->bibleVersion);
            $this->status = __('native.offline.bible_downloaded');
        } catch (Throwable $exception) {
            report($exception);
            $this->status = __('native.offline.bible_failed');
        }

        $this->refreshSummary();
    }

    public function portal(): void
    {
        $this->replace('/portal');
    }

    private function refreshSummary(): void
    {
        $server = app(ServerDiscoveryService::class)->selected();
        $this->caches = $server ? app(OfflineContentService::class)->summary($server) : [];
    }

    public function render(): View
    {
        return view('native.offline');
    }
}
