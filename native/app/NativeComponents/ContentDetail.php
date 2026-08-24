<?php

namespace App\NativeComponents;

use App\Services\NativeApiClient;
use App\Services\ServerDiscoveryService;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Throwable;

final class ContentDetail extends NativeComponent
{
    /** @var array<string, mixed> */
    public array $content = [];

    public string $contentType = '';

    public string $error = '';

    public function mount(): void
    {
        $server = app(ServerDiscoveryService::class)->selected();

        if (! $server) {
            $this->replace('/server');

            return;
        }

        $this->contentType = (string) $this->param('contentType');
        $identifier = (string) ($this->param('slug') ?? $this->param('id'));
        $path = match ($this->contentType) {
            'post' => 'content/posts/'.rawurlencode($identifier),
            'event' => 'content/events/'.rawurlencode($identifier),
            'live_stream' => 'content/live-streams/'.rawurlencode($identifier),
            default => null,
        };

        if ($path === null || $identifier === '') {
            $this->error = __('native.errors.content_unavailable');

            return;
        }

        try {
            $this->content = app(NativeApiClient::class)->get($server, $path);
        } catch (Throwable $exception) {
            report($exception);
            $this->error = __('native.errors.content_unavailable');
        }
    }

    public function portal(): void
    {
        $this->replace('/portal');
    }

    public function render(): View
    {
        return view('native.content-detail');
    }
}
