<?php

namespace App\Http\Controllers\Api;

use App\Enums\NotificationCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpsertDevicePushTokenRequest;
use App\Models\DevicePushToken;
use App\Models\MobileSession;
use App\Services\InstanceIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class DevicePushTokenController extends Controller
{
    public function __construct(private readonly InstanceIdentity $instanceIdentity) {}

    public function store(UpsertDevicePushTokenRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $accessTokenId = $request->user()->currentAccessToken()?->getKey();
        $mobileSession = $accessTokenId
            ? MobileSession::query()->where('current_access_token_id', $accessTokenId)->first()
            : null;
        $categories = $validated['enabled_categories'] ?? collect(NotificationCategory::cases())
            ->map->value
            ->all();

        $device = DevicePushToken::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'device_id' => $validated['device_id'],
                'transport' => $validated['transport'],
            ],
            [
                'app_instance_id' => $this->instanceIdentity->id(),
                'mobile_session_id' => $mobileSession?->id,
                'token_hash' => hash('sha256', $validated['token']),
                'token' => $validated['token'],
                'app_version' => $validated['app_version'] ?? null,
                'locale' => $validated['locale'] ?? null,
                'enabled_categories' => $categories,
                'last_seen_at' => now(),
                'invalidated_at' => null,
            ],
        );

        return response()->json(['data' => ['id' => $device->id]], $device->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, string $deviceId): JsonResponse
    {
        abort_unless(Str::isUlid($deviceId), 404);

        $request->user()->devicePushTokens()->where('device_id', $deviceId)->delete();

        return response()->json(status: 204);
    }
}
