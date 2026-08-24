<?php

namespace App\NativeComponents;

use App\Services\ChurchSelectionService;
use App\Services\NativeApiClient;
use App\Services\NativePushRegistrationService;
use App\Services\NativeSessionService;
use App\Services\ServerDiscoveryService;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Throwable;

final class Login extends NativeComponent
{
    public string $email = '';

    public string $password = '';

    public string $twoFactorCode = '';

    public string $error = '';

    public bool $submitting = false;

    public function login(): void
    {
        $this->submitting = true;
        $this->error = '';

        try {
            $server = app(ServerDiscoveryService::class)->selected();

            if (! $server) {
                $this->replace('/server');

                return;
            }

            $sessions = app(NativeSessionService::class);
            $session = app(NativeApiClient::class)->login($server, array_filter([
                'email' => $this->email,
                'password' => $this->password,
                'two_factor_code' => $this->twoFactorCode,
                'device_id' => $sessions->deviceId($server),
                'device_name' => __('native.login.device_name'),
            ], fn (mixed $value): bool => $value !== ''));
            app(NativePushRegistrationService::class)->synchronize($server);
            $user = is_array($session['user'] ?? null) ? $session['user'] : [];
            $selected = app(ChurchSelectionService::class)->choose($server, $user);
            $memberships = collect($user['memberships'] ?? []);
            $this->replace($selected || $memberships->isEmpty() ? '/portal' : '/church-selector');
        } catch (Throwable $exception) {
            report($exception);
            $this->error = $exception instanceof ValidationException
                ? (string) collect($exception->errors())->flatten()->first()
                : __('native.errors.login_failed');
        } finally {
            $this->submitting = false;
        }
    }

    public function changeServer(): void
    {
        $this->replace('/server');
    }

    public function continuePublicly(): void
    {
        $this->replace('/portal');
    }

    public function render(): View
    {
        return view('native.login');
    }
}
