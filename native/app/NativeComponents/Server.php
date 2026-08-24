<?php

namespace App\NativeComponents;

use App\Services\NativeSessionService;
use App\Services\ServerDiscoveryService;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Native\Mobile\Attributes\OnNative;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Scanner\CodeScanned;
use Native\Mobile\Facades\Scanner;
use Throwable;

final class Server extends NativeComponent
{
    public string $server = '';

    public string $error = '';

    public bool $connecting = false;

    public function mount(): void
    {
        $this->server = (string) config('nossa_casa.default_server');
    }

    public function connect(): void
    {
        $this->connecting = true;
        $this->error = '';

        try {
            $profile = app(ServerDiscoveryService::class)->discover($this->server);
            $session = app(NativeSessionService::class)->get($profile);
            $this->replace($session ? '/portal' : '/login');
        } catch (Throwable $exception) {
            report($exception);
            $this->error = $exception instanceof ValidationException
                ? (string) collect($exception->errors())->flatten()->first()
                : __('native.errors.discovery_failed');
        } finally {
            $this->connecting = false;
        }
    }

    public function scan(): void
    {
        Scanner::scan()
            ->prompt(__('native.server.scan_prompt'))
            ->formats(['qr'])
            ->scan();
    }

    #[OnNative(CodeScanned::class)]
    public function scanned(CodeScanned $event): void
    {
        $this->server = $event->data;
        $this->connect();
    }

    public function render(): View
    {
        return view('native.server');
    }
}
