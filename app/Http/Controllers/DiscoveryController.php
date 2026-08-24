<?php

namespace App\Http\Controllers;

use App\Services\InstanceIdentity;
use Illuminate\Http\JsonResponse;

class DiscoveryController extends Controller
{
    public function __construct(private InstanceIdentity $identity) {}

    public function __invoke(): JsonResponse
    {
        $baseUrl = rtrim((string) config('native.base_url'), '/');

        return response()->json([
            'protocol' => 'nossa-casa',
            'protocol_version' => (int) config('native.protocol_version'),
            'instance_id' => $this->identity->id(),
            'instance_name' => (string) config('native.instance_name'),
            'api_base_url' => $baseUrl.'/api',
            'web_base_url' => $baseUrl,
            'auth_driver' => 'sanctum',
            'capabilities' => config('native.capabilities'),
            'api_version' => (int) config('native.api_version'),
            'minimum_app_version' => (string) config('native.minimum_app_version'),
            'privacy_url' => config('native.privacy_url'),
            'terms_url' => config('native.terms_url'),
            'realtime' => config('native.realtime'),
        ])->header('Cache-Control', 'public, max-age=300');
    }
}
