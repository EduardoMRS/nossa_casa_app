<?php

namespace App\Services;

use App\Models\ServerProfile;
use Native\Mobile\Facades\PushNotifications;

final readonly class NativePushRegistrationService
{
    public function __construct(
        private NativeApiClient $api,
        private NativeSessionService $sessions,
    ) {}

    public function synchronize(ServerProfile $server): void
    {
        if ($this->sessions->get($server) === null) {
            return;
        }

        $token = PushNotifications::getToken();

        if (is_string($token) && $token !== '') {
            $this->register($server, $token);

            return;
        }

        PushNotifications::enroll()
            ->id('nossa-casa-'.$server->instance_id)
            ->enroll();
    }

    public function registerGeneratedToken(string $token): void
    {
        $server = app(ServerDiscoveryService::class)->selected();

        if ($server && $token !== '') {
            $this->register($server, $token);
        }
    }

    public function unregister(ServerProfile $server): void
    {
        $session = $this->sessions->get($server);
        $deviceId = is_array($session) ? ($session['device_id'] ?? null) : null;

        if (is_string($deviceId) && $deviceId !== '') {
            $this->api->delete($server, 'push/devices/'.rawurlencode($deviceId));
        }
    }

    private function register(ServerProfile $server, string $token): void
    {
        $session = $this->sessions->get($server);
        $deviceId = is_array($session) ? ($session['device_id'] ?? null) : null;

        if (! is_string($deviceId) || $deviceId === '') {
            return;
        }

        $this->api->post($server, 'push/devices', [
            'device_id' => $deviceId,
            'transport' => getenv('NATIVEPHP_PLATFORM') === 'ios' ? 'apns' : 'fcm',
            'token' => $token,
            'app_version' => (string) config('nativephp.version'),
            'locale' => app()->getLocale(),
        ]);
    }
}
