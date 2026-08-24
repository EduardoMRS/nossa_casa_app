<?php

namespace App\Http\Controllers\Api;

use App\Data\PushMessage;
use App\Enums\NotificationCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PushGatewayRequest;
use App\Jobs\SendNativePush;
use App\Models\AppInstance;
use App\Models\DevicePushToken;
use Illuminate\Http\JsonResponse;

final class PushGatewayController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(PushGatewayRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $message = new PushMessage(
            $validated['title'],
            $validated['body'],
            NotificationCategory::from($validated['category']),
            $validated['deep_link'],
            $validated['data'] ?? [],
        );
        /** @var AppInstance $instance */
        $instance = $request->attributes->get('push_gateway_instance');
        $deviceIds = DevicePushToken::query()
            ->whereBelongsTo($instance, 'appInstance')
            ->whereIn('id', $validated['device_ids'])
            ->whereNull('invalidated_at')
            ->pluck('id');

        $deviceIds->each(fn (string $deviceId) => SendNativePush::dispatch($deviceId, $message));

        return response()->json(['data' => ['accepted' => $deviceIds->count()]], 202);
    }
}
