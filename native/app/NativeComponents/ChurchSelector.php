<?php

namespace App\NativeComponents;

use App\Services\ChurchSelectionService;
use App\Services\NativeSessionService;
use App\Services\ServerDiscoveryService;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

final class ChurchSelector extends NativeComponent
{
    /** @var array<int, array<string, mixed>> */
    public array $memberships = [];

    public function mount(): void
    {
        $server = app(ServerDiscoveryService::class)->selected();

        if (! $server) {
            $this->replace('/server');

            return;
        }

        $session = app(NativeSessionService::class)->get($server);
        $this->memberships = is_array($session['user']['memberships'] ?? null)
            ? $session['user']['memberships']
            : [];
    }

    public function select(string $churchId): void
    {
        $server = app(ServerDiscoveryService::class)->selected();

        if ($server && collect($this->memberships)->contains('church_id', $churchId)) {
            app(ChurchSelectionService::class)->select($server, $churchId);
            $this->replace('/portal');
        }
    }

    public function render(): View
    {
        return view('native.church-selector');
    }
}
